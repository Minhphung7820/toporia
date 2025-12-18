<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\PostAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Post Admin Service
 *
 * Handles admin post operations with clean architecture.
 * Uses repository pattern for data access.
 */
final class PostAdminService
{
    public function __construct(
        private readonly PostAdminRepository $postRepository
    ) {}

    /**
     * Get paginated posts with filters (offset-based).
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data: array, message: string}
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->postRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get cursor-paginated posts with filters.
     *
     * Ultra-fast for large datasets - O(1) performance.
     *
     * @param array $filters Filter criteria
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor (last seen ID)
     * @return array{success: bool, data: array, message: string}
     */
    public function getCursorPaginated(array $filters = [], int $perPage = 20, ?string $cursor = null): array
    {
        $paginator = $this->postRepository->getCursorPaginated($filters, $perPage, $cursor);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
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

        $post = $this->postRepository->create([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $data['excerpt'] ?? null,
            'featured_image' => $data['featured_image'] ?? null,
            'views' => 0,
            'reading_time' => $readingTime,
            'is_published' => false,
            'is_featured' => $data['is_featured'] ?? false,
            'author_id' => $authorId,
            'category_id' => $data['category_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
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

        $updatedPost = $this->postRepository->update($postId, [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $data['excerpt'] ?? $post->excerpt,
            'featured_image' => $data['featured_image'] ?? $post->featured_image,
            'reading_time' => $readingTime,
            'is_featured' => $data['is_featured'] ?? $post->is_featured,
            'category_id' => $data['category_id'] ?? $post->category_id,
            'meta_title' => $data['meta_title'] ?? $post->meta_title,
            'meta_description' => $data['meta_description'] ?? $post->meta_description,
            'meta_keywords' => $data['meta_keywords'] ?? $post->meta_keywords,
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
