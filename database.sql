-- =====================================================================
-- SmartPOS - Product & Billing Management System
-- Import via phpMyAdmin (Import tab). Safe to re-import: it DROPS and
-- recreates the three tables (all existing data in them is lost).
-- Requires MySQL 8.0.16+ (CHECK constraints are enforced).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smartpos_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smartpos_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS products;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    brand_name     VARCHAR(100) NOT NULL,
    cost_price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sell_price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_name_brand (name, brand_name),
    KEY idx_products_name (name),
    KEY idx_products_brand (brand_name),
    KEY idx_products_stock (stock_quantity),
    CONSTRAINT chk_products_cost  CHECK (cost_price >= 0),
    CONSTRAINT chk_products_sell  CHECK (sell_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- invoices
-- ---------------------------------------------------------------------
CREATE TABLE invoices (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number  VARCHAR(30) NOT NULL,
    customer_name   VARCHAR(100) NOT NULL,
    payment_method  ENUM('cash','upi','card') NOT NULL DEFAULT 'cash',
    invoice_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_type   ENUM('none','percentage','fixed') NOT NULL DEFAULT 'none',
    discount_value  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (invoice_number),
    KEY idx_invoices_date (invoice_date),
    KEY idx_invoices_customer (customer_name),
    CONSTRAINT chk_invoices_subtotal CHECK (subtotal >= 0),
    CONSTRAINT chk_invoices_discount CHECK (discount_amount >= 0 AND discount_amount <= subtotal),
    CONSTRAINT chk_invoices_total    CHECK (grand_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- invoice_items  (product_name, brand_name, unit_price, cost_price are
-- historical snapshots, so later product edits never change old invoices)
-- ---------------------------------------------------------------------
CREATE TABLE invoice_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id   INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    brand_name   VARCHAR(100) NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    cost_price   DECIMAL(10,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_invoice (invoice_id),
    KEY idx_items_product (product_id),
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_items_qty   CHECK (quantity > 0),
    CONSTRAINT chk_items_price CHECK (unit_price >= 0 AND cost_price >= 0),
    CONSTRAINT chk_items_total CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sample products (stock shown is AFTER the 3 sample invoices below)
-- ---------------------------------------------------------------------
INSERT INTO products (id, name, brand_name, cost_price, sell_price, stock_quantity) VALUES
(1,  'Wireless Mouse M221',        'Logitech',     420.00,   599.00,  38),
(2,  'Bluetooth Keyboard K380',    'Logitech',    1900.00,  2495.00,  25),
(3,  'USB-C Hub 7-in-1',           'Ugreen',      1350.00,  1899.00,  18),
(4,  'Galaxy Buds FE',             'Samsung',     5200.00,  6999.00,  11),
(5,  '27-inch Full HD Monitor',    'Samsung',     9800.00, 12499.00,   8),
(6,  'Laser Printer MFP 136nw',    'HP',         13500.00, 16999.00,   6),
(7,  'Ink Tank 315 Printer',       'HP',          9200.00, 11499.00,   9),
(8,  '1TB Portable SSD T7',        'Samsung',     6900.00,  8999.00,  15),
(9,  'Gaming Headset G331',        'Logitech',    2600.00,  3495.00,  20),
(10, 'WiFi Router Archer C6',      'TP-Link',     1800.00,  2499.00,  29),
(11, 'Smart Plug Tapo P100',       'TP-Link',      550.00,   799.00,  58),
(12, 'Laptop Backpack 15.6 inch',  'Wildcraft',    900.00,  1399.00,  34),
(13, 'HDMI Cable 2m',              'AmazonBasics', 180.00,   349.00,  99),
(14, '64GB Pen Drive Cruzer',      'SanDisk',      380.00,   549.00,   5),
(15, 'HD Webcam C270',             'Logitech',    1250.00,  1795.00,   3),
(16, 'Wireless Charger 15W',       'Anker',        950.00,  1499.00,   0);

-- ---------------------------------------------------------------------
-- Sample invoices
-- ---------------------------------------------------------------------
INSERT INTO invoices
(id, invoice_number, customer_name, payment_method, invoice_date, subtotal, discount_type, discount_value, discount_amount, grand_total) VALUES
(1, 'INV-20261008-0001', 'Rahul Sharma',                  'cash', '2026-10-08 11:15:00', 1547.00, 'percentage', 10.00, 154.70, 1392.30),
(2, 'INV-20261011-0001', 'Priya Patel',                   'upi',  '2026-10-11 15:40:00', 8398.00, 'fixed',     398.00, 398.00, 8000.00),
(3, 'INV-20261013-0001', 'Brainbean Technolabs Pvt. Ltd.', 'card', '2026-10-13 10:05:00', 4097.00, 'none',       0.00,   0.00, 4097.00);

INSERT INTO invoice_items
(invoice_id, product_id, product_name, brand_name, quantity, unit_price, cost_price, line_total) VALUES
(1, 1,  'Wireless Mouse M221',       'Logitech',     2,  599.00,  420.00, 1198.00),
(1, 13, 'HDMI Cable 2m',             'AmazonBasics', 1,  349.00,  180.00,  349.00),
(2, 4,  'Galaxy Buds FE',            'Samsung',      1, 6999.00, 5200.00, 6999.00),
(2, 12, 'Laptop Backpack 15.6 inch', 'Wildcraft',    1, 1399.00,  900.00, 1399.00),
(3, 11, 'Smart Plug Tapo P100',      'TP-Link',      2,  799.00,  550.00, 1598.00),
(3, 10, 'WiFi Router Archer C6',     'TP-Link',      1, 2499.00, 1800.00, 2499.00);