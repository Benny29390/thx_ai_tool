<?php

namespace Services;

use Core\Database;

require_once SERVICES_PATH . '/DocumentProcessor.php';
require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/CustomerCardService.php';
require_once SERVICES_PATH . '/UserDirectoryMatcher.php';

/**
 * Importiert einen Projekt-Steckbrief (DOCX/PDF/TXT/MD) als Karten-Vorschlag.
 *
 * Pipeline:
 *   1) Upload + Text-Extraktion
 *   2) LLM strukturiert den Text in Karten-Vorschläge (JSON nach Schema)
 *   3) UI zeigt Vorschau mit Checkbox-Auswahl je Karte
 *   4) Übernahme legt customer_cards an + triggert Auto-Vectorize
 */
class SteckbriefImportService
{
    private Database $db;
    private string $openaiKey;
    private string $uploadDir;

    public function __construct(Database $db, string $openaiKey, string $uploadDir)
    {
        $this->db = $db;
        $this->openaiKey = $openaiKey;
        $this->uploadDir = rtrim($uploadDir, '/');
        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0775, true);
        }
    }

    /**
     * Speichert die hochgeladene Datei und legt einen Import-Datensatz an.
     */
    public function uploadFile(int $customerId, array $file, int $userId): array
    {
        $orig = (string) ($file['name'] ?? 'datei');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if (!$tmp || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Upload fehlgeschlagen');
        }
        if ($size <= 0 || $size > 30 * 1024 * 1024) {
            throw new \RuntimeException('Datei zu gross (max 30 MB)');
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $orig);
        $name = 'sb-' . $customerId . '-' . time() . '-' . substr(md5($orig . random_bytes(4)), 0, 8) . '-' . $safe;
        $dest = $this->uploadDir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($dest) ?: '') : '';

        $importId = $this->db->insert('customer_steckbrief_imports', [
            'customer_id' => $customerId,
            'original_filename' => $orig,
            'file_path' => $dest,
            'mime_type' => $mime,
            'status' => 'uploaded',
            'created_by' => $userId,
        ]);

        return $this->get($importId);
    }

    /**
     * Speichert reinen Text als Pseudo-Datei und legt einen Import-Datensatz an.
     */
    public function ingestText(int $customerId, string $text, string $label, int $userId): array
    {
        $text = trim($text);
        if (mb_strlen($text) < 30) throw new \RuntimeException('Text zu kurz');
        $name = 'sb-' . $customerId . '-' . time() . '-text.txt';
        $dest = $this->uploadDir . '/' . $name;
        file_put_contents($dest, $text);

        $importId = $this->db->insert('customer_steckbrief_imports', [
            'customer_id' => $customerId,
            'original_filename' => trim($label) !== '' ? $label . '.txt' : 'Text-Eingabe.txt',
            'file_path' => $dest,
            'mime_type' => 'text/plain',
            'status' => 'uploaded',
            'text_content' => $text,
            'created_by' => $userId,
        ]);

        return $this->get($importId);
    }

    public function get(int $importId): ?array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM customer_steckbrief_imports WHERE id = ?",
            [$importId]
        );
        if (!$row) return null;
        $row['proposed_cards_decoded'] = $row['proposed_cards'] ? (json_decode($row['proposed_cards'], true) ?: []) : [];
        return $row;
    }

    /**
     * Analysiert den Import: Text extrahieren + LLM-Strukturierung.
     */
    public function analyze(int $importId): array
    {
        $row = $this->get($importId);
        if (!$row) throw new \RuntimeException('Import nicht gefunden');

        $this->db->update('customer_steckbrief_imports', ['status' => 'analyzing'], 'id = ?', [$importId]);

        // 1) Text extrahieren (falls noch nicht geschehen)
        $text = (string) ($row['text_content'] ?? '');
        if (mb_strlen($text) < 30) {
            $processor = new DocumentProcessor();
            try {
                $result = $processor->processFile($row['file_path'], $row['mime_type'] ?? '', $row['original_filename']);
                $text = $result['text'] ?? '';
            } catch (\Throwable $e) {
                $this->markFailed($importId, 'Text-Extraktion: ' . $e->getMessage());
                throw new \RuntimeException('Text-Extraktion fehlgeschlagen: ' . $e->getMessage());
            }
            $this->db->update('customer_steckbrief_imports', ['text_content' => $text], 'id = ?', [$importId]);
        }
        if (mb_strlen(trim($text)) < 60) {
            $this->markFailed($importId, 'Zu wenig Text aus dem Dokument extrahiert');
            throw new \RuntimeException('Aus dem Dokument wurde zu wenig Text extrahiert');
        }

        // 2) LLM-Strukturierung
        try {
            $proposed = $this->extractCardsViaLLM($text, (int) $row['customer_id']);
        } catch (\Throwable $e) {
            $this->markFailed($importId, 'LLM: ' . $e->getMessage());
            throw $e;
        }

        $this->db->update('customer_steckbrief_imports', [
            'status' => 'ready',
            'proposed_cards' => json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_message' => null,
        ], 'id = ?', [$importId]);

        return $this->get($importId);
    }

    /**
     * Übernimmt die ausgewählten Vorschläge als customer_cards.
     */
    public function commit(int $importId, array $acceptedIndexes, int $userId, ?CustomerCardService $cardService = null): array
    {
        $row = $this->get($importId);
        if (!$row) throw new \RuntimeException('Import nicht gefunden');
        $proposed = $row['proposed_cards_decoded'] ?? [];
        if (empty($proposed)) throw new \RuntimeException('Keine Vorschlaege vorhanden');

        if ($cardService === null) {
            // Lokal aufbauen
            $emb = null;
            $extract = null;
            $ingest = null;
            try {
                require_once SERVICES_PATH . '/EntityService.php';
                require_once SERVICES_PATH . '/KnowledgeIngestService.php';
                // Embeddings macht der qdrant_sync-Worker via bge-m3 — kein OpenAI-Embed-Call hier.
                $extract = new EntityService($this->db);
                $ingest = new KnowledgeIngestService($this->db, null, $extract);
            } catch (\Throwable $e) { /* ohne Ingest weitermachen */ }
            $cardService = new CustomerCardService($this->db, $ingest);
        }

        $created = [];
        $customerId = (int) $row['customer_id'];
        foreach ($acceptedIndexes as $idx) {
            $idx = (int) $idx;
            if (!isset($proposed[$idx])) continue;
            $p = $proposed[$idx];
            $type = $p['type'] ?? '';
            if (!in_array($type, ['links','richtext','brand','contacts','kpi','tracking_status'], true)) continue;

            $cardId = $cardService->create(
                $customerId,
                $type,
                (string) ($p['title'] ?? ''),
                $userId
            );
            $cardService->update($cardId, [
                'body' => $p['body'] ?? [],
                'target_tab' => $p['target_tab'] ?? 'inhalte',
                'column_idx' => max(1, min(3, (int) ($p['column_idx'] ?? 2))),
                'size_w' => max(1, min(3, (int) ($p['size_w'] ?? 1))),
            ], $userId);
            $created[] = $cardId;
        }

        $this->db->update('customer_steckbrief_imports', ['status' => 'imported'], 'id = ?', [$importId]);
        return ['imported' => count($created), 'card_ids' => $created];
    }

    /**
     * Falls eine contacts-Gruppe Thoxan-User UND echte Externe enthaelt, splitten:
     * Thoxan-User landen in eine 'Intern'-Gruppe, externe in eine 'Kunde'-Gruppe.
     * Gruppen, die bereits sauber sortiert sind, bleiben unveraendert.
     */
    private function splitInternalExternalGroups(array $groups, UserDirectoryMatcher $matcher): array
    {
        $internal = ['title' => 'Intern', 'people' => []];
        $external = ['title' => 'Kunde', 'people' => []];
        $passthrough = [];

        foreach ($groups as $g) {
            $title = (string) ($g['title'] ?? '');
            $audience = $matcher->cardAudience($title);
            // Gruppe ist eindeutig - so lassen
            if ($audience === 'internal' || $audience === 'external') {
                $passthrough[] = $g;
                continue;
            }
            // Mehrdeutig: aufsplitten
            $thoxan = $matcher->filterPeopleByAudience($g['people'] ?? [], 'internal');
            $extern = $matcher->filterPeopleByAudience($g['people'] ?? [], 'external');
            // Wenn die Gruppe sortenrein ist (nur eine Sorte) → so lassen
            if (empty($thoxan) || empty($extern)) {
                $passthrough[] = $g;
                continue;
            }
            $internal['people'] = array_merge($internal['people'], $thoxan);
            $external['people'] = array_merge($external['people'], $extern);
        }

        $result = $passthrough;
        if (!empty($internal['people'])) $result[] = $internal;
        if (!empty($external['people'])) $result[] = $external;
        return $result;
    }

    private function markFailed(int $importId, string $message): void
    {
        $this->db->update('customer_steckbrief_imports', [
            'status' => 'failed',
            'error_message' => mb_substr($message, 0, 1000),
        ], 'id = ?', [$importId]);
    }

    /**
     * Ruft das LLM mit dem extrahierten Text auf und erwartet ein
     * Array von Karten-Vorschlägen. Das Schema deckt alle 8 Karten-Typen ab.
     */
    private function extractCardsViaLLM(string $text, int $customerId): array
    {
        $customer = $this->db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
        $customerName = $customer['name'] ?? '';

        // Text kappen — LLM braucht keinen Roman, 18000 Zeichen reichen weit
        $text = mb_substr($text, 0, 18000);

        $ai = new AIService($this->openaiKey, 'openai');
        $ai->setModel('gpt-4o-mini');
        $ai->setMaxTokens(6000);

        $system = <<<TXT
Du bist Strukturierungs-Assistent fuer Kunden-Steckbriefe.
Eingabe ist ein Projekt-Steckbrief (Word/PDF-Export) eines Kunden.
Aufgabe: Den Text in atomare Karten aufteilen, jede Karte mit einem klar definierten Typ.

Verfuegbare Karten-Typen (genau diese Strings im Feld "type"):
- "links"           — Sammlung von URLs mit Titel (z.B. Quick Links: Looker, Asana, GA4, Search Console, CMS, Hoster)
- "richtext"        — Freitext mit Hierarchie (z.B. Strategische Leitplanken, allgemeine Notizen)
- "brand"           — Markenfarben + Schriften
- "contacts"        — Ansprechpartner-Gruppen mit Personen (Rolle, Name, E-Mail, Telefon, Kuerzel)
- "kpi"             — Kennzahlen, Budgets, Ziele (Label + Wert + Zeitraum + optionales Ziel)
- "tracking_status" — Checkliste: aktiv/fehlt/offen/n/a fuer Tracking-Komponenten

Body-Schema je Typ (genau so im "body" zurueckgeben):
- links:           {"items":[{"title":"...","url":"https://..."}]}
- richtext:        {"html":"<p>...</p><h3>...</h3><ul><li>...</li></ul>"}
- brand:           {"colors":[{"name":"Primaer","value":"#004C9B"}],"fonts":[{"name":"Frutiger","note":"Headlines"}]}
- contacts:        {"groups":[{"title":"Intern","people":[{"role":"...","name":"...","initials":"TK","email":"...","phone":""}]}]}
- kpi:             {"items":[{"label":"Meta-Ads Budget","value":"3.000 EUR","target":"CPL <= 10 EUR","period":"Monat"}]}
- tracking_status: {"items":[{"label":"GA4 Property","status":"ok","note":"312872182"}]}  // status: ok|fehlt|tbd|na

Ziel-Tab (Feld "target_tab", einer dieser Werte):
- "uebersicht" — Basics, KPI-Ueberblick, wichtige Quick-Links
- "inhalte"    — Ausfuehrliche Notizen, Tracking-Details, Werbekanaele
- "personen"   — alle contacts-Karten
- "dateien"    — nur fuer documents/images (hier nicht erzeugen)
- "marke"      — brand-Karten

Spalte (column_idx 1..3) und Breite (size_w 1..3) sind optional; setze size_w=2 fuer breite Karten (Hero), sonst 1.

Wichtige Regeln:
- Schreibe alle Klartexte mit Du/Dich/Dir Gross.
- KEINE Gedankenstriche (em-dash), stattdessen normales " - " oder Komma.
- Anglizismen vermeiden: "Maßnahme" statt "Campaign", "Vorgang" statt "Process".
- Fuer KPI: nicht jeden Satz als Karte machen, sondern verwandte Kennzahlen zu EINER kpi-Karte gruppieren (z.B. alle Werbekanal-Budgets als items).
- Quick-Links: ALLE URLs einer logischen Gruppe in EINE links-Karte packen. Pro logischer Gruppe (Werbeplattformen, Analytics, Tools, CMS+Hoster) eine eigene Karte ist OK.
- Personen: getrennt nach Gruppen "Intern" (Thoxan-Team) und "Kunde".
- Felder leer lassen wenn nicht im Quelltext, NICHT halluzinieren.
- contacts: NIEMALS Initialen, E-Mail-Adressen oder Telefonnummern halluzinieren. Diese Felder NUR ausfuellen, wenn sie WOERTLICH im Quelltext stehen. Sonst leer lassen - die werden spaeter aus den User-Stammdaten gezogen.

Antwort: NUR ein JSON-Objekt {"cards":[ ... ]}, keine Markdown-Codeblocks, keine Erklaerung.
TXT;

        $user = "Kunde: " . ($customerName !== '' ? $customerName : '(unbekannt)') . "\n\nSteckbrief-Text:\n\n" . $text;

        $resp = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = $resp['content'] ?? '';

        // Robust JSON parsen
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('LLM-Antwort war kein gueltiges JSON');
        }
        $cards = $decoded['cards'] ?? $decoded;
        if (!is_array($cards)) {
            throw new \RuntimeException('LLM-Antwort enthielt keine cards-Liste');
        }

        // Normalisieren + Sanity-Check
        $out = [];
        $validTypes = ['links','richtext','brand','contacts','kpi','tracking_status'];
        $validTabs = ['uebersicht','inhalte','personen','marke'];
        $matcher = new UserDirectoryMatcher($this->db);
        foreach ($cards as $c) {
            $type = $c['type'] ?? '';
            if (!in_array($type, $validTypes, true)) continue;
            $tab = $c['target_tab'] ?? '';
            if (!in_array($tab, $validTabs, true)) {
                $tab = match ($type) {
                    'contacts' => 'personen',
                    'brand' => 'marke',
                    'kpi', 'links' => 'uebersicht',
                    default => 'inhalte',
                };
            }
            $body = is_array($c['body'] ?? null) ? $c['body'] : [];
            // Contacts: gegen users-Tabelle anreichern (Stammdaten ueberschreiben Halluzinationen)
            if ($type === 'contacts' && isset($body['groups']) && is_array($body['groups'])) {
                $body['groups'] = $matcher->enrichGroups($body['groups']);
                // Wenn die KI ein und denselben Pool sowohl Thoxan- als auch Kunden-Personen
                // mischt, splitten wir auf Gruppen-Ebene: Thoxan-User → Gruppe "Intern",
                // Externe → Gruppe "Kunde". Aber nur, falls die KI nicht selbst sauber getrennt hat.
                $body['groups'] = $this->splitInternalExternalGroups($body['groups'], $matcher);
            }
            $out[] = [
                'type' => $type,
                'title' => trim((string) ($c['title'] ?? '')),
                'target_tab' => $tab,
                'column_idx' => max(1, min(3, (int) ($c['column_idx'] ?? 2))),
                'size_w' => max(1, min(3, (int) ($c['size_w'] ?? 1))),
                'body' => $body,
                'reason' => trim((string) ($c['reason'] ?? '')),
            ];
        }
        if (empty($out)) {
            throw new \RuntimeException('LLM konnte keine Karten erkennen');
        }
        return $out;
    }
}
