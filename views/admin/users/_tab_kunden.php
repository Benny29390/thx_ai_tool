<?php
/**
 * Kundenzuordnung — Matrix-Tabelle.
 *
 *   Zeilen:  Kunden (alle aktiven)
 *   Spalten: 3 Rollen (Manager, User, Guest) + jede(r) Non-Admin-User einzeln
 *
 * Klick auf Spalten-Header  → ganze Spalte toggeln (alle Kunden fuer diese Rolle/User)
 * Klick auf Kunden-Name     → ganze Zeile toggeln (alle Spalten fuer diesen Kunden)
 * Einzelner Klick auf Zelle → nur diese Zelle
 *
 * Speichern fragt im Modal pro geaenderter Spalte ab (wie beim Rollen-Tab).
 *
 * Wirkt global: Auth::loadUserCustomers() liest die effektive Liste aus
 * user_customers UNION role_customers — also genau die hier verwalteten Daten.
 *
 * Erwartete Variablen:
 *   $users          (mit id, name, email, role, is_active)
 *   $customers      (id, name, slug)
 *   $roleCustomers  array<role, int[]>
 *   $userCustomers  array<user_id, int[]>
 */
use Core\Auth;

// Spalten zusammenstellen: Rollen oben, User darunter. Admin entfaellt (sieht eh alles).
$rolleSpalten = [
    ['type' => 'role', 'key' => 'manager', 'label' => 'Manager (alle)', 'farbe' => 'thoxan'],
    ['type' => 'role', 'key' => 'user',    'label' => 'User (alle)',    'farbe' => 'emerald'],
    ['type' => 'role', 'key' => 'guest',   'label' => 'Guest (alle)',   'farbe' => 'slate'],
];

$userSpalten = [];
foreach ($users as $u) {
    if ($u['role'] === 'admin') continue; // Admin sieht eh alles, keine Zuordnung noetig
    if (!$u['is_active']) continue;
    $userSpalten[] = [
        'type'      => 'user',
        'key'       => (int)$u['id'],
        'label'     => $u['name'],
        'role'      => $u['role'],
        'farbe'     => $u['role'] === 'manager' ? 'thoxan' : ($u['role'] === 'guest' ? 'slate' : 'emerald'),
    ];
}

$alleSpalten = array_merge($rolleSpalten, $userSpalten);

// Initialer Aktiv-State pro Zelle (Kunde-Spalte) als Map fuer das Frontend.
$initialState = [];
foreach ($customers as $c) {
    $cid = (int)$c['id'];
    $row = [];
    foreach ($rolleSpalten as $sp) {
        $row['role:' . $sp['key']] = in_array($cid, $roleCustomers[$sp['key']] ?? [], true);
    }
    foreach ($userSpalten as $sp) {
        $uid = (int)$sp['key'];
        $row['user:' . $uid] = in_array($cid, $userCustomers[$uid] ?? [], true);
    }
    $initialState[$cid] = $row;
}
?>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="kundenMatrix()" x-init="init()">

    <div class="thx-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-top:8px;">
        <div>
            <p style="margin:0;font-size:var(--d-fs-sm);color:var(--slate-600);max-width:920px;">
                <strong>Wie das wirkt:</strong> Ein Häkchen schaltet einen Kunden frei — entweder für eine
                <strong>ganze Rolle</strong> (alle aktiven User dieser Rolle sehen den Kunden) oder
                <strong>individuell</strong> für eine einzelne Person. Wirkt überall: Chat-Kontext, Wissensdatenbank, Kundenliste, Steckbrief.
                <em style="color:var(--slate-500);">Admin sieht immer alle Kunden — daher hier keine Admin-Spalte.</em>
            </p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="lam-btn lam-btn-secondary" @click="zuruecksetzen()" :disabled="dirty.size === 0">
                <span class="material-symbols-rounded" style="font-size:16px;">undo</span>
                Verwerfen
            </button>
            <button type="button" class="lam-btn lam-btn-primary" id="km-btn-save" @click="speichereAlles()" :disabled="dirty.size === 0">
                Speichern
                <span class="dirty-count" x-show="dirty.size > 0" x-text="dirty.size"></span>
            </button>
        </div>
    </div>

    <!-- Filter-Bar -->
    <div class="lam-card" style="margin-bottom:16px;padding:14px 18px;display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="text" x-model="kundeFilter" class="thx-input" placeholder="Kundensuche…"
                   style="flex:1;min-width:220px;max-width:340px;font-size:var(--d-fs-sm);padding:8px 12px;">

            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-right:2px;">Segment</span>
                <template x-for="t in tagCounts" :key="t.tag">
                    <button type="button"
                            class="km-tag-chip"
                            :class="tagFilter.has(t.tag) ? 'is-active' : ''"
                            @click="toggleTag(t.tag)">
                        <span x-text="t.tag"></span>
                        <span class="km-tag-count" x-text="t.n"></span>
                    </button>
                </template>
                <button type="button"
                        class="km-tag-chip"
                        x-show="tagFilter.size > 0 || kundeFilter"
                        @click="clearTagFilter()"
                        style="background:transparent;color:var(--slate-500);">
                    × zurücksetzen
                </button>
            </div>

            <div style="margin-left:auto;display:flex;gap:8px;align-items:center;font-size:var(--d-fs-xs);color:var(--slate-500);">
                <span x-text="gefilterteKunden.length + ' von ' + alleKunden.length + ' Kunden'"></span>
                <span x-show="gefilterteKunden.length !== alleKunden.length" class="km-filter-info" title="Spalten-Klick wirkt nur auf die gefilterten Kunden">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">filter_alt</span>
                    Bulk wirkt auf gefilterte
                </span>
            </div>
        </div>
    </div>

    <!-- Matrix-Tabelle -->
    <div class="lam-card" style="padding:0;overflow:hidden;">
        <div class="km-scroll-wrap">
            <table class="km-matrix">
                <thead>
                    <tr>
                        <th class="km-corner">
                            <div>Kunde</div>
                            <small style="font-weight:400;color:var(--slate-400);">Klick = ganze Zeile</small>
                        </th>
                        <?php foreach ($alleSpalten as $i => $sp): ?>
                            <th class="km-col km-col-<?= $sp['farbe'] ?>"
                                :class="hoverCol === '<?= $sp['type'] . ':' . $sp['key'] ?>' ? 'is-hover' : ''"
                                @click="toggleSpalte('<?= $sp['type'] . ':' . $sp['key'] ?>')"
                                title="<?= $sp['type'] === 'role' ? 'Alle Manager/User/Guest bekommen Zugriff auf alle Kunden mit Haken' : 'Individuelle Zuweisung an diesen User' ?>">
                                <?php if ($sp['type'] === 'role'): ?>
                                    <div class="km-col-icon"><span class="material-symbols-rounded" style="font-size:14px;">group</span></div>
                                <?php else: ?>
                                    <div class="km-col-icon"><span class="material-symbols-rounded" style="font-size:14px;">person</span></div>
                                <?php endif; ?>
                                <div class="km-col-label"><?= htmlspecialchars($sp['label']) ?></div>
                                <?php if ($sp['type'] === 'user'): ?>
                                    <div class="km-col-meta"><?= htmlspecialchars(ucfirst($sp['role'])) ?></div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="k in gefilterteKunden" :key="k.id">
                        <tr class="km-row">
                            <td class="km-row-head" @click="toggleZeile(k.id)" :title="'Klick: alle Spalten fuer ' + k.name + ' umschalten'">
                                <strong x-text="k.name"></strong>
                                <small class="km-effective-count" x-text="effektivCount(k.id) + ' Zugriffe'"></small>
                            </td>
                            <?php foreach ($alleSpalten as $sp):
                                $colKey = $sp['type'] . ':' . $sp['key'];
                            ?>
                                <td class="km-cell km-col-<?= $sp['farbe'] ?>"
                                    @mouseover="hoverCol = '<?= $colKey ?>'" @mouseleave="hoverCol = null">
                                    <label class="km-toggle">
                                        <input type="checkbox"
                                               :checked="state[k.id]['<?= $colKey ?>']"
                                               @change="setZelle(k.id, '<?= $colKey ?>', $event.target.checked)">
                                        <span class="km-check"></span>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </template>

                    <tr x-show="gefilterteKunden.length === 0">
                        <td :colspan="<?= count($alleSpalten) + 1 ?>" style="text-align:center;padding:32px;color:var(--slate-500);">
                            Keine Kunden gefunden.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="role-hint" style="margin-top:14px;">
        <strong>Tipp:</strong> Spalten-Klick toggelt die ganze Spalte (z.B. „Manager (alle)" → alle Kunden für Manager an/aus).
        Zeilen-Klick (auf den Kunden-Namen) toggelt eine ganze Zeile. Mit dem Save-Button werden nur geänderte Spalten gespeichert.
    </div>

    <!-- Modal: Speichern-Bestätigung -->
    <div class="sync-modal-backdrop" x-show="modal.offen" x-cloak @click.self="modal.offen = false">
        <div class="sync-modal">
            <div class="sync-modal-header">
                <h2>Kundenzuordnung speichern</h2>
                <button type="button" @click="modal.offen = false" aria-label="Schliessen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="sync-modal-body">
                <p style="margin:0 0 12px 0;color:var(--slate-700);">
                    Folgende Änderungen werden gespeichert:
                </p>
                <ul class="km-summary-list">
                    <template x-for="z in modal.zusammenfassung" :key="z.col">
                        <li>
                            <strong x-text="z.label"></strong>
                            <span class="km-summary-meta">
                                <span x-show="z.added > 0" class="km-pill-add">+<span x-text="z.added"></span></span>
                                <span x-show="z.removed > 0" class="km-pill-remove">−<span x-text="z.removed"></span></span>
                                <span x-show="z.added === 0 && z.removed === 0" style="color:var(--slate-400);">keine Veränderung</span>
                            </span>
                        </li>
                    </template>
                </ul>
                <p style="margin-top:14px;font-size:var(--d-fs-xs);color:var(--slate-500);line-height:1.5;">
                    Wirkt sofort — z.B. ein neu freigeschalteter Kunde erscheint beim nächsten Page-Reload in der Kundenliste,
                    im Chat und in der Wissensdatenbank.
                </p>
            </div>
            <div class="sync-modal-footer">
                <button type="button" class="lam-btn lam-btn-secondary" @click="modal.offen = false">Abbrechen</button>
                <button type="button" class="lam-btn lam-btn-primary" @click="doSpeichern()" :disabled="modal.laeuft" x-text="modal.laeuft ? 'Speichere…' : 'Speichern'"></button>
            </div>
        </div>
    </div>

</div>

<style>
.dirty-count {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--amber-500);
    color: #fff;
    font-size: var(--d-fs-xs);
    font-weight: 700;
}

/* Segment-Filter-Chips */
.km-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--slate-100);
    color: var(--slate-700);
    border: 1px solid transparent;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.1s;
}
.km-tag-chip:hover:not(.is-active) {
    background: var(--slate-200);
}
.km-tag-chip.is-active {
    background: var(--thoxan-600);
    color: #fff;
    border-color: var(--thoxan-700);
}
.km-tag-count {
    background: rgba(255,255,255,0.35);
    color: inherit;
    padding: 0 6px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
}
.km-tag-chip:not(.is-active) .km-tag-count {
    background: var(--slate-50);
    color: var(--slate-500);
}

/* Hinweis: Bulk wirkt auf gefilterte */
.km-filter-info {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--amber-50, #fffbeb);
    border: 1px solid var(--amber-200, #fde68a);
    color: var(--amber-800, #92400e);
    padding: 2px 8px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
}

/* Matrix */
.km-scroll-wrap {
    overflow-x: auto;
    max-height: 70vh;
    overflow-y: auto;
}
.km-matrix {
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: var(--d-fs-sm);
}

/* Sticky header + first col */
.km-matrix thead th {
    position: sticky;
    top: 0;
    background: var(--slate-50);
    border-bottom: 2px solid var(--slate-200);
    padding: 8px 10px;
    text-align: center;
    z-index: 2;
    user-select: none;
    cursor: pointer;
    transition: background 0.1s;
}
.km-matrix thead th:hover { background: var(--slate-100); }
.km-matrix thead th.is-hover { background: var(--thoxan-50); }

.km-corner {
    position: sticky;
    left: 0;
    z-index: 3 !important;
    background: var(--slate-100) !important;
    text-align: left !important;
    cursor: default !important;
    min-width: 220px;
    border-right: 1px solid var(--slate-200);
}
.km-corner:hover { background: var(--slate-100) !important; }
.km-corner div { font-weight: 700; color: var(--slate-800); font-size: var(--d-fs-sm); }
.km-corner small { display: block; color: var(--slate-500); font-weight: 400; margin-top: 1px; }

.km-col {
    min-width: 100px;
    max-width: 130px;
}
.km-col-icon { color: var(--slate-500); margin-bottom: 2px; }
.km-col-label { font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-800); line-height: 1.2; }
.km-col-meta {
    font-size: var(--d-fs-xs);
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 2px;
    font-weight: 600;
}
.km-col-thoxan .km-col-label  { color: var(--thoxan-700); }
.km-col-emerald .km-col-label { color: var(--emerald-700); }
.km-col-slate .km-col-label   { color: var(--slate-700); }

/* Tbody */
.km-matrix tbody tr.km-row td {
    border-bottom: 1px solid var(--slate-100);
    padding: 6px 10px;
    text-align: center;
}
.km-matrix tbody tr.km-row:hover td { background: rgba(241, 245, 249, 0.5); }
.km-matrix tbody tr.km-row:last-child td { border-bottom: none; }

.km-row-head {
    position: sticky;
    left: 0;
    background: #fff;
    text-align: left !important;
    cursor: pointer;
    border-right: 1px solid var(--slate-200) !important;
    user-select: none;
    z-index: 1;
}
.km-row-head:hover { background: var(--slate-50); }
.km-row-head strong { display: block; font-size: var(--d-fs-sm); color: var(--slate-900); }
.km-row-head .km-effective-count {
    display: block;
    font-size: var(--d-fs-xs);
    color: var(--slate-500);
    margin-top: 1px;
}

.km-matrix tbody tr.km-row:hover .km-row-head { background: var(--slate-50); }

/* Zell-Hintergrund leicht eingefaerbt nach Spalte */
td.km-cell.km-col-thoxan:has(input:checked)  { background: rgba(191, 219, 254, 0.45); }
td.km-cell.km-col-emerald:has(input:checked) { background: rgba(187, 247, 208, 0.45); }
td.km-cell.km-col-slate:has(input:checked)   { background: rgba(226, 232, 240, 0.5); }

/* Custom-Check */
.km-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 4px;
}
.km-toggle input { display: none; }
.km-check {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    border: 1.5px solid var(--slate-300);
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.1s;
}
.km-check::after {
    content: '';
    width: 11px;
    height: 6px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: rotate(-45deg) translate(1px, -1px);
    opacity: 0;
    transition: opacity 0.1s;
}
.km-toggle input:checked + .km-check {
    background: var(--thoxan-600);
    border-color: var(--thoxan-600);
}
.km-toggle input:checked + .km-check::after { opacity: 1; }

.role-hint {
    margin-top: 16px;
    padding: 12px 16px;
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
    line-height: 1.5;
}
.role-hint strong { color: var(--slate-800); }

/* Save-Modal (shared style) */
.sync-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 16px;
}
.sync-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.35);
}
.sync-modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: center; justify-content: space-between;
}
.sync-modal-header h2 { margin: 0; font-size: var(--d-fs-lg); font-weight: 700; }
.sync-modal-header button {
    background: none; border: none; cursor: pointer; padding: 4px;
    color: var(--slate-500); border-radius: 6px; display:inline-flex;
}
.sync-modal-header button:hover { background: var(--slate-100); }
.sync-modal-body { padding: 22px; overflow-y: auto; }
.sync-modal-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--slate-200);
    background: var(--slate-50);
    display: flex; gap: 10px; justify-content: flex-end;
    border-radius: 0 0 12px 12px;
}

.km-summary-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.km-summary-list li {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 12px;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    font-size: var(--d-fs-sm);
}
.km-summary-meta { display: flex; gap: 6px; align-items: center; font-size: var(--d-fs-xs); }
.km-pill-add {
    background: var(--emerald-100); color: var(--emerald-800);
    padding: 2px 8px; border-radius: 999px; font-weight: 700;
}
.km-pill-remove {
    background: var(--rose-100); color: var(--rose-800);
    padding: 2px 8px; border-radius: 999px; font-weight: 700;
}

[x-cloak] { display: none !important; }
</style>

<script>
const KM_ALL_CUSTOMERS = <?= json_encode(array_map(fn($c) => [
    'id' => (int)$c['id'],
    'name' => $c['name'],
    'tags' => $c['tags'] ?? [],
], $customers), JSON_UNESCAPED_UNICODE) ?>;
const KM_COLUMNS       = <?= json_encode($alleSpalten, JSON_UNESCAPED_UNICODE) ?>;
const KM_INITIAL_STATE = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE) ?>;

// Alle Tags sammeln + Haeufigkeit fuer die Filter-Chips
const KM_TAG_COUNTS = (function() {
    const counts = {};
    for (const k of KM_ALL_CUSTOMERS) {
        for (const t of (k.tags || [])) counts[t] = (counts[t] || 0) + 1;
    }
    return Object.entries(counts)
        .sort((a, b) => b[1] - a[1])
        .map(([tag, n]) => ({ tag, n }));
})();

// Tiefes Klonen ohne structuredClone (Alpine-Proxies waeren nicht clonebar).
function kmClone(obj) { return JSON.parse(JSON.stringify(obj)); }

function kundenMatrix() {
    return {
        alleKunden: KM_ALL_CUSTOMERS,
        spalten: KM_COLUMNS,
        tagCounts: KM_TAG_COUNTS,
        state: kmClone(KM_INITIAL_STATE),
        initialState: kmClone(KM_INITIAL_STATE),
        kundeFilter: '',
        tagFilter: new Set(),   // multi-select: Set von Tag-Strings
        hoverCol: null,
        dirty: new Set(),  // Set von 'role:manager', 'user:5', etc.
        modal: { offen: false, zusammenfassung: [], laeuft: false },

        init() {},

        get gefilterteKunden() {
            const q = this.kundeFilter.toLowerCase().trim();
            const tags = this.tagFilter;
            return this.alleKunden.filter(k => {
                if (q && !k.name.toLowerCase().includes(q)) return false;
                if (tags.size > 0) {
                    // Kunde muss MINDESTENS EINEN der ausgewaehlten Tags haben (OR)
                    const ks = new Set(k.tags || []);
                    let match = false;
                    for (const t of tags) if (ks.has(t)) { match = true; break; }
                    if (!match) return false;
                }
                return true;
            });
        },

        toggleTag(tag) {
            if (this.tagFilter.has(tag)) this.tagFilter.delete(tag);
            else this.tagFilter.add(tag);
            this.tagFilter = new Set(this.tagFilter);
        },
        clearTagFilter() {
            this.tagFilter = new Set();
            this.kundeFilter = '';
        },

        setZelle(kundenId, colKey, neu) {
            this.state[kundenId][colKey] = neu;
            // Pruefen ob diese Spalte jetzt vom Initial-State abweicht
            this.updateDirtyForCol(colKey);
        },
        updateDirtyForCol(colKey) {
            let differs = false;
            for (const kid in this.state) {
                if ((this.state[kid][colKey] || false) !== (this.initialState[kid][colKey] || false)) {
                    differs = true; break;
                }
            }
            if (differs) this.dirty.add(colKey);
            else this.dirty.delete(colKey);
            // Vue-style Set-Reactivity: Trigger
            this.dirty = new Set(this.dirty);
        },

        toggleSpalte(colKey) {
            // Wenn alle in der Spalte (gefiltert) bereits gesetzt → alle aus, sonst alle an
            const ids = this.gefilterteKunden.map(k => k.id);
            const allOn = ids.every(id => this.state[id][colKey]);
            const ziel = !allOn;
            ids.forEach(id => this.state[id][colKey] = ziel);
            this.updateDirtyForCol(colKey);
        },

        toggleZeile(kundenId) {
            // Alle Spalten dieser Zeile umschalten — basierend auf der „Mehrheit"
            const colKeys = this.spalten.map(c => c.type + ':' + c.key);
            const allOn = colKeys.every(ck => this.state[kundenId][ck]);
            const ziel = !allOn;
            colKeys.forEach(ck => {
                this.state[kundenId][ck] = ziel;
                this.updateDirtyForCol(ck);
            });
        },

        effektivCount(kundenId) {
            // Zaehlt, wie viele „Zugriffe" der Kunde aktuell hat (Rolle-Anzahl wird hier nur als 1 gezaehlt)
            return Object.values(this.state[kundenId]).filter(v => v).length;
        },

        zuruecksetzen() {
            if (this.dirty.size === 0) return;
            if (!confirm('Ungespeicherte Änderungen verwerfen?')) return;
            this.state = kmClone(this.initialState);
            this.dirty = new Set();
        },

        // === Speichern ===
        speichereAlles() {
            if (this.dirty.size === 0) return;
            // Modal-Inhalt: pro Spalte +/- Counter
            const zsm = [];
            const sortedDirty = Array.from(this.dirty).sort();
            for (const colKey of sortedDirty) {
                let added = 0, removed = 0;
                for (const kid in this.state) {
                    const before = !!this.initialState[kid][colKey];
                    const now    = !!this.state[kid][colKey];
                    if (!before && now) added++;
                    if (before && !now) removed++;
                }
                const meta = this.spalten.find(c => (c.type + ':' + c.key) === colKey);
                zsm.push({
                    col: colKey,
                    label: meta ? meta.label : colKey,
                    added, removed,
                });
            }
            this.modal.zusammenfassung = zsm;
            this.modal.offen = true;
        },

        async doSpeichern() {
            this.modal.laeuft = true;
            // Payload: alle dirty Spalten mit der finalen customer_id-Liste
            const roles = {}, users = {};
            for (const colKey of this.dirty) {
                const [type, key] = colKey.split(':');
                const ids = [];
                for (const kid in this.state) {
                    if (this.state[kid][colKey]) ids.push(parseInt(kid, 10));
                }
                if (type === 'role') roles[key] = ids;
                else users[parseInt(key, 10)] = ids;
            }
            try {
                const resp = await App.post('/admin/user-customer-mapping', { roles, users });
                if (resp.success) {
                    App.showNotification('Zuordnung gespeichert', 'success');
                    // Neuer Initial-State = aktueller State
                    this.initialState = kmClone(this.state);
                    this.dirty = new Set();
                    this.modal.offen = false;
                } else {
                    App.showNotification(resp.message || 'Fehler', 'error');
                }
            } catch (e) {
                App.showNotification(e.message || 'Verbindungsfehler', 'error');
            } finally {
                this.modal.laeuft = false;
            }
        },
    };
}
</script>
