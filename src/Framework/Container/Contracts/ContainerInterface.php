<?php

declare(strict_types=1);

namespace Toporia\Framework\Container\Contracts;

use Toporia\Framework\Container\Exception\{ContainerException, NotFoundException};


/**
 * Interface ContainerInterface
 *
 * Contract defining the interface for ContainerInterface implementations
 * in the Dependency Injection container layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Container\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws NotFoundException  No entry was found for this identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Bind a service factory to the container.
     *
     * @param string $id Service identifier.
     * @param callable|string|null $concrete Concrete implementation (null = auto-bind to $id).
     * @param bool $shared Whether the service should be shared (singleton).
     * @return void
     */
    public function bind(string $id, callable|string|null $concrete = null, bool $shared = false): void;

    /**
     * Bind a singleton service to the container.
     * The service will be created once and reused on subsequent calls.
     *
     * @param string $id Service identifier.
     * @param callable|string|null $concrete Concrete implementation (null = auto-bind to $id).
     * @return void
     */
    public function singleton(string $id, callable|string|null $concrete = null): void;

    /**
     * Register an existing instance as a singleton.
     *
     * @param string $id Service identifier.
     * @param mixed $instance The service instance.
     * @return void
     */
    public function instance(string $id, mixed $instance): void;

    /**
     * Resolve and call a callable with dependency injection.
     *
     * @param callable|array|string $callable The callable to invoke.
     * @param array $parameters Additional parameters to pass.
     * @return mixed The result of the callable.
     * @throws ContainerException
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed;
}
