<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Import\ExcelImporter;
use Toporia\Framework\Console\Command;

/**
 * Import Excel Command
 *
 * Imports data from Excel files with chunking and progress tracking.
 * Supports millions of rows with memory-efficient streaming.
 *
 * Usage:
 *   php console excel:import /path/to/file.xlsx
 *   php console excel:import /path/to/file.xlsx --chunk-size=5000
 *   php console excel:import /path/to/file.xlsx --processor=ProductImporter
 *
 * Performance:
 * - Streaming: O(1) memory usage
 * - Chunking: Configurable chunk size
 * - Progress tracking
 */
final class ImportExcelCommand extends Command
{
    protected string $signature = 'excel:import {file : Path to Excel file} {--chunk-size=1000 : Chunk size for processing} {--processor= : Processor class or callback} {--no-header : File does not have header row}';

    protected string $description = 'Import data from Excel file with streaming and chunking support';

    /**
     * Execute the command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk-size', 1000);
        $processor = $this->option('processor');
        $hasHeader = !$this->option('no-header', false);

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        $importer = new ExcelImporter($chunkSize);
        $importer->setHasHeader($hasHeader);

        // Set processor if provided
        $options = [];
        if ($processor !== null) {
            if (is_callable($processor)) {
                $options['processor'] = $processor;
            } elseif (class_exists($processor)) {
                $processorInstance = new $processor();
                if (method_exists($processorInstance, 'process')) {
                    $options['processor'] = fn($row, $index) => $processorInstance->process($row, $index);
                }
            }
        }

        try {
            $result = $importer->import($filePath, $options);

            $this->info("Import completed!");
            $this->info("Total rows: {$result->totalRows}");
            $this->info("Success: {$result->successRows}");
            $this->info("Failed: {$result->failedRows}");
            $this->info("Success rate: " . round($result->getSuccessRate(), 2) . "%");
            $this->info("Execution time: " . round($result->executionTime, 2) . "s");

            if (!empty($result->errors)) {
                $this->warn("Errors: " . count($result->errors));
                foreach (array_slice($result->errors, 0, 10) as $error) {
                    $this->error("Row {$error['row']}: {$error['error']}");
                }
            }

            return $result->isSuccess() ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }
}
