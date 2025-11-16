<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Product Domain Entity
 *
 * Represents a Product in the domain layer.
 * This is a pure domain object with no infrastructure dependencies.
 *
 * SOLID Principles:
 * - Single Responsibility: Represents Product domain concept
 * - Immutability: ID is readonly, other properties can be modified via methods
 */
final class Product
{
    /**
     * @param int|null $id Product ID
     * @param string $title Product title
     * @param string|null $sku Product SKU
     * @param string|null $description Product description
     * @param float $price Product price
     * @param int $stock Stock quantity
     * @param bool $isActive Whether product is active
     * @param string|null $status Product status
     */
    public function __construct(
        public readonly ?int $id,
        public string $title,
        public ?string $sku = null,
        public ?string $description = null,
        public float $price = 0.0,
        public int $stock = 0,
        public bool $isActive = true,
        public ?string $status = 'active',
    ) {}

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
            price: (float) ($data['price'] ?? 0.0),
            stock: (int) ($data['stock'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            status: $data['status'] ?? 'active',
        );
    }

    /**
     * Convert Product to array.
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
            'price' => $this->price,
            'stock' => $this->stock,
            'is_active' => $this->isActive,
            'status' => $this->status,
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
