<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Config;
use App\Controllers\AccessController;
use App\Controllers\CampaignController;
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\WalletController;
use App\Database\PdoConnection;
use App\Http\ErrorHandler;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\MiddlewareStack;
use App\Http\Middleware\SellerMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Repositories\ProductRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\SaleRepository;
use App\Repositories\WalletEntryRepository;
use App\Services\HealthService;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\ProductService;
use App\Services\CampaignService;
use App\Services\SaleService;
use App\Services\WalletService;

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
$sellerOnly = new MiddlewareStack([$authMiddleware, new SellerMiddleware()]);
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

$resolveProductController = static function () use ($config): ProductController {
    static $controller = null;

    if (!$controller instanceof ProductController) {
        $connection = new PdoConnection($config);
        $service = new ProductService(new ProductRepository($connection->get()));
        $controller = new ProductController($service);
    }

    return $controller;
};

$router->get('/products', static function (Request $request, array $parameters) use ($adminOnly, $resolveProductController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveProductController()->index($request, $parameters),
    );
});

$router->post('/products', static function (Request $request, array $parameters) use ($adminOnly, $resolveProductController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveProductController()->store($request, $parameters),
    );
});

$router->put('/products/{id}', static function (Request $request, array $parameters) use ($adminOnly, $resolveProductController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveProductController()->update($request, $parameters),
    );
});

$router->delete('/products/{id}', static function (Request $request, array $parameters) use ($adminOnly, $resolveProductController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveProductController()->destroy($request, $parameters),
    );
});

$resolveCampaignController = static function () use ($config): CampaignController {
    static $controller = null;

    if (!$controller instanceof CampaignController) {
        $connection = new PdoConnection($config);
        $service = new CampaignService(new CampaignRepository($connection->get()));
        $controller = new CampaignController($service);
    }

    return $controller;
};

$router->get('/campaigns', static function (Request $request, array $parameters) use ($adminOnly, $resolveCampaignController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveCampaignController()->index($request, $parameters),
    );
});

$router->post('/campaigns', static function (Request $request, array $parameters) use ($adminOnly, $resolveCampaignController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveCampaignController()->store($request, $parameters),
    );
});

$resolveSaleController = static function () use ($config): SaleController {
    static $controller = null;

    if (!$controller instanceof SaleController) {
        $connection = new PdoConnection($config);
        $pdo = $connection->get();
        $service = new SaleService(
            $pdo,
            new SaleRepository($pdo),
            new UserRepository($pdo),
            new ProductRepository($pdo),
            new CampaignRepository($pdo),
            new WalletEntryRepository($pdo),
        );
        $controller = new SaleController($service);
    }

    return $controller;
};

$router->post('/sales', static function (Request $request, array $parameters) use ($adminOnly, $resolveSaleController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveSaleController()->store($request, $parameters),
    );
});

$router->post('/sales/{external_id}/cancel', static function (Request $request, array $parameters) use ($adminOnly, $resolveSaleController): Response {
    return $adminOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveSaleController()->cancel($request, $parameters),
    );
});

$resolveWalletController = static function () use ($config): WalletController {
    static $controller = null;

    if (!$controller instanceof WalletController) {
        $connection = new PdoConnection($config);
        $pdo = $connection->get();
        $controller = new WalletController(new WalletService(new WalletEntryRepository($pdo)));
    }

    return $controller;
};

$router->get('/me/wallet', static function (Request $request, array $parameters) use ($sellerOnly, $resolveWalletController): Response {
    return $sellerOnly->handle(
        $request,
        static fn (Request $request): Response => $resolveWalletController()->show($request, $parameters),
    );
});

$request = Request::fromGlobals();
$middleware = new MiddlewareStack([new CorsMiddleware()]);

$response = $middleware->handle(
    $request,
    static fn (Request $request): Response => $router->dispatch($request),
);

$response->send();
