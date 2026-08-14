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
// Die Reiter „Corporate Design" und „Soll/Ist" zeigen den generierten
// Thoxan-Referenz-Guide. Auf einer Kundeninstallation (echte Lizenzdatei
// vorhanden) sind sie irrelevant und werden ausgeblendet. Ein expliziter
// Schalter brand_show_reference kann das ueberschreiben.
$refOverride = \Core\Settings::get('brand_show_reference');
if ($refOverride === '0' || $refOverride === '1') {
    $showReference = $refOverride === '1';
} else {
    $showReference = (\Core\License::status() === 'none'); // Thoxan: ja, Kunde: nein
}
$erlaubteTabs = $showReference ? ['branding', 'corporate', 'tokens', 'vergleich'] : ['branding', 'tokens'];
$sgTab = $_GET['tab'] ?? 'branding';
if (!in_array($sgTab, $erlaubteTabs, true)) {
    $sgTab = 'branding';
}
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Styleguide</h1>
        <p class="thx-page-subtitle">Erscheinungsbild dieser Installation anpassen<?= $showReference ? ', Corporate-Design und Design-Tokens' : ' und Design-Tokens' ?>.</p>
    </div>
</div>

<nav class="thx-tabs" aria-label="Styleguide-Bereiche">
    <a href="/admin/styleguide?tab=branding" class="thx-tab<?= $sgTab === 'branding' ? ' is-active' : '' ?>">Eigenes Branding</a>
    <?php if ($showReference): ?>
    <a href="/admin/styleguide?tab=corporate" class="thx-tab<?= $sgTab === 'corporate' ? ' is-active' : '' ?>">Corporate Design</a>
    <?php endif; ?>
    <a href="/admin/styleguide?tab=tokens" class="thx-tab<?= $sgTab === 'tokens' ? ' is-active' : '' ?>">Tokens &amp; Live-Tuning</a>
    <?php if ($showReference): ?>
    <a href="/admin/styleguide?tab=vergleich" class="thx-tab<?= $sgTab === 'vergleich' ? ' is-active' : '' ?>">Soll/Ist</a>
    <?php endif; ?>
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
