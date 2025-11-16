#!/usr/bin/env php
<?php

/**
 * Fix duplicate use statements
 */

$directory = __DIR__ . '/src';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
);

$filesFixed = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    $originalContent = $content;

    // Find all use statements
    if (preg_match_all('/^use\s+([^;]+);/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $seen = [];
        $duplicates = [];

        foreach ($matches[1] as $match) {
            $import = trim($match[0]);

            // Extract the class name (last part after \)
            $parts = explode('\\', $import);
            $className = end($parts);

            if (isset($seen[$className])) {
                // Duplicate found
                $duplicates[] = $import;
            } else {
                $seen[$className] = $import;
            }
        }

        // Remove duplicates
        foreach ($duplicates as $duplicate) {
            $pattern = '/^use\s+' . preg_quote($duplicate, '/') . ';\n/m';
            $content = preg_replace($pattern, '', $content, 1);
        }

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            $filesFixed++;
            echo "✅ Fixed: {$file->getPathname()}\n";
            foreach ($duplicates as $dup) {
                echo "   Removed duplicate: use {$dup};\n";
            }
            echo "\n";
        }
    }
}

echo "\n";
echo "========================================\n";
echo "Summary:\n";
echo "========================================\n";
echo "Files fixed: {$filesFixed}\n";
echo "\n✅ Done!\n";
