<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Kundenportal') ?> · <?= APP_NAME ?></title>
    <link rel="preload" href="/assets/fonts/material-symbols-rounded.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/lam/frutiger-lt-std-roman.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/lam/frutiger-lt-std-bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/material-symbols.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="/assets/css/thx-tokens.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/thx-tokens.css') ?>">
    <link rel="stylesheet" href="/assets/css/thx-components.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/thx-components.css') ?>">
    <style>
        html { font-size: 120%; }
        body { margin: 0; background: var(--slate-50); color: var(--slate-800); font-family: 'Frutiger LT Std', system-ui, sans-serif; }
        /* Inhalt unter die fixe 44px-Topbar schieben */
        .portal-main { padding-top: 44px; }
        .portal-wrap { max-width: 1120px; margin: 0 auto; padding: var(--space-5) var(--space-4) 64px; }
        /* Brand links in der Topbar (analog Sidebar-Logo) */
        .portal-brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: #fff; }
        .portal-brand img { height: 22px; }
        /* Kachel-Raster aus .thx-card */
        .portal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-4); align-items: start; }
        .portal-grid .thx-card { display: flex; flex-direction: column; gap: 2px; }
        .portal-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: var(--d-card-gap); }
        .portal-card-head .material-symbols-rounded { font-size: 20px; color: var(--thoxan-700); }
        .portal-kpi { display: flex; justify-content: space-between; align-items: baseline; padding: 7px 0; border-bottom: 1px solid var(--slate-100); }
        .portal-kpi:last-child { border-bottom: none; }
        .portal-kpi .v { font-weight: 700; color: var(--slate-900); }
        .portal-ms { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: var(--d-fs-sm); }
        .portal-ms .material-symbols-rounded { font-size: 18px; }
        .portal-ms.done .material-symbols-rounded { color: var(--emerald-600); }
        .portal-ms.open .material-symbols-rounded { color: var(--slate-300); }
        .portal-ms .d { margin-left: auto; color: var(--slate-400); font-size: var(--d-fs-xs); white-space: nowrap; }
        .portal-rt { line-height: 1.55; font-size: var(--d-fs-sm); }
        .portal-rt h1, .portal-rt h2, .portal-rt h3 { font-size: var(--d-fs-base); margin: 10px 0 4px; }
        .portal-rt ul { margin: 6px 0; padding-left: 20px; }
        .portal-empty { color: var(--slate-400); font-size: var(--d-fs-sm); }
        .portal-preview { display:flex; align-items:center; gap:8px; justify-content:center; background: var(--amber-100); color: var(--amber-800); border-bottom: 1px solid var(--amber-300); font-size: var(--d-fs-xs); font-weight: 600; padding: 6px 12px; }
    </style>
</head>
<body>
    <header class="thx-topbar" aria-label="Kundenportal">
        <span class="portal-brand">
            <img src="/assets/images/thoxan-x.svg" alt="Thoxan" onerror="this.style.display='none'">
            <span><?= APP_NAME ?></span>
        </span>
        <span class="thx-topbar-spacer"></span>
        <div class="thx-topbar-links">
            <?php if (!empty($header['name'])): ?>
                <span class="thx-topbar-username"><?= htmlspecialchars($header['name']) ?></span>
                <span class="thx-topbar-divider"></span>
            <?php endif; ?>
            <a href="/logout" class="thx-topbar-link">Abmelden</a>
        </div>
    </header>
    <main class="portal-main">
        <?php if (!empty($isPreview)): ?>
            <div class="portal-preview">
                <span class="material-symbols-rounded" style="font-size:16px;">visibility</span>
                Vorschau-Modus (Team) — so sieht der Kunde seinen Bereich. Read-only.
            </div>
        <?php endif; ?>
        <div class="portal-wrap">
            <?= $content ?>
        </div>
    </main>
</body>
</html>
