<?php

declare(strict_types=1);

namespace App\Normalization;

use SCHF\SDK\Normalization\QualityIssue;

class DataQualityService
{
    /**
     * Run all quality checks on the normalized data.
     *
     * @param  array $results  Keyed by entity name, each value is an array of mapped rows.
     * @return QualityIssue[]
     */
    public function checkAll(array $results): array
    {
        $issues = [];

        foreach ($results as $entity => $rows) {
            $issues = array_merge($issues, $this->checkEmptyNames($entity, $rows));
            $issues = array_merge($issues, $this->checkInvalidDates($entity, $rows));
            $issues = array_merge($issues, $this->checkNegativeValues($entity, $rows));
            $issues = array_merge($issues, $this->checkDuplicates($entity, $rows));
            $issues = array_merge($issues, $this->checkOrphanRelations($entity, $rows, $results));
        }

        return $issues;
    }

    /**
     * @param  string $entity
     * @param  array  $rows
     * @return QualityIssue[]
     */
    private function checkEmptyNames(string $entity, array $rows): array
    {
        // Only check entities that carry a 'name' field in their schema
        $namedEntities = ['suppliers', 'categories', 'users'];
        if (!in_array($entity, $namedEntities, true)) {
            return [];
        }

        $issues = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? '';
            if (empty(trim((string) $name))) {
                $issues[] = new QualityIssue(
                    type:        'empty_name',
                    severity:    'error',
                    entity:      $entity,
                    external_id: (string)($row['external_id'] ?? 'unknown'),
                    field:       'name',
                    message:     "Entity has empty name",
                );
            }
        }
        return $issues;
    }

    /**
     * @param  string $entity
     * @param  array  $rows
     * @return QualityIssue[]
     */
    private function checkInvalidDates(string $entity, array $rows): array
    {
        $issues = [];
        $dateFields = ['due_date', 'paid_at', 'date'];

        foreach ($rows as $row) {
            foreach ($dateFields as $field) {
                $val = $row[$field] ?? null;
                if ($val === null) {
                    continue;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $val)) {
                    $issues[] = new QualityIssue(
                        type:        'invalid_date',
                        severity:    'warning',
                        entity:      $entity,
                        external_id: (string)($row['external_id'] ?? 'unknown'),
                        field:       $field,
                        message:     "Field '{$field}' has unexpected date format: {$val}",
                        value:       $val,
                    );
                }
            }
        }
        return $issues;
    }

    /**
     * @param  string $entity
     * @param  array  $rows
     * @return QualityIssue[]
     */
    private function checkNegativeValues(string $entity, array $rows): array
    {
        $issues = [];
        $numberFields = ['amount', 'opening_balance', 'value'];

        foreach ($rows as $row) {
            foreach ($numberFields as $field) {
                $val = $row[$field] ?? null;
                if ($val === null) {
                    continue;
                }
                $num = (float) $val;
                if ($num < 0) {
                    $issues[] = new QualityIssue(
                        type:        'negative_value',
                        severity:    'warning',
                        entity:      $entity,
                        external_id: (string)($row['external_id'] ?? 'unknown'),
                        field:       $field,
                        message:     "Field '{$field}' has a negative value: {$num}",
                        value:       $num,
                    );
                }
            }
        }
        return $issues;
    }

    /**
     * @param  string $entity
     * @param  array  $rows
     * @return QualityIssue[]
     */
    private function checkDuplicates(string $entity, array $rows): array
    {
        $issues  = [];
        $seen    = [];

        foreach ($rows as $idx => $row) {
            $eid = (string) ($row['external_id'] ?? '');
            if ($eid === '') {
                continue;
            }
            if (isset($seen[$eid])) {
                $issues[] = new QualityIssue(
                    type:        'duplicate',
                    severity:    'error',
                    entity:      $entity,
                    external_id: $eid,
                    field:       'external_id',
                    message:     "Duplicate external_id '{$eid}' found (rows {$seen[$eid]} and {$idx})",
                    value:       $eid,
                );
            }
            $seen[$eid] = $idx;
        }

        return $issues;
    }

    /**
     * @param  string $entity
     * @param  array  $rows
     * @param  array  $allResults  All entities and their rows.
     * @return QualityIssue[]
     */
    private function checkOrphanRelations(string $entity, array $rows, array $allResults): array
    {
        $issues = [];

        // Build a lookup of known external_ids across all entities
        $knownIds = [];
        foreach ($allResults as $otherEntity => $otherRows) {
            foreach ($otherRows as $otherRow) {
                $eid = (string) ($otherRow['external_id'] ?? '');
                if ($eid !== '') {
                    $knownIds[$eid] = true;
                }
            }
        }

        foreach ($rows as $row) {
            $eid = (string) ($row['external_id'] ?? '');

            // Check supplier_external_id
            $supplierId = $row['supplier_external_id'] ?? null;
            if ($supplierId !== null && $supplierId !== '' && !isset($knownIds[(string) $supplierId])) {
                $issues[] = new QualityIssue(
                    type:        'orphan',
                    severity:    'warning',
                    entity:      $entity,
                    external_id: $eid,
                    field:       'supplier_external_id',
                    message:     "References unknown supplier '{$supplierId}'",
                    value:       $supplierId,
                );
            }

            // Check category_external_id
            $catId = $row['category_external_id'] ?? null;
            if ($catId !== null && $catId !== '' && !isset($knownIds[(string) $catId])) {
                $issues[] = new QualityIssue(
                    type:        'orphan',
                    severity:    'warning',
                    entity:      $entity,
                    external_id: $eid,
                    field:       'category_external_id',
                    message:     "References unknown category '{$catId}'",
                    value:       $catId,
                );
            }
        }

        return $issues;
    }
}
