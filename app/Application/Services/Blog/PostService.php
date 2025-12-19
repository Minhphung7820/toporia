<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Contracts\Repository\CategoryRepository;

/**
 * Post Service - Handles blog post operations for the public site.
 *
 * Following Service pattern with consistent return format.
 */
final class PostService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly TagRepository $tagRepository,
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * Get published posts with cursor pagination (optimized).
     *
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPublishedPosts(int $perPage = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $result = $this->postRepository->findPublishedWithCursor($perPage, $cursor, $direction);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $result['posts']),
                'pagination' => [
                    'next_cursor' => $result['next_cursor'],
                    'prev_cursor' => $result['prev_cursor'],
                    'has_more' => $result['has_more'],
                    'per_page' => $perPage,
                ],
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get a single post by slug.
     *
     * @param string $slug Post slug
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostBySlug(string $slug): array
    {
        $post = $this->postRepository->findBySlug($slug);

        if (!$post) {
            return [
                'success' => false,
                'message' => 'Post not found',
            ];
        }

        if (!$post->isPublished) {
            return [
                'success' => false,
                'message' => 'Post not available',
            ];
        }

        // Get related data
        $tags = $this->tagRepository->findByPost($post->id);
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
     * Get featured posts.
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getFeaturedPosts(int $limit = 5): array
    {
        $posts = $this->postRepository->findFeatured($limit);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Featured posts retrieved successfully',
        ];
    }

    /**
     * Get most viewed posts.
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getMostViewedPosts(int $limit = 10): array
    {
        $posts = $this->postRepository->findMostViewed($limit);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Popular posts retrieved successfully',
        ];
    }

    /**
     * Get latest posts.
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getLatestPosts(int $limit = 10): array
    {
        $posts = $this->postRepository->findLatest($limit);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Latest posts retrieved successfully',
        ];
    }

    /**
     * Get related posts.
     *
     * @param int $postId Current post ID
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getRelatedPosts(int $postId, int $limit = 4): array
    {
        $posts = $this->postRepository->findRelated($postId, $limit);

        return [
            'success' => true,
            'data' => [
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Related posts retrieved successfully',
        ];
    }

    /**
     * Get posts by category with cursor pagination.
     *
     * @param string $categorySlug Category slug
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostsByCategory(string $categorySlug, int $perPage = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $category = $this->categoryRepository->findBySlug($categorySlug);

        if (!$category) {
            return [
                'success' => false,
                'message' => 'Category not found',
            ];
        }

        $result = $this->postRepository->findByCategoryWithCursor($category->id, $perPage, $cursor, $direction);

        return [
            'success' => true,
            'data' => [
                'category' => $category->toArray(),
                'posts' => array_map(fn($post) => $post->toArray(), $result['posts']),
                'pagination' => [
                    'next_cursor' => $result['next_cursor'],
                    'prev_cursor' => $result['prev_cursor'],
                    'has_more' => $result['has_more'],
                    'per_page' => $perPage,
                ],
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get posts by tag with cursor pagination.
     *
     * @param string $tagSlug Tag slug
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostsByTag(string $tagSlug, int $perPage = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $tag = $this->tagRepository->findBySlug($tagSlug);

        if (!$tag) {
            return [
                'success' => false,
                'message' => 'Tag not found',
            ];
        }

        $result = $this->postRepository->findByTagWithCursor($tag->id, $perPage, $cursor, $direction);

        return [
            'success' => true,
            'data' => [
                'tag' => $tag->toArray(),
                'posts' => array_map(fn($post) => $post->toArray(), $result['posts']),
                'pagination' => [
                    'next_cursor' => $result['next_cursor'],
                    'prev_cursor' => $result['prev_cursor'],
                    'has_more' => $result['has_more'],
                    'per_page' => $perPage,
                ],
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Search posts with cursor pagination.
     *
     * Uses caching for search count (expensive operation).
     * FULLTEXT search for queries >= 3 chars, LIKE for shorter.
     *
     * @param string $query Search query
     * @param int $perPage Posts per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{success: bool, data?: array, message: string}
     */
    public function searchPosts(string $query, int $perPage = 10, ?string $cursor = null, string $direction = 'next'): array
    {
        $trimmedQuery = trim($query);
        if (strlen($trimmedQuery) < 2) {
            return [
                'success' => false,
                'message' => 'Search query too short',
            ];
        }

        // Cache key for search results (5 minutes TTL)
        $cacheKey = 'blog:search:' . md5($trimmedQuery . ':' . $perPage . ':' . ($cursor ?? 'null') . ':' . $direction);
        $cacheTtl = 300; // 5 minutes

        $cachedResult = cache($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $this->postRepository->searchWithCursor($trimmedQuery, $perPage, $cursor, $direction);

        $response = [
            'success' => true,
            'data' => [
                'query' => $trimmedQuery,
                'posts' => array_map(fn($post) => $post->toArray(), $result['posts']),
                'pagination' => [
                    'next_cursor' => $result['next_cursor'],
                    'prev_cursor' => $result['prev_cursor'],
                    'has_more' => $result['has_more'],
                    'per_page' => $perPage,
                    'total' => $result['total'],
                ],
            ],
            'message' => 'Search completed successfully',
        ];

        // Cache the result
        cache($cacheKey, $response, $cacheTtl);

        return $response;
    }

    /**
     * Increment post views.
     *
     * @param int $postId Post ID
     * @return array{success: bool, message: string}
     */
    public function incrementViews(int $postId): array
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return [
                'success' => false,
                'message' => 'Post not found',
            ];
        }

        $this->postRepository->incrementViews($postId);

        return [
            'success' => true,
            'message' => 'View recorded',
        ];
    }
}
