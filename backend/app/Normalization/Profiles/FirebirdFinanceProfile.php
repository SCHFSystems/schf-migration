<?php

declare(strict_types=1);

namespace App\Normalization\Profiles;

use SCHF\SDK\Normalization\MappingProfile;
use SCHF\SDK\Normalization\MappingRule;
use SCHF\SDK\Normalization\NormalizedSupplier;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedCategory;

class FirebirdFinanceProfile
{
    /**
     * @return MappingProfile[]
     */
    public static function all(): array
    {
        return [
            self::fornecedorProfile(),
            self::contasPagarProfile(),
            self::categoriaProfile(),
        ];
    }

    public static function fornecedorProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile:     'generic-firebird-suppliers',
            version:     '0.1.0',
            source_table: 'FORNECEDOR',
            target_class: NormalizedSupplier::class,
            target_entity: 'suppliers',
            description:  'Maps Firebird FORNECEDOR table to SCHF suppliers',
            rules: [
                new MappingRule(
                    source_column: 'COD_FORNECEDOR',
                    target_field:  'external_id',
                    transform:     'trim',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'NOME_FANTASIA',
                    target_field:  'name',
                    transform:     'trim',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'RAZAO_SOCIAL',
                    target_field:  'name',
                    transform:     'trim',
                    description:   'Fallback: uso RZ_SOCIAL if NOME_FANTASIA is empty',
                ),
                new MappingRule(
                    source_column: 'CNPJ_CPF',
                    target_field:  'document',
                    transform:     'trim',
                ),
                new MappingRule(
                    source_column: 'ATIVO',
                    target_field:  'active',
                    transform:     'upper',
                ),
            ],
        );
    }

    public static function contasPagarProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile:     'generic-firebird-payables',
            version:     '0.1.0',
            source_table: 'CONTAS_RECEBER_PAGAR',
            target_class: NormalizedPayable::class,
            target_entity: 'payables',
            description:  'Maps Firebird CONTAS_RECEBER_PAGAR table to SCHF payables',
            rules: [
                new MappingRule(
                    source_column: 'COD_LANCAMENTO',
                    target_field:  'external_id',
                    transform:     'trim',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'COD_FORNECEDOR',
                    target_field:  'supplier_external_id',
                    transform:     'trim',
                ),
                new MappingRule(
                    source_column: 'DESCRICAO',
                    target_field:  'description',
                    transform:     'trim',
                ),
                new MappingRule(
                    source_column: 'VALOR',
                    target_field:  'amount',
                    transform:     'number',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'DATA_VENCIMENTO',
                    target_field:  'due_date',
                    transform:     'date',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'DATA_PAGAMENTO',
                    target_field:  'paid_at',
                    transform:     'date',
                ),
            ],
        );
    }

    public static function categoriaProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type:   'firebird',
            profile:       'generic-firebird-categories',
            version:       '0.1.0',
            source_table:  'CATEGORIA',
            target_class:  NormalizedCategory::class,
            target_entity: 'categories',
            description:   'Maps Firebird CATEGORIA table to SCHF categories',
            rules: [
                new MappingRule(
                    source_column: 'COD_CATEGORIA',
                    target_field:  'external_id',
                    transform:     'trim',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'DESCRICAO',
                    target_field:  'name',
                    transform:     'trim',
                    required:      true,
                ),
                new MappingRule(
                    source_column: 'TIPO',
                    target_field:  'type',
                ),
            ],
        );
    }
}
