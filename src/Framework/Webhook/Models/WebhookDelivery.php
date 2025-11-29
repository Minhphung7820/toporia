<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Webhook Delivery Model
 *
 * Tracks webhook delivery attempts and results.
 */
final class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'endpoint_id',
        'event',
        'payload',
        'status_code',
        'response_body',
        'attempts',
        'succeeded_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'status_code' => 'integer',
        'response_body' => 'string',
        'attempts' => 'integer',
        'succeeded_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Relationship to webhook endpoint.
     *
     * @return \Toporia\Framework\Database\ORM\Relations\BelongsTo
     */
    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    /**
     * Mark delivery as succeeded.
     *
     * @param int $statusCode HTTP status code
     * @param string|null $responseBody Response body
     * @return void
     */
    public function markSucceeded(int $statusCode, ?string $responseBody = null): void
    {
        $this->update([
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'succeeded_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Mark delivery as failed.
     *
     * @param string $errorMessage Error message
     * @return void
     */
    public function markFailed(string $errorMessage): void
    {
        $this->increment('attempts');
        $this->update([
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }
}

