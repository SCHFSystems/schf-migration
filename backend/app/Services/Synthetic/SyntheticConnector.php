<?php

declare(strict_types=1);

namespace App\Services\Synthetic;

use ArrayIterator;
use SCHF\SDK\Connector\ConnectorInterface;

class SyntheticConnector implements ConnectorInterface
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    public function __construct(
        private SyntheticDataFactory $factory = new SyntheticDataFactory(),
    ) {}

    public function connect(array $params): void
    {
        $this->tables = $this->factory->make((string) ($params['scenario'] ?? 'clean'));
    }

    public function disconnect(): void
    {
        $this->tables = [];
    }

    public function getDriverName(): string
    {
        return 'synthetic';
    }

    public function getSchema(): array
    {
        $schema = [];

        foreach ($this->tables as $tableName => $rows) {
            $columns = [];
            $sample = $rows[0] ?? [];

            foreach ($sample as $column => $value) {
                $columns[$column] = [
                    'type' => $this->inferType($value),
                    'nullable' => $this->columnHasNulls($rows, $column),
                    'default' => null,
                ];
            }

            $schema[] = [
                'table_name' => $tableName,
                'columns' => $columns,
            ];
        }

        return $schema;
    }

    public function query(string $sql, array $params = []): \Iterator
    {
        return new ArrayIterator($this->fetchAll($sql, $params));
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $tableName = $this->extractTableName($sql);

        if ($tableName === null || ! array_key_exists($tableName, $this->tables)) {
            return [];
        }

        if (preg_match('/COUNT\s*\(\s*\*\s*\)/i', $sql)) {
            return [['cnt' => count($this->tables[$tableName])]];
        }

        $rows = $this->tables[$tableName];

        if (preg_match('/SELECT\s+FIRST\s+(\d+)/i', $sql, $matches)) {
            return array_slice($rows, 0, (int) $matches[1]);
        }

        return $rows;
    }

    private function extractTableName(string $sql): ?string
    {
        if (preg_match('/FROM\s+"?([A-Z0-9_]+)"?/i', $sql, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'integer',
            is_float($value) => 'decimal',
            is_bool($value) => 'boolean',
            $value === null => 'string',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1 => 'date',
            default => 'string',
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function columnHasNulls(array $rows, string $column): bool
    {
        foreach ($rows as $row) {
            if (! array_key_exists($column, $row) || $row[$column] === null) {
                return true;
            }
        }

        return false;
    }
}
