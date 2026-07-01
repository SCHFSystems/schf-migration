<?php

declare(strict_types=1);

namespace App\Normalization\Profiles;

use SCHF\SDK\Normalization\MappingProfile;
use SCHF\SDK\Normalization\MappingRule;
use SCHF\SDK\Normalization\NormalizedBankAccount;
use SCHF\SDK\Normalization\NormalizedCategory;
use SCHF\SDK\Normalization\NormalizedExpense;
use SCHF\SDK\Normalization\NormalizedOrganization;
use SCHF\SDK\Normalization\NormalizedPayable;
use SCHF\SDK\Normalization\NormalizedSupplier;
use SCHF\SDK\Normalization\NormalizedUser;

class FirebirdLabFinanceProfile
{
    /**
     * @return MappingProfile[]
     */
    public static function all(): array
    {
        return [
            self::organizationProfile(),
            self::supplierProfile(),
            self::categoryProfile(),
            self::accountProfile(),
            self::payableProfile(),
            self::expenseProfile(),
            self::userProfile(),
        ];
    }

    public static function organizationProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-organizations',
            version: '1.0.0',
            source_table: 'EMPRESA',
            target_class: NormalizedOrganization::class,
            target_entity: 'organizations',
            description: 'Maps Firebird Lab company to SCHF organization',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:ORG-', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('NOME', 'legal_name', 'trim'),
            ],
        );
    }

    public static function supplierProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-suppliers',
            version: '1.0.0',
            source_table: 'FORNECEDOR',
            target_class: NormalizedSupplier::class,
            target_entity: 'suppliers',
            description: 'Maps Firebird Lab suppliers',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:SUP-', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('DOCUMENTO', 'document', 'trim'),
                new MappingRule('ATIVO', 'active', 'boolean'),
            ],
        );
    }

    public static function categoryProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-categories',
            version: '1.0.0',
            source_table: 'CATEGORIA',
            target_class: NormalizedCategory::class,
            target_entity: 'categories',
            description: 'Maps Firebird Lab categories',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:CAT-', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('TIPO', 'type', 'lower', default: 'expense'),
            ],
        );
    }

    public static function accountProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-accounts',
            version: '1.0.0',
            source_table: 'CONTA_BANCARIA',
            target_class: NormalizedBankAccount::class,
            target_entity: 'accounts',
            description: 'Maps Firebird Lab bank accounts',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:ACC-', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('TIPO_CONTA', 'type', 'lower', default: 'bank'),
                new MappingRule('BANCO', 'bank_name', 'trim'),
                new MappingRule('AGENCIA', 'branch', 'trim'),
                new MappingRule('CONTA', 'account_number', 'trim'),
            ],
        );
    }

    public static function payableProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-payables',
            version: '1.0.0',
            source_table: 'TITULO_PAGAR',
            target_class: NormalizedPayable::class,
            target_entity: 'payables',
            description: 'Maps Firebird Lab payables',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:PAY-', required: true),
                new MappingRule('DIRECTION', 'direction', 'lower', default: 'payable'),
                new MappingRule('FORNECEDOR_ID', 'supplier_external_id', 'prefix:SUP-'),
                new MappingRule('CATEGORIA_ID', 'category_external_id', 'prefix:CAT-'),
                new MappingRule('CONTA_ID', 'account_external_id', 'prefix:ACC-'),
                new MappingRule('DESCRICAO', 'description', 'trim'),
                new MappingRule('VALOR', 'amount', 'number', required: true),
                new MappingRule('VENCIMENTO', 'due_date', 'date', required: true),
                new MappingRule('STATUS', 'status', 'lower', default: 'pending'),
            ],
        );
    }

    public static function expenseProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-expenses',
            version: '1.0.0',
            source_table: 'DESPESA',
            target_class: NormalizedExpense::class,
            target_entity: 'expenses',
            description: 'Maps Firebird Lab expenses',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:EXP-', required: true),
                new MappingRule('CATEGORIA_ID', 'category_external_id', 'prefix:CAT-'),
                new MappingRule('DESCRICAO', 'description', 'trim'),
                new MappingRule('VALOR', 'amount', 'number', required: true),
                new MappingRule('DATA_DESPESA', 'date', 'date', required: true),
                new MappingRule('STATUS', 'status', 'lower', default: 'posted'),
            ],
        );
    }

    public static function userProfile(): MappingProfile
    {
        return new MappingProfile(
            source_type: 'firebird',
            profile: 'firebird-lab-finance-users',
            version: '1.0.0',
            source_table: 'USUARIO',
            target_class: NormalizedUser::class,
            target_entity: 'users',
            description: 'Maps Firebird Lab users',
            rules: [
                new MappingRule('ID', 'external_id', 'prefix:USR-', required: true),
                new MappingRule('NOME', 'name', 'trim', required: true),
                new MappingRule('EMAIL', 'email', 'trim', required: true),
                new MappingRule('PAPEIS', 'roles', 'split'),
                new MappingRule('ATIVO', 'active', 'boolean'),
            ],
        );
    }
}
