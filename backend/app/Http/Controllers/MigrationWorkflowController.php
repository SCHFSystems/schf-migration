<?php

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Services\MigrationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationWorkflowController extends Controller
{
    public function __construct(
        private MigrationEngine $engine,
    ) {}

    public function prepare(MigrationProject $project): JsonResponse
    {
        if (! $project->isEditable() && $project->status !== MigrationProject::STATUS_VALIDATING) {
            return response()->json([
                'error' => 'Project cannot be prepared in current status: ' . $project->status,
            ], 422);
        }

        $result = $this->engine->prepare($project);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function validate(MigrationProject $project): JsonResponse
    {
        if ($project->status !== MigrationProject::STATUS_VALIDATING) {
            return response()->json([
                'error' => 'Project is not in validating status',
            ], 422);
        }

        $result = $this->engine->validate($project);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function preview(MigrationProject $project, Request $request): JsonResponse
    {
        if (! in_array($project->status, [
            MigrationProject::STATUS_PREVIEWING,
            MigrationProject::STATUS_VALIDATING,
        ])) {
            return response()->json([
                'error' => 'Project is not ready for preview',
            ], 422);
        }

        $tableName = $request->input('table');

        $result = $this->engine->preview($project, $tableName);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function migrate(MigrationProject $project): JsonResponse
    {
        if (filter_var(env('MIGRATION_SYNTHETIC_ONLY', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'error' => 'Migration execution is disabled in synthetic-only mode',
            ], 403);
        }

        if ($project->status !== MigrationProject::STATUS_PREVIEWING) {
            return response()->json([
                'error' => 'Project must be in previewing status to start migration',
            ], 422);
        }

        $result = $this->engine->import($project);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function rollback(MigrationProject $project): JsonResponse
    {
        if (filter_var(env('MIGRATION_SYNTHETIC_ONLY', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'error' => 'Rollback is disabled in synthetic-only mode',
            ], 403);
        }

        if (! in_array($project->status, [
            MigrationProject::STATUS_COMPLETED,
            MigrationProject::STATUS_FAILED,
        ])) {
            return response()->json([
                'error' => 'Project cannot be rolled back in current status',
            ], 422);
        }

        $result = $this->engine->rollback($project);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function report(MigrationProject $project): JsonResponse
    {
        $report = $project->latestReport;

        if (! $report) {
            return response()->json(['error' => 'No report available'], 404);
        }

        return response()->json($report);
    }
}
