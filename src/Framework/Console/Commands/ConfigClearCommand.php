<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Foundation\Application;

/**
 * Config Clear Command
 *
 * Clear compiled configuration cache.
 *
 * Usage:
 *   php console config:clear
 *
 * Architecture:
 * - Single Responsibility: Config cache clearing only
 * - Clean Architecture: Framework layer
 */
final class ConfigClearCommand extends Command
{
    protected string $signature = 'config:clear';
    protected string $description = 'Clear configuration cache';

    public function __construct(
        private readonly Application $app
    ) {}

    public function handle(): int
    {
        $cachePath = $this->app->path('storage/framework/config.php');

        if (!file_exists($cachePath)) {
            $this->info('Configuration cache is already clear.');
            return 0;
        }

        try {
            @unlink($cachePath);
            $this->success('Configuration cache cleared successfully!');
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to clear configuration cache: {$e->getMessage()}");
            return 1;
        }
    }
}
