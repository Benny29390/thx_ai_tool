<?php
/**
 * Benutzer-Verwaltung — Tab-Hub.
 *
 * Tab-State per ?tab=. Partials liegen unter /var/www/views/admin/users/_tab_<slug>.php.
 * Beide Partials bekommen die per Controller geladenen Variablen vererbt:
 *   - $users, $customers, $csrfToken           (Benutzer-Tab)
 *   - $roleDefaults, $userCounts               (Rollen-Tab)
 */
use Core\Auth;

$tabs = [
    ['slug' => 'benutzer',       'name' => 'Benutzer',                'beschreibung' => 'Liste aller User + Bearbeiten (inkl. Projektplanner-Felder)'],
    ['slug' => 'rollen',         'name' => 'Rollen & Caps',           'beschreibung' => 'Default-Funktionen pro Rolle festlegen'],
    ['slug' => 'kunden',         'name' => 'Kundenzuordnung',         'beschreibung' => 'Welche Rolle/User auf welchen Kunden Zugriff hat'],
    ['slug' => 'pp-sharelinks',  'name' => 'Personen-Sharelinks',     'beschreibung' => 'Read-only-Aufgabenlisten pro Person ohne Login (Projektplanner)'],
    ['slug' => 'audit',          'name' => 'Audit-Log',               'beschreibung' => 'Wer hat wann welche Berechtigung geändert'],
];

$allowedSlugs = array_column($tabs, 'slug');
$activeTab = $_GET['tab'] ?? 'benutzer';
if (!in_array($activeTab, $allowedSlugs, true)) {
    $activeTab = 'benutzer';
}
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Benutzerverwaltung</h1>
        <p class="thx-page-subtitle">Einzelne User pflegen oder Default-Capabilities pro Rolle festlegen.</p>
    </div>
</div>

<nav class="thx-tabs" aria-label="Benutzer-Bereiche">
    <?php foreach ($tabs as $tab): ?>
        <a href="/admin/users?tab=<?= $tab['slug'] ?>"
           class="thx-tab<?= $activeTab === $tab['slug'] ? ' is-active' : '' ?>"
           title="<?= htmlspecialchars($tab['beschreibung']) ?>">
            <?= htmlspecialchars($tab['name']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div style="margin-top:24px;">
    <?php
    $partial = __DIR__ . '/users/_tab_' . $activeTab . '.php';
    if (file_exists($partial)) {
        include $partial;
    } else {
        echo '<div class="lam-card"><p class="muted">Tab nicht gefunden.</p></div>';
    }
    ?>
</div>
