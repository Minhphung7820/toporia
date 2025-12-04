<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Video ORM Model for testing polymorphic relationships.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $video_url
 * @property int $duration
 * @property int $views
 * @property bool $is_published
 * @property string $created_at
 * @property string $updated_at
 */
class VideoModel extends Model
{
    protected static string $table = 'videos';

    protected static array $fillable = [
        'title',
        'slug',
        'description',
        'video_url',
        'duration',
        'views',
        'is_published',
    ];

    protected static array $casts = [
        'duration' => 'int',
        'views' => 'int',
        'is_published' => 'bool',
    ];

    /**
     * Video has one image (polymorphic one-to-one).
     */
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    /**
     * Video has many comments (polymorphic one-to-many).
     */
    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }

    /**
     * Video has many tags (polymorphic many-to-many).
     */
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable', 'taggables')
            ->withPivot('created_at')
            ->withTimestamps();
    }
}

