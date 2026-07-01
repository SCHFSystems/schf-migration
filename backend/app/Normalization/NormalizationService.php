<?php

declare(strict_types=1);

namespace App\Normalization;

use App\Services\InventoryService;
use SCHF\SDK\Connector\ConnectorInterface;
use SCHF\SDK\Normalization\NormalizationResult;
use SCHF\SDK\Normalization\NormalizedOrganization;
use SCHF\SDK\Normalization\NormalizedSupplier;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedCategory;
use SCHF\SDK\Normalization\QualityIssue;

class NormalizationService
{
    public function __construct(
        private MappingProfileRegistry $registry,
        private DataQualityService $qualityService,
        private InventoryService $inventoryService,
    ) {}

    /**
     * Run the full normalization pipeline for a given source.
     *
     * @param  ConnectorInterface $connector
     * @param  string             $sourceType    'firebird', 'mysql', etc.
     * @param  array              $organization  Base organization info.
     * @return NormalizationResult
     */
    public function normalize(
        ConnectorInterface $connector,
        string $sourceType,
        array $organization = [],
    ): NormalizationResult {
        $profiles = $this->registry->allForSource($sourceType);
        $issues   = [];
        $results  = [];

        foreach ($profiles as $profile) {
            $entityName = $profile->target_entity;

            // Fetch ALL rows from the source table
            try {
                $rows = $connector->fetchAll("SELECT * FROM \"{$profile->source_table}\"");
            } catch (\Throwable $e) {
                $issues[] = new QualityIssue(
                    type:        'missing_required',
                    severity:    'error',
                    entity:      $entityName,
                    external_id: '',
                    field:       'table',
                    message:     "Failed to read source table '{$profile->source_table}': {$e->getMessage()}",
                );
                continue;
            }

            $normalized = [];

            foreach ($rows as $row) {
                $mapped = $this->registry->apply($profile, $row);

                // Check required fields
                foreach ($profile->rules as $rule) {
                    if ($rule->required && empty($mapped[$rule->target_field])) {
                        $issues[] = new QualityIssue(
                            type:        'missing_required',
                            severity:    'error',
                            entity:      $entityName,
                            external_id: (string)($mapped['external_id'] ?? 'unknown'),
                            field:       $rule->target_field,
                            message:     "Required field '{$rule->target_field}' is missing (source column: {$rule->source_column})",
                        );
                    }
                }

                $normalized[] = $mapped;
            }

            $results[$entityName] = $normalized;
        }

        // Run data quality checks
        $qualityIssues = $this->qualityService->checkAll($results);
        $issues = array_merge($issues, $qualityIssues);

        $organizations = $results['organizations'] ?? [];
        if (empty($organizations) && $organization) {
            $organizations = [new NormalizedOrganization(
                external_id: $organization['external_id'] ?? 'default',
                name:        $organization['name'] ?? 'Default Organization',
                legal_name:  $organization['legal_name'] ?? null,
            )];
        }
        $results['organizations'] = $organizations;

        // Build summary
        $summary = $this->buildSummary($results, $issues);

        return new NormalizationResult(
            organizations: $organizations,
            users:       $results['users'] ?? [],
            suppliers:   $results['suppliers'] ?? [],
            accounts:    $results['accounts'] ?? [],
            payables:    $results['payables'] ?? [],
            categories:  $results['categories'] ?? [],
            expenses:    $results['expenses'] ?? [],
            issues:      $issues,
            summary:     $summary,
        );
    }

    /**
     * Generate a preview summary without returning all data.
     *
     * @param  ConnectorInterface $connector
     * @param  string             $sourceType
     * @return array
     */
    public function preview(
        ConnectorInterface $connector,
        string $sourceType,
    ): array {
        $profiles = $this->registry->allForSource($sourceType);
        $preview  = [];
        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($profiles as $profile) {
            try {
                $rows = $connector->fetchAll("SELECT FIRST 10 * FROM \"{$profile->source_table}\"");
                $count = $connector->fetchAll("SELECT COUNT(*) AS cnt FROM \"{$profile->source_table}\"");
                $rowCount = (int) ($count[0]['cnt'] ?? 0);
            } catch (\Throwable $e) {
                $preview[$profile->target_entity] = [
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ];
                $totalErrors++;
                continue;
            }

            $preview[$profile->target_entity] = [
                'status'       => 'ok',
                'source_table' => $profile->source_table,
                'total_rows'   => $rowCount,
                'sample'       => $rows,
            ];
        }

        return [
            'profiles'      => $preview,
            'total_profiles' => count($profiles),
            'summary'       => [
                'total_errors'   => $totalErrors,
                'total_warnings' => $totalWarnings,
            ],
        ];
    }

    private function buildSummary(array $results, array $issues): array
    {
        $errors   = array_filter($issues, fn($i) => $i->severity === 'error');
        $warnings = array_filter($issues, fn($i) => $i->severity === 'warning');

        return [
            'total_organizations' => count($results['organizations'] ?? []),
            'total_users'         => count($results['users'] ?? []),
            'total_suppliers'     => count($results['suppliers'] ?? []),
            'total_categories'    => count($results['categories'] ?? []),
            'total_accounts'      => count($results['accounts'] ?? []),
            'total_payables'      => count($results['payables'] ?? []),
            'total_expenses'      => count($results['expenses'] ?? []),
            'total_issues'        => count($issues),
            'total_errors'        => count($errors),
            'total_warnings'      => count($warnings),
        ];
    }
}
