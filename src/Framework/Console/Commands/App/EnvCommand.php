<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\App;

use Toporia\Framework\Console\Command;

final class EnvCommand extends Command
{
    protected string $signature = 'env';

    protected string $description = 'Display the current framework environment';

    public function handle(): int
    {
        $environment = env('APP_ENV', 'production');

        $this->info("Current application environment: {$environment}");

        return 0;
    }
}
