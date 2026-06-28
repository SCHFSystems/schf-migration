<?php

namespace App\Services\SourceDetectors;

use Illuminate\Support\Facades\Log;
use ZipArchive;

class ZipDetector implements DatabaseDetectorInterface
{
    public function testConnection(array $config): bool
    {
        $path = $config['path'] ?? '';

        if (! file_exists($path)) {
            return false;
        }

        if (! extension_loaded('zip')) {
            Log::warning('PHP zip extension not available');

            return false;
        }

        $zip = new ZipArchive();
        $result = $zip->open($path);

        if ($result === true) {
            $zip->close();

            return true;
        }

        return false;
    }

    public function detectStructure(array $config): array
    {
        $path = $config['path'] ?? '';

        if (! file_exists($path)) {
            return ['error' => 'ZIP file not found: ' . $path];
        }

        $zip = new ZipArchive();
        $result = $zip->open($path);

        if ($result !== true) {
            return ['error' => 'Could not open ZIP file'];
        }

        $files = [];
        $csvFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $info = pathinfo($name);

            $files[] = [
                'name' => $name,
                'extension' => strtolower($info['extension'] ?? ''),
                'size' => $zip->statIndex($i)['size'] ?? 0,
            ];

            if (isset($info['extension']) && strtolower($info['extension']) === 'csv') {
                $csvFiles[] = $name;
            }
        }

        $tables = [];
        foreach ($csvFiles as $csvFile) {
            $tableName = pathinfo($csvFile, PATHINFO_FILENAME);
            $sampleData = $this->readCsvSample($zip, $csvFile, 10);

            $columns = [];
            if (! empty($sampleData['headers'])) {
                foreach ($sampleData['headers'] as $header) {
                    $columns[] = [
                        'name' => $header,
                        'type' => 'string',
                        'nullable' => true,
                    ];
                }
            }

            $tables[$tableName] = [
                'name' => $tableName,
                'source_file' => $csvFile,
                'columns' => $columns,
                'column_count' => count($columns),
                'estimated_rows' => $sampleData['total_lines'] ?? 0,
            ];
        }

        $zip->close();

        return [
            'tables' => $tables,
            'table_count' => count($tables),
            'total_files' => count($files),
            'files' => $files,
        ];
    }

    public function getSampleData(array $config, string $tableName, int $limit = 10): array
    {
        $path = $config['path'] ?? '';

        $zip = new ZipArchive();
        $result = $zip->open($path);

        if ($result !== true) {
            return ['error' => 'Could not open ZIP file'];
        }

        $csvFile = $this->findCsvFile($zip, $tableName);

        if (! $csvFile) {
            $zip->close();

            return ['error' => 'CSV file not found for table: ' . $tableName];
        }

        $sample = $this->readCsvSample($zip, $csvFile, $limit);
        $zip->close();

        return $sample;
    }

    public function countRecords(array $config, string $tableName): int
    {
        $path = $config['path'] ?? '';

        $zip = new ZipArchive();
        $result = $zip->open($path);

        if ($result !== true) {
            return 0;
        }

        $csvFile = $this->findCsvFile($zip, $tableName);

        if (! $csvFile) {
            $zip->close();

            return 0;
        }

        $content = $zip->getFromName($csvFile);
        $zip->close();

        if (! $content) {
            return 0;
        }

        $lines = explode("\n", trim($content));

        return max(0, count($lines) - 1);
    }

    public function getSourceType(): string
    {
        return 'zip';
    }

    private function readCsvSample(ZipArchive $zip, string $csvFile, int $limit): array
    {
        $content = $zip->getFromName($csvFile);

        if (! $content) {
            return ['headers' => [], 'data' => [], 'total_lines' => 0];
        }

        $lines = explode("\n", $content);
        $totalLines = count($lines);

        $headers = [];
        $data = [];

        if ($totalLines > 0) {
            $headers = $this->parseCsvLine($lines[0]);
        }

        $dataLines = array_slice($lines, 1, $limit);
        foreach ($dataLines as $line) {
            $row = $this->parseCsvLine($line);
            $data[] = array_combine($headers, $row);
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'total_lines' => max(0, $totalLines - 1),
        ];
    }

    private function parseCsvLine(string $line): array
    {
        $result = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];

            if ($char === '"') {
                if ($inQuotes && $i + 1 < strlen($line) && $line[$i + 1] === '"') {
                    $current .= '"';
                    $i++;
                } else {
                    $inQuotes = ! $inQuotes;
                }
            } elseif ($char === ',' && ! $inQuotes) {
                $result[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $result[] = $current;

        return $result;
    }

    private function findCsvFile(ZipArchive $zip, string $tableName): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $info = pathinfo($name);

            if (strtolower($info['extension'] ?? '') === 'csv') {
                $fileName = $info['filename'];
                if ($fileName === $tableName || strcasecmp($fileName, $tableName) === 0) {
                    return $name;
                }
            }
        }

        return null;
    }
}
