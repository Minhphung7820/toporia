<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Category Entity - Domain model for categories.
 *
 * Immutable entity following Clean Architecture principles.
 */
final class Category
{
    /**
     * @param int|null $id Category ID
     * @param string $name Category name
     * @param string $slug URL-friendly slug
     * @param string|null $description Category description
     * @param string|null $image Category image URL
     * @param bool $isActive Whether category is active
     * @param int $sortOrder Display order
     * @param int|null $parentId Parent category ID (for hierarchical categories)
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly bool $isActive = true,
        public readonly int $sortOrder = 0,
        public readonly ?int $parentId = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create a new Category with ID (after persistence).
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            image: $this->image,
            isActive: $this->isActive,
            sortOrder: $this->sortOrder,
            parentId: $this->parentId,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Activate the category.
     */
    public function activate(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            image: $this->image,
            isActive: true,
            sortOrder: $this->sortOrder,
            parentId: $this->parentId,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Deactivate the category.
     */
    public function deactivate(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            image: $this->image,
            isActive: false,
            sortOrder: $this->sortOrder,
            parentId: $this->parentId,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Check if category is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    /**
     * Check if category is a child category.
     */
    public function isChild(): bool
    {
        return $this->parentId !== null;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'parent_id' => $this->parentId,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
