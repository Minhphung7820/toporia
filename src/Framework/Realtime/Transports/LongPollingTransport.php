<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Transports;

use Toporia\Framework\Realtime\Contracts\{TransportInterface, ConnectionInterface, MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Realtime\{Connection, Message};

/**
 * Class LongPollingTransport
 *
 * HTTP-based fallback for environments where WebSocket is blocked.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Transports
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class LongPollingTransport implements TransportInterface
{
    /** @var array Message queues indexed by connection ID */
    private array $messageQueues = [];

    /** @var array Active polling requests */
    private array $pollingRequests = [];

    /** @var int Default timeout for long-polling (seconds) */
    private int $timeout;

    /**
     * @param array $config Configuration
     * @param RealtimeManagerInterface $manager Realtime manager
     */
    public function __construct(
        private readonly array $config,
        private readonly RealtimeManagerInterface $manager
    ) {
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * {@inheritdoc}
     */
    public function send(ConnectionInterface $connection, MessageInterface $message): void
    {
        $connId = $connection->getId();

        // Add message to queue
        if (!isset($this->messageQueues[$connId])) {
            $this->messageQueues[$connId] = [];
        }

        $this->messageQueues[$connId][] = $message;

        // If client is polling, respond immediately
        if (isset($this->pollingRequests[$connId])) {
            $this->flushMessages($connId);
        }

        $connection->updateLastActivity();
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(MessageInterface $message): void
    {
        // Add to all connection queues
        foreach ($this->messageQueues as $connId => $queue) {
            $this->messageQueues[$connId][] = $message;

            // Flush if polling
            if (isset($this->pollingRequests[$connId])) {
                $this->flushMessages($connId);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function broadcastToChannel(string $channel, MessageInterface $message): void
    {
        $channelObj = $this->manager->channel($channel);
        $channelObj->broadcast($message);
    }

    /**
     * {@inheritdoc}
     */
    public function start(string $host, int $port): void
    {
        echo "Long-Polling Transport ready (handles connections via HTTP router)\n";
        echo "Register routes:\n";
        echo "  GET  {$this->config['path']}/poll   -> LongPollingTransport::handlePoll()\n";
        echo "  POST {$this->config['path']}/send   -> LongPollingTransport::handleSend()\n";
        echo "  POST {$this->config['path']}/subscribe -> LongPollingTransport::handleSubscribe()\n";

        // Long-polling runs within HTTP server
        // Routes must be registered in application
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        // Respond to all pending polling requests
        foreach ($this->pollingRequests as $connId => $data) {
            $this->flushMessages($connId);
        }

        $this->messageQueues = [];
        $this->pollingRequests = [];
    }

    /**
     * Handle poll request (GET /poll?connection_id=xxx).
     *
     * Client polls for new messages. Server holds request until:
     * - Message available → respond immediately
     * - Timeout (30s) → respond with empty array
     * - Connection closed → respond with error
     *
     * Performance Optimization:
     * - Non-blocking wait using usleep()
     * - Batch messages (multiple messages in one response)
     * - Early exit on new messages
     *
     * @param \Toporia\Framework\Http\Contracts\RequestInterface $request
     * @param \Toporia\Framework\Http\Contracts\ResponseInterface $response
     * @return void
     */
    public function handlePoll($request, $response): void
    {
        $connId = $request->query('connection_id');

        if (!$connId) {
            $response->json(['error' => 'connection_id required'], 400);
            return;
        }

        // Initialize queue if not exists
        if (!isset($this->messageQueues[$connId])) {
            $this->messageQueues[$connId] = [];
        }

        // Register polling request
        $this->pollingRequests[$connId] = [
            'started_at' => now()->getTimestamp(),
            'response' => $response,
        ];

        // Wait for messages (long-polling)
        $startTime = now()->getTimestamp();

        while ((now()->getTimestamp() - $startTime) < $this->timeout) {
            // Check if messages available
            if (!empty($this->messageQueues[$connId])) {
                $this->flushMessages($connId);
                return;
            }

            // Non-blocking wait
            usleep(100000); // 100ms

            // Check if client disconnected
            if (connection_aborted()) {
                unset($this->pollingRequests[$connId]);
                return;
            }
        }

        // Timeout - respond with empty array
        unset($this->pollingRequests[$connId]);
        $response->json(['messages' => []]);
    }

    /**
     * Handle send request (POST /send).
     *
     * Client sends message to server (subscribe, unsubscribe, event).
     *
     * @param \Toporia\Framework\Http\Contracts\RequestInterface $request
     * @param \Toporia\Framework\Http\Contracts\ResponseInterface $response
     * @return void
     */
    public function handleSend($request, $response): void
    {
        $connId = $request->input('connection_id');

        if (!$connId) {
            $response->json(['error' => 'connection_id required'], 400);
            return;
        }

        try {
            $messageData = $request->input('message');
            $message = Message::fromArray($messageData);

            // Get connection
            $connection = $this->getOrCreateConnection($connId, $request);

            // Handle message based on type
            match ($message->getType()) {
                'subscribe' => $this->handleSubscribe($connection, $message, $response),
                'unsubscribe' => $this->handleUnsubscribe($connection, $message, $response),
                'event' => $this->handleEvent($connection, $message, $response),
                default => $response->json(['error' => 'Unknown message type'], 400)
            };
        } catch (\Throwable $e) {
            $response->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle subscribe request.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @param mixed $response
     * @return void
     */
    private function handleSubscribe(ConnectionInterface $connection, MessageInterface $message, $response): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            $response->json(['error' => 'Channel name required'], 400);
            return;
        }

        $channel = $this->manager->channel($channelName);

        // Check authorization
        if (!$channel->authorize($connection)) {
            $response->json(['error' => 'Unauthorized'], 403);
            return;
        }

        $channel->subscribe($connection);

        $response->json([
            'success' => true,
            'channel' => $channelName,
            'subscribers' => $channel->getSubscriberCount()
        ]);
    }

    /**
     * Handle unsubscribe request.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @param mixed $response
     * @return void
     */
    private function handleUnsubscribe(ConnectionInterface $connection, MessageInterface $message, $response): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            $response->json(['error' => 'Channel name required'], 400);
            return;
        }

        $channel = $this->manager->channel($channelName);
        $channel->unsubscribe($connection);

        $response->json(['success' => true]);
    }

    /**
     * Handle client event.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @param mixed $response
     * @return void
     */
    private function handleEvent(ConnectionInterface $connection, MessageInterface $message, $response): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            $response->json(['error' => 'Channel required'], 400);
            return;
        }

        if (!$connection->isSubscribed($channelName)) {
            $response->json(['error' => 'Not subscribed to channel'], 403);
            return;
        }

        // Broadcast to channel
        $channel = $this->manager->channel($channelName);
        $channel->broadcast($message, $connection);

        $response->json(['success' => true]);
    }

    /**
     * Flush queued messages to polling client.
     *
     * @param string $connId Connection ID
     * @return void
     */
    private function flushMessages(string $connId): void
    {
        if (!isset($this->pollingRequests[$connId])) {
            return;
        }

        $messages = $this->messageQueues[$connId] ?? [];
        $response = $this->pollingRequests[$connId]['response'];

        // Convert messages to arrays
        $messagesData = array_map(fn($msg) => $msg->toArray(), $messages);

        $response->json([
            'messages' => $messagesData,
            'count' => count($messagesData)
        ]);

        // Clear queue and polling request
        $this->messageQueues[$connId] = [];
        unset($this->pollingRequests[$connId]);
    }

    /**
     * Get or create connection object.
     *
     * @param string $connId Connection ID
     * @param mixed $request HTTP request
     * @return ConnectionInterface
     */
    private function getOrCreateConnection(string $connId, $request): ConnectionInterface
    {
        // Try to get existing connection from manager
        foreach ($this->manager->getAllConnections() as $conn) {
            if ($conn->getId() === $connId) {
                return $conn;
            }
        }

        // Create new connection
        $connection = new Connection($connId, [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        $connection->setId($connId); // Use client-provided ID

        $this->manager->addConnection($connection);
        $this->messageQueues[$connId] = [];

        return $connection;
    }

    /**
     * Clean up inactive connections.
     *
     * Remove connections that haven't polled in a while.
     *
     * @param int $timeout Inactivity timeout (seconds)
     * @return int Number of connections cleaned up
     */
    public function cleanupInactiveConnections(int $timeout = 300): int
    {
        $removed = 0;
        $now = now()->getTimestamp();

        foreach ($this->manager->getAllConnections() as $connection) {
            if (($now - $connection->getLastActivityAt()) > $timeout) {
                $this->manager->removeConnection($connection);
                unset($this->messageQueues[$connection->getId()]);
                $removed++;
            }
        }

        return $removed;
    }
}
