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
     */
    protected array $channels = ['events.stream', 'events.*'];

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
     */
    public function handle(MessageInterface $message, ConsumerContext $context): void
    {
        $data = $this->getData($message);

        $event = $data['event'] ?? 'unknown';
        $payload = $data['data'] ?? [];

        // Log the received event
        Log::info('User action event received', [
            'channel' => $message->getChannel(),
            'event' => $event,
            'action' => $payload['action'] ?? null,
            'ip' => $payload['ip'] ?? null,
            'agent' => $payload['agent'] ?? null,
            'message_id' => $message->getId(),
            'attempt' => $context->attempt,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $this->info("Received user action event", [
            'event' => $event,
            'action' => $payload['action'] ?? 'unknown',
        ]);

        // Process based on action type
        $action = $payload['action'] ?? null;

        switch ($action) {
            case 'login':
                $this->handleLogin($payload);
                break;
            case 'logout':
                $this->handleLogout($payload);
                break;
            case 'register':
                $this->handleRegister($payload);
                break;
            default:
                $this->handleGenericAction($action, $payload);
        }

        Log::info('User action event processed successfully', [
            'event' => $event,
            'action' => $action,
        ]);
    }

    /**
     * Handle login action.
     */
    private function handleLogin(array $payload): void
    {
        Log::info('User login detected', [
            'ip' => $payload['ip'] ?? 'unknown',
            'agent' => $payload['agent'] ?? 'unknown',
            'user_id' => $payload['user_id'] ?? null,
        ]);

        // Example: Track login statistics
        // Example: Send login notification
        // Example: Update last login timestamp
    }

    /**
     * Handle logout action.
     */
    private function handleLogout(array $payload): void
    {
        Log::info('User logout detected', [
            'user_id' => $payload['user_id'] ?? null,
        ]);
    }

    /**
     * Handle register action.
     */
    private function handleRegister(array $payload): void
    {
        Log::info('User registration detected', [
            'email' => $payload['email'] ?? null,
        ]);

        // Example: Send welcome email
        // Example: Track registration metrics
    }

    /**
     * Handle generic action.
     */
    private function handleGenericAction(?string $action, array $payload): void
    {
        Log::info('Generic user action detected', [
            'action' => $action,
            'payload' => $payload,
        ]);
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
