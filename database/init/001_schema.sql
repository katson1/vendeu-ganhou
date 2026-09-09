-- Initial schema for Vendeu, Ganhou.
-- MySQL runs this file when the database volume is created.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'seller') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    sku VARCHAR(64) NOT NULL,
    points_per_unit INT UNSIGNED NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_active (active),
    CONSTRAINT chk_products_points_per_unit CHECK (points_per_unit > 0)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    budget_total BIGINT UNSIGNED NOT NULL,
    budget_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_campaigns_status_dates (status, starts_at, ends_at),
    CONSTRAINT chk_campaigns_budget_total CHECK (budget_total > 0),
    CONSTRAINT chk_campaigns_budget_used CHECK (budget_used <= budget_total),
    CONSTRAINT chk_campaigns_dates CHECK (starts_at < ends_at)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_id VARCHAR(191) NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_value DECIMAL(12, 2) NOT NULL,
    status ENUM('approved', 'canceled') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_external_id (external_id),
    KEY idx_sales_campaign_status (campaign_id, status),
    KEY idx_sales_seller_created (seller_id, created_at),
    KEY idx_sales_product (product_id),
    CONSTRAINT fk_sales_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_seller
        FOREIGN KEY (seller_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_sales_quantity CHECK (quantity > 0),
    CONSTRAINT chk_sales_unit_value CHECK (unit_value > 0)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    points BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_entries_sale_type (sale_id, type),
    KEY idx_wallet_entries_seller_created (seller_id, created_at),
    KEY idx_wallet_entries_campaign (campaign_id),
    CONSTRAINT fk_wallet_entries_seller
        FOREIGN KEY (seller_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_wallet_entries_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_wallet_entries_sale
        FOREIGN KEY (sale_id) REFERENCES sales (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_wallet_entries_points CHECK (points > 0)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
