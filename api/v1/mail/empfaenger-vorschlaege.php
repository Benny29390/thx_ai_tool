<?php
/** GET /mail/empfaenger-vorschlaege?q=...  -> [{email, name}] aus bekannten Kontakten */
use Core\Response;
global $db;
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) Response::success([]);
$like = '%' . $q . '%';
$treffer = [];
// LAM-Kontakte
try {
    foreach ($db->query("SELECT DISTINCT email, TRIM(CONCAT(COALESCE(vorname,''),' ',COALESCE(nachname,''))) AS name
                         FROM lam_kontakte WHERE email LIKE ? OR vorname LIKE ? OR nachname LIKE ? LIMIT 8",
                         [$like,$like,$like]) as $r) {
        if (!empty($r['email'])) $treffer[strtolower($r['email'])] = ['email'=>$r['email'],'name'=>trim($r['name'])];
    }
} catch (\Throwable $e) {}
// Bisherige Korrespondenz-Partner
try {
    foreach ($db->query("SELECT DISTINCT absender_email AS email, absender_name AS name
                         FROM mail_nachrichten WHERE absender_email LIKE ? AND absender_email <> '' LIMIT 8", [$like]) as $r) {
        $k = strtolower((string)$r['email']);
        if ($k && !isset($treffer[$k])) $treffer[$k] = ['email'=>$r['email'],'name'=>trim((string)$r['name'])];
    }
} catch (\Throwable $e) {}
Response::success(array_values($treffer));
