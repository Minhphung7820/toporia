<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus\Contracts;

/**
 * Queueable Interface
 *
 * Marks a command/job as queueable (can be dispatched to queue).
 */
interface QueueableInterface
{
    /**
     * Get the queue name.
     *
     * @return string|null
     */
    public function getQueue(): ?string;

    /**
     * Set the queue name.
     *
     * @param string $queue Queue name
     * @return self
     */
    public function onQueue(string $queue): self;

    /**
     * Get the delay in seconds.
     *
     * @return int
     */
    public function getDelay(): int;

    /**
     * Set the delay in seconds.
     *
     * @param int $delay Delay in seconds
     * @return self
     */
    public function delay(int $delay): self;
}
