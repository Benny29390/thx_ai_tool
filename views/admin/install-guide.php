<?php
/**
 * Admin-Seite: Anleitung „Neue Kundeninstallation".
 * Rendert docs/kunden-installation.md (Single Source) mit Kopier-Knoepfen.
 * Route /admin/install-guide, nur Admin.
 */
$mdFile = ROOT_PATH . '/docs/kunden-installation.md';
$md = is_file($mdFile) ? (string) file_get_contents($mdFile) : '# Anleitung nicht gefunden';
?>
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">rocket_launch</span>
            Neue Kundeninstallation
        </h1>
        <p class="thx-page-subtitle">
            Schritt-für-Schritt-Anleitung, um die Plattform für einen neuen Kunden aufzusetzen.
            Jeder Befehlsblock hat rechts einen Kopier-Knopf. <strong>Fett markierte Platzhalter</strong> vorher ersetzen.
        </p>
    </div>
</div>

<div class="thx-card guide-wrap">
    <div id="guide-md" class="guide-md">Lädt…</div>
</div>

<style>
.guide-wrap { max-width: 900px; }
.guide-md h1 { font-size: 1.5rem; margin: 0 0 12px; color: var(--slate-900); }
.guide-md h2 { font-size: 1.15rem; margin: 26px 0 10px; padding-top: 16px; border-top: 1px solid var(--slate-200); color: var(--thoxan-700); }
.guide-md h1 + h2, .guide-md > h2:first-child { border-top: none; padding-top: 0; }
.guide-md p { line-height: 1.6; color: var(--slate-700); }
.guide-md ul, .guide-md ol { line-height: 1.7; color: var(--slate-700); }
.guide-md code { background: var(--slate-100); padding: 1px 6px; border-radius: 4px; font-family: var(--font-mono, monospace); font-size: .88em; }
.guide-md pre { position: relative; background: var(--slate-900); color: #e2e8f0; padding: 14px 16px; border-radius: 8px; overflow-x: auto; }
.guide-md pre code { background: none; padding: 0; color: inherit; font-size: .82rem; line-height: 1.5; white-space: pre; }
.guide-md blockquote { border-left: 4px solid var(--thoxan-300); margin: 12px 0; padding: 4px 14px; background: var(--thoxan-50); color: var(--slate-600); border-radius: 0 6px 6px 0; }
.guide-md table { border-collapse: collapse; width: 100%; margin: 12px 0; font-size: .9rem; }
.guide-md th, .guide-md td { border: 1px solid var(--slate-200); padding: 6px 10px; text-align: left; }
.guide-md th { background: var(--slate-50); }
.guide-md hr { border: none; border-top: 1px solid var(--slate-200); margin: 24px 0; }
.guide-copy { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,.12); color: #fff; border: none;
    border-radius: 5px; padding: 4px 10px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.guide-copy:hover { background: rgba(255,255,255,.24); }
.guide-copy.done { background: var(--emerald-600); }
</style>

<script src="/assets/js/vendor/marked.min.js"></script>
<script>
(function () {
    var md = <?= json_encode($md, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var box = document.getElementById('guide-md');
    if (window.marked) {
        try { window.marked.setOptions({ gfm: true, breaks: false }); } catch (e) {}
        box.innerHTML = window.marked.parse(md);
    } else {
        var pre = document.createElement('pre'); pre.textContent = md; box.innerHTML = ''; box.appendChild(pre);
    }
    // Kopier-Knopf an jeden Befehlsblock
    box.querySelectorAll('pre').forEach(function (pre) {
        var btn = document.createElement('button');
        btn.className = 'guide-copy';
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;">content_copy</span> Kopieren';
        btn.addEventListener('click', function () {
            var txt = (pre.querySelector('code') || pre).innerText;
            navigator.clipboard.writeText(txt).then(function () {
                btn.classList.add('done');
                btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;">check</span> Kopiert';
                setTimeout(function () {
                    btn.classList.remove('done');
                    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;">content_copy</span> Kopieren';
                }, 1600);
            });
        });
        pre.appendChild(btn);
    });
})();
</script>
