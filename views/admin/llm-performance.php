<?php
/**
 * LLM-Performance — Vergleich lokal vs. Cloud anhand des llm_request_log.
 * Erwartet: $byModel, $perDayRaw, $totals, $days
 */
$byModel   = $byModel ?? [];
$perDayRaw = $perDayRaw ?? [];
$totals    = $totals ?? ['requests' => 0, 'errors' => 0, 'avg_tps' => null];
$days      = $days ?? 30;
$recent    = $recent ?? [];

$provLabel = function (string $p): string {
    return match ($p) {
        'openai' => 'OpenAI', 'anthropic' => 'Claude', 'google' => 'Google', 'local' => 'Lokal', default => $p,
    };
};

// Pivot fuer "Requests pro Modell pro Tag": Tage (Spalten) x Modelle (Zeilen)
$days_axis = [];
$models_axis = [];
$pivot = [];
foreach ($perDayRaw as $r) {
    $day = $r['day'];
    $m = $r['model'];
    $days_axis[$day] = true;
    $models_axis[$m] = true;
    $pivot[$m][$day] = (int)$r['requests'];
}
$days_axis = array_keys($days_axis);   // bereits DESC sortiert aus der Query
$models_axis = array_keys($models_axis);
sort($models_axis);
$errorRate = ($totals['requests'] ?? 0) > 0
    ? round(($totals['errors'] ?? 0) * 100.0 / $totals['requests'], 1)
    : 0;
?>
<div class="page-header">
    <div>
        <h1>Modell-Performance</h1>
        <p class="text-muted" style="margin:.25rem 0 0;max-width:60ch;">
            Hier siehst Du, wie schnell und zuverlässig die einzelnen KI-Modelle antworten —
            damit Du entscheiden kannst, welches lokale Modell sich für welchen Zweck lohnt.
        </p>
    </div>
    <div class="month-selector">
        <select id="days-select" onchange="window.location.href='/admin/llm-performance?days='+this.value">
            <?php foreach ([7, 14, 30, 90, 365] as $d): ?>
                <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Letzte <?= $d ?> Tage</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Klartext-Erklaerung der Kennzahlen -->
<details class="card mb-lg" style="padding:1rem 1.25rem;">
    <summary style="cursor:pointer;font-weight:600;">Was bedeuten diese Zahlen?</summary>
    <dl style="margin:.75rem 0 0;display:grid;grid-template-columns:max-content 1fr;gap:.4rem 1rem;">
        <dt><strong>Anfragen</strong></dt>
        <dd class="text-muted" style="margin:0;">Wie oft dieses Modell im Zeitraum benutzt wurde.</dd>

        <dt><strong>Tempo</strong> <span class="text-muted">(Tokens/s)</span></dt>
        <dd class="text-muted" style="margin:0;">Wie schnell das Modell schreibt. Ein Token ist ungefähr eine Silbe bzw. ein halbes Wort. <strong>Höher = schneller.</strong></dd>

        <dt><strong>Reaktionszeit</strong> <span class="text-muted">(TTFT)</span></dt>
        <dd class="text-muted" style="margin:0;">Wie lange es dauert, bis das erste Wort der Antwort kommt (in Millisekunden, 1000 ms = 1 Sekunde). <strong>Niedriger = reagiert flotter.</strong></dd>

        <dt><strong>Gesamtdauer</strong></dt>
        <dd class="text-muted" style="margin:0;">Wie lange eine komplette Antwort von Anfang bis Ende braucht. <strong>Niedriger = besser.</strong></dd>

        <dt><strong>Antwortlänge</strong></dt>
        <dd class="text-muted" style="margin:0;">Wie lang die Antworten im Schnitt sind (in Tokens). Nur zur Einordnung der anderen Werte — lange Antworten dauern naturgemäß länger.</dd>

        <dt><strong>Fehlerquote</strong></dt>
        <dd class="text-muted" style="margin:0;">Anteil der Anfragen, die schiefgegangen sind (z.B. Modell nicht erreichbar). <strong>Niedriger = zuverlässiger</strong>, am besten 0 %.</dd>
    </dl>
    <p class="text-muted" style="margin:.75rem 0 0;">
        Faustregel: schnelles Tempo + kurze Reaktionszeit + niedrige Fehlerquote = ein Modell, das sich gut für den Alltag eignet.
    </p>
</details>

<!-- Gesamtuebersicht -->
<div class="stats-grid mb-lg">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">forum</span></div>
        <div class="stat-content">
            <span class="stat-value"><?= number_format($totals['requests'] ?? 0) ?></span>
            <span class="stat-label">Anfragen gesamt</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">speed</span></div>
        <div class="stat-content">
            <span class="stat-value"><?= $totals['avg_tps'] !== null ? number_format($totals['avg_tps'], 1) : '–' ?></span>
            <span class="stat-label">ø Tempo (Tokens/s)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">error</span></div>
        <div class="stat-content">
            <span class="stat-value"><?= $errorRate ?> %</span>
            <span class="stat-label">Fehlerquote</span>
        </div>
    </div>
</div>

<!-- Pro Modell -->
<div class="card mb-lg">
    <h3 class="card-title">Pro Modell (letzte <?= $days ?> Tage)</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Modell</th>
                    <th>Anbieter</th>
                    <th class="text-right">Anfragen</th>
                    <th class="text-right">ø Tempo<br><span class="text-muted" style="font-weight:400;font-size:.85em;">Tokens/s, höher = schneller</span></th>
                    <th class="text-right">ø Reaktionszeit<br><span class="text-muted" style="font-weight:400;font-size:.85em;">bis 1. Wort, niedriger = besser</span></th>
                    <th class="text-right">ø Gesamtdauer<br><span class="text-muted" style="font-weight:400;font-size:.85em;">pro Antwort</span></th>
                    <th class="text-right">ø Antwortlänge<br><span class="text-muted" style="font-weight:400;font-size:.85em;">Tokens</span></th>
                    <th class="text-right">Fehlerquote</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($byModel)): ?>
                    <tr><td colspan="8" class="text-center text-muted">Noch keine Daten im Zeitraum</td></tr>
                <?php else: foreach ($byModel as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['model']) ?></strong></td>
                        <td><?= htmlspecialchars($provLabel($row['provider'])) ?></td>
                        <td class="text-right"><?= number_format($row['requests']) ?></td>
                        <td class="text-right"><?= $row['avg_tps'] !== null ? number_format($row['avg_tps'], 1) : '–' ?></td>
                        <td class="text-right"><?= $row['avg_ttft_ms'] !== null ? number_format($row['avg_ttft_ms']) . ' ms' : '–' ?></td>
                        <td class="text-right"><?= $row['avg_total_ms'] !== null ? number_format($row['avg_total_ms']) . ' ms' : '–' ?></td>
                        <td class="text-right"><?= $row['avg_out_tokens'] !== null ? number_format($row['avg_out_tokens']) : '–' ?></td>
                        <td class="text-right"><?= number_format((float)$row['error_rate'], 1) ?> %</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Letzte Anfragen mit Detail (Prompt / Wissen / Antwort) -->
<div class="card mb-lg">
    <h3 class="card-title">Letzte Anfragen (mit Detail)</h3>
    <p class="text-muted" style="margin:-.25rem 0 1rem;max-width:75ch;">
        Hier ist pro Chat-Anfrage festgehalten, was tatsächlich passiert ist: der vollständige
        System-Prompt, die Frage, die herangezogenen Wissens-Bausteine (Chunks) und die Antwort.
        Klick auf eine Zeile, um hinter die Kulissen zu schauen. Diese Detaildaten werden nach
        90 Tagen automatisch gelöscht.
    </p>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Modell</th>
                    <th>Nutzer</th>
                    <th>Kunde</th>
                    <th>Frage</th>
                    <th class="text-right">Wissen</th>
                    <th class="text-right">Dauer</th>
                    <th class="text-right"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent)): ?>
                    <tr><td colspan="8" class="text-center text-muted">Noch keine Detail-Daten im Zeitraum. Sobald über den Chat eine Anfrage läuft, erscheint sie hier.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                    <tr class="row-clickable" style="cursor:pointer;" onclick="window.location.href='/admin/llm-request-detail?id=<?= (int)$r['id'] ?>'">
                        <td><?= htmlspecialchars(date('d.m. H:i', strtotime($r['created_at']))) ?></td>
                        <td><strong><?= htmlspecialchars($r['model']) ?></strong></td>
                        <td><?= htmlspecialchars($r['user_name'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($r['customer_name'] ?? '–') ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth((string)($r['user_message'] ?? ''), 0, 60, '…')) ?></td>
                        <td class="text-right"><?= !empty($r['has_chunks']) ? '<span class="material-symbols-rounded" style="font-size:1.1em;vertical-align:middle;color:var(--thoxan-600,#2563eb);">database</span>' : '–' ?></td>
                        <td class="text-right"><?= $r['total_ms'] !== null ? number_format($r['total_ms']) . ' ms' : '–' ?></td>
                        <td class="text-right"><?php if (empty($r['success'])): ?><span class="material-symbols-rounded" style="color:#dc2626;font-size:1.1em;vertical-align:middle;" title="Fehlgeschlagen">error</span><?php endif; ?><span class="material-symbols-rounded text-muted" style="font-size:1.1em;vertical-align:middle;">chevron_right</span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Requests pro Modell pro Tag -->
<div class="card">
    <h3 class="card-title">Anfragen pro Modell pro Tag</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Modell</th>
                    <?php foreach ($days_axis as $day): ?>
                        <th class="text-right"><?= htmlspecialchars(date('d.m.', strtotime($day))) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models_axis)): ?>
                    <tr><td colspan="<?= count($days_axis) + 1 ?>" class="text-center text-muted">Noch keine Daten im Zeitraum</td></tr>
                <?php else: foreach ($models_axis as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m) ?></strong></td>
                        <?php foreach ($days_axis as $day): ?>
                            <td class="text-right"><?= isset($pivot[$m][$day]) ? number_format($pivot[$m][$day]) : '·' ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
