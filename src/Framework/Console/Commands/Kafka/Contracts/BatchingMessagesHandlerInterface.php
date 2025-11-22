<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Contracts;

use Toporia\Framework\Support\Collection\Collection;


/**
 * Interface BatchingMessagesHandlerInterface
 *
 * Contract defining the interface for BatchingMessagesHandlerInterface
 * implementations in the Kafka layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Kafka\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
interface BatchingMessagesHandlerInterface
{
    /**
     * Handle a batch of Kafka messages.
     *
     * Each item in the collection is an array with:
     * - 'message': MessageInterface instance
     * - 'metadata': array with partition, offset, topic, timestamp, etc.
     *
     * @param Collection $messages Collection of message arrays
     * @return void
     * @throws \Throwable If batch processing fails (will trigger DLQ for entire batch)
     */
    public function handleMessages(Collection $messages): void;
}
