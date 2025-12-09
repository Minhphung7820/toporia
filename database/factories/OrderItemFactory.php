<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\OrderItemModel;
use Toporia\Framework\Database\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected string $model = OrderItemModel::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $totalPrice = $quantity * $unitPrice;

        return [
            'order_id' => null, // Will be set in seeder
            'product_id' => null, // Will be set in seeder
            'product_name' => $this->faker->words(rand(2, 5), true),
            'product_sku' => $this->faker->optional(0.8)->bothify('SKU-#####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'product_data' => $this->faker->optional(0.5)->randomElements([
                ['color' => $this->faker->colorName(), 'size' => 'M'],
                ['weight' => '1.5 kg'],
            ], 1)[0] ?? null,
        ];
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): OrderItemModel
    {
        $model = $this->make($attributes);
        if ($model instanceof OrderItemModel) {
            $model->save();
            return $model;
        }
        return OrderItemModel::create(array_merge($this->definition(), $attributes));
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
            OrderItemModel::query()->insert($chunk);
        }

        return [];
    }
}
