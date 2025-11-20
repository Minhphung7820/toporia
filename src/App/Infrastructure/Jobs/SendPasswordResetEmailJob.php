<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Mails\PasswordResetMail;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Mail\Contracts\MailerInterface;
use Toporia\Framework\Queue\Job;

/**
 * Send Password Reset Email Job
 *
 * Queue job for sending password reset emails.
 */
final class SendPasswordResetEmailJob extends Job implements ShouldQueueInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $token
    ) {
        parent::__construct();
        $this->tries(3);
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     *
     * @param MailerInterface $mailer
     * @return void
     */
    public function handle(MailerInterface $mailer): void
    {
        $mailable = new PasswordResetMail($this->email, $this->token);
        $message = $mailable->build();

        $success = $mailer->send($message);

        if (!$success) {
            throw new \RuntimeException('Failed to send password reset email');
        }
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send password reset email to {$this->email}: " . $exception->getMessage());
    }
}
