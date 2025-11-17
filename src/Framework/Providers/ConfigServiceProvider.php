<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Config\Repository;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\{Application, ServiceProvider};

/**
 * Configuration Service Provider
 *
 * Loads and registers configuration files.
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // If config is already loaded (by LoadConfiguration), use it
        if ($container->has('config')) {
            return;
        }

        // Otherwise, load it now (backward compatibility)
        $container->singleton(Repository::class, function (ContainerInterface $c) {
            /** @var Application $app */
            $app = $c->get(Application::class);

            $config = new Repository();

            // Load all config files from config directory
            $configPath = $app->path('config');
            if (is_dir($configPath)) {
                $config->loadDirectory($configPath);
            }

            return $config;
        });

        $container->singleton('config', fn(ContainerInterface $c) => $c->get(Repository::class));
    }
}
