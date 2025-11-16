<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Auth\RepositoryUserProvider;
use Toporia\Framework\Auth\Contracts\UserProviderInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Providers\AuthServiceProvider as FrameworkAuthServiceProvider;

/**
 * Application Auth Service Provider
 *
 * Registers App-specific user provider implementation.
 * Framework will automatically use this via UserProviderInterface from container.
 */
class AuthServiceProvider extends FrameworkAuthServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register parent services first
        parent::register($container);

        // Register UserProviderInterface implementation
        // Framework will get this from container when creating guards
        $container->singleton(UserProviderInterface::class, function (ContainerInterface $c) {
            return new RepositoryUserProvider(
                $c->get(UserRepository::class)
            );
        });
    }
}

