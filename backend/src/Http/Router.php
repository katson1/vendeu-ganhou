<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $parameters = $this->match($route['pattern'], $request->path());

            if ($parameters === null) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method()) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            return ($route['handler'])($request, $parameters);
        }

        if ($pathMatched) {
            return Response::json(
                ['error' => 'method_not_allowed', 'message' => 'HTTP method is not allowed for this path.'],
                405,
                ['Allow' => implode(', ', array_unique($allowedMethods))],
            );
        }

        return Response::json(['error' => 'not_found', 'message' => 'Route not found.'], 404);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => rtrim($pattern, '/') ?: '/',
            'handler' => $handler,
        ];
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $patternSegments = $this->segments($pattern);
        $pathSegments = $this->segments($path);

        if (count($patternSegments) !== count($pathSegments)) {
            return null;
        }

        $parameters = [];

        foreach ($patternSegments as $index => $patternSegment) {
            $pathSegment = $pathSegments[$index];

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $patternSegment, $matches) === 1) {
                $parameters[$matches[1]] = rawurldecode($pathSegment);
                continue;
            }

            if ($patternSegment !== $pathSegment) {
                return null;
            }
        }

        return $parameters;
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}
