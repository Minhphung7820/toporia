<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Webhook\Contracts\WebhookDispatcherInterface;

/**
 * Dispatch Webhook Job
 *
 * Async job for dispatching webhooks via queue.
 */
final class DispatchWebhookJob extends Job
{
    /**
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param string $endpoint Target URL
     * @param array<string, mixed> $options Options
     */
    public function __construct(
        private string $event,
        private mixed $payload,
        private string $endpoint,
        private array $options = []
    ) {
        parent::__construct();
    }

    /**
     * Handle the job execution.
     *
     * @param WebhookDispatcherInterface $dispatcher Webhook dispatcher
     * @return void
     */
    public function handle(WebhookDispatcherInterface $dispatcher): void
    {
        $dispatcher->dispatchTo($this->event, $this->payload, $this->endpoint, $this->options);
    }
}

