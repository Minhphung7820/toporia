<?php

declare(strict_types=1);

namespace App\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Database\ConnectionInterface;
use Toporia\Framework\Cache\CacheInterface;
use App\Domain\Product\ProductRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Repository\EloquentProductRepository;
use App\Infrastructure\Repository\Transaction\TransactionManager;
use App\Infrastructure\Repository\UnitOfWork;
use App\Infrastructure\Persistence\InMemoryUserRepository;

/**
 * Repository Service Provider
 *
 * Binds repository interfaces to their implementations.
 * This is where you configure which repository implementation to use.
 *
 * Clean Architecture:
 * - Binds Domain interfaces (ProductRepository, UserRepository) to Infrastructure implementations
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
            $uow = new UnitOfWork($c->get(TransactionManager::class));
            // Register repositories with Unit of Work
            $uow->register($c->get(ProductRepository::class));
            return $uow;
        });

        // Bind ProductRepository to Eloquent implementation (database-backed)
        // This uses BaseRepository for common functionality
        $container->singleton(ProductRepository::class, function (ContainerInterface $c) {
            return new EloquentProductRepository(
                $c->get(ConnectionInterface::class),
                $c->has(CacheInterface::class) ? $c->get(CacheInterface::class) : null
            );
        });

        // Bind UserRepository to InMemory implementation (for now)
        // TODO: Replace with EloquentUserRepository for production
        $container->singleton(UserRepository::class, fn() => new InMemoryUserRepository());

        // Alternative implementations (commented out):
        //
        // Use in-memory implementation (for testing)
        // $container->bind(ProductRepository::class, fn() => new InMemoryProductRepository());
        //
        // Use PDO repository (custom SQL)
        // $container->bind(ProductRepository::class, function(ContainerInterface $c) {
        //     return new PdoProductRepository($c->get('db'));
        // });
        //
        // Use Cached repository (decorator pattern)
        // $container->bind(ProductRepository::class, function(ContainerInterface $c) {
        //     $baseRepo = new EloquentProductRepository(
        //         $c->get(ConnectionInterface::class)
        //     );
        //     return new CachedProductRepository($baseRepo, $c->get(CacheInterface::class));
        // });
    }
}
