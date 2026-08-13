-- Migration: Async Order Jobs
-- Erweitert generation_jobs fuer Order-basierte asynchrone Operationen

ALTER TABLE generation_jobs
  ADD COLUMN order_id INT NULL AFTER project_id,
  ADD COLUMN chat_message TEXT NULL AFTER order_id;

-- project_id nullable machen (Orders brauchen kein project_id)
ALTER TABLE generation_jobs MODIFY COLUMN project_id INT NULL;

-- Neue Job-Typen erlauben
ALTER TABLE generation_jobs MODIFY COLUMN job_type VARCHAR(50) DEFAULT 'full_article';
