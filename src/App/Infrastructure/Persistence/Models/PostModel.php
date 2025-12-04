<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Post ORM Model for testing polymorphic relationships.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $content
 * @property int $views
 * @property bool $is_published
 * @property string $created_at
 * @property string $updated_at
 */
class PostModel extends Model
{
    protected static string $table = 'posts';

    protected static array $fillable = [
        'title',
        'slug',
        'content',
        'views',
        'is_published',
    ];

    protected static array $casts = [
        'views' => 'int',
        'is_published' => 'bool',
    ];

    /**
     * Post has one image (polymorphic one-to-one).
     */
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    /**
     * Post has many comments (polymorphic one-to-many).
     */
    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }

    /**
     * Post has many tags (polymorphic many-to-many).
     */
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable', 'taggables')
            ->withPivot('created_at')
            ->withTimestamps();
    }
}
