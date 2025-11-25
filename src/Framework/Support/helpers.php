<?php

declare(strict_types=1);

if (!function_exists('reflection')) {
    /**
     * Get ReflectionService instance from container.
     *
     * This is a helper function to make reflection access more convenient
     * while still following the container-managed pattern.
     *
     * @return \Toporia\Framework\Support\ReflectionService
     */
    function reflection(): \Toporia\Framework\Support\ReflectionService
    {
        return app()->make(\Toporia\Framework\Support\ReflectionService::class);
    }
}