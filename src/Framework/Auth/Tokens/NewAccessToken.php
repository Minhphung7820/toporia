<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Tokens;

use Toporia\Framework\Auth\Contracts\{NewAccessTokenInterface, PersonalAccessTokenInterface};

/**
 * New Access Token Value Object
 *
 * Wrapper for newly created access tokens containing both:
 * - The token model (PersonalAccessToken)
 * - The plain text token (only available at creation time!)
 *
 * Clean Architecture:
 * - Value Object pattern
 * - Immutable data structure
 *
 * SOLID Principles:
 * - Single Responsibility: Wraps token + plain text
 * - Interface Segregation: Implements focused contract
 *
 * Security:
 * - Plain text token ONLY available once at creation
 * - After this object is discarded, plain text is lost forever
 * - Only hashed token stored in database
 *
 * @package Toporia\Framework\Auth\Tokens
 */
final class NewAccessToken implements NewAccessTokenInterface
{
    /**
     * Create a new access token instance.
     *
     * @param PersonalAccessTokenInterface $accessToken Token model
     * @param string $plainTextToken Plain text token (ONLY available once!)
     */
    public function __construct(
        private readonly PersonalAccessTokenInterface $accessToken,
        private readonly string $plainTextToken
    ) {
    }

    /**
     * Get the personal access token instance.
     *
     * @return PersonalAccessTokenInterface Token model
     */
    public function accessToken(): PersonalAccessTokenInterface
    {
        return $this->accessToken;
    }

    /**
     * Get the plain text token (only available once!).
     *
     * IMPORTANT: This is the ONLY time you can get the plain text token.
     * After this object is garbage collected, the plain text is lost forever.
     * Only the hashed version is stored in the database.
     *
     * @return string Plain text token
     */
    public function getPlainTextToken(): string
    {
        return $this->plainTextToken;
    }

    /**
     * Convert to JSON representation.
     *
     * Useful for API responses showing token to user ONE TIME.
     *
     * @return array<string, mixed> JSON data
     */
    public function toArray(): array
    {
        return [
            'accessToken' => [
                'id' => $this->accessToken->getId(),
                'name' => $this->accessToken->getName(),
                'abilities' => $this->accessToken->getAbilities(),
            ],
            'plainTextToken' => $this->plainTextToken,
        ];
    }

    /**
     * Convert to JSON string.
     *
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get the plain text token when object is cast to string.
     *
     * Convenience method for echoing token directly.
     *
     * @return string Plain text token
     */
    public function __toString(): string
    {
        return $this->plainTextToken;
    }
}
