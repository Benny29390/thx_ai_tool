<?php
/** Kundenportal-Verwaltung (Team). Rendert im main-Layout. */
$h = fn($s) => htmlspecialchars((string)$s);
$cid = (int) $customer['id'];
// Allgemeine, NICHT-Kachel-basierte Module (Live-Daten aus dem Projektplaner)
$generalModules = [
    'projektstatus' => ['Projektstatus', 'Live-Status des laufenden Projekts (auf „Übersicht")'],
    'meilensteine'  => ['Meilensteine / Timeline', 'Termine aus dem Projektplaner (auf „Übersicht")'],
];
?>
<style>
    .cps-grid { display:grid; grid-template-columns:1.5fr 1fr; gap:var(--space-4); align-items:start; }
    @media (max-width:1100px){ .cps-grid { grid-template-columns:1fr; } }
    .cps-config { display:flex; flex-direction:column; gap:var(--space-4); }
    .cps-chat { position:sticky; top:16px; display:flex; flex-direction:column; min-height:480px; max-height:calc(100vh - 120px); }
    .cps-row { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid var(--slate-100); }
    .cps-row:last-child { border-bottom:none; }
    .cps-row .lbl { flex:1; min-width:0; }
    .cps-row .lbl b { font-weight:600; color:var(--slate-800); }
    .cps-row .lbl small { display:block; color:var(--slate-400); font-size:var(--d-fs-xs); margin-top:1px; }
    /* iOS-artiger Switch */
    .cps-sw { position:relative; display:inline-block; width:40px; height:22px; flex-shrink:0; }
    .cps-sw input { opacity:0; width:0; height:0; }
    .cps-sw .sl { position:absolute; inset:0; background:var(--slate-300); border-radius:999px; transition:.18s; cursor:pointer; }
    .cps-sw .sl:before { content:''; position:absolute; height:16px; width:16px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.18s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .cps-sw input:checked + .sl { background:var(--emerald-500); }
    .cps-sw input:checked + .sl:before { transform:translateX(18px); }
    /* Chat */
    .cps-stream { flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:10px; padding:12px; background:var(--slate-50); border:1px solid var(--slate-200); border-radius:10px; margin:12px 0; min-height:200px; }
    .pc-msg { max-width:88%; font-size:var(--d-fs-sm); line-height:1.5; }
    .pc-msg .pc-meta { font-size:var(--d-fs-xs); color:var(--slate-400); margin-bottom:3px; display:flex; align-items:center; gap:5px; }
    .pc-msg .pc-meta .material-symbols-rounded { font-size:14px; }
    .pc-msg .pc-body { padding:9px 12px; border-radius:12px; }
    .pc-msg .pc-body p { margin:0 0 6px; } .pc-msg .pc-body p:last-child { margin:0; }
    .pc-msg .pc-body ul { margin:4px 0; padding-left:18px; } .pc-msg .pc-body strong { font-weight:700; }
    .pc-customer { align-self:flex-start; } .pc-customer .pc-body { background:var(--slate-100); }
    .pc-ki { align-self:flex-end; } .pc-ki .pc-body { background:#fff; border:1px solid var(--indigo-200,#c7d2fe); }
    .pc-ki .pc-meta { color:#6366f1; justify-content:flex-end; }
    .pc-team { align-self:flex-end; } .pc-team .pc-body { background:#eef4fb; border:1px solid var(--thoxan-100,#cfe0f3); }
    .pc-team .pc-meta { color:var(--thoxan-700); justify-content:flex-end; }
    .pc-empty { color:var(--slate-400); font-size:var(--d-fs-sm); margin:auto; }
    .cps-ki-state { display:inline-flex; align-items:center; gap:8px; padding:5px 10px; border-radius:999px; font-size:var(--d-fs-xs); font-weight:600; }
    .cps-ki-on { background:var(--emerald-50); color:var(--emerald-700); border:1px solid var(--emerald-200); }
    .cps-ki-off { background:var(--amber-50); color:var(--amber-800); border:1px solid var(--amber-200); }
</style>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Kundenportal — <?= $h($customer['name']) ?></h1>
        <p class="thx-page-subtitle">Steuern Sie, was dieser Kunde sieht. Alles ist standardmäßig „aus".</p>
    </div>
    <div class="thx-page-actions">
        <a href="/admin/customers/<?= $cid ?>/steckbrief" class="thx-btn thx-btn-secondary thx-btn-small"><span class="material-symbols-rounded">arrow_back</span> Steckbrief</a>
        <a href="/portal/dashboard?customer=<?= $cid ?>" target="_blank" rel="noopener" class="thx-btn thx-btn-secondary thx-btn-small"><span class="material-symbols-rounded">visibility</span> Übersicht ansehen</a>
        <a href="/portal/chat?customer=<?= $cid ?>" target="_blank" rel="noopener" class="thx-btn thx-btn-secondary thx-btn-small"><span class="material-symbols-rounded">forum</span> Chat ansehen</a>
    </div>
</div>

<div class="cps-grid">
    <!-- LINKS: Konfiguration -->
    <div class="cps-config">
        <section class="thx-card">
            <h2 class="thx-card-title">Kacheln &amp; Inhalte</h2>
            <p class="thx-card-sub">Welche Kacheln der Kunde sieht, schaltest Du direkt im Steckbrief — pro Kachel über das Augen-Symbol. Ein freigegebener Bereich erscheint automatisch als Tab im Kundenportal.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                <a href="/admin/customers/<?= $cid ?>/steckbrief" class="thx-btn thx-btn-primary thx-btn-small"><span class="material-symbols-rounded">grid_view</span> Kacheln im Steckbrief schalten</a>
                <a href="/portal/dashboard?customer=<?= $cid ?>" target="_blank" rel="noopener" class="thx-btn thx-btn-secondary thx-btn-small"><span class="material-symbols-rounded">visibility</span> Portal-Vorschau</a>
            </div>
        </section>

        <section class="thx-card">
            <h2 class="thx-card-title">Allgemeine Einstellungen</h2>
            <p class="thx-card-sub">Live-Bereiche aus dem Projektplaner (keine Kacheln) — erscheinen auf der „Übersicht".</p>
            <?php foreach ($generalModules as $mk => $meta): [$lbl, $desc] = $meta; $on = in_array($mk, $enabled, true); ?>
                <div class="cps-row">
                    <span class="lbl"><b><?= $h($lbl) ?></b><small><?= $h($desc) ?></small></span>
                    <label class="cps-sw"><input type="checkbox" <?= $on ? 'checked' : '' ?> onchange="cpToggleModule('<?= $h($mk) ?>', this)"><span class="sl"></span></label>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="thx-card">
            <h2 class="thx-card-title">Zugänge</h2>
            <p class="thx-card-sub">Nutzer dieses Kunden mit eigenem Login. Alle sehen denselben Portal-Umfang.</p>
            <div id="cp-userlist">
                <?php if (empty($portalUsers)): ?>
                    <p style="color:var(--slate-400);font-size:var(--d-fs-sm);" id="cp-noUsers">Noch keine Zugänge.</p>
                <?php else: foreach ($portalUsers as $u): ?>
                    <div class="cps-row">
                        <span class="lbl"><b><?= $h($u['name']) ?></b><small><?= $h($u['email']) ?></small></span>
                        <span class="thx-chip <?= (int)$u['is_active'] === 1 ? 'is-active' : '' ?>"><?= (int)$u['is_active'] === 1 ? 'aktiv' : 'inaktiv' ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;">
                <input type="text" id="cp-name" class="thx-input" placeholder="Name (z. B. Max Muster)">
                <input type="email" id="cp-email" class="thx-input" placeholder="E-Mail">
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="cpCreateUser()" style="align-self:flex-start;"><span class="material-symbols-rounded">person_add</span> Zugang anlegen</button>
                <div id="cp-newcred" style="display:none;background:var(--emerald-50);border:1px solid var(--emerald-200);border-radius:8px;padding:10px 12px;font-size:var(--d-fs-sm);"></div>
            </div>
        </section>
    </div>

    <!-- RECHTS: Chat mit dem Kunden -->
    <aside class="thx-card cps-chat">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 class="thx-card-title" style="margin:0;flex:1;min-width:0;">Chat mit dem Kunden</h2>
            <span id="cp-ki-state" class="cps-ki-state <?= !empty($kiActive) ? 'cps-ki-on' : 'cps-ki-off' ?>">
                <span class="material-symbols-rounded" style="font-size:15px;">smart_toy</span>
                <span id="cp-ki-label"><?= !empty($kiActive) ? 'KI antwortet automatisch' : 'KI pausiert' ?></span>
            </span>
        </div>
        <p class="thx-card-sub" style="margin-top:6px;">Der Assistent antwortet aus den freigegebenen Inhalten. Antwortest Du, pausiert die KI. Mit dem Schalter gibst Du wieder an die KI ab.</p>
        <select class="thx-input" id="cp-conv" onchange="cpSelectConv(this.value)" style="margin:8px 0;"></select>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:var(--d-fs-sm);font-weight:600;color:var(--slate-600);">
            <span class="cps-sw"><input type="checkbox" id="cp-ki-toggle" onchange="cpToggleKi(this)"><span class="sl"></span></span>
            KI-Assistent automatisch antworten lassen
        </label>
        <div class="cps-stream" id="cp-comments"></div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <textarea id="cp-comment-input" class="thx-input" rows="2" placeholder="Antwort an den Kunden… (pausiert die KI)" style="flex:1;resize:vertical;"></textarea>
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="cpPostComment()"><span class="material-symbols-rounded">send</span></button>
        </div>
    </aside>
</div>

<script>
const CP_CID = <?= $cid ?>;
function cpMd(t) {
    let s = App.escapeHtml(String(t)).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    const lines = s.split('\n'); let out = '', inList = false;
    for (const line of lines) {
        const m = line.match(/^\s*[-•]\s+(.*)$/);
        if (m) { if (!inList) { out += '<ul>'; inList = true; } out += '<li>' + m[1] + '</li>'; }
        else { if (inList) { out += '</ul>'; inList = false; } if (line.trim() !== '') out += '<p>' + line + '</p>'; }
    }
    if (inList) out += '</ul>';
    return out || '<p></p>';
}
let cpConvId = null;
async function cpLoadConvs() {
    const r = await App.get('/portal/conversations?customer=' + CP_CID);
    const sel = document.getElementById('cp-conv');
    if (!r.success || !r.data.length) { sel.innerHTML = '<option value="">Keine Unterhaltungen</option>'; document.getElementById('cp-comments').innerHTML = '<div class="pc-empty">Noch keine Unterhaltungen.</div>'; return; }
    sel.innerHTML = r.data.map(c => '<option value="' + c.id + '">' + App.escapeHtml(c.title || 'Unterhaltung') + '</option>').join('');
    cpConvId = cpConvId || r.data[0].id;
    sel.value = cpConvId;
    const conv = r.data.find(c => String(c.id) === String(cpConvId));
    if (conv) cpSetKiUi(parseInt(conv.ki_active, 10) === 1);
    cpLoadComments();
}
function cpSelectConv(id) { cpConvId = id; cpLoadConvs(); }
async function cpLoadComments() {
    if (!cpConvId) return;
    const box = document.getElementById('cp-comments');
    const r = await App.get('/portal/comments?conversation=' + cpConvId + '&customer=' + CP_CID);
    if (!r.success) return;
    if (!r.data.length) { box.innerHTML = '<div class="pc-empty">Noch keine Nachrichten.</div>'; return; }
    box.innerHTML = r.data.map(c => {
        const role = c.author_role;
        const cls = role === 'customer' ? 'pc-customer' : (role === 'ki' ? 'pc-ki' : 'pc-team');
        const who = role === 'customer' ? (c.author_name || 'Kunde') : (role === 'ki' ? 'Assistent (KI)' : (c.author_name || 'Team'));
        const ic  = role === 'customer' ? 'person' : (role === 'ki' ? 'smart_toy' : 'support_agent');
        const d = new Date(String(c.created_at).replace(' ','T')).toLocaleString('de-DE',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
        return '<div class="pc-msg ' + cls + '"><div class="pc-meta"><span class="material-symbols-rounded">' + ic + '</span>' + App.escapeHtml(who) + ' · ' + d + '</div><div class="pc-body">' + cpMd(c.body) + '</div></div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
}
async function cpPostComment() {
    const ta = document.getElementById('cp-comment-input');
    const body = ta.value.trim(); if (!body || !cpConvId) return;
    const r = await App.post('/portal/comments?customer=' + CP_CID, { conversation_id: cpConvId, body });
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    ta.value = ''; cpSetKiUi(false); cpLoadComments();
}
function cpSetKiUi(on) {
    const cb = document.getElementById('cp-ki-toggle'); if (cb) cb.checked = on;
    const lb = document.getElementById('cp-ki-label'); if (lb) lb.textContent = on ? 'KI antwortet automatisch' : 'KI pausiert';
    const st = document.getElementById('cp-ki-state'); if (st) { st.classList.toggle('cps-ki-on', on); st.classList.toggle('cps-ki-off', !on); }
}
async function cpToggleKi(el) {
    if (!cpConvId) { el.checked = !el.checked; return; }
    const r = await App.post('/admin/customers/' + CP_CID + '/portal/ki-toggle', { conversation_id: cpConvId, enabled: el.checked ? 1 : 0 });
    if (!r.success) { el.checked = !el.checked; App.showNotification(r.message || 'Fehler', 'error'); return; }
    cpSetKiUi(el.checked); App.showNotification(r.message || 'Gespeichert', 'success');
}
async function cpToggleModule(mk, el) {
    const r = await App.post('/admin/customers/' + CP_CID + '/portal/module', { module_key: mk, enabled: el.checked ? 1 : 0 });
    if (!r.success) { el.checked = !el.checked; App.showNotification(r.message || 'Fehler', 'error'); } else App.showNotification('Gespeichert', 'success');
}
async function cpToggleCard(cardId, el) {
    const r = await App.post('/admin/customers/' + CP_CID + '/portal/card-visible', { card_id: cardId, visible: el.checked ? 1 : 0 });
    if (!r.success) { el.checked = !el.checked; App.showNotification(r.message || 'Fehler', 'error'); } else App.showNotification('Gespeichert', 'success');
}
async function cpCreateUser() {
    const name = document.getElementById('cp-name').value.trim();
    const email = document.getElementById('cp-email').value.trim();
    if (!name || !email) { App.showNotification('Name und E-Mail nötig', 'error'); return; }
    const r = await App.post('/admin/customers/' + CP_CID + '/portal/user', { name, email });
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    document.getElementById('cp-noUsers')?.remove();
    const row = document.createElement('div'); row.className = 'cps-row';
    row.innerHTML = '<span class="lbl"><b>' + App.escapeHtml(r.data.name||'') + '</b><small>' + App.escapeHtml(r.data.email||'') + '</small></span><span class="thx-chip is-active">aktiv</span>';
    document.getElementById('cp-userlist').appendChild(row);
    const cred = document.getElementById('cp-newcred'); cred.style.display = 'block';
    cred.innerHTML = 'Zugang angelegt. <strong>Einmal-Passwort:</strong> <code>' + App.escapeHtml(r.data.temp_password) + '</code><br>Bitte dem Kunden mitteilen — er kann es später ändern.';
    document.getElementById('cp-name').value = ''; document.getElementById('cp-email').value = '';
    App.showNotification('Customer-User angelegt', 'success');
}
document.addEventListener('DOMContentLoaded', cpLoadConvs);
</script>
