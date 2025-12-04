<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

/**
 * Class QueueFailedCommand
 *
 * List all failed queue jobs.
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
final class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed';

    protected string $description = 'List all of the failed queue jobs';

    public function handle(): int
    {
        $this->info('Listing failed jobs...');
        $this->info('This command requires database to be configured with failed_jobs table.');
        $this->info("Run 'php console queue:failed-table' to create the migration.");

        return 0;
    }
}
