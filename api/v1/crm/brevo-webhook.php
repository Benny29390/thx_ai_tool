<?php
/**
 * POST /api/v1/crm/brevo/webhook  — ÖFFENTLICH
 *
 * Brevo postet hier alle konfigurierten Events (sent, delivered, open, click,
 * bounce, unsubscribed, ...). Wir speichern jedes Event in crm_brevo_events
 * und legen einen Aktivitäten-Eintrag im Kontakt-Stream an.
 *
 * Signatur-Validierung (optional): wenn brevo_webhook_secret gesetzt ist,
 * prüfen wir den HMAC-Header. Brevo sendet die Signatur im X-Mailin-Signature-
 * Header (HMAC-SHA256 über den Body, hex-encoded).
 */

use Core\Database;
use Core\Response;
use Core\Settings;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Nur POST', 405);
}

$rawBody = file_get_contents('php://input');
if (!$rawBody) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'empty body']);
    exit;
}

// ─── Signatur-Validierung (wenn Secret gesetzt) ─────────────────────────────
$secret = (string)Settings::get('brevo_webhook_secret', '');
if ($secret !== '') {
    $signature = $_SERVER['HTTP_X_MAILIN_SIGNATURE'] ?? '';
    $erwartet = hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($erwartet, $signature)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'reason' => 'invalid signature']);
        exit;
    }
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid json']);
    exit;
}

// Brevo kann einzelne Events ODER Arrays schicken
$events = isset($payload[0]) ? $payload : [$payload];

$db = Database::getInstance();
$verarbeitet = 0;

foreach ($events as $event) {
    // Brevo-Event-Felder mappen
    $email = trim((string)($event['email'] ?? ''));
    $brevoEvent = strtolower((string)($event['event'] ?? ''));

    // Mapping zu unserem ENUM
    $mapping = [
        'sent'         => 'sent',
        'delivered'    => 'delivered',
        'opened'       => 'open',
        'click'        => 'click',
        'soft_bounce'  => 'soft_bounce',
        'hard_bounce'  => 'hard_bounce',
        'invalid_email'=> 'invalid',
        'spam'         => 'spam',
        'blocked'      => 'blocked',
        'unsubscribed' => 'unsubscribed',
        'deferred'     => 'deferred',
        'complaint'    => 'complaint',
    ];
    $eventTyp = $mapping[$brevoEvent] ?? null;
    if ($eventTyp === null) {
        // unbekanntes Event: trotzdem speichern als 'sent' und in raw_json
        $eventTyp = 'sent';
    }

    // Kontakt suchen (per email)
    $kontaktId = null;
    if ($email !== '') {
        $found = $db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ? AND geloescht_am IS NULL", [$email]);
        if ($found) $kontaktId = (int)$found;
        else {
            $found = $db->queryValue("SELECT id FROM crm_kontakte WHERE email_zweit = ? AND geloescht_am IS NULL", [$email]);
            if ($found) $kontaktId = (int)$found;
        }
    }

    // Event speichern
    $db->insert('crm_brevo_events', [
        'kontakt_id'    => $kontaktId,
        'brevo_email'   => $email,
        'event_typ'     => $eventTyp,
        'campaign_id'   => isset($event['camp_id']) ? (int)$event['camp_id'] : null,
        'campaign_name' => $event['camp_name'] ?? null,
        'link_url'      => $event['link'] ?? null,
        'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'raw_json'      => json_encode($event, JSON_UNESCAPED_UNICODE),
    ]);

    // Zeitlinien-Eintrag (nur wenn Kontakt bekannt + relevantes Event)
    if ($kontaktId && in_array($eventTyp, ['open','click','hard_bounce','unsubscribed','spam','complaint'], true)) {
        $aktTyp = match ($eventTyp) {
            'open'         => 'mail_open',
            'click'        => 'mail_click',
            'hard_bounce'  => 'mail_bounce',
            'unsubscribed' => 'mail_unsubscribe',
            'spam'         => 'mail_unsubscribe',
            'complaint'    => 'mail_unsubscribe',
            default        => 'sonstiges',
        };
        $db->insert('crm_aktivitaeten', [
            'kontakt_id'    => $kontaktId,
            'typ'           => $aktTyp,
            'titel'         => ($event['camp_name'] ?? '') ?: ucfirst($eventTyp),
            'metadata_json' => json_encode($event, JSON_UNESCAPED_UNICODE),
            'quelle'        => 'brevo_webhook',
        ]);

        // Opt-Status nach Bounce/Unsubscribe sofort am Kontakt aktualisieren
        if ($eventTyp === 'hard_bounce') {
            $db->update('crm_kontakte', ['opt_in_status' => 'hard_bounce'], 'id = ?', [$kontaktId]);
        }
        if (in_array($eventTyp, ['unsubscribed','spam','complaint'], true)) {
            $db->update('crm_kontakte', ['opt_in_status' => 'unsubscribed'], 'id = ?', [$kontaktId]);
        }
    }

    $verarbeitet++;
}

// Brevo erwartet 200 OK
http_response_code(200);
echo json_encode(['ok' => true, 'verarbeitet' => $verarbeitet]);
