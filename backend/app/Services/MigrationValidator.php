<?php

namespace App\Services;

use App\Models\MigrationProject;
use Illuminate\Support\Facades\Log;

class MigrationValidator
{
    public function __construct(
        private DataNormalizer $normalizer,
    ) {}

    public function validateProject(MigrationProject $project): array
    {
        $results = [
            'schema_validation' => $this->validateSchema($project),
            'referential_integrity' => $this->validateReferentialIntegrity($project),
            'duplicate_detection' => $this->detectDuplicates($project),
            'business_rules' => $this->validateBusinessRules($project),
            'data_quality' => $this->assessDataQuality($project),
        ];

        $results['overall_valid'] = ! in_array(false, array_map(fn ($r) => $r['valid'] ?? true, $results), true);
        $results['error_count'] = array_sum(array_map(fn ($r) => $r['error_count'] ?? 0, $results));
        $results['warning_count'] = array_sum(array_map(fn ($r) => $r['warning_count'] ?? 0, $results));

        return $results;
    }

    public function validateSchema(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $issues = [];
        $warnings = [];

        foreach ($structure as $tableName => $table) {
            $columns = $table['columns'] ?? [];

            if (empty($columns)) {
                $issues[] = [
                    'table' => $tableName,
                    'message' => 'Table has no columns detected',
                    'severity' => 'error',
                ];
                continue;
            }

            $hasId = false;
            foreach ($columns as $column) {
                $colName = strtolower($column['name']);

                if (in_array($colName, ['id', 'cod_' . $tableName, 'codigo'])) {
                    $hasId = true;
                }

                if (empty($column['type'])) {
                    $warnings[] = [
                        'table' => $tableName,
                        'column' => $column['name'],
                        'message' => 'Column type could not be detected',
                        'severity' => 'warning',
                    ];
                }
            }

            if (! $hasId) {
                $warnings[] = [
                    'table' => $tableName,
                    'message' => 'No primary key column detected',
                    'severity' => 'warning',
                ];
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'error_count' => count($issues),
            'warning_count' => count($warnings),
        ];
    }

    public function validateReferentialIntegrity(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $issues = [];
        $warnings = [];

        $tableNames = array_keys($structure);

        foreach ($structure as $tableName => $table) {
            $columns = $table['columns'] ?? [];

            foreach ($columns as $column) {
                $colName = strtolower($column['name']);

                if (str_ends_with($colName, '_id') || str_starts_with($colName, 'cod_')) {
                    $referencedTable = $this->guessReferencedTable($colName, $tableNames);

                    if ($referencedTable && ! isset($structure[$referencedTable])) {
                        $warnings[] = [
                            'table' => $tableName,
                            'column' => $column['name'],
                            'message' => "Possible foreign key references non-existent table: {$referencedTable}",
                            'severity' => 'warning',
                        ];
                    }
                }
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'error_count' => count($issues),
            'warning_count' => count($warnings),
        ];
    }

    public function detectDuplicates(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $warnings = [];

        foreach ($structure as $tableName => $table) {
            $columns = $table['columns'] ?? [];
            $pkColumns = array_filter($columns, fn ($c) => $c['primary'] ?? false);

            if (empty($pkColumns)) {
                $warnings[] = [
                    'table' => $tableName,
                    'message' => 'No primary key defined - duplicate detection limited',
                    'severity' => 'warning',
                ];
            }
        }

        return [
            'valid' => true,
            'issues' => [],
            'warnings' => $warnings,
            'error_count' => 0,
            'warning_count' => count($warnings),
        ];
    }

    public function validateBusinessRules(MigrationProject $project): array
    {
        $rules = data_get($project->source_config, 'business_rules', []);
        $issues = [];
        $warnings = [];

        foreach ($rules as $rule) {
            if (! isset($rule['table'], $rule['field'], $rule['rule'])) {
                $warnings[] = [
                    'message' => 'Incomplete business rule: ' . json_encode($rule),
                    'severity' => 'warning',
                ];
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'error_count' => count($issues),
            'warning_count' => count($warnings),
        ];
    }

    public function assessDataQuality(MigrationProject $project): array
    {
        $structure = data_get($project->source_config, 'detected_structure.tables', []);
        $warnings = [];
        $score = 100;

        foreach ($structure as $tableName => $table) {
            $columnCount = count($table['columns'] ?? []);

            if ($columnCount === 0) {
                $score -= 20;
                $warnings[] = [
                    'table' => $tableName,
                    'message' => 'No columns detected - data quality cannot be assessed',
                    'severity' => 'warning',
                ];
            }
        }

        $tableCount = count($structure);
        if ($tableCount === 0) {
            $score = 0;
            $warnings[] = [
                'message' => 'No tables detected in source',
                'severity' => 'warning',
            ];
        }

        return [
            'valid' => $score >= 50,
            'quality_score' => max(0, $score),
            'issues' => [],
            'warnings' => $warnings,
            'error_count' => 0,
            'warning_count' => count($warnings),
        ];
    }

    private function guessReferencedTable(string $columnName, array $tableNames): ?string
    {
        $patterns = [
            '/^cod_(.+)$/',
            '/^(.+)_id$/',
            '/^(.+)_cod$/,
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $columnName, $matches)) {
                $guessed = $matches[1];

                foreach ($tableNames as $tableName) {
                    if (strtolower($tableName) === strtolower($guessed)) {
                        return $tableName;
                    }
                }
            }
        }

        return null;
    }
}
