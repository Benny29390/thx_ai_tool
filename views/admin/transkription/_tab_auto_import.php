<?php
/* Transkription — Auto-Import: Record SDK + API-Token + Bookmarklet */
$baseUrl = \Core\Brand::url();
$endpoint = $baseUrl . '/api/v1/admin/transkription/loom-quick';
$loomSdkAppId = (string)\Core\Settings::get('loom_sdk_public_app_id');
?>
<style>
.tr-imp-card { background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);margin-bottom:var(--d-section-gap); }
.tr-imp-card h3 { margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base); }
.tr-imp-card p  { margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600); }
.tr-imp-code   { font-family:ui-monospace,monospace;font-size:var(--d-fs-xs);background:var(--slate-50);padding:8px 10px;border-radius:6px;border:1px solid var(--slate-200);overflow-x:auto;white-space:pre;word-break:break-all; }
.tr-imp-token  { display:flex;gap:8px;align-items:center;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:8px;padding:10px 12px;margin:8px 0; }
.tr-imp-token code { font-family:ui-monospace,monospace;font-size:var(--d-fs-sm);flex:1;word-break:break-all; }
.tr-imp-list   { width:100%;border-collapse:collapse;font-size:var(--d-fs-sm); }
.tr-imp-list th { text-align:left;padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x);color:var(--slate-500);font-size:var(--d-fs-xs);text-transform:uppercase;border-bottom:1px solid var(--slate-200); }
.tr-imp-list td { padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x);border-bottom:1px solid var(--slate-100); }
.tr-imp-bookmarklet { display:inline-block;background:var(--thoxan-700);color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none;font-weight:600;cursor:grab;user-select:all; }
.tr-imp-bookmarklet:hover { background:var(--thoxan-800); }
details.tr-imp-howto { background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;padding:10px 12px;margin-top:8px; }
details.tr-imp-howto summary { cursor:pointer;font-weight:600;font-size:var(--d-fs-sm);color:var(--slate-700); }
details.tr-imp-howto[open] summary { margin-bottom:8px; }
details.tr-imp-howto ol { margin:0;padding-left:20px;font-size:var(--d-fs-sm);color:var(--slate-700);line-height:1.6; }
details.tr-imp-howto code { background:#fff;border:1px solid var(--slate-200);padding:1px 5px;border-radius:3px;font-size:0.92em; }
</style>

<div class="tr-imp-card" style="border-left:4px solid var(--thoxan-600);">
    <h3>Loom-Library scrapen <span style="font-weight:400;color:var(--slate-500);font-size:var(--d-fs-xs);">empfohlen</span></h3>
    <p>
        <strong>Loom hat keine offene API</strong> (auch nicht in Zapier/Make/n8n — es gibt keinen offiziellen
        Loom-Trigger). Praktikabler Weg: das <em>Library-Scrape-Bookmarklet</em> weiter unten in die Lesezeichenleiste
        ziehen, einmalig oder regelmaessig auf <code>loom.com/looms</code> klicken — alle Aufnahmen werden in einem Rutsch
        eingereiht. Duplikate werden automatisch uebersprungen, alte Klicks tun nicht weh.
    </p>
    <details class="tr-imp-howto" open>
        <summary>Workflow (1× einrichten, danach 1 Klick alle paar Tage)</summary>
        <ol>
            <li>Im Token-Card unten zuerst einen API-Token erzeugen (Label „Bookmarklet").</li>
            <li>Den Button „→ Loom-Library scrapen" in Deine Lesezeichenleiste ziehen (nicht klicken).</li>
            <li>Auf <code>https://www.loom.com/looms</code> gehen (Deine Library) oder in einen Folder navigieren.</li>
            <li>Bookmarklet klicken → es scrollt automatisch durch die Library, sammelt alle Share-URLs und schickt sie
                an die Pipeline. Bestaetigungs-Popup zeigt am Ende „X eingereiht, Y uebersprungen".</li>
            <li>Bei neuen Aufnahmen einfach nochmal klicken — die neuen werden hinzugefuegt, alte ignoriert.</li>
        </ol>
        <p style="margin-top:8px;font-size:var(--d-fs-xs);color:var(--slate-500);">
            Falls Loom in Zukunft einen Workspace-API/Zapier-Connector ausrollt: dann koennen wir auf vollautomatisches
            Polling umstellen. Bis dahin ist 1-Klick-Bulk der robusteste Weg.
        </p>
    </details>
</div>

<div class="tr-imp-card">
    <h3>Loom Record SDK <span style="font-weight:400;color:var(--slate-500);font-size:var(--d-fs-xs);">optional (im Tool aufnehmen)</span></h3>
    <p>
        Falls Du im Browser aus unserem Tool heraus aufnehmen willst (statt extern in Loom). Setzt voraus, dass der Tab
        offen ist und das esm.sh-CDN erreichbar. Im Upload-Tab erscheint dann ein „Aufnahme starten"-Button.
    </p>
    <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" class="thx-input" id="tr-sdk-app-id" placeholder="Public App ID (z.B. cf83c463-...)" value="<?= htmlspecialchars($loomSdkAppId) ?>" style="flex:1;font-family:ui-monospace,monospace;font-size:var(--d-fs-sm);">
        <button class="thx-btn thx-btn-secondary" onclick="trSdkSave()">Speichern</button>
        <span id="tr-sdk-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
    </div>
</div>

<div class="tr-imp-card">
    <h3>API-Token <span style="font-weight:400;color:var(--slate-500);font-size:var(--d-fs-xs);">fuer Bookmarklet / externe Tools</span></h3>
    <p>
        Mit einem persoenlichen Token koennen externe Tools (Bookmarklet, iOS-Shortcuts, Make.com, Zapier, n8n)
        eine Loom-URL direkt in die Transkriptions-Pipeline pushen — ohne Login. Der Token zeigt sich nur
        EINMAL nach dem Erzeugen, danach wird nur ein sha256-Hash gespeichert.
    </p>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:var(--d-row-gap);">
        <input type="text" class="thx-input" id="tr-tok-label" placeholder="Bezeichnung (z.B. „Bookmarklet Safari")" style="flex:1;">
        <button class="thx-btn thx-btn-primary" onclick="trTokCreate()">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">key</span>
            Token erzeugen
        </button>
    </div>
    <div id="tr-tok-new"></div>

    <table class="tr-imp-list">
        <thead><tr><th>Bezeichnung</th><th>Erzeugt</th><th>Zuletzt benutzt</th><th style="width:60px;"></th></tr></thead>
        <tbody id="tr-tok-list"><tr><td colspan="4" style="padding:14px;text-align:center;color:var(--slate-400);">Laedt …</td></tr></tbody>
    </table>
</div>

<div class="tr-imp-card">
    <h3>Bookmarklets (Drag in Lesezeichenleiste)</h3>
    <p>
        Erst einen Token oben erzeugen. Dann die Knoepfe unten in Deine Lesezeichenleiste ziehen.
        Zwei Varianten — eine fuer einzelne Aufnahmen, eine fuer Massen-Backfill aus Deiner Loom-Library.
    </p>
    <div id="tr-imp-bm-host" style="margin:12px 0;">
        <span style="color:var(--slate-500);font-size:var(--d-fs-sm);">Bookmarklets werden erstellt sobald ein Token vorhanden ist …</span>
    </div>
</div>

<div class="tr-imp-card">
    <h3>API-Endpoint</h3>
    <p>Fuer eigene Integrationen (Zapier, Make, n8n, eigenes Skript):</p>
    <div class="tr-imp-code">POST <?= htmlspecialchars($endpoint) ?>
Authorization: Bearer thx_tr_DEIN_TOKEN
Content-Type: application/json

{ "url": "https://www.loom.com/share/..." }</div>

    <details class="tr-imp-howto">
        <summary>curl-Beispiel</summary>
        <div class="tr-imp-code" style="margin-top:8px;">curl -X POST <?= htmlspecialchars($endpoint) ?> \
  -H "Authorization: Bearer thx_tr_DEIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://www.loom.com/share/abc123"}'</div>
    </details>

    <details class="tr-imp-howto">
        <summary>iOS Kurzbefehle (Shortcut) einrichten</summary>
        <ol>
            <li>Kurzbefehle-App oeffnen → „+" oben rechts</li>
            <li>Aktion „URL erhalten" hinzufuegen → als <code>Eingabe</code> waehlen</li>
            <li>Aktion „Inhalte einer URL abrufen": Methode <code>POST</code>, URL <code><?= htmlspecialchars($endpoint) ?></code></li>
            <li>Header hinzufuegen: <code>Authorization</code> = <code>Bearer thx_tr_DEIN_TOKEN</code></li>
            <li>Anfragetext: JSON, Schluessel <code>url</code> = die erhaltene URL</li>
            <li>Im Sharesheet sichtbar machen, akzeptierte Typen: URLs</li>
            <li>In Loom „Teilen" → Kurzbefehl waehlen → fertig</li>
        </ol>
    </details>

    <details class="tr-imp-howto">
        <summary>Eigene Integration (n8n, Make HTTP-Module, Skript, …)</summary>
        <p style="margin:6px 0;font-size:var(--d-fs-sm);">
            Falls Du eine Quelle hast, die bei neuen Loom-Aufnahmen feuert (z.B. Loom-Notifications-Mail an einen
            Parser, eine eigene Browser-Extension, ein RSS-Reader), POST einfach mit dem Token an obigen Endpoint
            mit Body <code>{"url":"…"}</code>.
        </p>
    </details>
</div>

<script>
'use strict';
(function() {
    let activeToken = null;  // Klartext nur in Session, fuer Bookmarklet
    function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtDate(s) { if (!s) return '–'; return new Date(s).toLocaleString('de-DE'); }

    async function loadList() {
        const r = await fetch('/api/v1/admin/transkription/tokens');
        const j = await r.json();
        const tbody = document.getElementById('tr-tok-list');
        if (!j.success) { tbody.innerHTML = '<tr><td colspan="4" style="color:var(--rose-700);">' + esc(j.message) + '</td></tr>'; return; }
        if (!j.data.tokens.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="padding:14px;text-align:center;color:var(--slate-400);">Noch keine Tokens.</td></tr>';
            return;
        }
        tbody.innerHTML = j.data.tokens.map(t => `
            <tr>
                <td><strong>${esc(t.label)}</strong></td>
                <td style="color:var(--slate-500);font-size:var(--d-fs-xs);">${fmtDate(t.created_at)}</td>
                <td style="color:var(--slate-500);font-size:var(--d-fs-xs);">${t.last_used_at ? fmtDate(t.last_used_at) : 'nie'}</td>
                <td><button class="thx-btn thx-btn-small thx-btn-danger" onclick="trTokDel(${t.id})" title="Token loeschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button></td>
            </tr>`).join('');
    }

    function buildSingleBookmarklet(token) {
        const endpoint = <?= json_encode($endpoint) ?>;
        const js = `(function(){var u=location.href;if(!/loom\\.com\\/(share|embed)\\//.test(u)){alert('Diese Seite ist keine Loom-URL.');return;}fetch('${endpoint}',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer ${token}'},body:JSON.stringify({url:u})}).then(function(r){return r.json();}).then(function(j){alert(j.success?('Eingereiht: '+j.message):('Fehler: '+j.message));}).catch(function(e){alert('Netzwerkfehler: '+e.message);});})();`;
        return 'javascript:' + encodeURIComponent(js);
    }

    function buildLibraryBookmarklet(token) {
        const endpoint = <?= json_encode($endpoint) ?>;
        // Scrollt durch die Library um lazy-loaded Videos zu materialisieren,
        // sammelt alle eindeutigen Share-URLs aus <a>-Tags, schickt sie an /loom-quick.
        const js = `(async function(){
var T='${token}',E='${endpoint}';
var ok=confirm('Loom-Library-Scrape: scrollt automatisch durch alle sichtbaren Videos und sendet jede gefundene URL an Thoxan. Fortfahren?');
if(!ok)return;
var lastH=0,sameCount=0;
for(var i=0;i<60;i++){window.scrollTo(0,document.body.scrollHeight);await new Promise(function(r){setTimeout(r,900);});var h=document.body.scrollHeight;if(h===lastH){sameCount++;if(sameCount>=2)break;}else{sameCount=0;lastH=h;}}
window.scrollTo(0,0);
var urls=Array.from(document.querySelectorAll('a[href*="loom.com/share/"], a[href*="/share/"]')).map(function(a){return a.href;}).filter(function(h){return /loom\\.com\\/share\\/[a-z0-9]+/i.test(h);});
urls=Array.from(new Set(urls));
if(!urls.length){alert('Keine Loom-Share-URLs auf dieser Seite gefunden.');return;}
if(!confirm(urls.length+' eindeutige Aufnahmen gefunden. Alle in die Pipeline einreihen?'))return;
var oki=0,skip=0,fail=0;
for(var k=0;k<urls.length;k++){
  try{
    var r=await fetch(E,{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+T},body:JSON.stringify({url:urls[k]})});
    var j=await r.json();
    if(j.success)oki++;else if(/bereits|duplikat/i.test(j.message||''))skip++;else fail++;
  }catch(e){fail++;}
}
alert('Fertig: '+oki+' eingereiht, '+skip+' uebersprungen, '+fail+' fehlgeschlagen. Status im Thoxan-Jobs-Tab.');
})();`;
        return 'javascript:' + encodeURIComponent(js.replace(/\s+/g,' '));
    }

    function renderBookmarklet() {
        const host = document.getElementById('tr-imp-bm-host');
        if (!activeToken) {
            host.innerHTML = '<span style="color:var(--slate-500);font-size:var(--d-fs-sm);">Bookmarklets werden erstellt sobald ein Token vorhanden ist …</span>';
            return;
        }
        const singleHref = buildSingleBookmarklet(activeToken);
        const libHref    = buildLibraryBookmarklet(activeToken);
        host.innerHTML = `
            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;">
                <div style="flex:1;min-width:260px;">
                    <a class="tr-imp-bookmarklet" href="${singleHref}" onclick="event.preventDefault();alert('Bitte den Knopf in Deine Lesezeichenleiste ZIEHEN — nicht klicken.');">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">bookmark</span>
                        → Loom an Thoxan
                    </a>
                    <div style="margin-top:6px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                        Auf einer einzelnen Loom-Aufnahmen-Seite klicken — reiht <strong>diese eine</strong> URL ein.
                    </div>
                </div>
                <div style="flex:1;min-width:260px;">
                    <a class="tr-imp-bookmarklet" href="${libHref}" onclick="event.preventDefault();alert('Bitte den Knopf in Deine Lesezeichenleiste ZIEHEN — nicht klicken.');">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">playlist_add</span>
                        → Loom-Library scrapen
                    </a>
                    <div style="margin-top:6px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                        Auf <code>loom.com/looms</code> oder einer Folder-Seite klicken — scrollt automatisch durch
                        und reiht <strong>alle gefundenen</strong> Aufnahmen ein. Duplikate werden uebersprungen.
                        Perfekt fuer initialen Backfill.
                    </div>
                </div>
            </div>`;
    }

    window.trTokCreate = async function() {
        const label = document.getElementById('tr-tok-label').value.trim() || 'Bookmarklet';
        try {
            const r = await fetch('/api/v1/admin/transkription/tokens', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},
                body: JSON.stringify({ label }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            activeToken = j.data.token;
            document.getElementById('tr-tok-new').innerHTML = `
                <div class="tr-imp-token">
                    <code id="tr-tok-clear">${esc(j.data.token)}</code>
                    <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('tr-tok-clear').textContent).then(()=>App.showNotification('Kopiert','success'))">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">content_copy</span> Kopieren
                    </button>
                </div>
                <div style="font-size:var(--d-fs-xs);color:var(--amber-700);margin-bottom:8px;">⚠ Dieser Token erscheint nur EINMAL. Bookmarklet darunter ist mit diesem Token bestueckt — sicher in Lesezeichenleiste ziehen.</div>`;
            document.getElementById('tr-tok-label').value = '';
            renderBookmarklet();
            loadList();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trTokDel = async function(id) {
        if (!confirm('Token loeschen? Bookmarklets/Shortcuts mit diesem Token funktionieren danach nicht mehr.')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/tokens/' + id, {
                method:'DELETE', headers:{'X-CSRF-Token':App.csrfToken},
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification('Geloescht','success');
            loadList();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trSdkSave = async function() {
        const v = document.getElementById('tr-sdk-app-id').value.trim();
        try {
            const r = await fetch('/api/v1/admin/transkription/settings', {
                method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},
                body: JSON.stringify({ loom_sdk_public_app_id: v }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            document.getElementById('tr-sdk-status').textContent = '✓ gespeichert';
            App.showNotification('Public App ID gespeichert — Upload-Tab neu laden, um Recorder zu sehen.', 'success');
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    loadList();
})();
</script>
