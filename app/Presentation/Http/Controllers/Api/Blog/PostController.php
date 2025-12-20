<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\PostService;
use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\DashboardStatisticsModel;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Post Controller - Public blog post endpoints.
 */
final class PostController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly PostService $postService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get published posts with cursor pagination.
     *
     * GET /api/blog/posts
     */
    public function index(Request $request): JsonResponseInterface
    {
        $perPage = min((int) $request->query('per_page', 10), 50);
        $cursor = $request->query('cursor');
        $direction = $request->query('direction', 'next');

        $result = $this->postService->getPublishedPosts($perPage, $cursor, $direction);

        return $this->json($result);
    }

    /**
     * Get a single post by slug.
     *
     * GET /api/blog/posts/{slug}
     */
    public function show(string $slug): JsonResponseInterface
    {
        $result = $this->postService->getPostBySlug($slug);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get featured posts.
     *
     * GET /api/blog/posts/featured
     */
    public function featured(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 5), 20);

        $result = $this->postService->getFeaturedPosts($limit);

        return $this->json($result);
    }

    /**
     * Get most viewed posts.
     *
     * GET /api/blog/posts/popular
     */
    public function popular(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 10), 50);

        $result = $this->postService->getMostViewedPosts($limit);

        return $this->json($result);
    }

    /**
     * Get latest posts.
     *
     * GET /api/blog/posts/latest
     */
    public function latest(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 10), 50);

        $result = $this->postService->getLatestPosts($limit);

        return $this->json($result);
    }

    /**
     * Get related posts.
     *
     * GET /api/blog/posts/{id}/related
     */
    public function related(int $id, Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 4), 10);

        $result = $this->postService->getRelatedPosts($id, $limit);

        return $this->json($result);
    }

    /**
     * Get posts by category with cursor pagination.
     *
     * GET /api/blog/categories/{slug}/posts
     */
    public function byCategory(string $slug, Request $request): JsonResponseInterface
    {
        $perPage = min((int) $request->query('per_page', 10), 50);
        $cursor = $request->query('cursor');
        $direction = $request->query('direction', 'next');

        $result = $this->postService->getPostsByCategory($slug, $perPage, $cursor, $direction);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get posts by tag with cursor pagination.
     *
     * GET /api/blog/tags/{slug}/posts
     */
    public function byTag(string $slug, Request $request): JsonResponseInterface
    {
        $perPage = min((int) $request->query('per_page', 10), 50);
        $cursor = $request->query('cursor');
        $direction = $request->query('direction', 'next');

        $result = $this->postService->getPostsByTag($slug, $perPage, $cursor, $direction);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Search posts with cursor pagination.
     *
     * GET /api/blog/search
     */
    public function search(Request $request): JsonResponseInterface
    {
        $query = $request->query('q', '');
        $perPage = min((int) $request->query('per_page', 10), 50);
        $cursor = $request->query('cursor');
        $direction = $request->query('direction', 'next');

        $result = $this->postService->searchPosts($query, $perPage, $cursor, $direction);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result);
    }

    /**
     * Increment post views.
     *
     * POST /api/blog/posts/{id}/views
     */
    public function incrementViews(int $id): JsonResponseInterface
    {
        $result = $this->postService->incrementViews($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get blog statistics for homepage.
     *
     * GET /api/blog/stats
     */
    public function stats(): JsonResponseInterface
    {
        $postStats = DashboardStatisticsModel::getPostStats();
        $categoriesCount = CategoryModel::count();

        return $this->json([
            'success' => true,
            'data' => [
                'posts' => $postStats['published'],
                'categories' => $categoriesCount,
                'views' => $postStats['total_views'],
            ],
        ]);
    }
}
