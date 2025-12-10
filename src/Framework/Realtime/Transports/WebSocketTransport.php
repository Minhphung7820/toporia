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

            if ($authData) {
                echo "[{$request->fd}] Connected: user_id={$authData['user_id']} IP={$ipAddress}\n";
            } else {
                echo "[{$request->fd}] Connected: anonymous (IP: {$ipAddress})\n";
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
        $this->server->on('pipeMessage', function ($server, $_srcWorkerId, $message) {
            // Received broadcast message from Worker #0 (Redis subscription handler)
            // Broadcast to all connections in THIS worker
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, $message, WEBSOCKET_OPCODE_TEXT);
                }
            }
        });
    }

    /**
     * Start broker subscription based on configuration.
     *
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

        switch ($brokerName) {
            case 'redis':
                $this->startRedisBrokerSubscription($server);
                break;
            case 'rabbitmq':
                $this->startRabbitMqBrokerSubscription($server);
                break;
            default:
                echo "Broker '{$brokerName}' not supported for WebSocket transport (use redis or rabbitmq)\n";
        }
    }

    /**
     * Start Redis broker subscription using Swoole coroutine.
     *
     * This allows the WebSocket server to receive messages from PHP-FPM
     * via Redis Pub/Sub without blocking the event loop.
     *
     * Features:
     * - Auto-reconnect with exponential backoff (1s -> 2s -> 4s -> ... -> 30s max)
     * - Graceful error handling
     * - Production-ready reliability
     *
     * @param \Swoole\WebSocket\Server $server
     * @return void
     */
    private function startRedisBrokerSubscription(\Swoole\WebSocket\Server $server): void
    {
        // Load Redis broker config
        $brokerConfig = config('realtime.brokers.redis', []);

        // Start subscription in a coroutine with auto-reconnect
        \Swoole\Coroutine::create(function () use ($server, $brokerConfig) {
            $host = $brokerConfig['host'] ?? env('REDIS_HOST', '127.0.0.1');
            $port = (int) ($brokerConfig['port'] ?? env('REDIS_PORT', 6379));
            $password = $brokerConfig['password'] ?? env('REDIS_PASSWORD');
            $pattern = 'realtime:*';

            // Exponential backoff settings
            $baseDelay = 1.0;      // Start with 1 second
            $maxDelay = 30.0;      // Max 30 seconds between retries
            $currentDelay = $baseDelay;
            $consecutiveFailures = 0;

            // Auto-reconnect loop
            while ($this->running) {
                echo "[Redis Broker] Connecting to {$host}:{$port}...\n";

                $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
                $client->set([
                    'open_eof_check' => false,
                    'package_max_length' => 1024 * 1024,
                ]);

                // Try to connect
                if (!$client->connect($host, $port, 5.0)) {
                    $consecutiveFailures++;
                    echo "[Redis Broker] Failed to connect: {$client->errMsg} (attempt #{$consecutiveFailures})\n";

                    // Exponential backoff
                    $currentDelay = min($baseDelay * pow(2, $consecutiveFailures - 1), $maxDelay);
                    echo "[Redis Broker] Retrying in {$currentDelay}s...\n";
                    \Swoole\Coroutine::sleep($currentDelay);
                    continue;
                }

                // Authenticate if password is set
                if ($password && $password !== 'null') {
                    $authCmd = "*2\r\n\$4\r\nAUTH\r\n\$" . strlen($password) . "\r\n{$password}\r\n";
                    $client->send($authCmd);
                    $authResponse = $client->recv(5.0);
                    if (!$authResponse || !str_starts_with($authResponse, '+OK')) {
                        echo "[Redis Broker] Authentication failed: {$authResponse}\n";
                        $client->close();

                        $consecutiveFailures++;
                        $currentDelay = min($baseDelay * pow(2, $consecutiveFailures - 1), $maxDelay);
                        \Swoole\Coroutine::sleep($currentDelay);
                        continue;
                    }
                }

                // Send PSUBSCRIBE command
                $psubscribeCmd = "*2\r\n\$10\r\nPSUBSCRIBE\r\n\$" . strlen($pattern) . "\r\n{$pattern}\r\n";
                $client->send($psubscribeCmd);

                // Read subscription confirmation
                $confirmation = $client->recv(5.0);
                if (!$confirmation) {
                    echo "[Redis Broker] Failed to subscribe\n";
                    $client->close();

                    $consecutiveFailures++;
                    $currentDelay = min($baseDelay * pow(2, $consecutiveFailures - 1), $maxDelay);
                    \Swoole\Coroutine::sleep($currentDelay);
                    continue;
                }

                // Reset backoff on successful connection
                $consecutiveFailures = 0;
                $currentDelay = $baseDelay;
                echo "[Redis Broker] Connected and subscribed to pattern: {$pattern}\n";

                // Main loop - receive messages
                while ($this->running) {
                    $response = $client->recv(86400.0); // Long timeout for blocking receive

                    if ($response === false || $response === '') {
                        echo "[Redis Broker] Connection lost, reconnecting...\n";
                        break; // Break inner loop to reconnect
                    }

                    // Parse RESP protocol message
                    $this->handleRedisMessage($response, $server);
                }

                $client->close();

                // Small delay before reconnect attempt
                if ($this->running) {
                    \Swoole\Coroutine::sleep(1.0);
                }
            }

            echo "[Redis Broker] Subscription stopped\n";
        });
    }

    /**
     * Parse and handle Redis RESP protocol message from PSUBSCRIBE.
     *
     * @param string $response Raw Redis RESP response
     * @param \Swoole\WebSocket\Server $server
     * @return void
     */
    private function handleRedisMessage(string $response, \Swoole\WebSocket\Server $server): void
    {
        // Parse RESP array response for pmessage
        // Format: *4\r\n$8\r\npmessage\r\n$patternLen\r\npattern\r\n$channelLen\r\nchannel\r\n$msgLen\r\nmessage\r\n
        $lines = explode("\r\n", $response);

        // Find pmessage in response
        $pmessageIdx = array_search('pmessage', $lines);
        if ($pmessageIdx === false) {
            return; // Not a pmessage
        }

        // After pmessage, we have: $patternLen, pattern, $channelLen, channel, $msgLen, message
        // So pattern is at pmessageIdx + 2, channel is at pmessageIdx + 4, message is at pmessageIdx + 6
        $channel = $lines[$pmessageIdx + 4] ?? '';
        $messageJson = $lines[$pmessageIdx + 6] ?? '';

        if (!$channel || !$messageJson) {
            return;
        }

        try {
            $messageData = json_decode($messageJson, true);

            if (!$messageData) {
                return;
            }

            // Extract channel name (remove 'realtime:' prefix)
            $channelName = str_replace('realtime:', '', $channel);
            $event = $messageData['event'] ?? 'message';
            $data = $messageData['data'] ?? [];

            // Create message and serialize once
            $message = Message::event($channelName, $event, $data);
            $json = $message->toJson();

            // Broadcast to connections in Worker #0 (current worker)
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, $json, WEBSOCKET_OPCODE_TEXT);
                }
            }

            // Send message to all OTHER workers via pipe for them to broadcast
            // Worker #0 handles Redis, but connections may be in workers #1, #2, #3...
            // Use stored workerNum from start() to avoid recalculating
            for ($workerId = 1; $workerId < $this->workerNum; $workerId++) {
                $server->sendMessage($json, $workerId);
            }
        } catch (\Throwable $e) {
            error_log("[Redis] Error: {$e->getMessage()}");
        }
    }

    /**
     * Start RabbitMQ broker subscription using Swoole coroutine.
     *
     * Uses PhpAmqpLib with non-blocking wait to receive messages from PHP-FPM
     * via RabbitMQ without blocking Swoole's event loop.
     *
     * Features:
     * - Auto-reconnect with exponential backoff (1s -> 2s -> 4s -> ... -> 30s max)
     * - Non-blocking message consumption with periodic yield
     * - Graceful error handling
     * - Production-ready reliability
     *
     * @param \Swoole\WebSocket\Server $server
     * @return void
     */
    private function startRabbitMqBrokerSubscription(\Swoole\WebSocket\Server $server): void
    {
        // Load RabbitMQ broker config
        $brokerConfig = config('realtime.brokers.rabbitmq', []);

        // Start subscription in a coroutine with auto-reconnect
        \Swoole\Coroutine::create(function () use ($server, $brokerConfig) {
            $host = $brokerConfig['host'] ?? env('RABBITMQ_HOST', '127.0.0.1');
            $port = (int) ($brokerConfig['port'] ?? env('RABBITMQ_PORT', 5672));
            $user = $brokerConfig['user'] ?? env('RABBITMQ_USER', 'guest');
            $password = $brokerConfig['password'] ?? env('RABBITMQ_PASSWORD', 'guest');
            $vhost = $brokerConfig['vhost'] ?? env('RABBITMQ_VHOST', '/');
            $exchange = $brokerConfig['exchange'] ?? 'realtime';
            $exchangeType = $brokerConfig['exchange_type'] ?? 'topic';

            // Exponential backoff settings
            $baseDelay = 1.0;
            $maxDelay = 30.0;
            $currentDelay = $baseDelay;
            $consecutiveFailures = 0;

            // Auto-reconnect loop
            while ($this->running) {
                $connection = null;
                $channel = null;

                try {
                    echo "[RabbitMQ Broker] Connecting to {$host}:{$port}...\n";

                    // Create connection using PhpAmqpLib
                    $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                        $host,
                        $port,
                        $user,
                        $password,
                        $vhost,
                        false,    // insist
                        'AMQPLAIN', // login_method
                        null,     // login_response
                        'en_US',  // locale
                        3.0,      // connection_timeout
                        3.0,      // read_write_timeout
                        null,     // context
                        false,    // keepalive
                        60        // heartbeat
                    );

                    $channel = $connection->channel();

                    // Declare exchange
                    $channel->exchange_declare(
                        $exchange,
                        $exchangeType,
                        false,  // passive
                        true,   // durable
                        false   // auto_delete
                    );

                    // Declare exclusive queue for this WebSocket server
                    [$queueName] = $channel->queue_declare(
                        '',     // Let RabbitMQ generate name
                        false,  // passive
                        false,  // durable
                        true,   // exclusive
                        true    // auto_delete
                    );

                    // Bind queue to receive all messages (using # wildcard)
                    $channel->queue_bind($queueName, $exchange, '#');

                    // Set prefetch count for performance
                    $channel->basic_qos(null, 100, null);

                    // Set up consumer callback
                    $channel->basic_consume(
                        $queueName,
                        '',     // consumer_tag
                        false,  // no_local
                        true,   // no_ack (auto-ack for simplicity)
                        false,  // exclusive
                        false,  // nowait
                        function (\PhpAmqpLib\Message\AMQPMessage $message) use ($server) {
                            $this->handleRabbitMqMessage($message, $server);
                        }
                    );

                    // Reset backoff on successful connection
                    $consecutiveFailures = 0;
                    $currentDelay = $baseDelay;
                    echo "[RabbitMQ Broker] Connected and consuming from exchange: {$exchange}\n";

                    // Non-blocking consume loop
                    while ($this->running && $channel->is_consuming()) {
                        try {
                            // Non-blocking wait with short timeout
                            $channel->wait(null, true, 0.1);
                        } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                            // Expected timeout, yield to Swoole event loop
                            \Swoole\Coroutine::sleep(0.01);
                            continue;
                        }

                        // Yield to allow other coroutines to run
                        \Swoole\Coroutine::sleep(0.001);
                    }
                } catch (\Throwable $e) {
                    $consecutiveFailures++;
                    echo "[RabbitMQ Broker] Error: {$e->getMessage()} (attempt #{$consecutiveFailures})\n";

                    // Exponential backoff
                    $currentDelay = min($baseDelay * pow(2, $consecutiveFailures - 1), $maxDelay);
                    echo "[RabbitMQ Broker] Retrying in {$currentDelay}s...\n";
                } finally {
                    // Clean up
                    try {
                        $channel?->close();
                    } catch (\Throwable $e) {
                        // Ignore close errors
                    }
                    try {
                        $connection?->close();
                    } catch (\Throwable $e) {
                        // Ignore close errors
                    }
                }

                // Wait before reconnect
                if ($this->running) {
                    \Swoole\Coroutine::sleep($currentDelay);
                }
            }

            echo "[RabbitMQ Broker] Subscription stopped\n";
        });
    }

    /**
     * Handle incoming message from RabbitMQ.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @param \Swoole\WebSocket\Server $server
     * @return void
     */
    private function handleRabbitMqMessage(\PhpAmqpLib\Message\AMQPMessage $message, \Swoole\WebSocket\Server $server): void
    {
        try {
            $routingKey = $message->getRoutingKey();
            $payload = $message->getBody();

            $messageData = json_decode($payload, true);
            if (!$messageData) {
                return;
            }

            // Convert routing key to channel name (. -> :)
            $channelName = str_replace('.', ':', $routingKey);
            $event = $messageData['event'] ?? 'message';
            $data = $messageData['data'] ?? [];

            // Create message and serialize once
            $wsMessage = Message::event($channelName, $event, $data);
            $json = $wsMessage->toJson();

            // Broadcast to connections in Worker #0 (current worker)
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, $json, WEBSOCKET_OPCODE_TEXT);
                }
            }

            // Send message to all OTHER workers via pipe for them to broadcast
            for ($workerId = 1; $workerId < $this->workerNum; $workerId++) {
                $server->sendMessage($json, $workerId);
            }
        } catch (\Throwable $e) {
            error_log("[RabbitMQ] Error: {$e->getMessage()}");
        }
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
