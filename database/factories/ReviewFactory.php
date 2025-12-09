<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\ReviewModel;
use Toporia\Framework\Database\Factories\Factory;

class ReviewFactory extends Factory
{
    protected string $model = ReviewModel::class;

    public function definition(): array
    {
        return [
            'product_id' => null, // Will be set in seeder
            'user_id' => null, // Optional
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'rating' => $this->faker->numberBetween(1, 5),
            'title' => $this->faker->optional(0.7)->sentence(),
            'comment' => $this->faker->optional(0.8)->paragraphs(rand(1, 3), true),
            'is_approved' => $this->faker->boolean(70), // 70% approved
            'is_featured' => $this->faker->boolean(10), // 10% featured
            'helpful_count' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): ReviewModel
    {
        $model = $this->make($attributes);
        if ($model instanceof ReviewModel) {
            $model->save();
            return $model;
        }
        return ReviewModel::create(array_merge($this->definition(), $attributes));
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
            ReviewModel::query()->insert($chunk);
        }

        return [];
    }
}
