<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Comment ORM Model with nested comments support.
 *
 * @property int $id
 * @property string|null $commentable_type
 * @property int|null $commentable_id
 * @property string $content
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property string|null $author_name
 * @property string|null $author_email
 * @property string|null $author_ip
 * @property bool $is_approved
 * @property string $status Status: pending, approved, rejected
 * @property int $likes_count
 * @property int $depth
 * @property string $created_at
 * @property string $updated_at
 */
class CommentModel extends Model
{
    protected static string $table = 'comments';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected static array $fillable = [
        'commentable_type',
        'commentable_id',
        'content',
        'user_id',
        'parent_id',
        'author_name',
        'author_email',
        'author_ip',
        'is_approved',
        'status',
        'likes_count',
        'depth',
    ];

    protected static array $casts = [
        'commentable_id' => 'int',
        'user_id' => 'int',
        'parent_id' => 'int',
        'is_approved' => 'bool',
        'likes_count' => 'int',
        'depth' => 'int',
    ];

    public const MAX_DEPTH = 3;

    /**
     * Comment belongs to Post or Video (polymorphic inverse - morphTo).
     */
    public function commentable()
    {
        return $this->morphTo('commentable');
    }

    /**
     * Comment belongs to user.
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * Comment belongs to parent comment.
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Comment has many replies.
     */
    public function replies()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Get approved replies.
     */
    public function approvedReplies()
    {
        return $this->hasMany(static::class, 'parent_id')
            ->where('is_approved', true);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get approved comments.
     */
    public static function approved()
    {
        return static::query()->where('status', self::STATUS_APPROVED);
    }

    /**
     * Get pending comments.
     */
    public static function pending()
    {
        return static::query()->where('status', self::STATUS_PENDING);
    }

    /**
     * Get rejected comments.
     */
    public static function rejected()
    {
        return static::query()->where('status', self::STATUS_REJECTED);
    }

    /**
     * Get root comments (no parent).
     */
    public static function roots()
    {
        return static::query()->whereNull('parent_id');
    }

    /**
     * Get recent comments.
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
     * Check if comment is a reply.
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if comment is a root comment.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if comment can have replies.
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
        return $this->user_id !== null;
    }

    /**
     * Check if comment is from a guest.
     */
    public function isFromGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Get display author name.
     */
    public function getDisplayAuthorName(): string
    {
        if ($this->user_id && $this->user) {
            return $this->user->name;
        }
        return $this->author_name ?? 'Anonymous';
    }

    /**
     * Get the foreign key name for this model.
     */
    protected function getForeignKey(): string
    {
        return 'comment_id';
    }
}




