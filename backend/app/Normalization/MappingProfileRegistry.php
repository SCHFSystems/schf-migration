<?php

declare(strict_types=1);

namespace App\Normalization;

use SCHF\SDK\Normalization\MappingProfile;
use SCHF\SDK\Normalization\MappingRule;

class MappingProfileRegistry
{
    /** @var array<string, array<string, MappingProfile>> */
    private array $profiles = [];

    public function register(MappingProfile $profile): void
    {
        $this->profiles[$profile->source_type][$profile->profile] = $profile;
    }

    public function get(string $sourceType, string $profileName): ?MappingProfile
    {
        return $this->profiles[$sourceType][$profileName] ?? null;
    }

    /**
     * @param  string $sourceType
     * @return MappingProfile[]
     */
    public function allForSource(string $sourceType): array
    {
        return array_values($this->profiles[$sourceType] ?? []);
    }

    /**
     * Apply the profile's rules to a single row from the source.
     *
     * @param  MappingProfile $profile
     * @param  array          $row     Associative array from the source database.
     * @return array          Mapped associative array for the target class.
     */
    public function apply(MappingProfile $profile, array $row): array
    {
        $mapped = [];

        foreach ($profile->rules as $rule) {
            $value = $this->getSourceValue($row, $rule->source_column);

            // Apply transform
            $value = $this->applyTransform($value, $rule);

            if ($value === null && $rule->default !== null) {
                $value = $rule->default;
            }

            // Don't overwrite an existing non-null value with null (fallback support)
            if ($value === null && array_key_exists($rule->target_field, $mapped) && $mapped[$rule->target_field] !== null) {
                continue;
            }

            $mapped[$rule->target_field] = $value;
        }

        return $mapped;
    }

    private function getSourceValue(array $row, string $sourceColumn): mixed
    {
        return $row[$sourceColumn] ?? $row[strtolower($sourceColumn)] ?? null;
    }

    private function applyTransform(mixed $value, MappingRule $rule): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return $value;
        }

        $value = (string) $value;

        return match ($rule->transform) {
            'trim'   => trim($value),
            'upper'  => strtoupper(trim($value)),
            'lower'  => strtolower(trim($value)),
            'number' => is_numeric($value) ? (float) $value : $value,
            'date'   => $this->normalizeDate($value),
            'boolean' => $this->normalizeBoolean($value),
            'split'  => $this->normalizeSplit($value),
            default  => $value,
        };
    }

    private function normalizeDate(string $value): string
    {
        // Try common Brazilian date formats
        $formats = ['d/m/Y', 'd/m/y', 'Y-m-d', 'Y/m/d', 'dmY'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $value);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }
        return $value;
    }

    private function normalizeBoolean(string $value): bool
    {
        return in_array(strtoupper(trim($value)), ['S', '1', 'Y', 'YES', 'TRUE'], true);
    }

    private function normalizeSplit(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_map('trim', explode('|', $value));
    }
}
