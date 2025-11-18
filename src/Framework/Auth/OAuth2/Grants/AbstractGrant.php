<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\OAuth2\Grants;

use Toporia\Framework\Auth\OAuth2\Contracts\{GrantInterface, TokenRepositoryInterface, UserProviderInterface};
use Toporia\Framework\Http\Request;

/**
 * Abstract OAuth2 Grant
 *
 * Base class for OAuth2 grant type implementations.
 * Provides common functionality for all grant types.
 *
 * Clean Architecture:
 * - Template Method Pattern: Defines skeleton of grant flow
 * - Open/Closed: Extensible via inheritance
 *
 * SOLID Principles:
 * - S: Base grant functionality only
 * - O: Extensible via child classes
 */
abstract class AbstractGrant implements GrantInterface
{
    /**
     * @param TokenRepositoryInterface $tokenRepository Token repository
     * @param UserProviderInterface|null $userProvider User provider (for user-based grants)
     */
    public function __construct(
        protected TokenRepositoryInterface $tokenRepository,
        protected ?UserProviderInterface $userProvider = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getIdentifier(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function issueToken(Request $request, \Toporia\Framework\Auth\OAuth2\Contracts\ClientInterface $client): array;

    /**
     * Validate required parameters for grant type.
     *
     * @param Request $request HTTP request
     * @param array<string> $required Required parameter names
     * @return void
     * @throws \InvalidArgumentException If required parameters are missing
     */
    protected function validateRequiredParameters(Request $request, array $required): void
    {
        foreach ($required as $param) {
            if ($request->input($param) === null) {
                throw new \InvalidArgumentException("Missing required parameter: {$param}");
            }
        }
    }

    /**
     * Get token expiration time from request or use default.
     *
     * @param Request $request HTTP request
     * @param int $default Default expiration in seconds
     * @return int Expiration time in seconds
     */
    protected function getExpiresIn(Request $request, int $default = 3600): int
    {
        $expiresIn = $request->input('expires_in');
        return $expiresIn !== null ? (int) $expiresIn : $default;
    }

    /**
     * Get scopes from request or use default.
     *
     * @param Request $request HTTP request
     * @param array<string> $default Default scopes
     * @return array<string> Requested scopes
     */
    protected function getScopes(Request $request, array $default = []): array
    {
        $scopes = $request->input('scope');
        if ($scopes === null || $scopes === '') {
            return $default;
        }

        return is_array($scopes) ? $scopes : explode(' ', $scopes);
    }
}

