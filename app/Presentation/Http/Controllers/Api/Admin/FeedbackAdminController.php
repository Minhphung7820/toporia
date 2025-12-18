<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\FeedbackAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Feedback Admin Controller - Admin feedback management endpoints.
 */
final class FeedbackAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly FeedbackAdminService $feedbackAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all feedback with filters.
     *
     * GET /api/admin/feedback
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'priority' => $request->query('priority'),
            'assigned_to' => $request->query('assigned_to'),
            'search' => $request->query('search'),
        ];
        $filters = array_filter($filters);

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->feedbackAdminService->getPaginated($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get pending feedback.
     *
     * GET /api/admin/feedback/pending
     */
    public function pending(Request $request): JsonResponseInterface
    {
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->feedbackAdminService->getPending($page, $perPage);

        return $this->json($result);
    }

    /**
     * Get a single feedback item.
     *
     * GET /api/admin/feedback/{id}
     */
    public function show(int $id): JsonResponseInterface
    {
        $result = $this->feedbackAdminService->getFeedback($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Update feedback status.
     *
     * POST /api/admin/feedback/{id}/status
     */
    public function updateStatus(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $status = $data['status'] ?? '';
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->updateStatus($id, $status, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Feedback not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Update feedback priority.
     *
     * POST /api/admin/feedback/{id}/priority
     */
    public function updatePriority(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $priority = $data['priority'] ?? '';
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->updatePriority($id, $priority, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Feedback not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Assign feedback to admin.
     *
     * POST /api/admin/feedback/{id}/assign
     */
    public function assign(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $assigneeId = isset($data['assignee_id']) ? (int) $data['assignee_id'] : null;
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->assignFeedback($id, $assigneeId, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Add admin notes.
     *
     * POST /api/admin/feedback/{id}/notes
     */
    public function addNotes(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $notes = $data['notes'] ?? '';
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->addAdminNotes($id, $notes, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Delete feedback.
     *
     * DELETE /api/admin/feedback/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->deleteFeedback($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Bulk delete feedback.
     *
     * POST /api/admin/feedback/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $ids = $data['ids'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->feedbackAdminService->bulkDelete($ids, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    /**
     * Get feedback statistics.
     *
     * GET /api/admin/feedback/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $result = $this->feedbackAdminService->getStatistics();

        return $this->json($result);
    }

    /**
     * Get feedback assigned to current admin.
     *
     * GET /api/admin/feedback/my-assigned
     */
    public function myAssigned(Request $request): JsonResponseInterface
    {
        $adminId = Auth::user()->getAuthIdentifier();
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->feedbackAdminService->getAssignedFeedback($adminId, $page, $perPage);

        return $this->json($result);
    }
}
