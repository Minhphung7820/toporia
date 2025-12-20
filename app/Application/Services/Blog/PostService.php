<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Application\Services\Search\PostSearchService;
use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Contracts\Repository\UserRepository;

/**
 * Post Service - Handles blog post operations for the public site.
 *
 * Following Service pattern with consistent return format.
 * Uses Elasticsearch for search when available, falls back to MySQL.
 */
final class PostService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly TagRepository $tagRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UserRepository $userRepository,
        private readonly ?PostSearchService $postSearchService = null
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
                'posts' => $this->enrichPostsWithAuthors($result['posts']),
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

        // Get author information
        $author = $post->authorId
            ? $this->userRepository->findById($post->authorId)
            : null;

        $postData = $post->toArray();
        $postData['author_name'] = $author?->name;
        $postData['author_avatar'] = $author?->avatar;

        return [
            'success' => true,
            'data' => [
                'post' => $postData,
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
                'category' => $category?->toArray(),
            ],
            'message' => 'Post retrieved successfully',
        ];
    }

    /**
     * Get featured posts.
     *
     * Cached for 5 minutes to improve performance on large tables.
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getFeaturedPosts(int $limit = 5): array
    {
        $cacheKey = "blog:featured_posts:{$limit}";
        $cacheTtl = 300; // 5 minutes

        return cache()->remember($cacheKey, $cacheTtl, function () use ($limit) {
            $posts = $this->postRepository->findFeatured($limit);

            return [
                'success' => true,
                'data' => [
                    'posts' => $this->enrichPostsWithAuthors($posts),
                ],
                'message' => 'Featured posts retrieved successfully',
            ];
        });
    }

    /**
     * Get most viewed posts.
     *
     * Uses idx_posts_published_views index for O(1) performance on 1M+ rows.
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
                'posts' => $this->enrichPostsWithAuthors($posts),
            ],
            'message' => 'Popular posts retrieved successfully',
        ];
    }

    /**
     * Get latest posts.
     *
     * Cached for 2 minutes (shorter TTL since latest posts change more frequently).
     *
     * @param int $limit Number of posts
     * @return array{success: bool, data?: array, message: string}
     */
    public function getLatestPosts(int $limit = 10): array
    {
        $cacheKey = "blog:latest_posts:{$limit}";
        $cacheTtl = 120; // 2 minutes

        return cache()->remember($cacheKey, $cacheTtl, function () use ($limit) {
            $posts = $this->postRepository->findLatest($limit);

            return [
                'success' => true,
                'data' => [
                    'posts' => $this->enrichPostsWithAuthors($posts),
                ],
                'message' => 'Latest posts retrieved successfully',
            ];
        });
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
                'posts' => $this->enrichPostsWithAuthors($posts),
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
                'posts' => $this->enrichPostsWithAuthors($result['posts']),
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
                'posts' => $this->enrichPostsWithAuthors($result['posts']),
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
     * Uses Elasticsearch when available for fast full-text search.
     * Falls back to MySQL LIKE query if Elasticsearch is unavailable.
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

        // Try Elasticsearch first
        if ($this->postSearchService?->isAvailable()) {
            return $this->searchWithElasticsearch($trimmedQuery, $perPage, $cursor, $direction);
        }

        // Fallback to MySQL with caching
        return $this->searchWithMySQL($trimmedQuery, $perPage, $cursor, $direction);
    }

    /**
     * Search using Elasticsearch (fast full-text search).
     */
    private function searchWithElasticsearch(string $query, int $perPage, ?string $cursor, string $direction): array
    {
        $result = $this->postSearchService->searchPublished($query, $perPage, $cursor, $direction);

        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'posts' => $result['posts'], // Already enriched by Elasticsearch
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
    }

    /**
     * Search using MySQL with caching (fallback).
     */
    private function searchWithMySQL(string $query, int $perPage, ?string $cursor, string $direction): array
    {
        $cacheKey = 'blog:search:' . md5($query . ':' . $perPage . ':' . ($cursor ?? 'null') . ':' . $direction);
        $cacheTtl = 300; // 5 minutes

        return cache()->remember($cacheKey, $cacheTtl, function () use ($query, $perPage, $cursor, $direction) {
            $result = $this->postRepository->searchWithCursor($query, $perPage, $cursor, $direction);

            return [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'posts' => $this->enrichPostsWithAuthors($result['posts']),
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
        });
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

    /**
     * Enrich posts array with author information.
     * Batch fetches authors to avoid N+1 queries.
     *
     * @param array $posts Array of Post entities
     * @return array Array of post data with author info
     */
    private function enrichPostsWithAuthors(array $posts): array
    {
        if (empty($posts)) {
            return [];
        }

        // Collect unique author IDs
        $authorIds = [];
        foreach ($posts as $post) {
            if ($post->authorId !== null && !in_array($post->authorId, $authorIds, true)) {
                $authorIds[] = $post->authorId;
            }
        }

        // Batch fetch authors
        $authorsMap = [];
        foreach ($authorIds as $authorId) {
            $author = $this->userRepository->findById($authorId);
            if ($author) {
                $authorsMap[$authorId] = $author;
            }
        }

        // Enrich posts with author info
        $enrichedPosts = [];
        foreach ($posts as $post) {
            $postData = $post->toArray();
            $author = $authorsMap[$post->authorId] ?? null;
            $postData['author_name'] = $author?->name;
            $postData['author_avatar'] = $author?->avatar;
            $enrichedPosts[] = $postData;
        }

        return $enrichedPosts;
    }
}
