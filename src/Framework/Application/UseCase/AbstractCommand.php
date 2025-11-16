<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\UseCase;

use Toporia\Framework\Application\Contracts\CommandInterface;

/**
 * Abstract Command
 *
 * Base class for all commands (CQRS write operations).
 * Commands represent intent to change system state.
 *
 * Architecture:
 * - Application layer DTO
 * - Immutable data transfer object
 * - No business logic
 *
 * Usage:
 * ```php
 * final class CreateProductCommand extends AbstractCommand
 * {
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly float $price
 *     ) {}
 *
 *     public function validate(): void
 *     {
 *         if (empty($this->title)) {
 *             throw new \InvalidArgumentException('Title is required');
 *         }
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Application\UseCase
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * Validate the command data.
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
