<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Config;
use App\Controllers\AccessController;
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Database\PdoConnection;
use App\Http\ErrorHandler;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\MiddlewareStack;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Services\HealthService;
use App\Services\AuthService;
use App\Services\JwtService;

$config = Config::fromEnvironment();
(new ErrorHandler($config))->register();

$router = new Router();
$router->get('/health', new HealthController(new HealthService()));
$router->post('/auth/login', static function (Request $request, array $parameters) use ($config): Response {
    $connection = new PdoConnection($config);
    $userRepository = new UserRepository($connection->get());
    $authService = new AuthService($userRepository, new JwtService($config));

    return (new AuthController($authService))($request, $parameters);
});

$jwtService = new JwtService($config);
$authMiddleware = new AuthMiddleware($jwtService);
$authenticated = new MiddlewareStack([$authMiddleware]);
$adminOnly = new MiddlewareStack([$authMiddleware, new AdminMiddleware()]);
$accessController = new AccessController();

$router->get('/me', static function (Request $request, array $parameters) use ($authenticated, $accessController): Response {
    return $authenticated->handle(
        $request,
        static fn (Request $request): Response => $accessController->currentUser($request, $parameters),
    );
});

$router->get('/admin/health', static function (Request $request, array $parameters) use ($adminOnly, $accessController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $accessController->adminHealth($request, $parameters),
    );
});

$request = Request::fromGlobals();
$middleware = new MiddlewareStack([new CorsMiddleware()]);

$response = $middleware->handle(
    $request,
    static fn (Request $request): Response => $router->dispatch($request),
);

$response->send();
