<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$currentuserid = get_current_user_id();

// Load all clients (for plan creation)
$clients_table = $wpdb->prefix . 'tallyr_clients';
$all_clients = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, shortdesc, hexcolor FROM $clients_table WHERE userid = %d AND state = 1 ORDER BY title ASC",
    $currentuserid
));

// Load only clients that have at least one plan (for filter)
$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
$clients_with_plans = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT c.id, c.title FROM $clients_table c
     INNER JOIN $plans_table p ON p.client_id = c.id AND p.state = 1
     WHERE c.userid = %d AND c.state = 1 ORDER BY c.title ASC",
    $currentuserid
));

// Load WP users for responsible-person datalist
$wp_users = get_users(array('fields' => array('ID', 'display_name')));
$users_list = array();
foreach ($wp_users as $u) {
    $kuerzel = get_user_meta($u->ID, 'tallyr_kuerzel', true);
    $users_list[] = array(
        'id' => $u->ID,
        'name' => $u->display_name,
        'kuerzel' => $kuerzel ? $kuerzel : '',
    );
}
?>

<?php
$pp_fontsize = get_user_meta($currentuserid, 'tallyr_pp_fontsize', true) ?: 13;
$pp_colwidths = get_user_meta($currentuserid, 'tallyr_pp_colwidths', true) ?: '';
?>
<div id="projektplanner-dashboard" data-fontsize="<?php echo (int)$pp_fontsize; ?>" data-colwidths="<?php echo esc_attr($pp_colwidths); ?>">

    <!-- Header -->
    <div class="pp-header">
        <div class="pp-title">
            <h2><i class='bx bx-spreadsheet'></i> Projektplanner</h2>
        </div>
        <div class="pp-actions">
            <button type="button" id="pp-font-down" class="pp-tool-btn" title="Schrift kleiner"><i class='bx bx-minus'></i></button>
            <span id="pp-font-label" class="pp-font-label">13</span>
            <button type="button" id="pp-font-up" class="pp-tool-btn" title="Schrift größer"><i class='bx bx-plus'></i></button>
            <button type="button" id="pp-toggle-sidebar" class="pp-tool-btn" title="Seitenleiste ein-/ausblenden"><i class='bx bx-sidebar'></i></button>
            <button type="button" id="pp-dashboard-btn" class="pp-tool-btn" title="Dashboard / Statistiken"><i class='bx bx-bar-chart-alt-2'></i></button>
            <button type="button" id="pp-import-btn" class="pp-tool-btn" title="Plan aus Excel importieren"><i class='bx bx-import'></i></button>
            <input type="file" id="pp-import-file" accept=".xlsx,.xls" style="display:none;">
            <button type="button" id="pp-new-plan-btn" class="btn-primary"><i class='bx bx-plus'></i> Neuer Plan</button>
        </div>
    </div>

    <!-- Unread Feedback Banner -->
    <div id="pp-feedback-banner" style="display:none;"></div>

    <!-- Plan Selector -->
    <div class="pp-plan-selector">
        <div class="pp-selector-toolbar">
            <select id="pp-client-filter">
                <option value="">Alle Kunden</option>
                <?php foreach ($clients_with_plans as $c): ?>
                    <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->title); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="pp-status-filter">
                <option value="">Alle Status</option>
                <option value="entwurf">Entwurf</option>
                <option value="aktiv" selected>Aktiv</option>
                <option value="einzelprojekt">Einzelprojekte</option>
                <option value="reporting">Fertig für Reporting</option>
                <option value="abgeschlossen">Abgeschlossen</option>
                <option value="archiviert">Archiviert</option>
            </select>
            <input type="text" id="pp-plan-search" placeholder="Plan suchen..." class="pp-plan-search-input">
            <button type="button" id="pp-select-all" class="pp-tool-btn" title="Alle sichtbaren auswählen (Cmd+A)" style="font-size:11px !important;width:auto !important;padding:0 8px !important;"><i class='bx bx-select-multiple' style="margin-right:2px;"></i>Alle</button>
            <small style="color:#aaa;font-size:10px;margin-left:auto;">Cmd/Strg+Klick für Mehrfachauswahl</small>
        </div>
        <div id="pp-plans-grid" class="pp-plans-grid"></div>
        <div id="pp-selected-plans" class="pp-selected-plans" style="display:none;">
            <span class="pp-selected-label">Ausgewählt:</span>
            <div id="pp-selected-chips" class="pp-selected-chips"></div>
        </div>
    </div>
    <div class="pp-selector-divider"></div>

    <!-- Plan Header (shown when a plan is selected) -->
    <div id="pp-plan-header" class="pp-plan-header" style="display:none">
        <div class="pp-plan-info">
            <span class="pp-client-badge" id="pp-client-badge"></span>
            <span class="pp-plan-title" id="pp-plan-title-display"></span>
            <span class="pp-plan-period" id="pp-plan-period"></span>
        </div>
        <div class="pp-plan-actions-bar">
            <button type="button" class="btn-icon" id="pp-edit-plan" title="Plan bearbeiten"><i class='bx bx-pencil'></i></button>
            <button type="button" class="btn-icon" id="pp-share-plan" title="Freigeben"><i class='bx bx-share-alt'></i></button>
            <a href="#" class="btn-icon" id="pp-share-link-btn" title="Freigabelink öffnen" target="_blank" style="display:none;"><i class='bx bx-link-external'></i></a>
            <button type="button" class="btn-icon" id="pp-duplicate-plan" title="Plan duplizieren"><i class='bx bx-copy'></i></button>
            <button type="button" class="btn-icon" id="pp-feedback-btn" title="Kunden-Feedback"><i class='bx bx-comment-dots'></i></button>
            <button type="button" class="btn-icon" id="pp-revisions-btn" title="Versionen"><i class='bx bx-history'></i></button>
            <button type="button" class="btn-icon" id="pp-budget-btn" title="Soll/Ist Abgleich"><i class='bx bx-bar-chart-square'></i></button>
            <button type="button" class="btn-icon" id="pp-export-plan" title="Excel Export"><i class='bx bx-download'></i></button>
            <button type="button" class="btn-icon pp-danger" id="pp-delete-plan" title="Plan löschen"><i class='bx bx-trash'></i></button>
        </div>
        <div class="pp-plan-stats">
            <span class="pp-stat-item"><i class='bx bx-time-five'></i> <strong id="pp-total-ist">0</strong> Ist</span>
            <span class="pp-stat-item"><i class='bx bx-target-lock'></i> <strong id="pp-total-planned">0</strong> Geplant</span>
            <span class="pp-stat-item pp-stat-budget" id="pp-budget-stat" style="display:none;"><i class='bx bx-wallet'></i> <strong id="pp-budget-soll">0</strong> Soll</span>
            <span class="pp-stat-item pp-stat-gap" id="pp-budget-gap" style="display:none;"><i class='bx bx-error-circle'></i> <strong id="pp-budget-gap-val">0</strong></span>
            <span class="pp-stat-item"><i class='bx bx-check-double'></i> <strong id="pp-total-done">0</strong>/<strong id="pp-total-items">0</strong> erledigt</span>
        </div>
    </div>

    <!-- Table -->
    <div id="pp-table-container" class="pp-table-container" style="display:none">
        <div class="pp-filter-bar" id="pp-filter-bar">
            <button class="pp-filter-pill active" data-filter="all">Alle</button>
            <button class="pp-filter-pill" data-filter="open">Offen</button>
            <button class="pp-filter-pill" data-filter="done">Erledigt</button>
            <button class="pp-filter-pill" data-filter="placeholder">Platzhalter</button>
            <button class="pp-filter-pill" data-filter="no-asana">Ohne Asana</button>
            <button class="pp-filter-pill" data-filter="no-ticket">Kein Ticket</button>
            <button class="pp-filter-pill" data-filter="focus">Fokus</button>
            <span class="pp-filter-sep">|</span>
            <input type="text" id="pp-filter-search" placeholder="Suche..." class="pp-filter-search">
            <select id="pp-filter-lead" class="pp-filter-select"><option value="">Hauptverantw.</option></select>
            <select id="pp-filter-responsible" class="pp-filter-select"><option value="">Umsetzung</option></select>
            <span class="pp-filter-sep">|</span>
            <button type="button" id="pp-person-share-btn" class="pp-filter-action-btn" title="Aufgabenliste für Person teilen" style="display:none;"><i class='bx bx-share-alt'></i> Teilen</button>
            <button type="button" id="pp-person-export-btn" class="pp-filter-action-btn" title="Gefilterte Aufgaben als Excel" style="display:none;"><i class='bx bx-download'></i> Export</button>
        </div>
        <div class="pp-table-scroll">
            <table class="pp-table" id="pp-table">
                <thead>
                    <tr>
                        <th class="pp-col-drag"></th>
                        <th class="pp-th-done"></th>
                        <th class="pp-th-desc">Beschreibung</th>
                        <th class="pp-th-period pp-col-filterable" data-col="timeframe">Zeitraum <i class="bx bx-filter-alt pp-col-filter-icon"></i></th>
                        <th class="pp-th-ist">Ist (h)</th>
                        <th class="pp-th-planned">Soll (h)</th>
                        <th class="pp-th-lead pp-col-filterable" data-col="lead_responsible">Hauptverantw. <i class="bx bx-filter-alt pp-col-filter-icon"></i></th>
                        <th class="pp-th-resp pp-col-filterable" data-col="responsible">Umsetzung <i class="bx bx-filter-alt pp-col-filter-icon"></i></th>
                        <th class="pp-th-deadline pp-col-filterable" data-col="deadline">Umgesetzt bis <i class="bx bx-filter-alt pp-col-filter-icon"></i></th>
                        <th class="pp-th-actual">Aufwand</th>
                        <th class="pp-th-notes">Bemerkungen</th>
                        <th class="pp-th-asana"></th>
                        <th class="pp-col-actions"></th>
                    </tr>
                </thead>
                <tbody id="pp-table-body">
                </tbody>
            </table>
        </div>

        <div class="pp-add-row-bar">
            <button type="button" id="pp-add-section" class="btn-secondary"><i class='bx bx-heading'></i> Sektion</button>
            <button type="button" id="pp-add-item" class="btn-secondary"><i class='bx bx-plus'></i> Aufgabe</button>
            <button type="button" id="pp-add-asana" class="btn-secondary"><i class='bx bx-link'></i> Aufgabe aus Asana</button>
            <button type="button" id="pp-add-note" class="btn-secondary"><i class='bx bx-note'></i> Notiz</button>
            <button type="button" id="pp-add-spacer" class="btn-secondary"><i class='bx bx-minus'></i> Abstand</button>
            <button type="button" id="pp-refresh-asana" class="btn-secondary pp-btn-right" title="Asana Cache aktualisieren"><i class='bx bx-refresh'></i></button>
        </div>
    </div>

    <!-- Empty state -->
    <div id="pp-empty-state" class="pp-empty-state">
        <i class='bx bx-spreadsheet'></i>
        <p>Wähle einen Plan aus oder erstelle einen neuen.</p>
    </div>

    <!-- Dashboard -->
    <div id="pp-dashboard" style="display:none;">
        <div class="ppd-header">
            <h3><i class='bx bx-bar-chart-alt-2'></i> Dashboard</h3>
            <button type="button" id="pp-dashboard-close" class="pp-tool-btn" title="Schließen"><i class='bx bx-x'></i></button>
        </div>

        <!-- Filter Row 1: Zeitraum + Status + Kunde -->
        <div class="ppd-filters">
            <div class="ppd-filter-group ppd-filter-date">
                <label>Zeitraum</label>
                <div style="display:flex;gap:4px;align-items:center;">
                    <input type="date" id="ppd-date-from">
                    <span style="color:#999;">–</span>
                    <input type="date" id="ppd-date-to">
                </div>
            </div>
            <div class="ppd-filter-group">
                <label>Status</label>
                <select id="ppd-status-filter">
                    <option value="">Alle Status</option>
                    <option value="aktiv" selected>Aktiv</option>
                    <option value="entwurf">Entwurf</option>
                    <option value="einzelprojekt">Einzelprojekte</option>
                    <option value="reporting">Reporting</option>
                    <option value="abgeschlossen">Abgeschlossen</option>
                </select>
            </div>
            <div class="ppd-filter-group">
                <label>Kunde</label>
                <select id="ppd-client-filter">
                    <option value="">Alle Kunden</option>
                    <?php foreach ($all_clients as $c): ?>
                        <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ppd-filter-group">
                <label>Person</label>
                <select id="ppd-person-filter">
                    <option value="">Alle Personen</option>
                </select>
            </div>
            <button type="button" id="ppd-apply-filter" class="ppd-btn-apply">Aktualisieren</button>
            <button type="button" id="ppd-reset-filter" class="ppd-btn-reset" title="Filter zurücksetzen"><i class='bx bx-reset'></i></button>
        </div>

        <!-- Quick date presets -->
        <div class="ppd-presets">
            <button class="ppd-preset active" data-range="quarter">Akt. Quartal</button>
            <button class="ppd-preset" data-range="q1">Q1</button>
            <button class="ppd-preset" data-range="q2">Q2</button>
            <button class="ppd-preset" data-range="q3">Q3</button>
            <button class="ppd-preset" data-range="q4">Q4</button>
            <span class="ppd-preset-sep">|</span>
            <button class="ppd-preset" data-range="h1">H1</button>
            <button class="ppd-preset" data-range="h2">H2</button>
            <span class="ppd-preset-sep">|</span>
            <button class="ppd-preset" data-range="year">Jahr</button>
            <button class="ppd-preset" data-range="last-quarter">Vorquartal</button>
            <button class="ppd-preset" data-range="month">Monat</button>
            <button class="ppd-preset" data-range="all">Alles</button>
        </div>

        <!-- KPIs -->
        <div class="ppd-kpis" id="ppd-kpis"></div>

        <!-- Tabs -->
        <div class="ppd-tabs">
            <button class="ppd-tab active" data-tab="persons"><i class='bx bx-group'></i> Auslastung</button>
            <button class="ppd-tab" data-tab="plans"><i class='bx bx-list-ul'></i> Pläne</button>
            <button class="ppd-tab" data-tab="forecast"><i class='bx bx-calendar'></i> Forecast</button>
            <button class="ppd-tab" data-tab="done"><i class='bx bx-check-circle'></i> Erledigte Aufgaben</button>
            <button class="ppd-tab" data-tab="budget"><i class='bx bx-bar-chart-square'></i> Soll/Ist</button>
        </div>

        <!-- Tab: Auslastung -->
        <div class="ppd-tab-content ppd-tab-persons active">
            <div id="ppd-persons"></div>
        </div>

        <!-- Tab: Pläne -->
        <div class="ppd-tab-content ppd-tab-plans">
            <div id="ppd-plans"></div>
        </div>

        <!-- Tab: Forecast -->
        <div class="ppd-tab-content ppd-tab-forecast">
            <div id="ppd-forecast"></div>
        </div>

        <!-- Tab: Erledigte Aufgaben -->
        <div class="ppd-tab-content ppd-tab-done">
            <div id="ppd-done"></div>
        </div>

        <!-- Tab: Soll/Ist Budget -->
        <div class="ppd-tab-content ppd-tab-budget">
            <div id="ppd-budget"></div>
        </div>
    </div>

    <!-- Loading -->
    <div id="pp-loading" style="display:none">
        <i class="bx bx-loader-alt bx-spin"></i> Lade...
    </div>

    <!-- Datalist for responsible person suggestions -->
    <datalist id="pp-users-datalist">
        <?php if (is_array($custom_tags)): foreach ($custom_tags as $tag): ?>
            <option value="<?php echo esc_attr($tag['name']); ?>"><?php echo esc_attr($tag['name'] . ' (' . $tag['kuerzel'] . ')'); ?></option>
        <?php endforeach; endif; ?>
    </datalist>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<script>
<?php
// Only use custom tags (no WP users)
$custom_tags = get_user_meta($currentuserid, 'tallyr_pp_tags', true);
$custom_tags = $custom_tags ? json_decode($custom_tags, true) : array();
$all_resp = array();
if (is_array($custom_tags)) {
    foreach ($custom_tags as $tag) {
        $all_resp[] = array(
            'id' => 0,
            'name' => $tag['name'],
            'kuerzel' => $tag['kuerzel'],
            'capacity' => isset($tag['capacity']) ? (int)$tag['capacity'] : 0,
        );
    }
}
?>
var ppUsersData = <?php echo json_encode($all_resp); ?>;
var ppAllClients = <?php echo json_encode(array_map(function($c) { return array('id' => $c->id, 'title' => $c->title); }, $all_clients)); ?>;
</script>