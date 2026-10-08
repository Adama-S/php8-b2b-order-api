<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Documentation\OpenApi;
use PHPUnit\Framework\TestCase;

final class OpenApiTest extends TestCase
{
    public function testSpecificationDocumentsCoreApiAndIdempotency(): void
    {
        $specification = OpenApi::specification();

        self::assertSame('3.1.0', $specification['openapi']);
        self::assertSame('List active products', $specification['paths']['/products']['get']['summary']);
        self::assertSame('Create an order and reserve stock', $specification['paths']['/orders']['post']['summary']);
        self::assertArrayHasKey('/products', $specification['paths']);
        self::assertArrayHasKey('/products/{id}', $specification['paths']);
        self::assertArrayHasKey('/idempotency-key', $specification['paths']);
        self::assertArrayHasKey('get', $specification['paths']['/orders']);
        self::assertArrayHasKey('/orders/{id}', $specification['paths']);
        self::assertSame(
            'Idempotency-Key',
            $specification['paths']['/orders']['post']['parameters'][0]['name']
        );
        self::assertArrayHasKey('Order', $specification['components']['schemas']);
    }
}
