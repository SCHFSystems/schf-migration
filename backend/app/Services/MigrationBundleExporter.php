<?php

namespace App\Services;

use App\Models\MigrationProject;
use Illuminate\Support\Facades\File;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Contract;
use SCHF\SDK\Bundle\Doctor;
use SCHF\SDK\Bundle\History;
use SCHF\SDK\Bundle\Signer;
use SCHF\SDK\Bundle\Validator as BundleValidator;

class MigrationBundleExporter
{
    private const ENTITY_RECORD_FILES = [
        'users' => 'users.json',
        'roles' => 'roles.json',
        'permissions' => 'permissions.json',
        'suppliers' => 'suppliers.json',
        'accounts' => 'accounts.json',
        'banks' => 'banks.json',
        'categories' => 'categories.json',
        'payables' => 'payments.json',
        'expenses' => 'expenses.json',
    ];

    public function preview(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $entities = $this->normalizedEntities($project);
        $preview = $this->makeBuilder($project)->buildPreview();

        return [
            'success' => true,
            'bundle_version' => $preview['bundle_version'],
            'sdk_version' => $preview['sdk_version'],
            'core_min_version' => $preview['core_min_version'],
            'source' => [
                ...$preview['source'],
                'tables' => count($structure),
                'inventory_hash' => $this->inventoryHash($project),
            ],
            'files' => $this->previewFiles($preview['files']),
            'total_records' => $preview['total_records'],
            'warnings' => array_values(array_unique([
                ...$preview['warnings'],
                ...$this->buildPreviewWarnings($structure, $entities),
            ])),
        ];
    }

    public function export(MigrationProject $project): array
    {
        $timestamp = now()->format('Ymd_His');
        $baseDir = storage_path("app/migration_bundles/project_{$project->id}_{$timestamp}");
        $bundlePath = "{$baseDir}/migration-package." . Contract::EXTENSION;

        try {
            File::ensureDirectoryExists($baseDir);

            $temporaryPath = $this->makeBuilder($project)->build();
            File::move($temporaryPath, $bundlePath);

            $signature = $this->signIfConfigured($bundlePath);
            if (($signature['configured'] ?? false) && ! ($signature['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $signature['error'] ?? 'Bundle signing failed.',
                    'signature' => $signature,
                ];
            }

            $validator = new BundleValidator();
            $validation = $validator->validate($bundlePath);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'SDK bundle validation failed.',
                    'validation' => $validation,
                ];
            }

            $manifest = $validator->getManifest();
            $doctor = (new Doctor())->diagnose($bundlePath, true);
            $history = (new History(storage_path('app/migration_bundles/history')))->record($bundlePath, $manifest);
            $packageHash = strtoupper(hash_file('sha256', $bundlePath));

            $sourceConfig = $project->source_config ?? [];
            $sourceConfig['latest_bundle'] = [
                'bundle_path' => $bundlePath,
                'filename' => basename($bundlePath),
                'sha256' => $packageHash,
                'generated_at' => now()->toIso8601String(),
                'manifest' => $manifest?->toArray(),
                'doctor' => $doctor,
                'history' => $history,
                'signature' => $signature,
            ];
            $project->update(['source_config' => $sourceConfig]);

            return [
                'success' => true,
                'bundle_path' => $bundlePath,
                'bundle_sha256' => $packageHash,
                'download_url' => "/api/projects/{$project->id}/bundle/download",
                'manifest' => $manifest?->toArray(),
                'doctor' => $doctor,
                'history' => $history,
                'signature' => $signature,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function latestBundlePath(MigrationProject $project): ?string
    {
        $path = data_get($project->source_config, 'latest_bundle.bundle_path');

        return $path && File::exists($path) ? $path : null;
    }

    public function latestBundleFilename(MigrationProject $project): string
    {
        return data_get($project->source_config, 'latest_bundle.filename', 'migration-package.' . Contract::EXTENSION);
    }

    private function previewFiles(array $sdkFiles): array
    {
        return [
            ...$sdkFiles,
            [
                'path' => 'report.json',
                'schema' => 'schemas/bundle/report.schema.json',
                'required' => true,
                'records' => 1,
            ],
            [
                'path' => 'checksum.sha256',
                'schema' => null,
                'required' => true,
                'records' => 1,
            ],
        ];
    }

    private function makeBuilder(MigrationProject $project): Builder
    {
        $organization = $this->organization($project);
        $builder = new Builder();
        $builder
            ->setGenerator('schf-migration', config('app.migration_version', '1.0.0'), data_get($project->source_config, 'plugin.name'))
            ->setOrganization(
                (string) ($organization['external_id'] ?? "project-{$project->id}"),
                (string) ($organization['name'] ?? $project->name),
                array_diff_key($organization, array_flip(['external_id', 'name']))
            )
            ->setSource(
                $project->source_type,
                data_get($project->source_config, 'legacy_product'),
                data_get($project->source_config, 'legacy_version'),
                $this->inventoryHash($project)
            );

        foreach ($this->recordPayloads($project) as $file => $records) {
            $builder->addRecords($file, $records);
        }

        return $builder;
    }

    private function recordPayloads(MigrationProject $project): array
    {
        $entities = $this->normalizedEntities($project);
        $payloads = [];

        foreach (Contract::RECORD_FILES as $file => $schema) {
            $payloads[$file] = [];
        }

        foreach (self::ENTITY_RECORD_FILES as $entity => $file) {
            $records = array_values($entities[$entity] ?? []);
            $payloads[$file] = match ($file) {
                'suppliers.json' => array_map(fn (array $record): array => $this->supplierRecord($record), $records),
                'accounts.json' => array_map(fn (array $record): array => $this->accountRecord($record), $records),
                'payments.json' => array_map(fn (array $record): array => $this->paymentRecord($record), $records),
                default => $records,
            };
        }

        return $payloads;
    }

    private function normalizedEntities(MigrationProject $project): array
    {
        $entities = data_get($project->source_config, 'normalized_bundle.entities', []);

        return is_array($entities) ? $entities : [];
    }

    private function organization(MigrationProject $project): array
    {
        $organization = data_get($project->source_config, 'normalized_bundle.entities.organizations.0')
            ?? data_get($project->source_config, 'organization')
            ?? data_get($project->source_config, 'target_organization')
            ?? [];

        if (! is_array($organization)) {
            $organization = [];
        }

        return array_merge([
            'external_id' => "project-{$project->id}",
            'name' => $project->name,
        ], $organization);
    }

    private function supplierRecord(array $record): array
    {
        if (array_key_exists('active', $record)) {
            $record['active'] = $this->toBoolean($record['active']);
        }

        return $record;
    }

    private function accountRecord(array $record): array
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $metadata['agency'] = $metadata['agency'] ?? ($record['branch'] ?? null);
        $metadata['account_number'] = $metadata['account_number'] ?? ($record['account_number'] ?? null);

        $record['metadata'] = $metadata;
        $record['type'] = $record['type'] ?? 'checking';
        $record['bank_external_id'] = $record['bank_external_id'] ?? null;

        return $record;
    }

    private function paymentRecord(array $record): array
    {
        $record['direction'] = $record['direction'] ?? 'payable';
        $record['status'] = $record['status'] ?? (empty($record['paid_at']) ? 'pending' : 'paid');

        return $record;
    }

    private function buildPreviewWarnings(array $structure, array $entities): array
    {
        $warnings = [];

        if (empty($structure)) {
            $warnings[] = 'No source inventory was detected yet. Run preparation before exporting.';
        }

        $normalizedCount = array_sum(array_map(fn ($records) => is_array($records) ? count($records) : 0, $entities));
        if ($normalizedCount === 0) {
            $warnings[] = 'No normalized records found. Bundle will contain structure and metadata only.';
        }

        return $warnings;
    }

    private function signIfConfigured(string $bundlePath): array
    {
        $privateKeyPath = env('MIGRATION_BUNDLE_PRIVATE_KEY');
        if (! $privateKeyPath) {
            return [
                'configured' => false,
                'signed' => false,
                'message' => 'No signing key configured.',
            ];
        }

        $result = (new Signer())->sign(
            $bundlePath,
            $privateKeyPath,
            dirname($bundlePath) . DIRECTORY_SEPARATOR . 'signing-key.pub'
        );

        return ['configured' => true, 'signed' => $result['success'] ?? false, ...$result];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtoupper($value), ['1', 'S', 'Y', 'YES', 'TRUE', 'ACTIVE'], true);
        }

        return (bool) $value;
    }

    private function inventoryHash(MigrationProject $project): string
    {
        return strtoupper(hash('sha256', json_encode(
            data_get($project->source_config, 'detected_structure', []),
            JSON_UNESCAPED_SLASHES
        )));
    }
}
