<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Get all clients for filter dropdown
global $wpdb;
$clients_table = $wpdb->prefix . 'tallyr_clients';
$clients = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, hexcolor FROM $clients_table WHERE userid = %d AND state = 1 ORDER BY title ASC",
    get_current_user_id()
));
?>

<div id="recurring-dashboard">
    <!-- Header with Add Button -->
    <div class="recurring-header">
        <h2><i class='bx bx-refresh'></i> Wiederkehrende Rechnungen</h2>
        <div class="header-actions">
            <button type="button" id="manage-categories-btn" class="btn-secondary"><i class='bx bx-category'></i> Kategorien</button>
            <button type="button" id="add-recurring-btn" class="btn-primary"><i class='bx bx-plus'></i> Neu anlegen</button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div id="recurring-summary">
        <div class="summary-card summary-due">
            <div class="summary-icon"><i class='bx bx-error-circle'></i></div>
            <div class="summary-content">
                <div class="summary-value" id="due-count">0</div>
                <div class="summary-label">Fällig</div>
            </div>
        </div>
        <div class="summary-card summary-monthly">
            <div class="summary-icon"><i class='bx bx-calendar'></i></div>
            <div class="summary-content">
                <div class="summary-value" id="monthly-total">0 €</div>
                <div class="summary-label">Monatlich (Ø) Aktiv</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class='bx bx-time'></i></div>
            <div class="summary-content">
                <div class="summary-value" id="monthly-waiting">0 €</div>
                <div class="summary-label">Monatlich (Ø) Wartend</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div id="recurring-filters">
        <div class="filter-group">
            <label>Kunde:</label>
            <select id="filter-recurring-client">
                <option value="">Alle Kunden</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client->id; ?>"><?php echo esc_html($client->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Kategorie:</label>
            <select id="filter-recurring-category">
                <option value="">Alle Kategorien</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Status:</label>
            <select id="filter-recurring-partner">
                <option value="">Alle Partner</option>
            </select>
            <select id="filter-recurring-status">
                <option value="">Alle</option>
                <option value="active">Aktiv</option>
                <option value="waiting">Wartend</option>
                <option value="paused">Pausiert</option>
                <option value="cancelled">Beendet</option>
            </select>
        </div>
    </div>

    <!-- Due Items Section -->
    <div id="recurring-due-section" style="display:none">
        <h3 class="section-title section-due"><i class='bx bx-error-circle'></i> Fällig</h3>
        <div id="recurring-due-list" class="recurring-list"></div>
    </div>

    <!-- Active Items Section -->
    <div id="recurring-active-section">
        <h3 class="section-title"><i class='bx bx-check-circle'></i> Aktiv</h3>
        <div id="recurring-active-list" class="recurring-list"></div>
    </div>

    <!-- Other Items Section (paused/cancelled) -->
    <div id="recurring-other-section" style="display:none">
        <h3 class="section-title section-other"><i class='bx bx-pause-circle'></i> Pausiert / Beendet</h3>
        <div id="recurring-other-list" class="recurring-list"></div>
    </div>

    <!-- Loading -->
    <div id="recurring-loading" style="display:none">
        <i class="bx bx-loader-alt bx-spin"></i> Lade...
    </div>
</div>

<!-- Modal: Add/Edit Recurring -->
<div id="modal-recurring" class="recurring-modal" style="display:none">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-recurring-title">Neue wiederkehrende Rechnung</h3>
            <button type="button" class="modal-close"><i class='bx bx-x'></i></button>
        </div>
        <form id="form-recurring">
            <input type="hidden" id="recurring-id" value="0">

            <div class="form-row">
                <label for="recurring-client">Kunde *</label>
                <select id="recurring-client" required>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client->id; ?>"><?php echo esc_html($client->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="recurring-title">Bezeichnung / Domain</label>
                <input type="text" id="recurring-title" placeholder="z.B. domain.de">
            </div>

            <div class="form-row">
                <label for="recurring-partner">Partner</label>
                <input type="text" id="recurring-partner" list="recurring-partner-list" placeholder="z.B. Agenturname" autocomplete="off">
                <datalist id="recurring-partner-list"></datalist>
            </div>

            <div class="form-row-half">
                <div class="form-row">
                    <label for="recurring-category">Kategorie</label>
                    <div class="input-with-btn">
                        <select id="recurring-category">
                            <option value="">Bitte wählen</option>
                        </select>
                        <button type="button" id="quick-add-category" title="Neue Kategorie"><i class='bx bx-plus'></i></button>
                    </div>
                </div>
                <div class="form-row">
                    <label for="recurring-status">Status</label>
                    <select id="recurring-status">
                        <option value="waiting">Wartend</option>
                        <option value="active">Aktiv</option>
                        <option value="paused">Pausiert</option>
                        <option value="cancelled">Beendet</option>
                    </select>
                </div>
            </div>

            <div class="form-row-half">
                <div class="form-row">
                    <label for="recurring-amount">Betrag (€)</label>
                    <input type="number" id="recurring-amount" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-row">
                    <label for="recurring-interval">Intervall</label>
                    <select id="recurring-interval">
                        <option value="monthly">Monatlich</option>
                        <option value="quarterly">Quartalsweise</option>
                        <option value="yearly">Jährlich</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <label for="recurring-description">Beschreibung</label>
                <textarea id="recurring-description" placeholder="Optionale Details..."></textarea>
            </div>

            <div class="form-row-half">
                <div class="form-row">
                    <label for="recurring-start">Startdatum</label>
                    <input type="date" id="recurring-start">
                </div>
                <div class="form-row">
                    <label for="recurring-end">Enddatum</label>
                    <input type="date" id="recurring-end">
                </div>
            </div>

            <div class="form-row">
                <label for="recurring-next">Nächste Abrechnung</label>
                <input type="date" id="recurring-next">
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary modal-close">Abbrechen</button>
                <button type="submit" class="btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Categories Management -->
<div id="modal-categories" class="recurring-modal" style="display:none">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3>Kategorien verwalten</h3>
            <button type="button" class="modal-close"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="category-add-form">
                <input type="text" id="new-category-name" placeholder="Neue Kategorie...">
                <button type="button" id="add-category-btn"><i class='bx bx-plus'></i></button>
            </div>
            <div id="categories-list"></div>
        </div>
    </div>
</div>

<!-- Modal: Billing Log -->
<div id="modal-log" class="recurring-modal" style="display:none">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3>Abrechnungs-Historie</h3>
            <button type="button" class="modal-close"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div id="log-list"></div>
        </div>
    </div>
</div>
