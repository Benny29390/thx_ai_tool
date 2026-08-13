# Entity-Artefakt-Modell: Konzept & Kritische Analyse

Basierend auf dem Zoom-Call der KI-Sprechstunde und kritischer Bewertung, was davon
fuer das KI Text Tool sinnvoll umsetzbar ist.

---

## 1. Das Originalkonzept (wie im Zoom-Call beschrieben)

### Entitaeten = JEDES Wort

Im Originalmodell ist eine Entitaet nicht ein "Tag" oder "Konzept", sondern **jedes
einzelne Wort/Token**:

```
Entitaeten-Tabelle:
ID | value     | hash          | status
1  | Eberhard  | a7f3c...      | active
2  | hat       | 9b2e1...      | active
3  | einen     | c4d8f...      | active
4  | Kaffee    | 1e6a2...      | active
```

- Ca. 1.5 Millionen Eintraege (350k Deutsch, 300k Englisch, plus Programmiersprachen)
- Status: `active`, `blacklist`, `secure`
- `secure` = gehashed, damit externe KI-Systeme nie mit echten Daten in Beruehrung kommen
- Jedes Wort existiert genau EINMAL in der Entitaeten-Tabelle

### Artefakte = Entity-IDs in Sequenz

Ein Artefakt speichert KEINE JSON-Objekte, sondern **Entitaets-IDs in Reihenfolge**:

```
Artefakt-Tabelle:
ID | content
1  | 1~2~3~4        (= "Eberhard hat einen Kaffee")
```

Alternative: Relationale Zuordnung ueber eine Junction-Table (Artefakt-ID + Entity-ID +
Position). Wird aber nicht empfohlen wegen Performance bei Aenderungen.

### Selbstbeschreibung

Ein Artefakt beschreibt sich selbst, indem es Entitaeten enthaelt, die seinen Typ
definieren. Wenn Entitaet 7 = "Regel", dann sagt ein Artefakt "ich bin 7" und damit
"ich bin eine Regel".

### Der "Root of Everything"

Beim Start laedt Claude Code genau EIN Artefakt (z.B. "KI-Glove"). Dieses Artefakt
sagt: "je nach Aufgabe laedst du dir die erforderlichen Artefakte." Es wird NICHT alles
gelesen, sondern die KI wird gezielt zu den richtigen Artefakten navigiert.

### Intent-System

Vor jeder Dateiaenderung:
1. KI gibt eine Absichtserklaerung (Intent) ab
2. System validiert den Intent
3. System gibt ein Token zurueck
4. Erst mit gueltigem Token kann die Aenderung durchgefuehrt werden
5. Jede Operation ist automatisch dokumentiert

### Claude Code Hooks (nicht PHP-Hooks!)

Ueber `.claude/hooks.json`:
- **Pre-Edit Hook**: Vor jeder Datei-Aenderung liest Claude das Artefakt der Datei
  (wer bin ich, was kann ich, welche Funktionen habe ich, mit wem verbunden)
- **Post-Edit Hook**: Nach der Aenderung wird das Artefakt aktualisiert
- Impact-Analyse: Welche anderen Artefakte/Dateien sind betroffen?

### Skalierung (getestet)

- 10 Milliarden Artefakte + 10 Millionen Entitaeten auf Hetzner-Server
- Fuer diese Groesse: Engine von Python nach C portiert
- Tech-Stack: MariaDB (Struktur) + Redis (Cache) + Qdrant (Vektoren) + Graph-DB (Relationen)

---

## 2. Kritische Bewertung: Was passt, was nicht?

### Was der erste Plan FALSCH gemacht hat

**Fehler 1: JSON statt Entity-ID-Sequenzen**

Der erste Plan hat `content JSON NOT NULL` vorgeschlagen. Das ist ein Dokumenten-Datenbank-
Ansatz (wie MongoDB), NICHT das Entity-Artefakt-Modell. Im Original besteht der Inhalt
aus Entity-IDs (`1~2~3~4`), nicht aus JSON-Objekten.

**Fehler 2: Entitaeten als "Tags/Konzepte" verharmlost**

Der erste Plan sagte: "Pragmatisch: Entitaeten = Tags/Konzepte/Schlagwoerter, nicht
einzelne Woerter." Das widerspricht dem Originalkonzept, wo explizit JEDES Wort eine
Entitaet ist.

**Fehler 3: Security-Konzept ignoriert**

Entity-Status (active/blacklist/secure) und Hash-basierter Zugriff fuer KI-Systeme
wurden komplett uebergangen. Im Original ein zentrales Feature: "Externe KI-Systeme
kommen nie mit echten Daten in Beruehrung."

**Fehler 4: Root-of-Everything und Intent-System fehlen**

Zwei zentrale Steuerungsmechanismen des Originalkonzepts wurden nicht beschrieben.

### Was der erste Plan RICHTIG gemacht hat

- Die 4 Schmerzpunkte sind korrekt identifiziert (Klassifikation, M2M-Explosion,
  Kontextabhaengigkeit, Schema-Starrheit)
- Scope-basierte Kontextsteuerung loest Thomas' tatsaechliches Problem
- Parallelbetrieb neben dem alten System ist der richtige Ansatz
- Die Tabellen-Analyse (was bleibt, was wird Artefakt) ist richtig

---

## 3. Die ehrliche Frage: Volles Modell oder pragmatische Adaption?

### Das volle Entity-Artefakt-Modell

**Vorteile:**
- Jedes Wort ist steuerbar (Blacklist, Secure)
- Impact-Analyse auf Wort-Ebene ("Aendere ich 'Adresse', wo ueberall wirkt sich das aus?")
- KI sieht nur Hashes, nie echte Daten (Security/Privacy)
- Komplett technologieagnostisch

**Nachteile fuer das KI Text Tool:**
- 1.5M Entitaeten aufbauen und pflegen bevor das System ueberhaupt nutzbar ist
- Performance-Overhead: Jeder Artefakt-Inhalt muss durch Entity-Lookup aufgeloest werden
- Komplexitaet: Fuer ein Content-Tool, das Regeln und Wissen verwaltet, ist Wort-Tokenisierung
  Overkill
- Der Sprecher im Zoom selbst sagt: "Das Entitaet- und Artefakt-System ist eigentlich noch
  nicht Bestandteil von dem, was wir hier machen. Einfach weil der didaktische Sprung fuer
  die allermeisten zu hoch ist."

### Die pragmatische Adaption (JSON-Artefakte)

**Was wir uebernehmen:**
- Alles ist ein Artefakt (eine Tabelle fuer alles)
- Artefakte sind selbstbeschreibend (Typ, Scope, Verknuepfungen im Inhalt)
- Scope-basierte Kontextsteuerung
- Verknuepfungen zwischen Artefakten (statt M2M-Tabellen)

**Was wir NICHT uebernehmen (vorerst):**
- Wort-Tokenisierung (jedes Wort = Entitaet)
- Hash-basierter Zugriff fuer KI
- Entity-ID-Sequenzen als Artefakt-Inhalt
- C-Engine fuer High-Performance

**Wo wir ehrlich abweichen:**
Der JSON-Ansatz ist KEIN Entity-Artefakt-Modell im Sinne des Originals. Es ist ein
**selbstbeschreibendes Dokumentenmodell**, das die PHILOSOPHIE des Artefakt-Denkens
uebernimmt (alles ist ein Artefakt, Scope statt feste Zuordnung), aber die IMPLEMENTIERUNG
vereinfacht (JSON statt Entity-IDs). Das ist eine bewusste Entscheidung fuer Pragmatismus.

### Warum die Abweichung vertretbar ist

1. Thomas' Schmerzpunkte werden geloest (Klassifikation, Scope, M2M-Explosion)
2. Das System ist spaeter erweiterbar auf das volle Modell
3. Der Zoom-Sprecher selbst sagt: "Fangt erst mal an. Am Anfang macht man eine Tabelle,
   da sind die Tasks drin und eine Tabelle, da sind die Templates drin."
4. Eine Entity-Tabelle mit 1.5M Eintraegen bringt fuer Content-Produktion keinen
   unmittelbaren Mehrwert

---

## 4. Ist-Zustand: Das aktuelle System (39 Tabellen)

```
KERN (4)          users, customers, user_customers, sessions
CONTENT (7)       projects, orders, article_versions, order_versions,
                  article_sections, contexts, context_items
WISSEN (7)        knowledge_bases, knowledge_entries, knowledge_categories,
                  knowledge_tags, knowledge_entry_tags, customer_knowledge,
                  project_knowledge
REGELN (7)        rules, rule_types, rule_categories, rule_suggestions,
                  customer_rules, project_rules, order_rule_suggestions
AUTOREN (1)       author_profiles
CHAT/FEEDBACK (5) chat_messages, order_chat_messages, article_feedback,
                  section_feedback, internal_feedback
INFRA (8)         generation_jobs, ai_models, styles, links, settings,
                  daily_motivations, usage_logs, usage_summary
```

### Die 4 Schmerzpunkte (aus Thomas' Perspektive)

**1. Klassifikationsproblem**
"Ist das eine Regel, ein Wissensdatenbank-Eintrag oder ein Beispielartikel?"
Ein "Schreibe immer per Du" koennte in `rules`, `knowledge_entries` oder
`author_profiles.notes` stehen. Das Schema erzwingt eine Entscheidung.

**2. M2M-Tabellen-Explosion**
6 reine Zuordnungstabellen: `customer_rules`, `customer_knowledge`, `project_rules`,
`project_knowledge`, `knowledge_entry_tags`, `order_rule_suggestions`. Jede neue
Dimension (Artikeltyp, Kanal) braeuchte eine weitere M2M-Tabelle.

**3. Kontextabhaengigkeit nicht abbildbar**
"Schreibe per Du" soll nur gelten bei Kunde=Thoxan UND Artikeltyp=Blogpost UND
Kanal != Pressemeldung. `customer_rules` kann nur "Regel X gilt fuer Kunde Y" —
keine weiteren Dimensionen.

**4. Schema-Starrheit**
Neues Feld? ALTER TABLE. Neuer Typ? ENUM erweitern. Neue Beziehung? Neue Tabelle
+ Migration + API + UI.

---

## 5. Konkreter Plan: Pragmatische Artefakt-Adaption

### Phase 1: Artefakt-Tabelle anlegen (neben bestehendem System)

```sql
CREATE TABLE artifacts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    artifact_type VARCHAR(100) NOT NULL,
    content JSON NOT NULL,
    scope JSON DEFAULT NULL,
    related_artifacts JSON DEFAULT NULL,
    -- Bridge zum alten System
    source_table VARCHAR(100) DEFAULT NULL,
    source_id INT DEFAULT NULL,
    --
    is_active BOOLEAN DEFAULT TRUE,
    customer_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (artifact_type),
    INDEX idx_customer (customer_id),
    INDEX idx_source (source_table, source_id),
    INDEX idx_active_type (is_active, artifact_type)
);
```

Bewusst KEINE Entity-Tabelle in Phase 1. Die "Entitaeten" sind implizit in den
JSON-Inhalten der Artefakte enthalten.

### Phase 2: Bestehende Daten als Artefakte spiegeln

**Regeln -> Artefakte:**
```json
{
  "type": "Regel",
  "name": "Per Du schreiben",
  "category": "Tonalitaet",
  "content": "Verwende die Du-Ansprache statt Sie-Form.",
  "scope": {
    "customers": [3],
    "article_types": ["Blogpost", "Ratgeber"],
    "exclude_types": ["Pressemeldung"]
  },
  "priority": "high",
  "source": {"table": "rules", "id": 1}
}
```

**Wissenseintraege -> Artefakte:**
```json
{
  "type": "Wissen",
  "title": "SEO-Grundlagen fuer Blogposts",
  "content": "...",
  "tags": ["SEO", "Content-Marketing"],
  "scope": {
    "customers": [3, 5],
    "projects": [12]
  },
  "source": {"table": "knowledge_entries", "id": 42}
}
```

**Autorenprofile -> Artefakte:**
```json
{
  "type": "Autor",
  "name": "Dr. Maria Schmidt",
  "writing_style": "Lockerer, direkter Stil mit kurzen Saetzen...",
  "tone": "friendly",
  "perspective": "Ich-Form",
  "expertise": ["Content Marketing", "SEO"],
  "example_text": "...",
  "scope": {
    "customers": [3]
  },
  "source": {"table": "author_profiles", "id": 7}
}
```

**Inhalt der GLEICHZEITIG Wissen UND Beispiel ist:**
```json
{
  "type": ["Wissen", "Stilbeispiel", "Referenzartikel"],
  "title": "10 Tipps fuer bessere Blogposts",
  "content": "...",
  "demonstrates": ["kurze-absaetze", "du-ansprache", "aufzaehlungen"],
  "topics": ["Content Marketing"],
  "author_ref": "artifact:autor-dr-schmidt",
  "scope": {
    "customers": [3],
    "article_types": ["Blogpost"]
  }
}
```

### Phase 3: Artefakt-basierte Kontext-Erstellung fuer Auftraege

Statt `context_items` mit starrem ENUM-Typ:

```json
{
  "type": "Kontext",
  "name": "Thoxan Blogpost-Produktion",
  "description": "Alles was die KI braucht fuer Thoxan-Blogposts",
  "contains": [
    {"ref": "artifact:regel-per-du", "role": "Stilregel"},
    {"ref": "artifact:wissen-seo-basics", "role": "Fachwissen"},
    {"ref": "artifact:autor-dr-schmidt", "role": "Autorenprofil"},
    {"ref": "artifact:template-blogpost-thoxan", "role": "Template"}
  ],
  "scope": {
    "customers": [3],
    "article_types": ["Blogpost"]
  }
}
```

Bei einem neuen Auftrag:
1. Lese Kunde + Artikeltyp
2. `SELECT * FROM artifacts WHERE is_active=1 AND JSON_CONTAINS(scope->'$.customers', '3')`
3. Alle passenden Artefakte werden automatisch als Kontext geladen
4. Die KI weiss durch die Selbstbeschreibung, was jedes Artefakt ist

### Phase 4: Schrittweiser Abbau alter Tabellen

Wenn Artefakt-System bewaehrt, koennen folgende Tabellen entfallen:

| Entfaellt | Ersetzt durch | M2M die wegfallen |
|-----------|---------------|-------------------|
| `rules`, `rule_types`, `rule_categories` | Artefakt "Regel" | `customer_rules`, `project_rules` |
| `knowledge_entries`, `knowledge_categories`, `knowledge_tags` | Artefakt "Wissen" | `customer_knowledge`, `project_knowledge`, `knowledge_entry_tags` |
| `author_profiles` | Artefakt "Autor" | - |
| `contexts`, `context_items` | Artefakt "Kontext" | - |
| `styles` | Artefakt "Stil" | - |
| `links` | Artefakt "Link" | - |
| `rule_suggestions`, `order_rule_suggestions` | Artefakt "Regelvorschlag" | - |

**Ergebnis**: ~18 Tabellen weniger. Alle M2M-Tabellen entfallen.

**Was BLEIBT als feste Tabellen:**
- `users`, `sessions`, `user_customers` (Auth-System)
- `customers` (Kundenstamm)
- `projects`, `orders`, `article_versions`, `order_versions`, `article_sections` (Kern-Workflow)
- `ai_models`, `settings` (Systemkonfiguration)
- `usage_logs`, `usage_summary` (Append-only Logs)
- `generation_jobs` (Job-Queue)
- `chat_messages`, `order_chat_messages` (Chat-Historie)
- `article_feedback`, `section_feedback`, `internal_feedback` (Feedback)
- `daily_motivations` (Tagessprueche)

---

## 6. Spaetere Erweiterungen (nicht Teil der ersten Umsetzung)

### Entity-System (wenn benoetigt)

Falls spaeter Impact-Analyse auf Wort-Ebene oder Hash-basierter KI-Zugriff gewuenscht:

```sql
CREATE TABLE entities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(500) NOT NULL,
    hash CHAR(64) NOT NULL,
    status ENUM('active','blacklist','secure') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_hash (hash),
    INDEX idx_value (value(100)),
    INDEX idx_status (status)
);
```

### Vektorsuche (Qdrant oder aehnlich)

Jedes Artefakt bekommt optional einen Embedding-Vektor. Ermoeglicht semantische Suche:
"Finde Artefakte die aehnlich sind zu 'lockerer Schreibstil mit kurzen Saetzen'".

### Graph-Datenbank

Fuer komplexe relationale Abfragen zwischen Artefakten:
- "Welche Regeln hat Kunde X, die mit Autor Y verkuenpft sind?"
- Taxonomische und ontologische Abfragen

### Claude Code Hooks / Intent-System

`.claude/hooks.json` einrichten:
- Pre-Edit: Artefakt der Datei lesen (Kontext verstehen)
- Post-Edit: Artefakt aktualisieren (Selbstdokumentation)
- Intent-Validierung: Absichtserklaerung vor jeder Aenderung

---

## 7. Was das konkret fuer Thomas/Benni bedeutet

### Vorher (jetzt)

Thomas will eine Regel "Per Du" anlegen, die nur fuer Thoxan-Blogposts gilt:
1. Regel in `rules` anlegen
2. `customer_rules` Zuordnung machen
3. Feststellen: Es gibt keine Moeglichkeit, den Artikeltyp einzuschraenken
4. Workaround: In `rule_content` reinschreiben "Nur fuer Blogposts"
5. Die KI muss das aus dem Freitext interpretieren

### Nachher (mit Artefakten)

Thomas legt ein Artefakt an:
1. Typ "Regel", Name "Per Du", Scope = {customer: Thoxan, types: [Blogpost]}
2. Fertig. Das System weiss automatisch, wann die Regel gilt.

### Vorher: Redakteurin laedt Beispielartikel hoch

1. Wohin? `knowledge_entries`? Aber es ist ein Stilbeispiel...
2. Oder `author_profiles.example_text`? Aber nur einer passt dort...
3. Entscheidung erzwungen, Information geht verloren

### Nachher: Redakteurin laedt Beispielartikel hoch

1. System erstellt Artefakt mit type=["Wissen","Stilbeispiel"]
2. KI analysiert automatisch: Topics, Stil-Merkmale, passender Autor
3. Beim naechsten Auftrag fuer den gleichen Kunden + Thema wird es automatisch gefunden

### Vorher: Neuer Kunde

1. Regeln in `rules` anlegen + `customer_rules` zuordnen
2. Wissen in `knowledge_entries` anlegen + `customer_knowledge` zuordnen
3. Autorenprofil in `author_profiles` anlegen
4. Kontext in `contexts` + `context_items` aufbauen
5. 4 verschiedene Tabellen-Paare befuellen

### Nachher: Neuer Kunde

1. Artefakte anlegen mit `scope: {customer: "NeuerKunde"}`
2. Fertig. Alles in einer Tabelle, alles per Scope gesteuert.

---

## 8. Offene Entscheidungen

1. **UI-Strategie**: Brauchen wir einen generischen "Artefakt-Browser" oder bleiben
   separate Views (Regeln, Wissen, Autoren) als gefilterte Sicht auf die artifacts-Tabelle?

2. **Migration**: Big-Bang oder schrittweise? Empfehlung: Schrittweise. Artefakte
   parallel anlegen, altes System laeuft weiter, bis alles migriert ist.

3. **Suche**: Reicht MariaDB JSON-Suche (`JSON_CONTAINS`, `JSON_EXTRACT`) oder brauchen
   wir von Anfang an Volltextsuche / Generated Columns?

4. **Artefakt-Slug-Konvention**: Wie werden Slugs generiert? Vorschlag:
   `{type}-{kurzer-name}`, z.B. `regel-per-du`, `autor-dr-schmidt`, `wissen-seo-basics`

5. **Versionierung**: Sollen Artefakte versioniert werden? (Aenderungshistorie)

---

## Zusammenfassung

| Aspekt | Jetzt | Mit Artefakt-Adaption |
|--------|-------|----------------------|
| "Regel oder Wissen?" | Entweder-oder | Kann beides sein |
| Kontextabh. Regel | Nicht moeglich | Scope-Definition |
| Neue Beziehung | Neue M2M-Tabelle | Im Artefakt-Scope |
| Neuer Inhaltstyp | ALTER TABLE + Code | Neues Artefakt |
| Schema-Migration | Bei jeder Aenderung | Nie (JSON) |
| M2M-Tabellen | 6+ Stueck | 0 |
| Tabellen gesamt | 39 | ~23 (davon 1 artifacts) |

**Wichtig**: Das ist NICHT das volle Entity-Artefakt-Modell aus dem Zoom-Call.
Es ist eine pragmatische Adaption, die die Philosophie uebernimmt (alles ist ein
Artefakt, Selbstbeschreibung, Scope statt M2M), aber die Implementierung vereinfacht
(JSON statt Entity-ID-Sequenzen). Das volle Entity-System kann spaeter aufgebaut werden,
wenn Impact-Analyse auf Wort-Ebene oder Hash-basierte KI-Sicherheit benoetigt wird.
