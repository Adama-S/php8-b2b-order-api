<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Http\ApiException;
use PDO;
use Throwable;

final class OrderService
{
    public function __construct(
        private PDO $pdo,
        private ProductRepository $products,
        private OrderRepository $orders,
    ) {}

    public function createOrder(int $customerId, array $items, ?string $idempotencyKey): int
    {
        if ($customerId < 1 || $items === [] || $idempotencyKey === null || $idempotencyKey === '') {
            throw new ApiException(422, 'invalid_order', 'Customer, items and idempotency key are required.');
        }

        $normalizedItems = [];
        foreach ($items as $item) {
            if (
                !is_array($item)
                || !isset($item['product_id'], $item['quantity'])
                || filter_var($item['product_id'], FILTER_VALIDATE_INT) === false
                || filter_var($item['quantity'], FILTER_VALIDATE_INT) === false
                || (int) $item['product_id'] < 1
                || (int) $item['quantity'] < 1
                || (int) $item['quantity'] > 2_147_483_647
            ) {
                throw new ApiException(422, 'invalid_order_item', 'Each item needs a positive product_id and quantity.');
            }

            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            if (($normalizedItems[$productId] ?? 0) > 2_147_483_647 - $quantity) {
                throw new ApiException(422, 'invalid_order_item', 'The requested quantity is too large.');
            }
            $normalizedItems[$productId] = ($normalizedItems[$productId] ?? 0) + $quantity;
        }
        ksort($normalizedItems, SORT_NUMERIC);

        if (strlen($idempotencyKey) > 255) {
            throw new ApiException(422, 'invalid_idempotency_key', 'Idempotency-Key must not exceed 255 characters.');
        }

        $this->pdo->beginTransaction();
        try {
            $orderId = $this->orders->create($customerId, $idempotencyKey);
            if ($orderId === null) {
                $existing = $this->orders->findByIdempotencyKey($idempotencyKey);
                if ($existing === null) {
                    throw new \RuntimeException('Conflicting idempotent order was not found.');
                }

                $existingItems = [];
                foreach ($this->orders->findItems((int) $existing['id']) as $item) {
                    $existingItems[(int) $item['product_id']] = (int) $item['quantity'];
                }
                ksort($existingItems, SORT_NUMERIC);

                if ((int) $existing['customer_id'] !== $customerId || $existingItems !== $normalizedItems) {
                    throw new ApiException(
                        409,
                        'idempotency_conflict',
                        'This Idempotency-Key has already been used for a different order.'
                    );
                }

                $this->pdo->commit();
                return (int) $existing['id'];
            }

            $products = [];
            foreach ($this->products->findForUpdate(array_keys($normalizedItems)) as $product) {
                $products[(int) $product['id']] = $product;
            }
            foreach ($normalizedItems as $productId => $quantity) {
                if (!isset($products[$productId])) {
                    throw new ApiException(404, 'product_not_found', "Product {$productId} was not found.");
                }
                if ((int) $products[$productId]['active_flag'] !== 1) {
                    throw new ApiException(422, 'product_unavailable', "Product {$productId} is not available.");
                }
                if ((int) $products[$productId]['stock_quantity'] < $quantity) {
                    throw new ApiException(409, 'insufficient_stock', "Insufficient stock for product {$productId}.");
                }
            }

            $totalCents = 0;
            foreach ($normalizedItems as $productId => $quantity) {
                $unitPriceCents = (int) $products[$productId]['price_cents'];
                if (
                    $totalCents > 2_147_483_647
                    || ($unitPriceCents > 0 && $quantity > intdiv(2_147_483_647 - $totalCents, $unitPriceCents))
                ) {
                    throw new ApiException(422, 'order_total_too_large', 'The order total is too large.');
                }
                $totalCents += $unitPriceCents * $quantity;
                $this->products->decrementStock($productId, $quantity);
                $this->orders->addItem($orderId, $productId, $quantity, $unitPriceCents);
            }
            $this->orders->updateTotal($orderId, $totalCents);
            $this->pdo->commit();

            return $orderId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
