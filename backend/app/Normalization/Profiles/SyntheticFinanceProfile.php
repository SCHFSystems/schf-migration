<?php

declare(strict_types=1);

namespace App\Normalization\Profiles;

use SCHF\SDK\Normalization\MappingProfile;
use SCHF\SDK\Normalization\MappingRule;
use SCHF\SDK\Normalization\NormalizedBankAccount;
use SCHF\SDK\Normalization\NormalizedCategory;
use SCHF\SDK\Normalization\NormalizedExpense;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedSupplier;

class SyntheticFinanceProfile
{
    /**
     * @return MappingProfile[]
     */
    public static function all(): array
    {
        return [
            self::supplierProfile(),
            self::payableProfile(),
            self::categoryProfile(),
            self::accountProfile(),
            self::expenseProfile(),
        ];
    }

    public static function supplierProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'synthetic',
            profile: 'synthetic-suppliers',
            version: '1.0.0',
            source_table: 'FORNECEDOR',
            target_class: NormalizedSupplier::class,
            target_entity: 'suppliers',
            description: 'Maps synthetic suppliers to SCHF suppliers',
            rules: [
                new MappingRule('COD_FORNECEDOR', 'external_id', 'trim', required: true),
                new MappingRule('NOME_FANTASIA', 'name', 'trim', required: true),
                new MappingRule('RAZAO_SOCIAL', 'legal_name', 'trim'),
                new MappingRule('CNPJ_CPF', 'document', 'trim'),
                new MappingRule('ATIVO', 'active', 'upper'),
            ],
        );
    }

    public static function payableProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'synthetic',
            profile: 'synthetic-payables',
            version: '1.0.0',
            source_table: 'CONTAS_RECEBER_PAGAR',
            target_class: NormalizedPayable::class,
            target_entity: 'payables',
            description: 'Maps synthetic payables',
            rules: [
                new MappingRule('COD_LANCAMENTO', 'external_id', 'trim', required: true),
                new MappingRule('COD_FORNECEDOR', 'supplier_external_id', 'trim'),
                new MappingRule('COD_CATEGORIA', 'category_external_id', 'trim'),
                new MappingRule('DESCRICAO', 'description', 'trim'),
                new MappingRule('VALOR', 'amount', 'number', required: true),
                new MappingRule('DATA_VENCIMENTO', 'due_date', 'date', required: true),
                new MappingRule('DATA_PAGAMENTO', 'paid_at', 'date'),
            ],
        );
    }

    public static function categoryProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'synthetic',
            profile: 'synthetic-categories',
            version: '1.0.0',
            source_table: 'CATEGORIA',
            target_class: NormalizedCategory::class,
            target_entity: 'categories',
            description: 'Maps synthetic categories',
            rules: [
                new MappingRule('COD_CATEGORIA', 'external_id', 'trim', required: true),
                new MappingRule('DESCRICAO', 'name', 'trim', required: true),
                new MappingRule('TIPO', 'type', 'trim'),
            ],
        );
    }

    public static function accountProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'synthetic',
            profile: 'synthetic-bank-accounts',
            version: '1.0.0',
            source_table: 'CONTAS_BANCARIAS',
            target_class: NormalizedBankAccount::class,
            target_entity: 'accounts',
            description: 'Maps synthetic bank accounts',
            rules: [
                new MappingRule('COD_CONTA', 'external_id', 'trim', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('BANCO', 'bank_name', 'trim'),
                new MappingRule('AGENCIA', 'branch', 'trim'),
                new MappingRule('CONTA', 'account_number', 'trim'),
                new MappingRule('SALDO_INICIAL', 'opening_balance', 'number'),
            ],
        );
    }

    public static function expenseProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'synthetic',
            profile: 'synthetic-expenses',
            version: '1.0.0',
            source_table: 'DESPESAS',
            target_class: NormalizedExpense::class,
            target_entity: 'expenses',
            description: 'Maps synthetic expenses',
            rules: [
                new MappingRule('COD_DESPESA', 'external_id', 'trim', required: true),
                new MappingRule('COD_CATEGORIA', 'category_external_id', 'trim'),
                new MappingRule('DESCRICAO', 'description', 'trim'),
                new MappingRule('VALOR', 'amount', 'number', required: true),
                new MappingRule('DATA', 'date', 'date', required: true),
            ],
        );
    }
}
