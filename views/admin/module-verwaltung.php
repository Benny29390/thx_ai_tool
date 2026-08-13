<?php
/**
 * Modul-Verwaltung — installationsweite Module an-/abschalten.
 * Daten aus core/Modules::withState() (via Route uebergeben als $module, $selfcheck).
 */
$gruppen = [];
foreach (($module ?? []) as $m) {
    $gruppen[$m['gruppe']][] = $m;
}
$extLabels = [
    'whisper' => 'Whisper (Transkription)',
    'qdrant'  => 'Qdrant (Vektor-DB)',
    'ffmpeg'  => 'ffmpeg',
    'yt-dlp'  => 'yt-dlp',
];
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">widgets</span>
            Module
        </h1>
        <p class="thx-page-subtitle">
            Lege fest, welche Module auf dieser Installation aktiv sind. Abgeschaltete Module
            verschwinden aus dem Menü und sind für alle gesperrt — auch für Administratoren.
            Kernbereiche (Kunden, Benutzer, Einstellungen) bleiben immer aktiv.
        </p>
    </div>
</div>

<?php if (!empty($selfcheck)): ?>
<div class="thx-card" style="border-left:4px solid var(--amber-500);margin-bottom:16px;">
    <strong style="color:var(--amber-700);">Hinweis zur Registry:</strong>
    <ul style="margin:6px 0 0;padding-left:20px;">
        <?php foreach ($selfcheck as $p): ?>
            <li style="font-size:var(--d-fs-sm);"><?= htmlspecialchars($p) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="mv-page">
<?php foreach ($gruppen as $gruppe => $mods): ?>
    <div class="mv-group">
        <h2 class="mv-group-title"><?= htmlspecialchars($gruppe) ?></h2>
        <div class="mv-grid">
        <?php foreach ($mods as $m): ?>
            <?php
                $core = !empty($m['core']);
                $licensed = !empty($m['licensed']);
                $active = !empty($m['active']);
                $enabled = !empty($m['enabled']);
                $state = $core ? 'core' : (!$licensed ? 'locked' : ($enabled ? 'on' : 'off'));
            ?>
            <div class="mv-card mv-<?= $state ?>" data-key="<?= htmlspecialchars($m['key']) ?>">
                <div class="mv-card-head">
                    <span class="material-symbols-rounded mv-icon"><?= htmlspecialchars($m['icon']) ?></span>
                    <div class="mv-titles">
                        <div class="mv-name"><?= htmlspecialchars($m['label']) ?></div>
                        <div class="mv-desc"><?= htmlspecialchars($m['beschreibung']) ?></div>
                    </div>
                    <div class="mv-control">
                        <?php if ($core): ?>
                            <span class="mv-badge mv-badge-core">immer aktiv</span>
                        <?php elseif (!$licensed): ?>
                            <span class="mv-badge mv-badge-locked" title="Nicht in Ihrem Paket freigeschaltet">
                                <span class="material-symbols-rounded" style="font-size:14px;">lock</span> gesperrt
                            </span>
                        <?php else: ?>
                            <label class="mv-switch" title="Modul ein-/ausschalten">
                                <input type="checkbox" class="mv-toggle" <?= $enabled ? 'checked' : '' ?>>
                                <span class="mv-slider"></span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($m['externals'])): ?>
                <div class="mv-ext">
                    <span class="mv-ext-label">Benötigt:</span>
                    <?php foreach ($m['externals'] as $ext): ?>
                        <?php if (strpos($ext, 'cron:') === 0) continue; // Cron nicht als "Werkzeug" anzeigen ?>
                        <span class="mv-ext-chip"><?= htmlspecialchars($extLabels[$ext] ?? $ext) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="mv-status" data-role="status"></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<style>
.mv-page { max-width: 1100px; display:flex; flex-direction:column; gap:24px; }
.mv-group-title { font-size:var(--d-fs-sm); text-transform:uppercase; letter-spacing:.5px;
    color:var(--slate-500); margin:0 0 10px; font-weight:700; }
.mv-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:12px; }
.mv-card { background:#fff; border:1px solid var(--slate-200); border-radius:10px; padding:14px 16px;
    transition:border-color .15s, box-shadow .15s; }
.mv-card.mv-off { background:var(--slate-50); }
.mv-card.mv-locked { background:var(--slate-50); opacity:.75; }
.mv-card-head { display:flex; align-items:flex-start; gap:12px; }
.mv-icon { color:var(--thoxan-600); font-size:24px; flex:0 0 auto; }
.mv-card.mv-off .mv-icon, .mv-card.mv-locked .mv-icon { color:var(--slate-400); }
.mv-titles { flex:1 1 auto; min-width:0; }
.mv-name { font-weight:700; color:var(--slate-800); }
.mv-desc { font-size:var(--d-fs-sm); color:var(--slate-500); margin-top:2px; line-height:1.35; }
.mv-control { flex:0 0 auto; }
.mv-badge { display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:600;
    padding:3px 8px; border-radius:20px; }
.mv-badge-core { background:var(--emerald-50); color:var(--emerald-700); }
.mv-badge-locked { background:var(--slate-200); color:var(--slate-600); }
.mv-ext { margin-top:10px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.mv-ext-label { font-size:11px; color:var(--slate-500); }
.mv-ext-chip { font-size:11px; background:var(--amber-50); color:var(--amber-700);
    padding:2px 7px; border-radius:4px; border:1px solid var(--amber-200); }
.mv-status { font-size:12px; margin-top:8px; min-height:0; }
.mv-status.ok { color:var(--emerald-600); }
.mv-status.err { color:var(--rose-600); }
/* Toggle-Switch */
.mv-switch { position:relative; display:inline-block; width:42px; height:24px; cursor:pointer; }
.mv-switch input { opacity:0; width:0; height:0; }
.mv-slider { position:absolute; inset:0; background:var(--slate-300); border-radius:24px; transition:.2s; }
.mv-slider:before { content:""; position:absolute; height:18px; width:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.2s; }
.mv-switch input:checked + .mv-slider { background:var(--thoxan-500); }
.mv-switch input:checked + .mv-slider:before { transform:translateX(18px); }
</style>

<script>
(function () {
    function post(key, enabled) {
        return fetch('/api/v1/admin/modules', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ module_key: key, enabled: enabled })
        }).then(r => r.json());
    }

    document.querySelectorAll('.mv-toggle').forEach(function (input) {
        input.addEventListener('change', function () {
            var card = input.closest('.mv-card');
            var key = card.getAttribute('data-key');
            var status = card.querySelector('[data-role="status"]');
            var wanted = input.checked;
            input.disabled = true;
            status.className = 'mv-status';
            status.textContent = 'Speichere…';
            post(key, wanted).then(function (res) {
                input.disabled = false;
                if (res && res.success) {
                    status.className = 'mv-status ok';
                    status.textContent = wanted ? 'Aktiviert. Menü wird beim nächsten Laden aktualisiert.'
                                                 : 'Deaktiviert. Menü wird beim nächsten Laden aktualisiert.';
                    card.classList.toggle('mv-off', !wanted);
                } else {
                    input.checked = !wanted; // zuruecksetzen
                    status.className = 'mv-status err';
                    status.textContent = (res && res.message) ? res.message : 'Fehler beim Speichern.';
                }
            }).catch(function () {
                input.disabled = false;
                input.checked = !wanted;
                status.className = 'mv-status err';
                status.textContent = 'Netzwerkfehler.';
            });
        });
    });
})();
</script>
