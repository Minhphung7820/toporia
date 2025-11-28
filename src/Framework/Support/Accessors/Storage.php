<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Storage\StorageManager;
use Toporia\Framework\Storage\Contracts\FilesystemInterface;

/**
 * Storage Service Accessor
 *
 * Provides static-like access to the Storage system.
 * All methods are automatically delegated to the underlying service via __callStatic().
 *
 * @method static FilesystemInterface disk(?string $name = null) Get filesystem disk instance
 * @method static void setDefaultDisk(string $name) Set default disk
 * @method static string getDefaultDisk() Get default disk name
 * @method static bool put(string $path, mixed $contents, array $options = []) Store file contents
 * @method static string|null get(string $path) Get file contents
 * @method static resource|null readStream(string $path) Get file as stream resource
 * @method static bool exists(string $path) Check if file exists
 * @method static bool delete(string|array $paths) Delete file(s)
 * @method static bool copy(string $from, string $to) Copy file
 * @method static bool move(string $from, string $to) Move file
 * @method static int|null size(string $path) Get file size
 * @method static int|null lastModified(string $path) Get last modified timestamp
 * @method static string|null mimeType(string $path) Get MIME type
 * @method static array files(string $directory = '', bool $recursive = false) List files in directory
 * @method static array directories(string $directory = '', bool $recursive = false) List subdirectories
 * @method static bool makeDirectory(string $path) Create directory
 * @method static bool deleteDirectory(string $directory) Delete directory
 * @method static string url(string $path) Get public URL
 * @method static string temporaryUrl(string $path, int $expiration) Get temporary URL (signed)
 *
 * @see StorageManager
 *
 * @example
 * // Get default disk and use it
 * Storage::disk()->put('file.txt', 'content');
 * $content = Storage::disk()->get('file.txt');
 *
 * // Get specific disk
 * Storage::disk('s3')->put('uploads/photo.jpg', $data);
 *
 * // All FilesystemInterface methods available on disk
 * Storage::disk()->exists('file.txt');
 * Storage::disk()->delete('file.txt');
 * Storage::disk()->files('uploads');
 */
final class Storage extends ServiceAccessor
{
    /**
     * Get the service name for this accessor.
     *
     * This is the only method needed - all other methods are automatically
     * delegated to the underlying service via __callStatic().
     *
     * @return string Service name in container
     */
    protected static function getServiceName(): string
    {
        return 'storage';
    }
}
