<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;

/**
 * Translation Facade
 *
 * Provides static access to translation service.
 *
 * Usage:
 * - Trans::get('messages.welcome', [':name' => 'John'])
 * - Trans::trans('messages.welcome')
 * - Trans::choice('messages.apples', 5)
 * - Trans::getLocale()
 * - Trans::setLocale('vi')
 *
 * Performance:
 * - O(1) instance lookup (cached after first call)
 * - Direct method forwarding (no overhead)
 *
 * Clean Architecture:
 * - Presentation layer convenience (Facade pattern)
 * - Delegates to TranslatorInterface in Framework layer
 */
final class Trans extends ServiceAccessor
{
    /**
     * {@inheritdoc}
     */
    protected static function getServiceName(): string
    {
        return 'translation';
    }
}
