<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Product
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $name,
        public int $priceCents,
        public bool $active,
    ) {}
}
