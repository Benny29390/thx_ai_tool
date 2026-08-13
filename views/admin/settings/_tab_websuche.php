<?php
/** Web-Suche-Tab — Brave Search. */
?>
<div class="settings-card">
    <h2>Brave Search API</h2>
    <p class="settings-card-sub">
        Wird verwendet, wenn im Chat die Web-Suche aktiviert ist. 2.000 Suchen/Monat sind im Free-Tier kostenlos.
        <a href="https://brave.com/search/api/" target="_blank" rel="noopener">Key beantragen →</a>
    </p>
    <form id="form-brave" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-field">
            <label for="brave_search_api_key">
                Brave Search API Key
                <?php if ($isConfigured('brave_search_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="brave_search_api_key" name="brave_search_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('brave_search_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'BSA…' ?>">
            <p class="field-hint">Leeres Feld = aktuellen Key behalten.</p>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>
