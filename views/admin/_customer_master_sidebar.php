<?php
/**
 * Master-Sidebar fuer den Kunden-Bereich.
 * Erwartet: $customers (Liste, optional mit Tag/Counts), $activeCustomerId (int|null)
 *
 * Wird eingebunden in:
 *  - admin/customers.php (Master-Liste, Default-Pane = Site-Monitor)
 *  - admin/customer-steckbrief.php (Master-Detail)
 */

$activeCustomerId = $activeCustomerId ?? null;
if (!isset($customers)) {
    $db = \Core\Database::getInstance();
    // Sortierung nach Kürzel (Fallback Name) — wie in der Chat-Sidebar
    $customers = $db->query("SELECT * FROM customers ORDER BY COALESCE(NULLIF(TRIM(abbreviation), ''), name)") ?: [];
}

// Per-Customer Tags — bevorzugt das vom Controller ausgepackte 'tags'-Feld,
// sonst Fallback auf settings.tags (z.B. wenn das Partial standalone aufgerufen wird).
$_allTags = [];
foreach ($customers as &$_c) {
    if (isset($_c['tags']) && is_array($_c['tags'])) {
        $_c['tags_array'] = $_c['tags'];
    } else {
        $_s = json_decode($_c['settings'] ?? '{}', true) ?: [];
        $_c['tags_array'] = $_s['tags'] ?? [];
    }
    foreach ($_c['tags_array'] as $t) $_allTags[$t] = ($_allTags[$t] ?? 0) + 1;
}
unset($_c);
ksort($_allTags);

// Monitor-Status pro Customer aggregieren + Kategorien pro Customer (fuer Cross-Filter)
$_mon = [];
$_monCustStatusSet = []; // cust_id => "up down paused" als Space-Liste
$_monCustCatSet = [];    // cust_id => "kategorie1 kategorie2" als Space-Liste
$_monCats = [];
$_monStatusCounts = ['up' => 0, 'down' => 0, 'paused' => 0];
try {
    $db = \Core\Database::getInstance();
    $rows = $db->query("SELECT customer_id,
                               SUM(status='up') AS up_n,
                               SUM(status='down') AS down_n,
                               SUM(status='paused') AS paused_n,
                               COUNT(*) AS total
                        FROM pm_monitors GROUP BY customer_id") ?: [];
    foreach ($rows as $r) {
        $cid = (int) $r['customer_id'];
        $_mon[$cid] = [
            'up' => (int) $r['up_n'],
            'down' => (int) $r['down_n'],
            'paused' => (int) $r['paused_n'],
            'total' => (int) $r['total'],
        ];
        $_monStatusCounts['up'] += (int) $r['up_n'];
        $_monStatusCounts['down'] += (int) $r['down_n'];
        $_monStatusCounts['paused'] += (int) $r['paused_n'];
        $stati = [];
        if ($r['up_n'] > 0) $stati[] = 'up';
        if ($r['down_n'] > 0) $stati[] = 'down';
        if ($r['paused_n'] > 0) $stati[] = 'paused';
        $_monCustStatusSet[$cid] = implode(' ', $stati);
    }
    // Monitor-Kategorien: einheitlich aus Customer-Tags ableiten (single source of truth)
    // Multi-Tag: ein Customer mit „Eigenprojekt + Portal" zaehlt in BEIDEN Kategorien-Filtern.
    $_cTagsByCustomerActiveMonitor = [];
    foreach ($db->query(
        "SELECT DISTINCT m.customer_id, c.settings
         FROM pm_monitors m JOIN customers c ON c.id = m.customer_id
         WHERE m.customer_id IS NOT NULL"
    ) ?: [] as $r) {
        $cid = (int) $r['customer_id'];
        $s = json_decode($r['settings'] ?? '{}', true) ?: [];
        $_cTagsByCustomerActiveMonitor[$cid] = $s['tags'] ?? [];
    }
    foreach ($_cTagsByCustomerActiveMonitor as $cid => $tags) {
        foreach ($tags as $t) {
            $_monCats[$t] = ($_monCats[$t] ?? 0) + 1;
            // Mit '|' trennen, weil Tags Leerzeichen enthalten koennen ("Pro Bono")
            $_monCustCatSet[$cid] = ($_monCustCatSet[$cid] ?? '') . '|' . mb_strtolower($t);
        }
        $_monCustCatSet[$cid] = trim($_monCustCatSet[$cid] ?? '', '|');
    }
    ksort($_monCats);
} catch (\Throwable $e) { /* monitors optional */ }

$_initials = function (string $s): string {
    $parts = preg_split('/\s+/', trim($s));
    if (count($parts) >= 2) return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 2));
};
?>

<aside class="cm-sidebar" id="cm-sidebar">
    <div class="cm-sb-collapsed">
        <button class="cm-sb-toggle" onclick="cmToggleSidebar()" title="Aufklappen">
            <span class="material-symbols-rounded">menu</span>
        </button>
        <a href="/admin/customers" class="cm-sb-collapsed-home" title="Übersicht (Website-Monitor)">
            <span class="material-symbols-rounded">monitoring</span>
        </a>
    </div>

    <div class="cm-sb-head">
        <div class="cm-sb-title">Kunden</div>
        <button class="cm-sb-toggle" onclick="cmToggleSidebar()" title="Einklappen">
            <span class="material-symbols-rounded">chevron_left</span>
        </button>
    </div>

    <div class="cm-sb-search">
        <div class="cm-search-wrap">
            <span class="material-symbols-rounded cm-search-icon">search</span>
            <input type="search" class="cm-search-input" id="cm-search"
                   placeholder="Suchen — Name, Kürzel, Slug, Domain…"
                   oninput="cmFilterSidebar()">
        </div>
    </div>

    <div class="cm-sb-quickactions">
        <a href="/admin/customers" class="cm-quick<?= $activeCustomerId === null ? ' is-active' : '' ?>" title="Alle Projekte als Übersicht">
            <span class="material-symbols-rounded">grid_view</span>
            <span>Alle Projekte</span>
        </a>
        <button class="cm-quick" onclick="cmOpenNewCustomerModal()" title="Neuen Kunden anlegen">
            <span class="material-symbols-rounded">add_business</span>
            <span>Neuer Kunde</span>
        </button>
    </div>

    <div class="cm-sb-filters">
        <div class="cm-filter-row">
            <span class="cm-filter-label">Status</span>
            <button class="cm-filter-pill is-active" data-filter="status" data-value="" onclick="cmTogglePill(this)">Alle</button>
            <button class="cm-filter-pill" data-filter="status" data-value="active" onclick="cmTogglePill(this)">
                <span class="cm-dot cm-dot-active"></span>Aktiv
            </button>
            <button class="cm-filter-pill" data-filter="status" data-value="inactive" onclick="cmTogglePill(this)">
                <span class="cm-dot cm-dot-inactive"></span>Inaktiv
            </button>
        </div>
        <?php if (!empty($_allTags)): ?>
        <div class="cm-filter-row">
            <span class="cm-filter-label">Art</span>
            <?php foreach ($_allTags as $tag => $count): ?>
                <button class="cm-filter-pill" data-filter="tag" data-value="<?= htmlspecialchars($tag) ?>" onclick="cmTogglePill(this)">
                    <?= htmlspecialchars($tag) ?><span class="cm-pill-count"><?= $count ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($activeCustomerId === null && array_sum($_monStatusCounts) > 0): ?>
        <div class="cm-filter-row">
            <span class="cm-filter-label">Sites</span>
            <button class="cm-filter-pill" data-filter="monStatus" data-value="up" onclick="cmTogglePill(this)" title="Online">
                <span class="cm-dot" style="background:#10b981;"></span>Online<span class="cm-pill-count"><?= $_monStatusCounts['up'] ?></span>
            </button>
            <button class="cm-filter-pill" data-filter="monStatus" data-value="down" onclick="cmTogglePill(this)" title="Offline">
                <span class="cm-dot" style="background:#dc2626;"></span>Offline<span class="cm-pill-count"><?= $_monStatusCounts['down'] ?></span>
            </button>
            <?php if ($_monStatusCounts['paused'] > 0): ?>
            <button class="cm-filter-pill" data-filter="monStatus" data-value="paused" onclick="cmTogglePill(this)" title="Pausiert">
                <span class="cm-dot" style="background:#f59e0b;"></span>Pausiert<span class="cm-pill-count"><?= $_monStatusCounts['paused'] ?></span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="cm-sb-list" id="cm-customer-list">
        <?php foreach ($customers as $c):
            $cid = (int) $c['id'];
            $abbr = trim($c['abbreviation'] ?? '');
            if ($abbr === '') $abbr = $_initials($c['name'] ?? '?');
            $logo = trim($c['logo_path'] ?? '');
            $isActive = $cid === (int) $activeCustomerId;
            $statusDot = $c['is_active'] ? 'active' : 'inactive';
            $mon = $_mon[$cid] ?? null;
            $tags = $c['tags_array'] ?? [];
            $tagsHaystack = mb_strtolower(implode(' ', $tags));
            $haystack = mb_strtolower(($c['name'] ?? '') . ' ' . $abbr . ' ' . ($c['slug'] ?? '') . ' ' . ($c['website'] ?? '') . ' ' . $tagsHaystack);
        ?>
        <a class="cm-customer<?= $isActive ? ' is-active' : '' ?>"
           href="/admin/customers/<?= $cid ?>/steckbrief"
           data-id="<?= $cid ?>"
           data-status="<?= $statusDot ?>"
           data-tags="<?= htmlspecialchars($tagsHaystack) ?>"
           data-mon-status="<?= htmlspecialchars($_monCustStatusSet[$cid] ?? '') ?>"
           data-mon-cat="<?= htmlspecialchars(trim($_monCustCatSet[$cid] ?? '')) ?>"
           data-search="<?= htmlspecialchars($haystack) ?>">
            <span class="cm-customer-abbr"><?= htmlspecialchars(mb_substr($abbr, 0, 3)) ?></span>
            <div class="cm-customer-name"><?= htmlspecialchars($c['name']) ?></div>
            <?php if ($mon): ?>
                <?php if ($mon['down'] > 0): ?>
                    <span class="cm-mon-dot cm-mon-down" title="<?= $mon['down'] ?> von <?= $mon['total'] ?> Sites down"></span>
                <?php elseif ($mon['paused'] === $mon['total']): ?>
                    <span class="cm-mon-dot cm-mon-paused" title="<?= $mon['total'] ?> Sites pausiert"></span>
                <?php else: ?>
                    <span class="cm-mon-dot cm-mon-up" title="<?= $mon['up'] ?> von <?= $mon['total'] ?> Sites online"></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="cm-mon-dot cm-mon-none" title="Kein Monitor eingerichtet"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</aside>
