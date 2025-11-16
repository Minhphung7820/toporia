<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Contracts;

/**
 * New Access Token Interface
 *
 * Contract for newly created access tokens.
 * Contains both the token model and the plain text token.
 *
 * IMPORTANT: Plain text token is only available once at creation!
 *
 * @package Toporia\Framework\Auth\Contracts
 */
interface NewAccessTokenInterface
{
    /**
     * Get the personal access token instance.
     *
     * @return PersonalAccessTokenInterface Token model
     */
    public function accessToken(): PersonalAccessTokenInterface;

    /**
     * Get the plain text token (only available once!).
     *
     * @return string Plain text token
     */
    public function getPlainTextToken(): string;

    /**
     * Convert to JSON representation.
     *
     * @return array<string, mixed> JSON data
     */
    public function toArray(): array;
}
