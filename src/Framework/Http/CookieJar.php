<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

/**
 * Cookie Jar
 *
 * Manages HTTP cookies with encryption support.
 * Provides a fluent interface for creating and managing cookies.
 */
final class CookieJar
{
    private array $queued = [];
    private ?string $encryptionKey = null;

    public function __construct(?string $encryptionKey = null)
    {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Get a cookie value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (!isset($_COOKIE[$name])) {
            return $default;
        }

        $value = $_COOKIE[$name];

        // Decrypt if encryption is enabled
        if ($this->encryptionKey !== null) {
            $value = $this->decrypt($value);
        }

        return $value;
    }

    /**
     * Check if a cookie exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Queue a cookie to be sent
     *
     * @param Cookie $cookie
     * @return self
     */
    public function queue(Cookie $cookie): self
    {
        $this->queued[$cookie->name] = $cookie;
        return $this;
    }

    /**
     * Create and queue a cookie
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param array $options
     * @return self
     */
    public function make(string $name, string $value, int $minutes = 60, array $options = []): self
    {
        // Encrypt value if encryption is enabled
        if ($this->encryptionKey !== null) {
            $value = $this->encrypt($value);
        }

        $cookie = Cookie::make($name, $value, $minutes, $options);
        return $this->queue($cookie);
    }

    /**
     * Create and queue a cookie that lasts forever
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @return self
     */
    public function forever(string $name, string $value, array $options = []): self
    {
        return $this->make($name, $value, 60 * 24 * 365 * 5, $options);
    }

    /**
     * Queue a cookie for deletion
     *
     * @param string $name
     * @param array $options
     * @return self
     */
    public function forget(string $name, array $options = []): self
    {
        $cookie = Cookie::forget($name, $options);
        return $this->queue($cookie);
    }

    /**
     * Send all queued cookies
     *
     * @return void
     */
    public function sendQueued(): void
    {
        foreach ($this->queued as $cookie) {
            $cookie->send();
        }

        $this->queued = [];
    }

    /**
     * Get all queued cookies
     *
     * @return array
     */
    public function getQueued(): array
    {
        return $this->queued;
    }

    /**
     * Encrypt a cookie value.
     *
     * Security: Uses AES-256-CBC with random IV for each encryption.
     * Performance: O(N) where N = value length
     *
     * @param string $value
     * @return string Encrypted value (base64 encoded)
     */
    public function encrypt(string $value): string
    {
        if ($this->encryptionKey === null) {
            return $value; // No encryption if key not set
        }

        // Derive encryption key from APP_KEY (32 bytes for AES-256)
        $key = $this->deriveKey($this->encryptionKey);

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Cookie encryption failed');
        }

        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a cookie value.
     *
     * Security: Validates IV and handles decryption errors gracefully.
     * Performance: O(N) where N = value length
     *
     * @param string $value Encrypted value (base64 encoded)
     * @return string|null Decrypted value or null on failure
     */
    public function decrypt(string $value): ?string
    {
        if ($this->encryptionKey === null) {
            return $value; // No decryption if key not set
        }

        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < 16) {
            return null; // Invalid format
        }

        // Extract IV and encrypted data
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        // Derive encryption key from APP_KEY
        $key = $this->deriveKey($this->encryptionKey);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Derive encryption key from APP_KEY.
     *
     * Security: Uses HKDF to derive a 32-byte key for AES-256.
     * Performance: O(1) - Single hash operation
     *
     * @param string $key APP_KEY
     * @return string 32-byte encryption key
     */
    private function deriveKey(string $key): string
    {
        // Use HKDF to derive a 32-byte key for AES-256
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $key, 32, 'cookie-encryption');
        }

        // Fallback: hash and take first 32 bytes
        return substr(hash('sha256', $key . 'cookie-encryption', true), 0, 32);
    }
}
