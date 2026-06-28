<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Normalization\DataQualityService;

class DataQualityServiceTest extends TestCase
{
    private DataQualityService $service;

    protected function setUp(): void
    {
        $this->service = new DataQualityService();
    }

    public function test_detects_invalid_dates(): void
    {
        $results = [
            'payables' => [
                ['external_id' => 'P001', 'due_date' => 'not-a-date', 'amount' => 100],
            ],
        ];

        $issues = $this->service->checkAll($results);

        $dateIssues = array_filter($issues, fn($i) => $i->type === 'invalid_date');
        $this->assertCount(1, $dateIssues);
    }

    public function test_detects_negative_values(): void
    {
        $results = [
            'payables' => [
                ['external_id' => 'P001', 'amount' => -50],
            ],
        ];

        $issues = $this->service->checkAll($results);

        $negIssues = array_filter($issues, fn($i) => $i->type === 'negative_value');
        $this->assertCount(1, $negIssues);
    }

    public function test_detects_duplicates(): void
    {
        $results = [
            'suppliers' => [
                ['external_id' => 'S001', 'name' => 'Alpha'],
                ['external_id' => 'S001', 'name' => 'Alpha (dup)'],
            ],
        ];

        $issues = $this->service->checkAll($results);

        $dupIssues = array_filter($issues, fn($i) => $i->type === 'duplicate');
        $this->assertCount(1, $dupIssues);
    }

    public function test_detects_orphan_relations(): void
    {
        $results = [
            'suppliers' => [
                ['external_id' => 'S001', 'name' => 'Exists'],
            ],
            'payables' => [
                [
                    'external_id'           => 'P001',
                    'supplier_external_id'  => 'S999', // does not exist in suppliers
                    'category_external_id'  => 'C999', // does not exist
                    'amount'               => 100,
                ],
            ],
        ];

        $issues = $this->service->checkAll($results);

        $orphanIssues = array_filter($issues, fn($i) => $i->type === 'orphan');
        $this->assertCount(2, $orphanIssues);
    }

    public function test_passes_clean_data(): void
    {
        $results = [
            'suppliers' => [
                ['external_id' => 'S001', 'name' => 'Alpha'],
            ],
            'payables' => [
                [
                    'external_id'          => 'P001',
                    'supplier_external_id' => 'S001',
                    'due_date'            => '2025-01-15',
                    'amount'              => 1500,
                ],
            ],
        ];

        $issues = $this->service->checkAll($results);

        $this->assertCount(0, $issues);
    }
}
