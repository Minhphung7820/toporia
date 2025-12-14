<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Realtime\Broadcast;
use Toporia\Framework\Realtime\Message;
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

            // Get broker instance for batch publish
            $broker = $this->realtimeManager->broker($driver);

            if ($broker === null) {
                return response()->json([
                    'success' => false,
                    'error' => "Broker [{$driver}] is not configured",
                    'available_brokers' => ['redis', 'rabbitmq', 'kafka']
                ], 400);
            }

            // TRUE batch mode - prepare all messages first, then batch publish
            $batchResults = [];
            $totalBatches = (int) ceil($count / $batchSize);
            $totalQueued = 0;
            $totalFailed = 0;

            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $batchStart = microtime(true);

                $startIndex = $batch * $batchSize;
                $endIndex = min($startIndex + $batchSize, $count);
                $currentBatchSize = $endIndex - $startIndex;

                // Prepare batch messages
                $messages = [];
                for ($i = $startIndex; $i < $endIndex; $i++) {
                    $messageData = array_merge($data, [
                        'request_id' => $i,
                        'batch_id' => $batch,
                        'timestamp' => microtime(true),
                        'user_id' => rand(1, 100000),
                        'session_id' => bin2hex(random_bytes(4)),
                    ]);

                    $messages[] = [
                        'channel' => $channel,
                        'message' => Message::event($channel, $event, $messageData),
                    ];
                }

                // TRUE Kafka batching - all messages queued, single flush
                $result = $broker->publishBatch($messages);

                $batchDuration = microtime(true) - $batchStart;
                $totalQueued += $result['queued'];
                $totalFailed += $result['failed'];

                $batchResults[] = [
                    'batch' => $batch + 1,
                    'size' => $currentBatchSize,
                    'queued' => $result['queued'],
                    'failed' => $result['failed'],
                    'queue_time_ms' => $result['queue_time_ms'],
                    'flush_time_ms' => $result['flush_time_ms'],
                    'total_time_ms' => round($batchDuration * 1000, 2),
                    'throughput' => $result['throughput'],
                ];
            }

            $duration = microtime(true) - $startTime;
            $throughput = $duration > 0 ? $totalQueued / $duration : 0;

            return response()->json([
                'success' => $totalFailed === 0,
                'message' => "TRUE batch publish: {$totalQueued}/{$count} messages in {$totalBatches} batches",
                'mode' => 'true_kafka_batching',
                'results' => [
                    'total' => $count,
                    'queued' => $totalQueued,
                    'failed' => $totalFailed,
                    'duration_seconds' => round($duration, 3),
                    'throughput_per_second' => round($throughput, 0),
                    'avg_latency_ms' => $count > 0 ? round(($duration / $count) * 1000, 4) : 0,
                ],
                'batches' => [
                    'count' => $totalBatches,
                    'size' => $batchSize,
                    'details' => $batchResults,
                ],
                'config' => [
                    'channel' => $channel,
                    'event' => $event,
                    'driver' => $driver,
                ],
                'explanation' => [
                    'how_it_works' => 'Messages queued to Kafka buffer → Grouped by topic/partition → Compressed (lz4) → Single network request',
                    'performance' => '50K-200K msg/s (vs 1K-5K with individual publish)',
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
