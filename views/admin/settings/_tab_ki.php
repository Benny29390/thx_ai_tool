<?php
/** KI-Tab — OpenAI, Anthropic, Google, Standard-Modell. */
$aiModels = $aiModels ?? [];
$currentModel = $valueOf('default_model', 'gpt-4');
?>
<div class="settings-card">
    <h2>KI-API-Keys</h2>
    <p class="settings-card-sub">
        Die Keys werden serverseitig gespeichert. Leeres Feld = aktuellen Key behalten.
        Eingegebene Werte sind beim Speichern verschlüsselt nicht sichtbar.
    </p>
    <form id="form-ki-keys" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-field">
            <label for="openai_api_key">
                OpenAI API Key
                <?php if ($isConfigured('openai_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="openai_api_key" name="openai_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('openai_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'sk-…' ?>">
            <p class="field-hint">Für GPT-4 / GPT-4o (Chat-Completion + Metadaten-Extraktion). Embeddings laufen ueber das lokale Modell (siehe Block „Wissensdatenbank" unten).</p>
        </div>
        <div class="settings-field">
            <label for="anthropic_api_key">
                Anthropic API Key
                <?php if ($isConfigured('anthropic_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="anthropic_api_key" name="anthropic_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('anthropic_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'sk-ant-…' ?>">
            <p class="field-hint">Für Claude (Opus, Sonnet, Haiku).</p>
        </div>
        <div class="settings-field">
            <label for="google_api_key">
                Google API Key
                <?php if ($isConfigured('google_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="google_api_key" name="google_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('google_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'AIza…' ?>">
            <p class="field-hint">
                Für Gemini-Modelle.
                <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Key erstellen</a>
            </p>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Lokaler Inference-Server</h2>
    <p class="settings-card-sub">
        Eigener OpenAI-kompatibler Server für lokale Modelle (Qwen, GPT-OSS, Llama).
        Der Key wird verschlüsselt gespeichert. Leeres Feld = aktuellen Key behalten.
    </p>
    <form id="form-local-llm" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-field">
            <label for="local_base_url">Base-URL</label>
            <input type="text" id="local_base_url" name="local_base_url" autocomplete="off"
                   value="<?= htmlspecialchars($valueOf('local_base_url', 'https://ki.thoxan.com/llm/v1')) ?>"
                   placeholder="https://ki.thoxan.com/llm/v1">
            <p class="field-hint">OpenAI-kompatibler Endpoint (ohne <code>/chat/completions</code>).</p>
        </div>
        <div class="settings-field">
            <label for="local_api_key">
                API Key
                <?php if ($isConfigured('local_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="local_api_key" name="local_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('local_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'Bearer-Token…' ?>">
            <p class="field-hint">Wird als <code>Authorization: Bearer …</code> mitgeschickt.</p>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Wissensdatenbank — Qdrant + bge-m3</h2>
    <p class="settings-card-sub">
        Vektor-Suche fuer Chat-Wissen und Steckbrief-Vorschlaege. Embedding lokal ueber <code>bge-m3</code> (1024 Dim) auf ki.thoxan.com,
        Storage in Qdrant. Health-Check und Test-Suche unter <a href="/admin/wissen-status">Wissens-Status</a>.
    </p>
    <form id="form-wissen-v2" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-field">
            <label for="qdrant_url">Qdrant-URL</label>
            <input type="text" id="qdrant_url" name="qdrant_url" autocomplete="off"
                   value="<?= htmlspecialchars($valueOf('qdrant_url', 'http://localhost:6333')) ?>"
                   placeholder="http://localhost:6333">
            <p class="field-hint">REST-Endpoint der Qdrant-Instanz (per Docker auf diesem Server).</p>
        </div>
        <div class="settings-field">
            <label for="qdrant_api_key">
                Qdrant API Key (optional)
                <?php if ($isConfigured('qdrant_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="qdrant_api_key" name="qdrant_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('qdrant_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'nur falls Qdrant Auth verlangt' ?>">
        </div>
        <div class="settings-field">
            <label for="embedding_local_url">Embeddings-URL (bge-m3)</label>
            <input type="text" id="embedding_local_url" name="embedding_local_url" autocomplete="off"
                   value="<?= htmlspecialchars($valueOf('embedding_local_url', 'https://ki.thoxan.com/embeddings/embeddings')) ?>"
                   placeholder="https://ki.thoxan.com/embeddings/embeddings">
            <p class="field-hint">OpenAI-kompatibel. Auth über denselben Key wie der lokale Inference-Server.</p>
        </div>
        <div class="settings-field">
            <label for="embedding_local_model">Embedding-Modell</label>
            <input type="text" id="embedding_local_model" name="embedding_local_model" autocomplete="off"
                   value="<?= htmlspecialchars($valueOf('embedding_local_model', 'bge-m3')) ?>"
                   placeholder="bge-m3">
            <p class="field-hint">Achtung: Modellwechsel ändert die Dimension → Qdrant-Collection neu aufbauen.</p>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Reranking (RAG-Treffer neu sortieren)</h2>
    <p class="settings-card-sub">
        Holt mehr Wissens-Kandidaten und lässt sie von einem spezialisierten Modell nach echter
        Relevanz neu sortieren — nur die besten gehen ans Sprachmodell. Hebt die Antwortqualität
        spürbar und hält Rauschen draußen. Funktioniert lokal (Bennys GPU) oder über eine Cloud-API.
    </p>
    <form id="form-rerank" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Reranking-Einstellungen gespeichert', reloadOnSuccess: true });">
        <div class="settings-field">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" id="rerank_enabled" name="rerank_enabled" value="1" style="width:auto;"
                       <?= $valueOf('rerank_enabled', '0') === '1' ? 'checked' : '' ?>>
                Reranking aktiv
            </label>
            <p class="field-hint">Wenn aus, bleibt alles wie bisher (Top 10 direkt aus der Vektor-Suche).</p>
        </div>
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="rerank_provider">Anbieter</label>
                <?php $rp = $valueOf('rerank_provider', 'local'); ?>
                <select id="rerank_provider" name="rerank_provider">
                    <option value="local"  <?= $rp === 'local'  ? 'selected' : '' ?>>Lokal (eigene GPU, z.B. bge-reranker-v2-m3)</option>
                    <option value="cohere" <?= $rp === 'cohere' ? 'selected' : '' ?>>Cohere (rerank-v3.5)</option>
                    <option value="jina"   <?= $rp === 'jina'   ? 'selected' : '' ?>>Jina (jina-reranker-v2-base-multilingual)</option>
                    <option value="voyage" <?= $rp === 'voyage' ? 'selected' : '' ?>>Voyage (rerank-2.5)</option>
                </select>
                <p class="field-hint">Lokal = datenschutzkonform auf Eurem Server. Cloud = stärker, aber Daten verlassen das Haus.</p>
            </div>
            <div class="settings-field">
                <label for="rerank_model">Modell</label>
                <input type="text" id="rerank_model" name="rerank_model" autocomplete="off"
                       value="<?= htmlspecialchars($valueOf('rerank_model')) ?>"
                       placeholder="leer = Anbieter-Standard (z.B. bge-reranker-v2-m3)">
                <p class="field-hint">Leer lassen für den empfohlenen Standard des gewählten Anbieters.</p>
            </div>
        </div>
        <div class="settings-field">
            <label for="rerank_url">Endpoint-URL <span class="text-muted">(nur bei „Lokal" nötig)</span></label>
            <input type="text" id="rerank_url" name="rerank_url" autocomplete="off"
                   value="<?= htmlspecialchars($valueOf('rerank_url')) ?>"
                   placeholder="https://ki.thoxan.com/rerank/rerank">
            <p class="field-hint">Cohere-kompatibler <code>/rerank</code>-Endpoint (z.B. Infinity/TEI). Bei Cloud-Anbietern automatisch gesetzt.</p>
        </div>
        <div class="settings-field">
            <label for="rerank_api_key">
                API-Key <span class="text-muted">(Cloud-Anbieter)</span>
                <?php if ($isConfigured('rerank_api_key')): ?>
                    <span class="key-status ja">Gesetzt</span>
                <?php else: ?>
                    <span class="key-status nein">Nicht gesetzt</span>
                <?php endif; ?>
            </label>
            <input type="password" id="rerank_api_key" name="rerank_api_key" autocomplete="new-password"
                   placeholder="<?= $isConfigured('rerank_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'nur für Cohere/Jina/Voyage' ?>">
            <p class="field-hint">Bei „Lokal" leer lassen — dann wird der Schlüssel des lokalen Inference-Servers genutzt.</p>
        </div>
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="rerank_candidates">Kandidaten vor dem Rerank</label>
                <input type="number" id="rerank_candidates" name="rerank_candidates" min="10" max="100"
                       value="<?= htmlspecialchars($valueOf('rerank_candidates', '40')) ?>">
                <p class="field-hint">Wie breit gesucht wird (mehr = gründlicher, etwas langsamer). Empfehlung: 40.</p>
            </div>
            <div class="settings-field">
                <label for="rerank_top_n">Top-N ans Sprachmodell</label>
                <input type="number" id="rerank_top_n" name="rerank_top_n" min="1" max="30"
                       value="<?= htmlspecialchars($valueOf('rerank_top_n', '8')) ?>">
                <p class="field-hint">Wie viele der besten Treffer übrig bleiben. Empfehlung: 8.</p>
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <button type="button" class="thx-btn thx-btn-secondary" onclick="testRerank(this)">Verbindung testen</button>
            <span id="rerank-test-result" class="status-msg muted"></span>
        </div>
    </form>
</div>
<script>
async function testRerank(btn) {
    const out = document.getElementById('rerank-test-result');
    out.className = 'status-msg muted';
    out.textContent = 'Teste… (bitte vorher speichern)';
    btn.disabled = true;
    try {
        const resp = await App.request('POST', '/admin/settings', { action: 'test_rerank' });
        if (resp.success) {
            out.className = 'status-msg ok';
            out.textContent = resp.message || 'Reranker erreichbar';
        } else {
            out.className = 'status-msg err';
            out.textContent = resp.message || 'Test fehlgeschlagen';
        }
    } catch (e) {
        out.className = 'status-msg err';
        out.textContent = e.message || 'Verbindungsfehler';
    } finally {
        btn.disabled = false;
    }
}
</script>

<div class="settings-card">
    <h2>Standard-Modell (Fallback)</h2>
    <p class="settings-card-sub">
        Wird verwendet, wenn kein Modell explizit gewählt ist — z.B. Auto-Mode-Fallback im Chat.
    </p>
    <form id="form-default-model" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-field">
            <label for="default_model">Modell</label>
            <select id="default_model" name="default_model">
                <?php if (!empty($aiModels)): foreach ($aiModels as $m):
                    $provLabel = match($m['provider']) {
                        'openai' => 'OpenAI',
                        'anthropic' => 'Anthropic',
                        'google' => 'Google',
                        'local' => 'Lokal',
                        default => $m['provider']
                    };
                ?>
                    <option value="<?= htmlspecialchars($m['model_id']) ?>"
                            <?= $currentModel === $m['model_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['display_name']) ?> (<?= $provLabel ?>)
                    </option>
                <?php endforeach; else: ?>
                    <option value="gpt-4" selected>GPT-4 (OpenAI)</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <a href="/admin/models" class="thx-btn thx-btn-secondary">Modelle verwalten →</a>
        </div>
    </form>
</div>
