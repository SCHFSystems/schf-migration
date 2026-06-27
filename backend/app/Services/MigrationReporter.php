<?php

namespace App\Services;

use App\Models\MigrationImport;
use App\Models\MigrationProject;
use App\Models\MigrationRecord;
use App\Models\MigrationReport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MigrationReporter
{
    public function generate(MigrationProject $project): MigrationReport
    {
        $imports = $project->imports()->get();
        $totalRecords = $imports->sum('records_total');
        $importedRecords = $imports->sum('records_imported');
        $skippedRecords = $imports->sum('records_skipped');
        $failedRecords = $imports->sum('records_failed');

        $duration = null;
        if ($project->started_at && $project->completed_at) {
            $duration = $project->started_at->diffInSeconds($project->completed_at);
        }

        $summary = $this->buildSummary($imports, $project);
        $totals = [
            'total_records' => $totalRecords,
            'success' => $importedRecords,
            'skipped' => $skippedRecords,
            'failed' => $failedRecords,
            'success_rate' => $totalRecords > 0
                ? round(($importedRecords / $totalRecords) * 100, 2)
                : 0,
        ];

        $backupPath = $this->getBackupPath($project);
        $packageHash = $this->calculatePackageHash($project);

        $report = MigrationReport::create([
            'migration_project_id' => $project->id,
            'summary' => $summary,
            'totals' => $totals,
            'duration_seconds' => $duration,
            'core_version' => $this->getCoreVersion(),
            'migration_version' => config('app.migration_version', '1.0.0'),
            'legacy_version' => data_get($project->source_config, 'legacy_version', 'unknown'),
            'package_hash' => $packageHash,
            'operator_id' => $project->created_by,
            'backup_path' => $backupPath,
            'status' => MigrationReport::STATUS_FINAL,
        ]);

        Log::info('Migration report generated', [
            'project_id' => $project->id,
            'report_id' => $report->id,
            'totals' => $totals,
        ]);

        return $report;
    }

    private function buildSummary($imports, MigrationProject $project): array
    {
        $tableSummaries = [];

        foreach ($imports as $import) {
            $tableSummaries[$import->table_name] = [
                'table' => $import->table_name,
                'total' => $import->records_total,
                'imported' => $import->records_imported,
                'skipped' => $import->records_skipped,
                'failed' => $import->records_failed,
                'duration' => $import->duration,
                'success_rate' => $import->success_rate,
            ];
        }

        $failedRecords = MigrationRecord::whereHas('import', function ($q) use ($project) {
            $q->where('migration_project_id', $project->id);
        }->where('status', MigrationRecord::STATUS_FAILED)->get();

        $errorSummary = [];
        foreach ($failedRecords as $record) {
            $errors = $record->validation_errors ?? [];
            foreach ($errors as $error) {
                $errorKey = is_string($error) ? $error : ($error['message'] ?? 'Unknown error');
                $errorSummary[$errorKey] = ($errorSummary[$errorKey] ?? 0) + 1;
            }
        }

        arsort($errorSummary);

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'source_type' => $project->source_type,
                'status' => $project->status,
                'started_at' => $project->started_at?->toIso8601String(),
                'completed_at' => $project->completed_at?->toIso8601String(),
            ],
            'tables' => $tableSummaries,
            'top_errors' => array_slice($errorSummary, 0, 10),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getBackupPath(MigrationProject $project): ?string
    {
        $backupBase = storage_path('app/migration_backups');

        $dirs = glob("{$backupBase}/{$project->id}_*");

        return ! empty($dirs) ? $dirs[0] : null;
    }

    private function calculatePackageHash(MigrationProject $project): string
    {
        $data = json_encode([
            'project_id' => $project->id,
            'source_type' => $project->source_type,
            'completed_at' => $project->completed_at?->toIso8601String(),
        ]);

        return hash('sha256', $data);
    }

    private function getCoreVersion(): string
    {
        return config('app.core_version', '1.0.0');
    }
}
