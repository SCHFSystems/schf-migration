<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Normalization\DataQualityService;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\NormalizationService;
use App\Normalization\Profiles\FirebirdFinanceProfile;
use App\Normalization\Profiles\FirebirdLabFinanceProfile;
use App\Services\InventoryService;
use SCHF\SDK\Connector\ConnectorInterface;
use SCHF\SDK\Normalization\NormalizationResult;

class NormalizationServiceTest extends TestCase
{
    private NormalizationService $service;
    private MappingProfileRegistry $registry;
    private ConnectorInterface $connector;
    private InventoryService $inventoryServiceMock;

    protected function setUp(): void
    {
        $this->registry = new MappingProfileRegistry();
        $qualityService = new DataQualityService();

        // Register Firebird profiles
        foreach (FirebirdFinanceProfile::all() as $profile) {
            $this->registry->register($profile);
        }

        $this->inventoryServiceMock = $this->createMock(InventoryService::class);

        $this->service = new NormalizationService($this->registry, $qualityService, $this->inventoryServiceMock);

        $this->connector = $this->createMock(ConnectorInterface::class);
    }

    public function test_normalize_returns_result_object(): void
    {
        $this->connector->method('fetchAll')->willReturn([]);

        $result = $this->service->normalize($this->connector, 'firebird');

        $this->assertInstanceOf(NormalizationResult::class, $result);
        $this->assertIsArray($result->suppliers);
        $this->assertIsArray($result->payables);
        $this->assertIsArray($result->categories);
        $this->assertIsArray($result->issues);
        $this->assertIsArray($result->summary);
    }

    public function test_normalize_with_data(): void
    {
        $callCount = 0;
        $this->connector->method('fetchAll')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return match ($callCount) {
                1 => [['COD_FORNECEDOR' => 'S001', 'NOME_FANTASIA' => 'Alpha Ltda', 'CNPJ_CPF' => '11.111.111/0001-11', 'ATIVO' => 'S']],
                2 => [['COD_LANCAMENTO' => 'P001', 'COD_FORNECEDOR' => 'S001', 'VALOR' => 1500, 'DATA_VENCIMENTO' => '15/01/2025', 'DESCRICAO' => 'Servico']],
                3 => [['COD_CATEGORIA' => 'C001', 'DESCRICAO' => 'Servicos', 'TIPO' => 'expense']],
                default => [],
            };
        });

        $result = $this->service->normalize($this->connector, 'firebird');

        $this->assertCount(1, $result->suppliers);
        $this->assertCount(1, $result->payables);
        $this->assertCount(1, $result->categories);
        $this->assertCount(0, $result->issues);

        $this->assertSame('Alpha Ltda', $result->suppliers[0]['name']);
        $this->assertSame(1500.0, $result->payables[0]['amount']);
        $this->assertSame('2025-01-15', $result->payables[0]['due_date']);
        $this->assertSame('Servicos', $result->categories[0]['name']);
    }

    public function test_normalize_with_invalid_record(): void
    {
        $this->connector->method('fetchAll')->willReturnCallback(function ($sql) {
            if (str_contains($sql, 'FORNECEDOR')) {
                return [
                    ['COD_FORNECEDOR' => 'S001', 'NOME_FANTASIA' => '', 'CNPJ_CPF' => null, 'ATIVO' => 'S'],
                ];
            }
            if (str_contains($sql, 'CONTAS_RECEBER_PAGAR')) {
                return [
                    ['COD_LANCAMENTO' => '', 'COD_FORNECEDOR' => 'S999', 'VALOR' => -100, 'DATA_VENCIMENTO' => 'invalida', 'DESCRICAO' => ''],
                ];
            }
            if (str_contains($sql, 'CATEGORIA')) {
                return [['COD_CATEGORIA' => 'C001', 'DESCRICAO' => 'Teste', 'TIPO' => 'expense']];
            }
            return [];
        });

        $result = $this->service->normalize($this->connector, 'firebird');

        $this->assertGreaterThan(0, count($result->issues));

        $issueTypes = array_map(fn($i) => $i->type, $result->issues);
        $this->assertContains('empty_name', $issueTypes);
        $this->assertContains('negative_value', $issueTypes);
        $this->assertContains('invalid_date', $issueTypes);
    }

    public function test_preview_returns_structure(): void
    {
        $callCount = 0;
        $this->connector->method('fetchAll')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount <= 3) {
                // SELECT FIRST 10 calls (1 per profile)
                return [['ID' => 1]];
            }
            // COUNT calls
            return [['cnt' => 5]];
        });

        $preview = $this->service->preview($this->connector, 'firebird');

        $this->assertArrayHasKey('profiles', $preview);
        $this->assertArrayHasKey('summary', $preview);
        $this->assertSame(3, $preview['total_profiles']);
    }

    public function test_firebird_lab_profile_normalizes_all_entities(): void
    {
        $registry = new MappingProfileRegistry();
        foreach (FirebirdLabFinanceProfile::all() as $profile) {
            $registry->register($profile);
        }

        $service = new NormalizationService($registry, new DataQualityService(), $this->inventoryServiceMock);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('fetchAll')->willReturnCallback(function (string $sql): array {
            return match (true) {
                str_contains($sql, '"EMPRESA"') => [
                    ['ID' => 1, 'NOME' => 'Synthetic Firebird Company', 'CNPJ' => 'FIREBIRD-DOC-001', 'ATIVO' => 'S'],
                ],
                str_contains($sql, '"FORNECEDOR"') => [
                    ['ID' => 1, 'NOME' => 'Synthetic Firebird Supplier A', 'DOCUMENTO' => 'FIRE-SUP-DOC-001', 'EMAIL' => 'supplier.a@firebird-lab.local', 'ATIVO' => 'S'],
                    ['ID' => 2, 'NOME' => 'Synthetic Firebird Supplier B', 'DOCUMENTO' => 'FIRE-SUP-DOC-002', 'EMAIL' => 'supplier.b@firebird-lab.local', 'ATIVO' => 'S'],
                ],
                str_contains($sql, '"CATEGORIA"') => [
                    ['ID' => 1, 'NOME' => 'Synthetic Firebird Category A', 'TIPO' => 'expense', 'ATIVO' => 'S'],
                    ['ID' => 2, 'NOME' => 'Synthetic Firebird Category B', 'TIPO' => 'expense', 'ATIVO' => 'S'],
                ],
                str_contains($sql, '"CONTA_BANCARIA"') => [
                    ['ID' => 1, 'NOME' => 'Synthetic Firebird Account A', 'BANCO' => 'FIREBANK', 'AGENCIA' => '0001', 'CONTA' => 'FB-0001', 'ATIVO' => 'S'],
                    ['ID' => 2, 'NOME' => 'Synthetic Firebird Account B', 'BANCO' => 'FIREBANK', 'AGENCIA' => '0002', 'CONTA' => 'FB-0002', 'ATIVO' => 'S'],
                ],
                str_contains($sql, '"TITULO_PAGAR"') => [
                    ['ID' => 1, 'FORNECEDOR_ID' => 1, 'CATEGORIA_ID' => 1, 'CONTA_ID' => 1, 'DESCRICAO' => 'Synthetic Firebird payable A', 'VALOR' => 100, 'VENCIMENTO' => '2026-09-01', 'STATUS' => 'pending'],
                    ['ID' => 2, 'FORNECEDOR_ID' => 2, 'CATEGORIA_ID' => 2, 'CONTA_ID' => 2, 'DESCRICAO' => 'Synthetic Firebird payable B', 'VALOR' => 200, 'VENCIMENTO' => '2026-09-02', 'STATUS' => 'pending'],
                    ['ID' => 3, 'FORNECEDOR_ID' => 1, 'CATEGORIA_ID' => 2, 'CONTA_ID' => 1, 'DESCRICAO' => 'Synthetic Firebird payable C', 'VALOR' => 300, 'VENCIMENTO' => '2026-09-03', 'STATUS' => 'pending'],
                ],
                str_contains($sql, '"DESPESA"') => [
                    ['ID' => 1, 'CATEGORIA_ID' => 1, 'DESCRICAO' => 'Synthetic Firebird expense A', 'VALOR' => 25, 'DATA_DESPESA' => '2026-09-01'],
                    ['ID' => 2, 'CATEGORIA_ID' => 2, 'DESCRICAO' => 'Synthetic Firebird expense B', 'VALOR' => 75, 'DATA_DESPESA' => '2026-09-02'],
                ],
                str_contains($sql, '"USUARIO"') => [
                    ['ID' => 1, 'NOME' => 'Synthetic Firebird User', 'EMAIL' => 'synthetic.firebird.user@firebird-lab.local', 'ATIVO' => 'S', 'PAPEIS' => 'admin'],
                ],
                default => [],
            };
        });

        $result = $service->normalize($connector, 'firebird');

        $this->assertCount(1, $result->organizations);
        $this->assertCount(2, $result->suppliers);
        $this->assertCount(2, $result->categories);
        $this->assertCount(2, $result->accounts);
        $this->assertCount(3, $result->payables);
        $this->assertCount(2, $result->expenses);
        $this->assertCount(1, $result->users);
        $this->assertSame(['admin'], $result->users[0]['roles']);
        $this->assertTrue($result->users[0]['active']);
        $this->assertSame(0, $result->summary['total_errors']);
    }
}
