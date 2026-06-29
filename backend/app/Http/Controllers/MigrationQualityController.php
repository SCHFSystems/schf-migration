<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Normalization\DataQualityService;
use App\Normalization\NormalizationResultSerializer;
use Illuminate\Http\JsonResponse;

class MigrationQualityController
{
    public function __construct(
        private DataQualityService $qualityService,
        private NormalizationResultSerializer $serializer,
    ) {}

    public function run(MigrationProject $project): JsonResponse
    {
        $bundle = $project->source_config['normalized_bundle'] ?? null;

        if (! $bundle) {
            return response()->json(['error' => 'Normalization must run before data quality'], 422);
        }

        $issues = $this->qualityService->checkAll($bundle['entities'] ?? []);
        $quality = $this->serializer->qualityToArray($issues);

        $project->source_config = array_merge($project->source_config ?? [], [
            'quality' => $quality,
            'pipeline_logs' => array_merge($project->source_config['pipeline_logs'] ?? [], [
                ['step' => 'quality', 'status' => 'completed', 'at' => now()->toISOString()],
            ]),
        ]);
        $project->status = MigrationProject::STATUS_PREVIEWING;
        $project->save();

        return response()->json($quality);
    }

    public function show(MigrationProject $project): JsonResponse
    {
        $quality = $project->source_config['quality'] ?? null;

        if (! $quality) {
            return response()->json(['error' => 'Data quality has not been run yet'], 404);
        }

        return response()->json($quality);
    }
}
