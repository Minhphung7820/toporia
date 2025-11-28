<?php

declare(strict_types=1);

namespace Toporia\Framework\Storage;

use Toporia\Framework\Storage\Contracts\FilesystemInterface;

/**
 * Storage Manager
 *
 * Multi-driver storage manager with fluent API.
 *
 * Supports:
 * - Local filesystem
 * - S3 (AWS/DigitalOcean/Minio)
 * - FTP (future)
 *
 * Performance: O(1) driver lookup with caching
 * SOLID: Single Responsibility - manages multiple storage drivers
 */
final class StorageManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    public function __construct(
        private readonly array $config,
        private readonly string $defaultDisk = 'local'
    ) {}

    /**
     * Get filesystem disk instance.
     *
     * @param string|null $name Disk name (uses default if null)
     * @return FilesystemInterface
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name = $name ?? $this->defaultDisk;

        // Return cached disk
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        // Create and cache new disk
        $this->disks[$name] = $this->createDisk($name);
        return $this->disks[$name];
    }

    /**
     * Create filesystem disk from config.
     *
     * @param string $name Disk name
     * @return FilesystemInterface
     * @throws \RuntimeException If disk config not found
     */
    private function createDisk(string $name): FilesystemInterface
    {
        if (!isset($this->config['disks'][$name])) {
            throw new \RuntimeException("Disk [{$name}] not configured.");
        }

        $config = $this->config['disks'][$name];
        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => $this->createLocalDisk($config),
            's3' => $this->createS3Disk($config),
            default => throw new \RuntimeException("Unsupported driver [{$driver}]"),
        };
    }

    /**
     * Create local filesystem disk.
     *
     * @param array $config Disk configuration
     * @return LocalFilesystem
     */
    private function createLocalDisk(array $config): LocalFilesystem
    {
        return new LocalFilesystem(
            root: $config['root'],
            baseUrl: $config['url'] ?? ''
        );
    }

    /**
     * Create S3 filesystem disk.
     *
     * @param array $config Disk configuration
     * @return S3Filesystem
     */
    private function createS3Disk(array $config): S3Filesystem
    {
        return new S3Filesystem(
            bucket: $config['bucket'],
            region: $config['region'] ?? 'us-east-1',
            key: $config['key'],
            secret: $config['secret'],
            baseUrl: $config['url'] ?? '',
            endpoint: $config['endpoint'] ?? ''
        );
    }

    /**
     * Proxy method calls to default disk.
     *
     * Allows: $storage->put() instead of $storage->disk()->put()
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }

    // =========================================================================
    // CONVENIENCE METHODS (for static access via Storage accessor)
    // =========================================================================

    /**
     * Store file contents (convenience method).
     *
     * @param string $path File path
     * @param mixed $contents File contents (string or resource)
     * @param array $options Options (visibility, etc.)
     * @return bool
     */
    public function put(string $path, mixed $contents, array $options = []): bool
    {
        return $this->disk()->put($path, $contents, $options);
    }

    /**
     * Get file contents (convenience method).
     *
     * @param string $path File path
     * @return string|null
     */
    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    /**
     * Get file as stream resource (convenience method).
     *
     * @param string $path File path
     * @return resource|null
     */
    public function readStream(string $path)
    {
        return $this->disk()->readStream($path);
    }

    /**
     * Check if file exists (convenience method).
     *
     * @param string $path File path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * Delete file(s) (convenience method).
     *
     * @param string|array $paths File path(s)
     * @return bool
     */
    public function delete(string|array $paths): bool
    {
        return $this->disk()->delete($paths);
    }

    /**
     * Copy file (convenience method).
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        return $this->disk()->copy($from, $to);
    }

    /**
     * Move file (convenience method).
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        return $this->disk()->move($from, $to);
    }

    /**
     * Get file size (convenience method).
     *
     * @param string $path File path
     * @return int|null
     */
    public function size(string $path): ?int
    {
        return $this->disk()->size($path);
    }

    /**
     * Get last modified timestamp (convenience method).
     *
     * @param string $path File path
     * @return int|null
     */
    public function lastModified(string $path): ?int
    {
        return $this->disk()->lastModified($path);
    }

    /**
     * Get MIME type (convenience method).
     *
     * @param string $path File path
     * @return string|null
     */
    public function mimeType(string $path): ?string
    {
        return $this->disk()->mimeType($path);
    }

    /**
     * List files in directory (convenience method).
     *
     * @param string $directory Directory path
     * @param bool $recursive Recursive listing
     * @return array
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        return $this->disk()->files($directory, $recursive);
    }

    /**
     * List subdirectories (convenience method).
     *
     * @param string $directory Directory path
     * @param bool $recursive Recursive listing
     * @return array
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        return $this->disk()->directories($directory, $recursive);
    }

    /**
     * Create directory (convenience method).
     *
     * @param string $path Directory path
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        return $this->disk()->makeDirectory($path);
    }

    /**
     * Delete directory (convenience method).
     *
     * @param string $directory Directory path
     * @return bool
     */
    public function deleteDirectory(string $directory): bool
    {
        return $this->disk()->deleteDirectory($directory);
    }

    /**
     * Get public URL (convenience method).
     *
     * @param string $path File path
     * @return string
     */
    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    /**
     * Get temporary URL (signed) (convenience method).
     *
     * @param string $path File path
     * @param int $expiration Expiration in seconds
     * @return string
     */
    public function temporaryUrl(string $path, int $expiration): string
    {
        return $this->disk()->temporaryUrl($path, $expiration);
    }
}
