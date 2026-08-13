<?php /* Wiederverwendbarer Feld-Block für Kontakte. Iteriert $felderJs (Alpine-Ausdruck) — jedes Element: [key, label, typ].
        Optional $entity (Default 'k') = Name der Alpine-Variable, die das Kontakt-Objekt enthaelt.
        Mit $entity = 'drawer.k' wird die selbe Logik im Vorschau-Drawer verwendet.

        Modi:
        - editMode == false → Plain-Text (selektierbar, kein versehentliches Bearbeiten)
        - editMode == true  → Inline-Edit-Button + Editor

        Erwartet im Alpine-Scope: editMode, istOffen(), oeffneEdit(), schliesseEdit(), speichern(), editWert,
        formatFeldwert(), formatFeldwertLese(). */ ?>
<?php $E = $entity ?? 'k'; ?>
<template x-for="f in <?= htmlspecialchars($felderJs, ENT_QUOTES) ?>" :key="f[0]">
    <div :class="['crm-field', f[2] === 'textarea' ? 'crm-field-wide' : '']">
        <dt class="crm-field-label" x-text="f[1]"></dt>
        <dd class="crm-field-wert">

            <!-- Lesemodus: schlichter, selektierbarer Text -->
            <template x-if="!editMode">
                <span :class="['crm-readonly-wert', (<?= $E ?>[f[0]] === null || <?= $E ?>[f[0]] === undefined || <?= $E ?>[f[0]] === '') ? 'is-empty' : '']"
                      x-text="formatFeldwertLese(f[0], f[2], <?= $E ?>[f[0]])"></span>
            </template>

            <!-- Editmodus: Inline-Edit-Button -->
            <template x-if="editMode && !istOffen(f[0])">
                <button type="button" class="thx-inline-edit"
                        :class="(<?= $E ?>[f[0]] === null || <?= $E ?>[f[0]] === undefined || <?= $E ?>[f[0]] === '') ? 'is-empty' : ''"
                        @click="oeffneEdit(f[0], f[2])"
                        x-text="formatFeldwert(f[0], f[2], <?= $E ?>[f[0]])"></button>
            </template>

            <!-- Editmodus: aktiver Editor -->
            <template x-if="editMode && istOffen(f[0])">
                <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" :class="f[2] === 'textarea' ? 'is-stacked' : ''">
                    <template x-if="f[2] === 'select-status'">
                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()" @change="speichern()">
                            <option value="">— leeren —</option>
                            <option value="lead">Lead</option><option value="interessent">Interessent</option>
                            <option value="kunde">Kunde</option><option value="ehemaliger_kunde">Ehemaliger Kunde</option>
                            <option value="partner">Partner</option><option value="wunschkunde">Wunschkunde</option>
                            <option value="dienstleister">Dienstleister</option><option value="sonstiges">Sonstiges</option>
                        </select>
                    </template>
                    <template x-if="f[2] === 'select-optin'">
                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()" @change="speichern()">
                            <option value="">— leeren —</option>
                            <option value="pending">Pending</option>
                            <option value="single_opted_in">Single Opt-In</option>
                            <option value="double_opted_in">Bestätigt (DOI)</option>
                            <option value="unsubscribed">Abgemeldet</option>
                            <option value="hard_bounce">Hard Bounce</option>
                            <option value="invalid">Invalid</option>
                        </select>
                    </template>
                    <template x-if="f[2] === 'textarea'">
                        <textarea class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()" rows="4"
                                  @keydown.ctrl.enter.prevent="speichern()" @keydown.meta.enter.prevent="speichern()"
                                  style="width:100%;resize:vertical;"></textarea>
                    </template>
                    <template x-if="!['select-status','select-optin','textarea'].includes(f[2])">
                        <input :type="f[2]" class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()"
                               @keydown.enter="speichern()">
                    </template>
                    <div class="thx-inline-edit-actions">
                        <span x-show="f[2] === 'textarea'" style="font-size:0.7rem;color:var(--slate-400);margin-right:auto;">Strg+Enter = speichern</span>
                        <template x-if="!['select-status','select-optin'].includes(f[2])">
                            <button type="button" class="thx-btn thx-btn-primary thx-btn-small" @click="speichern()">✓</button>
                        </template>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseEdit()">×</button>
                    </div>
                </div>
            </template>
        </dd>
    </div>
</template>
