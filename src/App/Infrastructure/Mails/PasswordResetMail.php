<?php

declare(strict_types=1);

namespace App\Infrastructure\Mails;

use Toporia\Framework\Mail\Mailable;

/**
 * Password Reset Mail
 *
 * Beautiful email template for password reset.
 */
final class PasswordResetMail extends Mailable
{
    public function __construct(
        private readonly string $email,
        private readonly string $token
    ) {}

    /**
     * Build the email message.
     */
    public function buildMessage(): void
    {
        $appUrl = env('APP_URL', 'http://localhost:8000');
        $resetUrl = $appUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->email);

        $this->from(
            env('MAIL_FROM_ADDRESS', 'noreply@toporia.dev'),
            env('MAIL_FROM_NAME', 'Toporia Framework')
        );

        $this->to($this->email);
        $this->subject('Reset Your Password - Toporia');

        // Use inline HTML template (beautiful design)
        $this->view('emails.password-reset', [
            'email' => $this->email,
            'resetUrl' => $resetUrl,
            'token' => $this->token,
            'appName' => env('APP_NAME', 'Toporia Framework'),
            'appUrl' => $appUrl,
        ]);
    }
}
