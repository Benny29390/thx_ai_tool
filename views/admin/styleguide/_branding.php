<?php
/**
 * Styleguide-Reiter „Eigenes Branding" — pro Installation anpassbares Erscheinungsbild.
 * Schreibt in die settings-Tabelle (app_name, app_logo, brand_primary_color,
 * brand_logo_icon_path); Core\Brand liest sie und faerbt die gesamte Oberflaeche.
 */
$sv = fn(string $k, string $d = ''): string => (string) (\Core\Settings::get($k) ?? $d);
$primary   = $sv('brand_primary_color');
$appName   = $sv('app_name', defined('APP_NAME') ? APP_NAME : 'KI Text Tool');
$appLogo   = $sv('app_logo');
$iconPath  = $sv('brand_logo_icon_path');
?>
<div class="brand-grid">

    <!-- Produktname -->
    <div class="thx-card">
        <h2 class="brand-h2">Produktname</h2>
        <p class="brand-sub">Erscheint in Kopfzeile, Titel, E-Mails und Exporten.</p>
        <form onsubmit="event.preventDefault(); App.post('/admin/settings',{app_name:this.app_name.value}).then(()=>{App.showNotification('Gespeichert','success');setTimeout(()=>location.reload(),700);});">
            <input type="text" name="app_name" class="thx-input" value="<?= htmlspecialchars($appName) ?>" style="max-width:340px;">
            <div style="margin-top:12px;"><button class="thx-btn thx-btn-primary">Speichern</button></div>
        </form>
    </div>

    <!-- Primaerfarbe + Live-Palette -->
    <div class="thx-card">
        <h2 class="brand-h2">Primärfarbe</h2>
        <p class="brand-sub">Bestimmt die gesamte Markenfarbe der Oberfläche. Aus dieser einen Farbe wird die komplette Abstufung erzeugt — die Vorschau zeigt sie live.</p>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <input type="color" id="bp-picker" value="<?= htmlspecialchars($primary !== '' ? $primary : '#006fb9') ?>"
                   style="width:56px;height:44px;border:1px solid var(--slate-300);border-radius:8px;background:none;cursor:pointer;">
            <input type="text" id="bp-hex" class="thx-input" value="<?= htmlspecialchars($primary) ?>" placeholder="#006fb9"
                   pattern="^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$" style="width:130px;font-family:var(--font-mono,monospace);">
            <span class="brand-sub" style="margin:0;">Standard: <code>#006fb9</code></span>
        </div>

        <div class="brand-ramp" id="bp-ramp" style="margin-top:16px;"></div>

        <!-- Mini-Vorschau echter Elemente -->
        <div class="brand-preview" id="bp-preview" style="margin-top:16px;">
            <button class="bp-demo-btn">Beispiel-Button</button>
            <span class="bp-demo-chip">Aktiv-Markierung</span>
            <a href="#" class="bp-demo-link" onclick="return false;">Ein Link</a>
        </div>

        <div style="margin-top:16px;display:flex;gap:8px;">
            <button class="thx-btn thx-btn-primary" onclick="bpSave()">Farbe speichern</button>
            <?php if ($primary !== ''): ?>
            <button class="thx-btn" onclick="App.post('/admin/settings',{brand_primary_color:''}).then(()=>location.reload());">Zurücksetzen</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logo -->
    <div class="thx-card">
        <h2 class="brand-h2">Logo</h2>
        <p class="brand-sub">Volles Logo für die Seitenleiste. SVG oder PNG hochladen.</p>
        <div class="brand-drop" id="logo-drop">
            <div>Logo hierher ziehen oder klicken (SVG / PNG)</div>
            <input type="file" id="logo-input" accept=".svg,.png,image/svg+xml,image/png" hidden>
        </div>
        <div class="brand-logo-preview" id="logo-preview" style="<?= $appLogo === '' ? 'display:none;' : '' ?>">
            <?= $appLogo /* trusted admin input */ ?>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <button class="thx-btn thx-btn-primary" onclick="logoSave()">Logo speichern</button>
            <?php if ($appLogo !== ''): ?>
            <button class="thx-btn" onclick="App.post('/admin/settings',{app_logo:''}).then(()=>location.reload());">Entfernen</button>
            <?php endif; ?>
        </div>
        <input type="hidden" id="logo-code" value="<?= htmlspecialchars($appLogo) ?>">
    </div>

    <!-- Icon / Favicon -->
    <div class="thx-card">
        <h2 class="brand-h2">Symbol (eingeklappte Leiste &amp; Favicon)</h2>
        <p class="brand-sub">Kleines quadratisches Zeichen für die eingeklappte Seitenleiste und den Browser-Tab. PNG oder SVG.</p>
        <div class="brand-drop" id="icon-drop">
            <div>Symbol hierher ziehen oder klicken</div>
            <input type="file" id="icon-input" accept=".svg,.png,image/svg+xml,image/png" hidden>
        </div>
        <div class="brand-icon-preview" id="icon-preview" style="<?= $iconPath === '' ? 'display:none;' : '' ?>">
            <img src="<?= htmlspecialchars($iconPath) ?>" alt="Symbol">
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <button class="thx-btn thx-btn-primary" onclick="iconSave()">Symbol speichern</button>
            <?php if ($iconPath !== ''): ?>
            <button class="thx-btn" onclick="App.post('/admin/settings',{brand_logo_icon_path:''}).then(()=>{App.post('/admin/settings',{brand_favicon:''}).then(()=>location.reload());});">Entfernen</button>
            <?php endif; ?>
        </div>
        <input type="hidden" id="icon-data" value="<?= htmlspecialchars($iconPath) ?>">
    </div>

</div>

<style>
.brand-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(360px,1fr)); gap:16px; max-width:1100px; }
.brand-h2 { margin:0; font-size:var(--d-fs-base); font-weight:700; color:var(--slate-900); }
.brand-sub { margin:2px 0 12px; font-size:var(--d-fs-sm); color:var(--slate-500); }
.brand-ramp { display:flex; gap:0; border-radius:8px; overflow:hidden; border:1px solid var(--slate-200); }
.brand-ramp .stop { flex:1; height:44px; position:relative; }
.brand-ramp .stop span { position:absolute; bottom:2px; left:0; right:0; text-align:center; font-size:9px;
    color:rgba(255,255,255,.9); text-shadow:0 1px 1px rgba(0,0,0,.4); }
.brand-ramp .stop.light span { color:rgba(0,0,0,.55); text-shadow:none; }
.brand-preview { display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding:14px; border:1px dashed var(--slate-200); border-radius:8px; }
.bp-demo-btn { background:var(--bp-500,#006fb9); color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:default; }
.bp-demo-chip { background:var(--bp-50,#e6f0f8); color:var(--bp-700,#004a86); padding:4px 12px; border-radius:20px; font-size:var(--d-fs-sm); font-weight:600; }
.bp-demo-link { color:var(--bp-600,#005da8); font-weight:600; text-decoration:none; }
.brand-drop { border:2px dashed var(--slate-300); border-radius:8px; padding:22px; text-align:center; cursor:pointer;
    background:var(--slate-50); color:var(--slate-600); font-size:var(--d-fs-sm); transition:border-color .15s; }
.brand-drop.drag { border-color:var(--thoxan-500); }
.brand-logo-preview { margin-top:12px; border:1px solid var(--slate-200); border-radius:6px; padding:16px; background:#fff;
    display:flex; align-items:center; justify-content:center; min-height:70px; }
.brand-logo-preview svg, .brand-logo-preview img { max-width:240px; max-height:56px; width:auto; height:auto; }
.brand-icon-preview { margin-top:12px; }
.brand-icon-preview img { width:44px; height:44px; object-fit:contain; border:1px solid var(--slate-200); border-radius:8px; padding:4px; background:#fff; }
</style>

<script>
(function () {
    // Rampe exakt wie in core/Brand.php (mixWhite/mixBlack, gleiche Verhaeltnisse).
    var LIGHT = {50:0.90,100:0.80,200:0.60,300:0.40,400:0.20};
    var DARK  = {600:0.10,700:0.30,800:0.47,900:0.65,950:0.80};
    function toRgb(h){ h=h.replace('#',''); if(h.length===3){h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2];}
        return [parseInt(h.slice(0,2),16),parseInt(h.slice(2,4),16),parseInt(h.slice(4,6),16)]; }
    function hex(r,g,b){ return '#'+[r,g,b].map(function(x){return Math.max(0,Math.min(255,Math.round(x))).toString(16).padStart(2,'0');}).join(''); }
    function mixW(c,r){var p=toRgb(c);return hex(p[0]+(255-p[0])*r,p[1]+(255-p[1])*r,p[2]+(255-p[2])*r);}
    function mixB(c,r){var p=toRgb(c);var f=1-r;return hex(p[0]*f,p[1]*f,p[2]*f);}
    function isHex(v){ return /^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v); }
    function ramp(primary){
        var out={}; Object.keys(LIGHT).forEach(function(s){out[s]=mixW(primary,LIGHT[s]);});
        out[500]=hex.apply(null,toRgb(primary));
        Object.keys(DARK).forEach(function(s){out[s]=mixB(primary,DARK[s]);}); return out;
    }
    var picker=document.getElementById('bp-picker'), hexIn=document.getElementById('bp-hex'),
        rampBox=document.getElementById('bp-ramp'), preview=document.getElementById('bp-preview');
    function normalize(v){ v=v.trim(); if(v && v[0]!=='#') v='#'+v; return v; }
    function paint(primary){
        if(!isHex(primary)) return;
        var r=ramp(primary), stops=[50,100,200,300,400,500,600,700,800,900,950];
        rampBox.innerHTML='';
        stops.forEach(function(s){
            var d=document.createElement('div'); d.className='stop'+(s<=200?' light':''); d.style.background=r[s];
            var sp=document.createElement('span'); sp.textContent=s; d.appendChild(sp); rampBox.appendChild(d);
        });
        preview.style.setProperty('--bp-500',r[500]); preview.style.setProperty('--bp-600',r[600]);
        preview.style.setProperty('--bp-700',r[700]); preview.style.setProperty('--bp-50',r[50]);
    }
    picker.addEventListener('input',function(){ hexIn.value=this.value; paint(this.value); });
    hexIn.addEventListener('input',function(){ var v=normalize(this.value); if(isHex(v)){ picker.value=v; paint(v);} });
    paint(normalize(hexIn.value) || '#006fb9');

    window.bpSave=function(){
        var v=normalize(hexIn.value);
        if(!isHex(v)){ App.showNotification('Ungültiger Farbwert','error'); return; }
        App.post('/admin/settings',{brand_primary_color:v}).then(function(){
            App.showNotification('Farbe gespeichert','success'); setTimeout(()=>location.reload(),700);
        });
    };

    // --- Datei-Uploads (Logo + Symbol) ---
    function wireDrop(dropId, inputId, onData){
        var drop=document.getElementById(dropId), input=document.getElementById(inputId);
        drop.addEventListener('click',()=>input.click());
        drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('drag');});
        drop.addEventListener('dragleave',()=>drop.classList.remove('drag'));
        drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('drag');if(e.dataTransfer.files[0])onData(e.dataTransfer.files[0]);});
        input.addEventListener('change',e=>{if(e.target.files[0])onData(e.target.files[0]);});
    }
    function readLogo(file, asInline){
        var isSvg=file.type.includes('svg')||file.name.endsWith('.svg');
        var isPng=file.type.includes('png')||file.name.endsWith('.png');
        if(!isSvg&&!isPng){ App.showNotification('Nur SVG oder PNG','error'); return; }
        var reader=new FileReader();
        if(isSvg && asInline){ reader.onload=e=>{ if(!e.target.result.includes('<svg')){App.showNotification('Ungültiges SVG','error');return;}
            document.getElementById('logo-code').value=e.target.result;
            var p=document.getElementById('logo-preview'); p.style.display=''; p.innerHTML=e.target.result;
            App.showNotification('Logo geladen — speichern','info'); }; reader.readAsText(file); }
        else { reader.onload=e=>{ var url=e.target.result;
            if(asInline){ document.getElementById('logo-code').value='<img src="'+url+'" alt="Logo">';
                var p=document.getElementById('logo-preview'); p.style.display=''; p.innerHTML='<img src="'+url+'" alt="Logo">'; }
            else { document.getElementById('icon-data').value=url;
                var p=document.getElementById('icon-preview'); p.style.display=''; p.querySelector('img').src=url; }
            App.showNotification((asInline?'Logo':'Symbol')+' geladen — speichern','info'); }; reader.readAsDataURL(file); }
    }
    wireDrop('logo-drop','logo-input',f=>readLogo(f,true));
    wireDrop('icon-drop','icon-input',f=>readLogo(f,false));

    window.logoSave=function(){
        App.post('/admin/settings',{app_logo:document.getElementById('logo-code').value}).then(function(){
            App.showNotification('Logo gespeichert','success'); setTimeout(()=>location.reload(),700);
        });
    };
    window.iconSave=function(){
        var url=document.getElementById('icon-data').value;
        // Symbol dient zugleich als Favicon.
        App.post('/admin/settings',{brand_logo_icon_path:url}).then(function(){
            App.post('/admin/settings',{brand_favicon:url}).then(function(){
                App.showNotification('Symbol gespeichert','success'); setTimeout(()=>location.reload(),700);
            });
        });
    };
})();
</script>
