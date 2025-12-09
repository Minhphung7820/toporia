<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\CategoryModel;
use Toporia\Framework\Database\Factories\Factory;

class CategoryFactory extends Factory
{
    protected string $model = CategoryModel::class;

    public function definition(): array
    {
        $name = $this->faker->words(rand(1, 3), true);

        return [
            'name' => ucwords($name),
            'slug' => strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->optional(0.7)->paragraph(),
            'image' => $this->faker->optional(0.5)->imageUrl(400, 300),
            'is_active' => $this->faker->boolean(90), // 90% active
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): CategoryModel
    {
        $model = $this->make($attributes);
        if ($model instanceof CategoryModel) {
            $model->save();
            return $model;
        }
        return CategoryModel::create(array_merge($this->definition(), $attributes));
    }

    /**
     * Create many models.
     */
    public function createMany(int $count, array $attributes = []): array
    {
        $models = [];
        $data = [];

        for ($i = 0; $i < $count; $i++) {
            $data[] = array_merge($this->definition(), $attributes);
        }

        // Batch insert for performance
        foreach (array_chunk($data, 500) as $chunk) {
            CategoryModel::query()->insert($chunk);
        }

        // Return last inserted IDs
        $lastId = CategoryModel::query()->max('id') ?? 0;
        for ($i = 0; $i < $count; $i++) {
            $models[] = CategoryModel::find($lastId - $count + $i + 1);
        }

        return array_filter($models);
    }
}
