<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\ProductRepository;
use PDOException;

final class ProductService
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->productRepository->all();
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): array
    {
        $data = $this->validateCreate($payload);

        try {
            return $this->productRepository->create($data);
        } catch (PDOException $exception) {
            $this->throwConflictForDuplicateSku($exception);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload): array
    {
        $current = $this->findOrFail($id);

        if ($payload === []) {
            throw new HttpException(422, 'validation_error', 'At least one product field is required.');
        }

        $this->rejectUnknownFields($payload);
        $data = $this->validateProduct([
            'name' => $payload['name'] ?? $current['name'],
            'sku' => $payload['sku'] ?? $current['sku'],
            'points_per_unit' => $payload['points_per_unit'] ?? (int) $current['points_per_unit'],
            'active' => $payload['active'] ?? (bool) $current['active'],
        ]);

        try {
            return $this->productRepository->update($id, $data) ?? throw new \RuntimeException('Updated product could not be found.');
        } catch (PDOException $exception) {
            $this->throwConflictForDuplicateSku($exception);
            throw $exception;
        }
    }

    public function deactivate(int $id): array
    {
        $this->findOrFail($id);

        return $this->productRepository->deactivate($id) ?? throw new \RuntimeException('Deactivated product could not be found.');
    }

    /** @param array<string, mixed> $payload */
    private function validateCreate(array $payload): array
    {
        $this->rejectUnknownFields($payload);

        foreach (['name', 'sku', 'points_per_unit'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(422, 'validation_error', sprintf('Field "%s" is required.', $field));
            }
        }

        return $this->validateProduct([
            'name' => $payload['name'],
            'sku' => $payload['sku'],
            'points_per_unit' => $payload['points_per_unit'],
            'active' => $payload['active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function validateProduct(array $payload): array
    {
        if (!is_string($payload['name']) || trim($payload['name']) === '' || strlen(trim($payload['name'])) > 160) {
            throw new HttpException(422, 'validation_error', 'Name must be a non-empty string with at most 160 characters.');
        }

        if (!is_string($payload['sku']) || trim($payload['sku']) === '' || strlen(trim($payload['sku'])) > 64) {
            throw new HttpException(422, 'validation_error', 'SKU must be a non-empty string with at most 64 characters.');
        }

        if (!is_int($payload['points_per_unit']) || $payload['points_per_unit'] < 1) {
            throw new HttpException(422, 'validation_error', 'points_per_unit must be a positive integer.');
        }

        if (!is_bool($payload['active'])) {
            throw new HttpException(422, 'validation_error', 'Active must be a boolean.');
        }

        return [
            'name' => trim($payload['name']),
            'sku' => trim($payload['sku']),
            'points_per_unit' => $payload['points_per_unit'],
            'active' => $payload['active'],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function rejectUnknownFields(array $payload): void
    {
        $unknownFields = array_diff(array_keys($payload), ['name', 'sku', 'points_per_unit', 'active']);

        if ($unknownFields !== []) {
            throw new HttpException(422, 'validation_error', sprintf('Unknown field "%s".', (string) reset($unknownFields)));
        }
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        if ($id < 1) {
            throw new HttpException(422, 'validation_error', 'Product id must be a positive integer.');
        }

        return $this->productRepository->findById($id)
            ?? throw new HttpException(404, 'product_not_found', 'Product not found.');
    }

    private function throwConflictForDuplicateSku(PDOException $exception): void
    {
        if (($exception->errorInfo[1] ?? null) === 1062) {
            throw new HttpException(409, 'duplicate_sku', 'SKU is already in use.', $exception);
        }
    }
}
