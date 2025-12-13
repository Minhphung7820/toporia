<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Contracts\Transformer\ResourceInterface;

/**
 * Resource Implementation
 *
 * Represents a transformed entity as a resource for API responses.
 *
 * Clean Architecture:
 * - Implements Domain ResourceInterface
 * - Used in Presentation layer
 *
 * SOLID Principles:
 * - Single Responsibility: Represents entity in presentation format
 * - Immutability: Resource data is readonly
 */
final class Resource implements ResourceInterface
{
    /**
     * @param array<string, mixed> $data Resource data
     */
    public function __construct(
        private readonly array $data
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(array $context = []): array
    {
        return $this->data;
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
        return $this->data;
    }

    /**
     * Create resource from array.
     *
     * @param array<string, mixed> $data Resource data
     * @return self Resource instance
     */
    public static function make(array $data): self
    {
        return new self($data);
    }
}

