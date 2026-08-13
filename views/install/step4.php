<h2>KI-API-Konfiguration</h2>
<p>Gib die API-Keys für die KI-Dienste ein. Du kannst dies auch später in den Einstellungen tun.</p>

<form method="post" class="install-form">
    <div class="form-group">
        <label for="openai_key">OpenAI API Key</label>
        <input type="password" id="openai_key" name="openai_key"
               value="<?= htmlspecialchars($data['ai']['openai_key'] ?? '') ?>"
               placeholder="sk-...">
        <small>Für GPT-4 und Embeddings</small>
    </div>

    <div class="form-group">
        <label for="anthropic_key">Anthropic API Key</label>
        <input type="password" id="anthropic_key" name="anthropic_key"
               value="<?= htmlspecialchars($data['ai']['anthropic_key'] ?? '') ?>"
               placeholder="sk-ant-...">
        <small>Für Claude</small>
    </div>

    <div class="form-group">
        <label for="default_model">Standard-Modell</label>
        <select id="default_model" name="default_model">
            <option value="gpt-4" <?= ($data['ai']['default_model'] ?? 'gpt-4') === 'gpt-4' ? 'selected' : '' ?>>
                GPT-4 (OpenAI)
            </option>
            <option value="gpt-4-turbo" <?= ($data['ai']['default_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>
                GPT-4 Turbo (OpenAI)
            </option>
            <option value="claude-3-opus" <?= ($data['ai']['default_model'] ?? '') === 'claude-3-opus' ? 'selected' : '' ?>>
                Claude 3 Opus (Anthropic)
            </option>
            <option value="claude-3-sonnet" <?= ($data['ai']['default_model'] ?? '') === 'claude-3-sonnet' ? 'selected' : '' ?>>
                Claude 3 Sonnet (Anthropic)
            </option>
        </select>
    </div>

    <div class="form-actions">
        <a href="?step=3" class="btn btn-secondary">Zurück</a>
        <button type="submit" class="btn btn-primary">Weiter</button>
    </div>
</form>
