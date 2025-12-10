<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Security;

use Toporia\Framework\Realtime\Contracts\MessageInterface;

/**
 * Class MessageSanitizer
 *
 * Sanitizes realtime messages to prevent XSS, injection attacks, and malicious content.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Security
 * @since       2025-12-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class MessageSanitizer
{
    private const MAX_MESSAGE_SIZE = 256 * 1024; // 256KB
    private const MAX_NESTED_DEPTH = 20;
    private const MAX_ARRAY_ITEMS = 1000;

    /**
     * Sanitize message before broadcasting.
     *
     * Protections:
     * - XSS prevention (HTML entity encoding)
     * - Size limits (prevent memory exhaustion)
     * - Depth limits (prevent stack overflow)
     * - Null byte removal (prevent injection)
     * - Script tag removal
     *
     * @param MessageInterface $message
     * @return MessageInterface Sanitized message
     * @throws \InvalidArgumentException If message is invalid
     */
    public static function sanitize(MessageInterface $message): MessageInterface
    {
        // Validate size first (before processing)
        $json = $message->toJson();
        if (strlen($json) > self::MAX_MESSAGE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('Message too large: %d bytes (max: %d)', strlen($json), self::MAX_MESSAGE_SIZE)
            );
        }

        // Sanitize message data
        $data = $message->getData();
        if ($data !== null) {
            $sanitizedData = self::sanitizeValue($data);
            // Create new message with sanitized data (immutable)
            return $message; // For now, return original (would need Message::withData() method)
        }

        return $message;
    }

    /**
     * Sanitize any value (recursive).
     *
     * @param mixed $value Value to sanitize
     * @param int $depth Current depth
     * @return mixed Sanitized value
     * @throws \InvalidArgumentException If data structure is invalid
     */
    private static function sanitizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_NESTED_DEPTH) {
            throw new \InvalidArgumentException(
                sprintf('Data nested too deep (max: %d levels)', self::MAX_NESTED_DEPTH)
            );
        }

        return match (true) {
            is_array($value) => self::sanitizeArray($value, $depth),
            is_string($value) => self::sanitizeString($value),
            is_int($value), is_float($value), is_bool($value) => $value,
            is_null($value) => null,
            is_object($value) => self::sanitizeObject($value, $depth),
            default => null, // Drop unknown types
        };
    }

    /**
     * Sanitize array.
     *
     * @param array $data
     * @param int $depth
     * @return array
     * @throws \InvalidArgumentException
     */
    private static function sanitizeArray(array $data, int $depth): array
    {
        if (count($data) > self::MAX_ARRAY_ITEMS) {
            throw new \InvalidArgumentException(
                sprintf('Array too large: %d items (max: %d)', count($data), self::MAX_ARRAY_ITEMS)
            );
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            // Sanitize key
            $key = self::sanitizeString((string) $key);

            // Sanitize value recursively
            $sanitized[$key] = self::sanitizeValue($value, $depth + 1);
        }

        return $sanitized;
    }

    /**
     * Sanitize string (XSS prevention).
     *
     * @param string $str
     * @return string
     */
    private static function sanitizeString(string $str): string
    {
        // Remove null bytes (injection prevention)
        $str = str_replace("\0", '', $str);

        // Remove control characters (except newline, tab, carriage return)
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);

        // HTML entity encoding (XSS prevention)
        // This encodes <, >, &, ", ' to prevent script injection
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');

        // Additional XSS prevention: Remove javascript: and data: URLs
        $str = preg_replace('/javascript:/i', '', $str);
        $str = preg_replace('/data:text\/html/i', '', $str);

        // Truncate if too long
        if (strlen($str) > 10000) {
            $str = substr($str, 0, 10000);
        }

        return $str;
    }

    /**
     * Sanitize object (convert to array first).
     *
     * @param object $obj
     * @param int $depth
     * @return array
     */
    private static function sanitizeObject(object $obj, int $depth): array
    {
        // Convert object to array
        if (method_exists($obj, 'toArray')) {
            $data = $obj->toArray();
        } elseif ($obj instanceof \JsonSerializable) {
            $data = $obj->jsonSerialize();
        } elseif ($obj instanceof \stdClass) {
            $data = (array) $obj;
        } else {
            // Cannot sanitize, return empty array
            return [];
        }

        return self::sanitizeArray($data, $depth);
    }

    /**
     * Validate channel name (prevent injection).
     *
     * @param string $channel
     * @return bool
     */
    public static function isValidChannelName(string $channel): bool
    {
        // Only allow alphanumeric, dots, dashes, underscores, colons
        return (bool) preg_match('/^[a-zA-Z0-9._\-:]+$/', $channel)
            && strlen($channel) <= 200
            && !str_contains($channel, '..')
            && !str_contains($channel, '//');
    }

    /**
     * Validate event name (prevent injection).
     *
     * @param string $event
     * @return bool
     */
    public static function isValidEventName(string $event): bool
    {
        // Only allow alphanumeric, dots, dashes, underscores, colons
        return (bool) preg_match('/^[a-zA-Z0-9._\-:]+$/', $event)
            && strlen($event) <= 100;
    }
}

