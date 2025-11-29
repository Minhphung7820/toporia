<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\EmailVerification\Contracts;

/**
 * Verifiable Interface
 *
 * Contract for models that can verify their email.
 */
interface VerifiableInterface
{
    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail(): bool;

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified(): bool;

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification(): string;

    /**
     * Get the primary key for the model.
     *
     * @return int|string
     */
    public function getKey(): int|string;
}
