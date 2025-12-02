<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Resource;

/**
 * Class MissingValue
 *
 * Represents a missing/undefined value in resources.
 * Used by conditional attributes (when, whenLoaded) to indicate
 * that a value should be omitted from the response.
 *
 * @package Toporia\Framework\DataTransfer\Resource
 */
class MissingValue
{
    /**
     * Check if value is missing.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isMissing(mixed $value): bool
    {
        return $value instanceof self;
    }
}
