<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Transports;

use Toporia\Framework\Realtime\Contracts\{TransportInterface, ConnectionInterface, MessageInterface, RealtimeManagerInterface};
use Toporia\Framework\Realtime\{Connection, Message};
use Toporia\Framework\Realtime\Subscriptions\BrokerSubscriptionFactory;

/**
 * Class SocketIOGateway
 *
 * Socket.IO compatible gateway for real-time bidirectional communication. Implements Engine.IO protocol v4 with Socket.IO namespace support.
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
final class SocketIOGateway implements TransportInterface
{
    private ?\Swoole\WebSocket\Server $server = null;
    private array $connections = [];
    private array $namespaces = [];
    private array $rooms = [];
    private bool $running = false;
    private int $workerNum = 1;

    // Socket.IO packet types
    private const PACKET_CONNECT = 0;
    private const PACKET_DISCONNECT = 1;
    private const PACKET_EVENT = 2;
    private const PACKET_ACK = 3;
    private const PACKET_ERROR = 4;
    private const PACKET_BINARY_EVENT = 5;
    private const PACKET_BINARY_ACK = 6;

    // Engine.IO packet types
    private const EIO_OPEN = '0';
    private const EIO_CLOSE = '1';
    private const EIO_PING = '2';
    private const EIO_PONG = '3';
    private const EIO_MESSAGE = '4';
    private const EIO_UPGRADE = '5';
    private const EIO_NOOP = '6';

    /**
     * @param array $config Configuration
     * @param RealtimeManagerInterface $manager Realtime manager
     */
    public function __construct(
        private readonly array $config,
        private readonly RealtimeManagerInterface $manager
    ) {}

    /**
     * {@inheritdoc}
     */
    public function send(ConnectionInterface $connection, MessageInterface $message): void
    {
        if (!$this->server) {
            throw new \RuntimeException('Socket.IO gateway not started');
        }

        $fd = (int) $connection->getResource();

        if (!$this->server->isEstablished($fd)) {
            return;
        }

        // Convert to Socket.IO packet
        $namespace = $connection->get('namespace', '/');
        $packet = $this->createSocketIOPacket(
            type: self::PACKET_EVENT,
            namespace: $namespace,
            data: [$message->getEvent(), $message->getData()]
        );

        // Wrap in Engine.IO message packet
        $eioPacket = self::EIO_MESSAGE . $packet;

        $this->server->push($fd, $eioPacket, WEBSOCKET_OPCODE_TEXT);
        $connection->updateLastActivity();
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(MessageInterface $message): void
    {
        if (!$this->server) {
            throw new \RuntimeException('Socket.IO gateway not started');
        }

        // Broadcast to all connections in default namespace
        $this->broadcastToNamespace('/', $message);
    }

    /**
     * {@inheritdoc}
     */
    public function broadcastToChannel(string $channel, MessageInterface $message): void
    {
        // In Socket.IO, channels are called "rooms"
        $this->broadcastToRoom($channel, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function start(string $host, int $port): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException(
                'Swoole extension is required for Socket.IO gateway. ' .
                    'Install: pecl install swoole'
            );
        }

        $this->server = new \Swoole\WebSocket\Server($host, $port);

        // Calculate worker count
        $configWorkerNum = $this->config['worker_num'] ?? 0;
        $workerNum = $configWorkerNum > 0 ? $configWorkerNum : swoole_cpu_num();
        $this->workerNum = $workerNum;

        // Performance optimization
        $this->server->set([
            'worker_num' => $workerNum,
            'max_request' => 0,
            'max_conn' => $this->config['max_connections'] ?? 50000,
            'heartbeat_check_interval' => 25,  // Socket.IO ping interval
            'heartbeat_idle_time' => 60,        // 60s timeout
            'package_max_length' => 4 * 1024 * 1024, // 4MB
            'buffer_output_size' => 32 * 1024 * 1024,  // 32MB output buffer
            'open_tcp_nodelay' => true,
            'enable_coroutine' => true,
            'max_coroutine' => 100000,
            'socket_buffer_size' => 8 * 1024 * 1024,   // 8MB socket buffer
            'send_yield' => true,
            'dispatch_mode' => 2,  // Fixed mode - same connection always goes to same worker
        ]);

        echo "Workers: {$workerNum}, Max connections: " . ($this->config['max_connections'] ?? 50000) . "\n";

        // SSL support
        if ($this->config['ssl'] ?? false) {
            $this->server->set([
                'ssl_cert_file' => $this->config['cert'],
                'ssl_key_file' => $this->config['key'],
            ]);
        }

        $this->registerEventHandlers();

        echo "Socket.IO Gateway starting on {$host}:{$port}...\n";
        echo "Compatible with Socket.IO v4 clients\n";
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
        return 'socketio';
    }

    /**
     * Register Swoole event handlers.
     *
     * @return void
     */
    private function registerEventHandlers(): void
    {
        // WebSocket connection opened
        $this->server->on('open', function ($server, $request) {
            // Send Engine.IO handshake
            $handshake = [
                'sid' => uniqid('', true),
                'upgrades' => ['websocket'],
                'pingInterval' => 25000,
                'pingTimeout' => 60000,
                'maxPayload' => 1000000
            ];

            $openPacket = self::EIO_OPEN . json_encode($handshake);
            $server->push($request->fd, $openPacket);

            // Create connection
            $connection = new Connection($request->fd, [
                'ip' => $request->server['remote_addr'] ?? null,
                'user_agent' => $request->header['user-agent'] ?? null,
                'namespace' => '/', // Default namespace
                'sid' => $handshake['sid'],
            ]);

            $this->connections[$request->fd] = $connection;
            $this->manager->addConnection($connection);

            echo "[{$request->fd}] Socket.IO client connected\n";
        });

        // WebSocket message received
        $this->server->on('message', function ($server, $frame) {
            if (!isset($this->connections[$frame->fd])) {
                return;
            }

            $connection = $this->connections[$frame->fd];

            try {
                $this->handleEngineIOPacket($connection, $frame->data);
            } catch (\Throwable $e) {
                error_log("Socket.IO error: {$e->getMessage()}");
                $this->sendError($connection, $e->getMessage());
            }
        });

        // Connection closed
        $this->server->on('close', function ($server, $fd) {
            if (isset($this->connections[$fd])) {
                $connection = $this->connections[$fd];

                // Remove from all rooms
                $this->removeFromAllRooms($connection);

                $this->manager->removeConnection($connection);
                unset($this->connections[$fd]);

                echo "[{$fd}] Socket.IO client disconnected\n";
            }
        });

        // Worker started
        $this->server->on('workerStart', function ($server, $workerId) {
            echo "Socket.IO Worker #{$workerId} started\n";

            // Start broker subscription in Worker #0 only to avoid duplicate messages
            if ($workerId === 0) {
                $this->startBrokerSubscription($server);
            }
        });

        // Inter-worker communication for multi-worker broadcast
        $this->server->on('pipeMessage', function ($server, $_srcWorkerId, $message) {
            // Received broadcast message from Worker #0 (broker subscription handler)
            // Broadcast to all connections in THIS worker using Socket.IO format
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
     * Uses Strategy/Factory pattern for extensibility.
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
            echo "Broker '{$brokerName}' not supported for Socket.IO gateway (available: {$available})\n";
            return;
        }

        // Message handler callback - broadcasts to all workers using Socket.IO format
        $messageHandler = function (string $channelName, string $event, array $data) use ($server): void {
            // Create Socket.IO formatted packet
            $packet = $this->createSocketIOPacket(
                type: self::PACKET_EVENT,
                namespace: '/',
                data: [$event, array_merge($data, ['channel' => $channelName])]
            );
            $eioPacket = self::EIO_MESSAGE . $packet;

            // Broadcast to connections in Worker #0 (current worker)
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, $eioPacket, WEBSOCKET_OPCODE_TEXT);
                }
            }

            // Send message to all OTHER workers via pipe for them to broadcast
            for ($workerId = 1; $workerId < $this->workerNum; $workerId++) {
                $server->sendMessage($eioPacket, $workerId);
            }
        };

        // Is running callback
        $isRunning = fn(): bool => $this->running;

        // Start subscription using strategy
        $strategy->subscribe($server, $messageHandler, $isRunning);
    }

    /**
     * Handle Engine.IO packet.
     *
     * @param ConnectionInterface $connection
     * @param string $data Raw packet data
     * @return void
     */
    private function handleEngineIOPacket(ConnectionInterface $connection, string $data): void
    {
        if (empty($data)) {
            return;
        }

        $packetType = $data[0];
        $payload = substr($data, 1);

        match ($packetType) {
            self::EIO_MESSAGE => $this->handleSocketIOPacket($connection, $payload),
            self::EIO_PING => $this->sendPong($connection),
            self::EIO_CLOSE => $this->handleDisconnect($connection),
            default => null
        };
    }

    /**
     * Handle Socket.IO packet.
     *
     * @param ConnectionInterface $connection
     * @param string $payload Socket.IO packet
     * @return void
     */
    private function handleSocketIOPacket(ConnectionInterface $connection, string $payload): void
    {
        // Note: '0' is a valid CONNECT packet, so don't use empty() which returns true for '0'
        if ($payload === '') {
            return;
        }

        // Parse Socket.IO packet: type[namespace,][ackId,]data
        $packet = $this->parseSocketIOPacket($payload);

        match ($packet['type']) {
            self::PACKET_CONNECT => $this->handleConnect($connection, $packet),
            self::PACKET_DISCONNECT => $this->handleDisconnect($connection),
            self::PACKET_EVENT => $this->handleEvent($connection, $packet),
            self::PACKET_ACK => $this->handleAck($connection, $packet),
            default => null
        };
    }

    /**
     * Handle Socket.IO CONNECT packet.
     *
     * @param ConnectionInterface $connection
     * @param array $packet
     * @return void
     */
    private function handleConnect(ConnectionInterface $connection, array $packet): void
    {
        $namespace = $packet['namespace'] ?? '/';

        // Set connection namespace
        $connection->set('namespace', $namespace);

        // Add to namespace
        if (!isset($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = [];
        }
        $this->namespaces[$namespace][$connection->getId()] = $connection;

        // Send CONNECT acknowledgment
        $ackPacket = $this->createSocketIOPacket(
            type: self::PACKET_CONNECT,
            namespace: $namespace,
            data: ['sid' => $connection->get('sid')]
        );

        $this->sendRaw($connection, self::EIO_MESSAGE . $ackPacket);

        echo "[{$connection->getId()}] Connected to namespace: {$namespace}\n";
    }

    /**
     * Handle Socket.IO EVENT packet.
     *
     * @param ConnectionInterface $connection
     * @param array $packet
     * @return void
     */
    private function handleEvent(ConnectionInterface $connection, array $packet): void
    {
        $data = $packet['data'] ?? [];

        if (empty($data) || !is_array($data)) {
            return;
        }

        $eventName = array_shift($data); // First element is event name
        $eventData = $data[0] ?? null;   // Second element is event data
        $ackId = $packet['ackId'] ?? null;

        // Special Socket.IO events
        match ($eventName) {
            'join' => $this->handleJoinRoom($connection, $eventData, $ackId),
            'leave' => $this->handleLeaveRoom($connection, $eventData, $ackId),
            default => $this->handleCustomEvent($connection, $eventName, $eventData, $ackId)
        };
    }

    /**
     * Handle custom Socket.IO event.
     *
     * @param ConnectionInterface $connection
     * @param string $eventName
     * @param mixed $eventData
     * @param string|null $ackId
     * @return void
     */
    private function handleCustomEvent(ConnectionInterface $connection, string $eventName, mixed $eventData, ?string $ackId): void
    {
        // Convert to internal message format
        $message = Message::event(null, $eventName, $eventData);

        // Get current rooms/channels
        $rooms = $connection->get('rooms', []);

        // Broadcast to all rooms the user is in
        foreach ($rooms as $room) {
            $channel = $this->manager->channel($room);
            $channel->broadcast($message, $connection); // Exclude sender
        }

        // Send ACK if requested
        if ($ackId !== null) {
            $this->sendAck($connection, $ackId, ['status' => 'ok']);
        }
    }

    /**
     * Handle join room request.
     *
     * @param ConnectionInterface $connection
     * @param mixed $data Room name or array of room names
     * @param string|null $ackId
     * @return void
     */
    private function handleJoinRoom(ConnectionInterface $connection, mixed $data, ?string $ackId): void
    {
        $roomName = is_array($data) ? ($data['room'] ?? $data[0] ?? null) : $data;

        if (!$roomName) {
            return;
        }

        // Add to room
        if (!isset($this->rooms[$roomName])) {
            $this->rooms[$roomName] = [];
        }
        $this->rooms[$roomName][$connection->getId()] = $connection;

        // Track rooms in connection
        $rooms = $connection->get('rooms', []);
        $rooms[] = $roomName;
        $connection->set('rooms', array_unique($rooms));

        // Subscribe to channel
        $channel = $this->manager->channel($roomName);
        $channel->subscribe($connection);

        echo "[{$connection->getId()}] Joined room: {$roomName}\n";

        // Send ACK
        if ($ackId !== null) {
            $this->sendAck($connection, $ackId, ['room' => $roomName]);
        }
    }

    /**
     * Handle leave room request.
     *
     * @param ConnectionInterface $connection
     * @param mixed $data Room name
     * @param string|null $ackId
     * @return void
     */
    private function handleLeaveRoom(ConnectionInterface $connection, mixed $data, ?string $ackId): void
    {
        $roomName = is_array($data) ? ($data['room'] ?? $data[0] ?? null) : $data;

        if (!$roomName) {
            return;
        }

        // Remove from room
        if (isset($this->rooms[$roomName][$connection->getId()])) {
            unset($this->rooms[$roomName][$connection->getId()]);
        }

        // Update connection rooms
        $rooms = $connection->get('rooms', []);
        $rooms = array_diff($rooms, [$roomName]);
        $connection->set('rooms', array_values($rooms));

        // Unsubscribe from channel
        $channel = $this->manager->channel($roomName);
        $channel->unsubscribe($connection);

        echo "[{$connection->getId()}] Left room: {$roomName}\n";

        // Send ACK
        if ($ackId !== null) {
            $this->sendAck($connection, $ackId, ['room' => $roomName]);
        }
    }

    /**
     * Handle ACK packet.
     *
     * @param ConnectionInterface $connection
     * @param array $packet
     * @return void
     */
    private function handleAck(ConnectionInterface $connection, array $packet): void
    {
        // ACK handling for client-initiated events
        // Store callbacks and invoke them here
        // This is for advanced use cases
    }

    /**
     * Handle disconnect.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    private function handleDisconnect(ConnectionInterface $connection): void
    {
        $this->removeFromAllRooms($connection);
    }

    /**
     * Broadcast message to namespace.
     *
     * @param string $namespace
     * @param MessageInterface $message
     * @return void
     */
    private function broadcastToNamespace(string $namespace, MessageInterface $message): void
    {
        $connections = $this->namespaces[$namespace] ?? [];

        $packet = $this->createSocketIOPacket(
            type: self::PACKET_EVENT,
            namespace: $namespace,
            data: [$message->getEvent(), $message->getData()]
        );

        $eioPacket = self::EIO_MESSAGE . $packet;

        foreach ($connections as $connection) {
            $fd = (int) $connection->getResource();
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $eioPacket);
            }
        }
    }

    /**
     * Broadcast message to room.
     *
     * @param string $room
     * @param MessageInterface $message
     * @param ConnectionInterface|null $except
     * @return void
     */
    private function broadcastToRoom(string $room, MessageInterface $message, ?ConnectionInterface $except = null): void
    {
        $connections = $this->rooms[$room] ?? [];

        $packet = $this->createSocketIOPacket(
            type: self::PACKET_EVENT,
            namespace: '/',
            data: [$message->getEvent(), $message->getData()]
        );

        $eioPacket = self::EIO_MESSAGE . $packet;

        foreach ($connections as $connection) {
            if ($except && $connection->getId() === $except->getId()) {
                continue;
            }

            $fd = (int) $connection->getResource();
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $eioPacket);
            }
        }
    }

    /**
     * Send pong response.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    private function sendPong(ConnectionInterface $connection): void
    {
        $this->sendRaw($connection, self::EIO_PONG);
    }

    /**
     * Send ACK packet.
     *
     * @param ConnectionInterface $connection
     * @param string $ackId
     * @param mixed $data
     * @return void
     */
    private function sendAck(ConnectionInterface $connection, string $ackId, mixed $data): void
    {
        $packet = $this->createSocketIOPacket(
            type: self::PACKET_ACK,
            namespace: $connection->get('namespace', '/'),
            data: [$data],
            ackId: $ackId
        );

        $this->sendRaw($connection, self::EIO_MESSAGE . $packet);
    }

    /**
     * Send error packet.
     *
     * @param ConnectionInterface $connection
     * @param string $error
     * @return void
     */
    private function sendError(ConnectionInterface $connection, string $error): void
    {
        $packet = $this->createSocketIOPacket(
            type: self::PACKET_ERROR,
            namespace: $connection->get('namespace', '/'),
            data: $error
        );

        $this->sendRaw($connection, self::EIO_MESSAGE . $packet);
    }

    /**
     * Send raw packet.
     *
     * @param ConnectionInterface $connection
     * @param string $data
     * @return void
     */
    private function sendRaw(ConnectionInterface $connection, string $data): void
    {
        $fd = (int) $connection->getResource();
        if ($this->server && $this->server->isEstablished($fd)) {
            $this->server->push($fd, $data);
        }
    }

    /**
     * Remove connection from all rooms.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    private function removeFromAllRooms(ConnectionInterface $connection): void
    {
        $rooms = $connection->get('rooms', []);

        foreach ($rooms as $room) {
            if (isset($this->rooms[$room][$connection->getId()])) {
                unset($this->rooms[$room][$connection->getId()]);
            }
        }
    }

    /**
     * Create Socket.IO packet string.
     *
     * Format: type[namespace,][ackId,]data
     *
     * @param int $type Packet type
     * @param string $namespace Namespace
     * @param mixed $data Packet data
     * @param string|null $ackId Acknowledgment ID
     * @return string
     */
    private function createSocketIOPacket(int $type, string $namespace, mixed $data = null, ?string $ackId = null): string
    {
        $packet = (string) $type;

        // Add namespace if not default
        if ($namespace !== '/') {
            $packet .= $namespace . ',';
        }

        // Add ACK ID
        if ($ackId !== null) {
            $packet .= $ackId;
        }

        // Add data
        if ($data !== null) {
            $packet .= json_encode($data);
        }

        return $packet;
    }

    /**
     * Parse Socket.IO packet.
     *
     * @param string $packet
     * @return array
     */
    private function parseSocketIOPacket(string $packet): array
    {
        $result = [
            'type' => (int) ($packet[0] ?? self::PACKET_EVENT),
            'namespace' => '/',
            'ackId' => null,
            'data' => null,
        ];

        $remaining = substr($packet, 1);

        // Parse namespace - only if namespace comes before data
        // Namespace format: /custom-ns,data or just data
        if (str_starts_with($remaining, '/')) {
            $commaPos = strpos($remaining, ',');
            if ($commaPos !== false) {
                $result['namespace'] = substr($remaining, 0, $commaPos);
                $remaining = substr($remaining, $commaPos + 1);
            }
        }

        // Parse ack ID (if numeric and before data)
        // AckId is digits before [ or {
        if ($remaining !== '' && ctype_digit($remaining[0])) {
            $ackIdEnd = strcspn($remaining, '[{');
            if ($ackIdEnd > 0 && $ackIdEnd < strlen($remaining)) {
                $result['ackId'] = substr($remaining, 0, $ackIdEnd);
                $remaining = substr($remaining, $ackIdEnd);
            }
        }

        // Parse data (JSON)
        if ($remaining !== '') {
            try {
                $result['data'] = json_decode($remaining, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $result['data'] = $remaining;
            }
        }

        return $result;
    }
}
