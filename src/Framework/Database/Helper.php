<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\FactoryInterface;
use Toporia\Framework\Database\ORM\Model;

/**
 * Database Helper Functions
 *
 * Provides convenient helper functions for database operations.
 *
 * Usage:
 * ```php
 * factory(UserFactory::class)->create();
 * factory(UserFactory::class, 10)->create();
 * factory(UserFactory::class)->state('admin')->create();
 * ```
 */
class Helper
{
    /**
     * Create a factory instance.
     *
     * @param class-string<FactoryInterface> $factoryClass
     * @return FactoryInterface
     */
    public static function factory(string $factoryClass): FactoryInterface
    {
        if (!is_subclass_of($factoryClass, FactoryInterface::class)) {
            throw new \InvalidArgumentException(
                "Factory class [{$factoryClass}] must implement " . FactoryInterface::class
            );
        }

        return $factoryClass::new();
    }
}

/**
 * Global helper function: factory()
 *
 * Creates a factory instance for convenient usage.
 *
 * @param class-string<FactoryInterface> $factoryClass
 * @return FactoryInterface
 */
function factory(string $factoryClass): FactoryInterface
{
    return Helper::factory($factoryClass);
}

