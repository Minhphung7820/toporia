<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\UpdateProduct;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use App\Domain\Entities\Product;
use App\Domain\ValueObjects\Product\Money;
use Toporia\Framework\Application\UseCase\AbstractHandler;
use InvalidArgumentException;

/**
 * Update Product Handler
 *
 * Use case for updating an existing product.
 *
 * Clean Architecture:
 * - Application layer Use Case Handler
 * - Orchestrates domain entities and repository
 *
 * SOLID Principles:
 * - Single Responsibility: Product update logic only
 * - Dependency Inversion: Depends on abstractions
 */
final class UpdateProductHandler extends AbstractHandler
{
    /**
     * @param ProductRepositoryInterface $repository Product repository (auto-wired)
     */
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    /**
     * Handle product update.
     *
     * @param UpdateProductCommand $command Update product command
     * @return Product Updated product
     * @throws InvalidArgumentException If product not found or SKU conflict
     */
    public function __invoke(object $command): mixed
    {
        // Find existing product
        $product = $this->repository->findById($command->id);
        if ($product === null) {
            throw new InvalidArgumentException("Product with ID {$command->id} not found");
        }

        // Business rule: SKU must be unique (excluding current product)
        if ($command->sku !== null
            && $this->repository->skuExists($command->sku, $command->id)) {
            throw new InvalidArgumentException(
                "Product with SKU '{$command->sku}' already exists"
            );
        }

        // Create Money Value Object
        $price = Money::fromAmount($command->price, $command->currency);

        // Update product (immutable pattern - returns new instance)
        $updatedProduct = $product->update(
            title: $command->title,
            price: $price,
            sku: $command->sku,
            description: $command->description
        );

        // Persist updated product
        return $this->repository->save($updatedProduct);
    }
}
