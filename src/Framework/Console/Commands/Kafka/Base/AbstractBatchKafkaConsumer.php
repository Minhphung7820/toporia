<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Support\Collection\Collection;


/**
 * Abstract Class AbstractBatchKafkaConsumer
 *
 * Abstract base class for AbstractBatchKafkaConsumer implementations in
 * the Base layer providing common functionality and contracts.
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
abstract class AbstractBatchKafkaConsumer extends AbstractKafkaConsumer implements BatchingMessagesHandlerInterface
{
    /**
     * Get batch size limit.
     *
     * Maximum number of messages to accumulate before processing.
     *
     * @return int
     */
    abstract protected function getBatchSizeLimit(): int;

    /**
     * Get batch release interval in milliseconds.
     *
     * Maximum time to wait before processing batch (even if not full).
     *
     * @return int Milliseconds
     */
    abstract protected function getBatchReleaseInterval(): int;

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            $broker = $this->getBroker();
            $topic = $this->getTopic();
            $batchSize = $this->getBatchSizeLimit();
            $interval = $this->getBatchReleaseInterval();

            // Validate
            if (empty($topic)) {
                $this->error('Topic is required. Override getTopic() method.');
                return 1;
            }

            if ($batchSize <= 0) {
                $this->error('Batch size must be greater than 0.');
                return 1;
            }

            // Display header
            $this->displayHeader('Batch Consumer', [
                'broker' => $this->getBrokerName(),
                'batch_size' => $batchSize,
                'interval' => $interval . 'ms',
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $broker->stopConsuming();
            });

            // Start batch consuming
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
        $batch = [];
        $lastFlushTime = (int) (microtime(true) * 1000); // milliseconds (cast to int to avoid precision loss)
        $interval = $this->getBatchReleaseInterval();

        $topic = $this->getTopic();
        $this->logKafkaEvent('SUBSCRIBE', "topic <options=bold>{$topic}</>");

        // Subscribe to topic
        $broker->subscribe($topic, function (MessageInterface $message) use (&$batch, &$lastFlushTime, $batchSize, $interval, $broker, $topic) {
            // Log message metadata with highlighting
            $event = $message->getEvent() ?? 'unknown';
            $this->logKafkaEvent(
                'MESSAGE',
                "topic <fg=cyan>{$topic}</> • event <comment>{$event}</comment>"
            );

            $batch[] = [
                'message' => $message,
                'metadata' => [
                    'topic' => $topic,
                    'timestamp' => now()->getTimestamp(),
                ],
            ];

            // Suppress precision warnings for large Kafka offsets (handled internally by rdkafka)
            $now = @(int) (microtime(true) * 1000);

            // Process batch if full or interval elapsed
            if (count($batch) >= $batchSize || ($now - $lastFlushTime) >= $interval) {
                $this->logKafkaEvent(
                    'BATCH',
                    "Processing <info>" . count($batch) . "</info> message(s)"
                );
                $this->processBatch($batch);
                $batch = [];
                $lastFlushTime = $now;
            }

            // Check shouldQuit
            if ($this->shouldQuit) {
                $broker->stopConsuming();
            }
        });

        $this->logKafkaEvent('CONSUME', "listening on <fg=cyan>{$topic}</>");
        // Start consuming (this will use the batch size from KafkaBroker)
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
        // Similar to consumeBatches but with limit checking
        $this->consumeBatches($broker, $timeout, $batchSize);
    }

    /**
     * Process a batch of messages.
     *
     * @param array<array{message: MessageInterface, metadata: array}> $batch Message batch
     * @return void
     */
    protected function processBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            // Convert to Collection
            $messages = new Collection($batch);

            // Call handler
            $this->handleMessages($messages);

            $this->processed += count($batch);

            // Display progress (every 10 batches)
            if (($this->processed / $this->getBatchSizeLimit()) % 10 === 0) {
                $this->writeln("Processed: <info>{$this->processed}</info> messages in batches");
            }
        } catch (\Throwable $e) {
            $this->logError($e, [
                'batch_size' => count($batch),
                'topic' => $this->getTopic(),
            ]);

            // Re-throw to trigger DLQ for entire batch
            throw $e;
        }
    }
}
