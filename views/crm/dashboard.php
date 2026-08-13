<?php $activeModul = 'dashboard'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Kontakte (CRM)</h1>
        <div class="thx-page-subtitle">Source of Truth fuer alle Kontakt- und Firmendaten. Brevo bleibt Versand-Engine.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmDashboard()" x-init="laden()">
    <template x-if="laedt">
        <div style="padding:40px;text-align:center;color:#94a3b8;">Lade …</div>
    </template>

    <template x-if="!laedt">
        <div>
            <!-- KPI-Kacheln -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
                <a href="/crm/kontakte" class="thx-card" style="text-decoration:none;color:inherit;padding:18px;display:block;">
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Kontakte</div>
                    <div style="font-size:1.8rem;font-weight:700;margin-top:6px;" x-text="stats.kontakte.toLocaleString('de-DE')"></div>
                    <div style="font-size:0.72rem;color:#94a3b8;margin-top:2px;">aktive Eintraege</div>
                </a>
                <a href="/crm/firmen" class="thx-card" style="text-decoration:none;color:inherit;padding:18px;display:block;">
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Firmen</div>
                    <div style="font-size:1.8rem;font-weight:700;margin-top:6px;" x-text="stats.firmen.toLocaleString('de-DE')"></div>
                </a>
                <a href="/crm/listen" class="thx-card" style="text-decoration:none;color:inherit;padding:18px;display:block;">
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Listen</div>
                    <div style="font-size:1.8rem;font-weight:700;margin-top:6px;" x-text="stats.listen"></div>
                </a>
                <a href="/crm/tags" class="thx-card" style="text-decoration:none;color:inherit;padding:18px;display:block;">
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Tags</div>
                    <div style="font-size:1.8rem;font-weight:700;margin-top:6px;" x-text="stats.tags"></div>
                </a>
            </div>

            <!-- Empty State Welcome -->
            <template x-if="stats.kontakte === 0">
                <div class="thx-card" style="padding:30px;text-align:center;">
                    <h2 style="margin:0 0 10px;font-size:1.2rem;color:#0f172a;">Willkommen im CRM</h2>
                    <p style="color:#475569;max-width:560px;margin:0 auto 18px;line-height:1.5;">
                        Noch keine Kontakte importiert. Starte mit der Brevo-Migration oder lege deinen ersten Kontakt manuell an.
                    </p>
                    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                        <?php if (\Core\Auth::can(CAP_CRM_MIGRATION)): ?>
                        <a href="/crm/migration" class="thx-btn thx-btn-primary">
                            Brevo-Migration starten
                        </a>
                        <?php endif; ?>
                        <a href="/crm/kontakte" class="thx-btn thx-btn-secondary">
                            Kontakt anlegen
                        </a>
                    </div>
                </div>
            </template>

            <template x-if="stats.kontakte > 0">
                <div class="thx-card" style="padding:20px;">
                    <h3 style="margin:0 0 8px;font-size:1rem;">Schnelleinstieg</h3>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="/crm/kontakte" class="thx-btn thx-btn-secondary">Kontakte durchsuchen</a>
                        <a href="/crm/segmente" class="thx-btn thx-btn-secondary">Segment erstellen</a>
                        <a href="/crm/dubletten" class="thx-btn thx-btn-secondary">Dubletten pruefen</a>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

<script>
function crmDashboard() {
    return {
        laedt: true,
        stats: { kontakte: 0, firmen: 0, listen: 0, tags: 0 },
        async laden() {
            this.laedt = true;
            try {
                const r = await fetch('/api/v1/crm/dashboard', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.stats = j.data;
            } catch (e) { /* still show defaults */ }
            this.laedt = false;
        }
    };
}
</script>
