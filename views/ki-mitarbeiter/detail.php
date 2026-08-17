<?php
/** KI-Mitarbeiter — Detailansicht (Tab-Hub). $employeeId */
require_once SERVICES_PATH . '/KiMitarbeiterService.php';
$svc = new \Services\KiMitarbeiterService(\Core\Database::getInstance());
$e = $svc->get((int) $employeeId);
if (!$e) { echo '<div class="thx-card" style="margin:24px;">KI-Mitarbeiter nicht gefunden.</div>'; return; }
if (!empty($e['customer_id']) && !\Core\Auth::canAccessCustomer((int) $e['customer_id'])) {
    echo '<div class="thx-card" style="margin:24px;">Kein Zugriff.</div>'; return;
}
$isAdmin = \Core\Auth::isAdmin();
$p = $e['profile'] ?? [];
$statusMeta = [
    'draft'=>['Entwurf','var(--slate-500)','var(--slate-100)'],'review'=>['In Prüfung','var(--amber-700)','var(--amber-50)'],
    'onboarding'=>['Einarbeitung','var(--indigo-700)','var(--indigo-100)'],'probation'=>['Probezeit','var(--thoxan-700)','var(--thoxan-50)'],
    'active'=>['Aktiv','var(--emerald-700)','var(--emerald-50)'],'paused'=>['Pausiert','var(--rose-700)','var(--rose-50)'],'archived'=>['Archiviert','var(--slate-400)','var(--slate-50)'],
];
$sm = $statusMeta[$e['status']] ?? [$e['status'],'var(--slate-500)','var(--slate-100)'];
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** kleine Helfer zum Rendern von Profil-Listen */
function kmList($items) { $esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); if(empty($items)||!is_array($items)) return '<p class="km-empty">—</p>'; $o='<ul class="km-ul">'; foreach($items as $it){ if(is_array($it)){ $o.='<li>'.$esc($it['title']??$it['name']??json_encode($it,JSON_UNESCAPED_UNICODE)).'</li>'; } else { $o.='<li>'.$esc($it).'</li>'; } } return $o.'</ul>'; }
$tabs = ['uebersicht'=>'Übersicht','stelle'=>'Stellenbeschreibung','workflows'=>'Workflows','wissen'=>'Wissen','tools'=>'Tools & Berechtigungen','persoenlichkeit'=>'Persönlichkeit','tests'=>'Tests & Qualität','feedback'=>'Feedback','testchat'=>'Test-Chat','versionen'=>'Versionen','audit'=>'Aktivität'];
?>
<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
    <div style="display:flex;gap:14px;align-items:center;">
        <span class="km-avatar material-symbols-rounded" style="width:52px;height:52px;font-size:28px;">smart_toy</span>
        <div>
            <h1 class="thx-page-title" style="margin:0;"><?= $esc($e['name']) ?>
                <span class="km-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;font-size:12px;vertical-align:middle;margin-left:8px;"><?= $esc($sm[0]) ?></span>
            </h1>
            <p class="thx-page-subtitle" style="margin:2px 0 0;"><?= $esc($e['role_title'] ?: 'Ohne Rollenbezeichnung') ?><?php if(!empty($e['owner_name'])): ?> · Verantwortlich: <?= $esc($e['owner_name']) ?><?php endif; ?><?php if(!empty($e['customer_name'])): ?> · Kunde: <?= $esc($e['customer_name']) ?><?php endif; ?></p>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/ki-mitarbeiter/<?= (int)$e['id'] ?>/wizard" class="thx-btn"><span class="material-symbols-rounded" style="font-size:16px;">auto_awesome</span> Im Wizard bearbeiten</a>
        <?php if($e['status']==='draft'): ?><button class="thx-btn thx-btn-primary" onclick="kmAction('submit-review')">Zur Prüfung einreichen</button><?php endif; ?>
        <?php if($e['status']==='review' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('onboarding')">Freigeben → Einarbeitung</button><?php endif; ?>
        <?php if($e['status']==='onboarding' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('probation')">In Probezeit</button><?php endif; ?>
        <?php if($e['status']==='probation' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('active')">Aktivieren</button><?php endif; ?>
        <?php if($e['status']==='active'): ?><button class="thx-btn" onclick="kmAction('pause')">Pausieren</button><?php endif; ?>
        <?php if($e['status']==='paused' && $isAdmin): ?><button class="thx-btn thx-btn-primary" onclick="kmTransition('active')">Fortsetzen</button><?php endif; ?>
    </div>
</div>

<nav class="thx-tabs" style="margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach($tabs as $slug=>$name): ?>
        <a href="#<?= $slug ?>" class="thx-tab km-tab" data-tab="<?= $slug ?>"><?= $esc($name) ?></a>
    <?php endforeach; ?>
</nav>

<div class="km-panels" data-employee="<?= (int)$e['id'] ?>" data-admin="<?= $isAdmin?'1':'0' ?>">
    <!-- Übersicht -->
    <section class="km-panel" data-panel="uebersicht">
        <div class="km-cols">
            <div class="thx-card"><h3 class="km-h">Problem &amp; Nutzen</h3>
                <p class="km-field"><strong>Problem:</strong> <?= $esc($p['problem_statement'] ?? $e['problem_statement'] ?: '—') ?></p>
                <p class="km-field"><strong>Erwarteter Nutzen:</strong> <?= $esc($p['expected_benefit'] ?? $e['expected_benefit'] ?: '—') ?></p>
                <p class="km-field"><strong>Einordnung:</strong> <?= $esc($p['need_classification'] ?? '—') ?></p>
            </div>
            <div class="thx-card"><h3 class="km-h">Vollständigkeit</h3>
                <div class="wz-bar"><div class="wz-bar-fill" style="width:<?= (int)($e['completeness']['percentage']??0) ?>%;height:8px;background:var(--thoxan-500);border-radius:6px;"></div></div>
                <p class="km-field" style="margin-top:8px;"><?= (int)($e['completeness']['percentage']??0) ?>% · fehlt: <?= $esc(implode(', ', $e['completeness']['missing_sections']??[])) ?: 'nichts' ?></p>
            </div>
        </div>
    </section>
    <!-- Stellenbeschreibung -->
    <section class="km-panel" data-panel="stelle" hidden>
        <div class="thx-card"><h3 class="km-h">Ziele</h3><?= kmList($p['goals']??[]) ?></div>
        <div class="km-cols">
            <div class="thx-card"><h3 class="km-h">Aufgaben</h3><?= kmList($p['tasks']??[]) ?></div>
            <div class="thx-card"><h3 class="km-h">Nicht-Aufgaben</h3><?= kmList($p['non_tasks']??[]) ?></div>
        </div>
        <div class="thx-card"><h3 class="km-h">Eskalationsregeln</h3><?= kmList($p['escalation_rules']??[]) ?></div>
    </section>
    <!-- Workflows -->
    <section class="km-panel" data-panel="workflows" hidden>
        <div class="thx-card"><h3 class="km-h">Arbeitsabläufe</h3><?= kmList($p['workflows']??[]) ?></div>
    </section>
    <!-- Wissen -->
    <section class="km-panel" data-panel="wissen" hidden>
        <div class="thx-card"><h3 class="km-h">Wissensquellen</h3><?= kmList($p['knowledge_sources']??[]) ?></div>
        <div class="km-cols">
            <div class="thx-card"><h3 class="km-h">Positivbeispiele</h3><?= kmList($p['positive_examples']??[]) ?></div>
            <div class="thx-card"><h3 class="km-h">Negativbeispiele</h3><?= kmList($p['negative_examples']??[]) ?></div>
        </div>
    </section>
    <!-- Tools & Berechtigungen -->
    <section class="km-panel" data-panel="tools" hidden>
        <div class="thx-card">
            <h3 class="km-h">Beantragte &amp; freigegebene Zugriffe</h3>
            <p class="km-empty" style="margin-top:0;">Kritische Zugriffe schaltet nur ein Admin frei. Least Privilege: nur das Nötigste.</p>
            <div id="km-perms">Lädt…</div>
        </div>
        <div class="thx-card"><h3 class="km-h">Zugriff beantragen</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <label class="km-lbl">Werkzeug<select id="km-perm-tool" class="thx-input"><option value="customers">Kunden</option><option value="projects">Projekte</option><option value="tasks">Aufgaben</option><option value="email">E-Mail</option></select></label>
                <label class="km-lbl">Zugriffsstufe<select id="km-perm-level" class="thx-input"><option value="read">lesen</option><option value="draft">Entwurf erstellen</option><option value="write">verändern</option><option value="execute">ausführen/versenden</option></select></label>
                <label class="km-lbl" style="flex:1;min-width:180px;">Begründung<input id="km-perm-just" class="thx-input" placeholder="Wofür wird der Zugriff gebraucht?"></label>
                <button class="thx-btn thx-btn-primary" onclick="kmRequestPerm()">Beantragen</button>
            </div>
        </div>
    </section>
    <!-- Persönlichkeit -->
    <section class="km-panel" data-panel="persoenlichkeit" hidden>
        <div class="thx-card"><h3 class="km-h">Kommunikation &amp; Ton</h3>
            <?php $pers=$p['personality']??[]; if(empty($pers)): ?><p class="km-empty">—</p><?php else: ?>
            <ul class="km-ul"><?php foreach($pers as $k=>$v): ?><li><strong><?= $esc($k) ?>:</strong> <?= $esc(is_array($v)?implode(', ',$v):$v) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="thx-card"><h3 class="km-h">Verbotene Inhalte / Handlungen</h3><?= kmList($p['forbidden']??[]) ?></div>
    </section>
    <!-- Tests & Qualität -->
    <section class="km-panel" data-panel="tests" hidden>
        <div class="thx-card"><h3 class="km-h">Testfälle (<?= is_array($p['test_cases']??null)?count($p['test_cases']):0 ?>)</h3>
            <?php $tcs=$p['test_cases']??[]; if(empty($tcs)): ?><p class="km-empty">Noch keine Testfälle. Mindestens 3 sind zum Einreichen nötig (Standardfall, unvollständige Eingabe, kritischer Grenzfall).</p>
            <?php else: foreach($tcs as $tc): ?>
                <div class="km-tc"><strong><?= $esc($tc['name']??$tc['category']??'Testfall') ?></strong> <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);"><?= $esc($tc['category']??'') ?></span>
                    <div class="km-field"><?= $esc($tc['expected']??$tc['expected_behavior']??'') ?></div></div>
            <?php endforeach; endif; ?>
        </div>
        <div class="thx-card"><h3 class="km-h">Qualitätsregeln</h3><?= kmList($p['quality_rules']??[]) ?></div>
    </section>
    <!-- Feedback -->
    <section class="km-panel" data-panel="feedback" hidden>
        <div class="thx-card"><h3 class="km-h">Feedback &amp; Entwicklung</h3><div id="km-feedback">Lädt…</div></div>
    </section>
    <!-- Test-Chat -->
    <section class="km-panel" data-panel="testchat" hidden>
        <div class="thx-card" id="km-testchat-wrap"><h3 class="km-h">Test-Chat</h3>
            <p class="km-empty" id="km-testchat-hint">Probiere den KI-Mitarbeiter in einer isolierten Testumgebung aus.</p>
            <div id="km-tc-messages" class="wz-messages" style="height:340px;border:1px solid var(--slate-200);border-radius:8px;"></div>
            <div class="wz-input" style="border:none;padding:10px 0 0;">
                <textarea id="km-tc-input" rows="2" placeholder="Testeingabe…" style="flex:1;border:1px solid var(--slate-300);border-radius:8px;padding:8px;"></textarea>
                <button class="thx-btn thx-btn-primary" onclick="kmTestSend()">Senden</button>
            </div>
        </div>
    </section>
    <!-- Versionen -->
    <section class="km-panel" data-panel="versionen" hidden>
        <div class="thx-card"><h3 class="km-h">Versionen</h3><div id="km-versions">Lädt…</div></div>
    </section>
    <!-- Aktivität -->
    <section class="km-panel" data-panel="audit" hidden>
        <div class="thx-card"><h3 class="km-h">Aktivität / Audit-Log</h3><div id="km-audit">Lädt…</div></div>
    </section>
</div>

<style>
.km-avatar{width:44px;height:44px;border-radius:10px;background:var(--thoxan-50);color:var(--thoxan-600);display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.km-badge{font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;}
.km-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;}@media(max-width:800px){.km-cols{grid-template-columns:1fr;}}
.km-panel .thx-card{margin-bottom:14px;}
.km-h{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--slate-500);margin:0 0 8px;font-weight:700;}
.km-field{font-size:var(--d-fs-sm);color:var(--slate-700);margin:4px 0;line-height:1.5;}
.km-ul{margin:0;padding-left:18px;font-size:var(--d-fs-sm);color:var(--slate-700);}.km-ul li{margin:3px 0;}
.km-empty{color:var(--slate-400);font-size:var(--d-fs-sm);}
.km-lbl{display:flex;flex-direction:column;gap:3px;font-size:12px;color:var(--slate-500);}
.km-tc{padding:8px 0;border-bottom:1px solid var(--slate-100);}
.km-perm-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-sm);}
.km-tab{cursor:pointer;}
</style>

<script>
(function(){
    var wrap=document.querySelector('.km-panels'); var eid=wrap.getAttribute('data-employee'); var isAdmin=wrap.getAttribute('data-admin')==='1';
    var base='/api/v1/ki-mitarbeiter/'+eid;
    function h(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    var CSRF=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
    function notify(m,t){ if(window.App && App.showNotification) App.showNotification(m,t); }
    function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(b||{})}).then(r=>r.json());}
    function get(u){return fetch(u).then(r=>r.json());}
    var loaded={};
    function showTab(slug){
        document.querySelectorAll('.km-panel').forEach(function(s){ s.hidden = s.getAttribute('data-panel')!==slug; });
        document.querySelectorAll('.km-tab').forEach(function(t){ t.classList.toggle('is-active', t.getAttribute('data-tab')===slug); });
        if(!loaded[slug]){ loaded[slug]=true; if(slug==='tools')loadPerms(); if(slug==='versionen')loadVersions(); if(slug==='audit')loadAudit(); if(slug==='feedback')loadFeedback(); }
    }
    document.querySelectorAll('.km-tab').forEach(function(t){ t.addEventListener('click',function(ev){ ev.preventDefault(); var s=t.getAttribute('data-tab'); history.replaceState(null,'','#'+s); showTab(s); }); });
    showTab((location.hash||'#uebersicht').slice(1));

    window.kmAction=function(action){ post(base+'/'+action,{}).then(function(res){ if(res.success){location.reload();} else {alert(res.message||'Fehler');} }); };
    window.kmTransition=function(to){ post(base+'/transition',{to:to}).then(function(res){ if(res.success){location.reload();} else {alert(res.message||'Fehler');} }); };

    function loadPerms(){ get(base+'/permissions').then(function(res){
        var box=document.getElementById('km-perms'); if(!res.success){box.textContent='Fehler';return;}
        var ps=res.data.permissions||[]; if(!ps.length){box.innerHTML='<p class="km-empty">Noch keine Zugriffe beantragt.</p>';return;}
        box.innerHTML=ps.map(function(p){
            var st={requested:'beantragt',approved:'freigegeben',rejected:'abgelehnt'}[p.status]||p.status;
            var actions=''; if(p.status==='requested'&&isAdmin){ actions='<button class="thx-btn thx-btn-small thx-btn-primary" onclick="kmApprovePerm('+p.id+',1)">Freigeben</button> <button class="thx-btn thx-btn-small" onclick="kmApprovePerm('+p.id+',0)">Ablehnen</button>'; }
            return '<div class="km-perm-row"><div><strong>'+h(p.tool_key)+'</strong> — '+h(p.permission_level)+' <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">'+h(st)+'</span><br><span class="km-empty">'+h(p.justification||'')+'</span></div><div>'+actions+'</div></div>';
        }).join('');
    }); }
    window.kmApprovePerm=function(id,ok){ post('/api/v1/ai-permissions/'+id+'/'+(ok?'approve':'reject'),{}).then(function(res){ loaded['tools']=false; loadPerms(); }); };
    window.kmRequestPerm=function(){
        post(base+'/permissions/request',{tool_key:document.getElementById('km-perm-tool').value,permission_level:document.getElementById('km-perm-level').value,justification:document.getElementById('km-perm-just').value}).then(function(res){ if(res.success){document.getElementById('km-perm-just').value='';loadPerms();} else alert(res.message); });
    };

    function loadVersions(){ get(base+'/versions').then(function(res){ var box=document.getElementById('km-versions'); if(!res.success){box.textContent='Fehler';return;} var vs=res.data.versions||[]; if(!vs.length){box.innerHTML='<p class="km-empty">Noch keine Versionen.</p>';return;} box.innerHTML=vs.map(function(v){ return '<div class="km-perm-row"><div><strong>v'+v.version_number+'</strong> · '+h(v.change_summary||'')+'<br><span class="km-empty">'+h(v.created_by_name||'')+' · '+h(v.created_at)+(v.approved_by_name?' · freigegeben von '+h(v.approved_by_name):'')+'</span></div><button class="thx-btn thx-btn-small" onclick="kmRestore('+v.version_number+')">Wiederherstellen</button></div>'; }).join(''); }); }
    window.kmRestore=function(n){ if(!confirm('Version v'+n+' wiederherstellen? Der aktuelle Stand wird vorher als Version gesichert.'))return; post(base+'/versions/'+n+'/restore',{}).then(function(res){ if(res.success)location.reload(); else alert(res.message); }); };

    function loadAudit(){ get(base+'/audit-log').then(function(res){ var box=document.getElementById('km-audit'); if(!res.success){box.textContent='Fehler';return;} var evs=res.data.events||[]; if(!evs.length){box.innerHTML='<p class="km-empty">Noch keine Aktivität.</p>';return;} box.innerHTML='<ul class="km-ul">'+evs.map(function(a){ return '<li><strong>'+h(a.action)+'</strong> · '+h(a.actor_name||'System')+' · '+h(a.occurred_at)+'</li>'; }).join('')+'</ul>'; }); }
    function loadFeedback(){ get(base+'/feedback').then(function(res){
        var box=document.getElementById('km-feedback'); if(!res.success){box.textContent='Fehler';return;}
        var fs=res.data.feedback||[]; if(!fs.length){box.innerHTML='<p class="km-empty">Noch kein Feedback. Bewerte einen Testlauf, um den Lernkreis zu starten.</p>';return;}
        box.innerHTML=fs.map(function(f){
            var st={open:'offen',accepted:'übernommen',rejected:'abgelehnt'}[f.status]||f.status;
            var sug=''; if(f.suggested_change){ sug='<div class="km-field" style="background:var(--thoxan-50);padding:8px;border-radius:6px;margin-top:6px;"><strong>KI-Vorschlag:</strong> '+h(f.suggested_change.summary||'')+'<br><span class="km-empty">Betrifft: '+h(Object.keys(f.suggested_change.profile_patch||{}).join(', '))+'</span></div>'; }
            var actions=''; if(f.status==='open'){ if(!f.suggested_change){ actions='<button class="thx-btn thx-btn-small" onclick="kmSuggest('+f.id+')">Verbesserungsvorschlag erzeugen</button>'; } else { actions='<button class="thx-btn thx-btn-small thx-btn-primary" onclick="kmApplyFb('+f.id+')">Übernehmen (neue Version)</button> <button class="thx-btn thx-btn-small" onclick="kmRejectFb('+f.id+')">Ablehnen</button>'; } }
            return '<div class="km-perm-row" style="flex-direction:column;align-items:stretch;"><div><strong>'+h(f.feedback_type)+'</strong> '+(f.rating?('· '+(f.rating>0?'👍':'👎')):'')+' <span class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">'+h(st)+'</span><br><span class="km-empty">'+h(f.comment||'')+' — '+h(f.user_name||'')+'</span></div>'+sug+'<div style="margin-top:6px;">'+actions+'</div></div>';
        }).join('');
    }); }
    window.kmSuggest=function(fid){ post(base+'/feedback/'+fid+'/suggest',{}).then(function(res){ if(res.success){loaded['feedback']=false;loadFeedback();} else alert(res.message); }); };
    window.kmApplyFb=function(fid){ if(!confirm('Vorschlag übernehmen? Es entsteht eine neue Profilversion.'))return; post(base+'/feedback/'+fid+'/apply',{}).then(function(res){ if(res.success){notify('Übernommen','success');loaded['feedback']=false;loadFeedback();} else alert(res.message); }); };
    window.kmRejectFb=function(fid){ post(base+'/feedback/'+fid+'/reject',{}).then(function(res){ loaded['feedback']=false;loadFeedback(); }); };
    window.kmRunFeedback=function(runId,rating,type,comment){ post('/api/v1/ai-runs/'+runId+'/feedback',{rating:rating,feedback_type:type,comment:comment||''}).then(function(res){ if(res.success) notify('Danke für das Feedback','success'); loaded['feedback']=false; }); };

    // Test-Chat (Runner)
    window.kmTestSend=function(){
        var inp=document.getElementById('km-tc-input'); var msg=inp.value.trim(); if(!msg)return; inp.value='';
        var box=document.getElementById('km-tc-messages');
        var u=document.createElement('div'); u.className='wz-msg user'; u.textContent=msg; box.appendChild(u);
        var a=document.createElement('div'); a.className='wz-msg assistant'; a.textContent='…'; box.appendChild(a); box.scrollTop=box.scrollHeight;
        post(base+'/test-runs',{message:msg}).then(function(res){
            a.textContent = res.success ? (res.data.reply||'(keine Antwort)') : ('Fehler: '+(res.message||''));
            if(res.success && res.data.run_id){
                var fb=document.createElement('div'); fb.style.cssText='align-self:flex-start;margin-top:-4px;display:flex;gap:6px;';
                fb.innerHTML='<button class="thx-btn thx-btn-small" title="gut">👍</button><button class="thx-btn thx-btn-small" title="nicht gut">👎</button>';
                var rid=res.data.run_id;
                fb.children[0].onclick=function(){ kmRunFeedback(rid,1,'gut'); fb.remove(); };
                fb.children[1].onclick=function(){ var c=prompt('Was war nicht gut?')||''; kmRunFeedback(rid,-1,'fachlich_falsch',c); fb.remove(); };
                box.appendChild(fb);
            }
            box.scrollTop=box.scrollHeight;
        }).catch(function(){ a.textContent='Netzwerkfehler.'; });
    };
})();
</script>
