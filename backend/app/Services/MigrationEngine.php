<?php

namespace App\Services;

use App\Models\MigrationImport;
use App\Models\MigrationProject;
use App\Models\MigrationRecord;
use App\Models\MigrationReport;
use App\Services\ConnectorFactory;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrationEngine
{
    public function __construct(
        private DataNormalizer $normalizer,
        private MigrationValidator $validator,
        private MigrationRollback $rollback,
        private MigrationReporter $reporter,
        private CoreApiClient $coreClient,
        private ConnectorFactory $connectorFactory,
        private InventoryService $inventoryService,
    ) {}

    public function prepare(MigrationProject $project): array
    {
        try {
            $project->update(['status' => MigrationProject::STATUS_PREPARING]);

            $detector = $this->getDetector($project->source_type);
            $structure = $detector->detectStructure($project->source_config);

            if (isset($structure['error'])) {
                $project->update([
                    'status' => MigrationProject::STATUS_FAILED,
                    'error_message' => $structure['error'],
                ]);

                return ['success' => false, 'error' => $structure['error']];
            }

            // Generate inventory using the new connector architecture
            $inventory = null;
            try {
                $connector = $this->connectorFactory->make(
                    $project->source_type,
                    $project->source_config,
                );
                $inventory = $this->inventoryService->generate($connector);
                $connector->disconnect();
            } catch (\Throwable $e) {
                Log::warning('Inventory generation failed (non-fatal)', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $project->update([
                'source_config' => array_merge($project->source_config ?? [], [
                    'detected_structure' => $structure,
                    'inventory' => $inventory,
                ]),
                'status' => MigrationProject::STATUS_VALIDATING,
            ]);

            return [
                'success' => true,
                'structure' => $structure,
                'inventory' => $inventory,
                'message' => 'Source structure detected and inventory generated',
            ];
        } catch (\Exception $e) {
            Log::error('Migration prepare failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $project->update([
                'status' => MigrationProject::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validate(MigrationProject $project): array
    {
        try {
            $project->update(['status' => MigrationProject::STATUS_VALIDATING]);

            $results = $this->validator->validateProject($project);

            $project->update(['status' => MigrationProject::STATUS_PREVIEWING]);

            return [
                'success' => true,
                'validation_results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Migration validation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $project->update([
                'status' => MigrationProject::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function preview(MigrationProject $project, ?string $tableName = null): array
    {
        try {
            $detector = $this->getDetector($project->source_type);
            $structure = data_get($project->source_config, 'detected_structure.tables', []);

            $previews = [];
            $tablesToPreview = $tableName
                ? array_filter($structure, fn ($t) => $t['name'] === $tableName)
                : $structure;

            foreach ($tablesToPreview as $table) {
                $sampleData = $detector->getSampleData(
                    $project->source_config,
                    $table['name'],
                    20
                );

                $normalizedData = $this->normalizer->normalizePreview(
                    $sampleData['data'] ?? [],
                    $table['name'],
                    $project
                );

                $previews[$table['name']] = [
                    'original' => $sampleData['data'] ?? [],
                    'normalized' => $normalizedData,
                    'column_mapping' => $this->normalizer->getColumnMapping($table['name'], $project),
                ];
            }

            return [
                'success' => true,
                'previews' => $previews,
            ];
        } catch (\Exception $e) {
            Log::error('Migration preview failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function import(MigrationProject $project): array
    {
        try {
            DB::beginTransaction();

            $project->update([
                'status' => MigrationProject::STATUS_MIGRATING,
                'started_at' => now(),
            ]);

            $this->rollback->createBackup($project);

            $detector = $this->getDetector($project->source_type);
            $structure = data_get($project->source_config, 'detected_structure.tables', []);

            $batchNumber = 0;
            $totalImported = 0;
            $totalFailed = 0;

            foreach ($structure as $table) {
                $batchNumber++;
                $batch = $this->processTableImport(
                    $project,
                    $detector,
                    $table,
                    $batchNumber
                );

                $totalImported += $batch->records_imported;
                $totalFailed += $batch->records_failed;
            }

            DB::commit();

            $report = $this->reporter->generate($project);

            $project->update([
                'status' => MigrationProject::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return [
                'success' => true,
                'imported' => $totalImported,
                'failed' => $totalFailed,
                'report_id' => $report->id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Migration import failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $project->update([
                'status' => MigrationProject::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function rollback(MigrationProject $project): array
    {
        try {
            $result = $this->rollback->execute($project);

            $project->update([
                'status' => MigrationProject::STATUS_ROLLED_BACK,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Migration rollback failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function generateReport(MigrationProject $project): MigrationReport
    {
        return $this->reporter->generate($project);
    }

    private function processTableImport(
        MigrationProject $project,
        $detector,
        array $table,
        int $batchNumber
    ): MigrationImport {
        $import = MigrationImport::create([
            'migration_project_id' => $project->id,
            'batch_number' => $batchNumber,
            'table_name' => $table['name'],
            'records_total' => $detector->countRecords($project->source_config, $table['name']),
            'records_imported' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
            'status' => MigrationImport::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $offset = 0;
            $batchSize = 100;
            $imported = 0;
            $skipped = 0;
            $failed = 0;

            while (true) {
                $sampleData = $detector->getSampleData(
                    $project->source_config,
                    $table['name'],
                    $batchSize
                );

                $rows = $sampleData['data'] ?? [];

                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    $result = $this->normalizer->importRecord(
                        $row,
                        $table['name'],
                        $project
                    );

                    if ($result['status'] === 'imported') {
                        $imported++;
                    } elseif ($result['status'] === 'skipped') {
                        $skipped++;
                    } else {
                        $failed++;
                    }
                }

                $offset += $batchSize;

                if (count($rows) < $batchSize) {
                    break;
                }
            }

            $import->update([
                'records_imported' => $imported,
                'records_skipped' => $skipped,
                'records_failed' => $failed,
                'status' => MigrationImport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $import->update([
                'status' => MigrationImport::STATUS_FAILED,
                'error_log' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);

            throw $e;
        }

        return $import;
    }

    private function getDetector(string $sourceType)
    {
        return match ($sourceType) {
            'firebird' => app(\App\Services\SourceDetectors\FirebirdDetector::class),
            'mysql' => app(\App\Services\SourceDetectors\MysqlDetector::class),
            'zip' => app(\App\Services\SourceDetectors\ZipDetector::class),
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };
    }
}
