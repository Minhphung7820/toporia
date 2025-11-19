<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\App;

use Toporia\Framework\Console\Command;

final class UpCommand extends Command
{
    protected string $signature = 'up';

    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        $maintenancePath = $this->getBasePath() . '/storage/framework/down';

        if (!file_exists($maintenancePath)) {
            $this->info('Application is already up.');
            return 0;
        }

        unlink($maintenancePath);

        $this->success('Application is now live.');

        return 0;
    }

    private function getBasePath(): string
    {
        if (defined('APP_BASE_PATH')) {
            return constant('APP_BASE_PATH');
        }

        return getcwd() ?: dirname(__DIR__, 5);
    }
}
