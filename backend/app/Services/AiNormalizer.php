<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\MigrationProject;
use App\Services\SecretManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiNormalizer
{
    private SecretManager $secretManager;

    public function __construct()
    {
        $this->secretManager = new SecretManager();
    }

    private array $providerEndpoints = [
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'nvidia' => 'https://integrate.api.nvidia.com/v1/chat/completions',
        'glm' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
        'minimax' => 'https://api.minimax.chat/v1/text/chatcompletion_v2',
        'kimi' => 'https://api.moonshot.cn/v1/chat/completions',
    ];

    public function analyzeFields(array $sampleData, string $tableName, MigrationProject $project): array
    {
        $aiConfig = $this->getActiveConfig($project);

        if (! $aiConfig) {
            return ['error' => 'No AI configuration found'];
        }

        $prompt = $this->buildAnalysisPrompt($sampleData, $tableName);

        try {
            $response = $this->callAiProvider($aiConfig, $prompt);

            return $this->parseAnalysisResponse($response, $tableName);
        } catch (\Exception $e) {
            Log::error('AI field analysis failed', [
                'project_id' => $project->id,
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function suggestMapping(array $sourceColumns, array $targetSchema, MigrationProject $project): array
    {
        $aiConfig = $this->getActiveConfig($project);

        if (! $aiConfig) {
            return ['error' => 'No AI configuration found'];
        }

        $prompt = $this->buildMappingPrompt($sourceColumns, $targetSchema);

        try {
            $response = $this->callAiProvider($aiConfig, $prompt);

            return $this->parseMappingResponse($response);
        } catch (\Exception $e) {
            Log::error('AI mapping suggestion failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function suggestTransformations(array $columnSample, string $targetType, MigrationProject $project): array
    {
        $aiConfig = $this->getActiveConfig($project);

        if (! $aiConfig) {
            return ['error' => 'No AI configuration found'];
        }

        $prompt = $this->buildTransformationPrompt($columnSample, $targetType);

        try {
            $response = $this->callAiProvider($aiConfig, $prompt);

            return $this->parseTransformationResponse($response);
        } catch (\Exception $e) {
            Log::error('AI transformation suggestion failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function getActiveConfig(MigrationProject $project): ?AiConfig
    {
        return AiConfig::where('migration_project_id', $project->id)
            ->where('is_active', true)
            ->first();
    }

    private function callAiProvider(AiConfig $config, string $prompt): string
    {
        $endpoint = $this->providerEndpoints[$config->provider] ?? $config->provider;

        if ($config->provider === 'custom') {
            $endpoint = data_get($config->toArray(), 'custom_endpoint', '');
        }

        $apiKey = $this->secretManager->decrypt($config->api_key_encrypted);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($endpoint, [
            'model' => $config->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $config->system_prompt ?? $this->getDefaultSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $config->temperature ?? 0.3,
            'max_tokens' => $config->max_tokens ?? 2000,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('AI provider returned error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }

    private function buildAnalysisPrompt(array $sampleData, string $tableName): string
    {
        $sampleJson = json_encode(array_slice($sampleData, 0, 5), JSON_PRETTY_PRINT);

        return "Analyze the following data sample from table '{$tableName}' and suggest:
1. Data types for each column
2. Potential data quality issues
3. Recommended transformations
4. Primary key candidates

Data sample:
{$sampleJson}

Respond in JSON format with:
{
  \"columns\": {
    \"column_name\": {
      \"detected_type\": \"string|integer|float|date|boolean\",
      \"quality_score\": 0-100,
      \"issues\": [\"issue1\"],
      \"transform_suggestion\": \"none|trim|uppercase|lowercase|date_format|number_format\"
    }
  },
  \"primary_key_candidates\": [\"column1\"],
  \"data_quality_overall\": 0-100
}";
    }

    private function buildMappingPrompt(array $sourceColumns, array $targetSchema): string
    {
        $sourceJson = json_encode($sourceColumns, JSON_PRETTY_PRINT);
        $targetJson = json_encode($targetSchema, JSON_PRETTY_PRINT);

        return "Map the source columns to the target schema fields.

Source columns:
{$sourceJson}

Target schema:
{$targetJson}

Respond in JSON format with:
{
  \"mappings\": {
    \"source_column\": {
      \"target_field\": \"target_field_name\",
      \"confidence\": 0.0-1.0,
      \"transform_needed\": \"none|trim|uppercase|lowercase|date_format|number_format\",
      \"notes\": \"optional mapping notes\"
    }
  },
  \"unmapped_source\": [\"columns without matches\"],
  \"unmatched_target\": [\"target fields without source\"]
}";
    }

    private function buildTransformationPrompt(array $columnSample, string $targetType): string
    {
        $sampleJson = json_encode(array_slice($columnSample, 0, 10), JSON_PRETTY_PRINT);

        return "Suggest transformations to convert these values to type '{$targetType}'.

Sample values:
{$sampleJson}

Respond in JSON format with:
{
  \"recommended_transform\": \"none|trim|uppercase|lowercase|date_format|number_format|custom\",
  \"custom_transform\": \"optional PHP expression\",
  \"validation_regex\": \"optional validation pattern\",
  \"default_value\": \"optional default for null values\",
  \"notes\": \"explanation\"
}";
    }

    private function parseAnalysisResponse(string $response, string $tableName): array
    {
        $json = $this->extractJson($response);

        return [
            'table' => $tableName,
            'columns' => data_get($json, 'columns', []),
            'primary_key_candidates' => data_get($json, 'primary_key_candidates', []),
            'data_quality_overall' => data_get($json, 'data_quality_overall', 0),
        ];
    }

    private function parseMappingResponse(string $response): array
    {
        $json = $this->extractJson($response);

        return [
            'mappings' => data_get($json, 'mappings', []),
            'unmapped_source' => data_get($json, 'unmapped_source', []),
            'unmatched_target' => data_get($json, 'unmatched_target', []),
        ];
    }

    private function parseTransformationResponse(string $response): array
    {
        $json = $this->extractJson($response);

        return [
            'transform' => data_get($json, 'recommended_transform', 'none'),
            'custom_transform' => data_get($json, 'custom_transform'),
            'validation_regex' => data_get($json, 'validation_regex'),
            'default_value' => data_get($json, 'default_value'),
            'notes' => data_get($json, 'notes', ''),
        ];
    }

    private function extractJson(string $text): array
    {
        $jsonMatch = [];
        preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $jsonMatch);

        if (! empty($jsonMatch[1])) {
            return json_decode(trim($jsonMatch[1]), true) ?? [];
        }

        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

            return json_decode($jsonStr, true) ?? [];
        }

        return [];
    }

    private function getDefaultSystemPrompt(): string
    {
        return 'You are a data migration expert specializing in SCHF (Sistema de Controle de Hold Freights) data normalization. You analyze legacy database structures and suggest mappings and transformations for migration into the SCHF Core system. Always respond in valid JSON format.';
    }
}
