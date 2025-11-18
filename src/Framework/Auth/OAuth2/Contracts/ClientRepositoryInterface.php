<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Contracts;

/**
 * OAuth2 Client Repository Interface
 *
 * Contract for managing OAuth2 clients (applications).
 *
 * Clean Architecture:
 * - Dependency Inversion: High-level modules depend on this abstraction
 * - Interface Segregation: Focused interface for client operations
 */
interface ClientRepositoryInterface
{
    /**
     * Find a client by ID and secret.
     *
     * @param string $clientId Client ID
     * @param string|null $clientSecret Client secret (null for public clients)
     * @return ClientInterface|null Client or null if not found/invalid
     */
    public function findForAuthentication(string $clientId, ?string $clientSecret): ?ClientInterface;

    /**
     * Find a client by ID.
     *
     * @param string $clientId Client ID
     * @return ClientInterface|null Client or null if not found
     */
    public function findById(string $clientId): ?ClientInterface;

    /**
     * Create a new client.
     *
     * @param string $name Client name
     * @param string $redirectUri Redirect URI
     * @param bool $isConfidential Whether client is confidential (has secret)
     * @param array<string> $scopes Allowed scopes
     * @return ClientInterface Created client
     */
    public function create(string $name, string $redirectUri, bool $isConfidential = true, array $scopes = []): ClientInterface;
}
