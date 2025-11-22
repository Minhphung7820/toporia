<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;


/**
 * Abstract Class AbstractAvroKafkaConsumer
 *
 * Abstract base class for AbstractAvroKafkaConsumer implementations in the
 * Base layer providing common functionality and contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Base
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class AbstractAvroKafkaConsumer extends AbstractKafkaConsumer
{
    /**
     * Get Avro schema name.
     *
     * @return string Schema name (e.g., 'com.example.UserEvent')
     */
    abstract protected function getSchemaName(): string;

    /**
     * Get Schema Registry base URI.
     *
     * @return string
     */
    protected function getSchemaRegistryUri(): string
    {
        return config('kafka.schema_registry.uri', env('KAFKA_SCHEMA_REGISTRY_URI', 'http://localhost:8000'));
    }

    /**
     * Check if Avro support is available.
     *
     * @return bool
     */
    protected function isAvroSupported(): bool
    {
        // Check if Avro libraries are available
        // For now, return false as Avro support is optional
        // Can be enabled when Avro libraries are installed
        return class_exists('FlixTech\AvroSerializer\Objects\RecordSerializer') ||
            class_exists('AvroStringIO');
    }

    /**
     * Create Avro deserializer.
     *
     * This method can be overridden to use different Avro libraries.
     * By default, returns null (Avro support is optional).
     *
     * @return object|null Avro deserializer instance
     * @throws \RuntimeException If Avro support is not available
     */
    protected function createAvroDeserializer(): ?object
    {
        if (!$this->isAvroSupported()) {
            throw new \RuntimeException(
                'Avro support is not available. ' .
                    'Install Avro libraries: composer require flix-tech/avro-serializer-php ' .
                    'or enable Avro support in your configuration.'
            );
        }

        // Placeholder for Avro deserializer creation
        // Can be implemented when Avro libraries are added
        // Example implementation:
        /*
        $cachedRegistry = new CachedRegistry(
            new BlockingRegistry(
                new PromisingRegistry(
                    new Client(['base_uri' => $this->getSchemaRegistryUri()])
                )
            ),
            new AvroObjectCacheAdapter()
        );

        $registry = new AvroSchemaRegistry($cachedRegistry);
        $recordSerializer = new RecordSerializer($cachedRegistry);

        $registry->addBodySchemaMappingForTopic(
            $this->getTopic(),
            new KafkaAvroSchema($this->getSchemaName())
        );

        return new AvroDeserializer($registry, $recordSerializer);
        */

        return null;
    }

    /**
     * Deserialize Avro message.
     *
     * @param string $payload Raw message payload
     * @return array Deserialized message data
     * @throws \RuntimeException If deserialization fails
     */
    protected function deserializeAvroMessage(string $payload): array
    {
        if (!$this->isAvroSupported()) {
            // Fallback to JSON if Avro not available
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        $deserializer = $this->createAvroDeserializer();
        if ($deserializer === null) {
            // Fallback to JSON
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        // Placeholder for actual Avro deserialization
        // This would call the deserializer's deserialize method
        throw new \RuntimeException('Avro deserialization not yet implemented. Use JSON consumer for now.');
    }
}
