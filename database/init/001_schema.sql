CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    reference VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE stock (
    product_id BIGINT PRIMARY KEY REFERENCES products(id),
    quantity INTEGER NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    status VARCHAR(30) NOT NULL,
    total_cents INTEGER NOT NULL DEFAULT 0 CHECK (total_cents >= 0),
    idempotency_key VARCHAR(255) UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price_cents INTEGER NOT NULL CHECK (unit_price_cents >= 0)
);

CREATE INDEX idx_products_active ON products(active);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);

INSERT INTO products (reference, name, price_cents) VALUES
    ('DEMO-001', 'Cafe en grains 1 kg', 1890),
    ('DEMO-002', 'The noir en vrac 500 g', 1240),
    ('DEMO-003', 'Sirop de vanille 1 L', 850),
    ('DEMO-004', 'Gobelets carton 250 ml (x100)', 720),
    ('DEMO-005', 'Couvercles pour gobelets (x100)', 390),
    ('DEMO-006', 'Serviettes papier (x200)', 460),
    ('DEMO-007', 'Chocolat noir patissier 2 kg', 2760),
    ('DEMO-008', 'Farine de ble T55 5 kg', 650),
    ('DEMO-009', 'Huile d olive vierge 1 L', 1120),
    ('DEMO-010', 'Bouteille eau minerale 1,5 L (x6)', 540);

INSERT INTO stock (product_id, quantity)
SELECT id, CASE reference
    WHEN 'DEMO-001' THEN 50
    WHEN 'DEMO-002' THEN 35
    WHEN 'DEMO-003' THEN 24
    WHEN 'DEMO-004' THEN 100
    WHEN 'DEMO-005' THEN 80
    WHEN 'DEMO-006' THEN 60
    WHEN 'DEMO-007' THEN 12
    WHEN 'DEMO-008' THEN 40
    WHEN 'DEMO-009' THEN 30
    WHEN 'DEMO-010' THEN 75
END
FROM products
WHERE reference LIKE 'DEMO-%';
