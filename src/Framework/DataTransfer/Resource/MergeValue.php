<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Resource;

/**
 * Class MergeValue
 *
 * Represents values that should be merged into the resource array.
 * Used by merge() and mergeWhen() methods.
 *
 * @package Toporia\Framework\DataTransfer\Resource
 */
class MergeValue
{
    /**
     * The data to merge.
     *
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * Create new merge value.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Check if value should be merged.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isMergeable(mixed $value): bool
    {
        return $value instanceof self;
    }
}
