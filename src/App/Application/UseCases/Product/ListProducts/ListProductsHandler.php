<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\ListProducts;

use App\Domain\Contracts\Product\ProductRepositoryInterface;
use Toporia\Framework\Application\UseCase\AbstractHandler;

/**
 * List Products Handler
 *
 * Query handler for listing products with pagination.
 *
 * Clean Architecture:
 * - Application layer Query Handler
 * - Read-only operation (CQRS pattern)
 *
 * SOLID Principles:
 * - Single Responsibility: Product listing logic only
 * - Dependency Inversion: Depends on repository abstraction
 */
final class ListProductsHandler extends AbstractHandler
{
    /**
     * @param ProductRepositoryInterface $repository Product repository (auto-wired)
     */
    public function __construct(
        private readonly ProductRepositoryInterface $repository
    ) {}

    /**
     * Handle product listing.
     *
     * @param ListProductsQuery $query List products query
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    public function __invoke(object $query): mixed
    {
        return $this->repository->paginate($query->page, $query->perPage);
    }
}
