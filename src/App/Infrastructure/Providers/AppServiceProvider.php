<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Contracts\Services\TopicServiceInterface;
use App\Domain\Contracts\Services\HealthCheckerInterface;
use App\Domain\Contracts\Services\ClusterFixerInterface;
use App\Infrastructure\Repository\InMemoryUserRepository;
use App\Infrastructure\Services\Kafka\KafkaTopicService;
use App\Infrastructure\Services\Kafka\KafkaHealthChecker;
use App\Infrastructure\Services\Kafka\KafkaClusterIdFixer;
use App\Presentation\Console\Kernel;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Realtime\RealtimeManager;

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
        // User Repository - Singleton
        // TODO: Replace InMemoryUserRepository with PdoUserRepository for production
        $container->singleton(UserRepository::class, fn() => new InMemoryUserRepository());

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
            return function ($app, $registry) {
                $kernel = new Kernel();
                $kernel->bootstrap($app, $registry);
            };
        });

        // Register other application services here
        // Examples:
        // - Custom services
        // - Business logic handlers
        // - Domain-specific repositories
    }
}
