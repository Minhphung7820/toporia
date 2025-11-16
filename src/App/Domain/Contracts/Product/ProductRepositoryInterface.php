<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Product;

use App\Domain\Entities\Product;

/**
 * Product Repository Interface
 *
 * Contract for Product persistence operations.
 * Pure domain interface - no infrastructure dependencies.
 *
 * Clean Architecture:
 * - Domain layer interface
 * - Infrastructure implements this interface
 * - Application layer depends on this abstraction
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on Product persistence only
 * - Dependency Inversion: Domain defines contract, Infrastructure implements
 */
interface ProductRepositoryInterface
{
    /**
     * Find product by ID.
     *
     * @param int $id Product ID
     * @return Product|null Product or null if not found
     */
    public function findById(int|string $id): mixed;

    /**
     * Find product by SKU.
     *
     * @param string $sku Product SKU
     * @return Product|null Product or null if not found
     */
    public function findBySku(string $sku): mixed;

    /**
     * Find all products.
     *
     * @return array<Product> All products
     */
    public function findAll(): array;

    /**
     * Find active products.
     *
     * @return array<Product> Active products
     */
    public function findActive(): array;

    /**
     * Save product (insert or update).
     *
     * @param Product $product Product to save
     * @return Product Saved product with ID
     */
    public function save(Product $product): mixed;

    /**
     * Delete product.
     *
     * @param int $id Product ID
     * @return bool True if deleted
     */
    public function delete(int|string $id): bool;

    /**
     * Check if SKU exists.
     *
     * @param string $sku SKU to check
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool True if exists
     */
    public function skuExists(string $sku, ?int $excludeId = null): bool;

    /**
     * Get total product count.
     *
     * @return int Total count
     */
    public function count(): int;

    /**
     * Get paginated products.
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Products per page
     * @return array{data: array<Product>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $page = 1, int $perPage = 15): array;
}
