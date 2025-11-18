<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Contracts\Transformer\TransformerInterface;
use App\Infrastructure\Transformer\TransformerManager;
use App\Infrastructure\Transformer\UserTransformer;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * Transformer Service Provider
 *
 * Registers transformer components in the dependency injection container.
 *
 * Clean Architecture:
 * - Application layer service provider
 * - Binds Domain interfaces to Infrastructure implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Registers transformer services
 * - Dependency Inversion: Binds abstractions to implementations
 *
 * Performance:
 * - Singleton bindings for O(1) resolution
 * - Lazy loading
 */
final class TransformerServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register transformers as singletons
        $container->singleton(
            UserTransformer::class,
            function (ContainerInterface $container) {
                $cache = $container->has('cache') ? $container->get('cache') : null;
                return new UserTransformer($cache, true, 3600);
            }
        );

        // Register transformer manager
        $container->singleton(
            TransformerManager::class,
            fn(ContainerInterface $container) => new TransformerManager($container)
        );

        // Alias for convenience
        $container->alias('transformer.manager', TransformerManager::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Register additional transformers if needed
    }
}

