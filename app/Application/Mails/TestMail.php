<?php

declare(strict_types=1);

namespace App\Application\Mails;

use Toporia\Framework\Mail\Mailable;
use Toporia\Framework\Mail\Message;

/**
 * Test Mail for Mail System Testing
 */
final class TestMail extends Mailable
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $content
    ) {}

    public function build(): void
    {
        $this->subject('Test Email from Toporia Framework')
            ->view('emails/test')
            ->with([
                'name' => $this->recipientName,
                'content' => $this->content,
                'timestamp' => date('Y-m-d H:i:s'),
                'framework' => 'Toporia Framework v1.0'
            ]);
    }
}

