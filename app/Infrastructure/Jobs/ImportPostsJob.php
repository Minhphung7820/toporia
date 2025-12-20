<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Imports\PostsImport;
use App\Infrastructure\Jobs\Middleware\LazyWithoutOverlapping;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Realtime\Broadcast;
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
        $this->reportProgress(0, 'Starting import...');

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
            ]);

            $this->reportProgress(5, "Found {$totalRows} rows to import");

            // Disable triggers for performance
            $this->dropTriggers();
            $triggersDropped = true;
            $this->reportProgress(10, 'Triggers disabled, starting import...');

            // Create import instance
            $import = $this->workers > 0
                ? PostsImport::parallel($this->workers, 'process')
                : PostsImport::make();

            // Configure progress callback with WebSocket broadcasting
            $lastProgress = 10;
            $import->withProgressCallback(function (int $processed, int $total) use (&$lastProgress, $job, $totalRows) {
                // Calculate progress (10-80% for import phase)
                $progress = 10 + (int) (($processed / max(1, $total)) * 70);

                // Only update if progress changed significantly (>= 2%)
                if ($progress >= $lastProgress + 2) {
                    $lastProgress = $progress;

                    // Update job record
                    $job->updateProcessedRows($processed, $processed, 0);

                    // Broadcast with extra row info
                    $this->broadcastProgress($progress, "Importing: {$processed}/{$total} rows", 'processing', [
                        'processed_rows' => $processed,
                        'total_rows' => $totalRows,
                        'success_rows' => $processed,
                        'failed_rows' => 0,
                    ]);
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

            // Run post-import tasks (stats recalculation, ES indexing)
            $this->runPostImportTasks($maxIdBeforeImport);

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
     * Run post-import tasks.
     *
     * 1. Recalculate dashboard statistics
     * 2. Recalculate category post counts
     * 3. Bulk index new posts to Elasticsearch
     */
    private function runPostImportTasks(int $maxIdBeforeImport): void
    {
        // 1. Recalculate dashboard statistics
        $this->reportProgress(90, 'Recalculating dashboard statistics...');
        try {
            \App\Infrastructure\Persistence\Models\DashboardStatisticsModel::recalculateAll();
            Log::info("Dashboard statistics recalculated");
        } catch (\Throwable $e) {
            Log::warning("Failed to recalculate dashboard stats", ['error' => $e->getMessage()]);
        }

        // 2. Recalculate category post counts
        $this->reportProgress(92, 'Recalculating category counts...');
        try {
            \App\Infrastructure\Persistence\Models\CategoryPostCountModel::recalculateAll();
            Log::info("Category post counts recalculated");
        } catch (\Throwable $e) {
            Log::warning("Failed to recalculate category counts", ['error' => $e->getMessage()]);
        }

        // 3. Bulk index new posts to Elasticsearch (if available)
        $this->reportProgress(94, 'Indexing posts to Elasticsearch...');
        try {
            $searchService = app(\App\Application\Services\Search\PostSearchService::class);
            if ($searchService && $searchService->isAvailable()) {
                $this->bulkIndexNewPosts($searchService, $maxIdBeforeImport);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to index posts to Elasticsearch", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk index new posts to Elasticsearch in chunks.
     *
     * Uses chunked processing to avoid memory issues with large imports.
     * Each chunk is bulk-indexed for optimal ES performance.
     *
     * @param \App\Application\Services\Search\PostSearchService $searchService
     * @param int $maxIdBeforeImport Max post ID before import started
     */
    private function bulkIndexNewPosts($searchService, int $maxIdBeforeImport): void
    {
        $chunkSize = 1000;
        $totalIndexed = 0;

        // Count new posts
        $totalNew = PostModel::where('id', '>', $maxIdBeforeImport)->count();

        if ($totalNew === 0) {
            Log::info("No new posts to index");
            return;
        }

        Log::info("Starting ES bulk indexing", ['total' => $totalNew]);

        // Process in chunks using cursor for memory efficiency
        PostModel::where('id', '>', $maxIdBeforeImport)
            ->orderBy('id')
            ->chunk($chunkSize, function ($posts) use ($searchService, &$totalIndexed, $totalNew) {
                $indexed = $searchService->bulkIndex($posts);
                $totalIndexed += $indexed;

                // Update progress (94-98% for ES indexing phase)
                $esProgress = 94 + (int) (($totalIndexed / $totalNew) * 4);
                $this->reportProgress($esProgress, "Indexing to ES: {$totalIndexed}/{$totalNew}");

                // Memory cleanup
                if ($totalIndexed % 10000 === 0) {
                    gc_collect_cycles();
                }
            });

        Log::info("ES bulk indexing completed", ['total_indexed' => $totalIndexed]);
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
