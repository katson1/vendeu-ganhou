<?php

declare(strict_types=1);

namespace App\Repositories;

use PDOException;

final class SaleRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findByExternalId(string $externalId): ?array
    {
        $statement = $this->execute(
            'SELECT id, external_id, campaign_id, seller_id, product_id, quantity, unit_value, status, created_at FROM sales WHERE external_id = :external_id LIMIT 1',
            ['external_id' => $externalId],
        );
        $sale = $statement->fetch();

        return $sale === false ? null : $sale;
    }

    /** @param array{external_id: string, campaign_id: int, seller_id: int, product_id: int, quantity: int, unit_value: string} $data */
    public function create(array $data): array
    {
        $this->execute(
            'INSERT INTO sales (external_id, campaign_id, seller_id, product_id, quantity, unit_value, status) VALUES (:external_id, :campaign_id, :seller_id, :product_id, :quantity, :unit_value, :status)',
            [
                'external_id' => $data['external_id'],
                'campaign_id' => $data['campaign_id'],
                'seller_id' => $data['seller_id'],
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'unit_value' => $data['unit_value'],
                'status' => 'approved',
            ],
        );

        $sale = $this->findByExternalId($data['external_id']);

        if ($sale === null) {
            throw new \RuntimeException('Created sale could not be found.');
        }

        return $sale;
    }

    public static function isDuplicateExternalId(PDOException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062;
    }
}
