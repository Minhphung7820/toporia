<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\CommentAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use App\Infrastructure\Notifications\CommentApprovedNotification;
use App\Infrastructure\Notifications\CommentRejectedNotification;
use Toporia\Framework\Notification\AnonymousNotifiable;

/**
 * Comment Admin Service
 *
 * Handles admin comment moderation with repository pattern.
 * Sends email notifications to comment authors on approval/rejection.
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
     * Get comments with cursor-based pagination.
     * Optimized for large tables to avoid disk I/O issues with OFFSET.
     *
     * @param array $filters Filter criteria
     * @param int $limit Number of records per page
     * @param int|null $cursor Last comment ID for pagination
     * @return array
     */
    public function getCursorPaginated(array $filters = [], int $limit = 20, ?int $cursor = null): array
    {
        $result = $this->commentRepository->getCursorPaginated($filters, $limit, $cursor);

        return [
            'success' => true,
            'data' => $result['data'],
            'next_cursor' => $result['next_cursor'],
            'has_more' => $result['has_more'],
            'message' => 'Comments retrieved successfully',
        ];
    }

    /**
     * Get pending comments.
     *
     * @param int $page
     * @param int $perPage
     * @param int|null $postAuthorId If provided, only returns pending comments on posts by this author
     */
    public function getPending(int $page = 1, int $perPage = 20, ?int $postAuthorId = null): array
    {
        $paginator = $this->commentRepository->getPending($perPage, $page, $postAuthorId);

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
        // Load comment with relations for notification
        $comment = $this->commentRepository->findWithRelations($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->approve($commentId);

        // Use truncated content for description
        $contentPreview = mb_substr($comment->content ?? '', 0, 50);
        if (strlen($comment->content ?? '') > 50) {
            $contentPreview .= '...';
        }

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_APPROVE,
            "Approved Comment: {$contentPreview}",
            'Comment',
            $commentId
        );

        // Send email notification to comment author
        $this->sendApprovalNotification($comment);

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
        // Load comment with relations for notification
        $comment = $this->commentRepository->findWithRelations($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->reject($commentId);

        // Use truncated content for description
        $contentPreview = mb_substr($comment->content ?? '', 0, 50);
        if (strlen($comment->content ?? '') > 50) {
            $contentPreview .= '...';
        }

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_REJECT,
            "Rejected Comment: {$contentPreview}",
            'Comment',
            $commentId
        );

        // Send email notification to comment author
        $this->sendRejectionNotification($comment);

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

        // Load comments with relations BEFORE approving (for notifications)
        $comments = [];
        foreach ($ids as $id) {
            $comment = $this->commentRepository->findWithRelations($id);
            if ($comment) {
                $comments[] = $comment;
            }
        }

        $count = $this->commentRepository->bulkApprove($ids);

        // Only log activity if comments were actually approved
        if ($count > 0) {
            AdminActivityLogModel::log(
                $userId,
                AdminActivityLogModel::ACTION_APPROVE,
                "Bulk approved {$count} comments",
                'Comment'
            );

            // Send notifications for each approved comment
            foreach ($comments as $comment) {
                $this->sendApprovalNotification($comment);
            }
        }

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

        // Load comments with relations BEFORE rejecting (for notifications)
        $comments = [];
        foreach ($ids as $id) {
            $comment = $this->commentRepository->findWithRelations($id);
            if ($comment) {
                $comments[] = $comment;
            }
        }

        $count = $this->commentRepository->bulkReject($ids);

        // Only log activity if comments were actually rejected
        if ($count > 0) {
            AdminActivityLogModel::log(
                $userId,
                AdminActivityLogModel::ACTION_REJECT,
                "Bulk rejected {$count} comments",
                'Comment'
            );

            // Send notifications for each rejected comment
            foreach ($comments as $comment) {
                $this->sendRejectionNotification($comment);
            }
        }

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

        // Only log activity if comments were actually deleted
        if ($count > 0) {
            AdminActivityLogModel::log(
                $userId,
                AdminActivityLogModel::ACTION_DELETE,
                "Bulk deleted {$count} comments",
                'Comment'
            );
        }

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} comments deleted successfully",
        ];
    }

    /**
     * Get comment statistics.
     *
     * @param int|null $postAuthorId If provided, only returns stats for comments on posts by this author
     */
    public function getStatistics(?int $postAuthorId = null): array
    {
        return [
            'success' => true,
            'data' => $this->commentRepository->getStatistics($postAuthorId),
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Send approval notification to comment author.
     *
     * @param mixed $comment Comment model/array
     */
    private function sendApprovalNotification(mixed $comment): void
    {
        $email = $this->getCommentAuthorEmail($comment);

        if (!$email) {
            return;
        }

        $postTitle = $this->getPostTitle($comment);
        $commentData = $this->commentToArray($comment);

        try {
            $notifiable = (new AnonymousNotifiable())->route('mail', $email);
            $notifiable->notify(new CommentApprovedNotification($commentData, $postTitle));
        } catch (\Throwable $e) {
            log_error('Failed to send comment approval notification', [
                'comment_id' => $commentData['id'] ?? null,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send rejection notification to comment author.
     *
     * @param mixed $comment Comment model/array
     * @param string|null $reason Optional rejection reason
     */
    private function sendRejectionNotification(mixed $comment, ?string $reason = null): void
    {
        $email = $this->getCommentAuthorEmail($comment);

        if (!$email) {
            return;
        }

        $postTitle = $this->getPostTitle($comment);
        $commentData = $this->commentToArray($comment);

        try {
            $notifiable = (new AnonymousNotifiable())->route('mail', $email);
            $notifiable->notify(new CommentRejectedNotification($commentData, $postTitle, $reason));
        } catch (\Throwable $e) {
            // Log error but don't fail the rejection
            log_error('Failed to send comment rejection notification', [
                'comment_id' => $commentData['id'] ?? null,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the email of the comment author.
     *
     * @param mixed $comment
     * @return string|null
     */
    private function getCommentAuthorEmail(mixed $comment): ?string
    {
        // Handle model object
        if (is_object($comment)) {
            // Guest comment email
            if (!empty($comment->author_email)) {
                return $comment->author_email;
            }

            // Registered user email
            if ($comment->user && !empty($comment->user->email)) {
                return $comment->user->email;
            }
        }

        // Handle array
        if (is_array($comment)) {
            if (!empty($comment['author_email'])) {
                return $comment['author_email'];
            }

            if (isset($comment['user']['email'])) {
                return $comment['user']['email'];
            }
        }

        return null;
    }

    /**
     * Get the post title for the comment.
     *
     * @param mixed $comment
     * @return string
     */
    private function getPostTitle(mixed $comment): string
    {
        if (is_object($comment)) {
            // Check commentable (polymorphic relation)
            if ($comment->commentable && !empty($comment->commentable->title)) {
                return $comment->commentable->title;
            }
        }

        if (is_array($comment)) {
            if (isset($comment['commentable']['title'])) {
                return $comment['commentable']['title'];
            }
        }

        return 'an article';
    }

    /**
     * Convert comment model to array.
     *
     * @param mixed $comment
     * @return array
     */
    private function commentToArray(mixed $comment): array
    {
        if (is_array($comment)) {
            return $comment;
        }

        if (is_object($comment) && method_exists($comment, 'toArray')) {
            return $comment->toArray();
        }

        // Manual conversion for object
        if (is_object($comment)) {
            return [
                'id' => $comment->id ?? null,
                'content' => $comment->content ?? '',
                'author_name' => $comment->author_name ?? null,
                'author_email' => $comment->author_email ?? null,
                'commentable_id' => $comment->commentable_id ?? null,
                'commentable_type' => $comment->commentable_type ?? null,
            ];
        }

        return [];
    }
}
