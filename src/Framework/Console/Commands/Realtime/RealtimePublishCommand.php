<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Realtime;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Exceptions\BrokerException;

/**
 * Publish a test message to a broadcast channel.
 *
 * Supports Redis, RabbitMQ, and Kafka brokers.
 */
/**
 * Class RealtimePublishCommand
 *
 * Publish a message to a realtime channel.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console\Commands\Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RealtimePublishCommand extends Command
{
    protected string $signature = 'realtime:publish {channel : The channel to publish to} {message : The message to publish (JSON or plain text)} {--driver= : The broadcast driver (redis, rabbitmq, kafka)} {--event=test : The event name}';

    protected string $description = 'Publish a test message to a broadcast channel';

    public function handle(): int
    {
        $channel = $this->argument('channel');
        $messageArg = $this->argument('message');
        $driver = $this->option('driver') ?: config('realtime.default_broker', 'redis');
        $event = $this->option('event') ?: 'test';

        $this->info("Publishing to channel [{$channel}] using [{$driver}] driver...");

        try {
            // Parse message as JSON if possible
            $data = json_decode($messageArg, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $data = ['message' => $messageArg];
            }

            // Get RealtimeManager from container or create directly
            $realtimeConfig = config('realtime', []);
            $realtimeConfig['default_broker'] = $driver;

            $manager = app()->has(RealtimeManager::class)
                ? app()->make(RealtimeManager::class)
                : new RealtimeManager($realtimeConfig);

            // Get the broker and publish
            $broker = $manager->broker($driver);

            if ($broker === null) {
                $this->error("Broker [{$driver}] is not configured or available.");
                $this->info("Available brokers: redis, rabbitmq, kafka");
                return 1;
            }

            // Create message and publish
            $message = Message::event($channel, $event, $data);
            $broker->publish($channel, $message);

            $this->success("Message published successfully!");
            $this->newLine();
            $this->info("Details:");
            $this->info("  Channel: {$channel}");
            $this->info("  Event: {$event}");
            $this->info("  Driver: {$driver}");
            $this->info("  Data: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (BrokerException $e) {
            $this->error("Broker error: {$e->getMessage()}");
            $context = $e->getContext();
            if (!empty($context)) {
                $this->info("Context: " . json_encode($context, JSON_PRETTY_PRINT));
            }
            return 1;
        } catch (\Throwable $e) {
            $this->error("Failed to publish: {$e->getMessage()}");
            if ($this->isVerbose()) {
                $this->info("Stack trace:");
                $this->line($e->getTraceAsString());
            }
            return 1;
        }

        return 0;
    }

    /**
     * Check if verbose output is enabled.
     */
    private function isVerbose(): bool
    {
        return $this->option('verbose') || in_array('-v', $_SERVER['argv'] ?? []);
    }
}
