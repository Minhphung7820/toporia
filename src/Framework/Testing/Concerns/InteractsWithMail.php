<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

/**
 * Mail Testing Trait
 *
 * Provides utilities for mail testing.
 *
 * Performance:
 * - O(1) mail faking
 * - Fast mail assertions
 */
trait InteractsWithMail
{
    /**
     * Fake mail (disable real sending).
     */
    protected bool $fakeMail = false;

    /**
     * Sent mails.
     *
     * @var array
     */
    protected array $sentMails = [];

    /**
     * Fake mail.
     *
     * Performance: O(1)
     */
    protected function fakeMail(): void
    {
        $this->fakeMail = true;
        $this->sentMails = [];
    }

    /**
     * Assert that a mail was sent.
     *
     * Performance: O(N) where N = number of mails
     */
    protected function assertMailSent(string $to, string $subject = null): void
    {
        $found = false;

        foreach ($this->sentMails as $mail) {
            if ($mail['to'] === $to) {
                if ($subject === null || $mail['subject'] === $subject) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Mail to {$to} was not sent");
    }

    /**
     * Assert that a mail was not sent.
     *
     * Performance: O(N) where N = number of mails
     */
    protected function assertMailNotSent(string $to): void
    {
        $found = false;

        foreach ($this->sentMails as $mail) {
            if ($mail['to'] === $to) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, "Mail to {$to} was unexpectedly sent");
    }

    /**
     * Record a sent mail.
     *
     * Performance: O(1)
     */
    protected function recordMail(string $to, string $subject, string $body): void
    {
        $this->sentMails[] = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Cleanup mail after test.
     */
    protected function tearDownMail(): void
    {
        $this->sentMails = [];
        $this->fakeMail = false;
    }
}

