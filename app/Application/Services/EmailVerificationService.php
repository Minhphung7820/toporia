<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;
use Toporia\Framework\Mail\Contracts\MailerInterface;
use Toporia\Framework\Mail\Message;

/**
 * Email Verification Service
 *
 * Handles email verification for user registration.
 */
final class EmailVerificationService
{
    /**
     * Token expiration in minutes.
     */
    private const TOKEN_EXPIRATION_MINUTES = 60;

    /**
     * Throttle limit in seconds (prevent spam).
     */
    private const THROTTLE_SECONDS = 60;

    /**
     * Application key for HMAC signing.
     */
    private string $appKey;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ?MailerInterface $mailer = null
    ) {
        $this->appKey = config('app.key', 'toporia-secret-key');
    }

    /**
     * Send verification email to user.
     *
     * @param User $user
     * @return array{success: bool, message: string}
     */
    public function sendVerificationEmail(User $user): array
    {
        // Check if already verified
        if ($user->emailVerifiedAt !== null) {
            return [
                'success' => false,
                'message' => 'Email already verified',
            ];
        }

        $verificationUrl = $this->createVerificationUrl($user);

        // Send email
        if ($this->mailer !== null) {
            $this->sendEmail($user, $verificationUrl);
        }

        return [
            'success' => true,
            'message' => 'Verification email sent',
            'verification_url' => $verificationUrl, // For development/testing
        ];
    }

    /**
     * Verify user's email with token.
     *
     * @param int $userId
     * @param string $hash
     * @param int $expires Expiration timestamp from URL
     * @return array{success: bool, message: string, error?: string, user?: User}
     */
    public function verify(int $userId, string $hash, int $expires): array
    {
        // Check expiration first
        if ($expires > 0 && time() > $expires) {
            return [
                'success' => false,
                'message' => 'This verification link has expired. Please request a new one.',
                'error' => 'expired',
            ];
        }

        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'User not found',
                'error' => 'not_found',
            ];
        }

        // Check if already verified
        if ($user->emailVerifiedAt !== null) {
            return [
                'success' => false,
                'message' => 'Email already verified',
                'error' => 'already_verified',
                'user' => $user,
            ];
        }

        // Verify the hash
        if (!$this->verifyHash($user, $hash)) {
            return [
                'success' => false,
                'message' => 'Invalid verification link',
                'error' => 'invalid',
            ];
        }

        // Mark email as verified
        $this->userRepository->markEmailAsVerified($userId);

        // Fetch updated user
        $updatedUser = $this->userRepository->findById($userId);

        return [
            'success' => true,
            'message' => 'Email verified successfully',
            'user' => $updatedUser,
        ];
    }

    /**
     * Create verification URL for user.
     *
     * @param User $user
     * @return string
     */
    public function createVerificationUrl(User $user): string
    {
        $timestamp = time();
        $signature = $this->createSignature($user, $timestamp);
        $hash = base64_encode($timestamp . ':' . $signature);

        // URL-safe base64
        $hash = strtr($hash, '+/', '-_');
        $hash = rtrim($hash, '=');

        $baseUrl = config('app.url', 'http://localhost:8000');
        $expires = $timestamp + (self::TOKEN_EXPIRATION_MINUTES * 60);

        return "{$baseUrl}/verify-email/{$user->id}/{$hash}?expires={$expires}";
    }

    /**
     * Verify hash with expiration check.
     *
     * @param User $user
     * @param string $hash
     * @return bool
     */
    public function verifyHash(User $user, string $hash): bool
    {
        // Restore URL-safe base64
        $hash = strtr($hash, '-_', '+/');
        $padding = strlen($hash) % 4;
        if ($padding) {
            $hash .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($hash, true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return false;
        }

        [$timestamp, $signature] = explode(':', $decoded, 2);

        // Check expiration
        $timestamp = (int) $timestamp;
        $expirationSeconds = self::TOKEN_EXPIRATION_MINUTES * 60;

        if (time() - $timestamp > $expirationSeconds) {
            return false;
        }

        // Verify signature
        $expectedSignature = $this->createSignature($user, $timestamp);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Create HMAC signature for user verification.
     *
     * @param User $user
     * @param int $timestamp
     * @return string
     */
    private function createSignature(User $user, int $timestamp): string
    {
        $data = implode('|', [
            $user->id,
            $user->email,
            $timestamp,
        ]);

        return hash_hmac('sha256', $data, $this->appKey);
    }

    /**
     * Send verification email.
     *
     * @param User $user
     * @param string $url
     * @return void
     */
    private function sendEmail(User $user, string $url): void
    {
        $siteName = config('app.name', 'Toporia');

        $message = (new Message())
            ->to($user->email)
            ->subject("Verify your email address - {$siteName}")
            ->html($this->buildEmailContent($user, $url, $siteName));

        $this->mailer->send($message);
    }

    /**
     * Build email HTML content.
     *
     * @param User $user
     * @param string $url
     * @param string $siteName
     * @return string
     */
    private function buildEmailContent(User $user, string $url, string $siteName): string
    {
        $userName = htmlspecialchars($user->name);
        $expirationMinutes = self::TOKEN_EXPIRATION_MINUTES;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h1 style="color: #1a1a1a; font-size: 24px; margin: 0 0 24px 0;">Verify your email address</h1>

            <p style="margin: 0 0 16px 0;">Hi {$userName},</p>

            <p style="margin: 0 0 24px 0;">Thanks for signing up for {$siteName}! Please click the button below to verify your email address.</p>

            <p style="margin: 0 0 24px 0;">
                <a href="{$url}"
                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                    Verify Email Address
                </a>
            </p>

            <p style="margin: 0 0 16px 0; color: #666; font-size: 14px;">
                This verification link will expire in {$expirationMinutes} minutes.
            </p>

            <p style="margin: 0 0 16px 0; color: #666; font-size: 14px;">
                If you did not create an account, no further action is required.
            </p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 32px 0;">

            <p style="color: #999; font-size: 12px; margin: 0;">
                If you're having trouble clicking the button, copy and paste this URL into your browser:
                <br>
                <a href="{$url}" style="color: #667eea; word-break: break-all;">{$url}</a>
            </p>
        </div>

        <p style="text-align: center; color: #999; font-size: 12px; margin-top: 24px;">
            &copy; {$siteName}. All rights reserved.
        </p>
    </div>
</body>
</html>
HTML;
    }
}
