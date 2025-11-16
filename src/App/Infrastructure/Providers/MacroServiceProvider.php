<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Toporia\Framework\Macro\Contracts\MacroRegistryInterface;
use App\Infrastructure\Macro\MacroRegistry;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * Macro Service Provider
 *
 * Registers macro system components in the dependency injection container.
 *
 * Clean Architecture:
 * - Application layer service provider
 * - Binds Domain interfaces to Infrastructure implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Registers macro services
 * - Dependency Inversion: Binds abstractions to implementations
 *
 * Performance:
 * - Singleton bindings for O(1) resolution
 * - Lazy loading
 */
final class MacroServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Bind MacroRegistryInterface to MacroRegistry implementation
        $container->singleton(
            MacroRegistryInterface::class,
            function (ContainerInterface $container) {
                $cache = $container->has('cache')
                    ? $container->get('cache')
                    : null;
                return new MacroRegistry($cache);
            }
        );

        // Alias for convenience
        $container->alias('macro.registry', MacroRegistryInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Register default macros if needed
        // Example:
        // $registry = $container->get(MacroRegistryInterface::class);
        // $registry->register(Collection::class, 'toUpper', fn($items) => ...);
    }
}
