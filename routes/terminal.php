<?php

declare(strict_types=1);

/**
 * Terminal Command Routes
 *
 * Define closure-based console commands here as an alternative to creating
 * full command classes. Perfect for simple, one-off tasks or quick utilities.
 *
 * Features:
 * - Lightweight closure-based commands
 * - Automatic dependency injection
 * - Access to all Command methods ($this->info(), $this->ask(), etc.)
 * - Support for arguments and options
 * - Fluent API with ->describe()
 *
 * Example Usage:
 *
 * ```php
 * Terminal::command('mail:send {user}', function (string $user) {
 *     $this->info("Sending email to: {$user}");
 * })->describe('Send marketing email to user');
 *
 * Terminal::command('cache:warm {--tags=*}', function (CacheService $cache) {
 *     $tags = $this->option('tags');
 *     $cache->warm($tags);
 *     $this->info('Cache warmed successfully!');
 * })->describe('Warm up application cache');
 * ```
 *
 * @see https://github.com/Minhphung7820/toporia/docs/TERMINAL_COMMANDS.md
 */

use Toporia\Framework\Support\Accessors\Terminal;

// Example 1: Simple command with argument
Terminal::command('greet {name}', function (string $name) {
    $this->info("Hello, {$name}! Welcome to Toporia Framework.");
    return 0;
})->describe('Greet a user by name');

/*
 * Add your custom terminal commands below:
 */
