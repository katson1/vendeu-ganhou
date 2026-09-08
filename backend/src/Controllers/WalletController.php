<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\WalletService;

final class WalletController
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $user = $request->attribute('current_user');

        if (!$user instanceof AuthenticatedUser) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        return Response::json($this->walletService->forSeller($user->id()));
    }
}
