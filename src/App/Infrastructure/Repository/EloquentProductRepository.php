<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\ProductRepository as ProductRepositoryInterface;
use App\Domain\Repository\RepositoryInterface;
use App\Infrastructure\Persistence\Models\ProductModel;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Eloquent Product Repository
 *
 * Implementation of ProductRepository using Eloquent ORM.
 * Extends BaseRepository for common functionality.
 *
 * SOLID Principles:
 * - Single Responsibility: Handles Product persistence
 * - Open/Closed: Extensible via inheritance
 * - Liskov Substitution: Implements ProductRepositoryInterface
 * - Dependency Inversion: Depends on abstractions
 *
 * Performance:
 * - Query caching
 * - Batch operations
 * - Eager loading support
 */
final class EloquentProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    /**
     * @param ConnectionInterface $connection Database connection
     * @param CacheInterface|null $cache Cache instance
     */
    public function __construct(
        ConnectionInterface $connection,
        ?CacheInterface $cache = null
    ) {
        parent::__construct($connection, $cache);
        $this->cachePrefix = 'product';
        $this->cacheTtl = 3600; // 1 hour
    }

    /**
     * {@inheritdoc}
     */
    protected function getTableName(): string
    {
        return ProductModel::getTableName();
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClass(): string
    {
        return Product::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToEntity(array $row): Product
    {
        return Product::fromArray($row);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToRow(object $entity): array
    {
        if (!$entity instanceof Product) {
            throw new \InvalidArgumentException('Entity must be an instance of Product');
        }

        return $entity->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function nextId(): int
    {
        // Get next auto-increment ID
        $result = $this->connection->query()
            ->statement("SHOW TABLE STATUS LIKE '{$this->getTableName()}'");

        // This is a simplified version - in production, use proper sequence or UUID
        $maxId = $this->createQueryBuilder()
            ->selectRaw('COALESCE(MAX(id), 0) as max_id')
            ->first();

        return (int) ($maxId->max_id ?? 0) + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function store(Product $product): Product
    {
        $saved = $this->save($product);
        return $saved instanceof Product ? $saved : $this->mapToEntity($this->mapToRow($saved));
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int|string $id): ?object
    {
        $entity = parent::findById($id);
        return $entity instanceof Product ? $entity : null;
    }

    /**
     * Find product by ID (type-safe).
     *
     * @param int $id Product ID
     * @return Product|null Product or null if not found
     */
    public function findProductById(int $id): ?Product
    {
        return $this->findById($id);
    }

    /**
     * Find products by status.
     *
     * @param string $status Product status
     * @return array<Product> Array of products
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Find products by price range.
     *
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @return array<Product> Array of products
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        $rows = $this->createQueryBuilder()
            ->where('price', '>=', $minPrice)
            ->where('price', '<=', $maxPrice)
            ->get();

        // RowCollection contains arrays, each $row is already an array
        return array_map(
            fn($row) => $this->mapToEntity($row),
            $rows->toArray()
        );
    }

    /**
     * Find active products.
     *
     * @return array<Product> Array of active products
     */
    public function findActive(): array
    {
        return $this->findByStatus('active');
    }

    /**
     * Count products by status.
     *
     * @param string $status Product status
     * @return int Count
     */
    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
