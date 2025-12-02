<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * OAuth2 Access Token Model
 *
 * Represents an OAuth2 access token.
 *
 * Clean Architecture:
 * - Domain Entity: Represents OAuth2 token domain concept
 *
 * SOLID Principles:
 * - S: Only represents OAuth2 token data
 */
final class OAuth2AccessToken extends Model
{
    protected static string $table = 'oauth_access_tokens';
    protected static bool $timestamps = true;

    protected static array $fillable = [
        'token',
        'client_id',
        'user_id',
        'scopes',
        'expires_at',
        'revoked_at',
    ];

    protected static array $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Check if token is revoked.
     *
     * @return bool True if revoked
     */
    public function isRevoked(): bool
    {
        return $this->getAttribute('revoked_at') !== null;
    }

    /**
     * Check if token is expired.
     *
     * @return bool True if expired
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');
        if ($expiresAt === null) {
            return false;
        }

        return is_string($expiresAt)
            ? strtotime($expiresAt) < now()->getTimestamp()
            : $expiresAt->getTimestamp() < now()->getTimestamp();
    }

    /**
     * Check if token is valid (not revoked and not expired).
     *
     * @return bool True if valid
     */
    public function isValid(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }
}
