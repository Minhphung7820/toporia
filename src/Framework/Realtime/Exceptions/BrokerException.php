<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Exceptions;

/**
 * Exception for broker-related errors.
 *
 * @package Toporia\Framework\Realtime\Exceptions
 */
class BrokerException extends RealtimeException
{
    /**
     * Create exception for connection failure.
     *
     * @param string $broker Broker name
     * @param string $reason Failure reason
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function connectionFailed(string $broker, string $reason, ?\Throwable $previous = null): static
    {
        return new static(
            "Failed to connect to {$broker} broker: {$reason}",
            ['broker' => $broker, 'reason' => $reason],
            0,
            $previous
        );
    }

    /**
     * Create exception for publish failure.
     *
     * @param string $broker Broker name
     * @param string $channel Channel name
     * @param string $reason Failure reason
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function publishFailed(string $broker, string $channel, string $reason, ?\Throwable $previous = null): static
    {
        return new static(
            "Failed to publish to {$broker} broker on channel '{$channel}': {$reason}",
            ['broker' => $broker, 'channel' => $channel, 'reason' => $reason],
            0,
            $previous
        );
    }

    /**
     * Create exception for subscribe failure.
     *
     * @param string $broker Broker name
     * @param string $channel Channel name
     * @param string $reason Failure reason
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function subscribeFailed(string $broker, string $channel, string $reason, ?\Throwable $previous = null): static
    {
        return new static(
            "Failed to subscribe to {$broker} broker on channel '{$channel}': {$reason}",
            ['broker' => $broker, 'channel' => $channel, 'reason' => $reason],
            0,
            $previous
        );
    }

    /**
     * Create exception for consumer failure.
     *
     * @param string $broker Broker name
     * @param string $reason Failure reason
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function consumeFailed(string $broker, string $reason, ?\Throwable $previous = null): static
    {
        return new static(
            "Consumer error on {$broker} broker: {$reason}",
            ['broker' => $broker, 'reason' => $reason],
            0,
            $previous
        );
    }

    /**
     * Create exception for configuration error.
     *
     * @param string $broker Broker name
     * @param string $reason Configuration issue
     * @return static
     */
    public static function invalidConfiguration(string $broker, string $reason): static
    {
        return new static(
            "Invalid {$broker} broker configuration: {$reason}",
            ['broker' => $broker, 'reason' => $reason]
        );
    }

    /**
     * Create exception for broker not connected.
     *
     * @param string $broker Broker name
     * @return static
     */
    public static function notConnected(string $broker): static
    {
        return new static(
            "{$broker} broker is not connected",
            ['broker' => $broker]
        );
    }
}
