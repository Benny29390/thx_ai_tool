<?php
/**
 * Master-Detail-Layout fuer Kunden.
 * Sidebar = Kunden-Liste mit Suche / Filter / Status-Dots.
 * Default-Content = Kunden-Card-Grid mit Monitor-Mini-Anzeige + viel Info auf einen Blick.
 * Site-Monitor selbst bleibt eigenstaendig unter /admin/site-monitor.
 */

$activeCustomerId = null;
include __DIR__ . '/_customer_master_styles.php';

$_db = \Core\Database::getInstance();

// Monitor-Daten pro Customer
$_cuMon = [];
try {
    foreach ($_db->query(
        "SELECT customer_id,
                SUM(status='up') AS up_n,
                SUM(status='down') AS down_n,
                SUM(status='paused') AS paused_n,
                COUNT(*) AS total,
                AVG(NULLIF(last_response_time, 0)) AS avg_resp,
                MAX(last_check) AS last_check
         FROM pm_monitors GROUP BY customer_id"
    ) ?: [] as $_r) {
        $_cuMon[(int) $_r['customer_id']] = [
            'up' => (int) $_r['up_n'],
            'down' => (int) $_r['down_n'],
            'paused' => (int) $_r['paused_n'],
            'total' => (int) $_r['total'],
            'avg_resp' => $_r['avg_resp'] ? (int) round($_r['avg_resp']) : null,
            'last_check' => $_r['last_check'],
        ];
    }
} catch (\Throwable $e) { /* monitors optional */ }

// Knowledge-Count pro Customer
$_cuKb = [];
try {
    foreach ($_db->query("SELECT customer_id, COUNT(*) AS n FROM knowledge_documents WHERE is_active=1 AND customer_id IS NOT NULL GROUP BY customer_id") ?: [] as $_r) {
        $_cuKb[(int) $_r['customer_id']] = (int) $_r['n'];
    }
} catch (\Throwable $e) {}

$_initials = function ($name) {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 2));
};

$_relDate = function ($ts) {
    if (!$ts) return null;
    $diff = time() - $ts;
    if ($diff < 60) return 'gerade eben';
    if ($diff < 3600) return floor($diff / 60) . ' Min.';
    if ($diff < 86400) return floor($diff / 3600) . ' Std.';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' Tg.';
    if ($diff < 86400 * 30) return floor($diff / 86400 / 7) . ' Wo.';
    return date('d.m.', $ts);
};
?>

<div class="cm-page">
    <?php include __DIR__ . '/_customer_master_sidebar.php'; ?>

    <section class="cm-main">
        <div class="cm-main-inner">
            <div class="thx-page-header">
                <div>
                    <h1 class="thx-page-title">Alle Projekte</h1>
                    <div class="thx-page-subtitle"><span id="cm-grid-count"><?= count($customers) ?></span> von <?= count($customers) ?> Kunden</div>
                </div>
                <div class="thx-page-actions">
                    <button type="button" id="qs-mode-btn" class="thx-btn thx-btn-secondary thx-btn-small" onclick="qsToggleSelectMode()"
                            title="Kunden auswählen und eine Asana-Sammelaufgabe mit Unteraufgaben erzeugen">
                        <span class="material-symbols-rounded" style="font-size:16px;">checklist</span>
                        Querschnittsaufgabe
                    </button>
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="cuBulkFetchFavicons()" title="Favicons bei allen Kunden mit Website holen">
                        <span class="material-symbols-rounded" style="font-size:16px;">language</span>
                        Favicons holen
                    </button>
                    <button type="button" class="thx-btn thx-btn-primary thx-btn-small" onclick="cmOpenNewCustomerModal()">
                        <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                        Neuer Kunde
                    </button>
                </div>
            </div>

            <!-- Auswahl-Leiste (nur im Auswahl-Modus) -->
            <div class="qs-bar" id="qs-bar" style="display:none;">
                <span class="qs-bar-count"><strong id="qs-count">0</strong> ausgewählt</span>
                <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="qsSelectVisible(true)">Alle sichtbaren</button>
                <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="qsSelectVisible(false)">Keine</button>
                <span style="flex:1;"></span>
                <button type="button" class="thx-btn thx-btn-primary thx-btn-small" id="qs-create-btn" onclick="qsOpenModal()" disabled>
                    <span class="material-symbols-rounded" style="font-size:16px;">playlist_add</span>
                    Sammelaufgabe erstellen
                </button>
                <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="qsToggleSelectMode()">Abbrechen</button>
            </div>

            <div class="cm-grid" id="cm-grid">
                <?php foreach ($customers as $customer):
                    $cid = (int) $customer['id'];
                    $abbr = trim($customer['abbreviation'] ?? '');
                    if ($abbr === '') $abbr = $_initials($customer['name'] ?? '?');
                    $logo = trim($customer['logo_path'] ?? '');
                    $tags = $customer['tags'] ?? [];
                    $tagsHaystack = mb_strtolower(implode(' ', $tags));
                    $website = trim($customer['website'] ?? '');
                    $industry = trim($customer['industry'] ?? '');
                    $mon = $_cuMon[$cid] ?? null;
                    $kbCount = $_cuKb[$cid] ?? 0;
                    $chats = (int) ($customer['chat_count'] ?? 0);
                    $lastChat = !empty($customer['last_chat_at']) ? strtotime($customer['last_chat_at']) : null;
                    $asanaSync = !empty($customer['asana_last_sync']) ? strtotime($customer['asana_last_sync']) : null;
                    $websiteSync = !empty($customer['website_last_sync']) ? strtotime($customer['website_last_sync']) : null;
                    $statusVal = $customer['is_active'] ? 'active' : 'inactive';
                    // Monitor-Status fuer Filter-Daten
                    $monStatusStr = '';
                    if ($mon) {
                        $stati = [];
                        if ($mon['up'] > 0) $stati[] = 'up';
                        if ($mon['down'] > 0) $stati[] = 'down';
                        if ($mon['paused'] > 0) $stati[] = 'paused';
                        $monStatusStr = implode(' ', $stati);
                    }
                    // Monitor-Kategorie aus Customer-Tags — '|' weil Tags Leerzeichen haben koennen ("Pro Bono")
                    $monCatStr = mb_strtolower(implode('|', $tags));
                    $monState = 'none';
                    if ($mon) {
                        if ($mon['down'] > 0) $monState = 'down';
                        elseif ($mon['paused'] === $mon['total']) $monState = 'paused';
                        elseif ($mon['up'] > 0) $monState = 'up';
                    }
                    $haystack = mb_strtolower(($customer['name'] ?? '') . ' ' . $abbr . ' ' . ($customer['slug'] ?? '') . ' ' . $website . ' ' . $tagsHaystack . ' ' . $industry);
                ?>
                <div class="cm-card" role="link" tabindex="0"
                   data-href="/admin/customers/<?= $cid ?>/steckbrief"
                   data-cid="<?= $cid ?>"
                   data-cname="<?= htmlspecialchars($customer['name']) ?>"
                   data-cabbr="<?= htmlspecialchars($abbr) ?>"
                   onclick="if(window.qsSelectMode){qsToggleCard(this);return;} if(!event.target.closest('a,button'))window.location.href=this.dataset.href"
                   onkeydown="if(event.key==='Enter'||event.key===' '){ if(window.qsSelectMode){event.preventDefault();qsToggleCard(this);return;} window.location.href=this.dataset.href; }"
                   data-status="<?= $statusVal ?>"
                   data-tags="<?= htmlspecialchars($tagsHaystack) ?>"
                   data-mon-status="<?= htmlspecialchars($monStatusStr) ?>"
                   data-mon-cat="<?= htmlspecialchars($monCatStr) ?>"
                   data-search="<?= htmlspecialchars($haystack) ?>">

                    <span class="cm-card-check" aria-hidden="true">
                        <span class="material-symbols-rounded">check</span>
                    </span>

                    <div class="cm-card-head">
                        <?php if ($logo): ?>
                            <div class="cm-card-logo cm-card-logo-plain">
                                <img src="/uploads/customers/logos/<?= htmlspecialchars(basename($logo)) ?>" alt="" loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="cm-card-logo cm-card-logo-text"><?= htmlspecialchars(mb_substr($abbr, 0, 3)) ?></div>
                        <?php endif; ?>
                        <div class="cm-card-titles">
                            <div class="cm-card-name"><?= htmlspecialchars($customer['name']) ?></div>
                            <?php if ($industry !== ''): ?>
                                <div class="cm-card-industry"><?= htmlspecialchars($industry) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$customer['is_active']): ?>
                            <span class="cm-card-inactive">Inaktiv</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($tags)): ?>
                    <div class="cm-card-tags">
                        <?php foreach ($tags as $t): ?>
                            <span class="cm-card-tag"><?= htmlspecialchars($t) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($website || $mon):
                        $_displayUrl = $website !== '' ? preg_replace('#^https?://(www\.)?#', '', $website) : '';
                    ?>
                    <div class="cm-card-site">
                        <?php if ($website): ?>
                            <a class="cm-card-site-url" href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();"
                               title="<?= htmlspecialchars($website) ?>">
                                <?= htmlspecialchars($_displayUrl) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($mon && $mon['avg_resp']): ?>
                            <span class="cm-card-site-ms"><?= $mon['avg_resp'] ?> ms</span>
                        <?php endif; ?>
                        <?php if ($mon):
                            // Pro Monitor ein Icon: Klick = Stats-Modal fuer genau diesen Monitor.
                            $_mons = $_db->query(
                                "SELECT id, label, status FROM pm_monitors WHERE customer_id = ? ORDER BY id",
                                [$cid]
                            ) ?: [];
                            foreach ($_mons as $_m):
                                $_mState = $_m['status'] === 'down' ? 'down' : ($_m['status'] === 'paused' ? 'paused' : 'up');
                                $_mIcon = $_mState === 'down' ? 'cancel' : ($_mState === 'paused' ? 'pause_circle' : 'check_circle');
                                $_mLabel = $_m['label'] ?: $customer['name'];
                        ?>
                            <a class="cm-card-site-status cm-mon-<?= $_mState ?>"
                               href="javascript:void(0)"
                               onclick="event.stopPropagation();smOpenStats(<?= (int) $_m['id'] ?>, <?= htmlspecialchars(json_encode($_mLabel), ENT_QUOTES) ?>);"
                               title="<?= htmlspecialchars($_mLabel) ?> — Klick: Statistik-Modal (30 Tage)">
                                <span class="material-symbols-rounded"><?= $_mIcon ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="cm-card-foot">
                        <span class="cm-card-meta" title="Wissens-Einträge">
                            <span class="material-symbols-rounded">library_books</span><?= $kbCount ?>
                        </span>
                        <span class="cm-card-meta" title="Chats">
                            <span class="material-symbols-rounded">forum</span><?= $chats ?>
                        </span>
                        <?php if ($lastChat): ?>
                            <span class="cm-card-meta" title="Letzter Chat">
                                <span class="material-symbols-rounded">chat</span><?= $_relDate($lastChat) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($asanaSync): ?>
                            <span class="cm-card-meta" title="Asana-Sync">
                                <span class="material-symbols-rounded">task_alt</span><?= $_relDate($asanaSync) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($websiteSync): ?>
                            <span class="cm-card-meta" title="Website-Crawl">
                                <span class="material-symbols-rounded">language</span><?= $_relDate($websiteSync) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="cm-grid-empty" id="cm-grid-empty" style="display:none;">
                <span class="material-symbols-rounded">filter_alt_off</span>
                <p>Keine Kunden passen zu den Filtern.</p>
            </div>
        </div>
    </section>
</div>

<!-- Stats-Modal: wird vom Partial geliefert (gemeinsam mit /admin/site-monitor) -->
<?php include __DIR__ . '/_sm_stats_modal.php'; ?>

<style>
/* ===== Querschnittsaufgaben: Auswahl-Modus auf den Kunden-Kacheln ===== */
.qs-bar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    background: var(--thoxan-50); border: 1px solid var(--thoxan-200);
    border-radius: var(--d-card-radius); padding: 10px 14px; margin-bottom: 14px;
}
.qs-bar-count { font-size: var(--d-fs-sm); color: var(--thoxan-800); }

.cm-card { position: relative; }
.cm-card-check {
    position: absolute; top: 10px; right: 10px; z-index: 2;
    width: 22px; height: 22px; border-radius: 6px;
    border: 2px solid var(--slate-300); background: #fff;
    display: none; align-items: center; justify-content: center;
}
.cm-card-check .material-symbols-rounded { font-size: 16px; color: transparent; }
body.qs-select-mode .cm-card-check { display: flex; }
body.qs-select-mode .cm-card { cursor: pointer; }
body.qs-select-mode .cm-card:hover { border-color: var(--thoxan-400); }
body.qs-select-mode .cm-card.is-qs-selected { border-color: var(--thoxan-600); box-shadow: 0 0 0 2px var(--thoxan-100); }
body.qs-select-mode .cm-card.is-qs-selected .cm-card-check {
    background: var(--thoxan-600); border-color: var(--thoxan-600);
}
body.qs-select-mode .cm-card.is-qs-selected .cm-card-check .material-symbols-rounded { color: #fff; }

.qs-result-item { display: flex; gap: 8px; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--slate-100); font-size: var(--d-fs-sm); }
</style>

<!-- Dialog: Querschnittsaufgabe -->
<div class="thx-modal-backdrop" id="qs-modal" style="display:none;" onclick="if(event.target===this)qsCloseModal()">
    <div class="thx-modal" style="width:560px;max-width:94vw;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Querschnittsaufgabe erstellen</h3>
            <button class="thx-modal-close" onclick="qsCloseModal()">&times;</button>
        </div>
        <div class="thx-modal-body" id="qs-modal-body"></div>
    </div>
</div>

<script>
/* ===== Querschnittsaufgaben ===== */
window.qsSelectMode = false;
window.qsSelected = new Set();
let qsConfig = null;

/** Heutiges Datum als YYYY-MM-DD (lokal, nicht UTC — sonst kippt es abends auf morgen). */
function qsToday() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

function qsToggleSelectMode() {
    window.qsSelectMode = !window.qsSelectMode;
    document.body.classList.toggle('qs-select-mode', window.qsSelectMode);
    document.getElementById('qs-bar').style.display = window.qsSelectMode ? 'flex' : 'none';
    document.getElementById('qs-mode-btn').classList.toggle('thx-btn-primary', window.qsSelectMode);
    if (!window.qsSelectMode) {
        window.qsSelected.clear();
        document.querySelectorAll('#cm-grid .cm-card.is-qs-selected').forEach(c => c.classList.remove('is-qs-selected'));
        qsUpdateCount();
    }
}

function qsToggleCard(card) {
    const cid = parseInt(card.dataset.cid);
    if (!cid) return;
    if (window.qsSelected.has(cid)) { window.qsSelected.delete(cid); card.classList.remove('is-qs-selected'); }
    else { window.qsSelected.add(cid); card.classList.add('is-qs-selected'); }
    qsUpdateCount();
}

/** Alle aktuell SICHTBAREN (= nicht weggefilterten) Kacheln aus-/abwaehlen. */
function qsSelectVisible(on) {
    document.querySelectorAll('#cm-grid .cm-card').forEach(card => {
        if (card.style.display === 'none') return;
        const cid = parseInt(card.dataset.cid);
        if (!cid) return;
        if (on) { window.qsSelected.add(cid); card.classList.add('is-qs-selected'); }
        else { window.qsSelected.delete(cid); card.classList.remove('is-qs-selected'); }
    });
    qsUpdateCount();
}

function qsUpdateCount() {
    const n = window.qsSelected.size;
    document.getElementById('qs-count').textContent = n;
    document.getElementById('qs-create-btn').disabled = n === 0;
}

async function qsOpenModal() {
    if (window.qsSelected.size === 0) return;
    const modal = document.getElementById('qs-modal');
    const body = document.getElementById('qs-modal-body');
    modal.style.display = 'flex';
    body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--slate-400);">Lade Asana-Projekte…</div>';

    if (!qsConfig) {
        try {
            const r = await fetch('/api/v1/admin/querschnitt-task');
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            qsConfig = j.data;
        } catch (e) {
            body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + (e.message || 'Fehler') + '</div>';
            return;
        }
    }

    const names = [];
    document.querySelectorAll('#cm-grid .cm-card.is-qs-selected').forEach(c => names.push(c.dataset.cabbr || c.dataset.cname));
    const def = qsConfig.default_project || {};
    const projects = qsConfig.projects || [];
    const projOpts = projects.map(p =>
        `<option value="${p.gid}" ${p.gid === def.gid ? 'selected' : ''}>${(p.name || p.gid).replace(/</g, '&lt;')}</option>`
    ).join('');

    body.innerHTML = `
        <div style="background:var(--thoxan-50);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:var(--d-fs-sm);color:var(--slate-700);">
            <strong>${window.qsSelected.size} Kunden</strong> — je Kunde eine Unteraufgabe (alphabetisch A→Z), Titel „Kürzel — Website".<br>
            Die Sammelaufgabe wird <strong>Dir</strong> zugewiesen.<br>
            <span style="color:var(--slate-500);font-size:var(--d-fs-xs);">${names.join(' · ')}</span>
        </div>
        <div class="pp-field" style="margin-bottom:12px;">
            <label style="display:block;font-size:var(--d-fs-sm);font-weight:600;margin-bottom:4px;">Titel der Sammelaufgabe *</label>
            <input type="text" id="qs-title" style="width:100%;" placeholder="z. B. Alle Kunden über neue Funktion informieren" autofocus>
        </div>
        <div class="pp-field" style="margin-bottom:12px;">
            <label style="display:block;font-size:var(--d-fs-sm);font-weight:600;margin-bottom:4px;">Beschreibung</label>
            <textarea id="qs-notes" rows="3" style="width:100%;" placeholder="Worum geht es? (landet in der Asana-Beschreibung)"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
            <div class="pp-field">
                <label style="display:block;font-size:var(--d-fs-sm);font-weight:600;margin-bottom:4px;">Fälligkeit</label>
                <input type="date" id="qs-due" style="width:100%;" value="${qsToday()}">
            </div>
            <div class="pp-field">
                <label style="display:block;font-size:var(--d-fs-sm);font-weight:600;margin-bottom:4px;">Asana-Projekt</label>
                ${projects.length
                    ? `<select id="qs-project" style="width:100%;">${projOpts}</select>`
                    : `<input type="text" id="qs-project" style="width:100%;" value="${def.gid || ''}" placeholder="Projekt-GID">`}
            </div>
        </div>
        ${!def.gid && !projects.length ? `<div style="color:var(--rose-600);font-size:var(--d-fs-sm);margin-bottom:10px;">
            Kein Ziel-Projekt hinterlegt — bitte unter <a href="/admin/settings?tab=asana">Einstellungen → Asana</a> setzen.</div>` : ''}
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
            <button class="thx-btn thx-btn-secondary" onclick="qsCloseModal()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="qs-submit" onclick="qsCreate()">In Asana anlegen</button>
        </div>`;
    setTimeout(() => document.getElementById('qs-title')?.focus(), 50);
}

function qsCloseModal() { document.getElementById('qs-modal').style.display = 'none'; }

async function qsCreate() {
    const title = (document.getElementById('qs-title').value || '').trim();
    if (!title) { App.showNotification('Bitte einen Titel angeben', 'error'); return; }
    const btn = document.getElementById('qs-submit');
    btn.disabled = true; btn.textContent = 'Lege an…';
    try {
        const r = await fetch('/api/v1/admin/querschnitt-task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({
                customer_ids: Array.from(window.qsSelected),
                title,
                notes: (document.getElementById('qs-notes').value || '').trim(),
                due_on: document.getElementById('qs-due').value || '',
                project_gid: (document.getElementById('qs-project')?.value || '').trim(),
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        qsShowResult(j.data);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false; btn.textContent = 'In Asana anlegen';
    }
}

function qsShowResult(data) {
    const body = document.getElementById('qs-modal-body');
    const subs = data.subtasks || [];
    const failed = data.failed || [];
    body.innerHTML = `
        <div style="text-align:center;padding:6px 0 14px;">
            <span class="material-symbols-rounded" style="font-size:38px;color:var(--emerald-600);">task_alt</span>
            <div style="font-weight:600;margin-top:4px;">${subs.length} Unteraufgaben angelegt</div>
        </div>
        <div style="background:var(--slate-50);border-radius:8px;padding:10px 12px;margin-bottom:12px;">
            <a href="${data.parent.url}" target="_blank" rel="noopener" style="font-weight:600;">
                ${(data.parent.name || '').replace(/</g, '&lt;')} ↗
            </a>
            <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">Sammelaufgabe in Asana öffnen</div>
        </div>
        <div style="max-height:34vh;overflow-y:auto;">
            ${subs.map(s => `<div class="qs-result-item">
                <span class="material-symbols-rounded" style="font-size:15px;color:var(--emerald-600);">check</span>
                <span style="flex:1;">${(s.abbreviation ? s.abbreviation + ' — ' : '') + (s.customer || '').replace(/</g, '&lt;')}</span>
                <a href="${s.url}" target="_blank" rel="noopener" style="font-size:var(--d-fs-xs);">öffnen ↗</a>
            </div>`).join('')}
            ${failed.map(f => `<div class="qs-result-item" style="color:var(--rose-600);">
                <span class="material-symbols-rounded" style="font-size:15px;">error</span>
                <span style="flex:1;">${(f.customer || '').replace(/</g, '&lt;')}: ${(f.error || '').replace(/</g, '&lt;')}</span>
            </div>`).join('')}
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button class="thx-btn thx-btn-primary" onclick="qsCloseModal();qsToggleSelectMode();">Fertig</button>
        </div>`;
}

window.cuBulkFetchFavicons = async function() {
    if (!confirm('Bei allen Kunden mit Website das Favicon holen?')) return;
    const r = await fetch('/api/v1/admin/customers/bulk-favicons', {
        method: 'POST', headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content }
    });
    const j = await r.json();
    if (j.success) { App.showNotification('Favicons aktualisiert', 'success'); location.reload(); }
    else App.showNotification(j.message || 'Fehler', 'error');
};

// Filter-Pillen liegen NUR in der Sidebar (kein Duplikat im Grid-Bereich),
// die Filterung wirkt aber auf BEIDE — Sidebar-Liste UND die Karten-Grid rechts.
document.addEventListener('cm-filter-changed', (e) => {
    const f = e.detail || {};
    let shown = 0;
    document.querySelectorAll('#cm-grid .cm-card').forEach(card => {
        let ok = true;
        if (f.status && card.dataset.status !== f.status) ok = false;
        if (ok && f.tag) {
            const t = (card.dataset.tags || '').toLowerCase();
            if (!t.includes(f.tag.toLowerCase())) ok = false;
        }
        if (ok && f.monStatus) {
            const s = (card.dataset.monStatus || '').split(/\s+/);
            if (!s.includes(f.monStatus)) ok = false;
        }
        card.style.display = ok ? '' : 'none';
        if (ok) shown++;
    });
    const q = (document.getElementById('cm-search')?.value || '').toLowerCase().trim();
    if (q) {
        shown = 0;
        document.querySelectorAll('#cm-grid .cm-card').forEach(card => {
            if (card.style.display === 'none') return;
            const ok = (card.dataset.search || '').includes(q);
            card.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
    }
    const countEl = document.getElementById('cm-grid-count');
    if (countEl) countEl.textContent = shown;
    const emptyEl = document.getElementById('cm-grid-empty');
    if (emptyEl) emptyEl.style.display = shown === 0 ? 'block' : 'none';
});
// Such-Input dispatcht das cm-filter-changed Event auch (sonst greift Suche nur in Sidebar)
document.getElementById('cm-search')?.addEventListener('input', function() {
    document.dispatchEvent(new CustomEvent('cm-filter-changed', { detail: {
        status: window.cmFilters?.status || '',
        tag: window.cmFilters?.tag || '',
        monStatus: window.cmFilters?.monStatus || '',
        monCat: window.cmFilters?.monCat || '',
    }}));
});
</script>
