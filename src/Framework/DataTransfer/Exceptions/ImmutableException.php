<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Exceptions;

use RuntimeException;

/**
 * Exception thrown when attempting to modify an immutable object.
 */
class ImmutableException extends RuntimeException
{
}
