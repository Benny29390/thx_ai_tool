<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();

// Linkoptionen merkt sich den zuletzt besuchten Tab (Pool oder Auswahl),
// damit ein Klick auf "Linkoptionen" im Menue dort weitermacht, wo man
// zuletzt war. Die Pool- und Liste-Seiten schreiben den Wert beim Mount.
const STORAGE_KEY_LINKOPTIONEN_TAB = 'lam_linkoptionen_tab';
const letzterLinkoptionenTab = ref(
    typeof window !== 'undefined'
        ? (window.localStorage?.getItem(STORAGE_KEY_LINKOPTIONEN_TAB) ?? 'index')
        : 'index',
);
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY_LINKOPTIONEN_TAB) letzterLinkoptionenTab.value = e.newValue ?? 'index';
    });
    // Auch im selben Tab reagieren, wenn die Vue-Seite den Wert frisch setzt
    window.addEventListener('lam:linkoptionen-tab', (e) => {
        letzterLinkoptionenTab.value = e.detail ?? 'index';
    });
}

const menueRoute = (item) => {
    if (item.route === 'linkoptionen.index' && letzterLinkoptionenTab.value === 'pool') {
        return route('linkoptionen.pool');
    }
    return route(item.route);
};

const flashErfolg = ref(null);
const flashFehler = ref(null);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.erfolg) {
            flashErfolg.value = flash.erfolg;
            setTimeout(() => (flashErfolg.value = null), 4000);
        }
        if (flash?.fehler) {
            flashFehler.value = flash.fehler;
            setTimeout(() => (flashFehler.value = null), 6000);
        }
    },
    { immediate: true, deep: true },
);

// Operative Navigation (weisse Hauptzeile)
// Reihenfolge folgt dem operativen Prozess von links (Recherche) nach rechts
// (Veröffentlichungs-Begleitung). Siehe docs/lam-briefing-01-menue-reihenfolge.md
const hauptnav = [
    { name: 'Dashboard', route: 'dashboard', enabled: true, phase: null, activePatterns: ['dashboard'] },
    { name: 'Linkprofil', route: 'linkprofil.index', enabled: true, phase: null, activePatterns: ['linkprofil.*'] },
    { name: 'Linkquellen', route: 'linkquellen.index', enabled: true, phase: null, activePatterns: ['linkquellen.*', 'tags.*', 'import.*'] },
    { name: 'Anbieter', route: 'anbieter.index', enabled: true, phase: null, activePatterns: ['anbieter.*', 'kontakte.*'] },
    { name: 'Linkakquise', route: 'linkakquise.index', enabled: true, phase: null, activePatterns: ['linkakquise.*'] },
    { name: 'Linkoptionen', route: 'linkoptionen.index', enabled: true, phase: null, activePatterns: ['linkoptionen.*'] },
    { name: 'Maßnahmen', route: 'massnahmen.index', enabled: true, phase: null, activePatterns: ['massnahmen.*'] },
    { name: 'Auslagen', route: 'auslagen.index', enabled: true, phase: null, activePatterns: ['auslagen.*'] },
    { name: 'Monitoring', route: 'monitoring.index', enabled: true, phase: null, activePatterns: ['monitoring.*'] },
    { name: 'Korrespondenz', route: 'korrespondenz.index', enabled: true, phase: null, activePatterns: ['korrespondenz.*'] },
];

// Admin- und Kontext-Items (dunkelblaue Top-Bar)
const adminnav = [
    { name: 'Kunden', route: 'kunden.index', enabled: true, phase: null, activePatterns: ['kunden.*', 'linkziele.*'] },
    { name: 'Einstellungen', route: 'einstellungen.index', enabled: true, phase: null, activePatterns: ['einstellungen.*'] },
];

const isActive = (item) => {
    if (!item.route) return false;
    const patterns = item.activePatterns ?? [item.route];

    return patterns.some((p) => {
        try { return route().current(p); } catch (e) { return false; }
    });
};

const userName = computed(() => page.props.auth?.user?.name ?? 'Gast');
const kuerzel = computed(() => page.props.aktiver_mitarbeiter ?? 'TKI');
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50">
        <div class="bg-thoxan-700 text-white">
            <div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8">
                <div class="flex h-11 items-center justify-end gap-1 text-sm">
                    <template v-for="item in adminnav" :key="item.name">
                        <Link
                            v-if="item.enabled"
                            :href="route(item.route)"
                            :class="[
                                'rounded px-3 py-1.5 text-sm font-bold transition',
                                isActive(item)
                                    ? 'bg-white text-thoxan-700'
                                    : 'text-thoxan-50 hover:bg-thoxan-600',
                            ]"
                        >
                            {{ item.name }}
                        </Link>
                        <span
                            v-else
                            :title="item.phase + ' — noch nicht aktiv'"
                            class="cursor-not-allowed rounded px-3 py-1.5 text-sm font-medium text-thoxan-200"
                        >
                            {{ item.name }}
                            <span class="ml-1 text-xs text-thoxan-300">({{ item.phase }})</span>
                        </span>
                    </template>

                    <span class="mx-2 h-5 border-l border-thoxan-500"></span>

                    <span class="flex items-center gap-2">
                        <span class="rounded bg-thoxan-600 px-2 py-0.5 font-mono text-xs font-bold text-white">{{ kuerzel }}</span>
                        <span class="text-thoxan-50">{{ userName }}</span>
                    </span>
                </div>
            </div>
        </div>

        <nav class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <Link :href="route('dashboard')" class="flex items-center" title="LAM Linkaufbau-Management">
                        <img src="/assets/thoxan-logo.svg" alt="Thoxan" class="h-12 w-auto" />
                    </Link>

                    <div class="hidden md:flex md:items-center md:gap-1">
                        <template v-for="item in hauptnav" :key="item.name">
                            <Link
                                v-if="item.enabled"
                                :href="menueRoute(item)"
                                :class="[
                                    'rounded-md px-3 py-2 text-sm font-bold transition',
                                    isActive(item)
                                        ? 'bg-thoxan-600 text-white'
                                        : 'text-slate-700 hover:bg-thoxan-50 hover:text-thoxan-700',
                                ]"
                            >
                                {{ item.name }}
                            </Link>
                            <span
                                v-else
                                :title="item.phase + ' — noch nicht aktiv'"
                                class="cursor-not-allowed rounded-md px-3 py-2 text-sm font-medium text-slate-400"
                            >
                                {{ item.name }}
                                <span class="ml-1 text-xs text-slate-300">({{ item.phase }})</span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-[1920px] px-4 py-5 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-[1920px] flex-1 flex-col px-4 py-6 sm:px-6 lg:px-8">
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div v-if="flashErfolg" class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                    {{ flashErfolg }}
                </div>
            </Transition>
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div v-if="flashFehler" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                    {{ flashFehler }}
                </div>
            </Transition>

            <slot />
        </main>

        <footer class="mt-auto border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-[1920px] px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-start gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-thoxan-700">LAM</span>
                        <span class="text-slate-400">Linkaufbau-Management der Thoxan Communications GmbH</span>
                    </div>
                    <span class="italic text-thoxan-600">frischer wind im netz.</span>
                </div>
            </div>
        </footer>
    </div>
</template>
