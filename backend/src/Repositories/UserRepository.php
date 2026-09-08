<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->execute(
            'SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->execute(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1',
            ['email' => $email],
        );
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }
}
