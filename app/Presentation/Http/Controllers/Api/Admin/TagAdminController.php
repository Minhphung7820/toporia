<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\TagAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Tag Admin Controller - Admin tag management endpoints.
 */
final class TagAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly TagAdminService $tagAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all tags with pagination.
     *
     * GET /api/admin/tags
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'search' => $request->query('search'),
        ];
        $filters = array_filter($filters);

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 50), 100);

        $result = $this->tagAdminService->getPaginated($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get a single tag.
     *
     * GET /api/admin/tags/{id}
     */
    public function show(int $id): JsonResponseInterface
    {
        $result = $this->tagAdminService->getTag($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Create a new tag.
     *
     * POST /api/admin/tags
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->tagAdminService->createTag($data, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Update a tag.
     *
     * PUT /api/admin/tags/{id}
     */
    public function update(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->tagAdminService->updateTag($id, $data, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Tag not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Delete a tag.
     *
     * DELETE /api/admin/tags/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->tagAdminService->deleteTag($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk delete tags.
     *
     * POST /api/admin/tags/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $ids = $data['ids'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->tagAdminService->bulkDelete($ids, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Get tag statistics.
     *
     * GET /api/admin/tags/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $result = $this->tagAdminService->getStatistics();

        return $this->json($result);
    }

    /**
     * Clean up unused tags.
     *
     * POST /api/admin/tags/cleanup
     */
    public function cleanup(): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->tagAdminService->cleanupUnused($userId);

        return $this->json($result);
    }
}
