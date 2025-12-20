<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Application\Services\Search\PostSearchService;
use App\Infrastructure\Repository\Admin\PostAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Post Admin Service
 *
 * Handles admin post operations with clean architecture.
 * Uses repository pattern for data access.
 * Uses Elasticsearch for search when available.
 */
final class PostAdminService
{
    public function __construct(
        private readonly PostAdminRepository $postRepository,
        private readonly ?PostSearchService $postSearchService = null
    ) {}

    /**
     * Get paginated posts with filters (offset-based).
     *
     * Uses Elasticsearch for search queries when available.
     * Falls back to MySQL for filtering without search.
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data: array, message: string}
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        // Use Elasticsearch when search query is present
        $searchQuery = $filters['search'] ?? '';
        if (!empty($searchQuery) && $this->postSearchService?->isAvailable()) {
            return $this->searchWithElasticsearch($searchQuery, $filters, $perPage, ($page - 1) * $perPage);
        }

        // Use MySQL for non-search queries or when ES is unavailable
        $paginator = $this->postRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Search posts using Elasticsearch.
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Results per page
     * @param int $offset Offset for pagination
     * @return array{success: bool, data: array, message: string}
     */
    private function searchWithElasticsearch(string $query, array $filters, int $limit, int $offset): array
    {
        $esFilters = [];

        // Map filters to Elasticsearch format
        if (!empty($filters['status'])) {
            $esFilters['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $esFilters['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['author_id'])) {
            $esFilters['author_id'] = (int) $filters['author_id'];
        }
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $esFilters['is_featured'] = (bool) $filters['is_featured'];
        }

        $result = $this->postSearchService->searchAdmin($query, $esFilters, $limit, $offset);

        // Calculate pagination info
        $total = $result['total'];
        $currentPage = $offset > 0 ? (int) floor($offset / $limit) + 1 : 1;
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($result['posts']), $total);

        // Return format matching Paginator->toArray() for frontend compatibility
        return [
            'success' => true,
            'data' => [
                'data' => $result['posts'],  // Must be 'data' to match Paginator format
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get cursor-paginated posts with filters.
     *
     * Uses Elasticsearch for search queries when available.
     * Falls back to MySQL for non-search queries or when ES is unavailable.
     *
     * @param array $filters Filter criteria
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor (last seen ID)
     * @return array{success: bool, data: array, message: string}
     */
    public function getCursorPaginated(array $filters = [], int $perPage = 20, ?string $cursor = null): array
    {
        // Use Elasticsearch when search query is present
        $searchQuery = $filters['search'] ?? '';
        if (!empty($searchQuery) && $this->postSearchService?->isAvailable()) {
            return $this->searchWithElasticsearchCursor($searchQuery, $filters, $perPage, $cursor);
        }

        // Use MySQL for non-search queries or when ES is unavailable
        $paginator = $this->postRepository->getCursorPaginated($filters, $perPage, $cursor);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Search posts using Elasticsearch with cursor pagination.
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Results per page
     * @param string|null $cursor Cursor for pagination
     * @return array{success: bool, data: array, message: string}
     */
    private function searchWithElasticsearchCursor(string $query, array $filters, int $limit, ?string $cursor): array
    {
        $esFilters = [];

        // Map filters to Elasticsearch format
        if (!empty($filters['status'])) {
            $esFilters['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $esFilters['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['author_id'])) {
            $esFilters['author_id'] = (int) $filters['author_id'];
        }
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $esFilters['is_featured'] = (bool) $filters['is_featured'];
        }

        $result = $this->postSearchService->searchAdminCursor($query, $esFilters, $limit, $cursor);

        // Return format matching CursorPaginator->toArray() for frontend compatibility
        return [
            'success' => true,
            'data' => [
                'data' => $result['posts'],  // Must be 'data' to match CursorPaginator format
                'per_page' => $limit,
                'next_cursor' => $result['next_cursor'],
                'prev_cursor' => $result['prev_cursor'],
                'has_more' => $result['has_more'],
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get a single post for editing.
     *
     * @param int $postId Post ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPost(int $postId): array
    {
        $post = $this->postRepository->findForEdit($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        // Get tags for this post
        $tags = [];
        if (method_exists($post, 'tags')) {
            $postTags = $post->tags;
            if ($postTags) {
                $tags = $postTags->toArray();
            }
        }

        return [
            'success' => true,
            'data' => [
                'post' => $post->toArray(),
                'tags' => $tags,
            ],
            'message' => 'Post retrieved successfully',
        ];
    }

    /**
     * Create a new post.
     *
     * @param array $data Post data
     * @param int $authorId Author user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function createPost(array $data, int $authorId): array
    {
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        // Generate slug
        $slug = $data['slug'] ?? Str::slug($data['title']);
        if ($this->postRepository->slugExists($slug)) {
            $slug = $slug . '-' . time();
        }

        // Calculate reading time
        $content = $data['content'] ?? '';
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        // Convert empty strings to null for nullable fields
        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

        $post = $this->postRepository->create([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => ($data['excerpt'] ?? null) ?: null,
            'featured_image' => ($data['featured_image'] ?? null) ?: null,
            'views' => 0,
            'reading_time' => $readingTime,
            'is_published' => false,
            'is_featured' => $data['is_featured'] ?? false,
            'author_id' => $authorId,
            'category_id' => $categoryId,
            'meta_title' => ($data['meta_title'] ?? null) ?: null,
            'meta_description' => ($data['meta_description'] ?? null) ?: null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
        ]);

        // Sync tags
        if (!empty($data['tag_ids'])) {
            $this->postRepository->syncTags($post->id, $data['tag_ids']);
        }

        AdminActivityLogModel::log(
            $authorId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created post: {$post->title}",
            'Post',
            $post->id
        );

        return [
            'success' => true,
            'data' => $post->toArray(),
            'message' => 'Post created successfully',
        ];
    }

    /**
     * Update an existing post.
     *
     * @param int $postId Post ID
     * @param array $data Post data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updatePost(int $postId, array $data, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        // Handle slug
        $slug = $data['slug'] ?? $post->slug;
        if ($slug !== $post->slug && $this->postRepository->slugExists($slug, $postId)) {
            $slug = $slug . '-' . time();
        }

        // Calculate reading time
        $content = $data['content'] ?? $post->content;
        $wordCount = str_word_count(strip_tags($content ?? ''));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        // Convert empty strings to null for nullable fields
        // Use array_key_exists to distinguish between "not provided" and "provided as empty"
        $categoryId = array_key_exists('category_id', $data)
            ? (!empty($data['category_id']) ? (int) $data['category_id'] : null)
            : $post->category_id;

        $updatedPost = $this->postRepository->update($postId, [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => array_key_exists('excerpt', $data) ? ($data['excerpt'] ?: null) : $post->excerpt,
            'featured_image' => array_key_exists('featured_image', $data) ? ($data['featured_image'] ?: null) : $post->featured_image,
            'reading_time' => $readingTime,
            'is_featured' => $data['is_featured'] ?? $post->is_featured,
            'category_id' => $categoryId,
            'meta_title' => array_key_exists('meta_title', $data) ? ($data['meta_title'] ?: null) : $post->meta_title,
            'meta_description' => array_key_exists('meta_description', $data) ? ($data['meta_description'] ?: null) : $post->meta_description,
            'meta_keywords' => array_key_exists('meta_keywords', $data) ? ($data['meta_keywords'] ?: null) : $post->meta_keywords,
        ]);

        // Sync tags
        if (isset($data['tag_ids'])) {
            $this->postRepository->syncTags($postId, $data['tag_ids']);
        }

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated post: {$updatedPost->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => $updatedPost->toArray(),
            'message' => 'Post updated successfully',
        ];
    }

    /**
     * Delete a post.
     *
     * @param int $postId Post ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deletePost(int $postId, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $title = $post->title;
        $this->postRepository->delete($postId);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted post: {$title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'message' => 'Post deleted successfully',
        ];
    }

    /**
     * Publish a post.
     *
     * @param int $postId Post ID
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function publishPost(int $postId, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $updatedPost = $this->postRepository->update($postId, [
            'is_published' => true,
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_PUBLISH,
            "Published post: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => $updatedPost->toArray(),
            'message' => 'Post published successfully',
        ];
    }

    /**
     * Unpublish a post.
     *
     * @param int $postId Post ID
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function unpublishPost(int $postId, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $updatedPost = $this->postRepository->update($postId, [
            'is_published' => false,
            'published_at' => null,
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UNPUBLISH,
            "Unpublished post: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => $updatedPost->toArray(),
            'message' => 'Post unpublished successfully',
        ];
    }

    /**
     * Schedule a post.
     *
     * @param int $postId Post ID
     * @param string $scheduledAt Scheduled date/time
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function schedulePost(int $postId, string $scheduledAt, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        try {
            $scheduleDate = new \DateTimeImmutable($scheduledAt);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        if ($scheduleDate <= new \DateTimeImmutable()) {
            return ['success' => false, 'message' => 'Schedule date must be in the future'];
        }

        $updatedPost = $this->postRepository->update($postId, [
            'is_published' => false,
            'scheduled_at' => $scheduleDate->format('Y-m-d H:i:s'),
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Scheduled post: {$post->title} for {$scheduledAt}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => $updatedPost->toArray(),
            'message' => 'Post scheduled successfully',
        ];
    }

    /**
     * Toggle featured status.
     *
     * @param int $postId Post ID
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function toggleFeatured(int $postId, int $userId): array
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $updatedPost = $this->postRepository->update($postId, [
            'is_featured' => !$post->is_featured,
        ]);

        $status = $updatedPost->is_featured ? 'featured' : 'unfeatured';

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Marked post as {$status}: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => $updatedPost->toArray(),
            'message' => "Post {$status} successfully",
        ];
    }
}
