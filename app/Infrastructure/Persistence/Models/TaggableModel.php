<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Taggable ORM Model (Polymorphic pivot table).
 *
 * @property int $id
 * @property int $tag_id
 * @property string $taggable_type
 * @property int $taggable_id
 * @property int|null $created_by
 * @property string $created_at
 * @property string $updated_at
 */
class TaggableModel extends Model
{
    protected static string $table = 'taggables';

    protected static array $fillable = [
        'tag_id',
        'taggable_type',
        'taggable_id',
        'created_by',
    ];

    protected static array $casts = [
        'tag_id' => 'int',
        'taggable_id' => 'int',
        'created_by' => 'int',
    ];

    /**
     * Tag associated with this taggable.
     */
    public function tag()
    {
        return $this->belongsTo(TagModel::class);
    }

    /**
     * Get the owning taggable model (polymorphic).
     */
    public function taggable()
    {
        return $this->morphTo('taggable');
    }

    /**
     * User who created this tag association.
     */
    public function creator()
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    /**
     * Scope: For specific tag.
     */
    public static function forTag(int $tagId)
    {
        return static::query()->where('tag_id', $tagId);
    }

    /**
     * Scope: For specific taggable type.
     */
    public static function forType(string $type)
    {
        return static::query()->where('taggable_type', $type);
    }
}
