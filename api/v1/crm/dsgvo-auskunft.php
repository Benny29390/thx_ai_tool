<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM_DSGVO)) Response::forbidden();
$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance();

$kontakt = $db->queryOne("SELECT * FROM crm_kontakte WHERE id = ?", [$id]);
if (!$kontakt) Response::error('Kontakt nicht gefunden', 404);

$auskunft = [
    'kontakt' => $kontakt,
    'firma'   => $kontakt['firma_id'] ? $db->queryOne("SELECT * FROM crm_firmen WHERE id = ?", [$kontakt['firma_id']]) : null,
    'adressen' => $db->query("SELECT * FROM crm_adressen WHERE kontakt_id = ?", [$id]),
    'social' => $db->query("SELECT * FROM crm_social_links WHERE kontakt_id = ?", [$id]),
    'tags' => $db->query("SELECT t.* FROM crm_tags t JOIN crm_kontakt_tags kt ON t.id = kt.tag_id WHERE kt.kontakt_id = ?", [$id]),
    'listen' => $db->query("SELECT l.*, kl.status, kl.beigetreten_am FROM crm_listen l JOIN crm_kontakt_listen kl ON l.id = kl.listen_id WHERE kl.kontakt_id = ?", [$id]),
    'aktivitaeten' => $db->query("SELECT * FROM crm_aktivitaeten WHERE kontakt_id = ? ORDER BY erstellt_am DESC", [$id]),
    'opt_in_events' => $db->query("SELECT * FROM crm_opt_in_events WHERE kontakt_id = ? ORDER BY erfolgt_am DESC", [$id]),
    'brevo_events' => $db->query("SELECT * FROM crm_brevo_events WHERE kontakt_id = ? ORDER BY empfangen_am DESC LIMIT 1000", [$id]),
    'lead_magnet_events' => $db->query("SELECT * FROM crm_lead_magnet_events WHERE kontakt_id = ?", [$id]),
    'auskunft_erstellt_am' => date('c'),
    'auskunft_erstellt_durch' => Auth::user()['name'] ?? 'unbekannt',
];

// Als JSON-Download anbieten
$filename = 'dsgvo-auskunft-' . $id . '-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($auskunft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
