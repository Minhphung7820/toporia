<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation\Contracts;

use Toporia\Framework\Container\Contracts\ContainerInterface;


/**
 * Interface ServiceProviderInterface
 *
 * Contract defining the interface for ServiceProviderInterface
 * implementations in the Application foundation and bootstrapping layer of
 * the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Foundation\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the container.
     *
     * This method is called during the bootstrap process to bind services.
     * You should only bind services here, not resolve them.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap services after all providers are registered.
     *
     * This method is called after all providers have registered their services.
     * You can safely resolve services from the container here.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}
