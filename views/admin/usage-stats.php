<div class="page-header">
    <h1>Verbrauchsstatistiken</h1>
    <div class="month-selector">
        <input type="month" id="month-select" value="<?= htmlspecialchars($stats['month'] ?? date('Y-m')) ?>" onchange="window.location.href='/admin/usage?month='+this.value">
    </div>
</div>

<!-- Gesamtübersicht -->
<div class="stats-grid mb-lg">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">monitoring</span></div>
        <div class="stat-content">
            <span class="stat-value" id="total-calls"><?= number_format($stats['totals']['total_calls'] ?? 0) ?></span>
            <span class="stat-label">API-Aufrufe</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">article</span></div>
        <div class="stat-content">
            <span class="stat-value" id="total-words"><?= number_format($stats['totals']['total_words'] ?? 0) ?></span>
            <span class="stat-label">Wörter generiert</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">token</span></div>
        <div class="stat-content">
            <span class="stat-value" id="total-tokens">
                <?= number_format(($stats['totals']['total_input'] ?? 0) + ($stats['totals']['total_output'] ?? 0)) ?>
            </span>
            <span class="stat-label">Tokens gesamt</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">payments</span></div>
        <div class="stat-content">
            <span class="stat-value" id="total-cost">
                $<?= number_format($stats['totals']['total_cost'] ?? 0, 2) ?>
            </span>
            <span class="stat-label">Geschätzte Kosten</span>
        </div>
    </div>
</div>

<!-- Tabellen-Grid -->
<div class="usage-tables-grid">
    <!-- Kunden-Aufschlüsselung -->
    <div class="card">
        <h3 class="card-title">Verbrauch nach Kunde</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Kunde</th>
                        <th class="text-right">Calls</th>
                        <th class="text-right">Input</th>
                        <th class="text-right">Output</th>
                        <th class="text-right">Kosten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['by_customer'])): ?>
                        <tr><td colspan="5" class="text-center text-muted">Keine Daten für diesen Monat</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats['by_customer'] as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                                <td class="text-right"><?= number_format($row['total_calls']) ?></td>
                                <td class="text-right"><?= number_format($row['total_tokens_input']) ?></td>
                                <td class="text-right"><?= number_format($row['total_tokens_output']) ?></td>
                                <td class="text-right">$<?= number_format($row['total_cost_estimate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modell-Aufschlüsselung -->
    <div class="card">
        <h3 class="card-title">Verbrauch nach Modell</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Modell</th>
                        <th class="text-right">Calls</th>
                        <th class="text-right">Input</th>
                        <th class="text-right">Output</th>
                        <th class="text-right">Kosten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['by_model'])): ?>
                        <tr><td colspan="5" class="text-center text-muted">Keine Daten für diesen Monat</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats['by_model'] as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['model_used']) ?></strong></td>
                                <td class="text-right"><?= number_format($row['total_calls']) ?></td>
                                <td class="text-right"><?= number_format($row['total_tokens_input']) ?></td>
                                <td class="text-right"><?= number_format($row['total_tokens_output']) ?></td>
                                <td class="text-right">$<?= number_format($row['total_cost_estimate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="usage-tables-grid mt-lg">
    <!-- Aktion-Aufschlüsselung -->
    <div class="card">
        <h3 class="card-title">Verbrauch nach Aktion</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Aktion</th>
                        <th class="text-right">Calls</th>
                        <th class="text-right">Tokens</th>
                        <th class="text-right">Kosten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['by_action'])): ?>
                        <tr><td colspan="4" class="text-center text-muted">Keine Daten</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats['by_action'] as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['action_type']) ?></strong></td>
                                <td class="text-right"><?= number_format($row['total_calls']) ?></td>
                                <td class="text-right"><?= number_format($row['total_tokens_input'] + $row['total_tokens_output']) ?></td>
                                <td class="text-right">$<?= number_format($row['total_cost_estimate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top-User -->
    <div class="card">
        <h3 class="card-title">Top-Nutzer</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nutzer</th>
                        <th class="text-right">Calls</th>
                        <th class="text-right">Kosten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['by_user'])): ?>
                        <tr><td colspan="3" class="text-center text-muted">Keine Daten</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats['by_user'] as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                                </td>
                                <td class="text-right"><?= number_format($row['total_calls']) ?></td>
                                <td class="text-right">$<?= number_format($row['total_cost_estimate'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.month-selector {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}
.month-selector input {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
}
.card-title {
    padding: var(--spacing-lg);
    margin: 0;
    border-bottom: 1px solid var(--color-gray-200);
}
.usage-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: var(--spacing-lg);
}
@media (max-width: 1100px) {
    .usage-tables-grid {
        grid-template-columns: 1fr;
    }
}
.mt-lg {
    margin-top: var(--spacing-lg);
}
.text-success {
    color: #059669;
}
.text-danger {
    color: #dc2626;
}
.rating-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    font-size: var(--d-fs-sm);
}
.rating-good {
    background: #d1fae5;
    color: #065f46;
}
.rating-ok {
    background: #fef3c7;
    color: #92400e;
}
.rating-bad {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<script>
// Kein JS-Reload-Trick mehr — Monats-Wechsel macht jetzt einen sauberen Reload via ?month=
async function _legacyLoadStats() {
    const month = document.getElementById('month-select').value;

    try {
        const response = await App.get('/usage?month=' + month);
        const data = response.data;

        // Totals aktualisieren
        if (data.totals) {
            document.getElementById('total-calls').textContent = App.formatNumber(data.totals.total_calls || 0);
            document.getElementById('total-words').textContent = App.formatNumber(data.totals.total_words || 0);
            document.getElementById('total-tokens').textContent = App.formatNumber(
                (data.totals.total_input || 0) + (data.totals.total_output || 0)
            );
            document.getElementById('total-cost').textContent = '$' + parseFloat(data.totals.total_cost || 0).toFixed(2);
        }

        // Kunden-Tabelle aktualisieren
        const customerTbody = document.getElementById('customer-stats');
        if (data.by_customer && data.by_customer.length > 0) {
            customerTbody.innerHTML = data.by_customer.map(row => `
                <tr>
                    <td><strong>${App.escapeHtml(row.customer_name)}</strong></td>
                    <td class="text-right">${App.formatNumber(row.total_api_calls)}</td>
                    <td class="text-right">${App.formatNumber(row.total_tokens_input)}</td>
                    <td class="text-right">${App.formatNumber(row.total_tokens_output)}</td>
                    <td class="text-right">${App.formatNumber(row.total_words_generated)}</td>
                    <td class="text-right">$${parseFloat(row.total_cost_estimate).toFixed(2)}</td>
                </tr>
            `).join('');
        } else {
            customerTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Keine Daten</td></tr>';
        }

        // Modell-Tabelle aktualisieren
        const modelTbody = document.getElementById('model-stats');
        if (data.by_model && data.by_model.length > 0) {
            modelTbody.innerHTML = data.by_model.map(row => `
                <tr>
                    <td><strong>${App.escapeHtml(row.model_used)}</strong></td>
                    <td class="text-right">${App.formatNumber(row.total_calls)}</td>
                    <td class="text-right">${App.formatNumber(row.total_tokens_input)}</td>
                    <td class="text-right">${App.formatNumber(row.total_tokens_output)}</td>
                    <td class="text-right">${App.formatNumber(row.total_words_generated)}</td>
                    <td class="text-right">$${parseFloat(row.total_cost_estimate).toFixed(2)}</td>
                </tr>
            `).join('');
        } else {
            modelTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Keine Daten</td></tr>';
        }

        // Bewertungs-Tabelle aktualisieren
        const ratingTbody = document.getElementById('rating-stats');
        if (data.model_ratings && data.model_ratings.length > 0) {
            ratingTbody.innerHTML = data.model_ratings.map(row => {
                const rate = parseFloat(row.positive_rate) || 0;
                const badgeClass = rate >= 70 ? 'rating-good' : (rate >= 40 ? 'rating-ok' : 'rating-bad');
                return `
                <tr>
                    <td><strong>${App.escapeHtml(row.model_used)}</strong></td>
                    <td class="text-right">${App.formatNumber(row.total_ratings)}</td>
                    <td class="text-right text-success">${App.formatNumber(row.positive)}</td>
                    <td class="text-right">${App.formatNumber(row.neutral)}</td>
                    <td class="text-right text-danger">${App.formatNumber(row.negative)}</td>
                    <td class="text-right"><span class="rating-badge ${badgeClass}">${rate}%</span></td>
                </tr>
            `}).join('');
        } else {
            ratingTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Noch keine Bewertungen</td></tr>';
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}
</script>
