<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\InventoryService;
use SCHF\SDK\Connector\ConnectorInterface;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;

    protected function setUp(): void
    {
        $this->service = new InventoryService();
    }

    public function test_generate_returns_correct_structure(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');

        $connector->method('getSchema')->willReturn([
            [
                'table_name' => 'USERS',
                'columns' => [
                    'ID'   => ['type' => 'integer', 'nullable' => false, 'default' => null],
                    'NAME' => ['type' => 'string',  'nullable' => true,  'default' => null],
                ],
            ],
        ]);

        $connector->method('fetchAll')->willReturnCallback(function ($sql) {
            if (str_contains($sql, 'COUNT(*)')) {
                return [['cnt' => 10]];
            }
            if (str_contains($sql, 'RDB$RELATION_CONSTRAINTS')) {
                if (str_contains($sql, 'PRIMARY KEY')) {
                    return [['COLUMN_NAME' => 'ID']];
                }
                if (str_contains($sql, 'FOREIGN KEY')) {
                    return [];
                }
            }
            if (str_contains($sql, 'SELECT FIRST')) {
                return [['ID' => 1, 'NAME' => 'Alice']];
            }
            return [];
        });

        $result = $this->service->generate($connector);

        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertSame('firebird', $result['driver']);

        $this->assertArrayHasKey('summary', $result);
        $this->assertSame(1, $result['summary']['total_tables']);
        $this->assertSame(10, $result['summary']['total_rows']);
        $this->assertSame(2, $result['summary']['total_columns']);

        $this->assertCount(1, $result['tables']);
        $table = $result['tables'][0];
        $this->assertSame('USERS', $table['name']);
        $this->assertSame(10, $table['row_count']);
        $this->assertSame(['ID', 'NAME'], $table['column_names']);
        $this->assertSame(['ID'], $table['primary_keys']);
        $this->assertSame([], $table['foreign_keys']);
        $this->assertCount(1, $table['sample']);
    }

    public function test_generate_handles_empty_schema(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');
        $connector->method('getSchema')->willReturn([]);

        $result = $this->service->generate($connector);

        $this->assertSame(0, $result['summary']['total_tables']);
        $this->assertSame(0, $result['summary']['total_rows']);
        $this->assertSame(0, $result['summary']['total_columns']);
        $this->assertCount(0, $result['tables']);
    }

    public function test_generate_handles_missing_counts_gracefully(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');
        $connector->method('getSchema')->willReturn([
            ['table_name' => 'EMPTY_TABLE', 'columns' => []],
        ]);

        // COUNT returns empty (no rows)
        $connector->method('fetchAll')->willReturn([]);

        $result = $this->service->generate($connector);

        $this->assertSame(1, $result['summary']['total_tables']);
        $this->assertSame(0, $result['summary']['total_rows']);
    }

    public function test_generate_handles_exception_during_count(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');
        $connector->method('getSchema')->willReturn([
            ['table_name' => 'BROKEN', 'columns' => ['X' => ['type' => 'string', 'nullable' => true, 'default' => null]]],
        ]);

        // First call (COUNT) throws exception, second call (PK) and third (FK) also throw
        $connector->method('fetchAll')->willThrowException(new \RuntimeException('Connection lost'));

        $result = $this->service->generate($connector);

        $this->assertSame(1, $result['summary']['total_tables']);
        // Row count should be -1 when exception occurs
        $this->assertSame(-1, $result['tables'][0]['row_count']);
        $this->assertSame([], $result['tables'][0]['primary_keys']);
        $this->assertSame([], $result['tables'][0]['sample']);
    }

    public function test_generate_truncates_long_sample_values(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');
        $connector->method('getSchema')->willReturn([
            ['table_name' => 'LOGS', 'columns' => ['TEXT' => ['type' => 'text', 'nullable' => true, 'default' => null]]],
        ]);

        $longText = str_repeat('A', 500);

        $callCount = 0;
        $connector->method('fetchAll')->willReturnCallback(function () use ($longText, &$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return [['cnt' => 1]];
            }
            if ($callCount === 2) {
                return [['COLUMN_NAME' => 'ID']];
            }
            if ($callCount === 3) {
                return [];
            }
            return [['TEXT' => $longText]];
        });

        $result = $this->service->generate($connector);

        $sampleValue = $result['tables'][0]['sample'][0]['TEXT'];
        $this->assertStringEndsWith('...', $sampleValue);
        $this->assertLessThan(300, strlen($sampleValue));
    }

    public function test_generate_detects_firebird_foreign_keys_and_uppercase_count_alias(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('getDriverName')->willReturn('firebird');
        $connector->method('getSchema')->willReturn([
            [
                'table_name' => 'TITULO_PAGAR',
                'columns' => [
                    'ID' => ['type' => 'integer', 'nullable' => false, 'default' => null],
                    'FORNECEDOR_ID' => ['type' => 'integer', 'nullable' => true, 'default' => null],
                ],
            ],
        ]);

        $connector->method('fetchAll')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'COUNT(*)')) {
                return [['CNT' => 3]];
            }
            if (str_contains($sql, 'PRIMARY KEY')) {
                return [['COLUMN_NAME' => 'ID']];
            }
            if (str_contains($sql, 'FOREIGN KEY')) {
                return [['COLUMN_NAME' => 'FORNECEDOR_ID', 'REF_TABLE' => 'FORNECEDOR']];
            }
            if (str_contains($sql, 'SELECT FIRST')) {
                return [['ID' => 1, 'FORNECEDOR_ID' => 1]];
            }
            return [];
        });

        $result = $this->service->generate($connector);

        $this->assertSame(3, $result['summary']['total_rows']);
        $this->assertSame(['FORNECEDOR_ID' => 'FORNECEDOR'], $result['tables'][0]['foreign_keys']);
    }
}
