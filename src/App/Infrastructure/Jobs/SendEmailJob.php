<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Mails\SimpleMail;
use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Backoff\ExponentialBackoff;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Accessors\Mail;

/**
 * Send Email Job
 *
 * Queue job that sends an email with exponential backoff retry strategy
 */
final class SendEmailJob extends Job implements ShouldQueueInterface
{
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $message
    ) {
        parent::__construct();

        // Configure retry behavior
        $this->tries(3); // Max 3 attempts

        // Exponential backoff: 5s, 25s, 60s (capped)
        // Gives SMTP server time to recover between retries
        $this->backoff(new ExponentialBackoff(base: 5, max: 60));

        // Optional: Set timeout for email sending (30 seconds)
        $this->setTimeout(30);
    }

    public function handle(): void
    {
        Log::info("Attemps: " . $this->attempts());
        try {
            $mail = new SimpleMail($this->subject, $this->message);

            // Send email using Mail accessor (framework convention)
            Mail::to($this->to)->send($mail);

            Log::info("✅ SendEmailJob: Email sent to {$this->to} - {$this->subject}");
        } catch (\Throwable $e) {
            // Log full exception details for debugging
            $errorDetails = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            // Log previous exception (original error) if exists
            if ($e->getPrevious()) {
                $prev = $e->getPrevious();
                $errorDetails['previous_exception'] = get_class($prev);
                $errorDetails['previous_message'] = $prev->getMessage();
                $errorDetails['previous_file'] = $prev->getFile();
                $errorDetails['previous_line'] = $prev->getLine();
            }

            Log::error("❌ SendEmailJob error", $errorDetails);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log detailed failure information
        $errorDetails = [
            'to' => $this->to,
            'subject' => $this->subject,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // Log root cause if exists
        if ($exception->getPrevious()) {
            $prev = $exception->getPrevious();
            $errorDetails['root_cause'] = get_class($prev);
            $errorDetails['root_message'] = $prev->getMessage();
        }

        Log::error("❌ SendEmailJob FAILED after all retries", $errorDetails);
    }
}
