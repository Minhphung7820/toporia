<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Import\ExcelImporter;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Support\Accessors\Concurrency;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Import posts from CSV/Excel file.
 *
 * Usage:
 *   php console import:posts posts_1m.csv
 *   php console import:posts posts_1m.csv --chunk=5000
 *   php console import:posts posts_1m.csv --truncate
 *   php console import:posts posts_1m.csv --concurrent --processes=10
 */
final class ImportPostsCommand extends Command
{
    protected string $signature = 'import:posts {file} {--chunk=1000} {--truncate} {--concurrent} {--processes=10}';
    protected string $description = 'Import posts from CSV/Excel file';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) ($this->option('chunk') ?? 1000);
        $shouldTruncate = (bool) $this->option('truncate');
        $useConcurrent = (bool) $this->option('concurrent');
        $numProcesses = (int) ($this->option('processes') ?? 10);

        // Validate file argument
        if (empty($filePath)) {
            $this->error('Please provide a file path: php console import:posts <file>');
            return 1;
        }

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing posts from: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        // Truncate table if requested
        if ($shouldTruncate) {
            $this->warn('Truncating posts table...');
            $this->truncateTable();
            $this->info('Table truncated.');
        }

        if ($useConcurrent) {
            $this->info("Mode: Concurrent ({$numProcesses} processes)");
            return $this->importConcurrent($filePath, $chunkSize, $numProcesses);
        }

        $this->info('Mode: Sequential (streaming)');
        return $this->importSequential($filePath, $chunkSize);
    }

    /**
     * Import using concurrent processes.
     * Each process handles every Nth line for parallel processing.
     */
    private function importConcurrent(string $filePath, int $chunkSize, int $numProcesses): int
    {
        $startTime = microtime(true);
        $now = date('Y-m-d H:i:s');

        $this->info("Starting {$numProcesses} parallel processes...");

        // Create tasks for each process
        $tasks = [];
        for ($i = 0; $i < $numProcesses; $i++) {
            $tasks[$i] = function () use ($filePath, $i, $numProcesses, $chunkSize, $now) {
                // Each child process needs fresh DB connection
                DB::reconnect();

                $handle = fopen($filePath, 'r');
                if ($handle === false) {
                    return ['imported' => 0, 'error' => 'Cannot open file'];
                }

                // Skip header
                fgets($handle);

                $currentLine = 0;
                $posts = [];
                $imported = 0;

                while (($line = fgets($handle)) !== false) {
                    // Each process takes every Nth line (round-robin distribution)
                    if ($currentLine++ % $numProcesses !== $i) {
                        continue;
                    }

                    $row = str_getcsv($line);

                    // CSV columns: title, slug, content, views, is_published
                    $posts[] = [
                        'title' => $row[0] ?? '',
                        'slug' => $row[1] ?? null,
                        'content' => $row[2] ?? null,
                        'views' => (int) ($row[3] ?? 0),
                        'is_published' => (int) ($row[4] ?? 1),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Batch insert when chunk is full
                    if (count($posts) >= $chunkSize) {
                        QueryBuilder::table('posts')->insert($posts);
                        $imported += count($posts);
                        $posts = [];
                    }
                }

                // Insert remaining posts
                if (!empty($posts)) {
                    QueryBuilder::table('posts')->insert($posts);
                    $imported += count($posts);
                }

                fclose($handle);

                return ['imported' => $imported, 'process' => $i];
            };
        }

        // Run all tasks concurrently
        $results = Concurrency::run($tasks);

        $totalImported = 0;
        foreach ($results as $processId => $result) {
            if (is_array($result) && isset($result['imported'])) {
                $totalImported += $result['imported'];
                $this->line("  Process {$processId}: " . number_format($result['imported']) . " records");
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->newLine();
        $this->info('========================================');
        $this->info('Import Complete! (Concurrent)');
        $this->info('========================================');
        $this->info("Total imported: " . number_format($totalImported));
        $this->info("Processes: {$numProcesses}");
        $this->info("Duration: {$duration}s");
        $this->info("Speed: " . number_format($totalImported / $duration) . " records/s");
        $this->info("Peak memory: {$memory}MB");
        $this->info('========================================');

        return 0;
    }

    /**
     * Import sequentially using streaming (original method).
     */
    private function importSequential(string $filePath, int $chunkSize): int
    {
        $startTime = microtime(true);
        $totalImported = 0;
        $totalFailed = 0;

        // Use ExcelImporter with chunked callback
        $importer = new ExcelImporter($chunkSize);
        $importer->setHasHeader(true);

        $result = $importer->importChunked(
            $filePath,
            function (array $chunk, int $chunkIndex) use (&$totalImported, &$totalFailed) {
                $this->processChunk($chunk, $chunkIndex, $totalImported, $totalFailed);
            }
        );

        $duration = round(microtime(true) - $startTime, 2);
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->newLine();
        $this->info('========================================');
        $this->info('Import Complete! (Sequential)');
        $this->info('========================================');
        $this->info("Total rows: " . number_format($result->totalRows));
        $this->info("Success: " . number_format($result->successRows));
        $this->info("Failed: " . number_format($result->failedRows));
        $this->info("Duration: {$duration}s");
        $this->info("Speed: " . number_format($result->successRows / $duration) . " records/s");
        $this->info("Peak memory: {$memory}MB");
        $this->info('========================================');

        if (!empty($result->errors)) {
            $this->warn('Errors:');
            foreach (array_slice($result->errors, 0, 10) as $error) {
                $this->line("  - Chunk {$error['chunk']}: {$error['error']}");
            }
            if (count($result->errors) > 10) {
                $this->line("  ... and " . (count($result->errors) - 10) . " more errors");
            }
        }

        return 0;
    }

    /**
     * Process a chunk of rows using bulk insert.
     */
    private function processChunk(array $chunk, int $chunkIndex, int &$totalImported, int &$totalFailed): void
    {
        $rows = [];

        foreach ($chunk as $row) {
            $rows[] = [
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? null,
                'content' => $row['content'] ?? null,
                'views' => (int) ($row['views'] ?? 0),
                'is_published' => (bool) ($row['is_published'] ?? true),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        try {
            // Use bulk insert for performance
            PostModel::insert($rows);
            $totalImported += count($rows);

            // Progress indicator
            $this->output->write("\r[Chunk {$chunkIndex}] Imported " . number_format($totalImported) . " records...");
        } catch (\Throwable $e) {
            $totalFailed += count($rows);
            $this->error("\nChunk {$chunkIndex} failed: " . $e->getMessage());
        }
    }

    /**
     * Truncate posts table.
     */
    private function truncateTable(): void
    {
        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);
        $db->connection()->statement('TRUNCATE TABLE posts');
    }
}
