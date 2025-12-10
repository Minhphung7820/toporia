<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Transports;

use Toporia\Framework\Realtime\Contracts\{TransportInterface, ConnectionInterface, MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Realtime\{Connection, Message};
use Toporia\Framework\Realtime\Auth\ConnectionAuthenticator;

/**
 * Class WebSocketTransport
 *
 * Production-grade WebSocket server using Swoole extension.
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
final class WebSocketTransport implements TransportInterface
{
    private ?\Swoole\WebSocket\Server $server = null;
    private array $connections = [];
    private bool $running = false;

    /**
     * @param array $config Configuration
     * @param RealtimeManagerInterface $manager Realtime manager
     * @param ConnectionAuthenticator|null $authenticator Connection authenticator
     */
    public function __construct(
        private readonly array $config,
        private readonly RealtimeManagerInterface $manager,
        private readonly ?ConnectionAuthenticator $authenticator = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function send(ConnectionInterface $connection, MessageInterface $message): void
    {
        if (!$this->server) {
            throw new \RuntimeException('WebSocket server not started');
        }

        $fd = (int) $connection->getResource();

        if (!$this->server->isEstablished($fd)) {
            return; // Connection closed
        }

        // Zero-copy send (Swoole optimized)
        $this->server->push($fd, $message->toJson(), WEBSOCKET_OPCODE_TEXT);

        $connection->updateLastActivity();
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(MessageInterface $message): void
    {
        if (!$this->server) {
            throw new \RuntimeException('WebSocket server not started');
        }

        $json = $message->toJson(); // Serialize once

        // Broadcast to all connections (O(N) but optimized by Swoole)
        foreach ($this->server->connections as $fd) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $json, WEBSOCKET_OPCODE_TEXT);
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
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException(
                'Swoole extension is required for WebSocket transport. ' .
                    'Install: pecl install swoole'
            );
        }

        $this->server = new \Swoole\WebSocket\Server($host, $port);

        // Performance optimization settings (OPTIMIZED for production)
        $this->server->set([
            'worker_num' => $this->config['worker_num'] ?? swoole_cpu_num() * 2,
            'max_request' => 0,                        // No worker restart limit
            'max_conn' => $this->config['max_connections'] ?? 50000, // Increased from 10K to 50K
            'heartbeat_check_interval' => 30,          // Check every 30s
            'heartbeat_idle_time' => 120,              // Close idle after 2min
            'package_max_length' => 256 * 1024,        // 256KB (reduced from 2MB for security)
            'buffer_output_size' => 32 * 1024 * 1024,  // 32MB output buffer
            'open_tcp_nodelay' => true,                // Disable Nagle (low latency)
            'open_http2_protocol' => false,            // WebSocket only
            'enable_coroutine' => true,                // Enable coroutines
            'max_coroutine' => 100000,                 // Support high concurrency
        ]);

        // SSL/TLS support
        if ($this->config['ssl'] ?? false) {
            $this->server->set([
                'ssl_cert_file' => $this->config['cert'],
                'ssl_key_file' => $this->config['key'],
            ]);
        }

        $this->registerEventHandlers();

        echo "WebSocket server starting on {$host}:{$port}...\n";
        $this->running = true;
        $this->server->start();
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        if ($this->server && $this->running) {
            $this->server->shutdown();
            $this->running = false;
        }
    }

    /**
     * Register Swoole event handlers.
     *
     * @return void
     */
    private function registerEventHandlers(): void
    {
        // Connection opened
        $this->server->on('open', function ($server, $request) {
            // 1. Try to authenticate connection
            $authData = $this->authenticator?->authenticateFromRequest($request);

            // 2. Check if authentication is required on connect
            $requireAuth = $this->config['require_auth'] ?? false;

            if ($requireAuth && $authData === null) {
                // Reject unauthenticated connections if required
                $server->close($request->fd, 4401, 'Authentication required');
                error_log("[{$request->fd}] Rejected: Authentication required");
                return;
            }

            // 3. Create connection with authentication data
            $connection = new Connection($request->fd, [
                'ip' => $request->server['remote_addr'] ?? null,
                'user_agent' => $request->header['user-agent'] ?? null,
                'user_id' => $authData['user_id'] ?? null,
                'username' => $authData['username'] ?? null,
                'roles' => $authData['roles'] ?? [],
                'authenticated_at' => $authData['authenticated_at'] ?? null,
            ]);

            $this->connections[$request->fd] = $connection;
            $this->manager->addConnection($connection);

            if ($authData) {
                echo "[{$request->fd}] Connected: user_id={$authData['user_id']}\n";
            } else {
                echo "[{$request->fd}] Connected: anonymous (IP: {$request->server['remote_addr']})\n";
            }
        });

        // Message received
        $this->server->on('message', function ($server, $frame) {
            if (!isset($this->connections[$frame->fd])) {
                return;
            }

            $connection = $this->connections[$frame->fd];

            try {
                $message = Message::fromJson($frame->data);
                $this->handleMessage($connection, $message);
            } catch (\Throwable $e) {
                $error = Message::error("Invalid message: {$e->getMessage()}", 400);
                $this->send($connection, $error);
            }
        });

        // Connection closed
        $this->server->on('close', function ($server, $fd) {
            if (isset($this->connections[$fd])) {
                $connection = $this->connections[$fd];
                $this->manager->removeConnection($connection);
                unset($this->connections[$fd]);

                echo "[{$fd}] Disconnected\n";
            }
        });

        // Worker started (coroutine context)
        $this->server->on('workerStart', function ($server, $workerId) {
            echo "Worker #{$workerId} started\n";

            // Subscribe to broker if configured
            if ($broker = $this->manager->broker()) {
                // Subscribe in coroutine to avoid blocking
                \Swoole\Coroutine::create(function () use ($broker) {
                    $broker->subscribe('*', function ($message) {
                        $this->broadcast($message);
                    });
                });
            }
        });
    }

    /**
     * Handle incoming message from client.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @return void
     */
    private function handleMessage(ConnectionInterface $connection, MessageInterface $message): void
    {
        $type = $message->getType();

        // Check if authentication is required for operations (except 'auth' and 'ping')
        if (!in_array($type, ['auth', 'ping'], true)) {
            $requireAuthForSubscribe = $this->config['require_auth_for_subscribe'] ?? true;

            if ($requireAuthForSubscribe && $connection->getUserId() === null) {
                $this->send($connection, Message::error('Authentication required. Please authenticate first.', 401));
                return;
            }
        }

        match ($type) {
            'auth' => $this->handleAuth($connection, $message),
            'subscribe' => $this->handleSubscribe($connection, $message),
            'unsubscribe' => $this->handleUnsubscribe($connection, $message),
            'event' => $this->handleEvent($connection, $message),
            'ping' => $this->handlePing($connection),
            default => $this->send($connection, Message::error("Unknown message type: {$message->getType()}", 400))
        };
    }

    /**
     * Handle subscribe request.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @return void
     */
    private function handleSubscribe(ConnectionInterface $connection, MessageInterface $message): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            $this->send($connection, Message::error('Channel name required', 400));
            return;
        }

        $channel = $this->manager->channel($channelName);

        // Check authorization
        if (!$channel->authorize($connection)) {
            $this->send($connection, Message::error("Unauthorized for channel: {$channelName}", 403));
            return;
        }

        $channel->subscribe($connection);

        // Send success response
        $this->send($connection, Message::event($channelName, 'subscribed', [
            'channel' => $channelName,
            'subscribers' => $channel->getSubscriberCount()
        ]));

        // Broadcast presence join for presence channels
        if ($channel->isPresence()) {
            $channel->broadcast(Message::event($channelName, 'presence.join', [
                'user_id' => $connection->getUserId(),
                'user_info' => $connection->get('user_info', [])
            ]), $connection);
        }
    }

    /**
     * Handle unsubscribe request.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @return void
     */
    private function handleUnsubscribe(ConnectionInterface $connection, MessageInterface $message): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            return;
        }

        $channel = $this->manager->channel($channelName);

        // Broadcast presence leave for presence channels
        if ($channel->isPresence() && $channel->hasSubscriber($connection)) {
            $channel->broadcast(Message::event($channelName, 'presence.leave', [
                'user_id' => $connection->getUserId()
            ]), $connection);
        }

        $channel->unsubscribe($connection);

        $this->send($connection, Message::event($channelName, 'unsubscribed', [
            'channel' => $channelName
        ]));
    }

    /**
     * Handle client event (client-to-server message).
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @return void
     */
    private function handleEvent(ConnectionInterface $connection, MessageInterface $message): void
    {
        $channelName = $message->getChannel();

        if (!$channelName) {
            $this->send($connection, Message::error('Channel required for events', 400));
            return;
        }

        // Verify connection is subscribed to channel
        if (!$connection->isSubscribed($channelName)) {
            $this->send($connection, Message::error("Not subscribed to channel: {$channelName}", 403));
            return;
        }

        // Broadcast to channel (excluding sender)
        $channel = $this->manager->channel($channelName);
        $channel->broadcast($message, $connection);
    }

    /**
     * Handle authentication request (two-step auth).
     *
     * Allows clients to authenticate after connection.
     *
     * @param ConnectionInterface $connection
     * @param MessageInterface $message
     * @return void
     */
    private function handleAuth(ConnectionInterface $connection, MessageInterface $message): void
    {
        // Check if already authenticated
        if ($connection->getUserId() !== null) {
            $this->send($connection, Message::error('Already authenticated', 400));
            return;
        }

        // Get token from message data
        $token = $message->getData()['token'] ?? null;

        if (!$token) {
            $this->send($connection, Message::error('Token required', 400));
            return;
        }

        // Authenticate using token
        $authData = $this->authenticator?->authenticateToken($token);

        if ($authData === null) {
            $this->send($connection, Message::error('Invalid token', 401));
            return;
        }

        // Update connection with authentication data
        $connection->set('user_id', $authData['user_id']);
        $connection->set('username', $authData['username']);
        $connection->set('roles', $authData['roles']);
        $connection->set('authenticated_at', $authData['authenticated_at']);

        // Send success response
        $this->send($connection, Message::create('auth_success', null, 'Authenticated successfully', [
            'user_id' => $authData['user_id'],
            'username' => $authData['username'],
            'roles' => $authData['roles'],
        ]));

        echo "[{$connection->getId()}] Authenticated: user_id={$authData['user_id']}\n";
    }

    /**
     * Handle ping request.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    private function handlePing(ConnectionInterface $connection): void
    {
        $this->send($connection, Message::pong());
        $connection->updateLastActivity();
    }
}
