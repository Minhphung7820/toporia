<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Backoff\ExponentialBackoff;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Facades\Console;

/**
 * Post Import Post-Processing Job
 *
 * Runs post-import tasks after ImportPostsJob completes:
 * 1. Index new posts to Elasticsearch (from maxId)
 * 2. Recalculate dashboard and category statistics
 *
 * This job is dispatched by ImportPostsJob after successful import.
 */
final class PostImportPostProcessingJob extends Job implements ShouldQueueInterface
{
    protected int $timeout = 1800; // 30 minutes max

    public function __construct(
        private readonly int $maxIdBeforeImport,
        private readonly int $chunkSize = 1000,
        ?string $queueName = null
    ) {
        parent::__construct();

        $this->tries(3);
        $this->backoff(new ExponentialBackoff(base: 30, max: 120));

        // Use provided queue name or default to 'import-export-high'
        if ($queueName !== null) {
            $this->onQueue($queueName);
        } else {
            $this->onQueue('import-export-high');
        }
    }

    public function handle(): void
    {
        // Reconnect database to avoid "MySQL server has gone away" error
        // This is critical for long-running jobs where connection may timeout
        DB::reconnect();

        Log::info('PostImportPostProcessingJob started', [
            'max_id_before_import' => $this->maxIdBeforeImport,
            'chunk_size' => $this->chunkSize,
        ]);

        $startTime = microtime(true);

        // 1. Index new posts to Elasticsearch
        $this->runIndexPostsCommand();

        // 2. Recalculate statistics
        $this->runRecalculateStatsCommand();

        $elapsed = round(microtime(true) - $startTime, 2);

        Log::info('PostImportPostProcessingJob completed', [
            'elapsed_seconds' => $elapsed,
        ]);
    }

    /**
     * Run search:index-posts command to index new posts.
     *
     * Command: php console search:index-posts --from-id={maxId} --chunk={chunkSize}
     */
    private function runIndexPostsCommand(): void
    {
        Log::info('Running search:index-posts command', [
            'from_id' => $this->maxIdBeforeImport,
            'chunk' => $this->chunkSize,
        ]);

        try {
            $exitCode = Console::call('search:index-posts', [
                '--from-id' => $this->maxIdBeforeImport,
                '--chunk' => $this->chunkSize,
            ]);

            if ($exitCode === 0) {
                Log::info('search:index-posts completed successfully');
            } else {
                Log::warning('search:index-posts exited with non-zero code', [
                    'exit_code' => $exitCode,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('search:index-posts failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Run stats:recalculate command to update statistics.
     *
     * Command: php console stats:recalculate
     */
    private function runRecalculateStatsCommand(): void
    {
        Log::info('Running stats:recalculate command');

        try {
            $exitCode = Console::call('stats:recalculate');

            if ($exitCode === 0) {
                Log::info('stats:recalculate completed successfully');
            } else {
                Log::warning('stats:recalculate exited with non-zero code', [
                    'exit_code' => $exitCode,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('stats:recalculate failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PostImportPostProcessingJob permanently failed', [
            'max_id_before_import' => $this->maxIdBeforeImport,
            'error' => $exception->getMessage(),
        ]);
    }
}
