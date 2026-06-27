<?php

namespace App\Services\SourceDetectors;

interface DatabaseDetectorInterface
{
    /**
     * Test connection to the data source.
     */
    public function testConnection(array $config): bool;

    /**
     * Detect database structure (tables, columns, types).
     */
    public function detectStructure(array $config): array;

    /**
     * Get sample data from a table.
     */
    public function getSampleData(array $config, string $tableName, int $limit = 10): array;

    /**
     * Count total records in a table.
     */
    public function countRecords(array $config, string $tableName): int;

    /**
     * Get supported source type identifier.
     */
    public function getSourceType(): string;
}
