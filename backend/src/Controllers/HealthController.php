<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\HealthService;

final class HealthController
{
    public function __construct(private readonly HealthService $healthService)
    {
    }

    /** @param array<string, string> $parameters */
    public function __invoke(Request $request, array $parameters): Response
    {
        return Response::json($this->healthService->status());
    }
}
