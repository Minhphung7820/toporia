<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\ListProducts;

use Toporia\Framework\Application\UseCase\AbstractQuery;

/**
 * List Products Query
 *
 * DTO for listing products with pagination.
 *
 * Clean Architecture:
 * - Application layer Query (DTO)
 * - Read-only operation
 *
 * SOLID Principles:
 * - Single Responsibility: Data transfer only
 */
final class ListProductsQuery extends AbstractQuery
{
    /**
     * @param int $page Page number (1-indexed)
     * @param int $perPage Products per page
     */
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}
}
