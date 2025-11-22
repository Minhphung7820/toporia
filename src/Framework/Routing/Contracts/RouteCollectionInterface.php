<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing\Contracts;


/**
 * Interface RouteCollectionInterface
 *
 * Contract defining the interface for RouteCollectionInterface
 * implementations in the HTTP routing and URL generation layer of the
 * Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Routing\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
interface RouteCollectionInterface
{
    /**
     * Add a route to the collection.
     *
     * @param RouteInterface $route
     * @return void
     */
    public function add(RouteInterface $route): void;

    /**
     * Find a route matching the given method and URI.
     *
     * @param string $method HTTP method.
     * @param string $uri Request URI.
     * @return array{route: RouteInterface, parameters: array}|null
     */
    public function match(string $method, string $uri): ?array;

    /**
     * Get a route by its name.
     *
     * @param string $name Route name.
     * @return RouteInterface|null
     */
    public function getByName(string $name): ?RouteInterface;

    /**
     * Get all routes.
     *
     * @return array<RouteInterface>
     */
    public function all(): array;
}
