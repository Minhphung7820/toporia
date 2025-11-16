<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Services;

/**
 * Topic Service Interface
 *
 * Contract for managing message broker topics (Kafka, RabbitMQ, etc.)
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on topic management only
 * - Dependency Inversion: Domain contract for infrastructure services
 * - Open/Closed: Extensible for different brokers
 *
 * @package App\Domain\Services
 */
interface TopicServiceInterface
{
    /**
     * Ensure service is healthy and ready.
     *
     * @return bool True if service is healthy
     */
    public function ensureHealthy(): bool;

    /**
     * Create a topic.
     *
     * @param string $topicName Topic name
     * @param int $partitions Number of partitions
     * @param int $replicationFactor Replication factor
     * @param bool $ifNotExists Create only if not exists
     * @return bool True if created or already exists
     */
    public function createTopic(
        string $topicName,
        int $partitions = 1,
        int $replicationFactor = 1,
        bool $ifNotExists = true
    ): bool;

    /**
     * Create multiple topics from configuration.
     *
     * @param array<string, array{topic: string, partitions: int}> $topicConfigs Topic configurations
     * @return array<string, bool> Results: topic => success
     */
    public function createTopicsFromConfig(array $topicConfigs): array;

    /**
     * List all topics.
     *
     * @return array<string> Topic names
     */
    public function listTopics(): array;

    /**
     * Get topic details.
     *
     * @param string $topicName Topic name
     * @return array<string, mixed>|null Topic details or null if not found
     */
    public function describeTopic(string $topicName): ?array;
}
