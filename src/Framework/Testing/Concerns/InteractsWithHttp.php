<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

use Toporia\Framework\Testing\TestResponse;

/**
 * HTTP Testing Trait
 *
 * Provides utilities for HTTP request/response testing.
 *
 * Performance:
 * - Fast request simulation
 * - Efficient response assertions
 * - Memory-efficient request building
 */
trait InteractsWithHttp
{
    /**
     * Make a GET request.
     *
     * Performance: O(1) - Request creation
     */
    protected function getRequest(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    /**
     * Make a POST request.
     *
     * Performance: O(1) - Request creation
     */
    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    /**
     * Make a PUT request.
     *
     * Performance: O(1) - Request creation
     */
    protected function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    /**
     * Make a PATCH request.
     *
     * Performance: O(1) - Request creation
     */
    protected function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    /**
     * Make a DELETE request.
     *
     * Performance: O(1) - Request creation
     */
    protected function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    /**
     * Make an HTTP request.
     *
     * Performance: O(1) - Request creation
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        // This would integrate with your routing system
        // For now, return a mock response
        return new TestResponse($method, $uri, $data, $headers);
    }

    /**
     * Assert that the response has a given status code.
     *
     * Performance: O(1) - Direct comparison
     */
    protected function assertStatus(TestResponse $response, int $expected): void
    {
        $this->assertEquals($expected, $response->status(), "Expected status code {$expected}, got {$response->status()}");
    }

    /**
     * Assert that the response is successful (2xx).
     *
     * Performance: O(1) - Range check
     */
    protected function assertSuccessful(TestResponse $response): void
    {
        $status = $response->status();
        $this->assertGreaterThanOrEqual(200, $status, "Expected successful status, got {$status}");
        $this->assertLessThan(300, $status, "Expected successful status, got {$status}");
    }

    /**
     * Assert that the response contains JSON.
     *
     * Performance: O(N) where N = JSON size
     */
    protected function assertJsonResponse(TestResponse $response, array $data = null): void
    {
        $this->assertJsonStructure($response);

        if ($data !== null) {
            $this->assertJsonContains($response, $data);
        }
    }

    /**
     * Assert JSON structure.
     */
    protected function assertJsonStructure(TestResponse $response): void
    {
        $content = $response->content();
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, "Response is not valid JSON");
    }

    /**
     * Assert JSON contains data.
     */
    protected function assertJsonContains(TestResponse $response, array $data): void
    {
        $content = $response->content();
        $decoded = json_decode($content, true);

        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $decoded, "JSON does not contain key: {$key}");
            $this->assertEquals($value, $decoded[$key], "JSON value mismatch for key: {$key}");
        }
    }
}

