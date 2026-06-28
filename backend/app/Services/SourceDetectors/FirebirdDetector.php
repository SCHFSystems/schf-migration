<?php

namespace App\Services\SourceDetectors;

use Illuminate\Support\Facades\Log;

class FirebirdDetector implements DatabaseDetectorInterface
{
    public function testConnection(array $config): bool
    {
        try {
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 3050;
            $database = $config['database'] ?? '';
            [$username, $password] = $this->credentials($config);

            $dsn = "{$host}/{$port}:{$database}";

            if (! function_exists('ibase_connect')) {
                Log::warning('Firebird ibase_connect extension not available');

                return false;
            }

            $connection = @ibase_connect($dsn, $username, $password);

            if ($connection) {
                ibase_close($connection);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Firebird connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function detectStructure(array $config): array
    {
        $tables = [];

        try {
            $dsn = $this->buildDsn($config);
            [$username, $password] = $this->credentials($config);
            $connection = ibase_connect($dsn, $username, $password);

            if (! $connection) {
                return ['error' => 'Could not connect to Firebird database'];
            }

            $query = "
                SELECT RDB\$RELATION_NAME as TABLE_NAME
                FROM RDB\$RELATIONS
                WHERE RDB\$SYSTEM_FLAG = 0
                AND RDB\$VIEW_BLR IS NULL
                ORDER BY RDB\$RELATION_NAME
            ";

            $result = ibase_query($connection, $query);

            while ($row = ibase_fetch_assoc($result)) {
                $tableName = trim($row['TABLE_NAME']);
                $columns = $this->getColumns($connection, $tableName);

                $tables[$tableName] = [
                    'name' => $tableName,
                    'columns' => $columns,
                    'column_count' => count($columns),
                ];
            }

            ibase_free_result($result);
            ibase_close($connection);
        } catch (\Exception $e) {
            Log::error('Firebird structure detection failed', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }

        return ['tables' => $tables, 'table_count' => count($tables)];
    }

    public function getSampleData(array $config, string $tableName, int $limit = 10): array
    {
        try {
            $dsn = $this->buildDsn($config);
            [$username, $password] = $this->credentials($config);
            $connection = ibase_connect($dsn, $username, $password);

            if (! $connection) {
                return ['error' => 'Could not connect to Firebird database'];
            }

            $safeName = $this->sanitizeIdentifier($tableName);
            $query = "SELECT FIRST {$limit} * FROM {$safeName}";
            $result = ibase_query($connection, $query);
            $rows = [];

            while ($row = ibase_fetch_assoc($result)) {
                $rows[] = $row;
            }

            ibase_free_result($result);
            ibase_close($connection);

            return ['data' => $rows, 'count' => count($rows)];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function countRecords(array $config, string $tableName): int
    {
        try {
            $dsn = $this->buildDsn($config);
            [$username, $password] = $this->credentials($config);
            $connection = ibase_connect($dsn, $username, $password);

            if (! $connection) {
                return 0;
            }

            $safeName = $this->sanitizeIdentifier($tableName);
            $query = "SELECT COUNT(*) as CNT FROM {$safeName}";
            $result = ibase_query($connection, $query);
            $row = ibase_fetch_assoc($result);
            $count = (int) $row['CNT'];

            ibase_free_result($result);
            ibase_close($connection);

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getSourceType(): string
    {
        return 'firebird';
    }

    private function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3050;
        $database = $config['database'] ?? '';

        return "{$host}/{$port}:{$database}";
    }

    private function credentials(array $config): array
    {
        if (empty($config['username']) || empty($config['password'])) {
            throw new \InvalidArgumentException('Firebird credentials must be provided in the local source configuration.');
        }

        return [$config['username'], $config['password']];
    }

    private function getColumns($connection, string $tableName): array
    {
        $columns = [];
        $safeName = $this->sanitizeIdentifier($tableName);

        $query = "
            SELECT
                RDB\$FIELD_NAME as FIELD_NAME,
                RDB\$FIELD_TYPE as FIELD_TYPE,
                RDB\$FIELD_LENGTH as FIELD_LENGTH,
                RDB\$FIELD_PRECISION as FIELD_PRECISION,
                RDB\$FIELD_SCALE as FIELD_SCALE,
                RDB\$NULL_FLAG as NULL_FLAG
            FROM RDB\$RELATION_FIELDS RF
            JOIN RDB\$FIELDS F ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$safeName}'
            ORDER BY RF.RDB\$FIELD_POSITION
        ";

        $result = ibase_query($connection, $query);

        while ($row = ibase_fetch_assoc($result)) {
            $columns[] = [
                'name' => trim($row['FIELD_NAME']),
                'type' => $this->mapFieldType((int) $row['FIELD_TYPE']),
                'length' => (int) $row['FIELD_LENGTH'],
                'precision' => (int) $row['FIELD_PRECISION'],
                'scale' => (int) $row['FIELD_SCALE'],
                'nullable' => (int) $row['NULL_FLAG'] !== 1,
            ];
        }

        ibase_free_result($result);

        return $columns;
    }

    private function mapFieldType(int $type): string
    {
        return match ($type) {
            7, 8, 9, 10, 11, 12, 13, 14, 15, 16 => 'integer',
            27 => 'float',
            10, 27, 700, 701 => 'float',
            35, 37 => 'timestamp',
            370 => 'date',
            510 => 'time',
            140 => 'blob',
            default => 'string',
        };
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }
}
