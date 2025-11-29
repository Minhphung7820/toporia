<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook\Contracts;

use Toporia\Framework\Http\Request;

/**
 * Webhook Receiver Interface
 *
 * Contract for receiving and processing incoming webhooks.
 * Handles signature verification, event routing, and processing.
 */
interface WebhookReceiverInterface
{
    /**
     * Verify webhook signature.
     *
     * @param Request $request HTTP request
     * @param string $secret Secret key for verification
     * @param string $algorithm Signature algorithm (sha256, sha1, etc.)
     * @return bool True if signature is valid
     */
    public function verifySignature(Request $request, string $secret, string $algorithm = 'sha256'): bool;

    /**
     * Process incoming webhook.
     *
     * @param Request $request HTTP request
     * @param string $secret Secret key
     * @param callable|null $handler Optional handler callback
     * @return array<string, mixed> Processed webhook data
     */
    public function process(Request $request, string $secret, ?callable $handler = null): array;

    /**
     * Extract event name from request.
     *
     * @param Request $request HTTP request
     * @return string Event name
     */
    public function extractEvent(Request $request): string;

    /**
     * Extract payload from request.
     *
     * @param Request $request HTTP request
     * @return array<string, mixed> Payload data
     */
    public function extractPayload(Request $request): array;
}

