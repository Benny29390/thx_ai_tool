<div class="page-header">
    <h1>Generierungs-Jobs</h1>
    <p class="text-muted">Übersicht aller Artikel-Generierungen</p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value status-pending"><?= $stats['pending'] ?? 0 ?></div>
        <div class="stat-label">Wartend</div>
    </div>
    <div class="stat-card">
        <div class="stat-value status-processing"><?= $stats['processing'] ?? 0 ?></div>
        <div class="stat-label">In Bearbeitung</div>
    </div>
    <div class="stat-card">
        <div class="stat-value status-completed"><?= $stats['completed_today'] ?? 0 ?></div>
        <div class="stat-label">Heute abgeschlossen</div>
    </div>
    <div class="stat-card">
        <div class="stat-value status-failed"><?= $stats['failed_today'] ?? 0 ?></div>
        <div class="stat-label">Heute fehlgeschlagen</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['avg_processing_time_ms'] ? round($stats['avg_processing_time_ms'] / 1000, 1) . 's' : '-' ?></div>
        <div class="stat-label">Avg. Dauer (24h)</div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-lg">
    <div class="filter-row">
        <div class="filter-group">
            <label>Status:</label>
            <select id="filter-status" onchange="filterJobs()">
                <option value="">Alle</option>
                <option value="pending" <?= ($currentStatus ?? '') === 'pending' ? 'selected' : '' ?>>Wartend</option>
                <option value="processing" <?= ($currentStatus ?? '') === 'processing' ? 'selected' : '' ?>>In Bearbeitung</option>
                <option value="completed" <?= ($currentStatus ?? '') === 'completed' ? 'selected' : '' ?>>Abgeschlossen</option>
                <option value="failed" <?= ($currentStatus ?? '') === 'failed' ? 'selected' : '' ?>>Fehlgeschlagen</option>
                <option value="cancelled" <?= ($currentStatus ?? '') === 'cancelled' ? 'selected' : '' ?>>Abgebrochen</option>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn btn-secondary btn-small" onclick="window.location.reload()">
                <span class="material-symbols-rounded">refresh</span> Aktualisieren
            </button>
        </div>
    </div>
</div>

<!-- Jobs Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Projekt / Thema</th>
                    <th>Benutzer</th>
                    <th>Modell</th>
                    <th>Versuche</th>
                    <th>Tokens</th>
                    <th>Dauer</th>
                    <th>Erstellt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted">Keine Jobs gefunden</td>
                </tr>
                <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                <tr class="job-row" data-status="<?= $job['status'] ?>">
                    <td><code>#<?= $job['id'] ?></code></td>
                    <td>
                        <span class="status-badge status-<?= $job['status'] ?>">
                            <?php
                            $statusLabels = [
                                'pending' => 'Wartend',
                                'processing' => 'In Bearbeitung',
                                'completed' => 'Abgeschlossen',
                                'failed' => 'Fehlgeschlagen',
                                'cancelled' => 'Abgebrochen'
                            ];
                            echo $statusLabels[$job['status']] ?? $job['status'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($job['project_id']): ?>
                        <a href="/projects/<?= $job['project_id'] ?>"><?= htmlspecialchars($job['project_title'] ?? 'Projekt #' . $job['project_id']) ?></a>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                        <?php if ($job['topic']): ?>
                        <div class="text-small text-muted"><?= htmlspecialchars(mb_substr($job['topic'], 0, 50)) ?><?= mb_strlen($job['topic']) > 50 ? '...' : '' ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($job['user_name'] ?? 'User #' . $job['user_id']) ?></td>
                    <td><code class="text-small"><?= htmlspecialchars($job['model'] ?? '–') ?></code></td>
                    <td><?= $job['attempts'] ?>/<?= $job['max_attempts'] ?></td>
                    <td>
                        <?php if ($job['tokens_input'] || $job['tokens_output']): ?>
                        <span title="Input: <?= number_format($job['tokens_input']) ?>, Output: <?= number_format($job['tokens_output']) ?>">
                            <?= number_format($job['tokens_input'] + $job['tokens_output']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($job['processing_time_ms']): ?>
                        <?= round($job['processing_time_ms'] / 1000, 1) ?>s
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span title="<?= $job['created_at'] ?>">
                            <?= date('d.m. H:i', strtotime($job['created_at'])) ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon" onclick="showJobDetails(<?= $job['id'] ?>)" title="Details">
                                <span class="material-symbols-rounded">info</span>
                            </button>
                            <?php if ($job['status'] === 'failed'): ?>
                            <button class="btn-icon" onclick="retryJob(<?= $job['id'] ?>)" title="Wiederholen">
                                <span class="material-symbols-rounded">refresh</span>
                            </button>
                            <?php endif; ?>
                            <?php if (in_array($job['status'], ['pending', 'processing'])): ?>
                            <button class="btn-icon btn-danger" onclick="cancelJob(<?= $job['id'] ?>)" title="Abbrechen">
                                <span class="material-symbols-rounded">cancel</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if ($job['status'] === 'failed' && $job['error_message']): ?>
                <tr class="error-row" data-status="<?= $job['status'] ?>">
                    <td></td>
                    <td colspan="9">
                        <div class="error-message">
                            <span class="material-symbols-rounded">error</span>
                            <?= htmlspecialchars($job['error_message']) ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Worker Log -->
<div class="card mt-lg">
    <div class="card-header">
        <h3>Worker-Log</h3>
        <button class="btn btn-secondary btn-small" onclick="refreshLog()">
            <span class="material-symbols-rounded">refresh</span> Aktualisieren
        </button>
    </div>
    <div class="log-container" id="worker-log">
        <pre><?= htmlspecialchars($workerLog ?? 'Keine Log-Einträge') ?></pre>
    </div>
</div>

<!-- Job Details Modal -->
<div class="modal" id="job-details-modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2>Job Details</h2>
            <button class="modal-close" onclick="closeJobDetailsModal()">&times;</button>
        </div>
        <div class="modal-body" id="job-details-content">
            <p class="text-muted">Lade...</p>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}
.stat-card {
    background: white;
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    text-align: center;
    box-shadow: var(--shadow-sm);
}
.stat-value {
    font-size: var(--d-fs-2xl);
    font-weight: 700;
    line-height: 1;
    margin-bottom: var(--spacing-xs);
}
.stat-label {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-pending { color: #f59e0b; }
.status-processing { color: #3b82f6; }
.status-completed { color: #22c55e; }
.status-failed { color: #ef4444; }
.status-cancelled { color: #6b7280; }

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-xs);
    font-weight: 500;
}
.status-badge.status-pending { background: #fef3c7; color: #92400e; }
.status-badge.status-processing { background: #dbeafe; color: #1e40af; }
.status-badge.status-completed { background: #dcfce7; color: #166534; }
.status-badge.status-failed { background: #fee2e2; color: #991b1b; }
.status-badge.status-cancelled { background: #f3f4f6; color: #374151; }

.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
}
.filter-group {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}
.filter-group label {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
}
.filter-group select {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
}

.error-row td {
    padding-top: 0 !important;
    border-top: none !important;
}
.error-message {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-xs);
    background: #fee2e2;
    color: #991b1b;
    padding: var(--spacing-sm);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
}
.error-message .material-symbols-rounded {
    font-size: var(--d-fs-base);
    flex-shrink: 0;
}

.action-buttons {
    display: flex;
    gap: var(--spacing-xs);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: 1px solid var(--color-gray-200);
}
.card-header h3 {
    margin: 0;
    font-size: var(--d-fs-base);
}

.log-container {
    max-height: 400px;
    overflow-y: auto;
    background: #1e1e1e;
    border-radius: 0 0 var(--radius-md) var(--radius-md);
}
.log-container pre {
    margin: 0;
    padding: var(--spacing-md);
    color: #d4d4d4;
    font-family: 'Fira Code', 'Monaco', monospace;
    font-size: var(--d-fs-xs);
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-all;
}

.mb-lg { margin-bottom: var(--spacing-lg); }
.mt-lg { margin-top: var(--spacing-lg); }

.text-small { font-size: var(--d-fs-sm); }

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: var(--radius-lg);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--color-gray-200);
}
.modal-header h2 {
    margin: 0;
    font-size: var(--d-fs-lg);
}
.modal-close {
    background: none;
    border: none;
    font-size: var(--d-fs-2xl);
    cursor: pointer;
    color: var(--color-gray-400);
}
.modal-body {
    padding: var(--spacing-lg);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--spacing-md);
}
.detail-item {
    padding: var(--spacing-sm);
    background: var(--color-gray-50);
    border-radius: var(--radius-sm);
}
.detail-label {
    font-size: var(--d-fs-xs);
    color: var(--color-gray-500);
    margin-bottom: 2px;
}
.detail-value {
    font-weight: 500;
}
.detail-full {
    grid-column: 1 / -1;
}
.detail-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}
.btn-copy {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-gray-400);
    padding: 2px;
    border-radius: 3px;
}
.btn-copy:hover {
    background: var(--color-gray-200);
    color: var(--color-gray-600);
}
.prompt-box {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    font-family: 'Fira Code', 'Monaco', monospace;
    font-size: var(--d-fs-xs);
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 300px;
    overflow-y: auto;
    margin-top: var(--spacing-xs);
}
.prompt-accordion {
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-top: var(--spacing-sm);
}
.prompt-accordion-item {
    border-bottom: 1px solid var(--color-gray-200);
}
.prompt-accordion-item:last-child {
    border-bottom: none;
}
.prompt-accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 0.875rem;
    background: var(--color-gray-50);
    cursor: pointer;
    user-select: none;
    font-size: var(--d-fs-sm);
    font-weight: 600;
    color: var(--color-gray-700);
    transition: background 0.15s;
}
.prompt-accordion-header:hover {
    background: var(--color-gray-100);
}
.prompt-accordion-header .acc-arrow {
    font-size: var(--d-fs-base);
    transition: transform 0.2s;
    color: var(--color-gray-400);
}
.prompt-accordion-item.open .acc-arrow {
    transform: rotate(180deg);
}
.prompt-accordion-header .acc-badge {
    font-size: var(--d-fs-xs);
    font-weight: 500;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: var(--color-gray-200);
    color: var(--color-gray-600);
}
.prompt-accordion-header .acc-badge.outline {
    background: #dbeafe;
    color: #1e40af;
}
.prompt-accordion-header .acc-badge.section {
    background: #dcfce7;
    color: #166534;
}
.prompt-accordion-body {
    display: none;
    padding: 0;
}
.prompt-accordion-item.open .prompt-accordion-body {
    display: block;
}
.prompt-accordion-body .prompt-pair {
    padding: 0.75rem 0.875rem;
    border-bottom: 1px solid var(--color-gray-100);
}
.prompt-accordion-body .prompt-pair:last-child {
    border-bottom: none;
}
.prompt-pair-label {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-gray-500);
    margin-bottom: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.prompt-pair-label .btn-copy {
    font-size: 12px;
}
</style>

<script>
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyToClipboard(btn, type) {
    const promptBox = document.getElementById('prompt-' + type);
    if (!promptBox) return;

    navigator.clipboard.writeText(promptBox.textContent).then(() => {
        const icon = btn.querySelector('.material-symbols-rounded');
        const originalText = icon.textContent;
        icon.textContent = 'check';
        btn.style.color = '#22c55e';
        setTimeout(() => {
            icon.textContent = originalText;
            btn.style.color = '';
        }, 2000);
    });
}

function filterJobs() {
    const status = document.getElementById('filter-status').value;
    const rows = document.querySelectorAll('.job-row, .error-row');

    rows.forEach(row => {
        if (!status || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

async function showJobDetails(jobId) {
    const modal = document.getElementById('job-details-modal');
    const content = document.getElementById('job-details-content');

    modal.classList.add('active');
    content.innerHTML = '<p class="text-muted">Lade...</p>';

    try {
        const response = await App.get('/jobs/' + jobId);
        const job = response.data;

        let html = `
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Job ID</div>
                    <div class="detail-value">#${job.id}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value"><span class="status-badge status-${job.status}">${job.status}</span></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Projekt ID</div>
                    <div class="detail-value"><a href="/projects/${job.project_id}">#${job.project_id}</a></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Modell</div>
                    <div class="detail-value"><code>${job.model}</code></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Versuche</div>
                    <div class="detail-value">${job.attempts} / ${job.max_attempts}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tokens (In/Out)</div>
                    <div class="detail-value">${job.tokens_input?.toLocaleString() || 0} / ${job.tokens_output?.toLocaleString() || 0}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Erstellt</div>
                    <div class="detail-value">${job.created_at || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Gestartet</div>
                    <div class="detail-value">${job.started_at || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Abgeschlossen</div>
                    <div class="detail-value">${job.completed_at || '-'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Verarbeitungszeit</div>
                    <div class="detail-value">${job.processing_time_ms ? (job.processing_time_ms / 1000).toFixed(1) + 's' : '-'}</div>
                </div>
                <div class="detail-item detail-full">
                    <div class="detail-label">Thema</div>
                    <div class="detail-value">${job.topic || '-'}</div>
                </div>
        `;

        if (job.error_message) {
            html += `
                <div class="detail-item detail-full">
                    <div class="detail-label">Fehlermeldung</div>
                    <div class="detail-value" style="color: #991b1b;">${job.error_message}</div>
                </div>
            `;
        }

        if (job.result && job.total_words) {
            html += `
                <div class="detail-item detail-full">
                    <div class="detail-label">Ergebnis</div>
                    <div class="detail-value">${job.result.length} Abschnitte, ${job.total_words} Wörter</div>
                </div>
            `;
        }

        // Modus anzeigen
        if (job.generation_mode) {
            html += `
                <div class="detail-item detail-full">
                    <div class="detail-label">Modus</div>
                    <div class="detail-value">${job.generation_mode === 'reliable' ? 'Zuverlässig (Schritt für Schritt)' : 'Schnell (Ein Prompt)'}</div>
                </div>
            `;
        }

        // Prompt anzeigen wenn vorhanden
        if (job.prompt && job.generation_mode === 'reliable') {
            // Reliable: Outline + einzelne Sections als Accordion
            html += `
                <div class="detail-item detail-full" style="margin-top: var(--spacing-md);">
                    <div class="detail-label">Prompts (${1 + (job.prompt.sections ? job.prompt.sections.length : 0)} Schritte)</div>
                    <div class="prompt-accordion">
            `;

            // Outline Prompt
            if (job.prompt.outline) {
                html += buildAccordionItem('Gliederung erstellen', 'outline', job.prompt.outline, 0);
            }

            // Section Prompts
            if (job.prompt.sections && Array.isArray(job.prompt.sections)) {
                job.prompt.sections.forEach((sp, i) => {
                    const label = job.outline && job.outline[i] ? (typeof job.outline[i] === 'string' ? job.outline[i] : job.outline[i].heading || `Abschnitt ${i+1}`) : `Abschnitt ${i+1}`;
                    html += buildAccordionItem(label, 'section', sp, i + 1);
                });
            }

            html += '</div></div>';

        } else if (job.prompt) {
            // Fast: einfacher System + User Prompt
            html += `
                <div class="detail-item detail-full" style="margin-top: var(--spacing-md);">
                    <div class="detail-label">
                        System-Prompt
                        <button class="btn-copy" onclick="copyToClipboard(this, 'system')" title="Kopieren">
                            <span class="material-symbols-rounded" style="font-size: 14px;">content_copy</span>
                        </button>
                    </div>
                    <div class="prompt-box" id="prompt-system">${escapeHtml(job.prompt.system || '')}</div>
                </div>
                <div class="detail-item detail-full">
                    <div class="detail-label">
                        User-Prompt
                        <button class="btn-copy" onclick="copyToClipboard(this, 'user')" title="Kopieren">
                            <span class="material-symbols-rounded" style="font-size: 14px;">content_copy</span>
                        </button>
                    </div>
                    <div class="prompt-box" id="prompt-user">${escapeHtml(job.prompt.user || '')}</div>
                </div>
            `;
        } else {
            html += `
                <div class="detail-item detail-full" style="margin-top: var(--spacing-md);">
                    <div class="detail-label">Prompt</div>
                    <div class="detail-value text-muted">Nicht verfügbar (Job wurde vor dem Update erstellt)</div>
                </div>
            `;
        }

        html += '</div>';
        content.innerHTML = html;

    } catch (error) {
        content.innerHTML = '<p class="text-muted">Fehler beim Laden: ' + error.message + '</p>';
    }
}

function closeJobDetailsModal() {
    document.getElementById('job-details-modal').classList.remove('active');
}

function buildAccordionItem(title, type, promptData, index) {
    const id = `acc-${type}-${index}`;
    const badgeClass = type === 'outline' ? 'outline' : 'section';
    const badgeText = type === 'outline' ? 'Outline' : `Schritt ${index}`;

    return `
        <div class="prompt-accordion-item" id="${id}">
            <div class="prompt-accordion-header" onclick="toggleAccordion('${id}')">
                <span>
                    <span class="acc-badge ${badgeClass}">${badgeText}</span>
                    ${escapeHtml(title)}
                </span>
                <span class="material-symbols-rounded acc-arrow">expand_more</span>
            </div>
            <div class="prompt-accordion-body">
                <div class="prompt-pair">
                    <div class="prompt-pair-label">
                        System-Prompt
                        <button class="btn-copy" onclick="event.stopPropagation(); copyPromptText(this)" title="Kopieren">
                            <span class="material-symbols-rounded" style="font-size: 14px;">content_copy</span>
                        </button>
                    </div>
                    <div class="prompt-box">${escapeHtml(promptData.system || '')}</div>
                </div>
                <div class="prompt-pair">
                    <div class="prompt-pair-label">
                        User-Prompt
                        <button class="btn-copy" onclick="event.stopPropagation(); copyPromptText(this)" title="Kopieren">
                            <span class="material-symbols-rounded" style="font-size: 14px;">content_copy</span>
                        </button>
                    </div>
                    <div class="prompt-box">${escapeHtml(promptData.user || '')}</div>
                </div>
            </div>
        </div>
    `;
}

function toggleAccordion(id) {
    document.getElementById(id).classList.toggle('open');
}

function copyPromptText(btn) {
    const box = btn.closest('.prompt-pair').querySelector('.prompt-box');
    navigator.clipboard.writeText(box.textContent).then(() => {
        btn.querySelector('.material-symbols-rounded').textContent = 'check';
        setTimeout(() => btn.querySelector('.material-symbols-rounded').textContent = 'content_copy', 1500);
    });
}

async function retryJob(jobId) {
    if (!confirm('Job wiederholen?')) return;

    try {
        await App.post('/jobs/' + jobId, { action: 'retry' });
        App.showNotification('Job wird wiederholt', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (error) {
        App.showNotification('Fehler: ' + error.message, 'error');
    }
}

async function cancelJob(jobId) {
    if (!confirm('Job abbrechen?')) return;

    try {
        await App.post('/jobs/' + jobId, { action: 'cancel' });
        App.showNotification('Job abgebrochen', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (error) {
        App.showNotification('Fehler: ' + error.message, 'error');
    }
}

async function refreshLog() {
    try {
        const response = await App.get('/admin/jobs/log');
        document.querySelector('#worker-log pre').textContent = response.data.log || 'Keine Log-Einträge';
    } catch (error) {
        App.showNotification('Fehler beim Laden des Logs', 'error');
    }
}

// Modal schließen bei Klick außerhalb
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => modal.classList.remove('active'));
    }
});
</script>
