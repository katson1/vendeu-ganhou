<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

abstract class AbstractRepository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /** @param array<string|int, mixed> $parameters */
    protected function execute(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement;
    }
}
