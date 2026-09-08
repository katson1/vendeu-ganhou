<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CampaignService;

final class CampaignController
{
    public function __construct(private readonly CampaignService $campaignService)
    {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        return Response::json(['campaigns' => $this->campaignService->list()]);
    }

    /** @param array<string, string> $parameters */
    public function store(Request $request, array $parameters): Response
    {
        return Response::json(['campaign' => $this->campaignService->create($request->json())], 201);
    }
}
