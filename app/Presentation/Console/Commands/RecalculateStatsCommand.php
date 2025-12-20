<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Persistence\Models\CategoryPostCountModel;
use App\Infrastructure\Persistence\Models\DashboardStatisticsModel;
use Toporia\Framework\Console\Command;

/**
 * Recalculate Dashboard Statistics Command
 *
 * Manually recalculates all pre-computed statistics:
 * - dashboard_statistics table (posts, comments, feedback counts)
 * - category_post_counts table (posts per category)
 *
 * Use this if triggers fail or for periodic validation of statistics accuracy.
 *
 * WARNING: This is an expensive operation on large tables (8M+ records).
 * It will scan all posts, comments, and feedback tables.
 *
 * Usage:
 *   php console stats:recalculate                    # Recalculate all stats
 *   php console stats:recalculate --show             # Show stats after recalculation
 *   php console stats:recalculate --categories-only  # Only recalculate category counts
 *   php console stats:recalculate --dashboard-only   # Only recalculate dashboard stats
 */
final class RecalculateStatsCommand extends Command
{
    protected string $signature = 'stats:recalculate {--show : Show statistics after recalculation} {--categories-only : Only recalculate category post counts} {--dashboard-only : Only recalculate dashboard statistics}';

    protected string $description = 'Recalculate all pre-computed statistics (dashboard + category counts)';

    /**
     * Execute the command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $this->warn('⚠️  This operation scans all posts, comments, and feedback tables.');
        $this->warn('   On large datasets (8M+ records), this may take several minutes.');
        $this->newLine();

        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $categoriesOnly = $this->option('categories-only');
        $dashboardOnly = $this->option('dashboard-only');
        $startTime = microtime(true);

        try {
            // Recalculate dashboard statistics
            if (!$categoriesOnly) {
                $this->info('Recalculating dashboard statistics...');
                DashboardStatisticsModel::recalculateAll();
                $this->writeln('  ✓ Dashboard statistics done');
            }

            // Recalculate category post counts
            if (!$dashboardOnly) {
                $this->info('Recalculating category post counts...');
                CategoryPostCountModel::recalculateAll();
                $this->writeln('  ✓ Category post counts done');
            }

            $elapsedTime = round(microtime(true) - $startTime, 2);

            $this->success("✅ Statistics recalculated successfully in {$elapsedTime}s");

            if ($this->option('show')) {
                $this->showCurrentStats();
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error('❌ Failed to recalculate statistics: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display current statistics.
     */
    private function showCurrentStats(): void
    {
        $this->newLine();
        $this->info('Current Statistics:');
        $this->newLine();

        $allStats = DashboardStatisticsModel::getAllStats();

        // Posts table
        $this->table(
            ['Posts Metric', 'Value'],
            [
                ['Total', number_format($allStats['posts']['total'])],
                ['Published', number_format($allStats['posts']['published'])],
                ['Draft', number_format($allStats['posts']['draft'])],
                ['Scheduled', number_format($allStats['posts']['scheduled'])],
                ['Total Views', number_format($allStats['posts']['total_views'])],
            ]
        );

        // Comments table
        $this->table(
            ['Comments Metric', 'Value'],
            [
                ['Total', number_format($allStats['comments']['total'])],
                ['Pending', number_format($allStats['comments']['pending'])],
                ['Approved', number_format($allStats['comments']['approved'])],
            ]
        );

        // Feedback table
        $this->table(
            ['Feedback Metric', 'Value'],
            [
                ['Total', number_format($allStats['feedback']['total'])],
                ['Pending', number_format($allStats['feedback']['pending'])],
            ]
        );

        // Category post counts
        $categoryCounts = CategoryPostCountModel::getAllCounts();
        $categoryRows = [];
        $totalPublished = 0;
        foreach ($categoryCounts as $categoryId => $counts) {
            $totalPublished += $counts['published'];
        }

        $this->table(
            ['Category Stats', 'Value'],
            [
                ['Categories with counts', number_format(count($categoryCounts))],
                ['Total published posts (sum)', number_format($totalPublished)],
            ]
        );
    }
}
