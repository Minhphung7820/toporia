<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\ProductModel;
use Toporia\Framework\Testing\Factories\Factory;

class ProductFactory extends Factory
{
    protected string $model = ProductModel::class;

    public function definition(): array
    {
        $title = $this->faker->words(rand(2, 5), true);
        $price = $this->faker->randomFloat(2, 10, 5000);
        $hasSale = $this->faker->boolean(30); // 30% on sale

        return [
            'category_id' => null, // Will be set in seeder
            'title' => ucwords($title),
            'slug' => strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)) . '-' . $this->faker->unique()->numberBetween(10000, 99999),
            'sku' => 'SKU-' . $this->faker->unique()->numberBetween(100000, 999999),
            'description' => $this->faker->optional(0.8)->paragraphs(rand(2, 5), true),
            'short_description' => $this->faker->optional(0.7)->sentence(),
            'images' => $this->faker->optional(0.6)->randomElements([
                ['https://picsum.photos/400/300?random=1', 'https://picsum.photos/400/300?random=2'],
                ['https://picsum.photos/400/300?random=3'],
                ['https://picsum.photos/400/300?random=4', 'https://picsum.photos/400/300?random=5', 'https://picsum.photos/400/300?random=6'],
            ], 1)[0] ?? null,
            'price' => $price,
            'sale_price' => $hasSale ? $this->faker->randomFloat(2, $price * 0.5, $price * 0.9) : null,
            'stock' => $this->faker->numberBetween(0, 1000),
            'views' => $this->faker->numberBetween(0, 10000),
            'rating' => $this->faker->randomFloat(2, 0, 5),
            'rating_count' => $this->faker->numberBetween(0, 500),
            'specifications' => $this->faker->optional(0.5)->randomElements([
                ['color' => $this->faker->colorName(), 'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL'])],
                ['weight' => $this->faker->randomFloat(2, 0.1, 10) . ' kg', 'dimensions' => '10x20x30 cm'],
                ['brand' => $this->faker->company(), 'warranty' => $this->faker->numberBetween(1, 5) . ' years'],
            ], 1)[0] ?? null,
            'is_active' => $this->faker->boolean(85), // 85% active
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft', 'archived']),
        ];
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): ProductModel
    {
        $model = $this->make($attributes);
        if ($model instanceof ProductModel) {
            $model->save();
            return $model;
        }
        return ProductModel::create(array_merge($this->definition(), $attributes));
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
            ProductModel::query()->insert($chunk);
        }

        return [];
    }
}
