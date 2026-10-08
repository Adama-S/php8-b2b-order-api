<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ProductRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAllActive(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.reference, p.name, p.price_cents, p.active,
                    s.quantity AS stock_quantity
             FROM products p
             JOIN stock s ON s.product_id = p.id
             WHERE p.active = TRUE
             ORDER BY p.id
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.reference, p.name, p.price_cents, p.active,
                    s.quantity AS stock_quantity
             FROM products p
             JOIN stock s ON s.product_id = p.id
             WHERE p.id = :id AND p.active = TRUE'
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function findForUpdate(array $productIds): array
    {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.price_cents,
                    CASE WHEN p.active THEN 1 ELSE 0 END AS active_flag,
                    s.quantity AS stock_quantity
             FROM products p
             JOIN stock s ON s.product_id = p.id
             WHERE p.id IN ({$placeholders})
             ORDER BY p.id
             FOR UPDATE OF p, s"
        );
        $stmt->execute($productIds);

        return $stmt->fetchAll();
    }

    public function decrementStock(int $productId, int $quantity): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE stock
             SET quantity = quantity - :quantity, updated_at = NOW()
             WHERE product_id = :product_id AND quantity >= :quantity'
        );
        $stmt->execute(['product_id' => $productId, 'quantity' => $quantity]);

        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Stock changed unexpectedly while locked.');
        }
    }
}
