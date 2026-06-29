<?php

declare(strict_types=1);

namespace App\Normalization;

use SCHF\SDK\Normalization\NormalizationResult;
use SCHF\SDK\Normalization\QualityIssue;

class NormalizationResultSerializer
{
    public function toArray(NormalizationResult $result): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'summary' => $result->summary,
            'entities' => [
                'organizations' => $this->recordsToArray($result->organizations),
                'users' => $this->recordsToArray($result->users),
                'suppliers' => $this->recordsToArray($result->suppliers),
                'accounts' => $this->recordsToArray($result->accounts),
                'categories' => $this->recordsToArray($result->categories),
                'payables' => $this->recordsToArray($result->payables),
                'expenses' => $this->recordsToArray($result->expenses),
            ],
            'issues' => $this->issuesToArray($result->issues),
        ];
    }

    public function toResult(array $bundle): NormalizationResult
    {
        $entities = $bundle['entities'] ?? [];

        return new NormalizationResult(
            organizations: $entities['organizations'] ?? [],
            users: $entities['users'] ?? [],
            suppliers: $entities['suppliers'] ?? [],
            accounts: $entities['accounts'] ?? [],
            categories: $entities['categories'] ?? [],
            payables: $entities['payables'] ?? [],
            expenses: $entities['expenses'] ?? [],
            issues: array_map(fn (array $issue): QualityIssue => new QualityIssue(
                type: (string) $issue['type'],
                severity: (string) $issue['severity'],
                entity: (string) $issue['entity'],
                external_id: (string) ($issue['external_id'] ?? ''),
                field: (string) ($issue['field'] ?? ''),
                message: (string) ($issue['message'] ?? ''),
                value: $issue['value'] ?? null,
            ), $bundle['issues'] ?? []),
            summary: $bundle['summary'] ?? [],
        );
    }

    public function qualityToArray(array $issues): array
    {
        $serialized = $this->issuesToArray($issues);
        $errors = array_values(array_filter($serialized, fn (array $issue): bool => $issue['severity'] === 'error'));
        $warnings = array_values(array_filter($serialized, fn (array $issue): bool => $issue['severity'] === 'warning'));

        return [
            'ran_at' => now()->toISOString(),
            'status' => count($errors) > 0 ? 'blocked' : (count($warnings) > 0 ? 'passed_with_warnings' : 'passed'),
            'summary' => [
                'total_issues' => count($serialized),
                'total_errors' => count($errors),
                'total_warnings' => count($warnings),
            ],
            'issues' => $serialized,
        ];
    }

    private function recordsToArray(array $records): array
    {
        return array_map(function (mixed $record): array {
            if (is_array($record)) {
                return $record;
            }

            if (is_object($record)) {
                return get_object_vars($record);
            }

            return ['value' => $record];
        }, $records);
    }

    private function issuesToArray(array $issues): array
    {
        return array_map(fn (QualityIssue $issue): array => [
            'type' => $issue->type,
            'severity' => $issue->severity,
            'entity' => $issue->entity,
            'external_id' => $issue->external_id,
            'field' => $issue->field,
            'message' => $issue->message,
            'value' => $issue->value,
        ], $issues);
    }
}
