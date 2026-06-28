<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Services\ConnectorFactory;
use App\Services\InventoryService;

class MigrationInventoryController
{
    public function __construct(
        private ConnectorFactory $connectorFactory,
        private InventoryService $inventoryService,
    ) {}

    /**
     * Generate and return the inventory for a project.
     */
    public function generate(MigrationProject $project)
    {
        abort_if(! $project->source_config, 422, 'Project has no source configuration');

        try {
            $connector = $this->connectorFactory->make(
                $project->source_type,
                $project->source_config,
            );

            $inventory = $this->inventoryService->generate($connector);

            $connector->disconnect();

            // Attach inventory to project
            $project->source_config = array_merge(
                $project->source_config ?? [],
                ['inventory' => $inventory],
            );
            $project->save();

            return response()->json($inventory);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Inventory generation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
