<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\NormalizationResultSerializer;
use App\Normalization\NormalizationService;
use App\Normalization\Profiles\FirebirdFinanceProfile;
use App\Normalization\Profiles\SyntheticFinanceProfile;
use App\Services\ConnectorFactory;
use Illuminate\Http\JsonResponse;

class NormalizationPreviewController
{
    public function __construct(
        private ConnectorFactory $connectorFactory,
        private MappingProfileRegistry $registry,
        private NormalizationService $normalizationService,
        private NormalizationResultSerializer $serializer,
    ) {}

    /**
     * Preview what normalization will produce.
     */
    public function preview(MigrationProject $project): JsonResponse
    {
        abort_if(! $project->source_config, 422, 'Project has no source configuration');

        // Register built-in profiles for this source type
        $this->registerProfiles($project->source_type);

        try {
            $connector = $this->connectorFactory->make(
                $project->source_type,
                $project->source_config,
            );

            $preview = $this->normalizationService->preview($connector, $project->source_type);

            $connector->disconnect();

            return response()->json($preview);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Normalization preview failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Run full normalization.
     */
    public function normalize(MigrationProject $project): JsonResponse
    {
        abort_if(! $project->source_config, 422, 'Project has no source configuration');

        $this->registerProfiles($project->source_type);

        try {
            $connector = $this->connectorFactory->make(
                $project->source_type,
                $project->source_config,
            );

            $result = $this->normalizationService->normalize(
                $connector,
                $project->source_type,
                $project->source_config['organization'] ?? [],
            );

            $connector->disconnect();

            $bundle = $this->serializer->toArray($result);
            $project->source_config = array_merge($project->source_config ?? [], [
                'normalized_bundle' => $bundle,
                'pipeline_logs' => array_merge($project->source_config['pipeline_logs'] ?? [], [
                    ['step' => 'normalization', 'status' => 'completed', 'at' => now()->toISOString()],
                ]),
            ]);
            $project->status = MigrationProject::STATUS_VALIDATING;
            $project->save();

            return response()->json($bundle);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Normalization failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(MigrationProject $project): JsonResponse
    {
        $bundle = $project->source_config['normalized_bundle'] ?? null;

        if (! $bundle) {
            return response()->json(['error' => 'Normalization has not been run yet'], 404);
        }

        return response()->json($bundle);
    }

    private function registerProfiles(string $sourceType): void
    {
        if ($sourceType === 'synthetic') {
            foreach (SyntheticFinanceProfile::all() as $profile) {
                $this->registry->register($profile);
            }
        }

        if ($sourceType === 'firebird') {
            foreach (FirebirdFinanceProfile::all() as $profile) {
                $this->registry->register($profile);
            }
        }
    }
}
