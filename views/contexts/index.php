<div class="page-header">
    <h1>Kontexte</h1>
    <div class="page-actions">
        <a href="/contexts/new" class="btn btn-primary">
            <span class="btn-icon">+</span> Neuer Kontext
        </a>
    </div>
</div>

<?php if (\Core\Auth::isAdmin() && !empty($customers)): ?>
<div class="card mb-lg">
    <div class="filter-bar">
        <div class="filter-group">
            <label>Kunde</label>
            <select id="filter-customer" onchange="filterContexts()">
                <option value="">Alle Kunden</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($contexts)): ?>
    <div class="empty-state">
        <div class="empty-icon"><span class="material-symbols-rounded">dataset</span></div>
        <h3>Noch keine Kontexte vorhanden</h3>
        <p>Erstelle einen Kontext, um Kunden-Informationen, Wissen, URLs und Regeln zu bündeln.</p>
        <a href="/contexts/new" class="btn btn-primary">Ersten Kontext erstellen</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="data-table sortable-table" id="contexts-table">
                <thead>
                    <tr>
                        <th data-sort="name" class="sortable-header">Name</th>
                        <th data-sort="customer" class="sortable-header">Kunde</th>
                        <th data-sort="items" class="sortable-header" style="text-align: center;">Items</th>
                        <th data-sort="orders" class="sortable-header" style="text-align: center;">Aufträge</th>
                        <th data-sort="creator" class="sortable-header">Ersteller</th>
                        <th data-sort="date" class="sortable-header">Erstellt</th>
                        <th style="width: 100px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contexts as $ctx): ?>
                    <tr class="table-row-clickable" data-href="/contexts/<?= $ctx['id'] ?>/edit"
                        data-customer="<?= $ctx['customer_id'] ?? '' ?>">
                        <td>
                            <div class="table-title"><?= htmlspecialchars($ctx['name']) ?></div>
                            <?php if ($ctx['description']): ?>
                                <div class="table-subtitle"><?= htmlspecialchars(substr($ctx['description'], 0, 80)) ?><?= strlen($ctx['description']) > 80 ? '...' : '' ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $ctx['customer_name'] ? htmlspecialchars($ctx['customer_name']) : '<span class="text-muted">Allgemein</span>' ?></td>
                        <td style="text-align: center;"><?= (int)$ctx['items_count'] ?></td>
                        <td style="text-align: center;"><?= (int)$ctx['orders_count'] ?></td>
                        <td><span class="text-muted"><?= htmlspecialchars($ctx['creator_name']) ?></span></td>
                        <td><?= date('d.m.Y', strtotime($ctx['created_at'])) ?></td>
                        <td class="table-actions" onclick="event.stopPropagation();">
                            <a href="/contexts/<?= $ctx['id'] ?>/edit" class="btn-icon" title="Bearbeiten"><span class="material-symbols-rounded">edit</span></a>
                            <button class="btn-icon btn-danger" onclick="deleteContext(<?= $ctx['id'] ?>)" title="Deaktivieren"><span class="material-symbols-rounded">delete</span></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.table-row-clickable').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => window.location.href = row.dataset.href);
});

document.querySelectorAll('.sortable-header').forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => sortTable(header));
});

let currentSort = { col: null, asc: true };

function sortTable(header) {
    const col = header.dataset.sort;
    const table = document.getElementById('contexts-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    currentSort.asc = currentSort.col === col ? !currentSort.asc : true;
    currentSort.col = col;

    document.querySelectorAll('.sortable-header').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    header.classList.add(currentSort.asc ? 'sort-asc' : 'sort-desc');

    const colIndex = Array.from(header.parentElement.children).indexOf(header);

    rows.sort((a, b) => {
        let valA = a.children[colIndex].textContent.trim();
        let valB = b.children[colIndex].textContent.trim();

        if (col === 'items' || col === 'orders') {
            return currentSort.asc ? (parseInt(valA)||0) - (parseInt(valB)||0) : (parseInt(valB)||0) - (parseInt(valA)||0);
        }
        if (col === 'date') {
            const parseDate = s => { const p = s.split('.'); return new Date(p[2], p[1]-1, p[0]); };
            return currentSort.asc ? parseDate(valA) - parseDate(valB) : parseDate(valB) - parseDate(valA);
        }
        return currentSort.asc ? valA.localeCompare(valB, 'de') : valB.localeCompare(valA, 'de');
    });

    rows.forEach(row => tbody.appendChild(row));
}

function filterContexts() {
    const customerId = document.getElementById('filter-customer')?.value || '';
    const rows = document.querySelectorAll('#contexts-table tbody tr');

    rows.forEach(row => {
        row.style.display = (!customerId || row.dataset.customer === customerId) ? '' : 'none';
    });
}

async function deleteContext(id) {
    if (!confirm('Kontext wirklich deaktivieren?')) return;

    try {
        await App.delete('/contexts/' + id);
        App.showNotification('Kontext deaktiviert', 'success');
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}
</script>
