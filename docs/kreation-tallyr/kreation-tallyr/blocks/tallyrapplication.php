<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

global $wpdb;
$currentuserid = get_current_user_id();
$table_name = $wpdb->prefix . 'tallyr_clients';
$sql = "SELECT * FROM $table_name WHERE userid = " . $currentuserid;
$clients = $wpdb->get_results($sql);

$tallyr_zeit = get_user_meta($currentuserid, 'tallyr_zeit', true);
if (!$tallyr_zeit) {
    $tallyr_zeit = 8;
}

//Sort clients A-Z
usort($clients, function($a, $b) {
    return strcasecmp($a->title, $b->title);
});


?>

<div data-time="<?php echo $tallyr_zeit; ?>" id="todaytimesdiagram">
    <div class="pie pieanimate" style=""><span id="todaytimesinhour"></span>/<?php echo $tallyr_zeit; ?></div>
</div>

<div id="neuropager">
    <div class="first">
        <div id="sidetitle">
            Kunde
        </div>
        <input type="text" class="searchin" placeholder="Suche Kunde">
        <div id="dbresults">
            <?php foreach ($clients as $pro) { ?>
                <div class="item">
                    <a data-id="<?php echo $pro->id; ?>" data-parentclient="<?php echo $pro->parentclient; ?>" data-stundensatz="<?php echo $pro->stundensatz; ?>" class="clientcho" href="#"><span class="ccolor" style="background:<?php echo $pro->hexcolor; ?>"></span><?php echo $pro->title; ?> (<?php echo $pro->shortdesc; ?>)</a>
                </div>
            <?php } ?>
                <a class="creat" href="https://tallyr.de/userdashboard/dashboard/?ccpage=view&pid=10"><i class='bx bx-plus'></i>Kunde anlegen</a>
        </div>
    </div>
    <div class="first" id="chopro">

        <div id="sidetitle" class="sidetitlepro">
            Projekt
        </div>
        <input type="text" class="searchin" placeholder="Suche Projekt">
        <div id="dbresultspro">
                <div class="results"></div>
                <a class="creat" id="createnewprojecter"><i class='bx bx-plus'></i>Projekt anlegen</a>
        </div>
    </div>
    <div class="first" id="chotimes">

        <div id="sidetitle" class="sidetitlepro">
            Zeiteinträge
        </div>
        <input type="text" class="searchin" placeholder="Suche Eintrag">
        <div id="dbresultstimes">
                <div class="results"></div>
                <a class="creat" id="createnewtime"><i class='bx bx-plus'></i>Zeit erfassen</a>
        </div>
    </div>
    <div class="second" id="captime">
        <div id="neuroheader">
            <div class="container">
                <h1>Zeit erfassen</h1>
            </div>
        </div>

        <div id="editproject">
            <form data-projectid="<?php echo $currentclient->id; ?>" id="capturetime">
                <div class="formgroup formgroup-description">
                    <label>Beschreibung<br><button data-id="description" class="kioutform">Mit KI ausformulieren</button></label>
                    <div class="description-wrapper">
                        <textarea placeholder="Kurze Beschreibung was gemacht wurde" id="description" name="description" required></textarea>
                        <button type="button" id="asana-tasks-btn" title="Asana Aufgaben anzeigen"><i class='bx bx-list-ul'></i></button>
                        <span id="asana-loading" style="display:none;"><i class='bx bx-loader-alt bx-spin'></i></span>
                    </div>
                </div>
                <div class="formgroup">
                    <label for="linknotice">Link / Notiz</label>
                    <input type="text" placeholder="Link oder Notiz" id="linknotice" name="linknotice">
                </div>
                <div class="formgroup">
                    <label>Stunden
                    <button id="starttimer">Start Timer</button>
                    </label>
                    <input step="0.01" placeholder="Stunden" id="time" type="number" name="time" required>
                </div>
                <hr>
                <div class="formgroup">
                    <label>Datum</label>
                    <input onfocus="this.showPicker()" placeholder="Datum" id="date" type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="formgroup">
                    <label>Stundensatz in €</label>
                    <input placeholder="Stundensatz" id="stundensatz" type="number" name="stundensatz" required>
                </div>
                <div class="formgroup">
                    <label>Rechnung an</label>
                    <select id="parentclient" name="parentclient">
                        <option value="0" disabled selected></option>
                        <?php foreach ($clients as $client) { ?>
                            <option value="<?php echo $client->id; ?>" <?php if ($currentclient->parentclient == $client->id) { echo "selected"; } ?>><?php echo $client->title; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <label class="checker" for="abrechenbar"><input type="checkbox" id="abrechenbar" name="abrechenbar"> <span>Nicht abrechenbar</span></label>
                <button disabled type="submit">Zeit erfassen</button>
            </form>
            </div>
    </div>
</div>

<div id="modalcreateproject" class="custommodal">
    <div class="modalcontentout">
    <div class="modalcontent">
        <i class="bx bx-x closemodal"></i>
        <h3>Projekt anlegen / bearbeiten</h3>
        <form data-projectid="0" id="createprojectform">
            <label>Projektname</label>
            <input required type="text" id="projecttitle" placeholder="Projektname">
            <label>Projektbeschreibung</label>
            <textarea id="projectdesc" placeholder="Projektbeschreibung"></textarea>
            <label>Projektstunden</label>
            <input type="number" id="projectmaxhours" placeholder="Verfügbare Stunden" step="0.01">
            <label>Stundensatz in €</label>
            <input type="number" id="projectstundensatz" placeholder="Stundensatz">
            <button id="createproject">Speichern</button>
        </form>
    </div>
    </div>
</div>