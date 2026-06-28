<?php

namespace App\Services;

use SCHF\SDK\Normalization\NormalizationResult;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedExpense;
use SCHF\SDK\Normalization\QualityIssue;

class MigrationPreviewService
{
    public function generate(int $projectId, array $sourceConfig, NormalizationResult $result): array
    {
        $entities = $this->buildEntities($result);
        $summary = $this->buildSummary($entities);
        $warnings = $this->filterIssues($result->issues, 'warning');
        $errors = $this->filterIssues($result->issues, 'error');
        $ignored = $this->extractIgnored($result);
        $historical = $this->extractHistorical($result);
        $readyForBundle = $this->isReadyForBundle($sourceConfig, $result, $errors);

        return [
            'project_id' => $projectId,
            'status' => $readyForBundle ? 'ready' : 'blocked',
            'ready_for_bundle' => $readyForBundle,
            'summary' => $summary,
            'entities' => $entities,
            'warnings' => $warnings,
            'errors' => $errors,
            'ignored' => $ignored,
            'historical' => $historical,
            'generated_at' => date('c'),
        ];
    }

    private function buildEntities(NormalizationResult $result): array
    {
        $entityMap = [
            'suppliers'     => $result->suppliers,
            'payables'      => $result->payables,
            'categories'    => $result->categories,
            'organizations' => $result->organizations,
            'users'         => $result->users,
            'accounts'      => $result->accounts,
            'expenses'      => $result->expenses,
        ];

        $entities = [];

        foreach ($entityMap as $name => $records) {
            $total = count($records);
            $entityIssues = array_filter($result->issues, fn(QualityIssue $i) => $i->entity === $name);
            $errorCount = count(array_filter($entityIssues, fn(QualityIssue $i) => $i->severity === 'error'));
            $warningCount = count(array_filter($entityIssues, fn(QualityIssue $i) => $i->severity === 'warning'));
            $ignoredCount = count(array_filter($records, fn($r) => $this->recordStatus($r) === 'ignored'));
            $historicalCount = count(array_filter($records, fn($r) => $this->recordStatus($r) === 'historical'));

            $entities[$name] = [
                'total' => $total,
                'valid' => $total - $errorCount - $ignoredCount - $historicalCount,
                'warnings' => $warningCount,
                'errors' => $errorCount,
                'ignored' => $ignoredCount,
                'historical' => $historicalCount,
            ];
        }

        return $entities;
    }

    private function buildSummary(array $entities): array
    {
        $totalRecords = 0;
        $validRecords = 0;
        $warningCount = 0;
        $errorCount = 0;
        $ignoredCount = 0;
        $historicalCount = 0;

        foreach ($entities as $entity) {
            $totalRecords += $entity['total'];
            $validRecords += $entity['valid'];
            $warningCount += $entity['warnings'];
            $errorCount += $entity['errors'];
            $ignoredCount += $entity['ignored'];
            $historicalCount += $entity['historical'];
        }

        return [
            'total_records' => $totalRecords,
            'valid_records' => $validRecords,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'ignored_count' => $ignoredCount,
            'historical_count' => $historicalCount,
        ];
    }

    private function filterIssues(array $issues, string $severity): array
    {
        return array_values(
            array_map(fn(QualityIssue $i) => [
                'type' => $i->type,
                'entity' => $i->entity,
                'external_id' => $i->external_id,
                'field' => $i->field,
                'message' => $i->message,
                'value' => $i->value,
            ], array_filter($issues, fn(QualityIssue $i) => $i->severity === $severity))
        );
    }

    private function extractIgnored(NormalizationResult $result): array
    {
        $ignored = [];
        $entityMap = [
            'suppliers' => $result->suppliers,
            'payables' => $result->payables,
            'categories' => $result->categories,
        ];

        foreach ($entityMap as $entity => $records) {
            foreach ($records as $record) {
                if ($this->recordStatus($record) === 'ignored') {
                    $ignored[] = [
                        'entity' => $entity,
                        'external_id' => $this->recordExternalId($record),
                        'reason' => is_array($record) ? ($record['ignore_reason'] ?? 'Skipped by rule') : 'Skipped by rule',
                    ];
                }
            }
        }

        return $ignored;
    }

    private function extractHistorical(NormalizationResult $result): array
    {
        $historical = [];
        $entityMap = [
            'payables' => $result->payables,
            'expenses' => $result->expenses,
        ];

        foreach ($entityMap as $entity => $records) {
            foreach ($records as $record) {
                if ($this->recordStatus($record) === 'historical') {
                    $historical[] = [
                        'entity' => $entity,
                        'external_id' => $this->recordExternalId($record),
                        'reason' => 'Record predates data cutoff',
                    ];
                }
            }
        }

        return $historical;
    }

    private function recordStatus(mixed $record): string
    {
        if (is_array($record)) {
            return $record['status'] ?? '';
        }
        if ($record instanceof NormalizedPayable) {
            return $record->status;
        }
        if ($record instanceof NormalizedExpense) {
            return $record->status;
        }
        return '';
    }

    private function recordExternalId(mixed $record): string
    {
        if (is_array($record)) {
            return $record['external_id'] ?? 'unknown';
        }
        if (is_object($record) && property_exists($record, 'external_id')) {
            return $record->external_id;
        }
        return 'unknown';
    }

    private function isReadyForBundle(array $sourceConfig, NormalizationResult $result, array $errors): bool
    {
        if (count($errors) > 0) {
            return false;
        }

        if (empty($sourceConfig)) {
            return false;
        }

        $inventory = $sourceConfig['inventory'] ?? null;
        if ($inventory === null) {
            return false;
        }

        $detectedStructure = $sourceConfig['detected_structure'] ?? null;
        if ($detectedStructure === null) {
            return false;
        }

        $totalRecords = count($result->suppliers) + count($result->payables) + count($result->categories);
        if ($totalRecords === 0) {
            return false;
        }

        return true;
    }
}
