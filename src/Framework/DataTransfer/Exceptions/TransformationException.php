<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when data transformation fails.
 */
class TransformationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $sourceType = null,
        public readonly ?string $targetType = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create exception for unsupported type.
     */
    public static function unsupportedType(string $type): self
    {
        return new self("Unsupported type for transformation: {$type}", $type);
    }

    /**
     * Create exception for missing transformer.
     */
    public static function noTransformer(string $entityType): self
    {
        return new self("No transformer registered for type: {$entityType}", $entityType);
    }
}
