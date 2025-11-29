<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail\Transport;

/**
 * Transport Exception
 *
 * Thrown when mail transport operations fail.
 */
class TransportException extends \RuntimeException
{
    /**
     * @param string $message Error message.
     * @param string $transport Transport name.
     * @param array<string, mixed> $context Additional context.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        private readonly string $transport = '',
        private readonly array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get transport name.
     *
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * Get additional context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create from API error.
     *
     * @param string $transport Transport name.
     * @param int $statusCode HTTP status code.
     * @param string $response API response.
     * @return self
     */
    public static function fromApiError(string $transport, int $statusCode, string $response): self
    {
        return new self(
            message: "API request failed with status {$statusCode}",
            transport: $transport,
            context: [
                'status_code' => $statusCode,
                'response' => $response,
            ]
        );
    }

    /**
     * Create connection error.
     *
     * @param string $transport Transport name.
     * @param string $host Host that failed to connect.
     * @param \Throwable|null $previous Previous exception.
     * @return self
     */
    public static function connectionFailed(string $transport, string $host, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Failed to connect to {$host}",
            transport: $transport,
            context: ['host' => $host],
            previous: $previous
        );
    }

    /**
     * Create authentication error.
     *
     * @param string $transport Transport name.
     * @return self
     */
    public static function authenticationFailed(string $transport): self
    {
        return new self(
            message: 'Authentication failed',
            transport: $transport
        );
    }
}
