<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Application\Services\SiteSettingsService;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Entities\Comment;
use App\Infrastructure\Persistence\Models\CommentAttachmentModel;
use App\Infrastructure\Persistence\Models\CommentMentionModel;
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
            content: $this->sanitizeContent($data['content']),
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

        // Save attachments if provided
        if (!empty($data['attachments'])) {
            $this->saveAttachments($savedComment->id, $data['attachments']);
        }

        // Save mentions if provided
        if (!empty($data['mentions'])) {
            $this->saveMentions($savedComment->id, $data['mentions'], $userId);
        }

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
            content: $this->sanitizeContent($data['content']),
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

        // Save attachments if provided
        if (!empty($data['attachments'])) {
            $this->saveAttachments($savedReply->id, $data['attachments']);
        }

        // Save mentions if provided
        if (!empty($data['mentions'])) {
            $this->saveMentions($savedReply->id, $data['mentions'], $userId);
        }

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
     * Enrich comment data with user information (name, avatar) and attachments.
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

        // Check if commenter is the post author
        $data['is_post_author'] = false;
        if ($comment->commentableType === 'Post' && $comment->userId !== null) {
            $post = $this->postRepository->findById($comment->commentableId);
            if ($post && $post->authorId === $comment->userId) {
                $data['is_post_author'] = true;
            }
        }

        // Load attachments
        $attachments = CommentAttachmentModel::where('comment_id', $comment->id)
            ->orderBy('sort_order')
            ->get();

        $data['attachments'] = $attachments->map(fn($att) => [
            'id' => $att->id,
            'filename' => $att->filename,
            'path' => $att->path,
            'url' => url('/storage/' . $att->path),
            'mime_type' => $att->mime_type,
            'size' => $att->size,
            'type' => $att->type,
            'width' => $att->width,
            'height' => $att->height,
        ])->all();

        // Load mentions
        $mentions = CommentMentionModel::where('comment_id', $comment->id)
            ->with('mentionedUser')
            ->get();

        $data['mentions'] = $mentions->map(fn($m) => [
            'user_id' => $m->mentioned_user_id,
            'name' => $m->mentionedUser?->name,
            'username' => $m->mentionedUser?->username,
        ])->all();

        return $data;
    }

    /**
     * Save attachments for a comment.
     *
     * @param int $commentId
     * @param array $attachments
     */
    private function saveAttachments(int $commentId, array $attachments): void
    {
        foreach ($attachments as $index => $attachment) {
            CommentAttachmentModel::create([
                'comment_id' => $commentId,
                'filename' => $attachment['filename'] ?? '',
                'path' => $attachment['path'] ?? '',
                'mime_type' => $attachment['mime_type'] ?? '',
                'size' => $attachment['size'] ?? 0,
                'type' => $attachment['type'] ?? 'file',
                'width' => $attachment['width'] ?? null,
                'height' => $attachment['height'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Save mentions for a comment.
     *
     * @param int $commentId
     * @param array $mentionedUserIds
     * @param int|null $mentionedByUserId
     */
    private function saveMentions(int $commentId, array $mentionedUserIds, ?int $mentionedByUserId): void
    {
        foreach ($mentionedUserIds as $userId) {
            CommentMentionModel::create([
                'comment_id' => $commentId,
                'mentioned_user_id' => (int) $userId,
                'mentioned_by_user_id' => $mentionedByUserId,
            ]);
        }
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

            // Load attachments for the comment
            $attachments = CommentAttachmentModel::where('comment_id', $comment->id)
                ->orderBy('sort_order')
                ->get();

            $attachmentsData = $attachments->map(fn($att) => [
                'id' => $att->id,
                'filename' => $att->filename,
                'path' => $att->path,
                'url' => $att->url, // Uses accessor from model
                'mime_type' => $att->mime_type,
                'size' => $att->size,
                'type' => $att->type,
                'width' => $att->width,
                'height' => $att->height,
            ])->all();

            // Load user info if comment is from registered user
            $authorAvatar = null;
            $authorDisplayName = $comment->authorName ?? 'Anonymous';
            if ($comment->userId !== null) {
                $user = UserModel::find($comment->userId);
                if ($user) {
                    $authorDisplayName = $user->name;
                    $authorAvatar = $user->avatar;
                }
            }

            $payload = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author_name' => $authorDisplayName,
                'author_email' => $comment->authorEmail,
                'author_avatar' => $authorAvatar,
                'user_id' => $comment->userId,
                'status' => $requiresApproval ? 'pending' : 'approved',
                'is_approved' => !$requiresApproval,
                'is_reply' => $comment->parentId !== null,
                'parent_id' => $comment->parentId,
                'depth' => $comment->depth,
                'likes_count' => $comment->likesCount,
                'commentable_type' => $comment->commentableType,
                'commentable_id' => $comment->commentableId,
                'commentable' => $post ? [
                    'id' => $post->id,
                    'title' => $post->title,
                ] : null,
                'attachments' => $attachmentsData,
                'created_at' => $comment->createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $comment->updatedAt->format('Y-m-d H:i:s'),
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

    /**
     * Sanitize HTML content, allowing only safe tags for comments.
     * Allows: b, strong, i, em, u, br, pre, code (for code blocks)
     *
     * @param string $content
     * @return string
     */
    private function sanitizeContent(string $content): string
    {
        // Allow only safe formatting tags and code blocks
        $allowedTags = '<b><strong><i><em><u><br><pre><code>';

        // Strip all tags except allowed ones
        $sanitized = strip_tags($content, $allowedTags);

        // Clean up pre tags - only allow class="code-block"
        $sanitized = preg_replace_callback(
            '/<pre([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];
                // Only keep class="code-block" if present
                if (preg_match('/class\s*=\s*["\']code-block["\']/i', $attrs)) {
                    return '<pre class="code-block">';
                }
                return '<pre>';
            },
            $sanitized
        );

        // Remove any other attributes from allowed tags for security
        $sanitized = preg_replace('/<(b|strong|i|em|u|br|code)(\s+[^>]*)>/i', '<$1>', $sanitized);

        return $sanitized;
    }
}
