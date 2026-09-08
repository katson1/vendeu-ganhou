<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

final class PdoConnection
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function get(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->string('DB_HOST'),
            $this->config->integer('DB_PORT'),
            $this->config->string('DB_DATABASE'),
        );

        $this->connection = new PDO(
            $dsn,
            $this->config->string('DB_USERNAME'),
            $this->config->string('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return $this->connection;
    }
}
