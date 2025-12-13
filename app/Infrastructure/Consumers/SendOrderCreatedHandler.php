<?php

declare(strict_types=1);

namespace App\Infrastructure\Consumers;

use Toporia\Framework\Realtime\Consumer\AbstractConsumerHandler;
use Toporia\Framework\Realtime\Consumer\Contracts\ConsumerContext;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Support\Accessors\Log;

/**
 * SendOrderCreatedHandler
 *
 * Example consumer handler for processing order.created events.
 * Demonstrates how to:
 * - Subscribe to specific channels
 * - Process message data
 * - Handle errors with retry logic
 * - Log operations
 *
 * Usage:
 *   php console broker:handler:consume --handler=SendOrderCreated --driver=rabbitmq
 *
 * Test by publishing:
 *   php console realtime:publish orders.created '{"order_id":123,"email":"test@example.com"}' --driver=rabbitmq
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/app
 */
final class SendOrderCreatedHandler extends AbstractConsumerHandler
{
    /**
     * Channels to subscribe.
     * Supports wildcards: orders.* (for RabbitMQ topic exchange)
     */
    protected array $channels = ['orders.created', 'orders.*'];

    /**
     * Preferred broker driver.
     * null = use default from config.
     */
    protected ?string $driver = 'rabbitmq';

    /**
     * Consumer group for Kafka.
     */
    protected ?string $consumerGroup = 'order-notification-group';

    /**
     * Maximum retry attempts.
     */
    protected int $maxRetries = 3;

    /**
     * Base retry delay in milliseconds.
     */
    protected int $retryDelay = 1000;

    /**
     * Use exponential backoff for retries.
     */
    protected bool $exponentialBackoff = true;

    /**
     * Handle incoming message.
     *
     * @param MessageInterface $message The incoming message
     * @param ConsumerContext $context Consumer context with metadata
     * @return void
     * @throws \RuntimeException If processing fails
     */
    public function handle(MessageInterface $message, ConsumerContext $context): void
    {
        $data = $this->getData($message);

        $orderId = $data['order_id'] ?? null;
        $email = $data['email'] ?? null;

        // Validate required data
        if ($orderId === null) {
            $this->warning("Missing order_id in message", [
                'message_id' => $message->getId(),
                'data' => $data,
            ]);
            return;
        }

        $this->info("Processing order notification", [
            'order_id' => $orderId,
            'email' => $email,
            'attempt' => $context->attempt,
        ]);

        // Simulate business logic
        // In real application, you would:
        // 1. Load order from database
        // 2. Send email notification
        // 3. Push to queue for additional processing
        // 4. Broadcast realtime event to clients

        try {
            // Example: Save to database
            // $this->orderRepository->markNotificationSent($orderId);

            // Example: Send email
            // if ($email) {
            //     dispatch(new SendOrderConfirmationEmail($orderId, $email));
            // }

            // Example: Broadcast to clients
            // $this->realtime->to("user.{$userId}")->emit('order.confirmed', [
            //     'order_id' => $orderId,
            // ]);

            // For demo, just log success
            $this->info("Order notification processed successfully", [
                'order_id' => $orderId,
                'email' => $email,
            ]);

        } catch (\Throwable $e) {
            $this->error("Failed to process order notification", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Determine if this handler should process the message.
     *
     * @param MessageInterface $message
     * @return bool
     */
    public function shouldHandle(MessageInterface $message): bool
    {
        // Example: Skip messages without order_id
        $data = $message->getData();

        if (is_array($data) && isset($data['skip']) && $data['skip'] === true) {
            $this->debug("Message skipped due to skip flag");
            return false;
        }

        return true;
    }

    /**
     * Called when all retry attempts are exhausted.
     *
     * @param MessageInterface $message
     * @param \Throwable $exception
     * @param ConsumerContext $context
     * @return void
     */
    public function onFailed(MessageInterface $message, \Throwable $exception, ConsumerContext $context): void
    {
        parent::onFailed($message, $exception, $context);

        $data = $this->getData($message);
        $orderId = $data['order_id'] ?? 'unknown';

        // Example: Save to failed_jobs table for manual retry
        // FailedJob::create([
        //     'handler' => self::class,
        //     'payload' => json_encode($data),
        //     'exception' => $exception->getMessage(),
        //     'failed_at' => now(),
        // ]);

        // Example: Send alert
        Log::critical("Order notification permanently failed", [
            'order_id' => $orderId,
            'handler' => $this->getName(),
            'error' => $exception->getMessage(),
            'attempts' => $context->attempt,
        ]);
    }

    /**
     * Called when consumer starts.
     *
     * @param ConsumerContext $context
     * @return void
     */
    public function onStart(ConsumerContext $context): void
    {
        parent::onStart($context);

        // Example: Initialize resources
        // $this->cache->set('consumer:' . $context->handlerName . ':started', time());
    }

    /**
     * Called when consumer stops.
     *
     * @param ConsumerContext $context
     * @return void
     */
    public function onStop(ConsumerContext $context): void
    {
        parent::onStop($context);

        // Example: Cleanup resources
        // $this->cache->delete('consumer:' . $context->handlerName . ':started');
    }
}
