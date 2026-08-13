-- 0001_module_state
-- Installationsweite Aktiv-Ebene der Modul-Steuerung (Phase 2).
-- Fehlt ein Modul-Key hier, gilt das Modul als AKTIV (Default 1) — damit laeuft
-- die bestehende Thoxan-Installation ohne jeden Eintrag voll weiter.
-- Additiv/idempotent: rein zusaetzliche Tabelle, keine Bestandsdaten betroffen.

CREATE TABLE IF NOT EXISTS module_state (
    module_key VARCHAR(64) PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
