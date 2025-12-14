<?php

declare(strict_types=1);

/**
 * Application Helper Functions
 *
 * Global helper functions for convenient access to application services.
 */

use Toporia\Framework\Events\Contracts\EventInterface;

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service.
     *
     * @param string|null $id Service identifier or null for application.
     * @return mixed
     */
    function app(?string $id = null): mixed
    {
        static $instance = null;

        if ($instance === null) {
            global $app;
            $instance = $app;
        }

        return $id !== null ? $instance->make($id) : $instance;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the application.
     *
     * @param string $path Path to append to base path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return app()->path($path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param string $path Path to append to storage path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     *
     * @param string $path Path to append to resources path
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param string $path Path to append to public path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the path to the config folder.
     *
     * @param string $path Path to append to config path
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event.
     *
     * @param string|EventInterface $event Event name or object.
     * @param array $payload Event payload data.
     * @return EventInterface
     */
    function event(string|EventInterface $event, array $payload = []): EventInterface
    {
        return app('events')->dispatch($event, $payload);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the authentication service.
     *
     * @return mixed
     */
    function auth(): mixed
    {
        return app('auth');
    }
}

if (!function_exists('container')) {
    /**
     * Get the container instance or resolve a service.
     *
     * @param string|null $id Service identifier or null for container.
     * @return mixed
     */
    function container(?string $id = null): mixed
    {
        $container = app()->getContainer();
        return $id !== null ? $container->get($id) : $container;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation.
     *
     * @param string $key Configuration key (e.g., 'app.name', 'database.default')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        // Load config file directly if app() not available yet
        static $configs = [];
        static $envLoaded = false;

        // Ensure env is loaded before loading config files (they use env())
        if (!$envLoaded) {
            $basePath = dirname(__DIR__);
            // Use framework's built-in env loader
            if (class_exists(\Toporia\Framework\Foundation\LoadEnvironmentVariables::class)) {
                \Toporia\Framework\Foundation\LoadEnvironmentVariables::bootstrap($basePath);
            }
            $envLoaded = true;
        }

        // Parse key: 'realtime.default_broker' -> file='realtime', key='default_broker'
        $parts = explode('.', $key, 2);
        $file = $parts[0];
        $configKey = $parts[1] ?? null;

        // Load config file if not cached
        if (!isset($configs[$file])) {
            $configPath = dirname(__DIR__) . '/config/' . $file . '.php';
            if (file_exists($configPath)) {
                $configs[$file] = require $configPath;
            } else {
                $configs[$file] = [];
            }
        }

        // Return full config or nested key
        if ($configKey === null) {
            return $configs[$file] ?: $default;
        }

        // Support nested keys like 'database.connections.mysql'
        $value = $configs[$file];
        foreach (explode('.', $configKey) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value.
     *
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Parse boolean values
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate a CSRF token.
     *
     * @param string $key Token identifier
     * @return string
     */
    function csrf_token(string $key = '_token'): string
    {
        $tokenManager = app('csrf');
        $existing = $tokenManager->getToken($key);

        if ($existing !== null) {
            return $existing;
        }

        return $tokenManager->generate($key);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token hidden input field.
     *
     * @param string $key Token identifier
     * @return string
     */
    function csrf_field(string $key = '_token'): string
    {
        $token = csrf_token($key);
        return '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * Get or generate the CSP nonce for inline scripts/styles.
     *
     * SECURITY: Use this in templates with inline scripts/styles:
     *   <script nonce="<?= csp_nonce() ?>">...</script>
     *   <style nonce="<?= csp_nonce() ?>">...</style>
     *
     * The nonce is generated once per request and stored in the container.
     * This allows the same nonce to be used across multiple inline elements.
     *
     * @return string Base64-encoded nonce
     */
    function csp_nonce(): string
    {
        static $nonce = null;

        if ($nonce === null) {
            // Try to get from AddSecurityHeaders middleware if it's been processed
            try {
                $container = app();
                if ($container->has('csp_nonce')) {
                    $nonce = $container->get('csp_nonce');
                } else {
                    // Generate and store for this request
                    $nonce = base64_encode(random_bytes(16));
                    $container->instance('csp_nonce', $nonce);
                }
            } catch (\Throwable $e) {
                // Fallback: generate nonce without storing
                $nonce = base64_encode(random_bytes(16));
            }
        }

        return $nonce;
    }
}

if (!function_exists('cache')) {
    /**
     * Get the cache service or get/set a cached value.
     *
     * @param string|null $key Cache key
     * @param mixed $default Default value
     * @return mixed
     */
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $cache = app('cache');

        if ($key === null) {
            return $cache;
        }

        return $cache->get($key, $default);
    }
}

if (!function_exists('session')) {
    /**
     * Get the session store instance or get/set a session value.
     *
     * @param string|array|null $key Session key or array of key-value pairs
     * @param mixed $default Default value when getting
     * @return mixed|\Toporia\Framework\Session\Store
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        $session = app('session');

        if ($key === null) {
            return $session;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $session->set($k, $v);
            }
            $session->save();
            return null;
        }

        return $session->get($key, $default);
    }
}

if (!function_exists('cookie')) {
    /**
     * Get a cookie value or the Cookie manager.
     *
     * Usage:
     * - cookie() - Get Cookie manager instance
     * - cookie('name') - Get cookie value (decoded/decrypted)
     * - cookie('name', 'default') - Get with default value
     *
     * @param string|null $key Cookie name
     * @param mixed $default Default value if not found
     * @return mixed|null
     */
    function cookie(?string $key = null, mixed $default = null): mixed
    {
        // If no key, return raw cookies for manager-like usage
        if ($key === null) {
            return $_COOKIE;
        }

        // Get cookie value
        $value = $_COOKIE[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('set_cookie')) {
    /**
     * Set a cookie with secure defaults.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $minutes Minutes until expiration (0 = session cookie)
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool|null $secure HTTPS only (null = auto-detect)
     * @param bool $httponly HttpOnly flag
     * @param string $samesite SameSite attribute (Strict, Lax, None)
     * @return bool True on success
     */
    function set_cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        string $domain = '',
        ?bool $secure = null,
        bool $httponly = true,
        string $samesite = 'Lax'
    ): bool {
        if (headers_sent()) {
            return false;
        }

        // Auto-detect secure connection
        if ($secure === null) {
            $secure = (
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            );
        }

        // Calculate expiration
        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

        return setcookie(
            $name,
            $value,
            [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]
        );
    }
}

if (!function_exists('forget_cookie')) {
    /**
     * Remove a cookie by setting it to expire in the past.
     *
     * @param string $name Cookie name
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @return bool True on success
     */
    function forget_cookie(string $name, string $path = '/', string $domain = ''): bool
    {
        if (headers_sent()) {
            return false;
        }

        // Unset from current request
        unset($_COOKIE[$name]);

        // Set expiration in the past
        return setcookie(
            $name,
            '',
            [
                'expires' => time() - 3600,
                'path' => $path,
                'domain' => $domain,
            ]
        );
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters.
     *
     * @param string|null $value
     * @param bool $doubleEncode
     * @return string
     */
    function e(?string $value, bool $doubleEncode = true): string
    {
        return \Toporia\Framework\Security\XssProtection::escape($value, $doubleEncode);
    }
}

if (!function_exists('clean')) {
    /**
     * Remove all HTML tags from a string.
     *
     * @param string|null $value
     * @return string
     */
    function clean(?string $value): string
    {
        return \Toporia\Framework\Security\XssProtection::clean($value);
    }
}

if (!function_exists('mail')) {
    /**
     * Get the mail manager or send an email.
     *
     * @param \Toporia\Framework\Mail\Mailable|null $mailable Mailable to send.
     * @return \Toporia\Framework\Mail\MailManagerInterface|bool
     */
    function mail(?\Toporia\Framework\Mail\Mailable $mailable = null): mixed
    {
        $manager = app('mail');

        if ($mailable === null) {
            return $manager;
        }

        return $manager->sendMailable($mailable);
    }
}

if (!function_exists('vite')) {
    /**
     * Generate Vite script tag for entry point.
     *
     * @param string $entry Entry point file (e.g., 'resources/js/app.js')
     * @param array $attributes Additional HTML attributes
     * @return string HTML script tag
     */
    function vite(string $entry, array $attributes = []): string
    {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->script($entry, $attributes);
        }

        // Fallback if Vite service not available
        return '<!-- Vite service not available -->';
    }
}

if (!function_exists('vite_css')) {
    /**
     * Generate Vite CSS link tags for entry point.
     *
     * @param string $entry Entry point file
     * @param array $attributes Additional HTML attributes
     * @return string HTML link tags
     */
    function vite_css(string $entry, array $attributes = []): string
    {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->css($entry, $attributes);
        }

        // Fallback if Vite service not available
        return '';
    }
}

if (!function_exists('vite_assets')) {
    /**
     * Generate both Vite script and CSS tags.
     *
     * @param string $entry Entry point file
     * @param array $scriptAttributes Script tag attributes
     * @param array $cssAttributes CSS link tag attributes
     * @return string Combined HTML tags
     */
    function vite_assets(string $entry, array $scriptAttributes = [], array $cssAttributes = []): string
    {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->assets($entry, $scriptAttributes, $cssAttributes);
        }

        // Fallback if Vite service not available
        return '';
    }
}


if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array|int|null $key
     * @param mixed $default
     * @return mixed
     */
    function data_get(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (!is_array($target)) {
                    return $default;
                }

                $result = [];
                foreach ($target as $item) {
                    $result[] = data_get($item, $key, $default);
                }

                return in_array('*', $key) ? array_merge(...$result) : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (is_array($target)) {
            if ($segments) {
                if (!array_key_exists($segment, $target)) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !array_key_exists($segment, $target)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('http')) {
    /**
     * Get the HTTP client manager or make a request.
     *
     * @param string|null $client Client name.
     * @return \Toporia\Framework\Http\Client\ClientManagerInterface|\Toporia\Framework\Http\Client\HttpClientInterface
     */
    function http(?string $client = null): mixed
    {
        $manager = app('http');

        if ($client === null) {
            return $manager;
        }

        return $manager->client($client);
    }
}

if (!function_exists('storage')) {
    /**
     * Get the storage manager or a specific disk.
     *
     * Usage:
     * - storage() - Get StorageManager
     * - storage('local') - Get specific disk
     * - storage()->disk('s3') - Get S3 disk
     *
     * @param string|null $disk Disk name
     * @return \Toporia\Framework\Storage\StorageManager|\Toporia\Framework\Storage\Contracts\FilesystemInterface
     */
    function storage(?string $disk = null): mixed
    {
        $manager = app('storage');

        if ($disk === null) {
            return $manager;
        }

        return $manager->disk($disk);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump the given variable with beautiful formatting.
     *
     * Performance: Optimized with lazy evaluation and efficient string building
     * Clean Architecture: Uses VarDumper class for maintainability
     *
     * @param mixed $var Variable to dump
     * @return mixed Returns the dumped variable for chaining
     */
    function dump(mixed $var): mixed
    {
        return \Toporia\Framework\Support\VarDumper::dump($var);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given variables and end the script.
     *
     * Performance: Optimized with single output buffer
     * Clean Architecture: Uses VarDumper class for maintainability
     *
     * @param mixed ...$vars Variables to dump
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        \Toporia\Framework\Support\VarDumper::dd(...$vars);
    }
}

if (!function_exists('notify')) {
    /**
     * Send a notification to a notifiable entity.
     *
     * @param \Toporia\Framework\Notification\Contracts\NotifiableInterface $notifiable
     * @param \Toporia\Framework\Notification\Contracts\NotificationInterface $notification
     * @return void
     */
    function notify(
        \Toporia\Framework\Notification\Contracts\NotifiableInterface $notifiable,
        \Toporia\Framework\Notification\Contracts\NotificationInterface $notification
    ): void {
        app('notification')->send($notifiable, $notification);
    }
}

if (!function_exists('realtime')) {
    /**
     * Get the realtime manager instance.
     *
     * Usage:
     * - realtime() - Get RealtimeManager instance
     * - realtime()->broadcast(...) - Broadcast message
     * - realtime()->send(...) - Send to connection
     *
     * @return \Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface
     */
    function realtime(): \Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface
    {
        return app('realtime');
    }
}

if (!function_exists('broadcast')) {
    /**
     * Broadcast realtime event to a channel.
     *
     * Usage:
     *   broadcast('channel', 'event', $data);                    // Quick send
     *   broadcast('channel', 'event', $data, 'kafka');           // With driver
     *   broadcast()->channel('ch')->event('ev')->with($d)->now(); // Fluent API
     *
     * @param string|null $channel Channel name (null for fluent API)
     * @param string|null $event Event name
     * @param array $data Event data
     * @param string|null $driver Broker driver (redis, rabbitmq, kafka)
     * @return \Toporia\Framework\Realtime\Broadcast|bool
     */
    function broadcast(
        ?string $channel = null,
        ?string $event = null,
        array $data = [],
        ?string $driver = null
    ): \Toporia\Framework\Realtime\Broadcast|bool {
        // Fluent API mode: broadcast()->channel('ch')->...
        if ($channel === null) {
            return \Toporia\Framework\Realtime\Broadcast::create();
        }

        // Quick send mode: broadcast('channel', 'event', $data)
        return \Toporia\Framework\Realtime\Broadcast::send($channel, $event ?? 'message', $data, $driver);
    }
}

if (!function_exists('broadcastBatch')) {
    /**
     * Create a BatchBroadcast instance for high-throughput batch publishing.
     *
     * TRUE Kafka batching: queue all → compress → single flush
     * Performance: 50K-200K msg/s (vs 1K-5K with individual publish)
     *
     * Usage:
     *   $result = broadcastBatch('kafka')
     *       ->channel('events.stream')
     *       ->event('user.action')
     *       ->messages([
     *           ['user_id' => 1, 'action' => 'login'],
     *           ['user_id' => 2, 'action' => 'logout'],
     *       ])
     *       ->publish();
     *
     *   // Builder pattern
     *   $result = broadcastBatch('kafka')
     *       ->channel('events')
     *       ->event('notification')
     *       ->add(['user_id' => 1, 'type' => 'welcome'])
     *       ->add(['user_id' => 2, 'type' => 'reminder'])
     *       ->publish();
     *
     *   // Memory efficient for large datasets
     *   $result = broadcastBatch('kafka')
     *       ->channel('events')
     *       ->event('bulk')
     *       ->each($users, fn($user) => ['id' => $user->id, 'name' => $user->name])
     *       ->publish();
     *
     * @param string|null $driver Broker driver (kafka, redis, rabbitmq)
     * @return \Toporia\Framework\Realtime\BatchBroadcast
     */
    function broadcastBatch(?string $driver = null): \Toporia\Framework\Realtime\BatchBroadcast
    {
        return \Toporia\Framework\Realtime\BatchBroadcast::create($driver);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value.
     *
     * Usage:
     * - config('app.name') - Get specific config value
     * - config('app.name', 'default') - With default value
     *
     * @param string $key Config key in dot notation (e.g., 'app.name')
     * @param mixed $default Default value
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')->get($key, $default);
    }
}

if (!function_exists('hash_make')) {
    /**
     * Hash the given value.
     *
     * @param string $value Plain text value to hash
     * @param array $options Hashing options
     * @return string Hashed value
     */
    function hash_make(string $value, array $options = []): string
    {
        return app('hash')->make($value, $options);
    }
}

if (!function_exists('hash_check')) {
    /**
     * Check the given plain value against a hash.
     *
     * @param string $value Plain text value
     * @param string $hashedValue Hashed value
     * @return bool True if match
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        return app('hash')->check($value, $hashedValue);
    }
}

if (!function_exists('hash_needs_rehash')) {
    /**
     * Check if the given hash needs to be rehashed.
     *
     * @param string $hashedValue Hashed value
     * @param array $options Current options
     * @return bool True if rehash needed
     */
    function hash_needs_rehash(string $hashedValue, array $options = []): bool
    {
        return app('hash')->needsRehash($hashedValue, $options);
    }
}

// ============================================================================
// URL Generation Helpers
// ============================================================================

if (!function_exists('url')) {
    /**
     * Generate a URL to a path.
     *
     * Usage:
     * - url() - Get UrlGenerator instance
     * - url('/path') - Generate URL to path
     * - url('/path', ['key' => 'value']) - With query parameters
     *
     * @param string|null $path URL path
     * @param array<string, mixed> $query Query parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return \Toporia\Framework\Routing\UrlGeneratorInterface|string
     */
    function url(?string $path = null, array $query = [], bool $absolute = true): mixed
    {
        $generator = app('url');

        if ($path === null) {
            return $generator;
        }

        return $generator->to($path, $query, $absolute);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     *
     * @param string $path Asset path
     * @param bool $absolute Generate absolute URL (default: false)
     * @return string Generated URL
     */
    function asset(string $path, bool $absolute = false): string
    {
        return app('url')->asset($path, $absolute);
    }
}

if (!function_exists('secure_asset')) {
    /**
     * Generate a secure asset URL (HTTPS).
     *
     * @param string $path Asset path
     * @return string Generated URL
     */
    function secure_asset(string $path): string
    {
        return app('url')->secureAsset($path);
    }
}

if (!function_exists('secure_url')) {
    /**
     * Generate a secure URL to a path (HTTPS).
     *
     * @param string $path URL path
     * @param array<string, mixed> $query Query parameters
     * @return string Generated URL
     */
    function secure_url(string $path, array $query = []): string
    {
        $generator = app('url');
        $generator->forceScheme('https');
        return $generator->to($path, $query, true);
    }
}

if (!function_exists('url_current')) {
    /**
     * Get the current URL.
     *
     * @return string Current URL
     */
    function url_current(): string
    {
        return app('url')->current();
    }
}

if (!function_exists('url_previous')) {
    /**
     * Get the previous URL.
     *
     * @param string|null $default Default URL if no previous
     * @return string Previous URL
     */
    function url_previous(?string $default = null): string
    {
        return app('url')->previous($default);
    }
}

if (!function_exists('url_full')) {
    /**
     * Get the full URL for the current request with query string.
     *
     * @return string Full URL
     */
    function url_full(): string
    {
        return app('url')->full();
    }
}

if (!function_exists('signed_route')) {
    /**
     * Generate a signed URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param int|null $expiration Expiration in seconds from now
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    function signed_route(string $name, array $parameters = [], ?int $expiration = null, bool $absolute = true): string
    {
        return app('url')->signedRoute($name, $parameters, $expiration, $absolute);
    }
}

if (!function_exists('temporary_signed_route')) {
    /**
     * Generate a temporary signed URL to a named route.
     *
     * @param string $name Route name
     * @param int $expiration Expiration in seconds from now
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    function temporary_signed_route(string $name, int $expiration, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->temporarySignedRoute($name, $expiration, $parameters, $absolute);
    }
}

// ============================================================================
// Pipeline Helpers
// ============================================================================

if (!function_exists('pipeline')) {
    /**
     * Create a new pipeline instance.
     *
     * Usage:
     * ```php
     * $result = pipeline($user)
     *     ->through([
     *         ValidateUser::class,
     *         NormalizeData::class,
     *         fn($user, $next) => $next($user)
     *     ])
     *     ->thenReturn();
     * ```
     *
     * @param mixed|null $passable Initial value to send through pipeline
     * @return \Toporia\Framework\Pipeline\Pipeline
     */
    function pipeline(mixed $passable = null): \Toporia\Framework\Pipeline\Pipeline
    {
        $pipeline = \Toporia\Framework\Pipeline\Pipeline::make(app()->getContainer());

        if ($passable !== null) {
            $pipeline->send($passable);
        }

        return $pipeline;
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance or log a message.
     *
     * @param string|null $message Log message (null to get logger instance)
     * @param array $context Context data
     * @param string $level Log level (info, error, warning, etc.)
     * @return \Toporia\Framework\Log\Contracts\LoggerInterface|void
     */
    function logger(?string $message = null, array $context = [], string $level = 'info')
    {
        $logger = app('logger');

        if ($message === null) {
            return $logger;
        }

        $logger->log($level, $message, $context);
    }
}

if (!function_exists('log_info')) {
    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_info(string $message, array $context = []): void
    {
        app('logger')->info($message, $context);
    }
}

if (!function_exists('log_error')) {
    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_error(string $message, array $context = []): void
    {
        app('logger')->error($message, $context);
    }
}

if (!function_exists('log_warning')) {
    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_warning(string $message, array $context = []): void
    {
        app('logger')->warning($message, $context);
    }
}

if (!function_exists('log_debug')) {
    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_debug(string $message, array $context = []): void
    {
        app('logger')->debug($message, $context);
    }
}

// ============================================================================
// Date/Time Helpers (Chronos)
// ============================================================================

if (!function_exists('now')) {
    /**
     * Create a new Chronos instance for the current date and time.
     *
     * @param \DateTimeZone|string|null $timezone
     * @return \Toporia\Framework\DateTime\Chronos
     */
    function now(\DateTimeZone|string|null $timezone = null): \Toporia\Framework\DateTime\Chronos
    {
        return \Toporia\Framework\DateTime\Chronos::now($timezone);
    }
}

if (!function_exists('today')) {
    /**
     * Create a Chronos instance for today's date at midnight.
     *
     * @param \DateTimeZone|string|null $timezone
     * @return \Toporia\Framework\DateTime\Chronos
     */
    function today(\DateTimeZone|string|null $timezone = null): \Toporia\Framework\DateTime\Chronos
    {
        return \Toporia\Framework\DateTime\Chronos::now($timezone)->startOfDay();
    }
}

if (!function_exists('yesterday')) {
    /**
     * Create a Chronos instance for yesterday's date at midnight.
     *
     * @param \DateTimeZone|string|null $timezone
     * @return \Toporia\Framework\DateTime\Chronos
     */
    function yesterday(\DateTimeZone|string|null $timezone = null): \Toporia\Framework\DateTime\Chronos
    {
        return \Toporia\Framework\DateTime\Chronos::now($timezone)->subDays(1)->startOfDay();
    }
}

if (!function_exists('tomorrow')) {
    /**
     * Create a Chronos instance for tomorrow's date at midnight.
     *
     * @param \DateTimeZone|string|null $timezone
     * @return \Toporia\Framework\DateTime\Chronos
     */
    function tomorrow(\DateTimeZone|string|null $timezone = null): \Toporia\Framework\DateTime\Chronos
    {
        return \Toporia\Framework\DateTime\Chronos::now($timezone)->addDays(1)->startOfDay();
    }
}

if (!function_exists('chronos')) {
    /**
     * Create a Chronos instance from a string or return current time.
     *
     * @param string|null $time
     * @param \DateTimeZone|string|null $timezone
     * @return \Toporia\Framework\DateTime\Chronos
     */
    function chronos(?string $time = null, \DateTimeZone|string|null $timezone = null): \Toporia\Framework\DateTime\Chronos
    {
        if ($time === null) {
            return \Toporia\Framework\DateTime\Chronos::now($timezone);
        }

        return \Toporia\Framework\DateTime\Chronos::parse($time, $timezone);
    }
}

// ============================================================================
// AUTHORIZATION HELPERS (Gate & Policy)
// ============================================================================

if (!function_exists('gate')) {
    /**
     * Get the gate instance.
     *
     * @return \Toporia\Framework\Auth\Contracts\GateContract
     */
    function gate(): \Toporia\Framework\Auth\Contracts\GateContract
    {
        return app(\Toporia\Framework\Auth\Contracts\GateContract::class);
    }
}

if (!function_exists('can')) {
    /**
     * Determine if the current user has a given ability.
     *
     * @param string $ability Ability name
     * @param mixed ...$arguments Arguments (typically resource instance)
     * @return bool True if allowed
     */
    function can(string $ability, mixed ...$arguments): bool
    {
        return gate()->allows($ability, ...$arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * Determine if the current user does not have a given ability.
     *
     * @param string $ability Ability name
     * @param mixed ...$arguments Arguments
     * @return bool True if denied
     */
    function cannot(string $ability, mixed ...$arguments): bool
    {
        return gate()->denies($ability, ...$arguments);
    }
}

if (!function_exists('authorize')) {
    /**
     * Authorize an ability or throw exception.
     *
     * @param string $ability Ability name
     * @param mixed ...$arguments Arguments
     * @return \Toporia\Framework\Auth\Access\Response Authorization response
     * @throws \Toporia\Framework\Auth\AuthorizationException If denied
     */
    function authorize(string $ability, mixed ...$arguments): \Toporia\Framework\Auth\Access\Response
    {
        return gate()->authorize($ability, ...$arguments);
    }
}

if (!function_exists('deny')) {
    /**
     * Create a denied authorization response.
     *
     * @param string|null $message Denial reason
     * @param mixed $code Error code
     * @return \Toporia\Framework\Auth\Access\Response Denied response
     */
    function deny(?string $message = null, mixed $code = null): \Toporia\Framework\Auth\Access\Response
    {
        return \Toporia\Framework\Auth\Access\Response::deny($message, $code);
    }
}

if (!function_exists('allow')) {
    /**
     * Create an allowed authorization response.
     *
     * @param string|null $message Success message
     * @return \Toporia\Framework\Auth\Access\Response Allowed response
     */
    function allow(?string $message = null): \Toporia\Framework\Auth\Access\Response
    {
        return \Toporia\Framework\Auth\Access\Response::allow($message);
    }
}

// =============================================================================
// Vite Helper Functions
// =============================================================================

if (!function_exists('vite')) {
    /**
     * Generate Vite script tag for an entry point.
     *
     * Automatically switches between dev server (HMR) and production manifest.
     *
     * @param string $entry Entry point file (e.g., 'resources/js/app.js')
     * @param array $attributes Additional HTML attributes
     * @return string HTML script tag
     *
     * @example
     * // In view template
     * {!! vite('resources/js/app.js') !!}
     * {!! vite('resources/js/admin.js', ['defer' => true]) !!}
     */
    function vite(string $entry, array $attributes = []): string
    {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->script($entry, $attributes);
        }

        throw new \RuntimeException('Vite service not available. Register ViteServiceProvider.');
    }
}

if (!function_exists('vite_css')) {
    /**
     * Generate Vite CSS link tags for an entry point.
     *
     * Returns empty string in development (CSS handled by Vite).
     * Returns CSS links in production (from manifest).
     *
     * @param string $entry Entry point file
     * @param array $attributes Additional HTML attributes
     * @return string HTML link tags
     *
     * @example
     * // In view template <head>
     * {!! vite_css('resources/js/app.js') !!}
     */
    function vite_css(string $entry, array $attributes = []): string
    {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->css($entry, $attributes);
        }

        throw new \RuntimeException('Vite service not available. Register ViteServiceProvider.');
    }
}

if (!function_exists('vite_assets')) {
    /**
     * Generate both Vite script and CSS tags.
     *
     * Convenience function to output both script and CSS in one call.
     *
     * @param string $entry Entry point file
     * @param array $scriptAttributes Script tag attributes
     * @param array $cssAttributes CSS link tag attributes
     * @return string Combined HTML tags
     *
     * @example
     * // In view template
     * {!! vite_assets('resources/js/app.js') !!}
     */
    function vite_assets(
        string $entry,
        array $scriptAttributes = [],
        array $cssAttributes = []
    ): string {
        if (function_exists('app') && app()->has('vite')) {
            return app('vite')->assets($entry, $scriptAttributes, $cssAttributes);
        }

        throw new \RuntimeException('Vite service not available. Register ViteServiceProvider.');
    }
}

// ========================================
// Bus Helpers
// ========================================

if (!function_exists('dispatch')) {
    /**
     * Dispatch a command/query/job to its handler.
     *
     * @template T
     * @param T $command Command instance
     * @return \Toporia\Framework\Bus\PendingDispatch<T>
     */
    function dispatch(mixed $command): \Toporia\Framework\Bus\PendingDispatch
    {
        return \Toporia\Framework\Bus\Bus::dispatch2($command);
    }
}

if (!function_exists('dispatch_sync')) {
    /**
     * Dispatch a command/query/job synchronously.
     *
     * @param mixed $command Command instance
     * @return mixed Handler result
     */
    function dispatch_sync(mixed $command): mixed
    {
        return \Toporia\Framework\Bus\Bus::dispatchSync($command);
    }
}

if (!function_exists('batch')) {
    /**
     * Create a new batch of jobs.
     *
     * @param array<mixed> $jobs Jobs array
     * @return \Toporia\Framework\Bus\PendingBatch
     */
    function batch(array $jobs): \Toporia\Framework\Bus\PendingBatch
    {
        return \Toporia\Framework\Bus\Bus::batch($jobs);
    }
}

if (!function_exists('chain')) {
    /**
     * Create a new chain of jobs (sequential execution).
     *
     * Performance:
     * - O(1) creation (lazy execution)
     * - Jobs executed sequentially when dispatch() is called
     * - Early termination on failure
     *
     * @template T
     * @param array<mixed> $jobs Jobs to chain (executed sequentially)
     * @return \Toporia\Framework\Bus\PendingChain<T>
     */
    function chain(array $jobs): \Toporia\Framework\Bus\PendingChain
    {
        return \Toporia\Framework\Bus\Bus::chain($jobs);
    }
}

// ============================================================================
// Concurrency Helpers
// ============================================================================

if (!function_exists('concurrency')) {
    /**
     * Run tasks concurrently or get Concurrency instance.
     *
     * Usage:
     * - concurrency($tasks) - Run tasks in parallel
     * - concurrency()->defer($task) - Defer task
     * - concurrency()->driver('sync') - Get specific driver
     *
     * @param array<string|int, callable>|null $tasks Tasks to run (null = get instance)
     * @return array<string|int, mixed>|\Toporia\Framework\Concurrency\ConcurrencyManager Results or instance
     *
     * @example
     * // Run parallel tasks with named results
     * $results = concurrency([
     *     'users' => fn() => User::all(),
     *     'posts' => fn() => Post::recent(),
     * ]);
     * // $results['users'], $results['posts']
     *
     * // Defer task to run after response
     * concurrency()->defer(fn() => sendEmail());
     */
    function concurrency(?array $tasks = null): mixed
    {
        $manager = app('concurrency');

        if ($tasks !== null) {
            return $manager->run($tasks);
        }

        return $manager;
    }
}

if (!function_exists('defer')) {
    /**
     * Defer a task to run after the response is sent.
     *
     * Use this for non-critical tasks like:
     * - Sending emails
     * - Logging analytics
     * - Cleanup operations
     * - Cache warming
     *
     * @param callable $task Task to defer
     * @return void
     *
     * @example
     * // In a controller
     * public function store(Request $request)
     * {
     *     $user = User::create($request->all());
     *
     *     // Defer email - response sent immediately
     *     defer(fn() => Mail::send(new WelcomeEmail($user)));
     *
     *     return response()->json($user);
     * }
     */
    function defer(callable $task): void
    {
        app('concurrency')->defer($task);
    }
}

// ============================================================================
// Translation Helpers
// ============================================================================

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * Usage:
     * - __('messages.welcome') - Simple translation
     * - __('messages.welcome', [':name' => 'John']) - With replacements
     * - __('messages.welcome', [':name' => 'John'], 'vi') - With locale
     *
     * Performance:
     * - O(1) service lookup (cached)
     * - O(1) translation cache (in-memory)
     * - Lazy loading (only loads when needed)
     *
     * @param string $key Translation key (dot notation supported)
     * @param array<string, mixed> $replace Replacements for placeholders
     * @param string|null $locale Target locale (null = use current locale)
     * @return string Translated message or key if not found
     *
     * @example
     * // Simple translation
     * echo __('messages.welcome'); // "Welcome"
     *
     * // With replacements
     * echo __('messages.welcome', [':name' => 'John']); // "Welcome, John"
     *
     * // With locale
     * echo __('messages.welcome', [], 'vi'); // "Chào mừng"
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        if (!function_exists('app') || !app()->has('translation')) {
            // Fallback if translation service not available
            return $key;
        }

        return app('translation')->get($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate the given message (alias for __).
     *
     * Usage:
     * - trans('messages.welcome')
     * - trans('messages.welcome', [':name' => 'John'])
     * - trans('messages.welcome', [':name' => 'John'], 'vi')
     *
     * @param string $key Translation key
     * @param array<string, mixed> $replace Replacements
     * @param string|null $locale Target locale
     * @return string Translated message
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate the given message with pluralization.
     *
     * Supports pluralization:
     * - Simple: "one|many"
     * - Complex: "{0} No apples|{1} One apple|[2,*] Many apples"
     *
     * Usage:
     * - trans_choice('messages.apples', 5)
     * - trans_choice('messages.apples', 5, [':name' => 'John'])
     * - trans_choice('messages.apples', 5, [':name' => 'John'], 'vi')
     *
     * @param string $key Translation key
     * @param int|array<string, mixed> $number Number for pluralization or replacements
     * @param array<string, mixed> $replace Replacements
     * @param string|null $locale Target locale
     * @return string Translated message
     *
     * @example
     * // Translation file: "one|many"
     * trans_choice('messages.apples', 1); // "one"
     * trans_choice('messages.apples', 5); // "many"
     *
     * // Translation file: "{0} No apples|{1} One apple|[2,*] Many apples"
     * trans_choice('messages.apples', 0); // "No apples"
     * trans_choice('messages.apples', 1); // "One apple"
     * trans_choice('messages.apples', 5); // "Many apples"
     */
    function trans_choice(string $key, int|array $number, array $replace = [], ?string $locale = null): string
    {
        if (!function_exists('app') || !app()->has('translation')) {
            return $key;
        }

        return app('translation')->choice($key, $number, $replace, $locale);
    }
}

if (!function_exists('trans_has')) {
    /**
     * Check if a translation exists for the given key.
     *
     * @param string $key Translation key
     * @param string|null $locale Target locale
     * @return bool True if translation exists
     */
    function trans_has(string $key, ?string $locale = null): bool
    {
        if (!function_exists('app') || !app()->has('translation')) {
            return false;
        }

        return app('translation')->has($key, $locale);
    }
}

if (!function_exists('DB')) {
    /**
     * Get DatabaseManager instance with fluent API for connection selection.
     *
     * Usage:
     * ```php
     * // Get DatabaseManager
     * $manager = DB();
     *
     * // Create QueryBuilder with specific connection (fluent API)
     * $query = DB()->onConnection('mysql')->table('users')->where('status', 'active');
     * $mongoQuery = DB()->onConnection('mongodb')->table('messages')->where('user_id', 123);
     *
     * // Or use connection() method (returns ConnectionProxy for fluent API)
     * $query = DB()->connection('mysql')->table('users');
     * ```
     *
     * Performance: Connections are cached per name (O(1) lookup after first call)
     * Grammar is automatically selected based on connection driver
     *
     * @return \Toporia\Framework\Database\DatabaseManager
     */
    function DB(): \Toporia\Framework\Database\DatabaseManager
    {
        static $manager = null;

        if ($manager === null) {
            $config = config('database.connections', []);
            $manager = new \Toporia\Framework\Database\DatabaseManager($config);
        }

        return $manager;
    }
}

// ============================================================================
// Hash/Security Helpers
// ============================================================================

if (!function_exists('bcrypt')) {
    /**
     * Hash the given value using Bcrypt.
     *
     * Uses PASSWORD_BCRYPT algorithm with automatic salt generation.
     * Default cost: 10 (can be configured in hashing config).
     *
     * Performance:
     * - O(1) service lookup (cached)
     * - ~50-100ms per hash (intentionally slow for security)
     *
     * Security:
     * - Bcrypt automatically handles salt generation
     * - Uses Blowfish cipher (adaptive hash function)
     * - Cost factor increases computation time exponentially
     *
     * @param string $value Plain text value to hash
     * @param array<string, mixed> $options Hashing options (e.g., ['rounds' => 12])
     * @return string Hashed value (60 characters)
     *
     * @example
     * // Hash password
     * $hash = bcrypt('secret123');
     *
     * // Hash with custom rounds
     * $hash = bcrypt('secret123', ['rounds' => 12]); // More secure but slower
     *
     * // Verify later
     * if (Hash::check('secret123', $hash)) {
     *     // Password correct
     * }
     */
    function bcrypt(string $value, array $options = []): string
    {
        // Try to use Hash facade if container is available
        try {
            if (function_exists('app') && app()->has('hash')) {
                return app()->make('hash')->make($value, $options);
            }
        } catch (\Throwable $e) {
            // Fallback to direct password_hash
        }

        // Fallback: Use native PHP password_hash
        $cost = $options['rounds'] ?? $options['cost'] ?? 10;
        return password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return \Toporia\Framework\Http\Response
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Toporia\Framework\Http\Response
    {
        $response = new \Toporia\Framework\Http\Response();

        // Set headers first
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        if (is_array($content) || is_object($content)) {
            $response->json($content, $status);
        } else {
            $response->setStatus($status);
            if ($content !== '') {
                $response->html((string) $content, $status);
            }
        }

        return $response;
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template.
     *
     * @param string $path View path relative to Views directory (without .php extension)
     * @param array $data Data to extract into view scope
     * @return string Rendered HTML content
     */
    function view(string $path, array $data = []): string
    {
        // Get the views directory path
        $viewsPath = base_path('resources/views');

        // Convert dot notation to directory separator
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $path);
        $fullPath = $viewsPath . DIRECTORY_SEPARATOR . $viewPath . '.php';

        // Check if view file exists
        if (!file_exists($fullPath)) {
            throw new \InvalidArgumentException("View file not found: {$fullPath}");
        }

        // Extract data into current scope
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();

        // Include the view file
        include $fullPath;

        // Get the rendered content
        $content = ob_get_clean();

        return $content ?: '';
    }
}
