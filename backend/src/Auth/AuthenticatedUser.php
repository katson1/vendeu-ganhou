<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Exceptions\HttpException;

final class AuthenticatedUser
{
    private function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $email,
        private readonly string $role,
    ) {
    }

    /** @param array<string, mixed> $claims */
    public static function fromClaims(array $claims): self
    {
        $subject = $claims['sub'] ?? null;
        $name = $claims['name'] ?? null;
        $email = $claims['email'] ?? null;
        $role = $claims['role'] ?? null;

        if (
            !is_string($subject)
            || !ctype_digit($subject)
            || (int) $subject < 1
            || !is_string($name)
            || trim($name) === ''
            || !is_string($email)
            || trim($email) === ''
            || !is_string($role)
            || !in_array($role, ['admin', 'seller'], true)
        ) {
            throw new HttpException(401, 'invalid_token', 'Token is invalid or expired.');
        }

        return new self((int) $subject, $name, $email, $role);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /** @return array{id: int, name: string, email: string, role: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
