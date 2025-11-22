<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Factory\Concerns;

use Toporia\Framework\Database\Factory;
use Closure;

/**
 * Has Sequences Trait
 *
 * Provides sequence support for generating sequential or unique values.
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles sequence management
 * - Open/Closed: Extend sequence behavior without modifying factory
 *
 * Usage:
 * ```php
 * UserFactory::new()
 *     ->sequence(
 *         ['role' => 'admin'],
 *         ['role' => 'user'],
 *         ['role' => 'moderator']
 *     )
 *     ->createMany(3);
 * ```
 *
 * @mixin Factory
 */
trait HasSequences
{
    /**
     * Sequence index counter.
     *
     * @var int
     */
    protected int $sequenceIndex = 0;

    /**
     * Sequence definitions.
     *
     * @var array<int, array<string, mixed>|Closure>
     */
    protected array $sequences = [];

    /**
     * Define sequence of attribute variations.
     *
     * @param array<int, array<string, mixed>|Closure> ...$sequences
     * @return static
     */
    public function sequence(array|Closure ...$sequences): static
    {
        $this->sequences = $sequences;
        $this->sequenceIndex = 0;

        return $this;
    }

    /**
     * Get next sequence attributes.
     *
     * @return array<string, mixed>
     */
    protected function getNextSequence(): array
    {
        if (empty($this->sequences)) {
            return [];
        }

        $index = $this->sequenceIndex % count($this->sequences);
        $sequence = $this->sequences[$index];
        $this->sequenceIndex++;

        if ($sequence instanceof Closure) {
            return $sequence($this->sequenceIndex - 1);
        }

        return $sequence;
    }

    /**
     * Reset sequence index.
     *
     * @return static
     */
    public function resetSequence(): static
    {
        $this->sequenceIndex = 0;
        return $this;
    }
}

