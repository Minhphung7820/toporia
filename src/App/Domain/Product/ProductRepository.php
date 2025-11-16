<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Repository\RepositoryInterface;

/**
 * Product Repository Interface
 *
 * Domain contract for Product persistence.
 * Extends base RepositoryInterface for common operations.
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on Product-specific operations
 * - Dependency Inversion: Domain depends on abstraction
 */
interface ProductRepository extends RepositoryInterface
{
    /**
     * Get next available ID.
     *
     * @return int Next ID
     */
    public function nextId(): int;

    /**
     * Store product (create or update).
     *
     * @param Product $product Product to store
     * @return Product Stored product
     */
    public function store(Product $product): Product;

    /**
     * Find product by ID.
     *
     * @param int $id Product ID
     * @return Product|null Product or null if not found
     */
    public function findById(int $id): ?Product;
}
