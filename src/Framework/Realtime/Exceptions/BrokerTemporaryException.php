<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Exceptions;

/**
 * Exception for temporary broker errors that can be retried.
 *
 * These exceptions indicate transient failures like network timeouts,
 * broker unavailability, or resource exhaustion. The operation may
 * succeed if retried after a delay.
 *
 * @package Toporia\Framework\Realtime\Exceptions
 */
class BrokerTemporaryException extends BrokerException
{
    /**
     * Suggested retry delay in milliseconds.
     */
    protected int $retryDelayMs;

    /**
     * Number of retry attempts made.
     */
    protected int $retryCount;

    /**
     * Maximum retry attempts.
     */
    protected int $maxRetries;

    /**
     * Create exception with retry information.
     *
     * @param string $message Error message
     * @param int $retryDelayMs Suggested retry delay in milliseconds
     * @param int $retryCount Current retry count
     * @param int $maxRetries Maximum retries allowed
     * @param array<string, mixed> $context Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        int $retryDelayMs = 1000,
        int $retryCount = 0,
        int $maxRetries = 5,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->retryDelayMs = $retryDelayMs;
        $this->retryCount = $retryCount;
        $this->maxRetries = $maxRetries;

        $context['retry_delay_ms'] = $retryDelayMs;
        $context['retry_count'] = $retryCount;
        $context['max_retries'] = $maxRetries;

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get suggested retry delay in milliseconds.
     *
     * @return int
     */
    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    /**
     * Get current retry count.
     *
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Get maximum retry attempts.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Check if more retries are available.
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }

    /**
     * Calculate exponential backoff delay.
     *
     * @param int $maxDelayMs Maximum delay cap
     * @return int Delay in milliseconds
     */
    public function getExponentialBackoffDelay(int $maxDelayMs = 30000): int
    {
        $delay = $this->retryDelayMs * (2 ** $this->retryCount);
        return min($delay, $maxDelayMs);
    }

    /**
     * Create exception for broker unavailable.
     *
     * @param string $broker Broker name
     * @param int $retryCount Current retry count
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function brokerUnavailable(string $broker, int $retryCount = 0, ?\Throwable $previous = null): static
    {
        return new static(
            "{$broker} broker is temporarily unavailable",
            1000,
            $retryCount,
            5,
            ['broker' => $broker],
            $previous
        );
    }

    /**
     * Create exception for network timeout.
     *
     * @param string $broker Broker name
     * @param int $timeoutMs Timeout duration
     * @param int $retryCount Current retry count
     * @return static
     */
    public static function networkTimeout(string $broker, int $timeoutMs, int $retryCount = 0): static
    {
        return new static(
            "{$broker} broker network timeout after {$timeoutMs}ms",
            min($timeoutMs, 5000),
            $retryCount,
            3,
            ['broker' => $broker, 'timeout_ms' => $timeoutMs]
        );
    }

    /**
     * Create exception for unknown topic/partition (Kafka-specific).
     *
     * @param string $topic Topic name
     * @param int $retryCount Current retry count
     * @return static
     */
    public static function unknownTopicOrPartition(string $topic, int $retryCount = 0): static
    {
        return new static(
            "Unknown topic or partition: {$topic}. Topic may not exist or metadata is stale.",
            500,
            $retryCount,
            10,
            ['topic' => $topic]
        );
    }
}
