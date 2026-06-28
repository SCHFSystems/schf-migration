<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Normalization\DataQualityService;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\NormalizationService;
use App\Normalization\Profiles\FirebirdFinanceProfile;
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
}
