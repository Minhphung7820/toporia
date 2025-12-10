<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Console\Commands\RabbitMq\Base\AbstractBatchRabbitMqConsumer;
use Toporia\Framework\Realtime\Brokers\RabbitMqBroker;
use Toporia\Framework\Realtime\Contracts\{MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Support\Collection\Collection;

/**
 * Realtime RabbitMQ Consumer Command
 *
 * Consume messages from RabbitMQ for realtime communication.
 * Runs as a long-lived process, consuming messages and broadcasting to local connections.
 *
 * Performance Optimizations:
 * - Batch processing for high throughput (configurable batch size)
 * - Prefetch control for flow control
 * - Graceful shutdown with signal handling
 * - Automatic reconnection on connection loss
 * - Memory-efficient message processing
 * - Durable message queues (guaranteed delivery)
 *
 * Usage:
 *   php console realtime:rabbitmq                           # Subscribe to all channels (routing key: #)
 *   php console realtime:rabbitmq --all                     # Subscribe to all channels
 *   php console realtime:rabbitmq --routing-key=user.*      # Subscribe using routing key pattern
 *   php console realtime:rabbitmq --channels=ch1,ch2        # Subscribe to specific channels
 *   php console realtime:rabbitmq --batch-size=100 --timeout=1000
 *   php console realtime:rabbitmq --max-messages=10000
 *
 * Options:
 *   --broker=name          RabbitMQ broker name from config (default: rabbitmq)
 *   --channels=ch1,ch2     Comma-separated list of channels to subscribe
 *   --batch-size=N         Messages per batch (default: 100)
 *   --timeout=N            Poll timeout in milliseconds (default: 1000)
 *   --max-messages=N       Maximum messages to process before exit (0 = unlimited)
 *   --stop-when-empty      Stop when no messages available (testing)
 *
 * Architecture:
 * - Subscribes to RabbitMQ queues (one per channel via routing keys)
 * - Consumes messages in batches for performance
 * - Broadcasts messages to local RealtimeManager
 * - Supports graceful shutdown (SIGTERM, SIGINT)
 * - Durable queues ensure message delivery
 *
 * SOLID Principles:
 * - Single Responsibility: Only consumes RabbitMQ messages
 * - Open/Closed: Extensible via broker configuration
 * - Dependency Inversion: Depends on BrokerInterface
 *
 * @package Toporia\Framework\Console\Commands
 */
/**
 * Class RealtimeRabbitMqConsumerCommand
 *
 * Consume messages from RabbitMQ for realtime broadcasting.
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
final class RealtimeRabbitMqConsumerCommand extends AbstractBatchRabbitMqConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'realtime:rabbitmq {--broker=rabbitmq} {--channels=*} {--routing-key=} {--all} {--batch-size=100} {--timeout=1000} {--max-messages=0} {--stop-when-empty}';

    protected string $description = 'Consume messages from RabbitMQ for realtime communication';

    /**
     * @var array<string> Channels to subscribe to
     */
    private array $channels = [];

    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(
        RealtimeManagerInterface $realtime
    ) {
        parent::__construct($realtime);
    }

    /**
     * {@inheritdoc}
     */
    protected function getChannels(): array
    {
        if (!empty($this->channels)) {
            return $this->channels;
        }

        // Parse from options
        $channelsOption = $this->option('channels', []);
        $this->channels = $this->parseChannels($channelsOption);

        return $this->channels;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchSizeLimit(): int
    {
        return (int) $this->option('batch-size', 100);
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchReleaseInterval(): int
    {
        return (int) config('rabbitmq.batch_release_interval', 1500); // 1.5 seconds
    }

    /**
     * @var string|null Routing key for wildcard subscription
     */
    private ?string $routingKey = null;

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Check for --all flag or --routing-key option
            $subscribeAll = $this->option('all', false);
            $this->routingKey = $this->option('routing-key');

            // Parse channels
            $channelsOption = $this->option('channels', []);
            $this->channels = $this->parseChannels($channelsOption);

            // Determine subscription mode
            if (empty($this->channels) && !$this->routingKey && !$subscribeAll) {
                $this->info('No channels specified. Using wildcard routing key (#) to subscribe to ALL channels.');
                $this->info('');
                $this->info('Usage options:');
                $this->info('  --all                    Subscribe to all channels (routing key: #)');
                $this->info('  --routing-key=user.*     Subscribe using routing key pattern');
                $this->info('  --channels=ch1,ch2       Subscribe to specific channels');
                $this->info('');
                $this->info('Running with --all mode...');
                $subscribeAll = true;
            }

            // Use wildcard routing key if --all
            if ($subscribeAll && empty($this->channels)) {
                $this->routingKey = '#'; // RabbitMQ wildcard for all
            }

            // Override parent handle to customize for multiple channels
            return $this->handleMultipleChannels();
        } catch (\Throwable $e) {
            $this->error("Consumer crashed: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Handle multiple channels consumption.
     *
     * @return int
     */
    private function handleMultipleChannels(): int
    {
        $broker = $this->getBroker();

        if (!$broker instanceof RabbitMqBroker) {
            $this->error("Broker is not a RabbitMQ broker. Got: " . get_class($broker));
            return 1;
        }

        $batchSize = $this->getBatchSizeLimit();
        $timeout = (int) $this->option('timeout', 1000);

        // Build subscription info
        $subscriptionInfo = $this->routingKey
            ? "routing key: {$this->routingKey}"
            : implode(', ', $this->channels);

        // Display header
        $this->displayHeader('Realtime Batch Consumer', [
            'broker' => $this->getBrokerName(),
            'mode' => $this->routingKey ? 'routing-key' : 'channels',
            'subscription' => $subscriptionInfo,
            'batch_size' => $batchSize,
        ]);

        // Setup graceful shutdown
        $this->setupSignalHandlers(function () use ($broker) {
            $broker->stopConsuming();
        });

        // Subscribe based on mode
        if ($this->routingKey) {
            // Use routing key pattern subscription
            $broker->subscribeWithRoutingKey($this->routingKey, function (MessageInterface $message, string $channel) {
                // Messages will be collected in batches
            });
            $this->line("Subscribed with routing key: <info>{$this->routingKey}</info>");
        } else {
            // Subscribe to specific channels
            foreach ($this->channels as $channel) {
                $broker->subscribe($channel, function (MessageInterface $message) use ($channel) {
                    // Messages will be collected in batches
                    // This callback is called by RabbitMqBroker's consume loop
                });
                $this->line("Subscribed to channel: <info>{$channel}</info>");
            }
        }

        // Start consuming
        $channelsOrKey = $this->routingKey ? [$this->routingKey] : $this->channels;
        $maxMessages = (int) $this->option('max-messages', 0);
        if ($maxMessages > 0) {
            $this->consumeBatchesWithLimit($broker, $channelsOrKey, $timeout, $batchSize, $maxMessages);
        } else {
            $this->consumeBatches($broker, $channelsOrKey, $timeout, $batchSize);
        }

        // Display summary
        $this->displaySummary();

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * Handle a batch of messages from RabbitMQ.
     * Broadcasts each message to local RealtimeManager.
     */
    public function handleMessages(Collection $messages): void
    {
        foreach ($messages as $item) {
            try {
                /** @var MessageInterface $message */
                $message = $item['message'] ?? null;
                $metadata = $item['metadata'] ?? [];

                if (!$message instanceof MessageInterface) {
                    continue;
                }

                // Extract channel from metadata or message
                $channel = $metadata['channel'] ?? $message->getChannel() ?? $this->channels[0] ?? 'default';

                // Broadcast locally only (do NOT publish to broker again to prevent loop)
                // This message came from broker, so we only broadcast to local clients
                if ($message->getEvent() && $message->getData() !== null) {
                    $this->realtime->broadcastLocal(
                        $channel,
                        $message->getEvent(),
                        $message->getData()
                    );
                }
            } catch (\Throwable $e) {
                $this->logError($e, [
                    'message_id' => $message->getId() ?? 'unknown',
                    'channel' => $channel ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Parse channels from options.
     *
     * Supports:
     * - Single value: --channels=ch1,ch2,ch3
     * - Multiple values: --channels=ch1 --channels=ch2
     *
     * @param array|string $channelsOption Channel option value
     * @return array<string> Channel names
     */
    private function parseChannels(array|string $channelsOption): array
    {
        if (is_string($channelsOption)) {
            $channelsOption = [$channelsOption];
        }

        $channels = [];

        foreach ($channelsOption as $value) {
            // Split comma-separated values
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $channels[] = $part;
                }
            }
        }

        return array_unique($channels);
    }
}

