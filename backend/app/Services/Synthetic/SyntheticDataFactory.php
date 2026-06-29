<?php

declare(strict_types=1);

namespace App\Services\Synthetic;

class SyntheticDataFactory
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function make(string $scenario = 'clean'): array
    {
        $data = $this->clean();

        if ($scenario === 'warnings') {
            $data['CONTAS_RECEBER_PAGAR'][1]['VALOR'] = -25.50;
            $data['CONTAS_RECEBER_PAGAR'][2]['DATA_VENCIMENTO'] = '31-02-2026';
            $data['DESPESAS'][1]['VALOR'] = -10.00;
        }

        if ($scenario === 'blocked') {
            $data['FORNECEDOR'][1]['NOME_FANTASIA'] = '';
            $data['CATEGORIA'][2]['COD_CATEGORIA'] = 'CAT-001';
        }

        return $data;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function clean(): array
    {
        return [
            'FORNECEDOR' => [
                [
                    'COD_FORNECEDOR' => 'SUP-001',
                    'NOME_FANTASIA' => 'Synthetic Supplier Alpha',
                    'RAZAO_SOCIAL' => 'Synthetic Supplier Alpha Ltd',
                    'CNPJ_CPF' => 'SYN-DOC-001',
                    'ATIVO' => 'S',
                ],
                [
                    'COD_FORNECEDOR' => 'SUP-002',
                    'NOME_FANTASIA' => 'Synthetic Supplier Beta',
                    'RAZAO_SOCIAL' => 'Synthetic Supplier Beta Ltd',
                    'CNPJ_CPF' => 'SYN-DOC-002',
                    'ATIVO' => 'S',
                ],
                [
                    'COD_FORNECEDOR' => 'SUP-003',
                    'NOME_FANTASIA' => 'Synthetic Supplier Gamma',
                    'RAZAO_SOCIAL' => 'Synthetic Supplier Gamma Ltd',
                    'CNPJ_CPF' => 'SYN-DOC-003',
                    'ATIVO' => 'N',
                ],
            ],
            'CATEGORIA' => [
                ['COD_CATEGORIA' => 'CAT-001', 'DESCRICAO' => 'Synthetic Services', 'TIPO' => 'expense'],
                ['COD_CATEGORIA' => 'CAT-002', 'DESCRICAO' => 'Synthetic Utilities', 'TIPO' => 'expense'],
                ['COD_CATEGORIA' => 'CAT-003', 'DESCRICAO' => 'Synthetic Supplies', 'TIPO' => 'expense'],
            ],
            'CONTAS_RECEBER_PAGAR' => [
                ['COD_LANCAMENTO' => 'PAY-001', 'COD_FORNECEDOR' => 'SUP-001', 'COD_CATEGORIA' => 'CAT-001', 'DESCRICAO' => 'Synthetic service invoice', 'VALOR' => 150.00, 'DATA_VENCIMENTO' => '2026-07-10', 'DATA_PAGAMENTO' => null],
                ['COD_LANCAMENTO' => 'PAY-002', 'COD_FORNECEDOR' => 'SUP-002', 'COD_CATEGORIA' => 'CAT-002', 'DESCRICAO' => 'Synthetic utility invoice', 'VALOR' => 250.00, 'DATA_VENCIMENTO' => '2026-07-15', 'DATA_PAGAMENTO' => null],
                ['COD_LANCAMENTO' => 'PAY-003', 'COD_FORNECEDOR' => 'SUP-003', 'COD_CATEGORIA' => 'CAT-003', 'DESCRICAO' => 'Synthetic supply invoice', 'VALOR' => 350.00, 'DATA_VENCIMENTO' => '2026-07-20', 'DATA_PAGAMENTO' => null],
                ['COD_LANCAMENTO' => 'PAY-004', 'COD_FORNECEDOR' => 'SUP-001', 'COD_CATEGORIA' => 'CAT-002', 'DESCRICAO' => 'Synthetic recurring charge', 'VALOR' => 450.00, 'DATA_VENCIMENTO' => '2026-07-25', 'DATA_PAGAMENTO' => '2026-07-26'],
                ['COD_LANCAMENTO' => 'PAY-005', 'COD_FORNECEDOR' => 'SUP-002', 'COD_CATEGORIA' => 'CAT-001', 'DESCRICAO' => 'Synthetic monthly charge', 'VALOR' => 550.00, 'DATA_VENCIMENTO' => '2026-07-30', 'DATA_PAGAMENTO' => null],
            ],
            'CONTAS_BANCARIAS' => [
                ['COD_CONTA' => 'ACC-001', 'NOME' => 'Synthetic Operating Account', 'BANCO' => 'SYNBANK', 'AGENCIA' => '0001', 'CONTA' => 'SYN-0001', 'SALDO_INICIAL' => 1000.00],
                ['COD_CONTA' => 'ACC-002', 'NOME' => 'Synthetic Reserve Account', 'BANCO' => 'SYNBANK', 'AGENCIA' => '0002', 'CONTA' => 'SYN-0002', 'SALDO_INICIAL' => 2000.00],
            ],
            'DESPESAS' => [
                ['COD_DESPESA' => 'EXP-001', 'COD_CATEGORIA' => 'CAT-001', 'DESCRICAO' => 'Synthetic office expense', 'VALOR' => 10.00, 'DATA' => '2026-07-01'],
                ['COD_DESPESA' => 'EXP-002', 'COD_CATEGORIA' => 'CAT-002', 'DESCRICAO' => 'Synthetic energy expense', 'VALOR' => 20.00, 'DATA' => '2026-07-02'],
                ['COD_DESPESA' => 'EXP-003', 'COD_CATEGORIA' => 'CAT-003', 'DESCRICAO' => 'Synthetic materials expense', 'VALOR' => 30.00, 'DATA' => '2026-07-03'],
                ['COD_DESPESA' => 'EXP-004', 'COD_CATEGORIA' => 'CAT-001', 'DESCRICAO' => 'Synthetic support expense', 'VALOR' => 40.00, 'DATA' => '2026-07-04'],
                ['COD_DESPESA' => 'EXP-005', 'COD_CATEGORIA' => 'CAT-002', 'DESCRICAO' => 'Synthetic maintenance expense', 'VALOR' => 50.00, 'DATA' => '2026-07-05'],
            ],
        ];
    }
}
