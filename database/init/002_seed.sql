-- Initial local data for Vendeu, Ganhou.
-- Passwords are bcrypt hashes. Plaintext credentials are documented in README.md
-- for local development only.

INSERT INTO users (name, email, password_hash, role)
VALUES
    ('Administrador', 'admin@vendeu.local', '$2y$10$4hy1oO4NimFv1aYY1q2v4uiUW0TpNs9.JXv5n/pNOGLPD6i12IBqu', 'admin'),
    ('Vendedor 1', 'seller1@vendeu.local', '$2y$10$Cm6L7l.N2NyvMA.4nyDJ2OD3d9gXGNy3EmtET7lo912P4rLA0aJrS', 'seller'),
    ('Vendedor 2', 'seller2@vendeu.local', '$2y$10$Cm6L7l.N2NyvMA.4nyDJ2OD3d9gXGNy3EmtET7lo912P4rLA0aJrS', 'seller')
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
