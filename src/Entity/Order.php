<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Order
{
    public function __construct(
        public int $id,
        public int $customerId,
        public string $status,
        public int $totalCents,
        public ?string $idempotencyKey,
    ) {}
}
