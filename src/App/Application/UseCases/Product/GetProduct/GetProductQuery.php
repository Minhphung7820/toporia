<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\GetProduct;

use Toporia\Framework\Application\UseCase\AbstractQuery;

/**
 * Get Product Query
 *
 * DTO for retrieving a single product.
 *
 * Clean Architecture:
 * - Application layer Query (DTO)
 *
 * SOLID Principles:
 * - Single Responsibility: Data transfer only
 */
final class GetProductQuery extends AbstractQuery
{
    /**
     * @param int $id Product ID
     */
    public function __construct(
        public readonly int $id
    ) {}
}
