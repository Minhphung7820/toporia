<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Contracts;

use JsonSerializable;

/**
 * Interface ResourceInterface
 *
 * Contract for API resources (Laravel-style JsonResource).
 * Resources wrap entities for JSON/API responses.
 *
 * @package Toporia\Framework\DataTransfer\Contracts
 */
interface ResourceInterface extends JsonSerializable
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get the underlying resource data.
     *
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * Create resource with additional data.
     *
     * @param array<string, mixed> $additional
     * @return static
     */
    public function additional(array $additional): static;

    /**
     * Get additional data.
     *
     * @return array<string, mixed>
     */
    public function getAdditional(): array;

    /**
     * Wrap the resource response.
     *
     * @param string|null $wrapper
     * @return static
     */
    public function wrap(?string $wrapper): static;

    /**
     * Resolve the resource to an array.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array;
}
