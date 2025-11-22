<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Contracts;

use Toporia\Framework\Realtime\Contracts\MessageInterface;


/**
 * Interface SingleMessageHandlerInterface
 *
 * Contract defining the interface for SingleMessageHandlerInterface
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
interface SingleMessageHandlerInterface
{
    /**
     * Handle a single Kafka message.
     *
     * @param MessageInterface $message Consumed message
     * @param array<string, mixed> $metadata Message metadata (partition, offset, topic, etc.)
     * @return void
     * @throws \Throwable If message processing fails (will trigger DLQ)
     */
    public function handleMessage(MessageInterface $message, array $metadata = []): void;
}
