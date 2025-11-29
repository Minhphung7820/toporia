<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\EmailVerification;

use Toporia\Framework\Auth\EmailVerification\Contracts\VerifiableInterface;

/**
 * Trait MustVerifyEmail
 *
 * Add this trait to your User model to enable email verification.
 *
 * Example:
 * ```php
 * class User extends Model implements VerifiableInterface
 * {
 *     use MustVerifyEmail;
 *
 *     protected string $emailColumn = 'email';
 *     protected string $emailVerifiedAtColumn = 'email_verified_at';
 * }
 * ```
 */
trait MustVerifyEmail
{
    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail(): bool
    {
        $column = $this->getEmailVerifiedAtColumn();

        return $this->{$column} !== null;
    }

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified(): bool
    {
        $column = $this->getEmailVerifiedAtColumn();

        return $this->forceFill([
            $column => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification(): void
    {
        // This can be overridden by the user to send custom notifications
    }

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification(): string
    {
        $column = $this->getEmailColumn();

        return $this->{$column};
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

    /**
     * Get the email verified at column name.
     *
     * @return string
     */
    protected function getEmailVerifiedAtColumn(): string
    {
        return $this->emailVerifiedAtColumn ?? 'email_verified_at';
    }

    /**
     * Get a fresh timestamp.
     *
     * @return \DateTimeInterface
     */
    protected function freshTimestamp(): \DateTimeInterface
    {
        return new \DateTimeImmutable();
    }

    /**
     * Force fill the model with an array of attributes.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    abstract public function forceFill(array $attributes): static;

    /**
     * Save the model.
     *
     * @return bool
     */
    abstract public function save(): bool;
}
