<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Auth\Contracts\{AuthManagerInterface, TokenRepositoryInterface, UserProviderInterface};
use Toporia\Framework\Auth\Guards\{SanctumGuard, SessionGuard, TokenGuard};
use Toporia\Framework\Auth\Repositories\TokenRepository;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Http\Request;

/**
 * Authentication Service Provider
 *
 * Registers authentication services including:
 * - Auth Manager with multiple guards
 * - User providers
 * - Guard configurations
 *
 * Following Single Responsibility Principle - only handles auth services.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        $this->registerTokenRepository($container);
        $this->registerAuthManager($container);
        $this->registerAuthAlias($container);
    }

    /**
     * Register Auth Manager with guard factories.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerAuthManager(ContainerInterface $container): void
    {
        $container->singleton(AuthManagerInterface::class, function (ContainerInterface $c) {
            $guardFactories = [
                'web' => fn() => $this->createSessionGuard($c, 'web'),
                'api' => fn() => $this->createTokenGuard($c, 'api'),
                'sanctum' => fn() => $this->createSanctumGuard($c),
                // Add more guards here as needed:
                // 'admin' => fn() => $this->createSessionGuard($c, 'admin'),
            ];

            $defaultGuard = $this->getDefaultGuard($c);

            return new AuthManager($guardFactories, $defaultGuard);
        });
    }

    /**
     * Register 'auth' alias for helper function.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerAuthAlias(ContainerInterface $container): void
    {
        $container->singleton('auth', fn(ContainerInterface $c) => $c->get(AuthManagerInterface::class));
    }

    /**
     * Create a session guard instance.
     *
     * Framework gets UserProviderInterface from container (registered by App).
     *
     * @param ContainerInterface $container
     * @param string $name Guard name.
     * @return SessionGuard
     */
    protected function createSessionGuard(ContainerInterface $container, string $name): SessionGuard
    {
        // Get UserProviderInterface from container (App must register it)
        $userProvider = $container->get(UserProviderInterface::class);

        return new SessionGuard($userProvider, $name);
    }

    /**
     * Get default guard name from config.
     *
     * @param ContainerInterface $container
     * @return string
     */
    protected function getDefaultGuard(ContainerInterface $container): string
    {
        try {
            if ($container->has('config')) {
                $config = $container->get('config');
                return $config->get('auth.defaults.guard', 'web');
            }
        } catch (\Throwable $e) {
            // Fall back to 'web'
        }

        return 'web';
    }

    /**
     * Create a token guard instance for API authentication.
     *
     * Framework gets UserProviderInterface from container (registered by App).
     *
     * @param ContainerInterface $container
     * @param string $name Guard name.
     * @return TokenGuard
     */
    protected function createTokenGuard(ContainerInterface $container, string $name): TokenGuard
    {
        // Get UserProviderInterface from container (App must register it)
        $userProvider = $container->get(UserProviderInterface::class);
        $request = $container->get(Request::class);

        return new TokenGuard($userProvider, $request, $name);
    }

    /**
     * Register Token Repository for Sanctum guard.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerTokenRepository(ContainerInterface $container): void
    {
        $container->singleton(TokenRepositoryInterface::class, function (ContainerInterface $c) {
            $connection = $c->has('db') ? $c->get('db') : null;
            $cache = $c->has('cache') ? $c->get('cache') : null;

            if ($connection === null) {
                throw new \RuntimeException('Database connection required for TokenRepository. Please register DatabaseServiceProvider.');
            }

            return new TokenRepository($connection, $cache);
        });

        $container->bind('auth.tokens', fn($c) => $c->get(TokenRepositoryInterface::class));
    }

    /**
     * Create a Sanctum guard instance for API token authentication.
     *
     * Sanctum guard uses database-stored tokens (personal access tokens).
     * Provides token management, scopes, and revocation capabilities.
     *
     * @param ContainerInterface $container
     * @return SanctumGuard
     */
    protected function createSanctumGuard(ContainerInterface $container): SanctumGuard
    {
        $userProvider = $container->get(UserProviderInterface::class);
        $request = $container->get(Request::class);
        $tokens = $container->get(TokenRepositoryInterface::class);

        return new SanctumGuard($request, $userProvider, $tokens);
    }
}
