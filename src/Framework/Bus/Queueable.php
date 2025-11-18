<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus;

/**
 * Queueable Trait
 *
 * Provides queueable functionality for commands/jobs.
 */
trait Queueable
{
    /**
     * Queue name.
     */
    protected ?string $queue = null;

    /**
     * Delay in seconds.
     */
    protected int $delay = 0;

    /**
     * Get the queue name.
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Set the queue name.
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Get the delay in seconds.
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Set the delay in seconds.
     */
    public function delay(int $delay): self
    {
        $this->delay = $delay;
        return $this;
    }
}
