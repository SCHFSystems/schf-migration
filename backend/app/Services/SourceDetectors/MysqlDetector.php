<?php

namespace App\Services\SourceDetectors;

use Illuminate\Support\Facades\Log;
use PDO;

class MysqlDetector implements DatabaseDetectorInterface
{
    public function testConnection(array $config): bool
    {
        try {
            $pdo = $this->getConnection($config);
            $pdo = null;

            return true;
        } catch (\Exception $e) {
            Log::error('MySQL connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function detectStructure(array $config): array
    {
        $tables = [];

        try {
            $pdo = $this->getConnection($config);

            $stmt = $pdo->query("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = :database
                AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
            ");
            $stmt->execute([':database' => $config['database']]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tableName = $row['TABLE_NAME'];
                $columns = $this->getColumns($pdo, $config['database'], $tableName);

                $tables[$tableName] = [
                    'name' => $tableName,
                    'columns' => $columns,
                    'column_count' => count($columns),
                ];
            }

            $pdo = null;
        } catch (\Exception $e) {
            Log::error('MySQL structure detection failed', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }

        return ['tables' => $tables, 'table_count' => count($tables)];
    }

    public function getSampleData(array $config, string $tableName, int $limit = 10): array
    {
        try {
            $pdo = $this->getConnection($config);
            $safeName = $this->sanitizeIdentifier($tableName);

            $stmt = $pdo->query("SELECT * FROM `{$safeName}` LIMIT {$limit}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pdo = null;

            return ['data' => $rows, 'count' => count($rows)];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function countRecords(array $config, string $tableName): int
    {
        try {
            $pdo = $this->getConnection($config);
            $safeName = $this->sanitizeIdentifier($tableName);

            $stmt = $pdo->query("SELECT COUNT(*) as CNT FROM `{$safeName}`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int) $row['CNT'];

            $pdo = null;

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getSourceType(): string
    {
        return 'mysql';
    }

    private function getConnection(array $config): PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function getColumns(PDO $pdo, string $database, string $tableName): array
    {
        $stmt = $pdo->prepare("
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                IS_NULLABLE,
                COLUMN_KEY,
                COLUMN_DEFAULT,
                EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute([':database' => $database, ':table' => $tableName]);

        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['COLUMN_NAME'],
                'type' => $row['DATA_TYPE'],
                'length' => $row['CHARACTER_MAXIMUM_LENGTH'],
                'precision' => $row['NUMERIC_PRECISION'],
                'scale' => $row['NUMERIC_SCALE'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'primary' => $row['COLUMN_KEY'] === 'PRI',
                'auto_increment' => str_contains($row['EXTRA'] ?? '', 'auto_increment'),
                'default' => $row['COLUMN_DEFAULT'],
            ];
        }

        return $columns;
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }
}
