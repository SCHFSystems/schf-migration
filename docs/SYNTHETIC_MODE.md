# Synthetic Mode

Sprint 9 runs with synthetic data only.

## Safety Rules

- No real data.
- No Santa Casa data.
- No Firebird connection.
- No `.FDB`, `.FBK`, `.RAR`, or `.ENC` files.
- No writes to SCHF Core.
- Real connectors are blocked when `MIGRATION_SYNTHETIC_ONLY=true`.

## Environment

```env
MIGRATION_SYNTHETIC_ONLY=true
FEATURE_REAL_CONNECTORS=false
```

## Scenarios

`clean`:

- 3 suppliers.
- 3 categories.
- 2 bank accounts.
- 5 payables.
- 5 expenses.
- Expected quality: `passed`.
- Expected preview: `ready`.

`warnings`:

- Same synthetic shape.
- Adds non-blocking negative/invalid-date quality issues.
- Expected quality: `passed_with_warnings`.

`blocked`:

- Same synthetic shape.
- Adds blocking duplicate/empty-name issues.
- Expected preview: `blocked`.

## Create Project

```http
POST /api/projects
```

```json
{
  "name": "Synthetic Project",
  "source_type": "synthetic",
  "source_config": {
    "scenario": "clean",
    "organization": {
      "external_id": "ORG-SYN",
      "name": "Synthetic Organization"
    }
  }
}
```

The synthetic source uses placeholder documents such as `SYN-DOC-001`, not real CPF/CNPJ values.
