-- 0002_ki_mitarbeiter
-- Datenmodell fuer den KI-Mitarbeiter-Builder (Spec §8), an Projektkonventionen
-- angepasst: INT AUTO_INCREMENT statt UUID, customer_id INT NULL statt tenant_id
-- (NULL = installationsweit). Audit laeuft ueber permission_audit_log.
-- Additiv/idempotent: nur CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS ai_employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    role_title VARCHAR(150) NOT NULL DEFAULT '',
    short_description TEXT NULL,
    department VARCHAR(150) NULL,
    owner_user_id INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    avatar_url TEXT NULL,
    problem_statement TEXT NULL,
    expected_benefit TEXT NULL,
    need_classification VARCHAR(50) NULL,
    profile JSON NULL,
    personality_config JSON NULL,
    memory_policy JSON NULL,
    model_config JSON NULL,
    current_version_id INT NULL,
    quality_score DECIMAL(5,2) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_employee_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    version_number INT NOT NULL,
    profile_snapshot JSON NOT NULL,
    change_summary TEXT NULL,
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    UNIQUE KEY uniq_employee_version (ai_employee_id, version_number),
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_employee_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    included BOOLEAN NOT NULL DEFAULT TRUE,
    frequency VARCHAR(50) NULL,
    priority INT NOT NULL DEFAULT 0,
    success_criteria JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_workflows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    trigger_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    trigger_config JSON NULL,
    input_schema JSON NULL,
    steps JSON NULL,
    output_schema JSON NULL,
    approval_rules JSON NULL,
    escalation_rules JSON NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    instructions TEXT NOT NULL,
    input_schema JSON NULL,
    output_schema JSON NULL,
    version INT NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_employee_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    skill_id INT NOT NULL,
    config JSON NULL,
    priority INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_employee_skill (ai_employee_id, skill_id),
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_tool_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    tool_key VARCHAR(100) NOT NULL,
    resource_scope JSON NULL,
    permission_level VARCHAR(30) NOT NULL DEFAULT 'none',
    status VARCHAR(30) NOT NULL DEFAULT 'requested',
    justification TEXT NULL,
    requested_by INT NOT NULL,
    approved_by INT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    INDEX idx_employee (ai_employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_knowledge_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_reference TEXT NOT NULL,
    title VARCHAR(200) NULL,
    access_scope JSON NULL,
    sync_status VARCHAR(30) NULL,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_test_cases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'standard',
    input_data JSON NULL,
    expected_behavior TEXT NOT NULL,
    must_have JSON NULL,
    must_not_have JSON NULL,
    minimum_score DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_runs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    workflow_id INT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'test',
    initiated_by INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'running',
    input_data JSON NULL,
    output_data JSON NULL,
    model_info JSON NULL,
    permission_events JSON NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    cost_usd DECIMAL(10,4) DEFAULT 0,
    requires_approval BOOLEAN NOT NULL DEFAULT FALSE,
    approved_by INT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    error_message TEXT NULL,
    INDEX idx_employee (ai_employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_run_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    run_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    content MEDIUMTEXT NULL,
    tool_calls JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    run_id INT NULL,
    user_id INT NOT NULL,
    rating SMALLINT NULL,
    feedback_type VARCHAR(50) NOT NULL DEFAULT 'sonstiges',
    comment TEXT NULL,
    suggested_change JSON NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_employee (ai_employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_wizard_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ai_employee_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    content MEDIUMTEXT NULL,
    patch JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (ai_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
