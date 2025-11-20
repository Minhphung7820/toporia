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
 * This eliminates dependency order issues between multiple providers.
 *
 * Clean Architecture Benefits:
 * - Single Responsibility: One provider for domain layer
 * - Dependency Graph: Dependencies resolved in correct order automatically
 * - Performance: All domain services registered in one pass
 * - Maintainability: Easy to see all domain dependencies in one place
 *
 * SOLID Principles:
 * - S: Only handles domain service registration
 * - O: Can be extended without modifying existing code
 * - L: All bindings follow same contract pattern
 * - I: Minimal interface (just register/boot)
 * - D: Depends on abstractions (ContainerInterface)
 *
 * Performance:
 * - O(1) registration per service
 * - Lazy resolution (resolved when first used)
 * - Singleton pattern (shared instances)
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // =====================================================================
        // Infrastructure Layer - Database & Persistence
        // =====================================================================

        // Transaction Manager - Required by UnitOfWork
        $container->singleton(TransactionManager::class, function (ContainerInterface $c) {
            return new TransactionManager($c->get(ConnectionInterface::class));
        });

        // Unit of Work - Depends on TransactionManager
        $container->singleton(UnitOfWork::class, function (ContainerInterface $c) {
            return new UnitOfWork($c->get(TransactionManager::class));
        });

        // =====================================================================
        // Domain Layer - Repositories
        // =====================================================================

        // User Repository - Core domain repository
        // Uses PdoUserRepository for production database persistence
        $container->singleton(UserRepository::class, function () {
            return new PdoUserRepository();
        });

        // =====================================================================
        // Infrastructure Layer - Auth
        // =====================================================================

        // User Provider - Depends on UserRepository
        // Bridges authentication system with domain layer
        $container->singleton(UserProviderInterface::class, function (ContainerInterface $c) {
            return new RepositoryUserProvider($c->get(UserRepository::class));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Boot logic here if needed (e.g., register observers, load translations)
    }
}
