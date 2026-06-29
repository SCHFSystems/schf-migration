<?php

namespace App\Http\Controllers;

use App\Models\MigrationPreview;
use App\Models\MigrationProject;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\NormalizationResultSerializer;
use App\Normalization\NormalizationService;
use App\Normalization\Profiles\FirebirdFinanceProfile;
use App\Normalization\Profiles\SyntheticFinanceProfile;
use App\Services\ConnectorFactory;
use App\Services\MigrationPreviewService;
use Illuminate\Http\JsonResponse;

class PreviewGenerateController
{
    public function __construct(
        private ConnectorFactory $connectorFactory,
        private MappingProfileRegistry $registry,
        private NormalizationService $normalizationService,
        private MigrationPreviewService $previewService,
        private NormalizationResultSerializer $serializer,
    ) {}

    public function generate(MigrationProject $project): JsonResponse
    {
        abort_if(! $project->source_config, 422, 'Project has no source configuration');

        $this->registerProfiles($project->source_type);

        try {
            $bundle = $project->source_config['normalized_bundle'] ?? null;

            if ($bundle) {
                $result = $this->serializer->toResult($bundle);
            } else {
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
                ]);
                $project->save();
            }

            $previewData = $this->previewService->generate(
                $project->id,
                $project->source_config ?? [],
                $result,
            );

            $preview = MigrationPreview::create([
                'project_id' => $previewData['project_id'],
                'status' => $previewData['status'],
                'ready_for_bundle' => $previewData['ready_for_bundle'],
                'summary_json' => $previewData['summary'],
                'entities_json' => $previewData['entities'],
                'warnings_json' => $previewData['warnings'],
                'errors_json' => $previewData['errors'],
                'ignored_json' => $previewData['ignored'],
                'historical_json' => $previewData['historical'],
                'generated_at' => $previewData['generated_at'],
            ]);

            $project->source_config = array_merge($project->source_config ?? [], [
                'preview' => $preview->toApiResponse(),
                'pipeline_logs' => array_merge($project->source_config['pipeline_logs'] ?? [], [
                    ['step' => 'preview', 'status' => 'completed', 'at' => now()->toISOString()],
                ]),
            ]);
            $project->save();

            return response()->json($preview->toApiResponse());
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Preview generation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(MigrationProject $project): JsonResponse
    {
        $preview = $project->latestPreview;

        if (! $preview) {
            return response()->json(['error' => 'No preview generated yet'], 404);
        }

        return response()->json($preview->toApiResponse());
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
