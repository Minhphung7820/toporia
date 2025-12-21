<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Application\Services\SiteSettingsService;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Entities\Comment;
use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Realtime\Broadcast;

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
     * Get comments for a post with nested replies and cursor pagination.
     *
     * @param int $postId Post ID
     * @param int $limit Number of root comments per page
     * @param int|null $cursor Last comment ID for pagination (load comments before this ID)
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPostComments(int $postId, int $limit = 5, ?int $cursor = null): array
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            return [
                'success' => false,
                'message' => 'Post not found',
            ];
        }

        // Get root comments with cursor pagination
        $rootComments = $this->commentRepository->findByPostPaginated($postId, $limit + 1, $cursor, true);

        // Check if there are more comments
        $hasMore = count($rootComments) > $limit;
        if ($hasMore) {
            $rootComments = array_slice($rootComments, 0, $limit);
        }

        // Build nested structure with user info
        $commentsWithReplies = [];
        $nextCursor = null;
        foreach ($rootComments as $comment) {
            $replies = $this->commentRepository->findReplies($comment->id, true);
            $commentsWithReplies[] = [
                'comment' => $this->enrichCommentWithUser($comment),
                'replies' => array_map(fn($reply) => $this->buildReplyTree($reply), $replies),
            ];
            $nextCursor = $comment->id;
        }

        return [
            'success' => true,
            'data' => [
                'comments' => $commentsWithReplies,
                'count' => $this->commentRepository->countByPost($postId, true),
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
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
        $settings = SiteSettingsService::getInstance();

        // Check if comments are enabled (also checked by middleware, but double-check here)
        if (!$settings->commentsEnabled()) {
            return ['success' => false, 'message' => 'Comments are disabled'];
        }

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

        // Check if comments require approval (from cached settings)
        $requiresApproval = $settings->commentsRequireApproval();

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

        // Broadcast realtime event to admin notifications channel
        $this->broadcastCommentCreated($savedComment, $requiresApproval);

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
        $settings = SiteSettingsService::getInstance();

        // Check if comments are enabled (also checked by middleware, but double-check here)
        if (!$settings->commentsEnabled()) {
            return ['success' => false, 'message' => 'Comments are disabled'];
        }

        // Get parent comment
        $parent = $this->commentRepository->findById($parentId);

        if (!$parent) {
            return ['success' => false, 'message' => 'Parent comment not found'];
        }

        // Check depth limit using cached setting
        $maxDepth = $settings->commentsMaxDepth();
        if ($parent->depth >= $maxDepth - 1) {
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

        // Check if comments require approval (from cached settings)
        $requiresApproval = $settings->commentsRequireApproval();

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

        // Broadcast realtime event to admin notifications channel
        $this->broadcastCommentCreated($savedReply, $requiresApproval);

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
            'comment' => $this->enrichCommentWithUser($comment),
            'replies' => $replies,
        ];
    }

    /**
     * Enrich comment data with user information (name, avatar).
     *
     * @param Comment $comment
     * @return array
     */
    private function enrichCommentWithUser(Comment $comment): array
    {
        $data = $comment->toArray();

        // If comment is from a registered user, get their info
        if ($comment->userId !== null) {
            $user = UserModel::find($comment->userId);
            if ($user) {
                $data['author_name'] = $user->name;
                $data['author_avatar'] = $user->avatar;
            }
        }

        return $data;
    }

    /**
     * Broadcast comment created event to admin channels.
     *
     * Broadcasts to both:
     * - admin.notifications (for Header notification bell)
     * - admin.comments (for Comments management page)
     *
     * @param Comment $comment The created comment
     * @param bool $requiresApproval Whether comment requires approval
     */
    private function broadcastCommentCreated(Comment $comment, bool $requiresApproval): void
    {
        try {
            // Get post info for the notification
            $post = $this->postRepository->findById($comment->commentableId);

            $payload = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author_name' => $comment->authorName ?? 'Anonymous',
                'author_email' => $comment->authorEmail,
                'status' => $requiresApproval ? 'pending' : 'approved',
                'is_reply' => $comment->parentId !== null,
                'post' => $post ? [
                    'id' => $post->id,
                    'title' => $post->title,
                ] : null,
                'created_at' => $comment->createdAt->format('Y-m-d H:i:s'),
            ];

            // Broadcast to admin notifications channel (for Header)
            Broadcast::channel('admin.notifications')
                ->event('comment.created')
                ->with($payload)
                ->now();

            // Broadcast to admin comments channel (for Comments page)
            Broadcast::channel('admin.comments')
                ->event('comment.created')
                ->with($payload)
                ->now();
        } catch (\Throwable $e) {
            // Log error but don't fail the comment creation
            error_log("Failed to broadcast comment.created event: {$e->getMessage()}");
        }
    }
}
