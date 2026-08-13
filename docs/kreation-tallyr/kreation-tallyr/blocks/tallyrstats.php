<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


// start date get current month and first day of month
$startdate_standard = date('Y-m-01');

// end date get current month and last day of month
$enddate_standard = date('Y-m-t');

?>
<div id="stats-dashboard">
    <div id="times_datepicker_admin">
        <div class="container flex">
            <div class="datepicker">
                Vom <input type="date" id="datepicker_start" name="datepicker_start" value="<?php echo $startdate_standard ; ?>">
                bis <input type="date" id="datepicker_end" name="datepicker_end" value="<?php echo $enddate_standard ; ?>">
            </div>
            <div id="monthpickerwrap">
                <button id="monthpicker">Monat auswählen</button>
                <div id="coolpick" style="display:none">
                    <div id="yearstopick">
                        <?php // all years from 2024 to current year
                        $year = date('Y');
                        for ($i = 2024; $i <= $year; $i++) {
                            // mark current year as active
                            if ($i == $year) {
                                $active = ' active';
                            } else {
                                $active = '';
                            }
                            ?>
                            <button class="year<?php echo $active; ?>" data-year="<?php echo $i; ?>"><?php echo $i; ?></button>
                        <?php } ?>
                    </div>
                    <div id="monthstopick">
                        <?php
                        // Set the locale to German
                        setlocale(LC_TIME, 'de_DE.UTF-8');

                        // All months, mark the current month as active, echo German month names
                        $month = date('m');
                        for ($i = 1; $i <= 12; $i++) {
                            if ($i == $month) {
                                $active = ' active';
                            } else {
                                $active = '';
                            }
                            ?>
                            <button class="month<?php echo $active; ?>" data-month="<?php echo sprintf('%02d', $i); ?>">
                                <?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $i, 1))); ?>
                            </button>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="stats-loading" style="display:none">
        <i class="bx bx-loader-alt bx-spin"></i> Lade Statistiken...
    </div>

    <!-- KPI Cards -->
    <div id="kpi-cards">
        <div class="kpi-card kpi-hours">
            <div class="kpi-icon"><i class="bx bx-time"></i></div>
            <div class="kpi-content">
                <div class="kpi-value" id="kpi-total-hours">-</div>
                <div class="kpi-label">Stunden gesamt</div>
                <div class="kpi-sub" id="kpi-billable-hours">- abrechenbar</div>
            </div>
            <div class="kpi-trend" id="kpi-hours-trend"></div>
        </div>
        <div class="kpi-card kpi-revenue">
            <div class="kpi-icon"><i class="bx bx-euro"></i></div>
            <div class="kpi-content">
                <div class="kpi-value" id="kpi-total-revenue">-</div>
                <div class="kpi-label">Umsatz</div>
                <div class="kpi-sub" id="kpi-open-revenue">- offen</div>
            </div>
            <div class="kpi-trend" id="kpi-revenue-trend"></div>
        </div>
        <div class="kpi-card kpi-rate">
            <div class="kpi-icon"><i class="bx bx-trending-up"></i></div>
            <div class="kpi-content">
                <div class="kpi-value" id="kpi-avg-rate">-</div>
                <div class="kpi-label">Ø Stundensatz</div>
                <div class="kpi-sub" id="kpi-billed-revenue">- abgerechnet</div>
            </div>
        </div>
        <div class="kpi-card kpi-count">
            <div class="kpi-icon"><i class="bx bx-briefcase"></i></div>
            <div class="kpi-content">
                <div class="kpi-value" id="kpi-project-count">-</div>
                <div class="kpi-label">Projekte</div>
                <div class="kpi-sub" id="kpi-entry-count">- Einträge</div>
            </div>
        </div>
    </div>

    <!-- Comparison Banner -->
    <div id="period-comparison" style="display:none">
        <span class="comparison-label">Vergleich zum Vorzeitraum:</span>
        <span class="comparison-item" id="comp-hours"></span>
        <span class="comparison-item" id="comp-revenue"></span>
    </div>

    <!-- Filters -->
    <div id="stats-filters">
        <div class="filter-group">
            <label>Kunde:</label>
            <select id="filter-client">
                <option value="">Alle Kunden</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Status:</label>
            <select id="filter-billable">
                <option value="">Alle</option>
                <option value="billable">Abrechenbar</option>
                <option value="notbillable">Nicht abrechenbar</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Abrechnung:</label>
            <select id="filter-billed">
                <option value="">Alle</option>
                <option value="billed">Abgerechnet</option>
                <option value="notbilled">Offen</option>
            </select>
        </div>
        <button id="stats-export-btn" class="export-btn"><i class="bx bx-spreadsheet"></i> Excel Export</button>
    </div>

    <!-- Charts Row -->
    <div id="charts-row">
        <!-- Client Distribution Chart -->
        <div class="chart-card" id="client-chart-card">
            <h3><i class="bx bx-pie-chart-alt-2"></i> Stunden nach Kunde</h3>
            <div id="client-chart">
                <div id="client-bars"></div>
            </div>
        </div>

        <!-- Weekday Pattern -->
        <div class="chart-card" id="weekday-chart-card">
            <h3><i class="bx bx-calendar-week"></i> Wochentag-Verteilung</h3>
            <div id="weekday-chart">
                <div id="weekday-bars"></div>
            </div>
        </div>
    </div>

    <!-- Projects Overview Table -->
    <div id="projects-overview" class="data-section">
        <h3><i class="bx bx-folder"></i> Projekt-Übersicht</h3>
        <table id="projects-table">
            <thead>
                <tr>
                    <th>Projekt</th>
                    <th>Kunde</th>
                    <th class="num">Stunden</th>
                    <th class="num">Umsatz</th>
                    <th class="num">Ø Satz</th>
                </tr>
            </thead>
            <tbody id="projects-tbody">
            </tbody>
        </table>
    </div>

    <!-- Time Entries Table -->
    <div id="entries-section" class="data-section">
        <h3><i class="bx bx-list-ul"></i> Zeiteinträge (<span id="entries-count">0</span>)</h3>
        <table id="entries-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kunde</th>
                    <th>Projekt</th>
                    <th>Beschreibung</th>
                    <th class="num">Stunden</th>
                    <th class="num">Satz</th>
                    <th class="num">Betrag</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="entries-tbody">
            </tbody>
        </table>
    </div>

</div>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
