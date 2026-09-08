<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JwtService $jwtService,
    ) {
    }

    public function login(string $email, string $password): string
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException(401, 'invalid_credentials', 'Email or password is invalid.');
        }

        return $this->jwtService->issue([
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ]);
    }
}
