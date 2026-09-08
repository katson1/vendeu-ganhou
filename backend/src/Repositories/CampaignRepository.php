<?php

declare(strict_types=1);

namespace App\Repositories;

final class CampaignRepository extends AbstractRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->execute(
            'SELECT id, name, budget_total, budget_used, starts_at, ends_at, status, created_at FROM campaigns ORDER BY id',
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->execute(
            'SELECT id, name, budget_total, budget_used, starts_at, ends_at, status, created_at FROM campaigns WHERE id = :id LIMIT 1',
            ['id' => $id],
        );
        $campaign = $statement->fetch();

        return $campaign === false ? null : $campaign;
    }

    /** @param array{name: string, budget_total: int, starts_at: string, ends_at: string, status: string} $data */
    public function create(array $data): array
    {
        $this->execute(
            'INSERT INTO campaigns (name, budget_total, starts_at, ends_at, status) VALUES (:name, :budget_total, :starts_at, :ends_at, :status)',
            [
                'name' => $data['name'],
                'budget_total' => $data['budget_total'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'],
            ],
        );

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Created campaign could not be found.');
    }
}
