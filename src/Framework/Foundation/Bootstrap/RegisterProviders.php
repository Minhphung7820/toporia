<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation\Bootstrap;

use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Foundation\FrameworkServiceProvider;

/**
 * Register Providers
 *
 * Registers all framework and application service providers.
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles provider registration
 * - Mid execution: Called after configuration is loaded
 * - Performance: O(P) where P = number of providers
 */
final class RegisterProviders
{
    /**
     * Bootstrap provider registration.
     *
     * @param Application $app Application instance
     * @return void
     */
    public static function bootstrap(Application $app): void
    {
        $app->registerProviders([
            // Framework providers (auto-loaded from FrameworkServiceProvider)
            ...FrameworkServiceProvider::providers(),

            // Application providers
            // DomainServiceProvider: Registers all domain-level services (Repositories, UnitOfWork, Auth)
            // This provider manages dependency order internally, eliminating inter-provider dependencies
            \App\Infrastructure\Providers\DomainServiceProvider::class,

            // Application-level services (Business logic, Kafka, etc.)
            \App\Infrastructure\Providers\AppServiceProvider::class,

            // Infrastructure services
            \App\Infrastructure\Providers\EventServiceProvider::class,
            \App\Infrastructure\Providers\RouteServiceProvider::class,
            \App\Infrastructure\Providers\ScheduleServiceProvider::class,
        ]);
    }
}

