<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Contracts\Auth\AuthenticatableInterface as DomainAuthenticatable;
use Toporia\Framework\Auth\Authenticatable as FrameworkAuthenticatable;

/**
 * Authenticatable Adapter
 *
 * Adapts domain AuthenticatableInterface to framework Authenticatable.
 * Bridge pattern between Domain and Framework layers.
 *
 * Clean Architecture:
 * - Infrastructure layer adapter
 * - Depends on both Domain interface (inward) and Framework interface (outward)
 * - Allows domain to remain independent of framework
 *
 * SOLID Principles:
 * - Single Responsibility: Adapt domain to framework
 * - Dependency Inversion: Both depend on abstractions
 * - Adapter Pattern: Converts one interface to another
 *
 * Usage:
 * ```php
 * $domainUser = new User(...);
 * $frameworkUser = new AuthenticatableAdapter($domainUser);
 * $guard->login($frameworkUser); // Framework expects Authenticatable
 * ```
 */
final class AuthenticatableAdapter implements FrameworkAuthenticatable
{
    public function __construct(
        private readonly DomainAuthenticatable $domainEntity
    ) {}

    /**
     * Get the underlying domain entity.
     *
     * @return DomainAuthenticatable Domain entity
     */
    public function getDomainEntity(): DomainAuthenticatable
    {
        return $this->domainEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->domainEntity->getAuthIdentifier();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifierName(): string
    {
        return 'id'; // Default for most entities
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthPassword(): string
    {
        return $this->domainEntity->getAuthPassword();
    }

    /**
     * {@inheritdoc}
     */
    public function getRememberToken(): ?string
    {
        return $this->domainEntity->getRememberToken();
    }

    /**
     * {@inheritdoc}
     *
     * Delegates to domain entity's immutable setRememberToken.
     */
    public function setRememberToken(string $token): void
    {
        // Domain entity is immutable, so we can't mutate it
        // This is handled by UserProvider which creates new instance
        // For framework compatibility, this method exists but does nothing

        // In practice, UserProvider will:
        // 1. Call $user->setRememberToken($token) which returns new User
        // 2. Persist the new User via UserRepository
    }
}
