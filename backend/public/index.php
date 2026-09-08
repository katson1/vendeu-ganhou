<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Config;
use App\Controllers\HealthController;
use App\Http\ErrorHandler;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\MiddlewareStack;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Services\HealthService;

$config = Config::fromEnvironment();
(new ErrorHandler($config))->register();

$router = new Router();
$router->get('/health', new HealthController(new HealthService()));

$request = Request::fromGlobals();
$middleware = new MiddlewareStack([new CorsMiddleware()]);

$response = $middleware->handle(
    $request,
    static fn (Request $request): Response => $router->dispatch($request),
);

$response->send();
