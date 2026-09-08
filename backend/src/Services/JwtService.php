<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Http\Exceptions\HttpException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    private readonly string $secret;
    private readonly int $ttl;

    public function __construct(Config $config)
    {
        $this->secret = $config->string('JWT_SECRET');
        $this->ttl = $config->integer('JWT_TTL');
    }

    /** @param array{id: int|string, name: string, email: string, role: string} $user */
    public function issue(array $user): string
    {
        $issuedAt = time();

        return JWT::encode([
            'iss' => 'vendeu-ganhou',
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->ttl,
            'sub' => (string) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ], $this->secret, 'HS256');
    }

    /** @return array<string, mixed> */
    public function decode(string $token): array
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable $exception) {
            throw new HttpException(401, 'invalid_token', 'Token is invalid or expired.', $exception);
        }

        $claims = get_object_vars($claims);

        if (
            !isset($claims['sub'], $claims['email'], $claims['role'])
            || !is_string($claims['sub'])
            || trim($claims['sub']) === ''
            || !is_string($claims['email'])
            || !in_array($claims['role'], ['admin', 'seller'], true)
        ) {
            throw new HttpException(401, 'invalid_token', 'Token is invalid or expired.');
        }

        return $claims;
    }
}
