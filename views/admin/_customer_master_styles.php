<?php
/**
 * Shared CSS + Sidebar-Filter-JS fuer den Customer-Master-Detail-Bereich.
 * Wird sowohl von admin/customers.php (Default-Pane) als auch von
 * admin/customer-steckbrief.php (Detail-Pane) eingebunden.
 */
?>
<style>
.cm-page {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    overflow: visible;
}
.cm-sidebar,
.cm-main {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}

/* ===== Sidebar ===== */
.cm-sidebar {
    width: 360px; min-width: 360px;
    background: var(--slate-50);
    display: flex; flex-direction: column;
    overflow: hidden;
    transition: width 0.2s ease, min-width 0.2s ease;
}
.cm-sidebar.collapsed { width: 56px; min-width: 56px; }
.cm-sidebar.collapsed .cm-sb-head,
.cm-sidebar.collapsed .cm-sb-search,
.cm-sidebar.collapsed .cm-sb-quickactions,
.cm-sidebar.collapsed .cm-sb-filters,
.cm-sidebar.collapsed .cm-sb-list { display: none !important; }
.cm-sb-collapsed { display: none; flex-direction: column; align-items: center; padding: 10px 4px; gap: 8px; }
.cm-sidebar.collapsed .cm-sb-collapsed { display: flex; }
.cm-sb-collapsed-home {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 8px;
    color: var(--thoxan-700); text-decoration: none;
}
.cm-sb-collapsed-home:hover { background: var(--thoxan-50); }

.cm-sb-head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 16px; border-bottom: 1px solid var(--slate-200);
    background: #fff;
}
.cm-sb-title { flex: 1; font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-800); }
.cm-sb-toggle {
    width: 32px; height: 32px; border: none; background: transparent;
    color: var(--slate-500); cursor: pointer; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
}
.cm-sb-toggle:hover { background: var(--slate-100); color: var(--slate-800); }

.cm-sb-search { padding: 12px 14px; background: #fff; border-bottom: 1px solid var(--slate-200); }
.cm-search-wrap { position: relative; }
.cm-search-input {
    width: 100%; padding: 9px 12px 9px 36px;
    border: 1px solid var(--slate-300); border-radius: 8px;
    font-size: var(--d-fs-sm); font-family: inherit;
    background: var(--slate-50); color: var(--slate-800);
    box-sizing: border-box;
}
.cm-search-input:focus { outline: none; border-color: var(--thoxan-600); background: #fff; box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
.cm-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: var(--slate-400); font-size: 18px;
}

.cm-sb-quickactions {
    display: flex; flex-direction: column; gap: 2px;
    padding: 8px 8px; background: #fff; border-bottom: 1px solid var(--slate-200);
}
.cm-quick {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border-radius: 8px;
    color: var(--slate-700); text-decoration: none;
    font-size: var(--d-fs-sm); font-weight: 600;
    border: 1px solid transparent; cursor: pointer; background: transparent; font-family: inherit; text-align: left;
}
.cm-quick:hover { background: var(--slate-100); color: var(--slate-900); }
.cm-quick.is-active { background: var(--thoxan-50); color: var(--thoxan-800); border-color: var(--thoxan-200); }
.cm-quick .material-symbols-rounded { font-size: 20px; color: var(--thoxan-700); }

.cm-sb-filters {
    padding: 8px 12px;
    border-bottom: 1px solid var(--slate-200);
    background: var(--slate-50);
}
.cm-filter-row { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; margin-bottom: 6px; }
.cm-filter-row:last-child { margin-bottom: 0; }
.cm-filter-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--slate-500); letter-spacing: 0.04em; margin-right: 4px; }
.cm-filter-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px;
    background: #fff; color: var(--slate-700);
    border: 1px solid var(--slate-300); border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 600;
    cursor: pointer; font-family: inherit;
}
.cm-filter-pill:hover { border-color: var(--thoxan-600); color: var(--thoxan-700); }
.cm-filter-pill.is-active { background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600); }
.cm-filter-pill.is-active .cm-pill-count { background: rgba(255,255,255,0.25); color: #fff; }
.cm-pill-count { background: var(--slate-100); color: var(--slate-700); padding: 0 6px; border-radius: 999px; font-size: 10px; margin-left: 2px; }
.cm-dot { display: inline-block; width: 6px; height: 6px; border-radius: 999px; }
.cm-dot-active { background: #10b981; }
.cm-dot-inactive { background: #dc2626; }

.cm-sb-list {
    flex: 1; overflow-y: auto;
    padding: 4px 0;
}
.cm-sb-list::-webkit-scrollbar { width: 6px; }
.cm-sb-list::-webkit-scrollbar-thumb { background: var(--slate-300); border-radius: 3px; }

.cm-customer {
    display: grid; grid-template-columns: 36px 1fr auto; gap: 10px; align-items: center;
    padding: 6px 14px;
    text-decoration: none; color: var(--slate-800);
    border-left: 3px solid transparent;
}
.cm-customer:hover { background: #fff; }
.cm-customer.is-active {
    background: #fff;
    border-left-color: var(--thoxan-700);
}
.cm-customer.is-active .cm-customer-name { color: var(--thoxan-800); font-weight: 700; }
.cm-customer.is-active .cm-customer-abbr { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }
.cm-customer.is-hidden { display: none; }
.cm-customer-abbr {
    width: 36px; height: 30px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 6px;
    font-size: var(--d-fs-xs); font-weight: 700; letter-spacing: 0.02em;
    color: var(--slate-700);
    flex-shrink: 0;
}
.cm-customer-name {
    font-size: var(--d-fs-sm); font-weight: 600; color: var(--slate-800);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    min-width: 0;
}
.cm-mon-dot {
    width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0;
}
.cm-mon-up      { background: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,0.18); }
.cm-mon-down    { background: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,0.18); }
.cm-mon-paused  { background: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.18); }
.cm-mon-none    { background: var(--slate-300); }

/* ===== Main-Pane (Default = Site-Monitor; Detail = Steckbrief) ===== */
.cm-main { display: flex; flex-direction: column; overflow: hidden; }
.cm-main-inner { flex: 1; overflow-y: auto; padding: 16px 22px; }

/* Wenn der Steckbrief eingebettet ist, soll er sich in der schmaleren Spalte nicht zerschießen */
.cm-main .thx-page-header { margin: 0 0 16px 0; padding: 0; border: 0; background: transparent; }

/* ===== Card-Grid im Default-Pane ===== */
.cm-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.cm-card {
    display: flex; flex-direction: column; gap: 8px;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 12px 14px; text-decoration: none; color: inherit;
    transition: border-color 0.12s, box-shadow 0.12s;
    min-height: 156px;
    cursor: pointer;
}
.cm-card:focus { outline: 2px solid var(--thoxan-600); outline-offset: 2px; }
.cm-card:hover { border-color: var(--thoxan-600); box-shadow: 0 4px 14px rgba(15,23,42,0.08); }

.cm-card-head { display: grid; grid-template-columns: 40px 1fr auto; gap: 10px; align-items: center; }
.cm-card-logo {
    width: 44px; height: 44px;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.cm-card-logo-plain {
    background: transparent; border: 0; border-radius: 0;
}
.cm-card-logo.cm-card-logo-text {
    background: #fff; color: var(--slate-700);
    border: 1px solid var(--slate-200); border-radius: 8px;
    font-weight: 700; font-size: var(--d-fs-xs); letter-spacing: 0.02em;
}
.cm-card-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.cm-card-titles { min-width: 0; }
.cm-card-name {
    font-weight: 700; color: var(--slate-900); font-size: var(--d-fs-sm);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.25;
}
.cm-card-industry {
    font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 1px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cm-card-inactive {
    font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 999px;
    background: var(--rose-100); color: var(--rose-700); text-transform: uppercase;
    letter-spacing: 0.04em;
}

.cm-card-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.cm-card-tag {
    font-size: 10px; font-weight: 600;
    background: var(--slate-100); color: var(--slate-700);
    padding: 2px 7px; border-radius: 999px;
}

/* Website-URL-Zeile mit Ladezeit + Online/Offline-Icon */
.cm-card-site {
    display: flex; align-items: center; gap: 8px;
    padding-top: 8px; border-top: 1px dashed var(--slate-200);
    font-size: var(--d-fs-xs);
    min-width: 0;
}
.cm-card-site-url {
    flex: 1; min-width: 0;
    color: var(--thoxan-700); text-decoration: none;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cm-card-site-url:hover { text-decoration: underline; }
.cm-card-site-ms {
    color: var(--slate-600); font-variant-numeric: tabular-nums;
    flex-shrink: 0;
}
.cm-card-site-status {
    width: 24px; height: 24px; border: none; background: transparent;
    cursor: pointer; padding: 0; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    text-decoration: none;
}
.cm-card-site-status:hover { transform: scale(1.1); transition: transform 0.1s; }
.cm-card-site-status .material-symbols-rounded { font-size: 22px; }
.cm-card-site-status.cm-mon-up     .material-symbols-rounded { color: #10b981; }
.cm-card-site-status.cm-mon-down   .material-symbols-rounded { color: #dc2626; }
.cm-card-site-status.cm-mon-paused .material-symbols-rounded { color: #f59e0b; }

.cm-card-foot {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-top: auto; padding-top: 4px;
    font-size: var(--d-fs-xs); color: var(--slate-500);
}
.cm-card-meta {
    display: inline-flex; align-items: center; gap: 3px;
    color: var(--slate-500);
}
.cm-card-meta .material-symbols-rounded { font-size: 13px; }
.cm-card-meta-link { cursor: pointer; color: var(--thoxan-700); font-weight: 600; }
.cm-card-meta-link:hover { color: var(--thoxan-900); text-decoration: underline; }

.cm-mon-dot { width: 7px; height: 7px; border-radius: 999px; background: var(--slate-300); display: inline-block; }

.cm-grid-empty {
    grid-column: 1 / -1;
    text-align: center; padding: 60px 20px; color: var(--slate-500);
}
.cm-grid-empty .material-symbols-rounded { font-size: 48px; opacity: 0.4; }
</style>

<script>
// Sidebar-Toggle
window.cmToggleSidebar = function() {
    const sb = document.getElementById('cm-sidebar');
    if (!sb) return;
    sb.classList.toggle('collapsed');
    localStorage.setItem('cm-sb-collapsed', sb.classList.contains('collapsed') ? '1' : '0');
};
if (localStorage.getItem('cm-sb-collapsed') === '1') {
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('cm-sidebar')?.classList.add('collapsed');
    });
}

// Filter-Pills + Suche
// Konvention: Single-Select pro Filter-Gruppe (Status / Art / Sites).
// Kombination ueber Gruppen hinweg ist erlaubt — z.B. "Aktiv" + "Eigenprojekt" + "Online".
// Filter werden in localStorage persistiert, sodass sie ueber Page-Loads erhalten bleiben.
window.cmFilters = (() => {
    try {
        const saved = JSON.parse(localStorage.getItem('thx_cm_filters') || '{}');
        return { status: saved.status || '', tag: saved.tag || '', monStatus: saved.monStatus || '', monCat: saved.monCat || '' };
    } catch (_) { return { status: '', tag: '', monStatus: '', monCat: '' }; }
})();
function cmPersistFilters() {
    try { localStorage.setItem('thx_cm_filters', JSON.stringify(cmFilters)); } catch (_) {}
}
// Beim Initial-Render gespeicherten Filter sofort anwenden (Pillen markieren + Liste filtern)
document.addEventListener('DOMContentLoaded', () => {
    const sync = (key, val) => {
        if (!val) {
            // Default-Pille (z.B. Status="" = Alle) bekommt is-active
            if (key === 'status') {
                document.querySelector('.cm-filter-pill[data-filter="status"][data-value=""]')?.classList.add('is-active');
            }
            return;
        }
        document.querySelectorAll('.cm-filter-pill[data-filter="' + key + '"]').forEach(b => b.classList.remove('is-active'));
        const target = document.querySelector('.cm-filter-pill[data-filter="' + key + '"][data-value="' + CSS.escape(val) + '"]');
        if (target) target.classList.add('is-active');
    };
    sync('status', cmFilters.status);
    sync('tag', cmFilters.tag);
    sync('monStatus', cmFilters.monStatus);
    sync('monCat', cmFilters.monCat);
    if (typeof cmApplyFilter === 'function') cmApplyFilter();
});
window.cmTogglePill = function(btn) {
    const f = btn.dataset.filter;
    const v = btn.dataset.value;
    const filterKey = (f === 'tag') ? 'tag' : f;
    const wasActive = btn.classList.contains('is-active');
    // Alle Pills der gleichen Gruppe deaktivieren
    document.querySelectorAll('.cm-filter-pill[data-filter="' + f + '"]').forEach(b => b.classList.remove('is-active'));
    if (f === 'status' && v === '') {
        // "Alle"-Pille — explizit kein Filter
        btn.classList.add('is-active');
        cmFilters.status = '';
    } else if (wasActive) {
        // Erneuter Klick auf aktive Pille → deselect (zurueck zu "Alle")
        cmFilters[filterKey] = '';
        // Bei Status "Alle"-Pille wieder aktivieren als Default
        if (f === 'status') {
            const allBtn = document.querySelector('.cm-filter-pill[data-filter="status"][data-value=""]');
            if (allBtn) allBtn.classList.add('is-active');
        }
    } else {
        btn.classList.add('is-active');
        cmFilters[filterKey] = v;
    }
    cmPersistFilters();
    cmApplyFilter();
};
window.cmFilterSidebar = function() { cmApplyFilter(); };
function cmApplyFilter() {
    const q = (document.getElementById('cm-search')?.value || '').toLowerCase().trim();
    const status = cmFilters.status;
    const tag = cmFilters.tag;            // Single-Select (vorher Set/Array)
    const monStatus = cmFilters.monStatus;
    const monCat = cmFilters.monCat;
    // Sidebar-Kundenliste filtern — Filter UEBER Gruppen werden kombiniert (UND-Logik),
    // innerhalb einer Gruppe gilt Single-Select.
    document.querySelectorAll('.cm-customer').forEach(el => {
        let ok = true;
        if (q && !el.dataset.search.includes(q)) ok = false;
        if (ok && status && el.dataset.status !== status) ok = false;
        if (ok && tag) {
            const t = (el.dataset.tags || '').toLowerCase();
            if (!t.includes(tag.toLowerCase())) ok = false;
        }
        if (ok && monStatus) {
            const s = el.dataset.monStatus || '';
            if (!s.split(/\s+/).includes(monStatus)) ok = false;
        }
        if (ok && monCat) {
            const c = (el.dataset.monCat || '').toLowerCase();
            if (!c.split('|').includes(monCat.toLowerCase())) ok = false;
        }
        el.classList.toggle('is-hidden', !ok);
    });
    document.dispatchEvent(new CustomEvent('cm-filter-changed', { detail: {
        status, tag, monStatus, monCat,
    }}));
}

// "Neuer Kunde"-Modal: nutzt die globale ThxQuickCustomer-Komponente
window.cmOpenNewCustomerModal = function() {
    if (!window.ThxQuickCustomer) {
        // Fallback ohne Modal-Komponente: direkt in den Wizard
        window.location.href = '/admin/customers/wizard';
        return;
    }
    ThxQuickCustomer.open(function(customer) {
        // Nach Anlage direkt zum Steckbrief
        window.location.href = '/admin/customers/' + customer.id + '/steckbrief';
    });
};
</script>
