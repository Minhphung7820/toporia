<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Domain\Entities\Feedback;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * Feedback Admin Service - Handles admin feedback management.
 *
 * Following Service pattern with consistent return format.
 */
final class FeedbackAdminService
{
    public function __construct(
        private readonly FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Get all feedback with filters.
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllFeedback(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $feedback = $this->feedbackRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->feedbackRepository->countAll();

        return [
            'success' => true,
            'data' => [
                'feedback' => array_map(fn($item) => $item->toArray(), $feedback),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Feedback retrieved successfully',
        ];
    }

    /**
     * Get pending feedback.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPendingFeedback(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $feedback = $this->feedbackRepository->findPending($perPage, $offset);
        $total = $this->feedbackRepository->countPending();

        return [
            'success' => true,
            'data' => [
                'feedback' => array_map(fn($item) => $item->toArray(), $feedback),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Pending feedback retrieved successfully',
        ];
    }

    /**
     * Get a single feedback item.
     *
     * @param int $feedbackId Feedback ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getFeedback(int $feedbackId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        return [
            'success' => true,
            'data' => [
                'feedback' => $feedback->toArray(),
            ],
            'message' => 'Feedback retrieved successfully',
        ];
    }

    /**
     * Update feedback status.
     *
     * @param int $feedbackId Feedback ID
     * @param string $status New status
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updateStatus(int $feedbackId, string $status, int $userId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $validStatuses = [
            Feedback::STATUS_PENDING,
            Feedback::STATUS_REVIEWING,
            Feedback::STATUS_IN_PROGRESS,
            Feedback::STATUS_RESOLVED,
            Feedback::STATUS_CLOSED,
            Feedback::STATUS_WONT_FIX,
        ];

        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $updatedFeedback = $feedback->withStatus($status);
        $this->feedbackRepository->save($updatedFeedback);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Changed feedback status to {$status}",
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => ['feedback' => $updatedFeedback->toArray()],
            'message' => 'Status updated successfully',
        ];
    }

    /**
     * Update feedback priority.
     *
     * @param int $feedbackId Feedback ID
     * @param string $priority New priority
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updatePriority(int $feedbackId, string $priority, int $userId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $validPriorities = [
            Feedback::PRIORITY_LOW,
            Feedback::PRIORITY_NORMAL,
            Feedback::PRIORITY_HIGH,
            Feedback::PRIORITY_URGENT,
        ];

        if (!in_array($priority, $validPriorities)) {
            return ['success' => false, 'message' => 'Invalid priority'];
        }

        $updatedFeedback = $feedback->withPriority($priority);
        $this->feedbackRepository->save($updatedFeedback);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Changed feedback priority to {$priority}",
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => ['feedback' => $updatedFeedback->toArray()],
            'message' => 'Priority updated successfully',
        ];
    }

    /**
     * Assign feedback to admin.
     *
     * @param int $feedbackId Feedback ID
     * @param int|null $assigneeId Assignee user ID (null to unassign)
     * @param int $userId Admin user ID performing action
     * @return array{success: bool, data?: array, message: string}
     */
    public function assignFeedback(int $feedbackId, ?int $assigneeId, int $userId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $updatedFeedback = $feedback->assignTo($assigneeId);
        $this->feedbackRepository->save($updatedFeedback);

        $action = $assigneeId ? "Assigned feedback to user {$assigneeId}" : 'Unassigned feedback';

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            $action,
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => ['feedback' => $updatedFeedback->toArray()],
            'message' => $assigneeId ? 'Feedback assigned successfully' : 'Feedback unassigned successfully',
        ];
    }

    /**
     * Add admin notes to feedback.
     *
     * @param int $feedbackId Feedback ID
     * @param string $notes Admin notes
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function addAdminNotes(int $feedbackId, string $notes, int $userId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $updatedFeedback = $feedback->withAdminNotes($notes);
        $this->feedbackRepository->save($updatedFeedback);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            'Added admin notes to feedback',
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => ['feedback' => $updatedFeedback->toArray()],
            'message' => 'Notes added successfully',
        ];
    }

    /**
     * Delete feedback.
     *
     * @param int $feedbackId Feedback ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deleteFeedback(int $feedbackId, int $userId): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $this->feedbackRepository->delete($feedback);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted feedback: {$feedback->subject}",
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'message' => 'Feedback deleted successfully',
        ];
    }

    /**
     * Bulk delete feedback.
     *
     * @param array $ids Feedback IDs
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No feedback selected'];
        }

        $count = $this->feedbackRepository->bulkDelete($ids);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Bulk deleted {$count} feedback items"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} feedback items deleted successfully",
        ];
    }

    /**
     * Get feedback statistics.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'total' => $this->feedbackRepository->countAll(),
                'pending' => $this->feedbackRepository->countPending(),
                'by_type' => $this->feedbackRepository->countByType(),
                'by_status' => $this->feedbackRepository->countByStatus(),
                'by_priority' => $this->feedbackRepository->countByPriority(),
            ],
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Get feedback assigned to specific admin.
     *
     * @param int $adminId Admin user ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAssignedFeedback(int $adminId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $feedback = $this->feedbackRepository->findByAssignee($adminId, $perPage, $offset);

        return [
            'success' => true,
            'data' => [
                'feedback' => array_map(fn($item) => $item->toArray(), $feedback),
            ],
            'message' => 'Assigned feedback retrieved successfully',
        ];
    }
}
