<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\DeleteProduct;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use Toporia\Framework\Application\UseCase\AbstractHandler;
use InvalidArgumentException;

/**
 * Delete Product Handler
 *
 * Use case for deleting a product.
 *
 * Clean Architecture:
 * - Application layer Use Case Handler
 * - Coordinates deletion through repository
 *
 * SOLID Principles:
 * - Single Responsibility: Product deletion logic only
 */
final class DeleteProductHandler extends AbstractHandler
{
    /**
     * @param ProductRepositoryInterface $repository Product repository (auto-wired)
     */
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    /**
     * Handle product deletion.
     *
     * @param DeleteProductCommand $command Delete product command
     * @return bool True if deleted successfully
     * @throws InvalidArgumentException If product not found
     */
    public function __invoke(object $command): mixed
    {
        // Verify product exists
        $product = $this->repository->findById($command->id);
        if ($product === null) {
            throw new InvalidArgumentException("Product with ID {$command->id} not found");
        }

        // Delete product
        return $this->repository->delete($command->id);
    }
}
