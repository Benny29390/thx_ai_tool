<?php
/** CRM-Modul Tab-Leiste — entspricht $activeModul in den View-Dateien. */
$tabs = [
    ['slug' => 'kontakte',  'name' => 'Kontakte',    'href' => '/crm/kontakte'],
    ['slug' => 'firmen',    'name' => 'Firmen',      'href' => '/crm/firmen'],
    ['slug' => 'segmente',  'name' => 'Segmente',    'href' => '/crm/segmente'],
    ['slug' => 'listen',    'name' => 'Listen',      'href' => '/crm/listen'],
    ['slug' => 'tags',      'name' => 'Tags',        'href' => '/crm/tags'],
    ['slug' => 'pflege',    'name' => 'Pflege',      'href' => '/crm/pflege'],
];
if (\Core\Auth::can(CAP_CRM_MIGRATION)) {
    $tabs[] = ['slug' => 'migration', 'name' => 'Migration', 'href' => '/crm/migration'];
}
if (\Core\Auth::can(CAP_CRM_DSGVO)) {
    $tabs[] = ['slug' => 'dsgvo', 'name' => 'DSGVO', 'href' => '/crm/dsgvo'];
}
?>
<nav class="thx-tabs" style="margin-top:8px;margin-bottom:18px;">
    <?php foreach ($tabs as $tab): ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>"
           class="thx-tab<?= ($activeModul ?? '') === $tab['slug'] ? ' is-active' : '' ?>">
            <?= htmlspecialchars($tab['name']) ?>
        </a>
    <?php endforeach; ?>
</nav>
<script>
// Sidebar-Memory: letzten CRM-Pfad merken
try { localStorage.setItem('thx_crm_last_path', window.location.pathname); } catch(e) {}
</script>
