<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use App\Application\Services\IssueCsrfCookieService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\{Request, Response, CookieJar};

/**
 * CSRF Cookie Controller
 *
 * Handles CSRF cookie issuance for SPA authentication.
 * Issues CSRF cookie for SPA authentication.
 *
 * Clean Architecture:
 * - Presentation layer (outermost)
 * - Delegates to Application Service
 * - Handles HTTP concerns only
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles HTTP for CSRF cookie
 * - Dependency Inversion: Depends on Application Service abstraction
 */
final class CsrfCookieController extends BaseController
{
    public function __construct(
        protected Request $request,
        protected Response $response,
        private readonly IssueCsrfCookieService $issueCsrfCookieService,
        private readonly CookieJar $cookieJar
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Issue CSRF cookie for SPA.
     *
     * GET /api/csrf-cookie
     *
     * This endpoint generates a CSRF token and sets it as a cookie.
     * SPA should call this endpoint before making authenticated requests.
     *
     * @return void
     */
    public function __invoke(): void
    {
        $this->issueCsrfCookieService->execute();

        // Send queued cookies (CSRF cookie)
        $this->cookieJar->sendQueued();

        // Return 204 No Content
        // No body needed - cookie is set via Set-Cookie header
        $this->response->setStatus(204);
    }
}
