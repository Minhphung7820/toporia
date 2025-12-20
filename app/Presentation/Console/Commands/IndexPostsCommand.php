<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Application\Services\Search\PostSearchService;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Console\Command;

/**
 * Index Posts to Elasticsearch Command
 *
 * Indexes all posts to Elasticsearch for fast full-text search.
 * Optimized for large datasets with batch processing.
 *
 * Usage:
 *   php console search:index-posts              # Index all posts
 *   php console search:index-posts --fresh      # Delete index and reindex
 *   php console search:index-posts --chunk=1000 # Custom chunk size
 *   php console search:index-posts --from-id=774000  # Resume from specific ID
 */
final class IndexPostsCommand extends Command
{
    protected string $signature = 'search:index-posts {--fresh : Delete and recreate the index} {--chunk=500 : Number of posts per batch} {--from-id=0 : Start from specific post ID}';

    protected string $description = 'Index all posts to Elasticsearch';

    public function __construct(
        private readonly PostSearchService $postSearchService
    ) {}

    public function handle(): int
    {
        if (!$this->postSearchService->isAvailable()) {
            $this->error('Elasticsearch is not available. Check your configuration and connection.');
            return 1;
        }

        $fresh = $this->option('fresh');
        $chunkSize = (int) $this->option('chunk');
        $fromId = (int) $this->option('from-id');

        if ($fresh) {
            $this->info('Recreating index...');
            $this->postSearchService->recreateIndex();
            $this->writeln('  Index recreated');
        } else {
            $this->info('Ensuring index exists...');
            $this->postSearchService->ensureIndex();
            $this->writeln('  Index ready');
        }

        $this->newLine();

        // Count total posts to index
        $totalPosts = PostModel::count();
        $postsToProcess = $fromId > 0
            ? PostModel::query()->where('id', '>', $fromId)->count()
            : $totalPosts;

        if ($fromId > 0) {
            $this->info("Resuming from ID {$fromId}...");
            $this->writeln("  Posts remaining: {$postsToProcess} / {$totalPosts}");
        } else {
            $this->info("Indexing {$totalPosts} posts...");
        }

        if ($postsToProcess === 0) {
            $this->warn('No posts to index.');
            return 0;
        }

        $startTime = microtime(true);
        $indexed = 0;
        $failed = 0;
        $lastId = $fromId;

        // Process in chunks using cursor-based pagination for memory efficiency
        while (true) {
            $posts = PostModel::query()
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($chunkSize)
                ->get();

            $batchCount = $posts->count();
            if ($batchCount === 0) {
                break;
            }

            try {
                $count = $this->postSearchService->bulkIndex($posts);
                $indexed += $count;
            } catch (\Throwable $e) {
                $failed += $batchCount;
                $this->warn("\n  Batch failed: {$e->getMessage()}");
            }

            $lastId = $posts->last()->id;

            // Progress output
            $progress = round(($indexed + $failed) / $postsToProcess * 100, 1);
            $this->write("\r  Progress: {$progress}% ({$indexed} indexed, {$failed} failed)");
        }

        $this->newLine();
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $rate = $totalPosts > 0 ? round($indexed / max($elapsed, 0.01)) : 0;

        $this->success("Indexing completed in {$elapsed}s");
        $this->writeln("  Total posts: {$totalPosts}");
        $this->writeln("  Indexed: {$indexed}");

        if ($failed > 0) {
            $this->warn("  Failed: {$failed}");
        }

        $this->writeln("  Rate: {$rate} docs/sec");

        // Show index stats
        $this->newLine();
        $this->info('Index statistics:');
        $stats = $this->postSearchService->getIndexStats();
        if (isset($stats['error'])) {
            $this->warn("  Could not get stats: {$stats['error']}");
        } else {
            $docsCount = $stats['_all']['primaries']['docs']['count'] ?? 'N/A';
            $storeSize = $stats['_all']['primaries']['store']['size_in_bytes'] ?? 0;
            $this->writeln("  Documents: {$docsCount}");
            $this->writeln("  Size: " . $this->formatBytes($storeSize));
        }

        return 0;
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
