<?php
/**
 * Feedback-Cockpit — 3 Spalten (Filter-Sidebar | Liste | Ticket).
 * Dezente, weitgehend neutrale Farbgebung. Daten: $feedback (alle).
 */
$feedbackJson = json_encode(array_map(function ($f) {
    return [
        'id'            => (int)$f['id'],
        'title'         => $f['title'] ?? '',
        'feedback_type' => $f['feedback_type'],
        'description'   => (string)$f['description'],
        'status'        => $f['status'],
        'media_type'    => $f['media_type'],
        'media_path'    => $f['media_path'],
        'media'         => $f['media'] ?? [],
        'page_url'      => $f['page_url'],
        'user_name'     => $f['user_name'] ?? '',
        'created_at'    => $f['created_at'],
        'admin_notes'   => $f['admin_notes'] ?? '',
        'next_steps'    => $f['next_steps'] ?? '',
        'ai_suggestion' => $f['ai_suggestion'] ?? '',
        'measures'      => $f['measures'] ?? [],
    ];
}, $feedback), JSON_UNESCAPED_UNICODE);

// Maßnahmen (To-dos) fuer dieselbe Cockpit-Ansicht
$measuresJson = json_encode(array_map(function ($m) {
    return [
        'id'             => (int)$m['id'],
        'title'          => $m['title'] ?? '',
        'description'    => $m['description'] ?? '',
        'area'           => $m['area'] ?? '',
        'status'         => $m['status'] ?? 'offen',
        'priority'       => $m['priority'] ?? 'mittel',
        'source'         => $m['source'] ?? 'manuell',
        'feedback_count' => (int)($m['feedback_count'] ?? 0),
        'created_at'     => $m['created_at'] ?? '',
        'feedbacks'      => $m['feedbacks'] ?? [],
    ];
}, $measures ?? []), JSON_UNESCAPED_UNICODE);
?>
<div class="fbc" x-data="fbCockpit()" x-init="init()" x-cloak>

    <!-- ============ Spalte 1: Filter-Sidebar ============ -->
    <aside class="fbc-side" :class="{ collapsed: sideCollapsed }">

        <!-- Eingeklappte Leiste (nur sichtbar, wenn zugeklappt) -->
        <div class="fbc-collapsed-bar" x-show="sideCollapsed">
            <button class="fbc-icon-btn" @click="toggleSide()" title="Aufklappen">
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
            <div class="fbc-collapsed-divider"></div>
            <template x-for="s in statusList" :key="s.key">
                <button class="fbc-collapsed-item" :class="{'is-active': mode==='feedback' && filters.status === s.key}"
                        @click="mode='feedback'; filters.status = s.key" :title="'Feedback: ' + s.label + ' (' + countStatus(s.key) + ')'">
                    <span class="fbc-dot" :class="'st-'+s.key"></span>
                    <span class="fbc-collapsed-count" x-text="countStatus(s.key)"></span>
                </button>
            </template>
            <div class="fbc-collapsed-divider"></div>
            <template x-for="s in mStatusList.filter(x => x.key !== 'all')" :key="'m'+s.key">
                <button class="fbc-collapsed-item" :class="{'is-active': mode==='measures' && mFilter.status === s.key}"
                        @click="enterMeasures(s.key)" :title="'Maßnahmen: ' + s.label + ' (' + countMeasureStatus(s.key) + ')'">
                    <span class="fbc-dot" :class="'mst-'+s.key"></span>
                    <span class="fbc-collapsed-count" x-text="countMeasureStatus(s.key)"></span>
                </button>
            </template>
        </div>

        <!-- Voller Inhalt (nur sichtbar, wenn aufgeklappt) -->
        <div class="fbc-side-full" x-show="!sideCollapsed">
            <div class="fbc-side-head">
                <button class="fbc-icon-btn" @click="toggleSide()" title="Einklappen">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
                <span class="fbc-side-title">
                    <span class="material-symbols-rounded">forum</span> Feedback
                </span>
            </div>

            <div class="fbc-search">
                <span class="material-symbols-rounded">search</span>
                <input type="text" placeholder="Suchen …" x-model="filters.search">
            </div>

            <div class="fbc-side-scroll">
                <!-- Feedback-Filter -->
                <div class="fbc-filter-group">
                    <div class="fbc-filter-label">Feedback nach Status</div>
                    <template x-for="s in statusList" :key="s.key">
                        <button class="fbc-filter" :class="{'is-active': mode==='feedback' && filters.status === s.key}"
                                @click="mode='feedback'; filters.status = s.key">
                            <span class="fbc-dot" :class="'st-'+s.key"></span>
                            <span x-text="s.label"></span>
                            <span class="fbc-count" x-text="countStatus(s.key)"></span>
                        </button>
                    </template>
                </div>

                <div class="fbc-filter-group">
                    <div class="fbc-filter-label">Feedback nach Typ</div>
                    <template x-for="t in typeList" :key="t.key">
                        <button class="fbc-filter" :class="{'is-active': mode==='feedback' && filters.type === t.key}"
                                @click="mode='feedback'; filters.type = (filters.type === t.key ? 'all' : t.key)">
                            <span class="fbc-type-badge" :class="'ty-'+t.key" x-text="t.label"></span>
                        </button>
                    </template>
                </div>

                <div class="fbc-filter-group">
                    <label class="fbc-check">
                        <input type="checkbox" x-model="filters.onlyScreenshot" @change="mode='feedback'">
                        <span>Nur mit Screenshot</span>
                    </label>
                </div>

                <!-- Maßnahmen-Filter (gleiche Sidebar) -->
                <div class="fbc-filter-group fbc-filter-group-sep">
                    <div class="fbc-filter-label">Maßnahmen nach Status</div>
                    <template x-for="s in mStatusList" :key="s.key">
                        <button class="fbc-filter" :class="{'is-active': mode==='measures' && mFilter.status === s.key}"
                                @click="enterMeasures(s.key)">
                            <span class="fbc-dot" :class="'mst-'+s.key"></span>
                            <span x-text="s.label"></span>
                            <span class="fbc-count" x-text="countMeasureStatus(s.key)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </aside>

    <!-- ============ Spalte 2: Liste ============ -->
    <section class="fbc-list">
        <!-- Feedback-Liste -->
        <template x-if="mode === 'feedback'">
            <div style="display:flex;flex-direction:column;height:100%;">
                <div class="fbc-list-head">
                    <span x-text="filtered.length + ' Ticket' + (filtered.length===1?'':'s')"></span>
                </div>
                <div class="fbc-list-scroll">
                    <template x-if="filtered.length === 0">
                        <div class="fbc-empty-list">Keine Treffer.</div>
                    </template>
                    <template x-for="f in filtered" :key="f.id">
                        <button class="fbc-item" :class="{'is-active': selected && selected.id === f.id}"
                                @click="select(f.id)">
                            <div class="fbc-item-top">
                                <span class="fbc-type-badge" :class="'ty-'+f.feedback_type" x-text="typeLabel(f.feedback_type)"></span>
                                <span class="fbc-item-date" x-text="fmtDate(f.created_at)"></span>
                            </div>
                            <div class="fbc-item-title" x-text="titleOf(f)"></div>
                            <div class="fbc-item-foot">
                                <span class="fbc-dot" :class="'st-'+f.status"></span>
                                <span x-text="statusLabel(f.status)"></span>
                                <span class="fbc-item-sep">·</span>
                                <span x-text="f.user_name"></span>
                                <span x-show="(f.media && f.media.length) || (f.media_type && f.media_type !== 'none')" class="material-symbols-rounded fbc-item-ico" title="Anhang vorhanden">attachment</span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <!-- Maßnahmen-Liste -->
        <template x-if="mode === 'measures'">
            <div style="display:flex;flex-direction:column;height:100%;">
                <div class="fbc-list-head fbc-list-head-row">
                    <span x-text="filteredMeasures.length + ' Maßnahme' + (filteredMeasures.length===1?'':'n')"></span>
                    <span style="display:flex;gap:6px;">
                        <button class="fbc-newmeasure" @click="copyRunPrompt()" title="Prompt zum Abarbeiten in einer frischen Claude-Code-Session kopieren">
                            <span class="material-symbols-rounded">bolt</span> Abarbeiten
                        </button>
                        <button class="fbc-newmeasure" @click="newMeasure()" title="Neue Maßnahme anlegen">
                            <span class="material-symbols-rounded">add</span> Neu
                        </button>
                    </span>
                </div>
                <div class="fbc-list-scroll">
                    <template x-if="filteredMeasures.length === 0">
                        <div class="fbc-empty-list">Keine Maßnahmen.</div>
                    </template>
                    <template x-for="m in filteredMeasures" :key="m.id">
                        <button class="fbc-item" :class="{'is-active': selectedMeasure && selectedMeasure.id === m.id}"
                                @click="selectMeasure(m.id)">
                            <div class="fbc-item-top">
                                <span class="fbc-prio-badge" :class="'prio-'+m.priority" x-text="prioLabel(m.priority)"></span>
                                <span x-show="m.source === 'ki'" class="material-symbols-rounded fbc-item-ico" title="KI-Vorschlag">auto_awesome</span>
                                <span class="fbc-item-date" x-text="fmtDate(m.created_at)"></span>
                            </div>
                            <div class="fbc-item-title" x-text="m.title"></div>
                            <div class="fbc-item-foot">
                                <span class="fbc-dot" :class="'mst-'+m.status"></span>
                                <span x-text="measureStatusLabel(m.status)"></span>
                                <template x-if="m.area">
                                    <span><span class="fbc-item-sep">·</span> <span x-text="m.area"></span></span>
                                </template>
                                <template x-if="m.feedback_count > 0">
                                    <span class="fbc-item-ico" style="margin-left:auto;display:inline-flex;align-items:center;gap:2px;">
                                        <span class="material-symbols-rounded" style="font-size:14px;">forum</span><span x-text="m.feedback_count"></span>
                                    </span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </section>

    <!-- ============ Spalte 3: Ticket ============ -->
    <section class="fbc-detail">
        <template x-if="mode==='feedback' && !selected">
            <div class="fbc-detail-empty">
                <span class="material-symbols-rounded">contact_support</span>
                <p>Wähle links ein Ticket aus.</p>
            </div>
        </template>
        <template x-if="mode==='measures' && !selectedMeasure">
            <div class="fbc-detail-empty">
                <span class="material-symbols-rounded">checklist</span>
                <p>Wähle links eine Maßnahme aus.</p>
            </div>
        </template>

        <template x-if="mode==='feedback' && selected">
            <div class="fbc-ticket">
                <!-- Kopf: Titel + Beschreibung getrennt -->
                <div class="fbc-ticket-head">
                    <div class="fbc-ticket-head-top">
                        <span class="fbc-type-badge" :class="'ty-'+selected.feedback_type" x-text="typeLabel(selected.feedback_type)"></span>
                        <select class="fbc-status-select" x-model="selected.status" @change="setStatus()">
                            <option value="new">Offen</option>
                            <option value="in_progress">In Arbeit</option>
                            <option value="resolved">Erledigt</option>
                            <option value="wont_fix">Verworfen</option>
                        </select>
                    </div>
                    <input type="text" class="fbc-title-input" x-model="selected.title"
                           :placeholder="titleOf(selected)" @change="saveTitle()" @blur="saveTitle()">
                    <div class="fbc-ticket-meta">
                        <span x-text="selected.user_name"></span>
                        <span>·</span>
                        <span x-text="fmtDateTime(selected.created_at)"></span>
                        <template x-if="selected.page_url">
                            <a :href="selected.page_url" target="_blank" class="fbc-ticket-link">
                                <span class="material-symbols-rounded" style="font-size:14px;">link</span>
                                <span x-text="shortUrl(selected.page_url)"></span>
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Feste Aktionsleiste -->
                <div class="fbc-ticket-toolbar">
                    <button class="fbc-tool-btn is-primary" @click="analyzeAndScroll()" :disabled="analyzing"
                            title="KI erkennt die Maßnahme und schlägt nächste Schritte vor">
                        <span class="material-symbols-rounded" :class="{spin:analyzing}" x-text="analyzing?'progress_activity':'auto_awesome'"></span>
                        <span x-text="analyzing ? 'Analysiere …' : 'Maßnahme vorschlagen'"></span>
                    </button>
                    <button class="fbc-tool-btn" @click="selected.status='resolved'; setStatus()">
                        <span class="material-symbols-rounded">check</span> Erledigt
                    </button>
                    <template x-if="selected.page_url">
                        <a class="fbc-tool-btn" :href="selected.page_url" target="_blank">
                            <span class="material-symbols-rounded">open_in_new</span> Seite öffnen
                        </a>
                    </template>
                    <button class="fbc-tool-btn" @click="copyLink()" title="Link zu diesem Ticket kopieren">
                        <span class="material-symbols-rounded">link</span> Link kopieren
                    </button>
                    <button class="fbc-tool-btn is-danger" @click="del()" style="margin-left:auto;" title="Löschen">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>

                <div class="fbc-ticket-body">
                    <!-- Anhaenge: Screenshots + Videos (Galerie, volle Breite) -->
                    <template x-for="(m, mi) in (selected.media || [])" :key="mi">
                        <div>
                            <template x-if="m.type === 'screenshot'">
                                <div class="fbc-shot-frame">
                                    <img :src="m.path" class="fbc-shot" alt="Screenshot"
                                         @click="lightbox = m.path" title="Klick zum Vergrößern">
                                    <button class="fbc-shot-zoom" @click="lightbox = m.path" title="Vergrößern">
                                        <span class="material-symbols-rounded">zoom_out_map</span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="m.type === 'video'">
                                <video :src="m.path" controls class="fbc-shot-video"></video>
                            </template>
                        </div>
                    </template>

                    <!-- Beschreibung: prominent, gut lesbar -->
                    <div class="fbc-block">
                        <div class="fbc-block-label">Beschreibung</div>
                        <div class="fbc-desc-full" x-text="selected.description"></div>
                    </div>

                    <!-- Angelegte Maßnahmen (aus diesem Feedback) -->
                    <div class="fbc-block" x-show="selected.measures && selected.measures.length">
                        <div class="fbc-block-label">
                            <span class="material-symbols-rounded" style="font-size:18px;">checklist</span>
                            Angelegte Maßnahmen
                        </div>
                        <template x-for="m in (selected.measures || [])" :key="m.id">
                            <div class="fbc-linked-measure">
                                <span class="fbc-dot" :class="'mst-'+m.status"></span>
                                <span class="fbc-lm-title" x-text="m.title"></span>
                                <span class="fbc-lm-status" x-text="measureStatusLabel(m.status)"></span>
                                <button class="fbc-lm-edit" @click="openMeasure(m.id)" title="Maßnahme öffnen und bearbeiten">
                                    <span class="material-symbols-rounded" style="font-size:15px;">edit</span> bearbeiten
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- KI-Maßnahmen-Erkennung -->
                    <div class="fbc-block fbc-ai">
                        <div class="fbc-block-label">
                            <span class="material-symbols-rounded fbc-ai-ico">auto_awesome</span>
                            Maßnahmen-Erkennung &amp; nächste Schritte
                            <button class="fbc-mini-btn" @click="analyze()" :disabled="analyzing">
                                <span class="material-symbols-rounded" :class="{'spin': analyzing}" x-text="analyzing ? 'progress_activity' : (ai ? 'refresh' : 'auto_awesome')"></span>
                                <span x-text="analyzing ? 'Analysiere …' : (ai ? 'Neu analysieren' : 'KI-Analyse')"></span>
                            </button>
                        </div>

                        <template x-if="!ai && !analyzing">
                            <p class="fbc-ai-hint">Noch keine Analyse. Klick „KI-Analyse", damit ich die Maßnahme erkenne und nächste Schritte vorschlage.</p>
                        </template>

                        <template x-if="ai">
                            <div>
                                <p class="fbc-ai-summary" x-text="ai.summary"></p>
                                <div class="fbc-measure-card">
                                    <div>
                                        <div class="fbc-measure-title" x-text="ai.measure.title"></div>
                                        <div class="fbc-measure-meta">
                                            <span class="fbc-chip" x-text="ai.measure.area"></span>
                                            <span class="fbc-chip" x-text="'Priorität: ' + ai.measure.priority"></span>
                                        </div>
                                    </div>
                                    <button class="thx-btn thx-btn-secondary thx-btn-sm" @click="createMeasure()" :disabled="creatingMeasure">
                                        <span class="material-symbols-rounded">playlist_add</span> Maßnahme anlegen
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div class="fbc-steps">
                            <div class="fbc-steps-label">Nächste Schritte <span class="fbc-steps-hint">(bearbeitbar)</span></div>
                            <textarea class="fbc-steps-text" x-model="selected.next_steps"
                                      placeholder="- Schritt 1&#10;- Schritt 2 …" rows="5"></textarea>
                            <div class="fbc-steps-actions">
                                <button class="thx-btn thx-btn-secondary thx-btn-sm" @click="saveSteps()" :disabled="savingSteps">
                                    <span class="material-symbols-rounded" x-text="savingSteps ? 'progress_activity' : 'save'" :class="{'spin': savingSteps}"></span>
                                    Speichern
                                </button>
                                <span class="fbc-saved" x-show="stepsSaved" x-transition>gespeichert ✓</span>
                            </div>
                        </div>

                        <div class="fbc-steps">
                            <div class="fbc-steps-label">Kommentar / Notiz</div>
                            <textarea class="fbc-steps-text" x-model="selected.admin_notes"
                                      placeholder="Eigene Anmerkung …" rows="2" @blur="saveNotes()"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Maßnahmen-Detail -->
        <template x-if="mode==='measures' && selectedMeasure">
            <div class="fbc-ticket">
                <div class="fbc-ticket-head">
                    <div class="fbc-ticket-head-top">
                        <span class="fbc-prio-badge" :class="'prio-'+selectedMeasure.priority" x-text="prioLabel(selectedMeasure.priority)"></span>
                        <select class="fbc-status-select" x-model="selectedMeasure.status" @change="updateMeasure('status', selectedMeasure.status)">
                            <option value="offen">Offen</option>
                            <option value="in_arbeit">In Arbeit</option>
                            <option value="erledigt">Erledigt</option>
                            <option value="verworfen">Verworfen</option>
                        </select>
                    </div>
                    <input type="text" class="fbc-title-input" x-model="selectedMeasure.title"
                           @change="updateMeasure('title', selectedMeasure.title)" @blur="updateMeasure('title', selectedMeasure.title)"
                           placeholder="Titel der Maßnahme">
                    <div class="fbc-ticket-meta">
                        <template x-if="selectedMeasure.source==='ki'"><span>KI-Vorschlag ·</span></template>
                        <span>Priorität:</span>
                        <select class="fbc-inline-sel" x-model="selectedMeasure.priority" @change="updateMeasure('priority', selectedMeasure.priority)">
                            <option value="hoch">Hoch</option>
                            <option value="mittel">Mittel</option>
                            <option value="niedrig">Niedrig</option>
                        </select>
                        <span>·</span>
                        <span x-text="fmtDateTime(selectedMeasure.created_at)"></span>
                    </div>
                </div>

                <div class="fbc-ticket-toolbar">
                    <button class="fbc-tool-btn" @click="selectedMeasure.status='erledigt'; updateMeasure('status','erledigt')">
                        <span class="material-symbols-rounded">check</span> Erledigt
                    </button>
                    <button class="fbc-tool-btn" @click="copyMeasureLink()" title="Link zu dieser Maßnahme kopieren">
                        <span class="material-symbols-rounded">link</span> Link kopieren
                    </button>
                    <button class="fbc-tool-btn is-danger" @click="deleteMeasure()" style="margin-left:auto;" title="Löschen">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>

                <div class="fbc-ticket-body">
                    <div class="fbc-block">
                        <div class="fbc-block-label">Bereich</div>
                        <input type="text" class="fbc-area-input" x-model="selectedMeasure.area"
                               @change="updateMeasure('area', selectedMeasure.area)" @blur="updateMeasure('area', selectedMeasure.area)"
                               placeholder="z.B. Chat, Wissen, CRM">
                    </div>
                    <div class="fbc-block">
                        <div class="fbc-block-label">Beschreibung / nächste Schritte</div>
                        <textarea class="fbc-steps-text" x-model="selectedMeasure.description" rows="9"
                                  @blur="updateMeasure('description', selectedMeasure.description)"
                                  placeholder="Was ist zu tun?"></textarea>
                    </div>
                    <div class="fbc-block" x-show="selectedMeasure.feedbacks && selectedMeasure.feedbacks.length">
                        <div class="fbc-block-label">
                            <span class="material-symbols-rounded" style="font-size:18px;">forum</span>
                            Aus diesen Feedbacks entstanden
                        </div>
                        <template x-for="f in (selectedMeasure.feedbacks || [])" :key="f.id">
                            <button class="fbc-linked-feedback" @click="openFeedback(f.id)" title="Zum Feedback springen">
                                <span class="fbc-dot" :class="'st-'+f.status"></span>
                                <span class="fbc-lf-title" x-text="(f.title && f.title.trim()) ? f.title : ((f.description||'').slice(0,70))"></span>
                                <span class="material-symbols-rounded" style="font-size:16px;color:var(--slate-400);">arrow_forward</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <!-- Lightbox -->
    <div class="fbc-lightbox" x-show="lightbox" @click="lightbox = null" x-transition style="display:none;">
        <img :src="lightbox" alt="">
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
.fbc {
    display: flex;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
}
.fbc-side, .fbc-list, .fbc-detail {
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius); overflow: hidden;
    display: flex; flex-direction: column;
}

/* ---- Spalte 1: Sidebar (ein-/ausklappbar) ---- */
.fbc-side { width: 360px; min-width: 360px; background: var(--slate-50);
    transition: width .18s ease, min-width .18s ease; }
.fbc-side.collapsed { width: 52px; min-width: 52px; }
.fbc-side-full { display:flex; flex-direction:column; overflow:hidden; flex:1; }
.fbc-side-head { display:flex; align-items:center; gap:8px; padding:12px 14px; min-height:50px;
    border-bottom:1px solid var(--slate-200); }
.fbc-side-title { display:flex; align-items:center; gap:8px; font-weight:600; color:#334155; flex:1; }
.fbc-side-title .material-symbols-rounded { color:var(--slate-500); font-size:20px; }
.fbc-side-link { color:var(--slate-400); display:inline-flex; }
.fbc-side-link:hover { color:var(--slate-600); }
/* Einheitlicher Icon-Button (auf- und zuklappen) */
.fbc-icon-btn { border:1px solid var(--slate-200); background:#fff; border-radius:7px; width:30px; height:30px;
    display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:var(--slate-500); flex-shrink:0; }
.fbc-icon-btn:hover { background:var(--slate-100); color:var(--slate-700); }
/* Eingeklappte Leiste: zentrierte Spalte mit Icon + Status-Schnellfilter */
.fbc-collapsed-bar { display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 4px; flex:1; overflow-y:auto; }
.fbc-collapsed-divider { width:24px; height:1px; background:var(--slate-200); margin:4px 0; flex-shrink:0; }
.fbc-collapsed-item { width:36px; height:36px; border-radius:8px; border:1px solid transparent; background:none; cursor:pointer;
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1px; color:var(--slate-500); }
.fbc-collapsed-item:hover { background:var(--slate-100); }
.fbc-collapsed-item.is-active { background:#fff; border-color:var(--slate-200); }
.fbc-collapsed-count { font-size:9px; line-height:1; color:var(--slate-400); }
.fbc-search { display:flex; align-items:center; gap:6px; margin:12px; padding:7px 10px;
    background:#fff; border:1px solid var(--slate-200); border-radius:8px; }
.fbc-search .material-symbols-rounded { font-size:18px; color:var(--slate-400); }
.fbc-search input { border:none; outline:none; width:100%; font-size:var(--d-fs-sm); background:transparent; }
.fbc-side-scroll { overflow-y:auto; padding:0 12px 12px; }
.fbc-filter-group { margin-bottom:18px; }
.fbc-filter-label { font-size:var(--d-fs-xs); text-transform:uppercase; letter-spacing:.04em;
    color:var(--slate-400); margin:0 4px 8px; font-weight:600; }
.fbc-filter { display:flex; align-items:center; gap:8px; width:100%; padding:7px 10px; border:none;
    background:transparent; border-radius:8px; cursor:pointer; font-size:var(--d-fs-sm); color:var(--slate-700); text-align:left; }
.fbc-filter:hover { background:var(--slate-100); }
.fbc-filter.is-active { background:#fff; color:#0f172a; font-weight:600; box-shadow:inset 0 0 0 1px var(--slate-200); }
.fbc-count { margin-left:auto; font-size:var(--d-fs-xs); color:var(--slate-400); }
.fbc-check { display:flex; align-items:center; gap:8px; padding:4px; font-size:var(--d-fs-sm); cursor:pointer; color:var(--slate-700); }

/* dezente Status-Punkte */
.fbc-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; background:var(--slate-300); }
.fbc-dot.st-new { background:#cf7b7b; } .fbc-dot.st-in_progress { background:#cfa06a; }
.fbc-dot.st-resolved { background:#7faf86; } .fbc-dot.st-wont_fix { background:#9aa6b3; }
.fbc-dot.st-all { background:var(--slate-400); }

/* dezente Typ-Badges: neutraler Grund, gedämpfte Schriftfarbe */
.fbc-type-badge { display:inline-block; font-size:var(--d-fs-xs); padding:2px 9px; border-radius:6px; font-weight:600;
    background:var(--slate-100); color:var(--slate-600); }
.ty-bug { color:#a86a6a; } .ty-feature { color:#6a7fa6; } .ty-improvement { color:#6a9079; } .ty-other { color:#74808d; }

/* ---- Spalte 2: Liste ---- */
.fbc-list { width: 360px; min-width: 320px; }
.fbc-list-head { padding:12px 16px; border-bottom:1px solid var(--slate-200); font-size:var(--d-fs-sm);
    color:var(--slate-500); font-weight:600; }
.fbc-list-scroll { overflow-y:auto; flex:1; }
.fbc-empty-list { padding:30px; text-align:center; color:var(--slate-400); font-size:var(--d-fs-sm); }
.fbc-item { display:block; width:100%; text-align:left; padding:14px 18px; border:none; border-bottom:1px solid var(--slate-100);
    background:#fff; cursor:pointer; }
.fbc-item:hover { background:var(--slate-50); }
.fbc-item.is-active { background:var(--slate-50); box-shadow:inset 3px 0 0 var(--slate-400); }
.fbc-item-top { display:flex; align-items:center; gap:8px; margin-bottom:7px; }
.fbc-item-date { margin-left:auto; font-size:var(--d-fs-xs); color:var(--slate-400); }
.fbc-item-title { font-weight:600; font-size:var(--d-fs-sm); color:#0f172a; line-height:1.35;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.fbc-item-foot { display:flex; align-items:center; gap:6px; margin-top:9px; font-size:var(--d-fs-xs); color:var(--slate-500); }
.fbc-item-sep { color:var(--slate-300); }
.fbc-item-ico { margin-left:auto; font-size:15px !important; color:var(--slate-400); }

/* ---- Spalte 3: Ticket ---- */
.fbc-detail { flex:1; min-width:0; }
.fbc-detail-empty { margin:auto; text-align:center; color:var(--slate-300); }
.fbc-detail-empty .material-symbols-rounded { font-size:56px; }
.fbc-ticket { display:flex; flex-direction:column; height:100%; }
.fbc-ticket-head { padding:16px 20px; border-bottom:1px solid var(--slate-200); }
.fbc-ticket-head-top { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
.fbc-title-input { width:100%; border:1px solid transparent; border-radius:8px; padding:4px 8px; margin:0 -8px;
    font-size:var(--d-fs-lg); font-weight:600; color:#0f172a; font-family:var(--font-family); background:transparent; }
.fbc-title-input:hover { border-color:var(--slate-200); }
.fbc-title-input:focus { outline:none; border-color:var(--slate-300); background:#fff; }
.fbc-ticket-meta { display:flex; align-items:center; gap:8px; font-size:var(--d-fs-xs); color:var(--slate-500); flex-wrap:wrap; margin-top:8px; }
.fbc-ticket-link { display:inline-flex; align-items:center; gap:3px; color:var(--slate-500); }
.fbc-ticket-link:hover { color:var(--slate-700); }
.fbc-status-select { padding:6px 10px; border-radius:8px; border:1px solid var(--slate-300); font-size:var(--d-fs-sm);
    cursor:pointer; font-family:var(--font-family); background:#fff; color:var(--slate-700); flex-shrink:0; }

/* Toolbar: neutrale Buttons, nur die Primäraktion leicht getönt */
.fbc-ticket-toolbar { display:flex; align-items:center; gap:8px; padding:10px 20px; border-bottom:1px solid var(--slate-200);
    background:var(--slate-50); flex-wrap:wrap; flex-shrink:0; }
.fbc-tool-btn { display:inline-flex; align-items:center; gap:5px; font-size:var(--d-fs-sm); padding:6px 12px; border-radius:8px;
    border:1px solid var(--slate-200); background:#fff; color:var(--slate-700); cursor:pointer; text-decoration:none; }
.fbc-tool-btn:hover { background:var(--slate-100); }
.fbc-tool-btn.is-primary { border-color:#cdd9e6; background:#f4f7fb; color:#456485; }
.fbc-tool-btn.is-primary:hover { background:#eaf1f8; }
.fbc-tool-btn.is-danger { color:var(--slate-400); }
.fbc-tool-btn.is-danger:hover { border-color:#e7c9c9; background:#fbf4f4; color:#a05656; }
.fbc-tool-btn .material-symbols-rounded { font-size:17px; }
.fbc-tool-btn:disabled { opacity:.55; cursor:default; }

.fbc-ticket-body { overflow-y:auto; padding:0 0 24px; flex:1; }
/* Screenshot volle Breite + 18px Padding, Höhe frei */
.fbc-shot-frame { position:relative; padding:18px; background:var(--slate-50); border-bottom:1px solid var(--slate-200); }
.fbc-shot { width:100%; height:auto; display:block; border-radius:8px; border:1px solid var(--slate-200); cursor:zoom-in; }
.fbc-shot-video { width:calc(100% - 36px); margin:18px; border-radius:8px; border:1px solid var(--slate-200); }
.fbc-shot-zoom { position:absolute; top:28px; right:28px; width:30px; height:30px; border-radius:7px; border:none;
    background:rgba(15,23,42,.55); color:#fff; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.fbc-shot-zoom:hover { background:rgba(15,23,42,.75); }
.fbc-shot-zoom .material-symbols-rounded { font-size:18px; }

.fbc-block { margin:22px 20px 0; }
.fbc-block-label { display:flex; align-items:center; gap:6px; font-size:var(--d-fs-xs); font-weight:600;
    text-transform:uppercase; letter-spacing:.04em; color:var(--slate-400); margin-bottom:10px; }
/* Beschreibung klar lesbar */
.fbc-desc-full { white-space:pre-wrap; color:#1e293b; font-size:var(--d-fs-base); line-height:1.7;
    background:#fff; border:1px solid var(--slate-200); border-radius:10px; padding:14px 16px; }
.fbc-linked-measure { display:flex; align-items:center; gap:8px; padding:9px 12px; background:#fff;
    border:1px solid var(--slate-200); border-radius:8px; margin-bottom:6px; font-size:var(--d-fs-sm); }
.fbc-lm-title { font-weight:600; color:#0f172a; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fbc-lm-status { font-size:var(--d-fs-xs); color:var(--slate-500); flex-shrink:0; }
.fbc-lm-edit { display:inline-flex; align-items:center; gap:3px; color:var(--slate-500); font-size:var(--d-fs-xs); white-space:nowrap; flex-shrink:0; }
.fbc-lm-edit:hover { color:var(--slate-700); }
.fbc-dot.mst-offen { background:#cf7b7b; } .fbc-dot.mst-in_arbeit { background:#cfa06a; }
.fbc-dot.mst-erledigt { background:#7faf86; } .fbc-dot.mst-verworfen { background:#9aa6b3; }
.fbc-ai { background:var(--slate-50); border:1px solid var(--slate-200); border-radius:12px; padding:16px; }
.fbc-ai-ico { color:var(--slate-500); font-size:18px !important; }
.fbc-mini-btn { margin-left:auto; display:inline-flex; align-items:center; gap:4px; font-size:var(--d-fs-xs);
    border:1px solid var(--slate-200); background:#fff; color:var(--slate-600); border-radius:20px; padding:4px 12px; cursor:pointer; text-transform:none; letter-spacing:normal; }
.fbc-mini-btn:hover { background:var(--slate-100); } .fbc-mini-btn:disabled { opacity:.6; cursor:default; }
.fbc-mini-btn .material-symbols-rounded { font-size:15px; }
.fbc-ai-hint { font-size:var(--d-fs-sm); color:var(--slate-500); margin:4px 0 0; }
.fbc-ai-summary { font-size:var(--d-fs-sm); color:#334155; margin:6px 0 12px; line-height:1.5; }
.fbc-measure-card { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#fff;
    border:1px solid var(--slate-200); border-radius:10px; padding:12px 14px; }
.fbc-measure-title { font-weight:600; color:#0f172a; font-size:var(--d-fs-sm); }
.fbc-measure-meta { display:flex; gap:6px; margin-top:6px; flex-wrap:wrap; }
.fbc-chip { background:var(--slate-100); color:var(--slate-600); font-size:var(--d-fs-xs); padding:2px 8px; border-radius:20px; }
.thx-btn-sm { padding:6px 12px; font-size:var(--d-fs-sm); }
.fbc-steps { margin-top:16px; }
.fbc-steps-label { font-size:var(--d-fs-sm); font-weight:600; color:#334155; margin-bottom:6px; }
.fbc-steps-hint { font-weight:400; color:var(--slate-400); font-size:var(--d-fs-xs); }
.fbc-steps-text { width:100%; border:1px solid var(--slate-300); border-radius:8px; padding:10px 12px;
    font-family:var(--font-family); font-size:var(--d-fs-sm); line-height:1.5; resize:vertical; box-sizing:border-box; }
.fbc-steps-text:focus { outline:none; border-color:var(--slate-400); }
.fbc-steps-actions { display:flex; align-items:center; gap:10px; margin-top:8px; }
.fbc-saved { color:#5a8a6a; font-size:var(--d-fs-xs); }
.fbc-lightbox { position:fixed; inset:0; z-index:9998; background:rgba(15,23,42,.85);
    display:flex; align-items:center; justify-content:center; cursor:zoom-out; padding:30px; }
.fbc-lightbox img { max-width:95%; max-height:95%; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.4); }
.spin { animation: fbspin 1s linear infinite; }
@keyframes fbspin { to { transform: rotate(360deg); } }

/* ---- Maßnahmen im Cockpit ---- */
.fbc-filter-group-sep { border-top:1px solid var(--slate-200); padding-top:16px; }
.fbc-prio-badge { display:inline-block; font-size:var(--d-fs-xs); padding:2px 9px; border-radius:6px; font-weight:600;
    background:var(--slate-100); color:var(--slate-600); }
.fbc-prio-badge.prio-hoch { color:#a86a6a; } .fbc-prio-badge.prio-mittel { color:#9a8a5a; } .fbc-prio-badge.prio-niedrig { color:#74808d; }
.fbc-dot.mst-all { background:var(--slate-400); }
.fbc-list-head-row { display:flex; align-items:center; justify-content:space-between; }
.fbc-newmeasure { display:inline-flex; align-items:center; gap:3px; font-size:var(--d-fs-xs); font-weight:600;
    border:1px solid var(--slate-200); background:#fff; color:var(--slate-700); border-radius:7px; padding:4px 10px; cursor:pointer; }
.fbc-newmeasure:hover { background:var(--slate-100); }
.fbc-newmeasure .material-symbols-rounded { font-size:16px; }
.fbc-area-input { width:100%; border:1px solid var(--slate-300); border-radius:8px; padding:9px 12px;
    font-family:var(--font-family); font-size:var(--d-fs-sm); box-sizing:border-box; }
.fbc-area-input:focus { outline:none; border-color:var(--slate-400); }
.fbc-inline-sel { border:1px solid var(--slate-300); border-radius:6px; padding:2px 6px; font-size:var(--d-fs-xs);
    font-family:var(--font-family); color:var(--slate-600); cursor:pointer; }
.fbc-linked-feedback { display:flex; align-items:center; gap:8px; width:100%; text-align:left; padding:9px 12px; background:#fff;
    border:1px solid var(--slate-200); border-radius:8px; margin-bottom:6px; font-size:var(--d-fs-sm); cursor:pointer; }
.fbc-linked-feedback:hover { background:var(--slate-50); border-color:var(--slate-300); }
.fbc-lf-title { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#334155; }
</style>

<script>
function fbCockpit() {
    return {
        items: <?= $feedbackJson ?: '[]' ?>,
        filters: { status: 'new', type: 'all', onlyScreenshot: false, search: '' },
        selected: null,
        ai: null,
        analyzing: false,
        savingSteps: false,
        stepsSaved: false,
        creatingMeasure: false,
        lightbox: null,
        sideCollapsed: false,
        statusList: [
            { key: 'new', label: 'Offen' },
            { key: 'in_progress', label: 'In Arbeit' },
            { key: 'resolved', label: 'Erledigt' },
            { key: 'wont_fix', label: 'Verworfen' },
            { key: 'all', label: 'Alle' },
        ],
        typeList: [
            { key: 'bug', label: 'Bug' },
            { key: 'feature', label: 'Feature' },
            { key: 'improvement', label: 'Verbesserung' },
            { key: 'other', label: 'Sonstiges' },
        ],

        // ---- Maßnahmen (gleiche Sidebar/Optik) ----
        mode: 'feedback',
        measures: <?= $measuresJson ?: '[]' ?>,
        selectedMeasure: null,
        mFilter: { status: 'offen' },
        mStatusList: [
            { key: 'offen', label: 'Offen' },
            { key: 'in_arbeit', label: 'In Arbeit' },
            { key: 'erledigt', label: 'Erledigt' },
            { key: 'verworfen', label: 'Verworfen' },
            { key: 'all', label: 'Alle' },
        ],

        init() {
            try { this.sideCollapsed = localStorage.getItem('fbc_side_collapsed') === '1'; } catch (e) {}
            // Zuletzt genutzten Modus (feedback/measures) merken
            try { this.$watch('mode', v => { try { localStorage.setItem('fbc_mode', v); } catch (e) {} }); } catch (e) {}

            const params = new URLSearchParams(location.search);

            // Explizite Deep-Links (geteilte Links / E-Mail) haben Vorrang
            const linkedMeasure = parseInt(params.get('measure') || '', 10);
            if (linkedMeasure && this.measures.some(m => m.id === linkedMeasure)) {
                this.mode = 'measures'; this.mFilter.status = 'all';
                this.selectMeasure(linkedMeasure);
                return;
            }
            const ms = params.get('ms');
            if (ms) {
                this.mode = 'measures';
                this.mFilter.status = ['offen','in_arbeit','erledigt','verworfen','all'].includes(ms) ? ms : 'offen';
                const fm = this.filteredMeasures[0];
                if (fm) this.selectMeasure(fm.id);
                return;
            }
            const linkedId = parseInt(params.get('id') || '', 10);
            if (linkedId && this.items.some(f => f.id === linkedId)) {
                this.mode = 'feedback'; this.filters.status = 'all';
                this.select(linkedId);
                this.$nextTick(() => this.scrollToSelected());
                return;
            }

            // Fallback beim normalen Reload: zuletzt genutzter Modus + Status "Offen"
            let savedMode = 'feedback';
            try { savedMode = localStorage.getItem('fbc_mode') || 'feedback'; } catch (e) {}
            if (savedMode === 'measures') {
                this.mode = 'measures';
                this.mFilter.status = 'offen';
                const fm = this.filteredMeasures[0];
                if (fm) this.selectMeasure(fm.id);
            } else {
                this.mode = 'feedback';
                this.filters.status = 'new'; // = "Offen"
                const first = this.filtered[0];
                if (first) this.select(first.id);
            }
        },

        scrollToSelected() {
            const el = document.querySelector('.fbc-item.is-active');
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'center' });
        },

        toggleSide() {
            this.sideCollapsed = !this.sideCollapsed;
            try { localStorage.setItem('fbc_side_collapsed', this.sideCollapsed ? '1' : '0'); } catch (e) {}
        },

        get filtered() {
            const q = this.filters.search.trim().toLowerCase();
            return this.items.filter(f => {
                if (this.filters.status !== 'all' && f.status !== this.filters.status) return false;
                if (this.filters.type !== 'all' && f.feedback_type !== this.filters.type) return false;
                if (this.filters.onlyScreenshot && f.media_type !== 'screenshot') return false;
                if (q && !(f.description || '').toLowerCase().includes(q)
                      && !(f.title || '').toLowerCase().includes(q)
                      && !(f.user_name || '').toLowerCase().includes(q)) return false;
                return true;
            });
        },

        countStatus(key) {
            if (key === 'all') return this.items.length;
            return this.items.filter(f => f.status === key).length;
        },

        // ---- Maßnahmen ----
        get filteredMeasures() {
            const q = this.filters.search.trim().toLowerCase();
            return this.measures.filter(m => {
                if (this.mFilter.status !== 'all' && m.status !== this.mFilter.status) return false;
                if (q && !(m.title || '').toLowerCase().includes(q)
                      && !(m.description || '').toLowerCase().includes(q)
                      && !(m.area || '').toLowerCase().includes(q)) return false;
                return true;
            });
        },
        countMeasureStatus(key) {
            if (key === 'all') return this.measures.length;
            return this.measures.filter(m => m.status === key).length;
        },
        enterMeasures(status) {
            this.mode = 'measures';
            this.mFilter.status = status;
            if (!this.selectedMeasure || !this.filteredMeasures.some(m => m.id === this.selectedMeasure.id)) {
                const first = this.filteredMeasures[0];
                if (first) this.selectMeasure(first.id);
            }
        },
        selectMeasure(id) {
            this.selectedMeasure = this.measures.find(m => m.id === id) || null;
        },
        async updateMeasure(field, value) {
            if (!this.selectedMeasure) return;
            const body = {}; body[field] = value;
            try { await App.put('/admin/measures/' + this.selectedMeasure.id, body); }
            catch (e) { App.showNotification(e.message, 'error'); }
        },
        async deleteMeasure() {
            if (!this.selectedMeasure || !confirm('Maßnahme wirklich löschen?')) return;
            const id = this.selectedMeasure.id;
            try {
                await App.delete('/admin/measures/' + id);
                this.measures = this.measures.filter(m => m.id !== id);
                this.selectedMeasure = null;
                App.showNotification('Gelöscht', 'success');
                const first = this.filteredMeasures[0];
                if (first) this.selectMeasure(first.id);
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        async newMeasure() {
            try {
                const res = await App.post('/admin/measures', { title: 'Neue Maßnahme', priority: 'mittel' });
                const id = (res.data && res.data.id) || res.id;
                this.measures.unshift({ id, title: 'Neue Maßnahme', description: '', area: '', status: 'offen',
                    priority: 'mittel', source: 'manuell', feedback_count: 0,
                    created_at: this.nowStr(), feedbacks: [] });
                this.mode = 'measures'; this.mFilter.status = 'all';
                this.selectMeasure(id);
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        copyMeasureLink() {
            if (!this.selectedMeasure) return;
            const url = location.origin + '/admin/feedback?measure=' + this.selectedMeasure.id;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => App.showNotification('Link kopiert', 'success')).catch(() => window.prompt('Link:', url));
            } else { window.prompt('Link kopieren:', url); }
        },
        copyRunPrompt() {
            const p = 'Attacke /var/www/docs/massnahmen-abarbeiten.md';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(p).then(() => App.showNotification('Abarbeiten-Prompt kopiert: ' + p, 'success')).catch(() => window.prompt('Prompt kopieren:', p));
            } else { window.prompt('Prompt kopieren:', p); }
        },
        openFeedback(id) {
            this.mode = 'feedback';
            this.filters.status = 'all';
            this.select(id);
            this.$nextTick(() => this.scrollToSelected());
        },
        openMeasure(id) {
            this.mode = 'measures';
            this.mFilter.status = 'all';
            this.selectMeasure(id);
        },
        prioLabel(p) { return ({ hoch: 'Hoch', mittel: 'Mittel', niedrig: 'Niedrig' })[p] || p; },
        nowStr() {
            const d = new Date(), p = n => (n < 10 ? '0' : '') + n;
            return d.getFullYear() + '-' + p(d.getMonth()+1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':00';
        },

        select(id) {
            this.selected = this.items.find(f => f.id === id) || null;
            this.ai = null;
            this.stepsSaved = false;
            if (this.selected && this.selected.ai_suggestion) {
                try { this.ai = JSON.parse(this.selected.ai_suggestion); } catch (e) { this.ai = null; }
            }
        },

        copyLink() {
            if (!this.selected) return;
            const url = location.origin + '/admin/feedback?id=' + this.selected.id;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(() => App.showNotification('Link kopiert', 'success'))
                    .catch(() => window.prompt('Link kopieren:', url));
            } else {
                window.prompt('Link kopieren:', url);
            }
        },

        async saveTitle() {
            if (!this.selected) return;
            try { await App.put('/admin/feedback/' + this.selected.id, { title: this.selected.title || '' }); }
            catch (e) { App.showNotification(e.message, 'error'); }
        },

        // Oberer Button: zum KI-Bereich scrollen UND die Analyse starten
        async analyzeAndScroll() {
            const el = document.querySelector('.fbc-ai');
            if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            await this.analyze();
        },

        async analyze() {
            if (!this.selected) return;
            this.analyzing = true;
            try {
                const res = await App.post('/admin/feedback/' + this.selected.id + '/analyze', {});
                this.ai = res.data || res;
                if (this.ai.next_steps_text && !this.selected.next_steps) this.selected.next_steps = this.ai.next_steps_text;
                if (this.ai.measure && this.ai.measure.title && !this.selected.title) this.selected.title = this.ai.measure.title;
                this.selected.ai_suggestion = JSON.stringify(this.ai);
                App.showNotification('Analyse fertig', 'success');
            } catch (e) {
                App.showNotification(e.message || 'Analyse fehlgeschlagen', 'error');
            } finally {
                this.analyzing = false;
            }
        },

        async saveSteps() {
            if (!this.selected) return;
            this.savingSteps = true; this.stepsSaved = false;
            try {
                await App.put('/admin/feedback/' + this.selected.id, { next_steps: this.selected.next_steps });
                this.stepsSaved = true; setTimeout(() => this.stepsSaved = false, 2500);
            } catch (e) { App.showNotification(e.message, 'error'); }
            finally { this.savingSteps = false; }
        },

        async saveNotes() {
            if (!this.selected) return;
            try { await App.put('/admin/feedback/' + this.selected.id, { admin_notes: this.selected.admin_notes }); }
            catch (e) { App.showNotification(e.message, 'error'); }
        },

        async setStatus() {
            if (!this.selected) return;
            try { await App.put('/admin/feedback/' + this.selected.id, { status: this.selected.status });
                  App.showNotification('Status geändert', 'success'); }
            catch (e) { App.showNotification(e.message, 'error'); }
        },

        async createMeasure() {
            if (!this.selected) return;
            this.creatingMeasure = true;
            try {
                // Noch nicht gespeicherte "Naechste Schritte" zuerst sichern — der Server baut die
                // Planung aus der DB in die Maßnahmen-Beschreibung ein.
                if ((this.selected.next_steps || '').trim() !== '') {
                    await App.put('/admin/feedback/' + this.selected.id, { next_steps: this.selected.next_steps });
                }
                const res = await App.post('/admin/measures/from-feedback', { feedback_id: this.selected.id });
                const m = res.data || res;
                if (!this.selected.measures) this.selected.measures = [];
                this.selected.measures.push({ id: m.id, title: m.title || this.titleOf(this.selected), status: m.status || 'offen' });
                this.selected.status = 'in_progress'; // serverseitig bereits gesetzt
                App.showNotification('Maßnahme angelegt — im Ticket dokumentiert', 'success');
            } catch (e) { App.showNotification(e.message, 'error'); }
            finally { this.creatingMeasure = false; }
        },

        measureStatusLabel(s) {
            return ({offen:'Offen', in_arbeit:'In Arbeit', erledigt:'Erledigt', verworfen:'Verworfen'})[s] || s;
        },

        async del() {
            if (!this.selected || !confirm('Feedback wirklich löschen?')) return;
            const id = this.selected.id;
            try {
                await App.delete('/admin/feedback/' + id);
                this.items = this.items.filter(f => f.id !== id);
                this.selected = null;
                App.showNotification('Gelöscht', 'success');
                const first = this.filtered[0];
                if (first) this.select(first.id);
            } catch (e) { App.showNotification(e.message, 'error'); }
        },

        typeLabel(t) { return ({bug:'Bug',feature:'Feature',improvement:'Verbesserung',other:'Sonstiges'})[t] || t; },
        statusLabel(s) { return ({new:'Offen',in_progress:'In Arbeit',resolved:'Erledigt',wont_fix:'Verworfen'})[s] || s; },
        titleOf(f) {
            if (f.title && f.title.trim()) return f.title.trim();
            const line = (f.description || '').split('\n')[0].trim();
            return line.length > 70 ? line.slice(0, 70) + '…' : (line || this.typeLabel(f.feedback_type));
        },
        shortUrl(u) { return (u || '').replace(/^https?:\/\//, '').slice(0, 40); },
        fmtDate(s) { const d = new Date((s||'').replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleDateString('de-DE', {day:'2-digit',month:'2-digit'}); },
        fmtDateTime(s) { const d = new Date((s||'').replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); },
    };
}
</script>
<script defer src="/assets/js/vendor/alpine.min.js"></script>
