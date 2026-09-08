<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\SaleService;

final class SaleController
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    /** @param array<string, string> $parameters */
    public function store(Request $request, array $parameters): Response
    {
        $result = $this->saleService->create($request->json());

        return Response::json(
            ['sale' => $result['sale']],
            $result['idempotent'] ? 200 : 201,
        );
    }

    /** @param array<string, string> $parameters */
    public function cancel(Request $request, array $parameters): Response
    {
        return Response::json([
            'sale' => $this->saleService->cancel($parameters['external_id'] ?? ''),
        ]);
    }
}
