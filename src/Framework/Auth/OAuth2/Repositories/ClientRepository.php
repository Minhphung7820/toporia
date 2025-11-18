<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Repositories;

use Toporia\Framework\Auth\OAuth2\Contracts\{ClientInterface, ClientRepositoryInterface};
use Toporia\Framework\Auth\OAuth2\Models\OAuth2Client;

/**
 * OAuth2 Client Repository
 *
 * Manages OAuth2 clients (applications).
 *
 * Performance:
 * - O(1) lookup by ID (indexed query)
 * - O(1) authentication check (indexed query)
 *
 * Clean Architecture:
 * - Infrastructure Layer: Database implementation of ClientRepositoryInterface
 * - Dependency Inversion: Implements domain contract
 *
 * SOLID Principles:
 * - S: Only manages OAuth2 clients
 * - I: Implements focused ClientRepositoryInterface
 * - D: Depends on ClientInterface abstraction
 */
final class ClientRepository implements ClientRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findForAuthentication(string $clientId, ?string $clientSecret): ?ClientInterface
    {
        $client = OAuth2Client::where('client_id', $clientId)->first();

        if ($client === null) {
            return null;
        }

        // Public clients don't require secret
        if (!$client->isConfidential()) {
            return $client;
        }

        // Confidential clients must provide and verify secret
        if ($clientSecret === null || !$client->verifySecret($clientSecret)) {
            return null;
        }

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $clientId): ?ClientInterface
    {
        return OAuth2Client::where('client_id', $clientId)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name, string $redirectUri, bool $isConfidential = true, array $scopes = []): ClientInterface
    {
        $clientId = OAuth2Client::generateClientId();
        $clientSecret = $isConfidential ? OAuth2Client::generateClientSecret() : null;
        $hashedSecret = $clientSecret !== null ? OAuth2Client::hashSecret($clientSecret) : null;

        $client = OAuth2Client::create([
            'name' => $name,
            'client_id' => $clientId,
            'client_secret' => $hashedSecret,
            'redirect_uri' => $redirectUri,
            'is_confidential' => $isConfidential,
            'scopes' => $scopes ?: ['*'], // Default to all scopes
        ]);

        // Store plain text secret temporarily for return (only in memory)
        // In real implementation, return NewClient object with plain text secret
        return $client;
    }
}
