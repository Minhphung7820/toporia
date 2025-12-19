<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Tabula\Tabula;
use App\Infrastructure\Imports\PostsImport;

/**
 * Import Posts CSV Command
 *
 * Imports posts from CSV file using Tabula.
 * Optimized for large datasets with batch processing.
 *
 * Supports parallel import with --parallel option for ~4-6x speedup.
 */
final class ImportPostsCsvCommand extends Command
{
    protected string $signature = 'posts:import {file=storage/app/posts.csv : CSV file path} {--parallel=0 : Number of parallel workers (0=sequential)} {--driver=process : Concurrency driver (process, fork, sync)}';

    protected string $description = 'Import posts from CSV file using Tabula';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $workers = (int) $this->option('parallel');
        $driver = $this->option('driver') ?? 'process';

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $fileSize = filesize($filePath);
        $this->info("Importing posts from: {$filePath}");
        $this->output?->writeln("  File size: " . $this->formatBytes($fileSize));

        if ($workers > 0) {
            $this->output?->writeln("  Mode: Parallel ({$workers} workers, {$driver} driver)");
        } else {
            $this->output?->writeln("  Mode: Sequential");
        }

        $this->newLine();

        $startTime = microtime(true);

        try {
            $import = $workers > 0
                ? PostsImport::parallel($workers, $driver)
                : PostsImport::make();

            $result = Tabula::import($import, $filePath);

            $elapsed = microtime(true) - $startTime;
            $peakMemory = memory_get_peak_usage(true);

            $this->newLine();
            $this->success(sprintf(
                "Import completed in %s",
                $this->formatTime($elapsed)
            ));
            $this->output?->writeln(sprintf("  Rows processed: %s", number_format($result->getTotalRows())));
            $this->output?->writeln(sprintf("  Rows imported: %s", number_format($result->getSuccessRows())));

            if ($result->getFailedRows() > 0) {
                $this->warn(sprintf("  Failed rows: %s", number_format($result->getFailedRows())));
            }

            $this->output?->writeln(sprintf("  Peak memory: %s", $this->formatBytes($peakMemory)));

            $rate = $result->getTotalRows() / $elapsed;
            $this->output?->writeln(sprintf("  Rate: %.0f rows/sec", $rate));

        } catch (\Throwable $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $minutes = (int) floor($seconds / 60);
        $secs = (int) ($seconds - ($minutes * 60));

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%dh %dm', $hours, $mins);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', $bytes, $units[$unit]);
    }
}
