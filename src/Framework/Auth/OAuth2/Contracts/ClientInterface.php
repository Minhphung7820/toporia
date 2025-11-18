<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Contracts;

/**
 * OAuth2 Client Interface
 *
 * Contract for OAuth2 client (application) entities.
 */
interface ClientInterface
{
    /**
     * Get client ID.
     *
     * @return string Client ID
     */
    public function getId(): string;

    /**
     * Get client secret (hashed).
     *
     * @return string|null Hashed client secret or null for public clients
     */
    public function getSecret(): ?string;

    /**
     * Verify client secret.
     *
     * @param string $secret Plain text secret
     * @return bool True if secret matches
     */
    public function verifySecret(string $secret): bool;

    /**
     * Get redirect URI.
     *
     * @return string Redirect URI
     */
    public function getRedirectUri(): string;

    /**
     * Check if client is confidential (has secret).
     *
     * @return bool True if confidential
     */
    public function isConfidential(): bool;

    /**
     * Get allowed scopes.
     *
     * @return array<string> Allowed scopes
     */
    public function getScopes(): array;

    /**
     * Check if client has a specific scope.
     *
     * @param string $scope Scope name
     * @return bool True if has scope
     */
    public function hasScope(string $scope): bool;
}
