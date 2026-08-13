<?php
/** GET /mail/ungelesen-zaehler — Anzahl ungelesener Eingangs-Mails über alle Konten */
use Core\Auth;
use Core\Database;
use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$zahl = (int)Database::getInstance()->queryValue(
    "SELECT COUNT(*) FROM mail_nachrichten
     WHERE richtung = 'eingang' AND geloescht_am IS NULL AND gelesen = 0"
);
Response::success(['ungelesen' => $zahl]);
