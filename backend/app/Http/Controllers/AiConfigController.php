<?php

namespace App\Http\Controllers;

use App\Models\AiConfig;
use App\Models\MigrationApiKey;
use App\Models\MigrationProject;
use App\Services\AiNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiConfigController extends Controller
{
    public function __construct(
        private AiNormalizer $aiNormalizer,
    ) {}

    public function index(MigrationProject $project): JsonResponse
    {
        $configs = $project->aiConfigs;

        return response()->json($configs);
    }

    public function store(Request $request, MigrationProject $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:' . implode(',', AiConfig::PROVIDERS),
            'api_key' => 'required|string',
            'model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:128000',
            'system_prompt' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        AiConfig::where('migration_project_id', $project->id)
            ->update(['is_active' => false]);

        $config = AiConfig::create([
            'migration_project_id' => $project->id,
            'provider' => $request->input('provider'),
            'api_key_encrypted' => $request->input('api_key'),
            'model' => $request->input('model') ?? AiConfig::DEFAULT_MODELS[$request->input('provider')],
            'temperature' => $request->input('temperature', 0.3),
            'max_tokens' => $request->input('max_tokens', 4096),
            'system_prompt' => $request->input('system_prompt'),
            'is_active' => true,
        ]);

        return response()->json($config, 201);
    }

    public function show(MigrationProject $project, AiConfig $config): JsonResponse
    {
        if ($config->migration_project_id !== $project->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($config);
    }

    public function update(Request $request, MigrationProject $project, AiConfig $config): JsonResponse
    {
        if ($config->migration_project_id !== $project->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'provider' => 'sometimes|string|in:' . implode(',', AiConfig::PROVIDERS),
            'api_key' => 'sometimes|string',
            'model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:128000',
            'system_prompt' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['api_key'])) {
            $data['api_key_encrypted'] = $data['api_key'];
            unset($data['api_key']);
        }

        if (isset($data['is_active']) && $data['is_active']) {
            AiConfig::where('migration_project_id', $project->id)
                ->where('id', '!=', $config->id)
                ->update(['is_active' => false]);
        }

        $config->update($data);

        return response()->json($config);
    }

    public function destroy(MigrationProject $project, AiConfig $config): JsonResponse
    {
        if ($config->migration_project_id !== $project->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $config->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function analyze(Request $request, MigrationProject $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_name' => 'required|string',
            'sample_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->aiNormalizer->analyzeFields(
            $request->input('sample_data'),
            $request->input('table_name'),
            $project
        );

        return response()->json($result);
    }
}
