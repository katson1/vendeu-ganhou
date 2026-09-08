<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($method === 'GET' && $path === '/health') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'service' => 'backend',
    ], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(404);
echo json_encode([
    'error' => 'not_found',
], JSON_THROW_ON_ERROR);
