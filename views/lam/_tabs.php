<?php
/**
 * LAM-Modul-Navigation als horizontale Tab-Leiste im Content-Bereich.
 *
 * Erwartete Variable im einbindenden View:
 *   $activeModul — Slug des aktiven Tabs (dashboard|linkprofil|linkquellen|...)
 *
 * Pflegt zudem im localStorage den zuletzt besuchten LAM-Bereich, damit der
 * Sidebar-Klick auf "LAM-System" dort weitermacht.
 */
$lamModule = [
    ['slug' => 'dashboard',     'name' => 'Dashboard',     'href' => '/lam'],
    ['slug' => 'linkprofil',    'name' => 'Linkprofil',    'href' => '/lam/linkprofil'],
    ['slug' => 'linkquellen',   'name' => 'Linkquellen',   'href' => '/lam/linkquellen'],
    ['slug' => 'anbieter',      'name' => 'Anbieter',      'href' => '/lam/anbieter'],
    ['slug' => 'linkakquise',   'name' => 'Linkakquise',   'href' => '/lam/linkakquise'],
    ['slug' => 'linkoptionen',  'name' => 'Linkoptionen',  'href' => '/lam/linkoptionen'],
    ['slug' => 'massnahmen',    'name' => 'Maßnahmen',     'href' => '/lam/massnahmen'],
    ['slug' => 'auslagen',      'name' => 'Auslagen',      'href' => '/lam/auslagen'],
    ['slug' => 'monitoring',    'name' => 'Monitoring',    'href' => '/lam/monitoring'],
    ['slug' => 'korrespondenz', 'name' => 'Korrespondenz', 'href' => '/lam/korrespondenz'],
    ['slug' => 'aufgaben',      'name' => 'Aufgaben',      'href' => '/lam/aufgaben'],
    ['slug' => 'linkziele',     'name' => 'Linkziele',     'href' => '/lam/linkziele'],
    ['slug' => 'tags',          'name' => 'Tags',          'href' => '/lam/tags'],
    ['slug' => 'audit',         'name' => 'Audit',         'href' => '/lam/audit-log'],
];
?>
<nav class="thx-tabs" aria-label="LAM-Module">
    <?php foreach ($lamModule as $item): ?>
        <?php
        $isActive   = ($activeModul ?? '') === $item['slug'];
        $isDisabled = !empty($item['disabled']);
        $classes    = 'thx-tab';
        if ($isActive)   $classes .= ' is-active';
        if ($isDisabled) $classes .= ' is-disabled';
        ?>
        <?php if ($isDisabled): ?>
            <span class="<?= $classes ?>" title="noch nicht aktiv"><?= htmlspecialchars($item['name']) ?></span>
        <?php else: ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $classes ?>"><?= htmlspecialchars($item['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<script>
(function() {
    try { localStorage.setItem('thx_lam_last_path', window.location.pathname); } catch (e) {}
})();

/**
 * Absolute, extern oeffenbare URL aus einem LAM-Eintrag bauen.
 * Die URLs liegen ohne Protokoll in der DB ("www.example.de/pfad", "dkv.org/foo") —
 * ohne "https://" wuerde der Browser sie als relativen Pfad interpretieren.
 * Global, damit jede LAM-View (Alpine-Ausdruecke greifen auf window zu) sie nutzen kann.
 */
window.extUrl = function (u) {
    const s = String(u == null ? '' : u).trim();
    if (s === '') return '#';
    return /^https?:\/\//i.test(s) ? s : 'https://' + s;
};
</script>
