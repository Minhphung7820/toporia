<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product\CreateProduct;

use Toporia\Framework\Application\UseCase\AbstractCommand;

/**
 * Create Product Command
 *
 * DTO for creating a new product.
 *
 * Clean Architecture:
 * - Application layer Command (DTO)
 * - Carries data from Presentation to Application
 * - No business logic, just data transfer
 *
 * SOLID Principles:
 * - Single Responsibility: Data transfer only
 * - Immutability: Readonly properties
 */
final class CreateProductCommand extends AbstractCommand
{
    /**
     * @param string $title Product title
     * @param float $price Product price
     * @param string $currency Currency code
     * @param string|null $sku Product SKU
     * @param string|null $description Product description
     * @param int $stock Initial stock quantity
     */
    public function __construct(
        public readonly string $title,
        public readonly float $price,
        public readonly string $currency = 'VND',
        public readonly ?string $sku = null,
        public readonly ?string $description = null,
        public readonly int $stock = 0,
    ) {}

    /**
     * Validate command input data.
     *
     * Performs basic input validation (format, required, type).
     * Business validation (e.g., SKU uniqueness) should be in Handler.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(): void
    {
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

        // Stock validation
        if ($this->stock < 0) {
            throw new \InvalidArgumentException('Stock quantity cannot be negative');
        }
    }
}
