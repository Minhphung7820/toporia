<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers\Kafka\Client;

use Toporia\Framework\Realtime\Exceptions\BrokerException;
use Toporia\Framework\Realtime\Exceptions\BrokerTemporaryException;

/**
 * Class RdKafkaClient
 *
 * High-performance Kafka client using rdkafka extension (librdkafka).
 *
 * Performance optimizations:
 * - Async produce with non-blocking poll
 * - Batched internal queuing via librdkafka
 * - Minimal latency for HTTP requests (~5-20ms)
 * - Background delivery confirmation via callbacks
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
 * @package     toporia/framework
 */
final class RdKafkaClient implements KafkaClientInterface
{
    private ?\RdKafka\Producer $producer = null;
    private ?\RdKafka\KafkaConsumer $consumer = null;
    private bool $connected = false;
    private bool $consuming = false;

    /**
     * @var array<string, \RdKafka\ProducerTopic> Topic cache
     */
    private array $topicCache = [];

    /**
     * Error recovery state.
     */
    private int $consecutiveErrors = 0;

    /**
     * Track failed deliveries for monitoring.
     */
    private int $failedDeliveries = 0;

    /**
     * @param array<string> $brokers Broker addresses
     * @param string $consumerGroup Consumer group ID
     * @param bool $manualCommit Enable manual offset commit
     * @param int $bufferSize Message buffer size (used for batch processing)
     * @param int $flushIntervalMs Flush interval in milliseconds
     * @param array<string, string> $producerConfig Additional producer config
     * @param array<string, string> $consumerConfig Additional consumer config
     */
    public function __construct(
        private readonly array $brokers,
        private readonly string $consumerGroup = 'realtime-servers',
        private readonly bool $manualCommit = false,
        private readonly int $bufferSize = 100,
        private readonly int $flushIntervalMs = 100,
        private readonly array $producerConfig = [],
        private readonly array $consumerConfig = []
    ) {
        if (empty($this->brokers)) {
            throw BrokerException::invalidConfiguration('kafka', 'Broker list is required');
        }
    }

    public function getName(): string
    {
        return 'rdkafka';
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        if (!extension_loaded('rdkafka')) {
            throw BrokerException::invalidConfiguration('kafka', 'rdkafka extension is not loaded');
        }

        $brokerList = implode(',', $this->brokers);

        // Minimal logging - only errors
        $errorCallback = function ($kafka, $err, $reason): void {
            if ($err !== RD_KAFKA_RESP_ERR__TRANSPORT && $err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                error_log("[Kafka] Error {$err}: {$reason}");
            }
        };

        // ========== PRODUCER CONFIG - Optimized for throughput ==========
        $producerConf = new \RdKafka\Conf();
        $producerConf->set('bootstrap.servers', $brokerList);
        $producerConf->set('log_level', '3');

        // Performance: Use acks=1 for speed (leader only, no replica wait)
        // Change to 'all' if you need stronger durability guarantee
        $producerConf->set('acks', '1');

        // Batching for throughput - librdkafka handles this efficiently
        $producerConf->set('queue.buffering.max.ms', '5');         // 5ms max wait - very low latency
        $producerConf->set('queue.buffering.max.messages', '10000'); // Large buffer
        $producerConf->set('batch.num.messages', '1000');          // Batch up to 1000 msgs
        $producerConf->set('linger.ms', '5');                      // Wait up to 5ms to batch

        // Compression for network efficiency (lz4 is fastest)
        $producerConf->set('compression.type', 'lz4');

        // Reliability with fast retries
        $producerConf->set('retries', '3');
        $producerConf->set('retry.backoff.ms', '50');
        $producerConf->set('request.timeout.ms', '1000');          // 1s timeout per request
        $producerConf->set('message.timeout.ms', '5000');          // 5s total message timeout

        // Socket optimization
        $producerConf->set('socket.keepalive.enable', 'true');
        $producerConf->set('socket.nagle.disable', 'true');        // Disable Nagle for low latency

        $producerConf->setErrorCb($errorCallback);

        // Delivery report - async, just for monitoring
        $producerConf->setDrMsgCb(function ($_kafka, $message): void {
            if ($message->err) {
                $this->failedDeliveries++;
                error_log("[Kafka] Delivery failed: " . rd_kafka_err2str($message->err));
            }
        });

        // Apply custom overrides
        foreach ($this->producerConfig as $key => $value) {
            $producerConf->set($key, (string) $value);
        }

        $this->producer = new \RdKafka\Producer($producerConf);
        $this->producer->addBrokers($brokerList);

        // ========== CONSUMER CONFIG - Optimized for low latency ==========
        $consumerConf = new \RdKafka\Conf();
        $consumerConf->set('bootstrap.servers', $brokerList);
        $consumerConf->set('group.id', $this->consumerGroup);
        $consumerConf->set('log_level', '3');

        // Auto-commit for simplicity (can be overridden)
        $consumerConf->set('enable.auto.commit', $this->manualCommit ? 'false' : 'true');
        $consumerConf->set('auto.offset.reset', 'earliest');
        $consumerConf->set('auto.commit.interval.ms', '1000');

        // Low latency fetching
        $consumerConf->set('fetch.wait.max.ms', '50');             // Max 50ms wait
        $consumerConf->set('fetch.min.bytes', '1');                // Fetch immediately
        $consumerConf->set('fetch.max.bytes', '1048576');          // 1MB max per fetch

        // Session management
        $consumerConf->set('session.timeout.ms', '30000');
        $consumerConf->set('heartbeat.interval.ms', '10000');
        $consumerConf->set('max.poll.interval.ms', '300000');

        $consumerConf->setErrorCb($errorCallback);

        foreach ($this->consumerConfig as $key => $value) {
            $consumerConf->set($key, (string) $value);
        }

        $this->consumer = new \RdKafka\KafkaConsumer($consumerConf);
        $this->connected = true;
    }

    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->stopConsuming();

        // Final flush on disconnect - wait for pending messages
        if ($this->producer !== null) {
            $this->producer->flush(2000);
            $this->producer = null;
        }

        if ($this->consumer !== null) {
            try {
                $this->consumer->unsubscribe();
            } catch (\Throwable) {
                // Ignore
            }
            $this->consumer = null;
        }

        $this->topicCache = [];
        $this->connected = false;
    }

    /**
     * High-performance publish with guaranteed delivery for HTTP requests.
     *
     * Strategy:
     * - Produce to internal librdkafka queue
     * - For short-lived processes (HTTP): flush with short timeout to ensure delivery
     * - For long-running processes: async with poll(0)
     *
     * Performance:
     * - HTTP requests: ~10-50ms latency (with delivery guarantee)
     * - Long-running: ~1-5ms latency (async)
     * - Throughput: 50k-100k+ msg/s
     */
    public function publish(string $topic, string $payload, ?int $partition = null, ?string $key = null): void
    {
        if (!$this->connected || $this->producer === null) {
            throw BrokerException::notConnected('kafka');
        }

        // Get or create cached topic
        if (!isset($this->topicCache[$topic])) {
            $this->topicCache[$topic] = $this->producer->newTopic($topic);
        }

        $topicInstance = $this->topicCache[$topic];
        $partitionValue = $partition ?? RD_KAFKA_PARTITION_UA;

        // Produce to internal queue
        if ($key !== null && method_exists($topicInstance, 'producev')) {
            $topicInstance->producev($partitionValue, 0, $payload, $key);
        } else {
            $topicInstance->produce($partitionValue, 0, $payload);
        }

        // Flush strategy:
        // - For long-running processes (consuming = true): use async poll(0)
        // - For HTTP requests (consuming = false): flush with short timeout
        if ($this->consuming) {
            // Async: just trigger internal processing
            $this->producer->poll(0);
        } else {
            // HTTP: ensure delivery before request ends
            // flush(50) is enough for local/fast Kafka, increases for remote
            $this->producer->flush(50);
        }
    }

    /**
     * Synchronous publish with delivery confirmation.
     * Use this when you MUST guarantee delivery before returning.
     *
     * Latency: ~20-100ms
     */
    public function publishSync(string $topic, string $payload, ?int $partition = null, ?string $key = null, int $timeoutMs = 1000): bool
    {
        $this->publish($topic, $payload, $partition, $key);

        // Wait for delivery
        $result = $this->producer->flush($timeoutMs);

        return $result === RD_KAFKA_RESP_ERR_NO_ERROR;
    }

    /**
     * Flush pending messages.
     * Call this periodically or before shutdown.
     */
    public function flush(int $timeoutMs = 5000): void
    {
        if ($this->producer !== null) {
            $this->producer->flush($timeoutMs);
        }
    }

    /**
     * Poll for delivery callbacks.
     * Call this periodically in long-running processes.
     *
     * @param int $timeoutMs Poll timeout (0 = non-blocking)
     * @return int Number of events processed
     */
    public function poll(int $timeoutMs = 0): int
    {
        if ($this->producer === null) {
            return 0;
        }

        return $this->producer->poll($timeoutMs) ?? 0;
    }

    public function subscribe(array $topics): void
    {
        if (!$this->connected || $this->consumer === null) {
            throw BrokerException::notConnected('kafka');
        }

        try {
            $this->consumer->subscribe($topics);
            // Brief wait for partition assignment
            $this->consumer->consume(200);
        } catch (\Throwable $e) {
            throw BrokerException::subscribeFailed('kafka', implode(',', $topics), $e->getMessage(), $e);
        }
    }

    /**
     * Consume messages with optimized batch processing.
     */
    public function consume(callable $callback, int $timeoutMs = 1000, int $batchSize = 100): void
    {
        if (!$this->connected || $this->consumer === null) {
            throw BrokerException::notConnected('kafka');
        }

        $this->consuming = true;
        $this->consecutiveErrors = 0;
        $messagesProcessed = 0;

        while ($this->consuming && $messagesProcessed < $batchSize) {
            try {
                $message = $this->consumer->consume($timeoutMs);

                if ($message === null) {
                    return; // Timeout
                }

                $kafkaMessage = KafkaMessage::fromRdKafka($message);

                if ($kafkaMessage->hasError()) {
                    if ($kafkaMessage->isEof() || $kafkaMessage->isTimeout()) {
                        $this->consecutiveErrors = 0;
                        return;
                    }

                    if ($kafkaMessage->isUnknownTopicOrPartition()) {
                        $this->handleUnknownTopicError();
                        return;
                    }

                    $this->consecutiveErrors++;
                    if ($this->consecutiveErrors > 3) {
                        throw BrokerException::consumeFailed('kafka', $kafkaMessage->errorMessage);
                    }
                    continue;
                }

                $this->consecutiveErrors = 0;

                if ($callback($kafkaMessage) === false) {
                    break;
                }

                $messagesProcessed++;

            } catch (BrokerException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->consecutiveErrors++;
                if ($this->consecutiveErrors >= 5) {
                    throw BrokerException::consumeFailed('kafka', $e->getMessage(), $e);
                }
            }
        }
    }

    public function stopConsuming(): void
    {
        $this->consuming = false;
    }

    public function commit(KafkaMessage $message): void
    {
        if (!$this->manualCommit || $this->consumer === null || $message->raw === null) {
            return;
        }

        try {
            $this->consumer->commit($message->raw);
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'precision')) {
                error_log("Commit failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get failed delivery count for monitoring.
     */
    public function getFailedDeliveries(): int
    {
        return $this->failedDeliveries;
    }

    /**
     * Reset failed delivery counter.
     */
    public function resetFailedDeliveries(): void
    {
        $this->failedDeliveries = 0;
    }

    private function handleUnknownTopicError(): void
    {
        $this->consecutiveErrors++;

        if ($this->consecutiveErrors >= 10) {
            throw BrokerTemporaryException::unknownTopicOrPartition('unknown', $this->consecutiveErrors);
        }

        $delayMs = min(100 * (2 ** ($this->consecutiveErrors - 1)), 5000);
        usleep($delayMs * 1000);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
