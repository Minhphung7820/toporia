<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Imports\PostsImport;
use App\Infrastructure\Jobs\Middleware\LazyWithoutOverlapping;
use App\Infrastructure\Jobs\PostImportPostProcessingJob;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Realtime\Broadcast;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Tabula\Tabula;

/**
 * Import Posts Job - Process CSV/Excel import with progress tracking.
 *
 * Features:
 * - Real-time progress tracking (0-100%) via WebSocket
 * - WithoutOverlapping middleware to prevent race conditions
 * - Automatic trigger management for performance
 * - Post-import tasks (stats recalculation, Elasticsearch indexing)
 *
 * WebSocket Events:
 * - Channel: import.jobs.{jobId}
 * - Event: progress
 * - Data: { progress: int, message: string, status: string, ... }
 */
final class ImportPostsJob extends Job implements ShouldQueueInterface
{
    protected bool $trackProgress = true;

    protected int $timeout = 3600; // 1 hour max

    /**
     * Triggers to disable during import for performance.
     */
    private array $postsTriggers = [
        'trg_posts_after_insert',
        'trg_posts_after_update',
        'trg_posts_after_delete',
        'trg_category_counts_after_insert',
        'trg_category_counts_after_update',
        'trg_category_counts_after_delete',
    ];

    public function __construct(
        private readonly string $jobId,
        private readonly int $userId,
        private readonly string $filePath,
        private readonly int $workers = 0
    ) {
        parent::__construct();

        // Configure retry behavior
        $this->tries(1); // No retry for import jobs - user can re-upload
    }

    /**
     * Middleware to prevent concurrent imports for same user.
     *
     * Uses LazyWithoutOverlapping which resolves cache at runtime,
     * avoiding serialization issues with CacheInterface.
     */
    public function middleware(): array
    {
        return [
            (new LazyWithoutOverlapping(expireAfter: 3600))
                ->by("import:user:{$this->userId}"),
        ];
    }

    public function handle(): void
    {
        // Reconnect database to avoid "MySQL server has gone away" error
        // This is critical for long-running jobs where connection may timeout
        DB::reconnect();

        $job = ImportExportJobModel::find($this->jobId);

        if (!$job) {
            Log::error("ImportPostsJob: Job not found", ['job_id' => $this->jobId]);
            return;
        }

        // Check if job was cancelled before starting
        if ($job->isCancelled()) {
            Log::info("ImportPostsJob: Job was cancelled", ['job_id' => $this->jobId]);
            return;
        }

        $job->markAsProcessing();

        // Resolve file path - support both relative and absolute paths
        $filePath = $this->resolveFilePath($this->filePath);

        if (!file_exists($filePath)) {
            Log::error("ImportPostsJob: File not found", ['file_path' => $filePath, 'original' => $this->filePath]);
            $job->markAsFailed("Import file not found: {$this->filePath}");
            return;
        }

        $triggersDropped = false;
        $maxIdBeforeImport = PostModel::max('id') ?? 0;

        try {
            // Count total rows first
            $totalRows = $this->countRows($filePath);
            ImportExportJobModel::where('id', $this->jobId)->update([
                'total_rows' => $totalRows,
                'progress' => 0,
                'message' => "Found {$totalRows} rows, preparing import...",
            ]);

            // Disable triggers for performance
            $this->dropTriggers();
            $triggersDropped = true;

            // Update message but keep progress at 0 until actual import starts
            ImportExportJobModel::where('id', $this->jobId)->update([
                'message' => 'Triggers disabled, starting import...',
            ]);

            // Create import instance
            $import = $this->workers > 0
                ? PostsImport::parallel($this->workers, 'process')
                : PostsImport::make();

            // Configure progress callback with WebSocket broadcasting
            // Progress = processed / total * 100 (simple, intuitive)
            $lastProgress = 0;
            $lastCancelCheck = 0;
            $jobId = $this->jobId; // Capture for closure
            $import->withProgressCallback(function (int $processed, int $total) use (&$lastProgress, &$lastCancelCheck, $jobId, $totalRows) {
                // Check for cancellation every 10,000 rows to avoid DB overhead
                if ($processed - $lastCancelCheck >= 10000) {
                    $lastCancelCheck = $processed;
                    $currentStatus = ImportExportJobModel::where('id', $jobId)->value('status');
                    if ($currentStatus === ImportExportJobModel::STATUS_CANCELLED) {
                        Log::info("ImportPostsJob: Cancellation detected during import", [
                            'job_id' => $jobId,
                            'processed' => $processed,
                        ]);
                        // Throw exception to stop import
                        throw new \RuntimeException('Import cancelled by user');
                    }
                }

                // Simple progress: processed / total * 100
                // Use pre-counted totalRows for accuracy
                $effectiveTotal = $totalRows > 0 ? $totalRows : max(1, $total);
                $progress = (int) (($processed / $effectiveTotal) * 100);

                // Only update if progress changed significantly (>= 1%)
                if ($progress > $lastProgress) {
                    $lastProgress = $progress;

                    // Update job record directly with calculated progress
                    ImportExportJobModel::where('id', $jobId)->update([
                        'processed_rows' => $processed,
                        'success_rows' => $processed,
                        'failed_rows' => 0,
                        'progress' => $progress,
                        'message' => "Importing: " . number_format($processed) . " / " . number_format($totalRows) . " rows",
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Broadcast with extra row info
                    Broadcast::channel("import.jobs.{$jobId}")
                        ->event('progress')
                        ->with([
                            'job_id' => $jobId,
                            'progress' => $progress,
                            'message' => "Importing: " . number_format($processed) . " / " . number_format($totalRows) . " rows",
                            'status' => 'processing',
                            'processed_rows' => $processed,
                            'total_rows' => $totalRows,
                            'success_rows' => $processed,
                            'failed_rows' => 0,
                            'timestamp' => date('c'),
                        ])
                        ->now();
                }
            });

            // Run import
            $startTime = microtime(true);
            $result = Tabula::import($import, $filePath);
            $elapsed = microtime(true) - $startTime;

            $this->reportProgress(80, 'Import completed, running post-import tasks...');

            // Update job with results
            ImportExportJobModel::where('id', $this->jobId)->update([
                'processed_rows' => $result->getTotalRows(),
                'success_rows' => $result->getSuccessRows(),
                'failed_rows' => $result->getFailedRows(),
            ]);

            // Restore triggers
            $this->restoreTriggers();
            $triggersDropped = false;
            $this->reportProgress(85, 'Triggers restored');

            // Dispatch post-import processing job (ES indexing, stats recalculation)
            $this->reportProgress(90, 'Dispatching post-import tasks...');
            dispatch(new PostImportPostProcessingJob($maxIdBeforeImport, 500));
            Log::info('PostImportPostProcessingJob dispatched', [
                'job_id' => $this->jobId,
                'max_id_before_import' => $maxIdBeforeImport,
            ]);

            // Mark as completed
            $job->markAsCompleted();

            $message = sprintf(
                'Import completed: %s rows imported (%s failed) in %.1f seconds',
                number_format($result->getSuccessRows()),
                number_format($result->getFailedRows()),
                $elapsed
            );

            ImportExportJobModel::where('id', $this->jobId)->update([
                'message' => $message,
            ]);

            // Final broadcast with completed status and full stats
            $this->broadcastProgress(100, $message, 'completed', [
                'processed_rows' => $result->getTotalRows(),
                'total_rows' => $totalRows,
                'success_rows' => $result->getSuccessRows(),
                'failed_rows' => $result->getFailedRows(),
                'elapsed_seconds' => round($elapsed, 2),
            ]);

            Log::info("ImportPostsJob completed", [
                'job_id' => $this->jobId,
                'success_rows' => $result->getSuccessRows(),
                'failed_rows' => $result->getFailedRows(),
                'elapsed' => $elapsed,
            ]);
        } catch (\Throwable $e) {
            // Check if this was a user cancellation
            if ($e->getMessage() === 'Import cancelled by user') {
                Log::info("ImportPostsJob cancelled by user", [
                    'job_id' => $this->jobId,
                    'processed_rows' => $lastProgress ?? 0,
                ]);

                // Job is already marked as cancelled in DB, just broadcast
                $this->broadcastProgress(
                    $lastProgress ?? 0,
                    'Import cancelled by user',
                    'cancelled',
                    ['cancelled' => true]
                );

                // Don't re-throw - this is expected behavior
                return;
            }

            Log::error("ImportPostsJob failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $job->markAsFailed($e->getMessage());

            // Broadcast failure status
            $this->broadcastProgress(
                $lastProgress ?? 0,
                "Import failed: {$e->getMessage()}",
                'failed',
                ['error' => $e->getMessage()]
            );

            throw $e;
        } finally {
            // Ensure triggers are restored
            if ($triggersDropped) {
                $this->restoreTriggers();
            }

            // Clean up uploaded file
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ImportPostsJob permanently failed", [
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
            "Import permanently failed: {$exception->getMessage()}",
            'failed',
            ['error' => $exception->getMessage(), 'permanent' => true]
        );

        // Clean up uploaded file
        $filePath = $this->resolveFilePath($this->filePath);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Resolve file path - support both relative and absolute paths.
     *
     * If path starts with 'app/' (relative), prepend storage_path().
     * Otherwise, return as-is (absolute path).
     */
    private function resolveFilePath(string $path): string
    {
        // If it's a relative path (starts with app/), resolve to storage
        if (str_starts_with($path, 'app/')) {
            return storage_path($path);
        }

        // If it's an absolute path but from different environment, try to fix
        if (str_contains($path, '/storage/app/')) {
            // Extract relative part after /storage/
            $parts = explode('/storage/', $path);
            if (count($parts) >= 2) {
                return storage_path($parts[count($parts) - 1]);
            }
        }

        // Return as-is for absolute paths
        return $path;
    }

    /**
     * Count rows in CSV file.
     */
    private function countRows(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');

        if ($handle) {
            // Skip header - PHP 8.4 requires explicit escape parameter
            fgetcsv($handle, 0, ',', '"', '\\');

            while (fgetcsv($handle, 0, ',', '"', '\\') !== false) {
                $count++;
            }

            fclose($handle);
        }

        return $count;
    }

    /**
     * Drop triggers temporarily for faster import.
     */
    private function dropTriggers(): void
    {
        $db = \Toporia\Framework\Support\Accessors\QueryBuilder::getConnection();

        foreach ($this->postsTriggers as $trigger) {
            try {
                $db->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }
    }

    /**
     * Broadcast progress update via WebSocket.
     *
     * Sends real-time updates to subscribed clients on channel: import.jobs.{jobId}
     *
     * @param int $progress Progress percentage (0-100)
     * @param string $message Status message
     * @param string $status Job status (processing, completed, failed)
     * @param array $extra Extra data to include (rows, etc)
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
                    'timestamp' => date('c'),
                ], $extra))
                ->now();
        } catch (\Throwable $e) {
            // Non-blocking - don't fail import if broadcast fails
            Log::debug("Broadcast failed for import job", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Report progress - updates DB and broadcasts via WebSocket.
     *
     * @param int $progress Progress percentage (0-100)
     * @param string|null $message Status message
     */
    public function reportProgress(int $progress, ?string $message = null): void
    {
        $message = $message ?? "Processing: {$progress}%";

        // Update database record
        ImportExportJobModel::where('id', $this->jobId)->update([
            'progress' => $progress,
            'message' => $message,
        ]);

        // Broadcast via WebSocket for real-time updates
        $status = match (true) {
            $progress >= 100 => 'completed',
            $progress === 0 => 'starting',
            default => 'processing',
        };

        $this->broadcastProgress($progress, $message, $status);
    }

    /**
     * Restore triggers after import.
     */
    private function restoreTriggers(): void
    {
        $db = \Toporia\Framework\Support\Accessors\QueryBuilder::getConnection();

        // Recreate dashboard statistics triggers for posts
        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_posts_after_insert
            AFTER INSERT ON posts
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_total';
                UPDATE dashboard_statistics SET stat_value = stat_value + NEW.views, updated_at = NOW() WHERE stat_key = 'posts_total_views';

                IF NEW.is_published = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                ELSEIF NEW.scheduled_at IS NOT NULL THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_posts_after_update
            AFTER UPDATE ON posts
            FOR EACH ROW
            BEGIN
                IF OLD.views != NEW.views THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value + (NEW.views - OLD.views), updated_at = NOW() WHERE stat_key = 'posts_total_views';
                END IF;

                IF OLD.is_published != NEW.is_published OR
                   (OLD.scheduled_at IS NULL) != (NEW.scheduled_at IS NULL) THEN

                    IF OLD.is_published = 1 THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                    ELSEIF OLD.scheduled_at IS NOT NULL THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                    END IF;

                    IF NEW.is_published = 1 THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                    ELSEIF NEW.scheduled_at IS NOT NULL THEN
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                    ELSE
                        UPDATE dashboard_statistics SET stat_value = stat_value + 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                    END IF;
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_posts_after_delete
            AFTER DELETE ON posts
            FOR EACH ROW
            BEGIN
                UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_total';
                UPDATE dashboard_statistics SET stat_value = stat_value - OLD.views, updated_at = NOW() WHERE stat_key = 'posts_total_views';

                IF OLD.is_published = 1 THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_published';
                ELSEIF OLD.scheduled_at IS NOT NULL THEN
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_scheduled';
                ELSE
                    UPDATE dashboard_statistics SET stat_value = stat_value - 1, updated_at = NOW() WHERE stat_key = 'posts_draft';
                END IF;
            END
        ");

        // Recreate category counts triggers
        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_category_counts_after_insert
            AFTER INSERT ON posts
            FOR EACH ROW
            BEGIN
                IF NEW.category_id IS NOT NULL THEN
                    INSERT IGNORE INTO category_post_counts (category_id, published_count, total_count, updated_at)
                    VALUES (NEW.category_id, 0, 0, NOW());

                    UPDATE category_post_counts
                    SET total_count = total_count + 1, updated_at = NOW()
                    WHERE category_id = NEW.category_id;

                    IF NEW.is_published = 1 AND (NEW.published_at IS NULL OR NEW.published_at <= NOW()) THEN
                        UPDATE category_post_counts
                        SET published_count = published_count + 1, updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    END IF;
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_category_counts_after_update
            AFTER UPDATE ON posts
            FOR EACH ROW
            BEGIN
                DECLARE old_was_published BOOLEAN;
                DECLARE new_is_published BOOLEAN;

                SET old_was_published = (OLD.is_published = 1 AND (OLD.published_at IS NULL OR OLD.published_at <= NOW()));
                SET new_is_published = (NEW.is_published = 1 AND (NEW.published_at IS NULL OR NEW.published_at <= NOW()));

                IF OLD.category_id != NEW.category_id OR
                   (OLD.category_id IS NULL AND NEW.category_id IS NOT NULL) OR
                   (OLD.category_id IS NOT NULL AND NEW.category_id IS NULL) THEN

                    IF OLD.category_id IS NOT NULL THEN
                        UPDATE category_post_counts
                        SET total_count = GREATEST(total_count - 1, 0), updated_at = NOW()
                        WHERE category_id = OLD.category_id;

                        IF old_was_published THEN
                            UPDATE category_post_counts
                            SET published_count = GREATEST(published_count - 1, 0), updated_at = NOW()
                            WHERE category_id = OLD.category_id;
                        END IF;
                    END IF;

                    IF NEW.category_id IS NOT NULL THEN
                        INSERT IGNORE INTO category_post_counts (category_id, published_count, total_count, updated_at)
                        VALUES (NEW.category_id, 0, 0, NOW());

                        UPDATE category_post_counts
                        SET total_count = total_count + 1, updated_at = NOW()
                        WHERE category_id = NEW.category_id;

                        IF new_is_published THEN
                            UPDATE category_post_counts
                            SET published_count = published_count + 1, updated_at = NOW()
                            WHERE category_id = NEW.category_id;
                        END IF;
                    END IF;

                ELSEIF NEW.category_id IS NOT NULL AND old_was_published != new_is_published THEN
                    IF new_is_published THEN
                        UPDATE category_post_counts
                        SET published_count = published_count + 1, updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    ELSE
                        UPDATE category_post_counts
                        SET published_count = GREATEST(published_count - 1, 0), updated_at = NOW()
                        WHERE category_id = NEW.category_id;
                    END IF;
                END IF;
            END
        ");

        $db->unprepared("
            CREATE TRIGGER IF NOT EXISTS trg_category_counts_after_delete
            AFTER DELETE ON posts
            FOR EACH ROW
            BEGIN
                IF OLD.category_id IS NOT NULL THEN
                    UPDATE category_post_counts
                    SET total_count = GREATEST(total_count - 1, 0), updated_at = NOW()
                    WHERE category_id = OLD.category_id;

                    IF OLD.is_published = 1 AND (OLD.published_at IS NULL OR OLD.published_at <= NOW()) THEN
                        UPDATE category_post_counts
                        SET published_count = GREATEST(published_count - 1, 0), updated_at = NOW()
                        WHERE category_id = OLD.category_id;
                    END IF;
                END IF;
            END
        ");
    }
}
