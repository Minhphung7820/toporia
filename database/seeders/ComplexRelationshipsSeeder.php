<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\UserModel;
use App\Infrastructure\Persistence\Models\ProductModel;
use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\TagModel;
use App\Infrastructure\Persistence\Models\ReviewModel;
use App\Infrastructure\Persistence\Models\UserProfileModel;
use Database\Factories\UserFactoryExtended;
use Database\Factories\TagFactory;
use Database\Factories\UserProfileFactory;
use Toporia\Framework\Database\Seeder;

/**
 * Seeder for complex relationships testing.
 */
class ComplexRelationshipsSeeder extends Seeder
{
    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [ProductSeeder::class];
    }

    /**
     * {@inheritdoc}
     */
    protected function seed(): void
    {
        $this->info('🌱 Starting Complex Relationships Seeder...');

        // 1. Create Users with Profiles using factories
        $this->info('Creating users with profiles...');
        $users = [];
        $userFactory = UserFactoryExtended::new();
        $profileFactory = UserProfileFactory::new();

        for ($i = 0; $i < 20; $i++) {
            // Use only basic user data that exists in current users table
            $userData = [
                'name' => "User " . ($i + 1),
                'email' => "user" . ($i + 1) . "@example.com",
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => password_hash('password', PASSWORD_DEFAULT),
            ];

            $user = UserModel::create($userData);

            // Create profile for each user
            $profileData = array_merge($profileFactory->definition(), [
                'user_id' => $user->id,
                'bio' => "Bio for user " . ($i + 1),
            ]);

            UserProfileModel::create($profileData);
            $users[] = $user;
        }

        // 2. Create Tags using factory
        $this->info('Creating tags...');
        $tags = [];
        $tagFactory = TagFactory::new();
        $tagNames = ['Electronics', 'Mobile', 'Laptop', 'Gaming', 'Accessories', 'Audio', 'Video', 'Smart', 'Wireless', 'Premium'];

        foreach ($tagNames as $tagName) {
            $tagData = array_merge($tagFactory->definition(), [
                'name' => $tagName,
                'slug' => strtolower($tagName),
                'description' => "Description for {$tagName} tag",
                'is_active' => true,
                'usage_count' => rand(10, 500),
            ]);

            $tag = TagModel::create($tagData);
            $tags[] = $tag;
        }

        // 3. Get existing products and categories
        $this->info('Loading existing products and categories...');
        $products = ProductModel::all();
        $categories = CategoryModel::all();

        if ($products->isEmpty() || $categories->isEmpty()) {
            $this->error("Please run ProductSeeder first to create products and categories.");
            return;
        }

        // 4. Create BelongsToMany relationships (Product <-> Categories with pivot data)
        $this->info('Creating product-category relationships...');
        foreach ($products as $product) {
            // Attach 1-3 categories to each product with pivot data
            $randomCategories = $categories->random(rand(1, 3));
            $categoryIds = [];
            foreach ($randomCategories as $category) {
                $categoryIds[] = $category->id;
            }

            $pivotData = [];
            foreach ($categoryIds as $index => $categoryId) {
                $pivotData[$categoryId] = [
                    'sort_order' => $index + 1,
                    'is_featured' => rand(0, 1) === 1,
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            $product->categories()->sync($pivotData);
        }

        // 5. Create Product <-> Tags relationships
        $this->info('Creating product-tag relationships...');
        foreach ($products as $product) {
            $randomTags = array_rand($tags, min(rand(2, 5), count($tags)));
            if (!is_array($randomTags)) $randomTags = [$randomTags];
            $tagIds = array_map(fn($i) => $tags[$i]->id, $randomTags);

            $pivotData = [];
            foreach ($tagIds as $tagId) {
                $pivotData[$tagId] = [
                    'created_by' => $users[rand(0, count($users) - 1)]->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            $product->tags()->sync($pivotData);
        }

        // 6. Create User <-> Product favorites
        $this->info('Creating user favorites...');
        foreach ($users as $user) {
            $favoriteProducts = $products->random(rand(3, 8))->pluck('id')->toArray();
            $user->favoriteProducts()->sync($favoriteProducts);
        }

        // 7. Create Product Relations (self-referencing many-to-many)
        $this->info('Creating product relations...');
        foreach ($products as $product) {
            $relatedProducts = $products->where('id', '!=', $product->id)
                ->random(rand(2, 4))
                ->pluck('id')
                ->toArray();

            $pivotData = [];
            foreach ($relatedProducts as $relatedId) {
                $pivotData[$relatedId] = [
                    'relation_type' => ['similar', 'complementary', 'alternative', 'accessory'][rand(0, 3)],
                    'strength' => round(rand(50, 100) / 100, 2),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            $product->relatedProducts()->sync($pivotData);
        }

        // 8. Create more Reviews with user relationships
        $this->info('Creating additional reviews...');
        foreach ($products as $product) {
            $reviewCount = rand(5, 15);
            for ($i = 0; $i < $reviewCount; $i++) {
                ReviewModel::create([
                    'product_id' => $product->id,
                    'user_id' => $users[rand(0, count($users) - 1)]->id,
                    'rating' => rand(1, 5),
                    'title' => "Review " . ($i + 1) . " for " . $product->title,
                    'comment' => "This is review comment " . ($i + 1) . " for product " . $product->title,
                    'is_approved' => rand(0, 1) === 1,
                    'helpful_count' => rand(0, 20),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days')),
                ]);
            }
        }

        // 9. Create Polymorphic Tag relationships (Categories tagged with tags)
        $this->info('Creating polymorphic tag relationships...');
        foreach ($categories as $category) {
            $randomTags = array_rand($tags, min(rand(1, 3), count($tags)));
            if (!is_array($randomTags)) $randomTags = [$randomTags];
            $tagIds = array_map(fn($i) => $tags[$i]->id, $randomTags);

            foreach ($tagIds as $tagId) {
                $this->insert('taggables', [[
                    'tag_id' => $tagId,
                    'taggable_type' => CategoryModel::class,
                    'taggable_id' => $category->id,
                    'created_by' => $users[rand(0, count($users) - 1)]->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]]);
            }
        }

        $this->info('🎉 Complex relationships seeded successfully!');
        $this->line("Created:");
        $this->line("- " . count($users) . " users with profiles");
        $this->line("- " . count($tags) . " tags");
        $this->line("- Product-Category relationships with pivot data");
        $this->line("- Product-Tag relationships");
        $this->line("- User-Product favorites");
        $this->line("- Product-Product relations");
        $this->line("- Additional reviews with user relationships");
        $this->line("- Polymorphic tag relationships");
    }
};
