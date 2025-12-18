<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\CommentRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * Comment Admin Service - Handles admin comment moderation.
 *
 * Following Service pattern with consistent return format.
 */
final class CommentAdminService
{
    public function __construct(
        private readonly CommentRepository $commentRepository
    ) {}

    /**
     * Get all comments with filters.
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Comments per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllComments(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $comments = $this->commentRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->commentRepository->countAll();

        return [
            'success' => true,
            'data' => [
                'comments' => array_map(fn($comment) => $comment->toArray(), $comments),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Comments retrieved successfully',
        ];
    }

    /**
     * Get pending comments.
     *
     * @param int $page Page number
     * @param int $perPage Comments per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPendingComments(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $comments = $this->commentRepository->findPending($perPage, $offset);
        $total = $this->commentRepository->countPending();

        return [
            'success' => true,
            'data' => [
                'comments' => array_map(fn($comment) => $comment->toArray(), $comments),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Pending comments retrieved successfully',
        ];
    }

    /**
     * Approve a comment.
     *
     * @param int $commentId Comment ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function approveComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->approve($commentId);

        // Log activity
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
     *
     * @param int $commentId Comment ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function rejectComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->reject($commentId);

        // Log activity
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
     *
     * @param array $ids Comment IDs
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function bulkApprove(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkApprove($ids);

        // Log activity
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
     *
     * @param array $ids Comment IDs
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function bulkReject(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkReject($ids);

        // Log activity
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
     *
     * @param int $commentId Comment ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deleteComment(int $commentId, int $userId): array
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->delete($comment);

        // Log activity
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
     *
     * @param array $ids Comment IDs
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No comments selected'];
        }

        $count = $this->commentRepository->bulkDelete($ids);

        // Log activity
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
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'total' => $this->commentRepository->countAll(),
                'pending' => $this->commentRepository->countPending(),
                'approved' => $this->commentRepository->countApproved(),
            ],
            'message' => 'Statistics retrieved successfully',
        ];
    }
}
