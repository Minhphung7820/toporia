<?php

declare(strict_types=1);

namespace Toporia\Framework\Storage\Concerns;

/**
 * Trait ManagesVisibility
 *
 * Common visibility management for storage drivers.
 */
trait ManagesVisibility
{
    /**
     * Default visibility for new files.
     */
    protected string $defaultVisibility = 'private';

    /**
     * Visibility mappings for local filesystem.
     *
     * @var array<string, array{file: int, dir: int}>
     */
    protected array $visibilityMap = [
        'public' => [
            'file' => 0644,
            'dir' => 0755,
        ],
        'private' => [
            'file' => 0600,
            'dir' => 0700,
        ],
    ];

    /**
     * Set default visibility.
     *
     * @param string $visibility 'public' or 'private'
     * @return $this
     */
    public function setDefaultVisibility(string $visibility): self
    {
        $this->defaultVisibility = $visibility;
        return $this;
    }

    /**
     * Get default visibility.
     *
     * @return string
     */
    public function getDefaultVisibility(): string
    {
        return $this->defaultVisibility;
    }

    /**
     * Get file permissions for visibility.
     *
     * @param string $visibility 'public' or 'private'
     * @return int
     */
    protected function getFilePermissions(string $visibility): int
    {
        return $this->visibilityMap[$visibility]['file'] ?? 0644;
    }

    /**
     * Get directory permissions for visibility.
     *
     * @param string $visibility 'public' or 'private'
     * @return int
     */
    protected function getDirPermissions(string $visibility): int
    {
        return $this->visibilityMap[$visibility]['dir'] ?? 0755;
    }

    /**
     * Normalize visibility value.
     *
     * @param string $visibility Visibility value.
     * @return string
     */
    protected function normalizeVisibility(string $visibility): string
    {
        return in_array($visibility, ['public', 'private'], true)
            ? $visibility
            : $this->defaultVisibility;
    }
}
