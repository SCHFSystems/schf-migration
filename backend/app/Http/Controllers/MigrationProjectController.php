<?php

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MigrationProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MigrationProject::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type' => 'required|string|in:' . implode(',', MigrationProject::SOURCE_TYPES),
            'source_config' => 'required|array',
            'data_cutoff_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($this->syntheticOnly() && $request->input('source_type') !== 'synthetic') {
            return response()->json([
                'error' => 'Real connectors are disabled in synthetic-only mode',
            ], 422);
        }

        $project = MigrationProject::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'source_type' => $request->input('source_type'),
            'source_config' => $request->input('source_config'),
            'status' => MigrationProject::STATUS_DRAFT,
            'data_cutoff_date' => $request->input('data_cutoff_date'),
            'organization_id' => $request->input('organization_id'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($project, 201);
    }

    public function show(MigrationProject $project): JsonResponse
    {
        $project->load(['imports', 'latestReport', 'apiKeys']);

        return response()->json($project);
    }

    public function update(Request $request, MigrationProject $project): JsonResponse
    {
        if (! $project->isEditable()) {
            return response()->json([
                'error' => 'Project cannot be edited in current status: ' . $project->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'source_type' => 'sometimes|string|in:' . implode(',', MigrationProject::SOURCE_TYPES),
            'source_config' => 'sometimes|array',
            'data_cutoff_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($this->syntheticOnly() && $request->input('source_type', $project->source_type) !== 'synthetic') {
            return response()->json([
                'error' => 'Real connectors are disabled in synthetic-only mode',
            ], 422);
        }

        $project->update($validator->validated());

        return response()->json($project);
    }

    public function destroy(MigrationProject $project): JsonResponse
    {
        if ($project->isActive()) {
            return response()->json([
                'error' => 'Cannot delete an active migration project',
            ], 422);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    private function syntheticOnly(): bool
    {
        return filter_var(env('MIGRATION_SYNTHETIC_ONLY', true), FILTER_VALIDATE_BOOLEAN);
    }
}
