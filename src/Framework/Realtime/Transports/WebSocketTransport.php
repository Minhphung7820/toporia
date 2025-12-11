<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Transports;

use Toporia\Framework\Realtime\Contracts\{TransportInterface, ConnectionInterface, MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Realtime\{Connection, Message};
use Toporia\Framework\Realtime\Auth\ConnectionAuthenticator;
use Toporia\Framework\Realtime\Subscriptions\BrokerSubscriptionFactory;

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
    private int $workerNum = 1;

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

        // Calculate worker count
        // For WebSocket: use CPU count (not *2) for optimal I/O performance
        // 0 means auto-detect, which Swoole will use swoole_cpu_num()
        $configWorkerNum = $this->config['worker_num'] ?? 0;
        $workerNum = $configWorkerNum > 0 ? $configWorkerNum : swoole_cpu_num();

        // Performance optimization settings (OPTIMIZED for production)
        $this->server->set([
            'worker_num' => $workerNum,
            'max_request' => 0,                        // No worker restart limit (long-lived connections)
            'max_conn' => $this->config['max_connections'] ?? 50000,
            'heartbeat_check_interval' => 30,          // Check every 30s
            'heartbeat_idle_time' => 120,              // Close idle after 2min
            'package_max_length' => 256 * 1024,        // 256KB max message size
            'buffer_output_size' => 32 * 1024 * 1024,  // 32MB output buffer
            'open_tcp_nodelay' => true,                // Disable Nagle for low latency
            'open_http2_protocol' => false,            // WebSocket only
            'enable_coroutine' => true,                // Enable coroutines for async I/O
            'max_coroutine' => 100000,                 // High concurrency support
            'socket_buffer_size' => 8 * 1024 * 1024,   // 8MB socket buffer
            'send_yield' => true,                      // Yield when send buffer is full (prevents blocking)
            'dispatch_mode' => 2,                      // Fixed mode - same connection always goes to same worker
        ]);

        // Store worker count for use in handleRedisMessage
        $this->workerNum = $workerNum;

        echo "Workers: {$workerNum}, Max connections: " . ($this->config['max_connections'] ?? 50000) . "\n";

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
     * {@inheritdoc}
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * {@inheritdoc}
     */
    public function hasConnection(string $connectionId): bool
    {
        return isset($this->connections[$connectionId]);
    }

    /**
     * {@inheritdoc}
     */
    public function close(ConnectionInterface $connection, int $code = 1000, string $reason = ''): void
    {
        if (!$this->server) {
            return;
        }

        $fd = (int) $connection->getResource();

        if ($this->server->isEstablished($fd)) {
            $this->server->close($fd, $code, $reason);
        }

        unset($this->connections[$fd]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'websocket';
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
            $ipAddress = $request->server['remote_addr'] ?? 'unknown';

            // 0. DDoS Protection Check (v2 - if enabled)
            $ddosProtection = $this->manager->getDDoSProtection();
            if ($ddosProtection !== null) {
                if (!$ddosProtection->isAllowed($ipAddress)) {
                    $server->close($request->fd, 4429, 'Too many connections - DDoS protection');
                    error_log("[{$request->fd}] Blocked by DDoS protection: IP {$ipAddress}");
                    return;
                }

                // Record this connection
                $ddosProtection->recordConnection($ipAddress);
            }

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
                'ip_address' => $ipAddress,
                'remote_address' => $ipAddress, // Alias for compatibility
                'user_agent' => $request->header['user-agent'] ?? null,
                'user_id' => $authData['user_id'] ?? null,
                'username' => $authData['username'] ?? null,
                'roles' => $authData['roles'] ?? [],
                'authenticated_at' => $authData['authenticated_at'] ?? null,
            ]);

            $this->connections[$request->fd] = $connection;
            $this->manager->addConnection($connection);

            $workerId = $server->worker_id;
            if ($authData) {
                echo "[Worker #{$workerId}][{$request->fd}] Connected: user_id={$authData['user_id']} IP={$ipAddress}\n";
            } else {
                echo "[Worker #{$workerId}][{$request->fd}] Connected: anonymous (IP: {$ipAddress})\n";
            }
        });

        // Message received
        $this->server->on('message', function ($server, $frame) {
            if (!isset($this->connections[$frame->fd])) {
                return;
            }

            $connection = $this->connections[$frame->fd];

            // Multi-layer rate limiting (v2 - if enabled)
            $multiLayerLimiter = $this->manager->getMultiLayerLimiter();
            if ($multiLayerLimiter !== null) {
                try {
                    $multiLayerLimiter->check($connection, null, 1);
                } catch (\Toporia\Framework\Realtime\Exceptions\RateLimitException $e) {
                    // Send rate limit error to client
                    $errorMsg = Message::event(null, 'error', [
                        'code' => 'rate_limit_exceeded',
                        'message' => 'Rate limit exceeded',
                        'retry_after' => $e->getRetryAfter(),
                    ]);
                    $server->push($frame->fd, $errorMsg->toJson());
                    return;
                }
            }

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

            // Start broker subscription in coroutine (only in worker 0 to avoid duplicate messages)
            if ($workerId === 0) {
                $this->startBrokerSubscription($server);
            }
        });

        // Inter-worker communication for multi-worker broadcast
        $this->server->on('pipeMessage', function ($server, $srcWorkerId, $message) {
            $workerId = $server->worker_id;
            echo "[Worker #{$workerId}] Received pipeMessage from Worker #{$srcWorkerId}\n";

            // Received message from Worker #0 (broker subscription handler)
            $decoded = json_decode($message, true);

            if ($decoded && ($decoded['type'] ?? '') === 'channel_broadcast') {
                $channelName = $decoded['channel'] ?? '';
                $event = $decoded['event'] ?? 'message';
                $data = $decoded['data'] ?? [];

                echo "[Worker #{$workerId}] Broadcasting to channel: {$channelName}, has " . count($this->connections) . " connections\n";

                if ($channelName) {
                    // Broadcast directly to subscribed connections in THIS worker
                    $msg = Message::event($channelName, $event, $data);
                    $json = $msg->toJson();

                    $sentCount = 0;
                    foreach ($this->connections as $connection) {
                        $subscribed = $connection->isSubscribed($channelName);
                        echo "[Worker #{$workerId}] Connection {$connection->getId()} subscribed to {$channelName}: " . ($subscribed ? 'yes' : 'no') . "\n";
                        if ($subscribed) {
                            $fd = (int) $connection->getResource();
                            if ($server->isEstablished($fd)) {
                                $server->push($fd, $json, WEBSOCKET_OPCODE_TEXT);
                                $sentCount++;
                            }
                        }
                    }
                    echo "[Worker #{$workerId}] Sent to {$sentCount} connections\n";
                }
            }
        });
    }

    /**
     * Start broker subscription based on configuration.
     *
     * Uses Strategy/Factory pattern for extensibility.
     * Automatically detects which broker is configured and starts the appropriate subscription.
     *
     * @param \Swoole\WebSocket\Server $server
     * @return void
     */
    private function startBrokerSubscription(\Swoole\WebSocket\Server $server): void
    {
        $brokerName = config('realtime.default_broker') ?: env('REALTIME_BROKER');

        if (!$brokerName) {
            echo "Broker: none (single server mode)\n";
            return;
        }

        echo "Broker: {$brokerName}\n";

        // Create factory with broker configs
        $factory = BrokerSubscriptionFactory::createWithDefaults([
            'redis' => config('realtime.brokers.redis', []),
            'rabbitmq' => config('realtime.brokers.rabbitmq', []),
            'kafka' => config('realtime.brokers.kafka', []),
        ]);

        // Get strategy for configured broker
        $strategy = $factory->create($brokerName);

        if ($strategy === null) {
            $available = implode(', ', $factory->getAvailableStrategies());
            echo "Broker '{$brokerName}' not supported for WebSocket transport (available: {$available})\n";
            return;
        }

        // Message handler callback - broadcasts to channel subscribers in all workers
        $messageHandler = function (string $channelName, string $event, array $data) use ($server): void {
            $message = Message::event($channelName, $event, $data);
            $json = $message->toJson();

            echo "[Broker] Received message for channel: {$channelName}, event: {$event}\n";
            echo "[Broker] Worker #0 has " . count($this->connections) . " connections\n";

            // Broadcast to subscribers in Worker #0 (current worker)
            $sentCount = 0;
            foreach ($this->connections as $connection) {
                $subscribed = $connection->isSubscribed($channelName);
                echo "[Broker] Connection {$connection->getId()} subscribed to {$channelName}: " . ($subscribed ? 'yes' : 'no') . "\n";
                if ($subscribed) {
                    $fd = (int) $connection->getResource();
                    if ($server->isEstablished($fd)) {
                        $server->push($fd, $json, WEBSOCKET_OPCODE_TEXT);
                        $sentCount++;
                    }
                }
            }
            echo "[Broker] Sent to {$sentCount} connections in Worker #0\n";

            // For multi-worker setup, send to other workers via pipe
            if ($this->workerNum > 1) {
                echo "[Broker] Sending to " . ($this->workerNum - 1) . " other workers via pipe\n";
                $pipeMessage = json_encode([
                    'type' => 'channel_broadcast',
                    'channel' => $channelName,
                    'event' => $event,
                    'data' => $data,
                ]);

                for ($workerId = 1; $workerId < $this->workerNum; $workerId++) {
                    $server->sendMessage($pipeMessage, $workerId);
                }
            }
        };

        // Is running callback
        $isRunning = fn(): bool => $this->running;

        // Start subscription using strategy
        $strategy->subscribe($server, $messageHandler, $isRunning);
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
            // Check config - prefer explicit false from env
            $envValue = env('REALTIME_REQUIRE_AUTH_SUBSCRIBE');
            $requireAuthForSubscribe = $envValue === false || $envValue === 'false'
                ? false
                : ($this->config['require_auth_for_subscribe'] ?? true);

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

        // Log subscription for debugging
        if ($this->server) {
            $workerId = $this->server->worker_id;
            echo "[Worker #{$workerId}] Connection {$connection->getId()} subscribed to channel: {$channelName}\n";
            echo "[Worker #{$workerId}] Connection channels: " . implode(', ', $connection->getChannels()) . "\n";
        }

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
