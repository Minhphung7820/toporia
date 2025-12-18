<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\TagService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Tag Controller - Public tag endpoints.
 */
final class TagController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly TagService $tagService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all tags.
     *
     * GET /api/blog/tags
     */
    public function index(): JsonResponseInterface
    {
        $result = $this->tagService->getAllTags();

        return $this->json($result);
    }

    /**
     * Get tag by slug.
     *
     * GET /api/blog/tags/{slug}
     */
    public function show(string $slug): JsonResponseInterface
    {
        $result = $this->tagService->getTagBySlug($slug);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get popular tags.
     *
     * GET /api/blog/tags/popular
     */
    public function popular(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 20), 100);

        $result = $this->tagService->getPopularTags($limit);

        return $this->json($result);
    }

    /**
     * Get tag cloud data.
     *
     * GET /api/blog/tags/cloud
     */
    public function cloud(Request $request): JsonResponseInterface
    {
        $limit = min((int) $request->query('limit', 50), 100);

        $result = $this->tagService->getTagCloud($limit);

        return $this->json($result);
    }

    /**
     * Search tags.
     *
     * GET /api/blog/tags/search
     */
    public function search(Request $request): JsonResponseInterface
    {
        $query = $request->query('q', '');
        $limit = min((int) $request->query('limit', 10), 50);

        $result = $this->tagService->searchTags($query, $limit);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result);
    }
}
