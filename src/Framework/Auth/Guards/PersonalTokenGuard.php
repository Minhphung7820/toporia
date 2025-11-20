<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Guards;

use Toporia\Framework\Auth\Contracts\{GuardInterface, HasApiTokensInterface, TokenRepositoryInterface, UserProviderInterface};
use Toporia\Framework\Http\Request;

/**
 * Personal Token Guard
 *
 * Token-based authentication guard using personal access tokens.
 *
 * Authentication Flow:
 * 1. Extract token from Authorization header (Bearer token)
 * 2. Find token in database (with caching)
 * 3. Verify token not expired
 * 4. Load token owner (user)
 * 5. Set current access token on user
 *
 * Clean Architecture:
 * - Framework layer guard
 * - Depends on domain contracts
 *
 * SOLID Principles:
 * - Single Responsibility: Token authentication
 * - Dependency Inversion: Depends on interfaces
 *
 * Performance:
 * - O(1) token lookup with cache hit
 * - O(1) database lookup with index on cache miss
 * - Lazy user loading
 *
 * @package Toporia\Framework\Auth\Guards
 */
final class PersonalTokenGuard implements GuardInterface
{
    /**
     * Authenticated user instance.
     *
     * @var HasApiTokensInterface|null
     */
    private ?HasApiTokensInterface $user = null;

    /**
     * Create Personal Token guard instance.
     *
     * @param Request $request HTTP request
     * @param UserProviderInterface $provider User provider
     * @param TokenRepositoryInterface $tokens Token repository
     */
    public function __construct(
        private readonly Request $request,
        private readonly UserProviderInterface $provider,
        private readonly TokenRepositoryInterface $tokens
    ) {
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool True if authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Determine if the current user is a guest (not authenticated).
     *
     * @return bool True if guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user.
     *
     * Performance:
     * - Cached after first call
     * - O(1) token lookup with cache
     * - Lazy user loading
     *
     * @return HasApiTokensInterface|null Authenticated user or null
     */
    public function user(): ?HasApiTokensInterface
    {
        // Return cached user
        if ($this->user !== null) {
            return $this->user;
        }

        // Extract token from request
        $token = $this->getTokenFromRequest();

        if ($token === null) {
            return null;
        }

        // Find token in database
        $accessToken = $this->tokens->findByPlainTextToken($token);

        if ($accessToken === null) {
            return null;
        }

        // Check if token expired
        if ($accessToken->hasExpired()) {
            return null;
        }

        // Get token owner
        $tokenable = $accessToken->getTokenable();

        if (!is_array($tokenable) || !isset($tokenable['type'], $tokenable['id'])) {
            return null;
        }

        // Load user via provider
        $user = $this->provider->retrieveById($tokenable['id']);

        if ($user === null || !$user instanceof HasApiTokensInterface) {
            return null;
        }

        // Set current access token on user
        $user->withAccessToken($accessToken);

        // Update last used timestamp (async/background task recommended)
        $this->tokens->touchLastUsedAt($accessToken->getId());

        // Cache user for request
        $this->user = $user;

        return $this->user;
    }

    /**
     * Get the ID of the currently authenticated user.
     *
     * @return int|string|null User ID or null
     */
    public function id(): int|string|null
    {
        $user = $this->user();

        return $user?->getId();
    }

    /**
     * Validate user credentials.
     *
     * Note: Not applicable for token-based guard.
     * Use SessionGuard for credential validation.
     *
     * @param array $credentials User credentials
     * @return bool False (not supported)
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Determine if the guard has a user instance.
     *
     * @return bool True if user loaded
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Set the current user.
     *
     * @param HasApiTokensInterface $user User instance
     * @return self
     */
    public function setUser(HasApiTokensInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Extract bearer token from request.
     *
     * Checks:
     * 1. Authorization header: "Bearer {token}"
     * 2. X-API-TOKEN header
     * 3. Query parameter: ?api_token={token}
     *
     * Performance: O(1) header lookup
     *
     * @return string|null Token or null
     */
    private function getTokenFromRequest(): ?string
    {
        // Check Authorization header
        $authorization = $this->request->header('Authorization');

        if ($authorization !== null && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7); // Remove "Bearer " prefix
        }

        // Check X-API-TOKEN header
        $apiToken = $this->request->header('X-API-TOKEN');

        if ($apiToken !== null) {
            return $apiToken;
        }

        // Check query parameter (less secure, use only for specific cases)
        $queryToken = $this->request->query('api_token');

        if ($queryToken !== null) {
            return $queryToken;
        }

        return null;
    }
}

