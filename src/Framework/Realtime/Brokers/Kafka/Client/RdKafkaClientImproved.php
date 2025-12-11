<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers\Kafka\Client;

use RdKafka;
use Toporia\Framework\Realtime\Exceptions\{BrokerException, BrokerTemporaryException};
use Toporia\Framework\Realtime\Metrics\BrokerMetrics;

/**
 * Class RdKafkaClientImproved
 *
 * Improved Kafka client with backpressure, better error handling, and metrics.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Brokers\Kafka\Client
 * @since       2025-12-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RdKafkaClientImproved implements KafkaClientInterface
{
    private ?RdKafka\Producer $producer = null;
    private ?RdKafka\KafkaConsumer $consumer = null;
    private bool $connected = false;
    private bool $consuming = false;

    /**
     * @var array<string, array{topic: RdKafka\ProducerTopic, created_at: int}> Topic cache with TTL
     */
    private array $topicCache = [];

    private int $consecutiveErrors = 0;
    private int $lastErrorTime = 0;

    private const TOPIC_CACHE_TTL = 3600; // 1 hour

    /**
     * @param array<string> $brokers Broker addresses
     * @param string $consumerGroup Consumer group ID
     * @param bool $manualCommit Enable manual offset commit
     * @param array<string, string> $producerConfig Additional producer config
     * @param array<string, string> $consumerConfig Additional consumer config
     */
    public function __construct(
        private readonly array $brokers,
        private readonly string $consumerGroup = 'realtime-servers',
        private readonly bool $manualCommit = false,
        private readonly array $producerConfig = [],
        private readonly array $consumerConfig = []
    ) {
        if (empty($this->brokers)) {
            throw BrokerException::invalidConfiguration('kafka', 'Broker list is required');
        }
    }

    public function getName(): string
    {
        return 'rdkafka-improved';
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

        // Initialize producer
        $producerConf = new RdKafka\Conf();
        $producerConf->set('bootstrap.servers', $brokerList);
        $producerConf->set('metadata.broker.list', $brokerList);

        foreach ($this->producerConfig as $key => $value) {
            $producerConf->set($key, (string) $value);
        }

        $this->producer = new RdKafka\Producer($producerConf);
        $this->producer->addBrokers($brokerList);

        // Initialize consumer
        $consumerConf = new RdKafka\Conf();
        $consumerConf->set('bootstrap.servers', $brokerList);
        $consumerConf->set('metadata.broker.list', $brokerList);
        $consumerConf->set('group.id', $this->consumerGroup);
        $consumerConf->set('enable.auto.commit', $this->manualCommit ? 'false' : 'true');
        $consumerConf->set('auto.offset.reset', 'earliest');

        foreach ($this->consumerConfig as $key => $value) {
            $consumerConf->set($key, (string) $value);
        }

        $this->consumer = new RdKafka\KafkaConsumer($consumerConf);

        $this->connected = true;
        BrokerMetrics::recordConnectionEvent('kafka', 'connect');
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
        $this->connected = false;

        BrokerMetrics::recordConnectionEvent('kafka', 'disconnect');
    }

    public function publish(string $topic, string $payload, ?int $partition = null, ?string $key = null): void
    {
        if (!$this->connected || $this->producer === null) {
            throw BrokerException::notConnected('kafka');
        }

        // Get or create cached topic with TTL check
        $topicInstance = $this->getTopicInstance($topic);

        // Produce directly without buffering for reliability
        // This ensures every message is sent immediately
        $partitionVal = $partition ?? RD_KAFKA_PARTITION_UA;

        try {
            if ($key !== null && method_exists($topicInstance, 'producev')) {
                $topicInstance->producev($partitionVal, 0, $payload, $key);
            } else {
                $topicInstance->produce($partitionVal, 0, $payload);
            }

            // Poll to trigger delivery callbacks and ensure message is sent
            // This is critical - without this, messages may be lost
            $this->producer->poll(0);

            // Flush with short timeout to ensure message delivery
            // This blocks until the message is delivered or timeout
            $result = $this->producer->flush(1000); // 1 second timeout

            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                // Message may not have been delivered, poll more aggressively
                $retries = 5;
                while ($retries-- > 0 && $this->producer->getOutQLen() > 0) {
                    $this->producer->poll(100);
                    $this->producer->flush(500);
                }

                // Final check
                if ($this->producer->getOutQLen() > 0) {
                    throw new \RuntimeException("Kafka producer queue not empty after flush, {$this->producer->getOutQLen()} messages pending");
                }
            }

        } catch (\Throwable $e) {
            BrokerMetrics::recordError('kafka', 'publish');
            throw BrokerException::publishFailed('kafka', $topic, $e->getMessage(), $e);
        }
    }

    public function flush(int $timeoutMs = 5000): void
    {
        if ($this->producer === null) {
            return;
        }

        // Poll first to process any pending callbacks
        $this->producer->poll(0);

        // Flush with timeout
        $result = $this->producer->flush($timeoutMs);

        // If flush didn't complete, keep trying
        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            $retries = 10;
            while ($retries-- > 0 && $this->producer->getOutQLen() > 0) {
                $this->producer->poll(100);
                $this->producer->flush(500);
            }
        }
    }

    public function subscribe(array $topics): void
    {
        if (!$this->connected || $this->consumer === null) {
            throw BrokerException::notConnected('kafka');
        }

        try {
            $this->consumer->subscribe($topics);
            usleep(500000); // Allow metadata refresh
            $this->consumer->consume(100); // Trigger metadata refresh
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

        while ($this->consuming) {
            try {
                $message = $this->consumer->consume($timeoutMs);

                if ($message === null) {
                    continue;
                }

                $kafkaMessage = KafkaMessage::fromRdKafka($message);

                // Handle errors
                if ($kafkaMessage->hasError()) {
                    $this->handleKafkaError($kafkaMessage);
                    continue;
                }

                // Reset error counter on successful message
                $this->consecutiveErrors = 0;

                // Process message
                $shouldContinue = $callback($kafkaMessage);
                if ($shouldContinue === false) {
                    break;
                }

            } catch (BrokerException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->consecutiveErrors++;
                BrokerMetrics::recordError('kafka', 'consume');

                if ($this->consecutiveErrors >= 5) {
                    throw BrokerException::consumeFailed('kafka', $e->getMessage(), $e);
                }

                // Brief pause before retry
                usleep(100000); // 100ms
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
                error_log("Failed to commit offset: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get topic instance with TTL-based caching.
     *
     * @param string $topic Topic name
     * @return RdKafka\ProducerTopic
     */
    private function getTopicInstance(string $topic): RdKafka\ProducerTopic
    {
        $now = time();

        // Cleanup expired topics
        foreach ($this->topicCache as $topicName => $cached) {
            if ($now - $cached['created_at'] > self::TOPIC_CACHE_TTL) {
                unset($this->topicCache[$topicName]);
            }
        }

        if (!isset($this->topicCache[$topic])) {
            $this->topicCache[$topic] = [
                'topic' => $this->producer->newTopic($topic),
                'created_at' => $now,
            ];
        }

        return $this->topicCache[$topic]['topic'];
    }

    /**
     * Handle Kafka error messages with circuit breaker logic.
     *
     * @param KafkaMessage $message
     * @return void
     */
    private function handleKafkaError(KafkaMessage $message): void
    {
        if ($message->isEof() || $message->isTimeout()) {
            $this->consecutiveErrors = 0;
            return;
        }

        if ($message->isUnknownTopicOrPartition()) {
            $this->handleUnknownTopicError();
            return;
        }

        // Other errors
        $this->consecutiveErrors++;
        $now = time();

        // Reset error counter if last error was long ago
        if ($now - $this->lastErrorTime > 60) {
            $this->consecutiveErrors = 0;
        }

        $this->lastErrorTime = $now;

        BrokerMetrics::recordError('kafka', 'message');

        if ($this->consecutiveErrors >= 10) {
            error_log("CRITICAL: Too many consecutive Kafka errors ({$this->consecutiveErrors}). Waiting 60s...");
            sleep(60);
            $this->consecutiveErrors = 0;
            return;
        }

        // Exponential backoff
        $delay = min((int) pow(2, $this->consecutiveErrors - 1), 30);
        if ($delay > 0) {
            error_log("Kafka error #{$this->consecutiveErrors}. Backoff {$delay}s");
            sleep($delay);
        }
    }

    /**
     * Handle unknown topic/partition error with exponential backoff.
     *
     * @return void
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

