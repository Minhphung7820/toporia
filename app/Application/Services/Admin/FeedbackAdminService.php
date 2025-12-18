<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\FeedbackAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * Feedback Admin Service
 *
 * Handles admin feedback operations with repository pattern.
 */
final class FeedbackAdminService
{
    public function __construct(
        private readonly FeedbackAdminRepository $feedbackRepository
    ) {}

    /**
     * Get paginated feedback with filters.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->feedbackRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Feedback retrieved successfully',
        ];
    }

    /**
     * Get pending feedback.
     */
    public function getPending(int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->feedbackRepository->getPending($perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Pending feedback retrieved successfully',
        ];
    }

    /**
     * Get feedback assigned to admin.
     */
    public function getAssignedFeedback(int $adminId, int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->feedbackRepository->getAssignedTo($adminId, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Assigned feedback retrieved successfully',
        ];
    }

    /**
     * Get a single feedback item.
     */
    public function getFeedback(int $feedbackId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        return [
            'success' => true,
            'data' => $feedback->toArray(),
            'message' => 'Feedback retrieved successfully',
        ];
    }

    /**
     * Update feedback status.
     */
    public function updateStatus(int $feedbackId, string $status, int $userId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $validStatuses = ['pending', 'reviewing', 'in_progress', 'resolved', 'closed', 'wont_fix'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $updatedFeedback = $this->feedbackRepository->updateStatus($feedbackId, $status);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Changed feedback status to {$status}",
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => $updatedFeedback->toArray(),
            'message' => 'Status updated successfully',
        ];
    }

    /**
     * Update feedback priority.
     */
    public function updatePriority(int $feedbackId, string $priority, int $userId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities)) {
            return ['success' => false, 'message' => 'Invalid priority'];
        }

        $updatedFeedback = $this->feedbackRepository->updatePriority($feedbackId, $priority);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Changed feedback priority to {$priority}",
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => $updatedFeedback->toArray(),
            'message' => 'Priority updated successfully',
        ];
    }

    /**
     * Assign feedback to admin.
     */
    public function assignFeedback(int $feedbackId, ?int $assigneeId, int $userId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $updatedFeedback = $this->feedbackRepository->assign($feedbackId, $assigneeId);
        $action = $assigneeId ? "Assigned feedback to user {$assigneeId}" : 'Unassigned feedback';

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            $action,
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => $updatedFeedback->toArray(),
            'message' => $assigneeId ? 'Feedback assigned successfully' : 'Feedback unassigned successfully',
        ];
    }

    /**
     * Add admin notes to feedback.
     */
    public function addAdminNotes(int $feedbackId, string $notes, int $userId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $updatedFeedback = $this->feedbackRepository->addNotes($feedbackId, $notes);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            'Added admin notes to feedback',
            'Feedback',
            $feedbackId
        );

        return [
            'success' => true,
            'data' => $updatedFeedback->toArray(),
            'message' => 'Notes added successfully',
        ];
    }

    /**
     * Delete feedback.
     */
    public function deleteFeedback(int $feedbackId, int $userId): array
    {
        $feedback = $this->feedbackRepository->find($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        $this->feedbackRepository->delete($feedbackId);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted feedback: {$feedback->title}",
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
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No feedback selected'];
        }

        $count = $this->feedbackRepository->bulkDelete($ids);

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
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => $this->feedbackRepository->getStatistics(),
            'message' => 'Statistics retrieved successfully',
        ];
    }
}
