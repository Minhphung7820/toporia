<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Webhook\WebhookManager;

/**
 * Webhook Facade
 *
 * Provides static access to webhook manager.
 *
 * @method static array dispatch(string $event, mixed $payload, bool $async = false)
 */
final class Webhook
{
    /**
     * Get webhook manager instance.
     *
     * @return WebhookManager
     */
    public static function getInstance(): WebhookManager
    {
        return Application::getInstance()->get('webhook');
    }

    /**
     * Dispatch webhook event.
     *
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param bool $async Dispatch asynchronously
     * @return array<string, bool> Map of endpoint URL => success status
     */
    public static function dispatch(string $event, mixed $payload, bool $async = false): array
    {
        return static::getInstance()->dispatch($event, $payload, $async);
    }
}

