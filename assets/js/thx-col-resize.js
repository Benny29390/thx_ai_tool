/**
 * thx-col-resize.js
 *
 * Wiederverwendbares Spaltenbreiten-Tool fuer beliebige Tabellen.
 *
 *   - Grip am rechten Rand jedes <th>, per Drag → Pixel-Breite anpassen
 *   - Doppelklick auf Grip → Spalte auf Default zuruecksetzen
 *   - Rechtsklick auf Header oder Grip → exakte Eingabe (px oder %)
 *   - Breiten werden in localStorage pro Tabelle persistiert
 *
 * Erwartet, dass jede Tabelle ein <thead> mit <th>-Reihe hat. Wenn die
 * Tabelle KEIN <colgroup> mitbringt, wird automatisch eines erzeugt.
 *
 * Benutzung:
 *   thxColResize.install({
 *       table: document.querySelector('#meine-tabelle'),
 *       storageKey: 'lam-linkprofil-cols-v1',
 *   });
 *
 * Mehrfacher install()-Aufruf auf derselben Tabelle bereinigt vorhandene Grips
 * und installiert neu — z.B. nach Tabellen-Render in einem SPA.
 */
(function (global) {
    'use strict';

    function ensureStyles() {
        if (document.getElementById('thx-col-resize-style')) return;
        const css = `
            .thx-col-resizer {
                position: absolute; top: 0; right: 0; bottom: 0;
                width: 6px; cursor: col-resize; user-select: none;
                z-index: 3;
                background: transparent;
                transition: background 0.1s;
            }
            .thx-col-resizer:hover, .thx-col-resizer:active {
                background: var(--thoxan-300);
            }
            /* Header muss position:relative haben, damit der Grip absolute floatet */
            .thx-col-resize-host thead th { position: relative !important; }
            /* table-layout: fixed sorgt dafuer, dass die Spaltenbreite NICHT mehr
               von der Datendichte abhaengt — Filter/Kundenwechsel veraendert nichts. */
            .thx-col-resize-host { table-layout: fixed; }
            /* Inhalts-Overflow in Tabellenzellen sauber abschneiden */
            .thx-col-resize-host tbody td { overflow: hidden; text-overflow: ellipsis; }
            /* Kein table-layout: fixed — sonst werden bei leerem colgroup alle Spalten
               gleich breit und das Layout zerfaellt. Browser respektiert col[i].style.width
               auch im auto-Mode. */
        `;
        const style = document.createElement('style');
        style.id = 'thx-col-resize-style';
        style.textContent = css;
        document.head.appendChild(style);
    }

    function ensureColgroup(table) {
        let colgroup = table.querySelector(':scope > colgroup');
        const ths = table.querySelectorAll(':scope > thead > tr > th');
        if (!ths.length) return null;
        if (!colgroup) {
            colgroup = document.createElement('colgroup');
            for (let i = 0; i < ths.length; i++) {
                colgroup.appendChild(document.createElement('col'));
            }
            table.insertBefore(colgroup, table.firstChild);
        } else {
            // Falls weniger <col> als <th>, ergaenzen
            while (colgroup.children.length < ths.length) {
                colgroup.appendChild(document.createElement('col'));
            }
        }
        return colgroup;
    }

    function loadSaved(storageKey) {
        try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; }
        catch (_) { return {}; }
    }
    function persist(storageKey, saved) {
        try { localStorage.setItem(storageKey, JSON.stringify(saved)); } catch (_) {}
    }

    function applyWidths(cols, storageKey, defaults) {
        const saved = loadSaved(storageKey);
        cols.forEach((col, i) => {
            const savedV = saved[i];
            if (savedV != null) {
                col.style.width = (typeof savedV === 'number') ? (savedV + 'px') : savedV;
                return;
            }
            // Kein Saved-Wert → Default verwenden (falls vorhanden)
            const def = defaults && defaults[i];
            if (def != null) {
                col.style.width = (typeof def === 'number') ? (def + 'px') : def;
            } else {
                col.style.removeProperty('width');
            }
        });
    }

    function install(opts) {
        const table = opts && opts.table;
        const storageKey = opts && opts.storageKey;
        if (!table || !storageKey) {
            console.warn('[thxColResize] install ohne table oder storageKey:', opts);
            return;
        }

        ensureStyles();
        table.classList.add('thx-col-resize-host');

        const colgroup = ensureColgroup(table);
        if (!colgroup) {
            console.warn('[thxColResize] kein <thead><th> in der Tabelle gefunden:', table);
            return;
        }
        const cols = colgroup.querySelectorAll(':scope > col');
        const ths  = table.querySelectorAll(':scope > thead > tr > th');
        console.log('[thxColResize] installiert auf', table.id || table, ths.length, 'Spalten');

        applyWidths(cols, storageKey, opts && opts.defaults);

        ths.forEach((th, i) => {
            // Alte Grips auf diesem TH wegraeumen — Idempotenz
            th.querySelectorAll('.thx-col-resizer').forEach(el => el.remove());

            const grip = document.createElement('div');
            grip.className = 'thx-col-resizer';
            grip.title = 'Spalte ziehen — Doppelklick: Default · Rechtsklick: exakt';

            grip.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const startX = e.clientX;
                const startWidth = ths[i].offsetWidth;
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
                const onMove = (ev) => {
                    const newW = Math.max(28, startWidth + (ev.clientX - startX));
                    cols[i].style.width = newW + 'px';
                };
                const onUp = () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    const saved = loadSaved(storageKey);
                    saved[i] = ths[i].offsetWidth + 'px';
                    persist(storageKey, saved);
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });

            grip.addEventListener('dblclick', (e) => {
                e.preventDefault();
                e.stopPropagation();
                cols[i].style.removeProperty('width');
                const saved = loadSaved(storageKey);
                delete saved[i];
                persist(storageKey, saved);
            });

            const onCtx = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const currentPx = ths[i].offsetWidth;
                const wrap = table.parentElement;
                const wrapW = (wrap && wrap.offsetWidth) || table.offsetWidth || 1;
                const currentPct = Math.round((currentPx / wrapW) * 100);
                const headerLabel = (ths[i].textContent || '').replace(/\s+/g, ' ').trim().substring(0, 30) || 'Spalte';
                const input = prompt(
                    'Spaltenbreite für "' + headerLabel + '":\n' +
                    '· Pixel: 120 oder 120px\n' +
                    '· Prozent: 18%\n' +
                    'Leer + OK = Default. Aktuell: ' + currentPx + 'px (~' + currentPct + '%).',
                    currentPx + 'px'
                );
                if (input === null) return;
                const raw = input.trim();
                if (raw === '') {
                    cols[i].style.removeProperty('width');
                    const saved = loadSaved(storageKey);
                    delete saved[i];
                    persist(storageKey, saved);
                    return;
                }
                let newWidth = null;
                if (raw.endsWith('%')) {
                    const pct = parseFloat(raw.slice(0, -1));
                    if (isFinite(pct) && pct > 0 && pct < 100) newWidth = pct + '%';
                } else {
                    const px = parseInt(raw.replace(/px$/i, ''), 10);
                    if (isFinite(px) && px >= 20 && px <= 2000) newWidth = px + 'px';
                }
                if (!newWidth) { alert('Ungültige Eingabe. Erlaubt: 20–2000 px oder 1–99 %.'); return; }
                cols[i].style.width = newWidth;
                const saved = loadSaved(storageKey);
                saved[i] = newWidth;
                persist(storageKey, saved);
            };
            grip.addEventListener('contextmenu', onCtx);
            th.addEventListener('contextmenu', (e) => {
                if (e.target === grip) return;
                onCtx(e);
            });

            th.appendChild(grip);
        });
    }

    /**
     * Convenience: installiert Resize auf einer Tabelle und re-installiert bei
     * Header-Re-Render (MutationObserver auf <thead>, NICHT auf tbody — sonst
     * feuert jeder zugefuegte <tr> bei grossen Listen den Observer und macht
     * das UI traege).
     */
    function autoInstall(opts) {
        const table = opts && opts.table;
        if (!table) return;
        install(opts);

        const thead = table.querySelector(':scope > thead');
        if (!thead) return; // nichts zum Beobachten

        let debounceTimer = null;
        const observer = new MutationObserver(() => {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => install(opts), 80);
        });
        observer.observe(thead, { childList: true, subtree: true });
        return observer;
    }

    global.thxColResize = { install, autoInstall };
})(window);
