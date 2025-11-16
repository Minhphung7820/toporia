<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\UpdateProduct;

use Toporia\Framework\Application\UseCase\AbstractCommand;

/**
 * Update Product Command
 *
 * DTO for updating an existing product.
 *
 * Clean Architecture:
 * - Application layer Command (DTO)
 * - Immutable data transfer object
 *
 * SOLID Principles:
 * - Single Responsibility: Data transfer only
 */
final class UpdateProductCommand extends AbstractCommand
{
    /**
     * @param int $id Product ID
     * @param string $title Product title
     * @param float $price Product price
     * @param string $currency Currency code
     * @param string|null $sku Product SKU
     * @param string|null $description Product description
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly float $price,
        public readonly string $currency = 'VND',
        public readonly ?string $sku = null,
        public readonly ?string $description = null,
    ) {}

    /**
     * Validate command input data.
     *
     * Performs basic input validation (format, required, type).
     * Business validation (e.g., product existence, SKU uniqueness) should be in Handler.
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

        // Title validation
        if (empty(trim($this->title))) {
            throw new \InvalidArgumentException('Product title is required');
        }

        if (strlen($this->title) > 255) {
            throw new \InvalidArgumentException('Product title must not exceed 255 characters');
        }

        // Price validation
        if ($this->price <= 0) {
            throw new \InvalidArgumentException('Product price must be greater than 0');
        }

        // Currency validation
        if (empty(trim($this->currency))) {
            throw new \InvalidArgumentException('Currency is required');
        }

        if (strlen($this->currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-character code (e.g., VND, USD)');
        }

        // SKU validation (if provided)
        if ($this->sku !== null && empty(trim($this->sku))) {
            throw new \InvalidArgumentException('SKU cannot be empty if provided');
        }
    }
}
