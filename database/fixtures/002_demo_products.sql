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
    ('DEMO-010', 'Bouteille eau minerale 1,5 L (x6)', 540)
ON CONFLICT (reference) DO UPDATE
SET name = EXCLUDED.name, price_cents = EXCLUDED.price_cents;

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
WHERE reference LIKE 'DEMO-%'
ON CONFLICT (product_id) DO NOTHING;
