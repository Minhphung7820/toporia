<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;
use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Repository\Transaction\TransactionManager;
use App\Infrastructure\Repository\UnitOfWork;
use App\Infrastructure\Repository\InMemoryUserRepository;

/**
 * Repository Service Provider
 *
 * Binds repository interfaces to their implementations.
 * This is where you configure which repository implementation to use.
 *
 * Clean Architecture:
 * - Binds Domain interfaces (UserRepository) to Infrastructure implementations
 * - This is the Dependency Inversion in action
 * - Domain layer doesn't know about Infrastructure
 *
 * SOLID Principles:
 * - Dependency Inversion: High-level modules (Domain) depend on abstractions
 * - Open/Closed: Can swap implementations without changing Domain code
 * - Single Responsibility: Each repository handles one entity type
 *
 * Performance:
 * - Repositories are singletons (shared instances)
 * - Cache is injected for query result caching
 * - Connection pooling for database operations
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register Transaction Manager (singleton)
        $container->singleton(TransactionManager::class, function (ContainerInterface $c) {
            return new TransactionManager($c->get(ConnectionInterface::class));
        });

        // Register Unit of Work (singleton)
        $container->singleton(UnitOfWork::class, function (ContainerInterface $c) {
            return new UnitOfWork($c->get(TransactionManager::class));
        });

        // Bind UserRepository to InMemory implementation (for now)
        // TODO: Replace with EloquentUserRepository for production
        $container->singleton(UserRepository::class, fn() => new InMemoryUserRepository());
    }
}
