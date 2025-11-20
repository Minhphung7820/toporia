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

            // =====================================================================
            // ACTIVE PROVIDERS
            // =====================================================================

            // Domain Layer - Repositories, Auth, UnitOfWork
            // MUST be first because other providers depend on it
            \App\Infrastructure\Providers\DomainServiceProvider::class,

            // Application Layer - Business logic services (Kafka, CSRF, etc.)
            \App\Infrastructure\Providers\AppServiceProvider::class,

            // Infrastructure Layer - Events, Routes, Schedules
            \App\Infrastructure\Providers\EventServiceProvider::class,
            \App\Infrastructure\Providers\RouteServiceProvider::class,
            \App\Infrastructure\Providers\ScheduleServiceProvider::class,

            // =====================================================================
            // OPTIONAL PROVIDERS (uncomment when needed)
            // =====================================================================

            // API Transformers - For formatting API responses
            // \App\Infrastructure\Providers\TransformerServiceProvider::class,

            // Macro System - For extending framework classes dynamically
            // \App\Infrastructure\Providers\MacroServiceProvider::class,
        ]);
    }
}

