<?php
$userName = htmlspecialchars($currentUser['name'] ?? 'Nutzer');
$firstName = explode(' ', $userName)[0];
$hour = (int) date('H');

// Tageszeit-Gruss
if ($hour < 12) {
    $timeGreeting = 'Guten Morgen';
} elseif ($hour < 18) {
    $timeGreeting = 'Guten Tag';
} else {
    $timeGreeting = 'Guten Abend';
}

// Wochentag
$weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$weekday = $weekdays[(int) date('w')];
$dateFormatted = date('d.m.Y');

// 2FA-Status prüfen
$has2FA = !empty($currentUser['two_factor_enabled']) && !empty($currentUser['two_factor_confirmed_at']);
?>

<!-- Hero — Cyber-Look mit animiertem Netz -->
<div class="hero-card hero-cyber">
    <canvas id="hero-canvas" class="hero-canvas"></canvas>
    <div class="hero-grid"></div>
    <div class="hero-vignette"></div>
    <div class="hero-content">
        <!-- Uhr oben rechts -->
        <div class="hero-clock">
            <div class="clock-display">
                <span class="clock-hours" id="clock-hours"><?= date('H') ?></span>
                <span class="clock-separator">:</span>
                <span class="clock-minutes" id="clock-minutes"><?= date('i') ?></span>
                <span class="clock-seconds" id="clock-seconds"><?= date('s') ?></span>
            </div>
            <div class="clock-date"><?= $weekday ?>, <?= $dateFormatted ?></div>
        </div>

        <!-- Begrüßung -->
        <div class="hero-greeting">
            <span class="hero-eyebrow">THOXAN · AI ASSISTANT</span>
            <h1>
                <span class="hero-greeting-word"><?= $timeGreeting ?>,</span>
                <span class="hero-name"><?= $firstName ?></span><span class="hero-cursor">_</span>
            </h1>
        </div>

        <!-- Motivation -->
        <div class="hero-motivation">
            <p id="motivation-text">Lade Tagesmotivation…</p>
            <button class="motivation-refresh" onclick="loadMotivation(true)" title="Neue Motivation">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<?php
// Welcome-State fuer User ohne Caps oder ohne Kunden
$isAdmin = \Core\Auth::isAdmin();
$myCaps  = \Core\Auth::capabilities();
$myCustomers = \Core\Auth::customers();
$hasNoCaps = !$isAdmin && empty($myCaps);
$hasNoCustomers = !$isAdmin && empty($myCustomers);
if ($hasNoCaps || $hasNoCustomers):
    // Admin-E-Mail fuer den Kontakt-Button
    $adminEmail = \Core\Database::getInstance()->queryValue(
        "SELECT email FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1"
    );
?>
<div class="welcome-empty-state">
    <div class="welcome-icon">
        <span class="material-symbols-rounded">waving_hand</span>
    </div>
    <h2>Willkommen, <?= htmlspecialchars($firstName) ?>!</h2>
    <p class="welcome-lead">
        <?php if ($hasNoCaps && $hasNoCustomers): ?>
            Dein Account ist angelegt, aber Dir sind noch keine Funktionen und keine Kunden zugewiesen.
        <?php elseif ($hasNoCaps): ?>
            Dein Account ist angelegt, aber Dir sind noch keine Funktionen zugewiesen.
        <?php else: ?>
            Dein Account ist angelegt, aber Dir sind noch keine Kunden zugewiesen — ohne Kunden gibt es nichts zu sehen.
        <?php endif; ?>
    </p>
    <p class="welcome-hint">
        Frag bitte den Admin nach den passenden Freischaltungen.
    </p>
    <?php if (!empty($adminEmail)): ?>
        <a href="mailto:<?= htmlspecialchars($adminEmail) ?>?subject=<?= rawurlencode('Account-Freischaltung benötigt') ?>&body=<?= rawurlencode('Hi,\n\nmein Account ist angelegt, aber ich kann noch nichts machen. Bitte schalte mir die nötigen Funktionen frei.\n\nDanke!\n' . $firstName) ?>"
           class="thx-btn thx-btn-primary" style="margin-top:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">mail</span>
            Admin per E-Mail anschreiben
        </a>
    <?php endif; ?>
</div>
<style>
.welcome-empty-state {
    margin: 16px 0 32px 0;
    padding: 32px 28px;
    background: linear-gradient(135deg, var(--thoxan-50, #eff6ff), #fff);
    border: 1px solid var(--thoxan-200, #bfdbfe);
    border-radius: 14px;
    text-align: center;
    max-width: 640px;
    margin-left: auto;
    margin-right: auto;
}
.welcome-empty-state .welcome-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px; height: 64px;
    border-radius: 50%;
    background: var(--thoxan-100, #dbeafe);
    color: var(--thoxan-700, #1d4ed8);
    margin-bottom: 14px;
}
.welcome-empty-state .welcome-icon .material-symbols-rounded { font-size: 32px; }
.welcome-empty-state h2 { margin: 0 0 8px 0; color: var(--slate-900, #0f172a); }
.welcome-empty-state .welcome-lead { font-size: var(--d-fs-base); color: var(--slate-700, #334155); margin: 0 0 4px 0; }
.welcome-empty-state .welcome-hint { font-size: var(--d-fs-sm); color: var(--slate-500, #64748b); margin: 0; }
</style>
<?php endif; ?>

<?php if (\Core\Auth::can(CAP_TRANSCRIPTION)): ?>
<!-- Transkriptions-Inbox -->
<div id="tr-inbox-host" style="display:none;margin-bottom:var(--d-section-gap);"></div>
<style>
.tr-inbox-card { background:#fff;border:1px solid var(--amber-200);border-left:4px solid var(--amber-400);border-radius:var(--d-card-radius);padding:var(--d-card-pad); }
.tr-inbox-card h3 { margin:0 0 8px;font-size:var(--d-fs-base);display:flex;align-items:center;gap:8px;color:var(--amber-800); }
.tr-inbox-list { display:flex;flex-direction:column;gap:6px;margin:8px 0 0; }
.tr-inbox-item { display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 8px;background:var(--slate-50);border-radius:6px;font-size:var(--d-fs-sm); }
.tr-inbox-item .tr-inbox-kind { font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:.03em;font-weight:600; }
.tr-inbox-item .tr-inbox-meta { font-size:var(--d-fs-xs);color:var(--slate-500); }
.tr-inbox-actions { display:flex;gap:6px; }
</style>
<script>
(async function() {
    try {
        const r = await fetch('/api/v1/admin/transkription/inbox');
        const j = await r.json();
        if (!j.success || !j.data.items.length) return;
        const host = document.getElementById('tr-inbox-host');
        host.style.display = '';
        host.innerHTML = `
            <div class="tr-inbox-card">
                <h3><span class="material-symbols-rounded" style="font-size:20px;">inbox</span> Transkription — zu klaeren (${j.data.items.length})</h3>
                <div class="tr-inbox-list">
                    ${j.data.items.map(it => {
                        const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
                        const kindLabel = { speakers:'Sprecher benennen', failed:'Job fehlgeschlagen', auto_partial:'Auto-Pipeline unvollst.' }[it.kind] || it.kind;
                        let actions = `<a class="thx-btn thx-btn-small thx-btn-secondary" href="/admin/transkription?tab=jobs">Jobs ansehen</a>`;
                        if (it.kind === 'speakers') {
                            actions = `<a class="thx-btn thx-btn-small thx-btn-primary" href="/admin/transkription?tab=editor&job=${it.job_id}">Im Editor benennen</a>`;
                        } else if (it.kind === 'auto_partial') {
                            actions = `<a class="thx-btn thx-btn-small thx-btn-secondary" href="/admin/transkription?tab=vorlagen">Vorlagen oeffnen</a>`;
                        }
                        return `
                            <div class="tr-inbox-item">
                                <div>
                                    <div><span class="tr-inbox-kind">${esc(kindLabel)}</span> · <strong>${esc(it.filename || '–')}</strong></div>
                                    ${it.detail ? `<div class="tr-inbox-meta">${esc(it.detail)}</div>` : ''}
                                </div>
                                <div class="tr-inbox-actions">${actions}</div>
                            </div>`;
                    }).join('')}
                </div>
            </div>`;
    } catch (e) {}
})();
</script>
<?php endif; ?>

<!-- Hauptpunkte -->
<div class="dashboard-tiles">
    <a href="/chat" class="tile tile-primary tile-large">
        <div class="tile-icon"><span class="material-symbols-rounded">forum</span></div>
        <div class="tile-content">
            <h3>Chat starten</h3>
            <p>Direkt mit der KI sprechen</p>
        </div>
        <span class="tile-arrow material-symbols-rounded">arrow_forward</span>
    </a>

    <?php if (\Core\Auth::isAdmin()): ?>
    <a href="/admin/customers" class="tile" style="--accent:#004c9b;">
        <div class="tile-icon"><span class="material-symbols-rounded">business</span></div>
        <div class="tile-content">
            <h3>Kunden</h3>
            <p><?= number_format($stats['customers'] ?? 0) ?> Kunden aktiv</p>
        </div>
    </a>
    <?php endif; ?>

    <a href="/wissen" class="tile" style="--accent:#10b981;">
        <div class="tile-icon"><span class="material-symbols-rounded">library_books</span></div>
        <div class="tile-content">
            <h3>Wissen</h3>
            <p><?= number_format($stats['knowledge_docs'] ?? 0) ?> Einträge</p>
        </div>
    </a>

    <?php if (\Core\Auth::hasRole(ROLE_MANAGER)): ?>
    <a href="/guidelines" class="tile" style="--accent:#a78bfa;">
        <div class="tile-icon"><span class="material-symbols-rounded">tips_and_updates</span></div>
        <div class="tile-content">
            <h3>Guidelines</h3>
            <p><?= number_format($stats['guidelines_active'] ?? 0) ?> aktiv</p>
        </div>
    </a>
    <?php endif; ?>

    <a href="/canvas" class="tile" style="--accent:#0ea5e9;">
        <div class="tile-icon"><span class="material-symbols-rounded">explore</span></div>
        <div class="tile-content">
            <h3>KI Kompass</h3>
            <p>Briefings &amp; Sparring</p>
        </div>
    </a>

    <?php if (\Core\Auth::isAdmin()): ?>
    <a href="/admin/users" class="tile" style="--accent:#64748b;">
        <div class="tile-icon"><span class="material-symbols-rounded">group</span></div>
        <div class="tile-content">
            <h3>Benutzer</h3>
            <p>Team verwalten</p>
        </div>
    </a>

    <a href="/admin/usage" class="tile" style="--accent:#f59e0b;">
        <div class="tile-icon"><span class="material-symbols-rounded">monitoring</span></div>
        <div class="tile-content">
            <h3>Verbrauch</h3>
            <p><?= number_format($stats['api_calls_today'] ?? 0) ?> Calls heute</p>
        </div>
    </a>

    <a href="/admin/settings" class="tile" style="--accent:#475569;">
        <div class="tile-icon"><span class="material-symbols-rounded">settings</span></div>
        <div class="tile-content">
            <h3>Einstellungen</h3>
            <p>System konfigurieren</p>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- Stats Section -->
<div class="stats-section">
    <h2 class="stats-title">Status</h2>
    <div class="stats-grid">
        <div class="stat-card" style="--delay: 0">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">library_books</span>
                </div>
                <div class="stat-value" data-value="<?= $stats['knowledge_docs'] ?? 0 ?>">0</div>
                <div class="stat-label">Wissens-Eintraege</div>
            </div>
        </div>

        <div class="stat-card" style="--delay: 1">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">scatter_plot</span>
                </div>
                <div class="stat-value" data-value="<?= $stats['knowledge_chunks'] ?? 0 ?>">0</div>
                <div class="stat-label">Indexierte Chunks</div>
            </div>
        </div>

        <div class="stat-card" style="--delay: 2">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">task_alt</span>
                </div>
                <div class="stat-value" data-value="<?= $stats['asana_tasks'] ?? 0 ?>">0</div>
                <div class="stat-label">Asana-Tasks</div>
            </div>
        </div>

        <div class="stat-card" style="--delay: 3">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">forum</span>
                </div>
                <div class="stat-value" data-value="<?= $stats['my_chats'] ?? 0 ?>">0</div>
                <div class="stat-label">Deine Chats</div>
            </div>
        </div>
    </div>
</div>

<style>
/* 2FA Reminder Banner */
.tfa-reminder {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-lg);
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: var(--radius-lg);
    padding: var(--spacing-md) var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}
.tfa-reminder-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}
.tfa-reminder-content .material-symbols-rounded {
    font-size: 28px;
    color: #b45309;
}
.tfa-reminder-content strong {
    display: block;
    color: #92400e;
}
.tfa-reminder-content p {
    margin: 0;
    font-size: var(--d-fs-sm);
    color: #a16207;
}
@media (max-width: 600px) {
    .tfa-reminder {
        flex-direction: column;
        align-items: flex-start;
    }
    .tfa-reminder .btn {
        width: 100%;
        text-align: center;
    }
}

/* Hero — Cyber-Look mit animiertem Netz */
.hero-card.hero-cyber {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    margin-bottom: var(--spacing-xl);
    min-height: 460px;
    background: radial-gradient(ellipse at 30% 20%, #001833 0%, #000c1f 60%, #000510 100%);
    box-shadow:
        0 0 0 1px rgba(0, 76, 155, 0.08) inset,
        0 30px 80px rgba(0, 0, 0, 0.45),
        0 0 60px rgba(0, 76, 155, 0.25);
}

.hero-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

/* Pulsierendes Grid */
.hero-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(0, 76, 155, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 76, 155, 0.07) 1px, transparent 1px);
    background-size: 48px 48px;
    background-position: -1px -1px;
    mask-image: radial-gradient(ellipse at 50% 40%, #000 0%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000 0%, transparent 75%);
    z-index: 2;
    animation: hero-grid-pulse 8s ease-in-out infinite;
}
@keyframes hero-grid-pulse {
    0%, 100% { opacity: 0.6; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.02); }
}

/* Vignette + Akzent-Glow */
.hero-vignette {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 85% 80%, rgba(0, 76, 155, 0.4) 0%, transparent 45%),
        radial-gradient(circle at 15% 15%, rgba(0, 76, 155, 0.15) 0%, transparent 50%),
        linear-gradient(180deg, transparent 60%, rgba(0, 0, 0, 0.5) 100%);
    z-index: 3;
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 4;
    padding: 56px 64px;
    display: flex;
    flex-direction: column;
    min-height: 460px;
}

/* Uhr */
.hero-clock {
    position: absolute;
    top: 48px;
    right: 64px;
    text-align: right;
}

.clock-display {
    font-family: 'SF Mono', 'Cascadia Code', 'Menlo', monospace;
    font-size: var(--d-fs-2xl);
    font-weight: 200;
    color: #e0f2fe;
    line-height: 1;
    letter-spacing: 0.02em;
    text-shadow: 0 0 20px rgba(0, 76, 155, 0.5), 0 0 40px rgba(0, 76, 155, 0.3);
}

.clock-hours, .clock-minutes {
    display: inline-block;
    min-width: 1.2em;
}

.clock-separator {
    opacity: 0.7;
    animation: blink 2s ease-in-out infinite;
}

.clock-seconds {
    font-size: var(--d-fs-lg);
    opacity: 0.5;
    margin-left: 6px;
    vertical-align: super;
    font-weight: 400;
}

@keyframes blink {
    0%, 100% { opacity: 0.7; }
    50% { opacity: 0.3; }
}

.clock-date {
    font-size: var(--d-fs-sm);
    color: rgba(60, 130, 200, 0.7);
    margin-top: 10px;
    font-weight: 500;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-family: 'SF Mono', monospace;
}

/* Begrüßung — größer, terminal-style */
.hero-greeting {
    margin-top: auto;
    margin-bottom: 28px;
}

.hero-eyebrow {
    display: inline-block;
    font-family: 'SF Mono', monospace;
    font-size: var(--d-fs-xs);
    color: #1565b8;
    letter-spacing: 0.3em;
    margin-bottom: 16px;
    padding: 4px 10px;
    border: 1px solid rgba(0, 76, 155, 0.3);
    border-radius: 6px;
    background: rgba(0, 76, 155, 0.06);
    text-transform: uppercase;
    animation: hero-eyebrow-pulse 3s ease-in-out infinite;
}
@keyframes hero-eyebrow-pulse {
    0%, 100% { box-shadow: 0 0 0 rgba(0, 76, 155, 0); }
    50% { box-shadow: 0 0 18px rgba(0, 76, 155, 0.4); }
}

.hero-greeting h1 {
    font-size: clamp(2.6rem, 5vw, 4rem);
    font-weight: 800;
    color: #fff;
    margin: 0;
    letter-spacing: -0.03em;
    line-height: 1.05;
    display: flex;
    align-items: baseline;
    gap: 0.4em;
    flex-wrap: wrap;
}
.hero-greeting-word {
    color: rgba(226, 232, 240, 0.85);
    font-weight: 300;
}
.hero-name {
    background: linear-gradient(135deg, #3a8ed9 0%, #1565b8 50%, #004c9b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 60px rgba(0, 76, 155, 0.6);
    font-weight: 800;
}
.hero-cursor {
    display: inline-block;
    color: #1565b8;
    font-weight: 400;
    animation: cursor-blink 1s step-end infinite;
    margin-left: 4px;
}
@keyframes cursor-blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Motivation — Glas-Pille */
.hero-motivation {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(0, 76, 155, 0.2);
    border-radius: 16px;
    padding: 20px 28px;
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(0, 76, 155, 0.05) inset,
        0 0 30px rgba(0, 76, 155, 0.15);
    max-width: 720px;
}
.hero-motivation::before {
    content: '"';
    font-size: var(--d-fs-2xl);
    line-height: 0.8;
    color: rgba(0, 76, 155, 0.4);
    font-family: serif;
    margin-top: -8px;
}
.hero-motivation p {
    flex: 1;
    font-size: var(--d-fs-lg);
    font-weight: 400;
    color: #e0f2fe;
    margin: 0;
    line-height: 1.6;
    font-style: italic;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
}

.motivation-refresh {
    flex-shrink: 0;
    background: rgba(0, 76, 155, 0.1);
    border: 1px solid rgba(0, 76, 155, 0.3);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #1565b8;
    transition: all 0.3s ease;
}

.motivation-refresh:hover {
    background: rgba(0, 76, 155, 0.2);
    transform: rotate(180deg);
    box-shadow: 0 0 20px rgba(0, 76, 155, 0.4);
}

/* Dashboard Tiles */
.dashboard-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.tile {
    --accent: #004c9b;
    position: relative;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: 18px;
    padding: 28px 24px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 130px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    cursor: pointer;
    overflow: hidden;
    isolation: isolate;
}

/* Akzent-Glow im Hintergrund — wandert beim Hover */
.tile::before {
    content: '';
    position: absolute;
    inset: -40%;
    background: radial-gradient(circle at var(--mx, 30%) var(--my, 20%),
        color-mix(in srgb, var(--accent) 18%, transparent) 0%,
        transparent 55%);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
    pointer-events: none;
}
.tile:hover::before { opacity: 1; }

/* Akzent-Linie unten — wächst beim Hover */
.tile::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 50%, white));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s cubic-bezier(.5,.05,.25,1);
}
.tile:hover::after { transform: scaleX(1); }

.tile:hover {
    border-color: color-mix(in srgb, var(--accent) 40%, var(--color-gray-200));
    transform: translateY(-3px);
    box-shadow: 0 12px 28px color-mix(in srgb, var(--accent) 18%, transparent);
}

.tile-icon {
    font-size: var(--d-fs-2xl);
    line-height: 1;
    color: var(--accent);
    transition: transform 0.25s ease;
}
.tile:hover .tile-icon {
    transform: scale(1.08) rotate(-3deg);
}

.tile-arrow {
    position: absolute;
    top: 50%;
    right: var(--spacing-lg);
    transform: translateY(-50%) translateX(-8px);
    opacity: 0;
    color: var(--accent);
    font-size: 22px !important;
    transition: all 0.25s ease;
}
.tile:hover .tile-arrow {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}

/* Primary-Tile: prominent in Akzent-Blau */
.tile-primary {
    --accent: #fff;
    background: linear-gradient(135deg, #003a78 0%, #004c9b 60%, #1565b8 100%);
    color: white;
    border: none;
    box-shadow: 0 8px 24px rgba(0, 76, 155, 0.25);
}
.tile-primary:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px rgba(0, 76, 155, 0.35);
    border: none;
}
.tile-primary::after {
    background: linear-gradient(90deg, rgba(255,255,255,0.6), rgba(255,255,255,0));
}
.tile-primary .tile-icon { color: white; }
.tile-primary .tile-arrow { color: white; }
.tile-primary .tile-content p { color: rgba(255, 255, 255, 0.85); }

.tile-large {
    grid-column: span 2;
    flex-direction: row;
    align-items: center;
}
.tile-large .tile-icon {
    font-size: var(--d-fs-2xl);
}

.tile-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}
.tile-content h3 {
    margin: 0;
    font-size: var(--d-fs-lg);
    font-weight: 700;
    letter-spacing: -0.01em;
}
.tile-content p {
    margin: 0;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.tile-large .tile-content h3 { font-size: var(--d-fs-xl); }
.tile-large .tile-content p { font-size: var(--d-fs-base); }

/* Stats Section */
.stats-section {
    margin-top: var(--spacing-xl);
}

.stats-title {
    margin: 0 0 var(--spacing-lg) 0;
    font-size: var(--d-fs-xl);
    font-weight: 600;
    color: var(--color-gray-700);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-lg);
}

.stat-card {
    position: relative;
    border-radius: var(--radius-lg);
    overflow: hidden;
    min-height: 160px;
    background: white;
    border: 1px solid var(--color-gray-200);
    transition: all 0.3s ease;
    justify-content: center;
}

.stat-card:hover {
    border-color: var(--color-gray-300);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.stat-card-bg {
    display: none;
}

.stat-card-content {
    position: relative;
    z-index: 1;
    padding: var(--spacing-xl);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 160px;
    text-align: center;
    box-sizing: border-box;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--color-gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--spacing-md) auto;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    background: var(--color-black);
}

.stat-card:hover .stat-icon .material-symbols-rounded {
    color: white;
}

.stat-icon .material-symbols-rounded {
    font-size: 24px;
    color: var(--color-gray-600);
    transition: color 0.3s ease;
}

.stat-value {
    font-size: var(--d-fs-2xl);
    font-weight: 800;
    color: var(--color-black);
    line-height: 1.1;
    margin-bottom: var(--spacing-xs);
    font-variant-numeric: tabular-nums;
}

.stat-label {
    font-size: var(--d-fs-sm);
    font-weight: 500;
    color: var(--color-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-card {
        min-height: 300px;
        border-radius: 16px;
    }

    .hero-content {
        min-height: 300px;
        padding: 28px 24px;
    }

    .hero-clock {
        top: 24px;
        right: 24px;
    }

    .clock-display {
        font-size: var(--d-fs-2xl);
    }

    .clock-seconds {
        font-size: var(--d-fs-sm);
    }

    .clock-date {
        font-size: var(--d-fs-sm);
    }

    .hero-greeting h1 {
        font-size: var(--d-fs-2xl);
    }

    .hero-motivation p {
        font-size: var(--d-fs-base);
    }

    .hero-motivation {
        padding: var(--spacing-md) var(--spacing-lg);
    }

    .tile-large {
        grid-column: span 1;
        flex-direction: column;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--spacing-md);
    }

    .stat-card {
        min-height: 140px;
    }

    .stat-card-content {
        min-height: 140px;
        padding: var(--spacing-lg);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
    }

    .stat-icon .material-symbols-rounded {
        font-size: 20px;
    }

    .stat-value {
        font-size: var(--d-fs-2xl);
    }
}

@media (max-width: 480px) {
    .hero-clock {
        position: static;
        text-align: left;
        margin-bottom: var(--spacing-lg);
    }

    .hero-greeting {
        margin-top: 0;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .stat-card {
        min-height: 120px;
    }

    .stat-card-content {
        min-height: 120px;
        padding: var(--spacing-md);
    }

    .stat-value {
        font-size: var(--d-fs-2xl);
    }

    .stat-label {
        font-size: var(--d-fs-xs);
    }
}
</style>

<script>
// Tile-Glow folgt der Maus
document.addEventListener('mousemove', (e) => {
    document.querySelectorAll('.tile').forEach(tile => {
        const rect = tile.getBoundingClientRect();
        if (e.clientX < rect.left - 100 || e.clientX > rect.right + 100 ||
            e.clientY < rect.top - 100 || e.clientY > rect.bottom + 100) return;
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        tile.style.setProperty('--mx', x + '%');
        tile.style.setProperty('--my', y + '%');
    });
});

// Cyber-Netz im Hero — bewegte Punkte mit Verbindungslinien
(function initHeroNetwork() {
    const canvas = document.getElementById('hero-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let mouse = { x: -9999, y: -9999 };
    let nodes = [];
    let dpr = Math.min(window.devicePixelRatio || 1, 2);

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.scale(dpr, dpr);
        seed();
    }

    function seed() {
        const { width, height } = canvas.getBoundingClientRect();
        const target = Math.round((width * height) / 11000);
        nodes = [];
        for (let i = 0; i < target; i++) {
            nodes.push({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.35,
                vy: (Math.random() - 0.5) * 0.35,
                r: 1 + Math.random() * 1.5,
                pulse: Math.random() * Math.PI * 2,
            });
        }
    }

    canvas.parentElement.addEventListener('mousemove', (e) => {
        const rect = canvas.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
    });
    canvas.parentElement.addEventListener('mouseleave', () => {
        mouse.x = -9999; mouse.y = -9999;
    });

    function step() {
        const { width, height } = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, width, height);

        // Knoten bewegen
        nodes.forEach(n => {
            n.x += n.vx;
            n.y += n.vy;
            if (n.x < 0 || n.x > width) n.vx *= -1;
            if (n.y < 0 || n.y > height) n.vy *= -1;
            // Maus-Repulsion
            const dx = n.x - mouse.x;
            const dy = n.y - mouse.y;
            const d2 = dx*dx + dy*dy;
            if (d2 < 14000) {
                const f = (1 - d2 / 14000) * 0.6;
                n.x += (dx / Math.sqrt(d2 + 1)) * f;
                n.y += (dy / Math.sqrt(d2 + 1)) * f;
            }
            n.pulse += 0.04;
        });

        // Linien zwischen nahen Knoten
        for (let i = 0; i < nodes.length; i++) {
            for (let j = i + 1; j < nodes.length; j++) {
                const a = nodes[i], b = nodes[j];
                const dx = a.x - b.x, dy = a.y - b.y;
                const dist = Math.sqrt(dx*dx + dy*dy);
                if (dist < 140) {
                    const op = (1 - dist / 140) * 0.45;
                    ctx.strokeStyle = `rgba(0, 76, 155, ${op})`;
                    ctx.lineWidth = 0.6;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                }
            }
        }

        // Knoten zeichnen mit Glow + Pulse
        nodes.forEach(n => {
            const pulse = 0.5 + 0.5 * Math.sin(n.pulse);
            const r = n.r * (0.85 + pulse * 0.5);

            // Outer glow
            const grad = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, r * 5);
            grad.addColorStop(0, `rgba(60, 130, 200, ${0.45 + pulse * 0.3})`);
            grad.addColorStop(1, 'rgba(60, 130, 200, 0)');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(n.x, n.y, r * 5, 0, Math.PI * 2);
            ctx.fill();

            // Core
            ctx.fillStyle = '#3a8ed9';
            ctx.beginPath();
            ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
            ctx.fill();
        });

        requestAnimationFrame(step);
    }

    resize();
    window.addEventListener('resize', resize);
    step();
})();

// Live Clock
function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    // Update sky if hour changed
    const heroCard = document.querySelector('.hero-card');
    if (heroCard.dataset.hour !== String(now.getHours())) {
        heroCard.dataset.hour = now.getHours();
    }

    document.getElementById('clock-hours').textContent = hours;
    document.getElementById('clock-minutes').textContent = minutes;
    document.getElementById('clock-seconds').textContent = seconds;
}

setInterval(updateClock, 1000);
updateClock();

// Motivation
const motivationKey = 'daily_motivation_' + new Date().toISOString().split('T')[0];

async function loadMotivation(forceNew = false) {
    const el = document.getElementById('motivation-text');
    const cached = !forceNew && localStorage.getItem(motivationKey);

    if (cached) {
        el.textContent = cached;
        return;
    }

    el.textContent = 'Lade Tagesmotivation...';

    try {
        const endpoint = forceNew ? '/motivation?force=1' : '/motivation';
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000);

        const res = await fetch(App.apiUrl + endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': App.csrfToken },
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        const json = await res.json();
        const motivation = json.data?.motivation || 'Heute ist ein guter Tag, um grossartige Texte zu schreiben!';

        el.textContent = motivation;
        localStorage.setItem(motivationKey, motivation);

        // Alte Einträge aufräumen
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith('daily_motivation_') && key !== motivationKey) {
                localStorage.removeItem(key);
            }
        });
    } catch (error) {
        el.textContent = 'Jeder Text beginnt mit einem ersten Wort. Leg los!';
    }
}

// Load motivation after App is available (app.js loads after inline scripts)
if (typeof App !== 'undefined' && App.apiUrl) {
    loadMotivation();
} else {
    window.addEventListener('DOMContentLoaded', () => loadMotivation());
}

// Animated Counter
function animateCounters() {
    const counters = document.querySelectorAll('.stat-value[data-value]');

    counters.forEach(counter => {
        const target = parseInt(counter.dataset.value) || 0;
        const duration = 2000;
        const startTime = performance.now();

        function easeOutExpo(t) {
            return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
        }

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easedProgress = easeOutExpo(progress);
            const current = Math.round(target * easedProgress);

            counter.textContent = current.toLocaleString('de-DE');

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        // Start animation when card becomes visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    requestAnimationFrame(update);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        observer.observe(counter.closest('.stat-card'));
    });
}

// Start counter animation
animateCounters();
</script>

