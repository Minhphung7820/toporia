<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\CategoryService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Category Controller - Public category endpoints.
 */
final class CategoryController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly CategoryService $categoryService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all active categories.
     *
     * GET /api/blog/categories
     */
    public function index(): JsonResponseInterface
    {
        $result = $this->categoryService->getAllCategories();

        return $this->json($result);
    }

    /**
     * Get category by slug.
     *
     * GET /api/blog/categories/{slug}
     */
    public function show(string $slug): JsonResponseInterface
    {
        $result = $this->categoryService->getCategoryBySlug($slug);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get categories tree structure.
     *
     * GET /api/blog/categories/tree
     */
    public function tree(): JsonResponseInterface
    {
        $result = $this->categoryService->getCategoriesTree();

        return $this->json($result);
    }

    /**
     * Get categories with post counts.
     *
     * GET /api/blog/categories/with-counts
     */
    public function withCounts(): JsonResponseInterface
    {
        $result = $this->categoryService->getCategoriesWithPostCounts();

        return $this->json($result);
    }

    /**
     * Get categories with published posts only.
     *
     * GET /api/blog/categories/with-posts
     */
    public function withPosts(Request $request): JsonResponseInterface
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : null;

        $result = $this->categoryService->getCategoriesWithPublishedPosts($limit);

        return $this->json($result);
    }

    /**
     * Get categories tree with published post counts.
     *
     * GET /api/blog/categories/tree-with-counts
     */
    public function treeWithCounts(): JsonResponseInterface
    {
        $result = $this->categoryService->getCategoriesTreeWithCounts();

        return $this->json($result);
    }
}
