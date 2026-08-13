<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM_DSGVO)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance();
$kontakt = $db->queryOne("SELECT id, email_primaer FROM crm_kontakte WHERE id = ?", [$id]);
if (!$kontakt) Response::error('Kontakt nicht gefunden', 404);

// Tombstone schreiben BEVOR wir löschen
$db->insert('crm_loesch_events', [
    'entity_typ' => 'kontakt',
    'entity_id' => $id,
    'geloescht_durch' => Auth::id(),
    'art' => 'hard',
    'grund' => 'DSGVO-Anfrage',
]);

// Stammdaten + Adressen + Lead-Magnet-Events + Aktivitäten + Opt-In + Social → komplett weg
// (Brevo-Events werden ANONYMISIERT, nicht gelöscht — Statistik-Erhalt laut Entscheidung 6)
$db->execute("UPDATE crm_brevo_events SET brevo_email = NULL WHERE kontakt_id = ? OR brevo_email = ?", [$id, $kontakt['email_primaer']]);
$db->execute("UPDATE crm_brevo_events SET kontakt_id = NULL WHERE kontakt_id = ?", [$id]);

// Foreign-Key-Cascade kümmert sich um Adressen, Tags, Listen, Aktivitäten, etc.
$db->execute("DELETE FROM crm_kontakte WHERE id = ?", [$id]);

// Audit
\Core\AuditLog::record('crm_kontakt', (string)$id, 'hard_deleted_dsgvo', ['email' => $kontakt['email_primaer']]);

Response::success(['ok' => true, 'tombstone_id' => $db->queryValue("SELECT MAX(id) FROM crm_loesch_events")], 'Kontakt physisch gelöscht. Brevo-Events anonymisiert.');
