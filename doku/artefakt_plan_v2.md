# Entity-Artefakt-Modell v2: Implementierungsplan

Finaler Plan nach kritischer Analyse des Zoom-Call-Transkripts und Klaerung aller
offenen Fragen. Ersetzt artefakt_plan.md (v1).

---

## Ausgangslage

Das KI Text Tool hat aktuell 39 Tabellen mit festem Schema. Schmerzpunkte:

1. **Klassifikation**: "Ist das eine Regel, ein Wissenseintrag oder ein Beispielartikel?"
2. **M2M-Explosion**: 6 Zuordnungstabellen, jede neue Dimension braucht eine weitere
3. **Kein Scope**: "Per Du" gilt pauschal fuer Kunde X — nicht einschraenkbar auf Artikeltyp
4. **Schema-Starrheit**: Neues Feld = ALTER TABLE, neuer Typ = ENUM erweitern

---

## Gewaehlter Ansatz: Volles Entity-Artefakt-Modell

### Entitaeten = Jedes Wort

Wie im Zoom-Call beschrieben: Jedes Wort/Token wird als eigene Entitaet gespeichert.
Nicht nur Schluesselkonzepte, sondern ALLE Woerter.

```
entities-Tabelle:
ID  | value      | status
1   | eberhard   | active
2   | hat        | active
3   | einen      | active
4   | kaffee     | active
```

- Jedes Wort existiert genau EINMAL
- Status: active / blacklist / secure (Hashing = spaeter)
- Waechst organisch mit der Nutzung
- Startgroesse: einige Tausend (nicht 1.5M — das kommt mit der Zeit)

### Artefakte = Entity-IDs + JSON-Meta

Jedes Artefakt hat zwei Inhalts-Dimensionen:

1. **entity_content**: Tilde-separierte Entity-IDs (`1~2~3~4`)
   → Ermoeglicht Impact-Analyse auf Wort-Ebene
   → Artefakte vernetzen sich ueber gemeinsame Entitaeten

2. **meta**: JSON-Selbstbeschreibung (Typ, Scope, Verknuepfungen)
   → KI versteht sofort was das Artefakt ist und wann es gilt
   → Scope-basierte Filterung ohne M2M-Tabellen

```
artifacts-Tabelle:
ID  | slug              | artifact_type | entity_content    | meta
1   | regel-per-du      | Regel         | 142~87~3~901~55   | {"type":"Regel","scope":...}
2   | autor-dr-schmidt  | Autor         | 33~7~102~88       | {"type":"Autor","tone":...}
```

### Was das gegenueber dem Originalkonzept NICHT hat (bewusst)

- **Kein Hashing/Secure** — kommt spaeter wenn externe KI angebunden wird
- **Kein C-Engine** — PHP reicht fuer unsere Groessenordnung
- **Kein Intent-System** — kommt mit Claude Code Hooks spaeter
- **Keine Graph-DB** — MariaDB + JSON reicht erstmal

---

## Entschiedene Punkte

| Frage | Entscheidung |
|-------|-------------|
| Entity-Modell | Volles Modell: jedes Wort = Entitaet |
| UI | Einheitlicher Artefakt-Browser (`/admin/artifacts`) |
| Slug-Format | `{typ}-{name}` z.B. `regel-per-du`, `autor-dr-schmidt` |
| Versionierung | Separate `artifact_versions` Tabelle |
| Hashing | Spaeter — nicht in Phase 1 |

---

## Datenbank-Schema

### entities

```sql
CREATE TABLE entities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(500) NOT NULL,
    status ENUM('active','blacklist','secure') DEFAULT 'active',
    hash CHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_value (value(200)),
    INDEX idx_hash (hash),
    INDEX idx_status (status)
);
```

### artifacts

```sql
CREATE TABLE artifacts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    artifact_type VARCHAR(100) NOT NULL,
    entity_content TEXT DEFAULT NULL,
    meta JSON NOT NULL,
    source_table VARCHAR(100) DEFAULT NULL,
    source_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    customer_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (artifact_type),
    INDEX idx_customer (customer_id),
    INDEX idx_source (source_table, source_id),
    INDEX idx_active_type (is_active, artifact_type),
    FULLTEXT INDEX idx_entity_content (entity_content)
);
```

### artifact_versions

```sql
CREATE TABLE artifact_versions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    artifact_id BIGINT NOT NULL,
    entity_content TEXT DEFAULT NULL,
    meta JSON NOT NULL,
    changed_by INT DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_reason VARCHAR(500) DEFAULT NULL,
    INDEX idx_artifact (artifact_id),
    INDEX idx_changed_at (changed_at)
);
```

---

## Implementierung in 4 Phasen

### Phase 1: Tabellen + Tokenizer

**Neue Dateien:**
- SQL-Migration: `entities`, `artifacts`, `artifact_versions` anlegen
- `services/EntityService.php` — Tokenizer + Entity-Verwaltung
- `services/ArtifactService.php` — Artefakt-CRUD + Versionierung

**EntityService — Kern-Funktionen:**

```php
class EntityService {
    // Text → Entity-ID-Sequenz
    function tokenize(string $text): string {
        $words = preg_split('/\s+/', trim($text));
        $ids = [];
        foreach ($words as $word) {
            $normalized = mb_strtolower(trim($word));
            if ($normalized === '') continue;
            $id = $this->findOrCreate($normalized);
            $ids[] = $id;
        }
        return implode('~', $ids);
    }

    // Entity-ID-Sequenz → lesbarer Text
    function resolve(string $entityContent): string {
        if (empty($entityContent)) return '';
        $ids = explode('~', $entityContent);
        $entities = $this->db->query(
            "SELECT id, value FROM entities WHERE id IN (" .
            implode(',', array_map('intval', $ids)) . ")"
        );
        $map = array_column($entities, 'value', 'id');
        return implode(' ', array_map(fn($id) => $map[$id] ?? '?', $ids));
    }

    // Entity finden oder anlegen
    function findOrCreate(string $value): int {
        $row = $this->db->queryOne(
            "SELECT id FROM entities WHERE value = ?", [$value]
        );
        if ($row) return $row['id'];
        return $this->db->insert('entities', ['value' => $value]);
    }

    // Impact-Analyse: Welche Artefakte enthalten diese Entity?
    function findArtifactsContaining(int $entityId): array {
        return $this->db->query(
            "SELECT * FROM artifacts WHERE FIND_IN_SET(?, REPLACE(entity_content, '~', ','))",
            [$entityId]
        );
    }
}
```

**ArtifactService — Kern-Funktionen:**

```php
class ArtifactService {
    function create(string $type, string $name, string $text, array $meta): int {
        $slug = $this->generateSlug($type, $name);
        $entityContent = $this->entityService->tokenize($text);
        $meta['type'] = $type;
        $meta['name'] = $name;

        return $this->db->insert('artifacts', [
            'slug' => $slug,
            'artifact_type' => $type,
            'entity_content' => $entityContent,
            'meta' => json_encode($meta),
            'created_by' => Auth::id(),
            'customer_id' => $meta['scope']['customers'][0] ?? null
        ]);
    }

    function update(int $id, array $changes, string $reason = ''): void {
        $old = $this->db->queryOne("SELECT * FROM artifacts WHERE id = ?", [$id]);

        // Alte Version sichern
        $this->db->insert('artifact_versions', [
            'artifact_id' => $id,
            'entity_content' => $old['entity_content'],
            'meta' => $old['meta'],
            'changed_by' => Auth::id(),
            'change_reason' => $reason
        ]);

        // Artefakt aktualisieren
        if (isset($changes['text'])) {
            $changes['entity_content'] = $this->entityService->tokenize($changes['text']);
            unset($changes['text']);
        }
        if (isset($changes['meta'])) {
            $changes['meta'] = json_encode($changes['meta']);
        }
        $this->db->update('artifacts', $changes, ['id' => $id]);
    }

    function findByScope(int $customerId, ?string $articleType = null): array {
        $sql = "SELECT * FROM artifacts WHERE is_active = 1
                AND JSON_CONTAINS(meta->'$.scope.customers', ?)";
        $params = [json_encode($customerId)];

        if ($articleType) {
            $sql .= " AND (
                JSON_CONTAINS(meta->'$.scope.article_types', ?)
                OR meta->'$.scope.article_types' IS NULL
            )";
            $params[] = json_encode($articleType);
        }
        return $this->db->query($sql, $params);
    }

    function generateSlug(string $type, string $name): string {
        $slug = mb_strtolower($type) . '-' . mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Eindeutigkeit sicherstellen
        $base = $slug;
        $counter = 1;
        while ($this->db->queryOne("SELECT id FROM artifacts WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}
```

### Phase 1b: Entity-Tabelle initial befuellen

Tokenizer laeuft ueber alle existierenden Texte:
1. `rules.rule_content` — alle Regeltexte
2. `knowledge_entries.content` — alle Wissenseintraege
3. `author_profiles.*` — Schreibstil, Expertise, Beispieltexte, Notizen
4. Kundennamen, Kategorien, Tags aus bestehenden Tabellen

---

### Phase 2: Daten-Migration

Script das bestehende Daten als Artefakte spiegelt (altes System laeuft weiter):

**Regeln** → Artefakt:
```
slug: regel-per-du-schreiben
artifact_type: Regel
entity_content: "142~87~3~901~55~..."
meta: {
    "type": "Regel",
    "name": "Per Du schreiben",
    "category": "Tonalitaet",
    "scope": {
        "customers": [3],
        "article_types": ["Blogpost", "Ratgeber"],
        "exclude_types": ["Pressemeldung"]
    },
    "source": {"table": "rules", "id": 1}
}
```

**Wissen** → Artefakt:
```
slug: wissen-seo-grundlagen
artifact_type: Wissen
entity_content: "8~201~44~..."
meta: {
    "type": "Wissen",
    "title": "SEO-Grundlagen",
    "tags": ["SEO", "Content-Marketing"],
    "scope": {"customers": [3, 5]},
    "source": {"table": "knowledge_entries", "id": 42}
}
```

**Autoren** → Artefakt:
```
slug: autor-dr-schmidt
artifact_type: Autor
entity_content: "33~7~102~..."
meta: {
    "type": "Autor",
    "name": "Dr. Schmidt",
    "tone": "friendly",
    "perspective": "Ich-Form",
    "scope": {"customers": [3]},
    "source": {"table": "author_profiles", "id": 7}
}
```

**Inhalte die gleichzeitig mehrere Typen sind:**
```
slug: wissen-10-tipps-bessere-blogposts
artifact_type: Wissen
entity_content: "..."
meta: {
    "type": ["Wissen", "Stilbeispiel", "Referenzartikel"],
    "title": "10 Tipps fuer bessere Blogposts",
    "demonstrates": ["kurze-absaetze", "du-ansprache"],
    "topics": ["Content Marketing"],
    "author_ref": "artifact:autor-dr-schmidt",
    "scope": {"customers": [3], "article_types": ["Blogpost"]}
}
```

---

### Phase 3: API + Artefakt-Browser UI

**Backend:**
- `api/v1/admin/artifacts.php` — REST API (GET list, GET detail, POST, PUT, DELETE)
- `api/v1/admin/entities.php` — Entity-Suche + Statistik
- Neue Routes in `api/handler.php`
- Route-Controller in `core/App.php`

**UI: Einheitlicher Artefakt-Browser (`/admin/artifacts`)**

Ersetzt langfristig die separaten Views fuer Regeln, Wissen und Autoren.

```
+--------------------------------------------------+
| Artefakte                    [+ Neues Artefakt]   |
+--------------------------------------------------+
| Filter: [Typ v] [Kunde v] [Suche...]  [Status v] |
+--------------------------------------------------+
| Typ       | Name              | Scope   | Datum   |
|-----------|-------------------|---------|---------|
| Regel     | Per Du schreiben  | Thoxan  | 12.02.  |
| Wissen    | SEO-Grundlagen    | Alle    | 11.02.  |
| Autor     | Dr. Schmidt       | Thoxan  | 10.02.  |
| Kontext   | Thoxan Blog       | Thoxan  | 09.02.  |
+--------------------------------------------------+
```

**Detail/Edit-Ansicht (Modal oder Slide-Panel):**
- Typ-Selektor: Regel, Wissen, Autor, Kontext, ... oder frei
- Scope-Editor: Kunden (Dropdown), Artikeltypen (Multi-Select), Ausnahmen
- Inhalt-Editor: Angepasst je nach Typ
  - Regel: Regeltext + Kategorie
  - Wissen: Titel + Langtext + Tags
  - Autor: Schreibstil + Tonalitaet + Beispieltext
  - Frei: JSON-Editor
- Verknuepfungen: Welche anderen Artefakte sind verbunden (klickbar)
- Entity-Ansicht: Welche Entitaeten stecken im Inhalt
- Versionshistorie: Aeltere Versionen einsehen + Rollback

**Typ-Badges** mit Farben (wie bestehende Status-Badges):
Regel = blau, Wissen = gruen, Autor = orange, Kontext = lila

---

### Phase 4: Alte Tabellen abbauen (langfristig)

Wenn das Artefakt-System bewaehrt ist, entfallen ~18 Tabellen:

```
ENTFAELLT:
rules, rule_types, rule_categories, rule_suggestions
customer_rules, project_rules, order_rule_suggestions
knowledge_entries, knowledge_bases, knowledge_categories, knowledge_tags
knowledge_entry_tags, customer_knowledge, project_knowledge
author_profiles
contexts, context_items
styles, links

BLEIBT:
users, sessions, user_customers (Auth)
customers (Kundenstamm)
projects, orders, article_versions, order_versions, article_sections (Kern-Workflow)
ai_models, settings (Systemkonfiguration)
usage_logs, usage_summary (Logs)
generation_jobs (Job-Queue)
chat_messages, order_chat_messages (Chat)
article_feedback, section_feedback, internal_feedback (Feedback)
daily_motivations
```

**Ergebnis**: Von 39 Tabellen auf ~21 + 3 neue (entities, artifacts, artifact_versions) = 24

---

## Spaetere Erweiterungen (nicht Teil dieser Umsetzung)

- **Hashing/Secure**: Entity-Status "secure" mit SHA-256 Hash, KI sieht nur Hashes
- **Intent-System**: Absichtserklaerung vor Aenderungen via Claude Code Hooks
- **Root-of-Everything**: Ein Einstiegs-Artefakt das alle anderen navigiert
- **Graph-DB**: Fuer komplexe relationale Queries zwischen Artefakten
- **Vektorsuche**: Embedding pro Artefakt fuer semantische Suche
- **Impact-Benachrichtigung**: Artefakt aendert sich → verbundene Artefakte werden informiert

---

## Verifikation

1. **Tabellen**: `entities`, `artifacts`, `artifact_versions` existieren
2. **Tokenizer**: Text rein → Entity-IDs → Text zurueck (verlustfrei)
3. **Migration**: Eine Regel als Artefakt spiegeln, entity_content pruefen
4. **API**: CRUD auf `/admin/artifacts` funktioniert
5. **UI**: Artefakt-Browser: Liste, Filter, Erstellen, Bearbeiten, Versionshistorie
6. **Impact**: Entity suchen → alle zugehoerigen Artefakte finden
7. **Scope**: `findByScope(customerId, articleType)` liefert korrekte Artefakte
