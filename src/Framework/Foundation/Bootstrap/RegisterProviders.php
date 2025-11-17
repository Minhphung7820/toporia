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
            \App\Infrastructure\Providers\AppServiceProvider::class,
            \App\Infrastructure\Providers\RepositoryServiceProvider::class,
            \App\Infrastructure\Providers\EventServiceProvider::class,
            \App\Infrastructure\Providers\RouteServiceProvider::class,
            \App\Infrastructure\Providers\ScheduleServiceProvider::class,
        ]);
    }
}

