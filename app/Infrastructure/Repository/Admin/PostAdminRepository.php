<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\CursorPaginator;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * Post Admin Repository
 *
 * Extends framework BaseRepository for admin post management.
 * Provides all CRUD operations with pagination support.
 */
final class PostAdminRepository extends BaseRepository
{
    protected string $model = PostModel::class;

    /**
     * Get paginated posts with optional filters.
     *
     * Optimized for large datasets (1M+ records):
     * - Uses indexed columns for ordering (id instead of created_at)
     * - Selects only needed columns to reduce memory
     * - Eager loads relations with specific columns
     *
     * @param array $filters Filter criteria (status, category_id, author_id, search, is_featured)
     * @param int $perPage Items per page
     * @param int $page Current page
     * @return Paginator
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->with(['author:id,name', 'category:id,name,slug'])
            ->applyFilters($filters)
            ->orderBy('id', 'desc')
            ->paginate($perPage, $page, [
                'id',
                'title',
                'slug',
                'excerpt',
                'featured_image',
                'views',
                'is_published',
                'is_featured',
                'author_id',
                'category_id',
                'published_at',
                'scheduled_at',
                'created_at'
            ]);
    }

    /**
     * Get cursor-paginated posts with optional filters.
     *
     * Ultra-fast for large datasets - O(1) performance regardless of position.
     * Uses cursor (last seen ID) instead of offset for pagination.
     *
     * @param array $filters Filter criteria
     * @param int $perPage Items per page
     * @param string|null $cursor Last seen ID (cursor)
     * @return CursorPaginator
     */
    public function getCursorPaginated(array $filters = [], int $perPage = 20, ?string $cursor = null): CursorPaginator
    {
        return $this
            ->with(['author:id,name', 'category:id,name,slug'])
            ->applyFilters($filters)
            ->orderBy('id', 'desc')
            ->cursorPaginate($perPage, $cursor, [
                'id',
                'title',
                'slug',
                'excerpt',
                'featured_image',
                'views',
                'is_published',
                'is_featured',
                'author_id',
                'category_id',
                'published_at',
                'scheduled_at',
                'created_at'
            ]);
    }

    /**
     * Apply filters to the query.
     *
     * @param array $filters
     * @return static
     */
    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                // Status filter
                if (!empty($filters['status'])) {
                    switch ($filters['status']) {
                        case 'published':
                            $query->where('is_published', true);
                            break;
                        case 'draft':
                            $query->where('is_published', false)->whereNull('scheduled_at');
                            break;
                        case 'scheduled':
                            $query->where('is_published', false)->whereNotNull('scheduled_at');
                            break;
                    }
                }

                // Category filter
                if (!empty($filters['category_id'])) {
                    $query->where('category_id', (int) $filters['category_id']);
                }

                // Author filter
                if (!empty($filters['author_id'])) {
                    $query->where('author_id', (int) $filters['author_id']);
                }

                // Search filter - only search title for performance
                // Searching content with LIKE causes full table scan on large datasets
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where('title', 'LIKE', "%{$search}%");
                }

                // Featured filter
                if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
                    $query->where('is_featured', (bool) $filters['is_featured']);
                }
            });
        }

        return $this;
    }

    /**
     * Find post by slug.
     *
     * @param string $slug
     * @return PostModel|null
     */
    public function findBySlug(string $slug): ?PostModel
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Check if slug exists (optionally excluding a post ID).
     *
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->getQuery()->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get post with all relations for editing.
     *
     * @param int $id
     * @return PostModel|null
     */
    public function findForEdit(int $id): ?PostModel
    {
        return $this
            ->with(['author', 'category', 'tags'])
            ->find($id);
    }

    /**
     * Sync tags for a post.
     *
     * @param int $postId
     * @param array $tagIds
     * @return void
     */
    public function syncTags(int $postId, array $tagIds): void
    {
        $post = $this->find($postId);
        if ($post && method_exists($post, 'tags')) {
            $post->tags()->sync($tagIds);
        }
    }

    /**
     * Increment post views.
     *
     * @param int $postId
     * @return bool
     */
    public function incrementViews(int $postId): bool
    {
        $post = $this->find($postId);
        if ($post) {
            $post->views = ($post->views ?? 0) + 1;
            return $post->save();
        }
        return false;
    }

    /**
     * Get post statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->count(),
            'published' => $this->countWhere(['is_published' => true]),
            'draft' => $this->raw(fn($q) => $q->where('is_published', false)->whereNull('scheduled_at')->count()),
            'scheduled' => $this->raw(fn($q) => $q->where('is_published', false)->whereNotNull('scheduled_at')->count()),
            'featured' => $this->countWhere(['is_featured' => true]),
            'total_views' => $this->sum('views'),
        ];
    }
}
