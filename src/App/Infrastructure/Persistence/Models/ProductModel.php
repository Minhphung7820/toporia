<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Product ORM Model.
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $sku
 * @property string|null $description
 * @property string|null $short_description
 * @property array|null $images
 * @property float $price
 * @property float|null $sale_price
 * @property int $stock
 * @property int $views
 * @property float $rating
 * @property int $rating_count
 * @property array|null $specifications
 * @property bool $is_active
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 */
class ProductModel extends Model
{
    protected static string $table = 'products';

    protected static array $fillable = [
        'category_id',
        'title',
        'slug',
        'sku',
        'description',
        'short_description',
        'images',
        'price',
        'sale_price',
        'stock',
        'views',
        'rating',
        'rating_count',
        'specifications',
        'is_active',
        'status',
    ];

    protected static array $casts = [
        'category_id' => 'int',
        'price' => 'float',
        'sale_price' => 'float',
        'stock' => 'int',
        'views' => 'int',
        'rating' => 'float',
        'rating_count' => 'int',
        'images' => 'array',
        'specifications' => 'array',
        'is_active' => 'bool',
    ];

    /**
     * Category this product belongs to.
     */
    public function category()
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    /**
     * Categories this product belongs to (many-to-many).
     */
    public function categories()
    {
        return $this->belongsToMany(
            CategoryModel::class,
            'product_categories',
            'product_id',    // Foreign key for current model (ProductModel)
            'category_id'    // Foreign key for related model (CategoryModel)
        );
    }

    /**
     * Reviews for this product.
     */
    public function reviews()
    {
        return $this->hasMany(ReviewModel::class);
    }

    /**
     * Approved reviews.
     */
    public function approvedReviews()
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * Order items for this product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItemModel::class);
    }

    /**
     * Get final price (sale_price if available, otherwise price).
     */
    public function getFinalPriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Check if product is on sale.
     */
    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Get the foreign key name for this model.
     *
     * @return string
     */
    protected function getForeignKey(): string
    {
        return 'product_id';
    }

    /**
     * Scope: Active products.
     */
    public static function active()
    {
        return static::query()->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope: Products on sale.
     */
    public static function onSale()
    {
        return static::query()
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'price');
    }

    /**
     * Scope: In stock products.
     */
    public static function inStock()
    {
        return static::query()->where('stock', '>', 0);
    }
}
