<?php
/**
 * Gemeinsame Top-Tabs fuer den Wissen-Bereich.
 *
 * Erwartete Variable: $wissenAktiv = 'datenbank' | 'transkripte'
 * Wird sowohl von /wissen (Wissensdatenbank) als auch /admin/transkription
 * eingebunden, damit der User nahtlos zwischen beiden Untersystemen wechselt.
 */
$wissenAktiv = $wissenAktiv ?? 'datenbank';
// Eigene lokale Variable — sonst kollidiert das mit $tabs in der einbindenden View.
$_wissenTabs = [];
if (\Core\Auth::can(CAP_KNOWLEDGE)) {
    $_wissenTabs[] = ['slug' => 'datenbank',   'label' => 'Wissensdatenbank', 'href' => '/wissen'];
}
if (\Core\Auth::can(CAP_TRANSCRIPTION)) {
    $_wissenTabs[] = ['slug' => 'transkripte', 'label' => 'Transkripte',      'href' => '/admin/transkription'];
}
if (count($_wissenTabs) <= 1) return;
?>
<nav class="thx-tabs" aria-label="Wissen-Bereiche" style="margin-bottom: var(--d-section-gap);">
    <?php foreach ($_wissenTabs as $_t): ?>
        <a href="<?= $_t['href'] ?>" class="thx-tab<?= $wissenAktiv === $_t['slug'] ? ' is-active' : '' ?>">
            <?= htmlspecialchars($_t['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php unset($_wissenTabs, $_t); ?>
