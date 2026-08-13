<?php
/**
 * DEAKTIVIERT — Projektplanner und Asana synchronisieren ihre Status NICHT mehr.
 *
 * Stand 2026-05-27: Erledigt-Status werden manuell in beiden Systemen gepflegt.
 * Der Asana-Link auf einer Plan-Zeile ist nur eine Sprung-Verknuepfung, keine
 * Daten-Bruecke. Der frueher hier laufende Sync hat manuell in der KI-Tool
 * gesetzte Erledigt-Flags zurueckgesetzt — das war so nicht gewuenscht.
 *
 * Datei bleibt als Stub erhalten, falls Cron einmal versehentlich noch laeuft.
 * Der eigentliche Cron-Eintrag /etc/cron.d/ki-tool-pp-asana-sync wurde geloescht.
 */
echo date('Y-m-d H:i:s') . " Asana-Sync ist deaktiviert (kein Status-Mirror gewuenscht).\n";
exit(0);
