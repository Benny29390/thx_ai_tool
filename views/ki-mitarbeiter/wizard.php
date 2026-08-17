<?php /** KI-Mitarbeiter — Wizard (Chat + Live-Profilvorschau). $employeeId (int|null) */ ?>
<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">badge</span>
            <span id="wz-title">Neuer KI-Mitarbeiter</span>
        </h1>
        <p class="thx-page-subtitle">Beantworte die Fragen im Chat — das Profil rechts füllt sich automatisch.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span id="wz-status" class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">Entwurf</span>
        <a id="wz-detail" href="#" class="thx-btn" style="display:none;">Zur Detailansicht</a>
        <button id="wz-submit" class="thx-btn thx-btn-primary" onclick="wzSubmit()" disabled>Zur Prüfung einreichen</button>
    </div>
</div>

<div class="wz-wrap">
    <!-- Chat -->
    <div class="thx-card wz-chat">
        <div id="wz-messages" class="wz-messages"></div>
        <div id="wz-quick" class="wz-quick"></div>
        <div class="wz-input">
            <textarea id="wz-input" rows="2" placeholder="Antwort schreiben…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();wzSend();}"></textarea>
            <button class="thx-btn thx-btn-primary" onclick="wzSend()" id="wz-send">Senden</button>
        </div>
    </div>

    <!-- Profilvorschau -->
    <div class="thx-card wz-preview">
        <div class="wz-complete">
            <div style="display:flex;justify-content:space-between;font-size:var(--d-fs-sm);color:var(--slate-500);">
                <span>Vollständigkeit</span><span id="wz-pct">0%</span>
            </div>
            <div class="wz-bar"><div id="wz-bar-fill" class="wz-bar-fill" style="width:0%;"></div></div>
        </div>
        <div id="wz-profile" class="wz-profile"><p class="wz-empty">Noch keine Angaben — leg im Chat los.</p></div>
    </div>
</div>

<style>
.wz-wrap { display:grid; grid-template-columns:1fr 1fr; gap:16px; max-width:1200px; align-items:start; }
@media (max-width:900px){ .wz-wrap{ grid-template-columns:1fr; } }
.wz-chat { display:flex; flex-direction:column; height:70vh; padding:0; }
.wz-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; }
.wz-msg { max-width:85%; padding:9px 13px; border-radius:12px; font-size:var(--d-fs-sm); line-height:1.5; white-space:pre-wrap; }
.wz-msg.user { align-self:flex-end; background:var(--thoxan-600); color:#fff; border-bottom-right-radius:3px; }
.wz-msg.assistant { align-self:flex-start; background:var(--slate-100); color:var(--slate-800); border-bottom-left-radius:3px; }
.wz-quick { display:flex; flex-wrap:wrap; gap:6px; padding:0 16px; }
.wz-quick button { background:var(--thoxan-50); color:var(--thoxan-700); border:1px solid var(--thoxan-200); border-radius:16px; padding:4px 12px; font-size:12px; cursor:pointer; }
.wz-quick button:hover { background:var(--thoxan-100); }
.wz-input { display:flex; gap:8px; padding:12px 16px; border-top:1px solid var(--slate-200); }
.wz-input textarea { flex:1; resize:none; border:1px solid var(--slate-300); border-radius:8px; padding:8px 10px; font-family:inherit; font-size:var(--d-fs-sm); }
.wz-preview { height:70vh; overflow-y:auto; }
.wz-complete { margin-bottom:14px; }
.wz-bar { height:8px; background:var(--slate-100); border-radius:6px; overflow:hidden; margin-top:4px; }
.wz-bar-fill { height:100%; background:var(--thoxan-500); transition:width .3s; }
.wz-profile h3 { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--slate-500); margin:14px 0 4px; font-weight:700; }
.wz-profile .wz-val { font-size:var(--d-fs-sm); color:var(--slate-800); }
.wz-profile ul { margin:2px 0 0; padding-left:18px; font-size:var(--d-fs-sm); color:var(--slate-700); }
.wz-profile li { margin:2px 0; }
.wz-empty { color:var(--slate-400); font-size:var(--d-fs-sm); }
</style>

<script>
(function () {
    var employeeId = <?= $employeeId ? (int) $employeeId : 'null' ?>;
    function h(s){ return App.escapeHtml ? App.escapeHtml(String(s==null?'':s)) : String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    function post(url, body){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},body:JSON.stringify(body||{})}).then(r=>r.json()); }
    function get(url){ return fetch(url).then(r=>r.json()); }

    var elMsgs=document.getElementById('wz-messages'), elQuick=document.getElementById('wz-quick'),
        elInput=document.getElementById('wz-input'), elSend=document.getElementById('wz-send');

    function addMsg(role, text){ var d=document.createElement('div'); d.className='wz-msg '+role; d.textContent=text; elMsgs.appendChild(d); elMsgs.scrollTop=elMsgs.scrollHeight; }

    function renderProfile(p){
        p = p || {};
        var box=document.getElementById('wz-profile'); box.innerHTML='';
        var sections=[
            ['role_title','Rollenbezeichnung','scalar'],['short_description','Kurzbeschreibung','scalar'],
            ['problem_statement','Problem','scalar'],['goals','Ziele','list'],
            ['tasks','Aufgaben','tasks'],['non_tasks','Nicht-Aufgaben','list'],
            ['escalation_rules','Eskalation','list'],['workflows','Workflows','wf'],
            ['knowledge_sources','Wissensquellen','list'],['forbidden','Verboten','list'],
            ['personality','Persönlichkeit','obj'],['test_cases','Testfälle','tc'],
        ];
        var any=false;
        sections.forEach(function(s){
            var v=p[s[0]]; if(v==null || (Array.isArray(v)&&!v.length) || v==='') return; any=true;
            var html='<h3>'+h(s[1])+'</h3>';
            if(s[2]==='scalar'){ html+='<div class="wz-val">'+h(v)+'</div>'; }
            else if(s[2]==='list'){ html+='<ul>'+v.map(function(x){return '<li>'+h(typeof x==='object'?JSON.stringify(x):x)+'</li>';}).join('')+'</ul>'; }
            else if(s[2]==='tasks'){ html+='<ul>'+v.map(function(t){return '<li>'+h(t.title||t)+(t.included===false?' <em>(nein)</em>':'')+'</li>';}).join('')+'</ul>'; }
            else if(s[2]==='wf'){ html+='<ul>'+v.map(function(w){return '<li>'+h(w.name||w)+'</li>';}).join('')+'</ul>'; }
            else if(s[2]==='tc'){ html+='<ul>'+v.map(function(t){return '<li>'+h(t.name||t.category||'Testfall')+'</li>';}).join('')+'</ul>'; }
            else if(s[2]==='obj'){ html+='<ul>'+Object.keys(v).map(function(k){return '<li>'+h(k)+': '+h(typeof v[k]==='object'?JSON.stringify(v[k]):v[k])+'</li>';}).join('')+'</ul>'; }
            box.insertAdjacentHTML('beforeend', html);
        });
        if(!any) box.innerHTML='<p class="wz-empty">Noch keine Angaben — leg im Chat los.</p>';
    }

    function renderCompletion(c){
        c=c||{percentage:0}; document.getElementById('wz-pct').textContent=(c.percentage||0)+'%';
        document.getElementById('wz-bar-fill').style.width=(c.percentage||0)+'%';
        // Einreichen erlauben, wenn Pflichtsektionen grob erfüllt (Server prüft final)
        document.getElementById('wz-submit').disabled = (c.percentage||0) < 60;
    }
    function renderNext(qs){
        elQuick.innerHTML=''; (qs||[]).forEach(function(q){
            if(q.options && q.options.length){ q.options.forEach(function(o){ var b=document.createElement('button'); b.textContent=o; b.onclick=function(){ elInput.value=o; wzSend(); }; elQuick.appendChild(b); }); }
        });
    }
    function setStatus(s){
        var map={draft:'Entwurf',review:'In Prüfung',onboarding:'Einarbeitung',probation:'Probezeit',active:'Aktiv',paused:'Pausiert',archived:'Archiviert'};
        var el=document.getElementById('wz-status'); el.textContent=map[s]||s;
    }

    function loadState(){
        get('/api/v1/ki-mitarbeiter/'+employeeId+'/wizard/state').then(function(res){
            if(!res.success) return; var d=res.data;
            elMsgs.innerHTML='';
            if(!d.messages || !d.messages.length){ addMsg('assistant','Hi! Erzähl mir kurz: Was soll Dir oder Deinem Team dieser KI-Mitarbeiter abnehmen? Wo ist gerade der größte Engpass?'); }
            else { d.messages.forEach(function(m){ addMsg(m.role==='assistant'?'assistant':'user', m.content); }); }
            if(d.name){ document.getElementById('wz-title').textContent=d.name; }
            setStatus(d.status||'draft');
            renderProfile(d.profile); renderCompletion(d.completeness);
            document.getElementById('wz-detail').style.display=''; document.getElementById('wz-detail').href='/ki-mitarbeiter/'+employeeId;
        });
    }

    window.wzSend=function(){
        var msg=elInput.value.trim(); if(!msg) return;
        elInput.value=''; elQuick.innerHTML=''; addMsg('user',msg);
        elSend.disabled=true; var typing=document.createElement('div'); typing.className='wz-msg assistant'; typing.textContent='…'; elMsgs.appendChild(typing);
        post('/api/v1/ki-mitarbeiter/'+employeeId+'/wizard/messages',{message:msg}).then(function(res){
            typing.remove(); elSend.disabled=false;
            if(!res.success){ addMsg('assistant','Fehler: '+(res.message||'unbekannt')); return; }
            var d=res.data; addMsg('assistant', d.assistant_message);
            renderProfile(d.profile); renderCompletion(d.completion); renderNext(d.next_questions);
        }).catch(function(){ typing.remove(); elSend.disabled=false; addMsg('assistant','Netzwerkfehler.'); });
    };

    window.wzSubmit=function(){
        post('/api/v1/ki-mitarbeiter/'+employeeId+'/submit-review',{}).then(function(res){
            if(res.success){ App.showNotification && App.showNotification('Zur Prüfung eingereicht','success'); setStatus('review'); }
            else { App.showNotification ? App.showNotification(res.message,'error') : alert(res.message); }
        });
    };

    // Init: bei /neu erst einen Entwurf anlegen
    if(!employeeId){
        post('/api/v1/ki-mitarbeiter',{name:'Neuer KI-Mitarbeiter'}).then(function(res){
            if(res.success){ employeeId=res.data.id; history.replaceState(null,'','/ki-mitarbeiter/'+employeeId+'/wizard'); loadState(); }
            else { addMsg('assistant','Konnte keinen Entwurf anlegen: '+(res.message||'')); }
        });
    } else { loadState(); }
})();
</script>
