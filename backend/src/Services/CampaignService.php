<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\CampaignRepository;
use DateTimeImmutable;

final class CampaignService
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly CampaignRepository $campaignRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map(
            fn (array $campaign): array => $this->present($campaign),
            $this->campaignRepository->all(),
        );
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): array
    {
        $data = $this->validateCreate($payload);

        return $this->present($this->campaignRepository->create($data));
    }

    /** @param array<string, mixed> $payload */
    private function validateCreate(array $payload): array
    {
        $this->rejectUnknownFields($payload);

        foreach (['name', 'budget_total', 'starts_at', 'ends_at'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(422, 'validation_error', sprintf('Field "%s" is required.', $field));
            }
        }

        $startsAt = $this->parseDate($payload['starts_at'], 'starts_at');
        $endsAt = $this->parseDate($payload['ends_at'], 'ends_at');

        if ($startsAt >= $endsAt) {
            throw new HttpException(422, 'validation_error', 'starts_at must be before ends_at.');
        }

        $status = $payload['status'] ?? 'active';

        if (!is_string($status) || !in_array($status, ['active', 'closed'], true)) {
            throw new HttpException(422, 'validation_error', 'Status must be active or closed.');
        }

        if (!is_string($payload['name']) || trim($payload['name']) === '' || strlen(trim($payload['name'])) > 160) {
            throw new HttpException(422, 'validation_error', 'Name must be a non-empty string with at most 160 characters.');
        }

        if (!is_int($payload['budget_total']) || $payload['budget_total'] < 1) {
            throw new HttpException(422, 'validation_error', 'budget_total must be a positive integer.');
        }

        return [
            'name' => trim($payload['name']),
            'budget_total' => $payload['budget_total'],
            'starts_at' => $startsAt->format(self::DATE_FORMAT),
            'ends_at' => $endsAt->format(self::DATE_FORMAT),
            'status' => $status,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function rejectUnknownFields(array $payload): void
    {
        $unknownFields = array_diff(array_keys($payload), ['name', 'budget_total', 'starts_at', 'ends_at', 'status']);

        if ($unknownFields !== []) {
            throw new HttpException(422, 'validation_error', sprintf('Unknown field "%s".', (string) reset($unknownFields)));
        }
    }

    private function parseDate(mixed $value, string $field): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a valid datetime.', $field));
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format(self::DATE_FORMAT) !== $value
        ) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must use format Y-m-d H:i:s.', $field));
        }

        return $date;
    }

    /** @return array<string, mixed> */
    private function present(array $campaign): array
    {
        $budgetTotal = (int) $campaign['budget_total'];
        $budgetUsed = (int) $campaign['budget_used'];

        return [
            'id' => (int) $campaign['id'],
            'name' => (string) $campaign['name'],
            'budget_total' => $budgetTotal,
            'budget_used' => $budgetUsed,
            'budget_remaining' => $budgetTotal - $budgetUsed,
            'starts_at' => (string) $campaign['starts_at'],
            'ends_at' => (string) $campaign['ends_at'],
            'status' => (string) $campaign['status'],
            'created_at' => (string) $campaign['created_at'],
        ];
    }
}
