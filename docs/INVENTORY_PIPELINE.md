# Pipeline de Inventário — SCHF Migration

## Visão Geral

O inventário é a **Etapa 2** do Pipeline Universal de Migração (após a Detecção).  
Ele gera um relatório completo do banco de dados de origem, incluindo:

- Lista de tabelas
- Quantidade de registros por tabela
- Colunas, tipos e nulabilidade
- Chaves primárias e estrangeiras
- Amostra de dados (5 primeiros registros)
- Sumário com totais

## Arquitetura

```
Sistema Legado (Firebird, MySQL, etc.)
      │
      ▼
schf-connectors (Conector específico do banco)
      │
      ▼
ConnectorFactory (schf-migration)
      │
      ▼
InventoryService (schf-migration)
      │
      ▼
MigrationInventoryController (API)
      │
      ▼
Frontend (React) — exibição no ProjectDetail
```

## Componentes

### ConnectorFactory
- **Arquivo:** `backend/app/Services/ConnectorFactory.php`
- **Responsabilidade:** Criar o conector adequado com base no `source_type` do projeto.
- **Método principal:** `make(string $sourceType, array $config): ConnectorInterface`
- **Tipos suportados:** `firebird` (outros em planejamento)

### InventoryService
- **Arquivo:** `backend/app/Services/InventoryService.php`
- **Responsabilidade:** Gerar o inventário a partir de um conector.
- **Método principal:** `generate(ConnectorInterface $connector): array`
- **Estrutura de retorno:**
```json
{
    "generated_at": "2026-06-27T22:00:00+00:00",
    "driver": "firebird",
    "tables": [
        {
            "name": "CLIENTES",
            "row_count": 1500,
            "columns": {
                "COD_CLIENTE": { "type": "integer", "nullable": false, "default": null },
                "NOME": { "type": "string", "nullable": false, "default": null }
            },
            "column_names": ["COD_CLIENTE", "NOME"],
            "primary_keys": ["COD_CLIENTE"],
            "foreign_keys": {},
            "sample": [
                { "COD_CLIENTE": 1, "NOME": "EMPRESA ABC LTDA" }
            ]
        }
    ],
    "summary": {
        "total_tables": 1,
        "total_rows": 1500,
        "total_columns": 2
    }
}
```

### MigrationInventoryController
- **Arquivo:** `backend/app/Http/Controllers/MigrationInventoryController.php`
- **Endpoint:** `POST /api/projects/{project}/inventory/generate`
- **Fluxo:**
  1. Verifica se o projeto tem `source_config`
  2. Cria o conector via `ConnectorFactory`
  3. Gera o inventário via `InventoryService`
  4. Desconecta o conector
  5. Salva o inventário em `source_config.inventory`
  6. Retorna o inventário como JSON

## Frontend

- **Hook:** `useGenerateInventory(projectId)` em `frontend/src/hooks/useMigration.ts`
- **API:** `migrationApi.inventory.generate(projectId)` em `frontend/src/services/migrationApi.ts`
- **Exibição:** Tabela de inventário no `ProjectDetail.tsx`

## Testes

| Teste | Arquivo | Status |
|-------|---------|--------|
| ConnectorFactory: exceção para tipo não suportado | `tests/Unit/ConnectorFactoryTest.php` | ✅ |
| InventoryService: estrutura correta | `tests/Unit/InventoryServiceTest.php` | ✅ |
| InventoryService: schema vazio | `tests/Unit/InventoryServiceTest.php` | ✅ |
| InventoryService: erro na contagem | `tests/Unit/InventoryServiceTest.php` | ✅ |
| InventoryService: truncamento de valores longos | `tests/Unit/InventoryServiceTest.php` | ✅ |
| Feature: endpoint HTTP (requer Laravel) | `tests/Feature/InventoryGenerateTest.php` | ⏳ Placeholder |

## Como Executar

```bash
# Na raiz do schf-migration
composer install
vendor/bin/phpunit
```

## Próximos Passos

1. Instalar Laravel framework e rodar Feature tests
2. Adicionar detectores para MySQL, PostgreSQL, SQL Server, Oracle, SQLite
3. Avançar para as etapas de Mapeamento e Normalização
