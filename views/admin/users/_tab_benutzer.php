<?php
/**
 * Benutzer-Liste — smart: Filter, Suche, Inline-Edit, Expand-Sektion mit Aktivitaeten.
 *
 * Erwartete Variablen (vom App.php-Controller):
 *   $users (mit capabilities, customer_ids, customer_names, customer_count,
 *           chat_count, last_chat_at, knowledge_count, lam_audit_count, lam_korr_count,
 *           feedback_count, recent_chats, two_factor_enabled, invite_pending)
 *   $customers
 *   $csrfToken
 */
use Core\Auth;

// JSON fuer Alpine-State: nur die wirklich gebrauchten Felder, sonst wird's gross.
$usersJson = array_map(function ($u) {
    return [
        'id'                   => (int)$u['id'],
        'name'                 => $u['name'],
        'email'                => $u['email'],
        'abbreviation'         => $u['abbreviation'] ?? null,
        'role'                 => $u['role'],
        'is_active'            => (int)$u['is_active'],
        'pp_team_active'       => (int)($u['pp_team_active'] ?? 0),
        'last_login'           => $u['last_login'],
        'last_activity'        => $u['last_activity'] ?? null,
        'two_factor_enabled'   => (int)($u['two_factor_enabled'] ?? 0),
        'invite_pending'       => (bool)($u['invite_pending'] ?? false),
        'capabilities'         => $u['capabilities'] ?? [],
        'customer_ids'         => $u['customer_ids'] ?? [],
        'customer_names'       => $u['customer_names'] ?? [],
        'customer_count'       => (int)($u['customer_count'] ?? 0),
        'chat_count'           => (int)($u['chat_count'] ?? 0),
        'last_chat_at'         => $u['last_chat_at'],
        'knowledge_count'      => (int)($u['knowledge_count'] ?? 0),
        'lam_audit_count'      => (int)($u['lam_audit_count'] ?? 0),
        'lam_korr_count'       => (int)($u['lam_korr_count'] ?? 0),
        'feedback_count'       => (int)($u['feedback_count'] ?? 0),
        'recent_chats'         => $u['recent_chats'] ?? [],
        'asana_user_name'      => $u['asana_user_name'] ?? null,
        'asana_user_email'     => $u['asana_user_email'] ?? null,
    ];
}, $users);

// Cap-Labels zentral aus Auth::CAP_META — neue Caps tauchen automatisch auf.
$capLabels = [];
foreach (\Core\Auth::ALL_CAPS as $cap) {
    $capLabels[$cap] = \Core\Auth::CAP_META[$cap]['label'] ?? $cap;
}
$capOrder = array_keys($capLabels);
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="benutzerTabelle()" x-init="initial()">

<!-- Filter-Bar -->
<div class="lam-card" style="margin-bottom:16px;padding:14px 18px;">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <input type="text" x-model="filter.suche" class="thx-input" placeholder="Name, E-Mail, Kürzel…"
               style="flex:1;min-width:220px;max-width:340px;font-size:var(--d-fs-sm);padding:8px 12px;">

        <div style="display:flex;gap:6px;align-items:center;">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-right:2px;">Rolle</span>
            <template x-for="r in [['alle','Alle'],['admin','Admin'],['manager','Manager'],['user','User'],['guest','Guest']]" :key="r[0]">
                <button class="lam-chip" :class="filter.rolle === r[0] ? 'is-active' : ''"
                        @click="filter.rolle = r[0]" x-text="r[1]"></button>
            </template>
        </div>

        <label style="display:inline-flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);color:var(--slate-600);cursor:pointer;">
            <input type="checkbox" x-model="filter.nurAktive">
            <span>nur aktive</span>
        </label>

        <label style="display:inline-flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);color:var(--slate-600);cursor:pointer;">
            <input type="checkbox" x-model="filter.nurOffeneEinladung">
            <span>offene Einladungen</span>
        </label>

        <div style="display:flex;gap:6px;align-items:center;">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-right:2px;">Zuletzt aktiv</span>
            <template x-for="a in [['alle','Alle'],['7','≤ 7 Tage'],['30','≤ 30 Tage'],['over30','> 30 Tage'],['never','Nie']]" :key="a[0]">
                <button class="lam-chip" :class="filter.aktivitaet === a[0] ? 'is-active' : ''"
                        @click="filter.aktivitaet = a[0]" x-text="a[1]"></button>
            </template>
        </div>

        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);" x-text="gefiltert.length + ' von ' + alle.length"></span>
            <button class="lam-btn lam-btn-primary" @click="oeffneNeuerUser()">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                Neuer Benutzer
            </button>
        </div>
    </div>
</div>

<!-- Bulk-Toolbar (erscheint, wenn etwas selektiert) -->
<div class="bulk-toolbar" x-show="selected.size > 0" x-cloak>
    <div style="display:flex;gap:10px;align-items:center;">
        <span class="bulk-count">
            <strong x-text="selected.size"></strong> ausgewählt
        </span>
        <button class="lam-btn lam-btn-secondary" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="selected = new Set()">
            Auswahl aufheben
        </button>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="lam-btn lam-btn-secondary" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="bulkSetRole()">
            <span class="material-symbols-rounded" style="font-size:14px;">badge</span>
            Rolle ändern
        </button>
        <button class="lam-btn lam-btn-secondary" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="bulkAssignCustomers()">
            <span class="material-symbols-rounded" style="font-size:14px;">business</span>
            Kunden zuweisen
        </button>
        <button class="lam-btn lam-btn-accent" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="bulkResetCaps()">
            <span class="material-symbols-rounded" style="font-size:14px;">refresh</span>
            Caps auf Rolle-Default
        </button>
        <button class="lam-btn lam-btn-secondary" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="bulkAction('activate')">
            <span class="material-symbols-rounded" style="font-size:14px;">toggle_on</span>
            Aktivieren
        </button>
        <button class="lam-btn lam-btn-danger" style="padding:5px 11px;font-size:var(--d-fs-xs);" @click="bulkAction('deactivate')">
            <span class="material-symbols-rounded" style="font-size:14px;">toggle_off</span>
            Deaktivieren
        </button>
    </div>
</div>

<!-- Tabelle -->
<div class="lam-card" style="padding:0;overflow:hidden;">
    <table class="user-table">
        <thead>
            <tr>
                <th style="width:30px;text-align:center;">
                    <input type="checkbox" :checked="alleSichtbarMarkiert"
                           @change="toggleAlleSichtbaren($event.target.checked)"
                           title="Alle sichtbaren markieren">
                </th>
                <th style="width:36px;"></th>
                <th class="sortbar" @click="sortBy('name')">
                    Name <span class="sort-arrow" x-text="sort.key === 'name' ? (sort.dir === 'asc' ? '↑' : '↓') : ''"></span>
                </th>
                <th class="sortbar" @click="sortBy('role')">
                    Rolle <span class="sort-arrow" x-text="sort.key === 'role' ? (sort.dir === 'asc' ? '↑' : '↓') : ''"></span>
                </th>
                <th>Caps</th>
                <th>Kunden</th>
                <th>Status</th>
                <th title="Im Projektplanner (LEAD/TEAM) wählbar">Projektplanner</th>
                <th class="sortbar" @click="sortBy('last_activity')">
                    Zuletzt aktiv <span class="sort-arrow" x-text="sort.key === 'last_activity' ? (sort.dir === 'asc' ? '↑' : '↓') : ''"></span>
                </th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <template x-if="gefiltert.length === 0">
                <tr><td colspan="10" style="text-align:center;padding:32px;color:var(--slate-500);">Keine Treffer.</td></tr>
            </template>

            <template x-for="u in gefiltert" :key="u.id">
                <template x-if="true">
                    <tbody style="display:contents;">

                        <!-- Hauptzeile -->
                        <tr class="user-row"
                            :class="(u.is_active ? '' : 'is-inactive ') + (selected.has(u.id) ? 'is-selected' : '')"
                            @click="onRowClick($event, u.id)">

                            <!-- Selektions-Checkbox -->
                            <td class="ui-stop" style="text-align:center;">
                                <input type="checkbox" :checked="selected.has(u.id)" @change="toggleSelect(u.id)">
                            </td>

                            <!-- Expand-Pfeil -->
                            <td class="ui-stop">
                                <button class="row-expand" @click.stop="toggleExpand(u.id)"
                                        :class="expanded[u.id] ? 'is-open' : ''"
                                        :title="expanded[u.id] ? 'Zuklappen' : 'Details aufklappen'">
                                    <span class="material-symbols-rounded">chevron_right</span>
                                </button>
                            </td>

                            <!-- Name + E-Mail -->
                            <td>
                                <div class="user-name-cell">
                                    <div class="user-avatar-mini"
                                         :style="'background:' + farbeAusName(u.name)"
                                         :class="(u.abbreviation && u.abbreviation.length >= 3) ? 'is-long' : ''"
                                         x-text="u.abbreviation || initialen(u.name)"
                                         :title="u.abbreviation ? 'Kürzel: ' + u.abbreviation + ' (in Stammdaten pflegen)' : 'Kein Kürzel gesetzt — in Stammdaten pflegen'"></div>
                                    <div style="min-width:0;">
                                        <strong x-text="u.name"></strong>
                                        <span class="user-email" x-text="u.email"></span>
                                    </div>
                                    <template x-if="u.invite_pending">
                                        <span class="badge-pending" title="Einladung gesendet, aber noch nie eingeloggt">offene Einladung</span>
                                    </template>
                                </div>
                            </td>

                            <!-- Kürzel-Spalte entfernt: Kürzel zeigt jetzt der Avatar links neben dem Namen.
                                 Bearbeiten weiterhin per User-Edit (Stammdaten). -->

                            <!-- Rolle -->
                            <td>
                                <span class="role-badge" :class="'role-' + u.role" x-text="rolleLabel(u.role)"></span>
                            </td>

                            <!-- Caps Indikator -->
                            <td>
                                <span class="caps-pill" :title="u.capabilities.join(', ') || 'keine'">
                                    <span x-text="u.capabilities.length"></span>
                                    <span style="opacity:0.5;"> / 10</span>
                                </span>
                            </td>

                            <!-- Kunden -->
                            <td>
                                <span class="caps-pill" :title="u.customer_names.join(', ') || 'keine Kunden zugewiesen'">
                                    <span x-text="u.customer_count"></span>
                                </span>
                            </td>

                            <!-- Status-Toggle (inline) -->
                            <td class="ui-stop">
                                <label class="status-toggle" :title="u.is_active ? 'Aktiv — Klick deaktiviert den Account' : 'Inaktiv — Klick aktiviert den Account'">
                                    <input type="checkbox" :checked="u.is_active === 1" @change="toggleStatus(u, $event.target.checked)">
                                    <span class="status-knob"></span>
                                </label>
                            </td>

                            <!-- Projektplanner-Toggle: steuert, ob der User im LEAD/TEAM-Dropdown auftaucht. Customer nie. -->
                            <td class="ui-stop">
                                <template x-if="u.role !== 'customer'">
                                    <label class="status-toggle" :title="u.pp_team_active ? 'Im Projektplanner wählbar (LEAD/TEAM) — Klick blendet aus' : 'Nicht im Projektplanner — Klick blendet ein'">
                                        <input type="checkbox" :checked="u.pp_team_active === 1" @change="togglePpTeam(u, $event.target.checked)">
                                        <span class="status-knob"></span>
                                    </label>
                                </template>
                                <template x-if="u.role === 'customer'">
                                    <span style="color:var(--slate-300);font-size:12px;" title="Kunden erscheinen nie im Projektplanner">—</span>
                                </template>
                            </td>

                            <!-- Zuletzt aktiv (nicht Login — Sessions bleiben bestehen) -->
                            <td>
                                <span class="last-login" :class="loginAge(u.last_activity || u.last_login)" :title="'Login: ' + (u.last_login || 'nie') + ' · Aktiv: ' + (u.last_activity || 'nie')"
                                      x-text="relativZeit(u.last_activity || u.last_login)"></span>
                                <template x-if="u.two_factor_enabled">
                                    <span class="badge-2fa" title="2FA aktiv">
                                        <span class="material-symbols-rounded" style="font-size:13px;">lock</span>
                                    </span>
                                </template>
                            </td>

                            <!-- Aktionen -->
                            <td class="ui-stop">
                                <div style="display:flex;gap:4px;justify-content:flex-end;">
                                    <button class="row-action-btn" @click.stop="sichtAnsehen(u)" title="Sicht ansehen" :disabled="u.id === <?= (int)Auth::id() ?>">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <a :href="'/admin/users/' + u.id + '/edit'" class="row-action-btn" title="Bearbeiten" @click.stop>
                                        <span class="material-symbols-rounded">edit</span>
                                    </a>
                                    <button class="row-action-btn is-danger" @click.stop="loescheUser(u)" title="Löschen" :disabled="u.id === <?= (int)Auth::id() ?>">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Expand-Reihe -->
                        <template x-if="expanded[u.id]">
                            <tr class="user-detail-row">
                                <td colspan="10">
                                    <div class="user-detail-grid">

                                        <!-- Stats -->
                                        <div class="user-detail-block">
                                            <h4>Aktivität</h4>
                                            <div class="stats-grid">
                                                <div class="stat"><span class="stat-num" x-text="u.chat_count"></span><span class="stat-label">Chats</span></div>
                                                <div class="stat"><span class="stat-num" x-text="u.knowledge_count"></span><span class="stat-label">Wissens-Einträge</span></div>
                                                <div class="stat"><span class="stat-num" x-text="u.lam_audit_count"></span><span class="stat-label">LAM-Aktionen</span></div>
                                                <div class="stat"><span class="stat-num" x-text="u.lam_korr_count"></span><span class="stat-label">Korrespondenz</span></div>
                                                <div class="stat"><span class="stat-num" x-text="u.feedback_count"></span><span class="stat-label">Feedback</span></div>
                                            </div>
                                            <div x-show="u.last_chat_at" style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                                                Letzter Chat: <span x-text="relativZeit(u.last_chat_at) + ' (' + datumFormat(u.last_chat_at) + ')'"></span>
                                            </div>
                                            <div x-show="u.asana_user_name" style="margin-top:6px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                                                Asana: <strong x-text="u.asana_user_name"></strong>
                                            </div>
                                        </div>

                                        <!-- Capabilities — alle in fester Reihenfolge, ungenutzte ausgegraut -->
                                        <div class="user-detail-block">
                                            <h4>Capabilities <small style="font-weight:400;color:var(--slate-400);" x-text="'(' + u.capabilities.length + '/' + ALL_CAPS_ORDERED.length + ')'"></small></h4>
                                            <div class="chip-list">
                                                <template x-for="cap in ALL_CAPS_ORDERED" :key="cap">
                                                    <span class="info-chip"
                                                          :class="u.capabilities.includes(cap) ? 'is-on' : 'is-off'"
                                                          :title="u.capabilities.includes(cap) ? 'aktiv' : 'nicht aktiv'"
                                                          x-text="capLabel(cap)"></span>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Kunden -->
                                        <div class="user-detail-block">
                                            <h4>Zugewiesene Kunden</h4>
                                            <div class="chip-list">
                                                <template x-for="name in u.customer_names" :key="name">
                                                    <span class="info-chip" x-text="name"></span>
                                                </template>
                                                <span x-show="u.customer_count === 0" style="font-size:var(--d-fs-xs);color:var(--slate-400);">keine zugewiesen <template x-if="u.role === 'admin'"><span> (Admin sieht alle)</span></template></span>
                                            </div>
                                        </div>

                                        <!-- Letzte Chats -->
                                        <div class="user-detail-block" style="grid-column: span 2;">
                                            <h4>Letzte Chats</h4>
                                            <div x-show="u.recent_chats.length === 0" style="font-size:var(--d-fs-xs);color:var(--slate-400);">Keine Chats.</div>
                                            <div class="recent-chats" x-show="u.recent_chats.length > 0">
                                                <template x-for="chat in u.recent_chats" :key="chat.id">
                                                    <div class="recent-chat">
                                                        <span class="material-symbols-rounded" style="font-size:14px;color:var(--slate-400);">chat</span>
                                                        <span class="chat-title" x-text="chat.title || 'Unbenannt'"></span>
                                                        <span class="chat-date" x-text="datumFormat(chat.updated_at)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Aktionen -->
                                        <div class="user-detail-block" style="grid-column: 1 / -1;display:flex;gap:8px;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--slate-100);">
                                            <a :href="'/admin/users/' + u.id + '/edit'" class="lam-btn lam-btn-primary" style="padding:6px 12px;font-size:var(--d-fs-sm);">
                                                <span class="material-symbols-rounded" style="font-size:16px;">edit</span>
                                                Vollständig bearbeiten
                                            </a>
                                            <button class="lam-btn lam-btn-secondary" @click="sichtAnsehen(u)" :disabled="u.id === <?= (int)Auth::id() ?>" style="padding:6px 12px;font-size:var(--d-fs-sm);">
                                                <span class="material-symbols-rounded" style="font-size:16px;">visibility</span>
                                                Sicht ansehen
                                            </button>
                                            <template x-if="u.invite_pending">
                                                <button class="lam-btn lam-btn-accent" @click="einladungErneut(u)" style="padding:6px 12px;font-size:var(--d-fs-sm);">
                                                    <span class="material-symbols-rounded" style="font-size:16px;">mail</span>
                                                    Einladung erneut senden
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </template>
            </template>
        </tbody>
    </table>
</div>

<!-- Modal: Neuer Benutzer (schlank — Detail-Edit auf separater Seite) -->
<div class="thx-modal-backdrop" x-show="modal.offen" x-cloak @click.self="modal.offen = false">
    <div class="thx-modal" style="max-width:560px;">
        <div class="thx-modal-header">
            <h2>Neuer Benutzer</h2>
            <button @click="modal.offen = false" aria-label="Schliessen">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <form @submit.prevent="speichereNeuerUser()">
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                    <div>
                        <label class="ue-label">Name</label>
                        <input type="text" x-model="modal.name" class="thx-input" required autofocus>
                    </div>
                    <div>
                        <label class="ue-label">Kürzel <small>(max. 5)</small></label>
                        <input type="text" x-model="modal.abbreviation" class="thx-input" maxlength="5"
                               style="text-transform:uppercase;" placeholder="auto">
                    </div>
                </div>
                <div>
                    <label class="ue-label">E-Mail</label>
                    <input type="email" x-model="modal.email" class="thx-input" required>
                </div>
                <div>
                    <label class="ue-label">Rolle</label>
                    <select x-model="modal.role" class="thx-input">
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="user">User</option>
                        <option value="guest">Guest</option>
                    </select>
                    <p style="margin:4px 0 0 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
                        Beim Speichern werden die Default-Caps der Rolle gesetzt. Caps + Kunden lassen sich danach auf der Detailseite anpassen.
                    </p>
                </div>
                <label class="invite-toggle">
                    <input type="checkbox" x-model="modal.sendInvite">
                    <div>
                        <strong>Per E-Mail einladen</strong>
                        <small>User bekommt einen Link, setzt das Passwort selbst</small>
                    </div>
                </label>
                <div x-show="!modal.sendInvite">
                    <label class="ue-label">Passwort <small>(min. 8 Zeichen)</small></label>
                    <input type="password" x-model="modal.password" class="thx-input" minlength="8" :required="!modal.sendInvite">
                </div>
            </div>
            <div class="thx-modal-footer">
                <button type="button" class="lam-btn lam-btn-secondary" @click="modal.offen = false">Abbrechen</button>
                <button type="submit" class="lam-btn lam-btn-primary" :disabled="modal.laeuft" x-text="modal.laeuft ? 'Speichere…' : 'Anlegen'"></button>
            </div>
        </form>
    </div>
</div>

</div>

<style>
[x-cloak] { display: none !important; }

/* Bulk-Toolbar */
.bulk-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 16px;
    background: var(--amber-50, #fffbeb);
    border: 1px solid var(--amber-300, #fcd34d);
    border-radius: 8px;
    margin-bottom: 12px;
}
.bulk-count {
    font-size: var(--d-fs-sm);
    color: var(--amber-800, #92400e);
}
.bulk-count strong { font-size: var(--d-fs-base); }

.user-row.is-selected td { background: var(--thoxan-50, #eff6ff) !important; }

/* Filter-Bar Chips */
.lam-chip {
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--slate-100);
    color: var(--slate-700);
    font-size: var(--d-fs-xs);
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.1s;
}
.lam-chip:hover { background: var(--slate-200); }
.lam-chip.is-active { background: var(--thoxan-600); color: #fff; }

/* Tabelle */
.user-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--d-fs-sm);
}
.user-table thead th {
    background: var(--slate-50);
    border-bottom: 1px solid var(--slate-200);
    padding: 10px 12px;
    text-align: left;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--slate-600);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.user-table thead th.sortbar { cursor: pointer; user-select: none; }
.user-table thead th.sortbar:hover { background: var(--slate-100); }
.sort-arrow { color: var(--thoxan-600); margin-left: 4px; }

.user-table tbody tr.user-row {
    border-bottom: 1px solid var(--slate-100);
    cursor: pointer;
    transition: background 0.08s;
}
.user-table tbody tr.user-row:hover { background: var(--slate-50); }
.user-table tbody tr.user-row.is-inactive { opacity: 0.55; }
.user-table tbody tr.user-row.is-inactive td strong { color: var(--slate-600); }
.user-table tbody td { padding: 12px; vertical-align: middle; }
.user-table tbody td.ui-stop { cursor: default; }
.user-table tbody td.ui-stop * { cursor: auto; }

/* Name-Zelle mit Mini-Avatar */
.user-name-cell { display: flex; align-items: center; gap: 12px; min-width: 0; }
.user-avatar-mini {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    font-size: var(--d-fs-sm);
    letter-spacing: 0.5px;
    flex-shrink: 0;
}
.user-avatar-mini.is-long { font-size: var(--d-fs-xs); letter-spacing: 0; }
.user-name-cell strong {
    display: block;
    color: var(--slate-900);
    font-size: var(--d-fs-sm);
}
.user-name-cell .user-email {
    display: block;
    color: var(--slate-500);
    font-size: var(--d-fs-xs);
    margin-top: 1px;
}

/* Pending Invite Badge */
.badge-pending {
    background: #fef3c7;
    color: #92400e;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-left: auto;
    white-space: nowrap;
}

/* Inline-Edit Kürzel */
.cell-inline-edit {
    display: inline-block;
    min-width: 44px;
    padding: 3px 8px;
    border-radius: 4px;
    transition: background 0.1s;
}
.cell-inline-edit:hover { background: var(--slate-100); }
.kuerzel-val {
    font-family: 'SF Mono', Menlo, monospace;
    font-size: var(--d-fs-sm);
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--slate-700);
    text-transform: uppercase;
}
.kuerzel-val.is-empty { color: var(--slate-400); font-weight: 400; }
.kuerzel-input {
    width: 56px;
    padding: 2px 6px;
    border: 1px solid var(--thoxan-400);
    border-radius: 4px;
    font: inherit;
    font-family: 'SF Mono', Menlo, monospace;
    font-size: var(--d-fs-sm);
    font-weight: 700;
    background: var(--thoxan-50);
}

/* Rolle-Badges (uebernommen vom alten Code) */
.role-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.role-admin   { background: #fecaca; color: #991b1b; }
.role-manager { background: var(--thoxan-100); color: var(--thoxan-700); }
.role-user    { background: var(--emerald-100); color: var(--emerald-800); }
.role-guest   { background: var(--slate-200); color: var(--slate-700); }

/* Caps/Kunden Counter-Pill */
.caps-pill {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--slate-100);
    color: var(--slate-700);
    font-size: var(--d-fs-xs);
    font-weight: 600;
    cursor: help;
}

/* Status-Toggle */
.status-toggle {
    position: relative;
    display: inline-block;
    width: 34px;
    height: 18px;
    cursor: pointer;
}
.status-toggle input { display: none; }
.status-knob {
    position: absolute;
    inset: 0;
    background: var(--slate-300);
    border-radius: 999px;
    transition: background 0.15s;
}
.status-knob::before {
    content: '';
    position: absolute;
    width: 14px; height: 14px;
    background: #fff;
    border-radius: 50%;
    top: 2px; left: 2px;
    transition: transform 0.15s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
}
.status-toggle input:checked + .status-knob {
    background: var(--emerald-500);
}
.status-toggle input:checked + .status-knob::before {
    transform: translateX(16px);
}

/* Letzter Login mit Farb-Indikator */
.last-login { font-size: var(--d-fs-xs); color: var(--slate-600); }
.last-login.is-nie { color: var(--rose-600); font-weight: 600; }
.last-login.is-stale { color: var(--amber-700); }
.last-login.is-fresh { color: var(--emerald-700); }

.badge-2fa {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px; height: 18px;
    background: var(--thoxan-100);
    color: var(--thoxan-700);
    border-radius: 4px;
    margin-left: 4px;
    vertical-align: middle;
}

/* Expand-Pfeil */
.row-expand {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: var(--slate-500);
    border-radius: 4px;
    display: inline-flex;
    transition: all 0.15s;
}
.row-expand:hover { background: var(--slate-200); color: var(--slate-900); }
.row-expand .material-symbols-rounded { transition: transform 0.15s; font-size: 20px; }
.row-expand.is-open .material-symbols-rounded { transform: rotate(90deg); color: var(--thoxan-700); }

/* Aktion-Buttons in der Reihe */
.row-action-btn {
    background: none;
    border: 1px solid transparent;
    cursor: pointer;
    padding: 6px;
    color: var(--slate-600);
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.1s;
    text-decoration: none;
}
.row-action-btn:hover:not(:disabled) { background: var(--slate-100); color: var(--thoxan-700); border-color: var(--slate-200); }
.row-action-btn.is-danger:hover:not(:disabled) { color: var(--rose-600); border-color: var(--rose-200); }
.row-action-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.row-action-btn .material-symbols-rounded { font-size: 18px; }

/* Expand-Reihe / Detail-Sektion */
.user-detail-row td {
    background: var(--slate-50);
    padding: 18px 24px !important;
    border-bottom: 2px solid var(--slate-200);
}
.user-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}
.user-detail-block h4 {
    margin: 0 0 8px 0;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--slate-500);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
}
.stat {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    padding: 8px;
    text-align: center;
}
.stat-num { display: block; font-size: var(--d-fs-lg); font-weight: 700; color: var(--slate-900); }
.stat-label { display: block; font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; }

.chip-list { display: flex; flex-wrap: wrap; gap: 4px; }
.info-chip {
    background: #fff;
    border: 1px solid var(--slate-200);
    color: var(--slate-700);
    padding: 3px 9px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    transition: all 0.1s;
}
.info-chip.is-on {
    background: var(--thoxan-50);
    border-color: var(--thoxan-200);
    color: var(--thoxan-800);
    font-weight: 600;
}
.info-chip.is-off {
    background: transparent;
    border-color: var(--slate-200);
    color: var(--slate-400);
    opacity: 0.65;
    font-weight: 400;
}

.recent-chats { display: flex; flex-direction: column; gap: 4px; }
.recent-chat {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 5px 8px;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 4px;
    font-size: var(--d-fs-xs);
}
.recent-chat .chat-title { flex: 1; min-width: 0; color: var(--slate-700); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recent-chat .chat-date { color: var(--slate-500); font-size: var(--d-fs-xs); white-space: nowrap; }

@media (max-width: 1200px) {
    .user-detail-grid { grid-template-columns: 1fr 1fr; }
    .user-detail-block:has(.recent-chats) { grid-column: 1 / -1; }
}
@media (max-width: 800px) {
    .user-detail-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

/* Modal */
.thx-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15,23,42,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 16px;
}
.thx-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    box-shadow: 0 25px 60px rgba(0,0,0,0.35);
    display: flex; flex-direction: column;
    max-height: 90vh;
}
.thx-modal-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: center; justify-content: space-between;
}
.thx-modal-header h2 { margin: 0; font-size: var(--d-fs-lg); font-weight: 700; }
.thx-modal-header button {
    background: none; border: none; cursor: pointer; padding: 4px;
    color: var(--slate-500); border-radius: 6px; display:inline-flex;
}
.thx-modal-header button:hover { background: var(--slate-100); }
.thx-modal-body { padding: 22px; overflow-y: auto; }
.thx-modal-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--slate-200);
    background: var(--slate-50);
    display: flex; gap: 10px; justify-content: flex-end;
    border-radius: 0 0 12px 12px;
}

.ue-label {
    display: block;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--slate-600);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.ue-label small { font-weight: 400; color: var(--slate-400); text-transform:none; letter-spacing:0; }

.invite-toggle {
    display: flex;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.1s;
}
.invite-toggle:has(input:checked) { border-color: var(--thoxan-500); background: var(--thoxan-50); }
.invite-toggle input[type=checkbox] { margin: 2px 0 0 0; width: 18px; height: 18px; accent-color: var(--thoxan-600); }
.invite-toggle strong { display: block; font-size: var(--d-fs-sm); }
.invite-toggle small { display: block; color: var(--slate-500); font-size: var(--d-fs-xs); margin-top: 1px; }
</style>

<script>
const ALL_USERS = <?= json_encode($usersJson, JSON_UNESCAPED_UNICODE) ?>;
const CAP_LABELS = <?= json_encode($capLabels, JSON_UNESCAPED_UNICODE) ?>;
const ALL_CAPS_ORDERED = <?= json_encode($capOrder, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_LABELS = { admin: 'Admin', manager: 'Manager', user: 'User', guest: 'Guest' };

function benutzerTabelle() {
    return {
        alle: ALL_USERS,
        filter: { suche: '', rolle: 'alle', nurAktive: false, nurOffeneEinladung: false, aktivitaet: 'alle' },
        sort: { key: 'name', dir: 'asc' },
        expanded: {},
        // edit.kuerzelId entfernt (Kürzel wird im User-Edit gepflegt, nicht mehr inline)
        modal: { offen: false, name: '', email: '', abbreviation: '', role: 'user', password: '', sendInvite: false, laeuft: false },
        selected: new Set(),

        get alleSichtbarMarkiert() {
            const sichtbar = this.gefiltert;
            if (sichtbar.length === 0) return false;
            for (const u of sichtbar) if (!this.selected.has(u.id)) return false;
            return true;
        },
        toggleSelect(id) {
            if (this.selected.has(id)) this.selected.delete(id);
            else this.selected.add(id);
            this.selected = new Set(this.selected);
        },
        toggleAlleSichtbaren(checked) {
            const next = new Set(this.selected);
            for (const u of this.gefiltert) {
                if (checked) next.add(u.id);
                else next.delete(u.id);
            }
            this.selected = next;
        },

        // === Bulk-Aktionen ===
        async bulkAction(action, value = null) {
            if (this.selected.size === 0) return;
            const ids = Array.from(this.selected);
            const labels = { activate: 'aktivieren', deactivate: 'deaktivieren' };
            const verb = labels[action] || action;
            if (!confirm(`${ids.length} User ${verb}?`)) return;
            try {
                const r = await App.post('/admin/users/bulk', { ids, action, value });
                if (r.success) {
                    App.showNotification(`${r.data.affected} User aktualisiert`, 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    App.showNotification(r.message || 'Fehler', 'error');
                }
            } catch (e) {
                App.showNotification(e.message || 'Verbindungsfehler', 'error');
            }
        },
        async bulkSetRole() {
            const role = prompt('Neue Rolle: admin / manager / user / guest');
            if (!role) return;
            if (!['admin','manager','user','guest'].includes(role)) {
                App.showNotification('Ungültige Rolle', 'error');
                return;
            }
            const ids = Array.from(this.selected);
            if (!confirm(`${ids.length} User auf Rolle "${role}" setzen?\n\nCaps werden automatisch auf die Rollen-Defaults gesetzt.`)) return;
            try {
                const r = await App.post('/admin/users/bulk', { ids, action: 'set_role', value: role });
                if (r.success) {
                    App.showNotification(`${r.data.affected} User aktualisiert`, 'success');
                    setTimeout(() => location.reload(), 600);
                } else App.showNotification(r.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
        },
        async bulkResetCaps() {
            const ids = Array.from(this.selected);
            if (!confirm(`Caps von ${ids.length} User auf die Defaults ihrer Rolle zurücksetzen?\n\nIndividuelle Anpassungen gehen verloren.`)) return;
            try {
                const r = await App.post('/admin/users/bulk', { ids, action: 'reset_caps_to_defaults' });
                if (r.success) {
                    App.showNotification(`${r.data.affected} User aktualisiert`, 'success');
                    setTimeout(() => location.reload(), 600);
                } else App.showNotification(r.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
        },
        async bulkAssignCustomers() {
            const mode = prompt('Modus: set (ersetzen) / add (hinzufügen) / remove (entfernen)');
            if (!mode || !['set','add','remove'].includes(mode)) return;
            const idsStr = prompt('Kunden-IDs (Komma-getrennt, z.B. 5,12,18):');
            if (!idsStr) return;
            const cIds = idsStr.split(',').map(s => parseInt(s.trim(), 10)).filter(n => n > 0);
            if (cIds.length === 0) return;
            const ids = Array.from(this.selected);
            const verb = { set: 'setzen auf', add: 'hinzufügen', remove: 'entfernen' }[mode];
            if (!confirm(`Bei ${ids.length} User Kunden ${verb}: ${cIds.join(', ')}?`)) return;
            try {
                const r = await App.post('/admin/users/bulk', { ids, action: 'assign_customers', value: { mode, ids: cIds } });
                if (r.success) {
                    App.showNotification(`${r.data.affected} User aktualisiert`, 'success');
                    setTimeout(() => location.reload(), 600);
                } else App.showNotification(r.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
        },

        initial() {
            // URL-Hash #user-X oeffnet die Sektion automatisch
            const m = (location.hash || '').match(/^#user-(\d+)$/);
            if (m) this.expanded[parseInt(m[1], 10)] = true;
        },

        get gefiltert() {
            const q = this.filter.suche.toLowerCase().trim();
            let liste = this.alle.filter(u => {
                if (this.filter.rolle !== 'alle' && u.role !== this.filter.rolle) return false;
                if (this.filter.nurAktive && !u.is_active) return false;
                if (this.filter.nurOffeneEinladung && !u.invite_pending) return false;
                if (this.filter.aktivitaet !== 'alle') {
                    const ts = u.last_activity || u.last_login;
                    const days = ts ? (Date.now() - new Date(ts).getTime()) / 86400000 : Infinity;
                    if (this.filter.aktivitaet === '7' && !(days <= 7)) return false;
                    if (this.filter.aktivitaet === '30' && !(days <= 30)) return false;
                    if (this.filter.aktivitaet === 'over30' && !(ts && days > 30)) return false;
                    if (this.filter.aktivitaet === 'never' && ts) return false;
                }
                if (q) {
                    const hay = (u.name + ' ' + u.email + ' ' + (u.abbreviation || '')).toLowerCase();
                    if (!hay.includes(q)) return false;
                }
                return true;
            });
            const key = this.sort.key;
            const dir = this.sort.dir === 'asc' ? 1 : -1;
            liste.sort((a, b) => {
                let av = a[key], bv = b[key];
                if (key === 'last_login' || key === 'last_activity') {
                    // 'Zuletzt aktiv' nutzt denselben Fallback wie die Anzeige (last_activity || last_login).
                    const ea = key === 'last_activity' ? (a.last_activity || a.last_login) : av;
                    const eb = key === 'last_activity' ? (b.last_activity || b.last_login) : bv;
                    av = ea ? new Date(ea).getTime() : 0;
                    bv = eb ? new Date(eb).getTime() : 0;
                } else if (key === 'role') {
                    const order = { admin: 1, manager: 2, user: 3, guest: 4 };
                    av = order[av] || 99; bv = order[bv] || 99;
                } else {
                    av = (av || '').toString().toLowerCase();
                    bv = (bv || '').toString().toLowerCase();
                }
                return av < bv ? -dir : av > bv ? dir : 0;
            });
            return liste;
        },

        sortBy(key) {
            if (this.sort.key === key) {
                this.sort.dir = this.sort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort.key = key;
                this.sort.dir = (key === 'last_login' || key === 'last_activity') ? 'desc' : 'asc';
            }
        },

        toggleExpand(id) {
            if (this.expanded[id]) { delete this.expanded[id]; }
            else { this.expanded[id] = true; }
        },

        onRowClick(e, id) {
            // Wenn der Klick in einer ui-stop-Zelle war, nichts tun.
            if (e.target.closest('.ui-stop')) return;
            // Sonst: Expand toggeln
            this.toggleExpand(id);
        },

        initialen(name) {
            const teile = (name || '').trim().split(/\s+/);
            const a = (teile[0] || '?').charAt(0);
            const b = teile.length > 1 ? teile[teile.length - 1].charAt(0) : (teile[0] || '').charAt(1) || '';
            return (a + b).toUpperCase();
        },
        farbeAusName(name) {
            // Deterministisches Pastell-Farbschema
            const palette = ['#0f766e', '#4338ca', '#9333ea', '#c2410c', '#0369a1', '#7c2d12', '#166534', '#9f1239', '#4d7c0f', '#0e7490'];
            let h = 0;
            for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) | 0;
            return palette[Math.abs(h) % palette.length];
        },
        rolleLabel(role) { return ROLE_LABELS[role] || role; },
        capLabel(cap) { return CAP_LABELS[cap] || cap; },

        relativZeit(iso) {
            if (!iso) return 'nie';
            const t = new Date(iso).getTime();
            const diff = Date.now() - t;
            const sek = Math.floor(diff / 1000);
            if (sek < 60) return 'gerade eben';
            const min = Math.floor(sek / 60);
            if (min < 60) return min + ' Min';
            const std = Math.floor(min / 60);
            if (std < 24) return std + ' Std';
            const tag = Math.floor(std / 24);
            if (tag < 7) return 'vor ' + tag + ' Tag' + (tag === 1 ? '' : 'en');
            if (tag < 30) return 'vor ' + Math.floor(tag / 7) + ' Wo';
            if (tag < 365) return 'vor ' + Math.floor(tag / 30) + ' Mon';
            return 'vor ' + Math.floor(tag / 365) + ' J';
        },
        loginAge(iso) {
            if (!iso) return 'is-nie';
            const diff = Date.now() - new Date(iso).getTime();
            const tage = diff / (1000 * 60 * 60 * 24);
            if (tage > 60) return 'is-stale';
            if (tage < 7) return 'is-fresh';
            return '';
        },
        datumFormat(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        },

        // (startKuerzelEdit / speichereKuerzel entfernt — Kürzel-Pflege jetzt in /admin/users/{id}/edit → Stammdaten)

        // === Status-Toggle ===
        async toggleStatus(u, neuAktiv) {
            try {
                const resp = await App.request('PUT', '/admin/users/' + u.id, { is_active: neuAktiv ? 1 : 0 });
                if (resp.success) {
                    u.is_active = neuAktiv ? 1 : 0;
                    App.showNotification(neuAktiv ? 'Account aktiviert' : 'Account deaktiviert', 'success');
                } else {
                    App.showNotification(resp.message || 'Fehler', 'error');
                }
            } catch (e) {
                App.showNotification(e.message || 'Verbindungsfehler', 'error');
            }
        },

        // === Projektplanner-Toggle (LEAD/TEAM-Dropdown) ===
        async togglePpTeam(u, an) {
            try {
                const resp = await App.request('PUT', '/admin/users/' + u.id, { pp_team_active: an ? 1 : 0 });
                if (resp.success) {
                    u.pp_team_active = an ? 1 : 0;
                    App.showNotification(an ? u.name + ' im Projektplanner wählbar' : u.name + ' aus Projektplanner ausgeblendet', 'success');
                } else {
                    App.showNotification(resp.message || 'Fehler', 'error');
                    u.pp_team_active = an ? 0 : 1; // zurückdrehen
                }
            } catch (e) {
                App.showNotification(e.message || 'Verbindungsfehler', 'error');
            }
        },

        // === Aktionen ===
        async sichtAnsehen(u) {
            if (!confirm('In die Sicht von "' + u.name + '" wechseln?')) return;
            try {
                const r = await App.post('/auth/login-as', { user_id: u.id });
                if (r.success) {
                    setTimeout(() => location.href = '/', 200);
                } else {
                    App.showNotification(r.message || 'Wechsel fehlgeschlagen', 'error');
                }
            } catch (e) {
                App.showNotification(e.message || 'Wechsel fehlgeschlagen', 'error');
            }
        },
        async loescheUser(u) {
            // Schritt 1: Wenn User noch aktiv ist → erst deaktivieren anbieten
            if (u.is_active) {
                const wantDeactivate = confirm(
                    'Vorsicht — endgültiges Löschen ist nicht reversibel.\n\n' +
                    '"' + u.name + '" ist noch aktiv. Bitte erst deaktivieren ' +
                    '(Daten bleiben dabei erhalten, der User kann sich aber nicht mehr einloggen).\n\n' +
                    'Jetzt deaktivieren?'
                );
                if (!wantDeactivate) return;
                try {
                    await App.request('PUT', '/admin/users/' + u.id, { is_active: 0 });
                    u.is_active = 0;
                    App.showNotification('User deaktiviert. Zum endgültigen Löschen erneut auf den Mülleimer klicken.', 'info');
                } catch (e) {
                    App.showNotification(e.message || 'Fehler beim Deaktivieren', 'error');
                }
                return;
            }

            // Schritt 2: User ist inaktiv → endgültige Löschung mit E-Mail-Bestätigung
            const typed = prompt(
                'Endgültiges Löschen: gib zur Bestätigung die E-Mail-Adresse des Users ein.\n\n' +
                'Bei Eingabe von "' + u.email + '" wird der User samt Caps- und Kundenzuordnungen gelöscht. ' +
                'Chats, Wissens-Einträge und Audit-Spuren bleiben anonymisiert erhalten (user_id wird auf NULL gesetzt).'
            );
            if (!typed) return;
            if (typed.trim().toLowerCase() !== u.email.toLowerCase()) {
                App.showNotification('E-Mail stimmt nicht — Löschen abgebrochen.', 'error');
                return;
            }
            try {
                await App.request('DELETE', '/admin/users/' + u.id, { confirm_email: u.email });
                App.showNotification('Benutzer gelöscht', 'success');
                this.alle = this.alle.filter(x => x.id !== u.id);
            } catch (e) {
                App.showNotification(e.message || 'Fehler', 'error');
            }
        },
        async einladungErneut(u) {
            App.showNotification('Erneutes Senden steht noch aus — bitte erst manuell anlegen oder den Admin anfragen.', 'info');
        },

        // === Neuer User Modal ===
        oeffneNeuerUser() {
            this.modal = { offen: true, name: '', email: '', abbreviation: '', role: 'user', password: '', sendInvite: false, laeuft: false };
        },
        async speichereNeuerUser() {
            this.modal.laeuft = true;
            try {
                const payload = {
                    name: this.modal.name.trim(),
                    email: this.modal.email.trim(),
                    abbreviation: this.modal.abbreviation.trim().toUpperCase(),
                    role: this.modal.role,
                };
                if (this.modal.sendInvite) {
                    payload.send_invite = 1;
                } else {
                    payload.password = this.modal.password;
                }
                const resp = await App.post('/admin/users', payload);
                if (resp.success) {
                    App.showNotification('Benutzer angelegt', 'success');
                    const newId = resp.data && resp.data.id;
                    if (newId) {
                        setTimeout(() => location.href = '/admin/users/' + newId + '/edit', 300);
                    } else {
                        location.reload();
                    }
                } else {
                    App.showNotification(resp.message || 'Fehler beim Anlegen', 'error');
                }
            } catch (e) {
                App.showNotification(e.message || 'Verbindungsfehler', 'error');
            } finally {
                this.modal.laeuft = false;
            }
        },
    };
}
</script>
