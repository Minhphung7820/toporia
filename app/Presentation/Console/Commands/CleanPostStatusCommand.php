<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Clean inconsistent post status data.
 *
 * Fixes posts where is_published=false but published_at is not null.
 * This inconsistency causes status display issues in admin panel.
 *
 * Optimized for large tables (9M+ records):
 * - Uses chunked UPDATE with LIMIT to avoid long locks
 * - Processes in small batches to minimize disk I/O
 * - Adds sleep between batches to prevent 100% disk utilization
 * - Uses indexed column (is_published) for efficient filtering
 *
 * Usage:
 *   php console posts:clean-status              # Run cleanup
 *   php console posts:clean-status --dry-run    # Preview without changes
 *   php console posts:clean-status --batch=5000 # Custom batch size
 *   php console posts:clean-status --sleep=100  # Custom sleep (ms) between batches
 */
final class CleanPostStatusCommand extends Command
{
    protected string $signature = 'posts:clean-status
        {--dry-run : Preview changes without updating}
        {--batch=1000 : Number of records per batch}
        {--sleep=50 : Milliseconds to sleep between batches}';

    protected string $description = 'Clean inconsistent post status (is_published=false but published_at not null)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');
        $sleepMs = (int) $this->option('sleep');

        $this->info('Scanning for inconsistent post status data...');
        $this->newLine();

        // Count affected records using index
        $affectedCount = QueryBuilder::table('posts')
            ->where('is_published', false)
            ->whereNotNull('published_at')
            ->count();

        if ($affectedCount === 0) {
            $this->success('No inconsistent records found. Database is clean!');
            return 0;
        }

        $this->warn("Found {$affectedCount} posts with inconsistent status:");
        $this->writeln("  - is_published = false");
        $this->writeln("  - published_at != NULL");
        $this->newLine();

        if ($dryRun) {
            $this->info('[DRY RUN] Would update these posts to set published_at = NULL');
            $this->newLine();

            // Show sample of affected posts
            $samples = QueryBuilder::table('posts')
                ->select(['id', 'title', 'is_published', 'published_at'])
                ->where('is_published', false)
                ->whereNotNull('published_at')
                ->limit(10)
                ->get();

            $rows = [];
            foreach ($samples as $post) {
                $rows[] = [
                    $post->id,
                    substr($post->title ?? '', 0, 40) . (strlen($post->title ?? '') > 40 ? '...' : ''),
                    $post->is_published ? 'true' : 'false',
                    $post->published_at,
                ];
            }

            $this->table(['ID', 'Title', 'is_published', 'published_at'], $rows);

            if ($affectedCount > 10) {
                $this->writeln("... and " . ($affectedCount - 10) . " more records");
            }

            $this->newLine();
            $this->info('Run without --dry-run to apply changes.');
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm("Update {$affectedCount} records? This will set published_at = NULL for unpublished posts.")) {
            $this->writeln('Cancelled.');
            return 0;
        }

        $this->newLine();
        $this->info("Processing in batches of {$batchSize} with {$sleepMs}ms delay...");

        $totalUpdated = 0;
        $batchCount = 0;
        $startTime = microtime(true);

        // Process in batches using chunked UPDATE
        // This approach: UPDATE ... WHERE id IN (SELECT id FROM ... LIMIT batch)
        // More efficient than offset-based pagination for large tables
        while (true) {
            $batchCount++;

            // Get batch of IDs to update (using indexed columns)
            $ids = QueryBuilder::table('posts')
                ->select(['id'])
                ->where('is_published', false)
                ->whereNotNull('published_at')
                ->limit($batchSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            // Update batch
            $updated = QueryBuilder::table('posts')
                ->whereIn('id', $ids)
                ->update(['published_at' => null]);

            $totalUpdated += $updated;

            // Progress output
            $progress = min(100, round(($totalUpdated / $affectedCount) * 100));
            $elapsed = round(microtime(true) - $startTime, 1);
            $this->write("\r  Batch {$batchCount}: Updated {$totalUpdated}/{$affectedCount} ({$progress}%) - {$elapsed}s");

            // Sleep to prevent disk I/O overload
            if ($sleepMs > 0 && count($ids) === $batchSize) {
                usleep($sleepMs * 1000);
            }
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->newLine();
        $this->success("Cleaned {$totalUpdated} records in {$totalTime}s ({$batchCount} batches)");

        return 0;
    }
}
