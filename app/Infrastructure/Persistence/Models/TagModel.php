<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Tag ORM Model.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $color
 * @property bool $is_active
 * @property int $usage_count
 * @property string $created_at
 * @property string $updated_at
 */
class TagModel extends Model
{
    protected static string $table = 'tags';

    protected static array $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'usage_count',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'usage_count' => 'int',
    ];

    /**
     * Products tagged with this tag (many-to-many).
     */
    public function products()
    {
        return $this->belongsToMany(
            ProductModel::class,
            'product_tags',
            'tag_id',
            'product_id'
        )->withPivot('created_at', 'created_by')
          ->withTimestamps();
    }

    /**
     * Categories tagged with this tag (polymorphic many-to-many).
     */
    public function categories()
    {
        return $this->morphedByMany(
            CategoryModel::class,
            'taggable',
            'taggables'
        );
    }

    /**
     * All taggable items (polymorphic many-to-many).
     */
    public function taggables()
    {
        return $this->hasMany(TaggableModel::class);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count.
     */
    public function decrementUsage(): void
    {
        $this->decrement('usage_count');
    }

    /**
     * Scope: Active tags.
     */
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    /**
     * Scope: Popular tags.
     */
    public static function popular(int $limit = 10)
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('usage_count', 'desc')
            ->limit($limit);
    }
}

