<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ProductService;

final class ProductController
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        return Response::json(['products' => $this->productService->list()]);
    }

    /** @param array<string, string> $parameters */
    public function store(Request $request, array $parameters): Response
    {
        return Response::json(['product' => $this->productService->create($request->json())], 201);
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        return Response::json([
            'product' => $this->productService->update($this->productId($parameters), $request->json()),
        ]);
    }

    /** @param array<string, string> $parameters */
    public function destroy(Request $request, array $parameters): Response
    {
        return Response::json([
            'product' => $this->productService->deactivate($this->productId($parameters)),
        ]);
    }

    /** @param array<string, string> $parameters */
    private function productId(array $parameters): int
    {
        $id = $parameters['id'] ?? null;

        return is_string($id) && ctype_digit($id) ? (int) $id : 0;
    }
}
