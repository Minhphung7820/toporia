<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use Toporia\Framework\Config\Repository;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Load Configuration
 *
 * Loads all configuration files from config directory into the container.
 * This should be called early in the bootstrap process, right after
 * Application instance is created.
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles configuration loading
 * - Performance: Lazy loading, cached after first load
 * - Early execution: Called before service providers register
 *
 * Performance: O(N) where N = number of config files
 */
final class LoadConfiguration
{
    /**
     * Bootstrap configuration loading.
     *
     * Loads all config files and registers them in the container.
     *
     * @param Application $app Application instance
     * @return void
     */
    public static function bootstrap(Application $app): void
    {
        $container = $app->getContainer();

        // Only load if not already loaded
        if ($container->has('config')) {
            return;
        }

        // Create config repository
        $cachePath = $app->path('storage/framework/config.php');
        $config = new Repository([], $cachePath);

        // Set config directory for lazy loading
        // Config files will be loaded on first access (lazy loading)
        // Or from cache if available
        $configPath = $app->path('config');
        if (is_dir($configPath)) {
            $config->loadDirectory($configPath, eager: false); // Lazy load or load from cache
        }

        // Register in container as singleton
        $container->singleton(Repository::class, fn() => $config);
        $container->singleton('config', fn(ContainerInterface $c) => $c->get(Repository::class));
    }
}
