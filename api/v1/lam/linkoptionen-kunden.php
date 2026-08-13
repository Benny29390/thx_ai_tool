<?php
/**
 * GET /api/v1/lam/linkoptionen-kunden
 * Liefert die Liste aller Kunden mit Linkoption-Einträgen (für Filter-Dropdown).
 * Inkl. Anzahl Einträge pro Kunde.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$db = Database::getInstance();
// Alle Kunden mit irgendeiner LAM-Aktivität: Linkpool ODER Vorschlagslisten-Eintrag
$rows = $db->query(
    "SELECT c.id, c.name, c.abbreviation,
            (SELECT COUNT(*) FROM lam_domain_customer dc WHERE dc.customer_id = c.id) AS linkpool_count,
            (SELECT COUNT(e.id)
               FROM lam_vorschlagsliste_eintraege e
               JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id AND v.geloescht_am IS NULL
               WHERE v.customer_id = c.id) AS eintrag_count
     FROM customers c
     WHERE EXISTS (SELECT 1 FROM lam_domain_customer dc WHERE dc.customer_id = c.id)
        OR EXISTS (SELECT 1 FROM lam_vorschlagslisten v WHERE v.customer_id = c.id AND v.geloescht_am IS NULL)
     ORDER BY c.name ASC"
) ?: [];

Response::success($rows);
