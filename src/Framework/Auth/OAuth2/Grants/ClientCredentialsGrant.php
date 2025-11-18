<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Grants;

use Toporia\Framework\Auth\OAuth2\Contracts\ClientInterface;
use Toporia\Framework\Http\Request;

/**
 * Client Credentials Grant
 *
 * OAuth2 grant type for machine-to-machine authentication.
 * No user involved - client authenticates itself.
 *
 * Usage:
 * POST /oauth/token
 * grant_type=client_credentials
 * client_id=xxx
 * client_secret=xxx
 * scope=read write
 *
 * Performance: O(1) - Single token creation
 *
 * Clean Architecture:
 * - Strategy Pattern: One of multiple grant implementations
 * - Single Responsibility: Only handles client credentials flow
 *
 * SOLID Principles:
 * - S: Only handles client credentials grant
 * - O: Extensible via inheritance
 * - L: Interchangeable with other grants
 */
final class ClientCredentialsGrant extends AbstractGrant
{
    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'client_credentials';
    }

    /**
     * {@inheritdoc}
     */
    public function issueToken(Request $request, ClientInterface $client): array
    {
        // Client credentials grant doesn't require additional parameters
        $scopes = $this->getScopes($request, $client->getScopes());
        $expiresIn = $this->getExpiresIn($request, 3600); // 1 hour default

        // Validate requested scopes against client's allowed scopes
        $scopes = $this->validateScopes($scopes, $client);

        // Create access token (no refresh token for client credentials)
        $accessToken = $this->tokenRepository->createAccessToken(
            $client->getId(),
            null, // No user for client credentials
            $scopes,
            $expiresIn
        );

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => implode(' ', $scopes),
        ];
    }

    /**
     * Validate requested scopes against client's allowed scopes.
     *
     * @param array<string> $requestedScopes Requested scopes
     * @param ClientInterface $client OAuth2 client
     * @return array<string> Validated scopes
     */
    private function validateScopes(array $requestedScopes, ClientInterface $client): array
    {
        $allowedScopes = $client->getScopes();

        // If client allows all scopes (*), return requested scopes
        if (in_array('*', $allowedScopes, true)) {
            return $requestedScopes;
        }

        // Filter to only allowed scopes
        return array_filter($requestedScopes, function ($scope) use ($allowedScopes) {
            return in_array($scope, $allowedScopes, true);
        });
    }
}

