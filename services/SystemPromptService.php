<?php
/**
 * SystemPromptService — zentrale Verwaltung aller LLM-System-Prompts.
 *
 * Jeder Prompt hat einen Code-Standard (defaults()) und kann optional in der Tabelle
 * `system_prompts` ueberschrieben werden. get() liefert den Override, falls gesetzt und
 * nicht leer, sonst den Code-Standard. So bleibt das System auch ohne DB-Eintrag/Migration
 * funktionsfaehig (sicherer Rueckfall), und Admins koennen Prompts zentral pflegen.
 *
 * Verwaltung: /admin/settings?tab=prompts
 */

namespace Services;

use Core\Database;

class SystemPromptService
{
    /** @var array<string,string>|null Cache der Overrides (prompt_key => content) */
    private static ?array $cache = null;

    /**
     * Code-Standards aller registrierten Prompts.
     * key => [label, description, category, default]
     *
     * Schrittweise befuellt — weitere Prompt-Stellen koennen hier ergaenzt und an
     * ihrer Aufruf-Stelle auf SystemPromptService::get('<key>') umgestellt werden.
     */
    public static function defaults(): array
    {
        return [
            'chat_default' => [
                'label'       => 'Chat-Standard-Prompt',
                'description' => 'Grundanweisung bei jeder Chat-Anfrage (Rolle, Stil, Faktentreue). Greift, wenn eine Unterhaltung keinen eigenen Prompt hat. Regeln, Kunden-Kontext und gefundenes Wissen werden automatisch ergaenzt.',
                'category'    => 'Chat',
                'default'     =>
                    "Du bist der KI-Assistent von Thoxan und unterstuetzt das Team beim Schreiben, "
                    . "Recherchieren und Beantworten von Fragen. Antworte praezise, sachlich und klar "
                    . "strukturiert auf Deutsch.\n\n"
                    . "Faktentreue (sehr wichtig): Stuetze Dich ausschliesslich auf belegte Informationen "
                    . "— auf das bereitgestellte Wissen aus der Wissensdatenbank, die geltenden Regeln, "
                    . "angehaengte Dateien und den bisherigen Gespraechsverlauf. Erfinde keine Fakten, "
                    . "Namen, Zahlen oder Quellen. Wenn die noetige Information nicht vorliegt, sage das "
                    . "ehrlich und benenne, was fehlt, statt zu raten. Kennzeichne klar, wenn etwas eine "
                    . "Einschaetzung und kein Beleg ist.",
            ],
            'auto_classifier' => [
                'label'       => 'Automatische Modell-Auswahl',
                'description' => 'Waehlt im Automatik-Modus das passende Modell fuer eine Frage. Antwortet nur mit JSON. Kurz halten.',
                'category'    => 'Chat',
                'default'     => 'Du bist ein Routing-Assistent. Antworte NUR mit validem JSON.',
            ],
            'customer_detection' => [
                'label'       => 'Kunden-Erkennung',
                'description' => 'Erkennt, welcher Kunde in einer Nachricht gemeint ist (fuer die Wissens-Filterung). Antwortet nur mit JSON.',
                'category'    => 'Chat',
                'default'     => 'Du klassifizierst Nutzer-Nachrichten danach, welcher Kunde aus einer gegebenen Liste gemeint ist. Antwortest IMMER mit gueltigem JSON.',
            ],
            'knowledge_grounding' => [
                'label'       => 'Wissens-Anweisung (Faktentreue im RAG)',
                'description' => 'Wird vor die gefundenen Wissens-Chunks gesetzt und weist die KI an, sich nur auf das Wissen zu stuetzen, statt zu raten. Die Markierungen "--- WISSEN ---" werden automatisch ergaenzt.',
                'category'    => 'Wissen',
                'default'     =>
                    "WICHTIG: Beantworte die Frage des Nutzers VORRANGIG anhand des folgenden "
                    . "WISSEN aus unserer Wissensdatenbank. Wenn die Antwort darin steht, stütze Dich "
                    . "ausschließlich darauf und gib NICHT Dein allgemeines Trainingswissen wieder, das dem "
                    . "WISSEN widerspricht. Steht die Information nicht im WISSEN, sage das ehrlich, statt zu raten.",
            ],
            'dual_compare' => [
                'label'       => 'Dual-Vergleich',
                'description' => 'Vergleicht im Dual-Chat zwei Antworten mit einem dritten Modell. Die beiden Antworten + die User-Frage werden automatisch angehaengt.',
                'category'    => 'Chat',
                'default'     =>
                    "Du bist ein neutraler KI-Analyst. Vergleiche die beiden Antworten sachlich und praegnant.\n\n"
                    . "Struktur deiner Analyse:\n"
                    . "1. **Gemeinsamkeiten** — Wo stimmen beide ueberein?\n"
                    . "2. **Unterschiede** — Wo weichen sie ab? (Inhalt, Stil, Tiefe, Genauigkeit)\n"
                    . "3. **Staerken & Schwaechen** — Was macht welche Antwort besser/schlechter?\n"
                    . "4. **Fazit** — Kurze Empfehlung, welche Antwort fuer den User nuetzlicher ist und warum.\n\n"
                    . "Sei konkret, zitiere wenn noetig, und bleibe neutral.",
            ],
        ];
    }

    /** Overrides aus der DB laden (mit Cache). Fehlt die Tabelle, bleibt der Cache leer. */
    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        self::$cache = [];
        try {
            $rows = Database::getInstance()->query("SELECT prompt_key, content FROM system_prompts");
            foreach ($rows as $r) {
                self::$cache[$r['prompt_key']] = (string)$r['content'];
            }
        } catch (\Throwable $e) {
            // Tabelle evtl. noch nicht migriert — Code-Standards greifen.
        }
        return self::$cache;
    }

    /**
     * Effektiver Prompt-Text: DB-Override (falls vorhanden & nicht leer), sonst Code-Standard.
     */
    public static function get(string $key): string
    {
        $overrides = self::load();
        if (isset($overrides[$key]) && trim($overrides[$key]) !== '') {
            return $overrides[$key];
        }
        return self::defaults()[$key]['default'] ?? '';
    }

    /** Reiner Code-Standard (ohne Override) — fuer "Standard wiederherstellen" in der UI. */
    public static function defaultFor(string $key): string
    {
        return self::defaults()[$key]['default'] ?? '';
    }

    /** Hat dieser Prompt einen aktiven Override (weicht also vom Code-Standard ab)? */
    public static function hasOverride(string $key): bool
    {
        $overrides = self::load();
        return isset($overrides[$key]) && trim($overrides[$key]) !== '';
    }

    /** Override setzen/aktualisieren. Schreibt zugleich eine Version in die Historie. */
    public static function set(string $key, string $content, ?string $note = null): void
    {
        $db = Database::getInstance();
        $uid = self::currentUserId();
        $db->execute(
            "INSERT INTO system_prompts (prompt_key, content, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP, updated_by = VALUES(updated_by)",
            [$key, $content, $uid]
        );
        self::recordVersion($key, $content, $note ?? 'gespeichert', $uid);
        self::$cache = null;
    }

    /** Override entfernen — der Code-Standard greift wieder; als Version festgehalten. */
    public static function reset(string $key): void
    {
        $db = Database::getInstance();
        $uid = self::currentUserId();
        $db->execute("DELETE FROM system_prompts WHERE prompt_key = ?", [$key]);
        // Den nun wieder geltenden Code-Standard als Version festhalten (Nachvollziehbarkeit).
        self::recordVersion($key, self::defaultFor($key), 'Standard wiederhergestellt', $uid);
        self::$cache = null;
    }

    /** Versionssnapshot schreiben (eigener try/catch — fehlt die Tabelle, nicht kippen). */
    private static function recordVersion(string $key, string $content, string $note, ?int $uid): void
    {
        try {
            Database::getInstance()->execute(
                "INSERT INTO system_prompt_versions (prompt_key, content, note, created_by) VALUES (?, ?, ?, ?)",
                [$key, $content, $note, $uid]
            );
        } catch (\Throwable $e) {
            error_log('SystemPromptService Versions-Fehler: ' . $e->getMessage());
        }
    }

    /** Versionshistorie eines Prompts (neueste zuerst), inkl. Nutzername. */
    public static function history(string $key, int $limit = 50): array
    {
        try {
            return Database::getInstance()->query(
                "SELECT v.id, v.content, v.note, v.created_at, v.created_by, u.name AS user_name
                 FROM system_prompt_versions v
                 LEFT JOIN users u ON u.id = v.created_by
                 WHERE v.prompt_key = ?
                 ORDER BY v.id DESC
                 LIMIT " . (int)$limit,
                [$key]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Inhalt einer bestimmten Version (zum Wiederherstellen). */
    public static function versionContent(string $key, int $versionId): ?string
    {
        try {
            $row = Database::getInstance()->queryOne(
                "SELECT content FROM system_prompt_versions WHERE id = ? AND prompt_key = ?",
                [$versionId, $key]
            );
            return $row ? (string)$row['content'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function currentUserId(): ?int
    {
        try {
            if (class_exists('\\Core\\Auth')) {
                $id = \Core\Auth::id();
                return $id ? (int)$id : null;
            }
        } catch (\Throwable $e) {}
        return null;
    }
}
