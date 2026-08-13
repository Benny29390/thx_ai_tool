<?php
/**
 * Settings API (Admin only)
 *
 * Schreiben: secrets werden ueber Core\Settings::set() automatisch verschluesselt.
 * Lesen: secrets werden im Response maskiert ('********') statt im Klartext.
 */

use Core\Response;
use Core\Settings;

global $db, $method, $input;

switch ($method) {
    case 'GET':
        $settings = $db->query("SELECT * FROM settings ORDER BY setting_key");

        $result = [];
        foreach ($settings as $setting) {
            $isSecret = Settings::isSecret($setting['setting_key']);
            if ($isSecret && !empty($setting['setting_value'])) {
                $setting['setting_value'] = '********';
            }
            $result[$setting['setting_key']] = $setting;
        }

        Response::success($result);
        break;

    case 'POST':
        // Test-Mail senden
        if (($input['action'] ?? '') === 'test_smtp') {
            // Session freigeben damit andere Requests nicht blockiert werden
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $adminEmail = \Core\Auth::user()['email'] ?? '';
            if (empty($adminEmail)) {
                Response::error('Keine Admin-E-Mail verfuegbar');
            }

            require_once SERVICES_PATH . '/EmailService.php';
            $emailService = \Services\EmailService::fromSettings($db);

            if (!$emailService->isConfigured()) {
                // Zeige was fehlt
                $smtpSettings = [];
                foreach ($db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'") as $row) {
                    $smtpSettings[$row['setting_key']] = $row['setting_value'];
                }
                $missing = [];
                if (empty($smtpSettings['smtp_host'])) $missing[] = 'Host';
                if (empty($smtpSettings['smtp_username'])) $missing[] = 'Benutzername';
                if (empty($smtpSettings['smtp_password'])) $missing[] = 'Passwort';
                Response::error('SMTP nicht konfiguriert. Fehlend: ' . (empty($missing) ? 'Einstellungen noch nicht gespeichert' : implode(', ', $missing)));
            }

            $sent = $emailService->send(
                $adminEmail,
                'SMTP Test — ' . APP_NAME,
                '<div style="font-family:sans-serif;padding:20px;"><h2>SMTP funktioniert!</h2><p>Diese Test-Mail wurde erfolgreich zugestellt.</p><p style="color:#94a3b8;font-size:12px;">Gesendet am ' . date('d.m.Y H:i:s') . '</p></div>'
            );

            if ($sent) {
                Response::success(null, 'Test-Mail gesendet an ' . $adminEmail);
            } else {
                Response::error('SMTP-Verbindung fehlgeschlagen. Pruefe Host, Port, Zugangsdaten und Verschluesselung im Error-Log.');
            }
        }

        // Rerank-Verbindung testen (nutzt die GESPEICHERTEN Einstellungen)
        if (($input['action'] ?? '') === 'test_rerank') {
            $provider = (string) Settings::get('rerank_provider') ?: 'local';
            $url   = trim((string) Settings::get('rerank_url'));
            if ($url === '') $url = \Services\RerankService::defaultUrlFor($provider);
            $model = trim((string) Settings::get('rerank_model'));
            if ($model === '') $model = \Services\RerankService::defaultModelFor($provider);
            $key   = (string) Settings::get('rerank_api_key');
            if ($key === '' && $provider === 'local') $key = (string) Settings::get('local_api_key');

            if ($url === '') {
                Response::error('Keine Endpoint-URL gesetzt. Bei „Lokal" bitte die URL eintragen und speichern.');
            }
            try {
                $svc = new \Services\RerankService($provider, $url, $model, $key);
                $docs = [
                    'Sunbrella-Stoffe werden im Bootsbau und auf Terrassen eingesetzt.',
                    'Das Impressum und die Kontaktdaten der Firma.',
                    'Loungemoebel aus Teakholz fuer den Garten.',
                ];
                $res = $svc->rerank('Wo werden Sunbrella-Stoffe eingesetzt?', $docs, 3);
                if (empty($res)) {
                    Response::error('Reranker antwortete, lieferte aber kein Ergebnis. Modellname/Format prüfen.');
                }
                $top = $res[0];
                $hint = ($top['index'] === 0) ? ' Plausibilitaet: ok (relevantester Treffer zuerst).' : '';
                Response::success(
                    ['top_index' => $top['index'], 'top_score' => $top['score']],
                    'Reranker erreichbar (' . $provider . ', Modell ' . $model . ').' . $hint
                );
            } catch (\Throwable $e) {
                Response::error('Reranker nicht erreichbar: ' . $e->getMessage());
            }
        }

        $updated = 0;

        // Prefixe / Keys, die wir automatisch anlegen dürfen (sobald sie noch nicht existieren)
        $allowedPrefixes = ['smtp_', 'asana_', 'sistrix_', 'brave_', 'openai_', 'anthropic_', 'google_', 'local_', 'qdrant_', 'embedding_', 'app_', 'lam_', 'mail_', 'brevo_', 'crm_', 'chat_', 'rerank_'];
        $allowedExact    = ['default_model', 'app_logo', 'app_name', 'app_url'];

        foreach ($input as $key => $value) {
            // CSRF-Token und action überspringen
            if (in_array($key, ['csrf_token', 'action'])) continue;

            $existing = $db->queryOne("SELECT id, setting_value FROM settings WHERE setting_key = ?", [$key]);
            $isSecret = Settings::isSecret($key);

            // Explizites Löschen (z.B. Sistrix-Key) via __clear__-Sentinel
            if ($value === '__clear__') {
                if ($existing) {
                    Settings::forget($key);
                    $updated++;
                }
                continue;
            }

            // Maskierte Werte nicht überschreiben
            if ($value === '********') continue;

            // Bei Secrets: leerer Wert lässt den alten Key unangetastet
            if ($isSecret && trim((string)$value) === '') continue;

            // Sistrix-Credits-Korrektur: JSON-Format mit Wochenstart speichern
            if ($key === 'sistrix_credits_korrektur' && trim((string)$value) !== '') {
                $wochenstart = (new \DateTime('Monday this week'))->format('Y-m-d');
                $value = json_encode([
                    'wert' => (int)$value,
                    'wochenstart' => $wochenstart,
                    'gesetzt_am' => date('Y-m-d H:i:s'),
                ]);
            }

            if (!$existing) {
                // Neue Settings nur für erlaubte Prefixe / bekannte Keys anlegen
                $allowed = in_array($key, $allowedExact, true);
                if (!$allowed) {
                    foreach ($allowedPrefixes as $p) {
                        if (strpos($key, $p) === 0) { $allowed = true; break; }
                    }
                }
                if (!$allowed) continue;
            }

            // Settings::set kuemmert sich um insert vs. update und Verschluesselung
            Settings::set($key, (string)$value);
            $updated++;
        }

        Response::success(['updated' => $updated], 'Einstellungen gespeichert');
        break;

    default:
        Response::error('Method not allowed', 405);
}
