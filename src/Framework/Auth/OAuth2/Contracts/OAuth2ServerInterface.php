<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Contracts;

use Toporia\Framework\Http\Request;

/**
 * OAuth2 Server Interface
 *
 * Contract for OAuth2 authorization server implementation.
 * Supports multiple grant types: authorization_code, client_credentials, password, refresh_token.
 *
 * Clean Architecture:
 * - Dependency Inversion: High-level modules depend on this abstraction
 * - Interface Segregation: Focused interface for OAuth2 operations
 *
 * SOLID Principles:
 * - I: Focused interface for OAuth2 server operations
 * - D: Depends on abstractions (Request, Response)
 */
interface OAuth2ServerInterface
{
    /**
     * Issue an access token.
     *
     * @param Request $request HTTP request
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope?: string}
     * @throws \RuntimeException If token issuance fails
     */
    public function issueAccessToken(Request $request): array;

    /**
     * Validate an access token.
     *
     * @param string $token Access token
     * @return array{client_id: string, user_id?: string, scopes: array<string>}|null Token data or null if invalid
     */
    public function validateAccessToken(string $token): ?array;

    /**
     * Revoke an access token.
     *
     * @param string $token Access token
     * @return bool True if revoked successfully
     */
    public function revokeAccessToken(string $token): bool;

    /**
     * Revoke a refresh token.
     *
     * @param string $token Refresh token
     * @return bool True if revoked successfully
     */
    public function revokeRefreshToken(string $token): bool;
}
