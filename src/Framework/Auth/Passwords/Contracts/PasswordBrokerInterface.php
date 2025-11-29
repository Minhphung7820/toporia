<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Passwords\Contracts;

/**
 * Password Broker Interface
 */
interface PasswordBrokerInterface
{
    /**
     * Send a password reset link to a user.
     *
     * @param array<string, mixed> $credentials
     * @param callable|null $callback
     * @return string
     */
    public function sendResetLink(array $credentials, ?callable $callback = null): string;

    /**
     * Reset the password for the given credentials.
     *
     * @param array<string, mixed> $credentials
     * @param callable $callback
     * @return string
     */
    public function reset(array $credentials, callable $callback): string;

    /**
     * Create a new password reset token for the given user.
     *
     * @param CanResetPasswordInterface $user
     * @return string
     */
    public function createToken(CanResetPasswordInterface $user): string;

    /**
     * Delete password reset tokens of the given user.
     *
     * @param CanResetPasswordInterface $user
     * @return void
     */
    public function deleteToken(CanResetPasswordInterface $user): void;

    /**
     * Validate the given password reset token.
     *
     * @param CanResetPasswordInterface $user
     * @param string $token
     * @return bool
     */
    public function tokenExists(CanResetPasswordInterface $user, string $token): bool;
}
