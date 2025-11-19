<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Event;

use Toporia\Framework\Console\Command;

final class EventListCommand extends Command
{
    protected string $signature = 'event:list {--event= : Filter the events by name}';

    protected string $description = 'List all registered events and their listeners';

    public function handle(): int
    {
        $this->info('Listing registered events and listeners...');
        $this->info('This command requires EventDispatcher to be configured.');

        return 0;
    }
}
