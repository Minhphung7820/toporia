<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Imports\PostsImport;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Support\Accessors\QueryBuilder;
use Toporia\Tabula\Tabula;

/**
 * Import Posts CSV Command
 *
 * Imports posts from CSV file using Tabula.
 * Optimized for large datasets with batch processing.
 *
 * Supports parallel import with --parallel option for ~4-6x speedup.
 *
 * Triggers are automatically disabled during import for performance.
 *
 * After import completes, automatically runs:
 * 1. stats:recalculate - Update pre-computed statistics
 * 2. search:index-posts - Index new posts to Elasticsearch
 */
final class ImportPostsCsvCommand extends Command
{
    protected string $signature = 'posts:import {file=storage/app/posts.csv : CSV file path} {--parallel=0 : Number of parallel workers (0=sequential)} {--driver=process : Concurrency driver (process, fork, sync)}';

    protected string $description = 'Import posts from CSV file using Tabula';

    /**
     * Triggers to disable during import.
     */
    private array $postsTriggers = [
        'trg_posts_after_insert',
        'trg_posts_after_update',
        'trg_posts_after_delete',
        'trg_category_counts_after_insert',
        'trg_category_counts_after_update',
        'trg_category_counts_after_delete',
    ];

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

        $this->output?->writeln("  Triggers: DISABLED");

        $this->newLine();

        $startTime = microtime(true);
        $triggersDropped = false;

        // Get max ID before import to only index new posts later
        $maxIdBeforeImport = PostModel::max('id') ?? 0;

        try {
            // Always disable triggers for bulk import performance
            $this->dropTriggers();
            $triggersDropped = true;

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

            // Always run post-import tasks
            $this->newLine();
            $this->info('Running post-import tasks...');

            // 1. Recalculate statistics
            $this->output?->writeln('  [1/2] Recalculating statistics...');
            $this->call('stats:recalculate');

            // 2. Index new posts to Elasticsearch (only posts added during this import)
            $this->output?->writeln('  [2/2] Indexing new posts to Elasticsearch...');
            $this->call('search:index-posts', ['--chunk' => 1000, '--from-id' => $maxIdBeforeImport]);

            $this->newLine();
            $this->success('All post-import tasks completed!');
        } catch (\Throwable $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        } finally {
            // Restore triggers if they were dropped
            if ($triggersDropped) {
                $this->restoreTriggers();
            }
        }

        return 0;
    }

    /**
     * Drop triggers temporarily for faster import.
     */
    private function dropTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        foreach ($this->postsTriggers as $trigger) {
            try {
                $db->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }
    }

    /**
     * Restore triggers after import.
     */
    private function restoreTriggers(): void
    {
        $db = QueryBuilder::getConnection();

        // Recreate dashboard statistics triggers for posts
        $db->unprepared("
            CREATE TRIGGER trg_posts_after_insert
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
            CREATE TRIGGER trg_posts_after_update
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
            CREATE TRIGGER trg_posts_after_delete
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
            CREATE TRIGGER trg_category_counts_after_insert
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
            CREATE TRIGGER trg_category_counts_after_update
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
            CREATE TRIGGER trg_category_counts_after_delete
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
