<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Product;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use App\Domain\Entities\Product;
use App\Domain\ValueObjects\Product\Money;
use App\Domain\ValueObjects\Product\ProductStatus;
use App\Infrastructure\Repository\BaseRepository;

/**
 * PDO Product Repository
 *
 * Infrastructure implementation of Product persistence.
 *
 * Clean Architecture:
 * - Infrastructure layer
 * - Implements Domain repository interface
 * - Extends BaseRepository for common functionality
 *
 * SOLID Principles:
 * - Single Responsibility: Product persistence only
 * - Dependency Inversion: Implements domain interface
 */
final class PdoProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    protected function getTableName(): string
    {
        return 'products';
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
    protected function mapToEntity(array $row): object
    {
        // Map is_active boolean to ProductStatus
        $isActive = isset($row['is_active']) ? (bool) $row['is_active'] : false;
        $status = $isActive ? ProductStatus::active() : ProductStatus::inactive();

        return new Product(
            id: (int) $row['id'],
            title: $row['title'],
            sku: $row['sku'] ?? null,
            description: $row['description'] ?? null,
            price: Money::fromAmount(
                (float) $row['price'],
                'VND'  // Default currency since table doesn't have currency column
            ),
            stock: (int) ($row['stock'] ?? 0),
            status: $status,
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToRow(object $entity): array
    {
        assert($entity instanceof Product);

        $data = [
            'title' => $entity->title,
            'sku' => $entity->sku,
            'description' => $entity->description,
            'price' => $entity->price->amount,
            'stock' => $entity->stock,
            'is_active' => $entity->status->isActive() ? 1 : 0,  // Map ProductStatus to is_active boolean
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Only include created_at for new products
        if ($entity->id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySku(string $sku): mixed
    {
        return $this->findOneBy(['sku' => $sku]);
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(): array
    {
        return $this->findBy(['is_active' => 1]);  // Use is_active column
    }

    /**
     * {@inheritdoc}
     */
    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $query = $this->createQueryBuilder()
            ->where('sku', $sku);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count();

        $results = $this->createQueryBuilder()
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        // Convert RowCollection to array if needed
        $resultsArray = is_array($results) ? $results : iterator_to_array($results);
        $products = array_map([$this, 'mapToEntity'], $resultsArray);

        return [
            'data' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}
