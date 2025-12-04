<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

/**
 * Class QueueMonitorCommand
 *
 * Monitor queue worker status.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console\Commands\Queue
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class QueueMonitorCommand extends Command
{
    protected string $signature = 'queue:monitor {queues? : Comma-separated list of queues to monitor} {--max=1000 : The maximum number of jobs allowed on the queue}';

    protected string $description = 'Monitor the size of the specified queues';

    public function handle(): int
    {
        $queuesInput = $this->argument('queues') ?: 'default';
        $queues = array_map('trim', explode(',', $queuesInput));
        $maxJobs = (int) $this->option('max', 1000);

        $this->info('Monitoring queues: ' . implode(', ', $queues));
        $this->info("Max threshold: {$maxJobs} jobs");
        $this->info('This command requires database to be configured with jobs table.');

        return 0;
    }
}
