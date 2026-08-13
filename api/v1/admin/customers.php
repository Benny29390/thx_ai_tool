<?php
/**
 * Customers API (Admin only)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// Live-Dependency-Check (vor dem Löschen — was hängt am Kunden?)
if ($action === 'dependencies' && $id && $method === 'GET') {
    $counts = [];
    $checks = [
        'Projektpläne'         => "SELECT COUNT(*) FROM pp_plans WHERE customer_id = ?",
        'CRM-Kontakt-Zuordnungen' => "SELECT COUNT(*) FROM crm_kunden_zuordnung WHERE customer_id = ?",
        'Verknüpfte CRM-Firma' => "SELECT COUNT(*) FROM customers WHERE id = ? AND crm_firma_id IS NOT NULL",
        'Site-Monitore'        => "SELECT COUNT(*) FROM pm_monitors WHERE customer_id = ?",
        'Wissens-Dokumente'    => "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ?",
        'User-Zuordnungen'     => "SELECT COUNT(*) FROM user_customers WHERE customer_id = ?",
        'Asana-Tasks (Cache)'  => "SELECT COUNT(*) FROM customer_asana_tasks WHERE customer_id = ?",
        'Regeln'               => "SELECT COUNT(*) FROM rules WHERE customer_id = ?",
    ];
    foreach ($checks as $label => $sql) {
        try { $counts[$label] = (int) $db->queryValue($sql, [$id]); }
        catch (\Throwable $e) { /* Tabelle existiert nicht — überspringen */ }
    }
    Response::success(['counts' => $counts]);
}

switch ($method) {
    case 'GET':
        // Tags aus settings.tags auspacken, damit das Frontend filtern kann
        $unpackTags = function (array &$c) {
            $s = json_decode($c['settings'] ?? '{}', true) ?: [];
            $c['tags'] = $s['tags'] ?? [];
        };
        if ($id) {
            $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$id]);
            if (!$customer) Response::notFound('Kunde nicht gefunden');
            $unpackTags($customer);
            Response::success($customer);
        } else {
            // Sortierung nach Kürzel (Fallback Name) — wie in der Chat-Sidebar
            $customers = $db->query("SELECT * FROM customers ORDER BY COALESCE(NULLIF(TRIM(abbreviation), ''), name)") ?: [];
            foreach ($customers as &$c) $unpackTags($c);
            unset($c);
            Response::success($customers);
        }
        break;

    case 'POST':
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $abbreviation = trim($input['abbreviation'] ?? '');

        if (empty($name) || empty($slug)) {
            Response::error('Name und Slug erforderlich');
        }

        // Slug validieren
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Response::error('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
        }

        // Abbreviation validieren — Unicode-Großbuchstaben, Ziffern und gängige Sonderzeichen
        if ($abbreviation && !preg_match('/^[\p{Lu}\p{N}&·\.\-\+\@\/\s]+$/u', $abbreviation)) {
            Response::error('Kürzel darf nur Großbuchstaben (inkl. Ä/Ö/Ü/ẞ), Zahlen und gängige Sonderzeichen (& · . - + @ /) enthalten');
        }
        if (mb_strlen($abbreviation) > 10) {
            Response::error('Kürzel darf maximal 10 Zeichen lang sein');
        }

        // Slug eindeutig?
        $existing = $db->queryOne("SELECT id FROM customers WHERE slug = ?", [$slug]);
        if ($existing) {
            Response::error('Slug bereits vergeben');
        }

        $crmFirmaIdRaw = $input['crm_firma_id'] ?? null;
        $crmFirmaId = ($crmFirmaIdRaw !== null && $crmFirmaIdRaw !== '') ? (int) $crmFirmaIdRaw : null;

        $customerId = $db->insert('customers', [
            'name' => $name,
            'slug' => $slug,
            'abbreviation' => $abbreviation ?: null,
            'is_active' => 1,
            'website' => trim($input['website'] ?? '') ?: null,
            'industry' => trim($input['industry'] ?? '') ?: null,
            'description' => trim($input['description'] ?? '') ?: null,
            'target_audience' => trim($input['target_audience'] ?? '') ?: null,
            'products_services' => trim($input['products_services'] ?? '') ?: null,
            'unique_selling_points' => trim($input['unique_selling_points'] ?? '') ?: null,
            'tone_of_voice' => trim($input['tone_of_voice'] ?? '') ?: null,
            'brand_values' => trim($input['brand_values'] ?? '') ?: null,
            'crm_firma_id' => $crmFirmaId,
        ]);

        Response::success(['id' => $customerId], 'Kunde erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $updates = [];
        if (isset($input['name'])) $updates['name'] = trim($input['name']);
        if (isset($input['is_active'])) $updates['is_active'] = (int) $input['is_active'];

        if (isset($input['slug'])) {
            $slug = trim($input['slug']);
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                Response::error('Ungültiger Slug');
            }
            $existing = $db->queryOne("SELECT id FROM customers WHERE slug = ? AND id != ?", [$slug, $id]);
            if ($existing) {
                Response::error('Slug bereits vergeben');
            }
            $updates['slug'] = $slug;
        }

        // Abbreviation aktualisieren
        if (isset($input['abbreviation'])) {
            $abbreviation = trim($input['abbreviation']);
            if ($abbreviation && !preg_match('/^[\p{Lu}\p{N}&·\.\-\+\@\/\s]+$/u', $abbreviation)) {
                Response::error('Kürzel darf nur Großbuchstaben (inkl. Ä/Ö/Ü/ẞ), Zahlen und gängige Sonderzeichen (& · . - + @ /) enthalten');
            }
            if (mb_strlen($abbreviation) > 10) {
                Response::error('Kürzel darf maximal 10 Zeichen lang sein');
            }
            $updates['abbreviation'] = $abbreviation ?: null;
        }

        // CRM-Firma-Verknüpfung aktualisieren (NULL = entkoppeln)
        $crmFirmaIdChanged = false;
        $crmFirmaIdOld = null;
        $crmFirmaIdNew = null;
        if (array_key_exists('crm_firma_id', $input)) {
            $raw = $input['crm_firma_id'];
            if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
                $updates['crm_firma_id'] = null;
                $crmFirmaIdNew = null;
            } else {
                $fid = (int) $raw;
                $exists = $db->queryValue("SELECT id FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$fid]);
                if (!$exists) Response::error('CRM-Firma nicht gefunden');
                $updates['crm_firma_id'] = $fid;
                $crmFirmaIdNew = $fid;
            }
            $crmFirmaIdOld = $db->queryValue("SELECT crm_firma_id FROM customers WHERE id = ?", [$id]);
            $crmFirmaIdOld = $crmFirmaIdOld !== null ? (int) $crmFirmaIdOld : null;
            $crmFirmaIdChanged = ($crmFirmaIdOld !== $crmFirmaIdNew);
        }

        // Profilfelder aktualisieren
        $profileFields = ['website', 'industry', 'description', 'target_audience',
                          'products_services', 'unique_selling_points', 'tone_of_voice', 'brand_values'];
        foreach ($profileFields as $field) {
            if (isset($input[$field])) {
                $updates[$field] = trim($input[$field]) ?: null;
            }
        }

        // Settings-basierte Felder (domains, tags) — gemeinsam laden/persistieren
        $settingsLoaded = null;
        $loadSettings = function () use ($db, $id, &$settingsLoaded) {
            if ($settingsLoaded === null) {
                $current = $db->queryOne("SELECT settings FROM customers WHERE id = ?", [$id]);
                $settingsLoaded = json_decode($current['settings'] ?? '{}', true) ?: [];
            }
            return $settingsLoaded;
        };

        // Zusätzliche Domains (settings.domains) — Array aus {label, url}
        if (isset($input['domains']) && is_array($input['domains'])) {
            $settings = $loadSettings();
            $clean = [];
            foreach ($input['domains'] as $d) {
                $url = trim((string) ($d['url'] ?? ''));
                $label = trim((string) ($d['label'] ?? ''));
                if ($url === '') continue;
                if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
                if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
                $clean[] = ['label' => $label, 'url' => $url];
            }
            $settings['domains'] = $clean;
            $settingsLoaded = $settings;
            $updates['settings'] = json_encode($settings);
        }

        // Tags (settings.tags) — Array aus Strings (Art von Kunde)
        $tagsTouched = false;
        $tagsList = [];
        if (isset($input['tags']) && is_array($input['tags'])) {
            $settings = $loadSettings();
            $tagsList = [];
            foreach ($input['tags'] as $t) {
                $t = trim((string) $t);
                if ($t === '' || mb_strlen($t) > 40) continue;
                if (!in_array($t, $tagsList, true)) $tagsList[] = $t;
            }
            $settings['tags'] = $tagsList;
            $settingsLoaded = $settings;
            $updates['settings'] = json_encode($settings);
            $tagsTouched = true;
        }

        if (!empty($updates)) {
            $db->update('customers', $updates, 'id = ?', [$id]);
        }

        // SYNC: Tags ↔ customer_project_types — single source of truth.
        // Wir matchen per Name (case-insensitive). Falls ein Tag noch keine Project-Art
        // hat, wird automatisch eine angelegt (damit Sidebar-Filter wie heute funktioniert).
        if ($tagsTouched) {
            // existierende Project-Arten by Name (lowercase) und by Slug
            $pts = $db->query("SELECT id, name, slug FROM project_types WHERE is_active = 1") ?: [];
            $byKey = [];
            foreach ($pts as $p) {
                $byKey[mb_strtolower($p['name'])] = (int)$p['id'];
                $byKey[mb_strtolower($p['slug'])] = (int)$p['id'];
            }
            $newPtIds = [];
            foreach ($tagsList as $tag) {
                $key = mb_strtolower(trim($tag));
                if (isset($byKey[$key])) {
                    $newPtIds[] = $byKey[$key];
                } else {
                    // Auto-anlegen
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($tag, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss'])));
                    $slug = trim($slug, '-');
                    if ($slug === '') continue;
                    // Slug-Eindeutigkeit absichern
                    $base = $slug; $n = 1;
                    while ($db->queryValue("SELECT id FROM project_types WHERE slug = ?", [$slug])) {
                        $n++; $slug = $base . '-' . $n;
                    }
                    $maxSort = (int) $db->queryValue("SELECT MAX(sort_order) FROM project_types");
                    $newId = $db->insert('project_types', [
                        'slug' => $slug, 'name' => $tag,
                        'color' => '#9ca3af', 'icon' => 'category',
                        'sort_order' => $maxSort + 10, 'is_active' => 1,
                    ]);
                    $newPtIds[] = $newId;
                    $byKey[$key] = $newId;
                }
            }
            // customer_project_types neu setzen (Vollersatz, kein Diff-Spiel)
            $db->execute("DELETE FROM customer_project_types WHERE customer_id = ?", [$id]);
            foreach (array_unique($newPtIds) as $ptid) {
                try { $db->insert('customer_project_types', ['customer_id' => $id, 'project_type_id' => $ptid]); }
                catch (\Throwable $e) {}
            }
        }

        // Bei Wechsel der CRM-Firma-Verknüpfung: alte+neue Firma re-syncen,
        // damit Kontakte beider Firmen in den richtigen Customer-Bucket umziehen.
        if ($crmFirmaIdChanged) {
            \Services\CrmKnowledgeSyncQueue::enqueueAfterCustomerLinkChange($crmFirmaIdOld, $crmFirmaIdNew);
        }

        // Regeln aktualisieren
        if (isset($input['rule_ids'])) {
            $db->delete('customer_rules', 'customer_id = ?', [$id]);
            if (is_array($input['rule_ids'])) {
                foreach ($input['rule_ids'] as $ruleId) {
                    $db->insert('customer_rules', [
                        'customer_id' => $id,
                        'rule_id' => (int) $ruleId,
                        'is_active' => 1
                    ]);
                }
            }
        }

        Response::success(null, 'Kunde aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        // Prüfen ob der Kunde existiert
        $customer = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$id]);
        if (!$customer) {
            Response::notFound('Kunde nicht gefunden');
        }

        // Audit-Log vor dem Löschen — damit nachvollziehbar bleibt, wer wann was gelöscht hat
        if (class_exists('\Core\AuditLog')) {
            try {
                \Core\AuditLog::record('customer', (string) $id, 'hard_delete', [
                    'name' => $customer['name'],
                    'deleted_by' => Auth::id(),
                ]);
            } catch (\Throwable $e) { /* nicht-kritisch */ }
        }

        // Referenzen aufraeumen — best effort: existierende Tabellen + SET NULL wo moeglich, DELETE fuer Junctions
        $setNullTables = [
            'users', 'orders', 'contexts', 'projects', 'rules',
            'usage_logs', 'usage_summary', 'links', 'rule_suggestions',
            'knowledge_documents', 'knowledge_chunks', 'knowledge_embeddings',
            'knowledge_entities', 'knowledge_relations',
        ];
        $deleteTables = [
            'user_customers', 'customer_rules', 'customer_knowledge',
        ];

        foreach ($setNullTables as $t) {
            try {
                $db->execute("UPDATE {$t} SET customer_id = NULL WHERE customer_id = ?", [$id]);
            } catch (\Exception $e) {
                error_log("Customer delete: skip UPDATE {$t}: " . $e->getMessage());
            }
        }
        foreach ($deleteTables as $t) {
            try {
                $db->execute("DELETE FROM {$t} WHERE customer_id = ?", [$id]);
            } catch (\Exception $e) {
                error_log("Customer delete: skip DELETE {$t}: " . $e->getMessage());
            }
        }

        $db->delete('customers', 'id = ?', [$id]);
        Response::success(null, 'Kunde gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
