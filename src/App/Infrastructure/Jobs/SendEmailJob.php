<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Mails\SimpleMail;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Accessors\Mail;

/**
 * Send Email Job
 *
 * Queue job that sends an email
 */
final class SendEmailJob extends Job implements ShouldQueueInterface
{
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $message
    ) {
        parent::__construct();
        $this->tries(3); // Only try once for testing
    }

    public function handle(): void
    {
        try {
            $mail = new SimpleMail($this->subject, $this->message);

            // Send email using Mail accessor (framework convention)
            Mail::to($this->to)->send($mail);

            Log::info("✅ SendEmailJob: Email sent to {$this->to} - {$this->subject}");
        } catch (\Throwable $e) {
            Log::error("❌ SendEmailJob error: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ SendEmailJob failed: " . $exception->getMessage());
    }
}
