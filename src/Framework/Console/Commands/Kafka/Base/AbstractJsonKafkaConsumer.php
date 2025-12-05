<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;

use Toporia\Framework\Console\Commands\Kafka\Contracts\SingleMessageHandlerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Message;


/**
 * Abstract Class AbstractJsonKafkaConsumer
 *
 * Abstract base class for AbstractJsonKafkaConsumer implementations in the
 * Base layer providing common functionality and contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Base
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class AbstractJsonKafkaConsumer extends AbstractKafkaConsumer implements SingleMessageHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            $broker = $this->getBroker();
            $topic = $this->getTopic();
            $groupId = $this->getGroupId();
            $offset = $this->getOffset();

            // Validate topic
            if (empty($topic)) {
                $this->error('Topic is required. Override getTopic() method.');
                return 1;
            }

            // Display header
            $this->displayHeader('JSON Consumer', [
                'broker' => $this->getBrokerName(),
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $broker->stopConsuming();
            });

            // Subscribe to topic
            $broker->subscribe($topic, function (MessageInterface $message) {
                $this->processMessage($message);
            });

            // Start consuming
            $timeout = (int) $this->option('timeout', 1000);
            $batchSize = (int) $this->option('batch-size', 1); // Single message for JSON
            $maxMessages = (int) $this->option('max-messages', 0);

            if ($maxMessages > 0) {
                $this->consumeWithLimit($broker, $timeout, $maxMessages);
            } else {
                $broker->consume($timeout, $batchSize);
            }

            // Display summary
            $this->displaySummary();

            return 0;
        } catch (\Throwable $e) {
            $this->error("Consumer crashed: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Process a single message.
     *
     * Wraps handleMessage() with error handling and DLQ support.
     *
     * @param MessageInterface $message Message to process
     * @return void
     */
    protected function processMessage(MessageInterface $message): void
    {
        try {
            // Check max messages limit
            $maxMessages = (int) $this->option('max-messages', 0);
            if ($maxMessages > 0 && $this->processed >= $maxMessages) {
                $this->shouldQuit = true;
                $this->getBroker()->stopConsuming();
                return;
            }

            // Extract metadata (if available from Kafka message)
            $metadata = [
                'topic' => $this->getTopic(),
                'timestamp' => now()->getTimestamp(),
            ];

            // Call handler
            $this->handleMessage($message, $metadata);

            $this->processed++;

            // Display progress (every 100 messages)
            if ($this->processed % 100 === 0) {
                $this->writeln("Processed: <info>{$this->processed}</info> messages");
            }

            // Check shouldQuit after processing
            if ($this->shouldQuit) {
                $this->getBroker()->stopConsuming();
            }
        } catch (\Throwable $e) {
            $this->logError($e, [
                'message_id' => $message->getId(),
                'channel' => $message->getChannel(),
                'event' => $message->getEvent(),
            ]);

            // Send to Dead Letter Queue if configured
            $this->sendToDeadLetterQueue($message, $e);
        }
    }

    /**
     * Consume with message limit.
     *
     * @param KafkaBroker $broker Kafka broker
     * @param int $timeout Poll timeout
     * @param int $maxMessages Maximum messages
     * @return void
     */
    protected function consumeWithLimit(KafkaBroker $broker, int $timeout, int $maxMessages): void
    {
        // This is a simplified version
        // The actual consume() method in KafkaBroker handles the loop
        // We monitor processed count in processMessage()
        $broker->consume($timeout, 1);
    }

    /**
     * Send failed message to Dead Letter Queue.
     *
     * Override getDeadLetterTopic() to enable DLQ support.
     * Messages sent to DLQ include original payload plus error metadata.
     *
     * @param MessageInterface $message Original message that failed
     * @param \Throwable $exception Exception that caused the failure
     * @return void
     */
    protected function sendToDeadLetterQueue(MessageInterface $message, \Throwable $exception): void
    {
        $dlqTopic = $this->getDeadLetterTopic();

        // Skip if DLQ not configured
        if ($dlqTopic === null) {
            return;
        }

        try {
            $broker = $this->getBroker();

            // Create DLQ message with error metadata
            $messageData = $message->getData();
            $dlqPayload = [
                'original_message' => [
                    'id' => $message->getId(),
                    'channel' => $message->getChannel(),
                    'event' => $message->getEvent(),
                    'data' => $messageData,
                ],
                'error' => [
                    'message' => $exception->getMessage(),
                    'class' => get_class($exception),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
                'metadata' => [
                    'original_topic' => $this->getTopic(),
                    'consumer_class' => static::class,
                    'failed_at' => now()->toDateTimeString(),
                    'retry_count' => (is_array($messageData) ? ($messageData['_retry_count'] ?? 0) : 0) + 1,
                ],
            ];

            $dlqMessage = new Message(
                type: 'event',
                channel: $dlqTopic,
                event: 'message.failed',
                data: $dlqPayload
            );

            $broker->publish($dlqTopic, $dlqMessage);

            $this->writeln(sprintf(
                '<comment>Message %s sent to DLQ topic: %s</comment>',
                $message->getId(),
                $dlqTopic
            ));
        } catch (\Throwable $e) {
            // Log DLQ failure but don't throw
            error_log(sprintf(
                'Failed to send message to DLQ: %s (original error: %s)',
                $e->getMessage(),
                $exception->getMessage()
            ));
        }
    }

    /**
     * Get the Dead Letter Queue topic name.
     *
     * Override this method to enable DLQ support.
     * Return null to disable DLQ (default).
     *
     * Example:
     * ```php
     * protected function getDeadLetterTopic(): ?string
     * {
     *     return $this->getTopic() . '.dlq';
     * }
     * ```
     *
     * @return string|null DLQ topic name or null to disable
     */
    protected function getDeadLetterTopic(): ?string
    {
        return null;
    }
}
