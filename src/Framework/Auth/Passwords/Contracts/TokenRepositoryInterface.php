<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Passwords\Contracts;

/**
 * Token Repository Interface
 */
interface TokenRepositoryInterface
{
    /**
     * Create a new token record.
     *
     * @param CanResetPasswordInterface $user
     * @return string
     */
    public function create(CanResetPasswordInterface $user): string;

    /**
     * Determine if a token record exists and is valid.
     *
     * @param CanResetPasswordInterface $user
     * @param string $token
     * @return bool
     */
    public function exists(CanResetPasswordInterface $user, string $token): bool;

    /**
     * Determine if a token was recently created.
     *
     * @param CanResetPasswordInterface $user
     * @return bool
     */
    public function recentlyCreatedToken(CanResetPasswordInterface $user): bool;

    /**
     * Delete a token record.
     *
     * @param CanResetPasswordInterface $user
     * @return void
     */
    public function delete(CanResetPasswordInterface $user): void;

    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function deleteExpired(): void;
}
