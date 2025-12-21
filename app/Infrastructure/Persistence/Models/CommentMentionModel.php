<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * CommentMention Model - ORM model for comment_mentions table.
 */
class CommentMentionModel extends Model
{
    protected static string $table = 'comment_mentions';

    protected static array $fillable = [
        'comment_id',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'position_start',
        'position_end',
        'notified',
        'notified_at',
    ];

    protected static array $casts = [
        'comment_id' => 'int',
        'mentioned_user_id' => 'int',
        'mentioned_by_user_id' => 'int',
        'position_start' => 'int',
        'position_end' => 'int',
        'notified' => 'bool',
    ];

    /**
     * Relationship: Comment that contains this mention.
     */
    public function comment()
    {
        return $this->belongsTo(CommentModel::class, 'comment_id', 'id');
    }

    /**
     * Relationship: User who was mentioned.
     */
    public function mentionedUser()
    {
        return $this->belongsTo(UserModel::class, 'mentioned_user_id', 'id');
    }

    /**
     * Relationship: User who made the mention.
     */
    public function mentionedBy()
    {
        return $this->belongsTo(UserModel::class, 'mentioned_by_user_id', 'id');
    }

    /**
     * Scope: Get unnotified mentions.
     */
    public static function scopeUnnotified($query)
    {
        return $query->where('notified', false);
    }

    /**
     * Scope: Get mentions for a specific user.
     */
    public static function scopeForUser($query, int $userId)
    {
        return $query->where('mentioned_user_id', $userId);
    }

    /**
     * Mark mention as notified.
     */
    public function markNotified(): bool
    {
        return static::where('id', $this->id)->update([
            'notified' => true,
            'notified_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
