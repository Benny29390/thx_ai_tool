<?php
/**
 * Einstellungen — Tab-basierter Hub.
 *
 * Tab-State per ?tab= in der URL. Jeder Tab liegt als eigenes Partial unter
 * /var/www/views/admin/settings/_tab_<slug>.php und bekommt $settings + $aiModels
 * via include vererbt.
 */
use Core\Auth;
use Core\Database;

$tabs = [
    ['slug' => 'allgemein', 'name' => 'Allgemein',  'beschreibung' => 'App-Name, Logo, Branding'],
    ['slug' => 'ki',        'name' => 'KI-Modelle', 'beschreibung' => 'OpenAI, Anthropic, Google, Standard-Modell'],
    ['slug' => 'prompts',   'name' => 'System-Prompts', 'beschreibung' => 'Zentrale Verwaltung aller LLM-System-Prompts'],
    ['slug' => 'sistrix',   'name' => 'Sistrix',    'beschreibung' => 'API-Key, Kontingent, Wochenstatus'],
    ['slug' => 'lam',       'name' => 'LAM',        'beschreibung' => 'Monitoring-Intervall, Aufräum-Schwelle, Linkakquise-Defaults'],
    ['slug' => 'asana',     'name' => 'Asana',      'beschreibung' => 'Personal Access Token, Workspace'],
    ['slug' => 'brevo',     'name' => 'Brevo',      'beschreibung' => 'API-Key fuer CRM-Anbindung (Listen, Kontakte, DOI, Webhooks)'],
    ['slug' => 'websuche',  'name' => 'Web-Suche',  'beschreibung' => 'Brave Search API'],
    ['slug' => 'smtp',      'name' => 'E-Mail',     'beschreibung' => 'System-Versand + Mail-Konten (IMAP/SMTP) für das Mail-Tool'],
    ['slug' => 'mail',      'name' => 'Mail-Tool',  'beschreibung' => 'Globale Einstellungen für das Mail-Tool (Stop-Wörter, Auto-Versand, Pull-Intervall)'],
    // 'design'-Tab nach /admin/styleguide?tab=tokens verschoben (Styleguide-Hub).
    ['slug' => 'system',    'name' => 'System',     'beschreibung' => 'Version, Speicher, PHP'],
];

$allowedSlugs = array_column($tabs, 'slug');
$activeTab = $_GET['tab'] ?? 'ki';
if (!in_array($activeTab, $allowedSlugs, true)) {
    $activeTab = 'ki';
}

// Status-Hilfen, die mehrere Tabs brauchen
$isConfigured = fn(string $key): bool => !empty($settings[$key]['setting_value'] ?? '');
$valueOf      = fn(string $key, $default = ''): string => (string)($settings[$key]['setting_value'] ?? $default);

// CSRF (sofern vorhanden — manche Routen setzen es nicht)
$csrfToken = $csrfToken ?? ($_SESSION['csrf_token'] ?? '');
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Einstellungen</h1>
        <p class="thx-page-subtitle">Zentrale Konfiguration für KI-Anbieter, Integrationen und Versand.</p>
    </div>
</div>

<nav class="thx-tabs" aria-label="Einstellungs-Bereiche">
    <?php foreach ($tabs as $tab): ?>
        <a href="/admin/settings?tab=<?= $tab['slug'] ?>"
           class="thx-tab<?= $activeTab === $tab['slug'] ? ' is-active' : '' ?>"
           title="<?= htmlspecialchars($tab['beschreibung']) ?>">
            <?= htmlspecialchars($tab['name']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="settings-tab-content" style="margin-top:24px;">
    <?php
    $partial = __DIR__ . '/settings/_tab_' . $activeTab . '.php';
    if (file_exists($partial)) {
        include $partial;
    } else {
        echo '<div class="thx-card"><p class="muted">Tab nicht gefunden.</p></div>';
    }
    ?>
</div>

<style>
[x-cloak] { display: none !important; }
.settings-card {
    background: var(--thx-surface, #fff);
    border: 1px solid var(--slate-200, #e2e8f0);
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
}
.settings-card h2 {
    margin: 0 0 4px 0;
    font-size: var(--d-fs-base);
    font-weight: 700;
    color: var(--slate-900, #0f172a);
}
.settings-card .settings-card-sub {
    margin: 0 0 16px 0;
    font-size: var(--d-fs-sm);
    color: var(--slate-500, #64748b);
}
.settings-row { display:grid; gap:16px; margin-bottom:14px; }
.settings-row.two-col { grid-template-columns: 1fr 1fr; }
.settings-row.three-col { grid-template-columns: 2fr 1fr 1fr; }
.settings-field label {
    display:block;
    font-size: var(--d-fs-sm);
    font-weight: 600;
    color: var(--slate-700, #334155);
    margin-bottom: 6px;
}
.settings-field label .key-status {
    font-weight: 500;
    font-size: var(--d-fs-xs);
    padding: 2px 8px;
    border-radius: 999px;
    margin-left: 8px;
}
.settings-field label .key-status.ja {
    background: var(--emerald-100, #d1fae5);
    color: var(--emerald-800, #065f46);
}
.settings-field label .key-status.nein {
    background: var(--rose-100, #ffe4e6);
    color: var(--rose-800, #9f1239);
}
.settings-field input[type=text],
.settings-field input[type=password],
.settings-field input[type=email],
.settings-field input[type=number],
.settings-field input[type=url],
.settings-field select,
.settings-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    border: 1px solid var(--slate-300, #cbd5e1);
    border-radius: 6px;
    font: inherit;
    background: #fff;
    color: var(--slate-900, #0f172a);
}
.settings-field input:focus,
.settings-field select:focus,
.settings-field textarea:focus {
    border-color: var(--thoxan-500, #2563eb);
    outline: 2px solid var(--thoxan-100, #dbeafe);
    outline-offset: -1px;
}
.settings-field .field-hint {
    margin: 4px 0 0 0;
    font-size: var(--d-fs-xs);
    color: var(--slate-500, #64748b);
}
.settings-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 16px;
}
.settings-actions .status-msg {
    font-size: var(--d-fs-sm);
    margin-left: 4px;
}
.settings-actions .status-msg.ok    { color: var(--emerald-700, #047857); }
.settings-actions .status-msg.err   { color: var(--rose-700, #be123c); }
.settings-actions .status-msg.muted { color: var(--slate-500, #64748b); }
.settings-status-grid {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 6px 16px;
    font-size: var(--d-fs-sm);
}
.settings-status-grid dt { color: var(--slate-500, #64748b); }
.settings-status-grid dd { margin: 0; color: var(--slate-900, #0f172a); }
.settings-status-grid dd strong { font-weight: 700; }
</style>

<script>
(function () {
    // Hilfsfunktion: ein Settings-Formular speichern (über bestehende /admin/settings POST)
    window.SettingsSave = async function(form, opts = {}) {
        const data = {};
        const fields = form.querySelectorAll('input[name], select[name], textarea[name]');
        fields.forEach(el => {
            if (el.type === 'checkbox') {
                data[el.name] = el.checked ? '1' : '0';
            } else if (el.type === 'password') {
                // Leeres Passwort/Key-Feld nicht mitsenden -> Backend behält alten Wert
                if (el.value.trim() !== '') data[el.name] = el.value;
            } else {
                data[el.name] = el.value;
            }
        });

        try {
            const resp = await App.request('POST', '/admin/settings', data);
            if (resp.success) {
                App.showNotification(opts.successMsg || 'Einstellungen gespeichert', 'success');
                if (opts.onSuccess) opts.onSuccess(resp);
                // Passwortfelder leeren — Backend hat den neuen Wert übernommen
                form.querySelectorAll('input[type=password]').forEach(el => el.value = '');
                // Optionaler Page-Reload: Status-Badges, Disabled-States neu aus DB rendern
                if (opts.reloadOnSuccess) {
                    setTimeout(() => location.reload(), 400);
                }
            } else {
                App.showNotification(resp.message || 'Fehler beim Speichern', 'error');
            }
        } catch (e) {
            App.showNotification(e.message || 'Verbindungsfehler', 'error');
        }
    };
})();
</script>
