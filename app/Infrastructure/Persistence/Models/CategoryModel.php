<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Category ORM Model.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property bool $is_active
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class CategoryModel extends Model
{
    protected static string $table = 'categories';

    protected static array $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
        'parent_id',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    /**
     * Products in this category (many-to-many).
     */
    public function products()
    {
        return $this->belongsToMany(ProductModel::class, 'product_categories');
    }

    /**
     * Posts in this category (one-to-many via primary category).
     */
    public function posts()
    {
        return $this->hasMany(PostModel::class, 'category_id');
    }

    /**
     * Posts in this category (many-to-many via pivot).
     */
    public function allPosts()
    {
        return $this->belongsToMany(PostModel::class, 'post_categories')
            ->withTimestamps();
    }

    /**
     * Parent category.
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Child categories.
     */
    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get active categories.
     */
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    /**
     * Get root categories (no parent).
     */
    public static function roots()
    {
        return static::query()->whereNull('parent_id');
    }

    /**
     * Get categories ordered by sort order.
     */
    public static function ordered()
    {
        return static::query()->orderBy('sort_order', 'asc');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if category is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if category is a child category.
     */
    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Get the foreign key name for this model.
     *
     * @return string
     */
    protected function getForeignKey(): string
    {
        return 'category_id';
    }
}
