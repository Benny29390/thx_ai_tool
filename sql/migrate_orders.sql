-- Migration: Kontextbasierte Auftraege (Context-Based Orders)
-- 6 neue Tabellen: contexts, context_items, orders, order_versions, order_chat_messages, order_rule_suggestions

-- ===== Kontexte =====
CREATE TABLE IF NOT EXISTS contexts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    created_by INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Kontext-Items =====
CREATE TABLE IF NOT EXISTS context_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    context_id INT NOT NULL,
    item_type ENUM('customer','knowledge','url','pdf','text','rule') NOT NULL,
    reference_id INT NULL,
    content TEXT NULL,
    title VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (context_id) REFERENCES contexts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Auftraege =====
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    context_id INT NOT NULL,
    customer_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('briefing','briefing_approved','generating','editing','completed') DEFAULT 'briefing',
    briefing_content LONGTEXT NULL,
    article_content LONGTEXT NULL,
    current_version INT DEFAULT 0,
    model VARCHAR(100) NULL,
    metadata JSON DEFAULT ('{}'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (context_id) REFERENCES contexts(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Versions-History =====
CREATE TABLE IF NOT EXISTS order_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    version_number INT NOT NULL,
    article_content LONGTEXT NOT NULL,
    briefing_content LONGTEXT NULL,
    change_description VARCHAR(500) NULL,
    change_source ENUM('ai','manual','generation','rollback') DEFAULT 'manual',
    word_count INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_version (order_id, version_number),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Chat-Verlauf =====
CREATE TABLE IF NOT EXISTS order_chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    phase ENUM('briefing','editing') NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content TEXT NOT NULL,
    applied_change JSON NULL,
    tokens_used INT DEFAULT 0,
    model_used VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Regel-Vorschlaege aus Lern-Schleife =====
CREATE TABLE IF NOT EXISTS order_rule_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    context_id INT NOT NULL,
    suggested_rule TEXT NOT NULL,
    rule_name VARCHAR(255) NULL,
    rule_type VARCHAR(50) DEFAULT 'content',
    status ENUM('pending','accepted','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (context_id) REFERENCES contexts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
