<h2>Datenbank-Konfiguration</h2>
<p>Gib die Zugangsdaten für die MySQL-Datenbank ein.</p>

<form method="post" class="install-form">
    <div class="form-group">
        <label for="db_host">Host</label>
        <input type="text" id="db_host" name="db_host"
               value="<?= htmlspecialchars($data['db']['host'] ?? 'localhost') ?>"
               placeholder="localhost" required>
    </div>

    <div class="form-group">
        <label for="db_port">Port</label>
        <input type="number" id="db_port" name="db_port"
               value="<?= htmlspecialchars($data['db']['port'] ?? '3306') ?>"
               placeholder="3306" required>
    </div>

    <div class="form-group">
        <label for="db_name">Datenbankname</label>
        <input type="text" id="db_name" name="db_name"
               value="<?= htmlspecialchars($data['db']['name'] ?? 'ki_tool') ?>"
               placeholder="ki_tool" required>
        <small>Wird erstellt falls nicht vorhanden</small>
    </div>

    <div class="form-group">
        <label for="db_user">Benutzername</label>
        <input type="text" id="db_user" name="db_user"
               value="<?= htmlspecialchars($data['db']['user'] ?? '') ?>"
               placeholder="root" required>
    </div>

    <div class="form-group">
        <label for="db_pass">Passwort</label>
        <input type="password" id="db_pass" name="db_pass"
               value="<?= htmlspecialchars($data['db']['pass'] ?? '') ?>"
               placeholder="Passwort">
    </div>

    <div class="form-actions">
        <a href="?step=1" class="btn btn-secondary">Zurück</a>
        <button type="submit" class="btn btn-primary">Verbindung testen & Weiter</button>
    </div>
</form>
