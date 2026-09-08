<?php

declare(strict_types=1);

namespace App\Repositories;

final class WalletEntryRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findBySaleAndTypeForUpdate(int $saleId, string $type): ?array
    {
        $statement = $this->execute(
            'SELECT id, seller_id, campaign_id, sale_id, type, points, description, created_at FROM wallet_entries WHERE sale_id = :sale_id AND type = :type LIMIT 1 FOR UPDATE',
            [
                'sale_id' => $saleId,
                'type' => $type,
            ],
        );
        $entry = $statement->fetch();

        return $entry === false ? null : $entry;
    }

    /** @param array{seller_id: int, campaign_id: int, sale_id: int, points: int, description: string} $data */
    public function createCredit(array $data): void
    {
        $this->execute(
            'INSERT INTO wallet_entries (seller_id, campaign_id, sale_id, type, points, description) VALUES (:seller_id, :campaign_id, :sale_id, :type, :points, :description)',
            [
                'seller_id' => $data['seller_id'],
                'campaign_id' => $data['campaign_id'],
                'sale_id' => $data['sale_id'],
                'type' => 'credit',
                'points' => $data['points'],
                'description' => $data['description'],
            ],
        );
    }

    /** @param array{seller_id: int, campaign_id: int, sale_id: int, points: int, description: string} $data */
    public function createDebit(array $data): void
    {
        $this->execute(
            'INSERT INTO wallet_entries (seller_id, campaign_id, sale_id, type, points, description) VALUES (:seller_id, :campaign_id, :sale_id, :type, :points, :description)',
            [
                'seller_id' => $data['seller_id'],
                'campaign_id' => $data['campaign_id'],
                'sale_id' => $data['sale_id'],
                'type' => 'debit',
                'points' => $data['points'],
                'description' => $data['description'],
            ],
        );
    }
}
