<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Realtime;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\{BrokerInterface, MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Realtime\Brokers\RedisBroker;
use Toporia\Framework\Realtime\Brokers\RabbitMqBroker;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Support\Accessors\Log;

/**
 * BrokerConsumeCommand
 *
 * Universal broker consumer for testing.
 * Logs all received messages to console.
 *
 * Usage:
 *   php console broker:consume --driver=redis
 *   php console broker:consume --driver=rabbitmq
 *   php console broker:consume --driver=kafka
 */
final class BrokerConsumeCommand extends Command
{
    protected string $signature = 'broker:consume {--driver=redis : Broker driver (redis, rabbitmq, kafka)} {--channel=* : Channel pattern to subscribe} {--timeout=1000 : Poll timeout in ms}';

    protected string $description = 'Consume messages from broker and log to console (for testing)';

    private bool $running = true;
    private int $messageCount = 0;
    private float $startTime;
    private ?BrokerInterface $currentBroker = null;

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function handle(): int
    {
        $this->startTime = microtime(true);
        $driver = $this->option('driver', 'redis');
        $channelPattern = $this->option('channel', '*');
        $timeout = (int) $this->option('timeout', 1000);

        $this->displayHeader($driver, $channelPattern);

        // Setup signal handlers
        $this->setupSignalHandlers();

        try {
            $broker = $this->realtime->broker($driver);

            if ($broker === null) {
                $this->error("Broker [{$driver}] is not configured or available.");
                return 1;
            }

            // Store broker for signal handler
            $this->currentBroker = $broker;

            $this->success("Connected to {$driver} broker!");
            $this->info("Waiting for messages... (Press Ctrl+C to stop)");
            $this->newLine();

            // Start consuming based on broker type
            match ($driver) {
                'redis' => $this->consumeRedis($broker, $channelPattern, $timeout),
                'rabbitmq' => $this->consumeRabbitMq($broker, $channelPattern, $timeout),
                'kafka' => $this->consumeKafka($broker, $channelPattern, $timeout),
                default => $this->error("Unknown driver: {$driver}")
            };

        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        } finally {
            $this->displaySummary();
        }

        return 0;
    }

    private function consumeRedis(BrokerInterface $broker, string $pattern, int $timeout): void
    {
        if (!$broker instanceof RedisBroker) {
            $this->error("Expected RedisBroker instance");
            return;
        }

        // Subscribe with callback
        $broker->psubscribe($pattern, function (MessageInterface $message, string $channel) {
            $this->logMessage($channel, $message);
        });

        // Start consuming (blocking)
        $broker->consume($timeout, 100);
    }

    private function consumeRabbitMq(BrokerInterface $broker, string $pattern, int $timeout): void
    {
        if (!$broker instanceof RabbitMqBroker) {
            $this->error("Expected RabbitMqBroker instance");
            return;
        }

        // Subscribe with callback
        $broker->subscribe($pattern, function (MessageInterface $message) use ($pattern) {
            $this->logMessage($pattern, $message);
        });

        // Start consuming
        while ($this->running) {
            $broker->consume($timeout, 10);
            usleep(10000); // 10ms sleep
        }
    }

    private function consumeKafka(BrokerInterface $broker, string $pattern, int $timeout): void
    {
        if (!$broker instanceof KafkaBroker) {
            $this->error("Expected KafkaBroker instance");
            return;
        }

        // Get Kafka topics from config
        $defaultTopic = config('kafka.default_topic', 'realtime');
        $topics = [$defaultTopic];

        // Subscribe
        $broker->subscribe($defaultTopic, function (MessageInterface $message) use ($defaultTopic) {
            $this->logMessage($defaultTopic, $message);
        });

        // Start consuming
        while ($this->running) {
            $broker->consume($timeout, 100);
            usleep(10000);
        }
    }

    private function logMessage(string $channel, MessageInterface $message): void
    {
        $this->messageCount++;
        $timestamp = now()->format('Y-m-d H:i:s');

        $data = $message->getData();
        $dataJson = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string) $data;

        // Log to file using Log::info
        Log::info("[Broker Consumer] Message received", [
            'channel' => $channel,
            'event' => $message->getEvent(),
            'id' => $message->getId(),
            'data' => $data,
            'message_count' => $this->messageCount,
        ]);

        // Also output to console
        $this->newLine();
        $this->info("========================================");
        $this->info("[{$timestamp}] Message #{$this->messageCount}");
        $this->info("========================================");
        $this->line("  Channel: {$channel}");
        $this->line("  Event:   {$message->getEvent()}");
        $this->line("  ID:      " . ($message->getId() ?? 'N/A'));
        $this->line("  Data:    {$dataJson}");
        $this->info("----------------------------------------");
    }

    private function displayHeader(string $driver, string $pattern): void
    {
        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║               Broker Consumer (Testing)                      ║");
        $this->info("╠════════════════════════════════════════════════════════════╣");
        $this->info("║ Driver:  " . str_pad($driver, 51) . " ║");
        $this->info("║ Pattern: " . str_pad($pattern, 51) . " ║");
        $this->info("║ Started: " . str_pad(now()->format('Y-m-d H:i:s'), 51) . " ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
        $this->newLine();
    }

    private function displaySummary(): void
    {
        $duration = microtime(true) - $this->startTime;

        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║                     Consumer Summary                         ║");
        $this->info("╠════════════════════════════════════════════════════════════╣");
        $this->info("║ Messages received: " . str_pad((string)$this->messageCount, 41) . " ║");
        $this->info("║ Duration:          " . str_pad(number_format($duration, 2) . "s", 41) . " ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
    }

    private function setupSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->warn('PCNTL extension not available. Use Ctrl+C to stop.');
            return;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        pcntl_signal(SIGTERM, function () {
            $this->shutdown('SIGTERM');
        });

        pcntl_signal(SIGINT, function () {
            $this->shutdown('SIGINT');
        });
    }

    private function shutdown(string $signal): void
    {
        $this->info("\nReceived {$signal}. Shutting down...");
        $this->running = false;

        // Stop broker consuming to exit blocking call
        if ($this->currentBroker !== null) {
            try {
                $this->currentBroker->stopConsuming();
            } catch (\Throwable) {
                // Ignore errors during shutdown
            }
        }

        // Force exit after cleanup
        $this->displaySummary();
        exit(0);
    }
}
