-- KI Text Tool - Datenbankschema
-- ================================================

-- Kunden/Mandanten
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    settings JSON DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benutzer
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'editor') NOT NULL DEFAULT 'editor',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    data TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wissensdatenbanken pro Kunde
CREATE TABLE IF NOT EXISTS knowledge_bases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wissens-Eintraege
CREATE TABLE IF NOT EXISTS knowledge_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    knowledge_base_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    url VARCHAR(500),
    entry_type ENUM('website', 'document', 'template', 'manual') DEFAULT 'manual',
    metadata JSON DEFAULT '{}',
    embedding_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (knowledge_base_id) REFERENCES knowledge_bases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regeln
CREATE TABLE IF NOT EXISTS rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rule_type ENUM('style', 'format', 'content', 'link', 'tone') NOT NULL,
    rule_content TEXT NOT NULL,
    source ENUM('manual', 'ai_suggested', 'ai_approved') DEFAULT 'manual',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KI-Regelvorschlaege
CREATE TABLE IF NOT EXISTS rule_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    suggested_rule TEXT NOT NULL,
    rule_type ENUM('style', 'format', 'content', 'link', 'tone') NOT NULL,
    derived_from_feedback_id INT,
    confidence_score DECIMAL(3,2),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projekte
CREATE TABLE IF NOT EXISTS projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    target_website VARCHAR(500),
    target_word_count INT DEFAULT 1000,
    status ENUM('draft', 'in_progress', 'review', 'completed') DEFAULT 'draft',
    current_version INT DEFAULT 1,
    metadata JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Artikel-Versionen
CREATE TABLE IF NOT EXISTS article_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL,
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_version (project_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Artikel-Abschnitte
CREATE TABLE IF NOT EXISTS article_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    article_version_id INT NOT NULL,
    section_order INT NOT NULL,
    heading_level ENUM('h1', 'h2', 'h3') NOT NULL,
    heading_text VARCHAR(255),
    content TEXT,
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_version_id) REFERENCES article_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abschnitt-Feedback
CREATE TABLE IF NOT EXISTS section_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    user_id INT NOT NULL,
    rating ENUM('positive', 'negative', 'neutral') NOT NULL,
    comment TEXT,
    improvement_request TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES article_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Artikel-Gesamtfeedback
CREATE TABLE IF NOT EXISTS article_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    article_version_id INT NOT NULL,
    user_id INT NOT NULL,
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 5),
    tone_rating INT CHECK (tone_rating BETWEEN 1 AND 5),
    structure_rating INT CHECK (structure_rating BETWEEN 1 AND 5),
    content_rating INT CHECK (content_rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_version_id) REFERENCES article_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link-Verwaltung
CREATE TABLE IF NOT EXISTS links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255),
    link_type ENUM('internal', 'external', 'trust') NOT NULL,
    description TEXT,
    use_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat-Nachrichten
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    section_context INT NULL,
    tokens_used INT DEFAULT 0,
    model_used VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (section_context) REFERENCES article_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verbrauchs-Tracking
CREATE TABLE IF NOT EXISTS usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('chat', 'generation', 'embedding', 'analysis') NOT NULL,
    model_used VARCHAR(50) NOT NULL,
    api_provider ENUM('openai', 'anthropic', 'google', 'local') NOT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    words_generated INT DEFAULT 0,
    cost_estimate DECIMAL(10,6) DEFAULT 0,
    metadata JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monatliche Zusammenfassung
CREATE TABLE IF NOT EXISTS usage_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,
    total_api_calls INT DEFAULT 0,
    total_tokens_input INT DEFAULT 0,
    total_tokens_output INT DEFAULT 0,
    total_words_generated INT DEFAULT 0,
    total_cost_estimate DECIMAL(10,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month (customer_id, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-Request-Performance-Log fuer LLM-Calls (Vergleich lokal vs. Cloud)
CREATE TABLE IF NOT EXISTS llm_request_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    provider VARCHAR(20) NOT NULL,
    model VARCHAR(100) NOT NULL,
    use_case VARCHAR(80) NULL,
    user_id INT NULL,
    customer_id INT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    tokens_total INT DEFAULT 0,
    ttft_ms INT NULL,
    total_ms INT NULL,
    tokens_per_second DECIMAL(8,2) NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message TEXT NULL,
    INDEX idx_model_created (model, created_at),
    INDEX idx_provider_created (provider, created_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System-Einstellungen
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Einstellungen einfuegen
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('openai_api_key', '', 'string', 'OpenAI API Key'),
('anthropic_api_key', '', 'string', 'Anthropic/Claude API Key'),
('default_model', 'gpt-4', 'string', 'Standard KI-Modell'),
('max_tokens_per_request', '4000', 'int', 'Max Tokens pro Anfrage'),
('embedding_model', 'text-embedding-3-small', 'string', 'Modell fuer Embeddings'),
('local_base_url', 'https://ki.thoxan.com/llm/v1', 'string', 'Base-URL des lokalen Inference-Servers (OpenAI-kompatibel)'),
('local_api_key', '', 'string', 'API-Key des lokalen Inference-Servers')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Indizes fuer Performance
CREATE INDEX idx_users_customer ON users(customer_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_projects_customer ON projects(customer_id);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_usage_logs_customer ON usage_logs(customer_id);
CREATE INDEX idx_usage_logs_created ON usage_logs(created_at);
CREATE INDEX idx_rules_customer ON rules(customer_id);
CREATE INDEX idx_rules_type ON rules(rule_type);
