# Migration Runtime

## Runtime Choice

The backend is a real Laravel 12 API.

This was selected because the repository already used Eloquent models, HTTP controllers, routes, migrations, validation helpers and feature-test concepts.

## Local Commands

```bash
composer install
vendor/bin/phpunit
```

Frontend:

```bash
cd frontend
npm install
npm run test
npm run typecheck
npm run build
```

## Docker Commands

```bash
docker compose up -d --build
docker compose ps
docker compose exec -T migration-backend php artisan migrate --force
docker compose exec -T migration-backend php artisan migrate:status
```

Services:

- `migration-backend`: Laravel API at `http://localhost:8001`.
- `migration-frontend`: Vite frontend at `http://localhost:3001`.
- `migration-postgres`: PostgreSQL exposed on `localhost:5433`.
- `migration-redis`: Redis exposed on `localhost:6380`.

## Health Check

```http
GET /api/health
```

Expected response:

```json
{
  "status": "ok",
  "system": "SCHF Migration"
}
```

## Composer Path Repository Note

`schf-migration` depends on the local `../schf-sdk` path repository. The Docker backend image uses the locally installed `vendor/` directory during build, so run `composer install` before `docker compose up -d --build`.
