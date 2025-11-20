<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Contracts;

use Toporia\Framework\Support\Collection\Collection;

/**
 * Has API Tokens Interface
 *
 * Contract for models that can issue API tokens.
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on token management only
 * - Dependency Inversion: Domain contract for infrastructure
 *
 * @package Toporia\Framework\Auth\Contracts
 */
interface HasApiTokensInterface
{
    /**
     * Create a new personal access token for the user.
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
    ): NewAccessTokenInterface;

    /**
     * Get all tokens for the user.
     *
     * @return Collection<PersonalAccessTokenInterface> User's tokens
     */
    public function tokens(): Collection;

    /**
     * Get the current access token being used.
     *
     * @return PersonalAccessTokenInterface|null Current token or null
     */
    public function currentAccessToken(): ?PersonalAccessTokenInterface;

    /**
     * Set the current access token for the user.
     *
     * @param PersonalAccessTokenInterface $token Token to set as current
     * @return self
     */
    public function withAccessToken(PersonalAccessTokenInterface $token): self;

    /**
     * Determine if the current API token has a given ability/scope.
     *
     * @param string $ability Ability to check
     * @return bool True if token has ability
     */
    public function tokenCan(string $ability): bool;

    /**
     * Determine if the current API token is missing a given ability.
     *
     * @param string $ability Ability to check
     * @return bool True if token lacks ability
     */
    public function tokenCant(string $ability): bool;
}
