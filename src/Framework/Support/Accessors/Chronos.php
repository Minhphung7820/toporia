<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\DateTime\Chronos as ChronosImpl;

/**
 * Chronos Service Accessor
 *
 * Provides static-like access to the Chronos date/time library.
 * Handles both static factory methods and instance methods.
 *
 * @method static ChronosImpl now(\DateTimeZone|string|null $timezone = null) Create instance from now
 * @method static ChronosImpl parse(string $time, \DateTimeZone|string|null $timezone = null) Parse date string
 * @method static ChronosImpl create(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minute = null, ?int $second = null, \DateTimeZone|string|null $timezone = null) Create from components
 * @method static ChronosImpl createFromTimestamp(int $timestamp, \DateTimeZone|string|null $timezone = null) Create from timestamp
 * @method static ChronosImpl createFromFormat(string $format, string $time, \DateTimeZone|string|null $timezone = null) Create from format
 *
 * @see ChronosImpl
 *
 * @example
 * // Static factory methods
 * Chronos::now()
 * Chronos::parse('2025-01-01')
 * Chronos::create(2025, 1, 1)
 *
 * // Instance methods (via container)
 * Chronos::getDefaultTimezone()
 */
final class Chronos extends ServiceAccessor
{
    /**
     * Get the service name for this accessor.
     *
     * @return string Service name in container
     */
    protected static function getServiceName(): string
    {
        return ChronosImpl::class;
    }

    /**
     * Forward static calls to Chronos.
     *
     * This override is REQUIRED because ChronosImpl has static factory methods
     * (now, parse, create, etc.) that must be called directly on the class,
     * not on an instance.
     *
     * ServiceAccessor's default __callStatic() would try to call static methods
     * on instance, which would fail.
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        // Known static factory methods - call directly on class
        // These methods create new instances and must be static
        $staticMethods = ['now', 'parse', 'create', 'createFromTimestamp', 'createFromFormat'];

        if (in_array($method, $staticMethods, true)) {
            return ChronosImpl::$method(...$args);
        }

        // For any other methods, try calling on class first (in case of other static methods)
        // If method doesn't exist on class, it will throw an error
        if (method_exists(ChronosImpl::class, $method)) {
            return ChronosImpl::$method(...$args);
        }

        // If not found, throw clear error
        throw new \BadMethodCallException(
            "Method '{$method}' does not exist on " . ChronosImpl::class . ". " .
                "Static factory methods: now, parse, create, createFromTimestamp, createFromFormat. " .
                "Instance methods should be called on the returned instance, e.g., Chronos::now()->addDays(1)."
        );
    }
}
