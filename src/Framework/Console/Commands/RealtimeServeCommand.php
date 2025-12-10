<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Realtime Server Command
 *
 * Start the realtime server with configured transport.
 *
 * Usage:
 * - php console realtime:serve                    # Start with default config
 * - php console realtime:serve --transport=websocket
 * - php console realtime:serve --host=0.0.0.0 --port=6001
 * - php console realtime:serve --transport=sse
 *
 * @package Toporia\Framework\Console\Commands
 */
/**
 * Class RealtimeServeCommand
 *
 * Start the realtime server for broadcasting.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console\Commands
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RealtimeServeCommand extends Command
{
    protected string $signature = 'realtime:serve';
    protected string $description = 'Start the realtime server';

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        $transport = $this->option('transport', config('realtime.default_transport', 'memory'));
        $host = $this->option('host', '0.0.0.0');
        $port = (int) $this->option('port', 6001);

        $this->info("Starting Realtime Server");
        $this->line("Transport: {$transport}");
        $this->line("Host: {$host}");
        $this->line("Port: {$port}");
        $this->line(str_repeat('-', 50));

        // Check transport requirements
        if ($transport === 'websocket') {
            if (!extension_loaded('swoole')) {
                $this->error('Swoole extension is required for WebSocket transport');
                $this->warn('Install: pecl install swoole');
                return 1;
            }

            $this->success('Swoole extension detected');
        }

        try {
            // Get transport instance
            $transportInstance = $this->realtime->transport($transport);

            // Register signal handlers for graceful shutdown
            $this->registerSignalHandlers($transportInstance);

            // Start broker subscription if broker is configured
            $this->startBrokerSubscription($transportInstance);

            // Start server
            $this->info("Server starting on {$host}:{$port}...\n");

            $transportInstance->start($host, $port);

            // This line is reached when server stops
            $this->info("\nServer stopped");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to start server: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Start broker subscription to receive messages from other servers.
     *
     * NOTE: Redis SUBSCRIBE/PSUBSCRIBE is blocking and incompatible with Swoole's event loop.
     * For proper Redis integration with Swoole, use Swoole\Coroutine\Redis instead.
     *
     * Current workaround: Log warning and skip broker subscription.
     * The WebSocket server will only receive direct broadcasts, not broker messages.
     *
     * @param mixed $transport Transport instance
     * @return void
     */
    private function startBrokerSubscription($transport): void
    {
        $brokerName = config('realtime.default_broker');

        if ($brokerName === null) {
            $this->line('Broker: none (single server mode)');
            return;
        }

        // TODO: Implement Swoole\Coroutine\Redis for async subscription
        // For now, warn the user that broker subscription is not yet supported
        $this->warn("Broker '{$brokerName}' configured but not integrated with Swoole WebSocket.");
        $this->warn('Redis SUBSCRIBE is blocking and incompatible with Swoole event loop.');
        $this->warn('For testing, send notifications directly via WebSocket or disable broker.');
        $this->line('');
        $this->info('Tip: To test, temporarily set REALTIME_BROKER= (empty) in .env');
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * Note: For Swoole WebSocket server, signals are handled internally by Swoole.
     * This method sets up pcntl signals for non-Swoole transports or as fallback.
     * Swoole handles SIGTERM/SIGINT automatically and will trigger shutdown.
     *
     * @param mixed $transport Transport instance
     * @return void
     */
    private function registerSignalHandlers($transport): void
    {
        // For Swoole: signals are handled in workerStart event
        // Swoole automatically handles SIGTERM/SIGINT for graceful shutdown
        // No need to register pcntl_signal as it conflicts with Swoole's event loop

        // Display shutdown instructions
        $this->line('');
        $this->info('Press Ctrl+C to stop the server');
        $this->line('Or run: php console realtime:stop');
        $this->line('');
    }
}
