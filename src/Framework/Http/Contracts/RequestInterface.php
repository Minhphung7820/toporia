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
}
