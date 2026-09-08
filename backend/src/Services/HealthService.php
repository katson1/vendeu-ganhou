<?php

declare(strict_types=1);

namespace App\Services;

final class HealthService
{
    /** @return array{status: string, service: string} */
    public function status(): array
    {
        return [
            'status' => 'ok',
            'service' => 'backend',
        ];
    }
}
