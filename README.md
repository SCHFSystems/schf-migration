# SCHF Migration

Pipeline de migração de dados legados → Bundle `.schf` → SCHF Core.

## Pipeline

```
Inventory → Normalization → DataQuality → Preview → Bundle → Doctor → Import
```

Nunca escreve direto no Core. Toda migração gera um Bundle `.schf`.

## Arquitetura

### Backend (PHP 8.2)
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
docker compose up -d
# Backend: http://localhost:8001
# Frontend: http://localhost:3001
```

## Testes

```bash
vendor/bin/phpunit
# 32 testes unitários passando
```

## Pipeline Detalhada

| Fase | Descrição | Status |
|------|-----------|--------|
| Inventory | Detecta schema de tabelas, colunas, PKs, FKs | ✅ |
| Normalization | Mapeia dados via perfis (FirebirdFinanceProfile) | ✅ |
| DataQuality | 5 verificações: empty names, invalid dates, negatives, dupes, orphans | ✅ |
| Preview | Consolida preview com totais, warnings, erros | ✅ Sprint 5 |
| Bundle | Gera ZIP com manifest + checksums | 🔄 Sprint 6 |
| Rollback | Reversão de dados importados | ⚠️ Parcial |

## API Endpoints

`POST/GET /api/projects` — CRUD de projetos de migração
`POST /api/projects/{id}/prepare` — Inventory
`POST /api/projects/{id}/normalization/run` — Normalização
`GET /api/projects/{id}/preview/result` — Preview
`POST /api/projects/{id}/bundle/export` — Bundle
`POST /api/projects/{id}/ai/analyze` — AI analysis

## Segurança

- API keys criptografadas via SecretManager (AES-256-CBC)
- Gitleaks: 0 leaks
- Migration nunca escreve no Core

## Licença

Proprietary — SCHF System
