<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\CommentAdminService;
use App\Application\Services\AuthorizationService;
use App\Infrastructure\Persistence\Models\PostModel;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Comment Admin Controller - Admin comment management endpoints.
 *
 * Authorization:
 * - Admin: Can moderate all comments
 * - Moderator: Can only moderate comments on their own posts
 */
final class CommentAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly CommentAdminService $commentAdminService,
        private readonly AuthorizationService $authService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all comments with filters.
     * Admin sees all comments, Moderator sees comments on their posts only.
     * Supports both offset and cursor-based pagination.
     *
     * GET /api/admin/comments
     * GET /api/admin/comments?pagination=cursor&limit=20&cursor=123
     */
    public function index(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $filters = [
            'status' => $request->query('status'),
            'post_id' => $request->query('post_id'),
            'search' => $request->query('search'),
        ];

        // Moderator can only see comments on their posts
        if ($this->authService->isModerator($user)) {
            $authorId = $this->authService->getUserId($user);
            $filters['post_author_id'] = $authorId;
        }

        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        // Check if cursor pagination is requested
        $cursor = $request->query('cursor');
        if ($cursor !== null || $request->query('pagination') === 'cursor') {
            $limit = min((int) $request->query('limit', 20), 100);
            $cursorValue = $cursor ? (int) $cursor : null;
            $result = $this->commentAdminService->getCursorPaginated($filters, $limit, $cursorValue);
            return $this->json($result);
        }

        // Default offset pagination
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->commentAdminService->getPaginated($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get pending comments.
     * Admin sees all pending, Moderator sees pending on their posts only.
     *
     * GET /api/admin/comments/pending
     */
    public function pending(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        // Moderator can only see pending comments on their posts
        $authorId = null;
        if ($this->authService->isModerator($user)) {
            $authorId = $this->authService->getUserId($user);
        }

        $result = $this->commentAdminService->getPending($page, $perPage, $authorId);

        return $this->json($result);
    }

    /**
     * Approve a comment.
     *
     * POST /api/admin/comments/{id}/approve
     */
    public function approve(int $id): JsonResponseInterface
    {
        $user = Auth::user();

        // Check authorization
        if (!$this->authService->canModerateComment($user, $id)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to approve this comment'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
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
        $user = Auth::user();

        // Check authorization
        if (!$this->authService->canModerateComment($user, $id)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to reject this comment'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
        $result = $this->commentAdminService->rejectComment($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk approve comments.
     * Only approves comments that user has permission to moderate.
     *
     * POST /api/admin/comments/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $data = $request->json();
        $ids = $data['ids'] ?? [];

        // Filter to only IDs user can moderate
        $allowedIds = $this->filterAllowedCommentIds($user, $ids);

        if (empty($allowedIds)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to approve any of the selected comments'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
        $result = $this->commentAdminService->bulkApprove($allowedIds, $userId);

        // Indicate if some were skipped
        $skipped = count($ids) - count($allowedIds);
        if ($skipped > 0) {
            $result['skipped'] = $skipped;
            $result['message'] = ($result['message'] ?? 'Comments approved') . " ({$skipped} skipped due to permissions)";
        }

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Bulk reject comments.
     * Only rejects comments that user has permission to moderate.
     *
     * POST /api/admin/comments/bulk-reject
     */
    public function bulkReject(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $data = $request->json();
        $ids = $data['ids'] ?? [];

        // Filter to only IDs user can moderate
        $allowedIds = $this->filterAllowedCommentIds($user, $ids);

        if (empty($allowedIds)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to reject any of the selected comments'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
        $result = $this->commentAdminService->bulkReject($allowedIds, $userId);

        $skipped = count($ids) - count($allowedIds);
        if ($skipped > 0) {
            $result['skipped'] = $skipped;
            $result['message'] = ($result['message'] ?? 'Comments rejected') . " ({$skipped} skipped due to permissions)";
        }

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
        $user = Auth::user();

        // Check authorization
        if (!$this->authService->canDeleteComment($user, $id)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
        $result = $this->commentAdminService->deleteComment($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk delete comments.
     * Only deletes comments that user has permission to delete.
     *
     * POST /api/admin/comments/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponseInterface
    {
        $user = Auth::user();
        $data = $request->json();
        $ids = $data['ids'] ?? [];

        // Filter to only IDs user can delete
        $allowedIds = $this->filterAllowedCommentIds($user, $ids);

        if (empty($allowedIds)) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to delete any of the selected comments'
            ], 403);
        }

        $userId = $this->authService->getUserId($user);
        $result = $this->commentAdminService->bulkDelete($allowedIds, $userId);

        $skipped = count($ids) - count($allowedIds);
        if ($skipped > 0) {
            $result['skipped'] = $skipped;
            $result['message'] = ($result['message'] ?? 'Comments deleted') . " ({$skipped} skipped due to permissions)";
        }

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Get comment statistics.
     * Admin gets all stats, Moderator gets stats for their posts only.
     *
     * GET /api/admin/comments/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $user = Auth::user();

        $authorId = null;
        if ($this->authService->isModerator($user)) {
            $authorId = $this->authService->getUserId($user);
        }

        $result = $this->commentAdminService->getStatistics($authorId);

        return $this->json($result);
    }

    /**
     * Filter comment IDs to only those user can moderate.
     *
     * @param mixed $user
     * @param array $ids
     * @return array
     */
    private function filterAllowedCommentIds(mixed $user, array $ids): array
    {
        // Admin can moderate all
        if ($this->authService->isAdmin($user)) {
            return $ids;
        }

        // Moderator: filter to comments on their posts
        $allowedIds = [];
        foreach ($ids as $id) {
            if ($this->authService->canModerateComment($user, (int) $id)) {
                $allowedIds[] = $id;
            }
        }

        return $allowedIds;
    }
}
