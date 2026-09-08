<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Config
{
    /** @param array<string, string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromEnvironment(): self
    {
        return new self([
            'APP_ENV' => self::environment('APP_ENV', 'local'),
            'DB_HOST' => self::environment('DB_HOST', 'database'),
            'DB_PORT' => self::environment('DB_PORT', '3306'),
            'DB_DATABASE' => self::environment('DB_DATABASE', 'vendeu_ganhou'),
            'DB_USERNAME' => self::environment('DB_USERNAME', 'app'),
            'DB_PASSWORD' => self::environment('DB_PASSWORD', 'app_local_password'),
        ]);
    }

    public function string(string $key): string
    {
        if (!array_key_exists($key, $this->values)) {
            throw new RuntimeException(sprintf('Configuration key "%s" is not defined.', $key));
        }

        return $this->values[$key];
    }

    public function integer(string $key): int
    {
        $value = filter_var($this->string($key), FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            throw new RuntimeException(sprintf('Configuration key "%s" must be a positive integer.', $key));
        }

        return $value;
    }

    private static function environment(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
