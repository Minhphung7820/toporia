<?php

declare(strict_types=1);

/**
 * Application Bootstrap Configuration
 *
 * This file bootstraps the application following Clean Architecture principles.
 * Each bootstrap step is handled by a dedicated class for maximum modularity.
 *
 * Bootstrap Order (Optimized):
 * 1. Load Environment Variables    - Required for all configuration
 * 2. Handle Exceptions              - Catch errors early
 * 3. Create Application            - Core framework instance
 * 4. Load Helper Functions         - Required for config files
 * 5. Load Configuration            - Required for service providers
 * 6. Register Facades              - Required for Route facade
 * 7. Register Providers            - Framework and application services
 * 8. Boot Providers                - Initialize services and load routes
 */

/*
|--------------------------------------------------------------------------
| Load Environment Variables
|--------------------------------------------------------------------------
|
| Load environment variables from .env file into $_ENV.
| This MUST be done FIRST, before any other bootstrap steps.
|
*/

\Toporia\Framework\Foundation\LoadEnvironmentVariables::bootstrap(dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Handle Exceptions
|--------------------------------------------------------------------------
|
| Register error and exception handler early to catch all errors.
|
*/

\Toporia\Framework\Foundation\Bootstrap\HandleExceptions::bootstrap();

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Create the application instance which serves as the central hub.
|
*/

$app = new \Toporia\Framework\Foundation\Application(
    basePath: dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Load Helper Functions
|--------------------------------------------------------------------------
|
| Load helper functions BEFORE loading configuration.
| Config files use env() helper function.
|
*/

require __DIR__ . '/helpers.php';

/*
|--------------------------------------------------------------------------
| Load Configuration
|--------------------------------------------------------------------------
|
| Load all configuration files from config directory into container.
|
*/

\Toporia\Framework\Foundation\LoadConfiguration::bootstrap($app);

/*
|--------------------------------------------------------------------------
| Register Facades
|--------------------------------------------------------------------------
|
| Set container for ServiceAccessor system (Route facade, etc.).
|
*/

\Toporia\Framework\Foundation\Bootstrap\RegisterFacades::bootstrap($app);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Register all framework and application service providers.
|
*/

\Toporia\Framework\Foundation\Bootstrap\RegisterProviders::bootstrap($app);

/*
|--------------------------------------------------------------------------
| Boot Service Providers
|--------------------------------------------------------------------------
|
| Boot all registered service providers. Routes are loaded here.
|
*/

\Toporia\Framework\Foundation\Bootstrap\BootProviders::bootstrap($app);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
