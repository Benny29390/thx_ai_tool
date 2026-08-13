<?php /* Wiederverwendbarer Feld-Block für Firma. Erwartet $felderJs (JS-Array [[key,label,typ], ...]).
        Modi: editMode == false → Plain-Text, editMode == true → Inline-Edit.
        Im Alpine-Scope: editMode, f (Firma-Objekt), istOffen(), oeffneEdit(), schliesseEdit(),
        speichern(), editWert, formatFeldwert(), formatFeldwertLese(). */ ?>
<template x-for="feld in <?= htmlspecialchars($felderJs, ENT_QUOTES) ?>" :key="feld[0]">
    <div :class="['crm-field', feld[2] === 'textarea' ? 'crm-field-wide' : '']">
        <dt class="crm-field-label" x-text="feld[1]"></dt>
        <dd class="crm-field-wert">

            <!-- Lesemodus -->
            <template x-if="!editMode">
                <span :class="['crm-readonly-wert', (f[feld[0]] === null || f[feld[0]] === undefined || f[feld[0]] === '') ? 'is-empty' : '']"
                      x-text="formatFeldwertLese(feld[0], feld[2], f[feld[0]])"></span>
            </template>

            <!-- Editmodus: Inline-Edit-Button -->
            <template x-if="editMode && !istOffen(feld[0])">
                <button type="button" class="thx-inline-edit"
                        :class="(f[feld[0]] === null || f[feld[0]] === undefined || f[feld[0]] === '') ? 'is-empty' : ''"
                        @click="oeffneEdit(feld[0], feld[2])" x-text="formatFeldwert(feld[0], feld[2], f[feld[0]])"></button>
            </template>

            <!-- Editmodus: aktiver Editor -->
            <template x-if="editMode && istOffen(feld[0])">
                <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" :class="feld[2] === 'textarea' ? 'is-stacked' : ''">
                    <template x-if="feld[2] === 'textarea'">
                        <textarea class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()" rows="4"
                                  @keydown.ctrl.enter.prevent="speichern()" @keydown.meta.enter.prevent="speichern()" style="width:100%;resize:vertical;"></textarea>
                    </template>
                    <template x-if="feld[2] !== 'textarea'">
                        <input :type="feld[2]" class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()" @keydown.enter="speichern()">
                    </template>
                    <div class="thx-inline-edit-actions">
                        <span x-show="feld[2] === 'textarea'" style="font-size:0.7rem;color:var(--slate-400);margin-right:auto;">Strg+Enter</span>
                        <button type="button" class="thx-btn thx-btn-primary thx-btn-small" @click="speichern()">✓</button>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseEdit()">×</button>
                    </div>
                </div>
            </template>
        </dd>
    </div>
</template>
