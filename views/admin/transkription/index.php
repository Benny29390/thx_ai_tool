<?php
/**
 * Transkription — Hauptview (Tab-Hub).
 * Audio/Video transkribieren, Sprecher erkennen, Vorlagen erzeugen, ins Wissen einspeisen.
 */
$tabs = [
    ['slug' => 'upload',      'label' => 'Upload'],
    ['slug' => 'jobs',        'label' => 'Jobs'],
    ['slug' => 'editor',      'label' => 'Editor'],
    ['slug' => 'bibliothek',  'label' => 'Bibliothek'],
    ['slug' => 'wissen',      'label' => 'Wissen'],
    ['slug' => 'korrekturen', 'label' => 'Korrekturen'],
    ['slug' => 'auto_import', 'label' => 'Auto-Import'],
];
if (\Core\Auth::isAdmin()) {
    $tabs[] = ['slug' => 'admin', 'label' => 'Admin-Prompts'];
}
$allowed = array_column($tabs, 'slug');
$active  = $_GET['tab'] ?? 'upload';
if (!in_array($active, $allowed, true)) $active = 'upload';
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Transkription</h1>
        <p class="thx-page-subtitle">Audio &amp; Video transkribieren, Sprecher erkennen, Protokolle ableiten und ins Wissen einspeisen.</p>
    </div>
</div>

<?php $wissenAktiv = 'transkripte'; include VIEWS_PATH . '/wissen/_wissen_tabs.php'; ?>

<nav class="thx-tabs" aria-label="Transkription-Bereiche">
    <?php foreach ($tabs as $t): ?>
        <a href="/admin/transkription?tab=<?= $t['slug'] ?>"
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
