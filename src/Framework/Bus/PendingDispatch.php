<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus;

use Toporia\Framework\Bus\Contracts\DispatcherInterface;
use Toporia\Framework\Bus\Contracts\QueueableInterface;

/**
 * Pending Dispatch
 *
 * Fluent API for configuring and dispatching commands/jobs.
 *
 * Performance:
 * - Lazy execution (only dispatches when needed)
 * - Zero-copy command modification (returns self)
 * - O(1) configuration changes
 *
 * @template T
 */
final class PendingDispatch
{
    private bool $afterResponse = false;

    /**
     * @param DispatcherInterface $dispatcher Dispatcher instance
     * @param mixed $command Command/Job instance
     */
    public function __construct(
        private DispatcherInterface $dispatcher,
        private mixed $command
    ) {
    }

    /**
     * Set the queue name.
     *
     * @param string $queue Queue name
     * @return self
     */
    public function onQueue(string $queue): self
    {
        if ($this->command instanceof QueueableInterface) {
            $this->command->onQueue($queue);
        }

        return $this;
    }

    /**
     * Set the delay in seconds.
     *
     * @param int $delay Delay in seconds
     * @return self
     */
    public function delay(int $delay): self
    {
        if ($this->command instanceof QueueableInterface) {
            $this->command->delay($delay);
        }

        return $this;
    }

    /**
     * Dispatch after the current response is sent.
     *
     * @return self
     */
    public function afterResponse(): self
    {
        $this->afterResponse = true;
        return $this;
    }

    /**
     * Handle the object's destruction (auto-dispatch).
     */
    public function __destruct()
    {
        if ($this->afterResponse) {
            $this->dispatcher->dispatchAfterResponse($this->command);
        } else {
            $this->dispatcher->dispatch($this->command);
        }
    }
}
