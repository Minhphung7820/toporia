<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\EmailVerification\Contracts;

/**
 * Email Verification Broker Interface
 */
interface EmailVerificationBrokerInterface
{
    /**
     * Send verification link to user.
     *
     * @param VerifiableInterface $user
     * @return string Status constant
     */
    public function sendVerificationLink(VerifiableInterface $user): string;

    /**
     * Verify user's email.
     *
     * @param VerifiableInterface $user
     * @param string $hash
     * @return string Status constant
     */
    public function verify(VerifiableInterface $user, string $hash): string;

    /**
     * Create verification URL for user.
     *
     * @param VerifiableInterface $user
     * @return string
     */
    public function createVerificationUrl(VerifiableInterface $user): string;
}
