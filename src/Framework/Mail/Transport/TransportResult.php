<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail\Transport;

/**
 * Transport Result
 *
 * Immutable result object from mail transport operations.
 * Contains message ID, status, and provider-specific metadata.
 */
final readonly class TransportResult
{
    /**
     * @param bool $success Whether sending was successful.
     * @param string|null $messageId Provider message ID.
     * @param array<string, mixed> $metadata Provider-specific metadata.
     * @param string|null $error Error message if failed.
     */
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public array $metadata = [],
        public ?string $error = null
    ) {}

    /**
     * Create successful result.
     *
     * @param string $messageId Provider message ID.
     * @param array<string, mixed> $metadata Additional metadata.
     * @return self
     */
    public static function success(string $messageId, array $metadata = []): self
    {
        return new self(
            success: true,
            messageId: $messageId,
            metadata: $metadata
        );
    }

    /**
     * Create failed result.
     *
     * @param string $error Error message.
     * @param array<string, mixed> $metadata Additional metadata.
     * @return self
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata
        );
    }

    /**
     * Check if sending was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if sending failed.
     *
     * @return bool
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_id' => $this->messageId,
            'metadata' => $this->metadata,
            'error' => $this->error,
        ];
    }
}
