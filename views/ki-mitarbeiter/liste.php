<?php
/** KI-Mitarbeiter — Liste. */
require_once SERVICES_PATH . '/KiMitarbeiterService.php';
$svc = new \Services\KiMitarbeiterService(\Core\Database::getInstance());
$filter = [];
if (!\Core\Auth::isAdmin()) $filter['allowed_customer_ids'] = \Core\Auth::customers();
$employees = $svc->liste($filter);

$statusMeta = [
    'draft'      => ['Entwurf', 'var(--slate-500)', 'var(--slate-100)'],
    'review'     => ['In Prüfung', 'var(--amber-700)', 'var(--amber-50)'],
    'onboarding' => ['Einarbeitung', 'var(--indigo-700)', 'var(--indigo-100)'],
    'probation'  => ['Probezeit', 'var(--thoxan-700)', 'var(--thoxan-50)'],
    'active'     => ['Aktiv', 'var(--emerald-700)', 'var(--emerald-50)'],
    'paused'     => ['Pausiert', 'var(--rose-700)', 'var(--rose-50)'],
    'archived'   => ['Archiviert', 'var(--slate-400)', 'var(--slate-50)'],
];
?>
<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">badge</span>
            KI-Mitarbeiter
        </h1>
        <p class="thx-page-subtitle">Spezialisierte KI-Mitarbeiter im Sparring mit KI entwerfen, testen und führen.</p>
    </div>
    <a href="/ki-mitarbeiter/neu" class="thx-btn thx-btn-primary" style="white-space:nowrap;">
        <span class="material-symbols-rounded" style="font-size:18px;">add</span> Neuer KI-Mitarbeiter
    </a>
</div>

<?php if (empty($employees)): ?>
<div class="thx-card" style="text-align:center;padding:48px 24px;color:var(--slate-500);">
    <span class="material-symbols-rounded" style="font-size:48px;color:var(--slate-300);">badge</span>
    <p style="margin:12px 0 4px;font-weight:600;color:var(--slate-700);">Noch keine KI-Mitarbeiter</p>
    <p style="margin:0 0 16px;">Lege deinen ersten spezialisierten KI-Mitarbeiter im geführten Sparring an.</p>
    <a href="/ki-mitarbeiter/neu" class="thx-btn thx-btn-primary">Jetzt starten</a>
</div>
<?php else: ?>
<div class="km-grid">
    <?php foreach ($employees as $e): ?>
        <?php $sm = $statusMeta[$e['status']] ?? [$e['status'], 'var(--slate-500)', 'var(--slate-100)']; ?>
        <a class="km-card" href="/ki-mitarbeiter/<?= (int) $e['id'] ?>">
            <div class="km-card-head">
                <span class="km-avatar material-symbols-rounded">smart_toy</span>
                <div style="flex:1;min-width:0;">
                    <div class="km-name"><?= htmlspecialchars($e['name']) ?></div>
                    <div class="km-role"><?= htmlspecialchars($e['role_title'] ?: 'Ohne Rollenbezeichnung') ?></div>
                </div>
                <span class="km-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;"><?= htmlspecialchars($sm[0]) ?></span>
            </div>
            <div class="km-meta">
                <?php if (!empty($e['owner_name'])): ?><span><span class="material-symbols-rounded">person</span><?= htmlspecialchars($e['owner_name']) ?></span><?php endif; ?>
                <?php if ((int) $e['open_permissions'] > 0): ?><span style="color:var(--amber-700);"><span class="material-symbols-rounded">lock_open</span><?= (int) $e['open_permissions'] ?> offene Freigabe(n)</span><?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.km-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; max-width:1100px; }
.km-card { display:block; background:#fff; border:1px solid var(--slate-200); border-radius:10px; padding:16px; text-decoration:none; color:inherit; transition:border-color .15s, box-shadow .15s; }
.km-card:hover { border-color:var(--thoxan-400); box-shadow:0 2px 10px rgba(0,0,0,.05); }
.km-card-head { display:flex; align-items:center; gap:12px; }
.km-avatar { width:44px; height:44px; border-radius:10px; background:var(--thoxan-50); color:var(--thoxan-600); display:flex; align-items:center; justify-content:center; font-size:24px; flex:0 0 auto; }
.km-name { font-weight:700; color:var(--slate-800); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-role { font-size:var(--d-fs-sm); color:var(--slate-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.km-meta { display:flex; gap:14px; flex-wrap:wrap; margin-top:12px; font-size:var(--d-fs-sm); color:var(--slate-500); }
.km-meta span { display:inline-flex; align-items:center; gap:4px; }
.km-meta .material-symbols-rounded { font-size:15px; }
</style>
