<?php
/** System-Tab — Read-only Versions- und Server-Informationen. */
$free  = @disk_free_space(STORAGE_PATH);
$total = @disk_total_space(STORAGE_PATH);
$usedPct = ($free && $total) ? round((($total - $free) / $total) * 100, 1) : null;

$db = $db ?? \Core\Database::getInstance();
$dbVersion = $db->queryValue("SELECT VERSION()");
?>
<div class="settings-card">
    <h2>System-Information</h2>
    <p class="settings-card-sub">Read-only. Diese Werte stammen aus dem Server / der Datenbank.</p>
    <dl class="settings-status-grid">
        <dt>App-Version</dt>
        <dd><?= defined('APP_VERSION') ? htmlspecialchars(APP_VERSION) : '—' ?></dd>

        <dt>PHP</dt>
        <dd><?= htmlspecialchars(PHP_VERSION) ?></dd>

        <dt>Datenbank</dt>
        <dd><?= htmlspecialchars($dbVersion ?: 'unbekannt') ?></dd>

        <dt>Speicherplatz frei</dt>
        <dd>
            <?php if ($free): ?>
                <?= number_format($free / 1024 / 1024 / 1024, 2, ',', '.') ?> GB
                <?php if ($usedPct !== null): ?>
                    <span class="muted" style="color:var(--slate-500,#64748b);">(<?= $usedPct ?>% belegt)</span>
                <?php endif; ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>

        <dt>Storage-Pfad</dt>
        <dd><code><?= htmlspecialchars(defined('STORAGE_PATH') ? STORAGE_PATH : '—') ?></code></dd>

        <dt>App-Pfad</dt>
        <dd><code><?= htmlspecialchars(defined('APP_PATH') ? APP_PATH : '—') ?></code></dd>

        <dt>Server</dt>
        <dd><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></dd>
    </dl>
</div>

<div class="settings-card">
    <h2>Wartung</h2>
    <p class="settings-card-sub">Nützliche Links zu Admin-Bereichen.</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <a href="/admin/backups" class="thx-btn thx-btn-secondary">Backups</a>
        <a href="/admin/system-log" class="thx-btn thx-btn-secondary">System-Log</a>
        <a href="/admin/jobs" class="thx-btn thx-btn-secondary">Jobs</a>
        <a href="/admin/usage-stats" class="thx-btn thx-btn-secondary">Nutzungsstatistiken</a>
    </div>
</div>
