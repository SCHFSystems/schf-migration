<?php

namespace Tests\Unit;

use App\Services\MigrationPreviewService;
use PHPUnit\Framework\TestCase;
use SCHF\SDK\Normalization\NormalizedCategory;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedSupplier;
use SCHF\SDK\Normalization\NormalizationResult;
use SCHF\SDK\Normalization\QualityIssue;

class MigrationPreviewServiceTest extends TestCase
{
    private MigrationPreviewService $service;

    private array $validSourceConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MigrationPreviewService();
        $this->validSourceConfig = [
            'inventory' => ['generated_at' => '2026-06-28'],
            'detected_structure' => ['tables' => ['FORNECEDOR' => []]],
        ];
    }

    public function testPreviewWithValidData(): void
    {
        $result = new NormalizationResult(
            suppliers: [
                new NormalizedSupplier(external_id: '1', name: 'Acme'),
                new NormalizedSupplier(external_id: '2', name: 'Beta'),
            ],
            payables: [
                new NormalizedPayable(external_id: '10', direction: 'payable', amount: 100.0, due_date: '2026-01-15', status: 'pending'),
            ],
            categories: [
                new NormalizedCategory(external_id: 'C1', name: 'Rent', type: 'expense'),
            ],
            issues: [],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertTrue($preview['ready_for_bundle']);
        $this->assertSame('ready', $preview['status']);
        $this->assertSame(4, $preview['summary']['total_records']);
        $this->assertSame(4, $preview['summary']['valid_records']);
        $this->assertSame(0, $preview['summary']['error_count']);
    }

    public function testPreviewWithWarnings(): void
    {
        $result = new NormalizationResult(
            suppliers: [new NormalizedSupplier(external_id: '1', name: 'Acme')],
            issues: [
                new QualityIssue(type: 'invalid_date', severity: 'warning', entity: 'payables', external_id: '10', field: 'due_date', message: 'Bad date'),
            ],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertTrue($preview['ready_for_bundle']);
        $this->assertSame(1, $preview['summary']['warning_count']);
        $this->assertCount(1, $preview['warnings']);
    }

    public function testPreviewWithErrors(): void
    {
        $result = new NormalizationResult(
            suppliers: [new NormalizedSupplier(external_id: '1', name: 'Acme')],
            issues: [
                new QualityIssue(type: 'empty_name', severity: 'error', entity: 'suppliers', external_id: '1', field: 'name', message: 'Name is empty'),
            ],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertFalse($preview['ready_for_bundle']);
        $this->assertSame('blocked', $preview['status']);
        $this->assertSame(1, $preview['summary']['error_count']);
        $this->assertCount(1, $preview['errors']);
    }

    public function testPreviewWithIgnored(): void
    {
        $result = new NormalizationResult(
            suppliers: [
                ['external_id' => '1', 'name' => 'Acme', 'status' => 'ignored', 'ignore_reason' => 'Duplicate'],
            ],
            issues: [],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertSame(1, $preview['summary']['ignored_count']);
        $this->assertCount(1, $preview['ignored']);
        $this->assertSame('suppliers', $preview['ignored'][0]['entity']);
    }

    public function testPreviewWithHistorical(): void
    {
        $result = new NormalizationResult(
            payables: [
                new NormalizedPayable(external_id: '10', direction: 'payable', amount: 50.0, due_date: '2020-01-01', status: 'historical'),
            ],
            issues: [],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertSame(1, $preview['summary']['historical_count']);
        $this->assertCount(1, $preview['historical']);
        $this->assertSame('payables', $preview['historical'][0]['entity']);
    }

    public function testReadyForBundleTrueWithWarnings(): void
    {
        $result = new NormalizationResult(
            suppliers: [new NormalizedSupplier(external_id: '1', name: 'Supplier')],
            categories: [new NormalizedCategory(external_id: 'C1', name: 'Cat', type: 'expense')],
            issues: [
                new QualityIssue(type: 'orphan', severity: 'warning', entity: 'payables', external_id: '10', field: 'supplier_external_id', message: 'Orphan ref'),
            ],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertTrue($preview['ready_for_bundle']);
    }

    public function testReadyForBundleFalseWithErrors(): void
    {
        $result = new NormalizationResult(
            suppliers: [new NormalizedSupplier(external_id: '1', name: 'Ok')],
            issues: [
                new QualityIssue(type: 'empty_name', severity: 'error', entity: 'suppliers', external_id: '2', field: 'name', message: 'Empty name'),
            ],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertFalse($preview['ready_for_bundle']);
    }

    public function testEntitiesWithZeroRecords(): void
    {
        $result = new NormalizationResult();

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        foreach (['suppliers', 'payables', 'categories'] as $entity) {
            $this->assertSame(0, $preview['entities'][$entity]['total']);
            $this->assertSame(0, $preview['entities'][$entity]['valid']);
        }
    }

    public function testSummaryTotalsAreCorrect(): void
    {
        $result = new NormalizationResult(
            suppliers: [
                new NormalizedSupplier(external_id: '1', name: 'A'),
                new NormalizedSupplier(external_id: '2', name: 'B'),
                new NormalizedSupplier(external_id: '3', name: 'C'),
            ],
            payables: [
                new NormalizedPayable(external_id: '10', direction: 'payable', amount: 100.0, due_date: '2026-01-01', status: 'pending'),
                new NormalizedPayable(external_id: '11', direction: 'receivable', amount: 200.0, due_date: '2026-02-01', status: 'paid'),
            ],
            categories: [
                new NormalizedCategory(external_id: 'C1', name: 'X', type: 'income'),
            ],
            issues: [],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $this->assertSame(6, $preview['summary']['total_records']);
        $this->assertSame(6, $preview['summary']['valid_records']);
    }

    public function testPreviewIsJsonSerializable(): void
    {
        $result = new NormalizationResult(
            suppliers: [new NormalizedSupplier(external_id: '1', name: 'Test')],
            issues: [],
        );

        $preview = $this->service->generate(1, $this->validSourceConfig, $result);

        $json = json_encode($preview);

        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('project_id', $decoded);
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('ready_for_bundle', $decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('entities', $decoded);
        $this->assertArrayHasKey('warnings', $decoded);
        $this->assertArrayHasKey('errors', $decoded);
        $this->assertArrayHasKey('ignored', $decoded);
        $this->assertArrayHasKey('historical', $decoded);
        $this->assertArrayHasKey('generated_at', $decoded);
    }
}
