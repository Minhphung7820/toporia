<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook\Contracts;

/**
 * Webhook Dispatcher Interface
 *
 * Contract for dispatching webhooks to external endpoints.
 * Supports retry, signature, and event-based dispatching.
 */
interface WebhookDispatcherInterface
{
    /**
     * Dispatch a webhook event.
     *
     * @param string $event Event name (e.g., 'user.created', 'order.updated')
     * @param mixed $payload Event payload data
     * @param array<string, string> $endpoints Target webhook endpoints
     * @param array<string, mixed> $options Additional options (secret, headers, etc.)
     * @return array<string, bool> Map of endpoint => success status
     */
    public function dispatch(string $event, mixed $payload, array $endpoints, array $options = []): array;

    /**
     * Dispatch webhook to a single endpoint.
     *
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param string $endpoint Target URL
     * @param array<string, mixed> $options Options (secret, headers, timeout, retry, etc.)
     * @return bool Success status
     */
    public function dispatchTo(string $event, mixed $payload, string $endpoint, array $options = []): bool;

    /**
     * Queue webhook for async dispatch.
     *
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param string $endpoint Target URL
     * @param array<string, mixed> $options Options
     * @return void
     */
    public function queue(string $event, mixed $payload, string $endpoint, array $options = []): void;
}

