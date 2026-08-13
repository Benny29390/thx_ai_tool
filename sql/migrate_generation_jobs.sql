-- Migration: Asynchrone Artikel-Generierung mit Job-Queue
-- Erstellt: 2026-02-05

-- ===== Neue Tabelle: generation_jobs =====
CREATE TABLE IF NOT EXISTS generation_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    customer_id INT,
    user_id INT NOT NULL,

    -- Job-Konfiguration
    job_type ENUM('full_article', 'single_section', 'regenerate') DEFAULT 'full_article',
    topic VARCHAR(500),
    target_words INT DEFAULT 800,
    style_slug VARCHAR(50),
    model VARCHAR(100) NOT NULL,

    -- Input-Daten (JSON)
    sections_config JSON,          -- Anzahl/Struktur der Abschnitte
    rule_ids JSON,                 -- Ausgewaehlte Regel-IDs
    knowledge_ids JSON,            -- Ausgewaehlte Wissens-IDs
    section_index INT NULL,        -- Fuer single_section regeneration

    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    priority INT DEFAULT 0,        -- Hoeher = wichtiger
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,

    -- Ergebnis
    result JSON,                   -- Generierte Sections
    error_message TEXT,

    -- Metriken
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    processing_time_ms INT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,

    INDEX idx_status (status),
    INDEX idx_project (project_id),
    INDEX idx_user (user_id),
    INDEX idx_pending (status, priority DESC, created_at ASC),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Projekt-Status erweitern =====
ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS generation_status ENUM('idle', 'generating', 'completed', 'failed') DEFAULT 'idle',
    ADD COLUMN IF NOT EXISTS current_job_id INT NULL,
    ADD COLUMN IF NOT EXISTS last_generation_error TEXT NULL;
