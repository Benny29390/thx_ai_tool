<h2>Installation abschließen</h2>
<p>Die Installation ist bereit. Prüfe die Zusammenfassung und schließe die Installation ab.</p>

<div class="summary">
    <div class="summary-section">
        <h3>Datenbank</h3>
        <dl>
            <dt>Host</dt>
            <dd><?= htmlspecialchars($data['db']['host'] ?? '') ?>:<?= htmlspecialchars($data['db']['port'] ?? '3306') ?></dd>
            <dt>Datenbank</dt>
            <dd><?= htmlspecialchars($data['db']['name'] ?? '') ?></dd>
            <dt>Benutzer</dt>
            <dd><?= htmlspecialchars($data['db']['user'] ?? '') ?></dd>
        </dl>
    </div>

    <div class="summary-section">
        <h3>Administrator</h3>
        <dl>
            <dt>Name</dt>
            <dd><?= htmlspecialchars($data['admin']['name'] ?? '') ?></dd>
            <dt>E-Mail</dt>
            <dd><?= htmlspecialchars($data['admin']['email'] ?? '') ?></dd>
        </dl>
    </div>

    <div class="summary-section">
        <h3>KI-Konfiguration</h3>
        <dl>
            <dt>OpenAI</dt>
            <dd><?= !empty($data['ai']['openai_key']) ? 'Konfiguriert' : 'Nicht konfiguriert' ?></dd>
            <dt>Anthropic</dt>
            <dd><?= !empty($data['ai']['anthropic_key']) ? 'Konfiguriert' : 'Nicht konfiguriert' ?></dd>
            <dt>Standard-Modell</dt>
            <dd><?= htmlspecialchars($data['ai']['default_model'] ?? 'gpt-4') ?></dd>
        </dl>
    </div>
</div>

<form method="post" class="install-form">
    <div class="form-actions">
        <a href="?step=4" class="btn btn-secondary">Zurück</a>
        <button type="submit" class="btn btn-primary btn-large">Installation abschließen</button>
    </div>
</form>
