<?php

declare(strict_types=1);

namespace Toporia\Framework\Socialite\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Socialite\SocialiteManager;
use Toporia\Framework\Http\Contracts\HttpClientInterface;

/**
 * Socialite Service Provider
 *
 * Registers socialite services.
 */
final class SocialiteServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register Socialite Manager
        $container->singleton(SocialiteManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('socialite', [])
                : [];

            return new SocialiteManager(
                $c,
                $c->get(HttpClientInterface::class),
                $config
            );
        });

        // Bind alias
        $container->bind('socialite', fn($c) => $c->get(SocialiteManager::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Socialite services are ready
    }
}

