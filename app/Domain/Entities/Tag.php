<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Tag Entity - Domain model for tags.
 *
 * Immutable entity following Clean Architecture principles.
 */
final class Tag
{
    /**
     * @param int|null $id Tag ID
     * @param string $name Tag name
     * @param string $slug URL-friendly slug
     * @param string|null $description Tag description
     * @param string $color Tag color (hex code)
     * @param bool $isActive Whether tag is active
     * @param int $usageCount Number of times tag is used
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description = null,
        public readonly string $color = '#6366f1',
        public readonly bool $isActive = true,
        public readonly int $usageCount = 0,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create a new Tag with ID (after persistence).
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            color: $this->color,
            isActive: $this->isActive,
            usageCount: $this->usageCount,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            color: $this->color,
            isActive: $this->isActive,
            usageCount: $this->usageCount + 1,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    /**
     * Decrement usage count.
     */
    public function decrementUsage(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            color: $this->color,
            isActive: $this->isActive,
            usageCount: max(0, $this->usageCount - 1),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    /**
     * Activate the tag.
     */
    public function activate(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            color: $this->color,
            isActive: true,
            usageCount: $this->usageCount,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Deactivate the tag.
     */
    public function deactivate(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            color: $this->color,
            isActive: false,
            usageCount: $this->usageCount,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Check if tag is popular (used more than threshold).
     */
    public function isPopular(int $threshold = 10): bool
    {
        return $this->usageCount >= $threshold;
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
            'color' => $this->color,
            'is_active' => $this->isActive,
            'usage_count' => $this->usageCount,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
