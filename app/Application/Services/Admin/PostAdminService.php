<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Entities\Post;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Post Admin Service - Handles admin post operations.
 *
 * Following Service pattern with consistent return format.
 */
final class PostAdminService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly TagRepository $tagRepository,
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * Get all posts with filters (for admin list).
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllPosts(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $posts = $this->postRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->postRepository->countAll();

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
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
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $tags = $this->tagRepository->findByPost($postId);
        $category = $post->categoryId
            ? $this->categoryRepository->findById($post->categoryId)
            : null;

        return [
            'success' => true,
            'data' => [
                'post' => $post->toArray(),
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
                'category' => $category?->toArray(),
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
        // Validate required fields
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        // Generate slug if not provided
        $slug = $data['slug'] ?? Str::slug($data['title']);

        // Check slug uniqueness
        if ($this->postRepository->findBySlug($slug)) {
            $slug = $slug . '-' . time();
        }

        // Calculate reading time
        $content = $data['content'] ?? '';
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $post = new Post(
            id: null,
            title: $data['title'],
            slug: $slug,
            content: $content,
            excerpt: $data['excerpt'] ?? null,
            featuredImage: $data['featured_image'] ?? null,
            views: 0,
            readingTime: $readingTime,
            isPublished: false,
            isFeatured: $data['is_featured'] ?? false,
            authorId: $authorId,
            categoryId: $data['category_id'] ?? null,
            metaTitle: $data['meta_title'] ?? null,
            metaDescription: $data['meta_description'] ?? null,
            metaKeywords: $data['meta_keywords'] ?? null,
            publishedAt: null,
            scheduledAt: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedPost = $this->postRepository->save($post);

        // Sync tags if provided
        if (!empty($data['tag_ids'])) {
            $this->postRepository->syncTags($savedPost->id, $data['tag_ids']);
        }

        // Log activity
        AdminActivityLogModel::log(
            $authorId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created post: {$savedPost->title}",
            'Post',
            $savedPost->id
        );

        return [
            'success' => true,
            'data' => ['post' => $savedPost->toArray()],
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
        $existingPost = $this->postRepository->findById($postId);

        if (!$existingPost) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        // Validate required fields
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        // Handle slug update
        $slug = $data['slug'] ?? $existingPost->slug;
        if ($slug !== $existingPost->slug) {
            $existing = $this->postRepository->findBySlug($slug);
            if ($existing && $existing->id !== $postId) {
                $slug = $slug . '-' . time();
            }
        }

        // Calculate reading time
        $content = $data['content'] ?? $existingPost->content;
        $wordCount = str_word_count(strip_tags($content ?? ''));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $updatedPost = new Post(
            id: $postId,
            title: $data['title'],
            slug: $slug,
            content: $content,
            excerpt: $data['excerpt'] ?? $existingPost->excerpt,
            featuredImage: $data['featured_image'] ?? $existingPost->featuredImage,
            views: $existingPost->views,
            readingTime: $readingTime,
            isPublished: $existingPost->isPublished,
            isFeatured: $data['is_featured'] ?? $existingPost->isFeatured,
            authorId: $existingPost->authorId,
            categoryId: $data['category_id'] ?? $existingPost->categoryId,
            metaTitle: $data['meta_title'] ?? $existingPost->metaTitle,
            metaDescription: $data['meta_description'] ?? $existingPost->metaDescription,
            metaKeywords: $data['meta_keywords'] ?? $existingPost->metaKeywords,
            publishedAt: $existingPost->publishedAt,
            scheduledAt: $existingPost->scheduledAt,
            createdAt: $existingPost->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedPost = $this->postRepository->save($updatedPost);

        // Sync tags if provided
        if (isset($data['tag_ids'])) {
            $this->postRepository->syncTags($postId, $data['tag_ids']);
        }

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated post: {$savedPost->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => ['post' => $savedPost->toArray()],
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
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $this->postRepository->delete($post);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted post: {$post->title}",
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
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $publishedPost = $post->publish();
        $this->postRepository->save($publishedPost);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_PUBLISH,
            "Published post: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => ['post' => $publishedPost->toArray()],
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
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $unpublishedPost = $post->unpublish();
        $this->postRepository->save($unpublishedPost);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UNPUBLISH,
            "Unpublished post: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => ['post' => $unpublishedPost->toArray()],
            'message' => 'Post unpublished successfully',
        ];
    }

    /**
     * Schedule a post for future publication.
     *
     * @param int $postId Post ID
     * @param string $scheduledAt Scheduled date/time
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function schedulePost(int $postId, string $scheduledAt, int $userId): array
    {
        $post = $this->postRepository->findById($postId);

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

        $scheduledPost = $post->schedule($scheduleDate);
        $this->postRepository->save($scheduledPost);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Scheduled post: {$post->title} for {$scheduledAt}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => ['post' => $scheduledPost->toArray()],
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
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        $toggledPost = $post->toggleFeatured();
        $this->postRepository->save($toggledPost);

        $status = $toggledPost->isFeatured ? 'featured' : 'unfeatured';

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Marked post as {$status}: {$post->title}",
            'Post',
            $postId
        );

        return [
            'success' => true,
            'data' => ['post' => $toggledPost->toArray()],
            'message' => "Post {$status} successfully",
        ];
    }
}
