<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus\Contracts;

/**
 * Should Queue Interface
 *
 * Marker interface indicating that a command/job should be queued by default.
 */
interface ShouldQueueInterface extends QueueableInterface
{
    //
}
