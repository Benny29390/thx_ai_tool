<?php
/** KI-Mitarbeiter — Wizard (Chat + Live-Profilvorschau). $employeeId (int|null) */
$me = \Core\Auth::user();
$meName = trim((string) ($me['name'] ?? 'Du'));
$parts = preg_split('/\s+/', $meName);
$meInit = mb_strtoupper(mb_substr($parts[0] ?? 'D', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
?>
<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--thoxan-600);font-size:22px;">auto_awesome</span>
            <span id="wz-title">Neuer KI-Mitarbeiter</span>
        </h1>
        <p class="thx-page-subtitle">Beantworte die Fragen im Chat — Dein KI-Assistent baut das Profil rechts Schritt für Schritt auf.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span id="wz-status" class="km-badge" style="background:var(--slate-100);color:var(--slate-500);">Entwurf</span>
        <a id="wz-detail" href="#" class="thx-btn" style="display:none;">Zur Detailansicht</a>
        <button id="wz-submit" class="thx-btn thx-btn-primary" onclick="wzSubmit()" disabled>Zur Prüfung einreichen</button>
    </div>
</div>

<!-- Schritt-Anleitung: die 7 Phasen -->
<div class="wz-steps" id="wz-steps">
    <?php $phasen = [['flag','Bedarf'],['badge','Rolle'],['checklist','Aufgaben'],['menu_book','Wissen'],['lock','Zugriffe'],['forum','Persönlichkeit'],['science','Tests']];
    foreach ($phasen as $i => $ph): ?>
        <div class="wz-step" data-step="<?= $i ?>">
            <span class="wz-step-dot"><span class="material-symbols-rounded"><?= $ph[0] ?></span></span>
            <span class="wz-step-label"><?= $ph[1] ?></span>
        </div>
        <?php if ($i < count($phasen) - 1): ?><span class="wz-step-line"></span><?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="wz-wrap">
    <!-- Chat -->
    <div class="thx-card wz-chat">
        <div class="wz-chat-head">
            <span class="wz-bot"><span class="material-symbols-rounded">smart_toy</span></span>
            <div><div class="wz-bot-name">KI-Assistent</div><div class="wz-bot-sub">hilft Dir beim Entwerfen</div></div>
        </div>
        <div id="wz-messages" class="wz-messages"></div>
        <div id="wz-quick" class="wz-quick"></div>
        <div class="wz-input">
            <textarea id="wz-input" rows="1" placeholder="Antwort schreiben…  (Enter sendet, Shift+Enter = Zeilenumbruch)" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();wzSend();}"></textarea>
            <button class="thx-btn thx-btn-primary wz-send-btn" onclick="wzSend()" id="wz-send"><span class="material-symbols-rounded">send</span></button>
        </div>
    </div>

    <!-- Profilvorschau -->
    <div class="thx-card wz-preview">
        <div class="wz-prev-head">
            <span class="wz-prev-avatar material-symbols-rounded">smart_toy</span>
            <div style="min-width:0;">
                <div class="wz-prev-name" id="wz-prev-name">Neuer KI-Mitarbeiter</div>
                <div class="wz-prev-role" id="wz-prev-role">Rolle wird im Gespräch festgelegt…</div>
            </div>
        </div>
        <div class="wz-complete">
            <div style="display:flex;justify-content:space-between;font-size:var(--d-fs-sm);color:var(--slate-500);margin-bottom:4px;">
                <span>Vollständigkeit</span><span id="wz-pct">0%</span>
            </div>
            <div class="wz-bar"><div id="wz-bar-fill" class="wz-bar-fill" style="width:0%;"></div></div>
        </div>
        <div id="wz-profile" class="wz-profile"><p class="wz-empty">Sobald Du antwortest, erscheint hier das Profil.</p></div>
    </div>
</div>

<style>
.wz-steps { display:flex; align-items:center; gap:4px; max-width:1200px; margin:0 0 16px; overflow-x:auto; padding-bottom:2px; }
.wz-step { display:flex; align-items:center; gap:6px; flex:0 0 auto; opacity:.55; transition:opacity .2s; }
.wz-step.is-active { opacity:1; }
.wz-step-dot { width:30px; height:30px; border-radius:50%; background:var(--slate-100); color:var(--slate-500); display:flex; align-items:center; justify-content:center; }
.wz-step.is-active .wz-step-dot { background:var(--thoxan-500); color:#fff; }
.wz-step.is-done .wz-step-dot { background:var(--emerald-500); color:#fff; }
.wz-step-dot .material-symbols-rounded { font-size:17px; }
.wz-step-label { font-size:12px; font-weight:600; color:var(--slate-600); white-space:nowrap; }
.wz-step-line { width:22px; height:2px; background:var(--slate-200); flex:0 0 auto; }

.wz-wrap { display:grid; grid-template-columns:1.1fr 1fr; gap:16px; max-width:1200px; align-items:start; }
@media (max-width:900px){ .wz-wrap{ grid-template-columns:1fr; } }
.wz-chat { display:flex; flex-direction:column; height:68vh; padding:0; overflow:hidden; }
.wz-chat-head { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--slate-100); background:linear-gradient(180deg,var(--thoxan-50),#fff); }
.wz-bot { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,var(--thoxan-500),var(--thoxan-700)); color:#fff; display:flex; align-items:center; justify-content:center; }
.wz-bot .material-symbols-rounded { font-size:19px; }
.wz-bot-name { font-weight:700; font-size:var(--d-fs-sm); color:var(--slate-800); }
.wz-bot-sub { font-size:11px; color:var(--slate-500); }
.wz-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:14px; }

.wz-row { display:flex; gap:9px; align-items:flex-end; max-width:88%; }
.wz-row.user { align-self:flex-end; flex-direction:row-reverse; }
.wz-row.assistant { align-self:flex-start; }
.wz-ava { width:30px; height:30px; border-radius:50%; flex:0 0 auto; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
.wz-ava.bot { background:linear-gradient(135deg,var(--thoxan-500),var(--thoxan-700)); color:#fff; }
.wz-ava.bot .material-symbols-rounded { font-size:17px; }
.wz-ava.me { background:var(--slate-700); color:#fff; }
.wz-bubble { padding:10px 14px; border-radius:14px; font-size:var(--d-fs-sm); line-height:1.55; white-space:pre-wrap; }
.wz-row.user .wz-bubble { background:var(--thoxan-600); color:#fff; border-bottom-right-radius:4px; }
.wz-row.assistant .wz-bubble { background:var(--slate-100); color:var(--slate-800); border-bottom-left-radius:4px; }
.wz-typing { display:inline-flex; gap:4px; padding:12px 14px; }
.wz-typing span { width:7px; height:7px; border-radius:50%; background:var(--slate-400); animation:wzblink 1.2s infinite both; }
.wz-typing span:nth-child(2){ animation-delay:.2s; } .wz-typing span:nth-child(3){ animation-delay:.4s; }
@keyframes wzblink { 0%,80%,100%{ opacity:.25; transform:translateY(0);} 40%{ opacity:1; transform:translateY(-3px);} }

.wz-quick { display:flex; flex-wrap:wrap; gap:6px; padding:0 16px 4px; }
.wz-quick button { background:#fff; color:var(--thoxan-700); border:1px solid var(--thoxan-200); border-radius:16px; padding:5px 13px; font-size:12px; cursor:pointer; transition:background .15s; }
.wz-quick button:hover { background:var(--thoxan-50); }
.wz-input { display:flex; gap:8px; padding:12px 16px; border-top:1px solid var(--slate-100); align-items:flex-end; }
.wz-input textarea { flex:1; resize:none; border:1px solid var(--slate-300); border-radius:12px; padding:10px 12px; font-family:inherit; font-size:var(--d-fs-sm); max-height:120px; }
.wz-input textarea:focus { outline:none; border-color:var(--thoxan-400); box-shadow:0 0 0 3px var(--thoxan-50); }
.wz-send-btn { padding:0; width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
.wz-send-btn .material-symbols-rounded { font-size:20px; }

.wz-preview { height:68vh; overflow-y:auto; }
.wz-prev-head { display:flex; gap:12px; align-items:center; padding-bottom:14px; margin-bottom:14px; border-bottom:1px solid var(--slate-100); }
.wz-prev-avatar { width:46px; height:46px; border-radius:12px; background:var(--thoxan-50); color:var(--thoxan-600); display:flex; align-items:center; justify-content:center; font-size:26px; flex:0 0 auto; }
.wz-prev-name { font-weight:700; color:var(--slate-800); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wz-prev-role { font-size:var(--d-fs-sm); color:var(--slate-500); }
.wz-complete { margin-bottom:14px; }
.wz-bar { height:8px; background:var(--slate-100); border-radius:6px; overflow:hidden; }
.wz-bar-fill { height:100%; background:linear-gradient(90deg,var(--thoxan-400),var(--thoxan-600)); transition:width .4s; }
.wz-profile .wz-sec { margin-bottom:14px; }
.wz-profile .wz-sec-h { display:flex; align-items:center; gap:6px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--slate-500); margin:0 0 5px; font-weight:700; }
.wz-profile .wz-sec-h .material-symbols-rounded { font-size:15px; color:var(--thoxan-500); }
.wz-profile .wz-val { font-size:var(--d-fs-sm); color:var(--slate-800); line-height:1.5; }
.wz-profile ul { margin:0; padding-left:18px; font-size:var(--d-fs-sm); color:var(--slate-700); }
.wz-profile li { margin:3px 0; }
.wz-empty { color:var(--slate-400); font-size:var(--d-fs-sm); }
</style>

<script>
(function () {
    var employeeId = <?= $employeeId ? (int) $employeeId : 'null' ?>;
    var MEINIT = <?= json_encode($meInit ?: 'D') ?>;
    var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';
    function notify(m,t){ if(window.App && App.showNotification) App.showNotification(m,t); }
    function h(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    function post(url, body){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body||{})}).then(r=>r.json()); }
    function get(url){ return fetch(url).then(r=>r.json()); }

    var elMsgs=document.getElementById('wz-messages'), elQuick=document.getElementById('wz-quick'),
        elInput=document.getElementById('wz-input'), elSend=document.getElementById('wz-send');

    function addMsg(role, text){
        var row=document.createElement('div'); row.className='wz-row '+role;
        var ava=document.createElement('div');
        if(role==='assistant'){ ava.className='wz-ava bot'; ava.innerHTML='<span class="material-symbols-rounded">smart_toy</span>'; }
        else { ava.className='wz-ava me'; ava.textContent=MEINIT; }
        var b=document.createElement('div'); b.className='wz-bubble'; b.textContent=text;
        row.appendChild(ava); row.appendChild(b);
        elMsgs.appendChild(row); elMsgs.scrollTop=elMsgs.scrollHeight;
        return row;
    }
    function addTyping(){
        var row=document.createElement('div'); row.className='wz-row assistant';
        row.innerHTML='<div class="wz-ava bot"><span class="material-symbols-rounded">smart_toy</span></div><div class="wz-bubble" style="padding:0;"><div class="wz-typing"><span></span><span></span><span></span></div></div>';
        elMsgs.appendChild(row); elMsgs.scrollTop=elMsgs.scrollHeight; return row;
    }

    var SEC=[
        ['role_title','Rollenbezeichnung','badge','scalar'],['short_description','Kurzbeschreibung','notes','scalar'],
        ['problem_statement','Problem','flag','scalar'],['goals','Ziele','target','list'],
        ['tasks','Aufgaben','checklist','tasks'],['non_tasks','Nicht-Aufgaben','block','list'],
        ['escalation_rules','Eskalation','emergency','list'],['workflows','Workflows','account_tree','wf'],
        ['knowledge_sources','Wissensquellen','menu_book','list'],['forbidden','Verboten','dangerous','list'],
        ['personality','Persönlichkeit','forum','obj'],['test_cases','Testfälle','science','tc'],
    ];
    function renderProfile(p){
        p=p||{}; var box=document.getElementById('wz-profile'); box.innerHTML=''; var any=false;
        SEC.forEach(function(s){
            var v=p[s[0]]; if(v==null||(Array.isArray(v)&&!v.length)||v===''||(typeof v==='object'&&!Array.isArray(v)&&!Object.keys(v).length)) return; any=true;
            var inner='';
            if(s[3]==='scalar'){ inner='<div class="wz-val">'+h(v)+'</div>'; }
            else if(s[3]==='list'){ inner='<ul>'+v.map(function(x){return '<li>'+h(typeof x==='object'?JSON.stringify(x):x)+'</li>';}).join('')+'</ul>'; }
            else if(s[3]==='tasks'){ inner='<ul>'+v.map(function(t){return '<li>'+h(t.title||t)+(t.included===false?' <em>(nein)</em>':'')+'</li>';}).join('')+'</ul>'; }
            else if(s[3]==='wf'){ inner='<ul>'+v.map(function(w){return '<li>'+h(w.name||w)+'</li>';}).join('')+'</ul>'; }
            else if(s[3]==='tc'){ inner='<ul>'+v.map(function(t){return '<li>'+h(t.name||t.category||'Testfall')+'</li>';}).join('')+'</ul>'; }
            else if(s[3]==='obj'){ inner='<ul>'+Object.keys(v).map(function(k){return '<li>'+h(k)+': '+h(typeof v[k]==='object'?JSON.stringify(v[k]):v[k])+'</li>';}).join('')+'</ul>'; }
            box.insertAdjacentHTML('beforeend','<div class="wz-sec"><div class="wz-sec-h"><span class="material-symbols-rounded">'+s[2]+'</span>'+h(s[1])+'</div>'+inner+'</div>');
        });
        if(!any) box.innerHTML='<p class="wz-empty">Sobald Du antwortest, erscheint hier das Profil.</p>';
        if(p.role_title){ document.getElementById('wz-prev-role').textContent=p.role_title; }
    }
    function renderCompletion(c){
        c=c||{percentage:0}; document.getElementById('wz-pct').textContent=(c.percentage||0)+'%';
        document.getElementById('wz-bar-fill').style.width=(c.percentage||0)+'%';
        document.getElementById('wz-submit').disabled=(c.percentage||0)<60;
        // Schritt-Fortschritt grob aus Vollständigkeit ableiten
        var steps=document.querySelectorAll('.wz-step'); var done=Math.round((c.percentage||0)/100*steps.length);
        steps.forEach(function(st,i){ st.classList.toggle('is-done', i<done); st.classList.toggle('is-active', i===done); });
    }
    function renderNext(qs){
        elQuick.innerHTML=''; (qs||[]).forEach(function(q){
            if(q.options&&q.options.length){ q.options.forEach(function(o){ var b=document.createElement('button'); b.textContent=o; b.onclick=function(){ elInput.value=o; wzSend(); }; elQuick.appendChild(b); }); }
        });
    }
    function setStatus(s){
        var map={draft:'Entwurf',review:'In Prüfung',onboarding:'Einarbeitung',probation:'Probezeit',active:'Aktiv',paused:'Pausiert',archived:'Archiviert'};
        document.getElementById('wz-status').textContent=map[s]||s;
    }
    function setName(n){ if(n){ document.getElementById('wz-title').textContent=n; document.getElementById('wz-prev-name').textContent=n; } }

    var STARTER=['Kundenanfragen vorsortieren und vorbereiten','Rechnungen prüfen und Auffälligkeiten melden','Social-Media-Beiträge im Entwurf schreiben','Protokolle aus Meetings zusammenfassen'];
    function showStarters(){
        elQuick.innerHTML=''; STARTER.forEach(function(s){ var b=document.createElement('button'); b.textContent=s; b.onclick=function(){ elInput.value=s; wzSend(); }; elQuick.appendChild(b); });
    }

    function loadState(){
        get('/api/v1/ki-mitarbeiter/'+employeeId+'/wizard/state').then(function(res){
            if(!res.success) return; var d=res.data; elMsgs.innerHTML='';
            if(!d.messages||!d.messages.length){
                addMsg('assistant','Hi! Schön, dass Du da bist. 🙌\n\nLass uns gemeinsam einen KI-Mitarbeiter entwerfen. Erzähl mir zuerst kurz: Was soll Dir oder Deinem Team abgenommen werden? Wo ist gerade der größte Engpass?');
                showStarters();
            } else { d.messages.forEach(function(m){ addMsg(m.role==='assistant'?'assistant':'user', m.content); }); }
            setName(d.name && d.name!=='Neuer KI-Mitarbeiter' ? d.name : ''); setStatus(d.status||'draft');
            renderProfile(d.profile); renderCompletion(d.completeness);
            var det=document.getElementById('wz-detail'); det.style.display=''; det.href='/ki-mitarbeiter/'+employeeId;
        });
    }

    window.wzSend=function(){
        var msg=elInput.value.trim(); if(!msg) return;
        elInput.value=''; elInput.style.height='auto'; elQuick.innerHTML=''; addMsg('user',msg);
        elSend.disabled=true; var typing=addTyping();
        post('/api/v1/ki-mitarbeiter/'+employeeId+'/wizard/messages',{message:msg}).then(function(res){
            typing.remove(); elSend.disabled=false;
            if(!res.success){ addMsg('assistant','Da ging etwas schief: '+(res.message||'unbekannt')); return; }
            var d=res.data; addMsg('assistant', d.assistant_message);
            if(d.profile_patch && d.profile_patch.name) setName(d.profile_patch.name);
            renderProfile(d.profile); renderCompletion(d.completion); renderNext(d.next_questions);
        }).catch(function(){ typing.remove(); elSend.disabled=false; addMsg('assistant','Netzwerkfehler — bitte nochmal.'); });
    };
    window.wzSubmit=function(){
        post('/api/v1/ki-mitarbeiter/'+employeeId+'/submit-review',{}).then(function(res){
            if(res.success){ notify('Zur Prüfung eingereicht','success'); setStatus('review'); }
            else { notify(res.message,'error'); }
        });
    };
    // Textarea automatisch wachsen
    elInput.addEventListener('input',function(){ this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,120)+'px'; });

    if(!employeeId){
        post('/api/v1/ki-mitarbeiter',{name:'Neuer KI-Mitarbeiter'}).then(function(res){
            if(res.success){ employeeId=res.data.id; history.replaceState(null,'','/ki-mitarbeiter/'+employeeId+'/wizard'); loadState(); }
            else { addMsg('assistant','Konnte keinen Entwurf anlegen: '+(res.message||'')); }
        });
    } else { loadState(); }
})();
</script>
