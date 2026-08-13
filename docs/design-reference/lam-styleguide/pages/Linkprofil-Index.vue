<script setup>
import LamLayout from '@/Layouts/LamLayout.vue';
import SucheCombobox from '@/Components/SucheCombobox.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    kunden: { type: Array, required: true },
    aktiver_kunde: { type: String, default: null },
    kennzahlen: { type: Object, required: true },
    verlinkungen: { type: Object, default: null },
    verfuegbare_tags: { type: Array, default: () => [] },
    sistrix_status: { type: Object, default: null },
    sistrix_credits_pro_teil: { type: Object, required: true },
    filter: { type: Object, required: true },
    filter_optionen: { type: Object, required: true },
    vokabular: { type: Object, required: true },
});

const page = usePage();
const gewaehlterKunde = ref(props.aktiver_kunde);

// Filter (reaktiv, mit Entprellung und Query-Sync)
const lokalerFilter = reactive({
    suche: props.filter.suche ?? '',
    linkart: Array.isArray(props.filter.linkart) ? [...props.filter.linkart] : [],
    empfehlung: Array.isArray(props.filter.empfehlung) ? [...props.filter.empfehlung] : [],
    status: Array.isArray(props.filter.status) ? [...props.filter.status] : [],
    importquelle: Array.isArray(props.filter.importquelle) ? [...props.filter.importquelle] : [],
    neu_alt: props.filter.neu_alt ?? '',
    ohne_empfehlung: !!props.filter.ohne_empfehlung,
    erreichbarkeit: props.filter.erreichbarkeit ?? '',
    nur_link_verloren: !!props.filter.nur_link_verloren,
    follow: props.filter.follow ?? '',
    ohne_linktext: !!props.filter.ohne_linktext,
    ohne_ziel_url: !!props.filter.ohne_ziel_url,
    ohne_linkart: !!props.filter.ohne_linkart,
    sortierung: props.filter.sortierung ?? 'domain_asc',
    pro_seite: props.filter.pro_seite ?? 25,
});

const filterAnzahl = computed(() =>
    lokalerFilter.linkart.length
    + lokalerFilter.empfehlung.length
    + lokalerFilter.status.length
    + lokalerFilter.importquelle.length
    + (lokalerFilter.suche ? 1 : 0)
    + (lokalerFilter.neu_alt ? 1 : 0)
    + (lokalerFilter.ohne_empfehlung ? 1 : 0)
    + (lokalerFilter.erreichbarkeit ? 1 : 0)
    + (lokalerFilter.nur_link_verloren ? 1 : 0)
    + (lokalerFilter.follow ? 1 : 0)
    + (lokalerFilter.ohne_linktext ? 1 : 0)
    + (lokalerFilter.ohne_ziel_url ? 1 : 0)
    + (lokalerFilter.ohne_linkart ? 1 : 0),
);

let entprell = null;
const sendeFilter = (sofort = false) => {
    clearTimeout(entprell);
    const tun = () => {
        router.get(route('linkprofil.index'), {
            kunde: gewaehlterKunde.value ?? undefined,
            ...lokalerFilter,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };
    if (sofort) tun();
    else entprell = setTimeout(tun, 250);
};

watch(lokalerFilter, () => sendeFilter(), { deep: true });

watch(gewaehlterKunde, (neuer) => {
    if (neuer === props.aktiver_kunde) return;
    // Beim Kundenwechsel Filter zuruecksetzen, damit der Kontext eindeutig bleibt.
    lokalerFilter.suche = '';
    lokalerFilter.linkart = [];
    lokalerFilter.empfehlung = [];
    lokalerFilter.status = [];
    lokalerFilter.importquelle = [];
    lokalerFilter.neu_alt = '';
    lokalerFilter.ohne_empfehlung = false;
    router.get(
        route('linkprofil.index'),
        { kunde: neuer ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

// Multi-Select-Chips: Klick = exklusiv (nur dieser), Shift/Ctrl/Cmd = additiv.
// Identisches Pattern wie in Linkquellen-Liste und Linkoptionen-Pool.
const chipKlick = (event, liste, wert) => {
    if (event.shiftKey || event.ctrlKey || event.metaKey) {
        const idx = liste.indexOf(wert);
        if (idx === -1) liste.push(wert);
        else liste.splice(idx, 1);
        return;
    }
    if (liste.length === 1 && liste[0] === wert) {
        liste.splice(0, liste.length);
    } else {
        liste.splice(0, liste.length, wert);
    }
};
const filterToggle = (gruppe, wert, event) => chipKlick(event ?? {}, lokalerFilter[gruppe], wert);
const gruppeAlleAbwaehlen = (gruppe) => { lokalerFilter[gruppe].splice(0, lokalerFilter[gruppe].length); };

// "Weitere Filter"-Toggle, analog Linkquellen/Linkoptionen. Default offen,
// wenn aus der URL bereits ein "weiterer" Filter aktiv ist.
const initialWeitereOffen = () => {
    const f = props.filter ?? {};
    return Boolean(
        f.neu_alt || f.follow ||
        f.ohne_empfehlung || f.nur_link_verloren ||
        f.ohne_linktext || f.ohne_ziel_url || f.ohne_linkart,
    );
};
const weitereFilterOffen = ref(initialWeitereOffen());

const weitereFilterAktiv = computed(() =>
    (lokalerFilter.neu_alt ? 1 : 0)
    + (lokalerFilter.follow ? 1 : 0)
    + (lokalerFilter.ohne_empfehlung ? 1 : 0)
    + (lokalerFilter.nur_link_verloren ? 1 : 0)
    + (lokalerFilter.ohne_linktext ? 1 : 0)
    + (lokalerFilter.ohne_ziel_url ? 1 : 0)
    + (lokalerFilter.ohne_linkart ? 1 : 0),
);

const filterZuruecksetzen = () => {
    lokalerFilter.suche = '';
    lokalerFilter.linkart = [];
    lokalerFilter.empfehlung = [];
    lokalerFilter.status = [];
    lokalerFilter.importquelle = [];
    lokalerFilter.neu_alt = '';
    lokalerFilter.ohne_empfehlung = false;
    lokalerFilter.erreichbarkeit = '';
    lokalerFilter.nur_link_verloren = false;
    lokalerFilter.follow = '';
    lokalerFilter.ohne_linktext = false;
    lokalerFilter.ohne_ziel_url = false;
    lokalerFilter.ohne_linkart = false;
};

// Sortierung per Header-Klick
const spalteSortierung = (spalte) => {
    const aktuelle = lokalerFilter.sortierung;
    if (aktuelle === `${spalte}_asc`) {
        lokalerFilter.sortierung = `${spalte}_desc`;
    } else {
        lokalerFilter.sortierung = `${spalte}_asc`;
    }
};

const sortierIndikator = (spalte) => {
    if (lokalerFilter.sortierung === `${spalte}_asc`) return '↑';
    if (lokalerFilter.sortierung === `${spalte}_desc`) return '↓';
    return '';
};

// Quellen-Labels (Modal)
const quellenOptionen = [
    { wert: 'sistrix', label: 'Sistrix' },
    { wert: 'ahrefs', label: 'AHREFs' },
    { wert: 'xovi', label: 'XOVI' },
    { wert: 'gsc', label: 'Google Search Console' },
];

// Name des aktiven Kunden fuer den Google-Suchhelfer. Faellt auf den
// Kuerzel zurueck, wenn der Name nicht eindeutig zuordbar ist.
const aktiver_kunde_name = computed(() => {
    const k = props.kunden?.find?.((x) => x.kuerzel === props.aktiver_kunde);
    return k?.name?.split(/\s+/)[0] ?? props.aktiver_kunde ?? '';
});

// Google-Suche fuer Deep-URL-Recherche: site:domain.de intext:Kundenname.
// Tom-Wunsch: schneller manueller Workaround, wenn die Quelle keine
// Ziel-URL liefert (z.B. GSC Top-linking-sites, XOVI Stichprobe).
const googleDeepUrlSuche = (domain) => {
    const q = `site:${domain} intext:${aktiver_kunde_name.value}`;
    return `https://www.google.com/search?q=${encodeURIComponent(q)}`;
};

// Kurz-Codes fuer die Quelle-Spalte in der Tabelle — die Langform
// "Google Search Console" sprengt die Spalte oder bricht haesslich um.
// Volle Bezeichnung steht im title-Tooltip.
const importquelleKurz = {
    sistrix: 'Sistrix',
    ahrefs: 'AHREFs',
    xovi: 'XOVI',
    gsc: 'GSC',
    manuell: 'Manuell',
};

// Lade-Overlay für teure Navigations (Aufräumen baut KI-Klassifikation,
// dauert je nach Datenmenge mehrere Sekunden).
const ladeText = ref(null);
const oeffneAufraeumen = () => {
    if (!gewaehlterKunde.value) return;
    ladeText.value = 'Aufräumen wird vorbereitet — Sitewide-Cluster werden ermittelt und Domains klassifiziert (KI).';
    router.get(
        route('linkprofil.aufraeumen', { kunde: gewaehlterKunde.value }),
        {},
        { onFinish: () => { ladeText.value = null; } },
    );
};

// CSV-Import-Modal-State (Multi-Upload: mehrere Dateien gleicher Quelle).
const importOffen = ref(false);
const importForm = ref({ quelle: 'sistrix', dateien: [] });
const importLaeuft = ref(false);
const importFehler = ref(null);

const oeffneImport = () => {
    if (!gewaehlterKunde.value) return;
    importForm.value = { quelle: 'sistrix', dateien: [] };
    importFehler.value = null;
    importOffen.value = true;
};

const schliesseImport = () => {
    if (importLaeuft.value) return;
    importOffen.value = false;
};

const dateiGewaehlt = (event) => {
    importForm.value.dateien = Array.from(event.target.files ?? []);
};

const entferneImportDatei = (idx) => {
    importForm.value.dateien = importForm.value.dateien.filter((_, i) => i !== idx);
};

const sendeImport = () => {
    if (!gewaehlterKunde.value || !importForm.value.dateien.length) {
        importFehler.value = 'Bitte Kunde wählen und mindestens eine CSV-Datei auswählen.';
        return;
    }
    importLaeuft.value = true;
    importFehler.value = null;
    router.post(
        route('linkprofil.import'),
        {
            kunde_kuerzel: gewaehlterKunde.value,
            quelle: importForm.value.quelle,
            dateien: importForm.value.dateien,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { importOffen.value = false; },
            onError: (errors) => {
                const ersterFehler = Object.values(errors)[0];
                importFehler.value = Array.isArray(ersterFehler) ? ersterFehler[0] : ersterFehler;
            },
            onFinish: () => { importLaeuft.value = false; },
        },
    );
};

const importBericht = computed(() => page.props.linkprofil_import_bericht ?? null);

// ─── Historien-Import (Excel) ─────────────────────────────────────────
const historieOffen = ref(false);
const historieForm = ref({ datei: null });
const historieLaeuft = ref(false);
const historieFehler = ref(null);

const oeffneHistorie = () => {
    if (!gewaehlterKunde.value) return;
    historieForm.value = { datei: null };
    historieFehler.value = null;
    historieOffen.value = true;
};

const schliesseHistorie = () => {
    if (historieLaeuft.value) return;
    historieOffen.value = false;
};

const historieDateiGewaehlt = (event) => {
    historieForm.value.datei = event.target.files?.[0] ?? null;
};

const sendeHistorie = () => {
    if (!gewaehlterKunde.value || !historieForm.value.datei) {
        historieFehler.value = 'Bitte Kunde wählen und Excel-Datei auswählen.';
        return;
    }
    historieLaeuft.value = true;
    historieFehler.value = null;
    router.post(
        route('linkprofil.historien-import'),
        { kunde_kuerzel: gewaehlterKunde.value, datei: historieForm.value.datei },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { historieOffen.value = false; },
            onError: (errors) => {
                const ersterFehler = Object.values(errors)[0];
                historieFehler.value = Array.isArray(ersterFehler) ? ersterFehler[0] : ersterFehler;
            },
            onFinish: () => { historieLaeuft.value = false; },
        },
    );
};

// Tabellen-Helfer
const kurz = (text, max = 60) => {
    if (!text) return '';
    return text.length > max ? text.substring(0, max) + '…' : text;
};

const siFormatiert = (wert) => {
    if (wert === null || wert === undefined || wert === '') return null;
    const num = Number(wert);
    if (Number.isNaN(num)) return null;
    return num.toFixed(num >= 10 ? 1 : 4);
};

// Farbgebung wie in der Linkquellen-Liste: < 6 Monate dunkelgrau,
// < 12 Monate slate, ≥ 12 Monate orange.
const siFarbe = (erfasstAm) => {
    if (!erfasstAm) return 'text-slate-700';
    const datum = new Date(erfasstAm);
    if (Number.isNaN(datum.getTime())) return 'text-slate-700';
    const monateAlt = (Date.now() - datum.getTime()) / (1000 * 60 * 60 * 24 * 30);
    if (monateAlt < 6) return 'text-slate-900 font-medium';
    if (monateAlt < 12) return 'text-slate-500';
    return 'text-orange-600';
};

// ─── Inline-Edit ──────────────────────────────────────────────────────
const editZelle = ref({ id: null, feld: null });
const editWert = ref('');
const editLaeuft = ref(false);

const startInline = (verlinkung, feld) => {
    if (editLaeuft.value) return;
    editZelle.value = { id: verlinkung.id, feld };
    editWert.value = verlinkung[feld] ?? '';
};

const istInline = (verlinkung, feld) =>
    editZelle.value.id === verlinkung.id && editZelle.value.feld === feld;

const abbrechenInline = () => {
    editZelle.value = { id: null, feld: null };
    editWert.value = '';
};

const speichereInline = (verlinkung, feld, wert) => {
    if (editLaeuft.value) return;
    if ((wert ?? '') === (verlinkung[feld] ?? '')) {
        abbrechenInline();
        return;
    }
    editLaeuft.value = true;
    router.post(
        route('linkprofil.verlinkungen.inline', verlinkung.id),
        { feld, wert: wert ?? null },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                editLaeuft.value = false;
                abbrechenInline();
            },
        },
    );
};

// ─── Tag-Modal ────────────────────────────────────────────────────────
const tagModalOffen = ref(false);
const tagModalVerlinkung = ref(null);
const tagAuswahl = ref([]); // [{id, name, slug, kunde_kuerzel}]
const tagNeueNamen = ref([]); // [string]
const tagEingabe = ref('');
const tagLaeuft = ref(false);

const oeffneTagModal = (verlinkung) => {
    tagModalVerlinkung.value = verlinkung;
    tagAuswahl.value = [...(verlinkung.tags ?? [])];
    tagNeueNamen.value = [];
    tagEingabe.value = '';
    tagModalOffen.value = true;
};

const schliesseTagModal = () => {
    if (tagLaeuft.value) return;
    tagModalOffen.value = false;
    tagModalVerlinkung.value = null;
};

const tagVorschlaege = computed(() => {
    const q = tagEingabe.value.trim().toLowerCase();
    const gewaehlteIds = new Set(tagAuswahl.value.map((t) => t.id));
    return (props.verfuegbare_tags ?? [])
        .filter((t) => !gewaehlteIds.has(t.id))
        .filter((t) => !q || t.name.toLowerCase().includes(q))
        .slice(0, 8);
});

const passenderNeuVorschlag = computed(() => {
    const q = tagEingabe.value.trim();
    if (!q) return null;
    const slug = q.toLowerCase().replace(/[\s-]+/g, '_');
    const existiert = (props.verfuegbare_tags ?? []).some((t) => t.slug === slug);
    const bereitsNeu = tagNeueNamen.value.includes(q);
    if (existiert || bereitsNeu) return null;
    return q;
});

const fuegeTagHinzu = (tag) => {
    if (tagAuswahl.value.some((t) => t.id === tag.id)) return;
    tagAuswahl.value.push(tag);
    tagEingabe.value = '';
};

const fuegeNeuenTagHinzu = (name) => {
    const n = name.trim();
    if (!n) return;
    if (tagNeueNamen.value.includes(n)) return;
    tagNeueNamen.value.push(n);
    tagEingabe.value = '';
};

const entferneTag = (tag) => {
    tagAuswahl.value = tagAuswahl.value.filter((t) => t.id !== tag.id);
};

const entferneNeuenTag = (name) => {
    tagNeueNamen.value = tagNeueNamen.value.filter((n) => n !== name);
};

const speichereTagModal = () => {
    if (!tagModalVerlinkung.value) return;
    tagLaeuft.value = true;
    router.post(
        route('linkprofil.verlinkungen.tags', tagModalVerlinkung.value.id),
        {
            tag_ids: tagAuswahl.value.map((t) => t.id),
            neue_tag_namen: tagNeueNamen.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                tagModalOffen.value = false;
                tagModalVerlinkung.value = null;
            },
            onFinish: () => {
                tagLaeuft.value = false;
            },
        },
    );
};

// ─── Massenbearbeitung ────────────────────────────────────────────────
const gewaehlteIds = ref(new Set());
const bulkAktion = ref('');
const bulkWert = ref('');
const bulkLaeuft = ref(false);
const bulkLoeschBestaetigung = ref(false);

const seitenIds = computed(() => (props.verlinkungen?.data ?? []).map((v) => v.id));
const alleAufSeiteGewaehlt = computed(
    () => seitenIds.value.length > 0 && seitenIds.value.every((id) => gewaehlteIds.value.has(id)),
);
const anzahlGewaehlt = computed(() => gewaehlteIds.value.size);

const toggleEine = (id) => {
    const neue = new Set(gewaehlteIds.value);
    if (neue.has(id)) neue.delete(id);
    else neue.add(id);
    gewaehlteIds.value = neue;
};

const toggleAlleAufSeite = () => {
    const neue = new Set(gewaehlteIds.value);
    if (alleAufSeiteGewaehlt.value) {
        seitenIds.value.forEach((id) => neue.delete(id));
    } else {
        seitenIds.value.forEach((id) => neue.add(id));
    }
    gewaehlteIds.value = neue;
};

const auswahlZuruecksetzen = () => {
    gewaehlteIds.value = new Set();
    bulkAktion.value = '';
    bulkWert.value = '';
};

// URLs der aktuell sichtbaren Seite in die Zwischenablage kopieren.
// Tom-Workflow: 100+ Tabs via Chrome-Plugin oeffnen, dafuer braucht
// er die URLs als Textliste. Eine Seite = max. 500 (pro_seite-Limit).
// Wenn Auswahl aktiv ist, werden nur die ausgewaehlten kopiert.
const kopierStatus = ref('');
const urlsKopieren = async () => {
    const quelle = (props.verlinkungen?.data ?? []);
    const urls = (gewaehlteIds.value.size > 0
        ? quelle.filter((v) => gewaehlteIds.value.has(v.id))
        : quelle
    ).map((v) => v.verlinkende_url).filter(Boolean);

    if (urls.length === 0) {
        kopierStatus.value = 'Keine URLs zum Kopieren.';
        setTimeout(() => { kopierStatus.value = ''; }, 2500);
        return;
    }

    try {
        await navigator.clipboard.writeText(urls.join('\n'));
        kopierStatus.value = `${urls.length} URL${urls.length === 1 ? '' : 's'} kopiert`;
    } catch (e) {
        kopierStatus.value = 'Konnte nicht kopieren (Browser blockiert)';
    }
    setTimeout(() => { kopierStatus.value = ''; }, 2500);
};

// Reset bei Kundenwechsel
watch(() => props.aktiver_kunde, () => auswahlZuruecksetzen());

const bulkOptionenWert = computed(() => {
    switch (bulkAktion.value) {
        case 'linkart_setzen':
            return props.filter_optionen.linkart.map((w) => ({ wert: w, label: props.vokabular.linkart_labels[w] }));
        case 'empfehlung_setzen':
            return props.filter_optionen.empfehlung.map((w) => ({ wert: w, label: props.vokabular.empfehlung_labels[w] }));
        case 'status_setzen':
            return props.filter_optionen.status.map((w) => ({ wert: w, label: props.vokabular.status_labels[w] }));
        case 'tag_hinzufuegen':
        case 'tag_entfernen':
            return (props.verfuegbare_tags ?? []).map((t) => ({ wert: t.id, label: t.name }));
        default:
            return [];
    }
});

const benoetigtWert = computed(() =>
    ['linkart_setzen', 'empfehlung_setzen', 'status_setzen', 'tag_hinzufuegen', 'tag_entfernen'].includes(bulkAktion.value),
);

watch(bulkAktion, () => { bulkWert.value = ''; });

// ─── Chunked Bulk-Loop mit Fortschritts-Modal (Linkquellen-Pattern) ─
// Tom-Wunsch: bei 500er-Bulks live sehen wo es steht und abbrechen
// koennen. Auswahl wird in Chunks aufgeteilt, pro Chunk ein JSON-POST,
// pro Antwort wird das Fortschritts-Modal aktualisiert. Bei
// "abbrechen" laeuft der gerade gestartete Chunk noch zu Ende.
const fortschritt = reactive({
    offen: false,
    label: '',
    total: 0,
    done: 0,
    erfolge: 0,
    fehler: [],
    abbrechen: false,
    fertig: false,
    extra: null,
});

const bulkInChunks = async (routeName, payload, label, chunkSize = 25) => {
    if (!gewaehlteIds.value.size) return;
    const ids = Array.from(gewaehlteIds.value);

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
                kunde_kuerzel: props.aktiver_kunde,
                verlinkung_ids: chunk,
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
    router.reload({ only: ['verlinkungen', 'sistrix_status', 'kennzahlen'] });
};

const fortschrittSchliessen = () => {
    fortschritt.offen = false;
    if (!fortschritt.abbrechen) auswahlZuruecksetzen();
};

// Sistrix-Pre-Modal mit den 4 Stufen (analog Linkquellen-Liste).
const SISTRIX_LABEL = { si: 'SI', alter: 'Alter', dp: 'DP', alles: 'Alles (SI+Alter+DP)' };
const sistrixPreModalOffen = ref(false);
const sistrixPreTeil = ref('si');

const sistrixPreOeffnen = (teil) => {
    if (!gewaehlteIds.value.size) return;
    sistrixPreTeil.value = teil;
    sistrixPreModalOffen.value = true;
};

const sistrixPreKostenGesamt = computed(() => {
    const proDomain = props.sistrix_credits_pro_teil?.[sistrixPreTeil.value] ?? 0;
    // Worst-Case: jede gewaehlte Verlinkung ist eine eigene Domain. Pro
    // Domain einmal abgerechnet (Backend dedupliziert).
    return proDomain * sistrixDomainsImBulk.value;
});

const sistrixBudgetReicht = computed(() => {
    if (!props.sistrix_status) return true;
    return sistrixPreKostenGesamt.value <= props.sistrix_status.credits_verbleibend;
});

const sistrixPreAnwenden = () => {
    sistrixPreModalOffen.value = false;
    bulkInChunks(
        'linkprofil.verlinkungen.bulk-sistrix',
        { teile: sistrixPreTeil.value },
        `Sistrix abrufen: ${SISTRIX_LABEL[sistrixPreTeil.value]}`,
        10, // wie Linkquellen-Liste: bei "alles" 3 API-Calls je Domain, kleinere Chunks
    );
};

const bulkErreichbarkeitAnwenden = () => {
    bulkInChunks(
        'linkprofil.verlinkungen.bulk-erreichbarkeit',
        {},
        'Erreichbarkeit prüfen',
        25,
    );
};

// ─── Linkart aus Wissensbasis ─────────────────────────────────────────
const sendeLinkartAusWissen = () => bulkInChunks(
    'linkprofil.verlinkungen.bulk-linkart-wissen',
    {},
    'Linkart aus Wissensbasis übernehmen',
    100,
);

// ─── Linkart per KI ───────────────────────────────────────────────────
const sendeLinkartKi = () => bulkInChunks(
    'linkprofil.verlinkungen.bulk-linkart-ki',
    {},
    'Linkart per KI klassifizieren',
    50,
);

// ─── Empfehlung per KI ────────────────────────────────────────────────
const sendeEmpfehlungKi = () => bulkInChunks(
    'linkprofil.verlinkungen.bulk-empfehlung-ki',
    {},
    'Empfehlung per KI vorschlagen',
    25,
);

// ─── Workflow: alle 5 Schritte fuer die Auswahl in einem Rutsch ─────
// Tom-Wunsch: nach Re-Import + Aufraeumen viele neue Verlinkungen
// systematisch durch Erreichbarkeit -> SI -> Linkart-Wissen -> Linkart-KI
// -> Empfehlung-KI schicken, ohne 5x klicken zu muessen.
const WORKFLOW_PHASEN = [
    { key: 'erreichbarkeit', label: '1. Erreichbarkeit prüfen', route: 'linkprofil.verlinkungen.bulk-erreichbarkeit', payload: {}, chunkSize: 25, kosten: 'kostenlos' },
    { key: 'sistrix', label: '2. Sistrix SI abrufen', route: 'linkprofil.verlinkungen.bulk-sistrix', payload: { teile: 'si' }, chunkSize: 10, kosten: '1 Credit/Domain' },
    { key: 'linkart-wissen', label: '3. Linkart aus Wissensbasis', route: 'linkprofil.verlinkungen.bulk-linkart-wissen', payload: {}, chunkSize: 100, kosten: 'kostenlos' },
    { key: 'linkart-ki', label: '4. Linkart per KI klassifizieren', route: 'linkprofil.verlinkungen.bulk-linkart-ki', payload: {}, chunkSize: 50, kosten: 'KI-Token' },
    { key: 'empfehlung-ki', label: '5. Empfehlung per KI vorschlagen', route: 'linkprofil.verlinkungen.bulk-empfehlung-ki', payload: {}, chunkSize: 25, kosten: 'KI-Token' },
];

const workflow = reactive({
    offen: false,
    phaseIdx: 0,
    phaseDone: 0,
    phaseTotal: 0,
    gesamtIds: 0,
    phasenLog: [], // [{ key, label, erfolge, fehler[], abgebrochen }]
    abbrechen: false,
    fertig: false,
});

const workflowOeffnen = () => {
    if (!gewaehlteIds.value.size) return;
    workflow.offen = true;
    workflow.fertig = false;
    workflow.abbrechen = false;
    workflow.phaseIdx = -1; // -1 = noch nicht gestartet, User bestaetigt im Modal
    workflow.phasenLog = [];
    workflow.gesamtIds = gewaehlteIds.value.size;
};

const workflowStarten = async () => {
    const ids = Array.from(gewaehlteIds.value);
    if (ids.length === 0) return;

    for (const [idx, phase] of WORKFLOW_PHASEN.entries()) {
        if (workflow.abbrechen) break;
        workflow.phaseIdx = idx;
        workflow.phaseDone = 0;
        workflow.phaseTotal = ids.length;

        const log = { key: phase.key, label: phase.label, erfolge: 0, fehler: [], abgebrochen: false };

        for (let i = 0; i < ids.length; i += phase.chunkSize) {
            if (workflow.abbrechen) break;
            const chunk = ids.slice(i, i + phase.chunkSize);
            try {
                const res = await window.axios.post(route(phase.route), {
                    kunde_kuerzel: props.aktiver_kunde,
                    verlinkung_ids: chunk,
                    ...phase.payload,
                }, { headers: { Accept: 'application/json' } });
                const data = res.data ?? {};
                log.erfolge += data.erfolge ?? data.erreichbar ?? chunk.length;
                if (Array.isArray(data.fehler) && data.fehler.length) {
                    log.fehler.push(...data.fehler);
                }
                if (data.abgebrochen) {
                    log.abgebrochen = true;
                    log.fehler.push('Vorzeitig abgebrochen (z.B. Sistrix-Wochenkontingent erschoepft).');
                    workflow.phaseDone = Math.min(i + chunk.length, ids.length);
                    break;
                }
            } catch (e) {
                log.fehler.push(`Chunk ab Position ${i + 1}: ${e.message || 'Netzwerkfehler'}`);
            }
            workflow.phaseDone = Math.min(i + phase.chunkSize, ids.length);
        }

        workflow.phasenLog.push(log);

        // Wenn diese Phase wegen Kontingent abgebrochen wurde, weitere
        // Phasen ueberspringen (z.B. Sistrix leer -> KI-Schritte machen
        // ohne SI keinen Mehrwert).
        if (log.abgebrochen) break;
    }

    workflow.fertig = true;
    router.reload({ only: ['verlinkungen', 'sistrix_status', 'kennzahlen'] });
};

const workflowSchliessen = () => {
    workflow.offen = false;
    if (workflow.fertig) auswahlZuruecksetzen();
};

// Eindeutige Domains in der aktuellen Auswahl (fuer Sistrix-Kostenvorschau).
const sistrixDomainsImBulk = computed(() => {
    const seenDomains = new Set();
    (props.verlinkungen?.data ?? []).forEach((v) => {
        if (gewaehlteIds.value.has(v.id)) seenDomains.add(v.domain);
    });
    return seenDomains.size;
});

const sendeBulk = () => {
    if (!gewaehlteIds.value.size || !bulkAktion.value) return;
    if (benoetigtWert.value && bulkWert.value === '') return;

    if (bulkAktion.value === 'loeschen' && !bulkLoeschBestaetigung.value) {
        bulkLoeschBestaetigung.value = true;
        return;
    }

    bulkLaeuft.value = true;
    router.post(
        route('linkprofil.verlinkungen.bulk'),
        {
            kunde_kuerzel: props.aktiver_kunde,
            verlinkung_ids: Array.from(gewaehlteIds.value),
            aktion: bulkAktion.value,
            wert: bulkWert.value === '' ? null : bulkWert.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => auswahlZuruecksetzen(),
            onFinish: () => {
                bulkLaeuft.value = false;
                bulkLoeschBestaetigung.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Linkprofil" />

    <LamLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-800">Linkprofil</h1>
                    <div class="mt-1 text-sm text-slate-500">
                        <span v-if="aktiver_kunde">
                            {{ kennzahlen.anzahl_gesamt }} Verlinkung{{ kennzahlen.anzahl_gesamt === 1 ? '' : 'en' }}
                            <span v-if="kennzahlen.anzahl_neu > 0" class="text-emerald-700"> · {{ kennzahlen.anzahl_neu }} neu</span>
                            <span v-if="kennzahlen.anzahl_ohne_empfehlung > 0" class="text-amber-700"> · {{ kennzahlen.anzahl_ohne_empfehlung }} ohne Empfehlung</span>
                        </span>
                        <span v-else>Kunde wählen, um die Verlinkungen zu sehen.</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('linkprofil.domain-wissen')"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        title="Kundenübergreifende Domain-Klassifikationen verwalten"
                    >
                        Domain-Wissen
                    </Link>
                    <Link
                        v-if="aktiver_kunde"
                        :href="route('linkprofil.snapshots', { kunde: aktiver_kunde })"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        title="Verlinkungs-Stand einfrieren und mit früheren Snapshots vergleichen"
                    >
                        Snapshots
                    </Link>
                    <Link
                        v-if="aktiver_kunde"
                        :href="route('linkprofil.statistik', { kunde: aktiver_kunde })"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        title="Verteilungen, Top-Domains und Tag-Wolke"
                    >
                        Statistik
                    </Link>
                    <a
                        v-if="aktiver_kunde"
                        :href="route('linkprofil.export', { kunde: aktiver_kunde })"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        title="Aktueller Stand als Excel exportieren (12 Spalten + Statistik)"
                    >
                        Excel
                    </a>
                    <button
                        v-if="aktiver_kunde && (verlinkungen?.data?.length ?? 0) > 0"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        :title="`URLs der aktuell sichtbaren Seite in die Zwischenablage (max. ${verlinkungen?.per_page ?? 25} pro Seite). Wenn Auswahl aktiv, nur ausgewählte.`"
                        @click="urlsKopieren"
                    >
                        <span v-if="kopierStatus" class="text-emerald-700">✓ {{ kopierStatus }}</span>
                        <span v-else>URLs kopieren</span>
                    </button>
                    <button
                        v-if="aktiver_kunde"
                        type="button"
                        :disabled="!!ladeText"
                        class="inline-flex items-center gap-1.5 rounded border border-thoxan-300 bg-white px-4 py-2 text-sm font-medium text-thoxan-700 hover:bg-thoxan-50 disabled:cursor-wait disabled:opacity-60"
                        title="Sitewide-Cluster zusammenfassen und Domains klassifizieren. Neue Imports werden automatisch zu bestehenden Clustern dazugezählt."
                        @click="oeffneAufraeumen"
                    >
                        Aufräumen
                    </button>
                    <button
                        type="button"
                        :disabled="!aktiver_kunde"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        title="Historische Linkprofil-Excel importieren — Bewertungen lernen die KI mit"
                        @click="oeffneHistorie"
                    >
                        Historie importieren
                    </button>
                    <button
                        type="button"
                        :disabled="!aktiver_kunde"
                        class="rounded bg-thoxan-600 px-4 py-2 text-sm font-bold text-white hover:bg-thoxan-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                        @click="oeffneImport"
                    >
                        + CSV importieren
                    </button>
                </div>
            </div>
        </template>

        <!-- Filter. Einheitliches Schema mit Linkquellen-Liste und Linkoptionen-Pool:
             - Container rounded-lg border bg-white p-4
             - Schnellfilter-Zeile: Suche (3) | Kunde (6) | Erreichbarkeit (3)
             - Chip-Reihen: Linkart, Empfehlung, Status, Quelle (untereinander)
             - "Weitere Filter" klappbar: Neu/Alt + Follow + Bool-Optionen -->
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
                    <button v-if="filterAnzahl" @click="filterZuruecksetzen" class="rounded border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700">
                        zurücksetzen ({{ filterAnzahl }})
                    </button>
                </div>
            </div>

            <!-- ZEILE 1: Schnellfilter (Suche, Kunde, Erreichbarkeit) -->
            <div class="mt-3 grid grid-cols-12 gap-3">
                <div class="col-span-12 md:col-span-3">
                    <label class="lam-filter-label">Volltext URL / Linktext</label>
                    <input v-model="lokalerFilter.suche" type="text" class="lam-filter-input mt-1" placeholder="z.B. spam" />
                </div>
                <div class="col-span-12 md:col-span-6">
                    <label class="lam-filter-label flex items-baseline gap-1.5">
                        Kunde
                        <span class="text-[10px] font-normal text-slate-400">Klick = wechseln</span>
                    </label>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <button
                            v-for="k in kunden"
                            :key="k.kuerzel"
                            type="button"
                            @click="gewaehlterKunde = k.kuerzel"
                            :title="k.name"
                            :class="['lam-filter-chip font-medium transition', gewaehlterKunde === k.kuerzel ? 'bg-thoxan-600 text-white' : 'bg-thoxan-50 text-thoxan-700 ring-1 ring-thoxan-200 hover:bg-thoxan-100']"
                        >{{ k.kuerzel }}</button>
                        <span v-if="!kunden.length" class="text-xs text-slate-400">Noch keine Kunden.</span>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="lam-filter-label">Erreichbarkeit</label>
                    <select v-model="lokalerFilter.erreichbarkeit" class="lam-filter-input mt-1 bg-white">
                        <option value="">alle</option>
                        <option value="erreichbar">erreichbar</option>
                        <option value="weg">nicht erreichbar</option>
                        <option value="ungeprueft">ungeprüft</option>
                    </select>
                </div>
            </div>

            <!-- ZEILE 2..5: Chip-Filter-Reihen (Linkart, Empfehlung, Status, Quelle) -->
            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Linkart
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur diese · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <button v-if="lokalerFilter.linkart.length" type="button" @click="gruppeAlleAbwaehlen('linkart')" class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50" title="Alle Linkarten abwählen">alle</button>
                    <button
                        v-for="wert in filter_optionen.linkart"
                        :key="`la-${wert}`"
                        type="button"
                        @click="filterToggle('linkart', wert, $event)"
                        :class="['lam-filter-chip transition', lokalerFilter.linkart.includes(wert) ? 'bg-thoxan-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                    >{{ vokabular.linkart_labels[wert] }}</button>
                </div>
            </div>

            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Empfehlung
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur diese · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <button v-if="lokalerFilter.empfehlung.length" type="button" @click="gruppeAlleAbwaehlen('empfehlung')" class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50" title="Alle Empfehlungen abwählen">alle</button>
                    <button
                        v-for="wert in filter_optionen.empfehlung"
                        :key="`emp-${wert}`"
                        type="button"
                        @click="filterToggle('empfehlung', wert, $event)"
                        :class="['lam-filter-chip transition', lokalerFilter.empfehlung.includes(wert) ? 'bg-thoxan-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                    >{{ vokabular.empfehlung_labels[wert] }}</button>
                </div>
            </div>

            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Status
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur dieser · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <button v-if="lokalerFilter.status.length" type="button" @click="gruppeAlleAbwaehlen('status')" class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50" title="Alle Stati abwählen">alle</button>
                    <button
                        v-for="wert in filter_optionen.status"
                        :key="`st-${wert}`"
                        type="button"
                        @click="filterToggle('status', wert, $event)"
                        :class="['lam-filter-chip transition', lokalerFilter.status.includes(wert) ? 'bg-thoxan-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                    >{{ vokabular.status_labels[wert] }}</button>
                </div>
            </div>

            <div class="mt-3">
                <label class="lam-filter-label flex items-baseline gap-1.5">
                    Quelle
                    <span class="text-[10px] font-normal text-slate-400">Klick = nur diese · Shift/Ctrl = mehrere</span>
                </label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <button v-if="lokalerFilter.importquelle.length" type="button" @click="gruppeAlleAbwaehlen('importquelle')" class="lam-filter-chip border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50" title="Alle Quellen abwählen">alle</button>
                    <button
                        v-for="wert in filter_optionen.importquelle"
                        :key="`q-${wert}`"
                        type="button"
                        @click="filterToggle('importquelle', wert, $event)"
                        :class="['lam-filter-chip transition', lokalerFilter.importquelle.includes(wert) ? 'bg-thoxan-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                    >{{ vokabular.importquelle_labels[wert] }}</button>
                </div>
            </div>

            <!-- WEITERE FILTER (klappbar): Neu/Alt, Follow + Bool-Optionen -->
            <div v-if="weitereFilterOffen" class="mt-3 border-t border-slate-100 pt-3">
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label">Neu/Alt</label>
                        <select v-model="lokalerFilter.neu_alt" class="lam-filter-input mt-1 bg-white">
                            <option value="">alle</option>
                            <option value="neu">neu</option>
                            <option value="alt">alt</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="lam-filter-label" title="follow gibt Linkjuice, nofollow nicht. Aus Sistrix-Export importiert.">Follow</label>
                        <select v-model="lokalerFilter.follow" class="lam-filter-input mt-1 bg-white">
                            <option value="">alle</option>
                            <option value="follow">nur follow</option>
                            <option value="nofollow">nur nofollow</option>
                            <option value="unbekannt">nur unbekannt</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="lam-filter-label">Zusätzliche Optionen</label>
                        <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1.5 text-sm text-slate-700">
                            <label class="flex items-center gap-2">
                                <input v-model="lokalerFilter.ohne_empfehlung" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                <span>ohne Empfehlung</span>
                            </label>
                            <label class="flex items-center gap-2" title="Linkziel-URL wurde im HTML der Quellseite nicht gefunden">
                                <input v-model="lokalerFilter.nur_link_verloren" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                <span>Link verloren</span>
                            </label>
                            <label class="flex items-center gap-2" title="Verlinkungen ohne Ankertext (z.B. Bild-Links, oder reduzierte Exporte)">
                                <input v-model="lokalerFilter.ohne_linktext" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                <span>ohne Linktext</span>
                            </label>
                            <label class="flex items-center gap-2" title="Verlinkungen ohne Ziel-URL (z.B. GSC Top-linking-sites liefert keine Deep-URL)">
                                <input v-model="lokalerFilter.ohne_ziel_url" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                <span>ohne Ziel-URL</span>
                            </label>
                            <label class="flex items-center gap-2" title="Verlinkungen ohne klassifizierte Linkart">
                                <input v-model="lokalerFilter.ohne_linkart" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                <span>ohne Linkart</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <div v-if="importBericht" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm">
                <div class="font-semibold text-emerald-900">Letzter Import</div>
                <div class="mt-1 text-emerald-800">
                    URL-Spalte
                    <span class="font-mono">{{ importBericht.header?.[importBericht.spalten?.url] }}</span>
                    <span v-if="importBericht.spalten?.linktext !== null && importBericht.spalten?.linktext !== undefined">
                        , Linktext-Spalte
                        <span class="font-mono">{{ importBericht.header?.[importBericht.spalten?.linktext] }}</span>
                    </span>.
                    Insgesamt {{ importBericht.gesamt_zeilen }} Zeilen verarbeitet.
                </div>
                <ul v-if="importBericht.fehler_details?.length" class="mt-2 list-inside list-disc text-emerald-900">
                    <li v-for="(eintrag, idx) in importBericht.fehler_details.slice(0, 5)" :key="idx">
                        Zeile {{ eintrag.zeile }}: {{ eintrag.grund }}
                    </li>
                    <li v-if="importBericht.fehler_details.length > 5">
                        + {{ importBericht.fehler_details.length - 5 }} weitere Fehler
                    </li>
                </ul>
            </div>


            <!-- Bulk-Toolbar -->
            <div v-if="verlinkungen && anzahlGewaehlt > 0" class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-thoxan-300 bg-thoxan-50 p-3 text-sm">
                <span class="font-semibold text-thoxan-900">{{ anzahlGewaehlt }} ausgewählt</span>
                <select v-model="bulkAktion" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                    <option value="">Aktion wählen…</option>
                    <option value="linkart_setzen">Linkart setzen</option>
                    <option value="empfehlung_setzen">Empfehlung setzen</option>
                    <option value="status_setzen">Status setzen</option>
                    <option value="tag_hinzufuegen">Tag hinzufügen</option>
                    <option value="tag_entfernen">Tag entfernen</option>
                    <option value="pool_uebernehmen">In Linkquellen-Pool übernehmen</option>
                    <option value="massnahme_uebernehmen">Als Maßnahme übernehmen (live, ins Monitoring)</option>
                    <option value="loeschen">Löschen</option>
                </select>
                <select v-if="benoetigtWert" v-model="bulkWert" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                    <option value="">— wählen —</option>
                    <option v-for="opt in bulkOptionenWert" :key="`bw-${opt.wert}`" :value="opt.wert">
                        {{ opt.label }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="bulkLaeuft || !bulkAktion || (benoetigtWert && !bulkWert)"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-semibold text-white shadow-sm',
                        bulkAktion === 'loeschen'
                            ? (bulkLoeschBestaetigung ? 'bg-rose-700 hover:bg-rose-800' : 'bg-rose-600 hover:bg-rose-700')
                            : 'bg-thoxan-700 hover:bg-thoxan-800',
                        'disabled:cursor-not-allowed disabled:bg-slate-300',
                    ]"
                    @click="sendeBulk"
                >
                    {{ bulkLaeuft ? 'Läuft…' : (bulkAktion === 'loeschen' && bulkLoeschBestaetigung ? 'Wirklich löschen?' : 'Anwenden') }}
                </button>
                <button
                    type="button"
                    :disabled="fortschritt.offen"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    title="HTTP-Check pro Verlinkung, kostet keine API-Credits"
                    @click="bulkErreichbarkeitAnwenden"
                >
                    Erreichbarkeit prüfen
                </button>
                <template v-if="sistrix_status">
                    <span class="h-4 w-px bg-thoxan-300"></span>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-thoxan-700">Sistrix</span>
                    <button type="button" :disabled="fortschritt.offen" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50" title="Sichtbarkeitsindex je Domain: 1 Credit" @click="sistrixPreOeffnen('si')">SI · 1</button>
                    <button type="button" :disabled="fortschritt.offen" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50" title="Sichtbar-seit je Domain: 10 Credits" @click="sistrixPreOeffnen('alter')">Alter · 10</button>
                    <button type="button" :disabled="fortschritt.offen" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50" title="Verlinkende Domains je Domain: 25 Credits" @click="sistrixPreOeffnen('dp')">DP · 25</button>
                    <button type="button" :disabled="fortschritt.offen" class="rounded-md border border-thoxan-300 bg-thoxan-50 px-2.5 py-1.5 text-xs font-medium text-thoxan-700 hover:bg-thoxan-100 disabled:opacity-50" title="Alles in einem Rutsch je Domain: 36 Credits" @click="sistrixPreOeffnen('alles')">Alles · 36</button>
                </template>
                <span class="h-4 w-px bg-thoxan-300"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider text-thoxan-700">KI</span>
                <button
                    type="button"
                    :disabled="fortschritt.offen"
                    class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    title="Setzt die Linkart auf den Wert aus der Domain-Wissensbasis, wenn dort ein Eintrag existiert. Kein API-Call."
                    @click="sendeLinkartAusWissen"
                >
                    Linkart aus Wissen
                </button>
                <button
                    type="button"
                    :disabled="fortschritt.offen"
                    class="rounded-md border border-thoxan-300 bg-white px-2.5 py-1.5 text-xs font-medium text-thoxan-700 hover:bg-thoxan-50 disabled:opacity-50"
                    title="KI klassifiziert pro Domain in eine der 17 Linkarten. Hohe-Confidence-Treffer (≥0.8) wandern in die Wissensbasis."
                    @click="sendeLinkartKi"
                >
                    Linkart (KI)
                </button>
                <button
                    type="button"
                    :disabled="fortschritt.offen"
                    class="rounded-md border border-thoxan-300 bg-thoxan-50 px-2.5 py-1.5 text-xs font-medium text-thoxan-700 hover:bg-thoxan-100 disabled:opacity-50"
                    title="KI schlägt pro Verlinkung eine Empfehlung vor (lassen, ändern, löschen, disavow, unsicher). Spam-Linkart -> automatisch disavow."
                    @click="sendeEmpfehlungKi"
                >
                    Empfehlung (KI)
                </button>
                <span class="h-4 w-px bg-thoxan-300"></span>
                <button
                    type="button"
                    :disabled="fortschritt.offen || workflow.offen"
                    class="rounded-md border border-emerald-500 bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                    title="Alle 5 Schritte hintereinander: Erreichbarkeit, Sistrix-SI, Linkart aus Wissen, Linkart-KI, Empfehlung-KI"
                    @click="workflowOeffnen"
                >
                    ▶ Alle Schritte
                </button>
                <button
                    type="button"
                    class="ml-auto rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                    @click="auswahlZuruecksetzen"
                >
                    Auswahl aufheben
                </button>
            </div>

            <!-- Verlinkungs-Tabelle -->
            <div v-if="verlinkungen" class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="w-full table-auto divide-y divide-slate-200 [&_tr>*:not(:first-child)]:border-l [&_tr>*:not(:first-child)]:border-slate-100">
                    <colgroup>
                        <col class="w-10" />               <!-- checkbox -->
                        <col class="w-[170px]" />          <!-- Domain -->
                        <col class="w-[260px]" />          <!-- URL -->
                        <col class="w-[60px]" />           <!-- Wie oft -->
                        <col class="w-[170px]" />          <!-- Linktext -->
                        <col class="w-[130px]" />          <!-- Linkart -->
                        <col class="w-[110px]" />          <!-- Empfehlung -->
                        <col class="w-[110px]" />          <!-- Status -->
                        <col class="w-[48px]" />           <!-- ↻ -->
                        <col class="w-[64px]" />           <!-- SI -->
                        <col class="w-[140px]" />          <!-- Tags -->
                        <col class="w-[160px]" />          <!-- Bemerkung -->
                        <col class="w-[56px]" />           <!-- Neu -->
                        <col class="w-[120px]" />          <!-- Quelle -->
                    </colgroup>
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-center">
                                <input
                                    type="checkbox"
                                    :checked="alleAufSeiteGewaehlt"
                                    class="rounded border-slate-300 text-thoxan-700 focus:ring-thoxan-500"
                                    @change="toggleAlleAufSeite"
                                />
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('domain')">
                                Domain {{ sortierIndikator('domain') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('url')">
                                URL {{ sortierIndikator('url') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('wie_oft')" title="Anzahl Verlinkungen mit gleicher Domain bei diesem Kunden">
                                Wie oft {{ sortierIndikator('wie_oft') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('linktext')">
                                Linktext {{ sortierIndikator('linktext') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('linkart')">
                                Linkart {{ sortierIndikator('linkart') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('empfehlung')">
                                Empfehlung {{ sortierIndikator('empfehlung') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('status')">
                                Status {{ sortierIndikator('status') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('erreichbarkeit')" title="HTTP-Erreichbarkeit der Quell-URL und ob die Ziel-URL noch im HTML steht">
                                ↻ {{ sortierIndikator('erreichbarkeit') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('sistrix_si')" title="Sistrix Sichtbarkeitsindex (pro Domain, gecached)">
                                SI {{ sortierIndikator('sistrix_si') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('tags')" title="Sortierung nach Anzahl Tags">
                                Tags {{ sortierIndikator('tags') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('bemerkung')">
                                Bemerkung {{ sortierIndikator('bemerkung') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('neu')">
                                Neu {{ sortierIndikator('neu') }}
                            </th>
                            <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-100" @click="spalteSortierung('quelle')">
                                Quelle {{ sortierIndikator('quelle') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white text-sm">
                        <tr v-for="v in verlinkungen.data" :key="v.id" :class="['hover:bg-slate-50', gewaehlteIds.has(v.id) ? 'bg-thoxan-50/40' : '']">
                            <td class="px-2 py-2 align-top text-center">
                                <input
                                    type="checkbox"
                                    :checked="gewaehlteIds.has(v.id)"
                                    class="rounded border-slate-300 text-thoxan-700 focus:ring-thoxan-500"
                                    @change="toggleEine(v.id)"
                                />
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="max-w-[160px] break-words" :title="v.domain">{{ v.domain }}</div>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="flex max-w-[260px] items-start gap-1">
                                    <a :href="v.verlinkende_url" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-thoxan-700 hover:underline" :title="v.verlinkende_url">
                                        {{ v.verlinkende_url }}
                                    </a>
                                    <a
                                        v-if="!v.ziel_url"
                                        :href="googleDeepUrlSuche(v.domain)"
                                        target="_blank"
                                        rel="noopener"
                                        class="shrink-0 rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        :title="`Deep-URL via Google-Suche: site:${v.domain} intext:${aktiver_kunde_name}`"
                                    >🔍</a>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-700">
                                <span v-if="v.wie_oft > 1" class="inline-flex items-center rounded-full bg-amber-100 px-2 text-xs font-semibold text-amber-800" :title="`${v.wie_oft} Verlinkungen mit Domain ${v.domain}`">
                                    {{ v.wie_oft }}×
                                </span>
                                <span v-else class="text-slate-400">1</span>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="flex max-w-[160px] items-center gap-1">
                                    <span class="truncate" :title="v.linktext">{{ v.linktext }}</span>
                                    <span
                                        v-if="v.is_follow === false"
                                        class="shrink-0 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800"
                                        title="nofollow — kein Linkjuice"
                                    >nf</span>
                                    <span
                                        v-else-if="v.is_follow === true"
                                        class="shrink-0 rounded bg-emerald-100 px-1 text-[10px] font-semibold text-emerald-800"
                                        title="follow"
                                    >f</span>
                                </div>
                            </td>

                            <!-- Linkart inline-edit -->
                            <td class="px-3 py-2 align-top text-slate-700">
                                <select
                                    v-if="istInline(v, 'linkart')"
                                    v-model="editWert"
                                    autofocus
                                    class="w-full rounded border border-thoxan-500 px-1 py-0.5 text-sm focus:border-thoxan-700 focus:ring-thoxan-700"
                                    @change="speichereInline(v, 'linkart', editWert)"
                                    @blur="abbrechenInline"
                                    @keydown.esc="abbrechenInline"
                                >
                                    <option value="">—</option>
                                    <option v-for="wert in filter_optionen.linkart" :key="`la-opt-${wert}`" :value="wert">
                                        {{ vokabular.linkart_labels[wert] }}
                                    </option>
                                </select>
                                <button
                                    v-else
                                    type="button"
                                    class="w-full whitespace-nowrap rounded px-1 py-0.5 text-left hover:bg-thoxan-50"
                                    @click="startInline(v, 'linkart')"
                                >
                                    <span v-if="v.linkart">{{ vokabular.linkart_labels[v.linkart] ?? v.linkart }}</span>
                                    <span v-else class="text-slate-400">—</span>
                                </button>
                            </td>

                            <!-- Empfehlung inline-edit -->
                            <td class="px-3 py-2 align-top text-slate-700">
                                <select
                                    v-if="istInline(v, 'empfehlung')"
                                    v-model="editWert"
                                    autofocus
                                    class="w-full rounded border border-thoxan-500 px-1 py-0.5 text-sm focus:border-thoxan-700 focus:ring-thoxan-700"
                                    @change="speichereInline(v, 'empfehlung', editWert)"
                                    @blur="abbrechenInline"
                                    @keydown.esc="abbrechenInline"
                                >
                                    <option value="">—</option>
                                    <option v-for="wert in filter_optionen.empfehlung" :key="`emp-opt-${wert}`" :value="wert">
                                        {{ vokabular.empfehlung_labels[wert] }}
                                    </option>
                                </select>
                                <button
                                    v-else
                                    type="button"
                                    class="w-full whitespace-nowrap rounded px-1 py-0.5 text-left hover:bg-thoxan-50"
                                    @click="startInline(v, 'empfehlung')"
                                >
                                    <span v-if="v.empfehlung">{{ vokabular.empfehlung_labels[v.empfehlung] ?? v.empfehlung }}</span>
                                    <span v-else class="text-slate-400">—</span>
                                </button>
                            </td>

                            <!-- Status inline-edit -->
                            <td class="px-3 py-2 align-top text-slate-700">
                                <select
                                    v-if="istInline(v, 'status')"
                                    v-model="editWert"
                                    autofocus
                                    class="w-full rounded border border-thoxan-500 px-1 py-0.5 text-sm focus:border-thoxan-700 focus:ring-thoxan-700"
                                    @change="speichereInline(v, 'status', editWert)"
                                    @blur="abbrechenInline"
                                    @keydown.esc="abbrechenInline"
                                >
                                    <option value="">—</option>
                                    <option v-for="wert in filter_optionen.status" :key="`st-opt-${wert}`" :value="wert">
                                        {{ vokabular.status_labels[wert] }}
                                    </option>
                                </select>
                                <button
                                    v-else
                                    type="button"
                                    class="w-full whitespace-nowrap rounded px-1 py-0.5 text-left hover:bg-thoxan-50"
                                    @click="startInline(v, 'status')"
                                >
                                    <span v-if="v.status">{{ vokabular.status_labels[v.status] ?? v.status }}</span>
                                    <span v-else class="text-slate-400">—</span>
                                </button>
                            </td>

                            <!-- Erreichbarkeit (read-only) -->
                            <td class="px-3 py-2 align-top text-center">
                                <span
                                    v-if="v.letzter_check_am === null"
                                    class="inline-block h-3 w-3 rounded-full bg-slate-200"
                                    title="noch nicht geprüft"
                                ></span>
                                <span
                                    v-else-if="v.letzter_http_erreichbar && v.linkziel_gefunden === false"
                                    class="inline-block h-3 w-3 rounded-full bg-amber-500"
                                    :title="'Erreichbar (HTTP ' + v.letzter_http_status + '), Linkziel nicht mehr im HTML — vermutlich entfernt'"
                                ></span>
                                <span
                                    v-else-if="v.letzter_http_erreichbar"
                                    class="inline-block h-3 w-3 rounded-full bg-emerald-500"
                                    :title="'Erreichbar (HTTP ' + v.letzter_http_status + ')'"
                                ></span>
                                <span
                                    v-else
                                    class="inline-block h-3 w-3 rounded-full bg-rose-500"
                                    :title="v.letzter_http_status ? ('Nicht erreichbar (HTTP ' + v.letzter_http_status + ')') : 'Nicht erreichbar (Timeout / Fehler)'"
                                ></span>
                            </td>

                            <!-- Sistrix SI (read-only, vom Subquery) -->
                            <td class="px-3 py-2 align-top text-right" :class="siFarbe(v.sistrix_erfasst_am)">
                                <span v-if="siFormatiert(v.sistrix_si) !== null" :title="v.sistrix_erfasst_am ? `erfasst ${new Date(v.sistrix_erfasst_am).toLocaleDateString('de-DE')}` : ''">
                                    {{ siFormatiert(v.sistrix_si) }}
                                </span>
                                <span v-else class="text-slate-300">—</span>
                            </td>

                            <!-- Tags-Spalte -->
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="flex flex-wrap items-center gap-1">
                                    <span
                                        v-for="tag in (v.tags ?? [])"
                                        :key="`tag-${v.id}-${tag.id}`"
                                        class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700"
                                    >
                                        {{ tag.name }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-xs text-slate-500 hover:border-thoxan-500 hover:text-thoxan-700"
                                        :title="(v.tags?.length ? 'Tags bearbeiten' : 'Tag hinzufügen')"
                                        @click="oeffneTagModal(v)"
                                    >
                                        {{ v.tags?.length ? '+' : '+ Tag' }}
                                    </button>
                                </div>
                            </td>

                            <!-- Bemerkung inline-edit -->
                            <td class="px-3 py-2 align-top text-slate-700">
                                <textarea
                                    v-if="istInline(v, 'bemerkung')"
                                    v-model="editWert"
                                    rows="2"
                                    autofocus
                                    class="w-full rounded border border-thoxan-500 px-2 py-1 text-sm focus:border-thoxan-700 focus:ring-thoxan-700"
                                    @blur="speichereInline(v, 'bemerkung', editWert)"
                                    @keydown.esc="abbrechenInline"
                                    @keydown.enter.exact.prevent="speichereInline(v, 'bemerkung', editWert)"
                                ></textarea>
                                <button
                                    v-else
                                    type="button"
                                    class="block w-full max-w-[180px] rounded px-1 py-0.5 text-left text-xs hover:bg-thoxan-50"
                                    :title="v.bemerkung || 'Bemerkung hinzufügen'"
                                    @click="startInline(v, 'bemerkung')"
                                >
                                    <span v-if="v.bemerkung" class="block max-h-10 overflow-hidden text-slate-700">{{ v.bemerkung }}</span>
                                    <span v-else class="text-slate-400">—</span>
                                </button>
                            </td>

                            <td class="px-3 py-2 align-top text-center">
                                <span v-if="v.ist_neu" class="inline-flex items-center rounded-full bg-emerald-100 px-2 text-xs font-semibold text-emerald-800">neu</span>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-500">
                                <span class="whitespace-nowrap" :title="vokabular.importquelle_labels[v.imported_from] ?? v.imported_from">{{ v.imported_from ? (importquelleKurz[v.imported_from] ?? v.imported_from) : '—' }}</span>
                            </td>
                        </tr>
                        <tr v-if="!verlinkungen.data.length">
                            <td colspan="14" class="px-3 py-8 text-center text-sm text-slate-500">
                                Keine Verlinkungen für diesen Filter. CSV importieren oder Filter lockern.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination + Pro-Seite -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    <div>
                        Zeige <span class="font-medium text-slate-900">{{ verlinkungen.from ?? 0 }}–{{ verlinkungen.to ?? 0 }}</span>
                        von <span class="font-medium text-slate-900">{{ verlinkungen.total }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2">
                            <span class="text-xs text-slate-500">Pro Seite</span>
                            <select v-model.number="lokalerFilter.pro_seite" class="rounded-md border border-slate-300 bg-white pl-2 pr-7 py-1 text-sm">
                                <option v-for="opt in filter_optionen.pro_seite" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </label>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="link in verlinkungen.links"
                                :key="link.label"
                                :href="link.url ?? ''"
                                v-html="link.label"
                                :class="[
                                    'rounded px-2 py-1 text-xs',
                                    link.active ? 'bg-thoxan-700 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                                    !link.url ? 'cursor-not-allowed opacity-50' : '',
                                ]"
                                :preserve-state="true"
                                :preserve-scroll="true"
                            />
                        </div>
                    </div>
                </div>
            </div>

        <div v-if="!aktiver_kunde" class="mt-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Lege zuerst einen Kunden an. Sobald ein Kunde gewählt ist, steht die Tabelle mit Filtern bereit.
        </div>

        <!-- CSV-Import-Modal -->
        <div
            v-if="importOffen"
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="schliesseImport"
        >
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">CSV importieren</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Kunde: <span class="font-medium text-slate-700">{{ gewaehlterKunde }}</span>.
                        Dubletten (gleiche URL nach Normalisierung) werden automatisch übersprungen.
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Quelle
                        </label>
                        <select
                            v-model="importForm.quelle"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-thoxan-500 focus:ring-thoxan-500"
                        >
                            <option v-for="q in quellenOptionen" :key="q.wert" :value="q.wert">
                                {{ q.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            CSV-Datei(en)
                        </label>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            multiple
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                            @change="dateiGewaehlt"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Mehrere Dateien gleichzeitig erlaubt (alle werden mit der gewählten Quelle importiert). Max. 20 MB pro Datei, max. 20 Dateien.
                        </p>
                        <ul v-if="importForm.dateien.length" class="mt-2 space-y-1 text-xs">
                            <li
                                v-for="(d, idx) in importForm.dateien"
                                :key="`imp-${idx}-${d.name}`"
                                class="flex items-center gap-2 rounded bg-slate-50 px-2 py-1"
                            >
                                <span class="flex-1 truncate text-slate-700">{{ d.name }}</span>
                                <span class="text-slate-500">{{ (d.size / 1024).toFixed(0) }} KB</span>
                                <button
                                    type="button"
                                    class="text-slate-400 hover:text-rose-600"
                                    :title="`${d.name} aus Auswahl entfernen`"
                                    @click="entferneImportDatei(idx)"
                                >
                                    ✕
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-if="importFehler" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ importFehler }}
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <button
                        type="button"
                        :disabled="importLaeuft"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        @click="schliesseImport"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        :disabled="importLaeuft || !importForm.dateien.length"
                        class="rounded-md bg-thoxan-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-thoxan-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        @click="sendeImport"
                    >
                        {{ importLaeuft ? 'Lädt…' : (importForm.dateien.length > 1 ? `${importForm.dateien.length} Dateien importieren` : 'Importieren') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Historien-Import-Modal -->
        <div v-if="historieOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4" @click.self="schliesseHistorie">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Historische Linkprofil-Excel importieren</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Kunde: <span class="font-medium text-slate-700">{{ gewaehlterKunde }}</span>.
                        Jede Zeile aktualisiert oder ergänzt eine Verlinkung UND füttert die kundenübergreifende
                        Wissensbasis (Linkart + Empfehlung als <em>manuell</em>-Klassifikation).
                        Bestehende Bewertungen werden nicht überschrieben.
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Excel-Datei
                        </label>
                        <input
                            type="file"
                            accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                            @change="historieDateiGewaehlt"
                        />
                        <p class="mt-1 text-xs text-slate-500">Max. 50 MB. Header-basiertes Mapping (deutsch).</p>
                    </div>
                    <div v-if="historieFehler" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ historieFehler }}
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <button type="button" :disabled="historieLaeuft" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="schliesseHistorie">
                        Abbrechen
                    </button>
                    <button type="button" :disabled="historieLaeuft || !historieForm.datei" class="rounded-md bg-thoxan-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-thoxan-800 disabled:cursor-not-allowed disabled:bg-slate-300" @click="sendeHistorie">
                        {{ historieLaeuft ? 'Verarbeite…' : 'Importieren' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Sistrix-Pre-Modal: Kosten-Vorschau, dann startet bulkInChunks -->
        <div v-if="sistrixPreModalOffen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click="sistrixPreModalOffen = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-base font-semibold text-slate-800">Sistrix abrufen: {{ SISTRIX_LABEL[sistrixPreTeil] }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Cache-Hits werden nicht erneut abgerechnet, das Maximum ist {{ sistrixPreKostenGesamt.toLocaleString('de-DE') }} Credits.
                    Unbekannte Domains werden automatisch als Linkquelle im Pool angelegt (Herkunft: aus Linkprofil-Analyse).
                </p>
                <dl class="mt-4 space-y-1.5 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Verlinkungen ausgewählt</dt>
                        <dd class="font-medium text-slate-800">{{ anzahlGewaehlt.toLocaleString('de-DE') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Eindeutige Domains</dt>
                        <dd class="font-medium text-slate-800">{{ sistrixDomainsImBulk.toLocaleString('de-DE') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Credits je Domain</dt>
                        <dd class="font-medium text-slate-800">{{ sistrix_credits_pro_teil?.[sistrixPreTeil] ?? '?' }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-1.5">
                        <dt class="font-semibold text-slate-700">Maximalkosten</dt>
                        <dd class="font-bold text-slate-900">{{ sistrixPreKostenGesamt.toLocaleString('de-DE') }} Credits</dd>
                    </div>
                    <div v-if="sistrix_status" class="flex justify-between text-xs">
                        <dt class="text-slate-500">Verbleibendes Wochenbudget</dt>
                        <dd :class="sistrixBudgetReicht ? 'text-slate-600' : 'font-semibold text-red-600'">
                            {{ sistrix_status.credits_verbleibend.toLocaleString('de-DE') }}
                            / {{ sistrix_status.wochenkontingent?.toLocaleString('de-DE') ?? '?' }}
                        </dd>
                    </div>
                </dl>
                <p v-if="!sistrixBudgetReicht" class="mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    Achtung: Das Wochenkontingent reicht nicht für alle ausgewählten Domains. Der Lauf bricht ab, sobald keine Credits mehr da sind.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="sistrixPreModalOffen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    <button
                        @click="sistrixPreAnwenden"
                        :disabled="anzahlGewaehlt === 0"
                        class="rounded bg-thoxan-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-thoxan-700 disabled:bg-slate-400"
                    >Jetzt abrufen</button>
                </div>
            </div>
        </div>

        <!-- Workflow-Modal: alle 5 Schritte hintereinander.
             Vor dem Start zeigt es die Phasen + Kosten-Hinweise,
             waehrend des Laufs den Phase-Counter + aktuelle Phase,
             nach Fertigstellung den Ergebnis-Log pro Phase. -->
        <div v-if="workflow.offen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-800">
                    Workflow: alle 5 Schritte für {{ workflow.gesamtIds.toLocaleString('de-DE') }} Verlinkungen
                </h3>

                <!-- Vor Start: Phasen-Uebersicht + Bestaetigung -->
                <div v-if="workflow.phaseIdx === -1" class="mt-4 space-y-3">
                    <p class="text-xs text-slate-500">
                        Die folgenden Schritte laufen nacheinander für die {{ workflow.gesamtIds }} ausgewählten Verlinkungen.
                        Jeder Schritt kann mehrere Minuten dauern.
                    </p>
                    <ol class="space-y-1 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                        <li v-for="phase in WORKFLOW_PHASEN" :key="phase.key" class="flex items-baseline justify-between">
                            <span class="text-slate-700">{{ phase.label }}</span>
                            <span class="text-xs text-slate-400">{{ phase.kosten }}</span>
                        </li>
                    </ol>
                    <p v-if="sistrix_status" class="text-xs text-slate-500">
                        Sistrix-Budget verbleibend: <span class="font-mono">{{ sistrix_status.credits_verbleibend.toLocaleString('de-DE') }}</span>
                        — Schritt 2 verbraucht max. {{ (sistrixDomainsImBulk).toLocaleString('de-DE') }} Credits.
                    </p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="workflow.offen = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                        <button @click="workflowStarten" class="rounded bg-emerald-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-emerald-700">Workflow starten</button>
                    </div>
                </div>

                <!-- Waehrend Lauf: Phase-Counter + aktuelle Phase -->
                <div v-else class="mt-4 space-y-3">
                    <div class="text-sm text-slate-700">
                        <span v-if="!workflow.fertig">
                            Phase {{ workflow.phaseIdx + 1 }} von {{ WORKFLOW_PHASEN.length }}: <span class="font-medium">{{ WORKFLOW_PHASEN[workflow.phaseIdx]?.label }}</span>
                        </span>
                        <span v-else class="font-medium text-emerald-700">Workflow abgeschlossen.</span>
                    </div>

                    <!-- Phase-Progress-Bar -->
                    <div v-if="!workflow.fertig">
                        <div class="flex items-baseline justify-between text-xs text-slate-500">
                            <span>{{ workflow.phaseDone.toLocaleString('de-DE') }} / {{ workflow.phaseTotal.toLocaleString('de-DE') }}</span>
                            <span>{{ Math.round((workflow.phaseDone / Math.max(workflow.phaseTotal, 1)) * 100) }} %</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-emerald-500 transition-all" :style="{ width: ((workflow.phaseDone / Math.max(workflow.phaseTotal, 1)) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <!-- Phasen-Log (abgeschlossene + laufende) -->
                    <ul class="space-y-1 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                        <li v-for="(phase, idx) in WORKFLOW_PHASEN" :key="phase.key" class="flex items-baseline justify-between">
                            <span :class="idx < workflow.phaseIdx ? 'text-slate-500' : (idx === workflow.phaseIdx ? 'font-medium text-slate-800' : 'text-slate-400')">
                                <span v-if="idx < workflow.phaseIdx || (idx === workflow.phaseIdx && workflow.fertig)">✓</span>
                                <span v-else-if="idx === workflow.phaseIdx">▸</span>
                                <span v-else>·</span>
                                {{ phase.label }}
                            </span>
                            <span class="text-xs text-slate-500">
                                <span v-if="workflow.phasenLog[idx]">
                                    {{ workflow.phasenLog[idx].erfolge }} ok
                                    <span v-if="workflow.phasenLog[idx].fehler.length" class="text-red-600">· {{ workflow.phasenLog[idx].fehler.length }} Fehler</span>
                                    <span v-if="workflow.phasenLog[idx].abgebrochen" class="text-amber-700">· abgebrochen</span>
                                </span>
                            </span>
                        </li>
                    </ul>

                    <details v-if="workflow.phasenLog.some(l => l.fehler.length)" class="text-xs">
                        <summary class="cursor-pointer text-slate-500 hover:text-slate-700">Fehlerdetails anzeigen</summary>
                        <div class="mt-1 max-h-40 overflow-y-auto rounded border border-red-200 bg-red-50 p-2 text-red-700">
                            <div v-for="(log, idx) in workflow.phasenLog" :key="`fhl-${idx}`">
                                <div v-if="log.fehler.length" class="mb-1">
                                    <div class="font-semibold">{{ log.label }}</div>
                                    <ul>
                                        <li v-for="(f, i) in log.fehler" :key="i" class="truncate" :title="f">{{ f }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            v-if="!workflow.fertig"
                            @click="workflow.abbrechen = true"
                            :disabled="workflow.abbrechen"
                            class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >{{ workflow.abbrechen ? 'Wird abgebrochen…' : 'Abbrechen' }}</button>
                        <button
                            v-if="workflow.fertig"
                            @click="workflowSchliessen"
                            class="rounded bg-emerald-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-emerald-700"
                        >Schließen</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fortschritts-Modal fuer Chunk-basierte Bulks (Erreichbarkeit, Sistrix) -->
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
                        v-if="!fortschritt.fertig"
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

        <!-- Tag-Modal -->
        <div
            v-if="tagModalOffen"
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="schliesseTagModal"
        >
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Tags bearbeiten</h3>
                    <p v-if="tagModalVerlinkung" class="mt-1 break-all text-xs text-slate-500">
                        {{ tagModalVerlinkung.verlinkende_url }}
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="tag in tagAuswahl"
                            :key="`mod-${tag.id}`"
                            class="inline-flex items-center gap-1 rounded-full bg-thoxan-100 px-2 py-0.5 text-xs text-thoxan-800"
                        >
                            {{ tag.name }}
                            <button type="button" class="text-thoxan-600 hover:text-thoxan-900" @click="entferneTag(tag)">×</button>
                        </span>
                        <span
                            v-for="name in tagNeueNamen"
                            :key="`neu-${name}`"
                            class="inline-flex items-center gap-1 rounded-full border border-dashed border-thoxan-400 bg-thoxan-50 px-2 py-0.5 text-xs text-thoxan-700"
                            title="Neu angelegt beim Speichern"
                        >
                            {{ name }}
                            <button type="button" class="text-thoxan-500 hover:text-thoxan-900" @click="entferneNeuenTag(name)">×</button>
                        </span>
                        <span v-if="!tagAuswahl.length && !tagNeueNamen.length" class="text-xs text-slate-400">
                            Noch keine Tags
                        </span>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Tag suchen oder neu anlegen
                        </label>
                        <input
                            v-model="tagEingabe"
                            type="text"
                            placeholder="Topp-Link, Disavow-Kandidat, …"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-thoxan-500 focus:ring-thoxan-500"
                            @keydown.enter.prevent="passenderNeuVorschlag ? fuegeNeuenTagHinzu(passenderNeuVorschlag) : (tagVorschlaege[0] && fuegeTagHinzu(tagVorschlaege[0]))"
                        />
                        <ul v-if="tagVorschlaege.length || passenderNeuVorschlag" class="mt-1 max-h-40 overflow-auto rounded-md border border-slate-200 bg-white text-sm">
                            <li
                                v-for="tag in tagVorschlaege"
                                :key="`vs-${tag.id}`"
                                class="cursor-pointer px-3 py-1 hover:bg-thoxan-50"
                                @click="fuegeTagHinzu(tag)"
                            >
                                {{ tag.name }}
                                <span v-if="tag.kunde_kuerzel" class="text-xs text-slate-400">({{ tag.kunde_kuerzel }})</span>
                                <span v-else class="text-xs text-slate-400">(global)</span>
                            </li>
                            <li
                                v-if="passenderNeuVorschlag"
                                class="cursor-pointer border-t border-slate-100 px-3 py-1 text-thoxan-700 hover:bg-thoxan-50"
                                @click="fuegeNeuenTagHinzu(passenderNeuVorschlag)"
                            >
                                + „{{ passenderNeuVorschlag }}" als neuen Tag anlegen
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <button
                        type="button"
                        :disabled="tagLaeuft"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        @click="schliesseTagModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        :disabled="tagLaeuft"
                        class="rounded-md bg-thoxan-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-thoxan-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        @click="speichereTagModal"
                    >
                        {{ tagLaeuft ? 'Speichert…' : 'Speichern' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Globales Lade-Overlay: blockiert UI, waehrend teure Navigationen laufen
             (Aufraeumen/Re-Run macht KI-Klassifikation, sonst gibt es Sekunden
             ohne sichtbare Rueckmeldung). -->
        <div
            v-if="ladeText"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-center gap-3">
                    <svg class="h-6 w-6 animate-spin text-thoxan-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <h3 class="text-base font-semibold text-slate-900">Einen Moment …</h3>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ ladeText }}</p>
            </div>
        </div>
    </LamLayout>
</template>

