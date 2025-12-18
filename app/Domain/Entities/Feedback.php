<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Feedback Entity - Domain model for user feedback and suggestions.
 *
 * Immutable entity following Clean Architecture principles.
 */
final class Feedback
{
    public const TYPE_FEEDBACK = 'feedback';
    public const TYPE_SUGGESTION = 'suggestion';
    public const TYPE_BUG_REPORT = 'bug_report';
    public const TYPE_FEATURE_REQUEST = 'feature_request';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_WONT_FIX = 'wont_fix';
    public const STATUS_DISMISSED = 'dismissed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * @param int|null $id Feedback ID
     * @param string $name Submitter name
     * @param string $email Submitter email
     * @param string $subject Feedback subject
     * @param string $message Feedback message
     * @param string $type Feedback type
     * @param string $status Current status
     * @param string $priority Priority level
     * @param int|null $userId User ID (null for guests)
     * @param int|null $assignedTo Assigned admin user ID
     * @param string|null $adminNotes Internal admin notes
     * @param string|null $ipAddress Submitter IP address
     * @param string|null $userAgent Submitter user agent
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $message,
        public readonly string $type = self::TYPE_FEEDBACK,
        public readonly string $status = self::STATUS_PENDING,
        public readonly string $priority = self::PRIORITY_NORMAL,
        public readonly ?int $userId = null,
        public readonly ?int $assignedTo = null,
        public readonly ?string $adminNotes = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create a new Feedback with ID (after persistence).
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            type: $this->type,
            status: $this->status,
            priority: $this->priority,
            userId: $this->userId,
            assignedTo: $this->assignedTo,
            adminNotes: $this->adminNotes,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Update status.
     */
    public function withStatus(string $status): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            type: $this->type,
            status: $status,
            priority: $this->priority,
            userId: $this->userId,
            assignedTo: $this->assignedTo,
            adminNotes: $this->adminNotes,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Assign to admin user.
     */
    public function assignTo(?int $adminId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            type: $this->type,
            status: $this->status === self::STATUS_PENDING ? self::STATUS_REVIEWING : $this->status,
            priority: $this->priority,
            userId: $this->userId,
            assignedTo: $adminId,
            adminNotes: $this->adminNotes,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Add admin notes.
     */
    public function withAdminNotes(string $notes): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            type: $this->type,
            status: $this->status,
            priority: $this->priority,
            userId: $this->userId,
            assignedTo: $this->assignedTo,
            adminNotes: $notes,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Update priority.
     */
    public function withPriority(string $priority): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            type: $this->type,
            status: $this->status,
            priority: $priority,
            userId: $this->userId,
            assignedTo: $this->assignedTo,
            adminNotes: $this->adminNotes,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Mark as reviewed.
     */
    public function markReviewed(): self
    {
        return $this->withStatus(self::STATUS_REVIEWED);
    }

    /**
     * Mark as in progress.
     */
    public function markInProgress(): self
    {
        return $this->withStatus(self::STATUS_IN_PROGRESS);
    }

    /**
     * Mark as resolved.
     */
    public function resolve(): self
    {
        return $this->withStatus(self::STATUS_RESOLVED);
    }

    /**
     * Dismiss the feedback.
     */
    public function dismiss(): self
    {
        return $this->withStatus(self::STATUS_DISMISSED);
    }

    /**
     * Check if feedback is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if feedback is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Check if feedback is from a registered user.
     */
    public function isFromUser(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Get valid types.
     *
     * @return string[]
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_FEEDBACK,
            self::TYPE_SUGGESTION,
            self::TYPE_BUG_REPORT,
            self::TYPE_FEATURE_REQUEST,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get valid statuses.
     *
     * @return string[]
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REVIEWING,
            self::STATUS_REVIEWED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_WONT_FIX,
            self::STATUS_DISMISSED,
        ];
    }

    /**
     * Get valid priorities.
     *
     * @return string[]
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
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
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'user_id' => $this->userId,
            'assigned_to' => $this->assignedTo,
            'admin_notes' => $this->adminNotes,
            'is_pending' => $this->isPending(),
            'is_resolved' => $this->isResolved(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
