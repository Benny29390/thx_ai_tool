<?php
/** Customer Portal — Chat. Optik & Funktionen 1:1 an /chat angelehnt (zwei Card-Frames, finder-Liste, TOC mit Pfeiltasten). Namespace pq-. */
$h = fn($s) => htmlspecialchars((string)$s);
$q = !empty($isPreview) ? ('?customer=' . (int)($customerId ?? 0)) : '';
?>
<style>
    /* Layout wie /chat: zwei eigene Card-Frames mit Gutter-Gap, fuellt den Content-Bereich */
    .pq-wrap { display:flex; gap:var(--d-gutter); height:calc(100vh - var(--topbar-h) - 2 * var(--d-gutter)); min-height:480px; background:transparent; }
    .pq-list, .pq-main { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius,12px); overflow:hidden; }
    .pq-list { width:360px; min-width:360px; display:flex; flex-direction:column; background:var(--slate-50); }
    .pq-list-head { display:flex; align-items:center; gap:8px; padding:14px var(--d-gutter); border-bottom:1px solid var(--slate-200); }
    .pq-list-head .t { flex:1; font-weight:700; font-size:var(--d-fs-base); color:var(--slate-800); }
    .pq-search { padding:10px var(--d-gutter); border-bottom:1px solid var(--slate-200); }
    .pq-search input { width:100%; box-sizing:border-box; }
    .pq-convs { flex:1; overflow-y:auto; padding-bottom:10px; }
    .finder-section-header { padding:14px var(--d-gutter) 4px; font-size:var(--d-fs-xs); font-weight:600; color:var(--slate-400); text-transform:uppercase; letter-spacing:.03em; }
    .finder-date-label { padding:12px var(--d-gutter) 4px; font-size:var(--d-fs-xs); font-weight:600; color:var(--slate-400); text-transform:uppercase; letter-spacing:.03em; user-select:none; }
    .finder-item { display:flex; align-items:center; gap:8px; padding:7px var(--d-gutter); border-left:3px solid transparent; cursor:pointer; font-size:var(--d-fs-sm); color:var(--slate-700); transition:background .1s, border-color .1s; user-select:none; }
    .finder-item:hover { background:var(--slate-100); }
    .finder-item.active { background:var(--thoxan-50); border-left-color:var(--thoxan-600); color:var(--slate-900); }
    .finder-item-icon { font-size:16px; color:var(--slate-400); flex-shrink:0; }
    .finder-item.active .finder-item-icon { color:var(--thoxan-600); }
    .finder-item-body { flex:1; min-width:0; }
    .finder-item-title { font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .finder-item-sub { color:var(--slate-400); font-size:var(--d-fs-xs); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:1px; }
    .pq-convs-empty { color:var(--slate-400); font-size:var(--d-fs-sm); padding:18px var(--d-gutter); }

    /* Hauptbereich */
    .pq-main { flex:1; display:flex; flex-direction:column; min-width:0; }
    .pq-head { display:flex; align-items:center; gap:10px; padding:13px 18px; border-bottom:1px solid var(--slate-200); background:#fff; }
    .pq-head .ttl { flex:1; min-width:0; font-weight:700; font-size:var(--d-fs-base); color:var(--slate-800); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pq-ki-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:var(--d-fs-xs); font-weight:600; }
    .pq-ki-on { background:var(--emerald-50); color:var(--emerald-700); border:1px solid var(--emerald-200); }
    .pq-ki-off { background:var(--amber-50); color:var(--amber-800); border:1px solid var(--amber-200); }
    .pq-ki-pill .material-symbols-rounded { font-size:14px; }
    .pq-bodyrow { flex:1; display:flex; min-height:0; }

    /* TOC — wie /chat: immer sichtbar, mit Pfeiltasten steuerbar */
    .pq-toc { flex-shrink:0; width:64px; padding:var(--d-gutter) 0; display:flex; flex-direction:column; gap:2px; overflow-y:auto; border-right:1px solid var(--slate-100); background:#fff; outline:none; }
    .pq-toc:empty { display:none; }
    .pq-toc-item { display:flex; align-items:center; justify-content:flex-start; padding:6px var(--d-gutter); width:100%; height:auto; background:transparent; border:0; cursor:pointer; transition:background .1s; position:relative; text-align:left; }
    .pq-toc-item::before { content:''; display:block; width:18px; height:3px; background:var(--slate-300); border-radius:2px; transition:width .12s, background .12s, height .12s; }
    .pq-toc-item:hover { background:var(--slate-50); }
    .pq-toc-item:hover::before { width:28px; background:var(--slate-600); }
    .pq-toc-item:focus { outline:none; background:var(--slate-50); }
    .pq-toc-item:focus::before { width:28px; background:var(--slate-700); }
    .pq-toc-item.active { background:var(--thoxan-50); }
    .pq-toc-item.active::before { width:30px; height:4px; background:var(--thoxan-600); }
    .pq-toc-item[data-preview]:hover::after { content:attr(data-preview); position:absolute; left:calc(100% + 4px); top:50%; transform:translateY(-50%); background:var(--slate-900); color:#fff; padding:6px 10px; border-radius:6px; font-size:11px; line-height:1.35; max-width:320px; min-width:120px; white-space:normal; word-break:break-word; box-shadow:0 4px 12px rgba(0,0,0,.18); z-index:30; pointer-events:none; }

    .pq-col { flex:1; display:flex; flex-direction:column; min-width:0; }
    .pq-messages { flex:1; overflow-y:auto; padding:var(--d-gutter); display:flex; flex-direction:column; }
    /* Nachrichten — exakte /chat-Werte */
    .pq-msg { max-width:960px; width:100%; margin:0 auto; padding:18px 0; }
    .pq-msg + .pq-msg { margin-top:4px; }
    .pq-msg-head { display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:var(--d-fs-sm); font-weight:600; }
    .pq-time { color:#94a3b8; font-size:var(--d-fs-xs); font-weight:500; margin-left:auto; }
    .pq-avatar { display:inline-flex; align-items:center; justify-content:center; min-width:30px; height:22px; padding:0 6px; border-radius:var(--d-control-radius,8px); border:1px solid var(--slate-200); background:#fff; color:var(--slate-700); font-size:11px; font-weight:700; letter-spacing:.3px; flex-shrink:0; text-transform:uppercase; }
    .pq-avatar.user { background:var(--thoxan-50); color:var(--thoxan-800); border-color:var(--thoxan-200); }
    .pq-avatar.ass  { background:var(--slate-50); color:var(--slate-700); border-color:var(--slate-200); min-width:22px; width:22px; padding:0; }
    .pq-avatar.ki   { background:#eef0ff; color:#6366f1; border-color:#c7d2fe; min-width:22px; width:22px; padding:0; }
    .pq-msg.user .pq-msg-head { color:var(--thoxan-900); }
    .pq-msg.user .pq-msg-body { background:#e6f0fa; border-left:3px solid var(--thoxan-700); border-radius:0 12px 12px 0; padding:14px 18px; white-space:pre-wrap; word-break:break-word; font-size:var(--d-fs-sm); line-height:1.65; color:#1e293b; margin-left:36px; }
    .pq-msg.ass .pq-msg-head { color:var(--slate-500); }
    .pq-msg.ass .pq-msg-body { font-size:var(--d-fs-base); line-height:1.75; color:#1e293b; padding:4px 0; margin-left:30px; }
    .pq-msg-body p { margin:0 0 10px; } .pq-msg-body p:last-child { margin:0; }
    .pq-msg-body ul,.pq-msg-body ol { margin:8px 0; padding-left:22px; } .pq-msg-body li { margin:3px 0; }
    .pq-msg-body strong { font-weight:700; } .pq-msg-body h1,.pq-msg-body h2,.pq-msg-body h3 { font-size:var(--d-fs-lg); margin:14px 0 6px; }
    .pq-msg-body code { background:var(--slate-100); padding:1px 5px; border-radius:5px; font-size:.9em; }
    .pq-msg-body pre { background:var(--slate-900); color:#e2e8f0; padding:12px 14px; border-radius:10px; overflow:auto; margin:10px 0; }
    .pq-msg-body pre code { background:none; padding:0; color:inherit; } .pq-msg-body a { color:var(--thoxan-700); text-decoration:underline; }
    .pq-att { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; margin-left:30px; }
    .pq-msg.user .pq-att { margin-left:36px; }
    .pq-att-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:#fff; border:1px solid var(--slate-200); border-radius:20px; font-size:var(--d-fs-xs); color:var(--slate-700); text-decoration:none; }
    .pq-att-chip:hover { border-color:var(--thoxan-400,#5b8fd1); color:var(--thoxan-700); }
    .pq-att-chip .material-symbols-rounded { font-size:14px; }
    .pq-typing { max-width:960px; width:100%; margin:0 auto; padding:6px 0 6px 30px; color:var(--slate-400); font-size:var(--d-fs-sm); display:flex; align-items:center; gap:6px; }
    .pq-typing .material-symbols-rounded { font-size:18px; animation:pqspin 1.2s linear infinite; }
    @keyframes pqspin { to { transform:rotate(360deg); } }
    .pq-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; color:var(--slate-400); text-align:center; padding:30px; }
    .pq-empty .material-symbols-rounded { font-size:56px; color:var(--slate-300); }
    .pq-empty h3 { font-size:var(--d-fs-xl); font-weight:600; color:var(--slate-700); margin:0; }
    /* Composer — /chat-Werte */
    .pq-input-area { padding:var(--d-gutter); background:#fff; border-top:1px solid var(--slate-100); }
    .pq-input-wrap { max-width:960px; margin:0 auto; width:100%; }
    .pq-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
    .pq-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; background:var(--slate-100); border:1px solid var(--slate-200); border-radius:18px; font-size:var(--d-fs-xs); color:var(--slate-700); }
    .pq-chip button { background:none; border:0; cursor:pointer; color:var(--slate-400); display:flex; padding:0; }
    .pq-chip button:hover { color:var(--rose-600); } .pq-chip .material-symbols-rounded { font-size:14px; }
    .pq-input-box { display:flex; align-items:flex-end; gap:8px; width:100%; box-sizing:border-box; border:1px solid var(--slate-200); border-radius:20px; padding:14px 18px; background:var(--slate-50); transition:border-color .15s, box-shadow .15s; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .pq-input-box:focus-within { border-color:var(--slate-400); background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .pq-attach-btn { background:none; border:0; padding:6px; border-radius:50%; color:var(--slate-400); display:flex; cursor:pointer; flex-shrink:0; }
    .pq-attach-btn:hover { color:var(--slate-700); background:var(--slate-200); }
    .pq-input-box textarea { flex:1 1 auto; min-width:0; border:none; outline:none; resize:none; font-size:var(--d-fs-sm); line-height:1.6; max-height:200px; min-height:40px; padding:4px 0; background:transparent; color:var(--slate-800); font-family:inherit; }
    .pq-input-box textarea::placeholder { color:var(--slate-400); }
    .pq-send { flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; padding:9px; border-radius:14px; }
    .pq-send .material-symbols-rounded { font-size:20px; }
    .pq-hint { text-align:center; font-size:var(--d-fs-xs); color:var(--slate-400); margin-top:8px; }
    .pq-preview { display:flex; align-items:center; gap:8px; background:var(--amber-50); color:var(--amber-800); border:1px solid var(--amber-200); border-radius:10px; font-size:var(--d-fs-xs); font-weight:600; padding:8px 12px; margin-bottom:10px; }
    .pq-preview .material-symbols-rounded { font-size:16px; }
    @media (max-width:860px){ .pq-list { display:none; } .pq-toc { display:none; } }
</style>

<?php if (!empty($isPreview)): ?>
    <div class="pq-preview"><span class="material-symbols-rounded">visibility</span> Vorschau-Modus (Team) — so sieht der Kunde seinen Chat.</div>
<?php endif; ?>

<div class="pq-wrap">
    <!-- Konversationsliste (eigener Card-Frame) -->
    <aside class="pq-list">
        <div class="pq-list-head">
            <span class="t">Unterhaltungen</span>
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="pqNewConv()" title="Neue Unterhaltung"><span class="material-symbols-rounded">add</span> Neu</button>
        </div>
        <div class="pq-search"><input type="text" class="thx-input" id="pq-conv-search" placeholder="Suchen…" oninput="pqRenderConvs()"></div>
        <div class="pq-convs" id="pq-convs"></div>
    </aside>

    <!-- Hauptbereich (eigener Card-Frame) -->
    <div class="pq-main">
        <div class="pq-head">
            <span class="ttl" id="pq-title">Ihr Draht zu Thoxan</span>
            <span class="pq-ki-pill pq-ki-on" id="pq-ki-pill" style="display:none;"><span class="material-symbols-rounded">smart_toy</span><span id="pq-ki-text">Assistent aktiv</span></span>
        </div>
        <div class="pq-bodyrow">
            <nav class="pq-toc" id="pq-toc" aria-label="Fragen-Verzeichnis"></nav>
            <div class="pq-col">
                <div class="pq-messages" id="pq-messages"></div>
                <div class="pq-input-area">
                    <div class="pq-input-wrap">
                        <div class="pq-chips" id="pq-chips"></div>
                        <div class="pq-input-box">
                            <button class="pq-attach-btn" onclick="document.getElementById('pq-file').click()" title="Datei anhängen"><span class="material-symbols-rounded">attach_file</span></button>
                            <input type="file" id="pq-file" style="display:none" onchange="pqUpload(this)" accept=".pdf,.docx,.txt,.md,.csv,.html,.htm,.png,.jpg,.jpeg,.gif,.webp">
                            <textarea id="pq-text" rows="1" placeholder="Nachricht eingeben…" onkeydown="pqKey(event)" oninput="pqGrow(this)"></textarea>
                            <button class="thx-btn thx-btn-primary pq-send" onclick="pqSend()" title="Senden"><span class="material-symbols-rounded">arrow_upward</span></button>
                        </div>
                        <span class="pq-hint">Enter = Senden, Shift+Enter = Neue Zeile · PDF/DOCX/Bild anhängbar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/vendor/marked.min.js"></script>
<script src="/assets/js/vendor/highlight.min.js"></script>
<script>
(function () { if (window.marked) { window.marked.setOptions({ breaks: true, gfm: true, pedantic: false }); } })();
const PQ_Q = <?= json_encode($q) ?>;
const pqState = { convId: null, convs: [], msgs: [], pending: [] };

function pqApi(path) { return path + (path.indexOf('?') >= 0 ? (PQ_Q ? '&' + PQ_Q.slice(1) : '') : PQ_Q); }
function pqMd(t) { const raw = String(t || ''); if (window.marked) { try { return window.marked.parse(raw); } catch (e) {} } return '<p>' + App.escapeHtml(raw).replace(/\n/g, '<br>') + '</p>'; }
function pqGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 200) + 'px'; }
function pqKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); pqSend(); } }
function pqTime(s) { try { return new Date(String(s).replace(' ','T')).toLocaleString('de-DE',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); } catch(e){ return ''; } }
function pqEsc(s) { return App.escapeHtml(String(s == null ? '' : s)); }

// ── Konversationsliste (mit Datumsgruppen wie /chat) ────────────────────────
function pqGroupByDate(list) {
    const groups = {}; const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yest = new Date(today); yest.setDate(today.getDate() - 1);
    const week = new Date(today); week.setDate(today.getDate() - 7);
    list.forEach(c => {
        const d = new Date(String(c.updated_at || '').replace(' ','T'));
        let label = 'Älter';
        if (d >= today) label = 'Heute'; else if (d >= yest) label = 'Gestern'; else if (d >= week) label = 'Letzte 7 Tage';
        (groups[label] = groups[label] || []).push(c);
    });
    return groups;
}
async function pqLoadConvs(selectId) {
    const r = await App.get(pqApi('/portal/conversations'));
    if (!r.success) return;
    pqState.convs = r.data || [];
    pqRenderConvs();
    if (selectId) pqSelectConv(selectId);
    else if (!pqState.convId && pqState.convs.length) pqSelectConv(pqState.convs[0].id);
    else if (!pqState.convs.length) pqShowEmptyState();
}
function pqRenderConvs() {
    const box = document.getElementById('pq-convs');
    const term = (document.getElementById('pq-conv-search').value || '').toLowerCase();
    const list = pqState.convs.filter(c => !term || (c.title || '').toLowerCase().includes(term) || (c.last_body || '').toLowerCase().includes(term));
    if (!list.length) { box.innerHTML = '<div class="pq-convs-empty">Keine Unterhaltungen.</div>'; return; }
    let html = '<div class="finder-section-header">Unterhaltungen <span style="color:#94a3b8;font-weight:500;">· ' + list.length + '</span></div>';
    const groups = pqGroupByDate(list);
    for (const [label, convs] of Object.entries(groups)) {
        html += '<div class="finder-date-label">' + label + '</div>';
        convs.forEach(c => {
            html += '<div class="finder-item ' + (c.id === pqState.convId ? 'active' : '') + '" onclick="pqSelectConv(' + c.id + ')">' +
                '<span class="material-symbols-rounded finder-item-icon">chat_bubble_outline</span>' +
                '<div class="finder-item-body"><div class="finder-item-title">' + pqEsc(c.title || 'Unterhaltung') + '</div>' +
                '<div class="finder-item-sub">' + pqEsc((c.last_body || '').slice(0, 70) || 'Noch keine Nachricht') + '</div></div></div>';
        });
    }
    box.innerHTML = html;
}
async function pqNewConv() {
    const r = await App.post(pqApi('/portal/conversations'), { title: '' });
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    pqState.convId = r.data.id;
    await pqLoadConvs(r.data.id);
    document.getElementById('pq-text').focus();
}
async function pqEnsureConv() {
    if (pqState.convId) return pqState.convId;
    const r = await App.post(pqApi('/portal/conversations'), { title: '' });
    if (!r.success) throw new Error(r.message || 'Fehler');
    pqState.convId = r.data.id; await pqLoadConvs(r.data.id); return pqState.convId;
}

// ── Auswahl + Nachrichten ───────────────────────────────────────────────────
async function pqSelectConv(id) {
    pqState.convId = id; pqState.pending = []; pqRenderChips(); pqRenderConvs();
    const conv = pqState.convs.find(c => c.id === id);
    document.getElementById('pq-title').textContent = conv ? (conv.title || 'Unterhaltung') : 'Unterhaltung';
    if (conv) pqSetKiPill(parseInt(conv.ki_active, 10) === 1);
    await pqLoadMessages();
}
function pqSetKiPill(on) {
    const p = document.getElementById('pq-ki-pill'); const t = document.getElementById('pq-ki-text');
    p.style.display = 'inline-flex'; p.className = 'pq-ki-pill ' + (on ? 'pq-ki-on' : 'pq-ki-off');
    t.textContent = on ? 'Assistent aktiv' : 'Team übernimmt';
}
function pqShowEmptyState() {
    document.getElementById('pq-messages').innerHTML =
        '<div class="pq-empty"><span class="material-symbols-rounded">forum</span><h3>Wie können wir helfen?</h3>' +
        '<p>Stellen Sie Ihre Frage — unser Assistent antwortet sofort aus dem aktuellen Projektstand, das Team schaltet sich bei Bedarf dazu.</p></div>';
    document.getElementById('pq-toc').innerHTML = '';
}
function pqAttHtml(atts) {
    if (!atts || !atts.length) return '';
    return '<div class="pq-att">' + atts.map(a => a.id
        ? '<a class="pq-att-chip" href="' + pqApi('/api/v1/portal/attachment?id=' + a.id) + '" target="_blank" rel="noopener"><span class="material-symbols-rounded">description</span>' + pqEsc(a.name) + '</a>'
        : '<span class="pq-att-chip"><span class="material-symbols-rounded">description</span>' + pqEsc(a.name) + '</span>'
    ).join('') + '</div>';
}
function pqRow(c) {
    const t = c.created_at ? '<span class="pq-time">' + pqTime(c.created_at) + '</span>' : '';
    const att = pqAttHtml(c.attachments);
    const anchor = c.id ? ' id="pq-anchor-' + c.id + '"' : '';
    if (c.author_role === 'customer') {
        return '<div class="pq-msg user"' + anchor + '><div class="pq-msg-head"><span class="pq-avatar user">Sie</span>Sie' + t + '</div>' +
               '<div class="pq-msg-body">' + pqEsc(c.body) + '</div>' + att + '</div>';
    }
    const isKi = c.author_role === 'ki';
    const who = isKi ? 'Assistent' : (c.author_name || 'Thoxan-Team');
    const av = isKi ? '<span class="pq-avatar ki"><span class="material-symbols-rounded" style="font-size:15px;">smart_toy</span></span>'
                    : '<span class="pq-avatar ass"><span class="material-symbols-rounded" style="font-size:15px;">support_agent</span></span>';
    return '<div class="pq-msg ass"' + anchor + '><div class="pq-msg-head">' + av + pqEsc(who) + t + '</div>' +
           '<div class="pq-msg-body">' + pqMd(c.body) + '</div>' + att + '</div>';
}
function pqScroll() { const m = document.getElementById('pq-messages'); if (m) m.scrollTop = m.scrollHeight; }
async function pqLoadMessages() {
    if (!pqState.convId) { pqShowEmptyState(); return; }
    const r = await App.get(pqApi('/portal/comments?conversation=' + pqState.convId));
    if (!r.success) return;
    pqState.msgs = r.data || [];
    if (!pqState.msgs.length) { pqShowEmptyState(); return; }
    document.getElementById('pq-messages').innerHTML = pqState.msgs.map(pqRow).join('');
    pqRenderToc(); pqScroll();
}

// ── TOC (Inhaltsverzeichnis) mit Pfeiltasten — wie /chat ────────────────────
function pqRenderToc() {
    const toc = document.getElementById('pq-toc');
    const userMsgs = (pqState.msgs || []).filter(m => m.author_role === 'customer' && m.id);
    if (userMsgs.length < 2) { toc.innerHTML = ''; return; }
    toc.innerHTML = userMsgs.map((m, i) => {
        const prev = String(m.body || '').replace(/\s+/g, ' ').trim().substring(0, 120);
        return '<button class="pq-toc-item" type="button" data-msg-id="' + m.id + '" data-preview="' + pqEsc(prev) + '" title="Frage ' + (i + 1) + ': ' + pqEsc(prev) + '" onclick="pqScrollToAnchor(' + m.id + ')"></button>';
    }).join('');
    pqInstallTocKeyboard();
    pqUpdateTocActive();
}
function pqInstallTocKeyboard() {
    const toc = document.getElementById('pq-toc');
    if (!toc || toc.dataset.kbReady === '1') return;
    toc.dataset.kbReady = '1';
    toc.setAttribute('tabindex', '0');
    toc.addEventListener('click', (e) => { if (e.target === toc) { const a = toc.querySelector('.pq-toc-item.active') || toc.querySelector('.pq-toc-item'); if (a) a.focus(); } });
    toc.addEventListener('keydown', (e) => {
        if (!['ArrowUp','ArrowDown','Home','End'].includes(e.key)) return;
        e.preventDefault();
        const items = Array.from(toc.querySelectorAll('.pq-toc-item'));
        if (!items.length) return;
        let idx = items.indexOf(document.activeElement);
        if (idx < 0) { const a = items.findIndex(it => it.classList.contains('active')); idx = a >= 0 ? a : 0; }
        let next = idx;
        if (e.key === 'ArrowUp') next = Math.max(0, idx - 1);
        else if (e.key === 'ArrowDown') next = Math.min(items.length - 1, idx + 1);
        else if (e.key === 'Home') next = 0; else if (e.key === 'End') next = items.length - 1;
        const target = items[next]; if (!target) return;
        target.focus();
        const id = parseInt(target.dataset.msgId, 10); if (id) pqScrollToAnchor(id);
    });
}
window.pqScrollToAnchor = function (id) {
    const el = document.getElementById('pq-anchor-' + id);
    const mc = document.getElementById('pq-messages');
    if (!el || !mc) return;
    const er = el.getBoundingClientRect(), mr = mc.getBoundingClientRect();
    mc.scrollTop = Math.max(0, mc.scrollTop + (er.top - mr.top) - 8);
    pqUpdateTocActive();
};
function pqUpdateTocActive() {
    const toc = document.getElementById('pq-toc'); const mc = document.getElementById('pq-messages');
    if (!toc || !mc) return;
    const items = toc.querySelectorAll('.pq-toc-item'); if (!items.length) return;
    if ((mc.scrollHeight - mc.scrollTop - mc.clientHeight) < 4) {
        const lastId = items[items.length - 1].dataset.msgId;
        items.forEach(it => it.classList.toggle('active', it.dataset.msgId === lastId)); return;
    }
    const mcTop = mc.getBoundingClientRect().top; let activeId = null;
    items.forEach(it => { const a = document.getElementById('pq-anchor-' + it.dataset.msgId); if (!a) return; if (a.getBoundingClientRect().top - mcTop <= 80) activeId = it.dataset.msgId; });
    items.forEach(it => it.classList.toggle('active', it.dataset.msgId === activeId));
}

// ── Anhaenge ────────────────────────────────────────────────────────────────
async function pqUpload(input) {
    const file = input.files && input.files[0]; input.value = '';
    if (!file) return;
    try { await pqEnsureConv(); } catch (e) { App.showNotification('Fehler', 'error'); return; }
    const fd = new FormData(); fd.append('file', file); fd.append('conversation_id', pqState.convId);
    App.showNotification('Lädt hoch…', 'info', 1500);
    try {
        const resp = await fetch('/api/v1' + pqApi('/portal/upload'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': App.csrfToken }, body: fd });
        const r = await resp.json();
        if (!r.success) { App.showNotification(r.message || 'Upload fehlgeschlagen', 'error'); return; }
        pqState.pending.push({ id: r.data.id, name: r.data.name }); pqRenderChips();
    } catch (e) { App.showNotification('Upload fehlgeschlagen', 'error'); }
}
function pqRenderChips() {
    document.getElementById('pq-chips').innerHTML = pqState.pending.map((p, i) =>
        '<span class="pq-chip"><span class="material-symbols-rounded">description</span>' + pqEsc(p.name) + '<button onclick="pqRemoveChip(' + i + ')" title="Entfernen"><span class="material-symbols-rounded">close</span></button></span>'
    ).join('');
}
function pqRemoveChip(i) { pqState.pending.splice(i, 1); pqRenderChips(); }

// ── Senden ──────────────────────────────────────────────────────────────────
async function pqSend() {
    const ta = document.getElementById('pq-text');
    const body = ta.value.trim();
    if (!body && !pqState.pending.length) return;
    try { await pqEnsureConv(); } catch (e) { App.showNotification('Fehler', 'error'); return; }
    ta.value = ''; ta.style.height = 'auto';
    const attIds = pqState.pending.map(p => p.id);
    const attNote = pqState.pending.map(p => ({ id: 0, name: p.name }));
    pqState.pending = []; pqRenderChips();
    const m = document.getElementById('pq-messages');
    if (m.querySelector('.pq-empty')) m.innerHTML = '';
    m.insertAdjacentHTML('beforeend', pqRow({ author_role: 'customer', body: body || '(Datei angehängt)', attachments: attNote }));
    m.insertAdjacentHTML('beforeend', '<div class="pq-typing" id="pq-typing"><span class="material-symbols-rounded">progress_activity</span> Assistent denkt nach…</div>');
    pqScroll();
    const r = await App.post(pqApi('/portal/comments'), { conversation_id: pqState.convId, body: body, attachment_ids: attIds });
    document.getElementById('pq-typing')?.remove();
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    await pqLoadMessages();
    pqLoadConvs(pqState.convId);
}
document.addEventListener('DOMContentLoaded', () => {
    pqLoadConvs();
    const mc = document.getElementById('pq-messages');
    if (mc) mc.addEventListener('scroll', () => { window.requestAnimationFrame(pqUpdateTocActive); });
});
</script>
