<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\CreateProduct;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use App\Domain\Entities\Product;
use App\Domain\ValueObjects\Product\Money;
use Toporia\Framework\Application\UseCase\AbstractHandler;
use InvalidArgumentException;

/**
 * Create Product Handler
 *
 * Use case for creating a new product.
 * Implements business logic for product creation.
 *
 * Clean Architecture:
 * - Application layer Use Case Handler
 * - Depends on Domain contracts (ProductRepositoryInterface)
 * - Pure business logic orchestration
 *
 * SOLID Principles:
 * - Single Responsibility: Product creation logic only
 * - Dependency Inversion: Depends on abstractions (repository interface)
 * - Dependencies auto-wired by DI container
 */
final class CreateProductHandler extends AbstractHandler
{
    /**
     * @param ProductRepositoryInterface $repository Product repository (auto-wired)
     */
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    /**
     * Handle product creation.
     *
     * @param CreateProductCommand $command Create product command
     * @return Product Created product with ID
     * @throws InvalidArgumentException If SKU already exists
     */
    public function __invoke(object $command): mixed
    {
        // Business rule: SKU must be unique
        if ($command->sku !== null && $this->repository->skuExists($command->sku)) {
            throw new InvalidArgumentException(
                "Product with SKU '{$command->sku}' already exists"
            );
        }

        // Create Money Value Object
        $price = Money::fromAmount($command->price, $command->currency);

        // Create Product Entity
        $product = Product::create(
            title: $command->title,
            price: $price,
            sku: $command->sku,
            description: $command->description,
            stock: $command->stock
        );

        // Persist through repository
        return $this->repository->save($product);
    }
}
