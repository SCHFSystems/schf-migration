<?php

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Models\MigrationReport;
use Illuminate\Http\JsonResponse;

class MigrationReportController extends Controller
{
    public function index(MigrationProject $project): JsonResponse
    {
        $reports = $project->reports()->orderBy('created_at', 'desc')->get();

        return response()->json($reports);
    }

    public function show(MigrationProject $project, MigrationReport $report): JsonResponse
    {
        if ($report->migration_project_id !== $project->id) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        return response()->json($report);
    }

    public function latest(MigrationProject $project): JsonResponse
    {
        $report = $project->latestReport;

        if (! $report) {
            return response()->json(['error' => 'No reports available'], 404);
        }

        return response()->json($report);
    }
}
