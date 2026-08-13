<?php
$logDir = '/var/www/storage/logs';
$logs = [
    'requests' => ['file' => $logDir . '/requests.log', 'label' => 'Alle Requests', 'icon' => 'list_alt'],
    'slow'     => ['file' => $logDir . '/slow.log',     'label' => 'Langsam / Heavy', 'icon' => 'speed'],
    'errors'   => ['file' => $logDir . '/errors.log',   'label' => 'Fatal-Errors', 'icon' => 'error'],
];
$active = $_GET['log'] ?? 'requests';
$current = $logs[$active] ?? $logs['requests'];
$tail = '(noch leer — wird beim nächsten Request geschrieben)';
$size = 0;
try {
    if (@is_file($current['file'])) {
        $size = (int) @filesize($current['file']);
        // Tail-Read: nur letzte ~200KB lesen, dann Zeilen schneiden — bei großem File deutlich schneller
        $fp = @fopen($current['file'], 'r');
        if ($fp) {
            $readBytes = min($size, 200 * 1024);
            if ($size > $readBytes) @fseek($fp, -$readBytes, SEEK_END);
            $chunk = @fread($fp, $readBytes) ?: '';
            @fclose($fp);
            $lines = $chunk !== '' ? explode("\n", $chunk) : [];
            if (count($lines) > 0 && $size > $readBytes) array_shift($lines);
            $lines = array_slice($lines, -300);
            $tail = implode("\n", $lines);
        }
    }
} catch (\Throwable $e) {
    $tail = '(Fehler beim Lesen: ' . htmlspecialchars($e->getMessage()) . ')';
}
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:22px;">monitor_heart</span>
            System-Log
        </h1>
        <p class="thx-page-subtitle">
            Spalten: Zeit | Method URI | Status | Dauer | Peak-Memory | User-ID | IP | [Fehler]
        </p>
    </div>
    <div class="thx-page-actions">
        <label style="display:inline-flex;align-items:center;gap:6px;font-size:var(--d-fs-xs);color:var(--slate-500);">
            <input type="checkbox" id="sl-auto" onchange="slToggleAuto()"> Auto-Refresh (5s)
        </label>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="location.reload()">
            <span class="material-symbols-rounded" style="font-size:14px;">refresh</span>
            Neu laden
        </button>
    </div>
</div>

<div class="sl-page">

    <div class="thx-tabs" style="margin-bottom:var(--d-gutter);">
        <?php foreach ($logs as $key => $info): ?>
            <a href="?log=<?= $key ?>" class="thx-tab <?= $active === $key ? 'is-active' : '' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;"><?= $info['icon'] ?></span>
                <?= htmlspecialchars($info['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="thx-card thx-card-flush">
        <div class="sl-meta">
            <span><strong><?= htmlspecialchars($current['label']) ?></strong></span>
            <span style="color:var(--slate-400);">·</span>
            <span class="sl-meta-icon material-symbols-rounded">description</span>
            <code><?= htmlspecialchars($current['file']) ?></code>
            <span style="color:var(--slate-400);">·</span>
            <span class="sl-meta-icon material-symbols-rounded">database</span>
            <span><?= $size > 0 ? number_format($size / 1024, 1, ',', '.') . ' KB' : '0' ?></span>
        </div>

        <pre class="sl-pre" id="sl-pre"><?= htmlspecialchars($tail) ?></pre>
    </div>
</div>

<style>
.sl-page {
    max-width: 1400px;
}
.sl-meta {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 10px var(--d-gutter);
    border-bottom: 1px solid var(--slate-200);
    background: var(--slate-50);
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
}
.sl-meta strong { color: var(--slate-800); }
.sl-meta code {
    background: #fff;
    border: 1px solid var(--slate-200);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
    font-size: 11px;
    color: var(--slate-700);
}
.sl-meta .sl-meta-icon { font-size: 14px; color: var(--slate-400); }

.sl-pre {
    margin: 0;
    background: #0f172a; color: #cbd5e1;
    padding: 16px var(--d-gutter);
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
    font-size: 11.5px; line-height: 1.55;
    overflow: auto;
    max-height: 70vh;
    white-space: pre;
    border-bottom-left-radius: var(--d-card-radius);
    border-bottom-right-radius: var(--d-card-radius);
}
.sl-pre .slow { color: #fbbf24; }
.sl-pre .err  { color: #f87171; }
.sl-pre .fast { color: #94a3b8; }
</style>

<script>
'use strict';
function slColorize() {
    const pre = document.getElementById('sl-pre');
    if (!pre) return;
    const lines = pre.textContent.split('\n');
    const html = lines.map(line => {
        const esc = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const m = line.match(/\|\s+(\d{3})\s+\|\s+(\d+)\s*ms/);
        if (m) {
            const status = parseInt(m[1]);
            const ms = parseInt(m[2]);
            if (status >= 500 || line.includes('FATAL')) return `<span class="err">${esc}</span>`;
            if (ms >= 2000) return `<span class="slow">${esc}</span>`;
            return `<span class="fast">${esc}</span>`;
        }
        return esc;
    }).join('\n');
    pre.innerHTML = html;
    pre.scrollTop = pre.scrollHeight;
}

let slAutoTimer = null;
function slToggleAuto() {
    if (document.getElementById('sl-auto').checked) {
        slAutoTimer = setInterval(() => location.reload(), 5000);
    } else if (slAutoTimer) {
        clearInterval(slAutoTimer);
        slAutoTimer = null;
    }
}

document.addEventListener('DOMContentLoaded', slColorize);
</script>
