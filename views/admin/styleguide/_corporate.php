<?php
/**
 * GENERIERT von scripts/import-styleguide.php am 2026-06-25 05:37 — NICHT von Hand bearbeiten.
 * Partial: der "Corporate Design"-Reiter von /admin/styleguide (Wrapper: views/admin/styleguide.php).
 * Quelle: Claude-Design-Projekt "Thoxan Styleguide Entwicklung".
 * Aktualisieren: neuen Export ablegen und  php scripts/import-styleguide.php  ausfuehren.
 * Doku: docs/design-reference/thoxan-styleguide/README.md
 */
?>
<style>
/* Original-Brand-Schriften — fuer originalgetreue Schriftproben, inkl. 95 UltraBlack.
   Aus den gelieferten TTFs zu woff2 re-serialisiert (umgeht OTS-Ablehnung alter Adobe-TTFs).
   Nur auf dieser Seite (scoped); die globale App-Webfont (Frutiger LT Std) bleibt unveraendert.
   Faellt eine Schrift doch aus, greift der Inline-Fallback 'Frutiger LT Std' -> sans-serif. */
@font-face { font-family:'Frutiger Light'; src:url('/assets/fonts/styleguide/frutiger-45-light.woff2?v=e5f27b46') format('woff2'); font-weight:400; font-display:swap; }
@font-face { font-family:'Frutiger Bold';  src:url('/assets/fonts/styleguide/frutiger-65-bold.woff2?v=e5f27b46') format('woff2'); font-weight:700; font-display:swap; }
@font-face { font-family:'Frutiger Black'; src:url('/assets/fonts/styleguide/frutiger-95-ultrablack.woff2?v=e5f27b46') format('woff2'); font-weight:900; font-display:swap; }

/* Layout: linke Navigation (App-Look) + 18px Abstand zur Vorschau rechts */
.sg-root { display:flex; gap:18px; align-items:flex-start; color:#0a0a0a; }
.sg-root * { box-sizing:border-box; }
.sg-aside { width:360px; flex:none; background:#fff; border:1px solid var(--slate-200,#e2e8f0); border-radius:12px; align-self:flex-start; position:sticky; top:18px; padding:10px; }
.sg-nav { display:flex; flex-direction:column; gap:2px; max-height:calc(100vh - 56px); overflow-y:auto; }
/* Nutzt die globale .nav-item-Optik der App; nur die Kapitelnummer wird gestylt */
.sg-nav .nav-item { cursor:pointer; }
.sg-nav-num { font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:12px; color:var(--slate-400,#9aa4b2); }
.sg-nav .nav-item.active .sg-nav-num { color:rgba(255,255,255,.7); }
.sg-main { flex:1; min-width:0; background:#fff; display:flex; flex-direction:column; border:1px solid #e0e6eb; border-radius:12px; overflow:hidden; }
.sg-main-bar { height:8px; background:#005DA8; flex:none; }
.sg-main-body { flex:1; min-width:0; }
.sg-root [data-sg-go] { cursor:pointer; }
</style>

<div class="sg-root">
  <aside class="sg-aside">
    <nav class="sg-nav">
          <div class="nav-item sg-nav-item" data-sg-go="uebersicht" data-target="uebersicht"><span class="nav-icon sg-nav-num">00</span><span class="sg-nav-label">Übersicht</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="logo" data-target="logo"><span class="nav-icon sg-nav-num">01</span><span class="sg-nav-label">Logo &amp; Bildmarke</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="farben" data-target="farben"><span class="nav-icon sg-nav-num">02</span><span class="sg-nav-label">Farben</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="typografie" data-target="typografie"><span class="nav-icon sg-nav-num">03</span><span class="sg-nav-label">Typografie</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="elemente" data-target="elemente"><span class="nav-icon sg-nav-num">04</span><span class="sg-nav-label">Gestaltungselemente</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="ui" data-target="ui"><span class="nav-icon sg-nav-num">05</span><span class="sg-nav-label">Buttons &amp; UI</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="bildsprache" data-target="bildsprache"><span class="nav-icon sg-nav-num">06</span><span class="sg-nav-label">Bildsprache</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="icons" data-target="icons"><span class="nav-icon sg-nav-num">07</span><span class="sg-nav-label">Icons</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="layout" data-target="layout"><span class="nav-icon sg-nav-num">08</span><span class="sg-nav-label">Layout &amp; Grid</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="tonalitaet" data-target="tonalitaet"><span class="nav-icon sg-nav-num">09</span><span class="sg-nav-label">Tonalität</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="werbemittel" data-target="werbemittel"><span class="nav-icon sg-nav-num">10</span><span class="sg-nav-label">Werbemittel &amp; Creatives</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="geschaeft" data-target="geschaeft"><span class="nav-icon sg-nav-num">11</span><span class="sg-nav-label">Geschäftsausstattung</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="anwendung" data-target="anwendung"><span class="nav-icon sg-nav-num">12</span><span class="sg-nav-label">Anwendung</span></div>
          <div class="nav-item sg-nav-item" data-sg-go="markenentwicklung" data-target="markenentwicklung"><span class="nav-icon sg-nav-num">13</span><span class="sg-nav-label">Markenentwicklung</span></div>
    </nav>
  </aside>

  <main class="sg-main">
    <div class="sg-main-bar"></div>
    <div class="sg-main-body">
<section class="sg-section" data-section="uebersicht" style="padding:0;; display:block;">
      <div style="padding:84px 80px 64px; position:relative; overflow:hidden; background:#005DA8;">
        <img src="/assets/images/thoxan-x-white.svg" alt="" style="position:absolute; right:-30px; bottom:-90px; height:420px; opacity:0.13;">
        <div style="position:relative; max-width:760px;">
          <div style="font-family:'IBM Plex Mono',monospace; font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:rgba(255,255,255,0.75); margin-bottom:28px;">Corporate Design · Styleguide 2026</div>
          <div style="line-height:0.88;"><img src="/assets/images/thoxan-logo-white.svg" alt="Thoxan" style="height:96px;"></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:22px; color:rgba(255,255,255,0.95); margin-top:22px; line-height:1.4;">Wunschkunden-Gewinnung im Internet.</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:rgba(255,255,255,0.85); margin-top:26px; max-width:620px;">Dieser Guide ist die verbindliche Grundlage für alle gestalterischen Entscheidungen der Thoxan Agenturgruppe — von Logo und Farbe über Typografie bis zu Web, Print und Social. Er sorgt dafür, dass die Marke überall konsistent, klar und wiedererkennbar auftritt.</p>
        </div>
      </div>
      <div style="padding:56px 80px 80px;">
        <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px; margin-bottom:22px;">Die Agenturgruppe</div>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:64px;">
          <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; border-top:4px solid #005DA8;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:19px; color:#000; margin-bottom:6px;">Thoxan GmbH</div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:12px;">Operative Holding</div>
            <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0;">Dach der Gruppe. Bündelt Beteiligungen, Strategie und die zentrale Verwaltung am Hauptsitz in Hille-Rothenuffeln.</p>
          </div>
          <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; border-top:4px solid #005DA8;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:19px; color:#000; margin-bottom:6px;">Communications</div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:12px;">Digitalagentur</div>
            <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0;">Strategische Beratung, verkaufsstarke Websites und Online-Marketing für spezialisierte B2B-Unternehmen.</p>
          </div>
          <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; border-top:4px solid #005DA8;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:19px; color:#000; margin-bottom:6px;">E-Commerce</div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:12px;">Handelsgesellschaft</div>
            <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0;">Eigene Handels- und Online-Projekte (u. a. WITTEKIND). Praxiswissen, das direkt in Kundenprojekte einfließt.</p>
          </div>
        </div>
        <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px; margin-bottom:22px;">Inhalt</div>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:0 56px;">
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="logo">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">01</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Logo &amp; Bildmarke</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="farben">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">02</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Farben</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="typografie">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">03</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Typografie</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="elemente">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">04</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Gestaltungselemente</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="ui">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">05</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Buttons &amp; UI</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="bildsprache">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">06</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Bildsprache</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="icons">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">07</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Icons</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="layout">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">08</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Layout &amp; Grid</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="tonalitaet">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">09</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Tonalität</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="werbemittel">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">10</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Werbemittel &amp; Creatives</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="geschaeft">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">11</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Geschäftsausstattung</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="anwendung">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">12</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Anwendung</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
            <div style="display:flex; gap:16px; align-items:baseline; padding:14px 0; border-bottom:1px solid #eef1f4; cursor:pointer;" data-sg-go="markenentwicklung">
              <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#005DA8; width:24px; flex:none;">13</span>
              <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#0a0a0a; flex:1;">Markenentwicklung</span>
              <span style="color:#c2cad2; font-size:16px;">→</span>
            </div>
          
        </div>
      </div>
    </section>
<section class="sg-section" data-section="logo" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">01 · Logo &amp; Bildmarke</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Die Wort-Bildmarke</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Die Wort-Bildmarke besteht aus dem Schriftzug <strong>„Thoxan"</strong> — großes „T", ohne Punkt —, bei dem das „x" durch die <strong>Bildmarke</strong> ersetzt ist. Die Bildmarke kann für sich allein stehen, bevorzugt im Anschnitt, und darf transparent dargestellt werden.</p>

      <!-- primary logo on light -->
      <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:64px; display:flex; align-items:center; justify-content:center; background:#fff;">
          <div><img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:98px;"></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:32px; display:flex; flex-direction:column; justify-content:center; background:#f7f9fb;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:10px;">Auf hellem Grund</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0;">Logo in 100 % Schwarz oder Thoxan Blau. Bevorzugte Anwendung auf Weiß und hellen Flächen.</p>
        </div>
      </div>
      <!-- on blue -->
      <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:48px;">
        <div style="border-radius:12px; padding:64px; display:flex; align-items:center; justify-content:center; background:#005DA8;">
          <div><img src="/assets/images/thoxan-logo-white.svg" alt="Thoxan" style="height:98px;"></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:32px; display:flex; flex-direction:column; justify-content:center; background:#f7f9fb;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:10px;">Auf farbigem Grund</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0;">Auf farbigen Flächen — bevorzugt Thoxan Blau — erscheint das Logo in Weiß.</p>
        </div>
      </div>

      <!-- bildmarke + schutzraum -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:48px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; margin-bottom:6px;">Bildmarke „x"</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0 0 20px;">Steht allein, bevorzugt im Anschnitt (am Rand angeschnitten). Auch transparent einsetzbar.</p>
          <div style="height:180px; border-radius:8px; background:#005DA8; overflow:hidden; position:relative;">
            <img src="/assets/images/thoxan-x-white.svg" alt="Bildmarke" style="position:absolute; left:-14px; top:-26px; height:230px;">
          </div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; margin-bottom:6px;">Schutzraum</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#5a6672; margin:0 0 20px;">Mindestabstand rundum = Höhe des „x". Innerhalb dieser Zone keine weiteren Elemente.</p>
          <div style="height:180px; border-radius:8px; background:#f7f9fb; border:1px dashed #b9c4ce; display:flex; align-items:center; justify-content:center; position:relative;">
            <div style="position:absolute; inset:26px; border:1px dashed #c2cad2; border-radius:4px;"></div>
            <div style="position:relative;"><img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:50px;"></div>
          </div>
        </div>
      </div>

      <!-- logos der gesellschaften -->
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px; margin-bottom:16px;">Logos der Gesellschaften</div>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:15px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 24px;">Alle drei Gesellschaften teilen denselben Schriftzug und die figürliche „x"-Marke. Sie unterscheiden sich nur durch die <strong>Subline</strong> mit dem Gesellschaftsnamen. Die Holding tritt ohne Subline als Dachmarke auf.</p>
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="height:140px; display:flex; align-items:center; justify-content:center; padding:30px; background:#fff; border-bottom:1px solid #eef1f4;"><img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="max-height:54px; max-width:80%;"></div>
          <div style="padding:20px 22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#000;">Thoxan GmbH</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Holding · Dachmarke, ohne Subline</div></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="height:140px; display:flex; align-items:center; justify-content:center; padding:30px; background:#fff; border-bottom:1px solid #eef1f4;"><img src="/assets/images/logo-communications.png" alt="Thoxan Communications GmbH" style="max-height:74px; max-width:88%;"></div>
          <div style="padding:20px 22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#000;">Communications GmbH</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Digitalagentur</div></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="height:140px; display:flex; align-items:center; justify-content:center; padding:30px; background:#fff; border-bottom:1px solid #eef1f4;"><img src="/assets/images/logo-ecommerce.svg" alt="Thoxan E-Commerce GmbH" style="max-height:74px; max-width:88%;"></div>
          <div style="padding:20px 22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#000;">E-Commerce GmbH</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Handelsgesellschaft</div></div>
        </div>
      </div>
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:48px;">
        <div style="border-radius:12px; height:120px; display:flex; align-items:center; justify-content:center; padding:24px; background:#005DA8;"><img src="/assets/images/thoxan-logo-white.svg" alt="Thoxan" style="max-height:48px; max-width:80%;"></div>
        <div style="border-radius:12px; height:120px; display:flex; align-items:center; justify-content:center; padding:24px; background:#005DA8;"><img src="/assets/images/logo-communications-white.png" alt="" style="max-height:66px; max-width:88%;"></div>
        <div style="border-radius:12px; height:120px; display:flex; align-items:center; justify-content:center; padding:24px; background:#005DA8;"><img src="/assets/images/logo-ecommerce-white.svg" alt="" style="max-height:66px; max-width:88%;"></div>
      </div>

      <!-- drop real logo -->
      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; margin-bottom:48px; background:#f7f9fb;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:14px;">Offizielle Logodatei hinterlegen</div>
        <div style="display:flex; gap:20px;">
          <div style="width:260px; height:120px; flex:none;; border:1px dashed #b9c4ce; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><img src="/assets/images/thoxan-logo.svg" alt="" style="max-width:84%; max-height:84%;"></div>
          <div style="width:120px; height:120px; flex:none;; border:1px dashed #b9c4ce; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><img src="/assets/images/thoxan-x.svg" alt="" style="max-width:84%; max-height:84%;"></div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; margin:0; align-self:center;">Ziehe die freigestellten Vektordateien (SVG/EPS) hierher. Die gezeigten Logos stammen aus der Live-Website und dienen als Referenz.</p>
        </div>
      </div>

      <!-- don'ts -->
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px; margin-bottom:18px;">Nicht erlaubt</div>
      <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
        <div style="border:1px solid #e4e9ee; border-radius:10px; padding:24px; text-align:center;">
          <div style="margin:14px 0 18px; display:flex; justify-content:center;"><img src="/assets/images/thoxan-logo.svg" alt="" style="height:30px; transform:scaleX(1.7);"></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:12px; color:#9aa4b2;">nicht verzerren</div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:10px; padding:24px; text-align:center;">
          <div style="margin:14px 0 18px; display:flex; justify-content:center;"><img src="/assets/images/thoxan-logo.svg" alt="" style="height:30px; filter:hue-rotate(130deg) saturate(2.5);"></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:12px; color:#9aa4b2;">nicht umfärben</div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:10px; padding:24px; text-align:center;">
          <div style="margin:14px 0 18px; display:flex; justify-content:center;"><img src="/assets/images/thoxan-logo.svg" alt="" style="height:30px; transform:rotate(-12deg);"></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:12px; color:#9aa4b2;">nicht drehen</div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:10px; padding:24px; text-align:center;">
          <div style="margin:14px 0 18px; display:flex; justify-content:center;"><img src="/assets/images/thoxan-logo.svg" alt="" style="height:30px; filter:drop-shadow(3px 4px 2px rgba(0,0,0,.45));"></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:12px; color:#9aa4b2;">keine Effekte / Schatten</div>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="farben" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">02 · Farben</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Farbwerte</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Die Marke lebt von einem klaren, reduzierten Farbsystem: <strong>Thoxan Blau</strong> als Leitfarbe, <strong>Schwarz</strong> und <strong>Weiß</strong>. Verbindlich ist der RGB-/HEX-Wert.</p>

      <!-- core colors -->
      <div style="display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:20px; margin-bottom:48px;">
        <div style="border-radius:14px; overflow:hidden; border:1px solid #e4e9ee;">
          <div style="height:200px; background:#005DA8;"></div>
          <div style="padding:24px;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:20px; color:#000;">Thoxan Blau</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:13px; color:#5a6672; line-height:2; margin-top:10px;">
              HEX&nbsp;&nbsp;<span style="color:#005DA8; font-weight:600;">#005DA8</span><br>
              RGB&nbsp;&nbsp;0 / 93 / 168<br>
              CMYK&nbsp;100 / 60 / 0 / 0
            </div>
          </div>
        </div>
        <div style="border-radius:14px; overflow:hidden; border:1px solid #e4e9ee;">
          <div style="height:200px; background:#000;"></div>
          <div style="padding:24px;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:20px; color:#000;">Schwarz</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:13px; color:#5a6672; line-height:2; margin-top:10px;">
              HEX&nbsp;&nbsp;<span style="color:#000; font-weight:600;">#000000</span><br>
              RGB&nbsp;&nbsp;0 / 0 / 0<br>
              CMYK&nbsp;0 / 0 / 0 / 100
            </div>
          </div>
        </div>
        <div style="border-radius:14px; overflow:hidden; border:1px solid #e4e9ee;">
          <div style="height:200px; background:#fff; border-bottom:1px solid #e4e9ee;"></div>
          <div style="padding:24px;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:20px; color:#000;">Weiß</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:13px; color:#5a6672; line-height:2; margin-top:10px;">
              HEX&nbsp;&nbsp;<span style="color:#000; font-weight:600;">#FFFFFF</span><br>
              RGB&nbsp;&nbsp;255 / 255 / 255<br>
              CMYK&nbsp;0 / 0 / 0 / 0
            </div>
          </div>
        </div>
      </div>

      <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; max-width:680px; margin:0 0 40px;">Verbindlich ist <strong style="color:#000;">#005DA8</strong> (offizielles CD). Hinweis aus dem Abgleich: Das aktuelle Website-Logo wird etwas dunkler ausgespielt (gemessen ~#004C9B) — bei einem Relaunch auf den CD-Wert vereinheitlichen.</div>

      <!-- digital extension -->
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px; margin-bottom:8px;">Digitale Erweiterung</div>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.6; color:#7a8590; max-width:620px; margin:0 0 22px;">Für Web &amp; UI ergänzen abgestimmte Blau-Abstufungen und Neutraltöne die Kernfarben (Zustände, Flächen, Text). Sie erweitern das Print-CD, ersetzen es nicht.</p>
      <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:14px;">
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#004A85;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Blau 700</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#004A85</div>
          </div>
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#CFE0EF;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Blau 100</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#CFE0EF</div>
          </div>
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#EAF2F8;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Blau 50</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#EAF2F8</div>
          </div>
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#33404F;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Grau 700</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#33404F</div>
          </div>
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#9AA4B2;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Grau 400</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#9AA4B2</div>
          </div>
        
          <div>
            <div style="height:88px; border-radius:10px; border:1px solid #e4e9ee; background:#F1F4F7;"></div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; margin-top:10px;">Grau 100</div>
            <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2;">#F1F4F7</div>
          </div>
        
      </div>

      <!-- contrast / usage -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:48px;">
        <div style="background:#005DA8; border-radius:14px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:28px; color:#fff; line-height:1.1;">Weiß auf Blau</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:rgba(255,255,255,0.85); margin:12px 0 0; line-height:1.6;">Headlines und Logo auf Thoxan Blau immer in Weiß. Hoher Kontrast, klare Lesbarkeit.</p>
        </div>
        <div style="background:#fff; border:1px solid #e4e9ee; border-radius:14px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:28px; color:#000; line-height:1.1;">Schwarz auf Weiß</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; margin:12px 0 0; line-height:1.6;">Fließtext grundsätzlich Schwarz auf Weiß. Thoxan Blau als Akzent für Links, Marken- und Aktionselemente.</p>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="typografie" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">03 · Typografie</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Corporate-Schrift</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Die Hausschrift ist <strong>Frutiger</strong> — eine humanistische Grotesk mit hoher Lesbarkeit. Im Web tragen zwei Schnitte die Marke: <strong>45 Light</strong> für Fließtext und <strong>65 Bold</strong> für Headlines und Akzente. <strong>95 UltraBlack</strong> setzen wir bewusst sparsam ein.</p>

      <!-- weights & roles -->
      <div style="display:flex; flex-direction:column; gap:0; border-top:1px solid #e4e9ee;">
        <div style="display:grid; grid-template-columns:230px 1fr; gap:32px; padding:34px 0; border-bottom:1px solid #e4e9ee; align-items:center;">
          <div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000;">Frutiger 45 Light</div>
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:3px;">Workhorse · Fließtext, Sublines, UI</div>
          </div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:22px; color:#0a0a0a; line-height:1.5;">Aus erklärungsbedürftig wird begehrenswert — mit Websites, die verkaufen statt verstecken.</div>
        </div>
        <div style="display:grid; grid-template-columns:230px 1fr; gap:32px; padding:34px 0; border-bottom:1px solid #e4e9ee; align-items:center;">
          <div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000;">Frutiger 65 Bold</div>
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:3px;">Headlines, Subheads &amp; Inline-Akzente</div>
          </div>
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; line-height:1.05; letter-spacing:-0.01em;">Verkaufsstarke Websites für B2B.</div>
        </div>
        <div style="display:grid; grid-template-columns:230px 1fr; gap:32px; padding:34px 0; border-bottom:1px solid #e4e9ee; align-items:center;">
          <div>
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000;">Frutiger 95 UltraBlack</div>
            <div style="display:inline-block; margin-top:6px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:11px; color:#9a6b00; background:#fbf0d6; padding:4px 10px; border-radius:100px;">sparsam einsetzen</div>
          </div>
          <div style="display:flex; align-items:baseline; gap:18px;">
            <div style="font-family:'Frutiger Black','Frutiger LT Std',sans-serif; font-size:30px; color:#0a0a0a; line-height:1.0;">Eine Key-Zahl.</div>
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#9aa4b2; max-width:230px;">Nur als seltener Höhepunkt — etwa eine einzelne Kennzahl oder ein Plakat-Statement. Nicht für laufende Headlines.</div>
          </div>
        </div>
      </div>

      <!-- web signature: mixed-weight headlines -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:44px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Mixed-Weight-Headline · Web-Signatur</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:30px; color:#0a0a0a; line-height:1.18;">Möchtest Du mehr <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Sichtbarkeit als Spezialanbieter?</strong></div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:30px; color:#0a0a0a; line-height:1.18; margin-top:18px;"><strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Was</strong> brauchst <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Du?</strong></div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; margin:20px 0 0;">Light und Bold im selben Satz: das Bold-Gewicht betont die Kernaussage, ohne dass die Headline laut wirkt.</p>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Inline-Bold im Fließtext</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:17px; line-height:1.65; color:#0a0a0a; margin:0;">Wir bringen Dich mit Deinen Wunschkunden zusammen — <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">auf Augenhöhe</strong> und mit <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">planbar neuen Anfragen</strong>. Kein Marketing-Sprech, sondern Websites, die verkaufen.</p>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; margin:20px 0 0;">Schlüsselbegriffe werden mit <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Bold</strong> ausgezeichnet — nie mit Farbe oder Unterstreichung im Text.</p>
        </div>
      </div>

      <!-- specimen + scale -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Alphabet · Frutiger 45 Light</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:21px; color:#000; line-height:1.7; word-spacing:2px;">ABCDEFGHIJKLM<br>NOPQRSTUVWXYZ<br>abcdefghijklm<br>nopqrstuvwxyz<br>0 1 2 3 4 5 6 7 8 9 &amp; ?</div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Type-Scale · Web</div>
          <div style="display:flex; flex-direction:column; gap:16px;">
            <div style="display:flex; align-items:baseline; gap:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; width:96px; flex:none;">H1 · 44 Bold</span><span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:28px; color:#000; line-height:1;">Headline</span></div>
            <div style="display:flex; align-items:baseline; gap:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; width:96px; flex:none;">H2 · 30 Bold</span><span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:21px; color:#000; line-height:1;">Subheadline</span></div>
            <div style="display:flex; align-items:baseline; gap:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; width:96px; flex:none;">H3 · 22 Bold</span><span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:17px; color:#000; line-height:1;">Abschnitt</span></div>
            <div style="display:flex; align-items:baseline; gap:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; width:96px; flex:none;">Body · 16 Light</span><span style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; color:#000; line-height:1;">Fließtext, 1.7 Zeilenhöhe</span></div>
            <div style="display:flex; align-items:baseline; gap:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; width:96px; flex:none;">Eyebrow</span><span style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; background:#eaf2f8; padding:6px 13px; border-radius:100px;">Über uns</span></div>
          </div>
        </div>
      </div>

      <!-- eyebrow rule -->
      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; margin-top:20px;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:16px;">Eyebrows &amp; Labels = Pill-Badges</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 15px; border-radius:100px;">Über uns</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 15px; border-radius:100px;">Unsere Digitalagentur</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 15px; border-radius:100px;">Thoxan Communications GmbH</span>
          <span style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">— kleine hellblaue Pills statt technischer Versal-Labels.</span>
        </div>
      </div>

      <div style="margin-top:22px; font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; line-height:1.6;">Hinweis: Frutiger ist lizenzpflichtig. Für Web werden die lizenzierten Webfonts eingebunden; für Office/Systemumgebungen ohne Frutiger-Lizenz gilt <strong>Arial</strong> bzw. <strong>Helvetica</strong> als Ersatzschrift.</div>
    </section>
<section class="sg-section" data-section="elemente" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">04 · Gestaltungselemente</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Formen &amp; Elemente</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Wiederkehrende Gestaltungselemente machen die Marke unverwechselbar — allen voran die <strong>blauen Balken</strong> oben und unten.</p>

      <!-- blue bars -->
      <div style="display:grid; grid-template-columns:1.3fr 1fr; gap:20px; margin-bottom:24px;">
        <div style="border:1px solid #e4e9ee; overflow:hidden;">
          <div style="height:10px; background:#005DA8;"></div>
          <div style="padding:48px 40px; min-height:200px; display:flex; flex-direction:column; justify-content:center;">
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:30px; color:#0a0a0a; line-height:1.12;">Sichtbarkeit ist eine <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Entscheidung.</strong></div>
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:16px;">Beispiel-Layout mit Balkenrahmung — Ecken bleiben rechtwinklig</div>
          </div>
          <div style="height:10px; background:#005DA8;"></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px; display:flex; flex-direction:column; justify-content:center;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; margin-bottom:8px;">Blaue Balken oben &amp; unten</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0 0 14px;">Das einheitliche Element des Corporate Designs. Schmale blaue Balken rahmen die Fläche oben und unten und schaffen Wiedererkennung.</p>
          <ul style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.8; color:#5a6672; margin:0; padding-left:18px;">
            <li>Balken laufen <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">randabfallend</strong> über die volle Breite</li>
            <li>Ecken bleiben <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">rechtwinklig</strong> — nie abgerundet</li>
            <li>Höhe dezent: ca. 1–1,5 % der Formathöhe</li>
          </ul>
        </div>
      </div>

      <!-- trennstriche + claim -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; margin-bottom:8px;">Trennstriche</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0 0 22px;">Feine Striche festigen und gliedern Text — etwa bei Adress- und Kontaktangaben.</p>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:15px; color:#000; line-height:2;">
            Thoxan GmbH<span style="display:inline-block; width:18px; height:2px; background:#005DA8; margin:0 10px; vertical-align:middle;"></span>Eicksen 55<br>
            32479 Hille-Rothenuffeln<span style="display:inline-block; width:18px; height:2px; background:#005DA8; margin:0 10px; vertical-align:middle;"></span>OWL
          </div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; margin-bottom:8px;">Der Claim</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0 0 22px;">Die zentrale Positionierungszeile der Marke. Aktuell: »Wunschkunden-Gewinnung im Internet.« Die historische Signatur wurde stets klein und mit Punkt gesetzt.</p>
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:24px; color:#005DA8; line-height:1.12;">Wunschkunden-Gewinnung im Internet.</div>
          <div style="display:flex; gap:18px; margin-top:18px;">
            <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#1a9d5a;">aktuell · Website</span>
            <span style="font-family:'IBM Plex Mono',monospace; font-size:12px; color:#9aa4b2;">historisch: »frischer wind im netz.«</span>
          </div>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="ui" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">05 · Buttons &amp; UI</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Interaktive Elemente</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Bausteine für Web-Oberflächen — konsequent in Markenfarbe und Frutiger.</p>

      <!-- buttons -->
      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:36px; margin-bottom:20px;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:22px;">Buttons</div>
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#005DA8; color:#fff; font-size:15px; padding:14px 26px; border-radius:8px; cursor:pointer;">Kontakt aufnehmen</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#fff; color:#005DA8; border:2px solid #005DA8; font-size:15px; padding:12px 24px; border-radius:8px; cursor:pointer;">Mehr erfahren</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:transparent; color:#005DA8; font-size:15px; padding:14px 8px; cursor:pointer;">Weiterlesen →</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#eef1f4; color:#9aa4b2; font-size:15px; padding:14px 26px; border-radius:8px;">Deaktiviert</span>
        </div>
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center; margin-top:18px;">
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#005DA8; color:#fff; font-size:15px; padding:16px 30px; border-radius:100px; cursor:pointer;">Jetzt starten →</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#000; color:#fff; font-size:15px; padding:16px 30px; border-radius:100px; cursor:pointer;">Referenzen</span>
        </div>
      </div>

      <!-- form + card -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:22px;">Formular</div>
          <label style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; display:block; margin-bottom:8px;">E-Mail</label>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:15px; color:#9aa4b2; border:1.5px solid #d8dee5; border-radius:8px; padding:13px 16px; margin-bottom:18px;">name@unternehmen.de</div>
          <label style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000; display:block; margin-bottom:8px;">Nachricht <span style="color:#005DA8;">(aktiv)</span></label>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:15px; color:#000; border:1.5px solid #005DA8; border-radius:8px; padding:13px 16px; box-shadow:0 0 0 3px rgba(0,93,168,0.12);">Worum geht es?</div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:36px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:22px;">Karte</div>
          <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
            <div style="height:96px; position:relative; overflow:hidden;"><img src="/assets/images/photo-meeting.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"></div>
            <div style="padding:22px;">
              <div style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#005DA8; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">Referenz</div>
              <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:18px; color:#000; line-height:1.2;">Pro-f lässt Maschinen tanzen</div>
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; margin-top:8px; line-height:1.5;">Website-Relaunch &amp; Online-Marketing für einen B2B-Spezialisten.</div>
            </div>
          </div>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="bildsprache" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">06 · Bildsprache</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Fotografie</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Authentisch, hell, partnerschaftlich. Echte Menschen und echte Projekte statt generischer Stockfotos — gern mit dem bodenständigen OWL-Charakter der Agentur.</p>

      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
        <div style="border-radius:12px; height:220px; position:relative; overflow:hidden; display:flex; align-items:flex-end; padding:18px;"><img src="/assets/images/photo-workshop.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"><span style="position:relative; font-family:'IBM Plex Mono',monospace; font-size:11px; color:#fff; background:rgba(0,0,0,0.45); padding:4px 9px; border-radius:4px;">team · authentisch</span></div>
        <div style="border-radius:12px; height:220px; position:relative; overflow:hidden; display:flex; align-items:flex-end; padding:18px;"><img src="/assets/images/photo-meeting.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"><span style="position:relative; font-family:'IBM Plex Mono',monospace; font-size:11px; color:#fff; background:rgba(0,0,0,0.45); padding:4px 9px; border-radius:4px;">arbeitssituation</span></div>
        <div style="border-radius:12px; height:220px; display:flex; align-items:flex-end; padding:18px; position:relative; overflow:hidden;"><img src="/assets/images/photo-consult.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"><div style="position:absolute; inset:0; background:#005DA8; mix-blend-mode:multiply; opacity:0.82;"></div><img src="/assets/images/thoxan-x-white.svg" alt="" style="position:absolute; right:-14px; top:-28px; height:160px; opacity:0.22;"><span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:rgba(255,255,255,0.95); position:relative;">marke + bild</span></div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; border-top:4px solid #1a9d5a;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:14px;">Do</div>
          <ul style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.9; margin:0; padding-left:18px;">
            <li>Natürliches Licht, echte Situationen</li>
            <li>Menschen, Hände, Teamarbeit</li>
            <li>Reale Kundenprojekte &amp; Produkte</li>
            <li>Ruhige Bildausschnitte, klarer Fokus</li>
          </ul>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; border-top:4px solid #d23b3b;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:14px;">Don't</div>
          <ul style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.9; margin:0; padding-left:18px;">
            <li>Generische, gestellte Stockfotos</li>
            <li>Übersättigte Farben &amp; harte Filter</li>
            <li>Unruhige, überladene Kompositionen</li>
            <li>Klischees (Handschlag, Headset-Lächeln)</li>
          </ul>
        </div>
      </div>

      <!-- image treatment -->
      <h3 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:22px; color:#0a0a0a; margin:56px 0 6px;">Bildeinsatz &amp; Formate</h3>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:15px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 28px;">Bilder werden ruhig und großzügig eingesetzt — bevorzugt randabfallend. Die Bildmarke und die blaue Markenfläche verbinden Foto und Marke.</p>

      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="aspect-ratio:16/9; position:relative; overflow:hidden;"><img src="/assets/images/photo-workshop.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"></div>
          <div style="padding:16px 18px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Quer · 16:9</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Hero, Web-Header, Slides</div></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="aspect-ratio:16/9; position:relative; overflow:hidden;"><img src="/assets/images/photo-team.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"></div>
          <div style="padding:16px 18px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Hoch · 4:5 / 9:16</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Social Feed &amp; Stories</div></div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="aspect-ratio:16/9; position:relative; overflow:hidden;"><img src="/assets/images/photo-meeting.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"></div>
          <div style="padding:16px 18px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Quadrat · 1:1</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; margin-top:2px;">Post, Teaser, Kachel</div></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:28px;">
        <div style="border-radius:12px; overflow:hidden; position:relative; aspect-ratio:16/9;">
          <img src="/assets/images/photo-team.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
          <img src="/assets/images/thoxan-x-white.svg" alt="" style="position:absolute; right:-20px; bottom:-50px; height:200px; opacity:0.6;">
          <div style="position:absolute; left:0; right:0; bottom:0; height:8px; background:#005DA8;"></div>
          <div style="position:absolute; left:22px; bottom:22px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:20px; color:#fff; text-shadow:0 1px 6px rgba(0,0,0,.4);">Bildmarke im Anschnitt</div>
        </div>
        <div style="border-radius:12px; overflow:hidden; position:relative; aspect-ratio:16/9;">
          <img src="/assets/images/photo-consult.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
          <div style="position:absolute; inset:0; background:#005DA8; mix-blend-mode:multiply; opacity:0.8;"></div>
          <div style="position:absolute; left:22px; bottom:18px; color:#fff;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:20px;">Blau-Duoton</div><div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; opacity:0.9;">Foto in Markenblau getont — für Akzentflächen</div></div>
        </div>
      </div>

      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; background:#f7f9fb;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:14px;">Eigene Bilder hinterlegen</div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
          <div style="width:240px; height:135px; flex:none;; border:1px dashed #b9c4ce; border-radius:10px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">16:9 Foto ablegen</span></div>
          <div style="width:135px; height:135px; flex:none;; border:1px dashed #b9c4ce; border-radius:10px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">1:1 Foto</span></div>
          <div style="width:108px; height:135px; flex:none;; border:1px dashed #b9c4ce; border-radius:10px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">4:5</span></div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; margin:0; align-self:center; max-width:200px;">Ziehe echte Projekt- oder Teamfotos hierher, um die Bildwelt zu testen.</p>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="icons" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">07 · Icons</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Icon-Stil</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Lineare Icons, einheitliche Strichstärke (2 px), runde Enden. Reduziert und funktional — nie illustrativ.</p>

      <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:16px; margin-bottom:24px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">arrow</span>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">check</span>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><polyline points="3 7 12 13 21 7"></polyline></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">mail</span>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"></path></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">phone</span>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">search</span>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#005DA8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="3" y1="12" x2="21" y2="12"></line><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"></path></svg>
          <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">globe</span>
        </div>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:8px;">Strich &amp; Raster</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0;">24 × 24 px Raster, 2 px Strich, runde Linienenden und -ecken. Innenflächen bleiben offen (Outline-Stil).</p>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:8px;">Farbe</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0;">Standard Thoxan Blau oder Schwarz. Auf farbigem Grund Weiß. Keine Mehrfarbigkeit, keine Verläufe.</p>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="layout" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">08 · Layout &amp; Grid</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Raster &amp; Abstände</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Ein 12-Spalten-Raster und eine 8-px-Abstandsskala sorgen für Ordnung und Rhythmus über alle Medien hinweg.</p>

      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px; margin-bottom:20px;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">12-Spalten-Raster</div>
        <div style="display:grid; grid-template-columns:repeat(12,1fr); gap:10px;">
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
            <div style="height:90px; background:#eaf2f8; border:1px solid #cfe0ef; border-radius:4px;"></div>
          
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:22px;">Abstandsskala · 8 px Basis</div>
          <div style="display:flex; flex-direction:column; gap:12px;">
            
              <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; width:54px; flex:none;">4 · xs</span>
                <div style="height:9px; background:#005DA8; border-radius:3px; width:8%;"></div>
              </div>
            
              <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; width:54px; flex:none;">8 · sm</span>
                <div style="height:9px; background:#005DA8; border-radius:3px; width:16%;"></div>
              </div>
            
              <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; width:54px; flex:none;">16 · md</span>
                <div style="height:9px; background:#005DA8; border-radius:3px; width:32%;"></div>
              </div>
            
              <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; width:54px; flex:none;">24 · lg</span>
                <div style="height:9px; background:#005DA8; border-radius:3px; width:56%;"></div>
              </div>
            
              <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; width:54px; flex:none;">48 · xl</span>
                <div style="height:9px; background:#005DA8; border-radius:3px; width:100%;"></div>
              </div>
            
          </div>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="height:8px; background:#005DA8;"></div>
          <div style="padding:30px;">
            <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:8px;">Seitenränder &amp; Balken</div>
            <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; line-height:1.65; color:#5a6672; margin:0;">Großzügige Außenränder, klare Spalten und die markentypische Balkenrahmung oben/unten geben jeder Anwendung Halt.</p>
          </div>
          <div style="height:8px; background:#005DA8;"></div>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="tonalitaet" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">09 · Tonalität</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Sprache &amp; Haltung</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Thoxan spricht <strong>auf Augenhöhe</strong> — direkt, klar und partnerschaftlich. Die Ansprache ist persönlich und nutzt durchgängig das <strong>„Du"</strong>.</p>

      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:32px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:26px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#005DA8; margin-bottom:8px;">Direkt</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.6; margin:0;">Kurze, aktive Sätze. Kein Marketing-Sprech, keine Floskeln.</p></div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:26px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#005DA8; margin-bottom:8px;">Klar</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.6; margin:0;">Verständlich auch bei erklärungsbedürftigen B2B-Themen.</p></div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:26px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#005DA8; margin-bottom:8px;">Partnerschaftlich</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.6; margin:0;">Auf Augenhöhe, ehrlich, mit echtem Interesse am Kunden.</p></div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:32px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #1a9d5a;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#000; margin-bottom:14px;">So klingt Thoxan</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; color:#000; line-height:1.6; margin:0 0 12px;">„Deine Website entscheidet, ob Kunden kaufen oder weiterklicken."</p>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; color:#000; line-height:1.6; margin:0;">„Lass uns das ändern."</p>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #d23b3b;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#000; margin-bottom:14px;">So nicht</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; color:#9aa4b2; line-height:1.6; margin:0 0 12px;">„Wir bieten Ihnen ganzheitliche, innovative Synergie-Lösungen."</p>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; color:#9aa4b2; line-height:1.6; margin:0;">„Kontaktieren Sie uns für ein unverbindliches Angebot."</p>
        </div>
      </div>

      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:16px;">Marken-Vokabular</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">Wunschkunden</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">Sichtbarkeit</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">Spezialanbieter</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">auf Augenhöhe</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">verkaufsstark</span>
          <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; background:#eaf2f8; padding:8px 14px; border-radius:100px;">B2B</span>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="werbemittel" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">10 · Werbemittel &amp; Creatives</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Plakate, Flyer &amp; Creatives</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 40px;">Werbemittel folgen einem einfachen Baukasten: schmale blaue Balken, eine <strong>Mixed-Weight-Headline</strong>, die Bildmarke im Anschnitt und der Claim. So bleibt jedes Format — vom Plakat bis zur Social-Ad — sofort als Thoxan erkennbar.</p>

      <!-- principles -->
      <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:40px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#005DA8; margin-bottom:6px;">Balkenrahmung</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#5a6672; margin:0;">Schmale, rechtwinklige Balken oben &amp; unten — randabfallend.</p></div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#005DA8; margin-bottom:6px;">Eine Botschaft</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#5a6672; margin:0;">Eine klare Aussage je Werbemittel — nicht überladen.</p></div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#005DA8; margin-bottom:6px;">Bildmarke im Anschnitt</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#5a6672; margin:0;">Das „x“ groß, transparent, am Rand angeschnitten.</p></div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:22px;"><div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#005DA8; margin-bottom:6px;">Claim als Abschluss</div><p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#5a6672; margin:0;">Logo + Claim unten, klein und mit Punkt.</p></div>
      </div>

      <!-- print formats row -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Print-Formate</div>
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:24px; margin-bottom:44px; align-items:start;">

        <!-- Plakat A2 -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); overflow:hidden; aspect-ratio:420/594; background:#fff; display:flex; flex-direction:column; position:relative;">
            <div style="height:6px; background:#005DA8;"></div>
            <div style="height:42%; position:relative; overflow:hidden;"><img src="/assets/images/photo-team.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"></div>
            <img src="/assets/images/thoxan-x.svg" alt="" style="position:absolute; right:-30px; top:46%; height:200px; opacity:0.10;">
            <div style="flex:1; padding:26px 26px; display:flex; flex-direction:column; position:relative;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:28px; color:#0a0a0a; line-height:1.12;">Mehr <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Wunschkunden</strong> als Spezialanbieter.</div>
              <div style="margin-top:auto;">
                <img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:26px;">
                <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8; margin-top:8px;">frischer wind im netz.</div>
              </div>
            </div>
            <div style="height:6px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Plakat</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">A2 / A1 · Hochformat</div>
        </div>

        <!-- Flyer DIN lang -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); overflow:hidden; aspect-ratio:210/594; background:#005DA8; display:flex; flex-direction:column; position:relative;">
            <img src="/assets/images/thoxan-x-white.svg" alt="" style="position:absolute; left:-26px; bottom:-30px; height:200px; opacity:0.16;">
            <div style="flex:1; padding:26px 22px; display:flex; flex-direction:column; position:relative;">
              <img src="/assets/images/thoxan-logo-white.svg" alt="Thoxan" style="height:22px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:23px; color:#fff; line-height:1.15; margin-top:26px;">Deine Website soll <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">verkaufen.</strong></div>
              <div style="margin-top:auto; font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:12px; color:rgba(255,255,255,0.9); line-height:1.6;">Strategie · Websites · Online-Marketing</div>
            </div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Flyer</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">DIN lang / A5 · Blaufläche</div>
        </div>

        <!-- Roll-up -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); overflow:hidden; aspect-ratio:300/720; background:#fff; display:flex; flex-direction:column; position:relative;">
            <div style="height:6px; background:#005DA8;"></div>
            <img src="/assets/images/thoxan-x.svg" alt="" style="position:absolute; right:-24px; bottom:14%; height:200px; opacity:0.10;">
            <div style="flex:1; padding:30px 24px; display:flex; flex-direction:column; position:relative;">
              <img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:24px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:26px; color:#0a0a0a; line-height:1.14; margin-top:30px;"><strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">Sichtbarkeit</strong> ist eine Entscheidung.</div>
              <div style="margin-top:auto; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#005DA8;">thoxan.com</div>
            </div>
            <div style="height:6px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Roll-up</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">85 × 200 cm · Messe &amp; Event</div>
        </div>
      </div>

      <!-- digital ads row -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Digitale Creatives</div>
      <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:24px; align-items:start;">

        <!-- Anzeige / Banner quer -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); overflow:hidden; aspect-ratio:16/9; background:#fff; display:flex; flex-direction:column; position:relative;">
            <div style="height:6px; background:#005DA8; position:relative; z-index:2;"></div>
            <img src="/assets/images/photo-consult.jpg" alt="" style="position:absolute; right:0; top:0; bottom:0; width:46%; height:100%; object-fit:cover;">
            <div style="position:absolute; right:0; top:0; bottom:0; width:46%; background:linear-gradient(90deg,#fff 0%,rgba(255,255,255,0) 38%);"></div>
            <div style="flex:1; padding:34px 36px; display:flex; flex-direction:column; justify-content:center; position:relative; z-index:2;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:32px; color:#0a0a0a; line-height:1.12; max-width:62%;">Brauchst Du mehr <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">qualifizierte Anfragen?</strong></div>
              <div style="display:flex; align-items:center; gap:16px; margin-top:22px;">
                <span style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#005DA8; color:#fff; font-size:13px; padding:11px 20px; border-radius:100px;">Erzähl mir mehr →</span>
                <img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:22px;">
              </div>
            </div>
            <div style="height:6px; background:#005DA8; position:relative; z-index:2;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Anzeige / Display-Banner</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">Querformat · Web &amp; Print-Anzeige</div>
        </div>

        <!-- Social Ad 1:1 -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); overflow:hidden; aspect-ratio:1; background:#005DA8; display:flex; flex-direction:column; position:relative;">
            <img src="/assets/images/photo-workshop.jpg" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
            <div style="position:absolute; inset:0; background:linear-gradient(180deg,rgba(0,93,168,0.15) 0%,rgba(0,93,168,0.30) 45%,rgba(0,93,168,0.92) 100%);"></div>
            <div style="flex:1; padding:30px; display:flex; flex-direction:column; position:relative;">
              <img src="/assets/images/thoxan-logo-white.svg" alt="Thoxan" style="height:22px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:28px; color:#fff; line-height:1.16; margin-top:auto;">Aus erklärungsbedürftig wird <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">begehrenswert.</strong></div>
            </div>
            <div style="position:absolute; left:0; right:0; bottom:0; height:7px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Social Ad</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">1:1 / 4:5 · Feed &amp; Stories</div>
        </div>
      </div>

      <!-- own creative dropzone -->
      <div style="border:1px solid #e4e9ee; border-radius:12px; padding:28px; background:#f7f9fb; margin-top:40px;">
        <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:14px;">Eigenes Creative testen</div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
          <div style="width:150px; height:212px; flex:none;; border:1px dashed #b9c4ce; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">Plakat / Flyer</span></div>
          <div style="width:240px; height:135px; flex:none;; border:1px dashed #b9c4ce; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">Anzeige / Banner</span></div>
          <div style="width:135px; height:135px; flex:none;; border:1px dashed #b9c4ce; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;"><span style="font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">Social Ad</span></div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; line-height:1.6; color:#7a8590; margin:0; align-self:center; max-width:200px;">Lege ein fertiges Werbemittel ab, um es im Markenkontext zu prüfen.</p>
        </div>
      </div>
    </section>
<section class="sg-section" data-section="geschaeft" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">11 · Geschäftsausstattung</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Briefbogen, Stempel &amp; Visitenkarte</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 40px;">Jede Gesellschaft hat ihre eigene Geschäftsausstattung — gemeinsamer Aufbau, eigene Daten. Adresse und Bankverbindung sind identisch, <strong>Web-Domain, Durchwahl und Registernummer</strong> unterscheiden sich.</p>

      <!-- letterheads -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Briefbogen je Gesellschaft</div>
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:24px; margin-bottom:48px; align-items:start;">

        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.07); overflow:hidden; aspect-ratio:210/297; background:#fff; display:flex; flex-direction:column;">
            <div style="height:6px; background:#005DA8;"></div>
            <div style="flex:1; padding:24px 22px; display:flex; flex-direction:column;">
              <img src="/assets/images/thoxan-logo.svg" alt="Thoxan GmbH" style="height:26px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:9px; color:#9aa4b2; line-height:1.7; margin-top:auto;">Thoxan GmbH · Eicksen 55<br>32479 Hille-Rothenuffeln<br><span style="color:#005DA8; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">www.thoxan.de</span></div>
            </div>
            <div style="height:6px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Holding</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">thoxan.de · Durchwahl -0</div>
        </div>

        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.07); overflow:hidden; aspect-ratio:210/297; background:#fff; display:flex; flex-direction:column;">
            <div style="height:6px; background:#005DA8;"></div>
            <div style="flex:1; padding:24px 22px; display:flex; flex-direction:column;">
              <img src="/assets/images/logo-communications.png" alt="Thoxan Communications GmbH" style="height:40px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:9px; color:#9aa4b2; line-height:1.7; margin-top:auto;">Thoxan Communications GmbH · Eicksen 55<br>32479 Hille-Rothenuffeln<br><span style="color:#005DA8; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">www.thoxan.com</span></div>
            </div>
            <div style="height:6px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Communications</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">thoxan.com · Durchwahl -0</div>
        </div>

        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.07); overflow:hidden; aspect-ratio:210/297; background:#fff; display:flex; flex-direction:column;">
            <div style="height:6px; background:#005DA8;"></div>
            <div style="flex:1; padding:24px 22px; display:flex; flex-direction:column;">
              <img src="/assets/images/logo-ecommerce.svg" alt="Thoxan E-Commerce GmbH" style="height:40px; align-self:flex-start;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:9px; color:#9aa4b2; line-height:1.7; margin-top:auto;">Thoxan E-Commerce GmbH · Eicksen 55<br>32479 Hille-Rothenuffeln<br><span style="color:#005DA8; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">www.thoxan.biz</span></div>
            </div>
            <div style="height:6px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">E-Commerce</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">thoxan.biz · Durchwahl -10</div>
        </div>
      </div>

      <!-- card + stamp -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Visitenkarte &amp; Stempel</div>
      <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:24px; margin-bottom:48px; align-items:start;">

        <!-- business card -->
        <div>
          <div style="border:1px solid #e4e9ee; box-shadow:0 8px 28px rgba(0,0,0,0.08); border-radius:6px; overflow:hidden; aspect-ratio:85/55; background:#fff; display:flex; flex-direction:column;">
            <div style="height:7px; background:#005DA8;"></div>
            <div style="flex:1; padding:26px 30px; display:flex; flex-direction:column; justify-content:space-between;">
              <img src="/assets/images/logo-communications.png" alt="Thoxan Communications GmbH" style="height:46px; align-self:flex-start;">
              <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                  <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:17px; color:#0a0a0a;">Thomas Kilian</div>
                  <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#5a6672;">Geschäftsführer</div>
                </div>
                <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:11px; color:#9aa4b2; text-align:right; line-height:1.7;">+49 5734 969 28-0<br><span style="color:#005DA8; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif;">info@thoxan.com</span></div>
              </div>
            </div>
            <div style="height:7px; background:#005DA8;"></div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Visitenkarte</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">85 × 55 mm · Vorderseite</div>
        </div>

        <!-- stamp -->
        <div>
          <div style="border:1px solid #e4e9ee; border-radius:12px; padding:34px; display:flex; align-items:center; justify-content:center; aspect-ratio:85/55;">
            <div style="border:2px solid #005DA8; border-radius:4px; padding:16px 20px; max-width:100%;">
              <img src="/assets/images/logo-communications.png" alt="" style="height:30px; display:block; margin-bottom:8px;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:10px; color:#0a0a0a; line-height:1.6;">Eicksen 55 · 32479 Hille-Rothenuffeln<br>Tel. +49 5734 969 28-0 · thoxan.com</div>
            </div>
          </div>
          <div style="margin-top:12px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:14px; color:#000;">Stempel</div>
          <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590;">Rechteckig, Kontur in Thoxan Blau</div>
        </div>
      </div>

      <!-- legal data -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Rechtliche Angaben (Fußzeile)</div>
      <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr; background:#f7f9fb; border-bottom:1px solid #e4e9ee;">
          <div style="padding:14px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#9aa4b2;"></div>
          <div style="padding:14px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000;">Holding</div>
          <div style="padding:14px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000;">Communications</div>
          <div style="padding:14px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:13px; color:#000;">E-Commerce</div>
        </div>
        <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#0a0a0a;">
          <div style="display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr; border-bottom:1px solid #eef1f4;">
            <div style="padding:13px 18px; color:#7a8590;">Web</div><div style="padding:13px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; color:#005DA8;">thoxan.de</div><div style="padding:13px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; color:#005DA8;">thoxan.com</div><div style="padding:13px 18px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; color:#005DA8;">thoxan.biz</div>
          </div>
          <div style="display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr; border-bottom:1px solid #eef1f4;">
            <div style="padding:13px 18px; color:#7a8590;">Telefon</div><div style="padding:13px 18px;">…969 28-0</div><div style="padding:13px 18px;">…969 28-0</div><div style="padding:13px 18px;">…969 28-10</div>
          </div>
          <div style="display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr; border-bottom:1px solid #eef1f4;">
            <div style="padding:13px 18px; color:#7a8590;">HRB (AG Bad Oeynhausen)</div><div style="padding:13px 18px;">11123</div><div style="padding:13px 18px;">12758</div><div style="padding:13px 18px;">12613</div>
          </div>
          <div style="display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr;">
            <div style="padding:13px 18px; color:#7a8590;">USt-IdNr.</div><div style="padding:13px 18px;">DE 262 752 103</div><div style="padding:13px 18px;">DE 279 414 939</div><div style="padding:13px 18px;">DE 277 430 872</div>
          </div>
        </div>
      </div>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; line-height:1.6; margin:16px 0 0;">Gemeinsam: Eicksen 55, 32479 Hille-Rothenuffeln · Sparkasse Herford (BIC WLAHDE44XXX) · Geschäftsführer Thomas Kilian · Sitz Hille · Finanzamt Minden.</p>
    </section>
<section class="sg-section" data-section="anwendung" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">12 · Anwendung</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Anwendungsbeispiele</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">So setzt sich das System in der Praxis zusammen — über Web, Print und Social hinweg.</p>

      <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:20px; margin-bottom:20px;">
        <!-- web -->
        <div style="border:1px solid #e4e9ee; border-radius:12px; overflow:hidden;">
          <div style="background:#f1f4f7; padding:10px 14px; display:flex; gap:6px; align-items:center; border-bottom:1px solid #e4e9ee;">
            <span style="width:10px; height:10px; border-radius:50%; background:#d8dee5;"></span>
            <span style="width:10px; height:10px; border-radius:50%; background:#d8dee5;"></span>
            <span style="width:10px; height:10px; border-radius:50%; background:#d8dee5;"></span>
            <span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2; margin-left:10px;">thoxan.com</span>
          </div>
          <div style="background:#005DA8; padding:40px; position:relative; overflow:hidden;">
            <img src="/assets/images/thoxan-x-white.svg" alt="" style="position:absolute; right:-24px; bottom:-56px; height:220px; opacity:0.14;">
            <div style="position:relative;">
              <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:34px; color:#fff; line-height:1.0;">Verkaufsstarke Websites für B2B.</div>
              <span style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; background:#fff; color:#005DA8; font-size:13px; padding:11px 20px; border-radius:100px; margin-top:20px;">Erzähl mir mehr →</span>
            </div>
          </div>
          <div style="padding:14px 16px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">WEB · Landingpage-Hero</span></div>
        </div>
        <!-- social -->
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:14px; padding-bottom:0;">
          <div style="aspect-ratio:1; background:#fff; display:flex; flex-direction:column; border:1px solid #eef1f4; overflow:hidden;">
            <div style="height:8px; background:#005DA8;"></div>
            <div style="flex:1; padding:34px; display:flex; flex-direction:column; justify-content:center;">
              <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:30px; color:#0a0a0a; line-height:1.12;">Sichtbarkeit ist eine <strong style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; color:#005DA8;">Entscheidung.</strong></div>
              <div style="margin-top:22px;"><img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:24px;"></div>
            </div>
            <div style="height:8px; background:#005DA8;"></div>
          </div>
          <div style="padding:12px 4px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">SOCIAL · 1:1 Post</span></div>
        </div>
      </div>

      <!-- print letterhead -->
      <div style="border:1px solid #e4e9ee; box-shadow:0 6px 24px rgba(0,0,0,0.06); overflow:hidden; max-width:560px;">
        <div style="height:9px; background:#005DA8;"></div>
        <div style="padding:40px 44px; min-height:300px; background:#fff;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div><img src="/assets/images/thoxan-logo.svg" alt="Thoxan" style="height:44px;"></div>
            <div style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:11px; color:#5a6672; text-align:right; line-height:1.7;">Thoxan GmbH<span style="display:inline-block; width:14px; height:2px; background:#005DA8; margin:0 8px; vertical-align:middle;"></span>Eicksen 55<br>32479 Hille-Rothenuffeln</div>
          </div>
          <div style="margin-top:60px; font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#9aa4b2; line-height:1.8;">Sehr geehrte Damen und Herren,<br><br>————————————————————<br>————————————————————————————<br>——————————————————</div>
          <div style="margin-top:50px; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:15px; color:#005DA8;">Wunschkunden-Gewinnung im Internet.</div>
        </div>
        <div style="height:9px; background:#005DA8;"></div>
      </div>
      <div style="margin-top:14px;"><span style="font-family:'IBM Plex Mono',monospace; font-size:10px; color:#9aa4b2;">PRINT · Briefbogen mit Balkenrahmung &amp; Trennstrichen</span></div>
    </section>
<section class="sg-section" data-section="markenentwicklung" style="padding:64px 80px 80px;; display:none;">
      <div style="display:inline-block; font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; letter-spacing:0.01em; color:#005DA8; background:#eaf2f8; padding:7px 15px; border-radius:100px;">13 · Markenentwicklung</div>
      <h2 style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:40px; color:#0a0a0a; letter-spacing:-0.012em; margin:14px 0 18px; line-height:1.08;">Woher die Marke kommt</h2>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:16px; line-height:1.7; color:#4a5663; max-width:680px; margin:0 0 44px;">Die figürliche „x"-Marke und das Thoxan Blau begleiten die Agentur seit den Anfängen. Was sich gewandelt hat, ist die Positionierung — vom technischen Internet-Dienstleister zum Partner für Wunschkunden-Gewinnung. Dieser Abschnitt ordnet historisches Material ein.</p>

      <!-- claim timeline -->
      <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:12px; color:#005DA8; letter-spacing:0.01em; margin-bottom:18px;">Der Claim im Wandel</div>
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:48px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #cfe0ef;">
          <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; margin-bottom:12px;">~2003–2015</div>
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:22px; color:#0a0a0a; line-height:1.15;">agentur für<br>neue medien</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; line-height:1.6; margin:14px 0 0;">Technischer Fokus, Beschreibung des Tätigkeitsfelds.</p>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #7fb0d8;">
          <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9aa4b2; margin-bottom:12px;">Übergang</div>
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:22px; color:#0a0a0a; line-height:1.15;">frischer wind<br>im netz.</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; line-height:1.6; margin:14px 0 0;">Bildhafter, sympathischer Claim — klein und mit Punkt.</p>
        </div>
        <div style="border-radius:12px; padding:30px; background:#005DA8;">
          <div style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:rgba(255,255,255,0.7); margin-bottom:12px;">heute</div>
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:22px; color:#fff; line-height:1.15;">Wunschkunden-Gewinnung im Internet.</div>
          <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:rgba(255,255,255,0.85); line-height:1.6; margin:14px 0 0;">Nutzenorientiert, klar auf das Kundenergebnis bezogen.</p>
        </div>
      </div>

      <!-- constants vs changed -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #1a9d5a;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:14px;">Konstanten</div>
          <ul style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.9; margin:0; padding-left:18px;">
            <li>Die figürliche „x"-Marke</li>
            <li>Thoxan Blau als Leitfarbe</li>
            <li>Frutiger als Hausschrift</li>
            <li>Blaue Balken als Rahmungselement</li>
          </ul>
        </div>
        <div style="border:1px solid #e4e9ee; border-radius:12px; padding:30px; border-top:4px solid #d8a72b;">
          <div style="font-family:'Frutiger Bold','Frutiger LT Std',sans-serif; font-size:16px; color:#000; margin-bottom:14px;">Weiterentwickelt</div>
          <ul style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:14px; color:#5a6672; line-height:1.9; margin:0; padding-left:18px;">
            <li>Gruppenstruktur: Holding · Communications · E-Commerce</li>
            <li>Frutiger 95 zurückgenommen (nur noch Akzent)</li>
            <li>Balken schmaler, Ecken rechtwinklig</li>
            <li>Eyebrows als Pill-Badges statt Versal-Labels</li>
          </ul>
        </div>
      </div>
      <p style="font-family:'Frutiger Light','Frutiger LT Std',sans-serif; font-size:13px; color:#7a8590; line-height:1.6; margin:24px 0 0;">Hinweis: „Thoxan Innovations" ist mit der Thoxan Communications GmbH verschmolzen und entfällt. Älteres Material (rasani-, WITTEKIND-, Mainusch-Styleguides, Werbemittel 2003–2019) dient als Referenz der Markenentwicklung, nicht als aktuelle Vorgabe.</p>
    </section>
    </div>
    <div class="sg-main-bar"></div>
  </main>
</div>

<script>
(function () {
  function sgGo(id) {
    document.querySelectorAll('.sg-section').forEach(function (s) {
      s.style.display = (s.dataset.section === id) ? 'block' : 'none';
    });
    document.querySelectorAll('.sg-nav-item').forEach(function (n) {
      n.classList.toggle('active', n.dataset.target === id);
    });
    try { if (history.replaceState) history.replaceState(null, '', '#' + id); } catch (e) {}
    try {
      var mc = document.querySelector('.main-content');
      if (mc && mc.scrollTo) mc.scrollTo({ top: 0 }); else window.scrollTo(0, 0);
    } catch (e) {}
  }
  window.sgGo = sgGo;
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sg-go]');
    if (t) { e.preventDefault(); sgGo(t.getAttribute('data-sg-go')); }
  });
  var initial = (location.hash || '').replace('#', '') || 'uebersicht';
  if (!document.querySelector('.sg-section[data-section="' + initial + '"]')) initial = 'uebersicht';
  sgGo(initial);
})();
</script>
