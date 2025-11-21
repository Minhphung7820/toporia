#!/usr/bin/env php
<?php

/**
 * Clear Redis Cache Script
 *
 * Clears all cache entries from Redis for testing/debugging.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Application\Application;

$app = Application::getInstance();
$app->bootstrap();

$cache = $app->get('cache');
$cacheManager = $app->get('cache_manager');

// Get cache driver type
$driver = $cacheManager->getDefaultDriver();
echo "Cache driver: {$driver}\n\n";

if ($driver !== 'redis') {
    echo "⚠️  Current cache driver is not Redis. Current driver: {$driver}\n";
    echo "Set CACHE_DRIVER=redis in .env to use Redis cache.\n";
    exit(1);
}

// Get Redis instance from cache
if (method_exists($cache, 'getRedis')) {
    $redis = $cache->getRedis();

    // Get prefix from config
    $prefix = $app->get('config')->get('cache.prefix', 'toporia_cache');
    $pattern = "{$prefix}:*";

    echo "Searching for keys matching: {$pattern}\n";

    // Get all keys with prefix
    $keys = $redis->keys($pattern);
    $count = count($keys);

    if ($count > 0) {
        echo "Found {$count} cache keys\n";

        // Delete all keys
        foreach ($keys as $key) {
            $redis->del($key);
        }

        echo "✅ Cleared {$count} cache keys\n";
    } else {
        echo "✅ No cache keys found\n";
    }
} else {
    // Fallback: use cache interface
    echo "Using cache interface to clear...\n";

    if (method_exists($cache, 'clear')) {
        $cache->clear();
        echo "✅ Cache cleared\n";
    } else {
        echo "❌ Cannot clear cache - method not available\n";
        exit(1);
    }
}

echo "\n✅ Redis cache cleared!\n";
echo "You can now test rate limiting from scratch.\n";

