<div class="page-header">
    <h1>Projekte</h1>
    <div class="page-header-actions">
        <a href="/projects/new" class="btn btn-primary">
            <span class="btn-icon">+</span> Neues Projekt
        </a>
    </div>
</div>

<!-- Such- und Filterleiste -->
<div class="projects-toolbar">
    <div class="search-box">
        <span class="material-symbols-rounded search-icon">search</span>
        <input type="text" id="project-search" placeholder="Projekte durchsuchen..." oninput="filterProjects()">
    </div>

    <div class="filter-buttons" id="status-filter">
        <button class="filter-btn active" data-status="all">Alle</button>
        <button class="filter-btn" data-status="draft">Entwurf</button>
        <button class="filter-btn" data-status="in_progress">In Arbeit</button>
        <button class="filter-btn" data-status="review">Review</button>
        <button class="filter-btn" data-status="completed">Fertig</button>
    </div>

    <?php if (!empty($customers)): ?>
    <div class="filter-group">
        <select id="customer-filter" onchange="filterByCustomer(this.value)">
            <option value="">Alle Kunden</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?= $customer['id'] ?>" <?= ($filterCustomerId ?? '') == $customer['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($customer['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</div>

<!-- Ergebnisanzeige -->
<div class="projects-count" id="projects-count">
    <?= count($projects) ?> Projekte gefunden
</div>

<div class="projects-grid" id="projects-grid">
    <?php if (empty($projects)): ?>
        <div class="card text-center" style="padding: var(--spacing-2xl); grid-column: 1/-1;">
            <p class="text-muted mb-lg">Noch keine Projekte vorhanden</p>
            <a href="/projects/new" class="btn btn-primary btn-large">Erstes Projekt starten</a>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <div class="project-card card" data-id="<?= $project['id'] ?>" data-status="<?= $project['status'] ?>" data-title="<?= htmlspecialchars(strtolower($project['title'])) ?>" data-website="<?= htmlspecialchars(strtolower($project['target_website'] ?? '')) ?>">
                <div class="project-status status-<?= $project['status'] ?>">
                    <?php
                    $statusLabels = [
                        'draft' => 'Entwurf',
                        'in_progress' => 'In Arbeit',
                        'review' => 'Review',
                        'completed' => 'Fertig'
                    ];
                    echo $statusLabels[$project['status']] ?? $project['status'];
                    ?>
                </div>

                <h3 class="project-title">
                    <?php if (!empty($project['customer_abbreviation'])): ?>
                        <span class="customer-badge">[<?= htmlspecialchars($project['customer_abbreviation']) ?>]</span>
                    <?php endif; ?>
                    <?= htmlspecialchars($project['title']) ?>
                </h3>

                <?php if ($project['target_website']): ?>
                    <p class="project-website text-muted">
                        <?= htmlspecialchars($project['target_website']) ?>
                    </p>
                <?php endif; ?>

                <div class="project-meta">
                    <span>
                        <?= number_format($project['target_word_count']) ?> Wörter Ziel
                    </span>
                    <span>
                        Version <?= $project['current_version'] ?>
                    </span>
                </div>

                <div class="project-footer">
                    <span class="project-author">
                        <?= htmlspecialchars($project['author_name']) ?>
                    </span>
                    <span class="project-date">
                        <?= date('d.m.Y', strtotime($project['updated_at'])) ?>
                    </span>
                </div>

                <div class="project-actions">
                    <a href="/projects/<?= $project['id'] ?>" class="btn btn-primary btn-small">
                        Öffnen
                    </a>
                    <button class="btn btn-small btn-danger btn-icon"
                            onclick="deleteProject(<?= $project['id'] ?>)" title="Löschen">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* Toolbar */
.projects-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-md);
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 200px;
    max-width: 400px;
}

.search-box .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-gray-400);
    font-size: 20px;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    padding: 10px 12px 10px 44px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: var(--d-fs-sm);
    background: white;
    transition: all var(--transition-fast);
}

.search-box input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
}

.filter-buttons {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    background: white;
    font-size: var(--d-fs-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.filter-btn:hover {
    background: var(--color-gray-100);
}

.filter-btn.active {
    background: var(--color-black);
    color: white;
    border-color: var(--color-black);
}

.projects-count {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    margin-bottom: var(--spacing-md);
}

.project-card.hidden {
    display: none;
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--spacing-lg);
}
.project-card {
    padding: var(--spacing-lg);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}
.project-status {
    align-self: flex-start;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    text-transform: uppercase;
}
.status-draft { background: var(--color-gray-100); color: var(--color-gray-600); }
.status-in_progress { background: #dbeafe; color: #1e40af; }
.status-review { background: #fef3c7; color: #92400e; }
.status-completed { background: #d1fae5; color: #065f46; }

.project-title {
    font-size: var(--d-fs-lg);
    font-weight: 600;
    margin: var(--spacing-sm) 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}
.customer-badge {
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--color-primary);
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
.project-website {
    font-size: var(--d-fs-sm);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.project-meta {
    display: flex;
    gap: var(--spacing-lg);
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.project-footer {
    display: flex;
    justify-content: space-between;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    margin-top: auto;
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--color-gray-100);
}
.project-actions {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
}
.page-header-actions {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}
.filter-group select {
    padding: 8px 12px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
    background: white;
    min-width: 180px;
}
.filter-group select:focus {
    outline: none;
    border-color: var(--color-primary);
}
</style>

<script>
// Status Filter
let currentStatus = 'all';
let currentSearch = '';

document.querySelectorAll('#status-filter .filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#status-filter .filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentStatus = this.dataset.status;
        filterProjects();
    });
});

function filterProjects() {
    const searchInput = document.getElementById('project-search');
    currentSearch = searchInput ? searchInput.value.toLowerCase().trim() : '';

    const cards = document.querySelectorAll('.project-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const status = card.dataset.status;
        const title = card.dataset.title || '';
        const website = card.dataset.website || '';

        // Status filter
        const matchesStatus = currentStatus === 'all' || status === currentStatus;

        // Search filter
        const matchesSearch = !currentSearch ||
            title.includes(currentSearch) ||
            website.includes(currentSearch);

        if (matchesStatus && matchesSearch) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });

    // Update count
    const countEl = document.getElementById('projects-count');
    if (countEl) {
        countEl.textContent = visibleCount + ' Projekt' + (visibleCount !== 1 ? 'e' : '') + ' gefunden';
    }
}

function filterByCustomer(customerId) {
    const url = new URL(window.location.href);
    if (customerId) {
        url.searchParams.set('customer_id', customerId);
    } else {
        url.searchParams.delete('customer_id');
    }
    window.location.href = url.toString();
}

async function deleteProject(id) {
    if (!confirm('Projekt wirklich löschen? Alle Versionen werden gelöscht.')) return;

    try {
        await App.delete('/projects/' + id);
        document.querySelector(`.project-card[data-id="${id}"]`).remove();
        App.showNotification('Projekt gelöscht', 'success');
        filterProjects(); // Update count after deletion
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Initialize filter
filterProjects();
</script>
