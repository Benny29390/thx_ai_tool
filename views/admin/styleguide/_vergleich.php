<?php
/**
 * Styleguide-Reiter "Soll/Ist" — dokumentiert die Abweichungen zwischen den
 * verbindlichen Marken-Standards (Soll) und den tatsaechlichen Design-Tokens
 * des Tools (Ist). Rein statische Doku-Ansicht.
 */
?>
<style>
.sg-cmp { max-width: 1100px; }
.sg-cmp .cmp-intro { font-size: var(--d-fs-sm); color: var(--slate-600); margin: 0 0 16px; line-height: 1.6; }
.sg-cmp .cmp-legend { display:flex; gap:18px; flex-wrap:wrap; font-size: var(--d-fs-xs); color: var(--slate-600); margin-bottom: 14px; }
.sg-cmp .cmp-legend b { font-weight: 700; }
.sg-cmp table { width: 100%; border-collapse: collapse; background:#fff; border:1px solid var(--slate-200); border-radius: 10px; overflow: hidden; }
.sg-cmp th, .sg-cmp td { text-align: left; vertical-align: top; padding: 12px 14px; border-bottom: 1px solid var(--slate-100); font-size: var(--d-fs-sm); }
.sg-cmp thead th { background: var(--slate-50); font-weight: 700; color: var(--slate-700); font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em; }
.sg-cmp tbody tr:last-child td { border-bottom: none; }
.sg-cmp .cmp-aspekt { font-weight: 700; color: var(--slate-900); white-space: nowrap; }
.sg-cmp .cmp-note { color: var(--slate-500); font-size: var(--d-fs-xs); margin-top: 4px; }
.sg-cmp .st { display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size: var(--d-fs-xs); padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
.sg-cmp .st.ok   { background: var(--emerald-100); color: var(--emerald-800); }
.sg-cmp .st.warn { background: var(--amber-100);   color: var(--amber-800); }
.sg-cmp .st.diff { background: var(--rose-100);     color: var(--rose-800); }
.sg-cmp .sw { display:inline-block; width:16px; height:16px; border-radius:4px; border:1px solid rgba(0,0,0,.12); vertical-align:-3px; margin-right:5px; }
.sg-cmp code { font-family: var(--font-mono, ui-monospace, monospace); font-size: 0.92em; background: var(--slate-100); padding: 1px 5px; border-radius: 3px; }
.sg-cmp .swrow { display:flex; gap:4px; flex-wrap:wrap; margin-top:4px; }
.sg-cmp .swrow .sw { margin-right:0; }
</style>

<div class="sg-cmp">
  <p class="cmp-intro">
    Gegenüberstellung der verbindlichen <strong>Marken-Standards</strong> (Soll, aus dem Corporate Design)
    und der tatsächlichen <strong>Design-Tokens des Tools</strong> (Ist, aus <code>thx-tokens.css</code> /
    <code>thx-components.css</code>). Manche Abweichungen sind bewusst (ein dichtes Arbeitswerkzeug darf
    kompakter sein als Print/Website), andere sind echte Inkonsistenzen.
  </p>
  <div class="cmp-legend">
    <span><span class="st ok">✓ deckungsgleich</span></span>
    <span><span class="st warn">≈ bewusst / teils anders</span></span>
    <span><span class="st diff">✗ echte Abweichung</span></span>
  </div>

  <table>
    <thead>
      <tr><th style="width:150px;">Aspekt</th><th>Soll (Marke)</th><th>Ist (Tool)</th><th style="width:150px;">Status</th></tr>
    </thead>
    <tbody>
      <tr>
        <td class="cmp-aspekt">Leitfarbe</td>
        <td><span class="sw" style="background:#005DA8;"></span>Thoxan&nbsp;Blau <code>#005DA8</code></td>
        <td><span class="sw" style="background:#005da8;"></span><code>--thoxan-600 #005da8</code> (Primär-Button)</td>
        <td><span class="st ok">✓ deckungsgleich</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Schrift / Schnitte</td>
        <td>Frutiger <strong>45 Light</strong> (Fließtext), <strong>65 Bold</strong> (Headlines), <strong>95 UltraBlack</strong> (selten)</td>
        <td>Jetzt global eingebunden: 45 Light / 65 Bold / 95 UltraBlack.
            <div class="cmp-note">Vorher nur Roman (400) + Bold (700) → Fließtext wirkte zu schwer. Am 24.06.2026 angeglichen.</div></td>
        <td><span class="st ok">✓ angeglichen</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Graustufen</td>
        <td>Marken-Neutrale:
            <div class="swrow">
              <span class="sw" style="background:#33404F;" title="Grau 700 #33404F"></span>
              <span class="sw" style="background:#9AA4B2;" title="Grau 400 #9AA4B2"></span>
              <span class="sw" style="background:#F1F4F7;" title="Grau 100 #F1F4F7"></span>
              <span class="sw" style="background:#EAF2F8;" title="Blau 50 #EAF2F8"></span>
              <span class="sw" style="background:#CFE0EF;" title="Blau 100 #CFE0EF"></span>
            </div>
            <div class="cmp-note">#33404F · #9AA4B2 · #F1F4F7 · Blau 50 #EAF2F8 · Blau 100 #CFE0EF</div></td>
        <td>Tailwind <strong>Slate</strong>:
            <div class="swrow">
              <span class="sw" style="background:#334155;" title="slate-700 #334155"></span>
              <span class="sw" style="background:#94a3b8;" title="slate-400 #94a3b8"></span>
              <span class="sw" style="background:#f1f5f9;" title="slate-100 #f1f5f9"></span>
              <span class="sw" style="background:#e6f0f8;" title="thoxan-50 #e6f0f8"></span>
              <span class="sw" style="background:#cce1f1;" title="thoxan-100 #cce1f1"></span>
            </div>
            <div class="cmp-note">Teils quasi identisch (slate-700 ≈ #33404F), teils kühler/blauer (slate-400 vs #9AA4B2).</div></td>
        <td><span class="st warn">≈ teils abweichend</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Status-Anzeige</td>
        <td><strong>Klartext</strong>, keine farbigen Badges (Empfehlungs-/Statuszellen als Text)</td>
        <td>Farbige Badges/Pills (<span class="sw" style="background:#10b981;"></span>Emerald / <span class="sw" style="background:#f59e0b;"></span>Amber / <span class="sw" style="background:#f43f5e;"></span>Rose) breit im Einsatz</td>
        <td><span class="st diff">✗ echte Abweichung</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Labels / Eyebrows</td>
        <td>Kleine <strong>hellblaue Pillen</strong> (Frutiger Bold, <code>#eaf2f8</code>/<code>#005DA8</code>) — statt technischer Versal-Labels</td>
        <td><code>text-transform: uppercase</code> an ~14 Stellen (Stat-Labels, Tabellenköpfe, Sektions-Labels)</td>
        <td><span class="st diff">✗ echte Abweichung</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Signatur „blaue Balken"</td>
        <td>Schmale, randabfallende blaue Balken oben/unten, Ecken <strong>rechtwinklig</strong> — das Wiedererkennungsmerkmal</td>
        <td>Im Tool <strong>nicht genutzt</strong>; stattdessen abgerundete Karten + blaue Topbar</td>
        <td><span class="st diff">✗ fehlt im Tool</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Ecken / Radius</td>
        <td>Balken rechtwinklig, „nie abgerundet"</td>
        <td>Karten/Controls durchgängig abgerundet (<code>--radius</code> 4–8px)</td>
        <td><span class="st warn">≈ bewusst (UI)</span></td>
      </tr>
      <tr>
        <td class="cmp-aspekt">Typo-Maßstab</td>
        <td>Marketing-Skala: H1 44, H2 30, Body 16 bei Zeilenhöhe 1.7</td>
        <td>Dichte Admin-UI: Page-Title ~1.125rem, Body ~0.875rem, 120%-Basis</td>
        <td><span class="st warn">≈ bewusst anders</span></td>
      </tr>
    </tbody>
  </table>

  <p class="cmp-note" style="margin-top:14px;">
    Stand: 24.06.2026. Offene echte Abweichungen (✗): farbige Status-Badges vs. Klartext,
    Versal-Labels vs. Pillen, fehlendes Balken-Signatur-Element. Bewusst unterschiedlich (≈):
    Graustufen-Feinheiten, Eckenradius, Maßstab/Dichte.
  </p>
</div>
