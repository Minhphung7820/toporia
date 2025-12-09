<?php

declare(strict_types=1);

namespace App\Infrastructure\Mails;

use Toporia\Framework\Mail\Mailable;

/**
 * Simple Mail
 *
 * Basic email for testing
 */
final class SimpleMail extends Mailable
{
    public function __construct(
        private readonly string $emailSubject,
        private readonly string $emailMessage
    ) {}

    public function buildMessage(): void
    {
        $this->from(env('MAIL_FROM_ADDRESS', 'noreply@toporia.dev'), env('MAIL_FROM_NAME', 'Toporia'));
        $this->subject($this->emailSubject);
        $this->view('simple', [
            'subject' => $this->emailSubject,
            'message' => $this->emailMessage,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

