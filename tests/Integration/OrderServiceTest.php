<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\Connection;
use App\Http\ApiException;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\OrderService;
use PDO;
use PHPUnit\Framework\TestCase;

final class OrderServiceTest extends TestCase
{
    private PDO $pdo;
    private int $productId;
    private OrderService $service;
    private OrderRepository $orders;
    private array $keys = [];

    protected function setUp(): void
    {
        $this->pdo = Connection::createFromEnvironment();
        $reference = 'TEST-' . bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (reference, name, price_cents)
             VALUES (:reference, :name, :price_cents) RETURNING id'
        );
        $stmt->execute(['reference' => $reference, 'name' => 'Integration test', 'price_cents' => 125]);
        $this->productId = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO stock (product_id, quantity) VALUES (:product_id, 10)');
        $stmt->execute(['product_id' => $this->productId]);

        $this->orders = new OrderRepository($this->pdo);
        $this->service = new OrderService(
            $this->pdo,
            new ProductRepository($this->pdo),
            $this->orders
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            if ($this->keys !== []) {
                $stmt = $this->pdo->prepare('DELETE FROM orders WHERE idempotency_key = :key');
                foreach ($this->keys as $key) {
                    $stmt->execute(['key' => $key]);
                }
            }
            if (isset($this->productId)) {
                $stmt = $this->pdo->prepare('DELETE FROM stock WHERE product_id = :id');
                $stmt->execute(['id' => $this->productId]);
                $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
                $stmt->execute(['id' => $this->productId]);
            }
        }
    }

    public function testIdempotentCreationLocksStockAndRollsBackOnFailure(): void
    {
        $key = 'test-' . bin2hex(random_bytes(12));
        $this->keys[] = $key;
        $orderId = $this->service->createOrder(77, [
            ['product_id' => $this->productId, 'quantity' => 2],
            ['product_id' => $this->productId, 'quantity' => 3],
        ], $key);

        self::assertSame(5, $this->stockQuantity());
        self::assertSame(
            $orderId,
            $this->service->createOrder(77, [
                ['product_id' => $this->productId, 'quantity' => 5],
            ], $key)
        );
        self::assertSame(5, $this->stockQuantity());
        self::assertSame(625, (int) $this->orders->findById($orderId)['total_cents']);
        self::assertSame($orderId, $this->orders->findAll(10, 0)[0]['id']);

        try {
            $this->service->createOrder(77, [
                ['product_id' => $this->productId, 'quantity' => 1],
            ], $key);
            self::fail('Reusing an idempotency key with a different order must fail.');
        } catch (ApiException $exception) {
            self::assertSame(409, $exception->status);
            self::assertSame('idempotency_conflict', $exception->errorCode);
        }

        $failedKey = 'test-' . bin2hex(random_bytes(12));
        $this->keys[] = $failedKey;
        try {
            $this->service->createOrder(77, [
                ['product_id' => $this->productId, 'quantity' => 11],
            ], $failedKey);
            self::fail('Insufficient stock must reject the order.');
        } catch (ApiException $exception) {
            self::assertSame(409, $exception->status);
            self::assertSame('insufficient_stock', $exception->errorCode);
        }

        self::assertSame(5, $this->stockQuantity());
        self::assertNull($this->orders->findByIdempotencyKey($failedKey));
    }

    private function stockQuantity(): int
    {
        $stmt = $this->pdo->prepare('SELECT quantity FROM stock WHERE product_id = :id');
        $stmt->execute(['id' => $this->productId]);

        return (int) $stmt->fetchColumn();
    }
}
