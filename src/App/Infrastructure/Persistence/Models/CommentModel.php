<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Comment ORM Model for testing polymorphic relationships.
 *
 * @property int $id
 * @property string|null $commentable_type
 * @property int|null $commentable_id
 * @property string $content
 * @property int|null $user_id
 * @property bool $is_approved
 * @property string $created_at
 * @property string $updated_at
 */
class CommentModel extends Model
{
    protected static string $table = 'comments';

    protected static array $fillable = [
        'commentable_type',
        'commentable_id',
        'content',
        'user_id',
        'is_approved',
    ];

    protected static array $casts = [
        'commentable_id' => 'int',
        'user_id' => 'int',
        'is_approved' => 'bool',
    ];

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
}


