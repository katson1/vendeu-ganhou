<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly int $statusCode,
        private readonly mixed $payload = null,
        private readonly array $headers = [],
    ) {
    }

    public static function json(mixed $payload, int $statusCode = 200, array $headers = []): self
    {
        return new self($statusCode, $payload, ['Content-Type' => 'application/json; charset=utf-8', ...$headers]);
    }

    /** @param array<string, string> $headers */
    public static function noContent(array $headers = []): self
    {
        return new self(204, null, $headers);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->statusCode, $this->payload, [...$this->headers, ...$headers]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }

        if ($this->statusCode !== 204) {
            echo json_encode($this->payload, JSON_THROW_ON_ERROR);
        }
    }
}
