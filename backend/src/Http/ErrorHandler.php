<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\Config;
use App\Http\Exceptions\HttpException;
use ErrorException;
use Throwable;

final class ErrorHandler
{
    public function __construct(private readonly Config $config)
    {
    }

    public function register(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $exception): void {
            $this->render($exception);
        });
    }

    private function render(Throwable $exception): void
    {
        error_log(sprintf('%s: %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        $isHttpException = $exception instanceof HttpException;
        $statusCode = $isHttpException ? $exception->statusCode() : 500;
        $errorCode = $isHttpException ? $exception->errorCode() : 'internal_server_error';
        $message = $isHttpException ? $exception->getMessage() : 'An unexpected error occurred.';

        Response::json(['error' => $errorCode, 'message' => $message], $statusCode)->send();
    }
}
