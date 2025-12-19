<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Tabula\Tabula;
use App\Infrastructure\Exports\PostsExport;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * Export Posts CSV Command
 *
 * Exports all posts to CSV file using Tabula.
 * Uses streaming for O(1) memory with large datasets.
 */
final class ExportPostsCsvCommand extends Command
{
    protected string $signature = 'posts:export
        {file=storage/app/posts_export.csv : Output CSV file path}
        {--published : Export only published posts}
        {--unpublished : Export only unpublished posts}
        {--category= : Filter by category ID}
        {--author= : Filter by author ID}';

    protected string $description = 'Export posts to CSV file using Tabula';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Count total posts for progress info
        $query = PostModel::query();

        // Apply filters
        $isPublished = null;
        if ($this->option('published')) {
            $isPublished = true;
            $query = $query->where('is_published', 1);
        } elseif ($this->option('unpublished')) {
            $isPublished = false;
            $query = $query->where('is_published', 0);
        }

        $categoryId = $this->option('category') ? (int) $this->option('category') : null;
        if ($categoryId !== null) {
            $query = $query->where('category_id', $categoryId);
        }

        $authorId = $this->option('author') ? (int) $this->option('author') : null;
        if ($authorId !== null) {
            $query = $query->where('author_id', $authorId);
        }

        $totalPosts = $query->count();

        if ($totalPosts === 0) {
            $this->warn('No posts found with the specified filters.');
            return 0;
        }

        $this->info("Exporting {$this->formatNumber($totalPosts)} posts to: {$filePath}");

        // Show filters
        if ($isPublished !== null) {
            $this->line("  Filter: " . ($isPublished ? 'published only' : 'unpublished only'));
        }
        if ($categoryId !== null) {
            $this->line("  Category ID: {$categoryId}");
        }
        if ($authorId !== null) {
            $this->line("  Author ID: {$authorId}");
        }
        $this->line('');

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // Create export with filters
            if ($isPublished !== null || $categoryId !== null || $authorId !== null) {
                $export = PostsExport::filtered($isPublished, $categoryId, $authorId);
            } else {
                $export = PostsExport::make();
            }

            $result = Tabula::export($export, $filePath);

            $elapsed = microtime(true) - $startTime;
            $peakMemory = memory_get_peak_usage(true);

            if ($result->isSuccessful()) {
                $fileSize = filesize($filePath);

                $this->line('');
                $this->success(sprintf(
                    "Export completed in %s",
                    $this->formatTime($elapsed)
                ));
                $this->line(sprintf("  Rows exported: %s", $this->formatNumber($result->getTotalRows())));
                $this->line(sprintf("  File size: %s", $this->formatBytes($fileSize)));
                $this->line(sprintf("  Peak memory: %s", $this->formatBytes($peakMemory)));

                $rate = $result->getTotalRows() / $elapsed;
                $this->line(sprintf("  Rate: %s rows/sec", $this->formatNumber((int) $rate)));

                return 0;
            }

            $this->error("Export failed: " . ($result->getErrorMessage() ?? 'Unknown error'));
            return 1;

        } catch (\Throwable $e) {
            $this->error("Export failed: " . $e->getMessage());
            return 1;
        }
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

    private function formatNumber(int $number): string
    {
        return number_format($number);
    }
}
