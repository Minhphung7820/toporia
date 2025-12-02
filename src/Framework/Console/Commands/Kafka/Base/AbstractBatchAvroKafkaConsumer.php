<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Support\Collection\Collection;


/**
 * Abstract Class AbstractBatchAvroKafkaConsumer
 *
 * Abstract base class for AbstractBatchAvroKafkaConsumer implementations
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
abstract class AbstractBatchAvroKafkaConsumer extends AbstractAvroKafkaConsumer implements BatchingMessagesHandlerInterface
{
    /**
     * Get batch size limit.
     *
     * @return int
     */
    abstract protected function getBatchSizeLimit(): int;

    /**
     * Get batch release interval in milliseconds.
     *
     * @return int
     */
    abstract protected function getBatchReleaseInterval(): int;

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Check Avro support
            if (!$this->isAvroSupported()) {
                throw new \RuntimeException('Avro support is required for this consumer.');
            }

            $broker = $this->getBroker();
            $topic = $this->getTopic();
            $batchSize = $this->getBatchSizeLimit();
            $interval = $this->getBatchReleaseInterval();
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

            if ($batchSize <= 0) {
                $this->error('Batch size must be greater than 0.');
                return 1;
            }

            // Display header
            $this->displayHeader('Batch Avro Consumer', [
                'broker' => $this->getBrokerName(),
                'schema' => $schemaName,
                'registry' => $this->getSchemaRegistryUri(),
                'batch_size' => $batchSize,
                'interval' => $interval . 'ms',
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $broker->stopConsuming();
            });

            // Start batch consuming (similar to AbstractBatchKafkaConsumer)
            $timeout = (int) $this->option('timeout', 1000);
            $maxMessages = (int) $this->option('max-messages', 0);

            if ($maxMessages > 0) {
                $this->consumeBatchesWithLimit($broker, $timeout, $batchSize, $maxMessages);
            } else {
                $this->consumeBatches($broker, $timeout, $batchSize);
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
     * Consume messages in batches.
     *
     * @param KafkaBroker $broker Kafka broker
     * @param int $timeout Poll timeout
     * @param int $batchSize Batch size
     * @return void
     */
    protected function consumeBatches(KafkaBroker $broker, int $timeout, int $batchSize): void
    {
        // Similar implementation to AbstractBatchKafkaConsumer
        // but with Avro deserialization
        $batch = [];
        $lastFlushTime = microtime(true) * 1000;
        $interval = $this->getBatchReleaseInterval();

        // Subscribe to topic
        $broker->subscribe($this->getTopic(), function ($message) use (&$batch, &$lastFlushTime, $batchSize, $interval, $broker) {
            // Deserialize Avro message
            // For now, treat as regular message (Avro deserialization to be implemented)
            $batch[] = [
                'message' => $message,
                'metadata' => [
                    'topic' => $this->getTopic(),
                    'schema' => $this->getSchemaName(),
                    'timestamp' => now()->getTimestamp(),
                ],
            ];

            $now = microtime(true) * 1000;

            if (count($batch) >= $batchSize || ($now - $lastFlushTime) >= $interval) {
                $this->processBatch($batch);
                $batch = [];
                $lastFlushTime = $now;
            }

            if ($this->shouldQuit) {
                $broker->stopConsuming();
            }
        });

        $broker->consume($timeout, $batchSize);
    }

    /**
     * Consume batches with message limit.
     *
     * @param KafkaBroker $broker Kafka broker
     * @param int $timeout Poll timeout
     * @param int $batchSize Batch size
     * @param int $maxMessages Maximum messages
     * @return void
     */
    protected function consumeBatchesWithLimit(KafkaBroker $broker, int $timeout, int $batchSize, int $maxMessages): void
    {
        $this->consumeBatches($broker, $timeout, $batchSize);
    }

    /**
     * Process a batch of messages.
     *
     * @param array<array{message: mixed, metadata: array}> $batch Message batch
     * @return void
     */
    protected function processBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            $messages = new Collection($batch);
            $this->handleMessages($messages);
            $this->processed += count($batch);

            if (($this->processed / $this->getBatchSizeLimit()) % 10 === 0) {
                $this->writeln("Processed: <info>{$this->processed}</info> messages in batches");
            }
        } catch (\Throwable $e) {
            $this->logError($e, [
                'batch_size' => count($batch),
                'topic' => $this->getTopic(),
                'schema' => $this->getSchemaName(),
            ]);

            throw $e;
        }
    }
}
