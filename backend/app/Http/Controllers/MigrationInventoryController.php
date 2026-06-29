<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Services\ConnectorFactory;
use App\Services\InventoryService;
use App\Services\Synthetic\SyntheticSourceService;
use Illuminate\Http\JsonResponse;

class MigrationInventoryController
{
    public function __construct(
        private ConnectorFactory $connectorFactory,
        private InventoryService $inventoryService,
        private SyntheticSourceService $syntheticSourceService,
    ) {}

    /**
     * Generate and return the inventory for a project.
     */
    public function generate(MigrationProject $project): JsonResponse
    {
        abort_if(! $project->source_config, 422, 'Project has no source configuration');

        try {
            $connector = $this->connectorFactory->make(
                $project->source_type,
                $project->source_config,
            );

            $inventory = $this->inventoryService->generate($connector);

            $connector->disconnect();

            $project->source_config = array_merge(
                $project->source_config ?? [],
                [
                    'inventory' => $inventory,
                    'detected_structure' => $this->syntheticSourceService->detectedStructure($inventory),
                    'pipeline_logs' => array_merge($project->source_config['pipeline_logs'] ?? [], [
                        ['step' => 'inventory', 'status' => 'completed', 'at' => now()->toISOString()],
                    ]),
                ],
            );
            $project->status = MigrationProject::STATUS_PREPARING;
            $project->save();

            return response()->json($inventory);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Inventory generation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(MigrationProject $project): JsonResponse
    {
        $inventory = $project->source_config['inventory'] ?? null;

        if (! $inventory) {
            return response()->json(['error' => 'Inventory has not been generated yet'], 404);
        }

        return response()->json($inventory);
    }
}
