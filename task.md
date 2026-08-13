# KI Text Tool - Vollstaendiger Projektplan

## Projektuebersicht

Ein modulares Online-Tool fuer die KI-gestuetzte Content-Erstellung. Das **Text-Modul** ist das erste von mehreren geplanten Modulen.

### Geplante Module (Zukunft)
1. **Text-Modul** (dieses Projekt) - Gastartikel, Fachartikel
2. Social Media Posts - LinkedIn, Instagram, Facebook
3. Newsletter/E-Mail - Kampagnen und Newsletter
4. SEO-Analysen - Keywords, Meta-Tags, Optimierung

---

###### Neutrale UI, Modern schwarz weiß, Apple like, glass effect

## Entscheidungen (aus Befragung)

| Thema | Entscheidung |
|-------|-------------|
| KI-APIs | Beide: OpenAI GPT-4 + Claude |
| Domain/Hosting | Flexibel, lokal entwickeln, FTP-Upload, Installer |
| Initiale Kunden | ca. 20 |
| Billing | Nein, aber Verbrauchstracking |
| Benutzer-Rollen | Admin > Manager > Editor |
| Feedback-System | Abschnitte + Gesamtbewertung kombiniert |
| KI-Regel-Validierung | Admin muss vorgeschlagene Regeln freigeben |
| Verbrauchstracking | API-Calls + Tokens + generierte Woerter |
| Kunden-Isolation | Getrennt, aber Admins sehen alles |
| Wissensdatenbank | RAG mit Vektor-Suche |
| RAG-Technologie | Lokal mit SQLite (Embeddings via API) |
| Installer | Mehrstufiger Wizard im Browser |
| Sprache | Nur Deutsch |

---

## Technologie-Stack

### Backend
- **PHP 8.1+** (vanilla, kein Framework)
- **MySQL 8.0+** (Hauptdatenbank)
- **SQLite** (Vektor-Speicherung pro Kunde)

### Frontend
- **JavaScript** (vanilla ES6+)
- **HTML5 / CSS3**
- **Kein Framework** (jQuery nur wenn noetig)

### KI-Integration
- **OpenAI API** (GPT-4 + Embeddings)
- **Anthropic API** (Claude)

### Zusaetzlich
- **PHPMailer** (fuer spaetere E-Mail-Funktionen)
- **TCPDF oder Dompdf** (Export-Funktionen)

---

## Architektur

### Modulares System

```
+--------------------------------------------------+
|                    CORE SYSTEM                    |
|  (Auth, Users, Customers, Settings, Tracking)    |
+--------------------------------------------------+
        |           |           |           |
   +--------+  +--------+  +--------+  +--------+
   | TEXT   |  | SOCIAL |  | EMAIL  |  | SEO    |
   | MODUL  |  | MODUL  |  | MODUL  |  | MODUL  |
   +--------+  +--------+  +--------+  +--------+
        |           |           |           |
+--------------------------------------------------+
|              SHARED SERVICES                      |
|  (KI-Handler, RAG-System, Regel-Engine)          |
+--------------------------------------------------+
```

### Benutzer-Hierarchie

```
ADMIN (Super-Admin)
  |-- Sieht alle Kunden
  |-- Verwaltet globale Einstellungen
  |-- Gibt KI-generierte Regeln frei
  |-- Sieht Verbrauchsstatistiken aller Kunden
  |
  +-- MANAGER (pro Kunde)
        |-- Verwaltet seinen Kunden-Account
        |-- Erstellt/verwaltet Editoren
        |-- Verwaltet Wissensdatenbank
        |-- Sieht Verbrauch seines Kunden
        |
        +-- EDITOR (pro Kunde)
              |-- Erstellt Artikel
              |-- Gibt Feedback (Kritiker-System)
              |-- Kein Zugriff auf Verwaltung
```

---

## Datenbankstruktur

### Hauptdatenbank (MySQL)

```sql
-- ================================================
-- CORE TABLES
-- ================================================

-- Kunden/Mandanten
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    settings JSON DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Benutzer
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL, -- NULL = Super-Admin
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'editor') NOT NULL DEFAULT 'editor',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Sessions
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    data TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ================================================
-- WISSENS-SYSTEM
-- ================================================

-- Wissensdatenbanken pro Kunde
CREATE TABLE knowledge_bases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Wissens-Eintraege (Texte werden auch als Embeddings in SQLite gespeichert)
CREATE TABLE knowledge_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    knowledge_base_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    url VARCHAR(500),
    entry_type ENUM('website', 'document', 'template', 'manual') DEFAULT 'manual',
    metadata JSON DEFAULT '{}',
    embedding_id VARCHAR(100), -- Referenz zur SQLite Vektor-DB
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (knowledge_base_id) REFERENCES knowledge_bases(id) ON DELETE CASCADE
);

-- ================================================
-- REGEL-SYSTEM
-- ================================================

-- Regeln (manuell + KI-generiert)
CREATE TABLE rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NULL, -- NULL = globale Regel
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rule_type ENUM('style', 'format', 'content', 'link', 'tone') NOT NULL,
    rule_content TEXT NOT NULL, -- Die eigentliche Regel als Text
    source ENUM('manual', 'ai_suggested', 'ai_approved') DEFAULT 'manual',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- KI-Regelvorschlaege (vor Freigabe)
CREATE TABLE rule_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    suggested_rule TEXT NOT NULL,
    rule_type ENUM('style', 'format', 'content', 'link', 'tone') NOT NULL,
    derived_from_feedback_id INT,
    confidence_score DECIMAL(3,2), -- 0.00 - 1.00
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ================================================
-- ARTIKEL/PROJEKTE
-- ================================================

-- Projekte
CREATE TABLE projects (
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
);

-- Artikel-Versionen
CREATE TABLE article_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL, -- Strukturierter Inhalt {sections: [...]}
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_version (project_id, version_number)
);

-- Artikel-Abschnitte (fuer granulares Feedback)
CREATE TABLE article_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    article_version_id INT NOT NULL,
    section_order INT NOT NULL,
    heading_level ENUM('h1', 'h2', 'h3') NOT NULL,
    heading_text VARCHAR(255),
    content TEXT,
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_version_id) REFERENCES article_versions(id) ON DELETE CASCADE
);

-- ================================================
-- FEEDBACK/KRITIKER-SYSTEM
-- ================================================

-- Abschnitt-Feedback
CREATE TABLE section_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    user_id INT NOT NULL,
    rating ENUM('positive', 'negative', 'neutral') NOT NULL,
    comment TEXT,
    improvement_request TEXT, -- "Die KI soll hier nochmal ran"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES article_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Artikel-Gesamtfeedback
CREATE TABLE article_feedback (
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
);

-- ================================================
-- LINK-VERWALTUNG
-- ================================================

CREATE TABLE links (
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
);

-- ================================================
-- CHAT/KONVERSATION
-- ================================================

CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    section_context INT NULL, -- Bezug zu einem Abschnitt
    tokens_used INT DEFAULT 0,
    model_used VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (section_context) REFERENCES article_sections(id) ON DELETE SET NULL
);

-- ================================================
-- VERBRAUCHS-TRACKING
-- ================================================

CREATE TABLE usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('chat', 'generation', 'embedding', 'analysis') NOT NULL,
    model_used VARCHAR(50) NOT NULL,
    api_provider ENUM('openai', 'anthropic') NOT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    words_generated INT DEFAULT 0,
    cost_estimate DECIMAL(10,6) DEFAULT 0,
    metadata JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Monatliche Zusammenfassung
CREATE TABLE usage_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    year_month VARCHAR(7) NOT NULL, -- Format: 2024-01
    total_api_calls INT DEFAULT 0,
    total_tokens_input INT DEFAULT 0,
    total_tokens_output INT DEFAULT 0,
    total_words_generated INT DEFAULT 0,
    total_cost_estimate DECIMAL(10,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month (customer_id, year_month)
);

-- ================================================
-- SYSTEM-EINSTELLUNGEN
-- ================================================

CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Standard-Einstellungen
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('openai_api_key', '', 'string', 'OpenAI API Key'),
('anthropic_api_key', '', 'string', 'Anthropic/Claude API Key'),
('default_model', 'gpt-4', 'string', 'Standard KI-Modell'),
('max_tokens_per_request', '4000', 'int', 'Max Tokens pro Anfrage'),
('embedding_model', 'text-embedding-3-small', 'string', 'Modell fuer Embeddings');
```

### Vektor-Datenbank (SQLite pro Kunde)

Jeder Kunde erhaelt eine eigene SQLite-Datei: `vectors/{customer_slug}.sqlite`

```sql
-- Embeddings-Tabelle
CREATE TABLE embeddings (
    id TEXT PRIMARY KEY,
    content_hash TEXT NOT NULL,
    vector BLOB NOT NULL, -- Serialisierter Float-Array
    dimensions INT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Index fuer schnelle Suche
CREATE INDEX idx_content_hash ON embeddings(content_hash);
```

---

## Ordnerstruktur

```
ki-tool/
|
|-- index.php                    # Einstiegspunkt / Router
|-- install.php                  # Installer-Wizard
|
|-- config/
|   |-- config.php              # Konfiguration (wird vom Installer erstellt)
|   |-- config.sample.php       # Beispiel-Konfiguration
|   +-- constants.php           # Konstanten
|
|-- core/
|   |-- App.php                 # Haupt-App-Klasse
|   |-- Router.php              # URL-Routing
|   |-- Database.php            # MySQL-Verbindung
|   |-- Auth.php                # Authentifizierung
|   |-- Session.php             # Session-Management
|   +-- Response.php            # HTTP-Responses
|
|-- modules/
|   |-- text/                   # TEXT-MODUL
|   |   |-- TextModule.php      # Modul-Hauptklasse
|   |   |-- controllers/
|   |   |   |-- ProjectController.php
|   |   |   |-- EditorController.php
|   |   |   +-- ExportController.php
|   |   |-- models/
|   |   |   |-- Project.php
|   |   |   |-- ArticleVersion.php
|   |   |   +-- Section.php
|   |   +-- views/
|   |       |-- dashboard.php
|   |       |-- editor.php
|   |       +-- project-list.php
|   |
|   +-- [weitere Module spaeter]
|
|-- services/
|   |-- AIService.php           # KI-API-Handler (OpenAI + Claude)
|   |-- EmbeddingService.php    # Embedding-Erstellung
|   |-- RAGService.php          # Vektor-Suche
|   |-- RuleEngine.php          # Regel-Verarbeitung
|   |-- FeedbackAnalyzer.php    # Feedback -> Regelvorschlaege
|   +-- UsageTracker.php        # Verbrauchstracking
|
|-- models/
|   |-- User.php
|   |-- Customer.php
|   |-- KnowledgeBase.php
|   |-- KnowledgeEntry.php
|   |-- Rule.php
|   +-- Link.php
|
|-- views/
|   |-- layouts/
|   |   |-- main.php            # Haupt-Layout mit Navigation
|   |   |-- auth.php            # Layout fuer Login/Register
|   |   +-- install.php         # Layout fuer Installer
|   |
|   |-- auth/
|   |   |-- login.php
|   |   +-- forgot-password.php
|   |
|   |-- admin/
|   |   |-- dashboard.php
|   |   |-- customers.php
|   |   |-- users.php
|   |   |-- rule-suggestions.php
|   |   |-- usage-stats.php
|   |   +-- settings.php
|   |
|   |-- knowledge/
|   |   |-- index.php
|   |   |-- edit.php
|   |   +-- import.php
|   |
|   |-- rules/
|   |   |-- index.php
|   |   +-- edit.php
|   |
|   +-- install/
|       |-- step1-requirements.php
|       |-- step2-database.php
|       |-- step3-admin.php
|       |-- step4-api-keys.php
|       +-- step5-complete.php
|
|-- assets/
|   |-- css/
|   |   |-- style.css           # Haupt-Styles
|   |   |-- editor.css          # Editor-spezifisch
|   |   +-- install.css         # Installer-Styles
|   |
|   +-- js/
|       |-- app.js              # Haupt-JavaScript
|       |-- editor.js           # Editor-Logik
|       |-- chat.js             # Chat-Interface
|       |-- feedback.js         # Feedback-System
|       +-- install.js          # Installer-Logik
|
|-- api/
|   |-- v1/
|   |   |-- chat.php            # POST /api/v1/chat
|   |   |-- projects.php        # CRUD /api/v1/projects
|   |   |-- sections.php        # CRUD /api/v1/sections
|   |   |-- feedback.php        # POST /api/v1/feedback
|   |   |-- knowledge.php       # CRUD /api/v1/knowledge
|   |   |-- rules.php           # CRUD /api/v1/rules
|   |   |-- suggestions.php     # GET/POST /api/v1/suggestions
|   |   +-- usage.php           # GET /api/v1/usage
|   +-- middleware.php          # Auth-Middleware fuer API
|
|-- storage/
|   |-- vectors/                # SQLite Vektor-DBs pro Kunde
|   |-- uploads/                # Hochgeladene Dokumente
|   |-- exports/                # Generierte Exporte
|   +-- logs/                   # Log-Dateien
|
|-- sql/
|   |-- schema.sql              # Komplettes DB-Schema
|   +-- seed.sql                # Beispieldaten
|
+-- vendor/                     # Externe Bibliotheken (manuell, kein Composer)
    |-- phpmailer/
    +-- tcpdf/
```

---

## Feature-Phasen

### Phase 0: Server-Setup [ERLEDIGT]
- [x] PHP 8.4 installiert
- [x] MariaDB 11.8 installiert
- [x] Apache konfiguriert (mod_rewrite, AllowOverride All)
- [x] PHP Extensions (cURL, JSON, SQLite, PDO, mbstring, xml, zip)
- [x] Ordnerstruktur mit Schreibrechten
- [x] Datenbank: ki_tool / User: ki_tool / Pass: ki_tool_2024!

### Phase 1: Core-System [ERLEDIGT]
- [x] Ordnerstruktur angelegt
- [x] Installer-Wizard (5 Schritte)
- [x] Datenbank-Setup (SQL-Schema)
- [x] Authentifizierung (Login/Logout/Session)
- [x] Benutzer-Verwaltung (Admin > Manager > Editor)
- [x] Kunden-Verwaltung (Multi-Tenant)
- [x] Basis-Routing und Layout
- [x] Dashboard mit Statistiken
- [x] Basis-API-Endpunkte (projects, users, customers, settings)

### Phase 2: KI-Integration [ERLEDIGT]
- [x] AIService fuer OpenAI
- [x] AIService fuer Claude
- [x] API-Key-Verwaltung in Settings
- [x] Verbrauchstracking implementieren (UsageTracker)
- [x] Modell-Auswahl (GPT-4 / Claude)
- [x] Chat-API Endpunkt

### Phase 3: Wissensdatenbank + RAG [ERLEDIGT]
- [x] Knowledge-Base CRUD
- [x] Manueller Eintrag hinzufuegen
- [x] Embedding-Service (OpenAI Embeddings)
- [x] SQLite Vektor-Speicherung pro Kunde
- [x] RAG-Suche implementieren
- [x] Knowledge View mit semantischer Suche

### Phase 4: Regel-System [ERLEDIGT]
- [x] Manuelle Regeln CRUD
- [x] Regel-Typen (style, format, content, link, tone)
- [x] Globale vs. Kunden-Regeln
- [x] Regel-Engine fuer Prompt-Erstellung
- [x] Regel-Prioritaeten
- [x] Rules View mit Toggle/Edit/Delete

### Phase 5: Text-Modul (Editor) [ERLEDIGT]
- [x] Projekt erstellen/bearbeiten
- [x] Abschnittsweiser Editor
- [x] Chat-Interface mit KI
- [x] Woerter-Zaehler (pro Abschnitt + gesamt)
- [x] Projekt-Status (draft/in_progress/review/completed)
- [x] Projekt-Info Panel
- [x] Kontext-basiertes Chatten

### Phase 6: Kritiker-System [ERLEDIGT]
- [x] Abschnitt-Feedback (Gut/Schlecht/Nochmal)
- [x] FeedbackAnalyzer: Feedback -> Muster erkennen
- [x] KI-Regelvorschlaege generieren
- [x] Admin-Interface fuer Regel-Freigabe (rule-suggestions.php)

### Phase 7: Link-Verwaltung [TEILWEISE]
- [x] Link-Panel im Editor (UI)
- [ ] Link-Datenbank pro Kunde (API fehlt)
- [ ] Link-Typen (intern, extern, affiliate)

### Phase 8: Export [ERLEDIGT]
- [x] Export als Plaintext
- [x] Export als HTML
- [x] Export als Markdown
- [ ] Export als Word (.docx) (Basis-Version offen)

### Phase 9: Admin-Bereich [ERLEDIGT]
- [x] Kundenverwaltung (customers.php)
- [x] Benutzerverwaltung (users.php)
- [x] Einstellungen (settings.php)
- [x] Verbrauchsstatistiken (usage-stats.php)
- [x] Regelvorschlaege (rule-suggestions.php)

### Noch offen / Erweiterungen (optional)
- [ ] URL-Import (Website scrapen)
- [ ] Dokument-Upload (TXT, PDF)
- [ ] Template-Import (bestehende Artikel)
- [ ] Gesamtartikel-Bewertung (5 Sterne + Kategorien)
- [ ] UI/UX Verbesserungen
- [ ] Performance-Optimierung
- [ ] Logging
- [ ] Dokumentation

---

## Kritiker-System im Detail

### Workflow

```
1. Editor arbeitet an Artikel
          |
          v
2. KI generiert Abschnitt
          |
          v
3. Editor bewertet: [Gut] [Schlecht] [Nochmal]
          |
          +---> [Gut]: Positives Feedback gespeichert
          |
          +---> [Schlecht]: Negatives Feedback + Kommentar
          |            |
          |            v
          |     FeedbackAnalyzer sammelt Muster
          |            |
          |            v
          |     Genug Feedback? -> KI generiert Regel-Vorschlag
          |            |
          |            v
          |     Admin prueft -> [Freigeben] / [Ablehnen]
          |            |
          |            v
          |     Regel wird aktiv fuer zukuenftige Generierungen
          |
          +---> [Nochmal]: KI ueberarbeitet mit Feedback
                         |
                         v
                  Neuer Versuch wird generiert
```

### Beispiel Regel-Generierung

```
Feedback-Daten:
- 5x "Gedankenstriche sind nervig"
- 3x "Zu viele Emojis"
- 4x "Einstieg zu langweilig"

KI analysiert und schlaegt vor:
{
  "rule_type": "style",
  "suggested_rule": "Verwende keine Gedankenstriche (–) im Text.
                     Nutze stattdessen Kommas oder Punkte.",
  "confidence_score": 0.85,
  "derived_from": "5 negative Feedbacks zu Gedankenstrichen"
}

Admin sieht:
+--------------------------------------------------+
| Neuer Regelvorschlag                    [85% Konfidenz]
|--------------------------------------------------|
| Typ: Stil                                        |
| Regel: Verwende keine Gedankenstriche (–) im    |
|        Text. Nutze stattdessen Kommas oder       |
|        Punkte.                                   |
|--------------------------------------------------|
| Basiert auf: 5 negative Feedbacks               |
|--------------------------------------------------|
| [Freigeben]  [Anpassen]  [Ablehnen]             |
+--------------------------------------------------+
```

---

## Installer-Wizard

### Schritt 1: Voraussetzungen pruefen
- PHP Version >= 8.1
- MySQL verfuegbar
- Schreibrechte fuer config/ und storage/
- cURL Extension
- JSON Extension
- SQLite Extension

### Schritt 2: Datenbank-Konfiguration
- Host, Port, Datenbankname
- Benutzername, Passwort
- Verbindung testen
- Tabellen erstellen

### Schritt 3: Admin-Account erstellen
- E-Mail
- Passwort (mit Staerke-Anzeige)
- Name

### Schritt 4: API-Keys
- OpenAI API Key (testen)
- Anthropic API Key (testen)
- Standard-Modell waehlen

### Schritt 5: Abschluss
- Zusammenfassung
- config.php erstellt
- Ersten Kunden anlegen (optional)
- Weiterleitung zum Login

---

## Erstellte Dateien (laut Plan)

### Core System
- `/config/constants.php` - Anwendungskonstanten
- `/config/config.sample.php` - Beispiel-Konfiguration
- `/core/Database.php` - MySQL PDO Wrapper
- `/core/Session.php` - Session-Management mit CSRF
- `/core/Auth.php` - Authentifizierung und Rollenverwaltung
- `/core/Router.php` - URL-Routing
- `/core/Response.php` - HTTP-Responses und Views
- `/core/App.php` - Hauptanwendungsklasse
- `/index.php` - Einstiegspunkt
- `/install.php` - Installer-Klasse
- `/.htaccess` - Apache Konfiguration

### Services
- `/services/AIService.php` - KI-Integration (OpenAI + Claude)
- `/services/UsageTracker.php` - Verbrauchstracking
- `/services/RuleEngine.php` - Regel-Management
- `/services/EmbeddingService.php` - Embeddings mit SQLite
- `/services/RAGService.php` - Retrieval Augmented Generation
- `/services/FeedbackAnalyzer.php` - Feedback-Analyse und Regelvorschlaege

### APIs
- `/api/v1/chat.php` - Chat-Endpunkt
- `/api/v1/projects.php` - Projektverwaltung
- `/api/v1/knowledge.php` - Wissensdatenbank
- `/api/v1/rules.php` - Regelverwaltung
- `/api/v1/feedback.php` - Feedback-System
- `/api/v1/suggestions.php` - Regelvorschlaege
- `/api/v1/usage.php` - Verbrauchsstatistiken
- `/api/v1/admin/customers.php` - Kundenverwaltung
- `/api/v1/admin/users.php` - Benutzerverwaltung
- `/api/v1/admin/settings.php` - Einstellungen

### Views
- `/views/layouts/main.php` - Hauptlayout
- `/views/layouts/auth.php` - Login-Layout
- `/views/layouts/install.php` - Installer-Layout
- `/views/auth/login.php` - Login-Formular
- `/views/install/step1-5.php` - Installer-Schritte
- `/views/admin/dashboard.php` - Dashboard
- `/views/admin/customers.php` - Kundenverwaltung
- `/views/admin/users.php` - Benutzerverwaltung
- `/views/admin/settings.php` - Einstellungen
- `/views/admin/usage-stats.php` - Verbrauchsstatistiken
- `/views/admin/rule-suggestions.php` - Regelvorschlaege
- `/views/knowledge/index.php` - Wissensdatenbank
- `/views/rules/index.php` - Regelverwaltung
- `/views/modules/text/editor.php` - Text-Editor

### Assets
- `/assets/css/style.css` - Haupt-Styles
- `/assets/css/install.css` - Installer-Styles
- `/assets/js/app.js` - Haupt-JavaScript
- `/assets/js/install.js` - Installer-JavaScript

### SQL
- `/sql/schema.sql` - Komplettes Datenbankschema

---

*Stand: 2026-02-04*
*Status: Phase 0-9 (Haupt-Features) erledigt - Anwendung laeuft unter http://localhost/*
