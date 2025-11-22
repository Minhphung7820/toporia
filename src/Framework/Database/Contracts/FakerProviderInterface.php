<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Contracts;

use Faker\Generator;

/**
 * Faker Provider Interface
 *
 * Defines contract for custom Faker providers.
 *
 * SOLID Principles:
 * - Open/Closed: Extend Faker functionality without modifying core
 * - Dependency Inversion: Depend on abstraction (Generator interface)
 *
 * @see https://fakerphp.github.io/
 */
interface FakerProviderInterface
{
    /**
     * Register custom formatters with the Faker generator.
     *
     * @param Generator $generator
     * @return void
     */
    public function register(Generator $generator): void;
}

