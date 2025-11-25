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
    protected static array $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
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
     * Get active categories.
     */
    public static function active()
    {
        return static::query()->where('is_active', true);
    }
}
