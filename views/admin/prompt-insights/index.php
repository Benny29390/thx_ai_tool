<?php
/**
 * Prompt-Insights — Hauptview (Tab-Hub).
 *
 * Tab-State per ?tab=.  Defaults auf 'imports'.
 */
$tabs = [
    ['slug' => 'imports',    'label' => 'Importe'],
    ['slug' => 'browser',    'label' => 'Prompt-Browser'],
    ['slug' => 'dashboard',  'label' => 'Statistik'],
    ['slug' => 'clusters',   'label' => 'Cluster'],
    ['slug' => 'rules',      'label' => 'Regel-Editor'],
    ['slug' => 'library',    'label' => 'Spielregeln'],
    ['slug' => 'whitelist',  'label' => 'Anonymisierung'],
];
$allowed = array_column($tabs, 'slug');
$active  = $_GET['tab'] ?? 'imports';
if (!in_array($active, $allowed, true)) $active = 'imports';
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Prompt-Insights</h1>
        <p class="thx-page-subtitle">Eigene Chatverläufe aus Claude.ai &amp; ChatGPT importieren, Muster erkennen, Spielregeln ableiten.</p>
    </div>
</div>

<nav class="thx-tabs" aria-label="Prompt-Insights-Bereiche">
    <?php foreach ($tabs as $t): ?>
        <a href="/admin/prompt-insights?tab=<?= $t['slug'] ?>"
           class="thx-tab<?= $active === $t['slug'] ? ' is-active' : '' ?>">
            <?= htmlspecialchars($t['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div style="margin-top: var(--d-section-gap);">
    <?php
    $partial = __DIR__ . '/_tab_' . $active . '.php';
    if (file_exists($partial)) {
        include $partial;
    } else {
        echo '<div class="thx-card"><p style="color:var(--slate-500);">Tab „' . htmlspecialchars($active) . '" wird im nächsten Block gebaut.</p></div>';
    }
    ?>
</div>
