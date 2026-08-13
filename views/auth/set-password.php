<?php $firstName = explode(' ', $user['name'])[0]; ?>

<h2>Willkommen an Bord!</h2>

<p style="color:#64748b;margin-bottom:1.5rem;">
    Hey <?= htmlspecialchars($firstName) ?>, leg dir ein Passwort fest und du kannst direkt loslegen.
</p>

<form method="post" action="/set-password" class="auth-form" onsubmit="return validatePassword()">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <div class="form-group">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required autofocus
               minlength="10" placeholder="Dein neues Passwort">
        <small style="color:#94a3b8;display:block;margin-top:4px;">Mind. 10 Zeichen, eine Ziffer und ein Sonderzeichen</small>
    </div>

    <div class="form-group">
        <label for="password_confirm">Passwort wiederholen</label>
        <input type="password" id="password_confirm" name="password_confirm" required
               minlength="10" placeholder="Nochmal eingeben">
    </div>

    <div id="pw-error" style="color:#dc2626;font-size: var(--d-fs-sm);margin-bottom:12px;display:none;"></div>

    <button type="submit" class="btn btn-primary btn-block">Los geht's</button>
</form>

<script>
function validatePassword() {
    const pw = document.getElementById('password').value;
    const pw2 = document.getElementById('password_confirm').value;
    const err = document.getElementById('pw-error');

    if (pw.length < 10) {
        err.textContent = 'Mindestens 10 Zeichen.';
        err.style.display = 'block';
        return false;
    }
    if (!/\d/.test(pw)) {
        err.textContent = 'Mindestens eine Ziffer.';
        err.style.display = 'block';
        return false;
    }
    if (!/[^a-zA-Z0-9]/.test(pw)) {
        err.textContent = 'Mindestens ein Sonderzeichen.';
        err.style.display = 'block';
        return false;
    }
    if (pw !== pw2) {
        err.textContent = 'Passwoerter stimmen nicht ueberein.';
        err.style.display = 'block';
        return false;
    }
    err.style.display = 'none';
    return true;
}
</script>
