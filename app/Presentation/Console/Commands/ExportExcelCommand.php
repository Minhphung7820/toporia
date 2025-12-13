<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Export\ExcelExporter;
use Toporia\Framework\Console\Command;

/**
 * Export Excel Command
 *
 * Exports data to Excel files with chunking and progress tracking.
 * Supports millions of rows with memory-efficient streaming.
 *
 * Usage:
 *   php console excel:export /path/to/output.xlsx --model=User
 *   php console excel:export /path/to/output.xlsx --query="SELECT * FROM users"
 *   php console excel:export /path/to/output.xlsx --chunk-size=5000
 *
 * Performance:
 * - Streaming: O(1) memory usage
 * - Chunking: Configurable chunk size
 * - Progress tracking
 */
final class ExportExcelCommand extends Command
{
    protected string $signature = 'excel:export {file : Output file path} {--model= : Model class to export} {--query= : SQL query to export} {--chunk-size=1000 : Chunk size for processing} {--headers= : Comma-separated headers}';

    protected string $description = 'Export data to Excel file with streaming and chunking support';

    /**
     * Execute the command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk-size', 1000);
        $model = $this->option('model');
        $query = $this->option('query');
        $headers = $this->option('headers');

        $this->info("Starting export to: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        $exporter = new ExcelExporter($chunkSize);

        // Parse headers
        $headersArray = [];
        if ($headers !== null) {
            $headersArray = array_map('trim', explode(',', $headers));
        }

        $options = [
            'headers' => $headersArray,
        ];

        try {
            // Get data source
            $data = $this->getDataSource($model, $query);

            if ($data === null) {
                $this->error("No data source specified. Use --model or --query");
                return 1;
            }

            // Get sample entity for headers if not provided
            if (empty($headersArray)) {
                $sample = is_array($data) ? reset($data) : null;
                if ($sample !== null) {
                    $options['sample_entity'] = $sample;
                }
            }

            $result = $exporter->export($data, $filePath, $options);

            $this->info("Export completed!");
            $this->info("File: {$result->filePath}");
            $this->info("Total rows: {$result->totalRows}");
            $this->info("File size: {$result->getFileSizeHuman()}");
            $this->info("Execution time: " . round($result->executionTime, 2) . "s");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Export failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get data source based on options.
     *
     * @param string|null $model Model class
     * @param string|null $query SQL query
     * @return iterable|null Data source
     */
    private function getDataSource(?string $model, ?string $query): ?iterable
    {
        // From model
        if ($model !== null && class_exists($model)) {
            if (method_exists($model, 'all')) {
                return $model::all();
            }
            if (method_exists($model, 'query')) {
                return $model::query()->get();
            }
        }

        // From query
        if ($query !== null) {
            $connection = \Toporia\Framework\Support\Accessors\DB::connection();
            return $connection->query($query)->get();
        }

        return null;
    }
}
