<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\IdempotencyKey;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyTest extends TestCase
{
    public function testGeneratedKeysHaveExpectedFormatAndAreUnique(): void
    {
        $first = IdempotencyKey::generate();
        $second = IdempotencyKey::generate();

        self::assertMatchesRegularExpression('/^idem_[a-f0-9]{64}$/', $first);
        self::assertNotSame($first, $second);
    }
}
