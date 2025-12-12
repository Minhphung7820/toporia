<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Import\ExcelImporter;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Database\DatabaseManager;

/**
 * Import posts from CSV/Excel file.
 *
 * Usage:
 *   php console import:posts posts_1m.csv
 *   php console import:posts posts_1m.csv --chunk=5000
 *   php console import:posts posts_1m.csv --truncate
 */
final class ImportPostsCommand extends Command
{
    protected string $signature = 'import:posts {file} {--chunk=1000} {--truncate}';
    protected string $description = 'Import posts from CSV/Excel file';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk'); // Default: 1000 (from signature)
        $shouldTruncate = (bool) $this->option('truncate');

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
        $this->info('Import Complete!');
        $this->info('========================================');
        $this->info("Total rows: " . number_format($result->totalRows));
        $this->info("Success: " . number_format($result->successRows));
        $this->info("Failed: " . number_format($result->failedRows));
        $this->info("Duration: {$duration}s");
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
     * Process a chunk of rows using bulk upsert.
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
            // Use bulk insert for performance (100x faster than individual inserts)
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
