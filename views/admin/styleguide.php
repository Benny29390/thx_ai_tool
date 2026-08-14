<?php
/**
 * Styleguide-Hub (Route /admin/styleguide, Cap CAP_STYLEGUIDE).
 *
 * Zwei Reiter:
 *   - corporate : generierter Thoxan Corporate-Design-Guide
 *                 (views/admin/styleguide/_corporate.php, via scripts/import-styleguide.php)
 *   - tokens    : Design-Tokens + Live-Tuning des Tools
 *                 (views/admin/styleguide/_tokens.php, frueher settings?tab=design)
 *
 * Reiter-State via ?tab=. Diese Wrapper-Datei ist HAND-geschrieben (nicht generiert).
 */
$sgTab = $_GET['tab'] ?? 'branding';
if (!in_array($sgTab, ['branding', 'corporate', 'tokens', 'vergleich'], true)) {
    $sgTab = 'branding';
}
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Styleguide</h1>
        <p class="thx-page-subtitle">Erscheinungsbild dieser Installation anpassen, Corporate-Design und Design-Tokens.</p>
    </div>
</div>

<nav class="thx-tabs" aria-label="Styleguide-Bereiche">
    <a href="/admin/styleguide?tab=branding" class="thx-tab<?= $sgTab === 'branding' ? ' is-active' : '' ?>">Eigenes Branding</a>
    <a href="/admin/styleguide?tab=corporate" class="thx-tab<?= $sgTab === 'corporate' ? ' is-active' : '' ?>">Corporate Design</a>
    <a href="/admin/styleguide?tab=tokens" class="thx-tab<?= $sgTab === 'tokens' ? ' is-active' : '' ?>">Tokens &amp; Live-Tuning</a>
    <a href="/admin/styleguide?tab=vergleich" class="thx-tab<?= $sgTab === 'vergleich' ? ' is-active' : '' ?>">Soll/Ist</a>
</nav>

<div style="margin-top:18px;">
<?php
// $sgTab ist auf 'corporate'|'tokens' validiert — kein Path-Traversal moeglich.
$partial = __DIR__ . '/styleguide/_' . $sgTab . '.php';
if (is_file($partial)) {
    include $partial;
} else {
    echo '<div class="thx-card"><p class="muted">Bereich nicht gefunden.</p></div>';
}
?>
</div>
