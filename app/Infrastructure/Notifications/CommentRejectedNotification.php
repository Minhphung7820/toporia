<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\MailMessage;

/**
 * Comment Rejected Notification
 *
 * Sent to comment author when their comment is rejected.
 *
 * Usage:
 * ```php
 * $user->notify(new CommentRejectedNotification($comment, $postTitle, $reason));
 * ```
 */
final class CommentRejectedNotification extends Notification
{
    protected bool $shouldQueue = true;
    protected string $queueName = 'notifications';

    private array $comment;
    private string $postTitle;
    private ?string $reason;

    public function __construct(array $comment, string $postTitle, ?string $reason = null)
    {
        parent::__construct();
        $this->comment = $comment;
        $this->postTitle = $postTitle;
        $this->reason = $reason;
    }

    /**
     * Get notification channels.
     */
    public function via(NotifiableInterface $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build email notification.
     */
    public function toMail(NotifiableInterface $notifiable): MailMessage
    {
        $authorName = $this->comment['author_name'] ?? 'there';
        $commentPreview = $this->truncate($this->comment['content'] ?? '', 100);

        $message = (new MailMessage)
            ->subject('Update on Your Comment')
            ->greeting("Hi {$authorName},")
            ->line('We wanted to let you know that your comment could not be published at this time.')
            ->line('')
            ->line('**Your comment on:** ' . $this->postTitle)
            ->line('')
            ->line('> ' . $commentPreview);

        if ($this->reason) {
            $message->line('')
                ->line('**Reason:** ' . $this->reason);
        }

        $message->line('')
            ->line('This may be due to our community guidelines. Please review our commenting policy and feel free to submit another comment.')
            ->action('View Our Guidelines', $this->getGuidelinesUrl())
            ->line('If you believe this was a mistake, please contact our support team.')
            ->salutation('Best regards, The Team');

        return $message;
    }

    /**
     * Build database notification data.
     */
    public function toDatabase(NotifiableInterface $notifiable): array
    {
        return [
            'title' => 'Comment Not Published',
            'message' => "Your comment on \"{$this->postTitle}\" could not be published.",
            'icon' => '❌',
            'action_url' => $this->getGuidelinesUrl(),
            'action_text' => 'View Guidelines',
            'comment_id' => $this->comment['id'] ?? null,
            'post_title' => $this->postTitle,
            'reason' => $this->reason,
        ];
    }

    /**
     * Get the guidelines URL.
     */
    private function getGuidelinesUrl(): string
    {
        $baseUrl = env('APP_URL', 'http://localhost:8000');
        return rtrim($baseUrl, '/') . '/community-guidelines';
    }

    /**
     * Truncate text to specified length.
     */
    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}
