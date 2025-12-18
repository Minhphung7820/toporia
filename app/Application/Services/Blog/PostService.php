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
     * Get published posts with pagination.
     *
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPublishedPosts(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $posts = $this->postRepository->findPublished($perPage, $offset);
        $total = $this->postRepository->countPublished();

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
     * Get posts by category.
     *
     * @param string $categorySlug Category slug
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostsByCategory(string $categorySlug, int $page = 1, int $perPage = 10): array
    {
        $category = $this->categoryRepository->findBySlug($categorySlug);

        if (!$category) {
            return [
                'success' => false,
                'message' => 'Category not found',
            ];
        }

        $offset = ($page - 1) * $perPage;
        $posts = $this->postRepository->findByCategory($category->id, $perPage, $offset);

        return [
            'success' => true,
            'data' => [
                'category' => $category->toArray(),
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Get posts by tag.
     *
     * @param string $tagSlug Tag slug
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostsByTag(string $tagSlug, int $page = 1, int $perPage = 10): array
    {
        $tag = $this->tagRepository->findBySlug($tagSlug);

        if (!$tag) {
            return [
                'success' => false,
                'message' => 'Tag not found',
            ];
        }

        $offset = ($page - 1) * $perPage;
        $posts = $this->postRepository->findByTag($tag->id, $perPage, $offset);

        return [
            'success' => true,
            'data' => [
                'tag' => $tag->toArray(),
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Posts retrieved successfully',
        ];
    }

    /**
     * Search posts.
     *
     * @param string $query Search query
     * @param int $page Page number
     * @param int $perPage Posts per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function searchPosts(string $query, int $page = 1, int $perPage = 10): array
    {
        if (strlen(trim($query)) < 2) {
            return [
                'success' => false,
                'message' => 'Search query too short',
            ];
        }

        $offset = ($page - 1) * $perPage;
        $posts = $this->postRepository->search($query, $perPage, $offset);

        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'posts' => array_map(fn($post) => $post->toArray(), $posts),
            ],
            'message' => 'Search completed successfully',
        ];
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
