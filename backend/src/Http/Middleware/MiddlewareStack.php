<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class MiddlewareStack
{
    /** @param list<MiddlewareInterface> $middleware */
    public function __construct(private readonly array $middleware)
    {
    }

    /** @param callable(Request): Response $destination */
    public function handle(Request $request, callable $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($this->middleware) as $middleware) {
            $current = $next;
            $next = static fn (Request $request): Response => $middleware->process($request, $current);
        }

        return $next($request);
    }
}
