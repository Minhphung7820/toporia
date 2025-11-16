<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Contracts\Transformer\ResourceCollectionInterface;
use App\Domain\Contracts\Transformer\ResourceInterface;

/**
 * Resource Collection Implementation
 *
 * Represents a collection of resources with metadata.
 *
 * Clean Architecture:
 * - Implements Domain ResourceCollectionInterface
 * - Used in Presentation layer
 *
 * SOLID Principles:
 * - Single Responsibility: Manages collection of resources
 */
final class ResourceCollection implements ResourceCollectionInterface
{
    /**
     * @param array<ResourceInterface> $resources Array of resources
     * @param array<string, mixed> $meta Collection metadata
     */
    public function __construct(
        private readonly array $resources,
        private readonly array $meta = []
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->resources);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->resources);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(array $context = []): array
    {
        $data = [
            'data' => array_map(
                fn(ResourceInterface $resource) => $resource->toArray($context),
                $this->resources
            ),
        ];

        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(array $context = []): string
    {
        return json_encode($this->toArray($context), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return $this->toArray();
    }

    /**
     * Create resource collection from resources.
     *
     * @param array<ResourceInterface> $resources Array of resources
     * @param array<string, mixed> $meta Optional metadata
     * @return self Resource collection instance
     */
    public static function make(array $resources, array $meta = []): self
    {
        return new self($resources, $meta);
    }
}

