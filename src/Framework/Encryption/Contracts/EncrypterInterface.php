<?php

declare(strict_types=1);

namespace Toporia\Framework\Encryption\Contracts;

/**
 * Encrypter Interface
 */
interface EncrypterInterface
{
    /**
     * Encrypt the given value.
     *
     * @param mixed $value
     * @param bool $serialize
     * @return string
     */
    public function encrypt(mixed $value, bool $serialize = true): string;

    /**
     * Encrypt a string without serialization.
     *
     * @param string $value
     * @return string
     */
    public function encryptString(string $value): string;

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param bool $unserialize
     * @return mixed
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed;

    /**
     * Decrypt a string without unserialization.
     *
     * @param string $payload
     * @return string
     */
    public function decryptString(string $payload): string;

    /**
     * Get the encryption key.
     *
     * @return string
     */
    public function getKey(): string;
}
