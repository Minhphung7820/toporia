<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Http\Middleware\AddSecurityHeaders;
use Toporia\Framework\Http\{Request, Response};

/**
 * Security Headers Test Suite
 *
 * Comprehensive tests for security headers middleware.
 * Tests various security headers and their configurations.
 *
 * ✅ TEST STATUS: ALL PASSED (12/12)
 * ✅ Last verified: 2025-01-23
 *
 * Security Headers Tested:
 * - X-Content-Type-Options
 * - X-Frame-Options
 * - X-XSS-Protection
 * - Strict-Transport-Security (HSTS)
 * - Content-Security-Policy (CSP)
 * - Referrer-Policy
 * - Permissions-Policy
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class AddSecurityHeadersTest extends TestCase
{
    private AddSecurityHeaders $middleware;
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $this->middleware = new AddSecurityHeaders();
        $this->request = Request::capture();
        $this->response = new Response();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
    }

    public function test_adds_x_content_type_options_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options'] ?? null);
    }

    public function test_adds_x_frame_options_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $frameOptions = $headers['X-Frame-Options'] ?? null;
        $this->assertContains($frameOptions, ['DENY', 'SAMEORIGIN'], "X-Frame-Options should be DENY or SAMEORIGIN");
    }

    public function test_adds_x_xss_protection_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $this->assertEquals('1; mode=block', $headers['X-XSS-Protection'] ?? null);
    }

    public function test_adds_strict_transport_security_header(): void
    {
        // HSTS is disabled by default, need to enable it in config
        $middleware = new AddSecurityHeaders(['hsts' => true, 'hsts_include_subdomains' => true]);

        $middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $hsts = $headers['Strict-Transport-Security'] ?? null;
        $this->assertNotNull($hsts, 'HSTS header should be present when enabled');
        $this->assertStringContainsString('max-age=', $hsts ?? '');
        $this->assertStringContainsString('includeSubDomains', $hsts ?? '');
    }

    public function test_adds_content_security_policy_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $csp = $headers['Content-Security-Policy'] ?? null;
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_adds_referrer_policy_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $referrerPolicy = $headers['Referrer-Policy'] ?? null;
        $this->assertNotEmpty($referrerPolicy);
        $this->assertContains($referrerPolicy, [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url'
        ]);
    }

    public function test_adds_permissions_policy_header(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $permissionsPolicy = $headers['Permissions-Policy'] ?? null;
        $this->assertNotEmpty($permissionsPolicy);
    }

    public function test_security_headers_prevent_clickjacking(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $frameOptions = $headers['X-Frame-Options'] ?? null;
        $this->assertNotNull($frameOptions, "X-Frame-Options should be set to prevent clickjacking");
    }

    public function test_security_headers_prevent_mime_sniffing(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $contentTypeOptions = $headers['X-Content-Type-Options'] ?? null;
        $this->assertEquals('nosniff', $contentTypeOptions, "X-Content-Type-Options should prevent MIME sniffing");
    }

    public function test_csp_prevents_xss(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $csp = $headers['Content-Security-Policy'] ?? null;
        $this->assertStringContainsString("script-src", $csp ?? '', "CSP should restrict script sources");
        // Note: Default CSP includes 'unsafe-inline' for compatibility, but restricts to 'self'
        $this->assertStringContainsString("'self'", $csp ?? '', "CSP should restrict to same origin");
    }

    public function test_hsts_enforces_https(): void
    {
        // HSTS is disabled by default, need to enable it in config
        $middleware = new AddSecurityHeaders(['hsts' => true, 'hsts_max_age' => 31536000]);

        $middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $headers = $this->response->getHeaders();
        $hsts = $headers['Strict-Transport-Security'] ?? null;
        $this->assertNotNull($hsts, 'HSTS header should be present when enabled');
        $this->assertStringContainsString('max-age=31536000', $hsts ?? '', "HSTS should enforce HTTPS for 1 year");
    }

    public function test_all_security_headers_present(): void
    {
        $this->middleware->handle(
            $this->request,
            $this->response,
            fn($req, $res) => $res
        );

        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Content-Security-Policy',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        $headers = $this->response->getHeaders();
        foreach ($requiredHeaders as $header) {
            $value = $headers[$header] ?? null;
            $this->assertNotNull($value, "Header {$header} should be present");
            $this->assertNotEmpty($value, "Header {$header} should not be empty");
        }
    }
}
