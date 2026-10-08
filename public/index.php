<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Documentation\OpenApi;
use App\Http\ApiException;
use App\Http\IdempotencyKey;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\OrderService;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$startedAt = hrtime(true);

$respondJson = static function (array $payload, int $status = 200) use ($startedAt): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Response-Time-ms: ' . number_format((hrtime(true) - $startedAt) / 1_000_000, 2, '.', ''));
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
};

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    if ($uri === '/health') {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }
        $respondJson(['status' => 'ok']);
    }

    if ($uri === '/openapi.json') {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }
        $respondJson(OpenApi::specification());
    }

    if ($uri === '/idempotency-key') {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }
        $respondJson(['idempotency_key' => IdempotencyKey::generate()]);
    }

    if ($uri === '/docs') {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }
        $documentation = file_get_contents(__DIR__ . '/docs.html');
        if ($documentation === false) {
            throw new RuntimeException('API documentation could not be loaded.');
        }
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Response-Time-ms: ' . number_format((hrtime(true) - $startedAt) / 1_000_000, 2, '.', ''));
        echo $documentation;
        exit;
    }

    if ($uri === '/') {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }
        $home = file_get_contents(__DIR__ . '/home.html');
        if ($home === false) {
            throw new RuntimeException('API home page could not be loaded.');
        }
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Response-Time-ms: ' . number_format((hrtime(true) - $startedAt) / 1_000_000, 2, '.', ''));
        echo $home;
        exit;
    }

    if ($uri === '/products' || preg_match('#^/products/([0-9]+)$#', $uri, $matches) === 1) {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }

        if ($uri === '/products') {
            $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $perPage = filter_var($_GET['per_page'] ?? '20', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($page === false || $perPage === false || $page > intdiv(PHP_INT_MAX, $perPage)) {
                throw new ApiException(422, 'invalid_pagination', 'page and per_page must be positive integers; per_page cannot exceed 100.');
            }

            $products = new ProductRepository(Connection::createFromEnvironment());
            $rows = $products->findAllActive($perPage + 1, ($page - 1) * $perPage);
            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                array_pop($rows);
            }
            $rows = array_map(static fn (array $product): array => [
                'id' => (int) $product['id'],
                'reference' => $product['reference'],
                'name' => $product['name'],
                'price_cents' => (int) $product['price_cents'],
                'stock_quantity' => (int) $product['stock_quantity'],
            ], $rows);
            $respondJson([
                'data' => $rows,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
            ]);
        }

        $products = new ProductRepository(Connection::createFromEnvironment());
        $product = $products->findById((int) $matches[1]);
        if ($product === null) {
            throw new ApiException(404, 'product_not_found', 'Product not found.');
        }
        $respondJson(['data' => [
            'id' => (int) $product['id'],
            'reference' => $product['reference'],
            'name' => $product['name'],
            'price_cents' => (int) $product['price_cents'],
            'stock_quantity' => (int) $product['stock_quantity'],
        ]]);
    }

    if ($uri === '/orders') {
        if ($method === 'GET') {
            $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $perPage = filter_var($_GET['per_page'] ?? '20', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($page === false || $perPage === false || $page > intdiv(PHP_INT_MAX, $perPage)) {
                throw new ApiException(422, 'invalid_pagination', 'page and per_page must be positive integers; per_page cannot exceed 100.');
            }

            $orders = new OrderRepository(Connection::createFromEnvironment());
            $rows = $orders->findAll($perPage + 1, ($page - 1) * $perPage);
            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                array_pop($rows);
            }
            $respondJson([
                'data' => $rows,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
            ]);
        }
        if ($method !== 'POST') {
            header('Allow: GET, POST');
            throw new ApiException(405, 'method_not_allowed', 'Only GET and POST are allowed for this resource.');
        }

        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($contentType !== 'application/json') {
            throw new ApiException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
        }
        $idempotencyKey = trim($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');
        if ($idempotencyKey === '') {
            throw new ApiException(400, 'idempotency_key_required', 'The Idempotency-Key header is required.');
        }

        try {
            $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
        if (
            !is_array($payload)
            || !isset($payload['customer_id'])
            || !is_int($payload['customer_id'])
            || !isset($payload['items'])
            || !is_array($payload['items'])
        ) {
            throw new ApiException(422, 'invalid_order', 'customer_id and items are required with valid types.');
        }

        $pdo = Connection::createFromEnvironment();
        $orders = new OrderRepository($pdo);
        $service = new OrderService($pdo, new ProductRepository($pdo), $orders);
        $orderId = $service->createOrder($payload['customer_id'], $payload['items'], $idempotencyKey);
        $order = $orders->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Created order could not be loaded.');
        }

        $order['id'] = (int) $order['id'];
        $order['customer_id'] = (int) $order['customer_id'];
        $order['total_cents'] = (int) $order['total_cents'];
        $order['items'] = array_map(static fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
            'unit_price_cents' => (int) $item['unit_price_cents'],
        ], $order['items']);
        $respondJson(['data' => $order], 201);
    }

    if (preg_match('#^/orders/([0-9]+)$#', $uri, $matches) === 1) {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed for this resource.');
        }

        $orders = new OrderRepository(Connection::createFromEnvironment());
        $order = $orders->findById((int) $matches[1]);
        if ($order === null) {
            throw new ApiException(404, 'order_not_found', 'Order not found.');
        }

        $order['id'] = (int) $order['id'];
        $order['customer_id'] = (int) $order['customer_id'];
        $order['total_cents'] = (int) $order['total_cents'];
        $order['items'] = array_map(static fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
            'unit_price_cents' => (int) $item['unit_price_cents'],
        ], $order['items']);
        $respondJson(['data' => $order]);
    }

    throw new ApiException(404, 'not_found', 'Route not found.');
} catch (ApiException $exception) {
    $respondJson([
        'error' => $exception->errorCode,
        'message' => $exception->getMessage(),
    ], $exception->status);
} catch (\PDOException $exception) {
    error_log('Database error: ' . $exception->getMessage());
    $respondJson([
        'error' => 'database_unavailable',
        'message' => 'The database is temporarily unavailable.',
    ], 503);
} catch (\Throwable $exception) {
    error_log('Unhandled API error: ' . $exception->getMessage());
    $respondJson([
        'error' => 'internal_error',
        'message' => 'An unexpected error occurred.',
    ], 500);
}
