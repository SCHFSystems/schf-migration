# Firebird Laboratory

Sprint 12 validates Firebird with a small disposable lab database only. Do not use customer databases, backups, archives, or secure storage paths with this lab.

## Services

Use the base compose file plus the lab override:

```bash
docker compose -f docker-compose.yml -f docker-compose.firebird-lab.yml up -d --build firebird-lab migration-backend
```

The override keeps real connectors opt-in:

- `MIGRATION_SYNTHETIC_ONLY=false`
- `FEATURE_REAL_CONNECTORS=true`

Lab-only Firebird defaults:

- Host: `firebird-lab`
- Port: `3050`
- Database: `/var/lib/firebird/data/lab_schf.fdb`
- User: `${FIREBIRD_LAB_USER:-schf_lab_user}`
- Password: `${FIREBIRD_LAB_PASSWORD:-schf_lab_password_change_me}`

These are fake local credentials. Override them in a local environment file when needed. Do not use production credentials.

## Schema And Seed

Lab SQL files live in `database/lab/firebird/`:

- `01_create_schema.sql`
- `02_seed_data.sql`
- `03_drop_schema.sql`

Initialize the schema after the `firebird-lab` container is healthy:

```bash
docker compose -f docker-compose.yml -f docker-compose.firebird-lab.yml exec -T firebird-lab /opt/firebird/bin/isql -u schf_lab_user -p schf_lab_password_change_me /var/lib/firebird/data/lab_schf.fdb < database/lab/firebird/01_create_schema.sql
docker compose -f docker-compose.yml -f docker-compose.firebird-lab.yml exec -T firebird-lab /opt/firebird/bin/isql -u schf_lab_user -p schf_lab_password_change_me /var/lib/firebird/data/lab_schf.fdb < database/lab/firebird/02_seed_data.sql
```

Expected source counts:

| Table | Rows |
| --- | ---: |
| `EMPRESA` | 1 |
| `FORNECEDOR` | 2 |
| `CATEGORIA` | 2 |
| `CONTA_BANCARIA` | 2 |
| `TITULO_PAGAR` | 3 |
| `DESPESA` | 2 |
| `USUARIO` | 1 |

## Migration Project Config

Use `source_type=firebird` with the lab profile:

```json
{
  "name": "Firebird Lab Pipeline",
  "source_type": "firebird",
  "source_config": {
    "profile": "firebird-lab-finance",
    "host": "firebird-lab",
    "port": 3050,
    "database": "/var/lib/firebird/data/lab_schf.fdb",
    "username": "schf_lab_user",
    "password": "<lab password from docker-compose.firebird-lab.yml>",
    "charset": "UTF8"
  }
}
```

Run the normal pipeline:

```text
inventory/generate -> normalization/run -> quality/run -> preview/generate -> bundle/export
```

Expected normalized counts:

- Organizations: 1
- Suppliers: 2
- Categories: 2
- Accounts: 2
- Payments: 3
- Expenses: 2
- Users: 1

## Bundle Validation

Validate the exported package with the SDK:

```bash
php ../schf-sdk/bin/schf bundle validate firebird-lab-package.schf
php ../schf-sdk/bin/schf bundle doctor firebird-lab-package.schf --deep
php ../schf-sdk/bin/schf bundle inspect firebird-lab-package.schf
php ../schf-sdk/bin/schf bundle info firebird-lab-package.schf
```
