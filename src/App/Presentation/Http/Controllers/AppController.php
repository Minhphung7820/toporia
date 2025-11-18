<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

/**
 * SPA Application Controller
 *
 * Serves the Vue.js Single Page Application.
 * All routes except API routes will be handled by Vue Router.
 */
final class AppController extends BaseController
{
    /**
     * Serve the SPA application.
     *
     * This method returns the main app view which contains the Vue.js application.
     * Vue Router will handle client-side routing.
     *
     * @return string
     */
    public function index(): string
    {
        return $this->view('app');
    }
}
