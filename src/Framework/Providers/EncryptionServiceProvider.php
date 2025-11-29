<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Encryption\Encrypter;
use Toporia\Framework\Encryption\Contracts\EncrypterInterface;

/**
 * Encryption Service Provider
 *
 * Registers encryption services into the container.
 * Provides data encryption/decryption functionality across the application.
 *
 * Services Registered:
 * - 'encrypter' => Encrypter instance
 * - EncrypterInterface => Encrypter instance
 *
 * Configuration (config/app.php):
 * ```php
 * 'key' => env('APP_KEY'),
 * 'cipher' => 'aes-256-gcm',
 * 'previous_keys' => [], // For key rotation
 * ```
 *
 * Usage:
 * ```php
 * $encrypter = app('encrypter');
 * $encrypted = $encrypter->encrypt('secret data');
 * $decrypted = $encrypter->decrypt($encrypted);
 * ```
 *
 * @package Toporia\Framework\Providers
 */
final class EncryptionServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register Encrypter as singleton
        $container->singleton('encrypter', function ($c) {
            $config = $this->getConfig($c);

            $key = $config['key'] ?? '';
            $cipher = $config['cipher'] ?? 'aes-256-gcm';

            if (empty($key)) {
                throw new \RuntimeException(
                    'No application encryption key has been specified. ' .
                    'Please set APP_KEY in your .env file.'
                );
            }

            $encrypter = new Encrypter($key, $cipher);

            // Support key rotation
            if (!empty($config['previous_keys'])) {
                $encrypter->previousKeys($config['previous_keys']);
            }

            return $encrypter;
        });

        // Register EncrypterInterface binding
        $container->bind(
            EncrypterInterface::class,
            fn($c) => $c->get('encrypter')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Nothing to boot
    }

    /**
     * Get encryption configuration.
     *
     * @param ContainerInterface $container
     * @return array<string, mixed>
     */
    private function getConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        $config = $container->get('config');

        return [
            'key' => $config->get('app.key', ''),
            'cipher' => $config->get('app.cipher', 'aes-256-gcm'),
            'previous_keys' => $config->get('app.previous_keys', []),
        ];
    }
}
