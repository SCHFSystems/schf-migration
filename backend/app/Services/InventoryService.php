<?php

declare(strict_types=1);

namespace App\Services;

use SCHF\SDK\Connector\ConnectorInterface;

class InventoryService
{
    /**
     * Generate a complete inventory from a database connector.
     *
     * @param  ConnectorInterface  $connector
     * @return array{
     *     generated_at: string,
     *     driver: string,
     *     tables: array<int, array{
     *         name: string,
     *         row_count: int,
     *         columns: array<string, array{type: string, nullable: bool, default: mixed}>,
     *         primary_keys: list<string>,
     *         foreign_keys: array<string, string>,
     *         sample: array<int, array<string, mixed>>,
     *     }>,
     *     summary: array{
     *         total_tables: int,
     *         total_rows: int,
     *         total_columns: int,
     *     },
     * }
     */
    public function generate(ConnectorInterface $connector): array
    {
        $schema   = $connector->getSchema();
        $tables   = [];
        $totalRows = 0;
        $totalCols = 0;

        foreach ($schema as $tableInfo) {
            $tableName  = $tableInfo['table_name'];
            $columns    = $tableInfo['columns'];
            $totalCols += count($columns);

            $rowCount    = $this->countRows($connector, $tableName);
            $totalRows  += $rowCount;

            $primaryKeys = $this->detectPrimaryKeys($connector, $tableName);
            $foreignKeys = $this->detectForeignKeys($connector, $tableName);
            $sample      = $this->getSample($connector, $tableName);

            // Collect column names in order
            $colNames = array_keys($columns);

            $tables[] = [
                'name'          => $tableName,
                'row_count'     => $rowCount,
                'columns'       => $columns,
                'column_names'  => $colNames,
                'primary_keys'  => $primaryKeys,
                'foreign_keys'  => $foreignKeys,
                'sample'        => $sample,
            ];
        }

        return [
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'driver'       => $connector->getDriverName(),
            'tables'       => $tables,
            'summary'      => [
                'total_tables'  => count($tables),
                'total_rows'    => $totalRows,
                'total_columns' => $totalCols,
            ],
        ];
    }

    private function countRows(ConnectorInterface $connector, string $tableName): int
    {
        try {
            $result = $connector->fetchAll("SELECT COUNT(*) AS cnt FROM \"{$tableName}\"");
            return (int) ($result[0]['cnt'] ?? 0);
        } catch (\Throwable) {
            return -1;
        }
    }

    /**
     * @return list<string>
     */
    private function detectPrimaryKeys(ConnectorInterface $connector, string $tableName): array
    {
        // Firebird-specific PK detection; other drivers will need their own implementation
        if ($connector->getDriverName() === 'firebird') {
            try {
                $rows = $connector->fetchAll(
                    "SELECT ISG.RDB\$FIELD_NAME AS COLUMN_NAME
                       FROM RDB\$RELATION_CONSTRAINTS RC
                       JOIN RDB\$INDEX_SEGMENTS ISG ON ISG.RDB\$INDEX_NAME = RC.RDB\$INDEX_NAME
                      WHERE RC.RDB\$RELATION_NAME = ?
                        AND RC.RDB\$CONSTRAINT_TYPE = 'PRIMARY KEY'
                      ORDER BY ISG.RDB\$FIELD_POSITION",
                    [$tableName]
                );
                return array_map(fn($r) => trim($r['COLUMN_NAME']), $rows);
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function detectForeignKeys(ConnectorInterface $connector, string $tableName): array
    {
        if ($connector->getDriverName() === 'firebird') {
            try {
                $rows = $connector->fetchAll(
                    "SELECT ISG.RDB\$FIELD_NAME AS COLUMN_NAME,
                            RC.RDB\$RELATION_NAME AS REF_TABLE
                       FROM RDB\$RELATION_CONSTRAINTS RC
                       JOIN RDB\$INDEX_SEGMENTS ISG ON ISG.RDB\$INDEX_NAME = RC.RDB\$INDEX_NAME
                      WHERE RC.RDB\$RELATION_NAME = ?
                        AND RC.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY'
                      ORDER BY ISG.RDB\$FIELD_POSITION",
                    [$tableName]
                );
                $fks = [];
                foreach ($rows as $r) {
                    $col = trim($r['COLUMN_NAME']);
                    $ref = trim($r['REF_TABLE']);
                    $fks[$col] = $ref;
                }
                return $fks;
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSample(ConnectorInterface $connector, string $tableName, int $limit = 5): array
    {
        try {
            $rows = $connector->fetchAll("SELECT FIRST {$limit} * FROM \"{$tableName}\"");
            // Truncate long values
            foreach ($rows as &$row) {
                foreach ($row as &$value) {
                    if (is_string($value) && strlen($value) > 200) {
                        $value = substr($value, 0, 200) . '...';
                    }
                }
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }
}
