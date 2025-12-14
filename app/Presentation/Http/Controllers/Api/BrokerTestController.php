<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Realtime\Broadcast;

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
     * Load test (multiple messages with batching):
     * {
     *   "channel": "events.stream",
     *   "event": "user.action",
     *   "data": {"action": "login"},
     *   "driver": "kafka",
     *   "count": 100000,
     *   "batch_size": 1000
     * }
     */
    public function publish(Request $request)
    {
        $startTime = microtime(true);

        $channel = $request->input('channel', 'test.channel');
        $event = $request->input('event', 'test.event');
        $data = $request->input('data', ['message' => 'Hello from BrokerTestController']);
        $driver = $request->input('driver', 'redis');
        $count = min(max((int) $request->input('count', 1), 1), 100000);
        $batchSize = min(max((int) $request->input('batch_size', 1000), 100), 5000);

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

            // Load test mode with batching
            $successful = 0;
            $failed = 0;
            $errors = [];
            $batchResults = [];
            $totalBatches = (int) ceil($count / $batchSize);

            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $batchStart = microtime(true);
                $batchSuccessful = 0;
                $batchFailed = 0;

                $startIndex = $batch * $batchSize;
                $endIndex = min($startIndex + $batchSize, $count);

                for ($i = $startIndex; $i < $endIndex; $i++) {
                    $messageData = array_merge($data, [
                        'request_id' => $i,
                        'batch_id' => $batch,
                        'timestamp' => microtime(true),
                        'user_id' => rand(1, 100000),
                        'session_id' => bin2hex(random_bytes(4)),
                    ]);

                    try {
                        Broadcast::via($driver)
                            ->toChannel($channel)
                            ->event($event)
                            ->with($messageData)
                            ->now();
                        $batchSuccessful++;
                        $successful++;
                    } catch (\Throwable $e) {
                        $batchFailed++;
                        $failed++;
                        if (count($errors) < 5) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }

                $batchDuration = microtime(true) - $batchStart;
                $batchResults[] = [
                    'batch' => $batch + 1,
                    'sent' => $batchSuccessful,
                    'failed' => $batchFailed,
                    'duration_ms' => round($batchDuration * 1000, 2),
                    'throughput' => $batchDuration > 0 ? round($batchSuccessful / $batchDuration, 0) : 0,
                ];

                // Small pause between batches to prevent overwhelming
                if ($batch < $totalBatches - 1) {
                    usleep(10000); // 10ms pause
                }
            }

            $duration = microtime(true) - $startTime;
            $throughput = $duration > 0 ? $successful / $duration : 0;

            return response()->json([
                'success' => $failed === 0,
                'message' => "Load test: {$successful}/{$count} messages sent in {$totalBatches} batches",
                'results' => [
                    'total' => $count,
                    'successful' => $successful,
                    'failed' => $failed,
                    'duration_seconds' => round($duration, 3),
                    'throughput_per_second' => round($throughput, 0),
                    'avg_latency_ms' => round(($duration / $count) * 1000, 4),
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
                'errors' => $errors,
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
