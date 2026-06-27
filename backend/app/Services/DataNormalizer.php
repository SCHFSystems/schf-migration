<?php

namespace App\Services;

use App\Models\MigrationProject;
use Illuminate\Support\Facades\Log;

class DataNormalizer
{
    private array $columnMappings = [];

    private array $transformationRules = [];

    public function __construct(
        private AiNormalizer $aiNormalizer,
    ) {}

    public function normalizePreview(array $data, string $tableName, MigrationProject $project): array
    {
        $mapping = $this->getColumnMapping($tableName, $project);
        $normalized = [];

        foreach ($data as $row) {
            $normalized[] = $this->normalizeRow($row, $mapping, $tableName, $project);
        }

        return $normalized;
    }

    public function importRecord(array $row, string $tableName, MigrationProject $project): array
    {
        try {
            $mapping = $this->getColumnMapping($tableName, $project);
            $normalized = $this->normalizeRow($row, $mapping, $tableName, $project);

            $validationResult = $this->validateRecord($normalized, $tableName, $project);

            if (! empty($validationResult['errors'])) {
                return [
                    'status' => 'failed',
                    'errors' => $validationResult['errors'],
                    'original' => $row,
                    'normalized' => $normalized,
                ];
            }

            if ($this->shouldSkip($normalized, $tableName, $project)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Record matches skip criteria',
                    'original' => $row,
                    'normalized' => $normalized,
                ];
            }

            return [
                'status' => 'imported',
                'original' => $row,
                'normalized' => $normalized,
            ];
        } catch (\Exception $e) {
            Log::error('Record normalization failed', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'errors' => [$e->getMessage()],
                'original' => $row,
            ];
        }
    }

    public function getColumnMapping(string $tableName, MigrationProject $project): array
    {
        if (isset($this->columnMappings[$tableName])) {
            return $this->columnMappings[$tableName];
        }

        $aiConfig = data_get($project->source_config, "ai_mapping.{$tableName}");

        if ($aiConfig) {
            $this->columnMappings[$tableName] = $aiConfig;

            return $aiConfig;
        }

        $structure = data_get($project->source_config, "detected_structure.tables.{$tableName}.columns", []);
        $mapping = [];

        foreach ($structure as $column) {
            $sourceCol = $column['name'];
            $mapping[$sourceCol] = [
                'source' => $sourceCol,
                'target' => $this->guessTargetColumn($sourceCol),
                'type' => $this->mapType($column['type']),
                'transform' => null,
                'required' => ! ($column['nullable'] ?? true),
            ];
        }

        $this->columnMappings[$tableName] = $mapping;

        return $mapping;
    }

    public function setColumnMapping(string $tableName, array $mapping, MigrationProject $project): void
    {
        $this->columnMappings[$tableName] = $mapping;

        $sourceConfig = $project->source_config ?? [];
        $sourceConfig['ai_mapping'][$tableName] = $mapping;
        $project->update(['source_config' => $sourceConfig]);
    }

    private function normalizeRow(array $row, array $mapping, string $tableName, MigrationProject $project): array
    {
        $normalized = [];

        foreach ($mapping as $sourceCol => $config) {
            $value = $row[$sourceCol] ?? null;

            if ($value === null && $config['required'] ?? false) {
                $value = $config['default'] ?? null;
            }

            if ($config['transform'] && $value !== null) {
                $value = $this->applyTransform($value, $config['transform']);
            }

            $value = $this->castValue($value, $config['type'] ?? 'string');

            $targetCol = $config['target'] ?? $sourceCol;
            $normalized[$targetCol] = $value;
        }

        return $normalized;
    }

    private function applyTransform($value, string $transform)
    {
        return match ($transform) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'date_format' => $this->formatDate($value),
            'number_format' => (float) $value,
            default => $value,
        };
    }

    private function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date', 'datetime' => $this->formatDate($value),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    private function formatDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            $date = new \DateTime($value);

            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    private function mapType(string $sourceType): string
    {
        return match (true) {
            str_contains($sourceType, 'int') => 'integer',
            str_contains($sourceType, 'float') || str_contains($sourceType, 'double') || str_contains($sourceType, 'numeric') => 'float',
            str_contains($sourceType, 'bool') => 'boolean',
            str_contains($sourceType, 'date') && ! str_contains($sourceType, 'time') => 'date',
            str_contains($sourceType, 'timestamp') || str_contains($sourceType, 'datetime') => 'datetime',
            str_contains($sourceType, 'text') || str_contains($sourceType, 'blob') => 'string',
            default => 'string',
        };
    }

    private function guessTargetColumn(string $sourceColumn): string
    {
        $lower = strtolower($sourceColumn);

        $mapping = [
            'cod_' => 'id_',
            'descricao' => 'description',
            'nome' => 'name',
            'data_' => 'date_',
            'vl_' => 'value_',
            'qtde' => 'quantity',
            'situacao' => 'status',
            'ativo' => 'active',
            'criado_em' => 'created_at',
            'atualizado_em' => 'updated_at',
        ];

        foreach ($mapping as $search => $replace) {
            if (str_starts_with($lower, $search)) {
                return substr($sourceColumn, strlen($search)) . $replace;
            }
        }

        return $sourceColumn;
    }

    private function validateRecord(array $record, string $tableName, MigrationProject $project): array
    {
        $errors = [];

        $rules = data_get($project->source_config, "validation_rules.{$tableName}", []);

        foreach ($rules as $field => $rule) {
            if (isset($rule['required']) && $rule['required'] && empty($record[$field])) {
                $errors[$field] = "Field {$field} is required";
            }

            if (isset($rule['max_length']) && strlen($record[$field] ?? '') > $rule['max_length']) {
                $errors[$field] = "Field {$field} exceeds maximum length of {$rule['max_length']}";
            }
        }

        return ['errors' => $errors, 'valid' => empty($errors)];
    }

    private function shouldSkip(array $record, string $tableName, MigrationProject $project): bool
    {
        $skipRules = data_get($project->source_config, "skip_rules.{$tableName}", []);

        foreach ($skipRules as $field => $value) {
            if (isset($record[$field]) && $record[$field] == $value) {
                return true;
            }
        }

        return false;
    }
}
