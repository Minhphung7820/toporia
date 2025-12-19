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
 */
final class ImportPostsCsvCommand extends Command
{
    protected string $signature = 'posts:import {file=storage/app/posts.csv : CSV file path}';

    protected string $description = 'Import posts from CSV file using Tabula';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $fileSize = filesize($filePath);
        $this->info("Importing posts from: {$filePath}");
        $this->line("  File size: " . $this->formatBytes($fileSize));
        $this->line('');

        $startTime = microtime(true);

        try {
            $import = PostsImport::make();
            $result = Tabula::import($import, $filePath);

            $elapsed = microtime(true) - $startTime;

            $this->line('');
            $this->success(sprintf(
                "Import completed in %s",
                $this->formatTime($elapsed)
            ));
            $this->line(sprintf("  Rows processed: %s", number_format($result->getTotalRows())));
            $this->line(sprintf("  Rows imported: %s", number_format($result->getSuccessRows())));

            if ($result->getFailedRows() > 0) {
                $this->warn(sprintf("  Failed rows: %s", number_format($result->getFailedRows())));
            }

            $rate = $result->getTotalRows() / $elapsed;
            $this->line(sprintf("  Rate: %.0f rows/sec", $rate));

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
