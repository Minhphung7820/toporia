<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Event;

use Toporia\Framework\Console\Command;

final class EventClearCommand extends Command
{
    protected string $signature = 'event:clear';

    protected string $description = 'Clear all cached events and listeners';

    public function handle(): int
    {
        $cachePath = $this->getCachePath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->success('Cached events cleared.');
        } else {
            $this->info('No event cache to clear.');
        }

        return 0;
    }

    private function getCachePath(): string
    {
        $basePath = defined('APP_BASE_PATH') ? constant('APP_BASE_PATH') : (getcwd() ?: dirname(__DIR__, 5));
        return $basePath . '/bootstrap/cache/events.php';
    }
}
