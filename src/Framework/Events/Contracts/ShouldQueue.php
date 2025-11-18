<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Contracts;

/**
 * Should Queue Marker Interface
 *
 * Marker interface for listeners that should be queued.
 * Listeners implementing this interface will be dispatched asynchronously.
 *
 * Performance:
 * - Offloads heavy listeners to background processing
 * - Improves response time for HTTP requests
 *
 * Clean Architecture:
 * - Marker pattern for behavior indication
 * - Separation of concerns (sync vs async)
 */
interface ShouldQueue
{
    // Marker interface - no methods required
}

