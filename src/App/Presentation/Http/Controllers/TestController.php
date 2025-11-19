<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Request;

final class TestController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): void
    {
        $this->json([
            'message' => 'Index method',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): void
    {
        $this->json([
            'message' => 'Show method',
            'id' => $id,
        ]);
    }
}
