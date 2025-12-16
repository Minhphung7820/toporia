<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Auth\Contracts\UserProviderInterface;
use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Repository\PdoUserRepository;
use App\Infrastructure\Repository\Transaction\TransactionManager;
use App\Infrastructure\Repository\UnitOfWork;
use App\Infrastructure\Auth\RepositoryUserProvider;

/**
 * Domain Service Provider
 *
 * Central provider that registers all domain-level services in the correct order.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Transaction Manager
        $container->singleton(TransactionManager::class, function (ContainerInterface $c) {
            return new TransactionManager($c->get(ConnectionInterface::class));
        });

        // Unit of Work
        $container->singleton(UnitOfWork::class, function (ContainerInterface $c) {
            return new UnitOfWork($c->get(TransactionManager::class));
        });

        // User Repository
        $container->singleton(UserRepository::class, function () {
            return new PdoUserRepository();
        });

        // User Provider for authentication
        $container->singleton(UserProviderInterface::class, function (ContainerInterface $c) {
            return new RepositoryUserProvider($c->get(UserRepository::class));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Boot logic here if needed
    }
}
