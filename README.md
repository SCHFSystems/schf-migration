# SCHF Migration

Pipeline local de migração sintética para validar Inventory -> Normalization -> DataQuality -> Preview.

## Pipeline

```
Inventory -> Normalization -> DataQuality -> Preview -> Bundle -> Doctor -> Import
```

Nunca escreve direto no Core. Toda migração gera um Bundle `.schf`.

Sprint 9 opera em modo synthetic-only: sem dados reais, sem Santa Casa e sem Firebird real.

## Arquitetura

### Backend (PHP 8.2+ / Laravel 12)
- **Models**: MigrationProject, MigrationImport, MigrationRecord, MigrationReport, MigrationPreview, MigrationApiKey, AiConfig
- **Services**: MigrationEngine (12 services), InventoryService, NormalizationService, DataQualityService, MappingProfileRegistry, MigrationPreviewService, MigrationBundleExporter, SecretManager, AiNormalizer
- **Controllers**: 9 controllers (~30 endpoints)
- **Source**: ConnectorInterface (via schf-sdk) + SourceDetectors

### Frontend (React + Vite + Tailwind)
- **Pages**: Dashboard, ProjectList, ProjectDetail, PreviewPage, AiConfig
- **Components**: WorkflowStepper, DataTable, ValidationResults, ImportProgress
- **Hooks**: useMigration (17 hooks via React Query)

## Docker

```bash
composer install
cd frontend && npm install && cd ..
docker compose up -d
docker compose exec -T migration-backend php artisan migrate --force
# Backend: http://localhost:8001
# Frontend: http://localhost:3001
```

## Testes

```bash
vendor/bin/phpunit
cd frontend
npm run test
npm run typecheck
npm run build
```

## Synthetic Quick Start

```bash
curl http://localhost:8001/api/health

curl -X POST http://localhost:8001/api/projects \
  -H "Content-Type: application/json" \
  -d '{"name":"Synthetic Project","source_type":"synthetic","source_config":{"scenario":"clean"}}'
```

Available scenarios: `clean`, `warnings`, `blocked`.

## Pipeline Detalhada

| Fase | Descrição | Status |
|------|-----------|--------|
| Inventory | Detecta schema de tabelas, colunas, PKs, FKs | ✅ |
| Normalization | Mapeia dados via perfis (FirebirdFinanceProfile) | ✅ |
| DataQuality | 5 verificações: empty names, invalid dates, negatives, dupes, orphans | ✅ |
| Preview | Consolida preview com totais, warnings, erros | ✅ Sprint 5 |
| Bundle | Gera ZIP com manifest + checksums | Fora da Sprint 9 |
| Rollback | Reversão de dados importados | ⚠️ Parcial |

## API Endpoints

`POST/GET /api/projects` — CRUD de projetos de migração
`POST /api/projects/{id}/inventory/generate` — Inventory sintético
`GET /api/projects/{id}/inventory` — Inventory persistido
`POST /api/projects/{id}/normalization/run` — Normalização
`GET /api/projects/{id}/normalization` — Normalização persistida
`POST /api/projects/{id}/quality/run` — DataQuality
`GET /api/projects/{id}/quality` — DataQuality persistida
`POST /api/projects/{id}/preview/generate` — Preview
`GET /api/projects/{id}/preview/result` — Preview

## Segurança

- API keys criptografadas via SecretManager (AES-256-CBC)
- Gitleaks: 0 leaks
- Migration nunca escreve no Core
- `MIGRATION_SYNTHETIC_ONLY=true` bloqueia conectores reais e `/migrate`

## Docs

- `docs/SYNTHETIC_MODE.md`
- `docs/MIGRATION_RUNTIME.md`
- `docs/PIPELINE_FLOW.md`

## Licença

Proprietary — SCHF System
