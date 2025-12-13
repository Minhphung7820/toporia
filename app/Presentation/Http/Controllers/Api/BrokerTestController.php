<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Realtime\Broadcast;
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * BrokerTestController
 *
 * API Controller for testing realtime broker system.
 * Supports Redis, RabbitMQ, and Kafka brokers.
 *
 * Examples using Broadcast Facade:
 *
 *   // Quick send (recommended for simple cases)
 *   Broadcast::send('channel', 'event', $data, 'kafka');
 *
 *   // Fluent API with specific driver
 *   Broadcast::via('kafka')->toChannel('events')->event('user.action')->with($data)->now();
 *
 *   // Start with channel (uses default driver)
 *   Broadcast::channel('events')->event('user.action')->with($data)->now();
 *
 *   // Private channel
 *   Broadcast::private('user.123')->event('notification')->with($data)->now();
 *
 *   // Helper function
 *   broadcast('channel', 'event', $data, 'kafka');
 */
final class BrokerTestController
{
    public function __construct(
        private readonly RealtimeManager $realtime
    ) {}

    /**
     * Publish a message to broker.
     *
     * POST /api/broker/publish
     * Body: {
     *   "channel": "test.channel",
     *   "event": "test.event",
     *   "data": {"message": "Hello World"},
     *   "driver": "redis"  // redis, rabbitmq, kafka
     * }
     */
    public function publish(Request $request)
    {
        $startTime = microtime(true);

        $channel = $request->input('channel', 'test.channel');
        $event = $request->input('event', 'test.event');
        $data = $request->input('data', ['message' => 'Hello from BrokerTestController']);
        $driver = $request->input('driver', 'redis');

        try {
            // New fluent API with Broadcast facade
            // Use toChannel() after via() since via() returns instance
            $success = Broadcast::via($driver)
                ->toChannel($channel)
                ->event($event)
                ->with($data)
                ->now();

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'error' => "Broker [{$driver}] is not configured or failed to publish",
                    'available_brokers' => ['redis', 'rabbitmq', 'kafka']
                ], 400);
            }

            $duration = (microtime(true) - $startTime) * 1000;

            return response()->json([
                'success' => true,
                'message' => 'Message published successfully',
                'details' => [
                    'channel' => $channel,
                    'event' => $event,
                    'driver' => $driver,
                    'data' => $data,
                    'duration_ms' => round($duration, 2),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'driver' => $driver
            ], 500);
        }
    }

    /**
     * Publish using helper function (alternative syntax).
     *
     * POST /api/broker/publish-alt
     */
    public function publishAlt(Request $request)
    {
        $startTime = microtime(true);

        $channel = $request->input('channel', 'test.channel');
        $event = $request->input('event', 'test.event');
        $data = $request->input('data', ['message' => 'Hello from helper']);
        $driver = $request->input('driver', 'redis');

        try {
            // Using helper function
            $success = broadcast($channel, $event, $data, $driver);

            $duration = (microtime(true) - $startTime) * 1000;

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Published via helper' : 'Failed to publish',
                'duration_ms' => round($duration, 2)
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check for all brokers.
     *
     * GET /api/broker/health
     */
    public function health()
    {
        $results = [];

        foreach (['redis', 'rabbitmq', 'kafka'] as $brokerName) {
            try {
                $broker = $this->realtime->broker($brokerName);

                if ($broker === null) {
                    $results[$brokerName] = [
                        'status' => 'not_configured',
                        'message' => 'Broker not configured'
                    ];
                    continue;
                }

                if (method_exists($broker, 'healthCheck')) {
                    $health = $broker->healthCheck();
                    $results[$brokerName] = [
                        'status' => $health->status,
                        'message' => $health->message,
                        'latency_ms' => $health->latencyMs,
                        'details' => $health->details
                    ];
                } else {
                    $results[$brokerName] = [
                        'status' => $broker->isConnected() ? 'healthy' : 'unhealthy',
                        'connected' => $broker->isConnected()
                    ];
                }
            } catch (\Throwable $e) {
                $results[$brokerName] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'brokers' => $results
        ]);
    }
}
