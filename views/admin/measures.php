<?php
/**
 * Maßnahmen — To-dos aus internem Feedback.
 * Daten kommen aus der Route /admin/measures (core/App.php):
 *   $measures, $stats, $currentStatus, $unprocessedCount
 */
function mStatusLabel($s) {
    return ['offen'=>'Offen','in_arbeit'=>'In Arbeit','erledigt'=>'Erledigt','verworfen'=>'Verworfen'][$s] ?? $s;
}
function mPrioLabel($p) {
    return ['hoch'=>'Hoch','mittel'=>'Mittel','niedrig'=>'Niedrig'][$p] ?? $p;
}
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:22px;">checklist</span>
            Maßnahmen
        </h1>
        <p class="thx-page-subtitle">To-dos für die nächsten Schritte, gespeist aus dem internen Feedback.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <button class="thx-btn thx-btn-secondary" onclick="openNewMeasure()">
            <span class="material-symbols-rounded">add</span> Neue Maßnahme
        </button>
        <button class="thx-btn thx-btn-primary" id="btn-analyze" onclick="runAnalyze()">
            <span class="material-symbols-rounded">auto_awesome</span>
            KI-Analyse<?php if ($unprocessedCount > 0): ?> (<?= $unprocessedCount ?> offen)<?php endif; ?>
        </button>
    </div>
</div>

<div class="ms-page">

    <!-- Filter -->
    <div class="ms-stats">
        <?php
        $filters = [
            'offen'     => ['Offen', 'is-red'],
            'in_arbeit' => ['In Arbeit', 'is-orange'],
            'erledigt'  => ['Erledigt', 'is-green'],
            'verworfen' => ['Verworfen', 'is-grey'],
            'all'       => ['Gesamt', ''],
        ];
        foreach ($filters as $key => [$label, $cls]): ?>
            <button type="button" class="ms-stat <?= $currentStatus === $key ? 'is-active' : '' ?>"
                    onclick="window.location.href='/admin/measures?status=<?= $key ?>'">
                <div class="ms-stat-num <?= $cls ?>"><?= (int)($stats[$key] ?? 0) ?></div>
                <div class="ms-stat-label"><?= $label ?></div>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if (empty($measures)): ?>
        <div class="thx-card ms-empty">
            <span class="material-symbols-rounded">task_alt</span>
            <p>Keine Maßnahmen in dieser Ansicht.</p>
            <p style="font-size:var(--d-fs-sm);color:var(--color-gray-500);">
                Tipp: „KI-Analyse" bündelt offene Feedbacks automatisch zu Maßnahmen.
            </p>
        </div>
    <?php else: ?>
        <div class="ms-grid">
            <?php foreach ($measures as $m): ?>
                <div class="thx-card ms-card ms-prio-<?= htmlspecialchars($m['priority']) ?>" data-id="<?= $m['id'] ?>">
                    <div class="ms-card-main">
                        <div class="ms-card-head">
                            <?php if ($m['source'] === 'ki'): ?>
                                <span class="ms-source" title="Von der KI vorgeschlagen">
                                    <span class="material-symbols-rounded">auto_awesome</span>
                                </span>
                            <?php endif; ?>
                            <h3 class="ms-title" contenteditable="false"><?= htmlspecialchars($m['title']) ?></h3>
                        </div>
                        <?php if (!empty($m['description'])): ?>
                            <p class="ms-desc"><?= nl2br(htmlspecialchars($m['description'])) ?></p>
                        <?php endif; ?>
                        <div class="ms-meta">
                            <?php if (!empty($m['area'])): ?>
                                <span class="ms-chip"><?= htmlspecialchars($m['area']) ?></span>
                            <?php endif; ?>
                            <?php if ((int)$m['feedback_count'] > 0): ?>
                                <button class="ms-chip ms-chip-link" onclick="toggleFeedbacks(<?= $m['id'] ?>)">
                                    <span class="material-symbols-rounded" style="font-size:14px;">forum</span>
                                    <?= (int)$m['feedback_count'] ?> Feedback<?= (int)$m['feedback_count'] > 1 ? 's' : '' ?>
                                </button>
                            <?php endif; ?>
                            <span class="ms-date"><?= date('d.m.Y', strtotime($m['created_at'])) ?></span>
                        </div>
                        <div class="ms-feedbacks" id="ms-fb-<?= $m['id'] ?>" style="display:none;"></div>
                    </div>
                    <div class="ms-card-side">
                        <select class="ms-select ms-status is-<?= htmlspecialchars($m['status']) ?>"
                                onchange="updateMeasure(<?= $m['id'] ?>, 'status', this.value, this)">
                            <?php foreach (['offen','in_arbeit','erledigt','verworfen'] as $s): ?>
                                <option value="<?= $s ?>" <?= $m['status'] === $s ? 'selected' : '' ?>><?= mStatusLabel($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="ms-select ms-prio is-<?= htmlspecialchars($m['priority']) ?>"
                                onchange="updateMeasure(<?= $m['id'] ?>, 'priority', this.value, this)">
                            <?php foreach (['hoch','mittel','niedrig'] as $p): ?>
                                <option value="<?= $p ?>" <?= $m['priority'] === $p ? 'selected' : '' ?>><?= mPrioLabel($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="ms-icon-btn is-danger" onclick="deleteMeasure(<?= $m['id'] ?>)" title="Löschen">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Neue-Maßnahme-Modal -->
<div class="thx-modal-backdrop" id="new-measure-modal" style="display:none;" onclick="if(event.target===this)closeNewMeasure()">
    <div class="thx-modal" style="max-width:560px;width:100%;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Neue Maßnahme</h3>
            <button class="thx-modal-close" onclick="closeNewMeasure()">&times;</button>
        </div>
        <div class="thx-modal-body" style="padding:18px var(--d-gutter);">
            <div class="thx-form-field">
                <label>Titel *</label>
                <input type="text" id="nm-title" class="thx-input" placeholder="Was ist zu tun?">
            </div>
            <div style="display:flex;gap:12px;">
                <div class="thx-form-field" style="flex:1;">
                    <label>Bereich</label>
                    <input type="text" id="nm-area" class="thx-input" placeholder="z.B. Chat, Wissen">
                </div>
                <div class="thx-form-field" style="width:140px;">
                    <label>Priorität</label>
                    <select id="nm-priority" class="thx-select">
                        <option value="hoch">Hoch</option>
                        <option value="mittel" selected>Mittel</option>
                        <option value="niedrig">Niedrig</option>
                    </select>
                </div>
            </div>
            <div class="thx-form-field">
                <label>Beschreibung</label>
                <textarea id="nm-description" class="thx-input" rows="3" placeholder="Details, Umsetzungs-Idee …"></textarea>
            </div>
        </div>
        <div class="thx-modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:12px var(--d-gutter);">
            <button class="thx-btn thx-btn-secondary" onclick="closeNewMeasure()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="saveNewMeasure()">Anlegen</button>
        </div>
    </div>
</div>

<style>
.ms-page { padding: 0 var(--d-gutter) 40px; }
.ms-stats { display:flex; gap:12px; margin:18px 0 22px; flex-wrap:wrap; }
.ms-stat { flex:1; min-width:120px; background:var(--color-white); border:1px solid var(--color-gray-200);
    border-radius:12px; padding:14px; cursor:pointer; text-align:center; transition:all .15s; }
.ms-stat:hover { border-color:var(--thoxan-400); }
.ms-stat.is-active { border-color:var(--thoxan-600); box-shadow:0 0 0 1px var(--thoxan-600) inset; }
.ms-stat-num { font-size:1.5rem; font-weight:700; color:var(--color-gray-800); }
.ms-stat-num.is-red { color:#dc2626; } .ms-stat-num.is-orange { color:#ea580c; }
.ms-stat-num.is-green { color:#16a34a; } .ms-stat-num.is-grey { color:#64748b; }
.ms-stat-label { font-size:var(--d-fs-sm); color:var(--color-gray-500); margin-top:2px; }

.ms-grid { display:flex; flex-direction:column; gap:12px; }
.ms-card { display:flex; gap:16px; align-items:flex-start; padding:16px 18px; border-left:4px solid var(--color-gray-300); }
.ms-card.ms-prio-hoch { border-left-color:#dc2626; }
.ms-card.ms-prio-mittel { border-left-color:#f59e0b; }
.ms-card.ms-prio-niedrig { border-left-color:#94a3b8; }
.ms-card-main { flex:1; min-width:0; }
.ms-card-head { display:flex; align-items:center; gap:8px; }
.ms-source { color:var(--thoxan-600); display:inline-flex; }
.ms-source .material-symbols-rounded { font-size:18px; }
.ms-title { margin:0; font-size:var(--d-fs-base); font-weight:600; color:#0f172a; }
.ms-desc { margin:6px 0 0; color:#475569; font-size:var(--d-fs-sm); line-height:1.5; }
.ms-meta { display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
.ms-chip { display:inline-flex; align-items:center; gap:4px; background:var(--color-gray-100); color:var(--color-gray-700);
    font-size:var(--d-fs-xs); padding:3px 9px; border-radius:20px; border:none; }
.ms-chip-link { cursor:pointer; } .ms-chip-link:hover { background:var(--thoxan-100); color:var(--thoxan-800); }
.ms-date { font-size:var(--d-fs-xs); color:var(--color-gray-400); margin-left:auto; }
.ms-feedbacks { margin-top:10px; border-top:1px dashed var(--color-gray-200); padding-top:10px; }
.ms-fb-item { font-size:var(--d-fs-sm); color:#475569; padding:6px 0; border-bottom:1px solid var(--color-gray-100); }
.ms-fb-item:last-child { border-bottom:none; }
.ms-fb-meta { font-size:var(--d-fs-xs); color:var(--color-gray-400); }

.ms-card-side { display:flex; flex-direction:column; gap:8px; width:150px; flex-shrink:0; }
.ms-select { width:100%; padding:6px 8px; border-radius:8px; border:1px solid var(--color-gray-300);
    font-size:var(--d-fs-sm); font-family:var(--font-family); cursor:pointer; }
.ms-status.is-offen { background:var(--rose-50); border-color:var(--rose-200); color:var(--rose-700); }
.ms-status.is-in_arbeit { background:var(--amber-50); border-color:var(--amber-200); color:var(--amber-700); }
.ms-status.is-erledigt { background:var(--emerald-50); border-color:var(--emerald-200); color:var(--emerald-700); }
.ms-status.is-verworfen { background:var(--slate-100); border-color:var(--slate-200); color:var(--slate-600); }
.ms-icon-btn { border:1px solid var(--color-gray-200); background:var(--color-white); border-radius:8px;
    padding:6px; cursor:pointer; color:var(--color-gray-500); display:inline-flex; justify-content:center; }
.ms-icon-btn.is-danger:hover { border-color:#fecaca; background:#fef2f2; color:#dc2626; }
.ms-empty { text-align:center; padding:40px; color:var(--color-gray-500); }
.ms-empty .material-symbols-rounded { font-size:40px; color:var(--color-gray-300); }
</style>

<script>
async function runAnalyze() {
    const btn = document.getElementById('btn-analyze');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-rounded spin">progress_activity</span> Analysiere …';
    try {
        const res = await App.post('/admin/measures/analyze', {});
        App.showNotification(res.message || 'Analyse fertig', 'success');
        setTimeout(() => window.location.href = '/admin/measures?status=offen', 700);
    } catch (e) {
        App.showNotification(e.message || 'Analyse fehlgeschlagen', 'error');
        btn.disabled = false; btn.innerHTML = orig;
    }
}

async function updateMeasure(id, field, value, el) {
    if (field === 'status') el.className = 'ms-select ms-status is-' + value;
    if (field === 'priority') {
        el.className = 'ms-select ms-prio is-' + value;
        const card = document.querySelector(`.ms-card[data-id="${id}"]`);
        if (card) card.className = card.className.replace(/ms-prio-\w+/, 'ms-prio-' + value);
    }
    try {
        const body = {}; body[field] = value;
        await App.put('/admin/measures/' + id, body);
        App.showNotification('Gespeichert', 'success');
    } catch (e) {
        App.showNotification(e.message, 'error');
    }
}

async function deleteMeasure(id) {
    if (!confirm('Maßnahme wirklich löschen?')) return;
    try {
        await App.delete('/admin/measures/' + id);
        const card = document.querySelector(`.ms-card[data-id="${id}"]`);
        if (card) card.remove();
        App.showNotification('Gelöscht', 'success');
    } catch (e) {
        App.showNotification(e.message, 'error');
    }
}

const fbCache = {};
async function toggleFeedbacks(id) {
    const box = document.getElementById('ms-fb-' + id);
    if (box.style.display === 'block') { box.style.display = 'none'; return; }
    box.style.display = 'block';
    if (fbCache[id]) return;
    box.innerHTML = '<div class="ms-fb-meta">lädt …</div>';
    try {
        const res = await App.get('/admin/measures/' + id);
        const fbs = (res.data && res.data.feedbacks) || res.feedbacks || [];
        fbCache[id] = true;
        box.innerHTML = fbs.length ? fbs.map(f => `
            <div class="ms-fb-item">
                ${escapeHtml(f.description || '')}
                <div class="ms-fb-meta">${escapeHtml(f.user_name || '')} · ${escapeHtml(f.feedback_type || '')} · ${escapeHtml((f.page_url||'').replace('https://',''))}</div>
            </div>`).join('') : '<div class="ms-fb-meta">Keine verknüpften Feedbacks.</div>';
    } catch (e) {
        box.innerHTML = '<div class="ms-fb-meta">Fehler beim Laden.</div>';
    }
}

function openNewMeasure() { document.getElementById('new-measure-modal').style.display = 'flex'; }
function closeNewMeasure() { document.getElementById('new-measure-modal').style.display = 'none'; }
async function saveNewMeasure() {
    const title = document.getElementById('nm-title').value.trim();
    if (!title) { App.showNotification('Titel erforderlich', 'error'); return; }
    try {
        await App.post('/admin/measures', {
            title,
            area: document.getElementById('nm-area').value.trim(),
            priority: document.getElementById('nm-priority').value,
            description: document.getElementById('nm-description').value.trim(),
        });
        App.showNotification('Maßnahme angelegt', 'success');
        setTimeout(() => window.location.reload(), 500);
    } catch (e) {
        App.showNotification(e.message, 'error');
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>
<style>.spin{animation:msspin 1s linear infinite;}@keyframes msspin{to{transform:rotate(360deg);}}</style>
