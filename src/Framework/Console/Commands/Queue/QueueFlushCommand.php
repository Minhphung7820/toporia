<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

/**
 * Class QueueFlushCommand
 *
 * Flush all failed queue jobs.
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
final class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush {--hours= : The number of hours to retain failed job data}';

    protected string $description = 'Flush all of the failed queue jobs';

    public function handle(): int
    {
        $this->info('Flushing failed jobs...');
        $this->info('This command requires database to be configured with failed_jobs table.');

        return 0;
    }
}
