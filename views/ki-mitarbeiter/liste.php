<?php
/** KI-Mitarbeiter — Liste (such-, filter- und gruppierbar). */
require_once SERVICES_PATH . '/KiMitarbeiterService.php';
$svc = new \Services\KiMitarbeiterService(\Core\Database::getInstance());
$filter = [];
if (!\Core\Auth::isAdmin()) $filter['allowed_customer_ids'] = \Core\Auth::customers();
$employees = $svc->liste($filter);
// Fürs Frontend aufbereiten (nur benötigte Felder)
$data = array_map(fn($e) => [
    'id' => (int) $e['id'], 'name' => $e['name'], 'role_title' => $e['role_title'],
    'status' => $e['status'], 'owner' => $e['owner_name'] ?: 'Ohne Verantwortlichen',
    'customer' => $e['customer_name'] ?: 'Installationsweit', 'open' => (int) $e['open_permissions'],
], $employees);
?>
<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">badge</span>
            KI-Mitarbeiter
        </h1>
        <p class="thx-page-subtitle">Spezialisierte KI-Mitarbeiter im Sparring mit KI entwerfen, testen und führen.</p>
    </div>
    <a href="/ki-mitarbeiter/neu" class="thx-btn thx-btn-primary" style="white-space:nowrap;">
        <span class="material-symbols-rounded" style="font-size:18px;">add</span> Neuer KI-Mitarbeiter
    </a>
</div>

<?php if (empty($data)): ?>
<div class="thx-card" style="text-align:center;padding:48px 24px;color:var(--slate-500);">
    <span class="material-symbols-rounded" style="font-size:48px;color:var(--slate-300);">badge</span>
    <p style="margin:12px 0 4px;font-weight:600;color:var(--slate-700);">Noch keine KI-Mitarbeiter</p>
    <p style="margin:0 0 16px;">Lege deinen ersten spezialisierten KI-Mitarbeiter im geführten Sparring an.</p>
    <a href="/ki-mitarbeiter/neu" class="thx-btn thx-btn-primary">Jetzt starten</a>
</div>
<?php else: ?>
<!-- Filter- und Gruppierleiste -->
<div class="km-toolbar">
    <div class="km-search">
        <span class="material-symbols-rounded">search</span>
        <input type="text" id="km-search" placeholder="Suchen (Name, Rolle, Verantwortlicher)…">
    </div>
    <div class="km-chips" id="km-status-chips">
        <button class="km-chip is-active" data-status="">Alle</button>
        <button class="km-chip" data-status="draft">Entwurf</button>
        <button class="km-chip" data-status="review">In Prüfung</button>
        <button class="km-chip" data-status="onboarding">Einarbeitung</button>
        <button class="km-chip" data-status="probation">Probezeit</button>
        <button class="km-chip" data-status="active">Aktiv</button>
        <button class="km-chip" data-status="paused">Pausiert</button>
        <button class="km-chip" data-status="archived">Archiviert</button>
    </div>
    <label class="km-group">Gruppieren:
        <select id="km-group">
            <option value="none">keine</option>
            <option value="status" selected>Status</option>
            <option value="owner">Verantwortlicher</option>
            <option value="customer">Kunde</option>
        </select>
    </label>
</div>
<div id="km-list"></div>
<p id="km-none" class="km-empty" style="display:none;padding:24px;text-align:center;">Keine Treffer.</p>
<?php endif; ?>

<style>
.km-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; max-width:1100px; }
.km-search { display:flex; align-items:center; gap:6px; background:#fff; border:1px solid var(--slate-300); border-radius:10px; padding:7px 12px; flex:1; min-width:220px; }
.km-search .material-symbols-rounded { color:var(--slate-400); font-size:19px; }
.km-search input { border:none; outline:none; flex:1; font-size:var(--d-fs-sm); background:none; }
.km-chips { display:flex; gap:6px; flex-wrap:wrap; }
.km-chip { background:#fff; border:1px solid var(--slate-200); border-radius:16px; padding:5px 13px; font-size:12px; font-weight:600; color:var(--slate-600); cursor:pointer; }
.km-chip:hover { border-color:var(--thoxan-300); }
.km-chip.is-active { background:var(--brand-accent-600,var(--thoxan-600)); color:#fff; border-color:var(--brand-accent-600,var(--thoxan-600)); }
.km-group { font-size:var(--d-fs-sm); color:var(--slate-500); display:flex; align-items:center; gap:6px; }
.km-group select { border:1px solid var(--slate-300); border-radius:8px; padding:6px 8px; font-size:var(--d-fs-sm); }
.km-group-h { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--slate-500); font-weight:700; margin:18px 0 8px; display:flex; align-items:center; gap:8px; }
.km-group-h .km-count { background:var(--slate-100); color:var(--slate-500); border-radius:10px; padding:1px 8px; font-size:11px; }
.km-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; max-width:1100px; }
.km-card { display:block; background:#fff; border:1px solid var(--slate-200); border-radius:10px; padding:16px; text-decoration:none; color:inherit; transition:border-color .15s, box-shadow .15s; }
.km-card:hover { border-color:var(--thoxan-400); box-shadow:0 2px 10px rgba(0,0,0,.05); }
.km-card-head { display:flex; align-items:center; gap:12px; }
.km-avatar { width:44px; height:44px; border-radius:10px; background:var(--thoxan-50); color:var(--thoxan-600); display:flex; align-items:center; justify-content:center; font-size:24px; flex:0 0 auto; }
.km-name { font-weight:700; color:var(--slate-800); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-role { font-size:var(--d-fs-sm); color:var(--slate-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.km-meta { display:flex; gap:14px; flex-wrap:wrap; margin-top:12px; font-size:var(--d-fs-sm); color:var(--slate-500); }
.km-meta span { display:inline-flex; align-items:center; gap:4px; }
.km-meta .material-symbols-rounded { font-size:15px; }
.km-empty { color:var(--slate-400); font-size:var(--d-fs-sm); }
</style>

<script>
(function(){
    var EMP=<?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
    if(!EMP.length) return;
    var SM={draft:['Entwurf','var(--slate-500)','var(--slate-100)'],review:['In Prüfung','var(--amber-700)','var(--amber-50)'],onboarding:['Einarbeitung','var(--indigo-700)','var(--indigo-100)'],probation:['Probezeit','var(--thoxan-700)','var(--thoxan-50)'],active:['Aktiv','var(--emerald-700)','var(--emerald-50)'],paused:['Pausiert','var(--rose-700)','var(--rose-50)'],archived:['Archiviert','var(--slate-400)','var(--slate-50)']};
    var STATUS_ORDER=['active','probation','onboarding','review','draft','paused','archived'];
    function h(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    var search='', status='', group='status';

    function card(e){
        var sm=SM[e.status]||[e.status,'var(--slate-500)','var(--slate-100)'];
        var open=e.open>0?'<span style="color:var(--amber-700);"><span class="material-symbols-rounded">lock_open</span>'+e.open+' offen</span>':'';
        return '<a class="km-card" href="/ki-mitarbeiter/'+e.id+'"><div class="km-card-head"><span class="km-avatar material-symbols-rounded">smart_toy</span><div style="flex:1;min-width:0;"><div class="km-name">'+h(e.name)+'</div><div class="km-role">'+h(e.role_title||'Ohne Rollenbezeichnung')+'</div></div><span class="km-badge" style="color:'+sm[1]+';background:'+sm[2]+';">'+h(sm[0])+'</span></div><div class="km-meta"><span><span class="material-symbols-rounded">person</span>'+h(e.owner)+'</span><span><span class="material-symbols-rounded">apartment</span>'+h(e.customer)+'</span>'+open+'</div></a>';
    }
    function groupKey(e){ return group==='none'?'':(group==='status'?(SM[e.status]?SM[e.status][0]:e.status):(group==='owner'?e.owner:e.customer)); }

    function render(){
        var list=EMP.filter(function(e){
            if(status && e.status!==status) return false;
            if(search){ var s=(e.name+' '+e.role_title+' '+e.owner+' '+e.customer).toLowerCase(); if(s.indexOf(search)<0) return false; }
            return true;
        });
        var box=document.getElementById('km-list'); box.innerHTML='';
        document.getElementById('km-none').style.display=list.length?'none':'';
        if(group==='none'){ box.innerHTML='<div class="km-grid">'+list.map(card).join('')+'</div>'; return; }
        var groups={};
        list.forEach(function(e){ var k=groupKey(e); (groups[k]=groups[k]||[]).push(e); });
        var keys=Object.keys(groups);
        if(group==='status'){ keys.sort(function(a,b){ function idx(n){for(var i=0;i<STATUS_ORDER.length;i++){if((SM[STATUS_ORDER[i]]||[])[0]===n)return i;}return 99;} return idx(a)-idx(b); }); }
        else { keys.sort(); }
        keys.forEach(function(k){
            box.insertAdjacentHTML('beforeend','<div class="km-group-h">'+h(k)+'<span class="km-count">'+groups[k].length+'</span></div><div class="km-grid">'+groups[k].map(card).join('')+'</div>');
        });
    }

    document.getElementById('km-search').addEventListener('input',function(){ search=this.value.trim().toLowerCase(); render(); });
    document.getElementById('km-group').addEventListener('change',function(){ group=this.value; render(); });
    document.querySelectorAll('#km-status-chips .km-chip').forEach(function(c){ c.addEventListener('click',function(){ document.querySelectorAll('#km-status-chips .km-chip').forEach(function(x){x.classList.remove('is-active');}); c.classList.add('is-active'); status=c.getAttribute('data-status'); render(); }); });
    render();
})();
</script>
