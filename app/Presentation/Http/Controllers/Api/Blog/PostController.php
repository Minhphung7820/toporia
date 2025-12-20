<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\PostService;
use App\Application\Services\SiteSettingsService;
use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\DashboardStatisticsModel;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Post Controller - Public blog post endpoints.
 *
 * Uses SiteSettingsService for default values (cached for performance).
 * Settings are enforced from database, client can only request within limits.
 */
final class PostController extends BaseController
{
    private SiteSettingsService $settings;

    public function __construct(
        Request $request,
        Response $response,
        private readonly PostService $postService
    ) {
        parent::__construct($request, $response);
        $this->settings = SiteSettingsService::getInstance();
    }

    /**
     * Get published posts with cursor pagination.
     *
     * GET /api/blog/posts
     */
    public function index(Request $request): JsonResponseInterface
    {
        // Use settings default, allow override up to max 50
        $defaultPerPage = $this->settings->postsPerPage();
        $perPage = $this->getLimit($request, 'per_page', $defaultPerPage, 50);
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
        // Use settings default, allow override up to max 20
        $defaultLimit = $this->settings->featuredPostsCount();
        $limit = $this->getLimit($request, 'limit', $defaultLimit, 20);

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
        // Use settings default, allow override up to max 50
        $defaultLimit = $this->settings->popularPostsCount();
        $limit = $this->getLimit($request, 'limit', $defaultLimit, 50);

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
        // Use settings default, allow override up to max 50
        $defaultLimit = $this->settings->postsPerPage();
        $limit = $this->getLimit($request, 'limit', $defaultLimit, 50);

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
        // Use settings default, allow override up to max 10
        $defaultLimit = $this->settings->relatedPostsCount();
        $limit = $this->getLimit($request, 'limit', $defaultLimit, 10);

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
        $defaultPerPage = $this->settings->postsPerPage();
        $perPage = $this->getLimit($request, 'per_page', $defaultPerPage, 50);
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
        $defaultPerPage = $this->settings->postsPerPage();
        $perPage = $this->getLimit($request, 'per_page', $defaultPerPage, 50);
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
        $defaultPerPage = $this->settings->postsPerPage();
        $perPage = $this->getLimit($request, 'per_page', $defaultPerPage, 50);
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

    /**
     * Get limit from request with default and max constraints.
     */
    private function getLimit(Request $request, string $param, int $default, int $max): int
    {
        $value = $request->query($param);

        // If not provided, use default from settings
        if ($value === null || $value === '') {
            return $default;
        }

        // Ensure within bounds: min 1, max as specified
        return max(1, min((int) $value, $max));
    }
}
