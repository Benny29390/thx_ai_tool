<?php
/**
 * Detail einer einzelnen LLM-Anfrage — finaler System-Prompt, User-Frage,
 * herangezogene Wissens-Chunks und die Antwort. Hilft beim Nachvollziehen,
 * WARUM eine Antwort so ausgefallen ist (z.B. fehlender/falscher Chunk).
 * Erwartet: $row (oder null), $chunks
 */
$row    = $row ?? null;
$chunks = $chunks ?? [];
$rerank = $rerank ?? null;

$provLabel = function (string $p): string {
    return match ($p) {
        'openai' => 'OpenAI', 'anthropic' => 'Claude', 'google' => 'Google', 'local' => 'Lokal', default => $p,
    };
};
?>
<div class="page-header">
    <div>
        <h1>Anfrage-Detail</h1>
        <p class="text-muted" style="margin:.25rem 0 0;">
            <a href="/admin/llm-performance" style="color:inherit;">&larr; zurück zur Performance-Übersicht</a>
        </p>
    </div>
</div>

<?php if (!$row): ?>
    <div class="card"><p class="text-muted" style="margin:0;">Diese Anfrage wurde nicht gefunden — möglicherweise sind die Detaildaten bereits nach 90 Tagen gelöscht worden.</p></div>
<?php else: ?>

<!-- Kopfdaten -->
<div class="card mb-lg">
    <dl style="margin:0;display:grid;grid-template-columns:max-content 1fr;gap:.4rem 1.5rem;">
        <dt class="text-muted">Zeitpunkt</dt>
        <dd style="margin:0;"><?= htmlspecialchars(date('d.m.Y, H:i:s', strtotime($row['created_at']))) ?></dd>
        <dt class="text-muted">Modell</dt>
        <dd style="margin:0;"><strong><?= htmlspecialchars($row['model']) ?></strong> <span class="text-muted">(<?= htmlspecialchars($provLabel($row['provider'])) ?>)</span></dd>
        <dt class="text-muted">Nutzer</dt>
        <dd style="margin:0;"><?= htmlspecialchars($row['user_name'] ?? '–') ?></dd>
        <dt class="text-muted">Kunden-Kontext</dt>
        <dd style="margin:0;"><?= htmlspecialchars($row['customer_name'] ?? '–') ?></dd>
        <dt class="text-muted">Tokens</dt>
        <dd style="margin:0;"><?= number_format((int)$row['tokens_input']) ?> rein / <?= number_format((int)$row['tokens_output']) ?> raus</dd>
        <dt class="text-muted">Dauer</dt>
        <dd style="margin:0;"><?= $row['total_ms'] !== null ? number_format($row['total_ms']) . ' ms' : '–' ?><?= $row['ttft_ms'] !== null ? ' (1. Wort nach ' . number_format($row['ttft_ms']) . ' ms)' : '' ?></dd>
        <dt class="text-muted">Status</dt>
        <dd style="margin:0;"><?= !empty($row['success']) ? '<span style="color:#16a34a;">erfolgreich</span>' : '<span style="color:#dc2626;">fehlgeschlagen</span>' ?></dd>
        <?php if (!empty($row['error_message'])): ?>
            <dt class="text-muted">Fehler</dt>
            <dd style="margin:0;color:#dc2626;"><?= htmlspecialchars($row['error_message']) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<!-- Frage -->
<div class="card mb-lg">
    <h3 class="card-title">Frage des Nutzers</h3>
    <pre style="white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;"><?= htmlspecialchars((string)($row['user_message'] ?? '')) ?></pre>
</div>

<!-- Reranking -->
<?php if ($rerank && !empty($rerank['enabled'])): ?>
<div class="card mb-lg">
    <h3 class="card-title">Reranking</h3>
    <?php if (!empty($rerank['applied'])): ?>
        <dl style="margin:0 0 1rem;display:grid;grid-template-columns:max-content 1fr;gap:.4rem 1.5rem;">
            <dt class="text-muted">Reranker</dt>
            <dd style="margin:0;"><strong><?= htmlspecialchars($rerank['model'] ?? '') ?></strong> <span class="text-muted">(<?= htmlspecialchars($rerank['provider'] ?? '') ?>)</span></dd>
            <dt class="text-muted">Kandidaten</dt>
            <dd style="margin:0;"><?= (int)($rerank['candidates'] ?? 0) ?> geprüft → <?= (int)($rerank['kept'] ?? 0) ?> behalten<?= isset($rerank['ms']) && $rerank['ms'] !== null ? ' · ' . (int)$rerank['ms'] . ' ms' : '' ?></dd>
        </dl>
        <?php $ranking = $rerank['ranking'] ?? []; if (!empty($ranking)): ?>
            <p class="text-muted" style="margin:-.25rem 0 .75rem;max-width:75ch;">Vollständige Rangliste des Rerankers. <strong>✓</strong> = ging ans Sprachmodell, ausgegraut = aussortiert. So siehst Du, was der Reranker hoch/runter gestuft hat.</p>
            <div class="table-container">
                <table>
                    <thead><tr><th></th><th>#</th><th>Titel</th><th>Quelle</th><th class="text-right">Rerank-Score</th></tr></thead>
                    <tbody>
                        <?php foreach ($ranking as $i => $r): $kept = !empty($r['kept']); $st = $r['source_type'] ?? ''; ?>
                            <tr style="<?= $kept ? '' : 'opacity:.55;' ?>">
                                <td><?= $kept ? '<span style="color:#16a34a;font-weight:700;">✓</span>' : '<span class="text-muted">·</span>' ?></td>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($r['title'] ?? 'Ohne Titel') ?></td>
                                <td><?php if ($st !== ''): $isCrm = strpos($st, 'crm') === 0; ?><span style="padding:1px 7px;border-radius:999px;font-size:.85em;background:<?= $isCrm ? '#fee2e2' : '#e0e7ff' ?>;color:<?= $isCrm ? '#991b1b' : '#3730a3' ?>;"><?= htmlspecialchars($st) ?></span><?php endif; ?></td>
                                <td class="text-right"><?= isset($r['score']) ? number_format((float)$r['score'], 3) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p style="margin:0;color:#dc2626;">Reranking war aktiv, wurde aber nicht angewandt<?= !empty($rerank['error']) ? ': ' . htmlspecialchars($rerank['error']) : '' ?>. Es galt die normale Vektor-Reihenfolge.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Herangezogene Wissens-Chunks -->
<div class="card mb-lg">
    <h3 class="card-title">Herangezogenes Wissen (<?= count($chunks) ?> Chunk<?= count($chunks) === 1 ? '' : 's' ?>)</h3>
    <p class="text-muted" style="margin:-.25rem 0 1rem;max-width:75ch;">
        Das sind die Textbausteine, die die Suche gefunden und dem Modell mitgegeben hat —
        in genau dieser Reihenfolge. Score = Ähnlichkeit zur Frage (höher = passender).
        Wenn die richtige Information hier fehlt oder weit unten steht, liegt es am Auffinden/Sortieren, nicht am Modell.
    </p>
    <?php if (empty($chunks)): ?>
        <p class="text-muted" style="margin:0;">Für diese Anfrage wurde kein Wissen herangezogen (Wissens-Toggle aus, oder kein Treffer über der Schwelle).</p>
    <?php else: foreach ($chunks as $i => $c): ?>
        <details class="card" style="padding:.75rem 1rem;margin-bottom:.6rem;<?= $i === 0 ? '' : '' ?>">
            <summary style="cursor:pointer;font-weight:600;display:flex;justify-content:space-between;gap:1rem;">
                <span><?= ($i + 1) ?>. <?= htmlspecialchars($c['title'] ?? 'Ohne Titel') ?></span>
                <span class="text-muted" style="font-weight:400;white-space:nowrap;">
                    <?php if (!empty($c['source_type'])): ?>
                        <?php $st = $c['source_type']; $isCrm = strpos($st, 'crm') === 0; ?>
                        <span style="padding:1px 7px;border-radius:999px;font-size:.85em;background:<?= $isCrm ? '#fee2e2' : '#e0e7ff' ?>;color:<?= $isCrm ? '#991b1b' : '#3730a3' ?>;"><?= htmlspecialchars($st) ?></span>
                    <?php endif; ?>
                    <?php if (isset($c['word_count']) && $c['word_count'] !== null): ?>
                        <span title="Wortzahl"><?= (int)$c['word_count'] ?> W</span> ·
                    <?php endif; ?>
                    Score <?= isset($c['score']) ? number_format((float)$c['score'], 3) : '–' ?>
                    <?php if (!empty($c['sources']) && is_array($c['sources'])): ?>
                        · <?= htmlspecialchars(implode(', ', $c['sources'])) ?>
                    <?php endif; ?>
                </span>
            </summary>
            <pre style="white-space:pre-wrap;word-break:break-word;margin:.6rem 0 0;font-family:inherit;font-size:.92em;"><?= htmlspecialchars((string)($c['content'] ?? '')) ?></pre>
        </details>
    <?php endforeach; endif; ?>
</div>

<!-- Finaler System-Prompt -->
<div class="card mb-lg">
    <details>
        <summary style="cursor:pointer;font-weight:600;font-size:1.05rem;">Vollständiger System-Prompt (so wie er ans Modell ging)</summary>
        <pre style="white-space:pre-wrap;word-break:break-word;margin:.75rem 0 0;font-family:inherit;font-size:.9em;background:var(--surface-2,#f8fafc);padding:1rem;border-radius:.5rem;"><?= htmlspecialchars((string)($row['system_prompt'] ?? '')) ?></pre>
    </details>
</div>

<!-- Antwort -->
<div class="card">
    <h3 class="card-title">Antwort des Modells</h3>
    <pre style="white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;"><?= htmlspecialchars((string)($row['response_text'] ?? '')) ?></pre>
</div>

<?php endif; ?>
