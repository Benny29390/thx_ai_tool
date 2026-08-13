<?php
/**
 * System-Update — aktuellen Stand anzeigen und Updates einspielen.
 * $version = Core\Version::status(), $license = Core\License::info()
 */
$statusLabels = [
    'none'    => ['Unbegrenzt (keine Lizenzdatei)', 'var(--emerald-600)'],
    'valid'   => ['Gültig', 'var(--emerald-600)'],
    'grace'   => ['Abgelaufen (Kulanzzeit läuft)', 'var(--amber-600)'],
    'expired' => ['Abgelaufen', 'var(--rose-600)'],
    'invalid' => ['Ungültig', 'var(--rose-600)'],
];
$ls = $statusLabels[$license['status']] ?? ['Unbekannt', 'var(--slate-500)'];
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">system_update_alt</span>
            System-Update
        </h1>
        <p class="thx-page-subtitle">
            Zentrale Weiterentwicklungen und Fehlerbehebungen einspielen. Vor jedem Update wird
            automatisch eine Sicherung von Datenbank und Konfiguration angelegt.
        </p>
    </div>
</div>

<div class="su-page">
    <!-- Version -->
    <div class="thx-card">
        <div class="su-row">
            <div>
                <div class="su-label">Installierte Version</div>
                <div class="su-version" id="su-current"><?= htmlspecialchars($version['current']) ?></div>
                <div class="su-sub">Kanal: <?= htmlspecialchars($version['branch'] ?: '—') ?><?php if (!empty($version['commit'])): ?> · Stand <?= htmlspecialchars($version['commit']) ?><?php endif; ?></div>
            </div>
            <div id="su-badge" class="su-badge su-badge-neutral">Prüfe…</div>
        </div>

        <div id="su-update-box" class="su-update-box" style="display:none;">
            <div class="su-update-head">
                <span class="material-symbols-rounded" style="color:var(--thoxan-600);">new_releases</span>
                <span id="su-avail-text">Update verfügbar</span>
            </div>
            <ul id="su-changelog" class="su-changelog"></ul>
            <button id="su-install" class="thx-btn thx-btn-primary" onclick="suInstall()">
                <span class="material-symbols-rounded" style="font-size:18px;">download</span>
                Update installieren
            </button>
            <div id="su-install-msg" class="su-msg"></div>
        </div>

        <div id="su-uptodate" class="su-uptodate" style="display:none;">
            <span class="material-symbols-rounded" style="color:var(--emerald-600);">check_circle</span>
            Diese Installation ist auf dem neuesten Stand.
        </div>

        <div id="su-noremote" class="su-msg" style="display:none;color:var(--slate-500);">
            Es ist noch kein zentraler Update-Server hinterlegt. Sobald das Repository verbunden ist,
            erscheinen hier verfügbare Aktualisierungen.
        </div>
    </div>

    <!-- Lizenz -->
    <div class="thx-card">
        <div class="su-label">Lizenz</div>
        <div class="su-lic">
            <div><span class="su-lic-k">Status</span> <span style="color:<?= $ls[1] ?>;font-weight:600;"><?= htmlspecialchars($ls[0]) ?></span></div>
            <?php if (!empty($license['customer'])): ?>
                <div><span class="su-lic-k">Kunde</span> <?= htmlspecialchars($license['customer']) ?></div>
            <?php endif; ?>
            <?php if (!empty($license['expires_at'])): ?>
                <div><span class="su-lic-k">Gültig bis</span> <?= htmlspecialchars($license['expires_at']) ?></div>
            <?php endif; ?>
            <?php if (!empty($license['modules']) && is_array($license['modules'])): ?>
                <div><span class="su-lic-k">Module</span> <?= htmlspecialchars(in_array('*', $license['modules']) ? 'Alle' : implode(', ', $license['modules'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.su-page { max-width: 820px; display:flex; flex-direction:column; gap:16px; }
.su-row { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
.su-label { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--slate-500); font-weight:700; }
.su-version { font-size:24px; font-weight:700; color:var(--slate-800); margin-top:2px; }
.su-sub { font-size:var(--d-fs-sm); color:var(--slate-500); margin-top:2px; }
.su-badge { font-size:12px; font-weight:600; padding:5px 12px; border-radius:20px; white-space:nowrap; }
.su-badge-neutral { background:var(--slate-100); color:var(--slate-500); }
.su-badge-ok { background:var(--emerald-50); color:var(--emerald-700); }
.su-badge-update { background:var(--thoxan-50); color:var(--thoxan-700); }
.su-update-box { margin-top:16px; padding-top:16px; border-top:1px solid var(--slate-200); }
.su-update-head { display:flex; align-items:center; gap:8px; font-weight:700; color:var(--slate-800); margin-bottom:8px; }
.su-changelog { margin:0 0 14px; padding-left:22px; color:var(--slate-600); font-size:var(--d-fs-sm); max-height:220px; overflow:auto; }
.su-changelog li { margin:3px 0; }
.su-uptodate { display:flex; align-items:center; gap:8px; margin-top:14px; color:var(--slate-600); font-size:var(--d-fs-sm); }
.su-msg { margin-top:10px; font-size:var(--d-fs-sm); }
.su-lic { display:flex; flex-direction:column; gap:6px; margin-top:8px; font-size:var(--d-fs-sm); color:var(--slate-700); }
.su-lic-k { display:inline-block; min-width:90px; color:var(--slate-500); }
</style>

<script>
(function () {
    function el(id) { return document.getElementById(id); }

    function render(d) {
        var live = d.live || {};
        var cached = d.cached;
        el('su-current').textContent = live.current || '—';

        if (!live.has_remote) {
            el('su-badge').textContent = 'Kein Update-Server';
            el('su-badge').className = 'su-badge su-badge-neutral';
            el('su-noremote').style.display = '';
            return;
        }

        if (d.maintenance || d.running) {
            el('su-badge').textContent = 'Update läuft…';
            el('su-badge').className = 'su-badge su-badge-update';
            el('su-install-msg').textContent = 'Das Update wird gerade eingespielt. Bitte die Seite in einigen Minuten neu laden.';
            return;
        }

        var behind = cached ? cached.behind : null;
        if (behind === null || behind === undefined) {
            el('su-badge').textContent = 'Status wird ermittelt…';
            el('su-badge').className = 'su-badge su-badge-neutral';
            return;
        }
        if (behind === 0) {
            el('su-badge').textContent = 'Aktuell';
            el('su-badge').className = 'su-badge su-badge-ok';
            el('su-uptodate').style.display = '';
            return;
        }
        // Update verfuegbar
        el('su-badge').textContent = behind + ' Update' + (behind > 1 ? 's' : '') + ' verfügbar';
        el('su-badge').className = 'su-badge su-badge-update';
        el('su-avail-text').textContent = 'Neue Version verfügbar: ' + (cached.available || '') + ' (' + behind + ' Änderung' + (behind > 1 ? 'en' : '') + ')';
        var ul = el('su-changelog'); ul.innerHTML = '';
        (cached.changes || []).forEach(function (c) {
            var li = document.createElement('li'); li.textContent = c; ul.appendChild(li);
        });
        el('su-update-box').style.display = '';
        if (d.update_requested) {
            el('su-install').disabled = true;
            el('su-install-msg').textContent = 'Update ist angefordert und wird in Kürze installiert.';
        }
    }

    window.suLoad = function () {
        fetch('/api/v1/admin/update').then(r => r.json()).then(function (res) {
            if (res && res.success) render(res.data);
        });
    };

    window.suInstall = function () {
        el('su-install').disabled = true;
        el('su-install-msg').textContent = 'Fordere Update an…';
        fetch('/api/v1/admin/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ action: 'install' })
        }).then(r => r.json()).then(function (res) {
            el('su-install-msg').textContent = (res && res.message) ? res.message : (res.success ? 'Angefordert.' : 'Fehler.');
            if (!res.success) el('su-install').disabled = false;
            setTimeout(suLoad, 3000);
        }).catch(function () {
            el('su-install').disabled = false;
            el('su-install-msg').textContent = 'Netzwerkfehler.';
        });
    };

    suLoad();
})();
</script>
