<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\CommentAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * Comment Admin Service
 *
 * Handles admin comment moderation with repository pattern.
 */
final class CommentAdminService
{
    public function __construct(
        private readonly CommentAdminRepository $commentRepository
    ) {}

    /**
     * Get paginated comments with filters.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->commentRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Comments retrieved successfully',
        ];
    }

    /**
     * Get pending comments.
     */
    public function getPending(int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->commentRepository->getPending($perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Pending comments retrieved successfully',
        ];
    }

    /**
     * Approve a comment.
     */
    public function approveComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->find($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->approve($commentId);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_APPROVE,
            'Approved comment',
            'Comment',
            $commentId
        );

        return [
            'success' => true,
            'message' => 'Comment approved successfully',
        ];
    }

    /**
     * Reject a comment.
     */
    public function rejectComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->find($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->reject($commentId);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_REJECT,
            'Rejected comment',
            'Comment',
            $commentId
        );

        return [
            'success' => true,
            'message' => 'Comment rejected successfully',
        ];
    }

    /**
     * Bulk approve comments.
     */
    public function bulkApprove(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkApprove($ids);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_APPROVE,
            "Bulk approved {$count} comments"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} comments approved successfully",
        ];
    }

    /**
     * Bulk reject comments.
     */
    public function bulkReject(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkReject($ids);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_REJECT,
            "Bulk rejected {$count} comments"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} comments rejected successfully",
        ];
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->find($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->delete($commentId);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            'Deleted comment',
            'Comment',
            $commentId
        );

        return [
            'success' => true,
            'message' => 'Comment deleted successfully',
        ];
    }

    /**
     * Bulk delete comments.
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkDelete($ids);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Bulk deleted {$count} comments"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} comments deleted successfully",
        ];
    }

    /**
     * Get comment statistics.
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => $this->commentRepository->getStatistics(),
            'message' => 'Statistics retrieved successfully',
        ];
    }
}
