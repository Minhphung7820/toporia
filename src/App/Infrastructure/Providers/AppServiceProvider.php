<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Contracts\Services\TopicServiceInterface;
use App\Domain\Contracts\Services\HealthCheckerInterface;
use App\Domain\Contracts\Services\ClusterFixerInterface;
use App\Infrastructure\Services\Kafka\KafkaTopicService;
use App\Infrastructure\Services\Kafka\KafkaHealthChecker;
use App\Infrastructure\Services\Kafka\KafkaClusterIdFixer;
use App\Presentation\Console\Kernel;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\RateLimit\{RateLimiter, Limit};
use Toporia\Framework\Http\Request;

/**
 * Application Service Provider
 *
 * Register core application-level services here.
 *
 * Keep this provider focused on application business logic services.
 * Framework-level services (Auth, Events, etc.) should be in Framework providers.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // NOTE: UserRepository and UserProvider are now registered in DomainServiceProvider
        // This eliminates inter-provider dependency issues

        // Kafka Services - Register domain interfaces with infrastructure implementations
        $container->singleton(HealthCheckerInterface::class, function (ContainerInterface $c) {
            return new KafkaHealthChecker($c->get(RealtimeManager::class));
        });

        $container->singleton(ClusterFixerInterface::class, fn() => new KafkaClusterIdFixer());

        $container->singleton(TopicServiceInterface::class, function (ContainerInterface $c) {
            return new KafkaTopicService(
                $c->get(RealtimeManager::class),
                $c->get(HealthCheckerInterface::class),
                $c->get(ClusterFixerInterface::class),
                config('kafka', [])
            );
        });

        // Register console kernel bootstrap callback
        $container->singleton('console.kernel.bootstrap', function (ContainerInterface $c) {
            return function ($application) {
                $kernel = new Kernel();
                $kernel->bootstrap($application);
            };
        });

        // Register Application Services (auto-wired via constructor injection)
        // Services are resolved automatically by container, no need to register explicitly
        // But we can register them as singletons for performance if needed

        // Auth Services - Auto-wired, but register as singletons for performance
        $container->singleton(\App\Application\Services\RegisterUserService::class);
        $container->singleton(\App\Application\Services\LoginService::class);
        $container->singleton(\App\Application\Services\ForgotPasswordService::class);
        $container->singleton(\App\Application\Services\ResetPasswordService::class);
        $container->singleton(\App\Application\Services\ChangePasswordService::class);
        // Issue CSRF Cookie Service - inject production flag
        $container->singleton(\App\Application\Services\IssueCsrfCookieService::class, function ($c) {
            $isProduction = env('APP_ENV') === 'production';
            return new \App\Application\Services\IssueCsrfCookieService(
                $c->get(\Toporia\Framework\Security\Contracts\CsrfTokenManagerInterface::class),
                $isProduction
            );
        });
    }

    /**
     * {@inheritdoc}
     *
     * Boot application services.
     * Register named rate limiters here (similar to Laravel).
     */
    public function boot(ContainerInterface $container): void
    {
        // Set RateLimiter instance for named limiters
        $limiter = $container->get(\Toporia\Framework\RateLimit\Contracts\RateLimiterInterface::class);
        RateLimiter::setLimiter($limiter);

        // Register named rate limiters
        // Example: API rate limit per user (100 requests per minute)
        RateLimiter::for('api-per-user', function (Request $request) {
            return Limit::perMinute(100)->by(
                $request->user()?->getId() ?? $request->ip()
            );
        });

        // Example: API rate limit (20 requests per 2 minutes)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Example: Strict rate limit for sensitive endpoints
        RateLimiter::for('strict', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Example: High-volume rate limit (1000 requests per hour)
        RateLimiter::for('high-volume', function (Request $request) {
            return Limit::perHour(1000)->by(
                $request->user()?->getId() ?? $request->ip()
            );
        });
    }
}
