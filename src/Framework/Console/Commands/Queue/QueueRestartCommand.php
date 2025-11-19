<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

final class QueueRestartCommand extends Command
{
    protected string $signature = 'queue:restart';

    protected string $description = 'Restart queue worker daemons after their current job';

    public function handle(): int
    {
        $cachePath = $this->getBasePath() . '/storage/framework/cache';

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        file_put_contents($cachePath . '/queue-restart', (string) time());

        $this->success('Broadcasting queue restart signal.');

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
