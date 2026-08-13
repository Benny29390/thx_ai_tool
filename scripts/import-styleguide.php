<?php
/**
 * Importer: Claude-Design-Export  ->  views/admin/styleguide.php
 * ---------------------------------------------------------------
 * Wandelt den Roh-Export aus dem Claude-Design-Projekt "Thoxan Styleguide
 * Entwicklung" in eine eigenstaendige, in sich geschlossene App-View um:
 *   - Template-Direktiven (sc-if / sc-for) werden aufgeloest
 *   - Editier-Komponenten (<image-slot>) werden zu statischen Platzhaltern
 *   - Asset-Pfade (assets/*.svg) zeigen auf /assets/images/*
 *   - Frutiger-Schriftnamen werden auf die App-Webfont (Frutiger LT Std) gemappt
 *
 * AUF ZURUF AKTUALISIEREN:
 *   1. Neuen Export aus Claude Design holen und als
 *      docs/design-reference/thoxan-styleguide/Thoxan Styleguide.dc.html ablegen
 *      (macht normalerweise Claude via Design-Anbindung).
 *   2.  php scripts/import-styleguide.php
 *   -> regeneriert views/admin/styleguide.php
 *
 * Details: docs/design-reference/thoxan-styleguide/README.md
 */

$SRC  = __DIR__ . '/../docs/design-reference/thoxan-styleguide/Thoxan Styleguide.dc.html';
// Generiert wird ein PARTIAL (der "Corporate Design"-Reiter). Der Seiten-Wrapper mit
// den Reitern liegt hand-geschrieben in views/admin/styleguide.php.
$DEST = __DIR__ . '/../views/admin/styleguide/_corporate.php';
@mkdir(dirname($DEST), 0775, true);

// Cache-Buster fuer die Webfonts: aendert sich automatisch, sobald die woff2 neu sind.
$fontDir = __DIR__ . '/../assets/fonts/styleguide';
$fontVer = substr(md5(implode('|', array_map(
    fn($n) => (string) @filemtime("$fontDir/$n"),
    ['frutiger-45-light.woff2', 'frutiger-65-bold.woff2', 'frutiger-95-ultrablack.woff2']
))), 0, 8);

if (!is_file($SRC)) {
    fwrite(STDERR, "Quelle fehlt: $SRC\n");
    exit(1);
}
$html = file_get_contents($SRC);

// --- Daten datengetrieben aus dem Export parsen (Single Source of Truth: das .dc.html).
//     So werden neue/entfallene Menuepunkte automatisch uebernommen — nichts hartkodiert.
//     Das feste Layout (Sidebar links + Hauptbereich rechts) bleibt davon unberuehrt. ---

/** Inhalt eines JS-Array-Literals `const NAME = [ ... ];` aus dem Export holen. */
function jsArrayBody(string $html, string $name): string {
    return preg_match('#const\s+' . preg_quote($name, '#') . '\s*=\s*\[(.*?)\]\s*;#s', $html, $m) ? $m[1] : '';
}

// nav: [{ id, no, label }] -> Menuepunkte inkl. Reihenfolge (= Zahl der Sidebar-Eintraege)
$nav = [];
if (preg_match_all("#\{\s*id:'([^']+)'\s*,\s*no:'([^']*)'\s*,\s*label:'([^']*)'\s*\}#", jsArrayBody($html, 'nav'), $nm, PREG_SET_ORDER)) {
    foreach ($nm as $m) $nav[] = ['id' => $m[1], 'no' => $m[2], 'label' => $m[3]];
}

// stateVar <-> id aus dem Return-Mapping `sXxx: cur==='id'`
$stateToId = [];
if (preg_match_all("#(s[A-Za-z]+)\s*:\s*cur===\s*'([a-z]+)'#", $html, $sm, PREG_SET_ORDER)) {
    foreach ($sm as $m) $stateToId[$m[1]] = $m[2];
}

// neutrals: name + hex (background == hex). Robust gegen Feld-Reihenfolge.
$neutrals = [];
$nb = jsArrayBody($html, 'neutralsOut') ?: jsArrayBody($html, 'neutrals');
if (preg_match_all("#name:'([^']*)'\s*,\s*hex:'([^']*)'#", $nb, $cm, PREG_SET_ORDER)) {
    foreach ($cm as $m) $neutrals[] = ['name' => $m[1], 'hex' => $m[2], 'background' => $m[2]];
}

// spacing: [{ label, w }]
$spacing = [];
if (preg_match_all("#label:'([^']*)'\s*,\s*w:'([^']*)'#", jsArrayBody($html, 'spacing'), $pm, PREG_SET_ORDER)) {
    foreach ($pm as $m) $spacing[] = ['label' => $m[1], 'w' => $m[2]];
}

// cols: Anzahl Rasterspalten (Array.from({length:N}))
$colCount = preg_match('#const\s+cols\s*=\s*Array\.from\(\{\s*length\s*:\s*(\d+)#', $html, $cc) ? (int) $cc[1] : 12;

if (empty($nav) || empty($stateToId)) {
    fwrite(STDERR, "FEHLER: nav/Mapping nicht aus dem Export geparst — Abbruch (Layout bleibt unveraendert).\n");
    exit(1);
}

// --- Hilfsfunktionen: sc-for-Aufloesung -------------------------------------
/** Ersetzt einen <sc-for list="{{ NAME }}" ...> TEMPLATE </sc-for>-Block. */
function resolveScFor(string $section, string $listName, callable $renderItem): string {
    $pattern = '#<sc-for\s+list="\{\{\s*' . preg_quote($listName, '#') . '\s*\}\}"[^>]*>(.*?)</sc-for>#s';
    return preg_replace_callback($pattern, function ($m) use ($renderItem) {
        return $renderItem($m[1]); // $m[1] = Template zwischen den Tags
    }, $section);
}

/** <image-slot ...></image-slot>  ->  statischer Platzhalter / Bild. */
function resolveImageSlots(string $section): string {
    return preg_replace_callback('#<image-slot\b([^>]*)></image-slot>#s', function ($m) {
        $attrs = $m[1];
        $style = preg_match('#style="([^"]*)"#', $attrs, $s) ? $s[1] : '';
        $src   = preg_match('#\ssrc="([^"]*)"#', $attrs, $r) ? $r[1] : '';
        $ph    = preg_match('#placeholder="([^"]*)"#', $attrs, $p) ? $p[1] : '';
        $rad   = preg_match('#radius="([^"]*)"#', $attrs, $rd) ? (int)$rd[1] : 8;
        $box   = $style . '; border:1px dashed #b9c4ce; border-radius:' . $rad . 'px; background:#fff; '
               . 'display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center;';
        if ($src !== '') {
            $imgSrc = preg_replace('#^assets/#', '/assets/images/', $src);
            $inner = '<img src="' . htmlspecialchars($imgSrc, ENT_QUOTES) . '" alt="" style="max-width:84%; max-height:84%;">';
        } else {
            $inner = '<span style="font-family:\'IBM Plex Mono\',ui-monospace,monospace; font-size:11px; color:#9aabb9; padding:8px; line-height:1.4;">'
                   . htmlspecialchars($ph) . '</span>';
        }
        return '<div style="' . $box . '">' . $inner . '</div>';
    }, $section);
}

// --- Sektionen aus dem Export ziehen ----------------------------------------
$sectionsById = [];
if (preg_match_all('#<sc-if\s+value="\{\{\s*(s\w+)\s*\}\}"[^>]*>\s*(<section.*?</section>)\s*</sc-if>#s', $html, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        $stateVar = $m[1];
        $sectionHtml = $m[2];
        if (!isset($stateToId[$stateVar])) continue;
        $id = $stateToId[$stateVar];

        // sc-for: neutrals (Farben)
        $sectionHtml = resolveScFor($sectionHtml, 'neutrals', function ($tpl) use ($neutrals) {
            $out = '';
            foreach ($neutrals as $c) {
                $out .= strtr($tpl, [
                    '{{ c.background }}' => $c['background'],
                    '{{ c.name }}'       => $c['name'],
                    '{{ c.hex }}'        => $c['hex'],
                ]);
            }
            return $out;
        });
        // sc-for: cols (N-Spalten-Raster, Anzahl aus dem Export)
        $sectionHtml = resolveScFor($sectionHtml, 'cols', function ($tpl) use ($colCount) {
            return str_repeat($tpl, $colCount);
        });
        // sc-for: spacing (Abstandsskala)
        $sectionHtml = resolveScFor($sectionHtml, 'spacing', function ($tpl) use ($spacing) {
            $out = '';
            foreach ($spacing as $s) {
                $out .= strtr($tpl, ['{{ s.label }}' => $s['label'], '{{ s.w }}' => $s['w']]);
            }
            return $out;
        });
        // sc-for: tocItems (Inhaltsverzeichnis in der Übersicht)
        $sectionHtml = resolveScFor($sectionHtml, 'tocItems', function ($tpl) use ($nav) {
            $out = '';
            foreach ($nav as $n) {
                if ($n['id'] === 'uebersicht') continue;
                $item = strtr($tpl, ['{{ t.no }}' => $n['no'], '{{ t.label }}' => htmlspecialchars($n['label'])]);
                // onClick-Binding -> Section-Switch
                $item = preg_replace('#onClick="\{\{\s*t\.onClick\s*\}\}"#', 'data-sg-go="' . $n['id'] . '"', $item);
                $out .= $item;
            }
            return $out;
        });

        // image-slots -> Platzhalter
        $sectionHtml = resolveImageSlots($sectionHtml);

        // DC-spezifische Hover-Direktive (style-hover) entfernen — als reines HTML inert
        $sectionHtml = preg_replace('#\s+style-hover="[^"]*"#', '', $sectionHtml);

        // Sicherer Schrift-Fallback: jede Inline-font-family der Design-Schnitte um
        // 'Frutiger LT Std' (App-Webfont) + sans-serif erweitern. So erscheint NIE eine
        // Serifenschrift, falls eine Brand-Schrift mal nicht lädt. Konsumiert ein evtl.
        // bereits vorhandenes ",sans-serif" mit, damit nichts doppelt wird.
        $sectionHtml = preg_replace(
            "#'Frutiger (Light|Bold|Black)'(,sans-serif)?#",
            "'Frutiger \$1','Frutiger LT Std',sans-serif",
            $sectionHtml
        );

        // Asset-Pfade umbiegen
        $sectionHtml = str_replace('src="assets/', 'src="/assets/images/', $sectionHtml);

        // <section ...> mit Klasse + data-section + Default-Sichtbarkeit versehen
        $disp = ($id === 'uebersicht') ? 'block' : 'none';
        $sectionHtml = preg_replace(
            '#^<section\s+style="([^"]*)">#',
            '<section class="sg-section" data-section="' . $id . '" style="$1; display:' . $disp . ';">',
            $sectionHtml,
            1
        );

        $sectionsById[$id] = $sectionHtml;
    }
}

if (count($sectionsById) < 12) {
    fwrite(STDERR, 'WARN: nur ' . count($sectionsById) . " von 12 Sektionen erkannt.\n");
}

// --- Linke Sektions-Navigation (eigene, statt der DC-Aside) -----------------
$navHtml = '';
foreach ($nav as $n) {
    $navHtml .= '          <div class="nav-item sg-nav-item" data-sg-go="' . $n['id'] . '" data-target="' . $n['id'] . '">'
              . '<span class="nav-icon sg-nav-num">' . $n['no'] . '</span>'
              . '<span class="sg-nav-label">' . htmlspecialchars($n['label']) . '</span></div>' . "\n";
}

// Sektionen in nav-Reihenfolge zusammensetzen
$sectionsHtml = '';
foreach ($nav as $n) {
    if (isset($sectionsById[$n['id']])) {
        $sectionsHtml .= $sectionsById[$n['id']] . "\n";
    }
}

// --- View zusammenbauen ------------------------------------------------------
$generated = date('Y-m-d H:i');
$view = <<<PHP
<?php
/**
 * GENERIERT von scripts/import-styleguide.php am {$generated} — NICHT von Hand bearbeiten.
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
@font-face { font-family:'Frutiger Light'; src:url('/assets/fonts/styleguide/frutiger-45-light.woff2?v={$fontVer}') format('woff2'); font-weight:400; font-display:swap; }
@font-face { font-family:'Frutiger Bold';  src:url('/assets/fonts/styleguide/frutiger-65-bold.woff2?v={$fontVer}') format('woff2'); font-weight:700; font-display:swap; }
@font-face { font-family:'Frutiger Black'; src:url('/assets/fonts/styleguide/frutiger-95-ultrablack.woff2?v={$fontVer}') format('woff2'); font-weight:900; font-display:swap; }

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
{$navHtml}    </nav>
  </aside>

  <main class="sg-main">
    <div class="sg-main-bar"></div>
    <div class="sg-main-body">
{$sectionsHtml}    </div>
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

PHP;

file_put_contents($DEST, $view);
echo "OK: " . count($sectionsById) . " Sektionen -> " . realpath($DEST) . " (" . strlen($view) . " bytes)\n";
