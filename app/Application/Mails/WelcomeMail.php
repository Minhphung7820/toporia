<?php

declare(strict_types=1);

namespace App\Application\Mails;

use Toporia\Framework\Mail\Mailable;

/**
 * Welcome Email for new users
 */
final class WelcomeMail extends Mailable
{
    public function __construct(
        private readonly string $userName,
        private readonly string $userEmail
    ) {}

    public function build(): void
    {
        $this->subject('Welcome to Toporia Framework!')
            ->view('emails/welcome')
            ->with([
                'userName' => $this->userName,
                'userEmail' => $this->userEmail,
                'features' => [
                    'Queue System',
                    'Mail System',
                    'Schedule System',
                    'Event System',
                    'Notification System'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
}

