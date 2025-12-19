<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Entities\Post;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * PDO Post Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Post entity and database PostModel.
 */
final class PdoPostRepository implements PostRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Post
    {
        $model = PostModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Post
    {
        $model = PostModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findPublished(int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::published()
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findFeatured(int $limit = 5): array
    {
        $models = PostModel::featured()
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     *
     * Optimized: Use indexed query for large tables.
     */
    public function findMostViewed(int $limit = 10): array
    {
        // Optimized query using index on (is_published, views DESC)
        $rows = QueryBuilder::getConnection()->select("
            SELECT id, title, slug, views, published_at, featured_image,
                   excerpt, reading_time, is_featured, author_id, category_id,
                   meta_title, meta_description, meta_keywords, scheduled_at,
                   created_at, updated_at, content, is_published
            FROM posts
            WHERE is_published = 1
            ORDER BY views DESC
            LIMIT ?
        ", [$limit]);

        return array_map(fn($row) => $this->rowToDomain($row), $rows);
    }

    /**
     * Convert raw row array to domain entity.
     */
    private function rowToDomain(array $row): \App\Domain\Entities\Post
    {
        return new \App\Domain\Entities\Post(
            id: (int) $row['id'],
            title: $row['title'],
            slug: $row['slug'] ?? '',
            content: $row['content'],
            excerpt: $row['excerpt'],
            featuredImage: $row['featured_image'],
            views: (int) ($row['views'] ?? 0),
            readingTime: (int) ($row['reading_time'] ?? 0),
            isPublished: (bool) ($row['is_published'] ?? false),
            isFeatured: (bool) ($row['is_featured'] ?? false),
            authorId: $row['author_id'] ? (int) $row['author_id'] : null,
            categoryId: $row['category_id'] ? (int) $row['category_id'] : null,
            metaTitle: $row['meta_title'],
            metaDescription: $row['meta_description'],
            metaKeywords: $row['meta_keywords'],
            publishedAt: $row['published_at'] ? new \DateTimeImmutable($row['published_at']) : null,
            scheduledAt: $row['scheduled_at'] ? new \DateTimeImmutable($row['scheduled_at']) : null,
            createdAt: $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null,
            updatedAt: $row['updated_at'] ? new \DateTimeImmutable($row['updated_at']) : null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findLatest(int $limit = 10): array
    {
        $models = PostModel::latest($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::byCategory($categoryId)
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByTag(int $tagId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::published()
            ->whereHas('tags', function ($query) use ($tagId) {
                $query->where('tags.id', $tagId);
            })
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByAuthor(int $authorId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::byAuthor($authorId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRelated(int $postId, int $limit = 5): array
    {
        $post = PostModel::find($postId);
        if (!$post) {
            return [];
        }

        // Find related posts by same category or shared tags
        $models = PostModel::published()
            ->where('id', '!=', $postId)
            ->where(function ($query) use ($post) {
                // Same category
                if ($post->category_id) {
                    $query->orWhere('category_id', $post->category_id);
                }
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, int $limit = 10, int $offset = 0): array
    {
        $searchTerm = '%' . $query . '%';

        $models = PostModel::published()
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                    ->orWhere('content', 'LIKE', $searchTerm)
                    ->orWhere('excerpt', 'LIKE', $searchTerm);
            })
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = PostModel::query();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'published' => $query->where('is_published', true),
                'draft' => $query->where('is_published', false)->whereNull('scheduled_at'),
                'scheduled' => $query->where('is_published', false)->whereNotNull('scheduled_at'),
                default => null,
            };
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                    ->orWhere('content', 'LIKE', $searchTerm);
            });
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(PostModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findScheduledReady(): array
    {
        $models = PostModel::scheduledReady()->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Post $post): Post
    {
        $data = [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'featured_image' => $post->featuredImage,
            'views' => $post->views,
            'reading_time' => $post->readingTime,
            'is_published' => $post->isPublished,
            'is_featured' => $post->isFeatured,
            'author_id' => $post->authorId,
            'category_id' => $post->categoryId,
            'meta_title' => $post->metaTitle,
            'meta_description' => $post->metaDescription,
            'meta_keywords' => $post->metaKeywords,
            'published_at' => $post->publishedAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $post->scheduledAt?->format('Y-m-d H:i:s'),
        ];

        if ($post->id === null) {
            // Create new post
            $model = PostModel::create($data);

            return $post->withId($model->id);
        }

        // Update existing post
        PostModel::where('id', $post->id)->update($data);

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Post $post): bool
    {
        if ($post->id === null) {
            return false;
        }

        return PostModel::destroy($post->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementViews(int $postId): void
    {
        PostModel::where('id', $postId)->increment('views');
    }

    /**
     * {@inheritdoc}
     */
    public function countPublished(): int
    {
        return PostModel::published()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return PostModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countByStatus(): array
    {
        return [
            'published' => PostModel::where('is_published', true)->count(),
            'draft' => PostModel::where('is_published', false)->whereNull('scheduled_at')->count(),
            'scheduled' => PostModel::where('is_published', false)->whereNotNull('scheduled_at')->count(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalViews(): int
    {
        return (int) PostModel::sum('views');
    }

    /**
     * {@inheritdoc}
     *
     * Optimized: Single query aggregation for 1M+ rows performance.
     */
    public function getStatistics(): array
    {
        // Single optimized query with conditional aggregation
        $result = PostModel::getConnection()->selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NULL THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN is_published = 0 AND scheduled_at IS NOT NULL THEN 1 ELSE 0 END) as scheduled,
                COALESCE(SUM(views), 0) as total_views
            FROM posts
        ");

        return [
            'total' => (int) ($result['total'] ?? 0),
            'published' => (int) ($result['published'] ?? 0),
            'by_status' => [
                'published' => (int) ($result['published'] ?? 0),
                'draft' => (int) ($result['draft'] ?? 0),
                'scheduled' => (int) ($result['scheduled'] ?? 0),
            ],
            'total_views' => (int) ($result['total_views'] ?? 0),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function syncTags(int $postId, array $tagIds): void
    {
        $post = PostModel::find($postId);
        if ($post) {
            $post->tags()->sync($tagIds);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function syncCategories(int $postId, array $categoryIds): void
    {
        $post = PostModel::find($postId);
        if ($post) {
            $post->categories()->sync($categoryIds);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Cursor pagination using (published_at, id) for stable ordering.
     * Much faster than OFFSET for large datasets.
     */
    public function findPublishedWithCursor(int $limit = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $cursorData = $cursor ? $this->decodeCursor($cursor) : null;

        $params = [];
        $whereClause = 'WHERE is_published = 1 AND published_at <= ?';
        $params[] = date('Y-m-d H:i:s');

        if ($cursorData && $direction === 'next') {
            // Next page: get posts older than cursor
            $whereClause .= ' AND (published_at < ? OR (published_at = ? AND id < ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        } elseif ($cursorData && $direction === 'prev') {
            // Previous page: get posts newer than cursor
            $whereClause .= ' AND (published_at > ? OR (published_at = ? AND id > ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        }

        // Fetch one extra to check if there are more
        $fetchLimit = $limit + 1;
        $orderBy = $direction === 'prev' ? 'ASC' : 'DESC';

        $rows = QueryBuilder::getConnection()->select("
            SELECT id, title, slug, excerpt, featured_image, views, published_at,
                   reading_time, is_featured, author_id, category_id,
                   meta_title, meta_description, meta_keywords, scheduled_at,
                   created_at, updated_at, content, is_published
            FROM posts
            {$whereClause}
            ORDER BY published_at {$orderBy}, id {$orderBy}
            LIMIT ?
        ", [...$params, $fetchLimit]);

        // Reverse if going backwards
        if ($direction === 'prev') {
            $rows = array_reverse($rows);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $posts = array_map(fn($row) => $this->rowToDomain($row), $rows);

        // Generate cursors
        $nextCursor = null;
        $prevCursor = null;

        if (!empty($posts)) {
            $firstPost = $rows[0];
            $lastPost = $rows[count($rows) - 1];

            if ($hasMore || $cursorData) {
                $nextCursor = $this->encodeCursor($lastPost['published_at'], (int)$lastPost['id']);
            }

            if ($cursorData) {
                $prevCursor = $this->encodeCursor($firstPost['published_at'], (int)$firstPost['id']);
            }
        }

        return [
            'posts' => $posts,
            'next_cursor' => $hasMore ? $nextCursor : null,
            'prev_cursor' => $cursorData ? $prevCursor : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function findByCategoryWithCursor(int $categoryId, int $limit = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $cursorData = $cursor ? $this->decodeCursor($cursor) : null;

        $params = [$categoryId, date('Y-m-d H:i:s')];
        $whereClause = 'WHERE category_id = ? AND is_published = 1 AND published_at <= ?';

        if ($cursorData && $direction === 'next') {
            $whereClause .= ' AND (published_at < ? OR (published_at = ? AND id < ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        } elseif ($cursorData && $direction === 'prev') {
            $whereClause .= ' AND (published_at > ? OR (published_at = ? AND id > ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        }

        $fetchLimit = $limit + 1;
        $orderBy = $direction === 'prev' ? 'ASC' : 'DESC';

        $rows = QueryBuilder::getConnection()->select("
            SELECT id, title, slug, excerpt, featured_image, views, published_at,
                   reading_time, is_featured, author_id, category_id,
                   meta_title, meta_description, meta_keywords, scheduled_at,
                   created_at, updated_at, content, is_published
            FROM posts
            {$whereClause}
            ORDER BY published_at {$orderBy}, id {$orderBy}
            LIMIT ?
        ", [...$params, $fetchLimit]);

        if ($direction === 'prev') {
            $rows = array_reverse($rows);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $posts = array_map(fn($row) => $this->rowToDomain($row), $rows);

        $nextCursor = null;
        $prevCursor = null;

        if (!empty($rows)) {
            $firstPost = $rows[0];
            $lastPost = $rows[count($rows) - 1];

            if ($hasMore) {
                $nextCursor = $this->encodeCursor($lastPost['published_at'], (int)$lastPost['id']);
            }
            if ($cursorData) {
                $prevCursor = $this->encodeCursor($firstPost['published_at'], (int)$firstPost['id']);
            }
        }

        return [
            'posts' => $posts,
            'next_cursor' => $nextCursor,
            'prev_cursor' => $prevCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function findByTagWithCursor(int $tagId, int $limit = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $cursorData = $cursor ? $this->decodeCursor($cursor) : null;

        $params = [$tagId, date('Y-m-d H:i:s')];
        $whereClause = 'WHERE t.taggable_type = ? AND t.tag_id = ? AND p.is_published = 1 AND p.published_at <= ?';
        array_unshift($params, PostModel::class);

        if ($cursorData && $direction === 'next') {
            $whereClause .= ' AND (p.published_at < ? OR (p.published_at = ? AND p.id < ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        } elseif ($cursorData && $direction === 'prev') {
            $whereClause .= ' AND (p.published_at > ? OR (p.published_at = ? AND p.id > ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        }

        $fetchLimit = $limit + 1;
        $orderBy = $direction === 'prev' ? 'ASC' : 'DESC';

        $rows = QueryBuilder::getConnection()->select("
            SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.views, p.published_at,
                   p.reading_time, p.is_featured, p.author_id, p.category_id,
                   p.meta_title, p.meta_description, p.meta_keywords, p.scheduled_at,
                   p.created_at, p.updated_at, p.content, p.is_published
            FROM posts p
            INNER JOIN taggables t ON t.taggable_id = p.id
            {$whereClause}
            ORDER BY p.published_at {$orderBy}, p.id {$orderBy}
            LIMIT ?
        ", [...$params, $fetchLimit]);

        if ($direction === 'prev') {
            $rows = array_reverse($rows);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $posts = array_map(fn($row) => $this->rowToDomain($row), $rows);

        $nextCursor = null;
        $prevCursor = null;

        if (!empty($rows)) {
            $firstPost = $rows[0];
            $lastPost = $rows[count($rows) - 1];

            if ($hasMore) {
                $nextCursor = $this->encodeCursor($lastPost['published_at'], (int)$lastPost['id']);
            }
            if ($cursorData) {
                $prevCursor = $this->encodeCursor($firstPost['published_at'], (int)$firstPost['id']);
            }
        }

        return [
            'posts' => $posts,
            'next_cursor' => $nextCursor,
            'prev_cursor' => $prevCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Encode cursor from published_at and id.
     */
    private function encodeCursor(string $publishedAt, int $id): string
    {
        return base64_encode(json_encode(['published_at' => $publishedAt, 'id' => $id]));
    }

    /**
     * Decode cursor to published_at and id.
     */
    private function decodeCursor(string $cursor): ?array
    {
        try {
            $data = json_decode(base64_decode($cursor), true);
            if (isset($data['published_at'], $data['id'])) {
                return $data;
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function searchWithCursor(string $query, int $limit = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $cursorData = $cursor ? $this->decodeCursor($cursor) : null;
        $now = date('Y-m-d H:i:s');

        // Always use LIKE on title only for performance (title is indexed)
        // LIKE '%x%' on indexed column is faster than FULLTEXT for short patterns
        $searchTerm = '%' . $query . '%';
        $searchCondition = 'title LIKE ?';
        $searchParams = [$searchTerm, $now];

        // Build cursor condition
        $whereClause = "WHERE is_published = 1 AND {$searchCondition} AND published_at <= ?";
        $params = $searchParams;

        if ($cursorData && $direction === 'next') {
            $whereClause .= ' AND (published_at < ? OR (published_at = ? AND id < ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        } elseif ($cursorData && $direction === 'prev') {
            $whereClause .= ' AND (published_at > ? OR (published_at = ? AND id > ?))';
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['published_at'];
            $params[] = $cursorData['id'];
        }

        $fetchLimit = $limit + 1;
        $orderBy = $direction === 'prev' ? 'ASC' : 'DESC';

        $rows = QueryBuilder::getConnection()->select("
            SELECT id, title, slug, excerpt, featured_image, views, published_at,
                   reading_time, is_featured, author_id, category_id,
                   meta_title, meta_description, meta_keywords, scheduled_at,
                   created_at, updated_at, content, is_published
            FROM posts
            {$whereClause}
            ORDER BY published_at {$orderBy}, id {$orderBy}
            LIMIT ?
        ", [...$params, $fetchLimit]);

        if ($direction === 'prev') {
            $rows = array_reverse($rows);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $posts = array_map(fn($row) => $this->rowToDomain($row), $rows);

        $nextCursor = null;
        $prevCursor = null;

        if (!empty($rows)) {
            $firstPost = $rows[0];
            $lastPost = $rows[count($rows) - 1];

            if ($hasMore) {
                $nextCursor = $this->encodeCursor($lastPost['published_at'], (int)$lastPost['id']);
            }
            if ($cursorData) {
                $prevCursor = $this->encodeCursor($firstPost['published_at'], (int)$firstPost['id']);
            }
        }

        return [
            'posts' => $posts,
            'next_cursor' => $nextCursor,
            'prev_cursor' => $prevCursor,
            'has_more' => $hasMore,
            'total' => null, // Skip expensive COUNT query for performance
        ];
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(PostModel $model): Post
    {
        return new Post(
            id: $model->id,
            title: $model->title,
            slug: $model->slug ?? '',
            content: $model->content,
            excerpt: $model->excerpt,
            featuredImage: $model->featured_image,
            views: $model->views ?? 0,
            readingTime: $model->reading_time ?? 0,
            isPublished: $model->is_published ?? false,
            isFeatured: $model->is_featured ?? false,
            authorId: $model->author_id,
            categoryId: $model->category_id,
            metaTitle: $model->meta_title,
            metaDescription: $model->meta_description,
            metaKeywords: $model->meta_keywords,
            publishedAt: $model->published_at ? new \DateTimeImmutable($model->published_at) : null,
            scheduledAt: $model->scheduled_at ? new \DateTimeImmutable($model->scheduled_at) : null,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
