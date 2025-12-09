<?php

declare(strict_types=1);

namespace App\Application\Mails;

use Toporia\Framework\Mail\Mailable;

/**
 * Email with attachment for testing
 */
final class AttachmentMail extends Mailable
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $attachmentPath
    ) {}

    public function build(): void
    {
        $this->subject('Email with Attachment - Toporia Framework')
            ->view('emails/attachment')
            ->with([
                'name' => $this->recipientName,
                'filename' => basename($this->attachmentPath)
            ]);

        // Attach file if exists
        if (file_exists($this->attachmentPath)) {
            $this->attach($this->attachmentPath);
        }
    }
}
