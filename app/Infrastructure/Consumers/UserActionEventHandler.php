<?php

declare(strict_types=1);

namespace App\Infrastructure\Consumers;

use Toporia\Framework\Realtime\Consumer\AbstractConsumerHandler;
use Toporia\Framework\Realtime\Consumer\Contracts\ConsumerContext;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Support\Accessors\Log;

/**
 * UserActionEventHandler
 *
 * Listens to events.stream channel and logs user actions.
 *
 * Usage:
 *   php console broker:consume --handler=UserActionEvent --driver=rabbitmq
 *
 * Test by publishing:
 *   curl -X POST http://localhost:8000/api/broker/publish \
 *     -H "Content-Type: application/json" \
 *     -d '{"channel":"events.stream","event":"user.action","data":{"action":"login"},"driver":"rabbitmq"}'
 */
final class UserActionEventHandler extends AbstractConsumerHandler
{
    /**
     * Channels to subscribe.
     *
     * Note: For Redis Streams, use exact channel names only.
     * Wildcards like 'events.*' work with Kafka/RabbitMQ but create separate streams in Redis.
     */
    protected array $channels = ['events.stream'];

    /**
     * Preferred broker driver.
     */
    protected ?string $driver = 'kafka';

    /**
     * Consumer group for Kafka.
     */
    protected ?string $consumerGroup = 'user-action-group';

    /**
     * Maximum retry attempts.
     */
    protected int $maxRetries = 3;

    /**
     * Handle incoming message.
     *
     * Optimized for high throughput - single log line only.
     */
    public function handle(MessageInterface $message, ConsumerContext $context): void
    {
        $data = $this->getData($message);
        $event = $data['event'] ?? 'unknown';
        $payload = $data['data'] ?? [];
        $action = $payload['action'] ?? 'unknown';

        // Single log line with all essential info
        Log::info("User action: {$action}", [
            'event' => $event,
            'action' => $action,
            'user_id' => $payload['user_id'] ?? null,
            'message_id' => $message->getId(),
        ]);

        // Process logic without additional logging
        match ($action) {
            'login' => $this->handleLogin($payload),
            'logout' => $this->handleLogout($payload),
            'register' => $this->handleRegister($payload),
            default => null, // Generic action - no additional processing
        };
    }

    /**
     * Handle login action.
     */
    private function handleLogin(array $payload): void
    {
        // Track login statistics
        // Send login notification
        // Update last login timestamp
    }

    /**
     * Handle logout action.
     */
    private function handleLogout(array $payload): void
    {
        // Track logout statistics
    }

    /**
     * Handle register action.
     */
    private function handleRegister(array $payload): void
    {
        // Send welcome email
        // Track registration metrics
    }

    /**
     * Called when all retry attempts are exhausted.
     */
    public function onFailed(MessageInterface $message, \Throwable $exception, ConsumerContext $context): void
    {
        parent::onFailed($message, $exception, $context);

        Log::critical('User action event processing failed permanently', [
            'channel' => $message->getChannel(),
            'handler' => $this->getName(),
            'error' => $exception->getMessage(),
            'attempts' => $context->attempt,
        ]);
    }
}
