<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook;

use Toporia\Framework\Webhook\Contracts\WebhookDispatcherInterface;
use Toporia\Framework\Webhook\Models\{WebhookEndpoint, WebhookDelivery};

/**
 * Webhook Manager
 *
 * High-level manager for webhook operations.
 * Handles endpoint management, event dispatching, and delivery tracking.
 *
 * Performance:
 * - O(N) dispatch where N = number of active endpoints
 * - O(1) endpoint lookup (indexed)
 * - Batch operations for efficiency
 */
final class WebhookManager
{
    /**
     * @param WebhookDispatcherInterface $dispatcher Webhook dispatcher
     */
    public function __construct(
        private WebhookDispatcherInterface $dispatcher
    ) {}

    /**
     * Dispatch event to all matching endpoints.
     *
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param bool $async Dispatch asynchronously
     * @return array<string, bool> Map of endpoint URL => success status
     */
    public function dispatch(string $event, mixed $payload, bool $async = false): array
    {
        // Get active endpoints that should receive this event
        $endpoints = WebhookEndpoint::where('active', true)->get();

        $results = [];
        $endpointUrls = [];

        foreach ($endpoints as $endpoint) {
            if (!$endpoint->shouldReceive($event)) {
                continue;
            }

            $endpointUrls[] = $endpoint->url;

            $options = [
                'secret' => $endpoint->secret,
                'timeout' => $endpoint->timeout,
                'retry' => $endpoint->retry_count,
                'retry_delay' => $endpoint->retry_delay,
                'headers' => $endpoint->headers ?? [],
            ];

            if ($async) {
                $this->dispatcher->queue($event, $payload, $endpoint->url, $options);
                $results[$endpoint->url] = true; // Queued successfully
            } else {
                $success = $this->dispatcher->dispatchTo($event, $payload, $endpoint->url, $options);

                // Track delivery
                $this->trackDelivery($endpoint, $event, $payload, $success);

                $results[$endpoint->url] = $success;
            }
        }

        return $results;
    }

    /**
     * Track webhook delivery.
     *
     * @param WebhookEndpoint $endpoint Endpoint
     * @param string $event Event name
     * @param mixed $payload Payload
     * @param bool $success Success status
     * @return WebhookDelivery
     */
    private function trackDelivery(WebhookEndpoint $endpoint, string $event, mixed $payload, bool $success): WebhookDelivery
    {
        return WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => $payload,
            'succeeded_at' => $success ? now() : null,
            'failed_at' => $success ? null : now(),
            'error_message' => $success ? null : 'Delivery failed',
        ]);
    }
}

