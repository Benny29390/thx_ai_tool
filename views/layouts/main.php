<?php
// Branding-Settings laden (Logo + App-Name)
$appLogo = null;
$appName = APP_NAME;
try {
    $db = \Core\Database::getInstance();
    $brandRows = $db->query(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_logo', 'app_name')"
    );
    foreach ($brandRows as $row) {
        if ($row['setting_key'] === 'app_logo' && !empty($row['setting_value'])) {
            $appLogo = $row['setting_value'];
        }
        if ($row['setting_key'] === 'app_name' && !empty($row['setting_value'])) {
            $appName = $row['setting_value'];
        }
    }
} catch (\Exception $e) {
    // Ignorieren wenn Settings noch nicht existieren
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
    <title><?= htmlspecialchars($title ?? $appName) ?></title>
    <!-- Schrift-Skalierung: vor allem rendern ausfuehren, damit es nicht flackert -->
    <script>
    (function(){
        try {
            var step = parseInt(localStorage.getItem('thx_fontsize_step') || '0', 10);
            if (isNaN(step) || step < -2 || step > 2) step = 0;
            // Basis: 120% (siehe CLAUDE.md). Pro Stufe +/- 10% relativ.
            var pct = 120 * Math.pow(1.1, step);
            document.documentElement.style.fontSize = pct.toFixed(2) + '%';
            window.__thxFontStep = step;
            // Density-Profil: compact / comfortable (default) / spacious
            var density = localStorage.getItem('thx_density') || 'comfortable';
            if (['compact','comfortable','spacious'].indexOf(density) === -1) density = 'comfortable';
            document.documentElement.setAttribute('data-density', density);
            window.__thxDensity = density;
        } catch (e) {}
    })();
    </script>
    <link rel="preload" href="/assets/fonts/material-symbols-rounded.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/styleguide/frutiger-45-light.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/styleguide/frutiger-65-bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/material-symbols.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="/assets/css/thx-tokens.css?v=<?= filemtime(ROOT_PATH . '/assets/css/thx-tokens.css') ?>">
    <link rel="stylesheet" href="/assets/css/lam.css?v=<?= filemtime(ROOT_PATH . '/assets/css/lam.css') ?>">
    <link rel="stylesheet" href="/assets/css/thx-components.css?v=<?= filemtime(ROOT_PATH . '/assets/css/thx-components.css') ?>">
    <style>
        .nav-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        .tile-icon .material-symbols-rounded {
            font-size: 32px;
        }
        .tile-large .tile-icon .material-symbols-rounded {
            font-size: 40px;
        }

        /* Collapsed sidebar — Toggle ist der Hamburger oben links in der Topbar (thx-topbar-toggle).
           State persistiert in localStorage 'thx_sidebar_collapsed', wird vor dem ersten Paint angewendet. */
        .app-container.sidebar-collapsed .sidebar {
            width: 60px;
        }
        .app-container.sidebar-collapsed .main-content {
            margin-left: 60px;
        }
        .app-container.sidebar-collapsed .sidebar .sidebar-header {
            padding: 14px 8px;
            min-height: 64px;
            justify-content: center;
        }
        .app-container.sidebar-collapsed .sidebar .logo-full {
            display: none;
        }
        .app-container.sidebar-collapsed .sidebar .logo-icon {
            display: inline-flex;
        }
        .app-container.sidebar-collapsed .sidebar .nav-item {
            justify-content: center;
            padding: 10px 8px;
            font-size: 0;
            gap: 0;
        }
        .app-container.sidebar-collapsed .sidebar .nav-item .nav-icon {
            font-size: 22px;
        }
        .app-container.sidebar-collapsed .sidebar .nav-label,
        .app-container.sidebar-collapsed .sidebar .nav-divider,
        .app-container.sidebar-collapsed .sidebar .nav-group .nav-group-items,
        .app-container.sidebar-collapsed .sidebar .nav-group .nav-group-arrow,
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .customer-switcher,
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .customer-info,
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .user-name,
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .user-role,
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .logout-btn,
        .app-container.sidebar-collapsed .sidebar .nav-badge {
            display: none;
        }
        .app-container.sidebar-collapsed .sidebar .nav-group-header {
            justify-content: center;
            padding: 10px 8px;
            font-size: 0;
            gap: 0;
        }
        .app-container.sidebar-collapsed .sidebar .nav-group-header .nav-icon {
            font-size: 22px;
        }
        .app-container.sidebar-collapsed .sidebar .sidebar-footer {
            padding: 8px;
        }
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .user-actions {
            justify-content: center;
        }
        .app-container.sidebar-collapsed .sidebar .sidebar-footer .user-info {
            display: none;
        }

        /* Smooth transition */
        .sidebar {
            transition: width 0.25s ease;
        }
        .main-content {
            transition: margin-left 0.25s ease;
        }
    </style>

    <!-- Globales Spaltenbreiten-Tool — vor Body geladen, damit Inline-Scripts in Views drauf zugreifen koennen -->
    <script src="/assets/js/thx-col-resize.js?v=<?= @filemtime(ROOT_PATH . '/assets/js/thx-col-resize.js') ?: time() ?>"></script>
</head>
<body class="<?= !empty($embed) ? 'thx-embed' : '' ?><?= !empty($bodyClass) ? ' ' . htmlspecialchars($bodyClass) : '' ?>">
    <?php if (!empty($embed)): ?>
    <style>
        body.thx-embed .thx-topbar,
        body.thx-embed .sidebar,
        body.thx-embed #feedback-widget,
        body.thx-embed .thx-page-header { display: none !important; }
        body.thx-embed { --topbar-h: 0px; padding-top: 0 !important; }
        body.thx-embed .app-container { display: block; }
        body.thx-embed .main-content { margin: 0; padding: 16px; max-width: none; height: 100vh; overflow: auto; box-sizing: border-box; }
        body.thx-embed .kb-back { display: none !important; }
    </style>
    <?php endif; ?>
    <?php
    $currentUser = \Core\Auth::user();
    $userName = $currentUser['name'] ?? 'Gast';
    // Anzeige-Kuerzel:
    //   1. wenn der Admin ein explizites Kuerzel vergeben hat (users.abbreviation) → das
    //   2. sonst Initialen aus dem Namen (z.B. "Thomas Kilian" → "TK")
    $explicitAbbr = trim((string)($currentUser['abbreviation'] ?? ''));
    if ($explicitAbbr !== '') {
        $initials = mb_strtoupper($explicitAbbr);
    } else {
        $parts = preg_split('/\s+/', trim($userName));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = (count($parts) > 1) ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0] ?? '', 1, 1);
        $initials = mb_strtoupper($first . $last);
    }
    ?>
    <?php
    $isSimulating = \Core\Auth::isSimulating();
    $userRole = \Core\Auth::role();
    $roleLabels = ['admin' => 'Admin', 'manager' => 'Manager', 'user' => 'User', 'guest' => 'Guest'];
    $roleLabel = $roleLabels[$userRole] ?? ucfirst((string)$userRole);
    $needs2FA = \Core\Auth::requires2FASetup();
    $on2FAPage = strpos($_SERVER['REQUEST_URI'] ?? '', '/settings/security') === 0;
    ?>
    <?php if ($needs2FA && !$on2FAPage): ?>
        <div class="thx-2fa-warning-banner" role="alert">
            <span class="material-symbols-rounded" style="font-size:18px;">shield</span>
            <span><strong>2FA noch nicht eingerichtet.</strong> Als <?= htmlspecialchars($roleLabel) ?> bist Du verpflichtet, Deinen Account mit 2FA zu schützen.</span>
            <a href="/settings/security" class="thx-2fa-warning-cta">Jetzt einrichten</a>
        </div>
    <?php endif; ?>
    <header class="thx-topbar" aria-label="Globale Navigation">
        <button class="thx-topbar-toggle" type="button" onclick="thxToggleSidebar()" aria-label="Sidebar ein-/ausklappen" title="Sidebar ein-/ausklappen">
            <span class="material-symbols-rounded">menu</span>
        </button>
        <?php if ($isSimulating): ?>
            <button type="button"
                    onclick="thxSwitchBack()"
                    class="thx-topbar-sim-banner"
                    title="Zurueck zur echten Admin-Sicht">
                <span class="material-symbols-rounded" style="font-size:18px;">visibility</span>
                <span>Sicht: <strong><?= htmlspecialchars($userName) ?></strong> · <?= htmlspecialchars($roleLabel) ?> · <span style="text-decoration:underline;">zurück zur Admin-Sicht</span></span>
            </button>
        <?php endif; ?>
        <div class="thx-topbar-spacer"></div>
        <div class="thx-topbar-links">
            <!-- Schrift-Skalierung (Barrierefreiheit) -->
            <div class="thx-fontsize" role="group" aria-label="Schriftgröße">
                <button type="button" class="thx-fontsize-btn" onclick="thxFontStep(-1)" aria-label="Schrift verkleinern" title="Schrift verkleinern">
                    <span style="font-size:0.85em;">A−</span>
                </button>
                <button type="button" class="thx-fontsize-btn" onclick="thxFontStep(0)" aria-label="Standardgröße" title="Standardgröße">
                    <span style="font-size:0.95em;">A</span>
                </button>
                <button type="button" class="thx-fontsize-btn" onclick="thxFontStep(1)" aria-label="Schrift vergrößern" title="Schrift vergrößern">
                    <span style="font-size:1.05em;">A+</span>
                </button>
                <span class="thx-fontsize-indicator" id="thx-fontsize-indicator" title="Aktuelle Stufe">0</span>
            </div>
            <span class="thx-topbar-divider"></span>

            <?php if (\Core\Auth::isAdmin()): ?>
                <a href="/admin/customers" class="thx-topbar-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/customers') === 0 ? 'is-active' : '' ?>">Kunden</a>
                <a href="/admin/users"     class="thx-topbar-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/users')     === 0 ? 'is-active' : '' ?>">Benutzer</a>
                <a href="/admin/settings"  class="thx-topbar-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/settings')  === 0 ? 'is-active' : '' ?>">Einstellungen</a>
                <span class="thx-topbar-divider"></span>
            <?php endif; ?>
            <a href="<?= \Core\Auth::isCustomer() ? '/portal/dashboard' : '/settings/security' ?>" class="thx-topbar-user" title="Mein Konto">
                <span class="thx-topbar-initials"><?= htmlspecialchars($initials) ?></span>
                <span class="thx-topbar-username"><?= htmlspecialchars($userName) ?></span>
                <span class="thx-topbar-role-badge role-<?= htmlspecialchars($userRole ?? '') ?>"><?= htmlspecialchars($roleLabel) ?></span>
            </a>
            <a href="/logout" class="thx-topbar-link" title="Abmelden">Abmelden</a>
        </div>
    </header>
    <script>
    // Schrift-Skalierung: 5 Stufen (-2 bis +2), je 10% pro Stufe relativ zur Basis (120%).
    function thxFontStep(delta) {
        var cur = parseInt(window.__thxFontStep || 0, 10);
        var neu;
        if (delta === 0) {
            neu = 0; // expliziter Klick auf "A" = Standard
        } else {
            neu = Math.max(-2, Math.min(2, cur + delta));
            if (neu === cur) return;
        }
        try { localStorage.setItem('thx_fontsize_step', String(neu)); } catch (e) {}
        var pct = 120 * Math.pow(1.1, neu);
        document.documentElement.style.fontSize = pct.toFixed(2) + '%';
        window.__thxFontStep = neu;
        thxFontUpdateIndicator(neu);
    }
    function thxFontUpdateIndicator(step) {
        var el = document.getElementById('thx-fontsize-indicator');
        if (!el) return;
        el.textContent = step === 0 ? '0' : (step > 0 ? '+' + step : String(step));
        el.classList.toggle('is-default', step === 0);
    }
    // Indikator beim Laden setzen
    document.addEventListener('DOMContentLoaded', function(){
        thxFontUpdateIndicator(parseInt(window.__thxFontStep || 0, 10));
    });

    async function thxSwitchBack() {
        try {
            const r = await App.post('/auth/switch-back', {});
            if (r.success) { location.href = '/'; }
            else App.showNotification(r.message || 'Wechsel fehlgeschlagen', 'error');
        } catch (e) {
            App.showNotification(e.message || 'Wechsel fehlgeschlagen', 'error');
        }
    }
    </script>
    <script>document.body.classList.add('has-thx-topbar');</script>
    <div class="app-container">
    <script>
    // Sidebar-Collapsed-State direkt am app-container setzen, BEVOR der Browser
    // sie zeichnet - verhindert Flackern.
    (function() {
        try {
            if (localStorage.getItem('thx_sidebar_collapsed') === '1') {
                document.currentScript.parentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    })();
    </script>
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <?php if ($appLogo): ?>
                    <a href="/" class="logo logo-custom logo-full"><?= $appLogo ?></a>
                <?php else: ?>
                    <a href="/" class="logo logo-full"><?= htmlspecialchars($appName) ?></a>
                <?php endif; ?>
                <a href="/" class="logo logo-icon" title="Thoxan">
                    <img src="/assets/images/thoxan-x.svg" alt="Thoxan">
                </a>
            </div>

            <?php $currentUri = $_SERVER['REQUEST_URI'] ?? ''; ?>
            <nav class="sidebar-nav">
                <?php if (\Core\Auth::isCustomer()): ?>
                <!-- Kundenportal: reduziertes Menue -->
                <a href="/portal/dashboard" class="nav-item <?= ($currentUri === '/portal' || $currentUri === '/portal/dashboard') ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">dashboard</span>
                    Übersicht
                </a>
                <a href="/portal/chat" class="nav-item <?= strpos($currentUri, '/portal/chat') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">forum</span>
                    Nachrichten
                </a>
                <?php else: ?>
                <!-- Hauptbereich -->
                <a href="/" class="nav-item <?= $currentUri === '/' ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">dashboard</span>
                    Dashboard
                </a>
                <?php if (\Core\Auth::can(CAP_CUSTOMERS_VIEW)): ?>
                <a href="/admin/customers" class="nav-item <?= strpos($currentUri, '/admin/customers') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">business</span>
                    Kunden
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_CHAT)): ?>
                <a href="/chat" class="nav-item <?= $currentUri === '/chat' ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">chat</span>
                    Chat
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_KNOWLEDGE) || \Core\Auth::can(CAP_TRANSCRIPTION)): ?>
                <?php
                    // Sidebar-Eintrag: ein flacher Link „Wissen". Innerhalb der Seite
                    // entscheiden Tabs zwischen Wissensdatenbank und Transkripten.
                    // Default-Ziel: Wissensdatenbank, oder Transkripte wenn der User
                    // nur CAP_TRANSCRIPTION hat.
                    $wissenHref = \Core\Auth::can(CAP_KNOWLEDGE) ? '/wissen' : '/admin/transkription';
                    $wissenActive = strpos($currentUri, '/wissen') === 0 || strpos($currentUri, '/admin/transkription') === 0;
                ?>
                <a href="<?= $wissenHref ?>" class="nav-item <?= $wissenActive ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">library_books</span>
                    Wissen
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::hasRole(ROLE_USER)): ?>
                <a href="/canvas" class="nav-item <?= strpos($currentUri, '/canvas') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">explore</span>
                    KI Kompass
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::hasRole(ROLE_MANAGER)): ?>
                <a href="/rules" class="nav-item <?= strpos($currentUri, '/rules') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">rule</span>
                    Regeln
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_PROJEKTPLANNER)): ?>
                <a href="/admin/projektplanner" class="nav-item <?= strpos($currentUri, '/admin/projektplanner') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">view_kanban</span>
                    Projektplanner
                </a>
                <?php endif; ?>
                <a href="/tagesplan" class="nav-item <?= strpos($currentUri, '/tagesplan') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">today</span>
                    Tagesplan
                </a>
                <?php if (\Core\Auth::can(CAP_SITE_MONITOR)): ?>
                <a href="/admin/site-monitor" class="nav-item <?= strpos($currentUri, '/admin/site-monitor') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">monitoring</span>
                    Website-Monitor
                </a>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_LAM)): ?>
                <a href="/lam"
                   id="nav-lam-link"
                   class="nav-item <?= strpos($currentUri, '/lam') === 0 ? 'active' : '' ?>"
                   onclick="thxLamSidebarClick(event)">
                    <span class="nav-icon material-symbols-rounded">hub</span>
                    LAM-System
                </a>
                <script>
                // Klick auf "LAM-System" geht zur zuletzt besuchten LAM-Seite,
                // Fallback /lam (Dashboard). Mit Strg/Cmd/Mittelklick neuer Tab,
                // dann ohne Umleitung — Browser-Standard erhalten.
                function thxLamSidebarClick(event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;
                    try {
                        var last = localStorage.getItem('thx_lam_last_path');
                        if (last && last.indexOf('/lam') === 0 && last !== window.location.pathname) {
                            event.preventDefault();
                            window.location.href = last;
                        }
                    } catch (e) { /* ohne localStorage einfach default-Link */ }
                }
                </script>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_CRM)): ?>
                <a href="/crm"
                   id="nav-crm-link"
                   class="nav-item <?= strpos($currentUri, '/crm') === 0 ? 'active' : '' ?>"
                   onclick="thxCrmSidebarClick(event)">
                    <span class="nav-icon material-symbols-rounded">contacts</span>
                    Kontakte (CRM)
                </a>
                <script>
                function thxCrmSidebarClick(event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;
                    try {
                        var last = localStorage.getItem('thx_crm_last_path');
                        if (last && last.indexOf('/crm') === 0 && last !== window.location.pathname) {
                            event.preventDefault();
                            window.location.href = last;
                        }
                    } catch (e) { /* ohne localStorage einfach default-Link */ }
                }
                </script>
                <?php endif; ?>
                <?php if (\Core\Auth::can(CAP_MAIL)): ?>
                <a href="/mail" class="nav-item <?= strpos($currentUri, '/mail') === 0 ? 'active' : '' ?>" style="position:relative;">
                    <span class="nav-icon material-symbols-rounded">mail</span>
                    <span>Mail</span>
                    <span id="nav-mail-badge" style="display:none;margin-left:auto;background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:600;line-height:1.4;"></span>
                </a>
                <script>
                (function() {
                    function refreshMailBadge() {
                        fetch('/api/v1/mail/ungelesen-zaehler', { credentials: 'same-origin' })
                            .then(r => r.ok ? r.json() : null)
                            .then(j => {
                                if (!j || !j.success) return;
                                const badge = document.getElementById('nav-mail-badge');
                                if (!badge) return;
                                const n = parseInt(j.data?.ungelesen || 0);
                                if (n > 0) { badge.textContent = n; badge.style.display = 'inline-block'; }
                                else { badge.style.display = 'none'; }
                            })
                            .catch(() => {});
                    }
                    refreshMailBadge();
                    setInterval(refreshMailBadge, 60000);
                })();
                </script>
                <?php endif; ?>

                <?php if (\Core\Auth::can(CAP_STYLEGUIDE)): ?>
                <a href="/admin/styleguide" class="nav-item <?= strpos($currentUri, '/admin/styleguide') === 0 ? 'active' : '' ?>">
                    <span class="nav-icon material-symbols-rounded">palette</span>
                    Styleguide
                </a>
                <?php endif; ?>

                <?php if (\Core\Auth::isAdmin()): ?>
                    <div class="nav-divider"></div>
                    <span class="nav-label">Administration</span>

                    <!-- Benutzer + Rollen-Defaults (Tabs auf derselben Seite) -->
                    <a href="/admin/users" class="nav-item <?= strpos($currentUri, '/admin/users') === 0 ? 'active' : '' ?>">
                        <span class="nav-icon material-symbols-rounded">group</span>
                        Benutzer
                    </a>

                    <!-- KI & Modelle -->
                    <div class="nav-group" data-group="ai-models">
                        <button class="nav-group-header" onclick="toggleNavGroup('ai-models')">
                            <span class="nav-icon material-symbols-rounded">smart_toy</span>
                            KI & Modelle
                            <span class="nav-group-arrow material-symbols-rounded">expand_more</span>
                        </button>
                        <div class="nav-group-items">
                            <a href="/admin/models" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/models') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">smart_toy</span>
                                KI-Modelle
                            </a>
                            <a href="/admin/usage" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/usage') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">monitoring</span>
                                Verbrauch
                            </a>
                            <a href="/admin/llm-performance" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/llm-performance') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">speed</span>
                                Modell-Performance
                            </a>
                            <a href="/admin/wissen-status" class="nav-item nav-sub-item <?= (strpos($currentUri, '/admin/wissen-status') === 0 || strpos($currentUri, '/admin/wissen-v2') === 0) ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">hub</span>
                                Wissens-Status
                            </a>
                            <?php if (\Core\Auth::can(CAP_PROMPT_INSIGHTS)): ?>
                            <a href="/admin/prompt-insights" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/prompt-insights') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">psychology</span>
                                Prompt-Insights
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                        // Zaehler fuer die Hauptmenuepunkte Feedback + Maßnahmen
                        $newFeedbackCount = 0;
                        $openMeasureCount = 0;
                        try {
                            $newFeedbackCount = (int) \Core\Database::getInstance()
                                ->queryValue("SELECT COUNT(*) FROM internal_feedback WHERE status = 'new'");
                            $openMeasureCount = (int) \Core\Database::getInstance()
                                ->queryValue("SELECT COUNT(*) FROM feedback_measures WHERE status IN ('offen','in_arbeit')");
                        } catch (\Exception $e) {}
                    ?>
                    <!-- Feedback-Cockpit (enthaelt jetzt auch die Maßnahmen) -->
                    <a href="/admin/feedback" class="nav-item <?= (strpos($currentUri, '/admin/feedback') === 0 || strpos($currentUri, '/admin/measures') === 0) ? 'active' : '' ?>" style="position:relative;">
                        <span class="nav-icon material-symbols-rounded">feedback</span>
                        <span>Feedback</span>
                        <?php if ($newFeedbackCount > 0): ?>
                            <span style="margin-left:auto;background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:600;line-height:1.4;"><?= $newFeedbackCount ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- System -->
                    <div class="nav-group" data-group="system">
                        <button class="nav-group-header" onclick="toggleNavGroup('system')">
                            <span class="nav-icon material-symbols-rounded">settings</span>
                            System
                            <span class="nav-group-arrow material-symbols-rounded">expand_more</span>
                        </button>
                        <div class="nav-group-items">
                            <a href="/admin/settings" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/settings') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">settings</span>
                                Einstellungen
                            </a>
                            <a href="/admin/backups" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/backups') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">backup</span>
                                Backups
                            </a>
                            <a href="/admin/system-log" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/system-log') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">monitor_heart</span>
                                System-Log
                            </a>
                            <?php if (\Core\Auth::can(CAP_FIREWALL)): ?>
                            <a href="/admin/firewall" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/firewall') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">security</span>
                                Firewall / IP-Sperren
                            </a>
                            <?php endif; ?>
                            <a href="/admin/jobs" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/jobs') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">pending_actions</span>
                                Jobs
                                <?php
                                try {
                                    $jobsDb = \Core\Database::getInstance();
                                    $pendingJobsCount = $jobsDb->queryValue("SELECT COUNT(*) FROM generation_jobs WHERE status IN ('pending', 'processing')");
                                    if ($pendingJobsCount > 0): ?>
                                        <span class="nav-badge" style="background: #f59e0b;"><?= $pendingJobsCount ?></span>
                                    <?php endif;
                                } catch (\Exception $e) {}
                                ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (\Core\Auth::isAdmin()): ?>
                    <!-- Deprecated — alte Module die wir nicht mehr aktiv pflegen, nur fuer Admins sichtbar -->
                    <div class="nav-group nav-group-deprecated collapsed" data-group="deprecated">
                        <button class="nav-group-header" onclick="toggleNavGroup('deprecated')">
                            <span class="nav-icon material-symbols-rounded">archive</span>
                            Deprecated
                            <span class="nav-group-arrow material-symbols-rounded">expand_more</span>
                        </button>
                        <div class="nav-group-items">
                            <?php if (\Core\Auth::isAdmin()): ?>
                            <a href="/admin/coworker" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/coworker') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">terminal</span>
                                KI-Coworker
                            </a>
                            <a href="/admin/tasks" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/tasks') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">task_alt</span>
                                Auftrags-Modus
                            </a>
                            <?php endif; ?>
                            <a href="/rules" class="nav-item nav-sub-item <?= strpos($currentUri, '/rules') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">rule</span>
                                Regeln
                            </a>
                            <?php if (\Core\Auth::isAdmin()): ?>
                            <a href="/admin/suggestions" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/suggestions') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">lightbulb</span>
                                Regelvorschläge
                            </a>
                            <a href="/admin/rule-types" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/rule-types') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">category</span>
                                Regel-Typen
                            </a>
                            <a href="/admin/rule-categories" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/rule-categories') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">folder_special</span>
                                Regel-Kategorien
                            </a>
                            <a href="/admin/styles" class="nav-item nav-sub-item <?= strpos($currentUri, '/admin/styles') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">palette</span>
                                Stile
                            </a>
                            <?php endif; ?>
                            <a href="/orders" class="nav-item nav-sub-item <?= strpos($currentUri, '/orders') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">assignment</span>
                                Aufträge
                            </a>
                            <a href="/projects" class="nav-item nav-sub-item <?= strpos($currentUri, '/projects') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">edit_document</span>
                                Projekte
                            </a>
                            <a href="/contexts" class="nav-item nav-sub-item <?= strpos($currentUri, '/contexts') === 0 ? 'active' : '' ?>">
                                <span class="nav-icon material-symbols-rounded">dataset</span>
                                Kontexte
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <?php if (\Core\Auth::hasMultipleCustomers()): ?>
                    <div class="customer-switcher">
                        <label for="customer-select">Aktiver Kunde:</label>
                        <select id="customer-select" onchange="switchCustomer(this.value)">
                            <?php
                                $sidebarCustomers = \Core\Auth::customers();
                                // Alphabetisch nach Name (deutsche Sortierung inkl. Umlaute)
                                if (class_exists('Collator')) {
                                    $sbCol = new \Collator('de_DE');
                                    usort($sidebarCustomers, fn($a, $b) => $sbCol->compare($a['name'] ?? '', $b['name'] ?? ''));
                                } else {
                                    usort($sidebarCustomers, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
                                }
                            ?>
                            <?php foreach ($sidebarCustomers as $customer): ?>
                                <option value="<?= $customer['id'] ?>"
                                    <?= (int)$customer['id'] === \Core\Auth::customerId() ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif (\Core\Auth::activeCustomer()): ?>
                    <div class="customer-info">
                        <span class="customer-label">Kunde:</span>
                        <span class="customer-name"><?= htmlspecialchars(\Core\Auth::activeCustomer()['name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($currentUser['role'] ?? '')) ?></span>
                </div>
                <div class="user-actions">
                    <?php if (!\Core\Auth::isCustomer()): ?>
                    <a href="/settings/security" class="user-action-link" title="Mein Konto">
                        <span class="material-symbols-rounded">account_circle</span>
                    </a>
                    <button type="button" class="user-action-link" title="Feedback senden" data-feedback-trigger style="background:none;border:none;cursor:pointer;padding:0;">
                        <span class="material-symbols-rounded">rate_review</span>
                    </button>
                    <?php endif; ?>
                    <a href="/logout" class="logout-btn">Abmelden</a>
                </div>
            </div>
        </aside>
        <script>
        // Submenue-Stand SOFORT setzen, bevor der Browser die Sidebar painted.
        // Verhindert das kurze Aufklappen-Zuklappen-Flackern beim Seitenaufruf.
        (function() {
            var collapsed = {};
            try { collapsed = JSON.parse(localStorage.getItem('navCollapsed') || '{}'); } catch (e) {}
            document.querySelectorAll('.nav-group').forEach(function(group) {
                var groupId = group.dataset.group;
                var hasActiveItem = group.querySelector('.nav-item.active');
                if (hasActiveItem) {
                    group.classList.remove('collapsed');
                    collapsed[groupId] = false;
                } else if (collapsed[groupId] === true) {
                    group.classList.add('collapsed');
                }
            });
            try { localStorage.setItem('navCollapsed', JSON.stringify(collapsed)); } catch (e) {}
        })();
        </script>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (!empty($flashMessages)): ?>
                <div class="flash-messages">
                    <?php foreach ($flashMessages as $type => $messages): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?= htmlspecialchars($type) ?>">
                                <?= htmlspecialchars($message) ?>
                                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Globales Context-Menu — VOR $content registriert, damit View-Init-Scripts darauf zugreifen koennen -->
            <div class="thx-cm" id="thx-cm" onclick="event.stopPropagation()"></div>
            <script>
            window.ThxContextMenu = (function() {
                const el = () => document.getElementById('thx-cm');
                function close() { const m = el(); if (m) { m.classList.remove('open'); m.innerHTML = ''; } }
                function escape(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
                function render(items) {
                    return items.map((it, idx) => {
                        if (it.divider) return '<div class="thx-cm-divider"></div>';
                        if (it.header) return `<div class="thx-cm-header">${escape(it.header)}</div>`;
                        const cls = ['thx-cm-item'];
                        if (it.disabled) cls.push('is-disabled');
                        if (it.danger) cls.push('is-danger');
                        const icon = it.icon ? `<span class="material-symbols-rounded">${escape(it.icon)}</span>` : '<span style="width:18px;"></span>';
                        return `<div class="${cls.join(' ')}" data-idx="${idx}">${icon}<span>${escape(it.label)}</span></div>`;
                    }).join('');
                }
                function show(e, items) {
                    if (!items || items.length === 0) return;
                    e.preventDefault(); e.stopPropagation();
                    const m = el(); if (!m) return;
                    m.innerHTML = render(items);
                    m.classList.add('open');
                    m.querySelectorAll('.thx-cm-item').forEach(node => {
                        const idx = parseInt(node.dataset.idx, 10);
                        const item = items[idx];
                        if (item.disabled) return;
                        node.addEventListener('click', (ev) => {
                            ev.stopPropagation(); close();
                            try { item.onClick?.(ev); } catch (err) { console.error(err); }
                        });
                    });
                    const x = e.clientX, y = e.clientY;
                    m.style.left = '0px'; m.style.top = '0px';
                    const r = m.getBoundingClientRect();
                    m.style.left = Math.min(x, window.innerWidth - r.width - 8) + 'px';
                    m.style.top = Math.min(y, window.innerHeight - r.height - 8) + 'px';
                }
                function bind(selectorOrEl, builder) {
                    const handle = (e) => {
                        const t = typeof selectorOrEl === 'string' ? e.target.closest(selectorOrEl) : selectorOrEl;
                        if (!t) return;
                        if (typeof selectorOrEl === 'string' && !document.contains(t)) return;
                        const items = builder(e, t);
                        show(e, items || []);
                    };
                    if (typeof selectorOrEl === 'string') document.addEventListener('contextmenu', handle);
                    else selectorOrEl.addEventListener('contextmenu', handle);
                }
                document.addEventListener('click', close);
                document.addEventListener('scroll', close, true);
                window.addEventListener('resize', close);
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
                return { show, close, bind };
            })();
            </script>

            <?= $content ?>
        </main>
    </div>

    <!-- Feedback Widget — Trigger sitzt jetzt in der Sidebar (.user-actions),
         Panel ist ein zentriertes Modal mit Backdrop -->
    <div id="feedback-widget" class="feedback-widget">
        <div class="feedback-backdrop" onclick="toggleFeedbackPanel()"></div>
        <div class="feedback-panel" id="feedback-panel">
            <div class="feedback-panel-header">
                <h3>Feedback senden</h3>
                <button class="feedback-panel-close" onclick="toggleFeedbackPanel()">&times;</button>
            </div>
            <div class="feedback-panel-body">
                <div class="feedback-form-group">
                    <label>Art des Feedbacks</label>
                    <select id="fb-type">
                        <option value="bug" selected>Bug / Fehler</option>
                        <option value="feature">Feature-Wunsch</option>
                        <option value="improvement">Verbesserung</option>
                        <option value="other">Sonstiges</option>
                    </select>
                </div>

                <div class="feedback-form-group">
                    <label>Titel *</label>
                    <input type="text" id="fb-title" placeholder="Kurz: worum geht es?">
                </div>

                <div class="feedback-form-group">
                    <label>Beschreibung (optional)</label>
                    <textarea id="fb-description" rows="4" placeholder="Details, falls nötig …"></textarea>
                </div>

                <div class="feedback-form-group">
                    <label>Anhang (optional)</label>
                    <div class="feedback-media-buttons">
                        <button type="button" class="feedback-media-btn" onclick="captureScreenshot()" id="btn-screenshot">
                            <span class="material-symbols-rounded">screenshot_monitor</span>
                            Screenshot
                        </button>
                        <button type="button" class="feedback-media-btn" onclick="startVideoRecording()" id="btn-video">
                            <span class="material-symbols-rounded">videocam</span>
                            Video
                        </button>
                    </div>
                    <div id="fb-media-preview" class="feedback-media-preview"></div>
                </div>

                <button class="btn btn-primary btn-block" onclick="submitFeedback()" id="fb-submit">
                    Feedback senden
                </button>
            </div>
        </div>
    </div>

    <!-- Screenshot-Annotator (Markieren mit Stift/Rechteck/Pfeil + Farben) -->
    <div id="fb-annot" class="fb-annot" style="display:none;">
        <div class="fb-annot-box">
            <div class="fb-annot-toolbar">
                <span class="fb-annot-title">Screenshot markieren</span>
                <div class="fb-annot-group">
                    <button type="button" class="fb-annot-tool is-active" data-tool="arrow" title="Pfeil"><span class="material-symbols-rounded">north_east</span></button>
                    <button type="button" class="fb-annot-tool" data-tool="rect" title="Rechteck"><span class="material-symbols-rounded">crop_square</span></button>
                    <button type="button" class="fb-annot-tool" data-tool="pen" title="Stift"><span class="material-symbols-rounded">edit</span></button>
                </div>
                <div class="fb-annot-group">
                    <button type="button" class="fb-annot-color is-active" data-color="#e11d48" style="background:#e11d48" title="Rot"></button>
                    <button type="button" class="fb-annot-color" data-color="#f59e0b" style="background:#f59e0b" title="Gelb"></button>
                    <button type="button" class="fb-annot-color" data-color="#2563eb" style="background:#2563eb" title="Blau"></button>
                </div>
                <div class="fb-annot-group fb-annot-actions">
                    <button type="button" class="fb-annot-btn" id="fb-annot-undo" title="Rückgängig"><span class="material-symbols-rounded">undo</span></button>
                    <button type="button" class="fb-annot-btn" onclick="cancelAnnotator()">Abbrechen</button>
                    <button type="button" class="fb-annot-btn primary" onclick="finishAnnotator()">Fertig</button>
                </div>
            </div>
            <div class="fb-annot-stage"><canvas id="fb-annot-canvas"></canvas></div>
        </div>
    </div>

    <style>
    /* Widget ist Modal — Trigger sitzt jetzt in der Sidebar (.user-actions) */
    .feedback-widget {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .feedback-widget.is-open { display: flex; }
    .feedback-backdrop {
        position: absolute; inset: 0;
        background: rgba(15, 23, 42, 0.5);
    }
    .feedback-panel {
        position: relative;
        width: 420px; max-width: calc(100vw - 32px);
        background: white;
        border-radius: 12px;
        box-shadow: 0 12px 48px rgba(0,0,0,0.25);
        overflow: hidden;
        animation: feedbackFadeIn 0.15s ease;
    }
    @keyframes feedbackFadeIn {
        from { opacity: 0; transform: translateY(8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .feedback-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid var(--color-gray-200);
    }
    .feedback-panel-header h3 {
        margin: 0;
        font-size: var(--d-fs-base);
    }
    .feedback-panel-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--color-gray-400);
        line-height: 1;
    }
    .feedback-panel-body {
        padding: 16px;
    }
    .feedback-form-group {
        margin-bottom: 16px;
    }
    .feedback-form-group label {
        display: block;
        font-size: var(--d-fs-xs);
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--color-gray-600);
    }
    .feedback-form-group select,
    .feedback-form-group input[type="text"],
    .feedback-form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--color-gray-300);
        border-radius: 6px;
        font-size: var(--d-fs-sm);
        box-sizing: border-box;
        font-family: var(--font-family);
    }
    .feedback-form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    .feedback-media-buttons {
        display: flex;
        gap: 8px;
    }
    .feedback-media-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px;
        background: var(--color-gray-100);
        border: 1px solid var(--color-gray-200);
        border-radius: 6px;
        cursor: pointer;
        font-size: var(--d-fs-sm);
        transition: background 0.2s;
    }
    .feedback-media-btn:hover {
        background: var(--color-gray-200);
    }
    .feedback-media-btn.recording {
        background: #fef2f2;
        border-color: #ef4444;
        color: #ef4444;
    }
    .feedback-media-btn .material-symbols-rounded {
        font-size: 18px;
    }
    .feedback-media-preview {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .fb-media-item {
        position: relative;
        border: 1px solid var(--color-gray-200);
        border-radius: 8px;
        overflow: hidden;
        background: #000;
    }
    .fb-media-item img,
    .fb-media-item video {
        max-width: 160px;
        max-height: 120px;
        display: block;
    }
    .fb-media-item .remove-media {
        position: absolute;
        top: 4px; right: 4px;
        width: 22px; height: 22px;
        border-radius: 50%;
        background: rgba(15,23,42,.65);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; line-height: 1;
        cursor: pointer;
    }
    .fb-media-item .remove-media:hover { background: #dc2626; }

    /* Annotator — grosses, zentriertes Modal (Feedback-Kontext bleibt dahinter sichtbar) */
    .fb-annot {
        position: fixed; inset: 0; z-index: 10000;
        background: rgba(15,23,42,.6);
        display: flex; align-items: center; justify-content: center;
        padding: 24px;
    }
    .fb-annot-box {
        background: #fff; border-radius: 14px; overflow: hidden;
        display: flex; flex-direction: column;
        max-width: 92vw; max-height: 90vh;
        box-shadow: 0 24px 70px rgba(0,0,0,.45);
    }
    .fb-annot-title { font-weight: 700; font-size: var(--d-fs-sm); color: var(--color-gray-700); margin-right: 4px; }
    .fb-annot-toolbar {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        padding: 10px 16px; background: #fff; border-bottom: 1px solid var(--color-gray-200);
    }
    .fb-annot-group { display: flex; align-items: center; gap: 6px; }
    .fb-annot-actions { margin-left: auto; }
    .fb-annot-tool {
        width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--color-gray-200);
        background: #fff; cursor: pointer; color: var(--color-gray-600);
        display: inline-flex; align-items: center; justify-content: center;
    }
    .fb-annot-tool:hover { background: var(--color-gray-100); }
    .fb-annot-tool.is-active { background: #eef2ff; border-color: #6366f1; color: #4338ca; }
    .fb-annot-tool .material-symbols-rounded { font-size: 20px; }
    .fb-annot-color {
        width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff;
        box-shadow: 0 0 0 1px var(--color-gray-300); cursor: pointer; padding: 0;
    }
    .fb-annot-color.is-active { box-shadow: 0 0 0 2px #0f172a; }
    .fb-annot-btn {
        border: 1px solid var(--color-gray-200); background: #fff; color: var(--color-gray-700);
        border-radius: 8px; padding: 7px 14px; cursor: pointer; font-weight: 600;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .fb-annot-btn:hover { background: var(--color-gray-100); }
    .fb-annot-btn.primary { background: var(--blue, #005da8); color: #fff; border-color: var(--blue, #005da8); }
    .fb-annot-btn.primary:hover { background: #004a86; }
    .fb-annot-btn .material-symbols-rounded { font-size: 18px; }
    .fb-annot-stage { flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--color-gray-50); min-height: 0; }
    #fb-annot-canvas { max-width: 100%; max-height: 100%; object-fit: contain; background: #fff; border: 1px solid var(--color-gray-200); cursor: crosshair; touch-action: none; }
    .nav-badge {
        background: #ef4444;
        color: white;
        font-size: var(--d-fs-xs);
        padding: 2px 6px;
        line-height: 1;
        border-radius: 10px;
        margin-left: auto;
        display: inline-flex;
        align-items: center;
    }
    /* Badge auf der Gruppen-Kopfzeile: nur zeigen, wenn die Gruppe ZUGEKLAPPT
       ist (sonst zeigt der Unterpunkt das Badge selbst -> kein Doppel). */
    .nav-group-badge {
        display: none;
        background: #ef4444;
        color: white;
        font-size: var(--d-fs-xs);
        padding: 2px 6px;
        line-height: 1;
        border-radius: 10px;
        margin-left: 8px;
        align-items: center;
    }
    .nav-group.collapsed .nav-group-badge { display: inline-flex; }
    /* In der voll-eingeklappten Sidebar (60px) nicht anzeigen */
    .app-container.sidebar-collapsed .sidebar .nav-group-badge { display: none; }

    </style>

    <script>
    // Nav Group Toggle with localStorage persistence
    function toggleNavGroup(groupId) {
        const group = document.querySelector(`.nav-group[data-group="${groupId}"]`);
        if (!group) return;
        group.classList.toggle('collapsed');
        // Save state
        const collapsed = JSON.parse(localStorage.getItem('navCollapsed') || '{}');
        collapsed[groupId] = group.classList.contains('collapsed');
        localStorage.setItem('navCollapsed', JSON.stringify(collapsed));
    }

    // Init der Submenue-Klassen passiert oben direkt nach der Sidebar
    // (vor dem ersten Paint), um Flackern zu verhindern.

    // Sidebar ein- und ausklappen
    function thxToggleSidebar() {
        var container = document.querySelector('.app-container');
        if (!container) return;
        container.classList.toggle('sidebar-collapsed');
        try {
            localStorage.setItem(
                'thx_sidebar_collapsed',
                container.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        } catch (e) {}
    }

    // Feedback Widget — mehrere Anhaenge moeglich (Screenshot UND Video)
    let feedbackMedia = [];   // [{ type:'screenshot'|'video', data:'data:...;base64,...' }]
    let recorderWindow = null;

    function toggleFeedbackPanel() {
        const widget = document.getElementById('feedback-widget');
        const panel  = document.getElementById('feedback-panel');
        if (!widget || !panel) {
            console.error('Feedback-Widget nicht im DOM gefunden');
            return;
        }
        widget.classList.toggle('is-open');
        // Backwards-compat: panel.classList.contains('active') wird an einigen
        // Stellen weiter unten geprueft (zB beim Screenshot-Flow)
        panel.classList.toggle('active');
    }
    window.toggleFeedbackPanel = toggleFeedbackPanel;
    // WICHTIG: nur EINMAL binden. Dieser Footer-Block wird im gerenderten
    // Dokument doppelt ausgefuehrt; ohne Guard wuerde der Klick-Handler zweimal
    // registriert -> jeder Klick toggelt zweimal (auf+sofort zu = "nichts
    // passiert"). Genau das war der Bug (Diagnose 09.06.2026: ein Klick loeste
    // zwei toggle-Aufrufe aus). Der Guard auf window stellt einen einzigen
    // Listener sicher. Capture-Phase (3. Param true), damit das globale
    // Context-Menu den Klick nicht per stopPropagation abfaengt.
    if (!window.__fbTriggerBound) {
        window.__fbTriggerBound = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-feedback-trigger]')) {
                e.preventDefault();
                toggleFeedbackPanel();
            }
        }, true);
    }
    // ESC schliesst das Modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const w = document.getElementById('feedback-widget');
            if (w && w.classList.contains('is-open')) toggleFeedbackPanel();
        }
    });

    async function captureScreenshot() {
        const widget = document.getElementById('feedback-widget');
        // Feedback-Fenster (samt abgedunkeltem Hintergrund) fuer die Aufnahme ausblenden,
        // sonst nimmt es sich selbst mit auf. Wird im Annotator-Ende wieder eingeblendet.
        if (widget) widget.style.visibility = 'hidden';
        try {
            const stream = await navigator.mediaDevices.getDisplayMedia({
                preferCurrentTab: true,
                video: { displaySurface: 'browser' }
            });

            const video = document.createElement('video');
            video.srcObject = stream;
            await video.play();

            // Zwei Frames warten, damit das ausgeblendete Fenster sicher aus dem Bild ist
            await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            stream.getTracks().forEach(track => track.stop());

            // Screenshot in den Annotator zum Markieren (Stift/Rechteck/Pfeil + Farben)
            openAnnotator(canvas);
        } catch (error) {
            if (widget) widget.style.visibility = '';   // bei Abbruch wieder zeigen
            if (error.name !== 'AbortError') {
                App.showNotification('Screenshot fehlgeschlagen: ' + error.message, 'error');
            }
        }
    }

    function startVideoRecording() {
        // Popup-Fenster für Aufnahme öffnen
        const width = 450;
        const height = 500;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;

        recorderWindow = window.open(
            '/feedback-recorder.php',
            'FeedbackRecorder',
            `width=${width},height=${height},left=${left},top=${top},resizable=no,scrollbars=no,toolbar=no,menubar=no,location=no,status=no`
        );

        if (!recorderWindow) {
            App.showNotification('Popup wurde blockiert. Bitte erlaube Popups für diese Seite.', 'error');
            return;
        }

        // Feedback-Panel schließen damit User frei navigieren kann
        const panel = document.getElementById('feedback-panel');
        if (panel.classList.contains('active')) {
            toggleFeedbackPanel();
        }

        App.showNotification('Aufnahme-Fenster geöffnet. Du kannst jetzt frei navigieren.', 'info');
    }

    // Wird vom Popup aufgerufen wenn Video fertig ist
    function receiveRecordedVideo(base64Data, blob) {
        feedbackMedia.push({ type: 'video', data: base64Data });
        renderMediaPreview();
        saveFbState();
        const panel = document.getElementById('feedback-panel');
        if (!panel.classList.contains('active')) toggleFeedbackPanel();
        App.showNotification('Video aufgenommen!', 'success');
    }

    // Ein Anhang entfernen (oder alle, wenn ohne Index aufgerufen)
    function removeMedia(index) {
        if (typeof index === 'number') feedbackMedia.splice(index, 1);
        else feedbackMedia = [];
        renderMediaPreview();
        saveFbState();
    }

    // Vorschau-Galerie aller Anhaenge (Screenshot + Video nebeneinander)
    function renderMediaPreview() {
        const preview = document.getElementById('fb-media-preview');
        if (!preview) return;
        preview.innerHTML = feedbackMedia.map(function (m, i) {
            const inner = m.type === 'video'
                ? '<video src="' + m.data + '" controls></video>'
                : '<img src="' + m.data + '" alt="Screenshot">';
            return '<div class="fb-media-item">' + inner +
                   '<span class="remove-media" onclick="removeMedia(' + i + ')" title="Entfernen">&times;</span></div>';
        }).join('');
    }

    // ----- Entwurf-Persistenz: Formular + Anhaenge ueberleben einen Reload (z.B. waehrend Video) -----
    function saveFbState() {
        try {
            sessionStorage.setItem('fb_draft', JSON.stringify({
                ts: Date.now(),
                title: (document.getElementById('fb-title') || {}).value || '',
                description: (document.getElementById('fb-description') || {}).value || '',
                type: (document.getElementById('fb-type') || {}).value || 'bug',
                media: feedbackMedia
            }));
        } catch (e) {}
    }
    function restoreFbState() {
        try {
            const raw = sessionStorage.getItem('fb_draft');
            if (!raw) return;
            const s = JSON.parse(raw);
            if (!s || (Date.now() - (s.ts || 0)) > 2 * 60 * 60 * 1000) { sessionStorage.removeItem('fb_draft'); return; }
            const t = document.getElementById('fb-title'); if (t && s.title) t.value = s.title;
            const d = document.getElementById('fb-description'); if (d && s.description) d.value = s.description;
            const ty = document.getElementById('fb-type'); if (ty && s.type) ty.value = s.type;
            if (Array.isArray(s.media)) { feedbackMedia = s.media; renderMediaPreview(); }
        } catch (e) {}
    }
    function clearFbState() { try { sessionStorage.removeItem('fb_draft'); } catch (e) {} }

    document.addEventListener('DOMContentLoaded', function () {
        ['fb-title', 'fb-description', 'fb-type'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) { el.addEventListener('input', saveFbState); el.addEventListener('change', saveFbState); }
        });
        restoreFbState();
    });

    // ===== Screenshot-Annotator (Stift / Rechteck / Pfeil + 3 Farben) =====
    const fbAnnot = { img: null, ctx: null, canvas: null, shapes: [], cur: null, drawing: false, tool: 'arrow', color: '#e11d48' };

    function openAnnotator(srcCanvas) {
        const modal = document.getElementById('fb-annot');
        const canvas = document.getElementById('fb-annot-canvas');
        canvas.width = srcCanvas.width;
        canvas.height = srcCanvas.height;
        fbAnnot.canvas = canvas;
        fbAnnot.ctx = canvas.getContext('2d');
        fbAnnot.shapes = [];
        fbAnnot.cur = null;
        fbAnnot.drawing = false;
        fbAnnot.img = new Image();
        fbAnnot.img.onload = function () { fbAnnotRedraw(); };
        fbAnnot.img.src = srcCanvas.toDataURL('image/png');
        modal.style.display = 'flex';
        fbAnnotBind();
    }
    function fbAnnotPos(e) {
        const r = fbAnnot.canvas.getBoundingClientRect();
        const cx = (e.touches ? e.touches[0].clientX : e.clientX);
        const cy = (e.touches ? e.touches[0].clientY : e.clientY);
        return { x: (cx - r.left) * (fbAnnot.canvas.width / r.width), y: (cy - r.top) * (fbAnnot.canvas.height / r.height) };
    }
    function fbAnnotBind() {
        const c = fbAnnot.canvas;
        c.onpointerdown = function (e) {
            fbAnnot.drawing = true;
            const p = fbAnnotPos(e);
            fbAnnot.cur = { tool: fbAnnot.tool, color: fbAnnot.color, x0: p.x, y0: p.y, x1: p.x, y1: p.y, points: [p] };
            try { c.setPointerCapture(e.pointerId); } catch (er) {}
        };
        c.onpointermove = function (e) {
            if (!fbAnnot.drawing) return;
            const p = fbAnnotPos(e);
            fbAnnot.cur.x1 = p.x; fbAnnot.cur.y1 = p.y;
            if (fbAnnot.tool === 'pen') fbAnnot.cur.points.push(p);
            fbAnnotRedraw(); fbAnnotDraw(fbAnnot.cur);
        };
        c.onpointerup = function () {
            if (!fbAnnot.drawing) return;
            fbAnnot.shapes.push(fbAnnot.cur); fbAnnot.cur = null; fbAnnot.drawing = false; fbAnnotRedraw();
        };
        document.querySelectorAll('.fb-annot-tool').forEach(function (b) {
            b.onclick = function () { fbAnnot.tool = b.dataset.tool; document.querySelectorAll('.fb-annot-tool').forEach(function (x) { x.classList.toggle('is-active', x === b); }); };
        });
        document.querySelectorAll('.fb-annot-color').forEach(function (b) {
            b.onclick = function () { fbAnnot.color = b.dataset.color; document.querySelectorAll('.fb-annot-color').forEach(function (x) { x.classList.toggle('is-active', x === b); }); };
        });
        document.getElementById('fb-annot-undo').onclick = function () { fbAnnot.shapes.pop(); fbAnnotRedraw(); };
    }
    function fbAnnotRedraw() {
        const ctx = fbAnnot.ctx;
        ctx.clearRect(0, 0, fbAnnot.canvas.width, fbAnnot.canvas.height);
        if (fbAnnot.img) ctx.drawImage(fbAnnot.img, 0, 0);
        fbAnnot.shapes.forEach(fbAnnotDraw);
    }
    function fbAnnotDraw(s) {
        const ctx = fbAnnot.ctx;
        const lw = Math.max(3, Math.round(fbAnnot.canvas.width / 400));
        ctx.lineWidth = lw; ctx.strokeStyle = s.color; ctx.fillStyle = s.color; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        if (s.tool === 'rect') {
            ctx.strokeRect(s.x0, s.y0, s.x1 - s.x0, s.y1 - s.y0);
        } else if (s.tool === 'pen') {
            ctx.beginPath(); s.points.forEach(function (p, i) { i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y); }); ctx.stroke();
        } else {
            ctx.beginPath(); ctx.moveTo(s.x0, s.y0); ctx.lineTo(s.x1, s.y1); ctx.stroke();
            const ang = Math.atan2(s.y1 - s.y0, s.x1 - s.x0), h = lw * 4.5;
            ctx.beginPath(); ctx.moveTo(s.x1, s.y1);
            ctx.lineTo(s.x1 - h * Math.cos(ang - Math.PI / 6), s.y1 - h * Math.sin(ang - Math.PI / 6));
            ctx.lineTo(s.x1 - h * Math.cos(ang + Math.PI / 6), s.y1 - h * Math.sin(ang + Math.PI / 6));
            ctx.closePath(); ctx.fill();
        }
    }
    function finishAnnotator() {
        feedbackMedia.push({ type: 'screenshot', data: fbAnnot.canvas.toDataURL('image/png') });
        renderMediaPreview(); saveFbState();
        document.getElementById('fb-annot').style.display = 'none';
        const widget = document.getElementById('feedback-widget');
        if (widget) widget.style.visibility = '';   // Feedback-Fenster wieder zeigen
        const panel = document.getElementById('feedback-panel');
        if (panel && !panel.classList.contains('active')) toggleFeedbackPanel();
        App.showNotification('Screenshot übernommen', 'success');
    }
    function cancelAnnotator() {
        document.getElementById('fb-annot').style.display = 'none';
        const widget = document.getElementById('feedback-widget');
        if (widget) widget.style.visibility = '';   // Feedback-Fenster wieder zeigen
        const panel = document.getElementById('feedback-panel');
        if (panel && !panel.classList.contains('active')) toggleFeedbackPanel();
    }

    async function submitFeedback() {
        const title = document.getElementById('fb-title').value.trim();
        const description = document.getElementById('fb-description').value.trim();
        if (!title) {
            App.showNotification('Bitte einen Titel eingeben', 'error');
            return;
        }

        const btn = document.getElementById('fb-submit');
        btn.disabled = true;
        btn.textContent = 'Sende...';

        try {
            await App.post('/feedback/internal', {
                feedback_type: document.getElementById('fb-type').value,
                title: title,
                description: description,
                page_url: window.location.href,
                media: feedbackMedia,
                screen_width: window.screen.width,
                screen_height: window.screen.height
            });

            App.showNotification('Feedback gesendet! Vielen Dank.', 'success');

            // Reset + Entwurf verwerfen
            document.getElementById('fb-title').value = '';
            document.getElementById('fb-description').value = '';
            document.getElementById('fb-type').value = 'bug';
            removeMedia();
            clearFbState();
            toggleFeedbackPanel();

        } catch (error) {
            App.showNotification('Fehler: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Feedback senden';
        }
    }

    // Panel schließen bei Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const panel = document.getElementById('feedback-panel');
            if (panel.classList.contains('active')) {
                toggleFeedbackPanel();
            }
        }
    });

    // Panel schließen bei Klick außerhalb.
    // WICHTIG: Klicks auf den Trigger-Button (data-feedback-trigger) ausnehmen!
    // Sonst lief folgender Konflikt (Bug bis 09.06.2026): der Öffnen-Handler
    // setzt beim Button-Klick 'active', der GLEICHE Klick blubbert hier weiter,
    // sieht 'active' + Ziel ausserhalb des Panels und schliesst sofort wieder
    // => Panel ging auf und sofort zu, der Button schien tot.
    document.addEventListener('click', function(e) {
        const widget = document.getElementById('feedback-widget');
        const panel = document.getElementById('feedback-panel');
        if (!widget || !panel) return;
        if (panel.classList.contains('active')
            && !widget.contains(e.target)
            && !e.target.closest('[data-feedback-trigger]')
            && !e.target.closest('#fb-annot')) {
            toggleFeedbackPanel();
        }
    });
    </script>

    <!-- ============================================================
         Globale Komponente: Quick-Customer-Create
         Wird ueberall genutzt, wo ein Kunde fuer eine Wissens-Aktion
         ausgewaehlt werden muss (Chat-Save, Wissen-Edit, Transkript,
         Steckbrief-Import). Verwendung:
            ThxQuickCustomer.attachToSelect(selectEl, { onCreate(c) })
         Fuegt eine 'Neuen Kunden anlegen…'-Option an, die einen Mini-Dialog
         oeffnet und das frisch angelegte Customer-Objekt zurueckmeldet.
         ============================================================ -->
    <style>
    .thx-qc-backdrop {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4);
        z-index: 1100; display: none; align-items: center; justify-content: center;
    }
    .thx-qc-backdrop.open { display: flex; }
    .thx-qc-modal {
        background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        width: 460px; max-width: 92vw; max-height: 90vh; overflow: auto;
        padding: 18px 22px;
    }
    .thx-qc-modal h3 {
        margin: 0 0 14px 0; font-size: var(--d-fs-lg); color: var(--slate-900);
        display: flex; gap: 8px; align-items: center;
    }
    .thx-qc-modal .material-symbols-rounded { color: var(--thoxan-700); }
    .thx-qc-modal label { display: block; font-size: var(--d-fs-sm); font-weight: 600; color: var(--slate-700); margin: 10px 0 4px; }
    .thx-qc-modal input {
        width: 100%; padding: 8px 10px; border: 1px solid var(--slate-300); border-radius: 8px;
        font-size: var(--d-fs-sm); font-family: inherit; box-sizing: border-box;
    }
    .thx-qc-modal input:focus { outline: none; border-color: var(--thoxan-600); box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
    .thx-qc-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px; }
    .thx-qc-hint { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 4px; }
    .thx-qc-error { background: var(--rose-50); color: var(--rose-700); border: 1px solid var(--rose-200); padding: 8px 10px; border-radius: 6px; font-size: var(--d-fs-sm); margin-top: 12px; }
    </style>
    <div class="thx-qc-backdrop" id="thx-qc-backdrop" onclick="if(event.target===this)ThxQuickCustomer.close()">
        <div class="thx-qc-modal" id="thx-qc-modal">
            <h3><span class="material-symbols-rounded">add_business</span> Neuen Kunden anlegen</h3>
            <div>
                <label for="thx-qc-name">Name <span style="color:var(--rose-600);">*</span></label>
                <input type="text" id="thx-qc-name" placeholder="z. B. Beispiel GmbH" autocomplete="off">
            </div>
            <div>
                <label for="thx-qc-abbr">Kürzel <span style="color:var(--slate-400);font-weight:400;">(optional)</span></label>
                <input type="text" id="thx-qc-abbr" placeholder="z. B. BSP" maxlength="10" autocomplete="off" style="text-transform:uppercase;">
                <div class="thx-qc-hint">Bleibt leer: wird aus den Anfangsbuchstaben des Namens generiert.</div>
            </div>
            <div>
                <label for="thx-qc-industry">Branche <span style="color:var(--slate-400);font-weight:400;">(optional)</span></label>
                <input type="text" id="thx-qc-industry" placeholder="z. B. Maschinenbau" autocomplete="off">
            </div>
            <div id="thx-qc-error" class="thx-qc-error" style="display:none;"></div>
            <div class="thx-qc-actions">
                <button class="thx-btn thx-btn-ghost" type="button" onclick="ThxQuickCustomer.close()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" type="button" id="thx-qc-submit" onclick="ThxQuickCustomer.submit()">
                    <span class="material-symbols-rounded" style="font-size:16px;">check</span> Anlegen
                </button>
            </div>
        </div>
    </div>
    <script>
    window.ThxQuickCustomer = (function() {
        let onCreate = null;

        function open(_onCreate) {
            onCreate = _onCreate;
            document.getElementById('thx-qc-name').value = '';
            document.getElementById('thx-qc-abbr').value = '';
            document.getElementById('thx-qc-industry').value = '';
            const err = document.getElementById('thx-qc-error'); err.style.display = 'none'; err.textContent = '';
            document.getElementById('thx-qc-backdrop').classList.add('open');
            setTimeout(() => document.getElementById('thx-qc-name').focus(), 80);
        }
        function close() {
            document.getElementById('thx-qc-backdrop').classList.remove('open');
            onCreate = null;
        }
        async function submit() {
            const name = document.getElementById('thx-qc-name').value.trim();
            const abbr = document.getElementById('thx-qc-abbr').value.trim();
            const industry = document.getElementById('thx-qc-industry').value.trim();
            const btn = document.getElementById('thx-qc-submit');
            const err = document.getElementById('thx-qc-error');
            if (!name) {
                err.textContent = 'Bitte einen Namen eintragen.';
                err.style.display = 'block';
                return;
            }
            btn.disabled = true;
            err.style.display = 'none';
            try {
                const r = await fetch('/api/v1/customers/quick-create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (window.App?.csrfToken || '') },
                    body: JSON.stringify({ name, abbreviation: abbr, industry }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message || 'Fehler');
                if (onCreate) onCreate(j.data);
                close();
                if (window.App?.showNotification) window.App.showNotification('Kunde "' + j.data.name + '" angelegt', 'success');
            } catch (e) {
                err.textContent = e.message || 'Fehler beim Anlegen';
                err.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }
        /**
         * Haengt eine "Neuen Kunden anlegen…"-Option an ein bestehendes <select>.
         * Nach Anlegen wird die neue Option zum Select hinzugefuegt und ausgewaehlt.
         * Optional: change-Listener am Select triggern.
         */
        function attachToSelect(selectEl, opts) {
            if (!selectEl || selectEl.dataset.thxQcAttached === '1') return;
            opts = opts || {};
            const optEl = document.createElement('option');
            optEl.value = '__new__';
            optEl.textContent = '＋ Neuen Kunden anlegen…';
            optEl.style.fontWeight = '600';
            optEl.style.color = '#004C9B';
            selectEl.appendChild(optEl);
            selectEl.dataset.thxQcAttached = '1';
            selectEl.addEventListener('change', function() {
                if (this.value !== '__new__') return;
                // ZurÃ¼ck zum vorherigen Wert, falls Anlage abgebrochen wird
                const prevValue = this.dataset.thxQcPrev || '';
                this.value = prevValue;
                open(function(customer) {
                    const newOpt = document.createElement('option');
                    newOpt.value = String(customer.id);
                    newOpt.textContent = customer.name;
                    // Vor der "__new__"-Option einfuegen
                    selectEl.insertBefore(newOpt, optEl);
                    selectEl.value = String(customer.id);
                    selectEl.dataset.thxQcPrev = String(customer.id);
                    if (typeof opts.onCreate === 'function') opts.onCreate(customer, selectEl);
                    // Change-Event manuell ausloesen
                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            selectEl.addEventListener('focus', function() { this.dataset.thxQcPrev = this.value; });
        }
        // Esc schliesst
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('thx-qc-backdrop').classList.contains('open')) close();
        });
        return { open, close, submit, attachToSelect };
    })();
    </script>

    <!-- ============================================================
         Globale Komponente: Context-Menu (Rechtsklick)
         Verwendung:
            ThxContextMenu.bind(elementOrSelector, (e, target) => items)
         items: [{ label, icon?, onClick, divider?, disabled?, danger?, header? }]
         oder direkt:
            ThxContextMenu.show(event, items)
         ============================================================ -->
    <style>
    .thx-cm {
        position: fixed; z-index: 9000; display: none;
        background: #fff; border: 1px solid var(--slate-200);
        border-radius: 10px; box-shadow: 0 12px 32px rgba(15,23,42,0.22);
        min-width: 220px; max-width: 320px; padding: 4px;
        font-size: var(--d-fs-sm); font-family: inherit;
    }
    .thx-cm.open { display: block; }
    .thx-cm-item {
        display: flex; align-items: center; gap: 10px;
        padding: 7px 10px; border-radius: 6px; cursor: pointer;
        color: var(--slate-800); user-select: none;
    }
    .thx-cm-item:hover { background: var(--thoxan-50); color: var(--thoxan-800); }
    .thx-cm-item.is-disabled { opacity: 0.4; cursor: not-allowed; }
    .thx-cm-item.is-disabled:hover { background: transparent; color: var(--slate-800); }
    .thx-cm-item.is-danger { color: var(--rose-700); }
    .thx-cm-item.is-danger:hover { background: var(--rose-50); color: var(--rose-800); }
    .thx-cm-item .material-symbols-rounded { font-size: 18px; color: var(--slate-500); flex-shrink: 0; }
    .thx-cm-item.is-danger .material-symbols-rounded { color: var(--rose-600); }
    .thx-cm-divider { height: 1px; background: var(--slate-200); margin: 4px 6px; }
    .thx-cm-header {
        padding: 6px 10px 4px; font-size: var(--d-fs-xs); font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em; color: var(--slate-500);
    }
    </style>
    <!-- ThxContextMenu wird oben im <main> registriert (vor $content), damit View-Init-Scripts darauf zugreifen koennen -->

    <script src="/assets/js/app.js"></script>
    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
