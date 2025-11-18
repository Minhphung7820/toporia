<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing;

/**
 * Test Response
 *
 * Represents an HTTP response for testing purposes.
 *
 * Performance:
 * - O(1) property access
 * - Lazy content parsing
 */
final class TestResponse
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';
    private ?array $json = null;

    public function __construct(
        private string $method,
        private string $uri,
        private array $data = [],
        array $headers = []
    ) {
        $this->headers = $headers;
    }

    /**
     * Get response status code.
     *
     * Performance: O(1)
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * Set response status code.
     */
    public function setStatus(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get response content.
     *
     * Performance: O(1)
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Set response content.
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->json = null; // Reset JSON cache
        return $this;
    }

    /**
     * Get response as JSON array.
     *
     * Performance: O(N) where N = JSON size (cached after first call)
     */
    public function json(): array
    {
        if ($this->json === null) {
            $this->json = json_decode($this->content, true) ?? [];
        }

        return $this->json;
    }

    /**
     * Get response headers.
     *
     * Performance: O(1)
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header.
     *
     * Performance: O(1)
     */
    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
}

