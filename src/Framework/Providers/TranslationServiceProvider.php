<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Translation\{Contracts\LoaderInterface, Contracts\TranslatorInterface, Loaders\FileLoader, Translator};

/**
 * Translation Service Provider
 *
 * Registers translation services in the container.
 *
 * Clean Architecture:
 * - Framework layer service provider
 * - Registers core translation services
 *
 * SOLID Principles:
 * - Single Responsibility: Only registers translation services
 * - Dependency Inversion: Registers interfaces, not implementations
 */
final class TranslationServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register translation loader
        $container->singleton(LoaderInterface::class, function (ContainerInterface $c) {
            $app = $c->get('app');
            $config = $c->get('config');

            // Get translation path from config, fallback to default
            $path = $config->get('translation.path');
            if ($path === null) {
                $path = $app->path('resources/lang');
            }

            // Get cache if available
            $cache = $c->has('cache') ? $c->get('cache') : null;

            return new FileLoader($path, $cache);
        });

        // Register translator
        $container->singleton(TranslatorInterface::class, function (ContainerInterface $c) {
            $config = $c->get('config');
            $loader = $c->get(LoaderInterface::class);

            // Get locale from config, fallback to 'en'
            $locale = $config->get('app.locale');
            if ($locale === null) {
                $locale = 'en';
            }

            // Get fallback locale from config, fallback to 'en'
            $fallback = $config->get('translation.fallback');
            if ($fallback === null) {
                $fallback = 'en';
            }

            return new Translator($loader, $locale, $fallback);
        });

        // Register as 'translation' and 'translator' aliases for convenience
        $container->singleton('translation', fn($c) => $c->get(TranslatorInterface::class));
        $container->singleton('translator', fn($c) => $c->get(TranslatorInterface::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Set locale from config if available
        $config = $container->get('config');
        $locale = $config->get('app.locale', 'en');

        if ($container->has(TranslatorInterface::class)) {
            $translator = $container->get(TranslatorInterface::class);
            $translator->setLocale($locale);
        }
    }
}

