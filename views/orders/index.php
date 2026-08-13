<div class="page-header">
    <h1>Aufträge</h1>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="App.openModal('new-order-modal')">
            <span class="btn-icon">+</span> Neuer Auftrag
        </button>
    </div>
</div>

<!-- Filter -->
<div class="filter-bar">
    <?php if (\Core\Auth::isAdmin() && !empty($customers)): ?>
    <div class="filter-group">
        <label>Kunde</label>
        <select id="filter-customer" onchange="filterOrders()">
            <option value="">Alle Kunden</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="filter-group">
        <label>Status</label>
        <select id="filter-status" onchange="filterOrders()">
            <option value="">Alle Status</option>
            <option value="briefing">Briefing</option>
            <option value="briefing_approved">Freigegeben</option>
            <option value="generating">Wird generiert</option>
            <option value="editing">In Bearbeitung</option>
            <option value="completed">Abgeschlossen</option>
        </select>
    </div>
</div>

<!-- Aufträge -->
<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="empty-icon"><span class="material-symbols-rounded">assignment</span></div>
        <h3>Noch keine Aufträge vorhanden</h3>
        <p>Erstelle einen neuen Auftrag, um KI-gestützte Artikel zu generieren.</p>
        <button class="btn btn-primary" onclick="App.openModal('new-order-modal')">Ersten Auftrag erstellen</button>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="data-table sortable-table" id="orders-table">
                <thead>
                    <tr>
                        <th data-sort="status" class="sortable-header">Status</th>
                        <th data-sort="title" class="sortable-header">Titel</th>
                        <th data-sort="customer" class="sortable-header">Kunde</th>
                        <th data-sort="model" class="sortable-header">Modell</th>
                        <th data-sort="words" class="sortable-header" style="text-align: right;">Wörter</th>
                        <th data-sort="version" class="sortable-header" style="text-align: center;">Version</th>
                        <th data-sort="date" class="sortable-header">Erstellt</th>
                        <th style="width: 100px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusLabels = [
                        'briefing' => ['Briefing', '#f59e0b'],
                        'briefing_approved' => ['Freigegeben', '#3b82f6'],
                        'generating' => ['Generierung', '#1976d2'],
                        'editing' => ['Bearbeitung', '#22c55e'],
                        'completed' => ['Fertig', '#6b7280']
                    ];
                    foreach ($orders as $order):
                        $st = $statusLabels[$order['status']] ?? ['Unbekannt', '#6b7280'];
                    ?>
                    <tr class="table-row-clickable" data-href="/orders/<?= $order['id'] ?>/workspace"
                        data-customer="<?= $order['customer_id'] ?? '' ?>" data-status="<?= $order['status'] ?>">
                        <td>
                            <span class="status-badge" style="background: <?= $st[1] ?>20; color: <?= $st[1] ?>; border: 1px solid <?= $st[1] ?>40;">
                                <?= $st[0] ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-title"><?= htmlspecialchars($order['title']) ?></div>
                        </td>
                        <td><?= $order['customer_name'] ? htmlspecialchars($order['customer_name']) : '<span class="text-muted">—</span>' ?></td>
                        <td><span class="text-muted"><?= $order['model'] ? htmlspecialchars($order['model']) : '—' ?></span></td>
                        <td style="text-align: right;"><?= $order['word_count'] ? number_format((int)$order['word_count'], 0, ',', '.') : '—' ?></td>
                        <td style="text-align: center;"><?= $order['current_version'] > 0 ? 'v' . $order['current_version'] : '—' ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                        <td onclick="event.stopPropagation();">
                            <div class="table-actions">
                                <button class="btn-icon" onclick="duplicateOrder(<?= $order['id'] ?>)" title="Duplizieren">
                                    <span class="material-symbols-rounded">content_copy</span>
                                </button>
                                <button class="btn-icon btn-danger" onclick="deleteOrder(<?= $order['id'] ?>)" title="Löschen">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Neuer Auftrag Modal -->
<div class="modal" id="new-order-modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h2>Neuer Auftrag</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <form id="new-order-form" onsubmit="createOrder(event)">
                <div class="form-group">
                    <label for="order-title">Titel / Thema *</label>
                    <input type="text" id="order-title" placeholder="z.B. Blogartikel über KI im Marketing" required>
                </div>

                <?php if (\Core\Auth::isAdmin() && !empty($customers)): ?>
                <div class="form-group">
                    <label for="order-customer">Kunde</label>
                    <select id="order-customer">
                        <option value="">Kein Kunde</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="order-model">KI-Modell</label>
                    <select id="order-model">
                        <option value="">Standard-Modell</option>
                        <?php foreach ($aiModels as $m): ?>
                            <option value="<?= htmlspecialchars($m['model_id']) ?>"><?= htmlspecialchars($m['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                    <button type="submit" class="btn btn-primary" id="create-order-btn">Auftrag erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Clickable table rows
document.querySelectorAll('.table-row-clickable').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => window.location.href = row.dataset.href);
});

// Client-side sorting
document.querySelectorAll('.sortable-header').forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => sortTable(header));
});

let currentSort = { col: null, asc: true };

function sortTable(header) {
    const col = header.dataset.sort;
    const table = document.getElementById('orders-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    currentSort.asc = currentSort.col === col ? !currentSort.asc : true;
    currentSort.col = col;

    // Update header indicators
    document.querySelectorAll('.sortable-header').forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
    });
    header.classList.add(currentSort.asc ? 'sort-asc' : 'sort-desc');

    const colIndex = Array.from(header.parentElement.children).indexOf(header);

    rows.sort((a, b) => {
        let valA = a.children[colIndex].textContent.trim();
        let valB = b.children[colIndex].textContent.trim();

        // Numeric sort for words/version columns
        if (col === 'words' || col === 'version') {
            valA = parseInt(valA.replace(/[^\d]/g, '')) || 0;
            valB = parseInt(valB.replace(/[^\d]/g, '')) || 0;
            return currentSort.asc ? valA - valB : valB - valA;
        }

        // Date sort
        if (col === 'date') {
            const parseDate = s => { const p = s.split(/[\s.:]/); return new Date(p[2], p[1]-1, p[0], p[3]||0, p[4]||0); };
            return currentSort.asc ? parseDate(valA) - parseDate(valB) : parseDate(valB) - parseDate(valA);
        }

        // String sort
        return currentSort.asc ? valA.localeCompare(valB, 'de') : valB.localeCompare(valA, 'de');
    });

    rows.forEach(row => tbody.appendChild(row));
}

function filterOrders() {
    const customerId = document.getElementById('filter-customer')?.value || '';
    const status = document.getElementById('filter-status')?.value || '';
    const rows = document.querySelectorAll('#orders-table tbody tr');

    rows.forEach(row => {
        const matchCustomer = !customerId || row.dataset.customer === customerId;
        const matchStatus = !status || row.dataset.status === status;
        row.style.display = (matchCustomer && matchStatus) ? '' : 'none';
    });
}

async function createOrder(e) {
    e.preventDefault();

    const title = document.getElementById('order-title').value.trim();
    const model = document.getElementById('order-model').value || null;
    const customerEl = document.getElementById('order-customer');
    const customerId = customerEl ? customerEl.value || null : null;

    if (!title) {
        App.showNotification('Titel erforderlich', 'error');
        return;
    }

    const btn = document.getElementById('create-order-btn');
    btn.disabled = true;
    btn.textContent = 'Erstelle...';

    try {
        const response = await App.post('/orders', {
            title: title,
            customer_id: customerId,
            model: model
        });

        App.showNotification('Auftrag erstellt', 'success');
        window.location.href = '/orders/' + response.data.id + '/workspace';
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Auftrag erstellen';
    }
}

async function duplicateOrder(id) {
    try {
        const response = await App.post('/orders/' + id + '/duplicate');
        App.showNotification('Auftrag dupliziert', 'success');
        window.location.href = '/orders/' + response.data.id + '/workspace';
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

async function deleteOrder(id) {
    if (!confirm('Auftrag wirklich löschen? Alle Versionen und Chat-Verläufe gehen verloren.')) return;

    try {
        await App.delete('/orders/' + id);
        App.showNotification('Auftrag gelöscht', 'success');
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}
</script>
