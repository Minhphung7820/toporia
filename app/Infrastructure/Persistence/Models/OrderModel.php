<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Order ORM Model.
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string|null $shipping_address
 * @property string|null $billing_address
 * @property float $subtotal
 * @property float $tax
 * @property float $shipping_cost
 * @property float $discount
 * @property float $total
 * @property string $status
 * @property string $payment_status
 * @property string|null $payment_method
 * @property string|null $notes
 * @property string|null $shipped_at
 * @property string|null $delivered_at
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class OrderModel extends Model
{
    protected static array $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'billing_address',
        'subtotal',
        'tax',
        'shipping_cost',
        'discount',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'notes',
        'shipped_at',
        'delivered_at',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping_cost' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'shipped_at' => 'date',
        'delivered_at' => 'date',
    ];

    /**
     * User who placed this order.
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class);
    }

    /**
     * Items in this order.
     */
    public function items()
    {
        return $this->hasMany(OrderItemModel::class);
    }

    /**
     * Scope: Pending orders.
     */
    public static function pending()
    {
        return static::query()->where('status', 'pending');
    }

    /**
     * Scope: Completed orders.
     */
    public static function completed()
    {
        return static::query()->where('status', 'completed');
    }

    /**
     * Scope: Orders by status.
     */
    public static function byStatus(string $status)
    {
        return static::query()->where('status', $status);
    }
}

