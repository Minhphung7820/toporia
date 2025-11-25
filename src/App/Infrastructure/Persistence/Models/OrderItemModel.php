<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Order Item ORM Model.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property string|null $product_sku
 * @property int $quantity
 * @property float $unit_price
 * @property float $total_price
 * @property array|null $product_data
 * @property string $created_at
 * @property string $updated_at
 */
class OrderItemModel extends Model
{
    protected static array $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price',
        'product_data',
    ];

    protected static array $casts = [
        'order_id' => 'int',
        'product_id' => 'int',
        'quantity' => 'int',
        'unit_price' => 'float',
        'total_price' => 'float',
        'product_data' => 'array',
    ];

    /**
     * Order this item belongs to.
     */
    public function order()
    {
        return $this->belongsTo(OrderModel::class);
    }

    /**
     * Product this item references.
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class);
    }
}
