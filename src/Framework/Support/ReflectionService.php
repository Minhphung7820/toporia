<?php

declare(strict_types=1);

namespace Toporia\Framework\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

/**
 * Reflection Service
 *
 * Centralized service for all reflection operations in the framework.
 * Only the container should use reflection directly - all other components
 * should use this service through dependency injection.
 *
 * Performance:
 * - Caches reflection objects to avoid repeated instantiation
 * - Lazy loading of reflection data
 * - Optimized property/method access patterns
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles reflection operations
 * - Dependency Inversion: Other classes depend on this abstraction
 * - Open/Closed: Extensible for new reflection needs
 *
 * SOLID Principles:
 * - S: Single responsibility for reflection operations
 * - O: Open for extension (new reflection methods)
 * - L: Substitutable reflection implementation
 * - I: Interface segregation (focused methods)
 * - D: Dependency inversion (injected, not instantiated)
 *
 * High Reusability:
 * - Used across ORM, factories, seeders, container
 * - Consistent reflection patterns
 * - Cached results for performance
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Support
 * @since       2025-01-22
 */
final class ReflectionService
{
    /**
     * Cache for ReflectionClass instances
     *
     * @var array<string, ReflectionClass>
     */
    private array $classCache = [];

    /**
     * Cache for ReflectionMethod instances
     *
     * @var array<string, ReflectionMethod>
     */
    private array $methodCache = [];

    /**
     * Cache for ReflectionProperty instances
     *
     * @var array<string, ReflectionProperty>
     */
    private array $propertyCache = [];

    /**
     * Get ReflectionClass for a class name or object.
     *
     * Performance: O(1) with caching, O(n) first time
     *
     * @param string|object $class Class name or object instance
     * @return ReflectionClass
     * @throws ReflectionException
     */
    public function getClass(string|object $class): ReflectionClass
    {
        $className = is_object($class) ? get_class($class) : $class;

        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = new ReflectionClass($className);
        }

        return $this->classCache[$className];
    }

    /**
     * Get ReflectionMethod for a class method.
     *
     * Performance: O(1) with caching
     *
     * @param string|object $class Class name or object instance
     * @param string $method Method name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    public function getMethod(string|object $class, string $method): ReflectionMethod
    {
        $className = is_object($class) ? get_class($class) : $class;
        $cacheKey = "{$className}::{$method}";

        if (!isset($this->methodCache[$cacheKey])) {
            $this->methodCache[$cacheKey] = new ReflectionMethod($className, $method);
        }

        return $this->methodCache[$cacheKey];
    }

    /**
     * Get ReflectionProperty for a class property.
     *
     * Performance: O(1) with caching
     *
     * @param string|object $class Class name or object instance
     * @param string $property Property name
     * @return ReflectionProperty
     * @throws ReflectionException
     */
    public function getProperty(string|object $class, string $property): ReflectionProperty
    {
        $className = is_object($class) ? get_class($class) : $class;
        $cacheKey = "{$className}::{$property}";

        if (!isset($this->propertyCache[$cacheKey])) {
            $this->propertyCache[$cacheKey] = new ReflectionProperty($className, $property);
        }

        return $this->propertyCache[$cacheKey];
    }

    /**
     * Get the short name of a class (without namespace).
     *
     * Performance: O(1) - uses cached ReflectionClass
     *
     * @param string|object $class Class name or object instance
     * @return string
     * @throws ReflectionException
     */
    public function getShortName(string|object $class): string
    {
        return $this->getClass($class)->getShortName();
    }

    /**
     * Check if a class has a specific property.
     *
     * Performance: O(1) - uses cached ReflectionClass
     *
     * @param string|object $class Class name or object instance
     * @param string $property Property name
     * @return bool
     */
    public function hasProperty(string|object $class, string $property): bool
    {
        try {
            return $this->getClass($class)->hasProperty($property);
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Check if a class has a specific method.
     *
     * Performance: O(1) - uses cached ReflectionClass
     *
     * @param string|object $class Class name or object instance
     * @param string $method Method name
     * @return bool
     */
    public function hasMethod(string|object $class, string $method): bool
    {
        try {
            return $this->getClass($class)->hasMethod($method);
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Get the value of a property from an object.
     *
     * Automatically handles accessibility (makes private/protected accessible).
     *
     * Performance: O(1) with caching
     *
     * @param object $object Object instance
     * @param string $property Property name
     * @return mixed Property value
     * @throws ReflectionException
     */
    public function getPropertyValue(object $object, string $property): mixed
    {
        $reflectionProperty = $this->getProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Set the value of a property on an object.
     *
     * Automatically handles accessibility (makes private/protected accessible).
     *
     * Performance: O(1) with caching
     *
     * @param object $object Object instance
     * @param string $property Property name
     * @param mixed $value New property value
     * @return void
     * @throws ReflectionException
     */
    public function setPropertyValue(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = $this->getProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * Invoke a method on an object with arguments.
     *
     * Automatically handles accessibility (makes private/protected accessible).
     *
     * Performance: O(1) with caching
     *
     * @param object $object Object instance
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed Method return value
     * @throws ReflectionException
     */
    public function invokeMethod(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = $this->getMethod($object, $method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $args);
    }

    /**
     * Create a new instance of a class using its constructor.
     *
     * Performance: O(1) with caching for reflection, O(n) for instantiation
     *
     * @param string $className Class name
     * @param array $args Constructor arguments
     * @return object New instance
     * @throws ReflectionException
     */
    public function newInstance(string $className, array $args = []): object
    {
        $reflectionClass = $this->getClass($className);

        if (empty($args)) {
            return $reflectionClass->newInstance();
        }

        return $reflectionClass->newInstanceArgs($args);
    }

    /**
     * Get all properties of a class.
     *
     * Performance: O(1) - uses cached ReflectionClass
     *
     * @param string|object $class Class name or object instance
     * @param int $filter Property filter (ReflectionProperty::IS_PUBLIC, etc.)
     * @return array<ReflectionProperty>
     */
    public function getProperties(string|object $class, int $filter = null): array
    {
        $reflectionClass = $this->getClass($class);
        return $filter !== null ? $reflectionClass->getProperties($filter) : $reflectionClass->getProperties();
    }

    /**
     * Get all methods of a class.
     *
     * Performance: O(1) - uses cached ReflectionClass
     *
     * @param string|object $class Class name or object instance
     * @param int $filter Method filter (ReflectionMethod::IS_PUBLIC, etc.)
     * @return array<ReflectionMethod>
     */
    public function getMethods(string|object $class, int $filter = null): array
    {
        $reflectionClass = $this->getClass($class);
        return $filter !== null ? $reflectionClass->getMethods($filter) : $reflectionClass->getMethods();
    }

    /**
     * Clear all reflection caches.
     *
     * Useful for testing or memory management in long-running processes.
     *
     * Performance: O(1)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->classCache = [];
        $this->methodCache = [];
        $this->propertyCache = [];
    }

    /**
     * Get cache statistics for debugging/monitoring.
     *
     * Performance: O(1)
     *
     * @return array{classes: int, methods: int, properties: int}
     */
    public function getCacheStats(): array
    {
        return [
            'classes' => count($this->classCache),
            'methods' => count($this->methodCache),
            'properties' => count($this->propertyCache),
        ];
    }
}






