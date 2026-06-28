# SCHF Migration

Standalone Laravel + React framework for generating universal Migration Bundles accepted by SCHF Core.

## Overview

SCHF Migration handles the complete data migration workflow:

1. **Connect** to legacy data sources (Firebird, MySQL, SQL Server, PostgreSQL, Oracle, ZIP/CSV)
2. **Detect** database structure automatically
3. **Analyze** data with AI-powered field mapping suggestions
4. **Validate** data before import
5. **Preview** transformed data
6. **Export** a universal Migration Bundle with manifest, checksums, and report
7. **Import** the Bundle in SCHF Core using the Core import module

SCHF Migration never writes directly to the SCHF Core database.

## Architecture

### Backend (Laravel)
- **Models**: MigrationProject, MigrationImport, MigrationRecord, MigrationReport, MigrationApiKey, AiConfig
- **Services**: MigrationEngine, DataNormalizer, AiNormalizer, MigrationValidator, MigrationRollback, MigrationReporter, MigrationBundleExporter, CoreApiClient
- **Source Detectors**: FirebirdDetector, MysqlDetector, ZipDetector (implements DatabaseDetectorInterface)

### Frontend (React + Vite + Tailwind)
- **Pages**: Dashboard, ProjectList, ProjectCreate, ProjectDetail, SourceConfig, AiConfig, PreviewData, MigrationProgress, MigrationReport
- **Components**: WorkflowStepper, DataTable, ValidationResults, ImportProgress
- **Hooks**: useMigration (React Query hooks)
- **Services**: migrationApi (Axios client)

## Requirements

- PHP 8.2+
- Node.js 18+
- PostgreSQL 14+ (or SQLite for development)
- Redis

## Docker Setup

```bash
# Clone and setup
cd schf-migration
cp .env.example .env

# Generate app key
docker compose run --rm backend php artisan key:generate

# Start services
docker compose up -d

# Run migrations
docker compose run --rm backend php artisan migrate

# Install frontend dependencies (first time)
docker compose run --rm frontend npm install

# Access the application
# Frontend: http://localhost:3001
# Backend API: http://localhost:8001/api
```

## Manual Setup

### Backend

```bash
cd backend

# Install dependencies
composer install

# Setup environment
cp ../.env.example ../.env
# Edit .env with your database credentials

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve --port=8001
```

### Frontend

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev -- --port=3001
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/projects | List all migration projects |
| POST | /api/projects | Create new project |
| GET | /api/projects/{id} | Get project details |
| PUT | /api/projects/{id} | Update project |
| DELETE | /api/projects/{id} | Delete project |
| POST | /api/projects/{id}/prepare | Start source detection |
| POST | /api/projects/{id}/validate | Validate data |
| GET | /api/projects/{id}/preview | Preview data |
| POST | /api/projects/{id}/migrate | Legacy internal normalization flow (does not write to Core) |
| POST | /api/projects/{id}/rollback | Rollback migration |
| GET | /api/projects/{id}/report | Get migration report |
| GET | /api/projects/{id}/bundle/preview | Preview Migration Bundle contents |
| POST | /api/projects/{id}/bundle/export | Export migration-package.zip |
| GET | /api/projects/{id}/bundle/download | Download latest exported Bundle |
| POST | /api/projects/{id}/ai/analyze | AI field analysis |
| GET/POST | /api/projects/{id}/ai-config | AI configuration |
| GET/POST | /api/projects/{id}/api-keys | API keys |

## Supported Sources

- **Firebird**: Full support with Firebird PHP extension
- **MySQL**: Via PDO MySQL
- **SQL Server**: Via PDO SQLSRV (manual extension install)
- **PostgreSQL**: Via PDO PgSQL
- **Oracle**: Via PDO OCI (manual extension install)
- **ZIP/CSV**: Auto-detect CSV files in ZIP archives

## AI Integration

AI only supports the operator. It may identify tables, propose mappings, normalize names, and explain legacy structures. It never writes to SCHF Core and never bypasses the deterministic export pipeline.

Configure AI providers for intelligent field mapping:

- **OpenAI**: GPT-4o, GPT-4
- **NVIDIA NIM**: Nemotron models
- **GLM**: GLM-4
- **MiniMax**: abab models
- **Kimi**: Moonshot models
- **Custom**: Any OpenAI-compatible API

## License

Proprietary - SCHF System
