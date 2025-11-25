<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use App\Infrastructure\Persistence\Models\ProductModel;
use App\Infrastructure\Persistence\Models\ReviewModel;
use Database\Factories\CategoryFactory;
use Database\Factories\OrderFactory;
use Database\Factories\OrderItemFactory;
use Database\Factories\ProductFactory;
use Database\Factories\ReviewFactory;
use Toporia\Framework\Database\Seeder;

/**
 * Product Seeder
 *
 * Professional, scalable seeder following Clean Architecture, SOLID principles,
 * and performance optimization best practices.
 *
 * Features:
 * - Batch operations for optimal performance
 * - Dependency management
 * - Progress tracking
 * - Configurable counts via command options
 * - Transaction support
 * - Memory-efficient processing
 *
 * Usage:
 *   php console db:seed --class=ProductSeeder
 *   php console db:seed --class=ProductSeeder --products=5000 --reviews=15000 --orders=1000
 *
 * Architecture:
 * - Single Responsibility: Each method handles one seeding task
 * - Open/Closed: Extensible via configuration and overrides
 * - Dependency Inversion: Uses factory interfaces and model abstractions
 * - Clean Architecture: Business logic separated from infrastructure
 *
 * Performance:
 * - Batch inserts (500 records per batch)
 * - Minimal memory footprint
 * - Optimized ID retrieval
 * - Efficient pivot table seeding
 */
final class ProductSeeder extends Seeder
{
    /**
     * Default seeding configuration.
     */
    private const DEFAULTS = [
        'categories' => 75,
        'products' => 10000,
        'reviews' => null, // Auto: products * 3
        'orders' => null, // Auto: products / 5
        'order_items' => null, // Auto: orders * 3
        'users' => 500,
        'pivot_categories_per_product' => [1, 3], // Min, Max
    ];

    /**
     * Batch size for bulk operations.
     */
    protected int $batchSize = 500;

    /**
     * Seeding statistics.
     */
    private array $stats = [];

    /**
     * Cached IDs for relationships.
     */
    private array $cachedIds = [];

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function seed(): void
    {
        $this->logStart();

        try {
            // Seed in dependency order
            $this->seedCategories();
            $this->seedUsers();
            $this->seedProducts();
            $this->seedReviews();
            $this->seedOrders();
            $this->seedOrderItems();
            $this->seedProductCategories();

            $this->logCompletion();
        } catch (\Throwable $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Seed categories.
     */
    private function seedCategories(): void
    {
        $count = $this->getCount('categories');
        $this->logProgress('Categories', $count);

        $this->cachedIds['categories'] = $this->factoryBatch(
            CategoryFactory::class,
            $count,
            [],
            CategoryModel::getTableName()
        );

        $this->stats['categories'] = $count;
        $this->logSuccess('Categories', $count);
    }

    /**
     * Seed users for orders.
     *
     * Note: Users are optional. If UserFactory doesn't exist,
     * orders will be created with null user_id (guest orders).
     */
    private function seedUsers(): void
    {
        $count = $this->getCount('users');

        // Skip if count is 0 or UserFactory doesn't exist
        if ($count === 0) {
            $this->stats['users'] = 0;
            return;
        }

        $userFactoryClass = 'Database\Factories\UserFactory';
        if (!class_exists($userFactoryClass)) {
            $this->stats['users'] = 0;
            return;
        }

        $this->logProgress('Users', $count);

        $this->cachedIds['users'] = $this->factoryBatch(
            $userFactoryClass,
            $count,
            [],
            \App\Infrastructure\Persistence\Models\UserModel::getTableName()
        );

        $this->stats['users'] = $count;
        $this->logSuccess('Users', $count);
    }

    /**
     * Seed products with category relationships.
     */
    private function seedProducts(): void
    {
        $count = $this->getCount('products');
        $this->logProgress('Products', $count);

        $categoryIds = $this->cachedIds['categories'] ?? [];
        if (empty($categoryIds)) {
            throw new \RuntimeException('Categories must be seeded before products');
        }

        $this->cachedIds['products'] = $this->factoryBatchWithCallables(
            ProductFactory::class,
            $count,
            [
                'category_id' => fn() => $this->faker()->randomElement($categoryIds),
            ],
            ProductModel::getTableName()
        );

        $this->stats['products'] = $count;
        $this->logSuccess('Products', $count);
    }

    /**
     * Seed reviews for products.
     */
    private function seedReviews(): void
    {
        $productCount = $this->stats['products'] ?? 0;
        $count = $this->getCount('reviews', $productCount * 3);
        $this->logProgress('Reviews', $count);

        $productIds = $this->cachedIds['products'] ?? [];
        if (empty($productIds)) {
            throw new \RuntimeException('Products must be seeded before reviews');
        }

        $this->factoryBatchWithCallables(
            ReviewFactory::class,
            $count,
            [
                'product_id' => fn() => $this->faker()->randomElement($productIds),
                'user_id' => null, // Anonymous reviews
            ],
            ReviewModel::getTableName()
        );

        $this->stats['reviews'] = $count;
        $this->logSuccess('Reviews', $count);
    }

    /**
     * Seed orders with user relationships.
     */
    private function seedOrders(): void
    {
        $productCount = $this->stats['products'] ?? 0;
        $count = $this->getCount('orders', (int) ($productCount / 5));
        $this->logProgress('Orders', $count);

        $userIds = $this->cachedIds['users'] ?? [];

        // If no users, create guest orders (user_id = null)
        $orderAttributes = [];
        if (!empty($userIds)) {
            $orderAttributes['user_id'] = fn() => $this->faker()->randomElement($userIds);
        } else {
            $orderAttributes['user_id'] = null;
        }

        $this->cachedIds['orders'] = $this->factoryBatchWithCallables(
            OrderFactory::class,
            $count,
            $orderAttributes,
            OrderModel::getTableName()
        );

        $this->stats['orders'] = $count;
        $this->logSuccess('Orders', $count);
    }

    /**
     * Seed order items linking orders and products.
     */
    private function seedOrderItems(): void
    {
        $orderCount = $this->stats['orders'] ?? 0;
        $count = $this->getCount('order_items', $orderCount * 3);
        $this->logProgress('Order Items', $count);

        $orderIds = $this->cachedIds['orders'] ?? [];
        $productIds = $this->cachedIds['products'] ?? [];

        if (empty($orderIds) || empty($productIds)) {
            throw new \RuntimeException('Orders and products must be seeded before order items');
        }

        $this->factoryBatchWithCallables(
            OrderItemFactory::class,
            $count,
            [
                'order_id' => fn() => $this->faker()->randomElement($orderIds),
                'product_id' => fn() => $this->faker()->randomElement($productIds),
            ],
            OrderItemModel::getTableName()
        );

        $this->stats['order_items'] = $count;
        $this->logSuccess('Order Items', $count);
    }

    /**
     * Seed product-category pivot table (many-to-many).
     */
    private function seedProductCategories(): void
    {
        $this->logProgress('Product-Category Relationships', 0);

        $productIds = $this->cachedIds['products'] ?? [];
        $categoryIds = $this->cachedIds['categories'] ?? [];

        if (empty($productIds) || empty($categoryIds)) {
            throw new \RuntimeException('Products and categories must be seeded before pivot relationships');
        }

        $pivotData = $this->generatePivotData($productIds, $categoryIds);
        $this->insertPivotData($pivotData);

        $this->stats['product_categories'] = count($pivotData);
        $this->logSuccess('Product-Category Relationships', count($pivotData));
    }

    /**
     * Generate pivot table data for product-category relationships.
     *
     * @param array<int> $productIds
     * @param array<int> $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function generatePivotData(array $productIds, array $categoryIds): array
    {
        $pivotData = [];
        $now = date('Y-m-d H:i:s');
        [$minCategories, $maxCategories] = self::DEFAULTS['pivot_categories_per_product'];

        foreach ($productIds as $productId) {
            $numCategories = $this->faker()->numberBetween($minCategories, $maxCategories);
            $selectedCategories = $this->faker()->randomElements(
                $categoryIds,
                min($numCategories, count($categoryIds))
            );

            foreach ($selectedCategories as $categoryId) {
                $pivotData[] = [
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                    'sort_order' => $this->faker()->numberBetween(0, 100),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $pivotData;
    }

    /**
     * Insert pivot data in batches.
     *
     * @param array<int, array<string, mixed>> $pivotData
     */
    private function insertPivotData(array $pivotData): void
    {
        foreach (array_chunk($pivotData, $this->batchSize) as $chunk) {
            $this->insert('product_categories', $chunk);
        }
    }

    /**
     * Get count for a seeding operation.
     *
     * @param string $key Configuration key
     * @param int|null $default Default value if not provided
     * @return int
     */
    private function getCount(string $key, ?int $default = null): int
    {
        $option = $this->getOption($key);
        if ($option !== null) {
            return (int) $option;
        }

        return $default ?? self::DEFAULTS[$key] ?? 0;
    }

    /**
     * Log seeding start.
     */
    private function logStart(): void
    {
        $this->info('🌱 Starting Product Seeder...');
        $this->newLine();
    }

    /**
     * Log progress for a seeding operation.
     *
     * @param string $entity Entity name
     * @param int $count Count to seed
     */
    private function logProgress(string $entity, int $count): void
    {
        $this->line("  Creating {$count} {$entity}...");
    }

    /**
     * Log successful completion of seeding operation.
     *
     * @param string $entity Entity name
     * @param int $count Count created
     */
    private function logSuccess(string $entity, int $count): void
    {
        $this->line("  ✓ Created {$count} {$entity}");
    }

    /**
     * Log completion summary.
     */
    private function logCompletion(): void
    {
        $this->newLine();
        $this->info('🎉 Seeding completed successfully!');
        $this->newLine();

        foreach ($this->stats as $entity => $count) {
            $entityName = ucwords(str_replace('_', ' ', $entity));
            $this->line("   - {$entityName}: {$count}");
        }
    }

    /**
     * Log error.
     *
     * @param \Throwable $e Exception
     */
    private function logError(\Throwable $e): void
    {
        $this->newLine();
        $this->error('❌ Seeding failed!');
        $this->error("   Error: {$e->getMessage()}");
        $this->line("   File: {$e->getFile()}:{$e->getLine()}");
    }

    /**
     * Factory batch with callable attribute support.
     *
     * Enhanced version of factoryBatch that supports callable attributes
     * for dynamic value generation during batch creation.
     *
     * @param string|\Toporia\Framework\Database\Contracts\FactoryInterface $factory
     * @param int $count
     * @param array<string, mixed> $attributes Attributes can be callables
     * @param string|null $table
     * @return array<int> Inserted IDs
     */
    private function factoryBatchWithCallables(
        string|\Toporia\Framework\Database\Contracts\FactoryInterface $factory,
        int $count,
        array $attributes = [],
        ?string $table = null
    ): array {
        if ($count <= 0) {
            return [];
        }

        // Resolve factory if string provided
        if (is_string($factory)) {
            $factory = $factory::new();
        }

        // Get table name from model
        if ($table === null) {
            $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class);
            if ($reflection->hasProperty($factory, 'model')) {
                $modelClass = $reflection->getPropertyValue($factory, 'model');

                if ($modelClass && method_exists($modelClass, 'getTableName')) {
                    $table = $modelClass::getTableName();
                } elseif ($modelClass && method_exists($modelClass, 'getTable')) {
                    $table = (new $modelClass)->getTable();
                } else {
                    throw new \InvalidArgumentException("Cannot infer table name. Please provide table parameter.");
                }
            } else {
                throw new \InvalidArgumentException("Factory must have model property or table must be provided.");
            }
        }

        if ($table === null) {
            throw new \InvalidArgumentException("Table name is required for batch insert.");
        }

        // Generate data using factory
        $data = [];
        $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class);
        $method = $reflection->getMethod($factory, 'definition');
        $method->setAccessible(true);

        // Access defaultAttributes via reflection
        $defaultAttributes = $reflection->getPropertyValue($factory, 'defaultAttributes');

        for ($i = 0; $i < $count; $i++) {
            $reflection->setPropertyValue($factory, 'sequenceIndex', $i);

            // Get base data from factory
            $row = array_merge($method->invoke($factory), $defaultAttributes);

            // Apply callable attributes
            foreach ($attributes as $key => $value) {
                if (is_callable($value) && !is_string($value)) {
                    $row[$key] = $value();
                } else {
                    $row[$key] = $value;
                }
            }

            $data[] = $row;
        }

        // Batch insert
        $this->insert($table, $data);

        // Return IDs (approximate - actual IDs depend on auto-increment)
        $connection = $this->getConnection();
        $lastId = (int) $connection->selectOne("SELECT MAX(id) as max_id FROM {$table}")['max_id'] ?? 0;

        return range($lastId - $count + 1, $lastId);
    }
}
