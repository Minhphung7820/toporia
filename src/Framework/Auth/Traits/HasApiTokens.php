<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Traits;

use Toporia\Framework\Auth\Contracts\HasApiTokensInterface;
use Toporia\Framework\Auth\Contracts\NewAccessTokenInterface;
use Toporia\Framework\Auth\Contracts\PersonalAccessTokenInterface;
use Toporia\Framework\Auth\Contracts\TokenRepositoryInterface;
use Toporia\Framework\Support\Collection;

/**
 * Has API Tokens Trait
 *
 * Adds API token functionality to User models (Sanctum-style).
 *
 * Usage:
 * ```php
 * class User extends Model implements HasApiTokensInterface {
 *     use HasApiTokens;
 * }
 *
 * $token = $user->createToken('mobile-app', ['posts:read', 'posts:write']);
 * echo $token->plainTextToken; // Show to user ONCE!
 * ```
 *
 * Clean Architecture:
 * - Depends on domain contracts (interfaces)
 * - No framework coupling
 *
 * SOLID Principles:
 * - Single Responsibility: Token management for user
 * - Dependency Inversion: Depends on TokenRepositoryInterface
 *
 * Performance:
 * - Lazy loading of tokens (only when accessed)
 * - Caching of current access token
 *
 * @package Toporia\Framework\Auth\Traits
 */
trait HasApiTokens
{
    /**
     * Current access token being used.
     *
     * @var PersonalAccessTokenInterface|null
     */
    protected ?PersonalAccessTokenInterface $accessToken = null;

    /**
     * Create a new personal access token for the user.
     *
     * Example:
     * ```php
     * $token = $user->createToken('mobile-app');
     * $token = $user->createToken('api-client', ['posts:read', 'posts:write']);
     * $token = $user->createToken('temp', ['*'], new \DateTime('+1 hour'));
     * ```
     *
     * @param string $name Token name/identifier
     * @param array<string> $abilities Token abilities/scopes
     * @param \DateTimeInterface|null $expiresAt Token expiration
     * @return NewAccessTokenInterface Newly created token
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): NewAccessTokenInterface {
        $repository = $this->getTokenRepository();

        return $repository->create(
            tokenableId: $this->getKey(),
            tokenableType: static::class,
            name: $name,
            abilities: $abilities,
            expiresAt: $expiresAt
        );
    }

    /**
     * Get all tokens for the user.
     *
     * Performance: Lazy loaded, cached on first access
     *
     * @return Collection<PersonalAccessTokenInterface> User's tokens
     */
    public function tokens(): Collection
    {
        $repository = $this->getTokenRepository();

        return $repository->getTokensFor(
            tokenableId: $this->getKey(),
            tokenableType: static::class
        );
    }

    /**
     * Get the current access token being used.
     *
     * This is the token that was used to authenticate the current request.
     *
     * @return PersonalAccessTokenInterface|null Current token or null
     */
    public function currentAccessToken(): ?PersonalAccessTokenInterface
    {
        return $this->accessToken;
    }

    /**
     * Set the current access token for the user.
     *
     * Called by authentication guard when token is validated.
     *
     * @param PersonalAccessTokenInterface $token Token to set as current
     * @return self
     */
    public function withAccessToken(PersonalAccessTokenInterface $token): self
    {
        $this->accessToken = $token;

        return $this;
    }

    /**
     * Determine if the current API token has a given ability/scope.
     *
     * Example:
     * ```php
     * if ($user->tokenCan('posts:write')) {
     *     // Allow post creation
     * }
     * ```
     *
     * @param string $ability Ability to check
     * @return bool True if token has ability
     */
    public function tokenCan(string $ability): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        return $this->accessToken->can($ability);
    }

    /**
     * Determine if the current API token is missing a given ability.
     *
     * Example:
     * ```php
     * if ($user->tokenCant('posts:delete')) {
     *     abort(403, 'Forbidden');
     * }
     * ```
     *
     * @param string $ability Ability to check
     * @return bool True if token lacks ability
     */
    public function tokenCant(string $ability): bool
    {
        return !$this->tokenCan($ability);
    }

    /**
     * Get the token repository instance.
     *
     * Override this method if you need custom repository resolution.
     *
     * @return TokenRepositoryInterface Token repository
     */
    protected function getTokenRepository(): TokenRepositoryInterface
    {
        // Resolve from container
        return app(TokenRepositoryInterface::class);
    }

    /**
     * Get the primary key value for the model.
     *
     * This method should be provided by the Model class.
     * Here we define it for IDE autocomplete when using trait.
     *
     * @return int|string Primary key value
     */
    abstract public function getKey(): int|string;
}
