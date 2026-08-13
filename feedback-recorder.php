<?php
/**
 * Feedback-Video-Recorder (Popup).
 *
 * Geoeffnet vom Feedback-Widget (views/layouts/main.php -> startVideoRecording).
 * Nimmt den Bildschirm per getDisplayMedia + MediaRecorder auf und gibt das
 * Video an das Hauptfenster zurueck: window.opener.receiveRecordedVideo(dataUrl, blob).
 *
 * Sobald die Aufnahme laeuft:
 *  - schrumpft dieses Fenster zu einer kleinen Leiste in der Bildschirmecke
 *  - und (falls der Browser Document Picture-in-Picture kann, Chrome/Edge)
 *    schwebt ein Stopp-Knopf ueber ALLEN Fenstern, damit man nicht suchen muss.
 *  - alternativ stoppt auch die native „Freigabe beenden"-Leiste des Browsers.
 *
 * Limits: post_max_size = 8M, Base64 +33% -> Roh-Video < ~5,5 MB halten.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bildschirm aufnehmen</title>
    <style>
        :root { --blue:#005da8; --slate-700:#334155; --slate-500:#64748b; --slate-200:#e2e8f0; --slate-100:#f1f5f9; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               color:var(--slate-700); background:#fff; display:flex; flex-direction:column; min-height:100vh; }
        .hd { padding:14px 18px; border-bottom:1px solid var(--slate-200); font-weight:700; font-size:15px; }
        .bd { flex:1; padding:18px; display:flex; flex-direction:column; gap:14px; align-items:center; justify-content:center; text-align:center; }
        .hint { color:var(--slate-500); font-size:12.5px; line-height:1.5; max-width:340px; }
        button { font-family:inherit; font-size:15px; font-weight:600; border:none; border-radius:10px; padding:11px 20px;
                 cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary { background:var(--blue); color:#fff; } .btn-primary:hover { background:#004a86; }
        .btn-stop { background:#c0392b; color:#fff; } .btn-stop:hover { background:#a93226; }
        .btn-ghost { background:var(--slate-100); color:var(--slate-700); } .btn-ghost:hover { background:var(--slate-200); }
        .err { color:#c0392b; font-size:13px; max-width:340px; }
        .ok { color:#1e7e45; font-weight:600; }
        [hidden] { display:none !important; }
        /* kompakte Aufnahme-Leiste (auch fuers geschrumpfte Fenster) */
        .rec-bar { display:flex; align-items:center; gap:12px; }
        .dot { width:11px; height:11px; border-radius:50%; background:#c0392b; animation:blink 1s steps(2,start) infinite; }
        @keyframes blink { 50% { opacity:0; } }
        .timer { font:700 22px ui-monospace, monospace; font-variant-numeric:tabular-nums; }
        .meta { font-size:11.5px; color:var(--slate-500); max-width:300px; }
    </style>
</head>
<body>
    <div class="hd">Bildschirm aufnehmen</div>
    <div class="bd" id="bd">

        <div id="view-start">
            <p class="hint">Nimm Deinen Bildschirm (oder ein Fenster/Tab) auf, um ein Problem zu zeigen. Maximal 90&nbsp;Sekunden.</p>
            <button class="btn-primary" id="btn-start"><span>●</span> Aufnahme starten</button>
            <p class="hint" id="support-note"></p>
        </div>

        <div id="view-rec" hidden>
            <div class="rec-bar">
                <span class="dot"></span>
                <span class="timer" id="t">00:00</span>
                <button class="btn-stop" id="btn-stop">■ Stopp</button>
            </div>
            <p class="meta"><span id="size">0,0 MB</span> · Du kannst auch unten über die Browser-Leiste „Freigabe beenden".</p>
        </div>

        <div id="view-done" hidden>
            <p class="ok" id="done-msg">Video übernommen.</p>
            <p class="hint">Du kannst dieses Fenster schließen. Das Video hängt jetzt am Feedback.</p>
            <button class="btn-ghost" id="btn-close">Fenster schließen</button>
        </div>

        <div id="view-error" hidden>
            <p class="err" id="error-msg"></p>
            <button class="btn-ghost" id="btn-retry">Erneut versuchen</button>
        </div>
    </div>

    <script>
    (function () {
        var MAX_SECONDS = 90;
        var MAX_BYTES   = 5.5 * 1024 * 1024;

        var stream = null, recorder = null, chunks = [], totalBytes = 0, startedAt = 0, tick = null, pipWin = null, pipTimer = null;

        var byId = function (id) { return document.getElementById(id); };
        var bd = byId('bd');
        var views = { start: byId('view-start'), rec: byId('view-rec'), done: byId('view-done'), error: byId('view-error') };
        var timerEl = byId('t'), sizeEl = byId('size');

        function show(name) { for (var k in views) views[k].hidden = (k !== name); }
        function fmt(sec) { var m = Math.floor(sec/60), s = sec%60; return (m<10?'0':'')+m+':'+(s<10?'0':'')+s; }
        function stopTracks() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            if (tick) { clearInterval(tick); tick = null; }
        }
        function fail(msg) { stopTracks(); closePip(); restoreWindow(); byId('error-msg').textContent = msg; show('error'); }

        // --- Fenstergroesse ---
        // Klein und in die UNTERE LINKE Ecke: weg vom Inhalt (oben) und weg vom
        // PiP-Stopp-Knopf (unten rechts).
        function shrinkWindow() {
            try { window.resizeTo(280, 120); window.moveTo(16, Math.max(0, screen.availHeight - 150)); } catch (e) {}
        }
        function restoreWindow() {
            try { window.resizeTo(450, 500); window.moveTo(Math.max(0,(screen.width-450)/2), Math.max(0,(screen.height-500)/2)); } catch (e) {}
        }

        // --- Document Picture-in-Picture: schwebender Stopp-Knopf ueber allem ---
        async function openPip() {
            if (!('documentPictureInPicture' in window)) return;
            try {
                pipWin = await documentPictureInPicture.requestWindow({ width: 250, height: 70 });
            } catch (e) { pipWin = null; return; }
            var d = pipWin.document;
            d.body.style.cssText = 'margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
            var box = d.createElement('div');
            box.style.cssText = 'display:flex;align-items:center;gap:12px;height:100%;padding:0 14px;background:#fff;';
            box.innerHTML =
                '<span style="width:11px;height:11px;border-radius:50%;background:#c0392b;flex:0 0 auto;animation:blk 1s steps(2,start) infinite;"></span>' +
                '<span id="pt" style="font:700 20px ui-monospace,monospace;flex:1;color:#334155;">00:00</span>' +
                '<button id="pb" style="background:#c0392b;color:#fff;border:none;border-radius:8px;padding:9px 16px;font:600 14px sans-serif;cursor:pointer;">■ Stopp</button>';
            var st = d.createElement('style');
            st.textContent = '@keyframes blk{50%{opacity:0}}';
            d.head.appendChild(st);
            d.body.appendChild(box);
            pipTimer = box.querySelector('#pt');
            box.querySelector('#pb').addEventListener('click', stop);
            pipWin.addEventListener('pagehide', function () { pipTimer = null; pipWin = null; });
        }
        function closePip() { if (pipWin) { try { pipWin.close(); } catch (e) {} pipWin = null; pipTimer = null; } }

        // --- Support ---
        var supported = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia && window.MediaRecorder);
        if (!supported) {
            byId('btn-start').disabled = true; byId('btn-start').style.opacity = '0.5';
            byId('support-note').textContent = 'Bildschirmaufnahme wird hier nicht unterstützt (z.B. auf dem Handy). Bitte Desktop-Browser nutzen oder einen Screenshot anhängen.';
        }
        function pickMime() {
            var prefs = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];
            for (var i=0;i<prefs.length;i++) if (window.MediaRecorder && MediaRecorder.isTypeSupported(prefs[i])) return prefs[i];
            return 'video/webm';
        }

        async function start() {
            if (!supported) return;
            try {
                stream = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 12 }, audio: true });
            } catch (e) {
                if (e && e.name === 'NotAllowedError') { show('start'); return; }
                fail('Aufnahme konnte nicht gestartet werden: ' + (e && e.message ? e.message : e)); return;
            }
            stream.getVideoTracks()[0].addEventListener('ended', stop); // native „Freigabe beenden"

            chunks = []; totalBytes = 0;
            try { recorder = new MediaRecorder(stream, { mimeType: pickMime(), videoBitsPerSecond: 1200000 }); }
            catch (e) { recorder = new MediaRecorder(stream); }

            recorder.ondataavailable = function (ev) {
                if (ev.data && ev.data.size > 0) {
                    chunks.push(ev.data); totalBytes += ev.data.size;
                    sizeEl.textContent = (totalBytes/1048576).toFixed(1).replace('.', ',') + ' MB';
                    if (totalBytes >= MAX_BYTES) stop();
                }
            };
            recorder.onstop = finalize;
            recorder.start(1000);

            startedAt = Date.now();
            show('rec');
            shrinkWindow();
            await openPip();
            // Position nach dem Teilen/PiP-Start nochmal sichern (manche Browser
            // verschieben das Fenster beim Bildschirm-Teilen).
            setTimeout(shrinkWindow, 500);
            tick = setInterval(function () {
                var sec = Math.floor((Date.now() - startedAt) / 1000);
                var t = fmt(sec);
                timerEl.textContent = t;
                if (pipTimer) pipTimer.textContent = t;
                if (sec >= MAX_SECONDS) stop();
            }, 500);
        }

        function stop() {
            if (recorder && recorder.state !== 'inactive') { try { recorder.stop(); } catch (e) {} }
            if (tick) { clearInterval(tick); tick = null; }
        }

        function finalize() {
            stopTracks(); closePip(); restoreWindow();
            var blob = new Blob(chunks, { type: 'video/webm' }); // sauberer Typ -> Upload-Regex greift
            if (!window.opener || window.opener.closed || typeof window.opener.receiveRecordedVideo !== 'function') {
                fail('Das Feedback-Fenster wurde geschlossen. Bitte öffne das Feedback erneut und nimm noch einmal auf.'); return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                try {
                    window.opener.receiveRecordedVideo(reader.result, blob);
                    byId('done-msg').textContent = 'Video übernommen (' + (blob.size/1048576).toFixed(1).replace('.', ',') + ' MB).';
                    show('done');
                    setTimeout(function () { try { window.close(); } catch (e) {} }, 1200);
                } catch (e) { fail('Übergabe fehlgeschlagen: ' + (e && e.message ? e.message : e)); }
            };
            reader.onerror = function () { fail('Video konnte nicht verarbeitet werden.'); };
            reader.readAsDataURL(blob);
        }

        byId('btn-start').addEventListener('click', start);
        byId('btn-stop').addEventListener('click', stop);
        byId('btn-retry').addEventListener('click', function () { restoreWindow(); show('start'); });
        byId('btn-close').addEventListener('click', function () { try { window.close(); } catch (e) {} });
        window.addEventListener('beforeunload', function () { stopTracks(); closePip(); });
    })();
    </script>
</body>
</html>
