<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\OrderModel;
use Toporia\Framework\Testing\Factories\Factory;

class OrderFactory extends Factory
{
    protected string $model = OrderModel::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50, 5000);
        $tax = $subtotal * 0.1; // 10% tax
        $shipping = $this->faker->randomFloat(2, 0, 50);
        $discount = $this->faker->optional(0.3)->randomFloat(2, 10, $subtotal * 0.2) ?? 0;
        $total = $subtotal + $tax + $shipping - $discount;

        return [
            'order_number' => 'ORD-' . $this->faker->unique()->numberBetween(100000, 999999),
            'user_id' => null, // Optional
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->email(),
            'customer_phone' => $this->faker->optional(0.8)->phoneNumber(),
            'shipping_address' => $this->faker->optional(0.9)->address(),
            'billing_address' => $this->faker->optional(0.7)->address(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping_cost' => $shipping,
            'discount' => $discount,
            'total' => $total,
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'payment_method' => $this->faker->optional(0.8)->randomElement(['credit_card', 'paypal', 'bank_transfer', 'cash']),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'shipped_at' => $this->faker->optional(0.4)->dateTimeBetween('-30 days', 'now')?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->faker->optional(0.2)->dateTimeBetween('-20 days', 'now')?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): OrderModel
    {
        $model = $this->make($attributes);
        if ($model instanceof OrderModel) {
            $model->save();
            return $model;
        }
        return OrderModel::create(array_merge($this->definition(), $attributes));
    }

    /**
     * Create many models with batch insert.
     */
    public function createMany(int $count, array $attributes = []): array
    {
        $data = [];

        for ($i = 0; $i < $count; $i++) {
            $data[] = array_merge($this->definition(), $attributes);
        }

        // Batch insert for performance
        foreach (array_chunk($data, 500) as $chunk) {
            OrderModel::query()->insert($chunk);
        }

        return [];
    }
}
