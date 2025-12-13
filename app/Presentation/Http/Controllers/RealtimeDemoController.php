<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * RealtimeDemoController
 *
 * Controller for the realtime notification demo page.
 * Demonstrates Socket.IO + Redis Pub/Sub integration.
 */
final class RealtimeDemoController
{
    /**
     * Show the realtime demo page.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $content = view('realtime-demo', [
            'wsHost' => env('REALTIME_SOCKETIO_HOST', '127.0.0.1'),
            'wsPort' => env('REALTIME_SOCKETIO_PORT', 3000),
        ]);

        return response($content)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
