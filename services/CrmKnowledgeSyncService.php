<?php
/**
 * CrmKnowledgeSyncService
 *
 * Synchronisiert CRM-Kontakte und CRM-Firmen als strukturierte Markdown-
 * Dokumente in die Wissensdatenbank (V2 / Qdrant via knowledge_documents +
 * knowledge_chunks + bge-m3-Embeddings durch den qdrant_sync-Worker).
 *
 * Architektur-Entscheidungen:
 * - Ein Dokument pro Entity (Kontakt bzw. Firma), Cross-Referenzen als Text
 *   (Firmenname, Kontaktnamen) damit die Relation im Chunk semantisch erhalten
 *   bleibt — nicht nur als ID.
 * - Strukturiertes Markdown mit H2-Sektionen, je Sektion ein Chunk (mit hartem
 *   max. 1200 Zeichen — sonst weiter gesplittet).
 * - Customer-Resolver: customers.crm_firma_id JOIN; ohne Match → Thoxan-Bucket.
 * - Bypass von KnowledgeIngestService::commit/reprocess weil deren prepare()
 *   eine teure LLM-Extraktion macht — bei 5000+ Kontakten unbezahlbar. Direkter
 *   Write in knowledge_documents + knowledge_chunks, dann Qdrant-Job enqueuen.
 * - Queue-basierter Live-Sync mit Debounce: aktualisierte Entities werden via
 *   enqueueKontakt/Firma in crm_sync_queue markiert; processQueue() arbeitet
 *   nach Debounce ab. Verhindert Spam bei Inline-Edit-Sessions.
 */

namespace Services;

use Core\Database;

class CrmKnowledgeSyncService
{
    /** Fallback-Kunde wenn keine CRM-Firma einem Kunden zugeordnet ist (Thoxan-Bucket). */
    private const DEFAULT_BUCKET_CUSTOMER_ID = 35; // Thoxan Communications GmbH

    /** System-User-ID für created_by/updated_by bei automatischem Sync. */
    private const SYSTEM_USER_ID = 1;

    /** Max. Zeichen pro Chunk (übergroße Sektionen werden gesplittet). */
    private const CHUNK_MAX_CHARS = 1200;

    public function __construct(
        private Database $db,
        private CrmKontaktService $kontaktService,
        private CrmFirmaService $firmaService
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // CUSTOMER-RESOLVER
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Liefert die customer_id, in deren Wissens-Bucket die CRM-Firma + ihre
     * Kontakte synchronisiert werden. JOIN auf customers.crm_firma_id;
     * wenn kein Match → Default-Bucket (Thoxan).
     */
    public function resolveCustomerForFirma(?int $firmaId): int
    {
        if (!$firmaId) return self::DEFAULT_BUCKET_CUSTOMER_ID;
        $cid = $this->db->queryValue(
            "SELECT id FROM customers WHERE crm_firma_id = ? AND is_active = 1 LIMIT 1",
            [$firmaId]
        );
        return $cid ? (int) $cid : self::DEFAULT_BUCKET_CUSTOMER_ID;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDERER: KONTAKT
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Rendert einen Kontakt als strukturiertes Markdown-Dokument.
     * Returns: ['title', 'description', 'sections' (array of [headline, body]), 'tags', 'firma_id']
     * sections sind die spätere Chunk-Basis (eine H2-Sektion pro Eintrag).
     */
    public function renderKontakt(int $id): ?array
    {
        $k = $this->kontaktService->detail($id);
        if (!$k) return null;

        $vorname = trim((string) ($k['vorname'] ?? ''));
        $nachname = trim((string) ($k['nachname'] ?? ''));
        $name = trim($vorname . ' ' . $nachname);
        if ($name === '') $name = $k['email_primaer'] ?? ('Kontakt #' . $id);

        $firmenname = $k['firmenname'] ?? null;
        $funktion = $k['funktion'] ?? null;

        // Titel: "Kontakt: Name — Funktion bei Firma" (jeder Teil optional)
        $titleSuffix = '';
        if ($funktion && $firmenname) $titleSuffix = $funktion . ' bei ' . $firmenname;
        elseif ($funktion) $titleSuffix = $funktion;
        elseif ($firmenname) $titleSuffix = 'bei ' . $firmenname;
        $title = 'Kontakt: ' . $name . ($titleSuffix ? ' — ' . $titleSuffix : '');

        $description = $this->buildKontaktDescription($k);

        // Sektionen aufbauen — leere Sektionen werden ausgelassen
        $sections = [];

        // === Person ===
        $person = [];
        foreach ([
            'Anrede' => $k['anrede'] ?? null,
            'Titel' => $k['titel'] ?? null,
            'Vorname' => $vorname,
            'Nachname' => $nachname,
            'Funktion' => $funktion,
            'Abteilung' => $k['abteilung'] ?? null,
            'Geburtsdatum' => $this->fmtDate($k['geburtsdatum'] ?? null),
        ] as $label => $val) {
            if ($this->isFilled($val)) $person[] = "- {$label}: {$val}";
        }
        if ($person) $sections[] = ['Person', implode("\n", $person)];

        // === Kontaktdaten ===
        $kontakt = [];
        foreach ([
            'E-Mail primär' => $k['email_primaer'] ?? null,
            'E-Mail Zweit' => $k['email_zweit'] ?? null,
            'Telefon' => $k['telefon'] ?? null,
            'Telefon alt' => $k['telefon_alt'] ?? null,
            'Mobil' => $k['mobil'] ?? null,
            'Fax' => $k['fax'] ?? null,
            'Website' => $k['website'] ?? null,
        ] as $label => $val) {
            if ($this->isFilled($val)) $kontakt[] = "- {$label}: {$val}";
        }
        if ($kontakt) $sections[] = ['Kontaktdaten', implode("\n", $kontakt)];

        // === Profil & Interessen ===
        $profil = [];
        foreach ([
            'Bevorzugtes Thema' => $k['bevorzugtes_thema'] ?? null,
            'Interessen' => $k['interessen'] ?? null,
            'Merkmale' => $k['merkmale'] ?? null,
            'Beschreibung' => $k['beschreibung'] ?? null,
        ] as $label => $val) {
            if ($this->isFilled($val)) $profil[] = "- {$label}: {$val}";
        }
        if ($profil) $sections[] = ['Profil & Interessen', implode("\n", $profil)];

        // === Firma (Verknüpfung) ===
        if ($firmenname) {
            $firmaParts = [];
            $firma = $this->db->queryOne(
                "SELECT firmenname, branche, firmen_typ, website, beschaeftigte FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL",
                [(int) $k['firma_id']]
            );
            if ($firma) {
                $meta = [];
                if (!empty($firma['branche'])) $meta[] = 'Branche: ' . $firma['branche'];
                if (!empty($firma['firmen_typ'])) $meta[] = 'Typ: ' . $firma['firmen_typ'];
                if (!empty($firma['website'])) $meta[] = 'Website: ' . $firma['website'];
                if (!empty($firma['beschaeftigte'])) $meta[] = $firma['beschaeftigte'] . ' Beschäftigte';
                $body = 'Verknüpft mit **' . $firmenname . '**';
                if ($meta) $body .= ' (' . implode(' · ', $meta) . ')';
                $body .= ".\nVolltext-Profil siehe Dokument „Firma: " . $firmenname . '".';
                $sections[] = ['Firma (Verknüpfung)', $body];
            }
        }

        // === Adressen ===
        if (!empty($k['adressen'])) {
            $adrList = [];
            foreach ($k['adressen'] as $a) {
                $zeile = ucfirst($a['typ']) . (!empty($a['ist_primaer']) ? ' (primär)' : '') . ':';
                $teile = [];
                if (!empty($a['strasse'])) $teile[] = $a['strasse'];
                $plzStadt = trim(($a['plz'] ?? '') . ' ' . ($a['stadt'] ?? ''));
                if ($plzStadt !== '') $teile[] = $plzStadt;
                if (!empty($a['bundesland'])) $teile[] = $a['bundesland'];
                if (!empty($a['land']) && $a['land'] !== 'Deutschland') $teile[] = $a['land'];
                if ($teile) $adrList[] = '- ' . $zeile . ' ' . implode(', ', $teile);
            }
            if ($adrList) $sections[] = ['Adressen', implode("\n", $adrList)];
        }

        // === Social Profile ===
        if (!empty($k['social'])) {
            $soc = [];
            foreach ($k['social'] as $s) {
                $soc[] = "- " . ucfirst($s['plattform']) . ": " . $s['url'];
            }
            if ($soc) $sections[] = ['Social Profile', implode("\n", $soc)];
        }

        // === Status & Score ===
        $stat = [];
        if (!empty($k['kontakt_status'])) $stat[] = '- Status: ' . str_replace('_', ' ', $k['kontakt_status']);
        if (!empty($k['opt_in_status'])) $stat[] = '- Opt-In: ' . str_replace('_', ' ', $k['opt_in_status']);
        if ($k['thx_score'] !== null && $k['thx_score'] !== '') $stat[] = '- THX-Score: ' . $k['thx_score'] . '/100';
        if (!empty($k['lead_quelle'])) $stat[] = '- Lead-Quelle: ' . $k['lead_quelle'];
        if (!empty($k['kuendigungsoption'])) $stat[] = '- Kündigungsoption: ' . $k['kuendigungsoption'];
        if (!empty($k['stand_datensatz'])) $stat[] = '- Stand Datensatz: ' . $k['stand_datensatz'];
        if ($stat) $sections[] = ['Status & Score', implode("\n", $stat)];

        // === Tags ===
        $tagNames = array_column($k['tags'] ?? [], 'name');
        if ($tagNames) {
            $sections[] = ['Tags', '- ' . implode("\n- ", $tagNames)];
        }

        // === Listen / Abos ===
        if (!empty($k['listen'])) {
            $listen = [];
            foreach ($k['listen'] as $l) {
                $z = '- **' . $l['name'] . '**';
                if (!empty($l['status'])) $z .= ' — ' . $l['status'];
                if (!empty($l['beigetreten_am'])) $z .= ', beigetreten am ' . $this->fmtDate($l['beigetreten_am']);
                $listen[] = $z;
            }
            if ($listen) $sections[] = ['Listen / Abos', implode("\n", $listen)];
        }

        // === Opt-In-Historie ===
        $optIn = $this->db->query(
            "SELECT typ, quelle, erfolgt_am, ip_address FROM crm_opt_in_events WHERE kontakt_id = ? ORDER BY erfolgt_am ASC LIMIT 20",
            [$id]
        );
        if ($optIn) {
            $rows = [];
            foreach ($optIn as $o) {
                $z = '- ' . $this->fmtDate($o['erfolgt_am']) . ': ' . str_replace('_', ' ', $o['typ']);
                if (!empty($o['quelle'])) $z .= ' (Quelle: ' . $o['quelle'] . ')';
                $rows[] = $z;
            }
            $sections[] = ['Opt-In-Historie', implode("\n", $rows)];
        }

        // === Lead-Quelle & UTM ===
        $utm = [];
        foreach ([
            'UTM-Source' => $k['utm_source'] ?? null,
            'UTM-Medium' => $k['utm_medium'] ?? null,
            'UTM-Campaign' => $k['utm_campaign'] ?? null,
            'UTM-Content' => $k['utm_content'] ?? null,
            'UTM-Term' => $k['utm_term'] ?? null,
            'Herkunfts-Referrer' => $k['herkunft_referrer'] ?? null,
        ] as $label => $val) {
            if ($this->isFilled($val)) $utm[] = "- {$label}: {$val}";
        }
        if ($utm) $sections[] = ['Tracking & Herkunft', implode("\n", $utm)];

        // === Lead-Magneten ===
        $lms = $this->db->query(
            "SELECT lm.name AS lm_name, le.eingegangen_am, le.utm_source, le.utm_medium, le.utm_campaign
             FROM crm_lead_magnet_events le LEFT JOIN crm_lead_magneten lm ON lm.id = le.lead_magnet_id
             WHERE le.kontakt_id = ? ORDER BY le.eingegangen_am DESC LIMIT 20",
            [$id]
        );
        if ($lms) {
            $rows = [];
            foreach ($lms as $lm) {
                $z = '- **' . ($lm['lm_name'] ?? '(Lead-Magnet)') . '** — am ' . $this->fmtDate($lm['eingegangen_am']);
                $utmI = array_filter([$lm['utm_source'], $lm['utm_medium'], $lm['utm_campaign']]);
                if ($utmI) $z .= ' (UTM: ' . implode('/', $utmI) . ')';
                $rows[] = $z;
            }
            $sections[] = ['Lead-Magneten heruntergeladen', implode("\n", $rows)];
        }
        // Auch der direkt im Kontakt eingetragene LM:
        if (!empty($k['lead_magnet_name'])) {
            $body = '- Im Kontakt-Datensatz vermerkt: **' . $k['lead_magnet_name'] . '**';
            if (!empty($k['lead_magnet_url'])) $body .= ' (' . $k['lead_magnet_url'] . ')';
            // anhängen statt zweite Sektion zu machen
            if (isset($sections) && !empty($sections) && end($sections)[0] === 'Lead-Magneten heruntergeladen') {
                $sections[count($sections) - 1][1] .= "\n" . $body;
            } else {
                $sections[] = ['Lead-Magnet (vermerkt)', $body];
            }
        }

        // === Podcast ===
        if (!empty($k['podcast_titel'])) {
            $pod = ['- Titel: ' . $k['podcast_titel']];
            if (!empty($k['podcast_subtitel'])) $pod[] = '- Subtitel: ' . $k['podcast_subtitel'];
            if (!empty($k['podcast_release_datum'])) $pod[] = '- Release-Datum: ' . $this->fmtDate($k['podcast_release_datum']);
            if (!empty($k['podcast_release_url'])) $pod[] = '- URL: ' . $k['podcast_release_url'];
            if (!empty($k['podcast_release_mail'])) $pod[] = '- Versand-Mail: ' . $k['podcast_release_mail'];
            $sections[] = ['Podcast-Verknüpfung', implode("\n", $pod)];
        }

        // === Trigger-Flags ===
        $trig = [];
        foreach ([
            'Kontaktformular abgeschickt' => $k['trigger_kontaktformular'] ?? null,
            'Terminbuchung gemacht' => $k['trigger_terminbuchung'] ?? null,
            'Strategie-Check angefordert' => $k['trigger_strategie_check'] ?? null,
            'Lead-Magnet runtergeladen' => $k['trigger_lead_magnet'] ?? null,
            'Test-Trigger' => $k['trigger_test'] ?? null,
        ] as $label => $val) {
            if ((int) $val === 1) $trig[] = '- ' . $label . ': Ja';
        }
        if ($trig) $sections[] = ['Aktive Trigger / Flags', implode("\n", $trig)];

        // === Sales / Deal ===
        $sales = [];
        if ($k['deal_wert'] !== null && $k['deal_wert'] !== '') {
            $sales[] = '- Aktueller Deal-Wert: ' . number_format((float) $k['deal_wert'], 2, ',', '.') . ' €';
        }
        if (!empty($k['deal_stufe'])) $sales[] = '- Deal-Stufe: ' . $k['deal_stufe'];
        if ($sales) $sections[] = ['Sales / Deal', implode("\n", $sales)];

        // === Mails (letzte Events, gruppiert) ===
        $mails = $this->db->query(
            "SELECT campaign_name, event_typ, link_url, empfangen_am
             FROM crm_brevo_events WHERE kontakt_id = ?
             ORDER BY empfangen_am DESC LIMIT 10",
            [$id]
        );
        if ($mails) {
            $rows = [];
            foreach ($mails as $m) {
                $z = '- ' . $this->fmtDate($m['empfangen_am']) . ' — ' . $m['event_typ'];
                if (!empty($m['campaign_name'])) $z .= ': „' . $m['campaign_name'] . '"';
                if (!empty($m['link_url']) && $m['event_typ'] === 'click') $z .= ' → ' . $m['link_url'];
                $rows[] = $z;
            }
            $sections[] = ['Aktivitäts-Verlauf (letzte 10 Mail-Events)', implode("\n", $rows)];
        }

        // === Notizen (Volltext, chronologisch) ===
        $notizen = $this->db->query(
            "SELECT inhalt, erstellt_am, actor_user_id, titel FROM crm_aktivitaeten
             WHERE kontakt_id = ? AND typ = 'notiz' ORDER BY erstellt_am ASC LIMIT 100",
            [$id]
        );
        if ($notizen) {
            $rows = [];
            foreach ($notizen as $n) {
                $datum = $this->fmtDate($n['erstellt_am']);
                $autor = $n['actor_user_id'] ? $this->resolveUserName((int) $n['actor_user_id']) : '';
                $head = '**' . $datum . ($autor ? ' (' . $autor . ')' : '') . ':**';
                if (!empty($n['titel'])) $head .= ' ' . $n['titel'];
                $body = $n['inhalt'] ?? '';
                $rows[] = $head . ($body ? "\n" . $body : '');
            }
            $sections[] = ['Notizen (chronologisch)', implode("\n\n", $rows)];
        }

        // === Telefonate ===
        $calls = $this->db->query(
            "SELECT titel, inhalt, erstellt_am, actor_user_id, metadata_json
             FROM crm_aktivitaeten WHERE kontakt_id = ? AND typ = 'telefonat'
             ORDER BY erstellt_am ASC LIMIT 50",
            [$id]
        );
        if ($calls) {
            $rows = [];
            foreach ($calls as $c) {
                $datum = $this->fmtDate($c['erstellt_am']);
                $autor = $c['actor_user_id'] ? $this->resolveUserName((int) $c['actor_user_id']) : '';
                $head = '**' . $datum . ($autor ? ' (' . $autor . ')' : '') . ':**';
                if (!empty($c['titel'])) $head .= ' ' . $c['titel'];
                $body = $c['inhalt'] ?? '';
                $rows[] = $head . ($body ? "\n" . $body : '');
            }
            $sections[] = ['Telefonate', implode("\n\n", $rows)];
        }

        // === Meetings ===
        $meets = $this->db->query(
            "SELECT titel, inhalt, erstellt_am, actor_user_id FROM crm_aktivitaeten
             WHERE kontakt_id = ? AND typ = 'meeting' ORDER BY erstellt_am ASC LIMIT 50",
            [$id]
        );
        if ($meets) {
            $rows = [];
            foreach ($meets as $m) {
                $datum = $this->fmtDate($m['erstellt_am']);
                $autor = $m['actor_user_id'] ? $this->resolveUserName((int) $m['actor_user_id']) : '';
                $head = '**' . $datum . ($autor ? ' (' . $autor . ')' : '') . ':**';
                if (!empty($m['titel'])) $head .= ' ' . $m['titel'];
                $body = $m['inhalt'] ?? '';
                $rows[] = $head . ($body ? "\n" . $body : '');
            }
            $sections[] = ['Meetings', implode("\n\n", $rows)];
        }

        // === Verknüpfte Thoxan-Kunden ===
        if (!empty($k['kunden_zuordnung'])) {
            $rows = [];
            foreach ($k['kunden_zuordnung'] as $kz) {
                $z = '- **' . $kz['customer_name'] . '**';
                if (!empty($kz['rolle'])) $z .= ' — Rolle: ' . $kz['rolle'];
                $rows[] = $z;
            }
            $sections[] = ['Verknüpfte Thoxan-Kunden', implode("\n", $rows)];
        }

        // === Externe Verknüpfungen ===
        $ext = [];
        if (!empty($k['brevo_id'])) {
            $z = '- Brevo-Contact-ID: ' . $k['brevo_id'];
            if (!empty($k['brevo_zuletzt_gepusht_am'])) $z .= ' (zuletzt gepusht am ' . $this->fmtDate($k['brevo_zuletzt_gepusht_am']) . ')';
            $ext[] = $z;
        }
        if (!empty($k['legacy_zoho_id'])) {
            $ext[] = '- Zoho-CRM-ID: ' . $k['legacy_zoho_id'];
        }
        if (!empty($k['asana_task_gid'])) {
            $ext[] = '- Asana-Task-GID: ' . $k['asana_task_gid'];
        }
        if ($ext) $sections[] = ['Externe Verknüpfungen', implode("\n", $ext)];

        // === System-Meta ===
        $meta = [];
        if (!empty($k['kontakt_besitzer_user_id'])) {
            $meta[] = '- Kontakt-Besitzer: ' . $this->resolveUserName((int) $k['kontakt_besitzer_user_id']);
        }
        if (!empty($k['layout_name'])) $meta[] = '- Layout: ' . $k['layout_name'];
        if (!empty($k['erstellt_am'])) $meta[] = '- Angelegt: ' . $this->fmtDate($k['erstellt_am']);
        if (!empty($k['geaendert_am'])) $meta[] = '- Zuletzt geändert: ' . $this->fmtDate($k['geaendert_am']);
        if ((int) ($k['ac_sync'] ?? 0) === 1) $meta[] = '- ActiveCampaign-Sync: aktiv';
        if ($meta) $sections[] = ['System-Meta', implode("\n", $meta)];

        return [
            'title' => $title,
            'description' => $description,
            'sections' => $sections,
            'tags' => $tagNames,
            'firma_id' => $k['firma_id'] ? (int) $k['firma_id'] : null,
            'name' => $name,
            'firmenname' => $firmenname,
        ];
    }

    private function buildKontaktDescription(array $k): string
    {
        $parts = [];
        $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
        if ($name === '') $name = $k['email_primaer'] ?? '';
        if (!empty($k['funktion'])) $parts[] = $k['funktion'];
        if (!empty($k['firmenname'])) $parts[] = 'bei ' . $k['firmenname'];
        $desc = $name;
        if ($parts) $desc .= ' — ' . implode(' ', $parts);
        $sub = [];
        if (!empty($k['kontakt_status'])) $sub[] = 'Status: ' . str_replace('_', ' ', $k['kontakt_status']);
        if (!empty($k['opt_in_status'])) $sub[] = 'Opt-In: ' . str_replace('_', ' ', $k['opt_in_status']);
        if ($sub) $desc .= ' · ' . implode(' · ', $sub);
        return mb_substr($desc, 0, 500);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDERER: FIRMA
    // ═══════════════════════════════════════════════════════════════════════

    public function renderFirma(int $id): ?array
    {
        $f = $this->firmaService->detail($id);
        if (!$f) return null;

        $title = 'Firma: ' . $f['firmenname'];
        $description = $this->buildFirmaDescription($f);

        $sections = [];

        // === Stammdaten ===
        $stamm = [];
        foreach ([
            'Firmenname' => $f['firmenname'],
            'Website' => $f['website'] ?? null,
            'Branche' => $f['branche'] ?? null,
            'Firmen-Typ' => $f['firmen_typ'] ?? null,
            'Bewertung' => $f['bewertung'] !== null && $f['bewertung'] !== '' ? $f['bewertung'] . '/5' : null,
            'Beschäftigte' => $f['beschaeftigte'] ?? null,
            'Jahreseinnahmen' => ($f['jahreseinnahmen'] !== null && $f['jahreseinnahmen'] !== '')
                ? number_format((float) $f['jahreseinnahmen'], 2, ',', '.') . ' €' : null,
            'Telefon' => $f['telefon'] ?? null,
            'Fax' => $f['fax'] ?? null,
            'E-Mail' => $f['email'] ?? null,
        ] as $label => $val) {
            if ($this->isFilled($val)) $stamm[] = "- {$label}: {$val}";
        }
        if ($stamm) $sections[] = ['Stammdaten', implode("\n", $stamm)];

        // === Adressen ===
        if (!empty($f['adressen'])) {
            $adrList = [];
            foreach ($f['adressen'] as $a) {
                $zeile = ucfirst($a['typ']) . (!empty($a['ist_primaer']) ? ' (primär)' : '') . ':';
                $teile = [];
                if (!empty($a['strasse'])) $teile[] = $a['strasse'];
                $plzStadt = trim(($a['plz'] ?? '') . ' ' . ($a['stadt'] ?? ''));
                if ($plzStadt !== '') $teile[] = $plzStadt;
                if (!empty($a['bundesland'])) $teile[] = $a['bundesland'];
                if (!empty($a['land']) && $a['land'] !== 'Deutschland') $teile[] = $a['land'];
                if ($teile) $adrList[] = '- ' . $zeile . ' ' . implode(', ', $teile);
            }
            if ($adrList) $sections[] = ['Adressen', implode("\n", $adrList)];
        }

        // === Konzernstruktur ===
        $konzern = [];
        if (!empty($f['parent_firma'])) {
            $konzern[] = '**Muttergesellschaft:** ' . $f['parent_firma']['firmenname'];
        }
        if (!empty($f['tochter_firmen'])) {
            $konzern[] = '**Töchter:**';
            foreach ($f['tochter_firmen'] as $t) {
                $konzern[] = '- ' . $t['firmenname'];
            }
        }
        if ($konzern) $sections[] = ['Konzernstruktur', implode("\n", $konzern)];

        // === Top-Kontakte (alle mit Funktion) ===
        if (!empty($f['kontakte'])) {
            $rows = [];
            foreach ($f['kontakte'] as $kk) {
                $name = trim(($kk['vorname'] ?? '') . ' ' . ($kk['nachname'] ?? ''));
                if ($name === '') continue;
                $z = '- **' . $name . '**';
                if (!empty($kk['funktion'])) $z .= ' — ' . $kk['funktion'];
                $mks = [];
                if (!empty($kk['email_primaer'])) $mks[] = $kk['email_primaer'];
                if (!empty($kk['telefon'])) $mks[] = 'Tel ' . $kk['telefon'];
                else if (!empty($kk['mobil'])) $mks[] = 'Mobil ' . $kk['mobil'];
                if (!empty($kk['kontakt_status'])) $mks[] = 'Status: ' . str_replace('_', ' ', $kk['kontakt_status']);
                if ($mks) $z .= ' (' . implode(', ', $mks) . ')';
                $rows[] = $z;
            }
            if ($rows) $sections[] = ['Verknüpfte Kontakte (' . count($f['kontakte']) . ')', implode("\n", $rows)];
        }

        // === Aktivitäts-Statistik (aggregiert) ===
        if (!empty($f['stats']) && (int) ($f['stats']['aktivitaeten_gesamt'] ?? 0) > 0) {
            $s = $f['stats'];
            $rows = [
                '- Aktivitäten gesamt: ' . ($s['aktivitaeten_gesamt'] ?? 0),
            ];
            if (!empty($s['mails_geoeffnet'])) $rows[] = '- E-Mails geöffnet: ' . $s['mails_geoeffnet'];
            if (!empty($s['mails_geklickt'])) $rows[] = '- E-Mail-Klicks: ' . $s['mails_geklickt'];
            if (!empty($s['anrufe'])) $rows[] = '- Telefonate: ' . $s['anrufe'];
            if (!empty($s['notizen'])) $rows[] = '- Notizen: ' . $s['notizen'];
            if (!empty($s['letzte_aktivitaet'])) $rows[] = '- Letzte Aktivität: ' . $this->fmtDate($s['letzte_aktivitaet']);
            $sections[] = ['Aktivitäts-Statistik (alle Kontakte)', implode("\n", $rows)];
        }

        // === Firmen-Notizen ===
        if (!empty($f['notizen'])) {
            $sections[] = ['Notizen', $f['notizen']];
        }

        // === Externe Verknüpfungen ===
        if (!empty($f['legacy_zoho_id'])) {
            $sections[] = ['Externe Verknüpfungen', '- Zoho-CRM-ID: ' . $f['legacy_zoho_id']];
        }

        // === System-Meta ===
        $meta = [];
        if (!empty($f['erstellt_am'])) $meta[] = '- Angelegt: ' . $this->fmtDate($f['erstellt_am']);
        if (!empty($f['geaendert_am'])) $meta[] = '- Zuletzt geändert: ' . $this->fmtDate($f['geaendert_am']);
        if ($meta) $sections[] = ['System-Meta', implode("\n", $meta)];

        return [
            'title' => $title,
            'description' => $description,
            'sections' => $sections,
            'tags' => array_filter([$f['branche'] ?? null, $f['firmen_typ'] ?? null]),
            'firmenname' => $f['firmenname'],
            'kontakte' => $f['kontakte'] ?? [],
        ];
    }

    private function buildFirmaDescription(array $f): string
    {
        $parts = [];
        if (!empty($f['branche'])) $parts[] = 'Branche: ' . $f['branche'];
        if (!empty($f['firmen_typ'])) $parts[] = $f['firmen_typ'];
        if (!empty($f['beschaeftigte'])) $parts[] = $f['beschaeftigte'] . ' Beschäftigte';
        $n = count($f['kontakte'] ?? []);
        if ($n > 0) $parts[] = $n . ' verknüpfte Kontakt' . ($n === 1 ? '' : 'e');
        $desc = $f['firmenname'];
        if ($parts) $desc .= ' — ' . implode(' · ', $parts);
        return mb_substr($desc, 0, 500);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SYNC: kontakt / firma upserten (direkt, ohne LLM-Extraktion)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Synchronisiert einen Kontakt. Returns: document_id, oder null wenn gelöscht/nicht gefunden.
     */
    public function syncKontakt(int $id): ?int
    {
        $rendered = $this->renderKontakt($id);
        if (!$rendered) {
            // Kontakt nicht (mehr) vorhanden → Doc löschen falls existiert
            $this->deleteDocument('crm_kontakt', 'kontakt:' . $id);
            return null;
        }
        $customerId = $this->resolveCustomerForFirma($rendered['firma_id']);
        return $this->upsertDocument(
            'crm_kontakt',
            'kontakt:' . $id,
            $customerId,
            $rendered['title'],
            $rendered['description'],
            $rendered['sections'],
            $rendered['tags'],
            $this->buildContextLine('Kontakt: ' . $rendered['name'], $rendered['firmenname'])
        );
    }

    /**
     * Synchronisiert eine Firma + alle ihre Kontakte. Returns: document_id der Firma.
     */
    public function syncFirma(int $id): ?int
    {
        $rendered = $this->renderFirma($id);
        if (!$rendered) {
            $this->deleteDocument('crm_firma', 'firma:' . $id);
            return null;
        }
        $customerId = $this->resolveCustomerForFirma($id);
        $docId = $this->upsertDocument(
            'crm_firma',
            'firma:' . $id,
            $customerId,
            $rendered['title'],
            $rendered['description'],
            $rendered['sections'],
            $rendered['tags'],
            $this->buildContextLine('Firma: ' . $rendered['firmenname'], null)
        );

        // Kaskade: alle Kontakte dieser Firma re-enqueuen (Firma-Daten stehen
        // im Kontakt-Doc als Cross-Reference → muss bei Firmenwechseln neu rendern)
        foreach ($rendered['kontakte'] as $kk) {
            $this->enqueueKontakt((int) $kk['id']);
        }

        return $docId;
    }

    /**
     * Beim Wechsel von customer.crm_firma_id: alte+neue Firma + alle Kontakte
     * der Firma re-enqueuen. Sorgt dafür, dass Dokumente in den richtigen
     * Customer-Bucket wandern.
     */
    public function reSyncAfterCustomerLinkChange(?int $oldFirmaId, ?int $newFirmaId): void
    {
        $firmaIds = array_filter([$oldFirmaId, $newFirmaId], fn($x) => $x !== null && $x > 0);
        $firmaIds = array_unique(array_map('intval', $firmaIds));
        foreach ($firmaIds as $fid) {
            $this->enqueueFirma($fid);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DOC-UPSERT (direkt, ohne LLM)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Schreibt/aktualisiert ein Knowledge-Doc + dessen Chunks.
     * Bypass von KnowledgeIngestService::commit() um teure LLM-Extraktion zu
     * vermeiden — CRM-Daten sind bereits strukturiert.
     */
    private function upsertDocument(
        string $sourceType,
        string $externalId,
        int $customerId,
        string $title,
        string $description,
        array $sections,
        array $tags,
        string $contextLine
    ): int {
        // Volltext für Hash + original_content (alle Sektionen kombiniert)
        $fullText = "# " . $title . "\n\n" . $description . "\n\n";
        foreach ($sections as [$head, $body]) {
            $fullText .= "## " . $head . "\n" . $body . "\n\n";
        }
        $fullText = rtrim($fullText);
        $hash = hash('sha256', $fullText);

        // Doc finden oder anlegen
        $existing = $this->db->queryOne(
            "SELECT id, content_hash, customer_id FROM knowledge_documents
             WHERE source_type = ? AND external_id = ? LIMIT 1",
            [$sourceType, $externalId]
        );

        if ($existing) {
            // Wenn Hash UND Customer-Bucket gleich → nichts zu tun
            if ($existing['content_hash'] === $hash && (int) $existing['customer_id'] === $customerId) {
                return (int) $existing['id'];
            }
            // Update
            $this->db->update('knowledge_documents', [
                'customer_id' => $customerId,
                'title' => $title,
                'description' => $description,
                'tags' => json_encode(array_values(array_unique($tags))),
                'original_content' => $fullText,
                'content_hash' => $hash,
                'updated_by' => self::SYSTEM_USER_ID,
                'is_active' => 1,
            ], 'id = ?', [(int) $existing['id']]);
            $docId = (int) $existing['id'];
            // Alte Chunks weg
            $this->db->delete('knowledge_chunks', 'document_id = ?', [$docId]);
        } else {
            $docId = (int) $this->db->insert('knowledge_documents', [
                'customer_id' => $customerId,
                'title' => $title,
                'description' => $description,
                'source_type' => $sourceType,
                'ingest_mode' => 'auto',
                'source_ref' => $externalId,
                'external_id' => $externalId,
                'category' => $sourceType === 'crm_kontakt' ? 'CRM-Kontakt' : 'CRM-Firma',
                'tags' => json_encode(array_values(array_unique($tags))),
                'original_content' => $fullText,
                'content_hash' => $hash,
                'created_by' => self::SYSTEM_USER_ID,
                'is_active' => 1,
            ]);
        }

        // Chunks anlegen — eine H2-Sektion = ein Chunk (mit Context-Prefix),
        // übergroße Sektionen werden zusätzlich nach CHUNK_MAX_CHARS gesplittet.
        $chunkIndex = 0;
        foreach ($sections as [$head, $body]) {
            $base = $contextLine . "\n\n## " . $head . "\n" . $body;
            $parts = $this->splitOversized($base);
            foreach ($parts as $part) {
                $this->db->insert('knowledge_chunks', [
                    'document_id' => $docId,
                    'customer_id' => $customerId,
                    'chunk_index' => $chunkIndex++,
                    'content' => $part,
                    'word_count' => str_word_count(strip_tags($part)),
                ]);
            }
        }

        // Qdrant-Job enqueuen → bge-m3-Embedding wird vom qdrant_sync-Worker erzeugt
        $this->enqueueQdrantSync($docId, $customerId);

        return $docId;
    }

    private function deleteDocument(string $sourceType, string $externalId): void
    {
        $doc = $this->db->queryOne(
            "SELECT id, customer_id FROM knowledge_documents WHERE source_type = ? AND external_id = ? LIMIT 1",
            [$sourceType, $externalId]
        );
        if (!$doc) return;
        // Soft-Delete: is_active = 0, dann Qdrant-Worker räumt drüben auf
        $this->db->update('knowledge_documents', ['is_active' => 0, 'updated_by' => self::SYSTEM_USER_ID], 'id = ?', [(int) $doc['id']]);
        $this->enqueueQdrantSync((int) $doc['id'], (int) $doc['customer_id'], 'delete_document');
    }

    private function enqueueQdrantSync(int $docId, ?int $customerId, string $op = 'upsert_document'): void
    {
        try {
            (new JobQueue($this->db))->createJob([
                'user_id'         => self::SYSTEM_USER_ID,
                'customer_id'     => $customerId,
                'job_type'        => 'qdrant_sync',
                'sections_config' => ['op' => $op, 'document_id' => $docId],
                'priority'        => -5,
            ]);
        } catch (\Throwable $e) {
            error_log('CrmKnowledgeSync: qdrant_sync enqueue fehlgeschlagen (doc ' . $docId . '): ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // QUEUE (Debounce-Sync)
    // ═══════════════════════════════════════════════════════════════════════

    public function enqueueKontakt(int $id): void
    {
        $this->db->execute(
            "INSERT INTO crm_sync_queue (entity_typ, entity_id, last_change_at)
             VALUES ('kontakt', ?, NOW())
             ON DUPLICATE KEY UPDATE last_change_at = NOW(), attempts = 0, last_error = NULL",
            [$id]
        );
    }

    public function enqueueFirma(int $id): void
    {
        $this->db->execute(
            "INSERT INTO crm_sync_queue (entity_typ, entity_id, last_change_at)
             VALUES ('firma', ?, NOW())
             ON DUPLICATE KEY UPDATE last_change_at = NOW(), attempts = 0, last_error = NULL",
            [$id]
        );
        // Kontakte werden vom syncFirma() kaskadiert enqueued — hier nichts vorab tun.
    }

    /**
     * Arbeitet die Queue ab. Holt Entities, deren letzte Änderung mindestens
     * $debounceSeconds zurück liegt (verhindert Spam während Inline-Edit-Sessions).
     * Returns: ['processed' => int, 'errors' => int]
     */
    public function processQueue(int $debounceSeconds = 30, int $batchSize = 50): array
    {
        $rows = $this->db->query(
            "SELECT entity_typ, entity_id, attempts FROM crm_sync_queue
             WHERE last_change_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
               AND (last_attempt_at IS NULL OR last_attempt_at < DATE_SUB(NOW(), INTERVAL 60 SECOND))
               AND attempts < 5
             ORDER BY last_change_at ASC
             LIMIT ?",
            [$debounceSeconds, $batchSize]
        );

        $processed = 0;
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $this->db->execute(
                    "UPDATE crm_sync_queue SET last_attempt_at = NOW() WHERE entity_typ = ? AND entity_id = ?",
                    [$row['entity_typ'], $row['entity_id']]
                );
                if ($row['entity_typ'] === 'kontakt') {
                    $this->syncKontakt((int) $row['entity_id']);
                } else {
                    $this->syncFirma((int) $row['entity_id']);
                }
                $this->db->delete('crm_sync_queue', 'entity_typ = ? AND entity_id = ?', [$row['entity_typ'], $row['entity_id']]);
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->db->execute(
                    "UPDATE crm_sync_queue SET attempts = attempts + 1, last_error = ?
                     WHERE entity_typ = ? AND entity_id = ?",
                    [mb_substr($e->getMessage(), 0, 500), $row['entity_typ'], $row['entity_id']]
                );
                error_log('CrmKnowledgeSync (' . $row['entity_typ'] . ' ' . $row['entity_id'] . '): ' . $e->getMessage());
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Context-Prefix für jeden Chunk: stellt sicher, dass jeder Chunk weiß,
     * zu welcher Entity er gehört (auch wenn der Retriever ihn isoliert findet).
     */
    private function buildContextLine(string $entityHead, ?string $firmenname): string
    {
        $line = '[' . $entityHead;
        if ($firmenname) $line .= ' · Firma: ' . $firmenname;
        $line .= ']';
        return $line;
    }

    /**
     * Splittet übergroße Chunks (>1200 Zeichen). Versucht zuerst an Zeilenumbrüchen,
     * dann an Satzgrenzen, schließlich harter Char-Cut. Wichtig damit bge-m3's
     * Token-Limit (~512) nicht überschritten wird (12kb CRM-Datenmüll passiert).
     */
    private function splitOversized(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_MAX_CHARS) return [$text];
        // 1. Versuche Zeilenumbrüche
        $parts = [];
        $lines = explode("\n", $text);
        $current = '';
        foreach ($lines as $line) {
            if (mb_strlen($current . "\n" . $line) > self::CHUNK_MAX_CHARS) {
                if ($current !== '') $parts[] = trim($current);
                $current = $line;
            } else {
                $current = $current === '' ? $line : ($current . "\n" . $line);
            }
        }
        if ($current !== '') $parts[] = trim($current);
        // 2. Eventuell entstandene Mega-Parts (einzelne Zeilen >MAX) hart splitten
        $final = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) <= self::CHUNK_MAX_CHARS) { $final[] = $p; continue; }
            // Versuche an Satzpunkten/Bullets/Kommas zu splitten
            $segments = preg_split('/(?<=[\.\!\?])\s+|(?<=\;)\s+|(?<=\s---\s)/u', $p) ?: [$p];
            $buf = '';
            foreach ($segments as $seg) {
                if (mb_strlen($buf . ' ' . $seg) > self::CHUNK_MAX_CHARS) {
                    if ($buf !== '') $final[] = trim($buf);
                    $buf = $seg;
                } else {
                    $buf = $buf === '' ? $seg : ($buf . ' ' . $seg);
                }
            }
            if ($buf !== '') $final[] = trim($buf);
        }
        // 3. Fallback: harter Char-Cut für alles was noch >MAX ist
        $hardSplit = [];
        foreach ($final as $p) {
            if (mb_strlen($p) <= self::CHUNK_MAX_CHARS) { $hardSplit[] = $p; continue; }
            for ($i = 0, $len = mb_strlen($p); $i < $len; $i += self::CHUNK_MAX_CHARS) {
                $hardSplit[] = mb_substr($p, $i, self::CHUNK_MAX_CHARS);
            }
        }
        return $hardSplit;
    }

    private function isFilled($val): bool
    {
        if ($val === null) return false;
        if (is_string($val) && trim($val) === '') return false;
        if (is_numeric($val)) return true;
        return $val !== '';
    }

    private function fmtDate($d): string
    {
        if (!$d) return '';
        $ts = is_numeric($d) ? (int) $d : strtotime((string) $d);
        if (!$ts) return (string) $d;
        return date('d.m.Y', $ts);
    }

    private array $userNameCache = [];
    private function resolveUserName(int $userId): string
    {
        if (isset($this->userNameCache[$userId])) return $this->userNameCache[$userId];
        $name = $this->db->queryValue("SELECT name FROM users WHERE id = ?", [$userId]);
        return $this->userNameCache[$userId] = ($name ?: 'User #' . $userId);
    }
}
