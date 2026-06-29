<?php

declare(strict_types=1);

namespace App\Services\Synthetic;

class SyntheticSourceService
{
    public function connector(array $config): SyntheticConnector
    {
        $connector = new SyntheticConnector(new SyntheticDataFactory());
        $connector->connect($config);

        return $connector;
    }

    public function detectedStructure(array $inventory): array
    {
        return [
            'source_type' => 'synthetic',
            'tables' => array_map(fn (array $table): array => [
                'name' => $table['name'],
                'row_count' => $table['row_count'],
                'columns' => $table['column_names'] ?? array_keys($table['columns'] ?? []),
            ], $inventory['tables'] ?? []),
            'summary' => $inventory['summary'] ?? [],
        ];
    }
}
