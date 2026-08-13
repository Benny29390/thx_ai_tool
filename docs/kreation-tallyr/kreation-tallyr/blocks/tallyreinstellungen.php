<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$currentuserid = get_current_user_id();
$asana_token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
$has_asana_token = !empty($asana_token);
$kuerzel = get_user_meta($currentuserid, 'tallyr_kuerzel', true);
$tallyr_zeit = get_user_meta($currentuserid, 'tallyr_zeit', true) ?: 8;
$tallyr_capacity = get_user_meta($currentuserid, 'tallyr_capacity', true) ?: '';
?>

<div id="tallyr-settings">
    <div class="ts-header">
        <h2><i class='bx bx-cog'></i> Einstellungen</h2>
    </div>

    <div class="ts-sections">

        <!-- Profil -->
        <div class="ts-section">
            <h3>Profil</h3>
            <div class="ts-row">
                <label>Kürzel / Initialen</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="ts-kuerzel" value="<?php echo esc_attr($kuerzel); ?>" placeholder="z.B. TKI" style="width:120px !important;height:40px !important;padding:8px 12px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:14px !important;background:#fff !important;color:#333 !important;box-sizing:border-box !important;">
                    <button type="button" class="ts-save-btn" data-field="tallyr_kuerzel" data-input="ts-kuerzel" style="width:auto !important;height:40px;padding:8px 16px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;">Speichern</button>
                </div>
                <small>Wird als Kürzel in Projektplänen und Reports verwendet.</small>
            </div>
            <div class="ts-row">
                <label>Arbeitsstunden pro Tag</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" id="ts-zeit" value="<?php echo esc_attr($tallyr_zeit); ?>" min="1" max="24" step="0.5" style="width:100px !important;height:40px !important;padding:8px 12px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:14px !important;background:#fff !important;color:#333 !important;box-sizing:border-box !important;">
                    <button type="button" class="ts-save-btn" data-field="tallyr_zeit" data-input="ts-zeit" style="width:auto !important;height:40px;padding:8px 16px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;">Speichern</button>
                </div>
                <small>Für das Tagesdiagramm in der Zeiterfassung.</small>
            </div>
            <div class="ts-row">
                <label>Kapazität (Stunden pro Monat)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" id="ts-capacity" value="<?php echo esc_attr($tallyr_capacity); ?>" min="0" step="1" placeholder="z.B. 160" style="width:120px !important;height:40px !important;padding:8px 12px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:14px !important;background:#fff !important;color:#333 !important;box-sizing:border-box !important;">
                    <button type="button" class="ts-save-btn" data-field="tallyr_capacity" data-input="ts-capacity" style="width:auto !important;height:40px;padding:8px 16px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;">Speichern</button>
                </div>
                <small>Für Kapazitätsplanung im Dashboard. Wie viele Stunden stehen pro Monat zur Verfügung?</small>
            </div>
        </div>

        <!-- Asana -->
        <div class="ts-section">
            <h3>Asana Integration</h3>
            <div class="ts-row">
                <label>Personal Access Token</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="password" id="asana_token_input" value="<?php echo $has_asana_token ? '********' : ''; ?>" placeholder="Token einfügen..." style="flex:1 !important;height:40px !important;padding:8px 12px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:14px !important;background:#fff !important;color:#333 !important;box-sizing:border-box !important;width:100% !important;">
                    <button type="button" id="save_asana_token" style="width:auto !important;height:40px;padding:8px 16px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;white-space:nowrap;">Speichern</button>
                </div>
                <div id="asana_token_status" class="<?php echo $has_asana_token ? 'success' : ''; ?>" style="margin-top:4px;font-size:12px;">
                    <?php echo $has_asana_token ? 'Token gespeichert und verifiziert.' : ''; ?>
                </div>
                <small>Token erstellen: <a href="https://app.asana.com/0/my-apps" target="_blank">app.asana.com/0/my-apps</a></small>
            </div>
            <?php if ($has_asana_token): ?>
            <div class="ts-row">
                <button type="button" id="ts-refresh-asana" style="width:auto !important;padding:8px 14px;border:1px solid #ddd;border-radius:5px;font-size:12px;cursor:pointer;background:#f8f8f8;color:#555;display:inline-flex;align-items:center;gap:4px;"><i class='bx bx-refresh'></i> Asana Cache leeren</button>
                <small>Projekte und Tasks werden 1 Stunde gecached. Hier manuell aktualisieren.</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- Team Tags -->
        <div class="ts-section">
            <h3>Team / Verantwortliche</h3>
            <p style="font-size:12px;color:#888;margin:0 0 10px;">Kürzel die im Projektplanner als Verantwortliche verfügbar sind. WordPress-Benutzer werden automatisch übernommen.</p>
            <div id="ts-tags-list" style="margin-bottom:10px;">
                <?php
                $custom_tags = get_user_meta($currentuserid, 'tallyr_pp_tags', true);
                $custom_tags = $custom_tags ? json_decode($custom_tags, true) : array();
                if (is_array($custom_tags)):
                    foreach ($custom_tags as $tag):
                ?>
                    <span class="ts-tag" data-name="<?php echo esc_attr($tag['name']); ?>" data-kuerzel="<?php echo esc_attr($tag['kuerzel']); ?>" data-capacity="<?php echo esc_attr(isset($tag['capacity']) ? $tag['capacity'] : ''); ?>">
                        <?php echo esc_html($tag['kuerzel']); ?> <small style="color:#999;"><?php echo esc_html($tag['name']); ?></small>
                        <?php if (!empty($tag['capacity'])): ?><small style="color:#aaa;margin-left:2px;">(<?php echo (int)$tag['capacity']; ?>h)</small><?php endif; ?>
                        <i class="bx bx-x ts-tag-remove" style="cursor:pointer;margin-left:2px;color:#ccc;"></i>
                    </span>
                <?php
                    endforeach;
                endif;
                ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" id="ts-tag-kuerzel" placeholder="Kürzel (z.B. EXT)" style="width:100px !important;height:38px !important;padding:8px 10px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:13px !important;background:#fff !important;box-sizing:border-box !important;">
                <input type="text" id="ts-tag-name" placeholder="Name (z.B. Externer Texter)" style="flex:1 !important;height:38px !important;padding:8px 10px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:13px !important;background:#fff !important;box-sizing:border-box !important;">
                <input type="number" id="ts-tag-capacity" placeholder="h/Monat" step="1" min="0" style="width:90px !important;height:38px !important;padding:8px 10px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:13px !important;background:#fff !important;box-sizing:border-box !important;">
                <button type="button" id="ts-tag-add" style="width:auto !important;height:38px;padding:8px 14px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;">Hinzufügen</button>
            </div>
        </div>

        <!-- Textbausteine -->
        <?php if ($has_asana_token): ?>
        <div class="ts-section">
            <h3>Textbausteine (Asana Board)</h3>
            <p style="font-size:12px;color:#888;margin:0 0 10px;">Asana-Projekt mit Textbausteinen. Task-Namen werden als Autocomplete in der Beschreibung vorgeschlagen.</p>
            <?php $tb_project = get_user_meta($currentuserid, 'tallyr_pp_textbausteine_project', true); ?>
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px;">
                <select id="ts-tb-project" style="flex:1 !important;height:40px !important;padding:8px 10px !important;border:1px solid #ddd !important;border-radius:5px !important;font-size:13px !important;background:#fff !important;">
                    <option value="">Kein Projekt</option>
                </select>
                <button type="button" id="ts-tb-save" style="width:auto !important;height:40px;padding:8px 16px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;">Speichern</button>
                <button type="button" id="ts-tb-refresh" style="width:auto !important;height:40px;padding:8px 14px;border:1px solid #ddd;border-radius:5px;font-size:13px;cursor:pointer;background:#f8f8f8;color:#555;" title="Textbausteine neu laden"><i class='bx bx-refresh'></i></button>
            </div>
            <?php
            $tb_cache = get_transient('tallyr_pp_textbausteine_' . $currentuserid);
            $tb_count = is_array($tb_cache) ? count($tb_cache) : 0;
            ?>
            <small style="color:#aaa;font-size:11px;" id="ts-tb-status"><?php echo $tb_project ? $tb_count . ' Textbausteine gecached' : 'Noch nicht konfiguriert'; ?></small>
        </div>
        <?php endif; ?>

    </div>
</div>