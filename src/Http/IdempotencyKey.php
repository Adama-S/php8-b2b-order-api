<?php

declare(strict_types=1);

namespace App\Http;

final class IdempotencyKey
{
    public static function generate(): string
    {
        return 'idem_' . bin2hex(random_bytes(32));
    }
}
