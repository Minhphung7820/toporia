<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Product\Money;
use App\Domain\ValueObjects\Product\ProductStatus;
use InvalidArgumentException;

/**
 * Product Entity
 *
 * Represents a product in the domain with business rules.
 * Fully immutable - all modifications return new instances.
 *
 * Clean Architecture:
 * - Domain layer Entity
 * - Pure business logic, no framework dependencies
 * - Uses Value Objects for domain concepts
 *
 * SOLID Principles:
 * - Single Responsibility: Product business rules
 * - Immutability: All properties readonly, with* methods return new instances
 */
final class Product
{
    /**
     * @param int|null $id Product ID (null for new products)
     * @param string $title Product title (min 3 chars)
     * @param string|null $sku Product SKU (unique identifier)
     * @param string|null $description Product description
     * @param Money $price Product price
     * @param int $stock Stock quantity (>= 0)
     * @param ProductStatus $status Product status
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly ?string $sku,
        public readonly ?string $description,
        public readonly Money $price,
        public readonly int $stock,
        public readonly ProductStatus $status,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->validateBusinessRules();
    }

    /**
     * Create new product (factory method).
     *
     * @param string $title Product title
     * @param Money $price Product price
     * @param string|null $sku SKU
     * @param string|null $description Description
     * @param int $stock Initial stock
     * @return self New Product instance
     */
    public static function create(
        string $title,
        Money $price,
        ?string $sku = null,
        ?string $description = null,
        int $stock = 0
    ): self {
        return new self(
            id: null,
            title: $title,
            sku: $sku,
            description: $description,
            price: $price,
            stock: $stock,
            status: ProductStatus::draft(),
            createdAt: new \DateTimeImmutable(),
            updatedAt: null
        );
    }

    /**
     * Create Product from array.
     *
     * @param array<string, mixed> $data Product data
     * @return self Product instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            title: $data['title'] ?? '',
            sku: $data['sku'] ?? null,
            description: $data['description'] ?? null,
            price: isset($data['price'])
                ? Money::fromAmount($data['price'], $data['currency'] ?? 'VND')
                : Money::zero(),
            stock: (int) ($data['stock'] ?? 0),
            status: isset($data['status'])
                ? ProductStatus::fromString($data['status'])
                : ProductStatus::draft(),
            createdAt: isset($data['created_at'])
                ? new \DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new \DateTimeImmutable($data['updated_at'])
                : null
        );
    }

    /**
     * Update product details.
     *
     * @param string $title New title
     * @param Money $price New price
     * @param string|null $sku New SKU
     * @param string|null $description New description
     * @return self New Product instance
     */
    public function update(
        string $title,
        Money $price,
        ?string $sku = null,
        ?string $description = null
    ): self {
        return new self(
            $this->id,
            $title,
            $sku,
            $description,
            $price,
            $this->stock,
            $this->status,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Change product status.
     *
     * @param ProductStatus $status New status
     * @return self New Product instance
     */
    public function changeStatus(ProductStatus $status): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->sku,
            $this->description,
            $this->price,
            $this->stock,
            $status,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Activate product.
     *
     * @return self New Product instance
     */
    public function activate(): self
    {
        return $this->changeStatus(ProductStatus::active());
    }

    /**
     * Deactivate product.
     *
     * @return self New Product instance
     */
    public function deactivate(): self
    {
        return $this->changeStatus(ProductStatus::inactive());
    }

    /**
     * Archive product.
     *
     * @return self New Product instance
     */
    public function archive(): self
    {
        return $this->changeStatus(ProductStatus::archived());
    }

    /**
     * Increase stock.
     *
     * @param int $quantity Quantity to add
     * @return self New Product instance
     */
    public function increaseStock(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return new self(
            $this->id,
            $this->title,
            $this->sku,
            $this->description,
            $this->price,
            $this->stock + $quantity,
            $this->status,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Decrease stock.
     *
     * @param int $quantity Quantity to remove
     * @return self New Product instance
     * @throws InvalidArgumentException If insufficient stock
     */
    public function decreaseStock(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($this->stock < $quantity) {
            throw new InvalidArgumentException(
                "Insufficient stock. Available: {$this->stock}, requested: {$quantity}"
            );
        }

        return new self(
            $this->id,
            $this->title,
            $this->sku,
            $this->description,
            $this->price,
            $this->stock - $quantity,
            $this->status,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Update price.
     *
     * @param Money $price New price
     * @return self New Product instance
     */
    public function updatePrice(Money $price): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->sku,
            $this->description,
            $price,
            $this->stock,
            $this->status,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Check if product is in stock.
     *
     * @return bool True if in stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if product can be ordered.
     *
     * @return bool True if can be ordered
     */
    public function canBeOrdered(): bool
    {
        return $this->status->canBeOrdered() && $this->isInStock();
    }

    /**
     * Check if product is active.
     *
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Validate business rules.
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validateBusinessRules(): void
    {
        if (strlen($this->title) < 3) {
            throw new InvalidArgumentException('Product title must be at least 3 characters');
        }

        if (strlen($this->title) > 255) {
            throw new InvalidArgumentException('Product title must not exceed 255 characters');
        }

        if ($this->stock < 0) {
            throw new InvalidArgumentException('Stock cannot be negative');
        }

        if ($this->sku !== null && strlen($this->sku) > 100) {
            throw new InvalidArgumentException('SKU must not exceed 100 characters');
        }
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed> Product data
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price->amount,
            'currency' => $this->price->currency,
            'stock' => $this->stock,
            'status' => $this->status->toString(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get product ID.
     *
     * @return int|null Product ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
