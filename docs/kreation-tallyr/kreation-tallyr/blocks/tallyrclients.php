<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$currentclientid = (INT)$_GET['client'];
$currenturl = get_permalink().'?ccpage=view&pid=10';

/* function createtable_clients() {
	// Check if db table exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_clients';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		title text NOT NULL,
		shortdesc text NOT NULL,
		hexcolor int NOT NULL,
		stundensatz int NOT NULL,
		parentclient int NOT NULL,
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		state int NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
} */

// get all projects from user
global $wpdb;
$table_name = $wpdb->prefix . 'tallyr_clients';
$sql = "SELECT * FROM $table_name WHERE userid = " . get_current_user_id();
$clients = $wpdb->get_results($sql);

// Check if user can see project
$submitstring = "Kunde anlegen";
if ($currentclientid) {
    // check if project exists in $projects
    $clientexists = false;
    foreach ($clients as $client) {
        if ($client->id == $currentclientid) {
            $clientexists = true;
            $currentclient = $client;
            $submitstring = "Kunde aktualisieren";
        }
    }
    if (!$clientexists) {
        $currentclientid = 0;
    }
}

// sort clients A-Z
usort($clients, function($a, $b) {
    // egal ob gross oder kleinschreibung
    return strcasecmp($a->title, $b->title);
});

// Get Asana token status
$asana_token = get_user_meta(get_current_user_id(), 'tallyr_asana_token', true);
$has_asana_token = !empty($asana_token);

?>

<!-- Client Table View -->
<div id="clients-table-view" <?php if ($currentclientid) echo 'style="display:none;"'; ?>>
    <div class="ct-header">
        <h2><i class='bx bx-group'></i> Kunden</h2>
        <div class="ct-actions">
            <button type="button" id="ct-add-row" class="ct-btn-add"><i class='bx bx-plus'></i> Neuer Kunde</button>
        </div>
    </div>
    <div class="ct-table-scroll">
        <table class="ct-table" id="ct-table">
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th>Kürzel</th>
                    <th>URL</th>
                    <th>€/h</th>
                    <th>Farbe</th>
                    <th>Rechnung an</th>
                    <th>Passwort</th>
                    <?php if ($has_asana_token): ?><th>Asana</th><?php endif; ?>
                    <th>Link</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="ct-table-body">
                <?php foreach ($clients as $c):
                    if ($c->state != 1) continue;
                    $link = $c->randomlink ? site_url('/times?id=') . $c->randomlink : '';
                    // Get Asana project names for display
                    $asana_display = '';
                    $asana_ids = '';
                    if ($has_asana_token && !empty($c->asana_project_id)) {
                        $asana_ids = $c->asana_project_id;
                        $count = count(array_filter(explode(',', $c->asana_project_id)));
                        $asana_display = $count . ' Projekt' . ($count > 1 ? 'e' : '');
                    }
                ?>
                <tr data-id="<?php echo $c->id; ?>" data-asana="<?php echo esc_attr($asana_ids); ?>">
                    <td class="ct-td-title"><div class="pp-cell pp-field" data-field="title" contenteditable="true"><?php echo esc_html($c->title); ?></div></td>
                    <td class="ct-td-short"><div class="pp-cell pp-field" data-field="shortdesc" contenteditable="true"><?php echo esc_html($c->shortdesc); ?></div></td>
                    <td class="ct-td-url"><div class="pp-cell pp-field" data-field="url" contenteditable="true"><?php echo str_replace("\n", '<br>', esc_html($c->url ?? '')); ?></div></td>
                    <td class="ct-td-rate"><div class="pp-cell pp-cell-num pp-field" data-field="stundensatz" contenteditable="true"><?php echo (int)$c->stundensatz; ?></div></td>
                    <td class="ct-td-color"><input type="color" class="ct-color pp-field" data-field="hexcolor" value="<?php echo esc_attr($c->hexcolor ?: '#cccccc'); ?>"></td>
                    <td class="ct-td-parent"><select class="ct-select pp-field" data-field="parentclient">
                        <option value="0">–</option>
                        <?php foreach ($clients as $pc):
                            if ($pc->state != 1) continue;
                            $sel = ($c->parentclient == $pc->id) ? ' selected' : '';
                        ?>
                            <option value="<?php echo $pc->id; ?>"<?php echo $sel; ?>><?php echo esc_html($pc->title); ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td class="ct-td-pw"><button type="button" class="ct-pw-btn" title="Passwort ändern"><i class='bx <?php echo !empty($c->pass) ? 'bx-lock-alt' : 'bx-lock-open-alt'; ?>'></i></button></td>
                    <?php if ($has_asana_token): ?>
                    <td class="ct-td-asana"><button type="button" class="ct-asana-btn" title="Asana Projekte"><?php echo $asana_display ? '<span class="ct-asana-count">' . $asana_display . '</span>' : '<i class="bx bx-link"></i>'; ?></button></td>
                    <?php endif; ?>
                    <td class="ct-td-link"><?php if ($link): ?><a href="<?php echo esc_url($link); ?>" target="_blank" class="ct-link-icon" title="<?php echo esc_attr($link); ?>"><i class='bx bx-link-external'></i></a><?php endif; ?></td>
                    <td class="ct-td-del"><button type="button" class="ct-delete-btn" title="Kunde löschen"><i class='bx bx-trash'></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="neuropager" <?php if (!$currentclientid) echo 'style="display:none;"'; ?>>
    <div class="first">

        <div id="sidetitle">
            Meine Kunden
        </div>
        <div id="dbresults">
            <?php foreach ($clients as $pro) { ?>
                <div class="item">
                    <a <?php if ($pro->id == $currentclient->id) { echo 'class="current"'; } ?> href="<?php echo $currenturl; ?>&client=<?php echo $pro->id; ?>"><?php echo $pro->title; ?></a>
                </div>
            <?php } ?>
                <a class="creat" id="createnewproject" href="<?php echo $currenturl; ?>"><i class='bx bx-plus'></i>Kunde anlegen</a>
        </div>
    </div>
    <div class="second">
        <div id="neuroheader">
            <div class="container">
                <?php if ($currentclientid) { ?>
                    <h1>Kunde bearbeiten</h1>
                <?php } else { ?>
                    <h1>Neuen Kunde anlegen</h1>
                <?php } ?>
            </div>
        </div>

        <div id="editproject">
            <form data-projectid="<?php echo $currentclient->id; ?>" id="createclient">
                <input value="<?php echo $currentclient->title; ?>" id="c_title" type="text" name="title" placeholder="Kundenname" required>
                <input value="<?php echo $currentclient->shortdesc; ?>" id="c_shortdesc" type="text" name="shortdesc" placeholder="Kürzel" required>
                <input value="<?php echo $currentclient->stundensatz; ?>" id="stundensatz" type="number" name="stundensatz" placeholder="Standard Stundensatz" required>
                <select id="parentclient" required name="parentclient">
                    <option disabled selected>Rechnung an:</option>
                    <?php foreach ($clients as $client) { ?>
                        <option value="<?php echo $client->id; ?>" <?php if ($currentclient->parentclient == $client->id) { echo "selected"; } ?>><?php echo $client->title; ?></option>
                    <?php } ?>
                </select>
                <div class="password-field-wrapper" style="position:relative;display:flex;">
                    <input value="" id="passworder" type="password" name="passworder" placeholder="<?php echo !empty($currentclient->pass) ? 'Passwort gesetzt (leer lassen = behalten)' : 'Passwort für Einsicht der Zeiten'; ?>" style="padding-right:40px;">
                    <button type="button" id="toggle-password-btn" title="Passwort anzeigen/verbergen" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);width:30px;height:30px;padding:0;background:transparent;border:none;color:#888;cursor:pointer;font-size:18px;" onclick="var inp=document.getElementById('passworder');var ico=this.querySelector('i');if(inp.type==='password'){inp.type='text';ico.className='bx bx-show';}else{inp.type='password';ico.className='bx bx-hide';}"><i class='bx bx-hide'></i></button>
                </div>
                <input value="<?php echo site_url('/times?id=').$currentclient->randomlink; ?>" id="randomlink" disabled type="text" name="randomlink" placeholder="Link">
                <?php
                // if no color make a random hex color
                $randomcolor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                if ($currentclient->hexcolor) {
                    $randomcolor = $currentclient->hexcolor;
                }
                ?>
                <input value="<?php echo $randomcolor; ?>" id="c_hexcolor" type="color" name="hexcolor" required>
                <?php if ($has_asana_token): ?>
                <div class="asana-project-select">
                    <label>Asana Projekte verknüpfen</label>
                    <div class="searchable-multiselect" id="asana_project_wrapper">
                        <input type="hidden" id="asana_project_id" name="asana_project_id" value="<?php echo esc_attr($currentclient->asana_project_id); ?>">
                        <div class="searchable-multiselect-display" tabindex="0">
                            <div class="selected-tags"></div>
                            <input type="text" class="searchable-multiselect-input" placeholder="Projekt suchen...">
                            <i class='bx bx-chevron-down'></i>
                        </div>
                        <div class="searchable-multiselect-dropdown">
                            <div class="searchable-multiselect-options"></div>
                        </div>
                    </div>
                    <small>Tasks aus diesen Projekten werden in der Beschreibung vorgeschlagen.</small>
                </div>
                <?php endif; ?>
                <button type="submit"><?php echo $submitstring; ?></button>
            </form>
            </div>
    </div>
</div>