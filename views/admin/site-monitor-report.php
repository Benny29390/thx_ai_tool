<?php
/**
 * Druckfertiger Uptime-/Downtime-Report für EINEN Kunden.
 *
 * Aufruf: /admin/site-monitor/report?customer_id=X&days=N
 * Eigenständige Seite (nicht das main.php-Layout) — sauber für „Drucken → Als PDF speichern".
 * Öffnet den Druckdialog automatisch, wenn ?print=1 (der Export-Knopf setzt das).
 *
 * Daten kommen server-seitig aus PageMonitorService::getStatsForCustomer() — dieselbe Quelle
 * wie das Statistik-Modal, damit Zahlen im Report und in der Ansicht identisch sind.
 */

use Core\Auth;

require_once SERVICES_PATH . '/PageMonitorService.php';

// Zeiten in der Anzeige als MESZ/MEZ (Europe/Berlin) — DB und Server laufen auf UTC.
date_default_timezone_set('Europe/Berlin');

$db = \Core\Database::getInstance();

$customerId = (int) ($_GET['customer_id'] ?? 0);
$monitorId  = (int) ($_GET['monitor_id'] ?? 0);
$days       = max(1, min(366, (int) ($_GET['days'] ?? 30)));
$autoPrint  = !empty($_GET['print']);

$svc = new \Services\PageMonitorService($db);

// Zwei Modi: ganzer KUNDE (alle seine Websites) oder eine einzelne WEBSITE.
// Beide werden auf dieselbe Report-Struktur normalisiert (Kopf-Titel + „Zeilen").
if ($customerId > 0) {
    if (!Auth::canAccessCustomer($customerId)) {
        http_response_code(403); echo 'Kein Zugriff auf diesen Kunden.'; return;
    }
    $kunde  = $db->queryOne("SELECT id, name FROM customers WHERE id = ?", [$customerId]);
    $stats  = $svc->getStatsForCustomer($customerId, $days);
    $titel  = $kunde['name'] ?? 'Kunde';
    $unterZeile = ($stats['summary']['monitor_count'] ?? 0) . ' Website'
                . (($stats['summary']['monitor_count'] ?? 0) == 1 ? '' : 's');
    // Zeilen = pro Website
    $zeilen = array_map(fn($m) => [
        'name' => $m['label'], 'status' => $m['status'], 'uptime' => $m['uptime'],
        'outages' => $m['outages'], 'downtime_min' => $m['downtime_min'],
        'avg_ms' => $m['avg_ms'], 'checks' => $m['checks'],
    ], $stats['monitors']);
    $zeilenTitel = 'Aufschlüsselung pro Website';
    $zeilenSpalte = 'Website';
} elseif ($monitorId > 0) {
    $mon = $svc->getById($monitorId);
    if (!$mon) { http_response_code(404); echo 'Website nicht gefunden.'; return; }
    // Rechte: an den Kunden des Monitors gebunden (oder kundenlos → Admin-/Manager-Cap reicht via Route)
    if (!empty($mon['customer_id']) && !Auth::canAccessCustomer((int) $mon['customer_id'])) {
        http_response_code(403); echo 'Kein Zugriff.'; return;
    }
    $stats  = $svc->getStats($monitorId, $days);
    $titel  = $mon['label'];
    $unterZeile = $mon['customer_name'] ? ('Kunde: ' . $mon['customer_name']) : 'Eigenprojekt';
    // Zeilen = pro geprüfter URL des Monitors
    $zeilen = array_map(fn($u) => [
        'name' => $u['url'], 'status' => null, 'uptime' => $u['uptime'],
        'outages' => null, 'downtime_min' => null, 'avg_ms' => $u['avg_ms'], 'checks' => $u['checks'],
    ], $stats['urls'] ?? []);
    $zeilenTitel = 'Aufschlüsselung pro geprüfter Adresse';
    $zeilenSpalte = 'Adresse (URL)';
} else {
    http_response_code(400); echo 'customer_id oder monitor_id erforderlich.'; return;
}

$s = $stats['summary'];

// function_exists-Guards: die View ist zwar eine eigene Route (einmal pro Request), aber ein
// versehentlicher Doppel-Include darf sie nicht mit „Cannot redeclare" sprengen.
if (!function_exists('pmDauer')) {
    /** Minuten → „X Std Y Min" bzw. „Y Min". */
    function pmDauer(int $min): string
    {
        if ($min <= 0) return '0 Min';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $h > 0 ? "{$h} Std {$m} Min" : "{$m} Min";
    }
    function pmDatum(?string $s): string
    {
        if (!$s) return '—';
        // DB-Zeitstempel liegen in UTC vor → explizit als UTC lesen und nach Europe/Berlin wandeln.
        try {
            $dt = new \DateTime($s, new \DateTimeZone('UTC'));
            $dt->setTimezone(new \DateTimeZone('Europe/Berlin'));
            return $dt->format('d.m.Y H:i');
        } catch (\Exception $e) {
            return (string) $s;
        }
    }
    function pmZeitraumLabel(int $d): string
    {
        if ($d <= 7)   return 'letzte 7 Tage';
        if ($d <= 31)  return 'letzte 30 Tage';
        if ($d <= 93)  return 'letztes Quartal';
        return 'letztes Jahr';
    }
}

$vonDatum = date('d.m.Y', strtotime("-".($days-1)." days midnight"));
$bisDatum = date('d.m.Y');
$erstellt = date('d.m.Y H:i') . ' Uhr';
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Verfügbarkeits-Report — <?= $e($kunde['name'] ?? 'Kunde') ?></title>
<style>
    :root { --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --thx:#005da8; --ok:#15803d; --bad:#b91c1c; }
    * { box-sizing: border-box; }
    body { font-family: 'Frutiger LT Std','Segoe UI',Arial,sans-serif; color: var(--ink);
           margin: 0; padding: 32px 36px; font-size: 12.5px; line-height: 1.45; }
    h1 { font-size: 22px; margin: 0 0 2px; }
    h2 { font-size: 14px; margin: 26px 0 10px; padding-bottom: 5px; border-bottom: 2px solid var(--thx); }
    .sub { color: var(--muted); font-size: 12px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px;
            border-bottom: 3px solid var(--thx); padding-bottom: 14px; margin-bottom: 4px; }
    .brand { font-size: 16px; font-weight: 700; color: var(--thx); letter-spacing: .3px; white-space: nowrap; }

    .kpis { display: flex; gap: 12px; margin: 18px 0 4px; flex-wrap: wrap; }
    .kpi { flex: 1; min-width: 120px; border: 1px solid var(--line); border-radius: 8px; padding: 12px 14px; }
    .kpi .label { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
    .kpi .value { font-size: 22px; font-weight: 700; margin-top: 3px; }
    .kpi .value.ok { color: var(--ok); } .kpi .value.bad { color: var(--bad); }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { text-align: left; padding: 7px 10px; border-bottom: 1px solid var(--line); font-size: 12px; }
    th { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); background: #f8fafc; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 600; }
    .badge.on { background: #dcfce7; color: var(--ok); } .badge.off { background: #fee2e2; color: var(--bad); }
    .badge.pause { background: #f1f5f9; color: var(--muted); }
    .uphigh { color: var(--ok); font-weight: 600; } .uplow { color: var(--bad); font-weight: 600; }

    .foot { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--line);
            color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
    .empty { color: var(--muted); font-style: italic; padding: 10px 0; }

    /* Druck: Ränder schlank, keine Umbrüche mitten in Zeilen, Kopf-Wiederholung */
    @page { margin: 14mm 12mm; }
    @media print {
        body { padding: 0; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .no-print { display: none !important; }
    }
    .toolbar { text-align: right; margin-bottom: 14px; }
    .btn { background: var(--thx); color: #fff; border: none; border-radius: 7px;
           padding: 9px 16px; font-size: 13px; cursor: pointer; }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn" onclick="window.print()">🖨 Als PDF speichern / Drucken</button>
</div>

<div class="head">
    <div>
        <h1>Verfügbarkeits-Report</h1>
        <div class="sub"><strong><?= $e($titel) ?></strong> ·
            Zeitraum <?= pmZeitraumLabel($days) ?> (<?= $vonDatum ?> – <?= $bisDatum ?>) ·
            <?= $e($unterZeile) ?></div>
    </div>
    <div class="brand">Thoxan Communications GmbH</div>
</div>

<?php if (empty($zeilen)): ?>
    <p class="empty">Keine überwachten Adressen im gewählten Zeitraum.</p>
<?php else: ?>

<div class="kpis">
    <div class="kpi"><div class="label">Verfügbarkeit</div>
        <div class="value <?= $s['uptime'] >= 99 ? 'ok' : ($s['uptime'] < 95 ? 'bad' : '') ?>"><?= number_format($s['uptime'], 2, ',', '.') ?>%</div></div>
    <div class="kpi"><div class="label">Ausfälle</div>
        <div class="value"><?= (int) $s['outages'] ?></div></div>
    <div class="kpi"><div class="label">Ausfallzeit gesamt</div>
        <div class="value"><?= pmDauer((int) $s['downtime_min']) ?></div></div>
    <div class="kpi"><div class="label">Ø Antwortzeit</div>
        <div class="value"><?= number_format((float) $s['avg_ms'], 0, ',', '.') ?> ms</div></div>
    <div class="kpi"><div class="label">Prüfungen</div>
        <div class="value"><?= number_format((int) $s['checks'], 0, ',', '.') ?></div></div>
</div>

<h2><?= $e($zeilenTitel) ?></h2>
<table>
    <thead><tr>
        <th><?= $e($zeilenSpalte) ?></th>
        <?php if ($customerId > 0): ?><th>Status</th><?php endif; ?>
        <th class="num">Verfügbarkeit</th>
        <?php if ($customerId > 0): ?><th class="num">Ausfälle</th><th class="num">Ausfallzeit</th><?php endif; ?>
        <th class="num">Ø Antwort</th><th class="num">Prüfungen</th>
    </tr></thead>
    <tbody>
    <?php foreach ($zeilen as $z):
        $badge = $z['status'] === 'up' ? ['on','Online'] : ($z['status'] === 'paused' ? ['pause','Pausiert'] : ['off','Offline']); ?>
        <tr>
            <td><strong><?= $e($z['name']) ?></strong></td>
            <?php if ($customerId > 0): ?><td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td><?php endif; ?>
            <td class="num <?= $z['uptime'] >= 99 ? 'uphigh' : ($z['uptime'] < 95 ? 'uplow' : '') ?>"><?= number_format($z['uptime'], 2, ',', '.') ?>%</td>
            <?php if ($customerId > 0): ?>
                <td class="num"><?= (int) $z['outages'] ?></td>
                <td class="num"><?= pmDauer((int) $z['downtime_min']) ?></td>
            <?php endif; ?>
            <td class="num"><?= number_format((float) $z['avg_ms'], 0, ',', '.') ?> ms</td>
            <td class="num"><?= number_format((int) $z['checks'], 0, ',', '.') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Ausfälle im Zeitraum (<?= count($stats['incidents']) ?>)</h2>
<?php if (empty($stats['incidents'])): ?>
    <p class="empty">Keine Ausfälle im gewählten Zeitraum. 🎉</p>
<?php else: ?>
<table>
    <thead><tr>
        <?php if ($customerId > 0): ?><th>Website</th><?php endif; ?>
        <th>Beginn</th><th>Ende</th><th class="num">Dauer</th>
    </tr></thead>
    <tbody>
    <?php foreach ($stats['incidents'] as $inc): ?>
        <tr>
            <?php if ($customerId > 0): ?><td><?= $e($inc['monitor_label'] ?? '') ?></td><?php endif; ?>
            <td><?= pmDatum($inc['started_at'] ?? null) ?></td>
            <td><?= $inc['ended_at'] ? pmDatum($inc['ended_at']) : '<span class="uplow">läuft noch</span>' ?></td>
            <td class="num"><?= pmDauer((int) ($inc['duration_minutes'] ?? 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php endif; ?>

<div class="foot">
    <span>Thoxan Communications GmbH · Website-Monitoring</span>
    <span>Erstellt am <?= $erstellt ?></span>
</div>

<?php if ($autoPrint): ?>
<script>
    // Auf das vollständige Rendern warten, dann Druckdialog öffnen.
    window.addEventListener('load', () => setTimeout(() => window.print(), 350));
</script>
<?php endif; ?>
</body>
</html>
