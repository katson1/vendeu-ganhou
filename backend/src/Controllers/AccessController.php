<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;

final class AccessController
{
    /** @param array<string, string> $parameters */
    public function currentUser(Request $request, array $parameters): Response
    {
        $user = $this->authenticatedUser($request);

        return Response::json(['user' => $user->toArray()]);
    }

    /** @param array<string, string> $parameters */
    public function adminHealth(Request $request, array $parameters): Response
    {
        $this->authenticatedUser($request);

        return Response::json([
            'status' => 'ok',
            'scope' => 'admin',
        ]);
    }

    private function authenticatedUser(Request $request): AuthenticatedUser
    {
        $user = $request->attribute('current_user');

        if (!$user instanceof AuthenticatedUser) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        return $user;
    }
}
