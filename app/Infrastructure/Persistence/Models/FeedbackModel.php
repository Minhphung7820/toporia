<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Feedback ORM Model for user feedback and suggestions.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $subject
 * @property string $message
 * @property string $type
 * @property string $status
 * @property string $priority
 * @property int|null $assigned_to
 * @property string|null $admin_notes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $created_at
 * @property string $updated_at
 */
class FeedbackModel extends Model
{
    protected static string $table = 'feedback';

    protected static array $fillable = [
        'user_id',
        'name',
        'email',
        'subject',
        'message',
        'type',
        'status',
        'priority',
        'assigned_to',
        'admin_notes',
        'ip_address',
        'user_agent',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'assigned_to' => 'int',
    ];

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
     * Feedback belongs to user (optional).
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * Feedback is assigned to admin user.
     */
    public function assignee()
    {
        return $this->belongsTo(UserModel::class, 'assigned_to');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get pending feedback.
     */
    public static function pending()
    {
        return static::query()->where('status', self::STATUS_PENDING);
    }

    /**
     * Get reviewed feedback.
     */
    public static function reviewed()
    {
        return static::query()->where('status', self::STATUS_REVIEWED);
    }

    /**
     * Get in-progress feedback.
     */
    public static function inProgress()
    {
        return static::query()->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Get resolved feedback.
     */
    public static function resolved()
    {
        return static::query()->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Get feedback by type.
     */
    public static function ofType(string $type)
    {
        return static::query()->where('type', $type);
    }

    /**
     * Get recent feedback.
     */
    public static function recent(int $limit = 10)
    {
        return static::query()
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

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
        return $this->user_id !== null;
    }

    /**
     * Check if feedback is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->assigned_to !== null;
    }

    /**
     * Get valid types.
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
     * Get the foreign key name for this model.
     */
    protected function getForeignKey(): string
    {
        return 'feedback_id';
    }
}
