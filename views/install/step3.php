<h2>Administrator-Account</h2>
<p>Erstelle den ersten Administrator-Account.</p>

<form method="post" class="install-form">
    <div class="form-group">
        <label for="admin_name">Name</label>
        <input type="text" id="admin_name" name="admin_name"
               value="<?= htmlspecialchars($data['admin']['name'] ?? '') ?>"
               placeholder="Max Mustermann" required>
    </div>

    <div class="form-group">
        <label for="admin_email">E-Mail</label>
        <input type="email" id="admin_email" name="admin_email"
               value="<?= htmlspecialchars($data['admin']['email'] ?? '') ?>"
               placeholder="admin@example.com" required>
    </div>

    <div class="form-group">
        <label for="admin_password">Passwort</label>
        <input type="password" id="admin_password" name="admin_password"
               placeholder="Mindestens 8 Zeichen" required minlength="8">
        <div class="password-strength" id="password-strength"></div>
        <small>Mindestens 8 Zeichen, Groß-/Kleinbuchstaben und Zahlen</small>
    </div>

    <div class="form-actions">
        <a href="?step=2" class="btn btn-secondary">Zurück</a>
        <button type="submit" class="btn btn-primary">Weiter</button>
    </div>
</form>
