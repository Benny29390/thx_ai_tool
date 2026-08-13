<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Get all clients for the completion modal
global $wpdb;
$clients_table = $wpdb->prefix . 'tallyr_clients';
$clients = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, hexcolor FROM $clients_table WHERE userid = %d AND state = 1 ORDER BY title ASC",
    get_current_user_id()
));

// Get German day names
$day_names = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');
$day_names_full = array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag');
?>

<div id="planner-dashboard">
    <!-- Header -->
    <div class="planner-header">
        <div class="planner-title">
            <h2><i class='bx bx-calendar-week'></i> Wochenplaner</h2>
        </div>
        <div class="planner-nav">
            <button type="button" id="planner-prev-week" class="btn-nav" title="Vorherige Woche">
                <i class='bx bx-chevron-left'></i>
            </button>
            <button type="button" id="planner-today" class="btn-today">Heute</button>
            <button type="button" id="planner-next-week" class="btn-nav" title="Nächste Woche">
                <i class='bx bx-chevron-right'></i>
            </button>
        </div>
        <div class="planner-week-label">
            <span id="planner-week-range">KW --</span>
        </div>
        <div class="planner-actions">
            <button type="button" id="add-task-btn" class="btn-primary"><i class='bx bx-plus'></i> Aufgabe</button>
        </div>
    </div>

    <!-- Week View -->
    <div class="planner-week">
        <!-- Days will be rendered by JavaScript -->
        <div class="planner-days" id="planner-days"></div>

        <!-- Backlog Column -->
        <div class="planner-backlog" id="planner-backlog">
            <div class="backlog-header">
                <h3><i class='bx bx-archive'></i> Backlog</h3>
                <span class="backlog-hint">Ungeplante Aufgaben</span>
            </div>
            <div class="backlog-tasks" id="backlog-tasks" data-date="">
                <!-- Tasks without date go here -->
            </div>
        </div>

        <!-- Trash Drop Zone (hidden until dragging) -->
        <div class="planner-trash" id="planner-trash">
            <i class='bx bx-trash'></i>
            <span>Löschen</span>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="planner-stats">
        <div class="stat-item stat-completed">
            <i class='bx bx-check-circle'></i>
            <span id="stat-completed-count">0</span> erledigt
        </div>
        <div class="stat-item stat-open">
            <i class='bx bx-circle'></i>
            <span id="stat-open-count">0</span> offen
        </div>
        <div class="stat-item stat-backlog">
            <i class='bx bx-archive'></i>
            <span id="stat-backlog-count">0</span> ungeplant
        </div>
    </div>

    <!-- Motivating Quote -->
    <div class="planner-quote">
        <i class='bx bx-bulb'></i>
        <span id="planner-quote-text"></span>
    </div>

    <!-- Loading -->
    <div id="planner-loading" style="display:none">
        <i class="bx bx-loader-alt bx-spin"></i> Lade...
    </div>
</div>

<!-- Modal: Add/Edit Task -->
<div id="modal-task" class="planner-modal" style="display:none">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-task-title">Neue Aufgabe</h3>
            <button type="button" class="modal-close"><i class='bx bx-x'></i></button>
        </div>
        <form id="form-task">
            <input type="hidden" id="task-id" value="0">
            <input type="hidden" id="task-date-hidden" value="">

            <div class="form-row">
                <label for="task-title">Titel *</label>
                <input type="text" id="task-title" placeholder="Was muss erledigt werden?" required autofocus>
            </div>

            <div class="form-row">
                <label for="task-description">Beschreibung</label>
                <textarea id="task-description" placeholder="Optionale Details..." rows="2"></textarea>
            </div>

            <div class="form-row-half">
                <div class="form-row">
                    <label for="task-estimated">Geschätzte Zeit (Std.)</label>
                    <input type="number" id="task-estimated" step="0.25" min="0" placeholder="1.5" value="">
                </div>
                <div class="form-row">
                    <label for="task-date">Datum</label>
                    <input type="date" id="task-date">
                </div>
            </div>

            <div class="form-row">
                <label class="checkbox-label">
                    <input type="checkbox" id="task-recurring">
                    <span>Wiederkehrende Aufgabe</span>
                </label>
            </div>

            <div class="form-row recurring-options" id="recurring-options" style="display:none">
                <label for="task-recurring-interval">Wiederholen</label>
                <select id="task-recurring-interval">
                    <option value="daily">Täglich</option>
                    <option value="weekly" selected>Wöchentlich</option>
                    <option value="monthly">Monatlich</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary modal-close">Abbrechen</button>
                <button type="button" id="delete-task-btn" class="btn-danger" style="display:none">Löschen</button>
                <button type="submit" class="btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Complete Task (with time entry) -->
<div id="modal-complete" class="planner-modal" style="display:none">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3><i class='bx bx-check-circle'></i> Aufgabe abschließen</h3>
            <button type="button" class="modal-close"><i class='bx bx-x'></i></button>
        </div>
        <form id="form-complete">
            <input type="hidden" id="complete-task-id" value="0">

            <div class="task-summary" id="complete-task-summary">
                <!-- Task title shown here -->
            </div>

            <div class="form-row">
                <label for="complete-hours">Tatsächliche Zeit (Std.)</label>
                <input type="number" id="complete-hours" step="0.25" min="0" placeholder="0">
            </div>

            <div class="form-row">
                <label class="checkbox-label">
                    <input type="checkbox" id="complete-create-entry">
                    <span>Zeiteintrag erstellen</span>
                </label>
            </div>

            <div class="time-entry-options" id="time-entry-options" style="display:none">
                <div class="form-row">
                    <label for="complete-client">Kunde *</label>
                    <select id="complete-client" required>
                        <option value="">Bitte wählen</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client->id; ?>"><?php echo esc_html($client->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="complete-project">Projekt</label>
                    <select id="complete-project">
                        <option value="0">Kein Projekt</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary modal-close">Abbrechen</button>
                <button type="submit" class="btn-primary"><i class='bx bx-check'></i> Abschließen</button>
            </div>
        </form>
    </div>
</div>
