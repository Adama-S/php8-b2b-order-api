<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class OrderRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, status, total_cents
             FROM orders WHERE idempotency_key = :key FOR UPDATE'
        );
        $stmt->execute(['key' => $key]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $customerId, string $idempotencyKey): ?int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_id, status, idempotency_key)
             VALUES (:customer_id, :status, :idempotency_key)
             ON CONFLICT (idempotency_key) DO NOTHING
             RETURNING id'
        );
        $stmt->execute([
            'customer_id' => $customerId,
            'status' => 'accepted',
            'idempotency_key' => $idempotencyKey,
        ]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function findItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id, quantity
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY product_id'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function findAll(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, status, total_cents, created_at
             FROM orders
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        if ($orders === []) {
            return [];
        }

        $ids = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemsStmt = $this->pdo->prepare(
            "SELECT order_id, product_id, quantity, unit_price_cents
             FROM order_items
             WHERE order_id IN ({$placeholders})
             ORDER BY order_id, product_id"
        );
        $itemsStmt->execute($ids);

        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsByOrder[(int) $item['order_id']][] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price_cents' => (int) $item['unit_price_cents'],
            ];
        }

        foreach ($orders as &$order) {
            $order['id'] = (int) $order['id'];
            $order['customer_id'] = (int) $order['customer_id'];
            $order['total_cents'] = (int) $order['total_cents'];
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);

        return $orders;
    }

    public function findById(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, status, total_cents, created_at
             FROM orders WHERE id = :id'
        );
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->findOrderItems($orderId);
        return $order;
    }

    private function findOrderItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id, quantity, unit_price_cents
             FROM order_items WHERE order_id = :order_id ORDER BY product_id'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function addItem(int $orderId, int $productId, int $quantity, int $unitPriceCents): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price_cents)
             VALUES (:order_id, :product_id, :quantity, :unit_price_cents)'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
        ]);
    }

    public function updateTotal(int $orderId, int $totalCents): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET total_cents = :total_cents, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $orderId, 'total_cents' => $totalCents]);
    }
}
