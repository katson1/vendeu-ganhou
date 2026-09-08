<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\CampaignRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletEntryRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class SaleService
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly PDO $pdo,
        private readonly SaleRepository $saleRepository,
        private readonly UserRepository $userRepository,
        private readonly ProductRepository $productRepository,
        private readonly CampaignRepository $campaignRepository,
        private readonly WalletEntryRepository $walletEntryRepository,
    ) {
    }

    /** @param array<string, mixed> $payload */
    /** @return array{sale: array<string, mixed>, idempotent: bool} */
    public function create(array $payload): array
    {
        $data = $this->validate($payload);
        $this->pdo->beginTransaction();

        try {
            $seller = $this->userRepository->findById($data['seller_id']);

            if ($seller === null) {
                throw new HttpException(404, 'seller_not_found', 'Seller not found.');
            }

            if (($seller['role'] ?? null) !== 'seller') {
                throw new HttpException(422, 'invalid_seller', 'seller_id must reference a seller.');
            }

            $product = $this->productRepository->findById($data['product_id']);

            if ($product === null) {
                throw new HttpException(404, 'product_not_found', 'Product not found.');
            }

            if ((int) ($product['active'] ?? 0) !== 1) {
                throw new HttpException(422, 'product_inactive', 'Product is inactive.');
            }

            $campaign = $this->campaignRepository->findByIdForUpdate($data['campaign_id']);

            if ($campaign === null) {
                throw new HttpException(404, 'campaign_not_found', 'Campaign not found.');
            }

            $this->validateCampaignAvailability($campaign);

            $existing = $this->saleRepository->findByExternalId($data['external_id']);

            if ($existing !== null) {
                $this->assertSameSale($existing, $data);
                $this->pdo->commit();

                return [
                    'sale' => $this->present($existing, $this->calculatePoints($existing['quantity'], (int) $product['points_per_unit'])),
                    'idempotent' => true,
                ];
            }

            $points = $this->calculatePoints($data['quantity'], (int) $product['points_per_unit']);
            $budgetTotal = (int) $campaign['budget_total'];
            $budgetUsed = (int) $campaign['budget_used'];

            if ($points > $budgetTotal - $budgetUsed) {
                throw new HttpException(422, 'insufficient_budget', 'Campaign budget is insufficient for this sale.');
            }

            try {
                $sale = $this->saleRepository->create($data);
            } catch (PDOException $exception) {
                if (!SaleRepository::isDuplicateExternalId($exception)) {
                    throw $exception;
                }

                $existing = $this->saleRepository->findByExternalId($data['external_id']);

                if ($existing === null) {
                    throw $exception;
                }

                $this->assertSameSale($existing, $data);
                $this->pdo->commit();

                return [
                    'sale' => $this->present($existing, $this->calculatePoints($existing['quantity'], (int) $product['points_per_unit'])),
                    'idempotent' => true,
                ];
            }

            $this->walletEntryRepository->createCredit([
                'seller_id' => $data['seller_id'],
                'campaign_id' => $data['campaign_id'],
                'sale_id' => (int) $sale['id'],
                'points' => $points,
                'description' => sprintf('Sale %s approved', $data['external_id']),
            ]);

            if (!$this->campaignRepository->incrementBudget($data['campaign_id'], $points)) {
                throw new \RuntimeException('Campaign budget could not be updated.');
            }

            $this->pdo->commit();

            return [
                'sale' => $this->present($sale, $points),
                'idempotent' => false,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    /** @return array{external_id: string, campaign_id: int, seller_id: int, product_id: int, quantity: int, unit_value: string} */
    private function validate(array $payload): array
    {
        $allowedFields = ['external_id', 'campaign_id', 'seller_id', 'product_id', 'quantity', 'unit_value'];
        $unknownFields = array_diff(array_keys($payload), $allowedFields);

        if ($unknownFields !== []) {
            throw new HttpException(422, 'validation_error', sprintf('Unknown field "%s".', (string) reset($unknownFields)));
        }

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(422, 'validation_error', sprintf('Field "%s" is required.', $field));
            }
        }

        $externalId = $payload['external_id'];

        if (!is_string($externalId) || trim($externalId) === '' || strlen(trim($externalId)) > 191) {
            throw new HttpException(422, 'validation_error', 'external_id must be a non-empty string with at most 191 characters.');
        }

        $campaignId = $this->positiveInteger($payload['campaign_id'], 'campaign_id');
        $sellerId = $this->positiveInteger($payload['seller_id'], 'seller_id');
        $productId = $this->positiveInteger($payload['product_id'], 'product_id');
        $quantity = $this->positiveInteger($payload['quantity'], 'quantity');

        return [
            'external_id' => trim($externalId),
            'campaign_id' => $campaignId,
            'seller_id' => $sellerId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_value' => $this->normalizeUnitValue($payload['unit_value']),
        ];
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (!is_int($value) || $value < 1) {
            throw new HttpException(422, 'validation_error', sprintf('%s must be a positive integer.', $field));
        }

        return $value;
    }

    private function normalizeUnitValue(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value . '.00';
        } elseif (is_float($value)) {
            if (!is_finite($value) || $value <= 0) {
                throw new HttpException(422, 'validation_error', 'unit_value must be a positive monetary value.');
            }

            $value = number_format($value, 2, '.', '');
        } elseif (is_string($value)) {
            $value = trim($value);

            if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
                throw new HttpException(422, 'validation_error', 'unit_value must be a positive monetary value with up to two decimal places.');
            }
        } else {
            throw new HttpException(422, 'validation_error', 'unit_value must be a positive monetary value.');
        }

        [$integerPart, $decimalPart] = array_pad(explode('.', $value, 2), 2, '0');

        if ((int) $integerPart === 0 && (int) $decimalPart === 0) {
            throw new HttpException(422, 'validation_error', 'unit_value must be greater than zero.');
        }

        return $integerPart . '.' . str_pad(substr($decimalPart, 0, 2), 2, '0');
    }

    /** @param array<string, mixed> $campaign */
    private function validateCampaignAvailability(array $campaign): void
    {
        if (($campaign['status'] ?? null) !== 'active') {
            throw new HttpException(422, 'campaign_not_active', 'Campaign is not active.');
        }

        $startsAt = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, (string) $campaign['starts_at']);
        $endsAt = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, (string) $campaign['ends_at']);
        $now = new DateTimeImmutable('now');

        if ($startsAt === false || $endsAt === false || $now < $startsAt || $now > $endsAt) {
            throw new HttpException(422, 'campaign_out_of_period', 'Campaign is outside its active period.');
        }
    }

    /** @param array<string, mixed> $existing */
    /** @param array{external_id: string, campaign_id: int, seller_id: int, product_id: int, quantity: int, unit_value: string} $data */
    private function assertSameSale(array $existing, array $data): void
    {
        $same = (string) $existing['external_id'] === $data['external_id']
            && (int) $existing['campaign_id'] === $data['campaign_id']
            && (int) $existing['seller_id'] === $data['seller_id']
            && (int) $existing['product_id'] === $data['product_id']
            && (int) $existing['quantity'] === $data['quantity']
            && (string) $existing['unit_value'] === $data['unit_value'];

        if (!$same) {
            throw new HttpException(409, 'external_id_conflict', 'external_id is already associated with a different sale.');
        }
    }

    private function calculatePoints(mixed $quantity, int $pointsPerUnit): int
    {
        $quantity = (int) $quantity;

        if ($pointsPerUnit < 1 || $quantity < 1 || $quantity > intdiv(PHP_INT_MAX, $pointsPerUnit)) {
            throw new HttpException(422, 'validation_error', 'The calculated points exceed the supported positive integer range.');
        }

        return $quantity * $pointsPerUnit;
    }

    /** @return array<string, mixed> */
    private function present(array $sale, int $points): array
    {
        return [
            'id' => (int) $sale['id'],
            'external_id' => (string) $sale['external_id'],
            'campaign_id' => (int) $sale['campaign_id'],
            'seller_id' => (int) $sale['seller_id'],
            'product_id' => (int) $sale['product_id'],
            'quantity' => (int) $sale['quantity'],
            'unit_value' => (string) $sale['unit_value'],
            'points' => $points,
            'status' => (string) $sale['status'],
            'created_at' => (string) $sale['created_at'],
        ];
    }
}
