<?php
/** LAM-Tab — Modul-spezifische Einstellungen für das Linkaufbau-Management. */
$intervallAktuell = (int)\Core\Settings::get('lam_monitoring_intervall_minuten', 1440);
$sistrixVeraltet  = (int)\Core\Settings::get('lam_sistrix_veraltet_monate', 12);
$ohneAntwortTage  = (int)\Core\Settings::get('lam_ohne_antwort_tage', 14);

$intervallOptionen = [
    15 => 'alle 15 Minuten',
    30 => 'alle 30 Minuten',
    60 => 'stündlich',
    120 => 'alle 2 Stunden',
    360 => 'alle 6 Stunden',
    720 => 'alle 12 Stunden',
    1440 => 'täglich',
];
?>
<div class="settings-card">
    <h2>Monitoring-Scheduler</h2>
    <p class="settings-card-sub">
        Wie oft prüft der HTTP-Checker live-Maßnahmen? Der Cron unter
        <code>/etc/cron.d/ki-tool-lam-monitoring</code> liest diesen Wert beim nächsten Lauf.
        Die Cron-Datei selbst hat einen 5-Minuten-Tick — die effektive Frequenz wird hier gesteuert.
    </p>
    <form id="form-lam-monitoring"
          onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Monitoring-Intervall gespeichert' });">
        <div class="settings-field" style="max-width:280px;">
            <label for="lam_monitoring_intervall_minuten">Prüf-Frequenz</label>
            <select id="lam_monitoring_intervall_minuten" name="lam_monitoring_intervall_minuten">
                <?php foreach ($intervallOptionen as $min => $label): ?>
                    <option value="<?= $min ?>" <?= $intervallAktuell === $min ? 'selected' : '' ?>>
                        <?= $label ?> (<?= $min ?> Min)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Linkquellen-Wartung</h2>
    <form id="form-lam-pool"
          onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Pool-Einstellungen gespeichert' });">
        <div class="settings-field" style="max-width:280px;">
            <label for="lam_sistrix_veraltet_monate">Sistrix-Werte gelten als „veraltet" nach</label>
            <input type="number" id="lam_sistrix_veraltet_monate" name="lam_sistrix_veraltet_monate"
                   value="<?= $sistrixVeraltet ?>" min="1" max="36">
            <span class="field-hint">Monate. Triggert das orange „jetzt aktualisieren"-Banner im Linkquellen-Detail.</span>
        </div>
        <div class="settings-field" style="max-width:280px;">
            <label for="lam_ohne_antwort_tage">Linkakquise-Auto-„ohne Antwort" nach</label>
            <input type="number" id="lam_ohne_antwort_tage" name="lam_ohne_antwort_tage"
                   value="<?= $ohneAntwortTage ?>" min="1" max="90">
            <span class="field-hint">Tagen seit Erstkontakt. Vorschlag in der Linkakquise-UI.</span>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Aufräum-Modus (Sitewide-Cluster)</h2>
    <?php $clusterSchwelle = (int)\Core\Settings::get('lam_sitewide_schwelle', 5); ?>
    <form id="form-lam-cluster"
          onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Schwelle gespeichert' });">
        <div class="settings-field" style="max-width:280px;">
            <label for="lam_sitewide_schwelle">Sitewide-Cluster ab N Verlinkungen</label>
            <input type="number" id="lam_sitewide_schwelle" name="lam_sitewide_schwelle"
                   value="<?= $clusterSchwelle ?>" min="2" max="50">
            <span class="field-hint">Default 5. Diese Schwelle ist auch in der Aufräum-Sicht pro Anfrage überschreibbar.</span>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Aufräum-Modus (URL-Score-Gewichte)</h2>
    <p class="settings-card-sub">
        Bei „Auf 1 reduzieren" / „Auf 2 reduzieren" sucht das System pro Domain die <strong>beste</strong> URL.
        Der Score ist ein gewichtetes Mittel aus 5 Faktoren — hier kannst Du die Gewichte pro Linkart anpassen.
        Negative Werte sind erlaubt (z.B. <code>tiefe = -0.20</code> bevorzugt Deeplinks).
    </p>
    <?php
    $gewichteJson = (string)\Core\Settings::get('lam_aufraeum_gewichte', '');
    $gewichte = $gewichteJson ? (json_decode($gewichteJson, true) ?: []) : [];
    $linkarten = ['default', 'spam', 'branchenverzeichnis', 'fachverzeichnis', 'online_magazin', 'portal',
                  'blog', 'presseportal', 'forum', 'referenzprojekt', 'partner', 'sponsoring',
                  'stellenboerse', 'veranstaltung', 'kommentarlink', 'podcast', 'weiterleitung',
                  'social_media', 'sonstiges'];
    $faktoren = ['si' => 'SI', 'http' => 'HTTP', 'tiefe' => 'Tiefe', 'linktext' => 'Linktext', 'neuheit' => 'Neuheit'];
    $defaultRow = $gewichte['default'] ?? ['si' => 0.40, 'http' => 0.25, 'tiefe' => 0.15, 'linktext' => 0.15, 'neuheit' => 0.05];
    ?>
    <div style="overflow-x:auto;">
    <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid #e2e8f0;">
                <th style="text-align:left;padding:6px 8px;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Linkart</th>
                <?php foreach ($faktoren as $k => $label): ?>
                    <th style="text-align:right;padding:6px 8px;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;width:90px;"
                        title="<?= htmlspecialchars(['si'=>'Sistrix-Sichtbarkeitsindex','http'=>'HTTP erreichbar','tiefe'=>'URL-Tiefe (Default: weniger = besser; negativ → Deeplink bevorzugt)','linktext'=>'Linktext vorhanden','neuheit'=>'Erstellungs-Alter (jünger = besser)'][$k]) ?>">
                        <?= $label ?>
                    </th>
                <?php endforeach; ?>
                <th style="text-align:right;padding:6px 8px;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;width:60px;">Σ</th>
            </tr>
        </thead>
        <tbody id="gewichte-tbody">
            <?php foreach ($linkarten as $la):
                $vals = $gewichte[$la] ?? ($la === 'default' ? $defaultRow : []);
                $istLeer = empty($vals);
                $summe = 0;
                foreach ($faktoren as $k => $_) $summe += (float)($vals[$k] ?? 0);
            ?>
                <tr data-linkart="<?= htmlspecialchars($la) ?>" style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:5px 8px;<?= $la === 'default' ? 'font-weight:600;background:#f8fafc;' : '' ?>">
                        <?= $la === 'default' ? '<strong>Default</strong> (für alle ohne eigene Werte)' : '<code style="font-size:0.8rem;color:#475569;">' . htmlspecialchars($la) . '</code>' ?>
                    </td>
                    <?php foreach ($faktoren as $k => $_): ?>
                        <td style="padding:3px 4px;text-align:right;">
                            <input type="number" step="0.05" min="-1" max="1"
                                   value="<?= htmlspecialchars((string)($vals[$k] ?? '')) ?>"
                                   <?= $istLeer && $la !== 'default' ? 'placeholder="-"' : '' ?>
                                   data-faktor="<?= $k ?>"
                                   onchange="berechneSumme(this.closest('tr'))"
                                   style="width:75px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.8rem;text-align:right;font-variant-numeric:tabular-nums;">
                        </td>
                    <?php endforeach; ?>
                    <td style="padding:5px 8px;text-align:right;font-weight:600;font-variant-numeric:tabular-nums;color:#64748b;" class="row-sum">
                        <?= $istLeer && $la !== 'default' ? '—' : number_format($summe, 2, ',', '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="field-hint" style="margin-top:8px;">
        <strong>Tipp:</strong> leer lassen = nimmt den Default. Setze nur die Werte, die für die jeweilige Linkart anders sein sollen.
        Die Summe muss <em>nicht</em> 1.0 ergeben — sie zeigt nur, wie aggressiv die Gewichtung insgesamt ist.
    </p>
    <div class="settings-actions">
        <button type="button" class="thx-btn thx-btn-primary" onclick="speichereGewichte()">Speichern</button>
        <button type="button" class="thx-btn thx-btn-secondary" onclick="setzeGewichteZurueck()">Auf Defaults zurücksetzen</button>
    </div>
</div>

<script>
function berechneSumme(tr) {
    let s = 0;
    tr.querySelectorAll('input[type=number]').forEach(i => { s += parseFloat(i.value) || 0; });
    const cell = tr.querySelector('.row-sum');
    if (cell) {
        cell.textContent = s.toFixed(2).replace('.', ',');
        cell.style.color = Math.abs(s) < 0.001 ? '#94a3b8' : '#475569';
    }
}
async function speichereGewichte() {
    const out = {};
    document.querySelectorAll('#gewichte-tbody tr').forEach(tr => {
        const la = tr.dataset.linkart;
        const row = {};
        let hasValue = false;
        tr.querySelectorAll('input[type=number]').forEach(i => {
            const v = i.value.trim();
            if (v !== '') {
                row[i.dataset.faktor] = parseFloat(v);
                hasValue = true;
            }
        });
        if (la === 'default' || hasValue) out[la] = row;
    });
    try {
        const resp = await App.request('POST', '/admin/settings', { lam_aufraeum_gewichte: JSON.stringify(out, null, 2) });
        if (resp.success) App.showNotification('Score-Gewichte gespeichert', 'success');
        else App.showNotification(resp.message || 'Fehler', 'error');
    } catch (e) { App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error'); }
}
async function setzeGewichteZurueck() {
    if (!confirm('Alle Gewichte auf Defaults zurücksetzen?')) return;
    const defaults = JSON.stringify({
        default:              { si: 0.40, http: 0.25, tiefe:  0.15, linktext: 0.15, neuheit: 0.05 },
        blog:                 { si: 0.30, http: 0.20, tiefe:  0.05, linktext: 0.35, neuheit: 0.10 },
        online_magazin:       { si: 0.35, http: 0.20, tiefe:  0.05, linktext: 0.30, neuheit: 0.10 },
        forum:                { si: 0.25, http: 0.30, tiefe:  0.10, linktext: 0.30, neuheit: 0.05 },
        branchenverzeichnis:  { si: 0.50, http: 0.25, tiefe:  0.10, linktext: 0.10, neuheit: 0.05 },
    }, null, 2);
    try {
        const resp = await App.request('POST', '/admin/settings', { lam_aufraeum_gewichte: defaults });
        if (resp.success) {
            App.showNotification('Defaults wiederhergestellt — Seite wird neu geladen', 'success');
            setTimeout(() => location.reload(), 700);
        }
    } catch (e) { App.showNotification('Fehler: ' + e.message, 'error'); }
}
</script>
