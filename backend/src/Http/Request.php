<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Exceptions\HttpException;
use JsonException;

final class Request
{
    /** @param array<string, string> $headers */
    /** @param array<string, mixed> $query */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers,
        private readonly array $query,
        private readonly string $body,
        /** @var array<string, mixed> */
        private array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? $headers : [];
        $headers = array_change_key_case($headers, CASE_LOWER);

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] ??= $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] ??= $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $headers,
            $_GET,
            file_get_contents('php://input') ?: '',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        try {
            $payload = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HttpException(400, 'invalid_json', 'Request body must be valid JSON.', $exception);
        }

        if (!is_array($payload)) {
            throw new HttpException(400, 'invalid_json', 'Request JSON must be an object or array.');
        }

        return $payload;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
}
