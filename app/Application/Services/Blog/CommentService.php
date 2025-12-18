<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Entities\Comment;

/**
 * Comment Service - Handles comment operations for the public site.
 *
 * Following Service pattern with consistent return format.
 */
final class CommentService
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly PostRepository $postRepository
    ) {}

    /**
     * Get comments for a post with nested replies.
     *
     * @param int $postId Post ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostComments(int $postId): array
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return [
                'success' => false,
                'message' => 'Post not found',
            ];
        }

        // Get root comments
        $rootComments = $this->commentRepository->findByPost($postId, true);

        // Build nested structure
        $commentsWithReplies = [];
        foreach ($rootComments as $comment) {
            $replies = $this->commentRepository->findReplies($comment->id, true);
            $commentsWithReplies[] = [
                'comment' => $comment->toArray(),
                'replies' => array_map(fn($reply) => $this->buildReplyTree($reply), $replies),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'comments' => $commentsWithReplies,
                'count' => $this->commentRepository->countByPost($postId, true),
            ],
            'message' => 'Comments retrieved successfully',
        ];
    }

    /**
     * Create a new comment.
     *
     * @param array $data Comment data
     * @param int|null $userId Authenticated user ID
     * @param string|null $ipAddress Client IP address
     * @return array{success: bool, data?: array, message: string}
     */
    public function createComment(array $data, ?int $userId = null, ?string $ipAddress = null): array
    {
        // Validate required fields
        if (empty($data['post_id'])) {
            return ['success' => false, 'message' => 'Post ID is required'];
        }

        if (empty($data['content'])) {
            return ['success' => false, 'message' => 'Comment content is required'];
        }

        // Check if post exists
        $post = $this->postRepository->findById((int) $data['post_id']);
        if (!$post || !$post->isPublished) {
            return ['success' => false, 'message' => 'Post not found'];
        }

        // For guest comments, require name and email
        if ($userId === null) {
            if (empty($data['author_name'])) {
                return ['success' => false, 'message' => 'Name is required'];
            }
            if (empty($data['author_email']) || !filter_var($data['author_email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Valid email is required'];
            }
        }

        // Check if comments require approval
        $requiresApproval = config('blog.comments_require_approval', true);

        $comment = new Comment(
            id: null,
            content: strip_tags($data['content']),
            commentableType: 'Post',
            commentableId: (int) $data['post_id'],
            userId: $userId,
            parentId: null,
            authorName: $data['author_name'] ?? null,
            authorEmail: $data['author_email'] ?? null,
            authorIp: $ipAddress,
            isApproved: !$requiresApproval,
            likesCount: 0,
            depth: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedComment = $this->commentRepository->save($comment);

        return [
            'success' => true,
            'data' => [
                'comment' => $savedComment->toArray(),
                'pending_approval' => $requiresApproval,
            ],
            'message' => $requiresApproval
                ? 'Comment submitted and pending approval'
                : 'Comment posted successfully',
        ];
    }

    /**
     * Create a reply to a comment.
     *
     * @param int $parentId Parent comment ID
     * @param array $data Reply data
     * @param int|null $userId Authenticated user ID
     * @param string|null $ipAddress Client IP address
     * @return array{success: bool, data?: array, message: string}
     */
    public function createReply(int $parentId, array $data, ?int $userId = null, ?string $ipAddress = null): array
    {
        // Get parent comment
        $parent = $this->commentRepository->findById($parentId);

        if (!$parent) {
            return ['success' => false, 'message' => 'Parent comment not found'];
        }

        // Check depth limit
        if (!$parent->canHaveReplies()) {
            return ['success' => false, 'message' => 'Maximum reply depth reached'];
        }

        if (empty($data['content'])) {
            return ['success' => false, 'message' => 'Reply content is required'];
        }

        // For guest replies, require name and email
        if ($userId === null) {
            if (empty($data['author_name'])) {
                return ['success' => false, 'message' => 'Name is required'];
            }
            if (empty($data['author_email']) || !filter_var($data['author_email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Valid email is required'];
            }
        }

        $requiresApproval = config('blog.comments_require_approval', true);

        $reply = new Comment(
            id: null,
            content: strip_tags($data['content']),
            commentableType: $parent->commentableType,
            commentableId: $parent->commentableId,
            userId: $userId,
            parentId: $parentId,
            authorName: $data['author_name'] ?? null,
            authorEmail: $data['author_email'] ?? null,
            authorIp: $ipAddress,
            isApproved: !$requiresApproval,
            likesCount: 0,
            depth: $parent->depth + 1,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedReply = $this->commentRepository->save($reply);

        return [
            'success' => true,
            'data' => [
                'comment' => $savedReply->toArray(),
                'pending_approval' => $requiresApproval,
            ],
            'message' => $requiresApproval
                ? 'Reply submitted and pending approval'
                : 'Reply posted successfully',
        ];
    }

    /**
     * Like a comment.
     *
     * @param int $commentId Comment ID
     * @return array{success: bool, message: string}
     */
    public function likeComment(int $commentId): array
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found'];
        }

        $this->commentRepository->incrementLikes($commentId);

        return [
            'success' => true,
            'message' => 'Comment liked',
        ];
    }

    /**
     * Build nested reply tree recursively.
     */
    private function buildReplyTree(Comment $comment): array
    {
        $replies = [];

        if ($comment->canHaveReplies()) {
            $childReplies = $this->commentRepository->findReplies($comment->id, true);
            foreach ($childReplies as $reply) {
                $replies[] = $this->buildReplyTree($reply);
            }
        }

        return [
            'comment' => $comment->toArray(),
            'replies' => $replies,
        ];
    }
}
