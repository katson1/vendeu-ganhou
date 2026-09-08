<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /** @param array<string, string> $parameters */
    public function __invoke(Request $request, array $parameters): Response
    {
        $payload = $request->json();
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            throw new HttpException(422, 'validation_error', 'Email and password are required.');
        }

        return Response::json([
            'token' => $this->authService->login(strtolower(trim($email)), $password),
        ]);
    }
}
