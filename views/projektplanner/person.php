<?php
$shareHash = $share_hash ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aufgabenliste</title>
    <link rel="stylesheet" href="/assets/css/material-symbols.css">
    <link rel="stylesheet" href="/assets/css/thx-tokens.css">
    <link rel="stylesheet" href="/assets/fonts/lam/frutiger.css">
    <style>
        html { font-size: 100%; }
        body {
            font-family: 'Frutiger LT Std', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            margin: 0; background: var(--slate-50); color: var(--slate-800);
        }
        .pp-container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }

        .pp-head {
            background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
            padding: var(--d-card-pad); margin-bottom: var(--d-section-gap);
            display: flex; align-items: center; gap: var(--d-section-gap);
        }
        .pp-head-avatar {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--thoxan-100); color: var(--thoxan-800);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 18px; flex-shrink: 0;
            border: 2px solid var(--thoxan-200);
        }
        .pp-head h1 { margin: 0; font-size: var(--d-fs-xl); color: var(--slate-900); font-weight: 700; }
        .pp-head .sub { color: var(--slate-500); font-size: var(--d-fs-base); margin-top: 2px; }

        .pp-kpis {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;
            margin-bottom: 20px;
        }
        .pp-kpi {
            background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
            padding: var(--d-card-pad);
        }
        .pp-kpi-label { font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.05em; color: var(--slate-500); font-weight: 600; }
        .pp-kpi-value {
            font-size: var(--d-fs-2xl); font-weight: 700;
            font-family: ui-monospace, monospace; color: var(--slate-800);
            margin-top: 4px;
        }
        .pp-kpi.is-soll  .pp-kpi-value { color: #7e22ce; }
        .pp-kpi.is-ist   .pp-kpi-value { color: var(--thoxan-700); }
        .pp-kpi.is-done  .pp-kpi-value { color: var(--emerald-700); }
        .pp-kpi.is-open  .pp-kpi-value { color: var(--amber-700); }
        .pp-kpi.is-total .pp-kpi-value { color: var(--slate-700); }

        .pp-customer-block {
            background: #fff; border: 1px solid var(--slate-200);
            border-radius: var(--d-card-radius); overflow: hidden; margin-bottom: 18px;
        }
        .pp-customer-head {
            padding: var(--d-card-pad); display: flex; align-items: center; gap: var(--d-section-gap);
            border-bottom: 1px solid var(--slate-200);
        }
        .pp-customer-dot {
            width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
        }
        .pp-customer-name { font-weight: 700; color: var(--slate-800); font-size: var(--d-fs-base); }
        .pp-customer-stats { margin-left: auto; font-family: ui-monospace, monospace; font-size: var(--d-fs-sm); color: var(--slate-600); }

        .pp-task-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); }
        .pp-task-table th {
            text-align: left; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); color: var(--slate-500);
            font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em;
            background: var(--slate-50); border-bottom: 1px solid var(--slate-200); font-weight: 600;
        }
        .pp-task-table th.is-right { text-align: right; }
        .pp-task-table th.is-center { text-align: center; }
        .pp-task-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); }
        .pp-task-table td.is-right { text-align: right; font-family: ui-monospace, monospace; }
        .pp-task-table td.is-center { text-align: center; }
        .pp-task-table tr:hover td { background: var(--slate-50); }
        .pp-task-table tr.is-done td { background: rgba(16, 185, 129, 0.05); }
        .pp-task-table tr.is-done td:nth-child(2) { color: var(--slate-500); }

        .pp-task-table tr.pp-row-sum td {
            background: var(--slate-50); font-weight: 700;
            border-top: 2px solid var(--slate-200); border-bottom: none; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x);
        }

        .pp-role-badge {
            display: inline-block; padding: 1px var(--d-control-pad-x); border-radius: 999px;
            font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase;
        }
        .pp-role-lead { background: var(--thoxan-100); color: var(--thoxan-800); }
        .pp-role-resp { background: var(--slate-200); color: var(--slate-600); }

        .pp-empty { padding: 60px; text-align: center; color: var(--slate-400); font-size: var(--d-fs-base); }
        .pp-footer { margin-top: 24px; color: var(--slate-400); font-size: var(--d-fs-xs); text-align: center; font-style: italic; }
        .pp-filter-chip {
            background: var(--slate-50); color: var(--slate-700);
            border: 1px solid var(--slate-200); padding: var(--d-row-pad-y) var(--d-control-pad-x);
            font-size: var(--d-fs-sm); border-radius: 999px; cursor: pointer; font-family: inherit;
        }
        .pp-filter-chip:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); }
        .pp-filter-chip.is-active { background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600); }
    </style>
</head>
<body>

<div id="pp-root" class="pp-container">
    <div class="pp-empty">Lädt…</div>
</div>

<script>
'use strict';
const ppHash = <?= json_encode($shareHash) ?>;

function ppEsc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function ppInitials(name) {
    if (!name) return '?';
    return name.trim().split(/\s+/).slice(0, 2).map(s => s.charAt(0).toUpperCase()).join('');
}

const ppState = { data: null, filter: 'all', search: '', sort: null };

async function ppLoad() {
    try {
        const r = await fetch('/api/v1/public/personen-aufgaben/' + encodeURIComponent(ppHash));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        ppState.data = j.data;
        ppRender(j.data);
    } catch (e) {
        document.getElementById('pp-root').innerHTML = '<div class="pp-empty">' + ppEsc(e.message) + '</div>';
    }
}

function ppSetFilter(f) { ppState.filter = f; ppRender(ppState.data); }
function ppSetSearch(v) { ppState.search = v.toLowerCase().trim(); ppRender(ppState.data); }
function ppSetSort(col) {
    if (ppState.sort && ppState.sort.col === col) {
        ppState.sort = ppState.sort.dir === 'asc' ? { col, dir: 'desc' } : null;
    } else { ppState.sort = { col, dir: 'asc' }; }
    ppRender(ppState.data);
}
function ppSortArrow(col) {
    if (!ppState.sort || ppState.sort.col !== col) return '';
    return ppState.sort.dir === 'asc' ? ' ↑' : ' ↓';
}

async function ppToggleDone(rowId, current) {
    const newVal = current ? 0 : 1;
    try {
        const r = await fetch('/api/v1/public/personen-aufgaben/' + encodeURIComponent(ppHash) + '/toggle-done', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ row_id: rowId, is_done: newVal }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        // Lokal aktualisieren statt Reload
        ppState.data.customers.forEach(c => c.tasks.forEach(t => {
            if (parseInt(t.id) === rowId) {
                const wasDone = parseInt(t.is_done) === 1;
                t.is_done = newVal;
                const soll = parseFloat(t.planned_hours) || 0;
                if (wasDone && !newVal) { ppState.data.totals.done--; ppState.data.totals.open++; }
                else if (!wasDone && newVal) { ppState.data.totals.done++; ppState.data.totals.open--; }
            }
        }));
        ppRender(ppState.data);
    } catch (e) { alert('Fehler: ' + e.message); }
}

function ppFilterTask(t) {
    const isDone = parseInt(t.is_done) === 1;
    if (ppState.filter === 'open' && isDone) return false;
    if (ppState.filter === 'done' && !isDone) return false;
    if (ppState.search) {
        const hay = (t.description + ' ' + (t.plan_title || '') + ' ' + (t.timeframe || '')).toLowerCase();
        if (!hay.includes(ppState.search)) return false;
    }
    return true;
}

function ppSortTasks(tasks) {
    if (!ppState.sort) return tasks;
    const { col, dir } = ppState.sort;
    const sign = dir === 'desc' ? -1 : 1;
    return tasks.slice().sort((a, b) => {
        let va, vb;
        if (col === 'soll' || col === 'ist') { va = parseFloat(a[col === 'soll' ? 'planned_hours' : 'ist_hours']) || 0; vb = parseFloat(b[col === 'soll' ? 'planned_hours' : 'ist_hours']) || 0; return (va - vb) * sign; }
        if (col === 'done') return ((parseInt(a.is_done)||0) - (parseInt(b.is_done)||0)) * sign;
        va = String(a[col] ?? ''); vb = String(b[col] ?? '');
        return va.localeCompare(vb, 'de') * sign;
    });
}

function ppRender(d) {
    const t = d.totals;
    let html = `
        <div class="pp-head">
            <div class="pp-head-avatar">${ppEsc(ppInitials(d.person_name))}</div>
            <div>
                <h1>${ppEsc(d.person_name)}</h1>
                <div class="sub">Aufgabenliste aus allen aktiven Plänen</div>
            </div>
        </div>

        <div class="pp-kpis">
            <div class="pp-kpi is-soll"><div class="pp-kpi-label">Soll (h)</div><div class="pp-kpi-value">${t.soll.toFixed(0)}</div></div>
            <div class="pp-kpi is-ist"><div class="pp-kpi-label">Ist (h)</div><div class="pp-kpi-value">${t.ist.toFixed(0)}</div></div>
            <div class="pp-kpi is-done"><div class="pp-kpi-label">Erledigt</div><div class="pp-kpi-value">${t.done}</div></div>
            <div class="pp-kpi is-open"><div class="pp-kpi-label">Offen</div><div class="pp-kpi-value">${t.open}</div></div>
            <div class="pp-kpi is-total"><div class="pp-kpi-label">Aufgaben gesamt</div><div class="pp-kpi-value">${t.total}</div></div>
        </div>`;

    // Filter + Suche
    html += `
        <div style="background:#fff;border:1px solid var(--slate-200);border-radius:10px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <button class="pp-filter-chip ${ppState.filter === 'all' ? 'is-active' : ''}" onclick="ppSetFilter('all')">Alle</button>
            <button class="pp-filter-chip ${ppState.filter === 'open' ? 'is-active' : ''}" onclick="ppSetFilter('open')">Offen</button>
            <button class="pp-filter-chip ${ppState.filter === 'done' ? 'is-active' : ''}" onclick="ppSetFilter('done')">Erledigt</button>
            <input type="text" placeholder="Suche Aufgabe oder Plan…" value="${ppEsc(ppState.search)}" oninput="ppSetSearch(this.value)"
                style="margin-left:auto;flex:0 1 280px;padding:5px 10px;border:1px solid var(--slate-200);border-radius:6px;font-family:inherit;font-size:13px;">
        </div>`;

    if (!d.customers.length) {
        html += '<div class="pp-empty">Keine Aufgaben.</div>';
    } else {
        let totalVisible = 0;
        d.customers.forEach(c => {
            let filtered = c.tasks.filter(ppFilterTask);
            filtered = ppSortTasks(filtered);
            if (!filtered.length) return;
            totalVisible += filtered.length;
            const sumSoll = filtered.reduce((a,t) => a + (parseFloat(t.planned_hours)||0), 0);
            const sumIst = filtered.reduce((a,t) => a + (parseFloat(t.ist_hours)||0), 0);
            const taskRows = filtered.map(r => {
                const done = parseInt(r.is_done);
                const ist = parseFloat(r.ist_hours) || 0;
                const soll = parseFloat(r.planned_hours) || 0;
                return `
                <tr class="${done ? 'is-done' : ''}">
                    <td><strong>${ppEsc(r.plan_title)}</strong></td>
                    <td>${ppEsc(r.description || '')}</td>
                    <td class="is-right">${soll > 0 ? soll.toFixed(1) : ''}</td>
                    <td class="is-right">${ist > 0 ? ist.toFixed(1) : ''}</td>
                    <td>${ppEsc(r.timeframe || '')}</td>
                    <td>${ppEsc(r.deadline || '')}</td>
                    <td class="is-center">
                        <input type="checkbox" ${done ? 'checked' : ''} onchange="ppToggleDone(${parseInt(r.id)}, ${done})"
                               style="cursor:pointer;width:18px;height:18px;" title="Als erledigt markieren">
                    </td>
                    <td><span class="pp-role-badge ${r.role === 'lead' ? 'pp-role-lead' : 'pp-role-resp'}">${r.role === 'lead' ? 'HV' : 'Umsetz.'}</span></td>
                </tr>`;
            }).join('');

            html += `
            <div class="pp-customer-block">
                <div class="pp-customer-head">
                    <span class="pp-customer-dot" style="background:${c.customer_color}"></span>
                    <span class="pp-customer-name">${ppEsc(c.customer_name)}</span>
                    ${c.customer_abbr ? `<span style="background:var(--slate-100);color:var(--slate-700);padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;">${ppEsc(c.customer_abbr)}</span>` : ''}
                    <span class="pp-customer-stats">${filtered.length} Aufgaben · ${sumSoll.toFixed(1)} h Soll · ${sumIst.toFixed(1)} h Ist</span>
                </div>
                <table class="pp-task-table">
                    <thead><tr>
                        <th style="width:140px;cursor:pointer;" onclick="ppSetSort('plan_title')">Plan${ppSortArrow('plan_title')}</th>
                        <th style="cursor:pointer;" onclick="ppSetSort('description')">Aufgabe${ppSortArrow('description')}</th>
                        <th class="is-right" style="width:60px;cursor:pointer;" onclick="ppSetSort('soll')">Soll${ppSortArrow('soll')}</th>
                        <th class="is-right" style="width:60px;cursor:pointer;" onclick="ppSetSort('ist')">Ist${ppSortArrow('ist')}</th>
                        <th style="width:90px;cursor:pointer;" onclick="ppSetSort('timeframe')">Zeitraum${ppSortArrow('timeframe')}</th>
                        <th style="width:90px;cursor:pointer;" onclick="ppSetSort('deadline')">Deadline${ppSortArrow('deadline')}</th>
                        <th class="is-center" style="width:30px;cursor:pointer;" onclick="ppSetSort('done')">✓${ppSortArrow('done')}</th>
                        <th style="width:70px;">Rolle</th>
                    </tr></thead>
                    <tbody>${taskRows}</tbody>
                </table>
            </div>`;
        });
        if (totalVisible === 0) {
            html += '<div class="pp-empty">Keine Aufgaben für die aktiven Filter.</div>';
        }
    }

    html += `<div class="pp-footer">erstellt mit Thoxan</div>`;
    document.getElementById('pp-root').innerHTML = html;
}

document.addEventListener('DOMContentLoaded', ppLoad);
</script>

</body>
</html>
