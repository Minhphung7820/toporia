<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Comment Entity - Domain model for comments.
 *
 * Supports nested comments (replies) with depth tracking.
 * Polymorphic relationship via commentableType and commentableId.
 *
 * Immutable entity following Clean Architecture principles.
 */
final class Comment
{
    public const MAX_DEPTH = 3;

    /**
     * @param int|null $id Comment ID
     * @param string $content Comment content
     * @param string $commentableType Polymorphic type (e.g., 'Post', 'Video')
     * @param int $commentableId Polymorphic ID
     * @param int|null $userId User ID (null for guests)
     * @param int|null $parentId Parent comment ID (null for root comments)
     * @param string|null $authorName Guest author name
     * @param string|null $authorEmail Guest author email
     * @param string|null $authorIp Author IP address
     * @param bool $isApproved Whether comment is approved
     * @param int $likesCount Number of likes
     * @param int $depth Nesting depth (0 = root)
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $content,
        public readonly string $commentableType,
        public readonly int $commentableId,
        public readonly ?int $userId = null,
        public readonly ?int $parentId = null,
        public readonly ?string $authorName = null,
        public readonly ?string $authorEmail = null,
        public readonly ?string $authorIp = null,
        public readonly bool $isApproved = false,
        public readonly int $likesCount = 0,
        public readonly int $depth = 0,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create a new Comment with ID (after persistence).
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            content: $this->content,
            commentableType: $this->commentableType,
            commentableId: $this->commentableId,
            userId: $this->userId,
            parentId: $this->parentId,
            authorName: $this->authorName,
            authorEmail: $this->authorEmail,
            authorIp: $this->authorIp,
            isApproved: $this->isApproved,
            likesCount: $this->likesCount,
            depth: $this->depth,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Approve the comment.
     */
    public function approve(): self
    {
        return new self(
            id: $this->id,
            content: $this->content,
            commentableType: $this->commentableType,
            commentableId: $this->commentableId,
            userId: $this->userId,
            parentId: $this->parentId,
            authorName: $this->authorName,
            authorEmail: $this->authorEmail,
            authorIp: $this->authorIp,
            isApproved: true,
            likesCount: $this->likesCount,
            depth: $this->depth,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Reject the comment (unapprove).
     */
    public function reject(): self
    {
        return new self(
            id: $this->id,
            content: $this->content,
            commentableType: $this->commentableType,
            commentableId: $this->commentableId,
            userId: $this->userId,
            parentId: $this->parentId,
            authorName: $this->authorName,
            authorEmail: $this->authorEmail,
            authorIp: $this->authorIp,
            isApproved: false,
            likesCount: $this->likesCount,
            depth: $this->depth,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Increment like count.
     */
    public function like(): self
    {
        return new self(
            id: $this->id,
            content: $this->content,
            commentableType: $this->commentableType,
            commentableId: $this->commentableId,
            userId: $this->userId,
            parentId: $this->parentId,
            authorName: $this->authorName,
            authorEmail: $this->authorEmail,
            authorIp: $this->authorIp,
            isApproved: $this->isApproved,
            likesCount: $this->likesCount + 1,
            depth: $this->depth,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    /**
     * Check if this is a reply (has parent).
     */
    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    /**
     * Check if this is a root comment (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    /**
     * Check if comment can have replies (not at max depth).
     */
    public function canHaveReplies(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }

    /**
     * Check if comment is from a registered user.
     */
    public function isFromUser(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Check if comment is from a guest.
     */
    public function isFromGuest(): bool
    {
        return $this->userId === null;
    }

    /**
     * Get display author name.
     */
    public function getDisplayAuthorName(): string
    {
        return $this->authorName ?? 'Anonymous';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'author_name' => $this->authorName,
            'author_email' => $this->authorEmail,
            'is_approved' => $this->isApproved,
            'likes_count' => $this->likesCount,
            'depth' => $this->depth,
            'is_reply' => $this->isReply(),
            'can_have_replies' => $this->canHaveReplies(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
