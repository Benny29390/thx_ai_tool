<?php
// Tags-Sammlung für Filter
$_allTags = [];
foreach ($customers as $c) {
    foreach (($c['tags'] ?? []) as $t) $_allTags[$t] = ($_allTags[$t] ?? 0) + 1;
}
arsort($_allTags);

// Initialen-Fallback
$_initials = function ($name) {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 2));
};
?>
<div class="cu-page">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Kunden</h1>
            <div class="thx-page-subtitle"><?= count($customers) ?> Einträge insgesamt</div>
        </div>
        <div class="thx-page-actions">
            <button type="button" class="thx-btn thx-btn-secondary" id="cu-favicon-bulk-btn" onclick="cuBulkFetchFavicons()" title="Bei allen Kunden mit Website (und noch ohne Logo) automatisch das Favicon ziehen">
                <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">language</span>
                Favicons holen
            </button>
            <a href="/admin/customers/wizard" class="thx-btn thx-btn-primary">
                <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">add</span>
                Neuer Kunde
            </a>
        </div>
    </div>

    <!-- Toolbar: Suche + View-Toggle + Sortierung -->
    <div class="cu-toolbar">
        <div class="cu-search-wrap">
            <span class="material-symbols-rounded cu-search-icon">search</span>
            <input type="text" id="cu-search" placeholder="Suchen — Name, Kürzel, Slug, Domain, Tag…" oninput="cuApplyFilter()">
            <button class="cu-search-clear" id="cu-search-clear" onclick="cuClearSearch()" style="display:none;" title="Zurücksetzen">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <select id="cu-sort" class="cu-filter" onchange="cuApplyFilter()">
            <option value="name">Name A–Z</option>
            <option value="name_desc">Name Z–A</option>
            <option value="created_desc">Neueste zuerst</option>
            <option value="created_asc">Älteste zuerst</option>
            <option value="activity">Letzte Aktivität</option>
            <option value="chats">Meiste Chats</option>
        </select>
        <div class="cu-view-toggle" role="group">
            <button class="active" data-view="grid" onclick="cuSetView('grid')" title="Card-Ansicht">
                <span class="material-symbols-rounded">grid_view</span>
            </button>
            <button data-view="list" onclick="cuSetView('list')" title="Listen-Ansicht">
                <span class="material-symbols-rounded">view_list</span>
            </button>
        </div>
    </div>

    <!-- Filter-Pills -->
    <div class="cu-pills">
        <div class="cu-pill-group">
            <span class="cu-pill-label">Status:</span>
            <button class="cu-pill active" data-filter="status" data-value="" onclick="cuTogglePill(this)">Alle</button>
            <button class="cu-pill" data-filter="status" data-value="active" onclick="cuTogglePill(this)">
                <span class="cu-pill-dot" style="background:#10b981;"></span>Aktiv
            </button>
            <button class="cu-pill" data-filter="status" data-value="inactive" onclick="cuTogglePill(this)">
                <span class="cu-pill-dot" style="background:#dc2626;"></span>Inaktiv
            </button>
        </div>

        <?php if (!empty($_allTags)): ?>
        <div class="cu-pill-group">
            <span class="cu-pill-label">Art:</span>
            <?php foreach ($_allTags as $tag => $count): ?>
                <button class="cu-pill" data-filter="tag" data-value="<?= htmlspecialchars($tag) ?>" onclick="cuTogglePill(this)">
                    <?= htmlspecialchars($tag) ?><span class="cu-pill-count"><?= $count ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <button class="cu-pill cu-pill-reset" onclick="cuResetFilter()" id="cu-reset-btn" style="display:none;">
            <span class="material-symbols-rounded" style="font-size:14px;">refresh</span>Filter zurücksetzen
        </button>
    </div>

    <!-- Empty -->
    <div class="cu-empty" id="cu-empty" style="display:none;">
        <span class="material-symbols-rounded">person_search</span>
        <h3>Keine Treffer</h3>
        <p>Filter zurücksetzen oder anderen Begriff probieren.</p>
    </div>

    <!-- Card-Grid -->
    <div class="cu-grid" id="cu-grid">
        <?php foreach ($customers as $customer):
            $abbr = trim($customer['abbreviation'] ?? '');
            if ($abbr === '') $abbr = $_initials($customer['name'] ?? '?');
            $industry = trim($customer['industry'] ?? '');
            $website = trim($customer['website'] ?? '');
            $createdTs = strtotime($customer['created_at']);
            $lastChatTs = $customer['last_chat_at'] ? strtotime($customer['last_chat_at']) : null;
            $asanaLastTs = $customer['asana_last_sync'] ? strtotime($customer['asana_last_sync']) : null;
            $websiteLastTs = $customer['website_last_sync'] ? strtotime($customer['website_last_sync']) : null;
            $tags = $customer['tags'] ?? [];
            $additionalDomains = $customer['additional_domains'] ?? [];
            $allDomains = [];
            if ($website !== '') $allDomains[] = $website;
            foreach ($additionalDomains as $d) if (!empty($d['url'])) $allDomains[] = $d['url'];
            $domainsHaystack = mb_strtolower(implode(' ', $allDomains));
            $tagsHaystack = mb_strtolower(implode(' ', $tags));
        ?>
        <a class="cu-card" href="/admin/customers/<?= $customer['id'] ?>/steckbrief"
           data-id="<?= (int) $customer['id'] ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($customer['name'])) ?>"
           data-abbr="<?= htmlspecialchars(mb_strtolower($abbr)) ?>"
           data-slug="<?= htmlspecialchars(mb_strtolower($customer['slug'])) ?>"
           data-industry="<?= htmlspecialchars($industry) ?>"
           data-domains="<?= htmlspecialchars($domainsHaystack) ?>"
           data-tags="<?= htmlspecialchars($tagsHaystack) ?>"
           data-tags-list='<?= htmlspecialchars(json_encode($tags), ENT_QUOTES) ?>'
           data-status="<?= $customer['is_active'] ? 'active' : 'inactive' ?>"
           data-has-asana="<?= !empty($customer['has_asana']) ? '1' : '0' ?>"
           data-has-website="<?= !empty($customer['has_website']) ? '1' : '0' ?>"
           data-created="<?= $createdTs ?>"
           data-activity="<?= $lastChatTs ?? $createdTs ?>"
           data-chats="<?= (int) $customer['chat_count'] ?>">

            <div class="cu-card-top">
                <?php $_cuLogo = trim($customer['logo_path'] ?? ''); ?>
                <?php if ($_cuLogo !== ''): ?>
                    <div class="cu-badge cu-badge-logo">
                        <img src="/uploads/customers/logos/<?= htmlspecialchars(basename($_cuLogo)) ?>"
                             alt="<?= htmlspecialchars($customer['name']) ?>" loading="lazy">
                    </div>
                <?php else: ?>
                    <div class="cu-badge"><?= htmlspecialchars($abbr) ?></div>
                <?php endif; ?>
                <?php if (!$customer['is_active']): ?>
                    <span class="cu-status-pill inactive">Inaktiv</span>
                <?php endif; ?>
                <button class="cu-card-delete" onclick="event.preventDefault();event.stopPropagation();deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars(addslashes($customer['name'])) ?>')" title="Löschen">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>

            <div class="cu-card-name"><?= htmlspecialchars($customer['name']) ?></div>

            <?php if (!empty($tags)): ?>
            <div class="cu-card-tags">
                <?php foreach ($tags as $t): ?>
                    <span class="cu-tag-chip"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cu-card-meta">
                <?php if ($industry !== ''): ?><span class="cu-pill-static"><?= htmlspecialchars($industry) ?></span><?php endif; ?>
                <code class="cu-slug"><?= htmlspecialchars($customer['slug']) ?></code>
            </div>

            <?php if (!empty($allDomains)): ?>
            <div class="cu-card-domains">
                <?php $shown = array_slice($allDomains, 0, 3); foreach ($shown as $i => $u): ?>
                <div class="cu-card-website">
                    <span class="material-symbols-rounded">language</span>
                    <span><?= htmlspecialchars(preg_replace('#^https?://(www\.)?#', '', $u)) ?></span>
                </div>
                <?php endforeach; if (count($allDomains) > 3): ?>
                <div class="cu-card-website" style="color:#94a3b8;font-size: var(--d-fs-xs);">+<?= count($allDomains) - 3 ?> weitere</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="cu-stats">
                <div class="cu-stat" title="Chats">
                    <span class="material-symbols-rounded">chat</span>
                    <span class="cu-stat-num"><?= (int) $customer['chat_count'] ?></span>
                </div>
                <div class="cu-stat" title="Wissens-Einträge">
                    <span class="material-symbols-rounded">library_books</span>
                    <span class="cu-stat-num"><?= (int) $customer['knowledge_count'] ?></span>
                </div>
                <div class="cu-stat" title="Steckbrief-Cards">
                    <span class="material-symbols-rounded">dashboard</span>
                    <span class="cu-stat-num"><?= (int) $customer['user_cards_count'] ?></span>
                </div>
            </div>

            <div class="cu-sync-row">
                <div class="cu-sync <?= !empty($customer['has_asana']) ? 'on' : 'off' ?>" title="Asana">
                    <span class="material-symbols-rounded">task_alt</span>
                    <?= !empty($customer['has_asana']) ? ($asanaLastTs ? date('d.m.', $asanaLastTs) : 'aktiv') : '—' ?>
                </div>
                <div class="cu-sync <?= !empty($customer['has_website']) ? 'on' : 'off' ?>" title="Website-Crawler">
                    <span class="material-symbols-rounded">language</span>
                    <?= !empty($customer['has_website']) ? ($websiteLastTs ? date('d.m.', $websiteLastTs) : 'aktiv') : '—' ?>
                </div>
                <?php if ($lastChatTs): ?>
                <div class="cu-sync on" title="Letzter Chat" style="margin-left:auto;">
                    <span class="material-symbols-rounded">history</span>
                    <?= date('d.m.', $lastChatTs) ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Listen-Ansicht -->
    <div class="cu-list" id="cu-list" style="display:none;">
        <div class="cu-list-head">
            <div>Kunde</div>
            <div>Art / Branche</div>
            <div class="text-right">Chats</div>
            <div class="text-right">Wissen</div>
            <div class="text-right">Cards</div>
            <div>Status</div>
            <div></div>
        </div>
        <?php foreach ($customers as $customer):
            $abbr = trim($customer['abbreviation'] ?? '');
            if ($abbr === '') $abbr = $_initials($customer['name'] ?? '?');
            $createdTs = strtotime($customer['created_at']);
            $lastChatTs = $customer['last_chat_at'] ? strtotime($customer['last_chat_at']) : null;
            $tags = $customer['tags'] ?? [];
            $additionalDomains = $customer['additional_domains'] ?? [];
            $allDomains = []; if (!empty($customer['website'])) $allDomains[] = $customer['website'];
            foreach ($additionalDomains as $d) if (!empty($d['url'])) $allDomains[] = $d['url'];
            $domainsHaystack = mb_strtolower(implode(' ', $allDomains));
            $tagsHaystack = mb_strtolower(implode(' ', $tags));
        ?>
        <a class="cu-list-row" href="/admin/customers/<?= $customer['id'] ?>/steckbrief"
           data-id="<?= (int) $customer['id'] ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($customer['name'])) ?>"
           data-abbr="<?= htmlspecialchars(mb_strtolower($abbr)) ?>"
           data-slug="<?= htmlspecialchars(mb_strtolower($customer['slug'])) ?>"
           data-industry="<?= htmlspecialchars($customer['industry'] ?? '') ?>"
           data-domains="<?= htmlspecialchars($domainsHaystack) ?>"
           data-tags="<?= htmlspecialchars($tagsHaystack) ?>"
           data-tags-list='<?= htmlspecialchars(json_encode($tags), ENT_QUOTES) ?>'
           data-status="<?= $customer['is_active'] ? 'active' : 'inactive' ?>"
           data-has-asana="<?= !empty($customer['has_asana']) ? '1' : '0' ?>"
           data-has-website="<?= !empty($customer['has_website']) ? '1' : '0' ?>"
           data-created="<?= $createdTs ?>"
           data-activity="<?= $lastChatTs ?? $createdTs ?>"
           data-chats="<?= (int) $customer['chat_count'] ?>">
            <div class="cu-list-name">
                <?php $_cuLogoL = trim($customer['logo_path'] ?? ''); ?>
                <?php if ($_cuLogoL !== ''): ?>
                    <span class="cu-badge-mini cu-badge-mini-logo">
                        <img src="/uploads/customers/logos/<?= htmlspecialchars(basename($_cuLogoL)) ?>"
                             alt="<?= htmlspecialchars($customer['name']) ?>" loading="lazy">
                    </span>
                <?php else: ?>
                    <span class="cu-badge-mini"><?= htmlspecialchars($abbr) ?></span>
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($customer['name']) ?></strong>
                    <small><code><?= htmlspecialchars($customer['slug']) ?></code></small>
                </div>
            </div>
            <div class="cu-cell-muted">
                <?php if (!empty($tags)): foreach ($tags as $t): ?>
                    <span class="cu-tag-chip" style="margin-right:3px;"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; else: ?>
                    <?= htmlspecialchars($customer['industry'] ?: '–') ?>
                <?php endif; ?>
            </div>
            <div class="text-right"><?= (int) $customer['chat_count'] ?></div>
            <div class="text-right"><?= (int) $customer['knowledge_count'] ?></div>
            <div class="text-right"><?= (int) $customer['user_cards_count'] ?></div>
            <div>
                <?php if ($customer['is_active']): ?>
                    <span class="cu-status-pill active">Aktiv</span>
                <?php else: ?>
                    <span class="cu-status-pill inactive">Inaktiv</span>
                <?php endif; ?>
            </div>
            <div class="cu-list-actions">
                <button class="cu-icon-btn danger" onclick="event.preventDefault();event.stopPropagation();deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars(addslashes($customer['name'])) ?>')" title="Löschen">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
.cu-page { /* kein eigener max-width/padding — .main-content liefert die Gutter konsistent */ }
.cu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; gap: 1rem; }

/* Toolbar */
.cu-toolbar {
    display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;
    padding: 0.7rem; margin-bottom: 0.8rem;
    background: rgba(255,255,255,0.9); backdrop-filter: blur(14px);
    border: 1px solid #e2e8f0; border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    position: sticky; top: 0; z-index: 50;
}
.cu-search-wrap { flex: 1; min-width: 220px; position: relative; display: flex; align-items: center; }
.cu-search-icon { position: absolute; left: 12px; color: #94a3b8; font-size: 20px; pointer-events: none; transition: color 0.2s; }
.cu-search-wrap:focus-within .cu-search-icon { color: var(--thoxan-700); }
#cu-search { width: 100%; padding: 9px 36px 9px 40px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: var(--d-fs-sm); background: #fff; transition: all 0.2s; font-family: inherit; }
#cu-search:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 3px var(--thoxan-200); }
.cu-search-clear { position: absolute; right: 8px; background: none; border: 0; cursor: pointer; color: #64748b; padding: 4px; border-radius: 6px; display: flex; align-items: center; }
.cu-search-clear:hover { background: #f1f5f9; }
.cu-filter { padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: var(--d-fs-sm); cursor: pointer; font-family: inherit; color: #1e293b; }
.cu-filter:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 3px var(--thoxan-200); }
.cu-view-toggle { display: flex; gap: 2px; padding: 3px; background: #f1f5f9; border-radius: 10px; }
.cu-view-toggle button { background: transparent; border: 0; cursor: pointer; width: 34px; height: 32px; border-radius: 7px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.15s; }
.cu-view-toggle button:hover { color: #1e293b; }
.cu-view-toggle button.active { background: #fff; color: var(--thoxan-700); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.cu-view-toggle .material-symbols-rounded { font-size: 18px; }

/* Filter-Pills */
.cu-pills {
    display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap;
    margin-bottom: 1.25rem; padding: 0.4rem 0.2rem;
}
.cu-pill-group { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.cu-pill-label {
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.5px;
    color: #94a3b8; font-weight: 700; margin-right: 4px;
}
.cu-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 11px; border-radius: 999px;
    background: #fff; border: 1px solid #e2e8f0;
    color: #475569; font-size: var(--d-fs-sm); font-weight: 600;
    cursor: pointer; font-family: inherit;
    transition: all 0.15s;
}
.cu-pill:hover { border-color: #cbd5e1; background: #f8fafc; }
.cu-pill.active {
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)); color: #fff; border-color: transparent;
    box-shadow: 0 3px 10px var(--thoxan-300);
}
.cu-pill.active .cu-pill-count { background: rgba(255,255,255,0.25); color: #fff; }
.cu-pill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.cu-pill-count { background: #f1f5f9; color: #64748b; padding: 1px 7px; border-radius: 10px; font-size: var(--d-fs-xs); font-weight: 700; }
.cu-pill-reset {
    background: #fef2f2; border-color: #fecaca; color: #b91c1c;
}
.cu-pill-reset:hover { background: #fee2e2; border-color: #fca5a5; }

/* Empty */
.cu-empty { text-align: center; padding: 4rem 1rem; color: #94a3b8; }
.cu-empty .material-symbols-rounded { font-size: 56px; color: #cbd5e1; margin-bottom: 0.5rem; }
.cu-empty h3 { margin: 0 0 0.4rem; color: #475569; font-size: var(--d-fs-lg); }
.cu-empty p { margin: 0; font-size: var(--d-fs-sm); }

/* Grid */
.cu-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

/* Card */
.cu-card {
    display: flex; flex-direction: column; gap: 0.55rem;
    padding: 1.05rem; background: #fff;
    border: 1px solid #e2e8f0; border-radius: 16px;
    text-decoration: none; color: inherit;
    transition: transform 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.4), box-shadow 0.2s, border-color 0.2s;
    position: relative; overflow: hidden;
}
.cu-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--thoxan-700), var(--thoxan-600), #3a8ed9);
    opacity: 0; transition: opacity 0.2s;
}
.cu-card:hover { border-color: #cbd5e1; box-shadow: 0 12px 28px var(--thoxan-200); }
.cu-card:hover::before { opacity: 1; }
.cu-card.cu-hidden { display: none; }

.cu-card-top { display: flex; align-items: center; gap: 0.5rem; }
.cu-badge {
    width: 56px; height: 56px; border-radius: 14px;
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: var(--d-fs-base); font-weight: 800; letter-spacing: 0.5px;
    box-shadow: 0 4px 12px var(--thoxan-300);
    flex-shrink: 0; text-transform: uppercase;
    overflow: hidden;
}
.cu-badge-logo {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 4px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}
.cu-badge-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.cu-card-delete { margin-left: auto; background: none; border: 0; cursor: pointer; color: #cbd5e1; padding: 6px; border-radius: 8px; display: flex; align-items: center; opacity: 0; transition: all 0.15s; }
.cu-card:hover .cu-card-delete { opacity: 1; }
.cu-card-delete:hover { background: #fef2f2; color: #dc2626; }
.cu-card-delete .material-symbols-rounded { font-size: 18px; }

.cu-card-name { font-size: var(--d-fs-lg); font-weight: 700; color: #1e293b; line-height: 1.3; word-break: break-word; }
.cu-card-tags { display: flex; gap: 4px; flex-wrap: wrap; }
.cu-tag-chip {
    display: inline-block; padding: 2px 9px; background: var(--thoxan-100); color: var(--thoxan-900);
    border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 600;
}
.cu-card-meta { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; font-size: var(--d-fs-sm); }
.cu-pill-static { background: #f1f5f9; color: #475569; padding: 3px 10px; border-radius: 8px; font-weight: 500; }
.cu-slug { color: #94a3b8; font-size: var(--d-fs-xs); background: transparent; padding: 0; }

.cu-card-domains { display: flex; flex-direction: column; gap: 2px; }
.cu-card-website { display: flex; gap: 5px; align-items: center; font-size: var(--d-fs-sm); color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cu-card-website .material-symbols-rounded { font-size: 14px; color: #0891b2; flex-shrink: 0; }
.cu-card-website span:last-child { overflow: hidden; text-overflow: ellipsis; }

.cu-stats { display: flex; gap: 0.8rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; margin-top: auto; }
.cu-stat { display: flex; align-items: center; gap: 4px; color: #64748b; font-size: var(--d-fs-sm); font-weight: 500; }
.cu-stat .material-symbols-rounded { font-size: 16px; color: #94a3b8; }
.cu-stat-num { color: #1e293b; font-weight: 700; }

.cu-sync-row { display: flex; gap: 0.6rem; flex-wrap: wrap; font-size: var(--d-fs-xs); }
.cu-sync { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 8px; background: #f8fafc; color: #94a3b8; font-weight: 600; }
.cu-sync.on { background: #ecfdf5; color: #047857; }
.cu-sync .material-symbols-rounded { font-size: 12px; }

.cu-status-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 700;
    margin-left: auto;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.cu-status-pill.active { background: #ecfdf5; color: #047857; }
.cu-status-pill.inactive { background: #fef2f2; color: #b91c1c; }

/* FLIP-Animation: Cards "fliegen" beim Re-Order an ihre neue Position */
.cu-grid.flip-animate .cu-card { transition: transform 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), box-shadow 0.2s, border-color 0.2s; }

/* Liste */
.cu-list { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
.cu-list-head { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.4fr) 70px 70px 70px 100px 40px; gap: 0.75rem; padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700; }
.cu-list-row { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.4fr) 70px 70px 70px 100px 40px; gap: 0.75rem; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: background 0.1s; align-items: center; }
.cu-list-row:hover { background: #fafbfc; }
.cu-list-row:last-child { border-bottom: 0; }
.cu-list-row.cu-hidden { display: none; }
.cu-list-name { display: flex; gap: 10px; align-items: center; min-width: 0; }
.cu-list-name strong { display: block; font-size: var(--d-fs-sm); color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cu-list-name small { display: block; color: #94a3b8; font-size: var(--d-fs-xs); margin-top: 2px; }
.cu-list-name small code { background: transparent; padding: 0; }
.cu-badge-mini { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: var(--d-fs-xs); font-weight: 700; flex-shrink: 0; text-transform: uppercase; overflow: hidden; }
.cu-badge-mini-logo { background: #fff; border: 1px solid #e2e8f0; padding: 2px; }
.cu-badge-mini-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.cu-cell-muted { color: #64748b; font-size: var(--d-fs-sm); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.text-right { text-align: right; font-weight: 600; color: #1e293b; }
.cu-list-actions { display: flex; justify-content: flex-end; }
.cu-icon-btn { background: none; border: 0; cursor: pointer; padding: 6px; border-radius: 7px; color: #cbd5e1; display: flex; transition: all 0.1s; }
.cu-icon-btn:hover { color: #1e293b; background: #f1f5f9; }
.cu-icon-btn.danger:hover { background: #fef2f2; color: #dc2626; }
.cu-icon-btn .material-symbols-rounded { font-size: 18px; }

@media (max-width: 900px) {
    .cu-list-head, .cu-list-row { grid-template-columns: 1fr auto auto; }
    .cu-list-head > div:nth-child(2),
    .cu-list-head > div:nth-child(3),
    .cu-list-head > div:nth-child(4),
    .cu-list-row > div:nth-child(2),
    .cu-list-row > div:nth-child(3),
    .cu-list-row > div:nth-child(4) { display: none; }
}
</style>

<script>
const cuView = { value: localStorage.getItem('cu-view') || 'grid' };
const cuFilter = { status: '', tags: new Set() };

function cuSetView(v) {
    cuView.value = v;
    localStorage.setItem('cu-view', v);
    document.getElementById('cu-grid').style.display = (v === 'grid') ? 'grid' : 'none';
    document.getElementById('cu-list').style.display = (v === 'list') ? 'block' : 'none';
    document.querySelectorAll('.cu-view-toggle button').forEach(b => b.classList.toggle('active', b.dataset.view === v));
    cuApplyFilter();
}

function cuTogglePill(btn) {
    const f = btn.dataset.filter;
    const v = btn.dataset.value;
    if (f === 'tag') {
        if (cuFilter.tags.has(v)) cuFilter.tags.delete(v); else cuFilter.tags.add(v);
        btn.classList.toggle('active', cuFilter.tags.has(v));
    } else {
        // Single-Select pro Gruppe (status, sync)
        cuFilter[f] = (cuFilter[f] === v) ? '' : v;
        document.querySelectorAll(`.cu-pill[data-filter="${f}"]`).forEach(p => {
            p.classList.toggle('active', p.dataset.value === cuFilter[f]);
        });
    }
    cuApplyFilter();
}

function cuResetFilter() {
    cuFilter.status = ''; cuFilter.tags.clear();
    document.querySelectorAll('.cu-pill').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.cu-pill[data-filter="status"][data-value=""]').forEach(p => p.classList.add('active'));
    document.getElementById('cu-search').value = '';
    cuApplyFilter();
}

function cuApplyFilter() {
    const q = (document.getElementById('cu-search').value || '').toLowerCase().trim();
    const sort = document.getElementById('cu-sort').value;
    document.getElementById('cu-search-clear').style.display = q ? 'flex' : 'none';

    // Reset-Button anzeigen wenn etwas aktiv
    const anyActive = q || cuFilter.status || cuFilter.tags.size > 0;
    document.getElementById('cu-reset-btn').style.display = anyActive ? 'inline-flex' : 'none';

    const containerId = cuView.value === 'grid' ? 'cu-grid' : 'cu-list';
    const container = document.getElementById(containerId);
    const itemSelector = cuView.value === 'grid' ? '.cu-card' : '.cu-list-row';
    const items = Array.from(container.querySelectorAll(itemSelector));

    // FLIP: First — Positionen merken
    const positions = new Map();
    items.forEach(el => positions.set(el, el.getBoundingClientRect()));

    let visibleCount = 0;
    items.forEach(el => {
        let show = true;
        if (q) {
            const hay = [el.dataset.name, el.dataset.abbr, el.dataset.slug, (el.dataset.industry || '').toLowerCase(), el.dataset.domains, el.dataset.tags].join(' ');
            if (!hay.includes(q)) show = false;
        }
        if (show && cuFilter.status && el.dataset.status !== cuFilter.status) show = false;
        if (show && cuFilter.tags.size > 0) {
            let cardTags = [];
            try { cardTags = JSON.parse(el.dataset.tagsList || '[]'); } catch (e) {}
            const hasAny = Array.from(cuFilter.tags).some(t => cardTags.includes(t));
            if (!hasAny) show = false;
        }
        el.classList.toggle('cu-hidden', !show);
        if (show) visibleCount++;
    });

    // Sortierung
    const visible = items.filter(el => !el.classList.contains('cu-hidden'));
    visible.sort((a, b) => {
        switch (sort) {
            case 'name_desc':    return b.dataset.name.localeCompare(a.dataset.name, 'de');
            case 'created_desc': return (+b.dataset.created) - (+a.dataset.created);
            case 'created_asc':  return (+a.dataset.created) - (+b.dataset.created);
            case 'activity':     return (+b.dataset.activity) - (+a.dataset.activity);
            case 'chats':        return (+b.dataset.chats) - (+a.dataset.chats);
            case 'name':
            default:             return a.dataset.name.localeCompare(b.dataset.name, 'de');
        }
    });
    visible.forEach(el => container.appendChild(el));

    document.getElementById('cu-empty').style.display = visibleCount === 0 ? 'block' : 'none';
    container.style.display = (visibleCount === 0) ? 'none' : (cuView.value === 'grid' ? 'grid' : 'block');

    // FLIP: Last — neue Positionen, Invert + Play
    if (cuView.value === 'grid') {
        container.classList.add('flip-animate');
        items.forEach(el => {
            if (el.classList.contains('cu-hidden')) return;
            const oldRect = positions.get(el);
            const newRect = el.getBoundingClientRect();
            const dx = oldRect.left - newRect.left;
            const dy = oldRect.top - newRect.top;
            if (dx === 0 && dy === 0) return;
            el.style.transition = 'none';
            el.style.transform = `translate(${dx}px, ${dy}px)`;
            requestAnimationFrame(() => {
                el.style.transition = '';
                el.style.transform = '';
            });
        });
    }
}

function cuClearSearch() {
    document.getElementById('cu-search').value = '';
    cuApplyFilter();
}

async function deleteCustomer(id, name) {
    if (!confirm('Kunde "' + name + '" wirklich löschen?\n\nAlle Zuordnungen (Regeln, Wissen, etc.) werden entfernt.')) return;
    try {
        await App.delete('/admin/customers/' + id);
        App.showNotification('Kunde gelöscht', 'success');
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

cuSetView(cuView.value);

// ===== Favicon-Bulk =====
async function cuBulkFetchFavicons() {
    // Cards (Grid) und List-Rows enthalten dieselben Kunden — Set zum Deduplizieren
    const nodes = document.querySelectorAll('.cu-card, .cu-list-row');
    const seen = new Map(); // id → hasLogo
    nodes.forEach(c => {
        const id = parseInt(c.getAttribute('data-id'), 10) || 0;
        if (!id) return;
        const hasWebsite = c.getAttribute('data-has-website') === '1';
        if (!hasWebsite) return;
        const hasLogo = !!c.querySelector('.cu-badge-logo, .cu-badge-mini-logo');
        const prev = seen.get(id);
        seen.set(id, prev || hasLogo);
    });
    const validIds = [];
    seen.forEach((hasLogo, id) => { if (!hasLogo) validIds.push(id); });
    if (!validIds.length) {
        App.showNotification('Keine passenden Kunden (Website vorhanden, Logo fehlt)', 'info');
        return;
    }
    if (!confirm(`Für ${validIds.length} Kunde(n) das Favicon automatisch holen?\nDas kann je nach Server-Antworten 10–60 Sekunden dauern.`)) return;

    const btn = document.getElementById('cu-favicon-bulk-btn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">hourglass_top</span> Läuft…';
    try {
        const r = await fetch('/api/v1/admin/customers/bulk-fetch-favicons', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ ids: validIds })
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Bulk-Fetch fehlgeschlagen');
        const ok = j.data.ok || [];
        const fail = j.data.fail || [];
        App.showNotification(`${ok.length} Logos übernommen, ${fail.length} fehlgeschlagen — Seite wird neu geladen`, ok.length ? 'success' : 'info');
        if (fail.length) console.warn('Favicon-Fehler:', fail);
        setTimeout(() => location.reload(), 1200);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
</script>
