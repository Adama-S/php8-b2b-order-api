<?php

declare(strict_types=1);

namespace App\Controller;

final class HealthController
{
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
