-- Vendeu, Ganhou - deterministic local seed.
-- Passwords are bcrypt hashes. Plaintext credentials are documented for local use
-- in the project setup documentation in a later stage.

INSERT INTO users (name, email, password_hash, role)
VALUES
    ('Administrador', 'admin@vendeu.local', '$2y$10$JtiWNBJtjj5i2x9HANxvPO8BOBZCYE2O72gVpph9UcgdmLQFeeXri', 'admin'),
    ('Vendedor 1', 'seller1@vendeu.local', '$2y$10$iQP5h5ps49djUBsIUmG28.p18XjPHTKhII/uUbN3Djk2Sv6BvpqYu', 'seller'),
    ('Vendedor 2', 'seller2@vendeu.local', '$2y$10$iQP5h5ps49djUBsIUmG28.p18XjPHTKhII/uUbN3Djk2Sv6BvpqYu', 'seller')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    role = VALUES(role);

INSERT INTO products (name, sku, points_per_unit, active)
VALUES
    ('Produto A', 'PROD-A', 10, TRUE),
    ('Produto B', 'PROD-B', 25, TRUE),
    ('Produto C', 'PROD-C', 50, TRUE)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    points_per_unit = VALUES(points_per_unit);

INSERT INTO campaigns (name, budget_total, starts_at, ends_at, status)
SELECT 'Campanha de Lançamento', 10000, '2026-01-01 00:00:00', '2030-12-31 23:59:59', 'active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE name = 'Campanha de Lançamento'
);
