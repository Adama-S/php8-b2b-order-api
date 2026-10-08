<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\HealthController;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testHealthContract(): void
    {
        self::assertSame(['status' => 'ok'], (new HealthController())());
    }
}
