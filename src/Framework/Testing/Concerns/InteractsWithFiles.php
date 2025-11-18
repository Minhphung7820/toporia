<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

/**
 * File Testing Trait
 *
 * Provides utilities for file system testing.
 *
 * Performance:
 * - Fast temporary file creation
 * - Efficient file assertions
 */
trait InteractsWithFiles
{
    /**
     * Temporary files created during test.
     *
     * @var array<string>
     */
    protected array $tempFiles = [];

    /**
     * Create a temporary file.
     *
     * Performance: O(1)
     */
    protected function createTempFile(string $content = '', string $extension = 'tmp'): string
    {
        $file = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.' . $extension;
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }

    /**
     * Assert that a file exists.
     *
     * Performance: O(1)
     */
    protected function assertFileExistsInPath(string $path): void
    {
        $this->assertTrue(file_exists($path), "File {$path} does not exist");
    }

    /**
     * Assert that a file doesn't exist.
     *
     * Performance: O(1)
     */
    protected function assertFileNotExistsInPath(string $path): void
    {
        $this->assertFalse(file_exists($path), "File {$path} unexpectedly exists");
    }

    /**
     * Assert file content.
     *
     * Performance: O(N) where N = file size
     */
    protected function assertFileContent(string $expected, string $path): void
    {
        $this->assertFileExistsInPath($path);
        $actual = file_get_contents($path);
        $this->assertEquals($expected, $actual, "File content mismatch for {$path}");
    }

    /**
     * Cleanup temporary files after test.
     *
     * Performance: O(N) where N = number of temp files
     */
    protected function tearDownFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }
}

