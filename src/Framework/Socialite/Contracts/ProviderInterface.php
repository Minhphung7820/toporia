<?php

declare(strict_types=1);

namespace Toporia\Framework\Socialite\Contracts;

use Toporia\Framework\Http\Request;

/**
 * Socialite Provider Interface
 *
 * Contract for OAuth providers (Google, Facebook, GitHub, etc.)
 */
interface ProviderInterface
{
    /**
     * Redirect user to OAuth provider.
     *
     * @param Request|null $request HTTP request
     * @return string Redirect URL
     */
    public function redirect(?Request $request = null): string;

    /**
     * Handle OAuth callback and get user data.
     *
     * @param Request $request HTTP request with OAuth callback data
     * @return \Toporia\Framework\Socialite\User User data
     */
    public function user(Request $request): \Toporia\Framework\Socialite\User;

    /**
     * Get access token from callback.
     *
     * @param Request $request HTTP request
     * @return string Access token
     */
    public function getAccessToken(Request $request): string;

    /**
     * Get user data using access token.
     *
     * @param string $token Access token
     * @return \Toporia\Framework\Socialite\User User data
     */
    public function getUserFromToken(string $token): \Toporia\Framework\Socialite\User;
}

