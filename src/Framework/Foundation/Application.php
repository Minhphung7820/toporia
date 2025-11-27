<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use Toporia\Framework\Foundation\Contracts\ServiceProviderInterface;
use Toporia\Framework\Container\Container;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Support\ReflectionService;


/**
 * Class Application
 *
 * The central application bootstrapper managing dependency injection
 * container, service provider registration, and application boot process
 * following Clean Architecture principles.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Foundation
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
class Application
{
    /**
     * @var ContainerInterface Dependency injection container
     */
    private ContainerInterface $container;

    /**
     * @var array<ServiceProviderInterface> Registered service providers
     */
    private array $providers = [];

    /**
     * @var bool Whether service providers have been booted
     */
    private bool $booted = false;

    /**
     * @param string $basePath Application base path
     * @param ContainerInterface|null $container Optional custom container
     */
    public function __construct(
        private string $basePath,
        ?ContainerInterface $container = null
    ) {
        $this->container = $container ?? new Container();

        // Register the container itself
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance('app', $this);
        $this->container->instance(Application::class, $this);

        // Register core services
        $this->registerCoreServices();
    }

    /**
     * Register a service provider.
     *
     * @param string|ServiceProviderInterface $provider Provider class name or instance
     * @return self
     */
    public function register(string|ServiceProviderInterface $provider): self
    {
        if (is_string($provider)) {
            $provider = new $provider();
        }

        $provider->register($this->container);
        $this->providers[] = $provider;

        // If already booted, boot this provider immediately
        if ($this->booted) {
            $provider->boot($this->container);
        }

        return $this;
    }

    /**
     * Register multiple service providers.
     *
     * @param array<string|ServiceProviderInterface> $providers
     * @return self
     */
    public function registerProviders(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        return $this;
    }

    /**
     * Boot all registered service providers.
     *
     * @return self
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;

        return $this;
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the base path of the application.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get a path relative to the base path.
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Resolve a service from the container.
     *
     * @param string $id
     * @return mixed
     */
    public function make(string $id): mixed
    {
        return $this->container->get($id);
    }

    /**
     * Check if a service exists in the container.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Register core framework services.
     *
     * These services are essential for the framework operation and should
     * be available immediately after application instantiation.
     *
     * Performance: O(1) - Singleton registration
     *
     * @return void
     */
    private function registerCoreServices(): void
    {
        // Register ReflectionService as singleton
        // Only the container should use reflection directly
        $this->container->singleton(ReflectionService::class, function () {
            return new ReflectionService();
        });

        // Register as instance for easier access
        $this->container->instance('reflection', $this->container->make(ReflectionService::class));
    }

    // =========================================================================
    // ENVIRONMENT & CONFIGURATION METHODS
    // =========================================================================

    /**
     * Get the application environment.
     *
     * @return string
     */
    public function environment(): string
    {
        return $this->make('config')->get('app.env', 'production');
    }

    /**
     * Check if the application is in the given environment(s).
     *
     * @param string|array $environments
     * @return bool
     */
    public function isEnvironment(string|array $environments): bool
    {
        $currentEnv = $this->environment();

        if (is_string($environments)) {
            return $currentEnv === $environments;
        }

        return in_array($currentEnv, $environments, true);
    }

    /**
     * Check if the application is in local environment.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->isEnvironment('local');
    }

    /**
     * Check if the application is in development environment.
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->isEnvironment(['local', 'development']);
    }

    /**
     * Check if the application is in production environment.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->isEnvironment('production');
    }

    /**
     * Check if the application is in staging environment.
     *
     * @return bool
     */
    public function isStaging(): bool
    {
        return $this->isEnvironment('staging');
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return (bool) $this->make('config')->get('app.debug', false);
    }

    /**
     * Get the application name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->make('config')->get('app.name', 'Toporia Application');
    }

    /**
     * Get the application version.
     *
     * @return string
     */
    public function version(): string
    {
        return '1.0.0'; // Could be loaded from composer.json or version file
    }

    /**
     * Get the application locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->make('config')->get('app.locale', 'en');
    }

    /**
     * Get the application timezone.
     *
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->make('config')->get('app.timezone', 'UTC');
    }

    // =========================================================================
    // PATH HELPER METHODS
    // =========================================================================

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->path('config' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @return string
     */
    public function databasePath(string $path = ''): string
    {
        return $this->path('database' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the path to the storage directory.
     *
     * @param string $path
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        return $this->path('storage' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the path to the public directory.
     *
     * @param string $path
     * @return string
     */
    public function publicPath(string $path = ''): string
    {
        return $this->path('public' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the path to the resources directory.
     *
     * @param string $path
     * @return string
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->path('resources' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path
     * @return string
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->path('bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return $this->isEnvironment('testing') ||
            defined('PHPUNIT_RUNNING') ||
            class_exists('PHPUnit\\Framework\\TestCase');
    }

    /**
     * Get or check the current application locale.
     *
     * @param string|null $locale
     * @return string|bool
     */
    public function locale(?string $locale = null): string|bool
    {
        if ($locale === null) {
            return $this->getLocale();
        }

        // Set locale logic would go here
        return $this->getLocale() === $locale;
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->booted;
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return 'App\\';
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->providers = [];
        $this->booted = false;

        // Reset container if it supports flushing
        if (method_exists($this->container, 'flush')) {
            $this->container->flush();
        }
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param callable $callback
     * @return $this
     */
    public function terminating(callable $callback): self
    {
        // Store terminating callbacks for later execution
        // This would be implemented when needed
        return $this;
    }
}
