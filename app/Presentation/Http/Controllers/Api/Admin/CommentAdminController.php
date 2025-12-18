<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\CommentAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Comment Admin Controller - Admin comment management endpoints.
 */
final class CommentAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly CommentAdminService $commentAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all comments with filters.
     *
     * GET /api/admin/comments
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'status' => $request->query('status'),
            'post_id' => $request->query('post_id'),
            'search' => $request->query('search'),
        ];
        $filters = array_filter($filters);

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->commentAdminService->getPaginated($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get pending comments.
     *
     * GET /api/admin/comments/pending
     */
    public function pending(Request $request): JsonResponseInterface
    {
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->commentAdminService->getPending($page, $perPage);

        return $this->json($result);
    }

    /**
     * Approve a comment.
     *
     * POST /api/admin/comments/{id}/approve
     */
    public function approve(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->approveComment($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Reject a comment.
     *
     * POST /api/admin/comments/{id}/reject
     */
    public function reject(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->rejectComment($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk approve comments.
     *
     * POST /api/admin/comments/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $ids = $data['ids'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->bulkApprove($ids, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Bulk reject comments.
     *
     * POST /api/admin/comments/bulk-reject
     */
    public function bulkReject(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $ids = $data['ids'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->bulkReject($ids, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Delete a comment.
     *
     * DELETE /api/admin/comments/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->deleteComment($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk delete comments.
     *
     * POST /api/admin/comments/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $ids = $data['ids'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->commentAdminService->bulkDelete($ids, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Get comment statistics.
     *
     * GET /api/admin/comments/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $result = $this->commentAdminService->getStatistics();

        return $this->json($result);
    }
}
