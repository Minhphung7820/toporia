<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Auth;

/**
 * Authenticatable Interface
 *
 * Domain contract for entities that can be authenticated.
 * Pure domain interface with zero framework dependencies.
 *
 * Clean Architecture:
 * - Domain layer interface
 * - No framework dependencies
 * - Infrastructure provides adapters
 */
interface AuthenticatableInterface
{
    /**
     * Get unique identifier for authentication.
     *
     * @return int|string User ID
     */
    public function getAuthIdentifier(): int|string;

    /**
     * Get password for authentication.
     *
     * @return string Hashed password
     */
    public function getAuthPassword(): string;

    /**
     * Get remember token.
     *
     * @return string|null Remember token
     */
    public function getRememberToken(): ?string;

    /**
     * Set remember token.
     *
     * Note: In pure Clean Architecture, entities are immutable.
     * This method should return a new instance with updated token.
     *
     * @param string|null $token New remember token
     * @return self New instance with updated token
     */
    public function setRememberToken(?string $token): self;
}
