<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\NormalizationService;
use App\Normalization\Profiles\FirebirdFinanceProfile;
use App\Services\ConnectorFactory;

class NormalizationPreviewController
{
    public function __construct(
        private ConnectorFactory $connectorFactory,
        private MappingProfileRegistry $registry,
        private NormalizationService $normalizationService,
    ) {}

    /**
     * Preview what normalization will produce.
     */
    public function preview(MigrationProject $project)
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
    public function normalize(MigrationProject $project)
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

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Normalization failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function registerProfiles(string $sourceType): void
    {
        if ($sourceType === 'firebird') {
            foreach (FirebirdFinanceProfile::all() as $profile) {
                $this->registry->register($profile);
            }
        }
    }
}
