<?php

declare(strict_types=1);

namespace App\Repositories;

final class ProductRepository extends AbstractRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->execute(
            'SELECT id, name, sku, points_per_unit, active, created_at FROM products ORDER BY id',
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->execute(
            'SELECT id, name, sku, points_per_unit, active, created_at FROM products WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
        $product = $statement->fetch();

        return $product === false ? null : $product;
    }

    /** @param array{name: string, sku: string, points_per_unit: int, active: bool} $data */
    public function create(array $data): array
    {
        $this->execute(
            'INSERT INTO products (name, sku, points_per_unit, active) VALUES (:name, :sku, :points_per_unit, :active)',
            [
                'name' => $data['name'],
                'sku' => $data['sku'],
                'points_per_unit' => $data['points_per_unit'],
                'active' => $data['active'] ? 1 : 0,
            ],
        );

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Created product could not be found.');
    }

    /** @param array{name: string, sku: string, points_per_unit: int, active: bool} $data */
    public function update(int $id, array $data): ?array
    {
        $this->execute(
            'UPDATE products SET name = :name, sku = :sku, points_per_unit = :points_per_unit, active = :active WHERE id = :id',
            [
                'id' => $id,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'points_per_unit' => $data['points_per_unit'],
                'active' => $data['active'] ? 1 : 0,
            ],
        );

        return $this->findById($id);
    }

    public function deactivate(int $id): ?array
    {
        $this->execute('UPDATE products SET active = 0 WHERE id = :id', ['id' => $id]);

        return $this->findById($id);
    }
}
