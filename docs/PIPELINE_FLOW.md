# Pipeline Flow

Sprint 9 validates this synthetic-only flow:

```text
Create Project
-> Generate Inventory
-> Normalize
-> Run Data Quality
-> Generate Preview
```

## Endpoints

```http
GET /api/health

GET /api/projects
POST /api/projects

POST /api/projects/{id}/inventory/generate
GET /api/projects/{id}/inventory

POST /api/projects/{id}/normalization/run
GET /api/projects/{id}/normalization

POST /api/projects/{id}/quality/run
GET /api/projects/{id}/quality

POST /api/projects/{id}/preview/generate
GET /api/projects/{id}/preview/result
```

## Persistence

Project metadata is persisted in `migration_projects`.

Pipeline results are persisted in `migration_projects.source_config`:

- `inventory`
- `detected_structure`
- `normalized_bundle`
- `quality`
- `preview`
- `pipeline_logs`

Preview rows are also persisted in `migration_previews`.

## Status Updates

- Inventory sets project status to `preparing`.
- Normalization sets project status to `validating`.
- DataQuality sets project status to `previewing`.
- Preview remains pre-import and does not write to Core.

## Synthetic Success Criteria

For `scenario=clean`:

- Inventory has 5 tables and 18 rows.
- Normalization has 3 suppliers, 3 categories, 2 accounts, 5 payables and 5 expenses.
- DataQuality status is `passed`.
- Preview status is `ready` and `ready_for_bundle=true`.
