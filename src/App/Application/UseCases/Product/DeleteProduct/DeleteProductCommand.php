<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\DeleteProduct;

use Toporia\Framework\Application\UseCase\AbstractCommand;

/**
 * Delete Product Command
 *
 * DTO for deleting a product.
 *
 * Clean Architecture:
 * - Application layer Command (DTO)
 *
 * SOLID Principles:
 * - Single Responsibility: Data transfer only
 */
final class DeleteProductCommand extends AbstractCommand
{
    /**
     * @param int $id Product ID to delete
     */
    public function __construct(
        public readonly int $id
    ) {}

    /**
     * Validate command input data.
     *
     * Performs basic input validation (format, required, type).
     * Business validation (e.g., product existence, deletion rules) should be in Handler.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(): void
    {
        // ID validation
        if ($this->id <= 0) {
            throw new \InvalidArgumentException('Product ID must be greater than 0');
        }
    }
}
