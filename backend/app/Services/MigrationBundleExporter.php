<?php

namespace App\Services;

use App\Models\MigrationProject;
use Illuminate\Support\Facades\File;
use ZipArchive;

class MigrationBundleExporter
{
    private const BUNDLE_VERSION = '1.0.0';
    private const SDK_VERSION = '1.0.0';
    private const CORE_MIN_VERSION = '1.5.0';

    private const RECORD_FILES = [
        'users' => ['path' => 'users.json', 'schema' => 'schemas/records/users.schema.json'],
        'roles' => ['path' => 'roles.json', 'schema' => 'schemas/records/roles.schema.json'],
        'permissions' => ['path' => 'permissions.json', 'schema' => 'schemas/records/permissions.schema.json'],
        'suppliers' => ['path' => 'suppliers.json', 'schema' => 'schemas/records/suppliers.schema.json'],
        'accounts' => ['path' => 'accounts.json', 'schema' => 'schemas/records/accounts.schema.json'],
        'banks' => ['path' => 'banks.json', 'schema' => 'schemas/records/banks.schema.json'],
        'categories' => ['path' => 'categories.json', 'schema' => 'schemas/records/categories.schema.json'],
        'payments' => ['path' => 'payments.json', 'schema' => 'schemas/records/payments.schema.json'],
        'expenses' => ['path' => 'expenses.json', 'schema' => 'schemas/records/expenses.schema.json'],
    ];

    public function preview(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $normalizedBundle = data_get($project->source_config, 'normalized_bundle', []);

        return [
            'success' => true,
            'bundle_version' => self::BUNDLE_VERSION,
            'sdk_version' => self::SDK_VERSION,
            'core_min_version' => self::CORE_MIN_VERSION,
            'source' => [
                'type' => $project->source_type,
                'tables' => count($structure),
                'inventory_hash' => $this->inventoryHash($project),
            ],
            'files' => $this->previewFiles($normalizedBundle),
            'warnings' => $this->buildPreviewWarnings($structure, $normalizedBundle),
        ];
    }

    public function export(MigrationProject $project): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [
                'success' => false,
                'error' => 'PHP ZipArchive extension is required to export Migration Bundles.',
            ];
        }

        $timestamp = now()->format('Ymd_His');
        $baseDir = storage_path("app/migration_bundles/project_{$project->id}_{$timestamp}");
        $bundleDir = "{$baseDir}/bundle";

        File::ensureDirectoryExists($bundleDir);
        File::ensureDirectoryExists("{$bundleDir}/attachments");
        File::ensureDirectoryExists("{$bundleDir}/logs");

        $recordFiles = $this->writeRecordFiles($project, $bundleDir);
        $reportPath = $this->writeReport($project, $bundleDir, $recordFiles);

        $manifestFiles = $this->buildManifestFiles($bundleDir, $recordFiles, $reportPath);
        $manifest = $this->buildManifest($project, $manifestFiles);
        $this->writeJson("{$bundleDir}/manifest.json", $manifest);

        $this->writeChecksums($bundleDir);

        $zipPath = "{$baseDir}/migration-package.zip";
        $this->zipDirectory($bundleDir, $zipPath);

        $packageHash = hash_file('sha256', $zipPath);

        $sourceConfig = $project->source_config ?? [];
        $sourceConfig['latest_bundle'] = [
            'zip_path' => $zipPath,
            'directory' => $bundleDir,
            'sha256' => $packageHash,
            'generated_at' => now()->toIso8601String(),
        ];
        $project->update(['source_config' => $sourceConfig]);

        return [
            'success' => true,
            'bundle_path' => $zipPath,
            'bundle_sha256' => $packageHash,
            'download_url' => "/api/projects/{$project->id}/bundle/download",
            'manifest' => $manifest,
        ];
    }

    public function latestBundlePath(MigrationProject $project): ?string
    {
        $path = data_get($project->source_config, 'latest_bundle.zip_path');

        return $path && File::exists($path) ? $path : null;
    }

    private function previewFiles(array $normalizedBundle): array
    {
        $files = [[
            'path' => 'organization.json',
            'schema' => 'schemas/records/organization.schema.json',
            'required' => true,
            'records' => 1,
        ]];

        foreach (self::RECORD_FILES as $key => $definition) {
            $files[] = [
                'path' => $definition['path'],
                'schema' => $definition['schema'],
                'required' => true,
                'records' => count($normalizedBundle[$key] ?? []),
            ];
        }

        $files[] = [
            'path' => 'report.json',
            'schema' => 'schemas/bundle/report.schema.json',
            'required' => true,
            'records' => 1,
        ];

        return $files;
    }

    private function buildPreviewWarnings(array $structure, array $normalizedBundle): array
    {
        $warnings = [];

        if (empty($structure)) {
            $warnings[] = 'No source inventory was detected yet. Run preparation before exporting.';
        }

        $normalizedCount = array_sum(array_map(fn ($records) => is_array($records) ? count($records) : 0, $normalizedBundle));
        if ($normalizedCount === 0) {
            $warnings[] = 'No normalized records found. Bundle will contain structure and metadata only.';
        }

        return $warnings;
    }

    private function writeRecordFiles(MigrationProject $project, string $bundleDir): array
    {
        $normalizedBundle = data_get($project->source_config, 'normalized_bundle', []);

        $organization = data_get($project->source_config, 'target_organization', [
            'external_id' => "project-{$project->id}",
            'name' => $project->name,
            'legal_name' => null,
            'metadata' => [],
        ]);

        $files = [
            'organization.json' => $organization,
        ];

        foreach (self::RECORD_FILES as $key => $definition) {
            $files[$definition['path']] = array_values($normalizedBundle[$key] ?? []);
        }

        foreach ($files as $path => $payload) {
            $this->writeJson("{$bundleDir}/{$path}", $payload);
        }

        return array_keys($files);
    }

    private function writeReport(MigrationProject $project, string $bundleDir, array $recordFiles): string
    {
        $summary = [];
        foreach ($recordFiles as $file) {
            $payload = json_decode(File::get("{$bundleDir}/{$file}"), true);
            $summary[$file] = is_array($payload) && array_is_list($payload) ? count($payload) : 1;
        }

        $report = [
            'status' => 'ready_with_warnings',
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'warnings' => $this->buildPreviewWarnings(
                data_get($project->source_config, 'detected_structure.tables', []),
                data_get($project->source_config, 'normalized_bundle', [])
            ),
            'errors' => [],
        ];

        $path = 'report.json';
        $this->writeJson("{$bundleDir}/{$path}", $report);

        return $path;
    }

    private function buildManifestFiles(string $bundleDir, array $recordFiles, string $reportPath): array
    {
        $files = [];
        $schemaMap = ['organization.json' => 'schemas/records/organization.schema.json'];

        foreach (self::RECORD_FILES as $definition) {
            $schemaMap[$definition['path']] = $definition['schema'];
        }
        $schemaMap[$reportPath] = 'schemas/bundle/report.schema.json';

        foreach ([...$recordFiles, $reportPath] as $path) {
            $payload = json_decode(File::get("{$bundleDir}/{$path}"), true);
            $files[] = [
                'path' => $path,
                'schema' => $schemaMap[$path],
                'required' => true,
                'records' => is_array($payload) && array_is_list($payload) ? count($payload) : 1,
                'sha256' => strtoupper(hash_file('sha256', "{$bundleDir}/{$path}")),
            ];
        }

        return $files;
    }

    private function buildManifest(MigrationProject $project, array $files): array
    {
        return [
            'bundle_version' => self::BUNDLE_VERSION,
            'sdk_version' => self::SDK_VERSION,
            'core_min_version' => self::CORE_MIN_VERSION,
            'core_max_version' => null,
            'generated_at' => now()->toIso8601String(),
            'generator' => [
                'name' => 'schf-migration',
                'version' => config('app.migration_version', '1.0.0'),
                'plugin' => data_get($project->source_config, 'plugin.name'),
            ],
            'organization' => [
                'external_id' => data_get($project->source_config, 'target_organization.external_id', "project-{$project->id}"),
                'name' => data_get($project->source_config, 'target_organization.name', $project->name),
            ],
            'source' => [
                'type' => $project->source_type,
                'product' => data_get($project->source_config, 'legacy_product'),
                'version' => data_get($project->source_config, 'legacy_version'),
                'inventory_hash' => $this->inventoryHash($project),
            ],
            'files' => $files,
        ];
    }

    private function writeChecksums(string $bundleDir): void
    {
        $lines = [];
        foreach (File::allFiles($bundleDir) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($relative === 'checksum.sha256') {
                continue;
            }

            $lines[] = strtoupper(hash_file('sha256', $file->getPathname())) . "  {$relative}";
        }

        sort($lines);
        File::put("{$bundleDir}/checksum.sha256", implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function zipDirectory(string $directory, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create bundle zip: {$zipPath}");
        }

        foreach (File::allFiles($directory) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();
    }

    private function writeJson(string $path, array $payload): void
    {
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private function inventoryHash(MigrationProject $project): string
    {
        return strtoupper(hash('sha256', json_encode(
            data_get($project->source_config, 'detected_structure', []),
            JSON_UNESCAPED_SLASHES
        )));
    }
}
