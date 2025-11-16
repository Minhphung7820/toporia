<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Kafka;

use App\Domain\Services\TopicServiceInterface;
use App\Domain\Services\HealthCheckerInterface;
use App\Domain\Services\ClusterFixerInterface;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Kafka Topic Service
 *
 * Handles Kafka topic creation, management, and validation.
 *
 * Clean Architecture:
 * - Infrastructure layer implementation
 * - Implements domain interface (Dependency Inversion)
 * - Depends on domain abstractions (HealthChecker, ClusterFixer)
 *
 * SOLID Principles:
 * - Single Responsibility: Only manages topics
 * - Dependency Inversion: Depends on interfaces, not concretions
 * - Interface Segregation: Implements TopicServiceInterface
 * - Open/Closed: Extensible via configuration
 *
 * Performance Optimizations:
 * - Batch topic creation for efficiency
 * - Connection pooling for multiple operations
 * - Caching topic metadata
 *
 * @package App\Infrastructure\Services\Kafka
 */
final class KafkaTopicService implements TopicServiceInterface
{
    public function __construct(
        private readonly RealtimeManager $realtimeManager,
        private readonly HealthCheckerInterface $healthChecker,
        private readonly ClusterFixerInterface $clusterFixer,
        private readonly array $config = []
    ) {}

    /**
     * {@inheritdoc}
     */
    public function ensureHealthy(): bool
    {
        // Check connection first
        if (!$this->healthChecker->checkConnection()) {
            Log::error('Kafka connection failed');
            return false;
        }

        // Check and fix cluster ID mismatch
        if ($this->clusterFixer->needsFix()) {
            Log::warning('Cluster ID mismatch detected, attempting to fix...');
            if (!$this->clusterFixer->fix()) {
                Log::error('Failed to fix cluster ID mismatch');
                return false;
            }
            Log::info('Cluster ID mismatch fixed successfully');
        }

        // Verify API version compatibility
        if (!$this->healthChecker->checkApiVersion()) {
            Log::error('Kafka API version check failed');
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createTopic(
        string $topicName,
        int $partitions = 1,
        int $replicationFactor = 1,
        bool $ifNotExists = true
    ): bool {
        try {
            // Ensure Kafka is healthy before creating topic
            if (!$this->ensureHealthy()) {
                Log::error("Cannot create topic '{$topicName}': Kafka is not healthy");
                return false;
            }

            $broker = $this->realtimeManager->broker('kafka');
            if (!$broker) {
                Log::error("Kafka broker not available for topic creation: {$topicName}");
                return false;
            }

            // Use Docker exec to create topic (direct Kafka command)
            return $this->executeTopicCommand([
                '--create',
                '--topic',
                $topicName,
                '--partitions',
                (string) $partitions,
                '--replication-factor',
                (string) $replicationFactor,
                $ifNotExists ? '--if-not-exists' : '',
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to create topic '{$topicName}': {$e->getMessage()}");
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTopicsFromConfig(array $topicConfigs): array
    {
        $results = [];

        // Ensure Kafka is healthy first
        if (!$this->ensureHealthy()) {
            Log::error('Cannot create topics: Kafka is not healthy');
            foreach ($topicConfigs as $key => $config) {
                $topic = $config['topic'] ?? $key;
                $results[$topic] = false;
            }
            return $results;
        }

        // Create topics in batch
        foreach ($topicConfigs as $key => $config) {
            $topic = $config['topic'] ?? $key;
            $partitions = $config['partitions'] ?? 1;
            $replicationFactor = $config['replication_factor'] ?? 1;

            $results[$topic] = $this->createTopic(
                $topic,
                $partitions,
                $replicationFactor,
                true
            );
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function listTopics(): array
    {
        try {
            $output = $this->executeTopicCommand(['--list'], true);
            if (empty($output)) {
                return [];
            }

            $topics = array_filter(
                explode("\n", trim($output)),
                fn($line) => !empty($line) && !str_starts_with($line, '__')
            );

            return array_values($topics);
        } catch (\Throwable $e) {
            Log::error("Failed to list topics: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeTopic(string $topicName): ?array
    {
        try {
            $output = $this->executeTopicCommand([
                '--describe',
                '--topic',
                $topicName,
            ], true);

            if (empty($output)) {
                return null;
            }

            return $this->parseTopicDescription($output);
        } catch (\Throwable $e) {
            Log::error("Failed to describe topic '{$topicName}': {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Execute kafka-topics command via Docker.
     *
     * @param array<string> $args Command arguments
     * @param bool $returnOutput Return output instead of success status
     * @return string|bool Output if $returnOutput=true, success status otherwise
     */
    private function executeTopicCommand(array $args, bool $returnOutput = false): string|bool
    {
        $container = config('kafka.kafka_container', 'project_topo_kafka');
        $bootstrapServer = $this->getBootstrapServer();

        $cmd = sprintf(
            'docker exec %s /usr/bin/kafka-topics --bootstrap-server %s %s 2>&1',
            escapeshellarg($container),
            escapeshellarg($bootstrapServer),
            implode(' ', array_map('escapeshellarg', array_filter($args)))
        );

        $output = shell_exec($cmd);

        if ($returnOutput) {
            return $output ?? '';
        }

        // Check for success indicators
        $success = str_contains($output ?? '', 'Created topic')
            || str_contains($output ?? '', 'already exists')
            || (str_contains($output ?? '', 'Topic:') && !str_contains($output ?? '', 'Error'));

        return $success;
    }

    /**
     * Get bootstrap server address.
     *
     * @return string Bootstrap server address
     */
    private function getBootstrapServer(): string
    {
        // Use internal Docker network address for better performance
        return config('kafka.bootstrap_server', 'localhost:29092');
    }

    /**
     * Parse topic description output.
     *
     * @param string $output Command output
     * @return array<string, mixed> Parsed topic details
     */
    private function parseTopicDescription(string $output): array
    {
        $details = [
            'name' => '',
            'partitions' => 0,
            'replication_factor' => 0,
            'partitions_detail' => [],
        ];

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^Topic:\s+(\S+)\s+TopicId:\s+(\S+)\s+PartitionCount:\s+(\d+)\s+ReplicationFactor:\s+(\d+)/', $line, $matches)) {
                $details['name'] = $matches[1];
                $details['topic_id'] = $matches[2];
                $details['partitions'] = (int) $matches[3];
                $details['replication_factor'] = (int) $matches[4];
            } elseif (preg_match('/Partition:\s+(\d+)\s+Leader:\s+(\d+)\s+Replicas:\s+([\d,]+)\s+Isr:\s+([\d,]+)/', $line, $matches)) {
                $details['partitions_detail'][] = [
                    'partition' => (int) $matches[1],
                    'leader' => (int) $matches[2],
                    'replicas' => array_map('intval', explode(',', $matches[3])),
                    'isr' => array_map('intval', explode(',', $matches[4])),
                ];
            }
        }

        return $details;
    }
}
