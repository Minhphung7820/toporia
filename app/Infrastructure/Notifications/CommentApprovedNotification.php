<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\MailMessage;

/**
 * Comment Approved Notification
 *
 * Sent to comment author when their comment is approved.
 *
 * Usage:
 * ```php
 * $user->notify(new CommentApprovedNotification($comment, $postTitle));
 * ```
 */
final class CommentApprovedNotification extends Notification
{
    protected bool $shouldQueue = true;
    protected string $queueName = 'notifications';

    private array $comment;
    private string $postTitle;

    public function __construct(array $comment, string $postTitle)
    {
        parent::__construct();
        $this->comment = $comment;
        $this->postTitle = $postTitle;
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
        $postUrl = $this->getPostUrl();

        return (new MailMessage)
            ->subject('Your Comment Has Been Approved!')
            ->greeting("Hi {$authorName}!")
            ->line('Great news! Your comment has been approved and is now visible to everyone.')
            ->line('')
            ->line('**Your comment on:** ' . $this->postTitle)
            ->line('')
            ->line('> ' . $commentPreview)
            ->line('')
            ->action('View Your Comment', $postUrl)
            ->line('Thank you for being part of our community!')
            ->salutation('Best regards, The Team');
    }

    /**
     * Build database notification data.
     */
    public function toDatabase(NotifiableInterface $notifiable): array
    {
        return [
            'title' => 'Comment Approved',
            'message' => "Your comment on \"{$this->postTitle}\" has been approved.",
            'icon' => '✅',
            'action_url' => $this->getPostUrl(),
            'action_text' => 'View Comment',
            'comment_id' => $this->comment['id'] ?? null,
            'post_title' => $this->postTitle,
        ];
    }

    /**
     * Get the post URL with comment anchor.
     */
    private function getPostUrl(): string
    {
        $baseUrl = env('APP_URL', 'http://localhost:8000');
        $postId = $this->comment['commentable_id'] ?? null;
        $commentId = $this->comment['id'] ?? null;

        if ($postId) {
            return rtrim($baseUrl, '/') . "/posts/{$postId}#comment-{$commentId}";
        }

        return rtrim($baseUrl, '/') . '/';
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
