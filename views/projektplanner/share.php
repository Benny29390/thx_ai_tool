<?php
/**
 * Öffentliche Plan-Ansicht — kein Layout, kein Auth.
 * Daten kommen via /api/v1/public/projektplan/{hash}
 */
$shareHash = $share_hash ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projektplan</title>
    <link rel="stylesheet" href="/assets/css/material-symbols.css">
    <link rel="stylesheet" href="/assets/css/thx-tokens.css">
    <link rel="stylesheet" href="/assets/fonts/lam/frutiger.css">
    <style>
        html { font-size: 100%; }
        body {
            font-family: 'Frutiger LT Std', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            margin: 0; background: var(--slate-50); color: var(--slate-800);
        }
        .ps-container { max-width: 980px; margin: 0 auto; padding: 30px 20px; }

        .ps-header {
            background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
            padding: var(--d-card-pad); margin-bottom: var(--d-section-gap);
            display: flex; align-items: center; gap: var(--d-section-gap);
        }
        .ps-header-logo {
            width: 56px; height: 56px;
            background: var(--thoxan-50); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--thoxan-700); font-weight: 700; font-size: 18px;
            flex-shrink: 0; border: 2px solid var(--thoxan-200);
        }
        .ps-header h1 { margin: 0; font-size: var(--d-fs-2xl); color: var(--slate-900); font-weight: 700; line-height: 1.2; }
        .ps-header .ps-sub {
            color: var(--slate-500); font-size: var(--d-fs-base); margin-top: 4px;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .ps-header .ps-sub .ps-dot { color: var(--slate-300); }

        .ps-table-wrap { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); overflow: hidden; }
        .ps-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-base); }
        .ps-table th {
            background: var(--slate-50); padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x);
            font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--slate-500); border-bottom: 1px solid var(--slate-200);
            text-align: left; font-weight: 600;
        }
        .ps-table th.col-h { text-align: center; width: 70px; }
        .ps-table th.col-resp { width: 100px; }
        .ps-table th.col-deadline { width: 100px; }
        .ps-table th.col-done { width: 30px; text-align: center; }
        .ps-table th.col-asana { width: 28px; text-align: center; }
        .ps-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); vertical-align: top; }
        .ps-table td.col-hours { text-align: center; font-variant-numeric: tabular-nums; }
        .ps-table td.col-done-cell { text-align: center; }
        .ps-table td.col-asana-cell { text-align: center; }
        .ps-asana-link { color: var(--slate-400); display: inline-flex; align-items: center; }
        .ps-asana-link:hover { color: var(--thoxan-600); }

        .ps-row-section td {
            background: var(--thoxan-50); font-weight: 700; font-size: var(--d-fs-base);
            border-top: 3px solid var(--thoxan-200);
            border-bottom: 1px solid var(--thoxan-100);
            padding: 10px 14px; color: var(--thoxan-800);
        }
        .ps-row-note td { font-size: var(--d-fs-sm); color: var(--slate-600); font-style: italic; background: #eff6ff; }
        .ps-row-note a { color: var(--thoxan-600); word-break: break-all; }
        .ps-row-done td { background: rgba(16, 185, 129, 0.05); }

        .ps-kz {
            display: inline-block; padding: 1px 6px; border-radius: var(--d-control-radius);
            background: var(--slate-100); font-size: var(--d-fs-xs); font-weight: 700;
            color: var(--slate-700); margin-right: 3px;
        }
        .ps-sub-row td {
            background: var(--slate-50); border-bottom: 1px solid var(--slate-200);
            font-size: var(--d-fs-sm); color: var(--slate-600); padding: 6px 14px;
        }
        .ps-total-row td {
            background: var(--emerald-50); font-weight: 700; padding: 12px 14px;
            border-top: 2px solid var(--emerald-300);
            border-bottom: 2px solid var(--emerald-300);
            color: var(--emerald-800);
        }
        .ps-ts-row td { background: var(--slate-50); font-weight: 600; color: var(--slate-700); padding: 8px 14px; }

        .ps-footer { margin-top: 24px; color: var(--slate-400); font-size: var(--d-fs-xs); text-align: center; font-style: italic; }

        /* Feedback */
        .ps-fb-toggle {
            display: inline-flex; align-items: center; gap: 3px;
            margin-left: 6px; vertical-align: middle;
            opacity: 0.4; transition: opacity 0.15s;
            cursor: pointer; background: transparent; border: none;
            color: var(--slate-400); font-size: var(--d-fs-base); padding: 1px 3px;
        }
        tr:hover .ps-fb-toggle { opacity: 1; }
        .ps-fb-toggle:hover { color: var(--thoxan-600); }
        .ps-fb-count { font-size: var(--d-fs-xs); font-weight: 700; color: var(--thoxan-600); margin-left: 1px; }

        .ps-fb-list { margin-top: 6px; }
        .ps-fb-item {
            font-size: var(--d-fs-sm); color: var(--slate-700);
            background: var(--slate-50); padding: 6px 10px; border-radius: var(--d-control-radius);
            margin-top: 4px; border-left: 2px solid var(--slate-300);
            display: flex; gap: 8px; align-items: flex-start;
        }
        .ps-fb-item-body { flex: 1; }
        .ps-fb-author { font-weight: 600; color: var(--slate-800); }
        .ps-fb-time { color: var(--slate-400); font-size: var(--d-fs-xs); margin-left: 4px; }
        .ps-fb-text { display: block; margin-top: 2px; }
        .ps-fb-actions { opacity: 0; transition: opacity 0.15s; display: flex; gap: 2px; }
        .ps-fb-item:hover .ps-fb-actions { opacity: 1; }
        .ps-fb-actions button { background: none; border: none; color: var(--slate-400); cursor: pointer; padding: 1px 3px; font-size: var(--d-fs-sm); }
        .ps-fb-actions button:hover { color: var(--slate-700); }

        .ps-fb-input { display: none; margin-top: 6px; background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-control-radius); padding: 6px; }
        .ps-fb-input.is-open { display: block; }
        .ps-fb-input-row { display: flex; gap: 4px; align-items: center; }
        .ps-fb-input-row input.ps-fb-name { flex: 0 0 120px; padding: 5px 8px; border: 1px solid var(--slate-200); border-radius: var(--d-control-radius); font-size: var(--d-fs-sm); }
        .ps-fb-input-row textarea.ps-fb-msg {
            flex: 1; padding: 5px 8px; border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
            font-size: var(--d-fs-sm); resize: none; height: 28px; font-family: inherit;
        }
        .ps-fb-input-row button {
            background: var(--thoxan-600); color: #fff; border: none;
            border-radius: var(--d-control-radius); padding: 5px 10px; cursor: pointer; font-size: var(--d-fs-base);
        }

        /* Password Gate */
        .ps-pw-gate {
            max-width: 380px; margin: 80px auto;
            background: #fff; border: 1px solid var(--slate-200);
            border-radius: var(--d-card-radius); padding: 28px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
        }
        .ps-pw-gate h2 { margin: 0 0 4px; font-size: var(--d-fs-lg); color: var(--slate-800); }
        .ps-pw-gate p { color: var(--slate-500); font-size: var(--d-fs-sm); margin: 0 0 16px; }
        .ps-pw-gate input {
            width: 100%; padding: 10px 12px;
            border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
            font-size: var(--d-fs-base); margin-bottom: 8px; box-sizing: border-box;
        }
        .ps-pw-gate button {
            width: 100%; padding: 10px;
            background: var(--thoxan-600); color: #fff; border: none; border-radius: var(--d-control-radius);
            font-size: var(--d-fs-base); cursor: pointer; font-weight: 600;
        }
        .ps-pw-error { color: var(--rose-600); font-size: var(--d-fs-sm); margin-bottom: 8px; }

        .ps-empty { padding: 40px; text-align: center; color: var(--slate-500); }

        @media print {
            .ps-fb-toggle, .ps-fb-input, .ps-fb-list { display: none !important; }
            body { background: #fff; }
            .ps-header, .ps-table-wrap { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

<div id="ps-root" class="ps-container">
    <div style="text-align:center;padding:60px;color:var(--slate-400);">Lädt…</div>
</div>

<script>
'use strict';
const psState = {
    hash: <?= json_encode($shareHash) ?>,
    plan: null, rows: [], feedback: [], team: [], teamByName: {},
    authorName: localStorage.getItem('pp_share_author') || '',
};

function psEsc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
// Text HTML-sicher ausgeben und enthaltene URLs klickbar machen (auch mitten im Text).
function psLinkify(text) {
    return String(text == null ? '' : text)
        .split(/(https?:\/\/[^\s<]+)/g)
        .map((p, i) => {
            if (i % 2 === 1) {
                const u = psEsc(p);
                return `<a href="${u}" target="_blank" rel="noopener">${u}</a>`;
            }
            return psEsc(p).replace(/\n/g, '<br>');
        })
        .join('');
}
function psKz(name) {
    if (!name) return '?';
    const t = psState.teamByName[name.trim().toLowerCase()];
    if (t && t.abbreviation) return t.abbreviation;
    return name.trim().slice(0, 3).toUpperCase();
}
function psFmtDate(s) {
    if (!s) return '';
    return new Date(s).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function psFmtRelative(s) {
    if (!s) return '';
    return new Date(s).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function psLoad() {
    try {
        const r = await fetch('/api/v1/public/projektplan/' + encodeURIComponent(psState.hash));
        const j = await r.json();
        if (!j.success) {
            if (r.status === 403 && (j.message || '').toLowerCase().includes('passwort')) { psRenderPwGate(); return; }
            throw new Error(j.message || 'Fehler');
        }
        psState.plan = j.data.plan;
        psState.rows = j.data.rows || [];
        psState.feedback = j.data.feedback || [];
        psState.team = j.data.team || [];
        psState.teamByName = {};
        psState.team.forEach(t => { if (t.name) psState.teamByName[t.name.toLowerCase()] = t; });
        psRender();
    } catch (e) {
        document.getElementById('ps-root').innerHTML = '<div class="ps-empty">' + psEsc(e.message) + '</div>';
    }
}

function psRenderPwGate(errorMsg) {
    document.getElementById('ps-root').innerHTML = `
        <div class="ps-pw-gate">
            <h2>Projektplan</h2>
            <p>Dieser Plan ist passwortgeschützt.</p>
            ${errorMsg ? `<div class="ps-pw-error">${psEsc(errorMsg)}</div>` : ''}
            <input type="password" id="ps-pw-input" placeholder="Passwort eingeben" autofocus>
            <button onclick="psSubmitPw()">Anzeigen</button>
        </div>`;
    document.getElementById('ps-pw-input').addEventListener('keydown', e => {
        if (e.key === 'Enter') psSubmitPw();
    });
}
async function psSubmitPw() {
    const pw = document.getElementById('ps-pw-input').value;
    try {
        const r = await fetch('/api/v1/public/projektplan/' + encodeURIComponent(psState.hash) + '/auth', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pw }),
        });
        const j = await r.json();
        if (!j.success) { psRenderPwGate('Falsches Passwort.'); return; }
        psLoad();
    } catch (e) { psRenderPwGate(e.message); }
}

function psSectionHasItems(rows, secIdx) {
    for (let i = secIdx + 1; i < rows.length; i++) {
        if (rows[i].row_type === 'section') return false;
        if (rows[i].row_type === 'item' && !parseInt(rows[i].is_placeholder)) return true;
    }
    return false;
}

function psRender() {
    const p = psState.plan;
    const fbByRow = {};
    psState.feedback.forEach(f => {
        const k = f.row_id || 0;
        if (!fbByRow[k]) fbByRow[k] = [];
        fbByRow[k].push(f);
    });

    const visibleSection = new Set();
    psState.rows.forEach((r, idx) => {
        if (r.row_type === 'section' && psSectionHasItems(psState.rows, idx)) visibleSection.add(idx);
    });

    const period = (p.period_from && p.period_to)
        ? psFmtDate(p.period_from) + ' – ' + psFmtDate(p.period_to)
        : '';

    let html = `
        <div class="ps-header">
            <div class="ps-header-logo">${psEsc(p.customer_abbr || (p.customer_name ? p.customer_name.slice(0, 2).toUpperCase() : 'TX'))}</div>
            <div style="flex:1;">
                <h1>${psEsc(p.customer_name || 'Projektplan')}</h1>
                <div class="ps-sub">
                    <span>${psEsc(p.title)}</span>
                    ${period ? `<span class="ps-dot">·</span><span>${period}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="ps-table-wrap">
            <table class="ps-table">
                <thead><tr>
                    <th>Beschreibung</th>
                    <th class="col-h">Aufwand (h)</th>
                    <th class="col-resp">Umsetzung</th>
                    <th class="col-deadline">Umgesetzt</th>
                    <th class="col-done"></th>
                    <th class="col-asana"></th>
                </tr></thead>
                <tbody>`;

    let secOpen = false, secSoll = 0, totSoll = 0;
    let inEmptySection = false;
    // Stunden im Viertelstunden-Takt sauber anzeigen (0,25 / 0,5 / 0,75 / 1 …), KEIN toFixed(1) das 0,75→0,8 rundet.
    const fmtH = (n) => String(Math.round((parseFloat(n) || 0) * 100) / 100).replace('.', ',');

    psState.rows.forEach((r, idx) => {
        if (r.row_type === 'spacer') return;
        if (r.row_type === 'item' && parseInt(r.is_placeholder)) return;
        if (r.row_type === 'section') {
            if (!visibleSection.has(idx)) { inEmptySection = true; return; }
            inEmptySection = false;
        }
        if (inEmptySection && r.row_type === 'note') return;

        const fbs = fbByRow[r.id] || [];
        const comments = fbs.filter(f => f.feedback_type === 'comment');
        const commentCount = comments.length;

        if (r.row_type === 'section') {
            if (secOpen) {
                html += `<tr class="ps-sub-row"><td style="text-align:right;">Aufwand ca. in Std.</td><td class="col-hours">${fmtH(secSoll)}</td><td></td><td></td><td></td><td></td></tr>`;
            }
            secOpen = true; secSoll = 0;
            html += `<tr class="ps-row-section"><td colspan="6">${psEsc(r.description || '')}</td></tr>`;
        } else if (r.row_type === 'note') {
            // Notiz-Text kann in description ODER notes stehen; beide ausgeben, URLs klickbar.
            const noteText = [r.description, r.notes].filter(s => s && String(s).trim()).join('\n');
            html += `<tr class="ps-row-note"><td colspan="6">${psLinkify(noteText)}</td></tr>`;
        } else {
            const soll = parseFloat(r.planned_hours) || 0;
            secSoll += soll; totSoll += soll;
            const done = parseInt(r.is_done);

            const suffix = [];
            if (r.timeframe) suffix.push(psEsc(r.timeframe));
            const seenKz = new Set();
            const lead = (r.lead_responsible || '').trim();
            if (lead) {
                const kz = psKz(lead); seenKz.add(kz); suffix.push(kz);
            }
            (r.responsible || '').split(',').forEach(rn => {
                const n = rn.trim(); if (!n) return;
                const kz = psKz(n);
                if (!seenKz.has(kz)) { seenKz.add(kz); suffix.push(kz); }
            });

            const desc = psEsc(r.description || '') + (suffix.length ? ` <span style="color:var(--slate-400);">(${suffix.join(', ')})</span>` : '');

            // Umsetzung: LEAD-Kuerzel zuerst, dann TEAM-Kuerzel (dedupliziert). Leere Werte werden uebersprungen.
            const respKzSeen = new Set();
            const respKzList = [];
            if (lead) { const k = psKz(lead); if (!respKzSeen.has(k)) { respKzSeen.add(k); respKzList.push(k); } }
            (r.responsible || '').split(',').map(n => n.trim()).filter(Boolean).forEach(n => {
                const k = psKz(n); if (!respKzSeen.has(k)) { respKzSeen.add(k); respKzList.push(k); }
            });
            const respKzHtml = respKzList.map(k => `<span class="ps-kz">${psEsc(k)}</span>`).join('');

            html += `
            <tr class="${done ? 'ps-row-done' : ''}" data-rowid="${r.id}">
                <td>
                    ${desc}
                    ${(() => {
                        const likes = fbs.filter(f => f.feedback_type === 'like').length;
                        const dislikes = fbs.filter(f => f.feedback_type === 'dislike').length;
                        const myReact = psState.authorName ? fbs.find(f => (f.feedback_type === 'like' || f.feedback_type === 'dislike') && (f.author_name || '').toLowerCase() === psState.authorName.toLowerCase()) : null;
                        return `
                        <button class="ps-fb-toggle" onclick="psReact(${r.id}, 'like')" title="Daumen hoch" style="${myReact && myReact.feedback_type === 'like' ? 'color:var(--emerald-600);opacity:1;' : ''}">
                            👍${likes > 0 ? `<span class="ps-fb-count">${likes}</span>` : ''}
                        </button>
                        <button class="ps-fb-toggle" onclick="psReact(${r.id}, 'dislike')" title="Daumen runter" style="${myReact && myReact.feedback_type === 'dislike' ? 'color:var(--rose-600);opacity:1;' : ''}">
                            👎${dislikes > 0 ? `<span class="ps-fb-count">${dislikes}</span>` : ''}
                        </button>`;
                    })()}
                    <button class="ps-fb-toggle" onclick="psToggleFb(${r.id})" title="Kommentar">
                        💬${commentCount > 0 ? `<span class="ps-fb-count">${commentCount}</span>` : ''}
                    </button>
                    <div class="ps-fb-list" id="ps-fb-list-${r.id}">${
                        comments.map(c => psRenderComment(c, r.id)).join('')
                    }</div>
                    <div class="ps-fb-input" id="ps-fb-input-${r.id}">
                        <div class="ps-fb-input-row">
                            <input type="text" class="ps-fb-name" id="ps-fb-name-${r.id}" placeholder="Dein Name" value="${psEsc(psState.authorName)}">
                            <textarea class="ps-fb-msg" id="ps-fb-msg-${r.id}" placeholder="Kommentar…"></textarea>
                            <button onclick="psSendFb(${r.id})" title="Senden">➤</button>
                        </div>
                    </div>
                </td>
                <td class="col-hours">${soll > 0 ? fmtH(soll) : ''}</td>
                <td>${respKzHtml}</td>
                <td style="font-size:12px;color:var(--slate-500);">${psEsc(r.deadline || '')}</td>
                <td class="col-done-cell">${done ? '<span style="color:var(--emerald-600);">✓</span>' : ''}</td>
                <td class="col-asana-cell">${r.asana_url ? `<a class="ps-asana-link" href="${psEsc(r.asana_url)}" target="_blank" rel="noopener" title="${psEsc(r.asana_task_name || 'Aufgabe')} in Asana öffnen"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a>` : ''}</td>
            </tr>`;
        }
    });

    if (secOpen) {
        html += `<tr class="ps-sub-row"><td style="text-align:right;">Aufwand ca. in Std.</td><td class="col-hours">${fmtH(secSoll)}</td><td></td><td></td><td></td><td></td></tr>`;
    }
    if (totSoll > 0) {
        const fullDays = Math.floor(totSoll / 8);
        const rest = totSoll - fullDays * 8;
        const ts = fullDays + (rest >= 4 ? 0.5 : 0);
        html += `
            <tr><td colspan="6" style="height:8px;"></td></tr>
            <tr class="ps-total-row"><td>Aufwand ca. insgesamt</td><td class="col-hours">${fmtH(totSoll)} h</td><td colspan="4"></td></tr>
            <tr class="ps-ts-row"><td>Aufwand ca. in Tagessätzen</td><td class="col-hours">${ts.toFixed(1).replace('.', ',')} TS</td><td colspan="4"></td></tr>`;
    }

    html += `</tbody></table></div>
        <div class="ps-footer">erstellt mit Thoxan</div>`;
    document.getElementById('ps-root').innerHTML = html;
}

function psRenderComment(c, rowId) {
    const isMine = psState.authorName && psState.authorName.toLowerCase() === (c.author_name || '').toLowerCase();
    return `<div class="ps-fb-item" data-fbid="${c.id}">
        <div class="ps-fb-item-body">
            <span class="ps-fb-author">${psEsc(c.author_name || 'Anonym')}</span><span class="ps-fb-time">${psFmtRelative(c.created_at)}</span>
            <span class="ps-fb-text" id="ps-fb-text-${c.id}">${psEsc(c.message || '').replace(/\n/g, '<br>')}</span>
        </div>
        ${isMine ? `<div class="ps-fb-actions">
            <button title="Bearbeiten" onclick="psEditFb(${c.id}, ${rowId})">✎</button>
            <button title="Löschen" onclick="psDeleteFb(${c.id}, ${rowId})">×</button>
        </div>` : ''}
    </div>`;
}

function psToggleFb(rowId) {
    const el = document.getElementById('ps-fb-input-' + rowId);
    el.classList.toggle('is-open');
    if (el.classList.contains('is-open')) {
        setTimeout(() => document.getElementById('ps-fb-msg-' + rowId).focus(), 50);
    }
}

async function psReact(rowId, type) {
    let name = psState.authorName;
    if (!name) {
        name = prompt('Dein Name für Like/Dislike:');
        if (!name) return;
        name = name.trim();
        if (!name) return;
        psState.authorName = name;
        localStorage.setItem('pp_share_author', name);
    }
    try {
        const r = await fetch('/api/v1/public/projektplan/' + encodeURIComponent(psState.hash) + '/feedback', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ row_id: rowId, author_name: name, feedback_type: type }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        psLoad();
    } catch (e) { alert(e.message); }
}

async function psSendFb(rowId) {
    const name = document.getElementById('ps-fb-name-' + rowId).value.trim() || 'Anonym';
    const msg = document.getElementById('ps-fb-msg-' + rowId).value.trim();
    if (!msg) return;
    psState.authorName = name;
    localStorage.setItem('pp_share_author', name);
    try {
        const r = await fetch('/api/v1/public/projektplan/' + encodeURIComponent(psState.hash) + '/feedback', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ row_id: rowId, author_name: name, feedback_type: 'comment', message: msg }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        document.getElementById('ps-fb-msg-' + rowId).value = '';
        psLoad();
    } catch (e) { alert(e.message); }
}

async function psDeleteFb(id) {
    if (!confirm('Kommentar wirklich löschen?')) return;
    try {
        const r = await fetch(`/api/v1/public/projektplan/${encodeURIComponent(psState.hash)}/feedback/${id}?author=${encodeURIComponent(psState.authorName)}`, { method: 'DELETE' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        psLoad();
    } catch (e) { alert(e.message); }
}

function psEditFb(id) {
    const span = document.getElementById('ps-fb-text-' + id);
    const oldText = span.textContent;
    const ta = document.createElement('textarea');
    ta.value = oldText;
    ta.style.cssText = 'width:100%;padding:4px;border:1px solid var(--slate-300);border-radius:4px;font-family:inherit;font-size:12px;';
    span.replaceWith(ta);
    ta.focus();
    ta.addEventListener('blur', async () => {
        const newText = ta.value.trim();
        if (newText && newText !== oldText) {
            try {
                await fetch(`/api/v1/public/projektplan/${encodeURIComponent(psState.hash)}/feedback/${id}`, {
                    method: 'PUT', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ author_name: psState.authorName, message: newText }),
                });
                psLoad();
            } catch (e) { alert(e.message); psLoad(); }
        } else psLoad();
    });
    ta.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ta.blur(); }
        if (e.key === 'Escape') { psLoad(); }
    });
}

document.addEventListener('DOMContentLoaded', psLoad);
</script>
</body>
</html>
