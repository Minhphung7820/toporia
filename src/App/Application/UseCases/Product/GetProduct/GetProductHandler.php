<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\GetProduct;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use App\Domain\Entities\Product;
use Toporia\Framework\Application\UseCase\AbstractHandler;
use InvalidArgumentException;

/**
 * Get Product Handler
 *
 * Query handler for retrieving a single product.
 *
 * Clean Architecture:
 * - Application layer Query Handler
 * - Read-only operation
 *
 * SOLID Principles:
 * - Single Responsibility: Product retrieval logic only
 */
final class GetProductHandler extends AbstractHandler
{
    /**
     * @param ProductRepositoryInterface $repository Product repository (auto-wired)
     */
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    /**
     * Handle product retrieval.
     *
     * @param GetProductQuery $query Get product query
     * @return Product Product entity
     * @throws InvalidArgumentException If product not found
     */
    public function __invoke(object $query): mixed
    {
        $product = $this->repository->findById($query->id);

        if ($product === null) {
            throw new InvalidArgumentException("Product with ID {$query->id} not found");
        }

        return $product;
    }
}
