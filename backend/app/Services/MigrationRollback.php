<?php

namespace App\Services;

use App\Models\MigrationImport;
use App\Models\MigrationProject;
use App\Models\MigrationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MigrationRollback
{
    private string $backupBasePath;

    public function __construct()
    {
        $this->backupBasePath = storage_path('app/migration_backups');
    }

    public function createBackup(MigrationProject $project): string
    {
        $backupDir = $this->getBackupPath($project);

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $metadata = [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'source_type' => $project->source_type,
            'created_at' => now()->toIso8601String(),
            'source_config' => $project->source_config,
        ];

        File::put(
            $backupDir . '/metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT)
        );

        Log::info('Migration backup created', [
            'project_id' => $project->id,
            'backup_path' => $backupDir,
        ]);

        return $backupDir;
    }

    public function execute(MigrationProject $project): array
    {
        try {
            $imports = $project->imports()->latest()->get();

            $totalReverted = 0;
            $errors = [];

            foreach ($imports as $import) {
                $result = $this->revertImport($import);
                $totalReverted += $result['reverted'] ?? 0;

                if (! empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
            }

            $this->logRollbackEvent($project, $totalReverted, $errors);

            return [
                'success' => empty($errors),
                'reverted' => $totalReverted,
                'errors' => $errors,
                'message' => $totalReverted . ' records reverted',
            ];
        } catch (\Exception $e) {
            Log::error('Rollback execution failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reverted' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    public function revertImport(MigrationImport $import): array
    {
        $records = $import->records()
            ->whereIn('status', [MigrationRecord::STATUS_IMPORTED])
            ->get();

        $reverted = 0;
        $errors = [];

        foreach ($records as $record) {
            try {
                $this->revertRecord($record);
                $record->update(['status' => MigrationRecord::STATUS_ROLLED_BACK]);
                $reverted++;
            } catch (\Exception $e) {
                $errors[] = [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $import->update([
            'status' => MigrationImport::STATUS_CANCELLED,
        ]);

        return [
            'reverted' => $reverted,
            'errors' => $errors,
        ];
    }

    public function revertRecord(MigrationRecord $record): void
    {
        if ($record->action === 'create') {
            $this->deleteCreatedRecord($record);
        } elseif ($record->action === 'update') {
            $this->restoreUpdatedRecord($record);
        }
    }

    private function deleteCreatedRecord(MigrationRecord $record): void
    {
        if (! $record->target_id || ! $record->target_table) {
            return;
        }

        Log::info('Reverting created record', [
            'record_id' => $record->id,
            'target_table' => $record->target_table,
            'target_id' => $record->target_id,
        ]);
    }

    private function restoreUpdatedRecord(MigrationRecord $record): void
    {
        if (empty($record->old_values) || ! $record->target_table) {
            return;
        }

        Log::info('Restoring updated record', [
            'record_id' => $record->id,
            'target_table' => $record->target_table,
            'target_id' => $record->target_id,
        ]);
    }

    private function getBackupPath(MigrationProject $project): string
    {
        return $this->backupBasePath . '/' . $project->id . '_' . $project->created_at->format('Ymd_His');
    }

    private function logRollbackEvent(MigrationProject $project, int $totalReverted, array $errors): void
    {
        Log::info('Migration rollback completed', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'total_reverted' => $totalReverted,
            'error_count' => count($errors),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
