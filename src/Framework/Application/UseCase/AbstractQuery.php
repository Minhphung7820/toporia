<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\UseCase;

use Toporia\Framework\Application\Contracts\QueryInterface;

/**
 * Abstract Query
 *
 * Base class for all queries (CQRS read operations).
 * Queries represent read-only data fetching operations.
 *
 * Architecture:
 * - Application layer DTO
 * - Immutable data transfer object
 * - No business logic
 * - No side effects
 *
 * Usage:
 * ```php
 * final class GetProductQuery extends AbstractQuery
 * {
 *     public function __construct(
 *         public readonly string $id
 *     ) {}
 *
 *     public function validate(): void
 *     {
 *         if (empty($this->id)) {
 *             throw new \InvalidArgumentException('ID is required');
 *         }
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Application\UseCase
 */
abstract class AbstractQuery implements QueryInterface
{
    /**
     * Validate the query parameters.
     *
     * Override this method to add custom validation.
     * Default implementation does nothing.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(): void
    {
        // Override in subclasses for custom validation
    }
}
