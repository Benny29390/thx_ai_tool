-- Migration: Multi-Kunden pro Benutzer
-- Erstellt Junction-Tabelle user_customers und migriert bestehende Daten

-- 1. Junction-Tabelle erstellen
CREATE TABLE IF NOT EXISTS user_customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    customer_id INT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_customer (user_id, customer_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bestehende Zuweisungen migrieren (von users.customer_id)
INSERT IGNORE INTO user_customers (user_id, customer_id, is_default)
SELECT id, customer_id, 1
FROM users
WHERE customer_id IS NOT NULL;

-- 3. Index fuer Performance
CREATE INDEX IF NOT EXISTS idx_user_customers_user ON user_customers(user_id);
CREATE INDEX IF NOT EXISTS idx_user_customers_customer ON user_customers(customer_id);
