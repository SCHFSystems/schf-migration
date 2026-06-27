<?php

namespace App\Services;

use App\Models\MigrationApiKey;
use App\Models\MigrationProject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoreApiClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.schf_core.url', 'http://localhost:8000'), '/');
        $this->apiKey = config('services.schf_core.api_key', '');
    }

    public function setProject(MigrationProject $project): self
    {
        $key = $project->apiKeys()->where('is_active', true)->first();

        if ($key) {
            $this->apiKey = $key->key;
            $key->recordUsage();
        }

        return $this;
    }

    public function authenticate(): array
    {
        try {
            $response = $this->makeRequest('POST', '/api/auth/migration', [
                'api_key' => $this->apiKey,
            ]);

            return [
                'success' => true,
                'token' => $response['token'] ?? null,
                'expires_at' => $response['expires_at'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Core API authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function validateConnection(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/health');

            return [
                'success' => true,
                'status' => $response['status'] ?? 'unknown',
                'version' => $response['version'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function importRecords(string $tableName, array $records, array $options = []): array
    {
        try {
            $payload = [
                'table' => $tableName,
                'records' => $records,
                'options' => array_merge([
                    'validate' => true,
                    'upsert' => true,
                    'batch_size' => 100,
                ], $options),
            ];

            $response = $this->makeRequest('POST', '/api/migration/import', $payload);

            return [
                'success' => true,
                'imported' => $response['imported'] ?? 0,
                'skipped' => $response['skipped'] ?? 0,
                'failed' => $response['failed'] ?? 0,
                'errors' => $response['errors'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Core API import failed', [
                'table' => $tableName,
                'record_count' => count($records),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getStatus(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/migration/status');

            return [
                'success' => true,
                'status' => $response['status'] ?? 'unknown',
                'pending_imports' => $response['pending_imports'] ?? 0,
                'last_import_at' => $response['last_import_at'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSchema(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/migration/schema');

            return [
                'success' => true,
                'tables' => $response['tables'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function rollback(string $migrationId): array
    {
        try {
            $response = $this->makeRequest('POST', "/api/migration/{$migrationId}/rollback");

            return [
                'success' => true,
                'reverted' => $response['reverted'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $http = Http::withHeaders($headers)->timeout(60);

        $response = match ($method) {
            'GET' => $http->get($url),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'DELETE' => $http->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            throw new \RuntimeException(
                'Core API request failed: ' . $response->body(),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }
}
