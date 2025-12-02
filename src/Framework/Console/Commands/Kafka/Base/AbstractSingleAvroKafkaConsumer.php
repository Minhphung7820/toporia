<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;

use Toporia\Framework\Console\Commands\Kafka\Contracts\SingleMessageHandlerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Message;


/**
 * Abstract Class AbstractSingleAvroKafkaConsumer
 *
 * Abstract base class for AbstractSingleAvroKafkaConsumer implementations
 * in the Base layer providing common functionality and contracts.
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
abstract class AbstractSingleAvroKafkaConsumer extends AbstractAvroKafkaConsumer implements SingleMessageHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Check Avro support
            if (!$this->isAvroSupported()) {
                $this->warn('Avro support not available. Falling back to JSON.');
                // Could fallback to JSON consumer, but for now throw error
                throw new \RuntimeException('Avro support is required for this consumer.');
            }

            $broker = $this->getBroker();
            $topic = $this->getTopic();
            $groupId = $this->getGroupId();
            $offset = $this->getOffset();
            $schemaName = $this->getSchemaName();

            // Validate
            if (empty($topic)) {
                $this->error('Topic is required. Override getTopic() method.');
                return 1;
            }

            if (empty($schemaName)) {
                $this->error('Schema name is required. Override getSchemaName() method.');
                return 1;
            }

            // Display header
            $this->displayHeader('Avro Consumer', [
                'broker' => $this->getBrokerName(),
                'schema' => $schemaName,
                'registry' => $this->getSchemaRegistryUri(),
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
            $maxMessages = (int) $this->option('max-messages', 0);

            if ($maxMessages > 0) {
                $this->consumeWithLimit($broker, $timeout, $maxMessages);
            } else {
                $broker->consume($timeout, 1); // Single message for Avro
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
     * Process a single Avro message.
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

            // Extract metadata
            $metadata = [
                'topic' => $this->getTopic(),
                'schema' => $this->getSchemaName(),
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
                'schema' => $this->getSchemaName(),
            ]);

            // Re-throw to trigger DLQ
            throw $e;
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
        $broker->consume($timeout, 1);
    }
}
