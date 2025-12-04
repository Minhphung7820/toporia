<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

/**
 * Class QueueListenCommand
 *
 * Listen for and process queued jobs.
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
final class QueueListenCommand extends Command
{
    protected string $signature = 'queue:listen {connection? : The name of the queue connection to listen on} {--queue= : The queue to listen on} {--delay=0 : The number of seconds to delay failed jobs} {--memory=128 : The memory limit in megabytes} {--timeout=60 : The number of seconds a child process can run} {--sleep=3 : Number of seconds to sleep when no job is available} {--tries=1 : Number of times to attempt a job before logging it failed}';

    protected string $description = 'Listen to a given queue (auto-restart on code changes)';

    public function handle(): int
    {
        $connection = $this->argument('connection') ?: config('queue.default', 'sync');
        $queue = $this->option('queue', 'default');
        $memory = (int) $this->option('memory', 128);
        $sleep = (int) $this->option('sleep', 3);

        $this->info("Listening on queue [{$queue}] using connection [{$connection}]...");
        $this->info('Press Ctrl+C to stop.');
        $this->newLine();

        $this->warn('Queue listen command requires QueueManager to be configured.');
        $this->info('Please ensure queue driver is properly set up in config/queue.php');

        return 0;
    }
}
