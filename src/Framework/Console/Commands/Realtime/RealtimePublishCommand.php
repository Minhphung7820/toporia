<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Realtime;

use Toporia\Framework\Console\Command;

final class RealtimePublishCommand extends Command
{
    protected string $signature = 'realtime:publish {channel : The channel to publish to} {message : The message to publish} {--driver= : The broadcast driver to use}';

    protected string $description = 'Publish a test message to a broadcast channel';

    public function handle(): int
    {
        $channel = $this->argument('channel');
        $message = $this->argument('message');
        $driver = $this->option('driver') ?: config('realtime.default', 'redis');

        $this->info("Publishing to channel [{$channel}] using [{$driver}] driver...");

        try {
            // Parse message as JSON if possible
            $data = json_decode($message, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $data = ['message' => $message];
            }

            // Publish based on driver
            switch ($driver) {
                case 'redis':
                    $this->publishToRedis($channel, $data);
                    break;
                case 'rabbitmq':
                    $this->publishToRabbitMQ($channel, $data);
                    break;
                case 'kafka':
                    $this->publishToKafka($channel, $data);
                    break;
                default:
                    $this->error("Unknown driver: {$driver}");
                    return 1;
            }

            $this->success("Message published successfully.");
            $this->info("Channel: {$channel}");
            $this->info("Data: " . json_encode($data, JSON_PRETTY_PRINT));

        } catch (\Throwable $e) {
            $this->error("Failed to publish: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function publishToRedis(string $channel, array $data): void
    {
        $redis = new \Redis();
        $host = config('database.redis.default.host', '127.0.0.1');
        $port = (int) config('database.redis.default.port', 6379);

        $redis->connect($host, $port);
        $redis->publish($channel, json_encode($data));
        $redis->close();
    }

    private function publishToRabbitMQ(string $channel, array $data): void
    {
        // Simplified RabbitMQ publishing
        $this->info("(RabbitMQ publishing would require php-amqplib)");
    }

    private function publishToKafka(string $channel, array $data): void
    {
        // Simplified Kafka publishing
        $this->info("(Kafka publishing would require ext-rdkafka or nmred/kafka-php)");
    }
}
