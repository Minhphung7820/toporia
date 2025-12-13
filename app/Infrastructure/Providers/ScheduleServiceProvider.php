<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Services\Jobs\TestProcess;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Console\Scheduling\Scheduler;

/**
 * Schedule Service Provider
 *
 * Define all scheduled tasks in one place.
 * This provider is loaded automatically and tasks are registered during boot phase.
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Nothing to register
    }

    public function boot(ContainerInterface $container): void
    {
        $scheduler = $container->get(Scheduler::class);

        $this->defineSchedule($scheduler, $container);
    }

    /**
     * Define the application's scheduled tasks
     *
     * Add your scheduled tasks here.
     *
     * @param Scheduler $scheduler
     * @param ContainerInterface $container
     * @return void
     */
    private function defineSchedule(Scheduler $scheduler, ContainerInterface $container): void
    {
        // Daily email notification - runs at 12:30 PM Vietnam time
        $scheduler->command('email:daily --to=tmpdz7820@gmail.com --subject="Toporia Daily Report"')
            ->everyMinutes(10)
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->description('Send daily email notification every 10 minutes');

        // Security: Cleanup expired nonces (hourly)
        // Note: For session-based storage, this gracefully skips in CLI mode
        $scheduler->command('security:cleanup')
            ->hourly()
            ->withoutOverlapping()
            ->description('Cleanup expired security nonces');
    }
}
