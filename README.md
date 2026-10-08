# PHP 8 B2B Order API

A lightweight B2B order management REST API built with native PHP 8 and PostgreSQL. It is designed for business integrations operating over unreliable networks, with idempotent order creation, protected stock reservations, and a locally queued retry flow in the interactive documentation.

## Features

- Native PHP 8.3, without a framework
- PostgreSQL persistence
- Docker Compose setup
- Transactional stock reservation with row locking
- Idempotent order creation with `Idempotency-Key`
- Handmade OpenAPI 3.1 specification and interactive API documentation
- Local browser queue for pending orders when the network is unavailable
- PHPUnit unit and PostgreSQL integration tests

## Getting started

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec api composer install
```

- Home page: http://localhost:8080/
- Interactive documentation: http://localhost:8080/docs
- OpenAPI specification: http://localhost:8080/openapi.json
- Health check: http://localhost:8080/health
- Adminer: http://localhost:8081

Adminer connection:

- System: PostgreSQL
- Server: `db`
- Username: `order_api`
- Password: `order_api`
- Database: `order_api`

The database is seeded with ten demo products on its first initialization. To populate an existing database without resetting current stock, run the idempotent fixture:

```bash
docker compose exec -T db psql -v ON_ERROR_STOP=1 -U order_api -d order_api < database/fixtures/002_demo_products.sql
```

## API endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/health` | Check API availability without connecting to the database |
| `GET` | `/idempotency-key` | Generate a cryptographically random idempotency key |
| `GET` | `/products?page=1&per_page=20` | List active products with pagination |
| `GET` | `/products/{id}` | Get an active product and its available stock |
| `GET` | `/orders?page=1&per_page=20` | List recent orders with pagination |
| `GET` | `/orders/{id}` | Get an order and its item price snapshots |
| `POST` | `/orders` | Create an order and reserve stock atomically |

Prices are returned in cents. Pagination defaults to `page=1` and `per_page=20`, with a maximum `per_page` of 100.

## Create an order

Send JSON and provide a stable `Idempotency-Key` for each new logical order:

```http
POST /orders
Content-Type: application/json
Idempotency-Key: customer-42-order-2026-0001

{"customer_id":42,"items":[{"product_id":1,"quantity":2}]}
```

Replaying the same key with the same customer and normalized items returns the existing order without decrementing stock again. Reusing a key with different order content returns `409`. Stock validation, stock updates, the order, and its item price snapshots are committed in one database transaction.

Errors use the JSON shape `{"error":"code","message":"..."}`. Responses include an `X-Response-Time-ms` header.

## Interactive documentation and offline orders

The `/docs` page provides a **Try it out** form for each endpoint without third-party browser libraries. Use **Generate** to request an idempotency key from PHP; if the API is unreachable, the browser can generate a random key locally.

Before sending a new order, the page saves it in `localStorage`. If connectivity is lost, pending requests are retried automatically when the browser comes back online, using the same idempotency key. Functional errors remain visible in the queue and are not retried continuously. The local queue is specific to that browser and device.

## Tests

```bash
docker compose exec api composer test
```

Integration tests use the PostgreSQL `db` service and clean up their test records.

## Architecture

```text
Client / Browser
       |
       v
Apache + native PHP 8
       |
       +--> HTTP router
       |       |
       |       v
       |   Order service
       |       |
       |       v
       |   Repositories
       v       v
          PostgreSQL
```
