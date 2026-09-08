<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\JwtService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->header('authorization');

        if ($authorization === null || preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $request->setAttribute('auth', $this->jwtService->decode($matches[1]));

        return $next($request);
    }
}
