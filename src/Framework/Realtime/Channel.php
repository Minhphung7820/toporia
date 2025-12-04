<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Realtime\Contracts\{ChannelInterface, ConnectionInterface, MessageInterface, TransportInterface};

/**
 * Class Channel
 *
 * Manages connections subscribed to a specific channel/topic.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class Channel implements ChannelInterface
{
    /**
     * @var array<string, ConnectionInterface> Subscribers indexed by connection ID
     */
    private array $subscribers = [];

    /**
     * @param string $name Channel name
     * @param TransportInterface|null $transport Transport for broadcasting
     * @param callable|null $authorizer Authorization callback
     */
    public function __construct(
        private readonly string $name,
        private readonly ?TransportInterface $transport = null,
        private readonly mixed $authorizer = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function isPublic(): bool
    {
        return !$this->isPrivate() && !$this->isPresence();
    }

    /**
     * {@inheritdoc}
     */
    public function isPrivate(): bool
    {
        return str_starts_with($this->name, 'private-')
            || str_starts_with($this->name, 'private.')
            || str_starts_with($this->name, 'user.');
    }

    /**
     * {@inheritdoc}
     */
    public function isPresence(): bool
    {
        return str_starts_with($this->name, 'presence-')
            || str_starts_with($this->name, 'presence.');
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriberCount(): int
    {
        return count($this->subscribers);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribers(): array
    {
        return array_values($this->subscribers);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(ConnectionInterface $connection): void
    {
        $this->subscribers[$connection->getId()] = $connection;
        $connection->subscribe($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(ConnectionInterface $connection): void
    {
        unset($this->subscribers[$connection->getId()]);
        $connection->unsubscribe($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasSubscriber(ConnectionInterface $connection): bool
    {
        return isset($this->subscribers[$connection->getId()]);
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(MessageInterface $message, ?ConnectionInterface $except = null): void
    {
        if (!$this->transport) {
            return; // No transport available
        }

        foreach ($this->subscribers as $connection) {
            // Skip excluded connection
            if ($except && $connection->getId() === $except->getId()) {
                continue;
            }

            try {
                $this->transport->send($connection, $message);
            } catch (\Throwable $e) {
                // Log error but continue broadcasting
                error_log("Failed to send to connection {$connection->getId()}: {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(ConnectionInterface $connection): bool
    {
        // Public channels always authorized
        if ($this->isPublic()) {
            return true;
        }

        // No authorizer set - deny private channels
        if (!$this->authorizer) {
            return false;
        }

        // Call custom authorizer
        try {
            return (bool) call_user_func($this->authorizer, $connection, $this->name);
        } catch (\Throwable $e) {
            error_log("Authorization failed for {$this->name}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get presence data for presence channels.
     *
     * Returns list of online users with their data.
     *
     * @return array
     */
    public function getPresenceData(): array
    {
        if (!$this->isPresence()) {
            return [];
        }

        $presence = [];

        foreach ($this->subscribers as $connection) {
            $userId = $connection->getUserId();
            if ($userId === null) {
                continue;
            }

            $presence[] = [
                'user_id' => $userId,
                'user_info' => $connection->get('user_info', []),
                'connected_at' => $connection->getConnectedAt()
            ];
        }

        return $presence;
    }
}
