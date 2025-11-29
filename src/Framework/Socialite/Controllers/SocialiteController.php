<?php

declare(strict_types=1);

namespace Toporia\Framework\Socialite\Controllers;

use Toporia\Framework\Http\{Request, RedirectResponse};
use Toporia\Framework\Socialite\SocialiteManager;

/**
 * Socialite Controller
 *
 * Handles OAuth authentication flows.
 */
final class SocialiteController
{
    /**
     * @param SocialiteManager $socialite Socialite manager
     */
    public function __construct(
        private SocialiteManager $socialite
    ) {}

    /**
     * Redirect to OAuth provider.
     *
     * @param Request $request HTTP request
     * @param string $provider Provider name
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->socialite->driver($provider);
        $url = $driver->redirect($request);

        return new RedirectResponse($url);
    }

    /**
     * Handle OAuth callback.
     *
     * @param Request $request HTTP request
     * @param string $provider Provider name
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $driver = $this->socialite->driver($provider);
            $user = $driver->user($request);

            // Store user data in session for application to handle
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['socialite_user'] = $user->toArray();
                $_SESSION['socialite_provider'] = $provider;
            }

            // Redirect to application's callback handler
            $redirectUrl = $request->input('redirect') ?? '/auth/socialite/success';

            return new RedirectResponse($redirectUrl);
        } catch (\Throwable $e) {
            // Redirect to error page
            $redirectUrl = $request->input('redirect_error') ?? '/auth/socialite/error';

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['socialite_error'] = $e->getMessage();
            }

            return new RedirectResponse($redirectUrl);
        }
    }
}

