-- Upgrade for people who ALREADY imported the first version (keeps your data).
-- Run once in phpMyAdmin > smartpos_db > SQL tab. New installs do not need this.
USE smartpos_db;
ALTER TABLE invoices
    ADD COLUMN payment_method ENUM('cash','upi','card') NOT NULL DEFAULT 'cash' AFTER customer_name;
