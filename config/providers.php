<?php

declare(strict_types=1);

/**
 * Application Service Providers Configuration
 *
 * Register your application service providers here.
 * These providers are loaded AFTER framework and package providers.
 *
 * Loading Order:
 * 1. Framework providers (core framework services) - automatic
 * 2. Auto-discovered package providers (from packages/ and vendor/) - automatic
 * 3. Application providers (defined below) - your business logic
 *
 * Lazy Loading:
 * All providers support lazy loading. The register() method is called during bootstrap,
 * but expensive operations should be deferred to boot() method which is called only
 * when the service is actually needed.
 *
 * Best Practices:
 * - Keep providers focused (Single Responsibility)
 * - Use boot() for operations that depend on other services
 * - Group related bindings in the same provider
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Service Providers
    |--------------------------------------------------------------------------
    |
    | The providers listed here will be automatically loaded by the framework.
    | Order matters - providers are loaded in the order listed below.
    |
    | Note: DomainServiceProvider MUST be first because other providers depend
    | on the repository bindings it provides.
    |
    */

    'providers' => [
        // Domain Layer - Repositories, Auth, UnitOfWork
        // MUST be first because other providers depend on it
        App\Infrastructure\Providers\DomainServiceProvider::class,

        // Application Layer - Business logic services (Kafka, CSRF, etc.)
        App\Infrastructure\Providers\AppServiceProvider::class,

        // Blog Platform - Blog repositories and services
        App\Infrastructure\Providers\BlogServiceProvider::class,

        // Infrastructure Layer - Events, Routes, Schedules
        App\Infrastructure\Providers\EventServiceProvider::class,
        App\Infrastructure\Providers\RouteServiceProvider::class,
        App\Infrastructure\Providers\ScheduleServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred Service Providers
    |--------------------------------------------------------------------------
    |
    | Providers loaded only when their services are requested.
    | Format: ServiceInterface::class => ServiceProvider::class
    |
    */

    'deferred' => [
        // Add deferred providers here when needed
    ],

];
