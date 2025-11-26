<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Contracts;


/**
 * Interface RequestInterface
 *
 * Contract defining the interface for RequestInterface implementations in
 * the HTTP request and response handling layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Http\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
interface RequestInterface
{
    /**
     * Get the HTTP method.
     *
     * @return string (GET, POST, PUT, PATCH, DELETE, etc.)
     */
    public function method(): string;

    /**
     * Get the request URI path.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get query parameter(s).
     *
     * @param string|null $key Specific key or null for all.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed;

    /**
     * Get input data (body/POST).
     *
     * @param string|null $key Specific key or null for all.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed;

    /**
     * Get a header value.
     *
     * @param string $name Header name.
     * @param string|null $default Default value.
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string;

    /**
     * Check if request is AJAX.
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * Check if request expects JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Check if the request is over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool;

    /**
     * Get the host from the request.
     *
     * @return string
     */
    public function host(): string;

    /**
     * Get the raw request body.
     *
     * @return string
     */
    public function raw(): string;

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function ip(): string;

    // ============================================================================
    // Advanced Request Methods (Enhanced Interface)
    // ============================================================================

    /**
     * Merge new input into the current request's input array.
     *
     * @param array<string, mixed> $input New input data to merge
     * @return self Fluent interface
     */
    public function merge(array $input): self;

    /**
     * Get a subset of the input data containing only the specified keys.
     *
     * @param array<string> $keys Keys to retrieve
     * @return array<string, mixed> Filtered input data
     */
    public function only(array $keys): array;

    /**
     * Get all input except specified keys.
     *
     * @param array<string> $keys Keys to exclude
     * @return array<string, mixed> Filtered input data
     */
    public function except(array $keys): array;

    /**
     * Check if the request has specific input key.
     *
     * @param string $key Input key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get input data with type casting and validation.
     *
     * @param string $key Input key
     * @param mixed $default Default value
     * @param string|null $type Expected type
     * @param callable|null $validator Optional validation callback
     * @return mixed Typed and validated value
     */
    public function typed(string $key, mixed $default = null, ?string $type = null, ?callable $validator = null): mixed;

    /**
     * Get request data with automatic sanitization.
     *
     * @param string $key Input key
     * @param mixed $default Default value
     * @param string $sanitizer Sanitization method
     * @return mixed Sanitized value
     */
    public function safe(string $key, mixed $default = null, string $sanitizer = 'html'): mixed;

    /**
     * Check if the request is from a mobile device.
     *
     * @return bool
     */
    public function isMobile(): bool;

    /**
     * Check if the request is from a bot/crawler.
     *
     * @return bool
     */
    public function isBot(): bool;

    /**
     * Get the request fingerprint for caching/security purposes.
     *
     * @param array<string> $includeHeaders Additional headers to include
     * @return string Unique request fingerprint
     */
    public function fingerprint(array $includeHeaders = []): string;

    /**
     * Get request signature for API authentication.
     *
     * @param string $secret Secret key for signing
     * @param string $algorithm Hash algorithm
     * @param array<string> $includeHeaders Headers to include in signature
     * @return string Request signature
     */
    public function signature(string $secret, string $algorithm = 'sha256', array $includeHeaders = []): string;

    /**
     * Verify request signature for API authentication.
     *
     * @param string $expectedSignature Expected signature
     * @param string $secret Secret key
     * @param string $algorithm Hash algorithm
     * @param array<string> $includeHeaders Headers to include
     * @return bool True if signature is valid
     */
    public function verifySignature(string $expectedSignature, string $secret, string $algorithm = 'sha256', array $includeHeaders = []): bool;

    /**
     * Convert request to array for logging/debugging.
     *
     * @param bool $includeSensitive Whether to include sensitive data
     * @return array<string, mixed> Request data array
     */
    public function toArray(bool $includeSensitive = false): array;
}
