<?php
/** KI-Mitarbeiter — Detailansicht als vermenschlichtes Mitarbeiter-Profil. $employeeId */
require_once SERVICES_PATH . '/KiMitarbeiterService.php';
$svc = new \Services\KiMitarbeiterService(\Core\Database::getInstance());
$e = $svc->get((int) $employeeId);
if (!$e) { echo '<div class="thx-card" style="margin:24px;">KI-Mitarbeiter nicht gefunden.</div>'; return; }
if (!empty($e['customer_id']) && !\Core\Auth::canAccessCustomer((int) $e['customer_id'])) { echo '<div class="thx-card" style="margin:24px;">Kein Zugriff.</div>'; return; }
$isAdmin = \Core\Auth::isAdmin();
$p = $e['profile'] ?? [];
$persona = $p['persona'] ?? [];
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusMeta = ['draft'=>['Entwurf','#64748b','#f1f5f9'],'review'=>['In Prüfung','#b45309','#fffbeb'],'onboarding'=>['Einarbeitung','#4338ca','#eef2ff'],'probation'=>['Probezeit','#004a86','#e6f0f8'],'active'=>['Aktiv','#047857','#ecfdf5'],'paused'=>['Pausiert','#be123c','#fff1f2'],'archived'=>['Archiviert','#94a3b8','#f8fafc']];
$sm = $statusMeta[$e['status']] ?? [$e['status'],'#64748b','#f1f5f9'];
function kmAvatarHtml($p, $big=false) {
    $sz = $big ? 'width:84px;height:84px;font-size:44px;border-radius:20px;' : 'width:44px;height:44px;font-size:24px;border-radius:10px;';
    if (!empty($p['avatar_image'])) return '<span class="km-ava-wrap" style="'.$sz.'"><img src="'.htmlspecialchars($p['avatar_image']).'" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>';
    if (!empty($p['avatar'])) return '<span class="km-ava-wrap" style="'.$sz.'background:var(--thoxan-50);">'.htmlspecialchars($p['avatar']).'</span>';
    return '<span class="km-ava-wrap material-symbols-rounded" style="'.$sz.'background:var(--thoxan-50);color:var(--thoxan-600);">smart_toy</span>';
}
function kmList($items) { $esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); if(empty($items)||!is_array($items)) return '<p class="km-empty">—</p>'; $o='<ul class="km-ul">'; foreach($items as $it){ $o.='<li>'.$esc(is_array($it)?($it['title']??$it['name']??json_encode($it,JSON_UNESCAPED_UNICODE)):$it).'</li>'; } return $o.'</ul>'; }
$tabs = ['uebersicht'=>'Übersicht','stelle'=>'Stellenbeschreibung','workflows'=>'Workflows','wissen'=>'Wissen','tools'=>'Tools & Berechtigungen','persoenlichkeit'=>'Persönlichkeit & Steckbrief','tests'=>'Tests & Qualität','feedback'=>'Feedback','testchat'=>'Test-Chat','versionen'=>'Versionen','audit'=>'Aktivität'];
$fact = fn($icon,$label,$val) => $val ? '<div class="km-fact"><span class="material-symbols-rounded">'.$icon.'</span><div><div class="km-fact-l">'.$label.'</div><div class="km-fact-v">'.htmlspecialchars((string)$val).'</div></div></div>' : '';
?>
<!-- Steckbrief-Kopf -->
<div class="km-hero">
    <div class="km-hero-main">
        <div class="km-hero-ava"><?= kmAvatarHtml($p, true) ?></div>
        <div style="min-width:0;flex:1;">
            <div class="km-hero-name"><?= $esc($e['name']) ?>
                <span class="km-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;font-size:12px;margin-left:8px;vertical-align:middle;"><?= $esc($sm[0]) ?></span>
            </div>
            <div class="km-hero-role"><?= $esc($p['role_title'] ?? $e['role_title'] ?: 'Ohne Rollenbezeichnung') ?></div>
            <?php if(!empty($persona['bio'])||!empty($persona['motto'])): ?>
                <div class="km-hero-bio"><?= $esc($persona['bio'] ?? $persona['motto']) ?></div>
            <?php endif; ?>
            <div class="km-facts">
                <?= $fact('cake','Alter', $persona['age'] ?? null) ?>
                <?= $fact('workspace_premium','Erfahrung', $persona['experience'] ?? null) ?>
                <?= $fact('person','Verantwortlich', $e['owner_name'] ?? null) ?>
                <?= $fact('apartment','Kunde', $e['customer_name'] ?? 'Installationsweit') ?>
            </div>
        </div>
    </div>
    <div class="km-hero-actions">
        <a href="/ki-mitarbeiter/<?= (int)$e['id'] ?>/wizard" class="thx-btn"><span class="material-symbols-rounded" style="font-size:16px;">auto_awesome</span> Im Wizard bearbeiten</a>
        <?php if($e['status']==='draft'): ?><button class="thx-btn thx-btn-primary" onclick="kmAction('submit-review')">Zur Prüfung einreichen</button><?php endif; ?>
        <?php if($e['status']==='review' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('onboarding')">Freigeben → Einarbeitung</button><?php endif; ?>
        <?php if($e['status']==='onboarding' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('probation')">In Probezeit</button><?php endif; ?>
        <?php if($e['status']==='probation' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('active')">Aktivieren</button><?php endif; ?>
        <?php if($e['status']==='active'): ?><button class="thx-btn" onclick="kmAction('pause')">Pausieren</button><?php endif; ?>
        <?php if($e['status']==='paused' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('active')">Fortsetzen</button><?php endif; ?>
    </div>
</div>

<nav class="thx-tabs" style="margin:16px 0;flex-wrap:wrap;">
    <?php foreach($tabs as $slug=>$name): ?><a href="#<?= $slug ?>" class="thx-tab km-tab" data-tab="<?= $slug ?>"><?= $esc($name) ?></a><?php endforeach; ?>
</nav>

<div class="km-panels" data-employee="<?= (int)$e['id'] ?>" data-admin="<?= $isAdmin?'1':'0' ?>">
    <section class="km-panel" data-panel="uebersicht">
        <div class="km-cols">
            <div class="thx-card"><h3 class="km-h">Problem &amp; Nutzen</h3>
                <p class="km-field"><strong>Problem:</strong> <?= $esc($p['problem_statement'] ?? $e['problem_statement'] ?: '—') ?></p>
                <p class="km-field"><strong>Erwarteter Nutzen:</strong> <?= $esc($p['expected_benefit'] ?? $e['expected_benefit'] ?: '—') ?></p>
            </div>
            <div class="thx-card"><h3 class="km-h">Vollständigkeit</h3>
                <div class="km-bar"><div style="width:<?= (int)($e['completeness']['percentage']??0) ?>%;height:8px;background:linear-gradient(90deg,var(--thoxan-400),var(--thoxan-600));border-radius:6px;"></div></div>
                <p class="km-field" style="margin-top:8px;"><?= (int)($e['completeness']['percentage']??0) ?>% · fehlt: <?= $esc(implode(', ', $e['completeness']['missing_sections']??[])) ?: 'nichts' ?></p>
            </div>
        </div>
    </section>
    <section class="km-panel" data-panel="stelle" hidden>
        <div class="thx-card"><h3 class="km-h">Ziele</h3><?= kmList($p['goals']??[]) ?></div>
        <div class="km-cols">
            <div class="thx-card"><h3 class="km-h">Aufgaben</h3><?= kmList($p['tasks']??[]) ?></div>
            <div class="thx-card"><h3 class="km-h">Nicht-Aufgaben</h3><?= kmList($p['non_tasks']??[]) ?></div>
        </div>
        <div class="thx-card"><h3 class="km-h">Eskalationsregeln</h3><?= kmList($p['escalation_rules']??[]) ?></div>
    </section>
    <section class="km-panel" data-panel="workflows" hidden><div class="thx-card"><h3 class="km-h">Arbeitsabläufe</h3><?= kmList($p['workflows']??[]) ?></div></section>
    <section class="km-panel" data-panel="wissen" hidden>
        <div class="thx-card"><h3 class="km-h">Wissensquellen</h3><?= kmList($p['knowledge_sources']??[]) ?></div>
        <div class="km-cols"><div class="thx-card"><h3 class="km-h">Positivbeispiele</h3><?= kmList($p['positive_examples']??[]) ?></div><div class="thx-card"><h3 class="km-h">Negativbeispiele</h3><?= kmList($p['negative_examples']??[]) ?></div></div>
    </section>
    <section class="km-panel" data-panel="tools" hidden>
        <div class="thx-card"><h3 class="km-h">Beantragte &amp; freigegebene Zugriffe</h3><p class="km-empty" style="margin-top:0;">Kritische Zugriffe schaltet nur ein Admin frei. Least Privilege.</p><div id="km-perms">Lädt…</div></div>
        <div class="thx-card"><h3 class="km-h">Zugriff beantragen</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <label class="km-lbl">Werkzeug<select id="km-perm-tool" class="thx-input"><option value="customers">Kunden</option><option value="projects">Projekte</option><option value="tasks">Aufgaben</option><option value="email">E-Mail</option></select></label>
                <label class="km-lbl">Zugriffsstufe<select id="km-perm-level" class="thx-input"><option value="read">lesen</option><option value="draft">Entwurf erstellen</option><option value="write">verändern</option><option value="execute">ausführen/versenden</option></select></label>
                <label class="km-lbl" style="flex:1;min-width:180px;">Begründung<input id="km-perm-just" class="thx-input" placeholder="Wofür?"></label>
                <button class="thx-btn thx-btn-primary" onclick="kmRequestPerm()">Beantragen</button>
            </div>
        </div>
    </section>
    <!-- Persönlichkeit & Steckbrief (editierbar) -->
    <section class="km-panel" data-panel="persoenlichkeit" hidden>
        <div class="thx-card">
            <h3 class="km-h">Steckbrief (Vermenschlichung)</h3>
            <div class="km-persona-grid">
                <div class="km-persona-ava">
                    <div id="km-ava-preview"><?= kmAvatarHtml($p, true) ?></div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <input id="pf-avatar" class="thx-input" style="width:80px;text-align:center;font-size:22px;" value="<?= $esc($p['avatar'] ?? '') ?>" placeholder="🙂" title="Emoji als Avatar">
                        <button class="thx-btn thx-btn-small" onclick="kmGenAvatar(this)"><span class="material-symbols-rounded" style="font-size:15px;">auto_awesome</span> Bild generieren</button>
                    </div>
                </div>
                <div class="km-persona-fields">
                    <label class="km-lbl">Alter<input id="pf-age" class="thx-input" value="<?= $esc($persona['age'] ?? '') ?>" placeholder="z.B. 34"></label>
                    <label class="km-lbl">Erfahrung<input id="pf-exp" class="thx-input" value="<?= $esc($persona['experience'] ?? '') ?>" placeholder="z.B. 8 Jahre im Kundenservice"></label>
                    <label class="km-lbl">Pronomen<input id="pf-pron" class="thx-input" value="<?= $esc($persona['pronouns'] ?? '') ?>" placeholder="z.B. sie/ihr"></label>
                    <label class="km-lbl" style="grid-column:1/-1;">Kurz-Bio / Motto<input id="pf-bio" class="thx-input" value="<?= $esc($persona['bio'] ?? '') ?>" placeholder="Ein Satz, der die Figur greifbar macht"></label>
                    <label class="km-lbl" style="grid-column:1/-1;">Charakterzüge (kommagetrennt)<input id="pf-traits" class="thx-input" value="<?= $esc(is_array($persona['traits']??null)?implode(', ',$persona['traits']):'') ?>" placeholder="z.B. gründlich, freundlich, verbindlich"></label>
                </div>
            </div>
            <div style="margin-top:12px;"><button class="thx-btn thx-btn-primary" onclick="kmSavePersona()">Steckbrief speichern</button></div>
        </div>
        <div class="thx-card"><h3 class="km-h">Kommunikation &amp; Ton</h3>
            <?php $pers=$p['personality']??[]; if(empty($pers)): ?><p class="km-empty">Wird im Wizard erfasst.</p>
            <?php else: ?><ul class="km-ul"><?php foreach($pers as $k=>$v): ?><li><strong><?= $esc($k) ?>:</strong> <?= $esc(is_array($v)?implode(', ',$v):$v) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <div class="thx-card"><h3 class="km-h">Verbotene Inhalte / Handlungen</h3><?= kmList($p['forbidden']??[]) ?></div>
    </section>
    <section class="km-panel" data-panel="tests" hidden>
        <div class="thx-card"><h3 class="km-h">Testfälle (<?= is_array($p['test_cases']??null)?count($p['test_cases']):0 ?>)</h3>
            <?php $tcs=$p['test_cases']??[]; if(empty($tcs)): ?><p class="km-empty">Mindestens 3 sind zum Einreichen nötig.</p>
            <?php else: foreach($tcs as $tc): ?><div class="km-tc"><strong><?= $esc($tc['name']??$tc['category']??'Testfall') ?></strong> <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);"><?= $esc($tc['category']??'') ?></span><div class="km-field"><?= $esc($tc['expected']??$tc['expected_behavior']??'') ?></div></div><?php endforeach; endif; ?>
        </div>
        <div class="thx-card"><h3 class="km-h">Qualitätsregeln</h3><?= kmList($p['quality_rules']??[]) ?></div>
    </section>
    <section class="km-panel" data-panel="feedback" hidden><div class="thx-card"><h3 class="km-h">Feedback &amp; Entwicklung</h3><div id="km-feedback">Lädt…</div></div></section>
    <section class="km-panel" data-panel="testchat" hidden>
        <div class="thx-card"><h3 class="km-h">Test-Chat</h3><p class="km-empty">Probiere den KI-Mitarbeiter in einer isolierten Testumgebung aus.</p>
            <div id="km-tc-messages" class="wz-messages" style="height:340px;border:1px solid var(--slate-200);border-radius:8px;display:flex;flex-direction:column;gap:10px;padding:14px;overflow-y:auto;"></div>
            <div style="display:flex;gap:8px;margin-top:10px;"><textarea id="km-tc-input" rows="2" placeholder="Testeingabe…" style="flex:1;border:1px solid var(--slate-300);border-radius:8px;padding:8px;"></textarea><button class="thx-btn thx-btn-primary" onclick="kmTestSend()">Senden</button></div>
        </div>
    </section>
    <section class="km-panel" data-panel="versionen" hidden><div class="thx-card"><h3 class="km-h">Versionen</h3><div id="km-versions">Lädt…</div></div></section>
    <section class="km-panel" data-panel="audit" hidden><div class="thx-card"><h3 class="km-h">Aktivität / Audit-Log</h3><div id="km-audit">Lädt…</div></div></section>
</div>

<style>
.km-hero { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; background:linear-gradient(120deg,var(--thoxan-50),#fff 60%); border:1px solid var(--slate-200); border-radius:14px; padding:20px 22px; }
.km-hero-main { display:flex; gap:18px; align-items:center; flex:1; min-width:260px; }
.km-ava-wrap { display:inline-flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 auto; }
.km-hero-name { font-size:1.5rem; font-weight:800; color:var(--slate-900); }
.km-hero-role { font-size:var(--d-fs-base); color:var(--thoxan-700); font-weight:600; margin-top:2px; }
.km-hero-bio { font-size:var(--d-fs-sm); color:var(--slate-600); margin-top:6px; font-style:italic; }
.km-facts { display:flex; gap:22px; flex-wrap:wrap; margin-top:12px; }
.km-fact { display:flex; gap:8px; align-items:center; }
.km-fact .material-symbols-rounded { font-size:19px; color:var(--thoxan-500); }
.km-fact-l { font-size:11px; color:var(--slate-500); text-transform:uppercase; letter-spacing:.4px; }
.km-fact-v { font-size:var(--d-fs-sm); color:var(--slate-800); font-weight:600; }
.km-hero-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start; }
.km-badge { font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.km-cols { display:grid; grid-template-columns:1fr 1fr; gap:14px; } @media(max-width:800px){.km-cols{grid-template-columns:1fr;}}
.km-panel .thx-card{margin-bottom:14px;} .km-panel{max-width:1100px;}
.km-h{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-500);margin:0 0 8px;font-weight:700;}
.km-field{font-size:var(--d-fs-sm);color:var(--slate-700);margin:4px 0;line-height:1.5;}
.km-ul{margin:0;padding-left:18px;font-size:var(--d-fs-sm);color:var(--slate-700);}.km-ul li{margin:3px 0;}
.km-empty{color:var(--slate-400);font-size:var(--d-fs-sm);}
.km-lbl{display:flex;flex-direction:column;gap:3px;font-size:12px;color:var(--slate-500);}
.km-bar{height:8px;background:var(--slate-100);border-radius:6px;overflow:hidden;}
.km-tc{padding:8px 0;border-bottom:1px solid var(--slate-100);}
.km-perm-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-sm);}
.km-tab{cursor:pointer;}
.km-persona-grid{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;}
.km-persona-ava{display:flex;gap:12px;align-items:center;flex:0 0 auto;}
.km-persona-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px;flex:1;min-width:260px;}
.wz-msg{max-width:85%;padding:9px 13px;border-radius:12px;font-size:var(--d-fs-sm);line-height:1.5;white-space:pre-wrap;}
.wz-msg.user{align-self:flex-end;background:var(--thoxan-600);color:#fff;}
.wz-msg.assistant{align-self:flex-start;background:var(--slate-100);color:var(--slate-800);}
</style>

<script>
(function(){
    var wrap=document.querySelector('.km-panels'); var eid=wrap.getAttribute('data-employee'); var isAdmin=wrap.getAttribute('data-admin')==='1';
    var base='/api/v1/ki-mitarbeiter/'+eid;
    var CSRF=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
    function notify(m,t){ if(window.App && App.showNotification) App.showNotification(m,t); }
    function h(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(b||{})}).then(r=>r.json());}
    function get(u){return fetch(u).then(r=>r.json());}
    var loaded={};
    function showTab(slug){ document.querySelectorAll('.km-panel').forEach(function(s){s.hidden=s.getAttribute('data-panel')!==slug;}); document.querySelectorAll('.km-tab').forEach(function(t){t.classList.toggle('is-active',t.getAttribute('data-tab')===slug);}); if(!loaded[slug]){loaded[slug]=true; if(slug==='tools')loadPerms(); if(slug==='versionen')loadVersions(); if(slug==='audit')loadAudit(); if(slug==='feedback')loadFeedback();} }
    document.querySelectorAll('.km-tab').forEach(function(t){t.addEventListener('click',function(ev){ev.preventDefault();var s=t.getAttribute('data-tab');history.replaceState(null,'','#'+s);showTab(s);});});
    showTab((location.hash||'#uebersicht').slice(1));

    window.kmAction=function(a){post(base+'/'+a,{}).then(function(r){r.success?location.reload():alert(r.message||'Fehler');});};
    window.kmTransition=function(to){post(base+'/transition',{to:to}).then(function(r){r.success?location.reload():alert(r.message||'Fehler');});};

    // Persona speichern
    window.kmSavePersona=function(){
        var traits=(document.getElementById('pf-traits').value||'').split(',').map(function(x){return x.trim();}).filter(Boolean);
        var patch={avatar:document.getElementById('pf-avatar').value.trim(),persona:{age:document.getElementById('pf-age').value.trim(),experience:document.getElementById('pf-exp').value.trim(),pronouns:document.getElementById('pf-pron').value.trim(),bio:document.getElementById('pf-bio').value.trim(),traits:traits}};
        post(base+'/profile',{patch:patch}).then(function(r){ if(r.success){notify('Steckbrief gespeichert','success');setTimeout(function(){location.reload();},600);} else alert(r.message); });
    };
    window.kmGenAvatar=function(btn){
        var orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='Erzeuge Bild…';
        post(base+'/avatar/generate',{}).then(function(r){ btn.disabled=false; btn.innerHTML=orig; if(r.success){ document.getElementById('km-ava-preview').innerHTML='<span class="km-ava-wrap" style="width:84px;height:84px;border-radius:20px;overflow:hidden;"><img src="'+h(r.data.avatar_image)+'" style="width:100%;height:100%;object-fit:cover;"></span>'; notify('Avatar erstellt','success'); } else alert(r.message); }).catch(function(){ btn.disabled=false; btn.innerHTML=orig; alert('Fehler'); });
    };

    function loadPerms(){get(base+'/permissions').then(function(res){var box=document.getElementById('km-perms');if(!res.success){box.textContent='Fehler';return;}var ps=res.data.permissions||[];if(!ps.length){box.innerHTML='<p class="km-empty">Noch keine Zugriffe beantragt.</p>';return;}box.innerHTML=ps.map(function(p){var st={requested:'beantragt',approved:'freigegeben',rejected:'abgelehnt'}[p.status]||p.status;var a='';if(p.status==='requested'&&isAdmin){a='<button class="thx-btn thx-btn-small thx-btn-primary" onclick="kmApprovePerm('+p.id+',1)">Freigeben</button> <button class="thx-btn thx-btn-small" onclick="kmApprovePerm('+p.id+',0)">Ablehnen</button>';}return '<div class="km-perm-row"><div><strong>'+h(p.tool_key)+'</strong> — '+h(p.permission_level)+' <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">'+h(st)+'</span><br><span class="km-empty">'+h(p.justification||'')+'</span></div><div>'+a+'</div></div>';}).join('');});}
    window.kmApprovePerm=function(id,ok){post('/api/v1/ai-permissions/'+id+'/'+(ok?'approve':'reject'),{}).then(function(){loaded['tools']=false;loadPerms();});};
    window.kmRequestPerm=function(){post(base+'/permissions/request',{tool_key:document.getElementById('km-perm-tool').value,permission_level:document.getElementById('km-perm-level').value,justification:document.getElementById('km-perm-just').value}).then(function(r){if(r.success){document.getElementById('km-perm-just').value='';loadPerms();}else alert(r.message);});};
    function loadVersions(){get(base+'/versions').then(function(res){var box=document.getElementById('km-versions');if(!res.success){box.textContent='Fehler';return;}var vs=res.data.versions||[];if(!vs.length){box.innerHTML='<p class="km-empty">Noch keine Versionen.</p>';return;}box.innerHTML=vs.map(function(v){return '<div class="km-perm-row"><div><strong>v'+v.version_number+'</strong> · '+h(v.change_summary||'')+'<br><span class="km-empty">'+h(v.created_by_name||'')+' · '+h(v.created_at)+'</span></div><button class="thx-btn thx-btn-small" onclick="kmRestore('+v.version_number+')">Wiederherstellen</button></div>';}).join('');});}
    window.kmRestore=function(n){if(!confirm('Version v'+n+' wiederherstellen?'))return;post(base+'/versions/'+n+'/restore',{}).then(function(r){r.success?location.reload():alert(r.message);});};
    function loadAudit(){get(base+'/audit-log').then(function(res){var box=document.getElementById('km-audit');if(!res.success){box.textContent='Fehler';return;}var evs=res.data.events||[];if(!evs.length){box.innerHTML='<p class="km-empty">Noch keine Aktivität.</p>';return;}box.innerHTML='<ul class="km-ul">'+evs.map(function(a){return '<li><strong>'+h(a.action)+'</strong> · '+h(a.actor_name||'System')+' · '+h(a.occurred_at)+'</li>';}).join('')+'</ul>';});}
    function loadFeedback(){get(base+'/feedback').then(function(res){var box=document.getElementById('km-feedback');if(!res.success){box.textContent='Fehler';return;}var fs=res.data.feedback||[];if(!fs.length){box.innerHTML='<p class="km-empty">Noch kein Feedback. Bewerte einen Testlauf im Test-Chat.</p>';return;}box.innerHTML=fs.map(function(f){var st={open:'offen',accepted:'übernommen',rejected:'abgelehnt'}[f.status]||f.status;var sug='';if(f.suggested_change){sug='<div class="km-field" style="background:var(--thoxan-50);padding:8px;border-radius:6px;margin-top:6px;"><strong>KI-Vorschlag:</strong> '+h(f.suggested_change.summary||'')+'</div>';}var act='';if(f.status==='open'){act=f.suggested_change?'<button class="thx-btn thx-btn-small thx-btn-primary" onclick="kmApplyFb('+f.id+')">Übernehmen</button> <button class="thx-btn thx-btn-small" onclick="kmRejectFb('+f.id+')">Ablehnen</button>':'<button class="thx-btn thx-btn-small" onclick="kmSuggest('+f.id+')">Verbesserungsvorschlag erzeugen</button>';}return '<div class="km-perm-row" style="flex-direction:column;align-items:stretch;"><div><strong>'+h(f.feedback_type)+'</strong> '+(f.rating?(f.rating>0?'👍':'👎'):'')+' <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">'+h(st)+'</span><br><span class="km-empty">'+h(f.comment||'')+'</span></div>'+sug+'<div style="margin-top:6px;">'+act+'</div></div>';}).join('');});}
    window.kmSuggest=function(fid){post(base+'/feedback/'+fid+'/suggest',{}).then(function(r){if(r.success){loaded['feedback']=false;loadFeedback();}else alert(r.message);});};
    window.kmApplyFb=function(fid){if(!confirm('Vorschlag übernehmen? Neue Version.'))return;post(base+'/feedback/'+fid+'/apply',{}).then(function(r){if(r.success){notify('Übernommen','success');loaded['feedback']=false;loadFeedback();}else alert(r.message);});};
    window.kmRejectFb=function(fid){post(base+'/feedback/'+fid+'/reject',{}).then(function(){loaded['feedback']=false;loadFeedback();});};
    window.kmTestSend=function(){var inp=document.getElementById('km-tc-input');var msg=inp.value.trim();if(!msg)return;inp.value='';var box=document.getElementById('km-tc-messages');var u=document.createElement('div');u.className='wz-msg user';u.textContent=msg;box.appendChild(u);var a=document.createElement('div');a.className='wz-msg assistant';a.textContent='…';box.appendChild(a);box.scrollTop=box.scrollHeight;post(base+'/test-runs',{message:msg}).then(function(res){a.textContent=res.success?(res.data.reply||'(keine Antwort)'):('Fehler: '+(res.message||''));if(res.success&&res.data.run_id){var fb=document.createElement('div');fb.style.cssText='align-self:flex-start;display:flex;gap:6px;';fb.innerHTML='<button class="thx-btn thx-btn-small">👍</button><button class="thx-btn thx-btn-small">👎</button>';var rid=res.data.run_id;fb.children[0].onclick=function(){kmRunFeedback(rid,1,'gut');fb.remove();};fb.children[1].onclick=function(){var c=prompt('Was war nicht gut?')||'';kmRunFeedback(rid,-1,'fachlich_falsch',c);fb.remove();};box.appendChild(fb);}box.scrollTop=box.scrollHeight;}).catch(function(){a.textContent='Netzwerkfehler.';});};
    window.kmRunFeedback=function(runId,rating,type,comment){post('/api/v1/ai-runs/'+runId+'/feedback',{rating:rating,feedback_type:type,comment:comment||''}).then(function(r){if(r.success)notify('Danke für das Feedback','success');loaded['feedback']=false;});};
})();
</script>
