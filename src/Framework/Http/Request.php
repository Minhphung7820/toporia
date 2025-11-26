<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Http\Contracts\RequestInterface;

/**
 * HTTP Request implementation.
 *
 * Encapsulates all data from an incoming HTTP request including:
 * - HTTP method
 * - URI path
 * - Query parameters
 * - Request body/input
 * - Headers
 */
final class Request implements RequestInterface
{
    use \Toporia\Framework\Support\Macroable;
    /**
     * @var string HTTP method.
     */
    private string $method;

    /**
     * @var string Request URI path.
     */
    private string $path;

    /**
     * @var array<string, mixed> Query parameters.
     */
    private array $query;

    /**
     * @var array<string, mixed> Request body data.
     */
    private array $body;

    /**
     * @var array<string, string> Request headers.
     */
    private array $headers;

    /**
     * @var string Raw request body.
     */
    private string $rawBody;

    /**
     * @var array<string, mixed> Request attributes (for middleware/route data)
     */
    private array $attributes = [];

    /**
     * Create a Request instance from PHP globals.
     *
     * @return self
     */
    public static function capture(): self
    {
        $request = new self();

        // Method
        $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Path
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $request->path = rtrim($uri, '/') ?: '/';

        // Query parameters
        $request->query = $_GET ?? [];

        // Headers
        $request->headers = self::extractHeaders();

        // Raw body
        $request->rawBody = file_get_contents('php://input') ?: '';

        // Parse body based on content type
        $contentType = $request->headers['content-type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $request->body = json_decode($request->rawBody, true) ?: [];
        } else {
            $request->body = $_POST ?? [];
        }

        return $request;
    }

    /**
     * {@inheritdoc}
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return array_merge($this->query, $this->body);
        }

        // Check query parameters first, then body
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    /**
     * Get JSON data from request body.
     *
     * Convenience method for API requests that expect JSON payload.
     * Returns the parsed JSON body as an array.
     *
     * Performance: O(1) - Returns already parsed body
     *
     * @return array<string, mixed> Parsed JSON data
     */
    public function json(): array
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    /**
     * {@inheritdoc}
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    /**
     * Check if the request is over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        // Check HTTPS server variable
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Check forwarded protocol header (proxy/load balancer)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Check standard port
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * Get the host from the request.
     *
     * @return string
     */
    public function host(): string
    {
        // Check forwarded host header first (proxy/load balancer)
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
            return trim($hosts[0]);
        }

        // Check standard host header
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }

        // Fallback to server name
        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * {@inheritdoc}
     */
    public function raw(): string
    {
        return $this->rawBody;
    }

    /**
     * Get client IP address.
     *
     * Checks common proxy headers for the real client IP.
     * Falls back to REMOTE_ADDR if no proxy headers found.
     *
     * @return string Client IP address.
     */
    public function ip(): string
    {
        // Check proxy headers (in order of priority)
        $proxyHeaders = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2...)
                // Take the first one (the original client)
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Extract headers from $_SERVER superglobal.
     *
     * @return array<string, string>
     */
    private static function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // HTTP_ prefix headers
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
                continue;
            }

            // Common headers without HTTP_ prefix
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Check if the request has specific input key.
     *
     * @param string $key Input key.
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key]);
    }

    /**
     * Get all input data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Get only specified input keys.
     *
     * @param array<string> $keys Keys to retrieve.
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    /**
     * Get all input except specified keys.
     *
     * @param array<string> $keys Keys to exclude.
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->body, array_flip($keys));
    }

    /**
     * Set a request attribute.
     *
     * Attributes are used to store additional data about the request
     * (e.g., route handler, route parameters, etc.)
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get a request attribute.
     *
     * @param string $key Attribute key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all request attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get data from GET request (query parameters).
     *
     * @param string|null $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Get data from POST request body.
     *
     * @param string|null $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Get data from PUT request body.
     *
     * @param string|null $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function put(?string $key = null, mixed $default = null): mixed
    {
        if ($this->method !== 'PUT') {
            return $default;
        }

        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Get data from PATCH request body.
     *
     * @param string|null $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function patch(?string $key = null, mixed $default = null): mixed
    {
        if ($this->method !== 'PATCH') {
            return $default;
        }

        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Get data from DELETE request body.
     *
     * @param string|null $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function delete(?string $key = null, mixed $default = null): mixed
    {
        if ($this->method !== 'DELETE') {
            return $default;
        }

        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Check if the request is a GET request.
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Check if the request is a POST request.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Check if the request is a PUT request.
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Check if the request is a PATCH request.
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    /**
     * Check if the request is a DELETE request.
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Check if the request is a HEAD request.
     *
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->method === 'HEAD';
    }

    /**
     * Check if the request is an OPTIONS request.
     *
     * @return bool
     */
    public function isOptions(): bool
    {
        return $this->method === 'OPTIONS';
    }

    // ============================================================================
    // Advanced Request Data Manipulation (Laravel-compatible + Enhanced)
    // ============================================================================

    /**
     * Merge new input into the current request's input array.
     *
     * This method allows you to add or override input data dynamically.
     * Useful for middleware that needs to inject computed values or
     * normalize input data before it reaches the controller.
     *
     * Performance: O(n) where n = number of keys to merge
     * Memory: Minimal overhead, modifies existing arrays in-place
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles input merging
     * - Open/Closed: Extensible via array operations
     * - Interface Segregation: Focused method signature
     *
     * Clean Architecture:
     * - Maintains request state integrity
     * - Allows controlled mutation for middleware layer
     * - Preserves original data structure
     *
     * @param array<string, mixed> $input New input data to merge
     * @return $this Fluent interface for method chaining
     *
     * @example
     * ```php
     * // Middleware adding computed values
     * $request->merge([
     *     'user_id' => auth()->id(),
     *     'ip_address' => $request->ip(),
     *     'timestamp' => time()
     * ]);
     *
     * // Normalizing input data
     * $request->merge([
     *     'email' => strtolower($request->input('email')),
     *     'phone' => preg_replace('/[^0-9]/', '', $request->input('phone'))
     * ]);
     *
     * // Chaining operations
     * $request->merge(['step' => 1])
     *         ->merge(['validated' => true]);
     * ```
     */
    public function merge(array $input): self
    {
        // Merge into body (POST data) for consistency with Laravel
        $this->body = array_merge($this->body, $input);

        return $this;
    }

    /**
     * Merge new input into the request's query parameters.
     *
     * Similar to merge() but specifically targets query parameters (GET data).
     * Useful for adding computed query parameters or normalizing URLs.
     *
     * Performance: O(n) where n = number of query parameters
     *
     * @param array<string, mixed> $query New query parameters to merge
     * @return $this Fluent interface
     *
     * @example
     * ```php
     * // Add pagination defaults
     * $request->mergeQuery([
     *     'page' => $request->query('page', 1),
     *     'per_page' => $request->query('per_page', 15)
     * ]);
     * ```
     */
    public function mergeQuery(array $query): self
    {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Replace the entire input array.
     *
     * Completely replaces the request body with new data.
     * Use with caution as this discards all existing input.
     *
     * Performance: O(1) - Direct array assignment
     *
     * @param array<string, mixed> $input New input data
     * @return $this Fluent interface
     */
    public function replace(array $input): self
    {
        $this->body = $input;

        return $this;
    }

    /**
     * Remove specific keys from the request input.
     *
     * Efficiently removes unwanted input keys, useful for filtering
     * sensitive data or cleaning up input before processing.
     *
     * Performance: O(n) where n = number of keys to remove
     *
     * @param array<string>|string $keys Key(s) to remove
     * @return $this Fluent interface
     *
     * @example
     * ```php
     * // Remove sensitive fields
     * $request->forget(['password_confirmation', '_token']);
     *
     * // Remove single field
     * $request->forget('temp_data');
     * ```
     */
    public function forget(array|string $keys): self
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            unset($this->body[$key]);
        }

        return $this;
    }

    /**
     * Get a subset of the input data containing only the specified keys.
     *
     * Enhanced version with support for nested keys and default values.
     * More flexible than the existing only() method.
     *
     * Performance: O(n) where n = number of keys requested
     *
     * @param array<string> $keys Keys to retrieve
     * @param array<string, mixed> $defaults Default values for missing keys
     * @return array<string, mixed> Filtered input data
     *
     * @example
     * ```php
     * // Basic usage
     * $data = $request->onlyWithDefaults(['name', 'email'], [
     *     'name' => 'Anonymous',
     *     'email' => 'noreply@example.com'
     * ]);
     *
     * // Nested key support (dot notation)
     * $data = $request->onlyWithDefaults(['user.name', 'user.email']);
     * ```
     */
    public function onlyWithDefaults(array $keys, array $defaults = []): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (str_contains($key, '.')) {
                // Handle nested keys (dot notation)
                $value = $this->getNestedValue($key);
                if ($value !== null) {
                    $result[$key] = $value;
                } elseif (isset($defaults[$key])) {
                    $result[$key] = $defaults[$key];
                }
            } else {
                // Handle simple keys
                if (isset($this->body[$key])) {
                    $result[$key] = $this->body[$key];
                } elseif (isset($defaults[$key])) {
                    $result[$key] = $defaults[$key];
                }
            }
        }

        return $result;
    }

    /**
     * Get nested value using dot notation.
     *
     * Performance-optimized nested array access with caching.
     *
     * @param string $key Dot-notated key (e.g., 'user.profile.name')
     * @return mixed|null Value or null if not found
     */
    private function getNestedValue(string $key): mixed
    {
        static $cache = [];

        // Cache key for performance
        $cacheKey = md5($key . serialize($this->body));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $keys = explode('.', $key);
        $value = $this->body;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $cache[$cacheKey] = null;
                return null;
            }
            $value = $value[$segment];
        }

        $cache[$cacheKey] = $value;
        return $value;
    }

    /**
     * Check if the request contains any of the given keys.
     *
     * Enhanced version with support for nested keys and multiple conditions.
     *
     * Performance: O(n) where n = number of keys to check
     *
     * @param array<string>|string $keys Key(s) to check
     * @param bool $requireAll Whether all keys must be present (AND logic)
     * @return bool True if condition is met
     *
     * @example
     * ```php
     * // Check if any key exists (OR logic)
     * if ($request->hasAny(['name', 'email'])) {
     *     // At least one field is present
     * }
     *
     * // Check if all keys exist (AND logic)
     * if ($request->hasAny(['name', 'email'], true)) {
     *     // Both fields are present
     * }
     * ```
     */
    public function hasAny(array|string $keys, bool $requireAll = false): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $foundCount = 0;

        foreach ($keys as $key) {
            $exists = str_contains($key, '.')
                ? $this->getNestedValue($key) !== null
                : isset($this->body[$key]);

            if ($exists) {
                $foundCount++;
                if (!$requireAll) {
                    return true; // Early return for OR logic
                }
            }
        }

        return $requireAll ? $foundCount === count($keys) : $foundCount > 0;
    }

    /**
     * Get input data with type casting and validation.
     *
     * Enhanced input retrieval with built-in type casting and validation.
     * Provides better type safety and reduces boilerplate code.
     *
     * Performance: O(1) with optional validation overhead
     *
     * @param string $key Input key
     * @param mixed $default Default value
     * @param string|null $type Expected type ('int', 'float', 'bool', 'string', 'array')
     * @param callable|null $validator Optional validation callback
     * @return mixed Typed and validated value
     *
     * @throws \InvalidArgumentException If validation fails
     *
     * @example
     * ```php
     * // Type casting
     * $age = $request->typed('age', 0, 'int');
     * $price = $request->typed('price', 0.0, 'float');
     * $active = $request->typed('is_active', false, 'bool');
     *
     * // With validation
     * $email = $request->typed('email', '', 'string', function($value) {
     *     return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
     * });
     * ```
     */
    public function typed(string $key, mixed $default = null, ?string $type = null, ?callable $validator = null): mixed
    {
        $value = $this->input($key, $default);

        // Apply type casting
        if ($type !== null && $value !== null) {
            $value = match ($type) {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                'string' => (string) $value,
                'array' => is_array($value) ? $value : [$value],
                default => $value
            };
        }

        // Apply validation
        if ($validator !== null && !$validator($value)) {
            throw new \InvalidArgumentException("Validation failed for input key: {$key}");
        }

        return $value;
    }

    /**
     * Get multiple input values with type casting.
     *
     * Batch version of typed() for better performance when retrieving
     * multiple typed values.
     *
     * Performance: O(n) where n = number of keys
     *
     * @param array<string, array{default?: mixed, type?: string, validator?: callable}> $specs
     * @return array<string, mixed> Typed values
     *
     * @example
     * ```php
     * $data = $request->typedMany([
     *     'age' => ['default' => 0, 'type' => 'int'],
     *     'price' => ['default' => 0.0, 'type' => 'float'],
     *     'name' => ['default' => '', 'type' => 'string'],
     *     'tags' => ['default' => [], 'type' => 'array']
     * ]);
     * ```
     */
    public function typedMany(array $specs): array
    {
        $result = [];

        foreach ($specs as $key => $spec) {
            $default = $spec['default'] ?? null;
            $type = $spec['type'] ?? null;
            $validator = $spec['validator'] ?? null;

            $result[$key] = $this->typed($key, $default, $type, $validator);
        }

        return $result;
    }

    /**
     * Check if the request is from a mobile device.
     *
     * Enhanced mobile detection with caching for better performance.
     *
     * Performance: O(1) after first call (cached result)
     *
     * @return bool True if mobile device
     */
    public function isMobile(): bool
    {
        static $isMobile = null;

        if ($isMobile !== null) {
            return $isMobile;
        }

        $userAgent = $this->header('user-agent', '');

        // Common mobile patterns (optimized regex)
        $mobilePatterns = [
            '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i'
        ];

        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $isMobile = true;
            }
        }

        return $isMobile = false;
    }

    /**
     * Check if the request is from a bot/crawler.
     *
     * Enhanced bot detection for SEO and analytics purposes.
     *
     * Performance: O(1) after first call (cached result)
     *
     * @return bool True if bot/crawler
     */
    public function isBot(): bool
    {
        static $isBot = null;

        if ($isBot !== null) {
            return $isBot;
        }

        $userAgent = strtolower($this->header('user-agent', ''));

        // Common bot patterns
        $botPatterns = [
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'facebookexternalhit',
            'twitterbot',
            'rogerbot',
            'linkedinbot',
            'embedly',
            'quora link preview',
            'showyoubot',
            'outbrain',
            'pinterest',
            'developers.google.com/+/web/snippet'
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return $isBot = true;
            }
        }

        return $isBot = false;
    }

    /**
     * Get the request fingerprint for caching/security purposes.
     *
     * Creates a unique fingerprint based on request characteristics.
     * Useful for rate limiting, caching, and security analysis.
     *
     * Performance: O(1) - Hash calculation
     *
     * @param array<string> $includeHeaders Additional headers to include
     * @return string Unique request fingerprint
     */
    public function fingerprint(array $includeHeaders = []): string
    {
        $components = [
            $this->method(),
            $this->path(),
            $this->ip(),
            $this->header('user-agent', ''),
        ];

        // Include additional headers if specified
        foreach ($includeHeaders as $header) {
            $components[] = $this->header($header, '');
        }

        return hash('sha256', implode('|', $components));
    }

    /**
     * Get request timing information.
     *
     * Provides detailed timing information for performance monitoring.
     *
     * @return array<string, mixed> Timing information
     */
    public function timing(): array
    {
        static $startTime = null;

        if ($startTime === null) {
            $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        }

        $currentTime = microtime(true);

        return [
            'start_time' => $startTime,
            'current_time' => $currentTime,
            'elapsed_ms' => round(($currentTime - $startTime) * 1000, 2),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Convert request to array for logging/debugging.
     *
     * Enhanced debugging information with security considerations.
     *
     * @param bool $includeSensitive Whether to include sensitive data
     * @return array<string, mixed> Request data array
     */
    public function toArray(bool $includeSensitive = false): array
    {
        $data = [
            'method' => $this->method,
            'path' => $this->path,
            'query' => $this->query,
            'headers' => $this->headers,
            'ip' => $this->ip(),
            'user_agent' => $this->header('user-agent'),
            'is_ajax' => $this->isAjax(),
            'is_secure' => $this->isSecure(),
            'is_mobile' => $this->isMobile(),
            'is_bot' => $this->isBot(),
            'timing' => $this->timing(),
        ];

        if ($includeSensitive) {
            $data['body'] = $this->body;
            $data['raw_body'] = $this->rawBody;
        } else {
            // Filter out sensitive fields
            $sensitiveFields = ['password', 'token', 'secret', 'key', 'auth'];
            $filteredBody = $this->body;

            foreach ($sensitiveFields as $field) {
                if (isset($filteredBody[$field])) {
                    $filteredBody[$field] = '[FILTERED]';
                }
            }

            $data['body'] = $filteredBody;
        }

        return $data;
    }

    // ============================================================================
    // Advanced Request Validation & Transformation (Beyond Laravel)
    // ============================================================================

    /**
     * Validate and transform input data in a single operation.
     *
     * This method combines validation and transformation for maximum efficiency.
     * Reduces multiple passes over the data and provides better performance
     * than separate validation and transformation steps.
     *
     * Performance: O(n) single pass through data
     * Memory: Minimal overhead with in-place transformations
     *
     * SOLID Principles:
     * - Single Responsibility: Handles validation + transformation
     * - Open/Closed: Extensible via custom transformers
     * - Dependency Inversion: Uses callable transformers
     *
     * @param array<string, array{rules?: array, transform?: callable, default?: mixed}> $specs
     * @return array<string, mixed> Validated and transformed data
     * @throws \InvalidArgumentException If validation fails
     *
     * @example
     * ```php
     * $data = $request->validateAndTransform([
     *     'email' => [
     *         'rules' => ['required', 'email'],
     *         'transform' => fn($value) => strtolower(trim($value))
     *     ],
     *     'age' => [
     *         'rules' => ['required', 'integer', 'min:18'],
     *         'transform' => fn($value) => (int) $value,
     *         'default' => 18
     *     ],
     *     'tags' => [
     *         'transform' => fn($value) => is_string($value) ? explode(',', $value) : $value,
     *         'default' => []
     *     ]
     * ]);
     * ```
     */
    public function validateAndTransform(array $specs): array
    {
        $result = [];
        $errors = [];

        foreach ($specs as $key => $spec) {
            $value = $this->input($key, $spec['default'] ?? null);

            // Apply validation rules if specified
            if (isset($spec['rules'])) {
                $validationResult = $this->validateValue($key, $value, $spec['rules']);
                if ($validationResult !== true) {
                    $errors[$key] = $validationResult;
                    continue;
                }
            }

            // Apply transformation if specified
            if (isset($spec['transform']) && is_callable($spec['transform'])) {
                try {
                    $value = $spec['transform']($value);
                } catch (\Throwable $e) {
                    $errors[$key] = "Transformation failed: {$e->getMessage()}";
                    continue;
                }
            }

            $result[$key] = $value;
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        return $result;
    }

    /**
     * Simple validation for a single value.
     *
     * Lightweight validation without external dependencies.
     * Performance-optimized for common validation scenarios.
     *
     * @param string $key Field name for error messages
     * @param mixed $value Value to validate
     * @param array<string> $rules Validation rules
     * @return true|string True if valid, error message if invalid
     */
    private function validateValue(string $key, mixed $value, array $rules): true|string
    {
        foreach ($rules as $rule) {
            $result = match (true) {
                $rule === 'required' => $value !== null && $value !== '',
                $rule === 'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                $rule === 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                $rule === 'numeric' => is_numeric($value),
                str_starts_with($rule, 'min:') => $this->validateMin($value, substr($rule, 4)),
                str_starts_with($rule, 'max:') => $this->validateMax($value, substr($rule, 4)),
                str_starts_with($rule, 'regex:') => preg_match(substr($rule, 6), (string) $value) === 1,
                default => true
            };

            if (!$result) {
                return "Field {$key} failed validation rule: {$rule}";
            }
        }

        return true;
    }

    /**
     * Validate minimum value/length.
     */
    private function validateMin(mixed $value, string $min): bool
    {
        $minValue = (float) $min;

        return match (true) {
            is_numeric($value) => (float) $value >= $minValue,
            is_string($value) => strlen($value) >= $minValue,
            is_array($value) => count($value) >= $minValue,
            default => false
        };
    }

    /**
     * Validate maximum value/length.
     */
    private function validateMax(mixed $value, string $max): bool
    {
        $maxValue = (float) $max;

        return match (true) {
            is_numeric($value) => (float) $value <= $maxValue,
            is_string($value) => strlen($value) <= $maxValue,
            is_array($value) => count($value) <= $maxValue,
            default => false
        };
    }

    /**
     * Batch process multiple requests data.
     *
     * Process multiple sets of input data with the same transformation rules.
     * Useful for bulk operations and API batch processing.
     *
     * Performance: O(n*m) where n = datasets, m = transformations per dataset
     *
     * @param array<array<string, mixed>> $datasets Multiple input datasets
     * @param array<string, callable> $transformers Field transformers
     * @return array<array<string, mixed>> Processed datasets
     *
     * @example
     * ```php
     * $results = $request->batchProcess([
     *     ['name' => 'John', 'email' => 'JOHN@EXAMPLE.COM'],
     *     ['name' => 'Jane', 'email' => 'JANE@EXAMPLE.COM']
     * ], [
     *     'email' => fn($email) => strtolower($email),
     *     'name' => fn($name) => ucfirst($name)
     * ]);
     * ```
     */
    public function batchProcess(array $datasets, array $transformers): array
    {
        return array_map(function ($dataset) use ($transformers) {
            foreach ($transformers as $field => $transformer) {
                if (isset($dataset[$field])) {
                    $dataset[$field] = $transformer($dataset[$field]);
                }
            }
            return $dataset;
        }, $datasets);
    }

    /**
     * Get request data with automatic sanitization.
     *
     * Provides built-in XSS protection and data sanitization.
     * More secure than raw input() method.
     *
     * Performance: O(1) with sanitization overhead
     *
     * @param string $key Input key
     * @param mixed $default Default value
     * @param string $sanitizer Sanitization method ('html', 'sql', 'xss', 'none')
     * @return mixed Sanitized value
     */
    public function safe(string $key, mixed $default = null, string $sanitizer = 'html'): mixed
    {
        $value = $this->input($key, $default);

        if ($value === null || $sanitizer === 'none') {
            return $value;
        }

        return match ($sanitizer) {
            'html' => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'sql' => addslashes((string) $value),
            'xss' => $this->sanitizeXss((string) $value),
            'strip_tags' => strip_tags((string) $value),
            'trim' => trim((string) $value),
            default => $value
        };
    }

    /**
     * Advanced XSS sanitization.
     *
     * More comprehensive XSS protection than basic htmlspecialchars.
     *
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeXss(string $value): string
    {
        // Remove potentially dangerous tags and attributes
        $dangerous = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload/i',
            '/onerror/i',
            '/onclick/i',
            '/onmouseover/i'
        ];

        $value = preg_replace($dangerous, '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Get request data with caching for expensive operations.
     *
     * Caches processed input data to avoid repeated expensive operations
     * like JSON parsing, validation, or transformation.
     *
     * Performance: O(1) after first access (cached)
     *
     * @param string $key Cache key
     * @param callable $processor Data processor function
     * @param int $ttl Cache TTL in seconds (0 = no expiration)
     * @return mixed Processed and cached data
     */
    public function cached(string $key, callable $processor, int $ttl = 0): mixed
    {
        static $cache = [];
        static $expiry = [];

        $now = time();

        // Check if cached and not expired
        if (isset($cache[$key])) {
            if ($ttl === 0 || !isset($expiry[$key]) || $expiry[$key] > $now) {
                return $cache[$key];
            }
        }

        // Process and cache
        $result = $processor();
        $cache[$key] = $result;

        if ($ttl > 0) {
            $expiry[$key] = $now + $ttl;
        }

        return $result;
    }

    /**
     * Stream large request data for memory efficiency.
     *
     * Process large request bodies without loading everything into memory.
     * Useful for file uploads or large JSON payloads.
     *
     * Performance: O(1) memory usage regardless of input size
     *
     * @param callable $processor Chunk processor function
     * @param int $chunkSize Chunk size in bytes
     * @return mixed Processor result
     */
    public function stream(callable $processor, int $chunkSize = 8192): mixed
    {
        $handle = fopen('php://input', 'r');
        if (!$handle) {
            throw new \RuntimeException('Unable to open input stream');
        }

        try {
            $result = null;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk !== false) {
                    $result = $processor($chunk, $result);
                }
            }
            return $result;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get request signature for API authentication.
     *
     * Generate request signature for secure API authentication.
     * Compatible with AWS Signature V4 style authentication.
     *
     * Performance: O(1) - Hash calculation
     *
     * @param string $secret Secret key for signing
     * @param string $algorithm Hash algorithm ('sha256', 'sha1', etc.)
     * @param array<string> $includeHeaders Headers to include in signature
     * @return string Request signature
     */
    public function signature(string $secret, string $algorithm = 'sha256', array $includeHeaders = []): string
    {
        $components = [
            $this->method(),
            $this->path(),
            http_build_query($this->query),
            $this->rawBody
        ];

        // Include specified headers
        foreach ($includeHeaders as $header) {
            $components[] = $this->header($header, '');
        }

        $stringToSign = implode("\n", $components);

        return hash_hmac($algorithm, $stringToSign, $secret);
    }

    /**
     * Verify request signature for API authentication.
     *
     * @param string $expectedSignature Expected signature
     * @param string $secret Secret key
     * @param string $algorithm Hash algorithm
     * @param array<string> $includeHeaders Headers to include
     * @return bool True if signature is valid
     */
    public function verifySignature(string $expectedSignature, string $secret, string $algorithm = 'sha256', array $includeHeaders = []): bool
    {
        $actualSignature = $this->signature($secret, $algorithm, $includeHeaders);

        // Use hash_equals for timing-safe comparison
        return hash_equals($expectedSignature, $actualSignature);
    }

    /**
     * Get request rate limiting key.
     *
     * Generate a unique key for rate limiting based on various factors.
     *
     * @param string $scope Rate limiting scope ('ip', 'user', 'api_key', 'custom')
     * @param string|null $identifier Custom identifier
     * @return string Rate limiting key
     */
    public function rateLimitKey(string $scope = 'ip', ?string $identifier = null): string
    {
        return match ($scope) {
            'ip' => 'rate_limit:ip:' . $this->ip(),
            'user' => 'rate_limit:user:' . ($identifier ?? 'anonymous'),
            'api_key' => 'rate_limit:api:' . ($identifier ?? $this->header('x-api-key', 'unknown')),
            'endpoint' => 'rate_limit:endpoint:' . $this->method() . ':' . $this->path(),
            'custom' => 'rate_limit:custom:' . ($identifier ?? $this->fingerprint()),
            default => 'rate_limit:global:' . $this->fingerprint()
        };
    }

    /**
     * Check if request should be cached based on various factors.
     *
     * Intelligent caching decision based on request characteristics.
     *
     * @return bool True if request should be cached
     */
    public function shouldCache(): bool
    {
        // Don't cache if:
        // - Not a GET request
        // - Has query parameters that indicate dynamic content
        // - Is from a bot (different caching strategy needed)
        // - Has authentication headers

        if ($this->method !== 'GET') {
            return false;
        }

        if ($this->isBot()) {
            return false; // Bots might need different caching
        }

        if ($this->header('authorization') || $this->header('x-api-key')) {
            return false; // Authenticated requests
        }

        // Check for dynamic query parameters
        $dynamicParams = ['timestamp', 'rand', 'nocache', '_', 'cb'];
        foreach ($dynamicParams as $param) {
            if ($this->query($param) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get cache key for request caching.
     *
     * Generate a unique cache key based on request characteristics.
     *
     * @param array<string> $excludeParams Parameters to exclude from cache key
     * @return string Cache key
     */
    public function cacheKey(array $excludeParams = []): string
    {
        $query = $this->query;

        // Remove excluded parameters
        foreach ($excludeParams as $param) {
            unset($query[$param]);
        }

        // Sort for consistent keys
        ksort($query);

        $components = [
            $this->method(),
            $this->path(),
            http_build_query($query),
            $this->header('accept', ''),
            $this->header('accept-language', '')
        ];

        return 'request_cache:' . hash('sha256', implode('|', $components));
    }
}
