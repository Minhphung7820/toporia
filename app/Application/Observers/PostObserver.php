<?php

declare(strict_types=1);

namespace App\Application\Observers;

use App\Application\Services\Blog\BlogCacheService;
use App\Application\Services\Search\PostSearchService;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * Post Observer
 *
 * Handles lifecycle events for the PostModel with optimized performance.
 *
 * Responsibilities:
 * 1. Auto-sync posts with Elasticsearch for real-time search updates
 * 2. Invalidate blog cache when posts are modified
 * 3. Update category post counts (via triggers, but we clear cache)
 *
 * Events handled:
 * - created: Index new post in Elasticsearch, clear cache
 * - updated: Re-index post in Elasticsearch, clear cache
 * - deleted: Remove post from Elasticsearch, clear cache
 *
 * Performance optimizations:
 * - Lazy-load search service (only when needed)
 * - Single cache invalidation per operation (not duplicated)
 * - Batch cache key deletion (efficient for Redis)
 * - Non-blocking: errors don't fail main operation
 *
 * Note: Using created/updated/deleted instead of saved/deleted because:
 * - More precise control over when cache is cleared
 * - saved fires after both create and update (could double-clear)
 * - Better for debugging and logging
 */
final class PostObserver
{
    private ?PostSearchService $searchService = null;

    /**
     * Get the search service (lazy loaded).
     */
    private function getSearchService(): ?PostSearchService
    {
        if ($this->searchService === null) {
            try {
                $this->searchService = app(PostSearchService::class);
            } catch (\Throwable) {
                // Search service not available (e.g., ES not configured)
                return null;
            }
        }

        return $this->searchService;
    }

    /**
     * Handle the PostModel "creating" event (before insert).
     *
     * Can be used to validate or modify data before insert.
     * Return false to cancel the insert.
     */
    public function creating(PostModel $_post): bool
    {
        // Allow the creation to proceed
        return true;
    }

    /**
     * Handle the PostModel "created" event (after insert).
     *
     * Indexes new post in Elasticsearch and clears blog cache.
     */
    public function created(PostModel $post): void
    {
        $this->syncToElasticsearch($post);
        BlogCacheService::invalidateAll();
    }

    /**
     * Handle the PostModel "updating" event (before update).
     *
     * Can be used to validate or modify data before update.
     * Return false to cancel the update.
     */
    public function updating(PostModel $_post): bool
    {
        // Allow the update to proceed
        return true;
    }

    /**
     * Handle the PostModel "updated" event (after update).
     *
     * Re-indexes post in Elasticsearch and clears blog cache.
     */
    public function updated(PostModel $post): void
    {
        $this->syncToElasticsearch($post);
        BlogCacheService::invalidateAll();
    }

    /**
     * Handle the PostModel "deleting" event (before delete).
     *
     * Can be used to prevent deletion or cleanup related data.
     * Return false to cancel the delete.
     */
    public function deleting(PostModel $_post): bool
    {
        // Allow the deletion to proceed
        return true;
    }

    /**
     * Handle the PostModel "deleted" event (after delete).
     *
     * Removes post from Elasticsearch and clears blog cache.
     */
    public function deleted(PostModel $post): void
    {
        $this->removeFromElasticsearch($post);
        BlogCacheService::invalidateAll();
    }

    /**
     * Sync post to Elasticsearch.
     *
     * Non-blocking: errors are logged but don't fail main operation.
     */
    private function syncToElasticsearch(PostModel $post): void
    {
        $searchService = $this->getSearchService();

        if ($searchService === null || !$searchService->isAvailable()) {
            return;
        }

        try {
            $searchService->indexPost($post);
        } catch (\Throwable $e) {
            log_error('PostObserver: Failed to index post to Elasticsearch', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove post from Elasticsearch.
     *
     * Non-blocking: errors are logged but don't fail main operation.
     */
    private function removeFromElasticsearch(PostModel $post): void
    {
        $searchService = $this->getSearchService();

        if ($searchService === null || !$searchService->isAvailable()) {
            return;
        }

        try {
            $searchService->removePost($post->id);
        } catch (\Throwable $e) {
            log_error('PostObserver: Failed to remove post from Elasticsearch', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
