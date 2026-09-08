<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WalletEntryRepository;

final class WalletService
{
    public function __construct(private readonly WalletEntryRepository $walletEntryRepository)
    {
    }

    /** @return array{balance: int, entries: list<array<string, mixed>>} */
    public function forSeller(int $sellerId): array
    {
        $entries = array_map(
            static fn (array $entry): array => [
                'id' => (int) $entry['id'],
                'campaign_id' => (int) $entry['campaign_id'],
                'sale_id' => (int) $entry['sale_id'],
                'type' => (string) $entry['type'],
                'points' => (int) $entry['points'],
                'description' => (string) $entry['description'],
                'created_at' => (string) $entry['created_at'],
            ],
            $this->walletEntryRepository->listForSeller($sellerId),
        );

        return [
            'balance' => $this->walletEntryRepository->balanceForSeller($sellerId),
            'entries' => $entries,
        ];
    }
}
