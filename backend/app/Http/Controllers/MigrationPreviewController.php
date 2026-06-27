<?php

namespace App\Http\Controllers;

use App\Models\MigrationProject;
use App\Services\DataNormalizer;
use App\Services\SourceDetectors\FirebirdDetector;
use App\Services\SourceDetectors\MysqlDetector;
use App\Services\SourceDetectors\ZipDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationPreviewController extends Controller
{
    public function __construct(
        private DataNormalizer $normalizer,
    ) {}

    public function index(MigrationProject $project, Request $request): JsonResponse
    {
        $tableName = $request->input('table');
        $limit = $request->input('limit', 20);

        $detector = $this->getDetector($project->source_type);

        $structure = data_get($project->source_config, 'detected_structure.tables', []);

        if ($tableName && ! isset($structure[$tableName])) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $previews = [];
        $tablesToShow = $tableName ? [$tableName => $structure[$tableName]] : $structure;

        foreach ($tablesToShow as $table) {
            $sampleData = $detector->getSampleData(
                $project->source_config,
                $table['name'],
                $limit
            );

            $normalizedData = $this->normalizer->normalizePreview(
                $sampleData['data'] ?? [],
                $table['name'],
                $project
            );

            $previews[$table['name']] = [
                'original' => $sampleData['data'] ?? [],
                'normalized' => $normalizedData,
                'column_mapping' => $this->normalizer->getColumnMapping($table['name'], $project),
                'total_records' => $detector->countRecords($project->source_config, $table['name']),
            ];
        }

        return response()->json([
            'success' => true,
            'previews' => $previews,
        ]);
    }

    public function testConnection(MigrationProject $project): JsonResponse
    {
        $detector = $this->getDetector($project->source_type);
        $result = $detector->testConnection($project->source_config);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Connection successful' : 'Connection failed',
        ]);
    }

    private function getDetector(string $sourceType)
    {
        return match ($sourceType) {
            'firebird' => app(FirebirdDetector::class),
            'mysql' => app(MysqlDetector::class),
            'zip' => app(ZipDetector::class),
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };
    }
}
