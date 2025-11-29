<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Passwords;

/**
 * Trait CanResetPassword
 *
 * Add this trait to your User model to enable password reset.
 *
 * Example:
 * ```php
 * class User extends Model implements CanResetPasswordInterface
 * {
 *     use CanResetPassword;
 * }
 * ```
 */
trait CanResetPassword
{
    /**
     * Get the email address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->{$this->getEmailColumn()};
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification(string $token): void
    {
        // Override this method to send custom notification
        // Default: do nothing, let the broker handle it
    }

    /**
     * Get the email column name.
     *
     * @return string
     */
    protected function getEmailColumn(): string
    {
        return $this->emailColumn ?? 'email';
    }
}
