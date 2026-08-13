<h2>Anmelden</h2>

<form method="post" action="/login" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="form-group">
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required autofocus
               placeholder="name@example.com">
    </div>

    <div class="form-group">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required
               placeholder="Passwort">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
</form>
