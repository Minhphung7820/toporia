<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers\Kafka\Client;

use Toporia\Framework\Realtime\Exceptions\BrokerException;
use Toporia\Framework\Realtime\Exceptions\BrokerTemporaryException;

/**
 * Class RdKafkaClient
 *
 * Kafka client adapter using rdkafka extension. High-performance client using librdkafka C library.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Brokers\Kafka\Client
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RdKafkaClient implements KafkaClientInterface
{
    private ?\RdKafka\Producer $producer = null;
    private ?\RdKafka\KafkaConsumer $consumer = null;
    private bool $connected = false;
    private bool $consuming = false;

    /**
     * @var array<string, \RdKafka\ProducerTopic> Topic cache for performance
     */
    private array $topicCache = [];

    /**
     * @var array<array{topic: \RdKafka\ProducerTopic, partition: int|null, key: string|null, payload: string}> Message buffer
     */
    private array $messageBuffer = [];

    private int $lastFlushTime = 0;

    /**
     * Error recovery state.
     */
    private int $consecutiveErrors = 0;
    private int $lastErrorTime = 0;

    /**
     * @param array<string> $brokers Broker addresses
     * @param string $consumerGroup Consumer group ID
     * @param bool $manualCommit Enable manual offset commit
     * @param int $bufferSize Message buffer size before flush
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

        // Error callback to suppress verbose rdkafka logs
        $errorCallback = function ($kafka, $err, $reason): void {
            // Only log actual errors, not warnings
            if ($err !== RD_KAFKA_RESP_ERR__TRANSPORT && $err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                error_log("[Kafka] Error {$err}: {$reason}");
            }
        };

        // Log callback to suppress verbose logs (level 0-6)
        $logCallback = function ($kafka, $level, $facility, $message): void {
            // Only log errors (level <= 3), suppress info/debug
            if ($level <= 3) {
                error_log("[Kafka:{$facility}] {$message}");
            }
        };

        // Initialize producer
        $producerConf = new \RdKafka\Conf();
        $producerConf->set('bootstrap.servers', $brokerList);
        $producerConf->set('metadata.broker.list', $brokerList);
        $producerConf->set('log_level', '3'); // Only errors
        $producerConf->setErrorCb($errorCallback);
        $producerConf->setLogCb($logCallback);

        // Delivery report callback for tracking message delivery
        $producerConf->setDrMsgCb(function ($_kafka, $message): void {
            if ($message->err) {
                error_log("[Kafka] Delivery failed: " . rd_kafka_err2str($message->err));
            }
        });

        foreach ($this->producerConfig as $key => $value) {
            $producerConf->set($key, (string) $value);
        }

        $this->producer = new \RdKafka\Producer($producerConf);
        $this->producer->addBrokers($brokerList);

        // Initialize consumer
        $consumerConf = new \RdKafka\Conf();
        $consumerConf->set('bootstrap.servers', $brokerList);
        $consumerConf->set('metadata.broker.list', $brokerList);
        $consumerConf->set('group.id', $this->consumerGroup);
        $consumerConf->set('enable.auto.commit', $this->manualCommit ? 'false' : 'true');
        $consumerConf->set('auto.offset.reset', 'earliest');
        $consumerConf->set('log_level', '3'); // Only errors
        $consumerConf->setErrorCb($errorCallback);
        $consumerConf->setLogCb($logCallback);

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
        $this->flush(5000);

        if ($this->consumer !== null) {
            try {
                $this->consumer->unsubscribe();
            } catch (\Throwable) {
                // Ignore unsubscribe errors during disconnect
            }
            $this->consumer = null;
        }

        $this->producer = null;
        $this->topicCache = [];
        $this->messageBuffer = [];
        $this->connected = false;
    }

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

        // Add to buffer
        $this->messageBuffer[] = [
            'topic' => $topicInstance,
            'partition' => $partition,
            'key' => $key,
            'payload' => $payload,
        ];

        // Flush if buffer is full
        if (count($this->messageBuffer) >= $this->bufferSize) {
            $this->flushBuffer();
            return;
        }

        // Periodic flush
        $now = (int) (microtime(true) * 1000);
        if ($now - $this->lastFlushTime >= $this->flushIntervalMs) {
            $this->flushBuffer();
            return;
        }

        // For HTTP/short-lived requests: flush immediately to ensure delivery
        // In long-running processes (WebSocket server), batching provides better performance
        if (!$this->consuming) {
            $this->flushBuffer();
        }
    }

    public function flush(int $timeoutMs = 5000): void
    {
        $this->flushBuffer();

        if ($this->producer !== null && method_exists($this->producer, 'flush')) {
            $this->producer->flush($timeoutMs);
        }
    }

    public function subscribe(array $topics): void
    {
        if (!$this->connected || $this->consumer === null) {
            throw BrokerException::notConnected('kafka');
        }

        try {
            $this->consumer->subscribe($topics);
            // Allow metadata refresh
            usleep(500000);
            // Trigger metadata refresh
            $this->consumer->consume(100);
        } catch (\Throwable $e) {
            throw BrokerException::subscribeFailed('kafka', implode(',', $topics), $e->getMessage(), $e);
        }
    }

    public function consume(callable $callback, int $timeoutMs = 1000, int $batchSize = 100): void
    {
        if (!$this->connected || $this->consumer === null) {
            throw BrokerException::notConnected('kafka');
        }

        $this->consuming = true;
        $this->consecutiveErrors = 0;
        $batch = [];
        $lastBatchFlushTime = (int) (microtime(true) * 1000);

        while ($this->consuming) {
            try {
                $message = $this->consumer->consume($timeoutMs);

                if ($message === null) {
                    continue;
                }

                $kafkaMessage = KafkaMessage::fromRdKafka($message);

                // Handle errors
                if ($kafkaMessage->hasError()) {
                    if ($kafkaMessage->isEof() || $kafkaMessage->isTimeout()) {
                        // Normal, continue
                        $this->consecutiveErrors = 0;
                        continue;
                    }

                    if ($kafkaMessage->isUnknownTopicOrPartition()) {
                        $this->handleUnknownTopicError();
                        continue;
                    }

                    // Other errors
                    $this->consecutiveErrors++;
                    if ($this->consecutiveErrors <= 3) {
                        continue;
                    }

                    throw BrokerException::consumeFailed('kafka', $kafkaMessage->errorMessage);
                }

                // Reset error counter on successful message
                $this->consecutiveErrors = 0;

                // Process message
                $shouldContinue = $callback($kafkaMessage);
                if ($shouldContinue === false) {
                    break;
                }

                $batch[] = $kafkaMessage;

                // Process batch when full
                if (count($batch) >= $batchSize) {
                    $batch = [];
                }

                // Periodic batch flush
                $now = (int) (microtime(true) * 1000);
                if ($now - $lastBatchFlushTime >= 100) {
                    $batch = [];
                    $lastBatchFlushTime = $now;
                }

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
            // Log but don't fail - offset commit is best-effort
            // Precision warnings are common with large offsets
            if (!str_contains($e->getMessage(), 'precision')) {
                error_log("Failed to commit offset: {$e->getMessage()}");
            }
        }
    }

    /**
     * Flush message buffer.
     *
     * @param bool $sync If true, wait for messages to be delivered (for HTTP requests)
     */
    private function flushBuffer(bool $sync = true): void
    {
        if (empty($this->messageBuffer) || $this->producer === null) {
            return;
        }

        foreach ($this->messageBuffer as $item) {
            /** @var \RdKafka\ProducerTopic $topic */
            $topic = $item['topic'];
            $partition = $item['partition'] ?? RD_KAFKA_PARTITION_UA;
            $key = $item['key'];
            $payload = $item['payload'];

            if ($key !== null && method_exists($topic, 'producev')) {
                $topic->producev($partition, 0, $payload, $key);
            } else {
                $topic->produce($partition, 0, $payload);
            }
        }

        $this->messageBuffer = [];
        $this->lastFlushTime = (int) (microtime(true) * 1000);

        // Sync flush: wait for messages to be delivered
        // This ensures HTTP requests don't return before messages are sent
        if ($sync) {
            // Poll to trigger delivery callbacks (max 100ms total)
            for ($i = 0; $i < 5; $i++) {
                $events = $this->producer->poll(20);
                if ($events === 0) {
                    break; // No more events to process
                }
            }

            // Final flush with short timeout (500ms max)
            $this->producer->flush(500);
        } else {
            // Async: just poll without waiting
            $this->producer->poll(0);
        }
    }

    /**
     * Handle unknown topic/partition error with exponential backoff.
     */
    private function handleUnknownTopicError(): void
    {
        $this->consecutiveErrors++;
        $maxRetries = 10;

        if ($this->consecutiveErrors >= $maxRetries) {
            throw BrokerTemporaryException::unknownTopicOrPartition('unknown', $this->consecutiveErrors);
        }

        // Exponential backoff: 100ms, 200ms, 400ms, 800ms, 1600ms... capped at 10s
        $delayMs = min(100 * (2 ** ($this->consecutiveErrors - 1)), 10000);
        usleep($delayMs * 1000);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
