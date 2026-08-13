<script setup>
import LamLayout from '@/Layouts/LamLayout.vue';
import SucheCombobox from '@/Components/SucheCombobox.vue';
import LinkquelleBearbeitenModal from '@/Components/LinkquelleBearbeitenModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    domains: Object,
    filter: Object,
    filterOptionen: Object,
    sistrix: Object,
});

// Default-Werte für die Pool-Filter (siehe LinkquellenController::VERIFIKATION_DEFAULT).
const verifikationDefault = ['neu', 'in_arbeit', 'geprueft', 'veraltet'];

const lokalerFilter = reactive({
    ...props.filter,
    verifikation: Array.isArray(props.filter.verifikation) ? [...props.filter.verifikation] : [...verifikationDefault],
    tag_ids: Array.isArray(props.filter.tag_ids) ? [...props.filter.tag_ids] : [],
    kunde_kuerzel: Array.isArray(props.filter.kunde_kuerzel) ? [...props.filter.kunde_kuerzel] : [],
    ohne_kunden: !!props.filter.ohne_kunden,
    nur_loesch_kandidaten: !!props.filter.nur_loesch_kandidaten,
    nur_ungesichtet: !!props.filter.nur_ungesichtet,
    nur_ohne_si: !!props.filter.nur_ohne_si,
    nur_ohne_dp: !!props.filter.nur_ohne_dp,
    herkunft: props.filter.herkunft ?? 'alle',
    linkart: Array.isArray(props.filter.linkart) ? [...props.filter.linkart] : [],
    ohne_linkart: !!props.filter.ohne_linkart,
    pro_seite: props.filter.pro_seite ?? 25,
});

// "Weitere Filter"-Bereich offen halten und in localStorage persistieren —
// auch ueber Seitenwechsel hinweg. Wenn die URL bereits Filter aus diesem
// Bereich enthaelt, wird der Bereich automatisch geoeffnet.
const STORAGE_KEY_WEITERE_OFFEN = 'lam_linkquellen_weitere_filter_offen';
const initialWeitereOffen = () => {
    if (typeof window === 'undefined') return false;
    const gespeichert = window.localStorage?.getItem(STORAGE_KEY_WEITERE_OFFEN);
    if (gespeichert !== null) return gespeichert === '1';
    // Default: oeffnen, wenn ein "weiterer" Filter bereits aktiv ist
    const f = props.filter ?? {};
    return Boolean(
        f.anbieter_unbekannt || f.nur_mit_si || f.nur_ohne_si || f.nur_ohne_dp || f.nur_loesch_kandidaten || f.nur_ungesichtet ||
        (f.tag_ids?.length) || f.si_min || f.si_max ||
        f.dp_min || f.dp_max ||
        f.preis_min || f.preis_max || f.letzter_check_aelter_als ||
        (f.herkunft && f.herkunft !== 'alle'),
    );
};
const weitereFilterOffen = ref(initialWeitereOffen());

watch(weitereFilterOffen, (offen) => {
    if (typeof window === 'undefined') return;
    window.localStorage?.setItem(STORAGE_KEY_WEITERE_OFFEN, offen ? '1' : '0');
});

const STORAGE_KEY_FILTER = 'lam_linkquellen_filter';

let entprellTimer = null;
watch(lokalerFilter, (neu) => {
    clearTimeout(entprellTimer);
    entprellTimer = setTimeout(() => {
        // letzte Filter merken — Tom-Wunsch: beim Wiederkommen restorieren
        if (typeof window !== 'undefined') {
            window.localStorage?.setItem(STORAGE_KEY_FILTER, JSON.stringify(neu));
        }
        router.get(route('linkquellen.index'), { ...neu }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
}, { deep: true });

// Beim ersten Mount mit leerer URL die gespeicherten Filter laden.
// Object.assign triggert den lokalerFilter-watcher, der den router.get
// mit den restorierten Werten ausloest.
onMounted(() => {
    if (typeof window === 'undefined') return;
    const queryString = window.location.search.replace(/^\?/, '');
    if (queryString !== '') return; // URL hat Filter, Vorrang
    const gespeichert = window.localStorage?.getItem(STORAGE_KEY_FILTER);
    if (! gespeichert) return;
    try {
        const filter = JSON.parse(gespeichert);
        if (filter && typeof filter === 'object' && Object.keys(filter).length) {
            Object.assign(lokalerFilter, filter);
        }
    } catch {}
});

const filterZuruecksetzen = () => {
    lokalerFilter.suche = '';
    lokalerFilter.verifikation = [...verifikationDefault];
    lokalerFilter.anbieter_id = '';
    lokalerFilter.anbieter_unbekannt = false;
    lokalerFilter.via_anbieter_id = '';
    lokalerFilter.tag_ids = [];
    lokalerFilter.kunde_kuerzel = [];
    lokalerFilter.ohne_kunden = false;
    lokalerFilter.si_min = '';
    lokalerFilter.si_max = '';
    lokalerFilter.nur_mit_si = false;
    lokalerFilter.preis_min = '';
    lokalerFilter.preis_max = '';
    lokalerFilter.letzter_check_aelter_als = '';
    lokalerFilter.nur_loesch_kandidaten = false;
    lokalerFilter.nur_ungesichtet = false;
    lokalerFilter.nur_ohne_si = false;
    lokalerFilter.dp_min = '';
    lokalerFilter.dp_max = '';
    lokalerFilter.nur_ohne_dp = false;
    lokalerFilter.herkunft = 'alle';
    lokalerFilter.linkart = [];
    lokalerFilter.ohne_linkart = false;
    lokalerFilter.sortierung = 'si_desc';
    if (typeof window !== 'undefined') {
        window.localStorage?.removeItem(STORAGE_KEY_FILTER);
    }
};

const statusFarbe = (s) => ({
    neu: 'bg-amber-100 text-amber-800',
    in_arbeit: 'bg-blue-100 text-blue-800',
    geprueft: 'bg-emerald-100 text-emerald-800',
    veraltet: 'bg-orange-100 text-orange-800',
    geloescht: 'bg-slate-200 text-slate-700',
}[s] ?? 'bg-slate-100 text-slate-700');

// Briefing 01b: lesbare Anzeige der DB-Werte. DB bleibt ASCII-snake_case,
// UI zeigt "Neu / In Arbeit / Geprüft / Veraltet / Gelöscht".
const statusLabel = (s) => ({
    neu: 'Neu',
    in_arbeit: 'In Arbeit',
    geprueft: 'Geprüft',
    veraltet: 'Veraltet',
    geloescht: 'Gelöscht',
}[s] ?? s);

const verifikationChipKlasse = (s, ist_aktiv) => {
    const farben = {
        neu: 'bg-amber-100 text-amber-800 ring-amber-300',
        in_arbeit: 'bg-blue-100 text-blue-800 ring-blue-300',
        geprueft: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
        veraltet: 'bg-orange-100 text-orange-800 ring-orange-300',
        geloescht: 'bg-slate-200 text-slate-700 ring-slate-400',
    };
    if (ist_aktiv) {
        return (farben[s] ?? 'bg-slate-100 text-slate-700 ring-slate-300') + ' ring-2';
    }
    return 'bg-white text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50';
};

// Filter-Chips: Klick exklusiv (nur dieser), Shift/Ctrl/Cmd toggelt
// additiv. Wenn beim Klick die einzige aktive Auswahl auf den geklickten
// Chip faellt, wird alles deselektiert (= Filter aus).
const chipKlick = (event, liste, wert) => {
    if (event.shiftKey || event.ctrlKey || event.metaKey) {
        const idx = liste.indexOf(wert);
        if (idx === -1) liste.push(wert);
        else liste.splice(idx, 1);
        return;
    }
    // Einzel-Klick: nur dieser, oder zurueck auf "alle"
    if (liste.length === 1 && liste[0] === wert) {
        liste.splice(0, liste.length);
    } else {
        liste.splice(0, liste.length, wert);
    }
};

const verifikationToggle = (status, event) => chipKlick(event ?? {}, lokalerFilter.verifikation, status);
const tagToggleFilter = (id, event) => chipKlick(event ?? {}, lokalerFilter.tag_ids, id);
const linkartToggleFilter = (wert, event) => chipKlick(event ?? {}, lokalerFilter.linkart, wert);
const linkartAlleAbwaehlen = () => { lokalerFilter.linkart.splice(0, lokalerFilter.linkart.length); };

const verifikationAlleZuruecksetzen = () => {
    lokalerFilter.verifikation.splice(0, lokalerFilter.verifikation.length, ...verifikationDefault);
};
const tagsAlleAbwaehlen = () => {
    lokalerFilter.tag_ids.splice(0, lokalerFilter.tag_ids.length);
};

const kundeFilterToggle = (kuerzel, event) => chipKlick(event ?? {}, lokalerFilter.kunde_kuerzel, kuerzel);
const kundenFilterAbwaehlen = () => {
    lokalerFilter.kunde_kuerzel.splice(0, lokalerFilter.kunde_kuerzel.length);
};

const siAlterFarbe = (klasse) => ({
    frisch: 'text-slate-400',
    grau: 'text-slate-400',
    orange: 'text-orange-600',
}[klasse] ?? 'text-slate-400');

const siHinweis = (klasse) => ({
    grau: 'Sistrix-Wert älter als 6 Monate.',
    orange: 'Sistrix-Wert älter als 12 Monate. Bitte aktualisieren.',
}[klasse] ?? null);

const aelterLabel = {
    '1m': '1 Monat',
    '3m': '3 Monate',
    '6m': '6 Monate',
    '12m': '12 Monate',
};

const spaltenSortierung = {
    url: { asc: 'url_asc', desc: 'url_desc', default: 'asc' },
    anbieter: { asc: 'anbieter_asc', desc: 'anbieter_desc', default: 'asc' },
    cluster: { asc: 'cluster_asc', desc: 'cluster_desc', default: 'asc' },
    si: { asc: 'si_asc', desc: 'si_desc', default: 'desc' },
    preis: { asc: 'preis_asc', desc: 'preis_desc', default: 'asc' },
    letzter_check: { asc: 'letzter_check_asc', desc: 'letzter_check_desc', default: 'asc' },
    kunden: { asc: 'kunden_asc', desc: 'kunden_desc', default: 'asc' },
};

const eurFormat = (wert) => wert === null || wert === undefined
    ? null
    : new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(wert);

const aktiveSortierSpalte = computed(() => {
    for (const [spalte, optionen] of Object.entries(spaltenSortierung)) {
        if (lokalerFilter.sortierung === optionen.asc) return { spalte, richtung: 'asc' };
        if (lokalerFilter.sortierung === optionen.desc) return { spalte, richtung: 'desc' };
    }
    return { spalte: null, richtung: null };
});

const sortierenNach = (spalte) => {
    const optionen = spaltenSortierung[spalte];
    if (!optionen) return;

    const aktiv = aktiveSortierSpalte.value;
    if (aktiv.spalte === spalte) {
        lokalerFilter.sortierung = aktiv.richtung === 'asc' ? optionen.desc : optionen.asc;
    } else {
        lokalerFilter.sortierung = optionen.default === 'asc' ? optionen.asc : optionen.desc;
    }
};

const sortierIndikator = (spalte) => {
    const aktiv = aktiveSortierSpalte.value;
    if (aktiv.spalte !== spalte) return '';
    return aktiv.richtung === 'asc' ? '↑' : '↓';
};

const aktiveFilterAnzahl = computed(() => {
    let n = 0;
    if (props.filter.suche) n++;
    const v = props.filter.verifikation ?? [];
    if (v.length !== verifikationDefault.length || verifikationDefault.some((x) => !v.includes(x))) n++;
    if (props.filter.anbieter_id) n++;
    if (props.filter.anbieter_unbekannt) n++;
    if ((props.filter.tag_ids ?? []).length) n++;
    if ((props.filter.kunde_kuerzel ?? []).length) n++;
    if (props.filter.ohne_kunden) n++;
    if (props.filter.si_min || props.filter.si_max || props.filter.nur_mit_si) n++;
    if (props.filter.nur_ohne_si) n++;
    if (props.filter.dp_min || props.filter.dp_max) n++;
    if (props.filter.nur_ohne_dp) n++;
    if (props.filter.preis_min || props.filter.preis_max) n++;
    if (props.filter.letzter_check_aelter_als) n++;
    if (props.filter.nur_loesch_kandidaten) n++;
    if (props.filter.nur_ungesichtet) n++;
    if (props.filter.herkunft && props.filter.herkunft !== 'alle') n++;
    if ((props.filter.linkart ?? []).length) n++;
    if (props.filter.ohne_linkart) n++;
    return n;
});

const weitereFilterAktiv = computed(() => {
    let n = 0;
    if (props.filter.anbieter_unbekannt) n++;
    if ((props.filter.tag_ids ?? []).length) n++;
    if (props.filter.si_min || props.filter.si_max || props.filter.nur_mit_si) n++;
    if (props.filter.nur_ohne_si) n++;
    if (props.filter.dp_min || props.filter.dp_max) n++;
    if (props.filter.nur_ohne_dp) n++;
    if (props.filter.preis_min || props.filter.preis_max) n++;
    if (props.filter.letzter_check_aelter_als) n++;
    if (props.filter.nur_loesch_kandidaten) n++;
    if (props.filter.nur_ungesichtet) n++;
    if (props.filter.herkunft && props.filter.herkunft !== 'alle') n++;
    return n;
});

// ─────────────────────────────────────────────────────────────────────────
// Inline-Edit
//
// Pro Zeile darf immer nur ein Feld gleichzeitig bearbeitet werden. Der
// State editierZelle hält { id, feld } und liefert die "öffne"-Logik für
// jedes Edit-Popover. Klick außerhalb oder Speichern schließt es.
// ─────────────────────────────────────────────────────────────────────────
const editierZelle = ref({ id: null, feld: null });
const editAnbieter = reactive({ id: '', name: '' });
const editTagsAuswahl = ref([]); // lokal pro Eintrag, beim Öffnen befüllt
const editTagsNeuName = ref('');
const editPreis = reactive({ preis: '', buchungstyp: 'gastartikel', via_anbieter_id: '', inkl_text: false });
const editSistrixManuell = reactive({ si: '', dp: '', domain_alter: '' });
const sistrixManuellOffen = ref(false);
const verwerfenGrund = ref('');
const editKundenAuswahl = ref([]); // Array von Kuerzel-Strings
const laeuft = ref(null); // 'sistrix:{id}' o.ä., für Disable-State

const istOffen = (id, feld) => editierZelle.value.id === id && editierZelle.value.feld === feld;

const oeffneEdit = (d, feld) => {
    editierZelle.value = { id: d.id, feld };
    // pro Feld die Initial-Werte aus der aktuellen Karte spiegeln
    if (feld === 'anbieter') {
        editAnbieter.id = '';
        editAnbieter.name = '';
    } else if (feld === 'tags') {
        editTagsAuswahl.value = d.tags.map((t) => t.name);
        editTagsNeuName.value = '';
    } else if (feld === 'preis') {
        editPreis.preis = '';
        editPreis.buchungstyp = 'gastartikel';
        editPreis.via_anbieter_id = '';
        editPreis.inkl_text = false;
    } else if (feld === 'si') {
        editSistrixManuell.si = '';
        editSistrixManuell.dp = '';
        editSistrixManuell.domain_alter = '';
        sistrixManuellOffen.value = false;
    } else if (feld === 'status') {
        verwerfenGrund.value = '';
    } else if (feld === 'kunden') {
        editKundenAuswahl.value = (d.kunden ?? []).map((k) => k.kuerzel);
    }
};

const schliesseEdit = () => {
    editierZelle.value = { id: null, feld: null };
};

// Globale Tastatur: ESC schließt das Popover
const onKeydown = (e) => {
    if (e.key === 'Escape') schliesseEdit();
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// ── Status-Aktionen ──────────────────────────────────────────────────────
const statusSetzen = (d, routeName, extra = {}) => {
    laeuft.value = `${routeName}:${d.id}`;
    router.post(route(routeName, d.id), extra, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

const verwerfenAbsenden = (d) => {
    statusSetzen(d, 'linkquellen.verwerfen', { grund: verwerfenGrund.value || null });
};

// ── Anbieter inline speichern ───────────────────────────────────────────
const anbieterSpeichern = (d) => {
    const payload = editAnbieter.id
        ? { anbieter_id: editAnbieter.id }
        : { name: editAnbieter.name.trim() };

    if (!payload.anbieter_id && !payload.name) {
        // beides leer → Anbieter loesen
    }

    laeuft.value = `anbieter:${d.id}`;
    router.post(route('linkquellen.inline.anbieter', d.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

const anbieterEntfernen = (d) => {
    laeuft.value = `anbieter:${d.id}`;
    router.post(route('linkquellen.inline.anbieter', d.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

// ── Tags inline an/aus ──────────────────────────────────────────────────
const tagAnschalten = (d, tag) => {
    router.post(route('linkquellen.inline.tag', d.id), {
        tag_id: tag.id,
        aktion: 'an',
    }, { preserveScroll: true, preserveState: true });
};

const tagAusschalten = (d, tag) => {
    router.post(route('linkquellen.inline.tag', d.id), {
        tag_id: tag.id,
        aktion: 'ab',
    }, { preserveScroll: true, preserveState: true });
};

const tagNeuAnlegen = (d) => {
    const name = editTagsNeuName.value.trim();
    if (!name) return;

    router.post(route('linkquellen.inline.tag', d.id), {
        name,
        aktion: 'an',
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editTagsNeuName.value = ''; },
    });
};

const istTagDran = (d, tagId) => d.tags.some((t) => t.id === tagId);

// ── SI / DP refresh ─────────────────────────────────────────────────────
// Sistrix-Credits sparen: Teil-Abrufe statt immer "alles". Kosten je Methode:
// SI 1, Alter 10, DP 25, Alles 36.
const sistrixCrawlen = (d, teil = 'alles') => {
    laeuft.value = `sistrix:${d.id}:${teil}`;
    router.post(route('linkquellen.sistrix-pruefen', d.id), { teile: teil }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

const sistrixLaeuft = (d, teil) => laeuft.value === `sistrix:${d.id}:${teil}`;

const sistrixManuellSpeichern = (d) => {
    laeuft.value = `sistrix:${d.id}`;
    router.post(route('linkquellen.kennzahlen-manuell', d.id), {
        si: editSistrixManuell.si || null,
        dp: editSistrixManuell.dp || null,
        domain_alter: editSistrixManuell.domain_alter || null,
        erfasst_am: new Date().toISOString().slice(0, 10),
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

// ── Preis inline anlegen ────────────────────────────────────────────────
const preisSpeichern = (d) => {
    if (!editPreis.preis) return;

    laeuft.value = `preis:${d.id}`;
    router.post(route('linkquellen.inline.preis', d.id), {
        preis: editPreis.preis,
        buchungstyp: editPreis.buchungstyp,
        via_anbieter_id: editPreis.via_anbieter_id || null,
        inkl_text: editPreis.inkl_text,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

// ── Kunden-Zuordnung inline ─────────────────────────────────────────────
const kundeToggle = (kuerzel) => {
    const i = editKundenAuswahl.value.indexOf(kuerzel);
    if (i === -1) editKundenAuswahl.value.push(kuerzel);
    else editKundenAuswahl.value.splice(i, 1);
};

const kundenSpeichern = (d) => {
    laeuft.value = `kunden:${d.id}`;
    router.post(route('linkquellen.inline.kunden', d.id), {
        kuerzel: [...editKundenAuswahl.value],
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => schliesseEdit(),
        onFinish: () => { laeuft.value = null; },
    });
};

// Sistrix-Kontingent in der UI: aus filterOptionen oder aus dem Detail
const sistrixKonfiguriert = computed(() => (props.filterOptionen.vermittler ?? null) !== null);

// ────────────────────────────────────────────────────────────────────────
// Mehrfach-Auswahl + Bulk-Aktionen (Briefing 02 Block B)
//
// selectedIds als Set fuer O(1)-Lookup. Shift+Klick selektiert die Range
// zwischen letzter Anker-Zeile und aktueller Zeile (analog Excel/Gmail).
// Die Master-Checkbox im Header bezieht sich nur auf die aktuell sichtbare
// Seite, nicht alle Treffer ueber Pagination hinweg — sonst Risiko, dass
// Massenaktionen ungewollt tausende Datensaetze treffen.
// ────────────────────────────────────────────────────────────────────────
const selectedIds = ref(new Set());
const letzterAnkerIndex = ref(null);

const istSelektiert = (id) => selectedIds.value.has(id);

const anzahlSelektiert = computed(() => selectedIds.value.size);

const auswahlAufheben = () => {
    selectedIds.value = new Set();
    letzterAnkerIndex.value = null;
};

const alleSichtbarenSelektiert = computed(() => {
    const sichtbar = props.domains?.data ?? [];
    if (!sichtbar.length) return false;
    return sichtbar.every((d) => selectedIds.value.has(d.id));
});

const masterUmschalten = () => {
    const sichtbar = props.domains?.data ?? [];
    if (alleSichtbarenSelektiert.value) {
        sichtbar.forEach((d) => selectedIds.value.delete(d.id));
    } else {
        sichtbar.forEach((d) => selectedIds.value.add(d.id));
    }
    // Reaktivitaet anstoßen — Set-Mutation triggert sonst nicht
    selectedIds.value = new Set(selectedIds.value);
};

const zeileUmschalten = (event, index, d) => {
    if (event.shiftKey && letzterAnkerIndex.value !== null) {
        const sichtbar = props.domains?.data ?? [];
        const von = Math.min(letzterAnkerIndex.value, index);
        const bis = Math.max(letzterAnkerIndex.value, index);
        const anschalten = ! selectedIds.value.has(d.id);
        for (let i = von; i <= bis; i++) {
            const idAnIndex = sichtbar[i]?.id;
            if (! idAnIndex) continue;
            if (anschalten) selectedIds.value.add(idAnIndex);
            else selectedIds.value.delete(idAnIndex);
        }
    } else if (selectedIds.value.has(d.id)) {
        selectedIds.value.delete(d.id);
    } else {
        selectedIds.value.add(d.id);
    }
    selectedIds.value = new Set(selectedIds.value);
    letzterAnkerIndex.value = index;
};

// Bulk-Dialoge
const bulkTagsOffen = ref(false);
const bulkTagsModus = ref('anhaengen');
const bulkTagsAuswahl = ref([]);

const bulkStatusOffen = ref(false);
const bulkStatusAuswahl = ref('geprueft'); // DB-Wert, UI zeigt "Geprüft"

const bulkVerwerfenOffen = ref(false);
const bulkVerwerfenGrund = ref('');

const bulkKundenOffen = ref(false);
const bulkKundenModus = ref('anhaengen');
const bulkKundenAuswahl = ref([]);

const bulkLinkoptionenOffen = ref(false);
const bulkLinkoptionenModus = ref('bestehend'); // 'bestehend' | 'neu'
const bulkLinkoptionenListen = ref([]);
const bulkLinkoptionenLadeListen = ref(false);
const bulkLinkoptionenAuswahl = reactive({
    vorschlagsliste_id: '',
    kuerzel: '',
    name: '',
    zielzahl: '',
});

const bulkSistrixOffen = ref(false);
const bulkSistrixTeil = ref('si'); // 'si' | 'alter' | 'dp' | 'alles'

const bulkAktionLaeuft = ref(false);

const SISTRIX_CREDITS = { si: 1, alter: 10, dp: 25, alles: 36 };
const SISTRIX_LABEL = { si: 'SI', alter: 'Alter', dp: 'DP', alles: 'Alles (SI+Alter+DP)' };

const bulkSistrixOeffnen = (teil) => {
    bulkSistrixTeil.value = teil;
    bulkSistrixOffen.value = true;
};

const bulkSistrixKostenGesamt = computed(() =>
    SISTRIX_CREDITS[bulkSistrixTeil.value] * selectedIds.value.size,
);

const bulkSistrixBudgetReicht = computed(() => {
    const status = props.sistrix?.wochenstatus;
    if (! status) return true;
    return bulkSistrixKostenGesamt.value <= status.credits_verbleibend;
});

// Verwerfen-Modal: bei aktivem Loesch-Kandidaten-Filter den Grund
// vorbefuellen — Tom-Workflow "filtern + bulk-verwerfen ohne tippen".
const bulkVerwerfenOeffnen = () => {
    if (lokalerFilter.nur_loesch_kandidaten && bulkVerwerfenGrund.value.trim() === '') {
        bulkVerwerfenGrund.value = 'Auto-Tot: HTTP nicht erreichbar + Sistrix SI 0,0000';
    }
    bulkVerwerfenOffen.value = true;
};

// ── Chunked Bulk-Loop mit Fortschritts-Modal ──────────────────────────
// Tom-Wunsch: 500er-Bulks dauern bis zu mehreren Minuten, der User
// will sehen, wo er steht, und abbrechen koennen. Wir zerlegen die
// Auswahl in 25er-Chunks, posten sie per axios (JSON-Antwort) und
// updaten ein Modal pro Chunk. Bei "abbrechen" laeuft der gerade
// gestartete Chunk noch zu Ende, weitere werden uebersprungen.
const fortschritt = reactive({
    offen: false,
    label: '',
    total: 0,
    done: 0,
    erfolge: 0,
    fehler: [],
    abbrechen: false,
    fertig: false,
    extra: null, // optional: Text fuer Credits oder Sonstiges
});

const bulkInChunks = async (routeName, payload, label, chunkSize = 25) => {
    if (selectedIds.value.size === 0) return;
    const ids = [...selectedIds.value];

    Object.assign(fortschritt, {
        offen: true, label, total: ids.length, done: 0,
        erfolge: 0, fehler: [], abbrechen: false, fertig: false, extra: null,
    });

    let creditsVerbraucht = 0;
    for (let i = 0; i < ids.length; i += chunkSize) {
        if (fortschritt.abbrechen) break;
        const chunk = ids.slice(i, i + chunkSize);
        try {
            const res = await window.axios.post(route(routeName), {
                domain_ids: chunk,
                ...payload,
            }, { headers: { Accept: 'application/json' } });

            const data = res.data ?? {};
            fortschritt.erfolge += data.erfolge ?? data.erreichbar ?? chunk.length;
            if (Array.isArray(data.fehler) && data.fehler.length) {
                fortschritt.fehler.push(...data.fehler);
            }
            if (typeof data.credits_pro_domain === 'number') {
                creditsVerbraucht += (data.erfolge ?? 0) * data.credits_pro_domain;
                fortschritt.extra = `${creditsVerbraucht.toLocaleString('de-DE')} Credits verbraucht`;
            }
            if (data.abgebrochen) {
                fortschritt.fehler.push('Sistrix-Wochenkontingent aufgebraucht, restliche Chunks übersprungen.');
                fortschritt.done = Math.min(i + chunk.length, ids.length);
                break;
            }
        } catch (e) {
            fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${e.message || 'Netzwerkfehler'}`);
        }
        fortschritt.done = Math.min(i + chunkSize, ids.length);
    }

    fortschritt.fertig = true;
    router.reload({ only: ['domains', 'sistrix'] });
};

const fortschrittSchliessen = () => {
    fortschritt.offen = false;
    if (! fortschritt.abbrechen) auswahlAufheben();
};

const bulkSistrixAnwenden = () => {
    bulkSistrixOffen.value = false;
    // Sistrix-Chunks bewusst klein (10 statt 25): bei "alles" sind das 3
    // API-Calls je Domain, ein 25er-Chunk koennte > 2 Minuten dauern und
    // PHP-Standard-Timeout kippen (auch wenn set_time_limit(0) jetzt
    // gesetzt ist, halten wir kuerzere Chunks zur Sicherheit).
    bulkInChunks(
        'linkquellen.bulk.sistrix',
        { teile: bulkSistrixTeil.value },
        `Sistrix abrufen: ${SISTRIX_LABEL[bulkSistrixTeil.value]}`,
        10,
    );
};

// Bulk-Erreichbarkeit (HTTP HEAD, kostet keine API-Credits).
const bulkLinkartAusLinkprofilAnwenden = () => {
    bulkInChunks(
        'linkquellen.bulk.linkart-aus-linkprofil',
        {},
        'Linkart aus Linkprofil übernehmen',
        50,
    );
};

const bulkErreichbarkeitAnwenden = () => {
    bulkInChunks(
        'linkquellen.bulk.erreichbarkeit',
        {},
        'Erreichbarkeit prüfen',
    );
};

const bulkLinkoptionenOeffnen = async () => {
    bulkLinkoptionenOffen.value = true;
    bulkLinkoptionenModus.value = 'bestehend';
    bulkLinkoptionenAuswahl.vorschlagsliste_id = '';
    bulkLinkoptionenAuswahl.kuerzel = '';
    bulkLinkoptionenAuswahl.name = '';
    bulkLinkoptionenAuswahl.zielzahl = '';
    bulkLinkoptionenLadeListen.value = true;
    try {
        const res = await window.axios.get(route('linkquellen.linkoptionen-auswahl'));
        bulkLinkoptionenListen.value = res.data?.listen ?? [];
        // Wenn nichts vorhanden ist, gleich auf "neu" springen
        if (! bulkLinkoptionenListen.value.length) {
            bulkLinkoptionenModus.value = 'neu';
        }
    } catch (e) {
        bulkLinkoptionenListen.value = [];
        bulkLinkoptionenModus.value = 'neu';
    } finally {
        bulkLinkoptionenLadeListen.value = false;
    }
};

const bulkLinkoptionenAnwenden = () => {
    if (selectedIds.value.size === 0) return;
    const payload = {
        domain_ids: [...selectedIds.value],
        modus: bulkLinkoptionenModus.value,
    };
    if (bulkLinkoptionenModus.value === 'bestehend') {
        if (! bulkLinkoptionenAuswahl.vorschlagsliste_id) return;
        payload.vorschlagsliste_id = bulkLinkoptionenAuswahl.vorschlagsliste_id;
    } else {
        if (! bulkLinkoptionenAuswahl.kuerzel || ! bulkLinkoptionenAuswahl.name.trim()) return;
        payload.kuerzel = bulkLinkoptionenAuswahl.kuerzel;
        payload.name = bulkLinkoptionenAuswahl.name.trim();
        if (bulkLinkoptionenAuswahl.zielzahl) {
            payload.zielzahl = parseInt(bulkLinkoptionenAuswahl.zielzahl, 10);
        }
    }
    bulkAktionLaeuft.value = true;
    router.post(route('linkquellen.bulk.linkoptionen'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            bulkLinkoptionenOffen.value = false;
            auswahlAufheben();
        },
        onFinish: () => { bulkAktionLaeuft.value = false; },
    });
};

const bulkTagsAnwenden = () => {
    if (bulkTagsAuswahl.value.length === 0 || selectedIds.value.size === 0) return;
    bulkAktionLaeuft.value = true;
    router.post(route('linkquellen.bulk.tags'), {
        domain_ids: [...selectedIds.value],
        tag_ids: bulkTagsAuswahl.value,
        modus: bulkTagsModus.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkTagsOffen.value = false;
            bulkTagsAuswahl.value = [];
            auswahlAufheben();
        },
        onFinish: () => { bulkAktionLaeuft.value = false; },
    });
};

const bulkStatusAnwenden = () => {
    if (! bulkStatusAuswahl.value || selectedIds.value.size === 0) return;
    bulkAktionLaeuft.value = true;
    router.post(route('linkquellen.bulk.status'), {
        domain_ids: [...selectedIds.value],
        status: bulkStatusAuswahl.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkStatusOffen.value = false;
            auswahlAufheben();
        },
        onFinish: () => { bulkAktionLaeuft.value = false; },
    });
};

const bulkVerwerfenAnwenden = () => {
    if (bulkVerwerfenGrund.value.trim().length < 3 || selectedIds.value.size === 0) return;
    bulkAktionLaeuft.value = true;
    router.post(route('linkquellen.bulk.verwerfen'), {
        domain_ids: [...selectedIds.value],
        grund: bulkVerwerfenGrund.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkVerwerfenOffen.value = false;
            bulkVerwerfenGrund.value = '';
            auswahlAufheben();
        },
        onFinish: () => { bulkAktionLaeuft.value = false; },
    });
};

const bulkKundenAnwenden = () => {
    if (bulkKundenAuswahl.value.length === 0 || selectedIds.value.size === 0) return;
    bulkAktionLaeuft.value = true;
    router.post(route('linkquellen.bulk.kunden'), {
        domain_ids: [...selectedIds.value],
        kuerzel: bulkKundenAuswahl.value,
        modus: bulkKundenModus.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkKundenOffen.value = false;
            bulkKundenAuswahl.value = [];
            auswahlAufheben();
        },
        onFinish: () => { bulkAktionLaeuft.value = false; },
    });
};

// ────────────────────────────────────────────────────────────────────────
// Rechtsklick-Kontextmenü
//
// Statt eines aufwendigen Floating-Layers nutzen wir absolute Position
// relativ zum viewport. Outside-Click und ESC schließen das Menü. Touch-
// Geräte kennen kein contextmenu-Event — die Bulk-Bar bleibt der primäre
// Weg für diese User.
// ────────────────────────────────────────────────────────────────────────
const kontextmenu = ref({ offen: false, x: 0, y: 0, domain: null });
const kontextmenuStatusOffen = ref(false);

const oeffneKontextmenu = (event, d) => {
    event.preventDefault();
    kontextmenu.value = {
        offen: true,
        x: event.clientX,
        y: event.clientY,
        domain: d,
    };
    kontextmenuStatusOffen.value = false;
};

const schliesseKontextmenu = () => {
    kontextmenu.value.offen = false;
    kontextmenuStatusOffen.value = false;
};

const kontextmenuStatusSetzen = (status) => {
    const d = kontextmenu.value.domain;
    if (! d) return;
    router.post(route('linkquellen.bulk.status'), {
        domain_ids: [d.id],
        status,
    }, {
        preserveScroll: true,
        onFinish: schliesseKontextmenu,
    });
};

const kontextmenuVerwerfen = () => {
    const d = kontextmenu.value.domain;
    if (! d) return;
    const grund = prompt('Grund für das Verwerfen?');
    if (! grund || grund.trim().length < 3) {
        schliesseKontextmenu();
        return;
    }
    router.post(route('linkquellen.verwerfen', d.id), {
        grund: grund.trim(),
    }, {
        preserveScroll: true,
        onFinish: schliesseKontextmenu,
    });
};

const kontextmenuLoeschen = () => {
    const d = kontextmenu.value.domain;
    if (! d) return;
    if (! confirm(`Domain „${d.url}" wirklich löschen?`)) {
        schliesseKontextmenu();
        return;
    }
    router.delete(route('linkquellen.destroy', d.id), {
        preserveScroll: true,
        onFinish: schliesseKontextmenu,
    });
};

const onGlobalKeydown = (e) => {
    if (e.key === 'Escape') {
        if (kontextmenu.value.offen) schliesseKontextmenu();
        if (bulkTagsOffen.value) bulkTagsOffen.value = false;
        if (bulkStatusOffen.value) bulkStatusOffen.value = false;
        if (bulkVerwerfenOffen.value) bulkVerwerfenOffen.value = false;
        if (bulkLinkoptionenOffen.value) bulkLinkoptionenOffen.value = false;
        if (bulkKundenOffen.value) bulkKundenOffen.value = false;
        if (bulkSistrixOffen.value) bulkSistrixOffen.value = false;
        if (modalDomainId.value) modalDomainId.value = null;
    }
};
onMounted(() => document.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onGlobalKeydown));

// Modal: Inline-Bearbeitung statt Page-Sprung
const modalDomainId = ref(null);

const oeffneModal = (d) => {
    modalDomainId.value = d.id;
    schliesseKontextmenu();
};
const schliesseModal = () => {
    modalDomainId.value = null;
};
</script>

<template>
    <Head title="Linkquellen" />

    <LamLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-800">Linkquellen</h1>
                    <div class="mt-1 text-sm text-slate-500">
                        {{ domains.total }} Domain{{ domains.total === 1 ? '' : 's' }}
                        <span v-if="aktiveFilterAnzahl" class="ml-2 text-xs text-slate-400">
                            ({{ aktiveFilterAnzahl }} Filter aktiv)
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('tags.index')"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        title="Cluster und Tags pflegen"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                        </svg>
                        Tags
                    </Link>
                    <Link
                        :href="route('import.index')"
                        class="inline-flex items-center gap-1.5 rounded border border-thoxan-300 bg-white px-4 py-2 text-sm font-medium text-thoxan-700 hover:bg-thoxan-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        Linklisten-Import
                    </Link>
                    <Link :href="route('linkquellen.create')" class="rounded bg-thoxan-600 px-4 py-2 text-sm font-bold text-white hover:bg-thoxan-700">
                        + Neue Domain
                    </Link>
                </div>
            </div>
        </template>

        <!-- Filter. Einheitliche Groesse pro Element: Inputs/Selects/Combobox
             `lam-filter-input` (px-3 py-2 text-sm), Chips `lam-filter-chip`
             (px-2.5 py-1 text-xs), Labels `lam-filter-label` (text-xs).
             Tooltips/Hinweise bewusst kleiner (text-[10px]). -->
        <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Filter</h2>
                <div class="flex items-center gap-2">
                    <button
                        @click="weitereFilterOffen = !weitereFilterOffen"
                        class="flex items-center gap-1.5 rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <span>{{ weitereFilterOffen ? '▾' : '▸' }} Weitere Filter</span>
                        <span v-if="weitereFilterAktiv > 0" class="rounded-full bg-thoxan-100 px-1.5 py-0.5 text-[10px] font-medium text-thoxan-700">{{ weitereFilterAktiv }}</span>
                    </button>
                    <button v-if="aktiveFilterAnzahl" @click="filterZuruecksetzen" class="rounded border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700">zurücksetzen</button>
                </div>
            </div>
            <!-- ZEILE 1: Schnellfilter (Suche, Kunde, Anbieter) -->
            <div class="mt-3 grid grid-cols-12 gap-3">
                <div class="col-span-12 md:col-span-3">
                    <label class="lam-filter-label">Volltext URL / Notizen</label>
                    <input v-model="lokalerFilter.suche" type="text" class="lam-filter-input mt-1" placeholder="z.B. energie" />
                </div>
                <div class="col-span-12 md:col-span-6">
                    <label class="lam-filter-label flex items-baseline gap-1.5">
                        Kunden
                        <span class="text-[10px] font-normal text-slate-400">Klick = nur dieser · Shift/Ctrl = mehrere</span>
                    </label>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <button
                            v-if="lokalerFilter.kunde_kuerzel.length"
                            type="button"
                            @click="kundenFilterAbwaehlen"
                            class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50"
                            title="Alle Kunden-Filter aus"
                        >alle</button>
                        <button
                            type="button"
                            @click="lokalerFilter.ohne_kunden = !lokalerFilter.ohne_kunden"
                            title="Nur Quellen ohne jegliche Kunden-Zuweisung"
                            :class="['lam-filter-chip font-medium transition', lokalerFilter.ohne_kunden ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100']"
                        >ohne</button>
                        <button
                            v-for="k in filterOptionen.kunden"
                            :key="k.kuerzel"
                            type="button"
                            @click="kundeFilterToggle(k.kuerzel, $event)"
                            :title="k.name"
                            :class="['lam-filter-chip font-medium transition', lokalerFilter.kunde_kuerzel.includes(k.kuerzel) ? 'bg-thoxan-600 text-white' : 'bg-thoxan-50 text-thoxan-700 ring-1 ring-thoxan-200 hover:bg-thoxan-100']"
                        >{{ k.kuerzel }}</button>
                        <span v-if="!filterOptionen.kunden?.length" class="text-xs text-slate-400">Noch keine Kunden.</span>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="lam-filter-label">Anbieter</label>
                    <SucheCombobox
                        v-model="lokalerFilter.anbieter_id"
                        :optionen="filterOptionen.anbieter"
                        option-key="id"
                        option-label="name"
                        placeholder="Suchen…"
                        leerer-text="Alle"
                        :disabled="lokalerFilter.anbieter_unbekannt"
                        class="mt-1"
                    />
                </div>
            </div>

            <!-- ZEILE 2: Verifikation (Chip-Reihe) -->
            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Verifikation
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur dieser · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1">
                    <button
                        type="button"
                        @click="verifikationAlleZuruecksetzen"
                        class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50"
                        title="Alle Standard-Stati"
                    >alle</button>
                    <button
                        v-for="v in filterOptionen.verifikation"
                        :key="v"
                        type="button"
                        @click="verifikationToggle(v, $event)"
                        :class="['lam-filter-chip font-medium transition', verifikationChipKlasse(v, lokalerFilter.verifikation.includes(v))]"
                    >{{ statusLabel(v) }}</button>
                </div>
            </div>

            <!-- ZEILE 3: Linkart (Chip-Reihe) — gehoert zum Schnellfilter, weil
                 Tom haeufig nach Linkart drillt. Tags bleiben im Weitere-Bereich. -->
            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Linkart (aus Linkprofil-Klassifikation)
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur diese · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <button
                        v-if="lokalerFilter.linkart.length"
                        type="button"
                        @click="linkartAlleAbwaehlen"
                        class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50"
                        title="Alle Linkarten abwählen"
                    >alle</button>
                    <button
                        type="button"
                        @click="lokalerFilter.ohne_linkart = !lokalerFilter.ohne_linkart"
                        :class="['lam-filter-chip transition', lokalerFilter.ohne_linkart ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100']"
                        title="Domains ohne Linkart-Klassifikation"
                    >ohne</button>
                    <button
                        v-for="w in filterOptionen.linkarten ?? []"
                        :key="w"
                        type="button"
                        @click="linkartToggleFilter(w, $event)"
                        :class="['lam-filter-chip transition', lokalerFilter.linkart.includes(w) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                    >{{ (filterOptionen.linkart_labels ?? {})[w] ?? w }}</button>
                </div>
            </div>

            <div v-if="weitereFilterOffen" class="mt-3 border-t border-slate-100 pt-3">
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label">Sistrix-Bereich (SI)</label>
                        <div class="mt-1 grid grid-cols-2 gap-1.5">
                            <input v-model="lokalerFilter.si_min" type="number" step="0.01" min="0" placeholder="min" class="lam-filter-input" />
                            <input v-model="lokalerFilter.si_max" type="number" step="0.01" min="0" placeholder="max" class="lam-filter-input" />
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label" title="Verlinkende Domains (Domain Popularity) laut Sistrix">DP-Bereich (verlinkende Domains)</label>
                        <div class="mt-1 grid grid-cols-2 gap-1.5">
                            <input v-model="lokalerFilter.dp_min" type="number" step="1" min="0" placeholder="min" class="lam-filter-input" />
                            <input v-model="lokalerFilter.dp_max" type="number" step="1" min="0" placeholder="max" class="lam-filter-input" />
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label">Preis-Bereich (EUR)</label>
                        <div class="mt-1 grid grid-cols-2 gap-1.5">
                            <input v-model="lokalerFilter.preis_min" type="number" step="0.01" min="0" placeholder="min" class="lam-filter-input" />
                            <input v-model="lokalerFilter.preis_max" type="number" step="0.01" min="0" placeholder="max" class="lam-filter-input" />
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label">Letzter Check älter als</label>
                        <select v-model="lokalerFilter.letzter_check_aelter_als" class="lam-filter-input mt-1 bg-white">
                            <option value="">Egal</option>
                            <option v-for="k in filterOptionen.letzter_check_optionen" :key="k" :value="k">{{ aelterLabel[k] ?? k }}</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label" title="Pool-Bestand = alter Bestand vor dem Linkprofil-Modul. Linkprofil-Analyse = automatisch oder manuell aus dem Linkprofil ueberommen.">Herkunft</label>
                        <select v-model="lokalerFilter.herkunft" class="lam-filter-input mt-1 bg-white">
                            <option value="alle">alle</option>
                            <option value="pool">nur Pool-Bestand (ohne Marker)</option>
                            <option value="linkprofil_analyse">nur aus Linkprofil-Analyse</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label">Zusätzliche Optionen</label>
                        <div class="mt-1 flex flex-col gap-1.5">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" v-model="lokalerFilter.anbieter_unbekannt" class="h-4 w-4 rounded border-slate-300" />
                                <span>Nur Domains ohne Anbieter</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" v-model="lokalerFilter.nur_mit_si" class="h-4 w-4 rounded border-slate-300" />
                                <span>Nur Domains mit aktuellem SI</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700" title="Domains, die noch nie per Sistrix geprueft wurden">
                                <input type="checkbox" v-model="lokalerFilter.nur_ohne_si" class="h-4 w-4 rounded border-slate-300" />
                                <span>Nur Domains ohne SI</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700" title="Domains, die noch nie per Sistrix-DP-Abfrage geprueft wurden">
                                <input type="checkbox" v-model="lokalerFilter.nur_ohne_dp" class="h-4 w-4 rounded border-slate-300" />
                                <span>Nur Domains ohne DP</span>
                            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700" title="Status 'Neu' und noch keine Konditionen erfasst — typisch fuer frisch aus Linkprofil-Analyse uebernommene Domains">
                                <input type="checkbox" v-model="lokalerFilter.nur_ungesichtet" class="h-4 w-4 rounded border-slate-300" />
                                <span class="font-medium text-amber-700">Nur noch ungesichtete</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700" title="Nicht erreichbar UND ohne verwertbaren SI UND noch in der Pruefung (Neu/In Arbeit/Veraltet)">
                                <input type="checkbox" v-model="lokalerFilter.nur_loesch_kandidaten" class="h-4 w-4 rounded border-slate-300" />
                                <span class="font-medium text-red-700">Nur Lösch-Kandidaten</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="lam-filter-label flex items-baseline gap-1.5">
                        Cluster / Tags
                        <span class="text-[10px] font-normal text-slate-400">Klick = nur dieser · Shift/Ctrl = mehrere</span>
                    </label>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <button
                            v-if="lokalerFilter.tag_ids.length"
                            type="button"
                            @click="tagsAlleAbwaehlen"
                            class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50"
                            title="Alle Tags abwählen"
                        >alle</button>
                        <button
                            v-for="t in filterOptionen.tags"
                            :key="t.id"
                            type="button"
                            @click="tagToggleFilter(t.id, $event)"
                            :class="['lam-filter-chip transition', lokalerFilter.tag_ids.includes(t.id) ? 'bg-thoxan-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >{{ t.name }}</button>
                        <span v-if="!filterOptionen.tags.length" class="text-xs text-slate-400">Noch keine Tags angelegt.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk-Aktionsleiste (Briefing 02 Block B) -->
        <div v-if="anzahlSelektiert > 0" class="sticky top-0 z-30 mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-thoxan-300 bg-thoxan-50 px-4 py-2 shadow-sm">
            <span class="text-sm font-medium text-thoxan-900">{{ anzahlSelektiert }} ausgewählt</span>
            <span class="h-4 w-px bg-thoxan-300"></span>
            <button @click="bulkTagsOffen = true" class="rounded bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Tags zuweisen…</button>
            <button @click="bulkKundenOffen = true" class="rounded bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Kunden zuweisen…</button>
            <button @click="bulkStatusOffen = true" class="rounded bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Status setzen…</button>
            <button @click="bulkLinkoptionenOeffnen" class="rounded bg-white px-3 py-1 text-xs font-medium text-thoxan-700 ring-1 ring-thoxan-300 hover:bg-thoxan-50">In Linkoptionen aufnehmen…</button>
            <span class="h-4 w-px bg-thoxan-300"></span>
            <button @click="bulkErreichbarkeitAnwenden" :disabled="fortschritt.offen" class="rounded bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-50" title="HTTP HEAD-Check pro Linkquelle, kostet keine API-Credits">Erreichbarkeit prüfen</button>
            <button @click="bulkLinkartAusLinkprofilAnwenden" :disabled="fortschritt.offen" class="rounded bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-50" title="Setzt die Linkart auf den Wert aus der zugehoerigen Verlinkung im Linkprofil. Nur leere Felder werden gefuellt.">Linkart aus Linkprofil</button>
            <template v-if="sistrix?.konfiguriert">
                <span class="h-4 w-px bg-thoxan-300"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider text-thoxan-700">Sistrix</span>
                <button @click="bulkSistrixOeffnen('si')" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50" title="Sichtbarkeitsindex je Domain: 1 Credit">SI · 1</button>
                <button @click="bulkSistrixOeffnen('alter')" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50" title="Sichtbar-seit je Domain: 10 Credits">Alter · 10</button>
                <button @click="bulkSistrixOeffnen('dp')" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50" title="Verlinkende Domains je Domain: 25 Credits">DP · 25</button>
                <button @click="bulkSistrixOeffnen('alles')" class="rounded bg-thoxan-50 px-2.5 py-1 text-xs font-medium text-thoxan-700 ring-1 ring-thoxan-300 hover:bg-thoxan-100" title="Alles in einem Rutsch je Domain: 36 Credits">Alles · 36</button>
            </template>
            <span class="h-4 w-px bg-thoxan-300"></span>
            <button @click="bulkVerwerfenOeffnen" class="rounded bg-white px-3 py-1 text-xs font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50">Verwerfen mit Grund…</button>
            <span class="flex-1"></span>
            <button @click="auswahlAufheben" class="text-xs text-slate-500 hover:text-slate-800">Auswahl aufheben</button>
        </div>

        <!-- Tabelle. Inline-Edit pro Zelle: Klick auf Edit-Affordance ersetzt
             Display-Inhalt durch Mini-Form, ESC oder Außerhalb-Klick schließt.
             Pro Zeile darf immer nur ein Feld editiert werden (editierZelle). -->
        <div class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
            <table v-if="domains.data.length" class="min-w-full divide-y divide-slate-200">
                <thead class="sticky top-0 z-20 bg-slate-50 shadow-sm">
                    <tr>
                        <th class="w-10 px-3 py-3">
                            <input
                                type="checkbox"
                                :checked="alleSichtbarenSelektiert"
                                @change="masterUmschalten"
                                class="rounded border-slate-300"
                                title="Alle sichtbaren auswählen"
                            />
                        </th>
                        <th @click="sortierenNach('url')" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">URL <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('url') }}</span></th>
                        <th @click="sortierenNach('anbieter')" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">Anbieter <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('anbieter') }}</span></th>
                        <th @click="sortierenNach('cluster')" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">Tags <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('cluster') }}</span></th>
                        <th @click="sortierenNach('si')" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">SI / DP <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('si') }}</span></th>
                        <th @click="sortierenNach('preis')" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">Preis <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('preis') }}</span></th>
                        <th @click="sortierenNach('letzter_check')" class="w-32 cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">Status <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('letzter_check') }}</span></th>
                        <th @click="sortierenNach('kunden')" class="w-40 cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-800">Kunden <span class="ml-0.5 text-thoxan-600">{{ sortierIndikator('kunden') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr
                        v-for="(d, idx) in domains.data"
                        :key="d.id"
                        :class="['align-top', istSelektiert(d.id) ? 'bg-thoxan-50' : 'hover:bg-slate-50']"
                        @contextmenu="oeffneKontextmenu($event, d)"
                    >
                        <!-- Auswahl-Checkbox -->
                        <td class="w-10 px-3 py-3 align-top">
                            <input
                                type="checkbox"
                                :checked="istSelektiert(d.id)"
                                @click.stop="zeileUmschalten($event, idx, d)"
                                class="rounded border-slate-300"
                            />
                        </td>

                        <!-- URL -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 text-sm">
                                <Link :href="route('linkquellen.show', d.id)" class="font-bold text-slate-800 hover:underline">{{ d.url }}</Link>
                                <a :href="`https://${d.url}`" target="_blank" rel="noopener noreferrer" :title="`${d.url} extern öffnen`" class="text-slate-400 hover:text-slate-700" @click.stop>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="inline h-3.5 w-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    </svg>
                                </a>
                                <Link
                                    v-if="d.notizen_vorhanden"
                                    :href="route('linkquellen.show', d.id) + '#notizen'"
                                    :title="d.notizen_anfang"
                                    class="text-slate-400 hover:text-slate-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </Link>
                            </div>
                            <div class="mt-0.5 flex flex-wrap gap-1">
                                <span v-if="d.ist_ungesichtet" class="inline-block rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">noch ungesichtet</span>
                                <span
                                    v-if="d.linkart"
                                    :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium ring-1', d.linkart === 'spam' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-slate-100 text-slate-700 ring-slate-200']"
                                    :title="`Linkart: ${(filterOptionen.linkart_labels ?? {})[d.linkart] ?? d.linkart}`"
                                >{{ (filterOptionen.linkart_labels ?? {})[d.linkart] ?? d.linkart }}</span>
                                <span
                                    v-if="d.herkunft === 'linkprofil_analyse'"
                                    class="inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200"
                                    :title="d.herkunft_kunde_kuerzel ? `Aus Linkprofil-Analyse Kunde ${d.herkunft_kunde_kuerzel}` : 'Aus Linkprofil-Analyse'"
                                >LP{{ d.herkunft_kunde_kuerzel ? ' · ' + d.herkunft_kunde_kuerzel : '' }}</span>
                            </div>
                        </td>

                        <!-- Anbieter inline -->
                        <td class="relative px-4 py-3">
                            <div v-if="!istOffen(d.id, 'anbieter')">
                                <button
                                    @click="oeffneEdit(d, 'anbieter')"
                                    class="group block w-full text-left"
                                >
                                    <span v-if="d.anbieter_name" class="text-sm text-slate-800 group-hover:text-thoxan-700">{{ d.anbieter_name }}</span>
                                    <span v-else class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 group-hover:bg-thoxan-50 group-hover:text-thoxan-700">+ Anbieter zuweisen</span>
                                    <div v-if="d.anbieter_name && (d.anbieter_firma || d.anbieter_quelle === 'vermittler')" class="text-xs text-slate-500">
                                        <span v-if="d.anbieter_firma">{{ d.anbieter_firma }}</span>
                                        <span v-if="d.anbieter_quelle === 'vermittler'" :class="d.anbieter_firma ? 'ml-1' : ''">via Vermittler</span>
                                    </div>
                                </button>
                            </div>
                            <div v-else class="lam-popover">
                                <div class="lam-popover-label">Anbieter</div>
                                <SucheCombobox
                                    v-model="editAnbieter.id"
                                    :optionen="filterOptionen.anbieter"
                                    option-key="id"
                                    option-label="name"
                                    placeholder="Anbieter suchen…"
                                    leerer-text="— wählen oder neu anlegen —"
                                />
                                <input
                                    v-model="editAnbieter.name"
                                    type="text"
                                    placeholder="oder neuen Namen tippen"
                                    class="lam-input"
                                    @keydown.enter="anbieterSpeichern(d)"
                                />
                                <div class="lam-popover-actions">
                                    <button @click="anbieterSpeichern(d)" :disabled="laeuft === `anbieter:${d.id}`" class="lam-btn-primary">Speichern</button>
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Abbrechen</button>
                                    <button v-if="d.anbieter_name" @click="anbieterEntfernen(d)" class="lam-btn-destructive ml-auto">Entfernen</button>
                                </div>
                            </div>
                        </td>

                        <!-- Tags inline -->
                        <td class="relative px-4 py-3">
                            <div v-if="!istOffen(d.id, 'tags')">
                                <button @click="oeffneEdit(d, 'tags')" class="group block w-full text-left">
                                    <span v-for="t in d.tags" :key="t.name" :class="['mr-1 inline-block rounded px-1.5 py-0.5 text-xs', t.primaer ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700']">{{ t.name }}</span>
                                    <span v-if="!d.tags.length" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 group-hover:bg-thoxan-50 group-hover:text-thoxan-700">+ Tag hinzufügen</span>
                                </button>
                            </div>
                            <div v-else class="lam-popover">
                                <div class="lam-popover-label">Tags</div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="t in filterOptionen.tags"
                                        :key="t.id"
                                        type="button"
                                        @click="istTagDran(d, t.id) ? tagAusschalten(d, t) : tagAnschalten(d, t)"
                                        :class="['rounded px-2 py-0.5 text-xs transition', istTagDran(d, t.id) ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50']"
                                    >{{ t.name }}</button>
                                </div>
                                <div class="flex gap-1">
                                    <input
                                        v-model="editTagsNeuName"
                                        type="text"
                                        placeholder="Neuer Tag…"
                                        class="lam-input flex-1"
                                        @keydown.enter="tagNeuAnlegen(d)"
                                    />
                                    <button @click="tagNeuAnlegen(d)" class="lam-btn-primary">+ Neu</button>
                                </div>
                                <div class="lam-popover-actions">
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Fertig</button>
                                </div>
                            </div>
                        </td>

                        <!-- SI / DP inline -->
                        <td class="relative px-4 py-3 font-mono">
                            <div v-if="!istOffen(d.id, 'si')">
                                <button @click="oeffneEdit(d, 'si')" class="group block w-full text-left" :title="siHinweis(d.si_alter_klasse) ?? `Erfasst ${d.snapshot_erfasst_am} (${d.snapshot_quelle})`">
                                    <div v-if="d.si !== null || d.dp !== null" class="text-sm text-slate-800 group-hover:text-thoxan-700">
                                        <span v-if="d.si !== null" class="font-semibold">{{ Number(d.si).toFixed(4) }}</span>
                                        <span v-else class="text-slate-400">—</span>
                                        <span v-if="d.dp !== null" class="ml-1 font-semibold text-slate-700">/ {{ Number(d.dp).toLocaleString('de-DE') }}</span>
                                    </div>
                                    <div v-else class="text-sm text-slate-500 group-hover:text-thoxan-700">+ Sistrix</div>
                                    <div v-if="d.si !== null || d.dp !== null" :class="['text-xs', siAlterFarbe(d.si_alter_klasse)]">
                                        <span v-if="d.sichtbar_seit">seit {{ d.sichtbar_seit }}</span>
                                        <span v-else>{{ d.snapshot_erfasst_am }}</span>
                                        <span v-if="d.si_alter_klasse === 'orange'" class="ml-1">⚠</span>
                                    </div>
                                </button>
                            </div>
                            <div v-else class="lam-popover text-left font-sans">
                                <div class="lam-popover-label">Sichtbarkeit · Credits sparen mit Teil-Abruf</div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        @click="sistrixCrawlen(d, 'si')"
                                        :disabled="sistrixLaeuft(d, 'si')"
                                        class="lam-btn-secondary-bordered justify-center"
                                        title="Sichtbarkeitsindex holen (1 Credit)"
                                    >{{ sistrixLaeuft(d, 'si') ? 'SI…' : 'SI · 1' }}</button>
                                    <button
                                        @click="sistrixCrawlen(d, 'alter')"
                                        :disabled="sistrixLaeuft(d, 'alter')"
                                        class="lam-btn-secondary-bordered justify-center"
                                        title="Sichtbarkeit-seit holen (10 Credits)"
                                    >{{ sistrixLaeuft(d, 'alter') ? 'Alter…' : 'Alter · 10' }}</button>
                                    <button
                                        @click="sistrixCrawlen(d, 'dp')"
                                        :disabled="sistrixLaeuft(d, 'dp')"
                                        class="lam-btn-secondary-bordered justify-center"
                                        title="Verlinkende Domains holen (25 Credits)"
                                    >{{ sistrixLaeuft(d, 'dp') ? 'DP…' : 'DP · 25' }}</button>
                                    <button
                                        @click="sistrixCrawlen(d, 'alles')"
                                        :disabled="sistrixLaeuft(d, 'alles')"
                                        class="lam-btn-primary justify-center"
                                        title="SI + Alter + DP in einem Rutsch (36 Credits)"
                                    >{{ sistrixLaeuft(d, 'alles') ? 'Alles…' : 'Alles · 36' }}</button>
                                </div>
                                <button @click="sistrixManuellOffen = !sistrixManuellOffen" class="lam-btn-secondary-bordered w-full justify-center">
                                    {{ sistrixManuellOffen ? 'Manuell schließen' : 'Manuell eintragen' }}
                                </button>
                                <div v-if="sistrixManuellOffen" class="space-y-1.5 border-t border-slate-100 pt-2">
                                    <input v-model.number="editSistrixManuell.si" type="number" step="0.0001" placeholder="SI" class="lam-input" />
                                    <input v-model.number="editSistrixManuell.dp" type="number" placeholder="DP" class="lam-input" />
                                    <input v-model.number="editSistrixManuell.domain_alter" type="number" placeholder="Domain-Alter (Jahre)" class="lam-input" />
                                    <button @click="sistrixManuellSpeichern(d)" class="lam-btn-primary w-full justify-center">Manuell speichern</button>
                                </div>
                                <div class="lam-popover-actions">
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Abbrechen</button>
                                </div>
                            </div>
                        </td>

                        <!-- Preis inline -->
                        <td class="relative px-4 py-3 font-mono">
                            <div v-if="!istOffen(d.id, 'preis')">
                                <button @click="oeffneEdit(d, 'preis')" class="group block w-full text-left">
                                    <div v-if="d.preis !== null" class="text-sm text-slate-800 group-hover:text-thoxan-700">
                                        <span class="font-semibold">ab {{ eurFormat(d.preis) }}</span>
                                        <span v-if="d.preis_anzahl_alternativen > 0" class="ml-1 rounded bg-slate-100 px-1 text-xs text-slate-500" :title="`${d.preis_anzahl_alternativen} weitere Kondition${d.preis_anzahl_alternativen === 1 ? '' : 'en'}`">+{{ d.preis_anzahl_alternativen }}</span>
                                    </div>
                                    <div v-else class="text-sm text-slate-500 group-hover:text-thoxan-700">+ Preis</div>
                                </button>
                            </div>
                            <div v-else class="lam-popover text-left font-sans">
                                <div class="lam-popover-label">Kondition anlegen</div>
                                <input v-model.number="editPreis.preis" type="number" step="0.01" min="0" placeholder="Preis in EUR" class="lam-input" @keydown.enter="preisSpeichern(d)" />
                                <select v-model="editPreis.buchungstyp" class="lam-input">
                                    <option value="gastartikel">gastartikel</option>
                                    <option value="advertorial">advertorial</option>
                                    <option value="pressemitteilung">pressemitteilung</option>
                                    <option value="interview">interview</option>
                                    <option value="verzeichnis">verzeichnis</option>
                                    <option value="startseite">startseite</option>
                                </select>
                                <SucheCombobox
                                    v-model="editPreis.via_anbieter_id"
                                    :optionen="filterOptionen.vermittler"
                                    option-key="id"
                                    option-label="name"
                                    placeholder="über Vermittler…"
                                    leerer-text="direkt"
                                />
                                <label class="flex items-center gap-1.5 text-xs text-slate-700">
                                    <input type="checkbox" v-model="editPreis.inkl_text" class="rounded" />
                                    <span>inkl. Text</span>
                                </label>
                                <div class="lam-popover-actions">
                                    <button @click="preisSpeichern(d)" :disabled="!editPreis.preis || laeuft === `preis:${d.id}`" class="lam-btn-primary">Speichern</button>
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Abbrechen</button>
                                </div>
                            </div>
                        </td>

                        <!-- Status inline -->
                        <td class="relative px-4 py-3">
                            <div v-if="!istOffen(d.id, 'status')">
                                <button
                                    @click="oeffneEdit(d, 'status')"
                                    :class="['rounded px-2 py-0.5 text-xs font-medium hover:ring-2 hover:ring-thoxan-300', statusFarbe(d.verifikation_status)]"
                                    :title="d.verifikation_status === 'geloescht' && d.disqualifikations_grund ? d.disqualifikations_grund : 'Klicken zum Ändern'"
                                >{{ statusLabel(d.verifikation_status) }}</button>
                                <div v-if="d.letzter_check_am" class="mt-0.5 text-xs text-slate-500">{{ d.letzter_check_am }}</div>
                            </div>
                            <div v-else class="lam-popover text-left">
                                <div class="lam-popover-label">Status setzen</div>
                                <button @click="statusSetzen(d, 'linkquellen.verifizieren')" class="lam-status-option">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> geprüft
                                </button>
                                <button @click="statusSetzen(d, 'linkquellen.veraltet')" class="lam-status-option">
                                    <span class="h-2 w-2 rounded-full bg-orange-500"></span> veraltet
                                </button>
                                <div class="border-t border-slate-100 pt-2">
                                    <textarea v-model="verwerfenGrund" rows="2" placeholder="Grund für Verwerfen (optional)" class="lam-input resize-none"></textarea>
                                    <button @click="verwerfenAbsenden(d)" class="lam-status-option mt-1.5 text-red-700">
                                        <span class="h-2 w-2 rounded-full bg-red-500"></span> verwerfen
                                    </button>
                                </div>
                                <div class="lam-popover-actions">
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Abbrechen</button>
                                </div>
                            </div>
                        </td>

                        <!-- Kunden inline -->
                        <td class="relative px-4 py-3">
                            <div v-if="!istOffen(d.id, 'kunden')">
                                <button @click="oeffneEdit(d, 'kunden')" class="group block w-full text-left">
                                    <span v-if="d.kunden && d.kunden.length" class="flex flex-wrap gap-1">
                                        <span v-for="k in d.kunden" :key="k.kuerzel" class="rounded bg-thoxan-100 px-1.5 py-0.5 text-xs font-medium text-thoxan-800" :title="k.name">{{ k.kuerzel }}</span>
                                    </span>
                                    <span v-else class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 group-hover:bg-thoxan-50 group-hover:text-thoxan-700">+ Kunde</span>
                                </button>
                            </div>
                            <div v-else class="lam-popover">
                                <div class="lam-popover-label">Kunden zuordnen</div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="k in filterOptionen.kunden"
                                        :key="k.kuerzel"
                                        type="button"
                                        @click="kundeToggle(k.kuerzel)"
                                        :title="k.name"
                                        :class="['rounded px-2 py-0.5 text-xs transition', editKundenAuswahl.includes(k.kuerzel) ? 'bg-thoxan-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50']"
                                    >{{ k.kuerzel }}</button>
                                    <span v-if="!filterOptionen.kunden?.length" class="text-xs text-slate-400">Noch keine Kunden angelegt.</span>
                                </div>
                                <div class="lam-popover-actions">
                                    <button @click="kundenSpeichern(d)" :disabled="laeuft === `kunden:${d.id}`" class="lam-btn-primary">Speichern</button>
                                    <button @click="schliesseEdit" class="lam-btn-secondary">Abbrechen</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center">
                <p class="text-slate-500">Keine Domains gefunden.</p>
                <p v-if="aktiveFilterAnzahl" class="mt-1 text-sm text-slate-400">Versuche es mit weniger oder anderen Filtern.</p>
            </div>
        </div>

        <nav v-if="domains.total > 0" class="mt-4 flex items-center justify-between text-sm">
            <div class="flex items-center gap-4 text-slate-500">
                <div>Seite {{ domains.current_page }} von {{ domains.last_page }} ({{ domains.from }}-{{ domains.to }} von {{ domains.total }})</div>
                <label class="flex items-center gap-2">
                    <span class="text-xs">Pro Seite</span>
                    <select v-model.number="lokalerFilter.pro_seite" class="rounded border border-slate-300 bg-white pl-2 pr-7 py-1 text-xs">
                        <option v-for="opt in filterOptionen.pro_seite_optionen ?? [25, 50, 100, 250, 500]" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </label>
            </div>
            <div v-if="domains.last_page > 1" class="flex gap-1">
                <Link
                    v-for="link in domains.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="['rounded px-3 py-1.5', link.active ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-100', !link.url ? 'pointer-events-none text-slate-300' : '']"
                    preserve-scroll
                    v-html="link.label"
                />
            </div>
        </nav>

        <!-- Rechtsklick-Kontextmenü -->
        <div
            v-if="kontextmenu.offen"
            class="fixed inset-0 z-40"
            @click="schliesseKontextmenu"
            @contextmenu.prevent="schliesseKontextmenu"
        >
            <div
                :style="{ top: kontextmenu.y + 'px', left: kontextmenu.x + 'px' }"
                class="absolute min-w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                @click.stop
            >
                <div class="border-b border-slate-100 px-3 py-1.5 text-xs text-slate-500 truncate" :title="kontextmenu.domain?.url">{{ kontextmenu.domain?.url }}</div>
                <Link :href="route('linkquellen.show', kontextmenu.domain?.id)" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Öffnen</Link>
                <button @click="oeffneModal(kontextmenu.domain)" class="block w-full px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50">Bearbeiten…</button>
                <div class="my-1 border-t border-slate-100"></div>
                <div class="relative" @mouseleave="kontextmenuStatusOffen = false">
                    <button
                        type="button"
                        @click="kontextmenuStatusOffen = !kontextmenuStatusOffen"
                        class="flex w-full items-center justify-between px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        <span>Status setzen</span>
                        <span class="text-slate-400">›</span>
                    </button>
                    <div v-if="kontextmenuStatusOffen" class="absolute left-full top-0 ml-1 min-w-40 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                        <button v-for="s in ['neu', 'in_arbeit', 'geprueft', 'veraltet']" :key="s" @click="kontextmenuStatusSetzen(s)" class="block w-full px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50">{{ statusLabel(s) }}</button>
                    </div>
                </div>
                <button @click="kontextmenuVerwerfen" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50">Verwerfen mit Grund…</button>
                <div class="my-1 border-t border-slate-100"></div>
                <button @click="kontextmenuLoeschen" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50">Löschen</button>
            </div>
        </div>

        <!-- Bulk-Dialog: Kunden zuweisen / entfernen -->
        <div v-if="bulkKundenOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkKundenOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Kunden zuweisen oder entfernen</h3>
                <p class="mt-1 text-xs text-slate-500">{{ anzahlSelektiert }} Linkquelle(n) betroffen. Beim Hinzufügen wird der Verifikation-Status automatisch von „Neu"/„Veraltet" auf „In Arbeit" gesetzt.</p>

                <div class="mt-4 inline-flex rounded border border-slate-200 bg-slate-50 p-0.5 text-xs">
                    <button type="button" @click="bulkKundenModus = 'anhaengen'" :class="['rounded px-3 py-1 font-medium', bulkKundenModus === 'anhaengen' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Hinzufügen</button>
                    <button type="button" @click="bulkKundenModus = 'entfernen'" :class="['rounded px-3 py-1 font-medium', bulkKundenModus === 'entfernen' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Entfernen</button>
                </div>

                <div class="mt-4 max-h-72 overflow-y-auto rounded border border-slate-200 p-2">
                    <label v-for="k in filterOptionen.kunden" :key="k.kuerzel" class="flex items-center gap-2 px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" :value="k.kuerzel" v-model="bulkKundenAuswahl" class="rounded" />
                        <span class="font-mono font-medium text-thoxan-800">{{ k.kuerzel }}</span>
                        <span class="text-slate-500">{{ k.name }}</span>
                    </label>
                    <p v-if="!filterOptionen.kunden?.length" class="px-2 py-1 text-xs text-slate-400">Noch keine Kunden angelegt.</p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkKundenOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button @click="bulkKundenAnwenden" :disabled="bulkAktionLaeuft || bulkKundenAuswahl.length === 0" class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400">{{ bulkKundenModus === 'anhaengen' ? 'Hinzufügen' : 'Entfernen' }}</button>
                </div>
            </div>
        </div>

        <!-- Bulk-Dialog: Tags zuweisen / entfernen -->
        <div v-if="bulkTagsOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkTagsOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Tags zuweisen oder entfernen</h3>
                <p class="mt-1 text-xs text-slate-500">{{ anzahlSelektiert }} Linkquelle(n) betroffen.</p>

                <div class="mt-4 inline-flex rounded border border-slate-200 bg-slate-50 p-0.5 text-xs">
                    <button type="button" @click="bulkTagsModus = 'anhaengen'" :class="['rounded px-3 py-1 font-medium', bulkTagsModus === 'anhaengen' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Hinzufügen</button>
                    <button type="button" @click="bulkTagsModus = 'entfernen'" :class="['rounded px-3 py-1 font-medium', bulkTagsModus === 'entfernen' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Entfernen</button>
                </div>

                <div class="mt-4 max-h-72 overflow-y-auto rounded border border-slate-200 p-2">
                    <label v-for="t in filterOptionen.tags" :key="t.id" class="flex items-center gap-2 px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" :value="t.id" v-model="bulkTagsAuswahl" class="rounded" />
                        <span>{{ t.name }}</span>
                    </label>
                    <p v-if="!filterOptionen.tags.length" class="px-2 py-1 text-xs text-slate-400">Noch keine Tags angelegt.</p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkTagsOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button @click="bulkTagsAnwenden" :disabled="bulkAktionLaeuft || bulkTagsAuswahl.length === 0" class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400">{{ bulkTagsModus === 'anhaengen' ? 'Hinzufügen' : 'Entfernen' }}</button>
                </div>
            </div>
        </div>

        <!-- Bulk-Dialog: Status setzen -->
        <div v-if="bulkStatusOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkStatusOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Verifikation-Status setzen</h3>
                <p class="mt-1 text-xs text-slate-500">{{ anzahlSelektiert }} Linkquelle(n) betroffen. „Verworfen" geht über den eigenen Knopf mit Pflicht-Begründung.</p>

                <div class="mt-4 space-y-2">
                    <label v-for="s in ['neu', 'in_arbeit', 'geprueft', 'veraltet']" :key="s" class="flex items-center gap-2 text-sm">
                        <input type="radio" :value="s" v-model="bulkStatusAuswahl" />
                        <span :class="['rounded px-2 py-0.5 text-xs font-medium', statusFarbe(s)]">{{ statusLabel(s) }}</span>
                    </label>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkStatusOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button @click="bulkStatusAnwenden" :disabled="bulkAktionLaeuft" class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400">Setzen</button>
                </div>
            </div>
        </div>

        <!-- Bulk-Dialog: In Linkoptionen aufnehmen -->
        <div v-if="bulkLinkoptionenOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkLinkoptionenOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">In Linkoptionen aufnehmen</h3>
                <p class="mt-1 text-xs text-slate-500">{{ anzahlSelektiert }} Linkquelle(n) werden einer Linkoptionen-Liste hinzugefügt. Doppelte werden übersprungen.</p>

                <div class="mt-4 inline-flex rounded border border-slate-200 bg-slate-50 p-0.5 text-xs">
                    <button type="button" @click="bulkLinkoptionenModus = 'bestehend'" :disabled="!bulkLinkoptionenListen.length" :class="['rounded px-3 py-1 font-medium', bulkLinkoptionenModus === 'bestehend' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700 disabled:opacity-40']">Bestehende Liste</button>
                    <button type="button" @click="bulkLinkoptionenModus = 'neu'" :class="['rounded px-3 py-1 font-medium', bulkLinkoptionenModus === 'neu' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Neue Liste</button>
                </div>

                <div v-if="bulkLinkoptionenModus === 'bestehend'" class="mt-4">
                    <p v-if="bulkLinkoptionenLadeListen" class="text-xs text-slate-500">Lade Listen…</p>
                    <p v-else-if="!bulkLinkoptionenListen.length" class="rounded bg-slate-50 p-3 text-xs text-slate-600">Es gibt noch keine Linkoptionen-Listen. Bitte „Neue Liste" wählen.</p>
                    <div v-else class="max-h-60 space-y-1 overflow-y-auto rounded border border-slate-200 p-2">
                        <label v-for="l in bulkLinkoptionenListen" :key="l.id" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                            <input type="radio" :value="l.id" v-model="bulkLinkoptionenAuswahl.vorschlagsliste_id" />
                            <div class="flex-1">
                                <div class="font-medium text-slate-800">{{ l.name }}</div>
                                <div class="text-xs text-slate-500">
                                    <span class="font-mono">{{ l.kuerzel }}</span>
                                    <span v-if="l.kunde_name"> · {{ l.kunde_name }}</span>
                                    <span> · {{ l.status }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div v-else class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Kunde <span class="text-red-500">*</span></label>
                        <select v-model="bulkLinkoptionenAuswahl.kuerzel" class="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">— wählen —</option>
                            <option v-for="k in filterOptionen.kunden" :key="k.kuerzel" :value="k.kuerzel">{{ k.kuerzel }} — {{ k.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Listenname <span class="text-red-500">*</span></label>
                        <input v-model="bulkLinkoptionenAuswahl.name" type="text" placeholder="z.B. Q3-Kampagne Familien-Themen" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Zielzahl (optional)</label>
                        <input v-model="bulkLinkoptionenAuswahl.zielzahl" type="number" min="1" max="200" placeholder="z.B. 20" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkLinkoptionenOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button
                        @click="bulkLinkoptionenAnwenden"
                        :disabled="bulkAktionLaeuft || (bulkLinkoptionenModus === 'bestehend' ? !bulkLinkoptionenAuswahl.vorschlagsliste_id : (!bulkLinkoptionenAuswahl.kuerzel || !bulkLinkoptionenAuswahl.name.trim()))"
                        class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400"
                    >Übernehmen</button>
                </div>
            </div>
        </div>

        <!-- Inline-Bearbeiten-Modal (statt Page-Sprung) -->
        <LinkquelleBearbeitenModal
            :domain-id="modalDomainId"
            :anbieter="filterOptionen.anbieter ?? []"
            :tags="filterOptionen.tags ?? []"
            @schliessen="schliesseModal"
        />

        <!-- Bulk-Dialog: Verwerfen mit Grund -->
        <div v-if="bulkVerwerfenOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkVerwerfenOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Linkquellen verwerfen</h3>
                <p class="mt-1 text-xs text-slate-500">{{ anzahlSelektiert }} Linkquelle(n) werden auf Status „Gelöscht" gesetzt. Der Grund landet als Sammel-Begründung an jedem Eintrag.</p>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-slate-600">Grund <span class="text-red-500">*</span></label>
                    <textarea
                        v-model="bulkVerwerfenGrund"
                        rows="3"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        placeholder="z.B. Themenfit zu schwach, kein Backlink-Wert, …"
                    />
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkVerwerfenOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button @click="bulkVerwerfenAnwenden" :disabled="bulkAktionLaeuft || bulkVerwerfenGrund.trim().length < 3" class="rounded bg-red-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-red-700 disabled:bg-slate-400">Verwerfen</button>
                </div>
            </div>
        </div>

        <!-- Bulk-Dialog: Sistrix-Teil-Abruf mit Kosten-Vorschau -->
        <div v-if="bulkSistrixOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="bulkSistrixOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Sistrix abrufen: {{ SISTRIX_LABEL[bulkSistrixTeil] }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Cache-Hits werden nicht erneut abgerechnet, das Maximum ist {{ bulkSistrixKostenGesamt.toLocaleString('de-DE') }} Credits.
                </p>

                <dl class="mt-4 space-y-1.5 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Linkquellen</dt>
                        <dd class="font-medium text-slate-800">{{ anzahlSelektiert.toLocaleString('de-DE') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Credits je Linkquelle</dt>
                        <dd class="font-medium text-slate-800">{{ SISTRIX_CREDITS[bulkSistrixTeil] }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-1.5">
                        <dt class="font-semibold text-slate-700">Maximalkosten</dt>
                        <dd class="font-bold text-slate-900">{{ bulkSistrixKostenGesamt.toLocaleString('de-DE') }} Credits</dd>
                    </div>
                    <div v-if="sistrix?.wochenstatus" class="flex justify-between text-xs">
                        <dt class="text-slate-500">Verbleibendes Wochenbudget</dt>
                        <dd :class="bulkSistrixBudgetReicht ? 'text-slate-600' : 'font-semibold text-red-600'">
                            {{ sistrix.wochenstatus.credits_verbleibend.toLocaleString('de-DE') }}
                            / {{ sistrix.wochenstatus.wochenkontingent.toLocaleString('de-DE') }}
                        </dd>
                    </div>
                </dl>

                <p v-if="!bulkSistrixBudgetReicht" class="mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    Achtung: Das Wochenkontingent reicht nicht für alle ausgewählten Linkquellen. Der Lauf bricht ab, sobald keine Credits mehr da sind.
                </p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="bulkSistrixOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button
                        @click="bulkSistrixAnwenden"
                        :disabled="anzahlSelektiert === 0"
                        class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400"
                    >Jetzt abrufen</button>
                </div>
            </div>
        </div>

        <!-- Fortschritts-Modal fuer Chunk-basierte Bulks (Erreichbarkeit, Sistrix).
             Zeigt Balken + Counter, der User kann pausieren/abbrechen.
             Bleibt nach dem Lauf offen, damit der User die Statistik sieht. -->
        <div v-if="fortschritt.offen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-800">{{ fortschritt.label }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    {{ fortschritt.fertig ? 'Fertig.' : (fortschritt.abbrechen ? 'Wird abgebrochen…' : 'Läuft…') }}
                </p>

                <div class="mt-4">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="font-medium text-slate-700">
                            {{ fortschritt.done.toLocaleString('de-DE') }} / {{ fortschritt.total.toLocaleString('de-DE') }}
                        </span>
                        <span class="text-xs text-slate-500">
                            {{ Math.round((fortschritt.done / Math.max(fortschritt.total, 1)) * 100) }} %
                        </span>
                    </div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-thoxan-500 transition-all" :style="{ width: ((fortschritt.done / Math.max(fortschritt.total, 1)) * 100) + '%' }"></div>
                    </div>
                </div>

                <dl class="mt-4 space-y-1 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Erfolgreich</dt>
                        <dd class="font-medium text-emerald-700">{{ fortschritt.erfolge.toLocaleString('de-DE') }}</dd>
                    </div>
                    <div v-if="fortschritt.fehler.length" class="flex justify-between">
                        <dt class="text-slate-500">Fehler</dt>
                        <dd class="font-medium text-red-700">{{ fortschritt.fehler.length.toLocaleString('de-DE') }}</dd>
                    </div>
                    <div v-if="fortschritt.extra" class="flex justify-between border-t border-slate-200 pt-1">
                        <dt class="text-slate-500">Stand</dt>
                        <dd class="text-slate-700">{{ fortschritt.extra }}</dd>
                    </div>
                </dl>

                <details v-if="fortschritt.fehler.length" class="mt-2 text-xs">
                    <summary class="cursor-pointer text-slate-500 hover:text-slate-700">Fehlerdetails ({{ fortschritt.fehler.length }})</summary>
                    <ul class="mt-1 max-h-32 overflow-y-auto rounded border border-red-200 bg-red-50 p-2 text-red-700">
                        <li v-for="(f, i) in fortschritt.fehler" :key="i" class="truncate" :title="f">{{ f }}</li>
                    </ul>
                </details>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        v-if="! fortschritt.fertig"
                        @click="fortschritt.abbrechen = true"
                        :disabled="fortschritt.abbrechen"
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    >Abbrechen</button>
                    <button
                        v-if="fortschritt.fertig"
                        @click="fortschrittSchliessen"
                        class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700"
                    >Schließen</button>
                </div>
            </div>
        </div>
    </LamLayout>
</template>

<style scoped>
/* Einheitlicher Stil fuer alle Inline-Edit-Popover in der Linkquellen-Liste.
   - feste Breite, damit Tabellenspalten nicht "atmen" beim Aufklappen
   - weisser Hintergrund + Schatten statt thoxan-blauer Rahmen, ruhiger
   - kompakte Inputs und drei Button-Hierarchien (primary/secondary/destructive) */
/* Absolut positioniert, damit das Aufklappen die Tabellenspalten nicht
   verbreitert (Tom: "beim bearbeiten springt alles"). Die Eltern-<td>s
   haben class="relative", also schwebt das Popover relativ zur Zelle.
   Unterhalb der Zelle (top-full) und nach rechts oeffnend (left-0). */
.lam-popover {
    @apply absolute left-0 top-full z-20 mt-1 w-60 space-y-2 rounded-md border border-slate-200 bg-white p-3 shadow-lg;
}
.lam-popover-label {
    @apply -mt-1 mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400;
}
.lam-popover-actions {
    @apply flex items-center gap-1.5 border-t border-slate-100 pt-2;
}
.lam-input {
    @apply w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 focus:border-thoxan-400 focus:outline-none focus:ring-1 focus:ring-thoxan-300;
}
.lam-btn-primary {
    @apply inline-flex items-center justify-center gap-1 rounded bg-thoxan-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-thoxan-700 disabled:bg-slate-300 disabled:text-slate-100;
}
.lam-btn-secondary {
    @apply inline-flex items-center justify-center rounded px-2 py-1 text-xs text-slate-500 transition hover:text-slate-800;
}
.lam-btn-secondary-bordered {
    @apply inline-flex items-center justify-center rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 transition hover:bg-slate-50;
}
.lam-btn-destructive {
    @apply inline-flex items-center justify-center rounded px-2 py-1 text-xs text-red-600 transition hover:bg-red-50;
}
/* Status-Auswahl: Zeilen-Optionen mit Farbpunkt links, hover dezent. */
.lam-status-option {
    @apply flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs text-slate-700 transition hover:bg-slate-50;
}
/* Filter-Bereich-Klassen (lam-filter-label, lam-filter-input,
   lam-filter-chip) leben jetzt global in resources/css/app.css als
   @layer components, damit auch andere Listen-Seiten (Linkprofil)
   sie nutzen koennen, ohne sie duplizieren zu muessen. */
</style>
