<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\UseCase;

use Toporia\Framework\Application\Contracts\HandlerInterface;

/**
 * Abstract Handler
 *
 * Base class for all command and query handlers.
 * Handlers contain the business logic for executing use cases.
 *
 * Architecture:
 * - Application layer service
 * - Orchestrates domain objects and infrastructure services
 * - Single use case per handler
 *
 * Usage:
 * ```php
 * final class CreateProductHandler extends AbstractHandler
 * {
 *     public function __construct(
 *         private readonly ProductRepository $repository
 *     ) {}
 *
 *     public function __invoke(CreateProductCommand $command): Product
 *     {
 *         $product = new Product(
 *             $command->title,
 *             $command->price
 *         );
 *
 *         return $this->repository->save($product);
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Application\UseCase
 */
abstract class AbstractHandler implements HandlerInterface
{
    /**
     * Execute the use case.
     *
     * @param object $message Command or Query object
     * @return mixed Result of the operation
     */
    abstract public function __invoke(object $message): mixed;
}
