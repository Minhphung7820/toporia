<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Kafka;

use App\Domain\Services\HealthCheckerInterface;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Kafka Health Checker
 *
 * Checks Kafka connection, API version, and broker health.
 *
 * Clean Architecture:
 * - Infrastructure layer implementation
 * - Implements domain interface (Dependency Inversion)
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles health checking
 * - Dependency Inversion: Depends on RealtimeManager abstraction
 * - Interface Segregation: Implements HealthCheckerInterface
 *
 * @package App\Infrastructure\Services\Kafka
 */
final class KafkaHealthChecker implements HealthCheckerInterface
{
    public function __construct(
        private readonly RealtimeManager $realtimeManager
    ) {}

    /**
     * {@inheritdoc}
     */
    public function checkConnection(): bool
    {
        try {
            $broker = $this->realtimeManager->broker('kafka');
            if (!$broker) {
                return false;
            }

            // Check connection by attempting to list topics
            $container = config('kafka.kafka_container', 'project_topo_kafka');
            $bootstrapServer = config('kafka.bootstrap_server', 'localhost:29092');

            $cmd = sprintf(
                'docker exec %s /usr/bin/kafka-broker-api-versions --bootstrap-server %s >/dev/null 2>&1',
                escapeshellarg($container),
                escapeshellarg($bootstrapServer)
            );

            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            return $exitCode === 0;
        } catch (\Throwable $e) {
            Log::error("Kafka connection check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkApiVersion(): bool
    {
        try {
            $container = config('kafka.kafka_container', 'project_topo_kafka');
            $bootstrapServer = config('kafka.bootstrap_server', 'localhost:29092');

            $cmd = sprintf(
                'docker exec %s /usr/bin/kafka-broker-api-versions --bootstrap-server %s 2>&1',
                escapeshellarg($container),
                escapeshellarg($bootstrapServer)
            );

            $output = shell_exec($cmd);

            // Check if we got valid API version response
            return str_contains($output ?? '', 'Produce')
                && str_contains($output ?? '', 'Fetch')
                && !str_contains($output ?? '', 'Error')
                && !str_contains($output ?? '', 'could not be established');
        } catch (\Throwable $e) {
            Log::error("Kafka API version check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHealthStatus(): array
    {
        return [
            'connection' => $this->checkConnection(),
            'api_version' => $this->checkApiVersion(),
            'security_protocol' => $this->checkSecurityProtocol(),
        ];
    }

    /**
     * Check security protocol configuration.
     *
     * @return bool True if security protocol is correctly configured
     */
    private function checkSecurityProtocol(): bool
    {
        $brokerConfig = config('realtime.brokers.kafka', []);
        $consumerConfig = $brokerConfig['consumer_config'] ?? [];
        $producerConfig = $brokerConfig['producer_config'] ?? [];

        $consumerProtocol = $consumerConfig['security.protocol'] ?? 'plaintext';
        $producerProtocol = $producerConfig['security.protocol'] ?? 'plaintext';

        // Both should match
        if ($consumerProtocol && $producerProtocol) {
            return $consumerProtocol === $producerProtocol;
        }

        // Default to true if not configured (assumes plaintext for development)
        return true;
    }
}
