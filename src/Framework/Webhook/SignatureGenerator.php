<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook;

use Toporia\Framework\Webhook\Contracts\SignatureGeneratorInterface;

/**
 * Webhook Signature Generator
 *
 * Generates and verifies webhook signatures using HMAC.
 * Supports multiple algorithms (SHA256, SHA1, etc.)
 *
 * Performance:
 * - O(1) signature generation
 * - O(1) signature verification
 * - Constant time comparison (prevents timing attacks)
 */
final class SignatureGenerator implements SignatureGeneratorInterface
{
    /**
     * @param string $algorithm Hash algorithm (sha256, sha1, sha512)
     */
    public function __construct(
        private string $algorithm = 'sha256'
    ) {
        if (!in_array($this->algorithm, hash_algos(), true)) {
            throw new \InvalidArgumentException("Unsupported hash algorithm: {$this->algorithm}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $payload, string $secret): string
    {
        $payloadString = $this->serializePayload($payload);
        $signature = hash_hmac($this->algorithm, $payloadString, $secret);

        return $signature;
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $signature, array $payload, string $secret): bool
    {
        $expected = $this->generate($payload, $secret);

        // Use constant-time comparison to prevent timing attacks
        return hash_equals($expected, $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Serialize payload to string for hashing.
     *
     * @param array<string, mixed> $payload
     * @return string Serialized payload
     * @throws \RuntimeException If payload cannot be serialized
     */
    private function serializePayload(array $payload): string
    {
        // Sort keys for consistent hashing
        ksort($payload);

        // Convert to JSON string (canonical form)
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to serialize webhook payload: ' . json_last_error_msg());
        }

        return $json;
    }
}

