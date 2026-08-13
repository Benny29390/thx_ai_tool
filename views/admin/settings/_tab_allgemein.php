<?php
/** Allgemein-Tab — App-Name, Logo & Branding. */
?>
<div class="settings-card">
    <h2>App-Identität</h2>
    <p class="settings-card-sub">Anzeigename in Top-Bar, E-Mails und PDF-Exporten.</p>
    <form id="form-allgemein" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="app_name">App-Name</label>
                <input type="text" id="app_name" name="app_name"
                       value="<?= htmlspecialchars($valueOf('app_name', 'KI Text Tool')) ?>"
                       placeholder="z.B. KI Text Tool">
            </div>
            <div class="settings-field">
                <label for="app_url">App-URL</label>
                <input type="url" id="app_url" name="app_url"
                       value="<?= htmlspecialchars($valueOf('app_url', '')) ?>"
                       placeholder="https://ihre-domain.de">
                <p class="field-hint">Wird in E-Mail-Einladungen und Public-Share-Links verwendet.</p>
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Primärfarbe</h2>
    <p class="settings-card-sub">Bestimmt die Hauptfarbe der Oberfläche (Buttons, Aktivmarkierungen, Akzente). Leer lassen für den Standard.</p>
    <form id="form-brandcolor" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Farbe gespeichert — Seite wird neu geladen' }).then(()=>setTimeout(()=>location.reload(),700));">
        <div class="settings-row" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <?php $bc = $valueOf('brand_primary_color', ''); ?>
            <input type="color" id="brand_primary_color_picker"
                   value="<?= htmlspecialchars($bc !== '' ? $bc : '#006fb9') ?>"
                   style="width:52px;height:40px;border:1px solid var(--slate-300);border-radius:8px;background:none;cursor:pointer;"
                   oninput="document.getElementById('brand_primary_color').value=this.value;">
            <div class="settings-field" style="margin:0;">
                <label for="brand_primary_color" style="font-size:11px;">Hex-Wert</label>
                <input type="text" id="brand_primary_color" name="brand_primary_color"
                       value="<?= htmlspecialchars($bc) ?>" placeholder="#006fb9"
                       pattern="^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                       style="width:130px;font-family:monospace;"
                       oninput="try{document.getElementById('brand_primary_color_picker').value=this.value;}catch(e){}">
            </div>
            <span style="font-size:var(--d-fs-sm);color:var(--slate-500);">Standard: <code>#006fb9</code> (Thoxan-Blau)</span>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <?php if ($bc !== ''): ?>
            <button type="button" class="thx-btn" onclick="App.post('/admin/settings',{brand_primary_color:''}).then(()=>location.reload());">Auf Standard zurücksetzen</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Logo &amp; Branding</h2>
    <p class="settings-card-sub">SVG-Code direkt einfügen oder Datei (SVG/PNG) hochladen.</p>
    <form id="logo-form" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Logo gespeichert' });">
        <div class="logo-upload-area" id="logo-upload-area"
             style="border:2px dashed var(--slate-300, #cbd5e1);border-radius:8px;padding:24px;text-align:center;cursor:pointer;background:var(--slate-50, #f8fafc);transition:all .2s;">
            <div style="font-size: var(--d-fs-sm);color:var(--slate-600, #475569);">
                <strong>Logo hierher ziehen</strong> oder klicken (SVG / PNG)
            </div>
            <small style="display:block;margin-top:4px;color:var(--slate-400, #94a3b8);">Empfohlen: 200×50px</small>
            <input type="file" id="logo-file-input" accept=".svg,.png,image/svg+xml,image/png" hidden>
        </div>
        <div class="settings-field" style="margin-top:16px;">
            <label for="app_logo">Logo-Code (SVG / IMG-Tag)</label>
            <textarea id="app_logo" name="app_logo" rows="5"
                      placeholder="<svg>…</svg>"
                      style="font-family:'SF Mono', Menlo, monospace;font-size: var(--d-fs-sm);"><?= htmlspecialchars($valueOf('app_logo')) ?></textarea>
        </div>
        <?php if ($valueOf('app_logo') !== ''): ?>
            <div class="settings-field" style="margin-top:12px;">
                <label>Aktuelle Vorschau</label>
                <div class="logo-preview-box"
                     style="border:1px solid var(--slate-200,#e2e8f0);border-radius:6px;
                            padding:16px;background:#fff;display:flex;align-items:center;
                            justify-content:center;min-height:80px;">
                    <?= $valueOf('app_logo') /* trusted admin input — direkt einbinden */ ?>
                </div>
                <p class="field-hint">Vorschau ist auf 240×60 px begrenzt — in der Sidebar bestimmt das CSS dort die Größe.</p>
            </div>
        <?php endif; ?>
        <style>
            /* Inhaltliche SVGs/IMGs in der Vorschau auf einen sinnvollen Rahmen skalieren.
               Direkt auf das Element, nicht auf einen Wrapper — sonst kollabiert der Wrapper. */
            .logo-preview-box svg,
            .logo-preview-box img {
                max-width: 240px;
                max-height: 60px;
                width: auto;
                height: auto;
                display: block;
            }
        </style>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <?php if ($valueOf('app_logo') !== ''): ?>
                <button type="button" class="thx-btn thx-btn-danger" onclick="entferneLogo()">Logo entfernen</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(function () {
    const upArea = document.getElementById('logo-upload-area');
    const fileInput = document.getElementById('logo-file-input');
    const codeInput = document.getElementById('app_logo');
    if (!upArea || !fileInput || !codeInput) return;

    upArea.addEventListener('click', () => fileInput.click());
    upArea.addEventListener('dragover', e => { e.preventDefault(); upArea.style.borderColor = 'var(--thoxan-500, #2563eb)'; });
    upArea.addEventListener('dragleave', () => { upArea.style.borderColor = ''; });
    upArea.addEventListener('drop', e => {
        e.preventDefault();
        upArea.style.borderColor = '';
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', e => {
        if (e.target.files[0]) handleFile(e.target.files[0]);
    });

    function handleFile(file) {
        const isSvg = file.type.includes('svg') || file.name.endsWith('.svg');
        const isPng = file.type.includes('png') || file.name.endsWith('.png');
        if (!isSvg && !isPng) {
            App.showNotification('Nur SVG oder PNG erlaubt', 'error');
            return;
        }
        const reader = new FileReader();
        if (isSvg) {
            reader.onload = e => {
                const c = e.target.result;
                if (!c.includes('<svg')) { App.showNotification('Ungültige SVG-Datei', 'error'); return; }
                codeInput.value = c;
                App.showNotification('SVG geladen — bitte speichern', 'info');
            };
            reader.readAsText(file);
        } else {
            reader.onload = e => {
                codeInput.value = '<img src="' + e.target.result + '" alt="Logo">';
                App.showNotification('PNG geladen — bitte speichern', 'info');
            };
            reader.readAsDataURL(file);
        }
    }

    window.entferneLogo = async function () {
        if (!confirm('Logo wirklich entfernen?')) return;
        try {
            await App.post('/admin/settings', { app_logo: '' });
            codeInput.value = '';
            App.showNotification('Logo entfernt', 'success');
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            App.showNotification(e.message || 'Fehler', 'error');
        }
    };
})();
</script>
