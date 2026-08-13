<?php /** @var array $title */ ?>
<style>
.sm-page { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }
.sm-info {
    background: var(--thoxan-50); border: 1px solid var(--thoxan-200); border-radius: 8px;
    padding: 10px 14px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px; font-size: var(--d-fs-sm);
}
.sm-info .material-symbols-rounded { color: var(--thoxan-600); font-size: 18px; }

.sm-toolbar {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 10px 14px; margin-bottom: 14px;
}
.sm-search {
    flex: 1; min-width: 200px;
    padding: 6px 10px; border: 1px solid var(--slate-200); border-radius: 6px;
    font-size: var(--d-fs-sm); background: var(--slate-50);
}
.sm-search:focus { outline: none; border-color: var(--thoxan-400); background: #fff; }
.sm-view-toggle {
    display: inline-flex; border: 1px solid var(--slate-200); border-radius: 6px; overflow: hidden;
}
.sm-view-btn {
    background: #fff; border: none; padding: 6px 12px;
    cursor: pointer; color: var(--slate-500); font-family: inherit;
}
.sm-view-btn.is-active { background: var(--thoxan-600); color: #fff; }

.sm-filters {
    display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px;
}
.sm-filter-chip {
    background: #fff; color: var(--slate-700);
    border: 1px solid var(--slate-200); border-radius: 14px;
    padding: 4px 12px; font-size: var(--d-fs-xs); cursor: pointer; font-family: inherit;
}
.sm-filter-chip:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); }
.sm-filter-chip.is-active {
    background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600);
}

/* Grid */
.sm-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
}
.sm-card {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 14px; transition: box-shadow 0.15s;
    display: flex; flex-direction: column; gap: 10px;
}
.sm-card:hover { box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); }
.sm-card.is-down { border-color: var(--rose-300); background: rgba(244, 63, 94, 0.04); }
.sm-card.is-paused { opacity: 0.55; }

.sm-card-top {
    display: flex; align-items: center; justify-content: space-between;
}
.sm-card-status {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: var(--d-fs-xs); font-weight: 700;
}
.sm-card-status .dot {
    width: 8px; height: 8px; border-radius: 50%;
}
.sm-card.is-up    .sm-card-status .dot { background: var(--emerald-500); }
.sm-card.is-down  .sm-card-status .dot { background: var(--rose-500); }
.sm-card.is-paused .sm-card-status .dot { background: var(--slate-400); }
.sm-card.is-up    .sm-card-status { color: var(--emerald-700); }
.sm-card.is-down  .sm-card-status { color: var(--rose-700); }
.sm-card.is-paused .sm-card-status { color: var(--slate-500); }

.sm-card-actions {
    display: flex; gap: 2px; opacity: 0; transition: opacity 0.15s;
}
.sm-card:hover .sm-card-actions { opacity: 1; }
.sm-card-actions button, .sm-card-actions a {
    background: transparent; border: none; color: var(--slate-400);
    cursor: pointer; padding: 4px; border-radius: 4px;
    display: inline-flex; text-decoration: none;
}
.sm-card-actions button:hover { background: var(--slate-100); color: var(--slate-700); }
.sm-card-actions .is-delete:hover { color: var(--rose-600); }

.sm-card-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.sm-card-logo {
    width: 40px; height: 40px; border-radius: 8px;
    background: #fff; border: 1px solid var(--slate-200);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.sm-card-logo img { width: 100%; height: 100%; object-fit: contain; }
.sm-card-abbr {
    font-size: var(--d-fs-xs); font-weight: 700; color: var(--slate-700);
    letter-spacing: 0.04em;
}
.sm-card-head-text { min-width: 0; flex: 1; }
.sm-card-label { font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-800); margin: 0; }
.sm-card-url { font-size: var(--d-fs-xs); color: var(--slate-500); word-break: break-all; }
.sm-card-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.sm-tag {
    display: inline-block; padding: 1px 7px; border-radius: 10px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.sm-tag-cust { background: var(--thoxan-50); color: var(--thoxan-800); }
.sm-tag-cat { background: var(--slate-100); color: var(--slate-600); }

.sm-card-stats {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 8px; padding-top: 8px; border-top: 1px solid var(--slate-100);
}
.sm-stat-val {
    font-family: ui-monospace, monospace; font-size: var(--d-fs-base);
    font-weight: 700; color: var(--slate-800);
}
.sm-stat-val.is-warn { color: var(--amber-600); }
.sm-stat-val.is-down { color: var(--rose-600); }
.sm-stat-lbl { font-size: 10px; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.04em; }

/* List */
.sm-list {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px; overflow: hidden;
}
.sm-list table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); }
.sm-list th {
    text-align: left; padding: 10px 12px;
    font-size: 10px; text-transform: uppercase; color: var(--slate-500); font-weight: 600;
    border-bottom: 2px solid var(--slate-200); background: var(--slate-50);
}
.sm-list th.is-right { text-align: right; }
.sm-list td { padding: 10px 12px; border-bottom: 1px solid var(--slate-100); }
.sm-list td.is-right { text-align: right; font-family: ui-monospace, monospace; }
.sm-list tr:hover td { background: var(--slate-50); }

.sm-bulk-bar {
    background: var(--thoxan-50); border: 1px solid var(--thoxan-200); border-radius: 8px;
    padding: 8px 14px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}

/* Modal-Bodies (THX-Modal als Wrapper) */
.sm-mb .field { margin-bottom: 12px; }
.sm-mb label { display: block; font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-600); margin-bottom: 4px; }
.sm-mb input, .sm-mb select, .sm-mb textarea {
    width: 100%; padding: 8px 10px; border: 1px solid var(--slate-200);
    border-radius: 6px; font-size: var(--d-fs-sm); font-family: inherit;
}
.sm-mb textarea { resize: vertical; min-height: 90px; }

.sm-cron-box {
    background: var(--slate-50); padding: 12px; border-radius: 6px;
    margin-top: 10px; font-size: var(--d-fs-xs);
}
.sm-cron-box input { width: 100%; padding: 6px 8px; font-family: ui-monospace, monospace; font-size: 11px; border: 1px solid var(--slate-200); border-radius: 4px; }
</style>

<div class="sm-page">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">
                <span class="material-symbols-rounded" style="vertical-align:middle;font-size:24px;color:var(--thoxan-600);">monitoring</span>
                Website-Monitor
            </h1>
            <p class="thx-page-subtitle">Uptime + Response-Time aller Websites. Cron alle 2 Min., Alert ab 2 Fails in Folge, Recovery-Mail bei Wiederkehr.</p>
        </div>
        <div class="thx-page-actions">
            <button class="thx-btn thx-btn-secondary" onclick="smOpenCronModal()" title="Cron-URL">
                <span class="material-symbols-rounded" style="font-size:16px;">link</span>
                Cron-Info
            </button>
            <button class="thx-btn thx-btn-secondary" onclick="smTestReport()">
                <span class="material-symbols-rounded" style="font-size:16px;">mail</span>
                Test-Report
            </button>
            <button class="thx-btn thx-btn-secondary" onclick="smOpenBatchModal()">
                <span class="material-symbols-rounded" style="font-size:16px;">cloud_upload</span>
                Batch-Import
            </button>
            <button class="thx-btn thx-btn-secondary" onclick="smOpenImportModal()" title="JSON-Import aus Tallyr">
                <span class="material-symbols-rounded" style="font-size:16px;">upload_file</span>
                Tallyr-JSON
            </button>
            <button class="thx-btn thx-btn-secondary" onclick="smOpenMailLog()" title="E-Mail-Log">
                <span class="material-symbols-rounded" style="font-size:16px;">drafts</span>
                Mail-Log
            </button>
            <button class="thx-btn thx-btn-primary" onclick="smOpenAddModal()">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                Website hinzufügen
            </button>
        </div>
    </div>

    <div class="sm-info">
        <span class="material-symbols-rounded">info</span>
        <span>Alle aktiven Websites werden alle 2 Minuten geprüft. Bei 2 fehlgeschlagenen Checks in Folge wird eine Alert-Mail gesendet. Sobald wieder erreichbar, kommt eine Recovery-Mail.</span>
    </div>

    <?php $_embedded = !empty($embeddedInMaster); ?>
    <div class="sm-toolbar">
        <input type="text" class="sm-search" id="sm-search" placeholder="Suchen (Label oder URL)…" oninput="smRender()">
        <?php if (!$_embedded): ?>
        <select id="sm-customer-filter" class="sm-search" style="max-width:200px;" onchange="smRender()">
            <option value="">Alle Kunden</option>
        </select>
        <select id="sm-status-filter" class="sm-search" style="max-width:140px;" onchange="smRender()">
            <option value="">Alle Status</option>
            <option value="up">✓ Online</option>
            <option value="down">⚠ Offline</option>
            <option value="paused">⏸ Pausiert</option>
        </select>
        <?php else: ?>
        <!-- Im Embed-Modus: Customer + Status kommen aus der Sidebar (cm-filter-changed Event) -->
        <input type="hidden" id="sm-customer-filter" value="">
        <input type="hidden" id="sm-status-filter" value="">
        <?php endif; ?>
        <div id="sm-range-toolbar" style="display:flex;gap:4px;align-items:center;border-left:1px solid var(--slate-200);padding-left:8px;margin-left:4px;"
             title="Zeitraum fuer das Statistik-Modal — wird gemerkt">
            <span style="font-size:11px;color:var(--slate-500);margin-right:2px;">Zeitraum:</span>
            <!-- Buttons per JS, damit der aktive Wert aus localStorage gesetzt ist -->
        </div>
        <div class="sm-view-toggle">
            <button class="sm-view-btn is-active" data-view="grid" onclick="smSetView('grid')" title="Kacheln">
                <span class="material-symbols-rounded" style="font-size:16px;">grid_view</span>
            </button>
            <button class="sm-view-btn" data-view="list" onclick="smSetView('list')" title="Liste">
                <span class="material-symbols-rounded" style="font-size:16px;">view_list</span>
            </button>
        </div>
    </div>

    <?php if (!$_embedded): ?>
    <div class="sm-filters" id="sm-cat-filters"></div>
    <?php else: ?>
    <!-- Im Embed-Modus kommen Kategorie-Filter aus der Sidebar -->
    <input type="hidden" id="sm-cat-filter" value="">
    <?php endif; ?>

    <div class="sm-bulk-bar" id="sm-bulk-bar" style="display:none;">
        <span><strong id="sm-bulk-count">0</strong> ausgewählt</span>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smBulkCategory()">Kategorie zuweisen</button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smBulkClear()">Auswahl löschen</button>
    </div>

    <div id="sm-loading" style="text-align:center;padding:40px;color:var(--slate-400);">
        <span class="material-symbols-rounded" style="font-size:24px;animation:pp-spin 1s linear infinite;">refresh</span>
        Lade…
    </div>
    <div id="sm-content"></div>
</div>

<!-- Add/Edit Modal -->
<div class="thx-modal-backdrop" id="sm-add-modal" style="display:none;" onclick="if(event.target===this)smCloseModal('sm-add-modal')">
    <div class="thx-modal" style="width:560px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title" id="sm-add-title">Website hinzufügen</h3>
            <button class="thx-modal-close" onclick="smCloseModal('sm-add-modal')">&times;</button>
        </div>
        <div class="thx-modal-body sm-mb">
            <input type="hidden" id="sm-edit-id" value="0">
            <div class="field">
                <label>URL *</label>
                <div style="display:flex;gap:6px;">
                    <input type="url" id="sm-url" placeholder="https://example.com" required>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smFetchTitle()" title="Title aus Seite laden">
                        <span class="material-symbols-rounded" style="font-size:14px;">search</span>
                    </button>
                </div>
            </div>
            <div class="field">
                <label>Label *</label>
                <input type="text" id="sm-label" placeholder="Wird automatisch geladen oder manuell" required>
            </div>
            <div class="field">
                <label>Kunde</label>
                <select id="sm-customer"><option value="">— Kein Kunde —</option></select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="field">
                    <label>Kategorie</label>
                    <input type="text" id="sm-category" placeholder="z.B. Kundenprojekte, Portale">
                </div>
                <div class="field">
                    <label>Report-Schedule</label>
                    <select id="sm-report">
                        <option value="both" selected>Wöchentlich + Monatlich</option>
                        <option value="weekly">Nur Wöchentlich</option>
                        <option value="monthly">Nur Monatlich</option>
                        <option value="none">Kein Report</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label>Alert-E-Mail (leer = Default-Admin-Mail)</label>
                <input type="email" id="sm-alert-email" placeholder="alerts@example.com">
            </div>
            <div class="field">
                <label>Sub-URLs (eine pro Zeile, werden zusätzlich geprüft)</label>
                <textarea id="sm-sub-urls" placeholder="https://example.com/login&#10;https://example.com/api/status"></textarea>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="smCloseModal('sm-add-modal')">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="smSave()">Speichern</button>
        </div>
    </div>
</div>

<!-- Batch-Import Modal -->
<div class="thx-modal-backdrop" id="sm-batch-modal" style="display:none;" onclick="if(event.target===this)smCloseModal('sm-batch-modal')">
    <div class="thx-modal" style="width:560px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Batch-Import</h3>
            <button class="thx-modal-close" onclick="smCloseModal('sm-batch-modal')">&times;</button>
        </div>
        <div class="thx-modal-body sm-mb">
            <div class="field">
                <label>URLs (eine pro Zeile)</label>
                <textarea id="sm-batch-urls" rows="10" placeholder="https://www.example.com&#10;https://www.test.de"></textarea>
                <div style="font-size:11px;color:var(--slate-400);margin-top:4px;">Title wird automatisch aus jeder Seite geladen.</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                <div class="field">
                    <label>Kunde</label>
                    <select id="sm-batch-customer"><option value="">— Kein Kunde —</option></select>
                </div>
                <div class="field">
                    <label>Kategorie</label>
                    <input type="text" id="sm-batch-category" placeholder="optional">
                </div>
                <div class="field">
                    <label>Report</label>
                    <select id="sm-batch-report">
                        <option value="both" selected>Beide</option>
                        <option value="weekly">Wöchentlich</option>
                        <option value="monthly">Monatlich</option>
                        <option value="none">Kein</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="smCloseModal('sm-batch-modal')">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="smBatchImport()">Importieren</button>
        </div>
    </div>
</div>

<!-- Cron-Info Modal -->
<div class="thx-modal-backdrop" id="sm-cron-modal" style="display:none;" onclick="if(event.target===this)smCloseModal('sm-cron-modal')">
    <div class="thx-modal" style="width:560px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Cron-Info</h3>
            <button class="thx-modal-close" onclick="smCloseModal('sm-cron-modal')">&times;</button>
        </div>
        <div class="thx-modal-body">
            <p style="font-size:var(--d-fs-sm);color:var(--slate-700);">
                Der Site-Monitor läuft als System-Cron alle 2 Minuten:
            </p>
            <div class="sm-cron-box">
                <code style="font-family:ui-monospace,monospace;font-size:12px;">/etc/cron.d/ki-tool-site-monitor</code><br>
                <code style="display:block;margin-top:4px;font-family:ui-monospace,monospace;font-size:11px;">*/2 * * * * www-data php /var/www/scripts/pm-check.php &gt;&gt; /var/log/ki-tool-pm-check.log 2&gt;&amp;1</code>
            </div>
            <p style="font-size:var(--d-fs-sm);color:var(--slate-700);margin-top:14px;">
                Manueller Test (als root oder www-data):
            </p>
            <div class="sm-cron-box">
                <code style="font-family:ui-monospace,monospace;font-size:11px;">sudo -u www-data php /var/www/scripts/pm-check.php --verbose</code>
            </div>
            <p style="font-size:var(--d-fs-sm);color:var(--slate-700);margin-top:14px;">
                Log einsehen:
            </p>
            <div class="sm-cron-box">
                <code style="font-family:ui-monospace,monospace;font-size:11px;">tail -f /var/log/ki-tool-pm-check.log</code>
            </div>
        </div>
    </div>
</div>

<!-- Tallyr-Import Modal -->
<div class="thx-modal-backdrop" id="sm-import-modal" style="display:none;" onclick="if(event.target===this)smCloseModal('sm-import-modal')">
    <div class="thx-modal" style="width:680px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Site-Monitor-Import aus Tallyr</h3>
            <button class="thx-modal-close" onclick="smCloseModal('sm-import-modal')">&times;</button>
        </div>
        <div class="thx-modal-body sm-mb">
            <div style="background:var(--thoxan-50);border:1px solid var(--thoxan-200);border-radius:8px;padding:12px;margin-bottom:14px;font-size:var(--d-fs-sm);color:var(--thoxan-800);">
                <strong>Einmal-Setup in Tallyr (15 Zeilen Code):</strong>
                <div style="font-size:var(--d-fs-xs);margin-top:6px;line-height:1.6;">
                    Beim Projektplanner ist der Export-Endpoint bereits in Tallyr eingebaut — beim Site-Monitor noch nicht.
                    Das Snippet rechts <strong>einmal</strong> in eine <code>functions.php</code> Deines Tallyr-Childthemes pasten,
                    danach gleicher Workflow wie beim Projektplanner: URL aufrufen, JSON herunterladen, hier hochladen.<br>
                    <strong>Für die Verlaufs-Historie (Logs + Incidents):</strong> URL um <code>&amp;history=1&amp;days=90</code> erweitern.
                </div>
                <div style="margin-top:10px;display:flex;gap:6px;">
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="smToggleSnippet()">
                        <span class="material-symbols-rounded" style="font-size:14px;">code</span>
                        Snippet anzeigen
                    </button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smCopySnippet()">
                        <span class="material-symbols-rounded" style="font-size:14px;">content_copy</span>
                        Snippet kopieren
                    </button>
                </div>
                <pre id="sm-import-snippet" style="display:none;background:var(--slate-900);color:var(--slate-200);padding:12px;border-radius:6px;margin-top:10px;font-family:ui-monospace,monospace;font-size:11px;overflow:auto;line-height:1.5;"><?php echo htmlspecialchars(file_get_contents(ROOT_PATH . '/docs/tallyr-site-monitor-export-mini.php'), ENT_QUOTES, 'UTF-8'); ?></pre>
                <div style="font-size:var(--d-fs-xs);margin-top:8px;color:var(--thoxan-700);">
                    <strong>URLs nach dem Einbau:</strong><br>
                    Nur Monitore: <code>https://&lt;tallyr-host&gt;/?tallyr_monitor_export=v2&amp;key=&lt;SECRET&gt;</code><br>
                    Mit Historie (7 Tage): <code>…&amp;history=1&amp;days=7</code><br>
                    Mit Historie (30 Tage): <code>…&amp;history=1&amp;days=30</code><br>
                    <em>SECRET = `tallyr_monitor_cron_key` aus `wp_options`. Snippet v2 nutzt den eindeutigen Pfad <code>tallyr_monitor_export=v2</code>, kollidiert nicht mit der alten Version.</em>
                </div>
            </div>
            <div id="sm-import-step1">
                <div class="field">
                    <label>JSON-Datei hier hochladen</label>
                    <input type="file" id="sm-import-file" accept="application/json,.json" onchange="smImportPreview()">
                </div>
                <div id="sm-import-preview-result"></div>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="smCloseModal('sm-import-modal')">Schließen</button>
            <button class="thx-btn thx-btn-primary" id="sm-import-go" onclick="smImportDo()" style="display:none;">Import starten</button>
        </div>
    </div>
</div>

<!-- Mail-Log Modal -->
<div class="thx-modal-backdrop" id="sm-mail-log-modal" style="display:none;" onclick="if(event.target===this)smCloseModal('sm-mail-log-modal')">
    <div class="thx-modal" style="width:720px;max-width:96vw;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">E-Mail-Log (letzte 100 Einträge)</h3>
            <button class="thx-modal-close" onclick="smCloseModal('sm-mail-log-modal')">&times;</button>
        </div>
        <div class="thx-modal-body" id="sm-mail-log-body" style="max-height:70vh;overflow-y:auto;background:var(--slate-900);color:var(--slate-200);font-family:ui-monospace,monospace;font-size:12px;padding:14px;border-radius:0 0 8px 8px;"></div>
    </div>
</div>

<!-- Stats Modal (per Monitor-Card-Klick) -->
<!-- Stats-Modal: wird vom Partial geliefert (gemeinsam mit /admin/customers) -->
<?php include __DIR__ . '/_sm_stats_modal.php'; ?>

<script>
'use strict';
const smState = {
    monitors: [], customers: [], categories: [],
    view: localStorage.getItem('sm_view') || 'grid',
    selected: new Set(),
};

function smEsc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function smInit() {
    try {
        const [mRes, cRes, catRes] = await Promise.all([
            fetch('/api/v1/admin/site-monitor').then(r => r.json()),
            fetch('/api/v1/admin/customers').then(r => r.json()),
            fetch('/api/v1/admin/site-monitor/categories').then(r => r.json()),
        ]);
        smState.monitors = mRes.success ? (mRes.data.monitors || []) : [];
        // Alle Customers behalten (inkl. inaktive) — damit die Sidebar-Filter sauber arbeiten
        smState.customers = (cRes.data || []);
        smState.customersActive = smState.customers.filter(c => c.is_active);
        smState.categories = catRes.success ? (catRes.data.categories || []) : [];

        // Customer-Filter füllen
        const custSel = document.getElementById('sm-customer-filter');
        custSel.innerHTML = '<option value="">Alle Kunden</option>' +
            smState.customersActive.map(c => `<option value="${c.id}">${smEsc(c.name)}</option>`).join('');
        // Add-Modal-Selects füllen
        for (const id of ['sm-customer', 'sm-batch-customer']) {
            document.getElementById(id).innerHTML = '<option value="">— Kein Kunde —</option>' +
                smState.customersActive.map(c => `<option value="${c.id}">${smEsc(c.name)}</option>`).join('');
        }
        document.getElementById('sm-loading').style.display = 'none';

        // Deep-Link aus URL: ?customer_id=X setzt den Filter, ?openStats=ID oeffnet das Stats-Modal direkt
        const qs = new URLSearchParams(window.location.search);
        const qCust = qs.get('customer_id');
        if (qCust && document.getElementById('sm-customer-filter')) {
            document.getElementById('sm-customer-filter').value = qCust;
        }
        smRender();
        smRenderCategoryFilters();
        smRenderRangeToolbar();
        const qOpen = parseInt(qs.get('openStats') || '0', 10);
        if (qOpen > 0 && typeof smOpenStats === 'function') {
            setTimeout(() => smOpenStats(qOpen), 100);
        }
    } catch (e) {
        document.getElementById('sm-loading').textContent = 'Fehler: ' + e.message;
    }
}

function smSetView(v) {
    smState.view = v;
    localStorage.setItem('sm_view', v);
    document.querySelectorAll('.sm-view-btn').forEach(b => b.classList.toggle('is-active', b.dataset.view === v));
    smRender();
}

// Zeitraum-Toolbar: rendert die Range-Chips synchron mit smGetPreferredDays (Partial).
const SM_RANGES = [
    { d: 7,   label: '7 T' },
    { d: 30,  label: '30 T' },
    { d: 90,  label: 'Quartal' },
    { d: 365, label: 'Jahr' },
];
function smRenderRangeToolbar() {
    const wrap = document.getElementById('sm-range-toolbar');
    if (!wrap) return;
    const current = (typeof smGetPreferredDays === 'function') ? smGetPreferredDays() : 30;
    // Label-Span behalten + Chips ans Ende
    const labelSpan = wrap.querySelector('span');
    wrap.innerHTML = '';
    if (labelSpan) wrap.appendChild(labelSpan);
    SM_RANGES.forEach(r => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = r.label;
        const active = r.d === current;
        btn.style.cssText = 'padding:3px 9px;font-size:11px;border:1px solid '
            + (active ? 'var(--thoxan-700)' : 'var(--slate-200)')
            + ';background:' + (active ? 'var(--thoxan-50)' : '#fff')
            + ';color:' + (active ? 'var(--thoxan-800)' : 'var(--slate-600)')
            + ';border-radius:5px;cursor:pointer;font-weight:' + (active ? 600 : 500) + ';';
        btn.onclick = () => {
            try { localStorage.setItem('thx_sm_stats_days', String(r.d)); } catch (_) {}
            smRenderRangeToolbar();
            // Wenn Modal gerade offen: auf neuen Zeitraum umschalten
            if (typeof smChangeRange === 'function' && document.getElementById('sm-stats-modal')?.style.display === 'flex') {
                smChangeRange(r.d);
            }
        };
        wrap.appendChild(btn);
    });
}

let smActiveCategory = '';
function smRenderCategoryFilters() {
    const wrap = document.getElementById('sm-cat-filters');
    if (!smState.categories.length) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = `
        <button class="sm-filter-chip ${smActiveCategory === '' ? 'is-active' : ''}" onclick="smSetCategory('')">Alle (${smState.monitors.length})</button>
        ${smState.categories.map(c => `
            <button class="sm-filter-chip ${smActiveCategory === c.category ? 'is-active' : ''}" onclick="smSetCategory('${smEsc(c.category).replace(/'/g,"\\'")}')">${smEsc(c.category)} (${c.cnt})</button>
        `).join('')}`;
}
function smSetCategory(cat) {
    smActiveCategory = cat;
    smRenderCategoryFilters();
    smRender();
}

function smFilteredMonitors() {
    const search = (document.getElementById('sm-search').value || '').toLowerCase();
    const cust = document.getElementById('sm-customer-filter').value;
    const status = document.getElementById('sm-status-filter').value;
    // Zusatz-Filter aus Sidebar (Embed-Modus): customer.is_active + customer.tags + Kategorie
    const sb = window.smSidebarFilters || { status: '', tags: [], monStatus: '', monCat: '' };
    // Customer-Lookup fuer is_active/tags
    const cMap = {};
    (smState.customers || []).forEach(c => { cMap[c.id] = c; });
    return smState.monitors.filter(m => {
        if (smActiveCategory && (m.category || '') !== smActiveCategory) return false;
        if (search && !`${m.label || ''} ${m.url || ''}`.toLowerCase().includes(search)) return false;
        if (cust && (m.customer_id || 0) != cust) return false;
        if (status && m.status !== status) return false;
        // Sidebar-Status (up/down/paused)
        if (sb.monStatus && m.status !== sb.monStatus) return false;
        // Sidebar-Kategorie: matcht jetzt Customer-Tags (Multi-Tag: ein Monitor taucht in jedem Filter auf, den der Customer hat)
        if (sb.monCat) {
            const cTags = (m.customer_tags || []).map(t => String(t).toLowerCase());
            if (!cTags.includes(sb.monCat.toLowerCase())) return false;
        }
        // Sidebar-Customer-Status (aktiv/inaktiv des Kunden)
        if (sb.status) {
            const c = cMap[m.customer_id];
            if (!c) return false;
            const isActive = c.is_active ? 'active' : 'inactive';
            if (isActive !== sb.status) return false;
        }
        // Sidebar-Customer-Tags
        if (sb.tags && sb.tags.length) {
            const c = cMap[m.customer_id];
            if (!c) return false;
            const cTags = (c.tags || []).map(t => String(t).toLowerCase());
            for (const tag of sb.tags) {
                if (!cTags.includes(String(tag).toLowerCase())) return false;
            }
        }
        return true;
    });
}

// Event-Bridge: Sidebar-Filter -> Monitor neu rendern
document.addEventListener('cm-filter-changed', (e) => {
    window.smSidebarFilters = e.detail || {};
    if (typeof smRender === 'function') smRender();
});

function smRender() {
    const list = smFilteredMonitors();
    const content = document.getElementById('sm-content');
    if (!list.length) {
        content.innerHTML = '<div style="text-align:center;padding:60px 20px;color:var(--slate-400);">Keine Websites entsprechen den Filtern.</div>';
        return;
    }
    content.innerHTML = smState.view === 'grid' ? smRenderGrid(list) : smRenderList(list);
    smUpdateBulkBar();
}

function smRenderGrid(list) {
    return `<div class="sm-grid">${list.map(smRenderCard).join('')}</div>`;
}

function smRenderCard(m) {
    const checked = smState.selected.has(m.id);
    const uptime = parseFloat(m.uptime_24h || 100);
    const upClass = uptime >= 99 ? '' : (uptime >= 95 ? 'is-warn' : 'is-down');
    const lastCheck = m.last_check ? smRelative(m.last_check) : '—';
    return `
    <div class="sm-card is-${m.status}" data-id="${m.id}">
        <div class="sm-card-top">
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="checkbox" ${checked ? 'checked' : ''} onclick="event.stopPropagation();smToggleSelect(${m.id})" style="cursor:pointer;">
                <span class="sm-card-status">
                    <span class="dot"></span>
                    ${m.status === 'up' ? 'Online' : (m.status === 'down' ? 'Offline' : 'Pausiert')}
                </span>
            </div>
            <div class="sm-card-actions">
                <a href="${smEsc(m.url)}" target="_blank" title="Website öffnen">
                    <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span>
                </a>
                <button onclick="smOpenStats(${m.id})" title="Statistik">
                    <span class="material-symbols-rounded" style="font-size:14px;">bar_chart</span>
                </button>
                <button onclick="smOpenEdit(${m.id})" title="Bearbeiten">
                    <span class="material-symbols-rounded" style="font-size:14px;">edit</span>
                </button>
                <button onclick="smCheckNow(${m.id})" title="Jetzt prüfen">
                    <span class="material-symbols-rounded" style="font-size:14px;">refresh</span>
                </button>
                <button onclick="smTogglePause(${m.id})" title="${m.status === 'paused' ? 'Aktivieren' : 'Pausieren'}">
                    <span class="material-symbols-rounded" style="font-size:14px;">${m.status === 'paused' ? 'play_arrow' : 'pause'}</span>
                </button>
                <button class="is-delete" onclick="smDelete(${m.id})" title="Löschen">
                    <span class="material-symbols-rounded" style="font-size:14px;">delete</span>
                </button>
            </div>
        </div>
        <div onclick="smOpenStats(${m.id})" style="cursor:pointer;">
            <div class="sm-card-head">
                ${m.customer_logo
                    ? `<div class="sm-card-logo"><img src="/uploads/customers/logos/${smEsc(m.customer_logo.split('/').pop())}" alt="" onerror="this.parentElement.innerHTML='<span class=\\'sm-card-abbr\\'>${smEsc((m.customer_abbr || m.customer_name || '?').substring(0,3))}</span>'"></div>`
                    : (m.customer_name ? `<div class="sm-card-logo"><span class="sm-card-abbr">${smEsc((m.customer_abbr || m.customer_name).substring(0,3))}</span></div>` : '')}
                <div class="sm-card-head-text">
                    <h3 class="sm-card-label">${smEsc(m.label)}</h3>
                    <div class="sm-card-url">${smEsc(m.url)}</div>
                </div>
            </div>
            <div class="sm-card-meta" style="margin-top:6px;">
                ${m.customer_name ? `<span class="sm-tag sm-tag-cust" ${m.customer_color ? `style="background:${m.customer_color}22;color:${m.customer_color};"` : ''}>${smEsc(m.customer_name)}</span>` : ''}
                ${(m.customer_tags || []).map(t => `<span class="sm-tag sm-tag-cat">${smEsc(t)}</span>`).join('')}
            </div>
        </div>
        <div class="sm-card-stats">
            <div>
                <div class="sm-stat-val ${upClass}">${uptime.toFixed(1)}%</div>
                <div class="sm-stat-lbl">Uptime 24h</div>
            </div>
            <div>
                <div class="sm-stat-val">${m.last_response_time || '–'}<span style="font-size:10px;color:var(--slate-400);">ms</span></div>
                <div class="sm-stat-lbl">Response</div>
            </div>
            <div>
                <div class="sm-stat-val">${m.last_status_code || '–'}</div>
                <div class="sm-stat-lbl">Status</div>
            </div>
            <div>
                <div class="sm-stat-val" style="font-size:11px;font-weight:500;color:var(--slate-500);">${lastCheck}</div>
                <div class="sm-stat-lbl">Letzter Check</div>
            </div>
        </div>
    </div>`;
}

function smRenderList(list) {
    return `<div class="sm-list"><table>
        <thead><tr>
            <th style="width:30px;"></th>
            <th style="width:80px;">Status</th>
            <th>Website</th>
            <th>Kunde</th>
            <th>Kategorie</th>
            <th class="is-right" style="width:80px;">Uptime 24h</th>
            <th class="is-right" style="width:80px;">Response</th>
            <th class="is-right" style="width:60px;">Code</th>
            <th class="is-right" style="width:100px;">Letzter Check</th>
            <th style="width:120px;"></th>
        </tr></thead>
        <tbody>${list.map(m => {
            const uptime = parseFloat(m.uptime_24h || 100);
            const upClass = uptime >= 99 ? '' : (uptime >= 95 ? 'is-warn' : 'is-down');
            return `<tr>
                <td><input type="checkbox" ${smState.selected.has(m.id) ? 'checked' : ''} onclick="smToggleSelect(${m.id})"></td>
                <td><span class="sm-card-status" style="font-size:11px;"><span class="dot" style="background:${m.status === 'up' ? 'var(--emerald-500)' : (m.status === 'down' ? 'var(--rose-500)' : 'var(--slate-400)')};display:inline-block;width:8px;height:8px;border-radius:50%;"></span> ${m.status === 'up' ? 'Online' : (m.status === 'down' ? 'Offline' : 'Pausiert')}</span></td>
                <td><strong>${smEsc(m.label)}</strong><br><span style="color:var(--slate-400);font-size:11px;">${smEsc(m.url)}</span></td>
                <td>${m.customer_name ? smEsc(m.customer_name) : '<span style="color:var(--slate-400);">—</span>'}</td>
                <td>${m.category ? `<span class="sm-tag sm-tag-cat">${smEsc(m.category)}</span>` : ''}</td>
                <td class="is-right ${upClass}">${uptime.toFixed(1)}%</td>
                <td class="is-right">${m.last_response_time || '–'} ms</td>
                <td class="is-right">${m.last_status_code || '–'}</td>
                <td class="is-right" style="color:var(--slate-500);font-family:inherit;">${m.last_check ? smRelative(m.last_check) : '—'}</td>
                <td><div class="sm-card-actions" style="opacity:1;">
                    <a href="${smEsc(m.url)}" target="_blank" title="Öffnen"><span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span></a>
                    <button onclick="smOpenStats(${m.id})" title="Stats"><span class="material-symbols-rounded" style="font-size:14px;">bar_chart</span></button>
                    <button onclick="smOpenEdit(${m.id})" title="Bearbeiten"><span class="material-symbols-rounded" style="font-size:14px;">edit</span></button>
                    <button onclick="smTogglePause(${m.id})" title="Pause"><span class="material-symbols-rounded" style="font-size:14px;">${m.status === 'paused' ? 'play_arrow' : 'pause'}</span></button>
                    <button class="is-delete" onclick="smDelete(${m.id})" title="Löschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button>
                </div></td>
            </tr>`;
        }).join('')}</tbody>
    </table></div>`;
}

function smRelative(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T') + 'Z');
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60) return diff + 's';
    if (diff < 3600) return Math.floor(diff/60) + 'min';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd';
}

/* ===== Bulk ===== */
function smToggleSelect(id) {
    if (smState.selected.has(id)) smState.selected.delete(id);
    else smState.selected.add(id);
    smUpdateBulkBar();
}
function smUpdateBulkBar() {
    const bar = document.getElementById('sm-bulk-bar');
    if (smState.selected.size === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    document.getElementById('sm-bulk-count').textContent = smState.selected.size;
}
async function smBulkCategory() {
    const cat = prompt('Kategorie für die ' + smState.selected.size + ' ausgewählten Websites:');
    if (cat === null) return;
    try {
        await fetch('/api/v1/admin/site-monitor/bulk-category', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ ids: [...smState.selected], category: cat.trim() }),
        });
        smState.selected.clear();
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}
function smBulkClear() { smState.selected.clear(); smRender(); }

/* ===== Modals ===== */
function smOpenModal(id) { document.getElementById(id).style.display = 'flex'; }
function smCloseModal(id) { document.getElementById(id).style.display = 'none'; }

function smOpenAddModal() {
    document.getElementById('sm-add-title').textContent = 'Website hinzufügen';
    document.getElementById('sm-edit-id').value = '0';
    ['sm-url','sm-label','sm-category','sm-alert-email','sm-sub-urls'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('sm-customer').value = '';
    document.getElementById('sm-report').value = 'both';
    smOpenModal('sm-add-modal');
    setTimeout(() => document.getElementById('sm-url').focus(), 50);
}

function smOpenEdit(id) {
    const m = smState.monitors.find(x => x.id === id);
    if (!m) return;
    document.getElementById('sm-add-title').textContent = 'Website bearbeiten';
    document.getElementById('sm-edit-id').value = id;
    document.getElementById('sm-url').value = m.url || '';
    document.getElementById('sm-label').value = m.label || '';
    document.getElementById('sm-customer').value = m.customer_id || '';
    document.getElementById('sm-category').value = m.category || '';
    document.getElementById('sm-report').value = m.report_schedule || 'both';
    document.getElementById('sm-alert-email').value = m.alert_email || '';
    document.getElementById('sm-sub-urls').value = (m.sub_urls_arr || []).join('\n');
    smOpenModal('sm-add-modal');
}

async function smFetchTitle() {
    const url = document.getElementById('sm-url').value.trim();
    if (!url) return;
    try {
        const r = await fetch('/api/v1/admin/site-monitor/fetch-title?url=' + encodeURIComponent(url));
        const j = await r.json();
        if (j.success && j.data.title) document.getElementById('sm-label').value = j.data.title;
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function smSave() {
    const id = parseInt(document.getElementById('sm-edit-id').value) || 0;
    const payload = {
        id: id || undefined,
        url: document.getElementById('sm-url').value.trim(),
        label: document.getElementById('sm-label').value.trim(),
        customer_id: document.getElementById('sm-customer').value || null,
        category: document.getElementById('sm-category').value.trim(),
        report_schedule: document.getElementById('sm-report').value,
        alert_email: document.getElementById('sm-alert-email').value.trim(),
        sub_urls: document.getElementById('sm-sub-urls').value,
    };
    try {
        const r = await fetch('/api/v1/admin/site-monitor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Gespeichert', 'success');
        smCloseModal('sm-add-modal');
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function smDelete(id) {
    if (!confirm('Website wirklich löschen? Logs und Incidents werden mit gelöscht.')) return;
    try {
        await fetch('/api/v1/admin/site-monitor/' + id, { method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken } });
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function smTogglePause(id) {
    try {
        await fetch('/api/v1/admin/site-monitor/' + id + '/toggle', { method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken } });
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function smCheckNow(id) {
    try {
        const r = await fetch('/api/v1/admin/site-monitor/' + id + '/check-now', { method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken } });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Check ausgeführt', 'success');
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function smOpenBatchModal() {
    ['sm-batch-urls','sm-batch-category'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('sm-batch-customer').value = '';
    document.getElementById('sm-batch-report').value = 'both';
    smOpenModal('sm-batch-modal');
    setTimeout(() => document.getElementById('sm-batch-urls').focus(), 50);
}

async function smBatchImport() {
    const urls = document.getElementById('sm-batch-urls').value.trim();
    if (!urls) { App.showNotification('URLs eingeben', 'error'); return; }
    try {
        const r = await fetch('/api/v1/admin/site-monitor/batch-import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({
                urls,
                customer_id: document.getElementById('sm-batch-customer').value || null,
                category: document.getElementById('sm-batch-category').value.trim(),
                report_schedule: document.getElementById('sm-batch-report').value,
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message, 'success');
        smCloseModal('sm-batch-modal');
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function smOpenCronModal() { smOpenModal('sm-cron-modal'); }

/* ===== Tallyr-JSON-Import ===== */
let smImportData = null, smImportMapping = {}, smMonitorMapping = {};
function smOpenImportModal() {
    document.getElementById('sm-import-file').value = '';
    document.getElementById('sm-import-preview-result').innerHTML = '';
    document.getElementById('sm-import-go').style.display = 'none';
    document.getElementById('sm-import-snippet').style.display = 'none';
    smImportData = null;
    smImportMapping = {};
    smMonitorMapping = {};
    smOpenModal('sm-import-modal');
}

function smToggleSnippet() {
    const el = document.getElementById('sm-import-snippet');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function smCopySnippet() {
    const el = document.getElementById('sm-import-snippet');
    const txt = el.textContent;
    navigator.clipboard.writeText(txt).then(
        () => App.showNotification('Snippet kopiert — jetzt in functions.php des Tallyr-Childthemes einfügen', 'success'),
        () => { el.style.display = 'block'; App.showNotification('Konnte nicht kopieren — Snippet ist jetzt unten sichtbar zum manuellen Markieren', 'warning'); }
    );
}

async function smImportPreview() {
    const file = document.getElementById('sm-import-file').files[0];
    if (!file) return;
    const result = document.getElementById('sm-import-preview-result');
    const sizeMb = (file.size / 1024 / 1024).toFixed(1);
    result.innerHTML = `<div style="padding:10px;color:var(--slate-400);">Datei (${sizeMb} MB) wird geprüft…</div>`;
    try {
        const text = await file.text();
        smImportData = JSON.parse(text);
        // Wenn JSON > 5 MB → File-Upload statt JSON-Body (post_max_size umgehen)
        let r;
        if (file.size > 5 * 1024 * 1024) {
            const fd = new FormData();
            fd.append('json_file', file);
            fd.append('csrf_token', App.csrfToken);
            r = await fetch('/api/v1/admin/site-monitor/import-preview', {
                method: 'POST',
                headers: { 'X-CSRF-Token': App.csrfToken },
                body: fd,
            });
        } else {
            r = await fetch('/api/v1/admin/site-monitor/import-preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify(smImportData),
            });
        }
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        smRenderImportPreview(j.data.preview);
    } catch (e) {
        result.innerHTML = '<div style="padding:10px;color:var(--rose-600);">' + smEsc(e.message) + '</div>';
    }
}

function smRenderImportPreview(p) {
    const result = document.getElementById('sm-import-preview-result');
    const custOpts = smState.customersActive.map(c =>
        `<option value="${c.id}">${smEsc(c.name)} (${c.abbreviation || c.slug || c.id})</option>`
    ).join('');
    // History-Daten? Anzeigen wie viele
    const hasLogs = Array.isArray(smImportData.logs) && smImportData.logs.length > 0;
    const hasIncidents = Array.isArray(smImportData.incidents) && smImportData.incidents.length > 0;
    const historyInfo = (hasLogs || hasIncidents) ? `
        <div style="background:var(--emerald-50);border:1px solid var(--emerald-200);border-radius:6px;padding:10px;margin-top:10px;font-size:var(--d-fs-xs);">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" id="sm-import-history-cb" checked>
                <span><strong>Verlaufs-Historie mit importieren:</strong>
                ${hasLogs ? `<strong style="color:var(--emerald-700);">${smImportData.logs.length.toLocaleString('de-DE')} Log-Einträge</strong>` : ''}
                ${hasLogs && hasIncidents ? ' · ' : ''}
                ${hasIncidents ? `<strong style="color:var(--emerald-700);">${smImportData.incidents.length} Incidents</strong>` : ''}
                </span>
            </label>
            <div style="margin-top:4px;color:var(--slate-500);padding-left:24px;">Werden per URL gematcht (auch für die schon importierten 14 Monitore).</div>
        </div>` : `
        <div style="background:var(--amber-50);border:1px solid var(--amber-200);border-radius:6px;padding:10px;margin-top:10px;font-size:var(--d-fs-xs);">
            <strong>Keine Verlaufs-Historie in der JSON.</strong>
            Für die letzten 30/90 Tage: Tallyr-URL um <code>&amp;history=1&amp;days=90</code> erweitern und neu hochladen.
        </div>`;

    let html = `
        <div style="background:var(--slate-50);border-radius:8px;padding:12px;margin-top:10px;">
            <div style="font-size:var(--d-fs-sm);color:var(--slate-700);margin-bottom:8px;">
                <strong>${p.total}</strong> Monitore im JSON: <strong style="color:var(--emerald-700);">${p.new} neu</strong>
                ${p.duplicates > 0 ? `, <strong style="color:var(--amber-700);">${p.duplicates} Duplikate</strong> (URL existiert schon, wird übersprungen)` : ''}
            </div>
            ${historyInfo}`;
    // Eine einheitliche Liste: jeder Monitor mit URL+Label+Auto-Match
    if (p.monitors && p.monitors.length > 0) {
        const viaLabels = {
            'tallyr-client': { txt: 'via Tallyr-Kunde', color: 'var(--thoxan-700)' },
            'label':         { txt: 'via Label',        color: 'var(--emerald-700)' },
            'domain':        { txt: 'via Domain',       color: 'var(--amber-700)' },
        };
        html += `
            <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <div style="font-size:var(--d-fs-xs);color:var(--slate-700);font-weight:600;">
                    Kunden-Zuordnung pro Monitor (${p.monitors.length} Monitore):
                </div>
                <div style="display:flex;gap:4px;">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smImportSetAllMon('none')">Alle ohne Kunde</button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="smImportSetAllMon('skip')">Alle überspringen</button>
                </div>
            </div>
            <div style="font-size:11px;color:var(--slate-500);margin-bottom:8px;line-height:1.5;">
                <span style="color:var(--thoxan-700);">via Tallyr-Kunde</span> = Match über den Tallyr-Kundennamen.
                <span style="color:var(--emerald-700);">via Label</span> = Match über Label-Kürzel (z.B. „WIT" → Thoxan E-Commerce).
                <span style="color:var(--amber-700);">via Domain</span> = Match über die Website-URL des KI-Tool-Kunden.
            </div>
            <div style="max-height:50vh;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;background:var(--slate-50);">
                <div style="display:flex;flex-direction:column;" id="sm-import-rows-mon">`;
        p.monitors.forEach(m => {
            const tmid = String(m.tallyr_monitor_id);
            const defaultVal = m.matched_customer_id ? String(m.matched_customer_id) : 'none';
            smMonitorMapping[tmid] = defaultVal;
            const via = viaLabels[m.matched_via];
            const matchBadge = via
                ? `<span style="color:${via.color};font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">${via.txt}</span>`
                : '<span style="color:var(--slate-400);font-size:9px;">kein Auto-Match</span>';
            html += `
                <div style="display:grid;grid-template-columns:60px 1fr 90px 260px;gap:8px;align-items:center;padding:6px 10px;background:#fff;border-bottom:1px solid var(--slate-100);">
                    <div style="font-family:ui-monospace,monospace;font-size:11px;font-weight:700;background:var(--slate-100);padding:2px 6px;border-radius:3px;text-align:center;">${smEsc(m.label || '?')}</div>
                    <div style="font-size:var(--d-fs-xs);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${smEsc(m.url)}">
                        ${smEsc(m.url)}
                        ${m.tallyr_client_name ? `<span style="color:var(--slate-400);font-size:10px;display:block;">Tallyr: ${smEsc(m.tallyr_client_name)}</span>` : ''}
                    </div>
                    <div style="font-size:11px;text-align:right;">${matchBadge}</div>
                    <select data-tmid="${tmid}" onchange="smMonitorMapping['${tmid}'] = this.value" style="padding:5px 8px;border:1px solid var(--slate-200);border-radius:4px;font-size:var(--d-fs-xs);">
                        <option value="none" ${defaultVal === 'none' ? 'selected' : ''}>(kein KI-Tool-Kunde)</option>
                        <option value="skip" ${defaultVal === 'skip' ? 'selected' : ''}>↪ Überspringen</option>
                        <optgroup label="Auf existierenden Kunden mappen">
                            ${smState.customersActive.map(c2 =>
                                `<option value="${c2.id}" ${String(c2.id) === defaultVal ? 'selected' : ''}>${smEsc(c2.name)} (${c2.abbreviation || c2.slug || c2.id})</option>`
                            ).join('')}
                        </optgroup>
                    </select>
                </div>`;
        });
        html += '</div></div>';
    }
    html += '</div>';
    result.innerHTML = html;
    // Import-Button auch zeigen wenn nur Historie da ist (für Nach-Import)
    if (p.new > 0 || hasLogs || hasIncidents) {
        document.getElementById('sm-import-go').style.display = '';
        if (p.new === 0 && (hasLogs || hasIncidents)) {
            document.getElementById('sm-import-go').textContent = 'Nur Historie importieren';
        }
    }
}

function smImportSetAllMon(value) {
    document.querySelectorAll('#sm-import-rows-mon select[data-tmid]').forEach(sel => {
        const tmid = sel.dataset.tmid;
        if (value === 'skip') {
            sel.value = 'skip'; smMonitorMapping[tmid] = 'skip';
        } else if (sel.value === 'skip' || sel.value === 'none') {
            sel.value = 'none'; smMonitorMapping[tmid] = 'none';
        }
    });
}

async function smImportDo() {
    if (!smImportData) return;
    const importHistory = document.getElementById('sm-import-history-cb')?.checked ?? true;
    const goBtn = document.getElementById('sm-import-go');
    const origLabel = goBtn.textContent;
    goBtn.disabled = true;
    goBtn.textContent = 'Importiere…';
    try {
        const payload = Object.assign({}, smImportData, {
            _customer_mapping: smImportMapping,
            _monitor_mapping: smMonitorMapping,
            _import_history: importHistory,
        });
        const payloadJson = JSON.stringify(payload);
        let r;
        if (payloadJson.length > 5 * 1024 * 1024) {
            // Großer Payload → File-Upload (Blob)
            const fd = new FormData();
            fd.append('json_file', new Blob([payloadJson], { type: 'application/json' }), 'import-payload.json');
            r = await fetch('/api/v1/admin/site-monitor/import', {
                method: 'POST',
                headers: { 'X-CSRF-Token': App.csrfToken },
                body: fd,
            });
        } else {
            r = await fetch('/api/v1/admin/site-monitor/import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: payloadJson,
            });
        }
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message, 'success');
        smCloseModal('sm-import-modal');
        smInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
    finally { goBtn.disabled = false; goBtn.textContent = origLabel; }
}

/* ===== Mail-Log ===== */
async function smOpenMailLog() {
    smOpenModal('sm-mail-log-modal');
    const body = document.getElementById('sm-mail-log-body');
    body.textContent = 'Lädt…';
    try {
        const r = await fetch('/api/v1/admin/site-monitor/mail-log');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const lines = j.data.lines || [];
        if (!lines.length) {
            body.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:40px;">Noch keine Mails versendet.</div>';
        } else {
            body.innerHTML = lines.map(l => '<div style="padding:2px 0;">' + smEsc(l) + '</div>').join('');
        }
    } catch (e) {
        body.innerHTML = '<div style="color:var(--rose-400);padding:10px;">' + e.message + '</div>';
    }
}

async function smTestReport() {
    const email = prompt('Test-Report an welche E-Mail senden?', '<?= htmlspecialchars(\Core\Auth::user()['email'] ?? '', ENT_QUOTES) ?>');
    if (!email) return;
    try {
        const r = await fetch('/api/v1/admin/site-monitor/test-report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ email }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message, 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* smOpenStats + smRenderStats kommen jetzt aus dem Partial _sm_stats_modal.php */

/* ===== URL-Parameter ?monitor=ID öffnet Stats-Modal automatisch ===== */
async function smInitAndDeeplink() {
    await smInit();
    const params = new URLSearchParams(window.location.search);
    const monId = parseInt(params.get('monitor') || '0');
    if (monId > 0) {
        // Warten bis State geladen ist + Stats-Modal öffnen
        setTimeout(() => smOpenStats(monId), 200);
    }
}

document.addEventListener('DOMContentLoaded', smInitAndDeeplink);
</script>
<style>
@keyframes pp-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
</style>
