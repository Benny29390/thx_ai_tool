<?php /** @var array $title */ ?>
<style>
.fw-info {
    background: var(--thoxan-50); border: 1px solid var(--thoxan-200); border-radius: 8px;
    padding: 10px 14px; margin-bottom: 14px;
    display: flex; align-items: flex-start; gap: 10px; font-size: var(--d-fs-sm); color: var(--slate-700);
}
.fw-info .material-symbols-rounded { color: var(--thoxan-600); font-size: 18px; flex-shrink: 0; }

.fw-myip {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 12px 16px; margin-bottom: 14px;
}
.fw-myip .ip { font-family: ui-monospace, monospace; font-weight: 700; font-size: var(--d-fs-base); color: var(--slate-800); }
.fw-myip.is-banned { border-color: var(--rose-300); background: rgba(244,63,94,0.05); }

.fw-jails { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.fw-jail-chip {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 8px;
    padding: 8px 12px; font-size: var(--d-fs-xs); display: flex; flex-direction: column; gap: 2px;
}
.fw-jail-chip .name { font-weight: 700; color: var(--slate-700); }
.fw-jail-chip .cnt { font-family: ui-monospace, monospace; color: var(--slate-500); }
.fw-jail-chip.has-bans .cnt { color: var(--rose-600); font-weight: 700; }

.fw-toolbar {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 10px 14px; margin-bottom: 14px;
}
.fw-toolbar input[type=text] {
    flex: 1; min-width: 200px; padding: 6px 10px; border: 1px solid var(--slate-200);
    border-radius: 6px; font-size: var(--d-fs-sm); background: var(--slate-50); font-family: ui-monospace, monospace;
}
.fw-toolbar input[type=text]:focus { outline: none; border-color: var(--thoxan-400); background: #fff; }

.fw-table { background: #fff; border: 1px solid var(--slate-200); border-radius: 10px; overflow: hidden; }
.fw-table table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); }
.fw-table th {
    text-align: left; padding: 10px 14px; font-size: 10px; text-transform: uppercase;
    color: var(--slate-500); font-weight: 600; border-bottom: 2px solid var(--slate-200); background: var(--slate-50);
}
.fw-table td { padding: 10px 14px; border-bottom: 1px solid var(--slate-100); }
.fw-table tr:hover td { background: var(--slate-50); }
.fw-table td.ip { font-family: ui-monospace, monospace; font-weight: 600; color: var(--slate-800); }
.fw-table .jail-tag {
    display: inline-block; padding: 1px 7px; border-radius: 10px; margin: 1px;
    font-size: 10px; font-weight: 600; background: var(--slate-100); color: var(--slate-600);
}
.fw-table tr.is-me td { background: rgba(244,63,94,0.06); }
.fw-table tr.is-me td.ip::after { content: " (Deine IP)"; color: var(--rose-600); font-size: 11px; font-weight: 600; }

.fw-empty { text-align: center; padding: 50px 20px; color: var(--slate-400); }
.fw-ufw {
    margin-top: 18px; background: var(--slate-900); color: var(--slate-200);
    font-family: ui-monospace, monospace; font-size: 12px; padding: 14px; border-radius: 8px;
    max-height: 320px; overflow: auto; white-space: pre; line-height: 1.5;
}
@keyframes fw-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
</style>

<div>
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">
                <span class="material-symbols-rounded" style="vertical-align:middle;font-size:24px;color:var(--thoxan-600);">security</span>
                Firewall / IP-Sperren
            </h1>
            <p class="thx-page-subtitle">Von fail2ban gesperrte IP-Adressen ansehen und entsperren. Entsperren entfernt die Sperre aus allen Bereichen (Jails) gleichzeitig.</p>
        </div>
        <div class="thx-page-actions">
            <span id="fw-age" style="align-self:center;font-size:var(--d-fs-xs);color:var(--slate-400);"></span>
            <button class="thx-btn thx-btn-secondary" onclick="fwLoad()">
                <span class="material-symbols-rounded" style="font-size:16px;">refresh</span>
                Aktualisieren
            </button>
        </div>
    </div>

    <div class="fw-info">
        <span class="material-symbols-rounded">info</span>
        <span>fail2ban sperrt IP-Adressen automatisch nach zu vielen Fehlversuchen (z.B. Login, 404-Fluten). Wurde jemand zu Unrecht gesperrt, kannst Du die IP hier mit einem Klick wieder freigeben. <strong>Deine eigene IP</strong> ist oben markiert, damit Du Dich nicht versehentlich aussperrst. Die Spalte <strong>„Laeuft ab"</strong> zeigt, wann fail2ban die Sperre von selbst wieder aufhebt. Das Land ist eine grobe Schaetzung aus einer lokalen GeoIP-Datenbank.</span>
    </div>

    <div id="fw-loading" style="text-align:center;padding:40px;color:var(--slate-400);">
        <span class="material-symbols-rounded" style="font-size:24px;animation:fw-spin 1s linear infinite;">refresh</span>
        Lade…
    </div>

    <div id="fw-content" style="display:none;">
        <div class="fw-myip" id="fw-myip"></div>
        <div class="fw-jails" id="fw-jails"></div>

        <div class="fw-toolbar">
            <input type="text" id="fw-search" placeholder="IP suchen…" oninput="fwRender()">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);border-left:1px solid var(--slate-200);padding-left:10px;">IP manuell entsperren:</span>
            <input type="text" id="fw-manual-ip" placeholder="z.B. 203.0.113.5" style="flex:0 1 220px;" onkeydown="if(event.key==='Enter')fwUnbanManual()">
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="fwUnbanManual()">
                <span class="material-symbols-rounded" style="font-size:14px;">lock_open</span>
                Entsperren
            </button>
        </div>

        <div id="fw-table-wrap"></div>

        <div id="fw-ufw-wrap" style="display:none;">
            <div style="margin-top:18px;display:flex;align-items:center;gap:8px;">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="fwToggleUfw()">
                    <span class="material-symbols-rounded" style="font-size:14px;">terminal</span>
                    <span id="fw-ufw-toggle-label">ufw-Regeln anzeigen</span>
                </button>
                <span style="font-size:var(--d-fs-xs);color:var(--slate-500);">Rohe Firewall-Regeln (nur zur Ansicht)</span>
            </div>
            <pre class="fw-ufw" id="fw-ufw" style="display:none;"></pre>
        </div>
    </div>

    <div id="fw-unavailable" style="display:none;">
        <div class="fw-empty">
            <span class="material-symbols-rounded" style="font-size:40px;color:var(--amber-400);">hourglass_empty</span>
            <p style="margin-top:8px;font-weight:600;color:var(--slate-600);">Noch keine Daten vorhanden.</p>
            <p style="font-size:var(--d-fs-sm);">Der Hintergrund-Job hat den aktuellen Stand noch nicht geschrieben. Er laeuft jede Minute — bitte kurz warten und auf „Aktualisieren" klicken.<br>Falls dauerhaft nichts erscheint, pruefe den Cron <code>/etc/cron.d/ki-tool-firewall</code> und das Log <code>/var/log/ki-tool-firewall.log</code>.</p>
        </div>
    </div>
</div>

<script>
'use strict';
const fwState = { banned: [], jails: [], myIp: null, ufw: [], pending: new Set() };

function fwFmtAge(sec) {
    if (sec == null) return '';
    if (sec < 60) return 'vor ' + sec + ' Sek.';
    if (sec < 3600) return 'vor ' + Math.floor(sec/60) + ' Min.';
    return 'vor ' + Math.floor(sec/3600) + ' Std.';
}

// "2026-06-10 06:16:19" -> "10.06. 06:16"
function fwDate(s) {
    if (!s) return '—';
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
    return m ? `${m[3]}.${m[2]}. ${m[4]}:${m[5]}` : s;
}

// Laender-Code (z.B. "DE") -> Flaggen-Emoji 🇩🇪
function fwFlag(code) {
    if (!code || code.length !== 2) return '';
    return String.fromCodePoint(...[...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
}

function fwEsc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function fwLoad() {
    document.getElementById('fw-loading').style.display = '';
    document.getElementById('fw-content').style.display = 'none';
    document.getElementById('fw-unavailable').style.display = 'none';
    try {
        const r = await fetch('/api/v1/admin/firewall');
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler beim Laden');
        const d = j.data;
        document.getElementById('fw-loading').style.display = 'none';

        if (!d.available) {
            document.getElementById('fw-unavailable').style.display = '';
            document.getElementById('fw-age').textContent = '';
            return;
        }
        fwState.banned = d.banned || [];
        fwState.jails = d.jails || [];
        fwState.myIp = d.my_ip || null;
        fwState.ufw = d.ufw || [];
        fwState.pending = new Set(d.pending || []);

        document.getElementById('fw-age').textContent = d.snapshot_age != null ? 'Stand: ' + fwFmtAge(d.snapshot_age) : '';
        document.getElementById('fw-content').style.display = '';
        fwRenderMyIp(d.my_ip, d.my_ip_banned);
        fwRenderJails();
        fwRender();
        fwRenderUfw();
    } catch (e) {
        document.getElementById('fw-loading').style.display = 'none';
        document.getElementById('fw-unavailable').style.display = '';
        App.showNotification(e.message, 'error');
    }
}

function fwRenderMyIp(ip, banned) {
    const box = document.getElementById('fw-myip');
    box.classList.toggle('is-banned', !!banned);
    box.innerHTML = `
        <span class="material-symbols-rounded" style="color:${banned ? 'var(--rose-600)' : 'var(--thoxan-600)'};">${banned ? 'warning' : 'my_location'}</span>
        <span>Deine aktuelle IP-Adresse:</span>
        <span class="ip">${fwEsc(ip || 'unbekannt')}</span>
        ${banned
            ? (fwState.pending.has(ip)
                ? `<span style="color:var(--amber-600);font-weight:600;font-size:var(--d-fs-sm);margin-left:auto;"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;animation:fw-spin 1s linear infinite;">progress_activity</span> wird entsperrt…</span>`
                : `<span style="color:var(--rose-700);font-weight:600;font-size:var(--d-fs-sm);">⚠ Diese IP ist aktuell gesperrt! Du solltest sie entsperren.</span>
                   <button class="thx-btn thx-btn-primary thx-btn-small" style="margin-left:auto;" onclick="fwUnban('${fwEsc(ip)}')">Jetzt entsperren</button>`)
            : `<span style="color:var(--emerald-700);font-size:var(--d-fs-sm);">✓ nicht gesperrt</span>`}
    `;
}

function fwRenderJails() {
    const wrap = document.getElementById('fw-jails');
    wrap.innerHTML = fwState.jails.map(j => `
        <div class="fw-jail-chip ${j.currently_banned > 0 ? 'has-bans' : ''}">
            <span class="name">${fwEsc(j.name)}</span>
            <span class="cnt">${j.currently_banned} gesperrt</span>
        </div>
    `).join('');
}

function fwFiltered() {
    const q = (document.getElementById('fw-search').value || '').toLowerCase().trim();
    if (!q) return fwState.banned;
    return fwState.banned.filter(b =>
        b.ip.toLowerCase().includes(q) ||
        (b.country || '').toLowerCase().includes(q) ||
        (b.country_code || '').toLowerCase().includes(q)
    );
}

function fwRender() {
    const list = fwFiltered();
    const wrap = document.getElementById('fw-table-wrap');
    if (!fwState.banned.length) {
        wrap.innerHTML = `<div class="fw-empty"><span class="material-symbols-rounded" style="font-size:40px;color:var(--emerald-400);">verified_user</span><p style="margin-top:8px;color:var(--slate-500);">Aktuell sind keine IP-Adressen gesperrt.</p></div>`;
        return;
    }
    if (!list.length) {
        wrap.innerHTML = `<div class="fw-empty">Keine IP entspricht der Suche.</div>`;
        return;
    }
    wrap.innerHTML = `
    <div class="fw-table"><table>
        <thead><tr>
            <th style="width:170px;">IP-Adresse</th>
            <th style="width:150px;">Land</th>
            <th style="width:110px;">Gesperrt</th>
            <th style="width:110px;">Laeuft ab</th>
            <th>Gesperrt in (Jails)</th>
            <th style="width:140px;text-align:right;">Aktion</th>
        </tr></thead>
        <tbody>
        ${list.map(b => `
            <tr class="${b.ip === fwState.myIp ? 'is-me' : ''}">
                <td class="ip">${fwEsc(b.ip)}</td>
                <td>${b.country_code ? `${fwFlag(b.country_code)} ${fwEsc(b.country || b.country_code)}` : '<span style="color:var(--slate-400);">unbekannt</span>'}</td>
                <td style="color:var(--slate-600);white-space:nowrap;">${fwDate(b.banned_at)}</td>
                <td style="color:var(--slate-500);white-space:nowrap;">${fwDate(b.ban_expires)}</td>
                <td>${(b.jails || []).map(j => `<span class="jail-tag">${fwEsc(j)}</span>`).join(' ')}</td>
                <td style="text-align:right;">
                    ${fwState.pending.has(b.ip)
                        ? `<span style="font-size:var(--d-fs-xs);color:var(--amber-600);font-weight:600;"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;animation:fw-spin 1s linear infinite;">progress_activity</span> wird entsperrt…</span>`
                        : `<button class="thx-btn thx-btn-secondary thx-btn-small" onclick="fwUnban('${fwEsc(b.ip)}')">
                            <span class="material-symbols-rounded" style="font-size:14px;">lock_open</span>
                            Entsperren
                        </button>`}
                </td>
            </tr>
        `).join('')}
        </tbody>
    </table></div>
    <div style="font-size:var(--d-fs-xs);color:var(--slate-400);margin-top:8px;">${list.length} von ${fwState.banned.length} gesperrten IP-Adressen</div>`;
}

async function fwUnban(ip) {
    if (!ip) return;
    if (!confirm('IP ' + ip + ' entsperren?\nDie Sperre wird aus allen Bereichen entfernt (innerhalb ~1 Minute).')) return;
    try {
        const r = await fetch('/api/v1/admin/firewall/unban', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ ip }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message || 'Entsperr-Auftrag angelegt', 'success');
        // Sofortiges Feedback: IP als "wird entsperrt" markieren
        fwState.pending.add(ip);
        fwRender();
        fwRenderMyIp(fwState.myIp, fwState.banned.some(b => b.ip === fwState.myIp));
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function fwUnbanManual() {
    const ip = (document.getElementById('fw-manual-ip').value || '').trim();
    if (!ip) { App.showNotification('Bitte eine IP eingeben.', 'error'); return; }
    fwUnban(ip);
    document.getElementById('fw-manual-ip').value = '';
}

function fwRenderUfw() {
    const wrap = document.getElementById('fw-ufw-wrap');
    if (!fwState.ufw.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    document.getElementById('fw-ufw').textContent = fwState.ufw.join('\n');
}

function fwToggleUfw() {
    const pre = document.getElementById('fw-ufw');
    const open = pre.style.display === 'none';
    pre.style.display = open ? 'block' : 'none';
    document.getElementById('fw-ufw-toggle-label').textContent = open ? 'ufw-Regeln ausblenden' : 'ufw-Regeln anzeigen';
}

document.addEventListener('DOMContentLoaded', fwLoad);
</script>
