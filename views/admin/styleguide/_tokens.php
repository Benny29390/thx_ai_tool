<?php
/**
 * Styleguide-Reiter "Tokens & Live-Tuning" — Token-Tuner + Live-Showcase.
 * (Frueher der Design-Tab unter /admin/settings?tab=design — dorthin verschoben.)
 *
 * Pro Größen-Token ein 5-stufiger Stepper (XS · S · M · L · XL).
 * Werte werden sofort auf <html> als CSS-Variablen geschrieben.
 * Persistenz: localStorage. „In Doku schreiben" speichert die finale
 * Auswahl als Markdown-Block in docs/design-system.md.
 *
 * Vorgabe siehe docs/design-system.md
 */
?>

<script src="/assets/js/vendor/alpine.min.js" defer></script>
<div x-data="designTab()" x-init="init()" x-cloak class="design-page">

<div class="design-grid">

<!-- LINKS: Tuner + Density + Farben -->
<div class="design-controls">

<!-- Density-Profil -->
<section class="thx-card design-section">
    <header class="design-section-head">
        <h2 class="design-h2">Dichte-Profil</h2>
        <p class="design-sub">Setzt alle Werte unten gleichzeitig. Feintuning per Stepper überschreibt das Profil.</p>
    </header>
    <div class="density-row">
        <template x-for="opt in dichten" :key="opt.id">
            <button class="density-pill"
                    :class="density === opt.id ? 'is-active' : ''"
                    @click="setDensity(opt.id)">
                <strong x-text="opt.label"></strong>
                <span x-text="opt.sub"></span>
            </button>
        </template>
    </div>
</section>

<!-- Token-Tuner -->
<section class="thx-card design-section">
    <header class="design-section-head">
        <h2 class="design-h2">Feintuning</h2>
        <p class="design-sub">Pro Größe 5 Stufen. Stufe „M" ist die Empfehlung. Live-Vorschau rechts.</p>
    </header>

    <template x-for="t in tokens" :key="t.id">
        <div class="tuner-row">
            <div class="tuner-label">
                <span x-text="t.label"></span>
                <code class="tuner-token">
                    <span x-text="t.css"></span>
                    <span class="tuner-current" x-text="' = ' + t.stufen[stufenIdx[t.id] ?? 2].value"></span>
                </code>
            </div>
            <div class="tuner-stepper">
                <template x-for="(stufe, idx) in t.stufen" :key="idx">
                    <button class="tuner-step"
                            :class="stufenIdx[t.id] === idx ? 'is-active' : ''"
                            @click="setToken(t.id, idx)"
                            :title="stufe.label + ': ' + stufe.value">
                        <span x-text="stufe.label"></span>
                        <small x-text="stufe.preview || stufe.value"></small>
                    </button>
                </template>
            </div>
        </div>
    </template>

    <div class="design-actions">
        <button class="thx-btn thx-btn-secondary" @click="resetTokens()">↺ Zurück auf Profil</button>
        <button class="thx-btn thx-btn-primary" @click="exportDoku()">📋 Werte als Doku-Block</button>
    </div>
    <details x-show="dokuOffen" x-cloak style="margin-top:12px;">
        <summary class="muted" style="cursor:pointer;font-size:var(--d-fs-xs);">Markdown-Block (in Zwischenablage kopiert)</summary>
        <textarea x-ref="dokuOut" readonly class="doku-out" :value="dokuMd"></textarea>
    </details>
</section>

<!-- Farben (kompakt) -->
<section class="thx-card design-section">
    <header class="design-section-head">
        <h2 class="design-h2">Farben</h2>
        <p class="design-sub">Klick = Hex kopieren.</p>
    </header>
    <template x-for="(palette, name) in palettes" :key="name">
        <div class="color-row">
            <div class="color-row-label" x-text="name"></div>
            <div class="color-swatches">
                <template x-for="stop in palette" :key="stop.token">
                    <button class="color-swatch" :style="'background:' + stop.hex"
                            @click="kopiere(stop.hex)"
                            :title="stop.token + ' = ' + stop.hex">
                        <span class="color-swatch-token" x-text="stop.stop"></span>
                    </button>
                </template>
            </div>
        </div>
    </template>
</section>

</div>

<!-- RECHTS: Live-Vorschau, sticky -->
<div class="design-preview">
    <section class="thx-card design-section">
        <header class="design-section-head">
            <h2 class="design-h2">Live-Vorschau</h2>
            <p class="design-sub">Die Beispiele unten reagieren live auf jede Änderung.</p>
        </header>

        <!-- Mess-Box: Card-Padding visualisiert -->
        <div class="thx-section">
            <div class="design-sub" style="margin-bottom:4px;">Card-Padding · <code x-text="curr('card-pad')"></code></div>
            <div class="messbox">
                <div class="messbox-inner">Inhalt der Card</div>
            </div>
        </div>

        <!-- Mess-Liste: Row-Padding visualisiert -->
        <div class="thx-section">
            <div class="design-sub" style="margin-bottom:4px;">Row-Padding · Y <code x-text="curr('row-pad-y')"></code> · X <code x-text="curr('row-pad-x')"></code></div>
            <div class="messliste">
                <div class="messliste-row"><span>Listen-Zeile</span><span class="muted">Meta</span></div>
                <div class="messliste-row"><span>Listen-Zeile</span><span class="muted">Meta</span></div>
                <div class="messliste-row"><span>Listen-Zeile</span><span class="muted">Meta</span></div>
            </div>
        </div>

        <!-- Page-Header mit Struktur-Labels -->
        <div class="thx-section">
            <div class="thx-page-header">
                <div>
                    <h1 class="thx-page-title">H1 Page-Title</h1>
                    <div class="thx-page-subtitle">Subtitle / Page-Subtext</div>
                </div>
                <div class="thx-page-actions">
                    <button class="thx-btn thx-btn-secondary">Btn Secondary</button>
                    <button class="thx-btn thx-btn-primary">Btn Primary</button>
                </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <input type="text" class="thx-input" placeholder="Input-Feld" style="max-width:180px;">
                <select class="thx-select" style="max-width:140px;"><option>Select-Feld</option></select>
                <button class="thx-btn thx-btn-secondary">Btn Filter</button>
            </div>
        </div>

        <!-- Tabelle / Liste mit Struktur-Labels -->
        <div class="thx-section">
            <div class="thx-card thx-card-flush">
                <div class="thx-row" style="background:var(--slate-50);font-size:var(--d-fs-xs);font-weight:700;text-transform:uppercase;color:var(--slate-600);">
                    <span style="flex:1;">Spalte A</span>
                    <span style="flex:1;">Spalte B</span>
                    <span style="width:90px;text-align:right;">Status</span>
                </div>
                <div class="thx-row thx-row-clickable">
                    <span style="flex:1;">P Body-Text</span>
                    <span style="flex:1;color:var(--slate-500);">P Body Muted</span>
                    <span style="width:90px;text-align:right;"><span class="preview-badge ok">Badge ok</span></span>
                </div>
                <div class="thx-row thx-row-clickable is-active">
                    <span style="flex:1;">P Body-Text (aktiv)</span>
                    <span style="flex:1;color:var(--slate-500);">P Body Muted</span>
                    <span style="width:90px;text-align:right;"><span class="preview-badge muted">Badge neutral</span></span>
                </div>
                <div class="thx-row thx-row-clickable">
                    <span style="flex:1;">P Body-Text</span>
                    <span style="flex:1;color:var(--slate-500);">P Body Muted</span>
                    <span style="width:90px;text-align:right;"><span class="preview-badge warn">Badge warn</span></span>
                </div>
            </div>
        </div>

        <!-- Card mit Padding + Buttons -->
        <div class="thx-section">
            <div class="thx-card">
                <h3 class="thx-card-title">H3 Card-Titel</h3>
                <p class="thx-card-sub">P Body-Text small · zeigt <code>--d-card-pad</code> rundum und <code>--d-card-radius</code> als Ecken.</p>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="thx-btn thx-btn-primary">Btn Primary</button>
                    <button class="thx-btn thx-btn-secondary">Btn Secondary</button>
                    <button class="thx-btn thx-btn-accent">Btn Accent</button>
                    <button class="thx-btn thx-btn-danger">Btn Danger</button>
                    <button class="thx-btn thx-btn-success">Btn Success</button>
                </div>
            </div>
        </div>

        <!-- Stat-Cards mit Struktur-Labels -->
        <div class="thx-section" style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-2);">
            <div class="thx-stat-card">
                <span class="thx-stat-label">Stat-Label</span>
                <span class="thx-stat-value">H2 Wert</span>
            </div>
            <div class="thx-stat-card">
                <span class="thx-stat-label">Stat-Label</span>
                <span class="thx-stat-value">H2 Wert</span>
            </div>
            <div class="thx-stat-card">
                <span class="thx-stat-label">Stat-Label</span>
                <span class="thx-stat-value">H2 Wert</span>
            </div>
        </div>

        <!-- Typografie-Skala kompakt -->
        <div class="thx-section">
            <div class="design-sub" style="margin-bottom:4px;">Typografie · Skala = <code x-text="curr('scale')"></code></div>
            <div class="thx-card" style="display:flex;flex-direction:column;gap:4px;">
                <div style="font-size:var(--d-fs-2xl); font-weight:700;">H1 / fs-2xl</div>
                <div style="font-size:var(--d-fs-xl);  font-weight:700;">H2 / fs-xl</div>
                <div style="font-size:var(--d-fs-lg);  font-weight:600;">H3 / fs-lg</div>
                <div style="font-size:var(--d-fs-base);">P Body / fs-base</div>
                <div style="font-size:var(--d-fs-sm);">P Small / fs-sm</div>
                <div style="font-size:var(--d-fs-xs); color:var(--slate-500);">Meta / fs-xs</div>
            </div>
        </div>

    </section>
</div>

</div>

</div>

<style>
[x-cloak] { display: none !important; }
.design-page { padding: 0; }
.design-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(360px, 480px);
    gap: var(--space-3);
    align-items: start;
}
.design-controls { min-width: 0; display: flex; flex-direction: column; gap: var(--space-3); }
.design-preview { position: sticky; top: 60px; align-self: start; }

.design-section { padding: var(--space-3) var(--space-4); }
.design-section-head { margin-bottom: var(--space-3); }
.design-h2 { margin: 0; font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-900); }
.design-sub { margin: 2px 0 0; font-size: var(--d-fs-xs); color: var(--slate-500); }
.design-sub code { background: var(--slate-100); padding: 1px 5px; border-radius: 3px; font-family: var(--font-mono); font-size: 0.9em; }

/* Density-Pills */
.density-row { display: flex; gap: 6px; }
.density-pill {
    flex: 1; padding: 8px 10px; background: #fff; border: 1.5px solid var(--slate-200);
    border-radius: var(--radius-md); cursor: pointer; text-align: left;
    transition: all 0.12s; display: flex; flex-direction: column; gap: 2px;
}
.density-pill:hover { border-color: var(--thoxan-300); background: var(--thoxan-50); }
.density-pill.is-active { border-color: var(--thoxan-600); background: var(--thoxan-50); }
.density-pill strong { font-size: var(--d-fs-sm); color: var(--slate-900); }
.density-pill span { font-size: var(--d-fs-xs); color: var(--slate-500); }

/* Stepper */
.tuner-row { display: grid; grid-template-columns: 200px 1fr; gap: var(--space-3); align-items: center; padding: 6px 0; border-bottom: 1px dashed var(--slate-100); }
.tuner-row:last-of-type { border-bottom: none; }
.tuner-label { display: flex; flex-direction: column; gap: 1px; }
.tuner-label > span { font-size: var(--d-fs-sm); color: var(--slate-800); font-weight: 500; }
.tuner-token { font-family: var(--font-mono); font-size: var(--d-fs-xs); color: var(--slate-500); display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.tuner-current { color: var(--thoxan-700); font-weight: 700; }
.tuner-stepper { display: flex; gap: 2px; background: var(--slate-100); padding: 2px; border-radius: var(--radius-md); }
.tuner-step {
    flex: 1; padding: 4px 2px; background: transparent; border: none; cursor: pointer;
    border-radius: 4px; display: flex; flex-direction: column; align-items: center; gap: 1px;
    transition: all 0.1s; color: var(--slate-600);
}
.tuner-step:hover { background: rgba(255,255,255,0.6); color: var(--slate-900); }
.tuner-step.is-active { background: #fff; color: var(--thoxan-700); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
.tuner-step span { font-size: var(--d-fs-xs); font-weight: 700; }
.tuner-step small { font-size: var(--d-fs-xs); color: var(--slate-500); font-family: var(--font-mono); }
.tuner-step.is-active small { color: var(--thoxan-600); }

.design-actions { display: flex; gap: 6px; justify-content: flex-end; margin-top: var(--space-3); padding-top: var(--space-2); border-top: 1px solid var(--slate-100); }
.doku-out { width: 100%; min-height: 200px; padding: 8px; font-family: var(--font-mono); font-size: var(--d-fs-xs); border: 1px solid var(--slate-200); border-radius: var(--radius-sm); resize: vertical; }

/* Farben */
.color-row { display: flex; align-items: center; gap: var(--space-2); margin-bottom: 6px; }
.color-row-label { width: 70px; flex-shrink: 0; font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--slate-600); }
.color-swatches { display: flex; gap: 2px; flex-wrap: wrap; }
.color-swatch { position: relative; width: 38px; height: 38px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); cursor: pointer; padding: 0; overflow: hidden; transition: transform 0.1s; }
.color-swatch:hover { transform: scale(1.12); z-index: 2; box-shadow: var(--shadow-md); }
.color-swatch-token { position: absolute; bottom: 1px; left: 3px; font-size: var(--d-fs-xs); font-weight: 700; color: rgba(255,255,255,0.92); text-shadow: 0 1px 1px rgba(0,0,0,0.4); }

/* Mess-Boxen: zeigen Padding und Row-Padding visuell eindeutig */
.messbox {
    background: var(--amber-100);  /* Padding-Bereich = orange */
    border: 1px solid var(--amber-300);
    border-radius: var(--d-card-radius);
    padding: var(--d-card-pad);     /* HIER greift --d-card-pad sichtbar */
}
.messbox-inner {
    background: #fff;
    border: 1px dashed var(--slate-400);
    padding: 8px;
    text-align: center;
    font-size: var(--d-fs-sm);
    color: var(--slate-700);
}
.messliste {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}
.messliste-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--d-row-pad-y) var(--d-row-pad-x);  /* HIER greifen --d-row-pad-* */
    background: linear-gradient(to right, var(--emerald-50) 0, var(--emerald-50) 100%);
    border-bottom: 1px solid var(--slate-100);
    font-size: var(--d-fs-sm);
}
.messliste-row:last-child { border-bottom: none; }
.messliste-row .muted { color: var(--slate-500); font-size: var(--d-fs-xs); }

/* Preview-Komponenten */
.preview-frame { background: var(--slate-50); border: 1px dashed var(--slate-300); border-radius: var(--radius-md); padding: var(--space-3); }
.preview-tbl { background: #fff; border-radius: var(--radius-sm); overflow: hidden; }
.preview-tbl-head, .preview-tbl-row {
    display: grid; grid-template-columns: 1.5fr 1fr 1fr;
    padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x);
    align-items: center; font-size: var(--d-fs-sm);
}
.preview-tbl-head { background: var(--slate-100); font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--slate-600); }
.preview-tbl-row { border-top: 1px solid var(--slate-100); }
.preview-tbl-row:hover { background: var(--slate-50); }
.preview-tbl .muted { color: var(--slate-500); }
.preview-badge { font-size: var(--d-fs-xs); padding: 1px 7px; border-radius: 999px; font-weight: 600; }
.preview-badge.ok { background: var(--emerald-100); color: var(--emerald-800); }
.preview-badge.warn { background: var(--amber-100); color: var(--amber-800); }
.preview-badge.muted { background: var(--slate-200); color: var(--slate-600); }

/* Design-Sections im linken Panel: eigenes Padding (nicht vom Tuner abhängig),
   damit der Tab selbst stabil bleibt, während die rechte Vorschau live tanzt. */
.design-section.thx-card { padding: 12px 14px; }

@media (max-width: 1100px) {
    .design-grid { grid-template-columns: 1fr; }
    .design-preview { position: static; }
}
</style>

<script>
function designTab() {
    return {
        density: 'comfortable',
        stufenIdx: {},      // { tokenId: aktiveStufenIndex }
        dokuOffen: false,
        dokuMd: '',

        dichten: [
            { id: 'compact',     label: 'Compact',     sub: 'maximal dicht' },
            { id: 'comfortable', label: 'Comfortable', sub: 'Standard' },
            { id: 'spacious',    label: 'Spacious',    sub: 'mehr Luft' },
        ],

        // Pro Token: 5 Stufen. M ist die Empfehlung pro Profil.
        // Beim Klick wird `var = stufe.value` als Inline-Style auf <html> gesetzt.
        tokens: [
            { id: 'scale', label: 'Master-Skala', css: '--d-scale', stufen: [
                { label: 'XS', value: '0.75' },  { label: 'S', value: '0.85' },
                { label: 'M', value: '1.0' },    { label: 'L', value: '1.1' },
                { label: 'XL', value: '1.2' },
            ]},
            { id: 'page-title', label: 'Page-Title (H1)', css: '--d-page-title-fs', stufen: [
                { label: 'XS', value: '0.875rem' }, { label: 'S', value: '1rem' },
                { label: 'M', value: '1.125rem' },  { label: 'L', value: '1.25rem' },
                { label: 'XL', value: '1.5rem' },
            ]},
            { id: 'page-sub', label: 'Page-Subtext', css: '--d-page-sub-fs', stufen: [
                { label: 'XS', value: '0.6875rem' }, { label: 'S', value: '0.75rem' },
                { label: 'M', value: '0.8125rem' },  { label: 'L', value: '0.875rem' },
                { label: 'XL', value: '1rem' },
            ]},
            { id: 'control-h', label: 'Button-Höhe', css: '--d-control-h', stufen: [
                { label: 'XS', value: '1.5rem' },   { label: 'S', value: '1.625rem' },
                { label: 'M', value: '1.75rem' },   { label: 'L', value: '2rem' },
                { label: 'XL', value: '2.25rem' },
            ]},
            { id: 'control-pad', label: 'Button-Padding-X', css: '--d-control-pad-x', stufen: [
                { label: 'XS', value: '0.375rem' }, { label: 'S', value: '0.5rem' },
                { label: 'M', value: '0.625rem' },  { label: 'L', value: '0.75rem' },
                { label: 'XL', value: '1rem' },
            ]},
            { id: 'control-fs', label: 'Button-/Input-Schrift', css: '--d-control-fs', stufen: [
                { label: 'XS', value: '0.6875rem' }, { label: 'S', value: '0.75rem' },
                { label: 'M', value: '0.8125rem' },  { label: 'L', value: '0.875rem' },
                { label: 'XL', value: '0.9375rem' },
            ]},
            { id: 'card-pad', label: 'Card-Padding', css: '--d-card-pad', stufen: [
                { label: 'XS', value: '0.375rem' }, { label: 'S', value: '0.5rem' },
                { label: 'M', value: '0.625rem' },  { label: 'L', value: '0.875rem' },
                { label: 'XL', value: '1.25rem' },
            ]},
            { id: 'row-pad-y', label: 'Row-Padding-Y', css: '--d-row-pad-y', stufen: [
                { label: 'XS', value: '0.125rem' }, { label: 'S', value: '0.25rem' },
                { label: 'M', value: '0.375rem' },  { label: 'L', value: '0.5rem' },
                { label: 'XL', value: '0.75rem' },
            ]},
            { id: 'row-pad-x', label: 'Row-Padding-X', css: '--d-row-pad-x', stufen: [
                { label: 'XS', value: '0.375rem' }, { label: 'S', value: '0.5rem' },
                { label: 'M', value: '0.625rem' },  { label: 'L', value: '0.875rem' },
                { label: 'XL', value: '1.25rem' },
            ]},
            { id: 'tbl-pad-y', label: 'Tabellen-Padding-Y', css: '--d-tbl-pad-y', stufen: [
                { label: 'XS', value: '0.125rem' }, { label: 'S', value: '0.25rem' },
                { label: 'M', value: '0.375rem' },  { label: 'L', value: '0.5rem' },
                { label: 'XL', value: '0.75rem' },
            ]},
            { id: 'page-header-mb', label: 'Page-Header-Abstand', css: '--d-page-header-mb', stufen: [
                { label: 'XS', value: '0.375rem' }, { label: 'S', value: '0.5rem' },
                { label: 'M', value: '0.75rem' },   { label: 'L', value: '1rem' },
                { label: 'XL', value: '1.5rem' },
            ]},
            { id: 'section-gap', label: 'Section-Gap', css: '--d-section-gap', stufen: [
                { label: 'XS', value: '0.375rem' }, { label: 'S', value: '0.5rem' },
                { label: 'M', value: '0.75rem' },   { label: 'L', value: '1rem' },
                { label: 'XL', value: '1.5rem' },
            ]},
            { id: 'card-radius', label: 'Card-Radius', css: '--d-card-radius', stufen: [
                { label: 'XS', value: '0' },        { label: 'S', value: '3px' },
                { label: 'M', value: '6px' },       { label: 'L', value: '8px' },
                { label: 'XL', value: '12px' },
            ]},
            { id: 'control-radius', label: 'Button-Radius', css: '--d-control-radius', stufen: [
                { label: 'XS', value: '0' },        { label: 'S', value: '3px' },
                { label: 'M', value: '5px' },       { label: 'L', value: '8px' },
                { label: 'XL', value: '999px' },
            ]},
        ],

        palettes: {
            Thoxan: [
                { stop: '50', hex: '#e6f0f8', token: '--thoxan-50' }, { stop: '100', hex: '#cce1f1', token: '--thoxan-100' },
                { stop: '200', hex: '#99c3e3', token: '--thoxan-200' }, { stop: '300', hex: '#66a5d5', token: '--thoxan-300' },
                { stop: '400', hex: '#3387c7', token: '--thoxan-400' }, { stop: '500', hex: '#006fb9', token: '--thoxan-500' },
                { stop: '600', hex: '#005da8', token: '--thoxan-600' }, { stop: '700', hex: '#004a86', token: '--thoxan-700' },
                { stop: '800', hex: '#003864', token: '--thoxan-800' }, { stop: '900', hex: '#002542', token: '--thoxan-900' },
            ],
            Slate: [
                { stop: '50', hex: '#f8fafc', token: '--slate-50' }, { stop: '100', hex: '#f1f5f9', token: '--slate-100' },
                { stop: '200', hex: '#e2e8f0', token: '--slate-200' }, { stop: '300', hex: '#cbd5e1', token: '--slate-300' },
                { stop: '400', hex: '#94a3b8', token: '--slate-400' }, { stop: '500', hex: '#64748b', token: '--slate-500' },
                { stop: '600', hex: '#475569', token: '--slate-600' }, { stop: '700', hex: '#334155', token: '--slate-700' },
                { stop: '800', hex: '#1e293b', token: '--slate-800' }, { stop: '900', hex: '#0f172a', token: '--slate-900' },
            ],
            Emerald: [
                { stop: '100', hex: '#d1fae5', token: '--emerald-100' }, { stop: '500', hex: '#10b981', token: '--emerald-500' },
                { stop: '600', hex: '#059669', token: '--emerald-600' }, { stop: '700', hex: '#047857', token: '--emerald-700' },
                { stop: '800', hex: '#065f46', token: '--emerald-800' },
            ],
            Amber: [
                { stop: '100', hex: '#fef3c7', token: '--amber-100' }, { stop: '500', hex: '#f59e0b', token: '--amber-500' },
                { stop: '600', hex: '#d97706', token: '--amber-600' }, { stop: '700', hex: '#b45309', token: '--amber-700' },
                { stop: '800', hex: '#92400e', token: '--amber-800' },
            ],
            Rose: [
                { stop: '100', hex: '#ffe4e6', token: '--rose-100' }, { stop: '500', hex: '#f43f5e', token: '--rose-500' },
                { stop: '600', hex: '#e11d48', token: '--rose-600' }, { stop: '700', hex: '#be123c', token: '--rose-700' },
                { stop: '800', hex: '#9f1239', token: '--rose-800' },
            ],
        },

        init() {
            // Density laden
            try { this.density = localStorage.getItem('thx_density') || 'comfortable'; } catch (e) {}
            // Token-Overrides laden + anwenden
            try {
                const ov = JSON.parse(localStorage.getItem('thx_token_overrides') || '{}');
                for (const t of this.tokens) {
                    if (ov[t.id] !== undefined) {
                        this.stufenIdx[t.id] = ov[t.id];
                        document.documentElement.style.setProperty(t.css, t.stufen[ov[t.id]].value);
                    } else {
                        // Default = M (Index 2)
                        this.stufenIdx[t.id] = 2;
                    }
                }
            } catch (e) {
                for (const t of this.tokens) this.stufenIdx[t.id] = 2;
            }
        },

        setDensity(id) {
            try { localStorage.setItem('thx_density', id); } catch (e) {}
            document.documentElement.setAttribute('data-density', id);
            this.density = id;
            // Token-Overrides verwerfen, damit Density-Profil greift
            try { localStorage.removeItem('thx_token_overrides'); } catch (e) {}
            for (const t of this.tokens) {
                document.documentElement.style.removeProperty(t.css);
                this.stufenIdx[t.id] = 2;
            }
        },

        setToken(tokenId, idx) {
            const t = this.tokens.find(x => x.id === tokenId);
            if (!t) return;
            this.stufenIdx[tokenId] = idx;
            document.documentElement.style.setProperty(t.css, t.stufen[idx].value);
            // Persistieren
            try {
                const ov = JSON.parse(localStorage.getItem('thx_token_overrides') || '{}');
                ov[tokenId] = idx;
                localStorage.setItem('thx_token_overrides', JSON.stringify(ov));
            } catch (e) {}
        },

        resetTokens() {
            try { localStorage.removeItem('thx_token_overrides'); } catch (e) {}
            for (const t of this.tokens) {
                document.documentElement.style.removeProperty(t.css);
                this.stufenIdx[t.id] = 2;
            }
        },

        exportDoku() {
            const lines = [
                '## Gewählte Token-Konfiguration',
                '',
                '**Density-Profil:** `' + this.density + '`',
                '',
                '| Token | Stufe | Wert | Engere Variante | Weitere Variante |',
                '|-------|-------|------|-----------------|------------------|',
            ];
            for (const t of this.tokens) {
                const idx = this.stufenIdx[t.id] ?? 2;
                const s = t.stufen[idx];
                const enger = idx > 0 ? t.stufen[idx - 1] : null;
                const weiter = idx < t.stufen.length - 1 ? t.stufen[idx + 1] : null;
                lines.push(
                    '| `' + t.css + '` ' +
                    '| ' + s.label +
                    ' | `' + s.value + '`' +
                    ' | ' + (enger ? '`' + enger.value + '` (' + enger.label + ')' : '—') +
                    ' | ' + (weiter ? '`' + weiter.value + '` (' + weiter.label + ')' : '—') +
                    ' |'
                );
            }
            this.dokuMd = lines.join('\n');
            this.dokuOffen = true;
            navigator.clipboard?.writeText(this.dokuMd);
            if (window.App?.showNotification) App.showNotification('Markdown-Block kopiert', 'success');
        },

        kopiere(text) {
            navigator.clipboard?.writeText(text);
            if (window.App?.showNotification) App.showNotification(text + ' kopiert', 'info');
        },

        // Liefert den aktuellen Wert eines Tokens (für Live-Anzeigen in der Vorschau)
        curr(tokenId) {
            const t = this.tokens.find(x => x.id === tokenId);
            if (!t) return '';
            const idx = this.stufenIdx[tokenId] ?? 2;
            return t.stufen[idx]?.value || '';
        },
    };
}
</script>
