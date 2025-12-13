<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * OrderItem ORM Model.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property string|null $product_sku
 * @property float $price
 * @property int $quantity
 * @property float $total
 * @property array|null $product_options
 * @property string $created_at
 * @property string $updated_at
 */
class OrderItemModel extends Model
{
    protected static string $table = 'order_items';

    protected static array $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'price',
        'quantity',
        'total',
        'product_options',
    ];

    protected static array $casts = [
        'order_id' => 'int',
        'product_id' => 'int',
        'price' => 'float',
        'quantity' => 'int',
        'total' => 'float',
        'product_options' => 'array',
    ];

    /**
     * Order this item belongs to.
     */
    public function order()
    {
        return $this->belongsTo(OrderModel::class);
    }

    /**
     * Product this item represents.
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class);
    }

    /**
     * Calculate total price.
     */
    public function calculateTotal(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get subtotal (alias for total).
     */
    public function getSubtotalAttribute(): float
    {
        return $this->total;
    }

    /**
     * Scope: Items for specific order.
     */
    public static function forOrder(int $orderId)
    {
        return static::query()->where('order_id', $orderId);
    }

    /**
     * Scope: Items for specific product.
     */
    public static function forProduct(int $productId)
    {
        return static::query()->where('product_id', $productId);
    }
}