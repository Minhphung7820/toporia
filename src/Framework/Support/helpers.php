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

if (!function_exists('response')) {
    /**
     * Get the response factory instance or create a response.
     *
     * @param mixed $content Response content
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @return \Toporia\Framework\Http\Contracts\ResponseFactoryInterface|\Toporia\Framework\Http\Contracts\ResponseInterface
     */
    function response(mixed $content = null, int $status = 200, array $headers = []): mixed
    {
        $factory = app()->make(\Toporia\Framework\Http\Contracts\ResponseFactoryInterface::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('json_response')) {
    /**
     * Create a JSON response.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @return \Toporia\Framework\Http\Contracts\JsonResponseInterface
     */
    function json_response(mixed $data = null, int $status = 200, array $headers = []): \Toporia\Framework\Http\Contracts\JsonResponseInterface
    {
        return response()->json($data, $status, $headers);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response.
     *
     * @param string $to Target URL
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @return \Toporia\Framework\Http\Contracts\RedirectResponseInterface
     */
    function redirect(string $to, int $status = 302, array $headers = []): \Toporia\Framework\Http\Contracts\RedirectResponseInterface
    {
        return response()->redirectTo($to, $status, $headers);
    }
}

if (!function_exists('request')) {
    /**
     * Get the current request instance.
     *
     * @return \Toporia\Framework\Http\Request
     */
    function request(): \Toporia\Framework\Http\Request
    {
        return app()->make(\Toporia\Framework\Http\Request::class);
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with the given status code.
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @param array<string, string> $headers Response headers
     * @return never
     * @throws \Exception
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        $message = $message ?: "HTTP {$code} Error";

        // Send error response
        response()->json([
            'error' => $message,
            'status' => $code
        ], $code, $headers)->send();

        exit($code);
    }
}