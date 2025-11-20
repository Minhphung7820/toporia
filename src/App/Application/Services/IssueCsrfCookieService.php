<?php

declare(strict_types=1);

namespace App\Application\Services;

use Toporia\Framework\Security\Contracts\CsrfTokenManagerInterface;
use Toporia\Framework\Http\CookieJar;

/**
 * Issue CSRF Cookie Service
 *
 * Application Service for issuing CSRF cookies to SPA clients.
 * Issues CSRF cookie for SPA authentication.
 *
 * Clean Architecture:
 * - Application layer service
 * - Encapsulates business logic for CSRF cookie issuance
 * - Depends on framework abstractions (interfaces)
 *
 * SOLID Principles:
 * - Single Responsibility: Only issues CSRF cookies
 * - Dependency Inversion: Depends on interfaces, not implementations
 * - Open/Closed: Extensible via service composition
 *
 * Performance:
 * - O(1) token generation
 * - Cookie-based (faster than session lookups)
 * - Minimal overhead
 */
final class IssueCsrfCookieService
{
    /**
     * CSRF cookie name (standard SPA CSRF cookie name)
     */
    private const CSRF_COOKIE_NAME = 'XSRF-TOKEN';

    /**
     * CSRF cookie lifetime in minutes (2 hours)
     */
    private const CSRF_COOKIE_LIFETIME = 120;

    public function __construct(
        private readonly CsrfTokenManagerInterface $tokenManager,
        private readonly CookieJar $cookieJar,
        private readonly bool $isProduction = false
    ) {}

    /**
     * Issue a CSRF cookie for SPA authentication.
     *
     * Generates a new CSRF token and sets it as an HttpOnly cookie.
     * The cookie will be automatically sent with subsequent requests.
     *
     * Performance: O(1) - Direct token generation and cookie queue
     *
     * @return array{success: bool, message: string}
     */
    public function execute(): array
    {
        // Generate or get existing CSRF token
        $token = $this->tokenManager->getToken('_token');
        if ($token === null) {
            $token = $this->tokenManager->generate('_token');
        }

        // Set CSRF token as cookie (HttpOnly, SameSite=Lax for SPA)
        // Note: Cookie name is XSRF-TOKEN (standard SPA CSRF cookie name)
        // Frontend will read this cookie and send it as X-XSRF-TOKEN header
        $this->cookieJar->make(
            self::CSRF_COOKIE_NAME,
            $token,
            self::CSRF_COOKIE_LIFETIME,
            [
                'path' => '/',
                'httpOnly' => false, // Must be false so JavaScript can read it
                'sameSite' => 'Lax', // Lax allows same-site requests
                'secure' => $this->isProduction, // HTTPS only in production
            ]
        );

        return [
            'success' => true,
            'message' => 'CSRF cookie issued successfully'
        ];
    }
}
