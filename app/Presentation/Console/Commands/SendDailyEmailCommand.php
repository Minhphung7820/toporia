<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Jobs\SendEmailJob;
use Toporia\Framework\Console\Command;

/**
 * Send Daily Email Command
 *
 * Sends a scheduled daily email notification.
 * Can be triggered manually or via scheduler.
 *
 * Usage:
 *   php console email:daily
 *   php console email:daily --to=custom@email.com
 *   php console email:daily --subject="Custom Subject"
 */
final class SendDailyEmailCommand extends Command
{
    protected string $signature = 'email:daily {--to=tmpdz7820@gmail.com : Recipient email address} {--subject=Daily Notification : Email subject} {--message= : Custom message}';

    protected string $description = 'Send a scheduled daily email notification';

    /**
     * Execute the command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $to = $this->option('to', 'tmpdz7820@gmail.com');
        $subject = $this->option('subject', 'Daily Notification');
        $message = $this->option('message') ?: $this->getDefaultMessage();

        $this->info("Sending daily email to: {$to}");
        $this->info("Subject: {$subject}");

        try {
            // Dispatch the email job to queue
            SendEmailJob::dispatch($to, $subject, $message);

            $this->success("Email job dispatched successfully!");
            $this->info("Run 'php console queue:work' to process the job.");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to dispatch email job: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get default email message with current date/time.
     */
    private function getDefaultMessage(): string
    {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $timezone = date_default_timezone_get();

        return <<<HTML
        <h2>Daily Notification from Toporia</h2>
        <p>This is your scheduled daily email notification.</p>
        <hr>
        <p><strong>Date:</strong> {$date}</p>
        <p><strong>Time:</strong> {$time}</p>
        <p><strong>Timezone:</strong> {$timezone}</p>
        <hr>
        <p><em>This email was sent automatically by Toporia Framework Scheduler.</em></p>
        HTML;
    }
}
