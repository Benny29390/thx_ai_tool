<?php
/**
 * Öffentliche Multi-Plan-Übersicht — kein Auth.
 * Daten kommen via /api/v1/public/projektplan-uebersicht/{hash}
 */
$shareHash = $share_hash ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projektübersicht</title>
    <link rel="stylesheet" href="/assets/css/material-symbols.css">
    <link rel="stylesheet" href="/assets/css/thx-tokens.css">
    <link rel="stylesheet" href="/assets/fonts/lam/frutiger.css">
    <style>
        html { font-size: 100%; }
        body {
            font-family: 'Frutiger LT Std', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            margin: 0; background: var(--slate-50); color: var(--slate-800);
        }
        .mp { max-width: 1280px; margin: 0 auto; padding: 28px 22px; }
        .mp-header {
            background: #fff; border: 1px solid var(--slate-200); border-radius: 8px;
            padding: 20px 24px; margin-bottom: 18px;
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        }
        .mp-title { font-size: 1.35rem; font-weight: 700; color: var(--slate-900); flex: 1; min-width: 200px; margin: 0; }
        .mp-meta { font-size: 0.82rem; color: var(--slate-500); display: flex; gap: 14px; flex-wrap: wrap; }
        .mp-meta strong { color: var(--slate-700); font-weight: 600; }
        .mp-filters {
            background: var(--amber-50); border: 1px solid var(--amber-200); border-radius: 8px;
            padding: 10px 16px; margin-bottom: 14px;
            font-size: 0.8rem; color: var(--amber-900);
            display: flex; gap: 16px; flex-wrap: wrap; align-items: center;
        }
        .mp-filter-pill {
            background: var(--amber-100); padding: 3px 10px; border-radius: 12px; font-weight: 600;
        }
        .mp-plan-card {
            background: #fff; border: 1px solid var(--slate-200); border-radius: 8px;
            margin-bottom: 14px; overflow: hidden;
        }
        .mp-plan-head {
            padding: 12px 16px; background: var(--slate-50); border-bottom: 1px solid var(--slate-200);
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .mp-plan-title { font-weight: 700; color: var(--slate-900); }
        .mp-plan-sub { font-size: 0.78rem; color: var(--slate-500); }
        .mp-plan-status {
            font-size: 0.7rem; text-transform: uppercase; font-weight: 700; padding: 2px 8px; border-radius: 4px;
            background: var(--emerald-50); color: var(--emerald-700);
        }
        .mp-plan-status.archiviert { background: var(--slate-100); color: var(--slate-500); }
        .mp-plan-status.entwurf { background: var(--slate-100); color: var(--slate-600); }
        .mp-plan-status.einzelprojekt { background: rgba(168, 85, 247, 0.12); color: #7e22ce; }
        .mp-plan-counts { margin-left: auto; font-size: 0.78rem; color: var(--slate-600); display: flex; gap: 12px; }
        table.mp-rows {
            width: 100%; border-collapse: collapse; font-size: 0.85rem;
        }
        table.mp-rows th {
            background: #fff; border-bottom: 2px solid var(--slate-200);
            text-align: left; padding: 8px 10px; font-weight: 600; color: var(--slate-600); font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        table.mp-rows td {
            padding: 8px 10px; border-bottom: 1px solid var(--slate-100);
            vertical-align: top;
        }
        table.mp-rows tr.is-section td {
            background: var(--slate-50); font-weight: 600; color: var(--slate-700); font-size: 0.78rem;
        }
        table.mp-rows tr.is-done td { color: var(--slate-500); }
        table.mp-rows tr.is-done .mp-rows-desc { text-decoration: line-through; }
        .mp-empty { text-align: center; padding: 40px; color: var(--slate-400); }
        .mp-resp { display: inline-block; padding: 1px 8px; background: var(--slate-100); color: var(--slate-700); border-radius: 10px; font-size: 0.72rem; font-weight: 600; }
        .mp-resp.lead { background: var(--thoxan-50); color: var(--thoxan-700); }
        .mp-done-icon { color: var(--emerald-600); font-size: 14px; }
        .mp-placeholder { opacity: 0.5; font-style: italic; }
        .mp-error { color: var(--rose-600); padding: 30px; text-align: center; }
        .mp-loading { padding: 40px; text-align: center; color: var(--slate-400); }
        .mp-foot { margin-top: 24px; padding: 14px; text-align: center; font-size: 0.75rem; color: var(--slate-400); }
        .mp-zeitraum { font-size: 0.72rem; color: var(--slate-500); }
    </style>
</head>
<body>
<div class="mp" id="mp-root">
    <div class="mp-loading">Lade Übersicht …</div>
</div>

<script>
const SHARE_HASH = <?= json_encode($shareHash) ?>;
const RESP_ABBR = {}; // wird mit Team-Kürzeln gefüllt

function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function dateD(s) { if (!s) return ''; return s.split('-').reverse().join('.'); }
function respLabel(name) { if (!name) return ''; return RESP_ABBR[name] || name; }

function statusFiltern(row, status) {
    if (!status || status === 'all') return true;
    if (status === 'open')        return !row.is_done && !row.is_placeholder;
    if (status === 'done')        return !!row.is_done;
    if (status === 'placeholder') return !!row.is_placeholder;
    if (status === 'no-ticket')   return !!parseInt(row.no_ticket);
    if (status === 'no-asana')    return !row.asana_gid;
    // „Entscheidung offen": eingeplante Aufgabe, nicht verknüpft, nicht als „Kein Ticket" markiert
    if (status === 'decision')    return !row.asana_gid && !parseInt(row.no_ticket) && !parseInt(row.is_placeholder);
    return true;
}
function matchedRow(row, filters) {
    // Spacer komplett ignorieren — sind reine Layout-Lücken im internen Editor
    if (row.row_type === 'spacer') return false;
    // Sektionen + Notizen sind Strukturanker (werden später ausgeblendet, wenn ihre Items wegfallen)
    if (row.row_type !== 'item') return true;
    // Items ohne Inhalt rausfiltern (leere Editor-Stubs)
    if (!String(row.description || '').trim()) return false;
    if (!statusFiltern(row, filters.status)) return false;
    if (filters.lead && row.lead_responsible !== filters.lead) return false;
    if (filters.responsible && row.responsible !== filters.responsible) return false;
    if (filters.search && filters.search.length > 1) {
        const hay = ((row.description||'') + ' ' + (row.responsible||'') + ' ' + (row.lead_responsible||'')).toLowerCase();
        if (hay.indexOf(filters.search.toLowerCase()) === -1) return false;
    }
    return true;
}

function renderRows(rows, filters) {
    // Sektion + folgende Items zusammen halten, Sektionen ohne sichtbare Items ausblenden
    const out = [];
    let currentSection = null;
    let currentBucket = [];
    const flush = () => {
        if (currentSection && currentBucket.length > 0) {
            out.push(currentSection);
            out.push(...currentBucket);
        }
        currentSection = null;
        currentBucket = [];
    };
    rows.forEach(r => {
        if (r.row_type === 'section') {
            flush();
            currentSection = r;
            currentBucket = [];
        } else if (matchedRow(r, filters)) {
            if (currentSection) currentBucket.push(r);
            else out.push(r);
        }
    });
    flush();
    if (out.length === 0) return '<tr><td colspan="6" class="mp-empty">Keine Aufgaben für die aktiven Filter.</td></tr>';
    return out.map(r => {
        if (r.row_type === 'section') {
            return `<tr class="is-section"><td colspan="6">▸ ${esc(r.description || '')}</td></tr>`;
        }
        if (r.row_type === 'note') {
            return `<tr><td colspan="6" style="color:var(--slate-500);font-style:italic;">${esc(r.description || '')}</td></tr>`;
        }
        const done = !!r.is_done;
        const placeholder = !!r.is_placeholder;
        return `<tr class="${done ? 'is-done' : ''} ${placeholder ? 'mp-placeholder' : ''}">
            <td style="width:24px;">${done ? '<span class="material-symbols-rounded mp-done-icon">check_circle</span>' : ''}</td>
            <td class="mp-rows-desc">${esc(r.description || '')}</td>
            <td style="width:140px;" class="mp-zeitraum">${esc(r.timeframe || dateD(r.deadline) || '')}</td>
            <td style="width:80px; text-align:right;">${r.ist_hours != null ? r.ist_hours : ''}</td>
            <td style="width:80px; text-align:right;">${r.planned_hours != null ? r.planned_hours : ''}</td>
            <td style="width:140px;">
                ${r.lead_responsible ? `<span class="mp-resp lead">${esc(respLabel(r.lead_responsible))}</span>` : ''}
                ${r.responsible ? `<span class="mp-resp">${esc(respLabel(r.responsible))}</span>` : ''}
            </td>
        </tr>`;
    }).join('');
}

function planCounts(plan, filters) {
    const items = (plan.rows || []).filter(r => r.row_type === 'item' && !r.is_placeholder);
    const matched = items.filter(r => matchedRow(r, filters));
    const done = matched.filter(r => r.is_done).length;
    return { gesamt: matched.length, erledigt: done, offen: matched.length - done };
}

async function load() {
    const root = document.getElementById('mp-root');
    try {
        const r = await fetch('/api/v1/public/projektplan-uebersicht/' + SHARE_HASH);
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const d = j.data;
        // Sonderfälle: Ablauf / Passwort
        if (d.expired) {
            root.innerHTML = `<div class="mp-header" style="display:block;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:8px;">⌛</div>
                <h1 class="mp-title">${esc(d.title || 'Übersicht abgelaufen')}</h1>
                <div class="mp-meta" style="justify-content:center;margin-top:8px;">
                    Dieser Sharelink ist abgelaufen${d.expires_at ? ' am ' + dateD(d.expires_at.slice(0,10)) : ''}.
                </div>
            </div>`;
            return;
        }
        if (d.password_required) {
            root.innerHTML = `<div class="mp-header" style="display:block;max-width:420px;margin:60px auto 0;">
                <div style="font-size:2rem;text-align:center;margin-bottom:8px;">🔒</div>
                <h1 class="mp-title" style="text-align:center;">${esc(d.title || 'Geschützte Übersicht')}</h1>
                <div class="mp-meta" style="justify-content:center;margin:6px 0 18px;">Bitte Passwort eingeben:</div>
                <form id="mp-auth-form" style="display:flex;gap:6px;">
                    <input type="password" id="mp-pw" autofocus required
                           style="flex:1;padding:10px 12px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.95rem;">
                    <button type="submit" style="background:var(--thoxan-600);color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-weight:600;">Öffnen</button>
                </form>
                <div id="mp-auth-error" style="color:var(--rose-600);margin-top:10px;font-size:0.85rem;text-align:center;display:none;"></div>
            </div>`;
            document.getElementById('mp-auth-form').addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const pw = document.getElementById('mp-pw').value;
                try {
                    const ar = await fetch('/api/v1/public/projektplan-uebersicht/' + SHARE_HASH + '/auth', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ password: pw }),
                    });
                    const aj = await ar.json();
                    if (!aj.success) throw new Error(aj.message);
                    load(); // nach Auth neu laden
                } catch (e) {
                    const ee = document.getElementById('mp-auth-error');
                    ee.textContent = e.message;
                    ee.style.display = 'block';
                }
            });
            return;
        }
        (d.team || []).forEach(t => { if (t.name && t.abbreviation) RESP_ABBR[t.name] = t.abbreviation; });
        document.title = d.title || (d.plans.length + ' Pläne — Übersicht');

        const filters = d.filters || {};
        const filterPills = [];
        if (filters.status && filters.status !== 'all') filterPills.push(`Status: ${filters.status}`);
        if (filters.lead)        filterPills.push(`Lead: ${respLabel(filters.lead)}`);
        if (filters.responsible) filterPills.push(`Umsetzung: ${respLabel(filters.responsible)}`);
        if (filters.search)      filterPills.push(`Suche: „${esc(filters.search)}"`);

        const stamp = d.created_at ? new Date(d.created_at.replace(' ', 'T')) : null;
        const stampTxt = stamp ? stamp.toLocaleDateString('de-DE', { day:'2-digit', month:'short', year:'numeric'}) : '';

        // Plan-Count wird unten nach Sichtbarkeits-Filter berechnet, damit Header die echte Zahl zeigt.
        // Header wird daher erst nach plansHtml zusammengebaut.
        const filterPillsHtml = filterPills.length ? `<div class="mp-filters">
                <span class="material-symbols-rounded" style="font-size:16px;">filter_alt</span>
                <span>Gefiltert:</span>
                ${filterPills.map(p => `<span class="mp-filter-pill">${p}</span>`).join('')}
            </div>` : '';

        // Pläne alphabetisch nach Titel sortieren (defensiv — Snapshots haben evtl. alte Reihenfolge)
        const planUebersicht = [...d.plans].sort((a, b) =>
            String(a.title || '').localeCompare(String(b.title || ''), 'de', { sensitivity: 'base' })
        );
        // Pläne ohne sichtbare Items nach Filter werden ausgeblendet — sonst irreführend
        // („0 offen 0 erledigt"-Cards bei aktivem Status-Filter).
        const sichtbarePlane = planUebersicht.filter(p => {
            const c = planCounts(p, filters);
            // Wenn Filter aktiv ist: Plan nur zeigen wenn er mindestens 1 matched Item hat.
            // Wenn KEIN Filter (status=all, lead/responsible/search leer): Plan immer zeigen.
            const filterAktiv = (filters.status && filters.status !== 'all') || filters.lead || filters.responsible || filters.search;
            return !filterAktiv || c.gesamt > 0;
        });
        const ausgeblendet = planUebersicht.length - sichtbarePlane.length;

        const plansHtml = sichtbarePlane.map(p => {
            const counts = planCounts(p, filters);
            const rowsHtml = renderRows(p.rows || [], filters);
            const statusClass = p.plan_typ === 'einzelprojekt' ? 'einzelprojekt' : (p.plan_status || '');
            const statusLabel = p.plan_typ === 'einzelprojekt' ? 'Einzelprojekt' : (p.plan_status || '');
            const period = (p.period_from || p.period_to)
                ? `${dateD(p.period_from)} – ${dateD(p.period_to)}` : '';
            return `<div class="mp-plan-card">
                <div class="mp-plan-head">
                    <div>
                        <div class="mp-plan-title">${esc(p.title)}</div>
                        <div class="mp-plan-sub">${esc(p.customer_name || '—')}${period ? ' · ' + esc(period) : ''}</div>
                    </div>
                    ${statusLabel ? `<span class="mp-plan-status ${statusClass}">${esc(statusLabel)}</span>` : ''}
                    <div class="mp-plan-counts">
                        <span><strong>${counts.offen}</strong> offen</span>
                        <span><strong>${counts.erledigt}</strong> erledigt</span>
                    </div>
                </div>
                <table class="mp-rows">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Aufgabe</th>
                            <th>Zeitraum</th>
                            <th style="text-align:right;">Ist h</th>
                            <th style="text-align:right;">Plan h</th>
                            <th>Beteiligte</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </div>`;
        }).join('');
        // Header dynamisch aufbauen — Plan-Count zeigt sichtbare/insgesamt
        const sichtbarN = sichtbarePlane.length;
        const gesamtN = planUebersicht.length;
        const countLabel = (ausgeblendet > 0)
            ? `<strong>${sichtbarN}</strong> von <span style="color:var(--slate-400);">${gesamtN}</span> Pläne${sichtbarN === 1 ? '' : 'n'} sichtbar`
            : `<strong>${gesamtN}</strong> Pläne`;
        const headerHtml = `
            <div class="mp-header">
                <div style="flex:1;min-width:0;">
                    <h1 class="mp-title">${esc(d.title || gesamtN + ' Pläne — Übersicht')}</h1>
                    <div class="mp-meta">
                        <span>${countLabel}</span>
                        ${stampTxt ? `<span>Erstellt am ${stampTxt}</span>` : ''}
                        ${d.is_snapshot ? `<span style="background:rgba(168, 85, 247, 0.12);color:#7e22ce;padding:2px 10px;border-radius:10px;font-weight:600;">📸 Snapshot (eingefroren)</span>` : ''}
                        ${d.expires_at ? `<span style="color:var(--amber-700);">Läuft ab am ${dateD(d.expires_at.slice(0,10))}</span>` : ''}
                    </div>
                </div>
            </div>`;
        const ausgeblendetHinweis = ausgeblendet > 0
            ? `<div style="text-align:center;padding:8px 12px;font-size:0.78rem;color:var(--slate-500);background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;margin-bottom:14px;">
                  ${ausgeblendet} ${ausgeblendet === 1 ? 'weiterer Plan ist' : 'weitere Pläne sind'} im Filter-Kontext nicht enthalten und wurden ausgeblendet.
               </div>` : '';

        const footHtml = `<div class="mp-foot">
            Diese Übersicht ist read-only · ${sichtbarN} sichtbar${ausgeblendet > 0 ? ` von ${gesamtN}` : ''} Pläne · Sharelink ist privat — bitte nicht öffentlich verteilen.
        </div>`;

        root.innerHTML = headerHtml + filterPillsHtml + ausgeblendetHinweis + (plansHtml || '<div class="mp-empty">Keine Pläne treffen den aktuellen Filter.</div>') + footHtml;
    } catch (e) {
        root.innerHTML = `<div class="mp-error">${esc(e.message)}</div>`;
    }
}
load();
</script>
</body>
</html>
