<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Exports\PostsExport;
use App\Infrastructure\Jobs\Middleware\LazyWithoutOverlapping;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Tabula\Tabula;

/**
 * Export Posts Job - Process CSV export with progress tracking.
 *
 * Features:
 * - Real-time progress tracking (0-100%)
 * - WithoutOverlapping middleware to prevent race conditions
 * - Memory-efficient streaming export
 * - Automatic cleanup on failure
 */
final class ExportPostsJob extends Job implements ShouldQueueInterface
{
    protected bool $trackProgress = true;

    protected int $timeout = 3600; // 1 hour max

    public function __construct(
        private readonly string $jobId,
        private readonly int $userId,
        private readonly array $filters = []
    ) {
        parent::__construct();

        // Configure retry behavior
        $this->tries(1); // No retry for export jobs - user can re-trigger
    }

    /**
     * Middleware to prevent concurrent exports for same user.
     *
     * Uses LazyWithoutOverlapping which resolves cache at runtime,
     * avoiding serialization issues with CacheInterface.
     */
    public function middleware(): array
    {
        return [
            (new LazyWithoutOverlapping(expireAfter: 3600))
                ->by("export:user:{$this->userId}"),
        ];
    }

    public function handle(): void
    {
        $job = ImportExportJobModel::find($this->jobId);

        if (!$job) {
            Log::error("ExportPostsJob: Job not found", ['job_id' => $this->jobId]);
            return;
        }

        // Check if job was cancelled before starting
        if ($job->isCancelled()) {
            Log::info("ExportPostsJob: Job was cancelled", ['job_id' => $this->jobId]);
            return;
        }

        $job->markAsProcessing();
        $this->reportProgress(0, 'Starting export...');

        try {
            // Count total rows to export
            $query = $this->buildQuery();
            $totalRows = $query->count();

            ImportExportJobModel::where('id', $this->jobId)->update([
                'total_rows' => $totalRows,
            ]);

            $this->reportProgress(10, "Found {$totalRows} posts to export");

            if ($totalRows === 0) {
                $job->markAsCompleted();
                $this->reportProgress(100, 'No posts to export');
                return;
            }

            // Create export instance with filters
            $this->reportProgress(20, 'Preparing export...');
            $export = $this->createExport();

            // Generate output file path
            $filename = sprintf(
                'posts_export_%s_%s.csv',
                date('Y-m-d_His'),
                substr($this->jobId, 0, 8)
            );
            $outputPath = storage_path("app/exports/{$filename}");

            // Ensure directory exists
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Configure progress callback
            $lastProgress = 20;
            $export->onProgress(function (int $processed, int $total) use (&$lastProgress, $job) {
                // Calculate progress (20-90% for export phase)
                $progress = 20 + (int) (($processed / max(1, $total)) * 70);

                // Only update if progress changed significantly (>= 2%)
                if ($progress >= $lastProgress + 2) {
                    $lastProgress = $progress;
                    $this->reportProgress($progress, "Exporting: {$processed}/{$total} rows");

                    // Update job record
                    $job->updateProcessedRows($processed, $processed, 0);
                }
            });

            // Run export
            $startTime = microtime(true);
            Tabula::export($export, $outputPath);
            $elapsed = microtime(true) - $startTime;

            $this->reportProgress(95, 'Export completed, finalizing...');

            // Update job with results
            $fileSize = filesize($outputPath);

            ImportExportJobModel::where('id', $this->jobId)->update([
                'processed_rows' => $totalRows,
                'success_rows' => $totalRows,
                'failed_rows' => 0,
                'result_file_path' => $outputPath,
            ]);

            // Mark as completed
            $job->markAsCompleted($outputPath);

            $message = sprintf(
                'Export completed: %s rows exported (%.1f MB) in %.1f seconds',
                number_format($totalRows),
                $fileSize / 1024 / 1024,
                $elapsed
            );

            ImportExportJobModel::where('id', $this->jobId)->update([
                'message' => $message,
            ]);

            $this->reportProgress(100, $message);

            Log::info("ExportPostsJob completed", [
                'job_id' => $this->jobId,
                'total_rows' => $totalRows,
                'file_size' => $fileSize,
                'elapsed' => $elapsed,
            ]);
        } catch (\Throwable $e) {
            Log::error("ExportPostsJob failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $job->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExportPostsJob permanently failed", [
            'job_id' => $this->jobId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        // Mark job as failed
        $job = ImportExportJobModel::find($this->jobId);
        if ($job) {
            $job->markAsFailed($exception->getMessage());
        }
    }

    /**
     * Build query based on filters.
     */
    private function buildQuery(): mixed
    {
        $query = PostModel::query();

        if (isset($this->filters['is_published'])) {
            $query = $query->where('is_published', $this->filters['is_published'] ? 1 : 0);
        }

        if (!empty($this->filters['category_id'])) {
            $query = $query->where('category_id', $this->filters['category_id']);
        }

        if (!empty($this->filters['author_id'])) {
            $query = $query->where('author_id', $this->filters['author_id']);
        }

        return $query;
    }

    /**
     * Create export instance with filters.
     */
    private function createExport(): mixed
    {
        $isPublished = isset($this->filters['is_published'])
            ? (bool) $this->filters['is_published']
            : null;
        $categoryId = !empty($this->filters['category_id'])
            ? (int) $this->filters['category_id']
            : null;
        $authorId = !empty($this->filters['author_id'])
            ? (int) $this->filters['author_id']
            : null;

        if ($isPublished === null && $categoryId === null && $authorId === null) {
            return PostsExport::make();
        }

        return PostsExport::filtered($isPublished, $categoryId, $authorId);
    }
}
