<h2>Zwei-Faktor-Authentifizierung</h2>

<p class="text-muted mb-lg">Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>

<form method="post" action="/login/2fa" class="auth-form" id="2fa-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="is_backup" id="is-backup" value="0">

    <div class="form-group">
        <label for="code" id="code-label">Authenticator-Code</label>
        <input type="text" id="code" name="code" required autofocus
               autocomplete="one-time-code"
               inputmode="numeric"
               pattern="[0-9]{6}"
               maxlength="6"
               placeholder="000000"
               class="code-input">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Verifizieren</button>
</form>

<div class="auth-footer">
    <button type="button" class="link-button" onclick="toggleBackupMode()">
        <span id="toggle-text">Backup-Code verwenden</span>
    </button>
    <span class="separator">|</span>
    <a href="/login">Zurück zum Login</a>
</div>

<style>
.code-input {
    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
    font-size: var(--d-fs-2xl);
    text-align: center;
    letter-spacing: 0.5rem;
    padding: 16px;
}
.auth-footer {
    margin-top: var(--spacing-lg);
    text-align: center;
    font-size: var(--d-fs-sm);
}
.auth-footer a,
.auth-footer .link-button {
    color: var(--color-gray-500);
    text-decoration: none;
}
.auth-footer a:hover,
.auth-footer .link-button:hover {
    color: var(--color-black);
}
.link-button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: inherit;
    padding: 0;
}
.separator {
    margin: 0 var(--spacing-sm);
    color: var(--color-gray-300);
}
.text-muted {
    color: var(--color-gray-500);
    font-size: var(--d-fs-sm);
}
.mb-lg {
    margin-bottom: var(--spacing-lg);
}
</style>

<script>
let isBackupMode = false;

function toggleBackupMode() {
    isBackupMode = !isBackupMode;
    const codeInput = document.getElementById('code');
    const codeLabel = document.getElementById('code-label');
    const toggleText = document.getElementById('toggle-text');
    const isBackupField = document.getElementById('is-backup');

    if (isBackupMode) {
        codeInput.placeholder = 'XXXX-XXXX';
        codeInput.pattern = '[A-Za-z0-9]{4}-?[A-Za-z0-9]{4}';
        codeInput.maxLength = 9;
        codeInput.inputMode = 'text';
        codeInput.classList.remove('code-input');
        codeInput.style.letterSpacing = '0.1rem';
        codeLabel.textContent = 'Backup-Code';
        toggleText.textContent = 'Authenticator-Code verwenden';
        isBackupField.value = '1';
    } else {
        codeInput.placeholder = '000000';
        codeInput.pattern = '[0-9]{6}';
        codeInput.maxLength = 6;
        codeInput.inputMode = 'numeric';
        codeInput.classList.add('code-input');
        codeInput.style.letterSpacing = '0.5rem';
        codeLabel.textContent = 'Authenticator-Code';
        toggleText.textContent = 'Backup-Code verwenden';
        isBackupField.value = '0';
    }

    codeInput.value = '';
    codeInput.focus();
}

// Auto-submit wenn 6 Ziffern eingegeben (nur im normalen Modus)
document.getElementById('code').addEventListener('input', function() {
    if (!isBackupMode && this.value.length === 6 && /^\d{6}$/.test(this.value)) {
        document.getElementById('2fa-form').submit();
    }
});
</script>
