<?php

declare(strict_types=1);

namespace App\Application\Observers;

use App\Application\Services\Search\PostSearchService;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * Post Observer
 *
 * Handles lifecycle events for the PostModel.
 * Auto-syncs posts with Elasticsearch for real-time search updates.
 *
 * Events handled:
 * - saved: Index/update post in Elasticsearch after create/update
 * - deleted: Remove post from Elasticsearch after delete
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
                // Search service not available
                return null;
            }
        }

        return $this->searchService;
    }

    /**
     * Handle the PostModel "saved" event (after create or update).
     */
    public function saved(PostModel $post): void
    {
        $this->syncToElasticsearch($post);
    }

    /**
     * Handle the PostModel "deleted" event.
     */
    public function deleted(PostModel $post): void
    {
        $this->removeFromElasticsearch($post);
    }

    /**
     * Sync post to Elasticsearch.
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
            // Log error but don't fail the main operation
            log_error('Failed to index post to Elasticsearch', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove post from Elasticsearch.
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
            // Log error but don't fail the main operation
            log_error('Failed to remove post from Elasticsearch', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
