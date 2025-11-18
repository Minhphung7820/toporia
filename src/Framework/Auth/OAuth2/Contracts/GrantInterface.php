<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Contracts;

use Toporia\Framework\Http\Request;

/**
 * OAuth2 Grant Interface
 *
 * Contract for OAuth2 grant type implementations.
 * Each grant type (authorization_code, client_credentials, password, refresh_token) implements this interface.
 *
 * Clean Architecture:
 * - Strategy Pattern: Different grant types are interchangeable strategies
 * - Open/Closed: Add new grant types without modifying server
 *
 * SOLID Principles:
 * - S: Each grant handles one authentication flow
 * - O: Extensible via new grant implementations
 * - L: All grants are interchangeable
 * - I: Focused interface for grant operations
 */
interface GrantInterface
{
    /**
     * Get grant type identifier.
     *
     * @return string Grant type (e.g., 'authorization_code', 'client_credentials')
     */
    public function getIdentifier(): string;

    /**
     * Issue an access token for this grant type.
     *
     * @param Request $request HTTP request
     * @param ClientInterface $client OAuth2 client
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope?: string}
     * @throws \RuntimeException If token issuance fails
     */
    public function issueToken(Request $request, ClientInterface $client): array;
}
