<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Realtime;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Support\Accessors\Log;

/**
 * BrokerStopCommand
 *
 * Stop running broker consumers by sending SIGTERM signal.
 *
 * Usage:
 *   php console broker:stop              # Stop all broker consumers
 *   php console broker:stop --driver=redis  # Stop only redis consumers
 */
final class BrokerStopCommand extends Command
{
    protected string $signature = 'broker:stop {--driver= : Stop only consumers of specific driver (redis, rabbitmq, kafka)} {--force : Force kill with SIGKILL}';

    protected string $description = 'Stop running broker consumer processes';

    public function handle(): int
    {
        $driver = $this->option('driver');
        $force = $this->option('force', false);

        $this->info("Stopping broker consumers...");

        // Find broker:consume processes
        $pattern = 'broker:consume';
        if ($driver) {
            $pattern .= ".*--driver={$driver}";
        }

        // Get PIDs of running broker:consume processes
        $command = "ps aux | grep -E '[p]hp.*{$pattern}' | awk '{print $2}'";
        $output = shell_exec($command);

        if (empty(trim($output ?? ''))) {
            $this->warn("No running broker consumers found.");
            return 0;
        }

        $pids = array_filter(explode("\n", trim($output)));
        $signal = $force ? SIGKILL : SIGTERM;
        $signalName = $force ? 'SIGKILL' : 'SIGTERM';

        $this->info("Found " . count($pids) . " consumer process(es).");

        $stopped = 0;
        foreach ($pids as $pid) {
            $pid = (int) trim($pid);
            if ($pid <= 0) {
                continue;
            }

            $this->line("  Sending {$signalName} to PID {$pid}...");

            if (posix_kill($pid, $signal)) {
                $stopped++;
                Log::info("[Broker Stop] Sent {$signalName} to consumer", ['pid' => $pid]);
            } else {
                $this->error("  Failed to send signal to PID {$pid}");
            }
        }

        if ($stopped > 0) {
            $this->success("Stopped {$stopped} consumer process(es).");
        }

        return 0;
    }
}
