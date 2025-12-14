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
 *   // Batch publishing (high throughput - 50K-200K msg/s)
 *   $result = Broadcast::batch('kafka')
 *       ->channel('events.stream')
 *       ->event('user.action')
 *       ->messages([['user_id' => 1], ['user_id' => 2]])
 *       ->publish();
 *
 *   // Helper function
 *   broadcast('channel', 'event', $data, 'kafka');
 *   broadcastBatch('kafka')->channel('ch')->event('ev')->messages($data)->publish();
 */
final class BrokerTestController
{
    public function __construct(
        private readonly RealtimeManager $realtimeManager
    ) {}

    /**
     * Publish message(s) to broker.
     *
     * POST /api/broker/publish
     *
     * Single message:
     * {
     *   "channel": "test.channel",
     *   "event": "test.event",
     *   "data": {"message": "Hello World"},
     *   "driver": "kafka"
     * }
     *
     * Load test with TRUE Kafka batching:
     * {
     *   "channel": "events.stream",
     *   "event": "user.action",
     *   "data": {"action": "login"},
     *   "driver": "kafka",
     *   "count": 10000,
     *   "batch_size": 5000
     * }
     *
     * How TRUE batching works:
     * 1. All messages are queued to Kafka producer buffer (no flush)
     * 2. Kafka groups messages by topic/partition
     * 3. Kafka compresses entire batch (lz4)
     * 4. Single flush sends all batched messages
     *
     * Performance: 50K-200K msg/s (vs 1K-5K with individual publish)
     */
    public function publish(Request $request)
    {
        $startTime = microtime(true);

        $channel = $request->input('channel', 'test.channel');
        $event = $request->input('event', 'test.event');
        $data = $request->input('data', ['message' => 'Hello from BrokerTestController']);
        $driver = $request->input('driver', 'redis');
        $count = min(max((int) $request->input('count', 1), 1), 100000);
        $batchSize = min(max((int) $request->input('batch_size', 1000), 100), 10000);

        try {
            // Single message mode
            if ($count === 1) {
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
            }

            // Fluent Batch API - clean DX with TRUE Kafka batching
            $result = Broadcast::batch($driver)
                ->channel($channel)
                ->event($event)
                ->batchSize($batchSize)
                ->each(range(0, $count - 1), fn($i) => array_merge($data, [
                    'request_id' => $i,
                    'batch_id' => (int) floor($i / $batchSize),
                    'timestamp' => microtime(true),
                    'user_id' => rand(1, 100000),
                    'session_id' => bin2hex(random_bytes(4)),
                ]))
                ->publish();

            $duration = microtime(true) - $startTime;

            return response()->json([
                'success' => $result->successful(),
                'message' => "TRUE batch publish: {$result->queued}/{$result->total} messages",
                'mode' => 'fluent_batch_api',
                'results' => $result->toArray(),
                'config' => [
                    'channel' => $channel,
                    'event' => $event,
                    'driver' => $driver,
                    'batch_size' => $batchSize,
                ],
                'explanation' => [
                    'how_it_works' => 'Messages queued to Kafka buffer → Grouped by topic/partition → Compressed (lz4) → Single network request',
                    'performance' => '50K-200K msg/s (vs 1K-5K with individual publish)',
                    'api' => 'Broadcast::batch($driver)->channel()->event()->messages()->publish()',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'driver' => $driver
            ], 500);
        }
    }
}
