<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Exports\PostsExport;
use Toporia\Framework\Queue\Middleware\LazyWithoutOverlapping;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Realtime\Broadcast;
use Toporia\Framework\Support\Accessors\DB;
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
        // Reconnect database to avoid "MySQL server has gone away" error
        // This is critical for long-running jobs where connection may timeout
        DB::reconnect();

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
        $this->broadcastProgress(0, 'Starting export...', 'processing');

        try {
            // Get estimate count from MySQL stats (instant) instead of COUNT(*) (slow)
            // For 8M+ records, COUNT(*) can take 10+ seconds
            // Estimate from information_schema is ~95% accurate and instant
            $totalRows = $this->getEstimateCount();

            ImportExportJobModel::where('id', $this->jobId)->update([
                'total_rows' => $totalRows,
            ]);

            $this->reportProgress(10, "Found {$totalRows} posts to export");
            $this->broadcastProgress(10, "Found {$totalRows} posts to export", 'processing', [
                'total_rows' => $totalRows,
            ]);

            if ($totalRows === 0) {
                $job->markAsCompleted();
                $this->reportProgress(100, 'No posts to export');
                $this->broadcastProgress(100, 'No posts to export', 'completed');
                return;
            }

            // Create export instance with filters
            $this->reportProgress(20, 'Preparing export...');
            $this->broadcastProgress(20, 'Preparing export...', 'processing');
            $export = $this->createExport();

            // Set total count for accurate progress tracking
            $export->withTotalCount($totalRows);

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

            // Configure progress callback with WebSocket broadcasting
            // NOTE: Export uses cursor() with unbuffered queries - progress updates use separate PDO
            $lastProgress = 20;
            $jobId = $this->jobId;
            $progressPdo = null; // Lazy-initialized separate connection for progress updates
            $lastCancelCheck = 0; // Track when we last checked cancel status
            $export->withProgressCallback(function (int $processed, int $total) use (&$lastProgress, $totalRows, $jobId, &$progressPdo, &$lastCancelCheck) {
                // Check for cancellation every 10,000 rows to avoid too many DB queries
                if ($processed - $lastCancelCheck >= 10000) {
                    $lastCancelCheck = $processed;

                    // Check if job was cancelled using separate PDO connection
                    $progressPdo = $progressPdo ?? $this->createSeparatePdo();
                    $stmt = $progressPdo->prepare('SELECT status FROM import_export_jobs WHERE id = ?');
                    $stmt->execute([$jobId]);
                    $status = $stmt->fetchColumn();

                    if ($status === 'cancelled') {
                        // Throw exception to stop cursor iteration and release job
                        throw new \RuntimeException('Export cancelled by user');
                    }
                }

                // Calculate progress (20-90% for export phase)
                $progress = 20 + (int) (($processed / max(1, $total)) * 70);

                // Only update if progress changed significantly (>= 2%)
                if ($progress >= $lastProgress + 2) {
                    $lastProgress = $progress;
                    $this->reportProgress($progress, "Exporting: {$processed}/{$total} rows");

                    // Use separate PDO connection for progress updates
                    // This avoids conflict with cursor's unbuffered query on main connection
                    $dbProgress = $totalRows > 0 ? (int) (($processed / $totalRows) * 100) : 0;
                    $progressPdo = $this->updateProgressWithSeparatePdo(
                        $progressPdo,
                        $jobId,
                        $processed,
                        $dbProgress
                    );

                    // Broadcast progress via WebSocket
                    $this->broadcastProgress($dbProgress, "Exporting: " . number_format($processed) . " / " . number_format($totalRows) . " rows", 'processing', [
                        'processed_rows' => $processed,
                        'total_rows' => $totalRows,
                    ]);
                }
            });

            // Run export
            $startTime = microtime(true);
            Tabula::export($export, $outputPath);
            $elapsed = microtime(true) - $startTime;

            // Reconnect database after cursor streaming completes
            // The cursor uses unbuffered queries which may have affected connection state
            DB::reconnect();

            $this->reportProgress(95, 'Export completed, finalizing...');
            $this->broadcastProgress(95, 'Export completed, finalizing...', 'processing');

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
            $this->broadcastProgress(100, $message, 'completed', [
                'processed_rows' => $totalRows,
                'total_rows' => $totalRows,
                'success_rows' => $totalRows,
                'failed_rows' => 0,
                'elapsed_seconds' => round($elapsed, 2),
            ]);

            Log::info("ExportPostsJob completed", [
                'job_id' => $this->jobId,
                'total_rows' => $totalRows,
                'file_size' => $fileSize,
                'elapsed' => $elapsed,
            ]);
        } catch (\Throwable $e) {
            // Reconnect in case cursor left connection in bad state
            DB::reconnect();

            // Check if this was a cancellation (not a real error)
            $isCancelled = str_contains($e->getMessage(), 'cancelled by user');

            if ($isCancelled) {
                // Job was cancelled - this is expected, just log and clean up
                Log::info("ExportPostsJob cancelled", [
                    'job_id' => $this->jobId,
                    'processed_rows' => $job->processed_rows ?? 0,
                ]);

                // Clean up partial export file if exists
                if (isset($outputPath) && file_exists($outputPath)) {
                    unlink($outputPath);
                }

                // Broadcast cancellation
                $this->broadcastProgress(0, 'Export cancelled by user', 'cancelled', [
                    'cancelled' => true,
                ]);

                // Don't throw - job completed (cancelled) normally
                return;
            }

            // Real error - log and fail
            Log::error("ExportPostsJob failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $job->markAsFailed($e->getMessage());

            // Broadcast failure
            $this->broadcastProgress(0, "Export failed: {$e->getMessage()}", 'failed', [
                'error' => $e->getMessage(),
            ]);

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

        // Broadcast permanent failure
        $this->broadcastProgress(
            0,
            "Export permanently failed: {$exception->getMessage()}",
            'failed',
            ['error' => $exception->getMessage(), 'permanent' => true]
        );
    }

    /**
     * Get estimate row count from MySQL information_schema.
     *
     * Uses TABLE_ROWS from information_schema.tables which is:
     * - Instant (no table scan required)
     * - ~95% accurate for InnoDB tables
     * - Perfect for progress tracking where exact count isn't critical
     *
     * For filtered exports, we use estimate as upper bound.
     * Actual exported rows may be less due to WHERE conditions.
     *
     * @return int Estimated row count
     */
    private function getEstimateCount(): int
    {
        // If filters applied, use COUNT(*) on filtered query (smaller dataset)
        // For full table export, use information_schema estimate
        $hasFilters = isset($this->filters['is_published'])
            || !empty($this->filters['category_id'])
            || !empty($this->filters['author_id']);

        if ($hasFilters) {
            // Filtered query - COUNT is acceptable (smaller result set)
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

            return $query->count();
        }

        // Full table - use information_schema for instant estimate
        $tableName = (new PostModel())->getTable();
        $result = DB::select(
            "SELECT TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$tableName]
        );

        $row = $result->first();
        return (int) ($row['TABLE_ROWS'] ?? 0);
    }

    /**
     * Create a separate PDO connection for operations during cursor streaming.
     *
     * @return \PDO
     */
    private function createSeparatePdo(): \PDO
    {
        $config = config('database.connections.' . config('database.default'));
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );
        return new \PDO(
            $dsn,
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * Update progress using a separate PDO connection.
     *
     * When export uses cursor() with unbuffered queries, the main DB connection
     * cannot execute other queries. This method uses a dedicated PDO connection
     * for progress updates to avoid conflicts.
     *
     * @param \PDO|null $pdo Existing PDO connection or null to create new
     * @param string $jobId Job ID to update
     * @param int $processed Number of rows processed
     * @param int $progress Progress percentage (0-100)
     * @return \PDO The PDO connection (for reuse in next call)
     */
    private function updateProgressWithSeparatePdo(
        ?\PDO $pdo,
        string $jobId,
        int $processed,
        int $progress
    ): \PDO {
        // Lazy-initialize separate PDO connection
        if ($pdo === null) {
            $pdo = $this->createSeparatePdo();
        }

        // Update progress using prepared statement
        $stmt = $pdo->prepare('
            UPDATE import_export_jobs
            SET processed_rows = ?,
                success_rows = ?,
                failed_rows = 0,
                progress = ?,
                updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $processed,
            $processed,
            min(100, $progress),
            date('Y-m-d H:i:s'),
            $jobId,
        ]);

        return $pdo;
    }

    /**
     * Broadcast progress to WebSocket channel.
     */
    private function broadcastProgress(
        int $progress,
        string $message,
        string $status = 'processing',
        array $extra = []
    ): void {
        try {
            $channel = "import.jobs.{$this->jobId}";

            Broadcast::channel($channel)
                ->event('progress')
                ->with(array_merge([
                    'job_id' => $this->jobId,
                    'progress' => $progress,
                    'message' => $message,
                    'status' => $status,
                ], $extra))
                ->now();
        } catch (\Throwable $e) {
            Log::warning("Failed to broadcast export progress", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
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
