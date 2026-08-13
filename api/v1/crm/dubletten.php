<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();

// Dubletten-Scan ist eine schwere Query — explizit triggern, sonst hängt jede /crm/dubletten-View
// 60 Sekunden lang. Default ist „nicht scannen".
if (empty($_GET['scan']) || $_GET['scan'] !== '1') {
    Response::success(['dubletten' => [], 'anzahl' => 0, 'scan_erforderlich' => true]);
}

// Harter Cap, damit der Webrequest auch im Worst-Case nicht hängt
set_time_limit(60);
@ini_set('memory_limit', '256M');

$db = Database::getInstance();

/**
 * Dubletten-Erkennung über 3 Match-Typen:
 *   - email:    primaer = primaer ODER primaer = zweit
 *   - telefon:  normalisiert (nur Ziffern, Mindestlänge 7) gleicher Wert in mobil/telefon
 *   - firma+name: gleiche firma_id UND gleicher Nachname
 *
 * UNION über die Match-Typen, eindeutige Paare (id1 < id2).
 */
$limit = max(50, min(500, (int)($_GET['limit'] ?? 200)));

$sql = "
    (
        SELECT k1.id AS id1, k1.vorname AS v1, k1.nachname AS n1, k1.email_primaer AS e1, k1.telefon AS t1, k1.firma_id AS f1, fa1.firmenname AS fn1,
               k2.id AS id2, k2.vorname AS v2, k2.nachname AS n2, k2.email_primaer AS e2, k2.telefon AS t2, k2.firma_id AS f2, fa2.firmenname AS fn2,
               'email' AS match_typ, k1.email_primaer AS match_wert
        FROM crm_kontakte k1
        JOIN crm_kontakte k2 ON (
                k1.email_primaer = k2.email_primaer
             OR k1.email_primaer = k2.email_zweit
             OR k1.email_zweit  = k2.email_primaer
        ) AND k1.id < k2.id
        LEFT JOIN crm_firmen fa1 ON fa1.id = k1.firma_id
        LEFT JOIN crm_firmen fa2 ON fa2.id = k2.firma_id
        WHERE k1.geloescht_am IS NULL AND k2.geloescht_am IS NULL
          AND k1.email_primaer IS NOT NULL AND k1.email_primaer <> ''
    )
    UNION
    (
        SELECT k1.id AS id1, k1.vorname AS v1, k1.nachname AS n1, k1.email_primaer AS e1, k1.telefon AS t1, k1.firma_id AS f1, fa1.firmenname AS fn1,
               k2.id AS id2, k2.vorname AS v2, k2.nachname AS n2, k2.email_primaer AS e2, k2.telefon AS t2, k2.firma_id AS f2, fa2.firmenname AS fn2,
               'telefon' AS match_typ,
               COALESCE(NULLIF(REGEXP_REPLACE(k1.mobil, '[^0-9]', ''), ''),
                        NULLIF(REGEXP_REPLACE(k1.telefon, '[^0-9]', ''), ''),
                        REGEXP_REPLACE(COALESCE(k1.telefon_alt,''), '[^0-9]', '')) AS match_wert
        FROM crm_kontakte k1
        JOIN crm_kontakte k2
            ON k1.id < k2.id
           AND (
                (LENGTH(REGEXP_REPLACE(COALESCE(k1.mobil,''),  '[^0-9]','')) >= 7 AND REGEXP_REPLACE(COALESCE(k1.mobil,''),  '[^0-9]','') = REGEXP_REPLACE(COALESCE(k2.mobil,''),  '[^0-9]',''))
             OR (LENGTH(REGEXP_REPLACE(COALESCE(k1.telefon,''),'[^0-9]','')) >= 7 AND REGEXP_REPLACE(COALESCE(k1.telefon,''),'[^0-9]','') = REGEXP_REPLACE(COALESCE(k2.telefon,''),'[^0-9]',''))
             OR (LENGTH(REGEXP_REPLACE(COALESCE(k1.mobil,''),  '[^0-9]','')) >= 7 AND REGEXP_REPLACE(COALESCE(k1.mobil,''),  '[^0-9]','') = REGEXP_REPLACE(COALESCE(k2.telefon,''),'[^0-9]',''))
             OR (LENGTH(REGEXP_REPLACE(COALESCE(k1.telefon,''),'[^0-9]','')) >= 7 AND REGEXP_REPLACE(COALESCE(k1.telefon,''),'[^0-9]','') = REGEXP_REPLACE(COALESCE(k2.mobil,''),  '[^0-9]',''))
           )
        LEFT JOIN crm_firmen fa1 ON fa1.id = k1.firma_id
        LEFT JOIN crm_firmen fa2 ON fa2.id = k2.firma_id
        WHERE k1.geloescht_am IS NULL AND k2.geloescht_am IS NULL
    )
    UNION
    (
        SELECT k1.id AS id1, k1.vorname AS v1, k1.nachname AS n1, k1.email_primaer AS e1, k1.telefon AS t1, k1.firma_id AS f1, fa1.firmenname AS fn1,
               k2.id AS id2, k2.vorname AS v2, k2.nachname AS n2, k2.email_primaer AS e2, k2.telefon AS t2, k2.firma_id AS f2, fa2.firmenname AS fn2,
               'firma+name' AS match_typ, fa1.firmenname AS match_wert
        FROM crm_kontakte k1
        JOIN crm_kontakte k2
            ON k1.id < k2.id
           AND k1.firma_id = k2.firma_id
           AND LOWER(k1.nachname) = LOWER(k2.nachname)
        LEFT JOIN crm_firmen fa1 ON fa1.id = k1.firma_id
        LEFT JOIN crm_firmen fa2 ON fa2.id = k2.firma_id
        WHERE k1.geloescht_am IS NULL AND k2.geloescht_am IS NULL
          AND k1.firma_id IS NOT NULL
    )
    ORDER BY match_typ ASC
    LIMIT $limit
";

try {
    $dub = $db->query($sql);
} catch (\Throwable $e) {
    // REGEXP_REPLACE existiert ab MariaDB 10.0.5/MySQL 8 — Fallback ohne Telefon-Match
    $sql_fallback = "
        (
            SELECT k1.id AS id1, k1.vorname AS v1, k1.nachname AS n1, k1.email_primaer AS e1, k1.telefon AS t1, k1.firma_id AS f1, fa1.firmenname AS fn1,
                   k2.id AS id2, k2.vorname AS v2, k2.nachname AS n2, k2.email_primaer AS e2, k2.telefon AS t2, k2.firma_id AS f2, fa2.firmenname AS fn2,
                   'email' AS match_typ, k1.email_primaer AS match_wert
            FROM crm_kontakte k1
            JOIN crm_kontakte k2 ON (k1.email_primaer = k2.email_primaer OR k1.email_primaer = k2.email_zweit)
                                AND k1.id < k2.id
            LEFT JOIN crm_firmen fa1 ON fa1.id = k1.firma_id
            LEFT JOIN crm_firmen fa2 ON fa2.id = k2.firma_id
            WHERE k1.geloescht_am IS NULL AND k2.geloescht_am IS NULL
        )
        UNION
        (
            SELECT k1.id AS id1, k1.vorname AS v1, k1.nachname AS n1, k1.email_primaer AS e1, k1.telefon AS t1, k1.firma_id AS f1, fa1.firmenname AS fn1,
                   k2.id AS id2, k2.vorname AS v2, k2.nachname AS n2, k2.email_primaer AS e2, k2.telefon AS t2, k2.firma_id AS f2, fa2.firmenname AS fn2,
                   'firma+name' AS match_typ, fa1.firmenname AS match_wert
            FROM crm_kontakte k1
            JOIN crm_kontakte k2
                ON k1.id < k2.id
               AND k1.firma_id = k2.firma_id
               AND LOWER(k1.nachname) = LOWER(k2.nachname)
            LEFT JOIN crm_firmen fa1 ON fa1.id = k1.firma_id
            LEFT JOIN crm_firmen fa2 ON fa2.id = k2.firma_id
            WHERE k1.geloescht_am IS NULL AND k2.geloescht_am IS NULL
              AND k1.firma_id IS NOT NULL
        )
        LIMIT $limit
    ";
    $dub = $db->query($sql_fallback);
}

Response::success(['dubletten' => $dub, 'anzahl' => count($dub)]);
