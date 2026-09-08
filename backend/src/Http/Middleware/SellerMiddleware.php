<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;

final class SellerMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('current_user');

        if (!$user instanceof AuthenticatedUser) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        if (!$user->isSeller()) {
            throw new HttpException(403, 'forbidden', 'Seller access is required.');
        }

        return $next($request);
    }
}
