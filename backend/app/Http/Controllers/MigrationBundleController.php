<?php

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Services\MigrationBundleExporter;
use Illuminate\Http\JsonResponse;

class MigrationBundleController extends Controller
{
    public function __construct(
        private MigrationBundleExporter $exporter,
    ) {}

    public function preview(MigrationProject $project): JsonResponse
    {
        return response()->json($this->exporter->preview($project));
    }

    public function export(MigrationProject $project): JsonResponse
    {
        $result = $this->exporter->export($project);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function download(MigrationProject $project)
    {
        $path = $this->exporter->latestBundlePath($project);

        if (! $path) {
            return response()->json(['error' => 'No exported bundle found'], 404);
        }

        return response()->download($path, 'migration-package.zip');
    }
}
