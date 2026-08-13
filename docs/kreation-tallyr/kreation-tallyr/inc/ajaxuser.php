<?php
use Orhanerday\OpenAi\OpenAi;

// NEW FUNCTION

// uf_gettodaytimes
add_action('wp_ajax_uf_gettodaytimes', 'uf_gettodaytimes');

function uf_gettodaytimes() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$currentuserid = get_current_user_id();
	$today = date('Y-m-d');

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$times = $wpdb->get_results("SELECT * FROM $table_name WHERE workdate = '$today' AND userid = ".$currentuserid . " AND state = 1");

	$todaytimesinhour = 0;
	foreach ($times as $time) {
		$todaytimesinhour += $time->hours;
	}

	$todaytimesinhour = number_format($todaytimesinhour, 2, '.', '');

	echo $todaytimesinhour;
	exit();
};

// uf_markasbilled
add_action('wp_ajax_uf_markasbilled', 'uf_markasbilled');

function uf_markasbilled() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$times_id = (INT)$_POST['times_id'];
	$billed = (INT)$_POST['billed'];
	$currentuserid = get_current_user_id();

	$message = 'Zeit wurde als nicht abgerechnet markiert.';

	if ($billed) {
		$billed =  current_time('mysql', 1);
		$message = 'Zeit wurde als abgerechnet markiert.';
	} else {
		$billed = NULL;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$wpdb->update( 
		$table_name, 
		array( 
			'billed_on' => $billed,
		),
		array( 'id' => $times_id, 'userid' => $currentuserid )
	);

	echo $message;

	exit();
};


function createtable_clients() {
	// Check if db table exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_clients';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		title text NOT NULL,
		pass varchar(255),
		randomlink varchar(255),
		shortdesc text NOT NULL,
		hexcolor varchar(255) NOT NULL,
		stundensatz int NOT NULL,
		parentclient int NOT NULL,
		created datetime,
		state int NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
function createtable_projects() {
	// Check if db table exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_projects';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		clientid bigint(20) NOT NULL,
		description text,
		maxhours int,
		stundensatz int,
		title text NOT NULL,
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		state int NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
function createtable_times() {
	// Check if db table exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		clientid bigint(20) NOT NULL,
		projectid bigint(20) NOT NULL,
		description TEXT NOT NULL,
		hours DECIMAL(10,2) NOT NULL,
		workdate DATE NOT NULL,
		stundensatz INT NOT NULL,
		parentclient INT NOT NULL,
		notbillable TINYINT(1) DEFAULT 0,
		created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
		billed_on DATE,
		state INT NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

}

//deleteTableDevelopt('tallyr_projects');
//createtable_clients();
//createtable_projects();
/* createtable_times();
exit(); */

// Add asana_project_id column to clients table if not exists
function update_clients_table_asana() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_clients';
	$column = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'asana_project_id'");
	if (empty($column)) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN asana_project_id VARCHAR(255) DEFAULT NULL");
	}
	$url_col = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'url'");
	if (empty($url_col)) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN url VARCHAR(500) DEFAULT ''");
	}
}
update_clients_table_asana();

add_action('wp_ajax_uf_createclient', 'uf_createclient');

function uf_createclient() {

	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	$c_title = normform_sanitize_text_or_array_field($_POST['c_title']);
	$c_shortdesc = normform_sanitize_text_or_array_field($_POST['c_shortdesc']);
	$stundensatz = (INT)$_POST['stundensatz'];
	$c_hexcolor = normform_sanitize_text_or_array_field($_POST['c_hexcolor']);
	$password_raw = isset($_POST['password']) ? trim($_POST['password']) : '';
	$parentclient = (INT)$_POST['parentclient'];
	$client_id = (INT)$_POST['client_id'];
	$asana_project_id = isset($_POST['asana_project_id']) ? sanitize_text_field($_POST['asana_project_id']) : '';
	$currentuserid = get_current_user_id();

	// check if client exists and user is owner
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_clients';
	$client_exists = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $client_id AND userid = ".$currentuserid);

	if (!$client_exists) {
		// create client - hash password (even if empty)
		$password = wp_hash_password($password_raw);
		$wpdb->insert(
			$table_name,
			array(
				'userid' => get_current_user_id(),
				'title' => $c_title,
				'shortdesc' => $c_shortdesc,
				'hexcolor' => $c_hexcolor,
				'stundensatz' => $stundensatz,
				'parentclient' => $parentclient,
				'pass' => $password,
				'randomlink' => md5($c_title.$c_shortdesc.$stundensatz.$c_hexcolor.$parentclient.$password),
				'created' => current_time('mysql', 1),
				'state' => 1,
				'asana_project_id' => $asana_project_id
			)
		);
	} else {
		// update client - only update password if a new one is provided
		$update_data = array(
			'title' => $c_title,
			'shortdesc' => $c_shortdesc,
			'hexcolor' => $c_hexcolor,
			'stundensatz' => $stundensatz,
			'parentclient' => $parentclient,
			'asana_project_id' => $asana_project_id
		);

		// Only update password if not empty
		if (!empty($password_raw)) {
			$update_data['pass'] = wp_hash_password($password_raw);
		}

		$wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $client_id )
		);

		// check if randomlink is set
		$randomlink = $wpdb->get_row("SELECT randomlink FROM $table_name WHERE id = $client_id AND userid = ".$currentuserid);
		if (!$randomlink->randomlink) {
			$wpdb->update(
				$table_name,
				array(
					'randomlink' => md5($c_title.$c_shortdesc.$stundensatz.$c_hexcolor.$parentclient.time()),
				),
				array( 'id' => $client_id )
			);
		}
	}

	wp_send_json_success();
	exit();
};


// uf_quick_save_client - Auto-save single field from table view
add_action('wp_ajax_uf_quick_save_client', 'uf_quick_save_client');

function uf_quick_save_client() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$client_id = (int)$_POST['client_id'];
	$field = sanitize_text_field($_POST['field']);
	// Use textarea sanitization for fields that support line breaks
	$textarea_fields = array('url');
	if (in_array($field, $textarea_fields)) {
		$value = sanitize_textarea_field(wp_unslash($_POST['value']));
	} else {
		$value = sanitize_text_field(wp_unslash($_POST['value']));
	}

	$allowed_fields = array('title', 'shortdesc', 'stundensatz', 'hexcolor', 'parentclient', 'pass', 'asana_project_id', 'url');
	if (!in_array($field, $allowed_fields)) {
		wp_send_json_error('Feld nicht erlaubt.');
		exit();
	}

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_clients';
	$client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND userid = %d", $client_id, $currentuserid));
	if (!$client) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	if ($field === 'stundensatz' || $field === 'parentclient') {
		$value = (int)$value;
	}
	if ($field === 'pass') {
		$value = !empty($value) ? wp_hash_password($value) : $client->pass;
	}

	$wpdb->update($table, array($field => $value), array('id' => $client_id));
	wp_send_json_success();
	exit();
}

// uf_quick_create_client - Create client from table view
add_action('wp_ajax_uf_quick_create_client', 'uf_quick_create_client');

function uf_quick_create_client() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_clients';

	$randomcolor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
	$wpdb->insert($table, array(
		'userid' => $currentuserid,
		'title' => 'Neuer Kunde',
		'shortdesc' => '',
		'hexcolor' => $randomcolor,
		'stundensatz' => 0,
		'parentclient' => 0,
		'pass' => '',
		'randomlink' => md5('Neuer Kunde' . time()),
		'state' => 1,
		'created' => current_time('mysql'),
	));

	$new_id = $wpdb->insert_id;

	wp_send_json_success(array(
		'id' => $new_id,
		'title' => 'Neuer Kunde',
		'shortdesc' => '',
		'stundensatz' => 0,
		'hexcolor' => $randomcolor,
		'parentclient' => 0,
		'randomlink' => '',
	));
	exit();
}

// uf_save_tallyr_settings
add_action('wp_ajax_uf_save_tallyr_settings', 'uf_save_tallyr_settings');

function uf_save_tallyr_settings() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$field = sanitize_text_field($_POST['field']);
	$value = sanitize_text_field($_POST['value']);

	$allowed = array('tallyr_zeit', 'tallyr_kuerzel', 'tallyr_capacity', 'tallyr_pp_fontsize', 'tallyr_pp_tags', 'tallyr_pp_colwidths', 'tallyr_pp_textbausteine_project');
	if (!in_array($field, $allowed)) {
		wp_send_json_error('Feld nicht erlaubt.');
		exit();
	}

	update_user_meta($currentuserid, $field, $value);
	wp_send_json_success();
	exit();
}

// uf_delete_client
add_action('wp_ajax_uf_delete_client', 'uf_delete_client');

function uf_delete_client() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$client_id = (int)$_POST['client_id'];

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_clients';

	$client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND userid = %d", $client_id, $currentuserid));
	if (!$client) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	$wpdb->update($table, array('state' => 2), array('id' => $client_id));
	wp_send_json_success();
	exit();
}

// uf_getprojectsfromclient
add_action('wp_ajax_uf_getprojectsfromclient', 'uf_getprojectsfromclient');

/* <div class="item">
                    <a data-id="134">Titel<span class="dater">30.09.2009</span></a>
                </div> */

function uf_getprojectsfromclient() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$client_id = (INT)$_POST['client_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_projects';
	$projects = $wpdb->get_results("SELECT * FROM $table_name WHERE clientid = $client_id AND userid = ".$currentuserid);
	$projects = array_reverse($projects);

	$projecthtml = '';
	foreach ($projects as $project) {

		// get all hours from tasks with state 1 and notbillable = 0 to porject
		$taskhours = 0;
		$table_name = $wpdb->prefix . 'tallyr_times';
		/* $tasks = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project->id AND userid = ".$currentuserid AND state = 1 AND notbillable = 0"); */
		$tasks = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project->id AND userid = ".$currentuserid." AND state = 1 AND notbillable = 0");
		foreach ($tasks as $task) {
			$taskhours += $task->hours;
		}

		$taskhours = number_format($taskhours, 2, '.', '');

		if ($project->state == 2) {
			$projecthtml .= '<div class="item" style="display:none;">
			<a data-title="'.$project->title.'" data-description="'.$project->description.'" data-maxhours="'.$project->maxhours.'" data-stundensatz="'.$project->stundensatz.'" data-id="'.$project->id.'">'.$project->title.'<span class="dater">'.date('d.m.Y', strtotime($project->created)).'</span><i class="bx bx-edit editpro"></i><i class="bx bx-archive deleteproject"></i></a>
		</div>';
		} else {
			$projecthtml .= '<div class="item pitem">
			<a data-title="'.$project->title.'" data-description="'.$project->description.'" data-maxhours="'.$project->maxhours.'" data-stundensatz="'.$project->stundensatz.'" data-id="'.$project->id.'">'.$project->title.'<div class="hours"><span class="phou">'.$taskhours.'</span> / '.$project->maxhours.' h</div><span class="dater">'.date('d.m.Y', strtotime($project->created)).'</span><i class="bx bx-edit editpro"></i><i class="bx bx-archive deleteproject"></i></a>
		</div>';
		}
	}

	if (!$projects) {
		$projecthtml = '<div class="item">
			<a data-id="0">Keine Projekte vorhanden</a>
		</div>';
	}

	echo $projecthtml;

	exit();
};

// Get projects as JSON for select dropdowns
add_action('wp_ajax_uf_getprojectsjson', 'uf_getprojectsjson');
function uf_getprojectsjson() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$client_id = (int)$_POST['client_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_projects';
	$projects = $wpdb->get_results($wpdb->prepare(
		"SELECT id, title FROM $table_name WHERE clientid = %d AND userid = %d AND state = 1 ORDER BY title ASC",
		$client_id, $currentuserid
	));

	wp_send_json_success($projects);
	exit();
}

// create project and reutrn all projects
/* action: 'uf_createproject',
						client_id: client_id,
				p_title: p_title,
				p_desc: p_desc,
				p_maxhours: p_maxhours,
				p_stundensatz: p_stundensatz, */

add_action('wp_ajax_uf_createproject', 'uf_createproject');

function uf_createproject() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$p_title = normform_sanitize_text_or_array_field($_POST['p_title']);
	$client_id = (INT)$_POST['client_id'];
	$p_desc = normform_sanitize_text_or_array_field($_POST['p_desc']);
	$p_maxhours = (FLOAT)$_POST['p_maxhours'];
	$p_stundensatz = (INT)$_POST['p_stundensatz'];
	$projectid = (INT)$_POST['projectid'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_projects';

	// check if project with name exists
	$project_exists = $wpdb->get_row("SELECT * FROM $table_name WHERE title = '$p_title' AND clientid = $client_id AND userid = ".$currentuserid);

	// check if project by id exists and user is owner
	$project_exists = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $projectid AND userid = ".$currentuserid);


	if (!$project_exists) {
		$wpdb->insert( 
			$table_name, 
			array( 
				'userid' => get_current_user_id(),
				'clientid' => $client_id,
				'title' => $p_title,
				'created' => current_time('mysql', 1),
				'description' => $p_desc,
				'maxhours' => $p_maxhours,
				'stundensatz' => $p_stundensatz,
				'state' => 1
			) 
		);
	} else {
		$wpdb->update( 
			$table_name, 
			array( 
				'title' => $p_title,
				'description' => $p_desc,
				'maxhours' => $p_maxhours,
				'stundensatz' => $p_stundensatz,
			),
			array( 'id' => $projectid )
		);
	}

	$projects = $wpdb->get_results("SELECT * FROM $table_name WHERE clientid = $client_id AND userid = ".$currentuserid);
	$projects = array_reverse($projects);

	$projecthtml = '';
	foreach ($projects as $project) {

		// get all hours from tasks with state 1 and notbillable = 0 to porject
		$taskhours = 0;
		$table_name = $wpdb->prefix . 'tallyr_times';
		/* $tasks = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project->id AND userid = ".$currentuserid AND state = 1 AND notbillable = 0"); */
		$tasks = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project->id AND userid = ".$currentuserid." AND state = 1 AND notbillable = 0");
		foreach ($tasks as $task) {
			$taskhours += $task->hours;
		}
		if ($project->state == 2) {
			$projecthtml .= '<div class="item" style="display:none;">
			<a data-title="'.$project->title.'" data-description="'.$project->description.'" data-maxhours="'.$project->maxhours.'" data-stundensatz="'.$project->stundensatz.'" data-id="'.$project->id.'">'.$project->title.'<span class="dater">'.date('d.m.Y', strtotime($project->created)).'</span><i class="bx bx-edit editpro"></i><i class="bx bx-archive deleteproject"></i></a>
		</div>';
		} else {
			$projecthtml .= '<div class="item pitem">
			<a data-title="'.$project->title.'" data-description="'.$project->description.'" data-maxhours="'.$project->maxhours.'" data-stundensatz="'.$project->stundensatz.'" data-id="'.$project->id.'">'.$project->title.'<div class="hours"><span class="phou">'.$taskhours.'</span> / '.$project->maxhours.' h</div><span class="dater">'.date('d.m.Y', strtotime($project->created)).'</span><i class="bx bx-edit editpro"></i><i class="bx bx-archive deleteproject"></i></a>
		</div>';
		}
	}

	if (!$projects) {
		$projecthtml = '<div class="item">
			<a data-id="0">Keine Projekte vorhanden</a>
		</div>';
	}

	echo $projecthtml;
	exit();
};

// uf_deleteproject
add_action('wp_ajax_uf_deleteproject', 'uf_deleteproject');

function uf_deleteproject() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$project_id = (INT)$_POST['project_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_projects';
	$wpdb->update( 
		$table_name, 
		array( 
			'state' => 2
		),
		array( 'id' => $project_id, 'userid' => $currentuserid )
	);

	exit();
};

// uf_gettimesfromproject
add_action('wp_ajax_uf_gettimesfromproject', 'uf_gettimesfromproject');

function uf_gettimesfromproject() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$project_id = (INT)$_POST['project_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	// sort times by workdate

	//$times = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project_id AND userid = ".$currentuserid);
	$times = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project_id AND userid = ".$currentuserid." ORDER BY workdate DESC");


	$timehtml = '';
	foreach ($times as $time) {
		if ($time->state == 2) {
			$timehtml .= '<div class="item" style="display:none">';
			$timehtml .= '<a data-stundensatz="'.$time->stundensatz.'" data-parentclient="'.$time->parentclient.'" data-notbillable="'.$time->notbillable.'" data-time="'.$time->hours.'" data-workdate="'.$time->workdate.'" data-id="'.$time->id.'">';
			$timehtml .= '<div class="dater"><span>'.date('d.m.Y', strtotime($time->workdate)).'</span><span>'.$time->hours.' h</span></div>';
			if ($time->notbillable == 1) {
				$timehtml .= '<span class="notbillable">Nicht abrechenbar</span>';
			}
			$timehtml .= '<div class="desc">'.$time->description.'</div>';
			$timehtml .= '<div class="linknotice" style="display:none">'.$time->linknotice.'</div>';
			$timehtml .= '<i class="bx bx-archive deletetime"></i>';
			$timehtml .= '</a>';
			$timehtml .= '</div>';
		} else {
			$timehtml .= '<div class="item titem" draggable="true">';
			$timehtml .= '<a data-stundensatz="'.$time->stundensatz.'" data-parentclient="'.$time->parentclient.'" data-notbillable="'.$time->notbillable.'" data-time="'.$time->hours.'" data-workdate="'.$time->workdate.'" data-id="'.$time->id.'">';
			$timehtml .= '<div class="dater"><span>'.date('d.m.Y', strtotime($time->workdate)).'</span><span>'.$time->hours.' h</span><i class="copythis bx bx-copy-alt"></i></div>';
			if ($time->notbillable == 1) {
				$timehtml .= '<span class="notbillable">Nicht abrechenbar</span>';
			}
			$timehtml .= '<div class="desc">'.stripslashes($time->description).'</div>';
			$timehtml .= '<div class="linknotice" style="display:none">'.stripslashes($time->linknotice).'</div>';
			$timehtml .= '<i class="bx bx-archive deletetime"></i>';
			$timehtml .= '</a>';
			$timehtml .= '</div>';
		}
	}

	if (!$times) {
		$timehtml = '<div class="item">
			<a data-id="0">Keine Zeiteinträge vorhanden</a>
		</div>';
	}

	echo $timehtml;

	exit();
};

/* jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_createtime',
				project_id: project_id,
				description: description,
				time: time,
				date: date,
				stundensatz: stundensatz,
				parentclient: parentclient,
				abrechenbar: abrechenbar,
				client_id: client_id,
			},
			success: function (result) {
				// reload times
				$('#dbresultstimes .results').html(result);
				// show swal success
				toastr.success('Zeit wurde erfasst.');
				submitbutton.prop('disabled', false);
			},
			error: function (result) {
				console.log(result);
				submitbutton.prop('disabled', false);
			}
		}); */

// uf_createtime
add_action('wp_ajax_uf_createtime', 'uf_createtime');

function uf_createtime() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$project_id = (INT)$_POST['project_id'];
	$description = normform_sanitize_text_or_array_field($_POST['description']);
	$linknotice = normform_sanitize_text_or_array_field($_POST['linknotice']);
	$time = (FLOAT)$_POST['time'];
	$date = $_POST['date'];
	$stundensatz = (INT)$_POST['stundensatz'];
	$parentclient = (INT)$_POST['parentclient'];
	$abrechenbar = (INT)$_POST['abrechenbar'];
	$client_id = (INT)$_POST['client_id'];
	$currentuserid = get_current_user_id();
	$times_id = (INT)$_POST['times_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';

	// check if time exists and user is owner, edit if exists else create new one
	$time_exists = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $times_id AND userid = ".$currentuserid);

	if ($time_exists) {
		$wpdb->update( 
			$table_name, 
			array( 
				'userid' => $currentuserid,
				'clientid' => $client_id,
				'projectid' => $project_id,
				'description' => $description,
				'linknotice' => $linknotice,
				'hours' => $time,
				'workdate' => $date,
				'stundensatz' => $stundensatz,
				'parentclient' => $parentclient,
				'notbillable' => $abrechenbar,
				'created' => current_time('mysql', 1),
				'state' => 1
			),
			array( 'id' => $times_id )
		);
	} else {
		$wpdb->insert( 
			$table_name, 
			array( 
				'userid' => $currentuserid,
				'clientid' => $client_id,
				'projectid' => $project_id,
				'description' => $description,
				'linknotice' => $linknotice,
				'hours' => $time,
				'workdate' => $date,
				'stundensatz' => $stundensatz,
				'parentclient' => $parentclient,
				'notbillable' => $abrechenbar,
				'created' => current_time('mysql', 1),
				'state' => 1
			) 
		);
	}

	// last error
	$last_error = $wpdb->last_error;
	if ($last_error) {
		wp_send_json_error($last_error);
	}

	//$times = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project_id AND userid = ".$currentuserid);
	$times = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project_id AND userid = ".$currentuserid." ORDER BY workdate DESC");


	$timehtml = '';
	foreach ($times as $time) {
		if ($time->state == 2) {
			$timehtml .= '<div class="item" style="display:none">';
			$timehtml .= '<a data-stundensatz="'.$time->stundensatz.'" data-parentclient="'.$time->parentclient.'" data-notbillable="'.$time->notbillable.'" data-time="'.$time->hours.'" data-workdate="'.$time->workdate.'" data-id="'.$time->id.'">';
			$timehtml .= '<div class="dater"><span>'.date('d.m.Y', strtotime($time->workdate)).'</span><span>'.$time->hours.' h</span></div>';
			if ($time->notbillable == 1) {
				$timehtml .= '<span class="notbillable">Nicht abrechenbar</span>';
			}
			$timehtml .= '<div class="desc">'.$time->description.'</div>';
			$timehtml .= '<div class="linknotice" style="display:none">'.$time->linknotice.'</div>';
			$timehtml .= '<i class="bx bx-archive deletetime"></i>';
			$timehtml .= '</a>';
			$timehtml .= '</div>';
		} else {
			$timehtml .= '<div class="item titem" draggable="true">';
			$timehtml .= '<a data-stundensatz="'.$time->stundensatz.'" data-parentclient="'.$time->parentclient.'" data-notbillable="'.$time->notbillable.'" data-time="'.$time->hours.'" data-workdate="'.$time->workdate.'" data-id="'.$time->id.'">';
			$timehtml .= '<div class="dater"><span>'.date('d.m.Y', strtotime($time->workdate)).'</span><span>'.$time->hours.' h</span><i class="copythis bx bx-copy-alt"></i></div>';
			if ($time->notbillable == 1) {
				$timehtml .= '<span class="notbillable">Nicht abrechenbar</span>';
			}
			$timehtml .= '<div class="desc">'.stripslashes($time->description).'</div>';
			$timehtml .= '<div class="linknotice" style="display:none">'.stripslashes($time->linknotice).'</div>';
			$timehtml .= '<i class="bx bx-archive deletetime"></i>';
			$timehtml .= '</a>';
			$timehtml .= '</div>';
		}
	}

	if (!$times) {
		$timehtml = '<div class="item">
			<a data-id="0">Keine Zeiteinträge vorhanden</a>
		</div>';
	}

	echo $timehtml;

	exit();
};

// uf_deletetime
add_action('wp_ajax_uf_deletetime', 'uf_deletetime');

function uf_deletetime() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$time_id = (INT)$_POST['times_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$wpdb->update( 
		$table_name, 
		array( 
			'state' => 2
		),
		array( 'id' => $time_id, 'userid' => $currentuserid )
	);

	exit();
};















function deleteTableDevelopt($tablename) {
	global $wpdb;
	$table_name = $wpdb->prefix . $tablename;
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}



add_action('wp_ajax_uf_kiausformulieren', 'uf_kiausformulieren');

function uf_kiausformulieren() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$prompttext = normform_sanitize_text_or_array_field($_POST['prompttext']);

	$prompttext = 'Du bist ein Zeiterfassungstool für Freelancer. Der Freelancer will folgende geleistete Arbeit erfassen: "'.$prompttext.'". Bitte formuliere es aus, so dass auch Kunden es verstehen. Formuliere gerne etwas aus, aber nicht zu lange Formulierungen.';

	$api_key = (string) get_field('chatgptapikey', 'option');
	$open_ai = new OpenAi($api_key);
	$response = $open_ai->chat([
		'model' => 'gpt-4o',
		'messages' => array(array('role' => 'user', 'content' => $prompttext)),
		'temperature' => 0.6,
		'top_p' => 1,
		'frequency_penalty' => 0,
		'presence_penalty' => 0
	]);

	$result = json_decode($response);
	$answer = strip_tags(str_replace('"', '', htmlspecialchars_decode($result->choices[0]->message->content)));

	/* print_r($result); */

	echo $answer;
	exit();
};


// get all times by date - returns JSON with comprehensive statistics
add_action('wp_ajax_uf_gettimesbydate', 'uf_gettimesbydate');

function uf_gettimesbydate() {

	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$start_date = sanitize_text_field($_POST['start_date']);
	$end_date = sanitize_text_field($_POST['end_date']);
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$table_name_projects = $wpdb->prefix . 'tallyr_projects';
	$table_name_clients = $wpdb->prefix . 'tallyr_clients';

	// Get times for current period
	$times = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table_name WHERE workdate >= %s AND workdate <= %s AND userid = %d AND state = 1",
		$start_date, $end_date, $currentuserid
	));

	// Calculate previous period for comparison
	$period_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
	$prev_start = date('Y-m-d', strtotime($start_date . ' -' . $period_days . ' days'));
	$prev_end = date('Y-m-d', strtotime($start_date . ' -1 day'));

	$prev_times = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table_name WHERE workdate >= %s AND workdate <= %s AND userid = %d AND state = 1",
		$prev_start, $prev_end, $currentuserid
	));

	// Initialize statistics
	$stats = array(
		'total_hours' => 0,
		'billable_hours' => 0,
		'not_billable_hours' => 0,
		'billed_hours' => 0,
		'total_revenue' => 0,
		'billed_revenue' => 0,
		'open_revenue' => 0,
		'entry_count' => count($times),
		'avg_rate' => 0,
		'project_count' => 0,
		'client_count' => 0
	);

	$prev_stats = array(
		'total_hours' => 0,
		'billable_hours' => 0,
		'total_revenue' => 0
	);

	// Data aggregations
	$clients = array();
	$projects = array();
	$weekdays = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
	$daily_hours = array();
	$entries = array();

	// Cache for client/project lookups
	$client_cache = array();
	$project_cache = array();

	foreach ($times as $time) {
		// Basic stats
		$stats['total_hours'] += $time->hours;
		$amount = $time->hours * $time->stundensatz;

		if ($time->notbillable == 1) {
			$stats['not_billable_hours'] += $time->hours;
		} else {
			$stats['billable_hours'] += $time->hours;
			$stats['total_revenue'] += $amount;

			if ($time->billed_on) {
				$stats['billed_hours'] += $time->hours;
				$stats['billed_revenue'] += $amount;
			} else {
				$stats['open_revenue'] += $amount;
			}
		}

		// Get project and client info
		if (!isset($project_cache[$time->projectid])) {
			$project = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $table_name_projects WHERE id = %d AND userid = %d",
				$time->projectid, $currentuserid
			));
			$project_cache[$time->projectid] = $project;
		}
		$project = $project_cache[$time->projectid];

		$clientid = $project ? $project->clientid : 0;
		if ($clientid && !isset($client_cache[$clientid])) {
			$client = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $table_name_clients WHERE id = %d AND userid = %d",
				$clientid, $currentuserid
			));
			$client_cache[$clientid] = $client;
		}
		$client = isset($client_cache[$clientid]) ? $client_cache[$clientid] : null;

		$clientname = $client ? $client->title : 'Unbekannt';
		$projectname = $project ? $project->title : 'Unbekannt';
		$clientcolor = $client ? $client->hexcolor : '#999';

		// Aggregate by client
		if (!isset($clients[$clientid])) {
			$clients[$clientid] = array(
				'id' => $clientid,
				'name' => $clientname,
				'color' => $clientcolor,
				'hours' => 0,
				'revenue' => 0,
				'entries' => 0
			);
		}
		$clients[$clientid]['hours'] += $time->hours;
		$clients[$clientid]['revenue'] += $amount;
		$clients[$clientid]['entries']++;

		// Aggregate by project
		$proj_key = $time->projectid;
		if (!isset($projects[$proj_key])) {
			$projects[$proj_key] = array(
				'id' => $time->projectid,
				'name' => $projectname,
				'client' => $clientname,
				'client_color' => $clientcolor,
				'hours' => 0,
				'revenue' => 0,
				'entries' => 0,
				'rates' => array()
			);
		}
		$projects[$proj_key]['hours'] += $time->hours;
		$projects[$proj_key]['revenue'] += $amount;
		$projects[$proj_key]['entries']++;
		$projects[$proj_key]['rates'][] = $time->stundensatz;

		// Weekday distribution
		$weekday = date('w', strtotime($time->workdate));
		$weekdays[$weekday] += $time->hours;

		// Daily hours
		$datekey = $time->workdate;
		if (!isset($daily_hours[$datekey])) {
			$daily_hours[$datekey] = 0;
		}
		$daily_hours[$datekey] += $time->hours;

		// Build entry for table
		$tr_class = 'notbilled';
		if ($time->billed_on) {
			$tr_class = 'billed';
		}
		if ($time->notbillable == 1) {
			$tr_class = 'notbillable';
		}

		$entries[] = array(
			'id' => $time->id,
			'date' => date('d.m.Y', strtotime($time->workdate)),
			'date_raw' => $time->workdate,
			'client' => $clientname,
			'client_id' => $clientid,
			'project' => $projectname,
			'project_id' => $time->projectid,
			'description' => stripslashes($time->description),
			'hours' => floatval($time->hours),
			'rate' => floatval($time->stundensatz),
			'amount' => $amount,
			'billable' => $time->notbillable != 1,
			'billed' => !empty($time->billed_on),
			'billed_on' => $time->billed_on ? date('d.m.Y', strtotime($time->billed_on)) : null,
			'class' => $tr_class
		);
	}

	// Previous period stats for comparison
	foreach ($prev_times as $time) {
		$prev_stats['total_hours'] += $time->hours;
		if ($time->notbillable != 1) {
			$prev_stats['billable_hours'] += $time->hours;
			$prev_stats['total_revenue'] += $time->hours * $time->stundensatz;
		}
	}

	// Calculate averages and counts
	$stats['project_count'] = count($projects);
	$stats['client_count'] = count($clients);
	if ($stats['billable_hours'] > 0) {
		$stats['avg_rate'] = $stats['total_revenue'] / $stats['billable_hours'];
	}

	// Calculate average rate per project
	foreach ($projects as &$proj) {
		if (count($proj['rates']) > 0) {
			$proj['avg_rate'] = array_sum($proj['rates']) / count($proj['rates']);
		} else {
			$proj['avg_rate'] = 0;
		}
		unset($proj['rates']);
	}

	// Sort entries by date (newest first)
	usort($entries, function($a, $b) {
		return strtotime($b['date_raw']) - strtotime($a['date_raw']);
	});

	// Sort clients by hours (descending)
	usort($clients, function($a, $b) {
		return $b['hours'] - $a['hours'];
	});

	// Sort projects by hours (descending)
	usort($projects, function($a, $b) {
		return $b['hours'] - $a['hours'];
	});

	// Sort daily hours by date
	ksort($daily_hours);

	// Calculate comparison percentages
	$comparison = array(
		'hours_change' => 0,
		'revenue_change' => 0,
		'prev_hours' => $prev_stats['total_hours'],
		'prev_revenue' => $prev_stats['total_revenue']
	);

	if ($prev_stats['total_hours'] > 0) {
		$comparison['hours_change'] = (($stats['total_hours'] - $prev_stats['total_hours']) / $prev_stats['total_hours']) * 100;
	}
	if ($prev_stats['total_revenue'] > 0) {
		$comparison['revenue_change'] = (($stats['total_revenue'] - $prev_stats['total_revenue']) / $prev_stats['total_revenue']) * 100;
	}

	// Weekday names
	$weekday_names = array('So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa');
	$weekday_data = array();
	foreach ($weekdays as $day => $hours) {
		$weekday_data[] = array(
			'day' => $weekday_names[$day],
			'hours' => round($hours, 2)
		);
	}

	// Daily data for chart
	$daily_data = array();
	foreach ($daily_hours as $date => $hours) {
		$daily_data[] = array(
			'date' => date('d.m.', strtotime($date)),
			'hours' => round($hours, 2)
		);
	}

	// Get all clients for filter dropdown
	$all_clients = $wpdb->get_results($wpdb->prepare(
		"SELECT id, title FROM $table_name_clients WHERE userid = %d ORDER BY title ASC",
		$currentuserid
	));

	wp_send_json_success(array(
		'stats' => $stats,
		'comparison' => $comparison,
		'clients' => array_values($clients),
		'projects' => array_values($projects),
		'weekdays' => $weekday_data,
		'daily' => $daily_data,
		'entries' => $entries,
		'all_clients' => $all_clients,
		'period' => array(
			'start' => $start_date,
			'end' => $end_date,
			'prev_start' => $prev_start,
			'prev_end' => $prev_end
		)
	));
	exit();
}

add_action('wp_ajax_uf_movetimetoproject', 'uf_movetimetoproject');

function uf_movetimetoproject() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$new_project_id = (INT)$_POST['project_id'];
	$time_id = (INT)$_POST['times_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$wpdb->update( 
		$table_name, 
		array( 
			'projectid' => $new_project_id
		),
		array( 'id' => $time_id, 'userid' => $currentuserid )
	);

	exit();
};


// get project hours by oriject id
/* jQuery.ajax({
	type: 'POST',
	url: ajaxuser.url,
	data: {
		security: ajaxuser.nonce,
		action: 'uf_countprojecthours',
		project_id: project_id,
	},
	success: function (result) {
		$('a[data-id=' + project_id + ']').find('span.phou').html(result);
	},
	error: function (result) {
	}
}); */

add_action('wp_ajax_uf_countprojecthours', 'uf_countprojecthours');

function uf_countprojecthours() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$project_id = (INT)$_POST['project_id'];
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	$taskhours = 0;
	$tasks = $wpdb->get_results("SELECT * FROM $table_name WHERE projectid = $project_id AND userid = ".$currentuserid." AND state = 1");
	foreach ($tasks as $task) {
		$taskhours += $task->hours;
	}

	$taskhours = number_format($taskhours, 2, '.', '');

	echo $taskhours;
	exit();
};

// uf_readandaccept
add_action('wp_ajax_uf_readandaccept', 'uf_readandaccept');

function uf_readandaccept() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$currentuserid = get_current_user_id();
	$mid = (INT)$_POST['mid'];
	$timeid = (INT)$_POST['timeid'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_messages';
	$wpdb->update( 
		$table_name, 
		array( 
			'seenby' => $currentuserid,
		),
		array( 'mid' => $mid, 'uid' => $currentuserid, 'tid' => $timeid )
	);

	echo 'ok';

	exit();
};

// uf_settoundone
add_action('wp_ajax_uf_settoundone', 'uf_settoundone');

function uf_settoundone() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$currentuserid = get_current_user_id();
	$mid = (INT)$_POST['mid'];
	$timeid = (INT)$_POST['timeid'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_messages';
	$wpdb->update( 
		$table_name, 
		array( 
			'seenby' => 0,
		),
		array( 'mid' => $mid, 'uid' => $currentuserid, 'tid' => $timeid )
	);

	echo 'ok';

	exit();
};


// uf_sendreply
add_action('wp_ajax_uf_sendreply', 'uf_sendreply');
function uf_sendreply() {
	
	check_ajax_referer( 'dhAu21hdaZA721!naj!hdf', 'security' );
	$currentuserid = get_current_user_id();
	$timeid = (INT)$_POST['tid'];
	$replytext = nl2br(normform_sanitize_text_or_array_field($_POST['message']));
	$mid = (INT)$_POST['mid'];

	if (!$replytext) {
		echo 'error';
		exit();
	}

	/* 'tid' => $time_id,
		'uid' => $time->userid,
		'fr' => $fr,
		'clientid' => $client_id,
		'clientid_parent' => $client_id_parent,
		'message' => $comment,
		'date' => current_time('mysql') */

	// get time by id and the client_id and client_id_parent
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_times';
	// check if time exists and user is owner
	$time = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $timeid AND userid = $currentuserid");
	$client_id = $time->clientid;
	$client_id_parent = $time->clientid_parent;

	if (!$time) {
		echo 'error';
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_messages';
	$wpdb->insert(
		$table_name,
		array(
			'uid' => $currentuserid,
			'fr' => 0,
			'tid' => $timeid,
			'clientid' => $client_id,
			'clientid_parent' => $client_id_parent,
			'date' => current_time('mysql'),
			'message' => $replytext,
			'seenby' => 0,
		)
	);

	echo 'ok';

	exit();
};

// ========================================
// ASANA INTEGRATION ENDPOINTS
// ========================================

// uf_save_asana_token
add_action('wp_ajax_uf_save_asana_token', 'uf_save_asana_token');

function uf_save_asana_token() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	$token = isset($_POST['asana_token']) ? sanitize_text_field($_POST['asana_token']) : '';
	$currentuserid = get_current_user_id();

	if (empty($token)) {
		delete_user_meta($currentuserid, 'tallyr_asana_token');
		wp_send_json_success(array('message' => 'Asana Token entfernt.'));
		exit();
	}

	// Verify token before saving
	$asana = new Tallyr_Asana_API($token);
	$verification = $asana->verify_token();

	if ($verification['valid']) {
		update_user_meta($currentuserid, 'tallyr_asana_token', $token);
		delete_transient('tallyr_asana_projects_' . $currentuserid);
		wp_send_json_success(array(
			'message' => 'Asana Token gespeichert.',
			'user_name' => $verification['user']['name']
		));
	} else {
		wp_send_json_error('Ungültiger Asana Token. Bitte überprüfe deinen Personal Access Token.');
	}

	exit();
}

// uf_get_asana_projects
add_action('wp_ajax_uf_get_asana_projects', 'uf_get_asana_projects');

function uf_get_asana_projects() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	$currentuserid = get_current_user_id();
	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);

	if (empty($token)) {
		wp_send_json_error('Kein Asana Token vorhanden.');
		exit();
	}

	// Cache for 1 hour per user
	$cache_key = 'tallyr_asana_projects_' . $currentuserid;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		wp_send_json_success($cached);
		exit();
	}

	$asana = new Tallyr_Asana_API($token);
	$projects = $asana->get_all_projects();

	set_transient($cache_key, $projects, HOUR_IN_SECONDS);

	wp_send_json_success($projects);
	exit();
}

// uf_search_asana_tasks
add_action('wp_ajax_uf_search_asana_tasks', 'uf_search_asana_tasks');

function uf_search_asana_tasks() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	$currentuserid = get_current_user_id();
	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	$search_text = isset($_POST['search_text']) ? sanitize_text_field($_POST['search_text']) : '';
	$client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;

	if (empty($token)) {
		wp_send_json_error('Kein Asana Token vorhanden.');
		exit();
	}

	// Get Asana project IDs from client if set (comma-separated)
	$asana_project_ids = array();
	if ($client_id > 0) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'tallyr_clients';
		$client = $wpdb->get_row($wpdb->prepare(
			"SELECT asana_project_id FROM $table_name WHERE id = %d AND userid = %d",
			$client_id,
			$currentuserid
		));

		if ($client && !empty($client->asana_project_id)) {
			$asana_project_ids = array_filter(explode(',', $client->asana_project_id));
		}
	}

	// Cache key based on user + client + search
	$cache_key = 'tallyr_asana_tasks_' . $currentuserid . '_' . $client_id . '_' . md5($search_text);
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		wp_send_json_success($cached);
		exit();
	}

	$asana = new Tallyr_Asana_API($token);

	// Get workspaces
	$workspaces = $asana->get_workspaces();

	if (empty($workspaces)) {
		wp_send_json_error('Keine Asana Workspaces gefunden.');
		exit();
	}

	// Use first workspace
	$workspace_gid = $workspaces[0]['gid'];

	$tasks = array();

	if (!empty($asana_project_ids)) {
		// Search in multiple projects
		foreach ($asana_project_ids as $project_id) {
			if (!empty($search_text)) {
				$project_tasks = $asana->search_tasks($workspace_gid, $search_text, $project_id, 20);
			} else {
				$project_tasks = $asana->get_tasks_from_project($project_id, 30);
			}
			$tasks = array_merge($tasks, $project_tasks);
		}
		// Remove duplicates by gid
		$unique_tasks = array();
		$seen_gids = array();
		foreach ($tasks as $task) {
			if (!in_array($task['gid'], $seen_gids)) {
				$unique_tasks[] = $task;
				$seen_gids[] = $task['gid'];
			}
		}
		$tasks = array_slice($unique_tasks, 0, 30);
	} else if (!empty($search_text)) {
		// Search with text query in workspace
		$tasks = $asana->search_tasks($workspace_gid, $search_text, null, 20);
	} else {
		// Get recent tasks from workspace
		$tasks = $asana->get_tasks_from_workspace($workspace_gid, 50);
	}

	// Format response with gid, name, permalink_url
	$formatted_tasks = array();
	foreach ($tasks as $task) {
		if (!empty($task['name'])) {
			$formatted_tasks[] = array(
				'gid' => isset($task['gid']) ? $task['gid'] : '',
				'name' => $task['name'],
				'permalink_url' => isset($task['permalink_url']) ? $task['permalink_url'] : '',
				'url' => isset($task['permalink_url']) ? $task['permalink_url'] : '',
			);
		}
	}

	// Cache for 10 minutes
	set_transient($cache_key, $formatted_tasks, 10 * MINUTE_IN_SECONDS);

	wp_send_json_success($formatted_tasks);
	exit();
}

// uf_refresh_asana_cache - manually clear Asana caches
add_action('wp_ajax_uf_refresh_asana_cache', 'uf_refresh_asana_cache');

function uf_refresh_asana_cache() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	// Delete project cache
	delete_transient('tallyr_asana_projects_' . $currentuserid);

	// Delete all task search caches for this user (via DB query)
	global $wpdb;
	$wpdb->query($wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		'_transient_tallyr_asana_tasks_' . $currentuserid . '_%'
	));
	$wpdb->query($wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		'_transient_timeout_tallyr_asana_tasks_' . $currentuserid . '_%'
	));

	wp_send_json_success('Cache gelöscht');
	exit();
}

// uf_get_wp_users_for_share - get WP users for sharing dropdown
add_action('wp_ajax_uf_get_wp_users_for_share', 'uf_get_wp_users_for_share');
function uf_get_wp_users_for_share() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$wp_users = get_users(array('fields' => array('ID', 'display_name')));
	$result = array();
	foreach ($wp_users as $u) {
		if ($u->ID == $currentuserid) continue; // Don't show self
		$result[] = array('id' => (int)$u->ID, 'name' => $u->display_name);
	}
	wp_send_json_success($result);
	exit();
}

// uf_get_asana_users - get workspace members
add_action('wp_ajax_uf_get_asana_users', 'uf_get_asana_users');

function uf_get_asana_users() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) { wp_send_json_error('Kein Token.'); exit(); }

	$cache_key = 'tallyr_asana_users_' . $currentuserid;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		wp_send_json_success($cached);
		exit();
	}

	$asana = new Tallyr_Asana_API($token);
	$workspaces = $asana->get_workspaces();
	if (empty($workspaces)) { wp_send_json_error('Keine Workspaces.'); exit(); }

	$users = $asana->get_workspace_users($workspaces[0]['gid']);

	set_transient($cache_key, $users, HOUR_IN_SECONDS);
	wp_send_json_success($users);
	exit();
}

// uf_get_asana_project_members - get members of a specific project
add_action('wp_ajax_uf_get_asana_project_members', 'uf_get_asana_project_members');

function uf_get_asana_project_members() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$project_gid = sanitize_text_field($_POST['project_gid']);

	if (empty($project_gid)) { wp_send_json_success(array()); exit(); }

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) { wp_send_json_error('Kein Token.'); exit(); }

	$cache_key = 'tallyr_asana_members_' . $project_gid;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		wp_send_json_success($cached);
		exit();
	}

	$asana = new Tallyr_Asana_API($token);
	$members = $asana->get_project_members($project_gid);

	set_transient($cache_key, $members, HOUR_IN_SECONDS);
	wp_send_json_success($members);
	exit();
}

// uf_get_asana_sections - get columns/sections of a project
add_action('wp_ajax_uf_get_asana_sections', 'uf_get_asana_sections');

function uf_get_asana_sections() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$project_gid = sanitize_text_field($_POST['project_gid']);

	if (empty($project_gid)) { wp_send_json_success(array()); exit(); }

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) { wp_send_json_error('Kein Token.'); exit(); }

	// Cache for 1 hour
	$cache_key = 'tallyr_asana_sections_' . $project_gid;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		wp_send_json_success($cached);
		exit();
	}

	$asana = new Tallyr_Asana_API($token);
	$sections = $asana->get_sections($project_gid);

	set_transient($cache_key, $sections, HOUR_IN_SECONDS);
	wp_send_json_success($sections);
	exit();
}

// uf_create_asana_task - create task in Asana and return gid/url
add_action('wp_ajax_uf_create_asana_task', 'uf_create_asana_task');

function uf_create_asana_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) { wp_send_json_error('Kein Token.'); exit(); }

	$project_gid = sanitize_text_field($_POST['project_gid']);
	$section_gid = sanitize_text_field($_POST['section_gid']);
	$name = sanitize_text_field($_POST['name']);
	$notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
	$assignee_gid = isset($_POST['assignee_gid']) ? sanitize_text_field($_POST['assignee_gid']) : '';
	$due_on = isset($_POST['due_on']) ? sanitize_text_field($_POST['due_on']) : '';

	if (empty($project_gid) || empty($name)) {
		wp_send_json_error('Projekt und Titel sind Pflichtfelder.');
		exit();
	}

	$asana = new Tallyr_Asana_API($token);

	$task_data = array(
		'name' => $name,
		'projects' => array($project_gid),
	);
	if ($notes) $task_data['notes'] = $notes;
	if ($due_on) $task_data['due_on'] = $due_on;
	if ($assignee_gid) $task_data['assignee'] = $assignee_gid;

	$result = $asana->create_task($task_data);

	if (!$result || isset($result['error'])) {
		wp_send_json_error('Asana Fehler: ' . (is_array($result['error']) ? json_encode($result['error']) : $result['error']));
		exit();
	}

	// Move to section if specified
	if ($section_gid && isset($result['gid'])) {
		$asana->add_task_to_section($section_gid, $result['gid']);
	}

	wp_send_json_success(array(
		'gid' => $result['gid'],
		'name' => $result['name'],
		'permalink_url' => isset($result['permalink_url']) ? $result['permalink_url'] : 'https://app.asana.com/0/' . $project_gid . '/' . $result['gid'],
	));
	exit();
}

// uf_get_asana_task_detail
add_action('wp_ajax_uf_get_asana_task_detail', 'uf_get_asana_task_detail');

function uf_get_asana_task_detail() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$task_gid = sanitize_text_field($_POST['task_gid']);

	if (empty($task_gid)) {
		wp_send_json_error('Keine Task-ID.');
		exit();
	}

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) {
		wp_send_json_error('Kein Asana Token.');
		exit();
	}

	$asana = new Tallyr_Asana_API($token);
	$task = $asana->get_task($task_gid);

	if (!$task) {
		wp_send_json_error('Task nicht gefunden.');
		exit();
	}

	wp_send_json_success(array(
		'gid' => $task['gid'],
		'name' => $task['name'],
		'notes' => isset($task['notes']) ? $task['notes'] : '',
		'permalink_url' => isset($task['permalink_url']) ? $task['permalink_url'] : '',
	));
	exit();
}

// ========================================
// RECURRING INVOICES / SUBSCRIPTIONS
// ========================================

// Create recurring tables
function createtable_recurring_categories() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring_categories';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		title varchar(255) NOT NULL,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

function createtable_recurring() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		client_id bigint(20) NOT NULL,
		category_id bigint(20) NOT NULL,
		title varchar(255) NOT NULL,
		description text,
		amount decimal(10,2) NOT NULL,
		billing_interval varchar(50) NOT NULL,
		start_date date NOT NULL,
		end_date date,
		next_billing date NOT NULL,
		status varchar(50) DEFAULT 'active' NOT NULL,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

function createtable_recurring_log() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring_log';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		recurring_id bigint(20) NOT NULL,
		billed_date date NOT NULL,
		amount decimal(10,2) NOT NULL,
		notes text,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

// Initialize recurring tables
createtable_recurring_categories();
createtable_recurring();
createtable_recurring_log();

// ----------------------------------------
// RECURRING CATEGORIES AJAX
// ----------------------------------------

add_action('wp_ajax_uf_get_recurring_categories', 'uf_get_recurring_categories');
// Ensure partner column exists
function tallyr_ensure_recurring_partner() {
	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_recurring';
	if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {
		$cols = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);
		if (!in_array('partner', $cols)) {
			$wpdb->query("ALTER TABLE $table ADD COLUMN partner varchar(255) DEFAULT '' AFTER description");
		}
	}
}
tallyr_ensure_recurring_partner();

// uf_quick_update_recurring - quick update single field
add_action('wp_ajax_uf_quick_update_recurring', 'uf_quick_update_recurring');
function uf_quick_update_recurring() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$id = (int)$_POST['id'];
	$field = sanitize_text_field($_POST['field']);
	$value = sanitize_text_field(wp_unslash($_POST['value']));

	$allowed = array('status', 'category_id', 'partner', 'title', 'amount', 'billing_interval');
	if (!in_array($field, $allowed)) { wp_send_json_error('Feld nicht erlaubt.'); exit(); }

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_recurring';
	$item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND userid = %d", $id, $currentuserid));
	if (!$item) { wp_send_json_error('Nicht gefunden.'); exit(); }

	if ($field === 'category_id' || $field === 'amount') $value = $field === 'amount' ? floatval($value) : (int)$value;

	$wpdb->update($table, array($field => $value), array('id' => $id));
	wp_send_json_success();
	exit();
}

function uf_get_recurring_categories() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring_categories';
	$categories = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table_name WHERE userid = %d ORDER BY title ASC",
		$currentuserid
	));

	wp_send_json_success($categories);
	exit();
}

add_action('wp_ajax_uf_save_recurring_category', 'uf_save_recurring_category');
function uf_save_recurring_category() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
	$title = sanitize_text_field($_POST['title']);

	if (empty($title)) {
		wp_send_json_error('Titel erforderlich');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring_categories';

	if ($category_id > 0) {
		// Update
		$wpdb->update(
			$table_name,
			array('title' => $title),
			array('id' => $category_id, 'userid' => $currentuserid)
		);
	} else {
		// Insert
		$wpdb->insert(
			$table_name,
			array(
				'userid' => $currentuserid,
				'title' => $title,
				'created' => current_time('mysql', 1)
			)
		);
		$category_id = $wpdb->insert_id;
	}

	wp_send_json_success(array('id' => $category_id, 'title' => $title));
	exit();
}

add_action('wp_ajax_uf_delete_recurring_category', 'uf_delete_recurring_category');
function uf_delete_recurring_category() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$category_id = (int)$_POST['category_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring_categories';
	$recurring_table = $wpdb->prefix . 'tallyr_recurring';

	// Check if category is in use
	$in_use = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM $recurring_table WHERE category_id = %d AND userid = %d",
		$category_id, $currentuserid
	));

	if ($in_use > 0) {
		wp_send_json_error('Kategorie wird noch verwendet');
		exit();
	}

	$wpdb->delete($table_name, array('id' => $category_id, 'userid' => $currentuserid));
	wp_send_json_success();
	exit();
}

// ----------------------------------------
// RECURRING ITEMS AJAX
// ----------------------------------------

add_action('wp_ajax_uf_get_recurring', 'uf_get_recurring');
function uf_get_recurring() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$client_filter = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
	$category_filter = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
	$status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

	global $wpdb;
	$recurring_table = $wpdb->prefix . 'tallyr_recurring';
	$categories_table = $wpdb->prefix . 'tallyr_recurring_categories';
	$clients_table = $wpdb->prefix . 'tallyr_clients';
	$log_table = $wpdb->prefix . 'tallyr_recurring_log';

	$where = "r.userid = %d";
	$params = array($currentuserid);

	if ($client_filter > 0) {
		$where .= " AND r.client_id = %d";
		$params[] = $client_filter;
	}
	if ($category_filter > 0) {
		$where .= " AND r.category_id = %d";
		$params[] = $category_filter;
	}
	if (!empty($status_filter)) {
		$where .= " AND r.status = %s";
		$params[] = $status_filter;
	}

	$query = "SELECT r.*,
		c.title as client_name, c.hexcolor as client_color,
		cat.title as category_name,
		(SELECT MAX(billed_date) FROM $log_table WHERE recurring_id = r.id) as last_billed
		FROM $recurring_table r
		LEFT JOIN $clients_table c ON r.client_id = c.id
		LEFT JOIN $categories_table cat ON r.category_id = cat.id
		WHERE $where
		ORDER BY r.title ASC";

	$items = $wpdb->get_results($wpdb->prepare($query, $params));

	// Separate due and upcoming
	$today = date('Y-m-d');
	$due = array();
	$active = array();
	$other = array();

	foreach ($items as $item) {
		$item->is_due = ($item->next_billing <= $today && $item->status === 'active');

		if ($item->is_due) {
			$due[] = $item;
		} else if ($item->status === 'active') {
			$active[] = $item;
		} else {
			$other[] = $item;
		}
	}

	// Calculate monthly totals from ALL items (not filtered)
	$all_items_for_totals = $wpdb->get_results($wpdb->prepare(
		"SELECT amount, billing_interval, status FROM $recurring_table WHERE userid = %d",
		$currentuserid
	));
	$monthly_total = 0;
	$monthly_waiting = 0;
	foreach ($all_items_for_totals as $ti) {
		$monthlyAmount = 0;
		switch ($ti->billing_interval) {
			case 'monthly': $monthlyAmount = $ti->amount; break;
			case 'quarterly': $monthlyAmount = $ti->amount / 3; break;
			case 'yearly': $monthlyAmount = $ti->amount / 12; break;
		}
		if ($ti->status === 'active') {
			$monthly_total += $monthlyAmount;
		} elseif ($ti->status === 'waiting') {
			$monthly_waiting += $monthlyAmount;
		}
	}

	wp_send_json_success(array(
		'due' => $due,
		'active' => $active,
		'other' => $other,
		'due_count' => count($due),
		'monthly_total' => round($monthly_total, 2),
		'monthly_waiting' => round($monthly_waiting, 2),
	));
	exit();
}

add_action('wp_ajax_uf_save_recurring', 'uf_save_recurring');
function uf_save_recurring() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$recurring_id = isset($_POST['recurring_id']) ? (int)$_POST['recurring_id'] : 0;
	$client_id = (int)$_POST['client_id'];
	$category_id = (int)$_POST['category_id'];
	$title = sanitize_text_field($_POST['title']);
	$description = sanitize_textarea_field($_POST['description']);
	$partner = isset($_POST['partner']) ? sanitize_text_field(wp_unslash($_POST['partner'])) : '';
	$amount = floatval($_POST['amount']);
	$billing_interval = sanitize_text_field($_POST['billing_interval']);
	$start_date = sanitize_text_field($_POST['start_date']);
	$end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
	$next_billing = sanitize_text_field($_POST['next_billing']);
	$status = sanitize_text_field($_POST['status']);

	if ($client_id <= 0) {
		wp_send_json_error('Kunde ist Pflichtfeld');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring';

	$data = array(
		'client_id' => $client_id,
		'category_id' => $category_id,
		'title' => $title,
		'description' => $description,
		'partner' => $partner,
		'amount' => $amount,
		'billing_interval' => $billing_interval,
		'start_date' => $start_date,
		'end_date' => $end_date,
		'next_billing' => $next_billing,
		'status' => $status
	);

	if ($recurring_id > 0) {
		// Check ownership
		$existing = $wpdb->get_row($wpdb->prepare(
			"SELECT id FROM $table_name WHERE id = %d AND userid = %d",
			$recurring_id, $currentuserid
		));
		if (!$existing) {
			wp_send_json_error('Nicht gefunden');
			exit();
		}
		$wpdb->update($table_name, $data, array('id' => $recurring_id));
	} else {
		$data['userid'] = $currentuserid;
		$data['created'] = current_time('mysql', 1);
		$wpdb->insert($table_name, $data);
		$recurring_id = $wpdb->insert_id;
	}

	wp_send_json_success(array('id' => $recurring_id));
	exit();
}

add_action('wp_ajax_uf_delete_recurring', 'uf_delete_recurring');
function uf_delete_recurring() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$recurring_id = (int)$_POST['recurring_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring';
	$log_table = $wpdb->prefix . 'tallyr_recurring_log';

	// Delete log entries first
	$wpdb->delete($log_table, array('recurring_id' => $recurring_id));
	// Delete recurring item
	$wpdb->delete($table_name, array('id' => $recurring_id, 'userid' => $currentuserid));

	wp_send_json_success();
	exit();
}

add_action('wp_ajax_uf_mark_recurring_billed', 'uf_mark_recurring_billed');
function uf_mark_recurring_billed() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$recurring_id = (int)$_POST['recurring_id'];
	$notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

	global $wpdb;
	$recurring_table = $wpdb->prefix . 'tallyr_recurring';
	$log_table = $wpdb->prefix . 'tallyr_recurring_log';

	// Get recurring item
	$item = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $recurring_table WHERE id = %d AND userid = %d",
		$recurring_id, $currentuserid
	));

	if (!$item) {
		wp_send_json_error('Nicht gefunden');
		exit();
	}

	$billed_date = date('Y-m-d');

	// Insert log entry
	$wpdb->insert($log_table, array(
		'recurring_id' => $recurring_id,
		'billed_date' => $billed_date,
		'amount' => $item->amount,
		'notes' => $notes,
		'created' => current_time('mysql', 1)
	));

	// Calculate next billing date
	$next_billing = $item->next_billing;
	switch ($item->billing_interval) {
		case 'monthly':
			$next_billing = date('Y-m-d', strtotime($next_billing . ' +1 month'));
			break;
		case 'quarterly':
			$next_billing = date('Y-m-d', strtotime($next_billing . ' +3 months'));
			break;
		case 'yearly':
			$next_billing = date('Y-m-d', strtotime($next_billing . ' +1 year'));
			break;
	}

	// Update next_billing
	$wpdb->update(
		$recurring_table,
		array('next_billing' => $next_billing),
		array('id' => $recurring_id)
	);

	wp_send_json_success(array(
		'next_billing' => $next_billing,
		'billed_date' => $billed_date
	));
	exit();
}

add_action('wp_ajax_uf_get_recurring_log', 'uf_get_recurring_log');
function uf_get_recurring_log() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$recurring_id = (int)$_POST['recurring_id'];

	global $wpdb;
	$recurring_table = $wpdb->prefix . 'tallyr_recurring';
	$log_table = $wpdb->prefix . 'tallyr_recurring_log';

	// Check ownership
	$item = $wpdb->get_row($wpdb->prepare(
		"SELECT id FROM $recurring_table WHERE id = %d AND userid = %d",
		$recurring_id, $currentuserid
	));

	if (!$item) {
		wp_send_json_error('Nicht gefunden');
		exit();
	}

	$log = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $log_table WHERE recurring_id = %d ORDER BY billed_date DESC",
		$recurring_id
	));

	wp_send_json_success($log);
	exit();
}

// Get due count for notification badge
add_action('wp_ajax_uf_get_recurring_due_count', 'uf_get_recurring_due_count');
function uf_get_recurring_due_count() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_recurring';
	$today = date('Y-m-d');

	$count = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM $table_name WHERE userid = %d AND status = 'active' AND next_billing <= %s",
		$currentuserid, $today
	));

	wp_send_json_success(array('count' => (int)$count));
	exit();
}

// ========================================
// WEEKLY PLANNER / TASKS
// ========================================

// Create tasks table
function createtable_tasks() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		title varchar(255) NOT NULL,
		description text,
		estimated_hours decimal(10,2) DEFAULT 0,
		planned_date date,
		position int DEFAULT 0,
		is_recurring tinyint(1) DEFAULT 0,
		recurring_interval varchar(50),
		recurring_parent_id bigint(20),
		status varchar(50) DEFAULT 'pending',
		completed_at datetime,
		time_entry_id bigint(20),
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		KEY userid (userid),
		KEY planned_date (planned_date),
		KEY status (status)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
createtable_tasks();

// ----------------------------------------
// TASKS AJAX ENDPOINTS
// ----------------------------------------

// Get tasks for a week
add_action('wp_ajax_uf_get_tasks', 'uf_get_tasks');
function uf_get_tasks() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : date('Y-m-d', strtotime('monday this week'));
	$include_backlog = isset($_POST['include_backlog']) ? (bool)$_POST['include_backlog'] : true;

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	// Calculate week end (Sunday)
	$week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));

	// Get tasks for the week
	$week_tasks = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table_name
		WHERE userid = %d
		AND planned_date BETWEEN %s AND %s
		AND status != 'deleted'
		ORDER BY planned_date ASC, position ASC",
		$currentuserid, $week_start, $week_end
	));

	// Get backlog tasks (only tasks without planned_date)
	$backlog = array();
	if ($include_backlog) {
		$backlog = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table_name
			WHERE userid = %d
			AND planned_date IS NULL
			AND status = 'pending'
			ORDER BY position ASC, created DESC",
			$currentuserid
		));
	}

	// Organize tasks by day
	$days = array();
	for ($i = 0; $i < 7; $i++) {
		$date = date('Y-m-d', strtotime($week_start . " +$i days"));
		$days[$date] = array(
			'date' => $date,
			'day_name' => strftime('%A', strtotime($date)),
			'day_short' => date('D', strtotime($date)),
			'day_num' => date('d', strtotime($date)),
			'month' => date('M', strtotime($date)),
			'is_today' => $date === date('Y-m-d'),
			'tasks' => array()
		);
	}

	// Assign tasks to days
	foreach ($week_tasks as $task) {
		if (isset($days[$task->planned_date])) {
			$days[$task->planned_date]['tasks'][] = $task;
		}
	}

	// Calculate stats
	$total_planned = 0;
	$total_completed = 0;
	foreach ($week_tasks as $task) {
		$total_planned += (float)$task->estimated_hours;
		if ($task->status === 'completed') {
			$total_completed += (float)$task->estimated_hours;
		}
	}

	wp_send_json_success(array(
		'days' => array_values($days),
		'backlog' => $backlog,
		'week_start' => $week_start,
		'week_end' => $week_end,
		'stats' => array(
			'total_tasks' => count($week_tasks),
			'total_planned_hours' => round($total_planned, 2),
			'total_completed_hours' => round($total_completed, 2),
			'backlog_count' => count($backlog)
		)
	));
	exit();
}

// Save/Update task
add_action('wp_ajax_uf_save_task', 'uf_save_task');
function uf_save_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
	$title = sanitize_text_field($_POST['title']);
	$description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
	$estimated_hours = isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0;
	$planned_date = isset($_POST['planned_date']) && !empty($_POST['planned_date']) ? sanitize_text_field($_POST['planned_date']) : null;
	$is_recurring = isset($_POST['is_recurring']) ? (int)$_POST['is_recurring'] : 0;
	$recurring_interval = isset($_POST['recurring_interval']) ? sanitize_text_field($_POST['recurring_interval']) : null;

	if (empty($title)) {
		wp_send_json_error('Titel erforderlich');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	$data = array(
		'title' => $title,
		'description' => $description,
		'estimated_hours' => $estimated_hours,
		'planned_date' => $planned_date,
		'is_recurring' => $is_recurring,
		'recurring_interval' => $is_recurring ? $recurring_interval : null
	);

	if ($task_id > 0) {
		// Update existing task
		$existing = $wpdb->get_row($wpdb->prepare(
			"SELECT id FROM $table_name WHERE id = %d AND userid = %d",
			$task_id, $currentuserid
		));
		if (!$existing) {
			wp_send_json_error('Nicht gefunden');
			exit();
		}
		$wpdb->update($table_name, $data, array('id' => $task_id));
	} else {
		// Create new task
		$data['userid'] = $currentuserid;
		$data['created'] = current_time('mysql', 1);
		$data['status'] = 'pending';

		// Get max position for the date
		$max_pos = $wpdb->get_var($wpdb->prepare(
			"SELECT MAX(position) FROM $table_name WHERE userid = %d AND (planned_date = %s OR (planned_date IS NULL AND %s IS NULL))",
			$currentuserid, $planned_date, $planned_date
		));
		$data['position'] = ($max_pos !== null) ? $max_pos + 1 : 0;

		$wpdb->insert($table_name, $data);
		$task_id = $wpdb->insert_id;
	}

	// Get the full task data
	$task = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE id = %d",
		$task_id
	));

	wp_send_json_success(array('task' => $task));
	exit();
}

// Delete task
add_action('wp_ajax_uf_delete_task', 'uf_delete_task');
function uf_delete_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$task_id = (int)$_POST['task_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	$wpdb->delete($table_name, array('id' => $task_id, 'userid' => $currentuserid));

	wp_send_json_success();
	exit();
}

// Move task (drag & drop)
add_action('wp_ajax_uf_move_task', 'uf_move_task');
function uf_move_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$task_id = (int)$_POST['task_id'];
	$new_date = isset($_POST['new_date']) && !empty($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : null;
	$new_position = isset($_POST['new_position']) ? (int)$_POST['new_position'] : 0;

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	// Check ownership
	$task = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE id = %d AND userid = %d",
		$task_id, $currentuserid
	));

	if (!$task) {
		wp_send_json_error('Nicht gefunden');
		exit();
	}

	// Update task date and position
	$wpdb->update(
		$table_name,
		array(
			'planned_date' => $new_date,
			'position' => $new_position
		),
		array('id' => $task_id)
	);

	// Reorder other tasks at the new position
	$wpdb->query($wpdb->prepare(
		"UPDATE $table_name
		SET position = position + 1
		WHERE userid = %d
		AND id != %d
		AND (planned_date = %s OR (planned_date IS NULL AND %s IS NULL))
		AND position >= %d",
		$currentuserid, $task_id, $new_date, $new_date, $new_position
	));

	wp_send_json_success();
	exit();
}

// Complete task (creates time entry)
add_action('wp_ajax_uf_complete_task', 'uf_complete_task');
function uf_complete_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$task_id = (int)$_POST['task_id'];
	$actual_hours = isset($_POST['actual_hours']) ? floatval($_POST['actual_hours']) : 0;
	$create_time_entry = isset($_POST['create_time_entry']) ? (bool)$_POST['create_time_entry'] : true;
	$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
	$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

	global $wpdb;
	$task_table = $wpdb->prefix . 'tallyr_tasks';
	$times_table = $wpdb->prefix . 'tallyr_times';

	// Get task
	$task = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $task_table WHERE id = %d AND userid = %d",
		$task_id, $currentuserid
	));

	if (!$task) {
		wp_send_json_error('Nicht gefunden');
		exit();
	}

	$time_entry_id = null;
	$hours_used = $actual_hours > 0 ? $actual_hours : (float)$task->estimated_hours;

	// Create time entry if requested and client is set
	if ($create_time_entry && $client_id > 0 && $hours_used > 0) {
		// Get client hourly rate
		$clients_table = $wpdb->prefix . 'tallyr_clients';
		$client = $wpdb->get_row($wpdb->prepare(
			"SELECT stundensatz, parentclient FROM $clients_table WHERE id = %d AND userid = %d",
			$client_id, $currentuserid
		));

		$stundensatz = $client ? (int)$client->stundensatz : 0;
		$parentclient = $client ? (int)$client->parentclient : 0;

		$wpdb->insert($times_table, array(
			'userid' => $currentuserid,
			'clientid' => $client_id,
			'projectid' => $project_id,
			'description' => $task->title . ($task->description ? "\n" . $task->description : ''),
			'hours' => $hours_used,
			'workdate' => $task->planned_date ? $task->planned_date : date('Y-m-d'),
			'stundensatz' => $stundensatz,
			'parentclient' => $parentclient,
			'notbillable' => 0,
			'created' => current_time('mysql', 1),
			'state' => 1
		));
		$time_entry_id = $wpdb->insert_id;
	}

	// Update task status
	$wpdb->update(
		$task_table,
		array(
			'status' => 'completed',
			'completed_at' => current_time('mysql', 1),
			'time_entry_id' => $time_entry_id
		),
		array('id' => $task_id)
	);

	// Handle recurring task - create next occurrence
	if ($task->is_recurring && $task->recurring_interval) {
		$next_date = null;
		switch ($task->recurring_interval) {
			case 'daily':
				$next_date = date('Y-m-d', strtotime($task->planned_date . ' +1 day'));
				break;
			case 'weekly':
				$next_date = date('Y-m-d', strtotime($task->planned_date . ' +1 week'));
				break;
			case 'monthly':
				$next_date = date('Y-m-d', strtotime($task->planned_date . ' +1 month'));
				break;
		}

		if ($next_date) {
			$wpdb->insert($task_table, array(
				'userid' => $currentuserid,
				'title' => $task->title,
				'description' => $task->description,
				'estimated_hours' => $task->estimated_hours,
				'planned_date' => $next_date,
				'position' => 0,
				'is_recurring' => 1,
				'recurring_interval' => $task->recurring_interval,
				'recurring_parent_id' => $task->recurring_parent_id ? $task->recurring_parent_id : $task_id,
				'status' => 'pending',
				'created' => current_time('mysql', 1)
			));
		}
	}

	wp_send_json_success(array(
		'time_entry_id' => $time_entry_id,
		'hours_logged' => $hours_used
	));
	exit();
}

// Uncomplete task
add_action('wp_ajax_uf_uncomplete_task', 'uf_uncomplete_task');
function uf_uncomplete_task() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$task_id = (int)$_POST['task_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	$wpdb->update(
		$table_name,
		array(
			'status' => 'pending',
			'completed_at' => null
		),
		array('id' => $task_id, 'userid' => $currentuserid)
	);

	wp_send_json_success();
	exit();
}

// Batch update positions (for drag & drop reordering)
add_action('wp_ajax_uf_reorder_tasks', 'uf_reorder_tasks');
function uf_reorder_tasks() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$task_positions = isset($_POST['task_positions']) ? $_POST['task_positions'] : array();

	if (empty($task_positions)) {
		wp_send_json_error('Keine Positionen');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	foreach ($task_positions as $item) {
		$task_id = (int)$item['id'];
		$position = (int)$item['position'];
		$date = isset($item['date']) && !empty($item['date']) ? sanitize_text_field($item['date']) : null;

		$wpdb->update(
			$table_name,
			array(
				'position' => $position,
				'planned_date' => $date
			),
			array('id' => $task_id, 'userid' => $currentuserid)
		);
	}

	wp_send_json_success();
	exit();
}

// Search task titles for autocomplete
add_action('wp_ajax_uf_search_task_titles', 'uf_search_task_titles');
function uf_search_task_titles() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

	if (strlen($search) < 2) {
		wp_send_json_success(array());
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_tasks';

	// Get distinct task titles matching search
	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT DISTINCT title, description, estimated_hours
		FROM $table_name
		WHERE userid = %d
		AND title LIKE %s
		ORDER BY created DESC
		LIMIT 10",
		$currentuserid,
		'%' . $wpdb->esc_like($search) . '%'
	));

	wp_send_json_success($results);
	exit();
}

// ========================================
// MONITOR AJAX ENDPOINTS
// ========================================

// uf_get_monitors
add_action('wp_ajax_uf_get_monitors', 'uf_get_monitors');

function uf_get_monitors() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	// Single query: monitors + 24h stats via subquery
	$monitors = $wpdb->get_results($wpdb->prepare(
		"SELECT m.*, c.title as client_title, c.hexcolor as client_color,
		COALESCE(s.total_24h, 0) as total_24h,
		COALESCE(s.up_24h, 0) as up_24h,
		COALESCE(s.avg_resp, 0) as avg_response
		FROM $monitors_table m
		LEFT JOIN $clients_table c ON m.client_id = c.id
		LEFT JOIN (
			SELECT monitor_id,
				COUNT(*) as total_24h,
				SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_24h,
				ROUND(AVG(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_resp
			FROM $log_table
			WHERE checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
			GROUP BY monitor_id
		) s ON s.monitor_id = m.id
		WHERE m.userid = %d
		ORDER BY m.label ASC",
		$currentuserid
	));

	foreach ($monitors as &$m) {
		$m->uptime_24h = $m->total_24h > 0 ? round(($m->up_24h / $m->total_24h) * 100, 2) : 100;
		$m->avg_response = $m->avg_response ? round($m->avg_response) : 0;
	}

	wp_send_json_success($monitors);
	exit();
}

// uf_save_monitor
add_action('wp_ajax_uf_save_monitor', 'uf_save_monitor');

function uf_save_monitor() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$monitor_id = (INT)$_POST['monitor_id'];
	$url = esc_url_raw($_POST['url']);
	$label = sanitize_text_field($_POST['label']);
	$client_id = (INT)$_POST['client_id'];
	$alert_email = sanitize_email($_POST['alert_email']);
	$report_schedule = sanitize_text_field($_POST['report_schedule']);
	$sub_urls_raw = isset($_POST['sub_urls']) ? sanitize_textarea_field($_POST['sub_urls']) : '';
	$category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

	if (empty($url) || empty($label)) {
		wp_send_json_error('URL und Label sind Pflichtfelder.');
		exit();
	}

	if (!in_array($report_schedule, array('none', 'weekly', 'monthly', 'both'))) {
		$report_schedule = 'none';
	}

	// Parse sub-URLs
	$sub_urls = array();
	if (!empty($sub_urls_raw)) {
		$lines = array_filter(array_map('trim', explode("\n", $sub_urls_raw)));
		foreach ($lines as $line) {
			$clean = esc_url_raw($line);
			if (!empty($clean)) {
				$sub_urls[] = $clean;
			}
		}
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';

	$data = array(
		'url' => $url,
		'label' => $label,
		'client_id' => $client_id,
		'alert_email' => $alert_email,
		'report_schedule' => $report_schedule,
		'sub_urls' => !empty($sub_urls) ? json_encode($sub_urls) : null,
		'category' => $category,
	);

	if ($monitor_id > 0) {
		// Update – verify ownership
		$existing = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d AND userid = %d",
			$monitor_id, $currentuserid
		));
		if (!$existing) {
			wp_send_json_error('Nicht gefunden.');
			exit();
		}
		$wpdb->update($table_name, $data, array('id' => $monitor_id));
	} else {
		// Insert
		$data['userid'] = $currentuserid;
		$data['status'] = 'up';
		$data['created'] = current_time('mysql', 1);
		$wpdb->insert($table_name, $data);
		$monitor_id = $wpdb->insert_id;
	}

	wp_send_json_success(array('id' => $monitor_id));
	exit();
}

// uf_delete_monitor
add_action('wp_ajax_uf_delete_monitor', 'uf_delete_monitor');

function uf_delete_monitor() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (INT)$_POST['monitor_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$existing = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$existing) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	$wpdb->delete($log_table, array('monitor_id' => $monitor_id));
	$wpdb->delete($incidents_table, array('monitor_id' => $monitor_id));
	$wpdb->delete($table_name, array('id' => $monitor_id));

	wp_send_json_success();
	exit();
}

// uf_toggle_monitor
add_action('wp_ajax_uf_toggle_monitor', 'uf_toggle_monitor');

function uf_toggle_monitor() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (INT)$_POST['monitor_id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';

	$existing = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$existing) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	$new_status = ($existing->status === 'paused') ? 'up' : 'paused';
	$wpdb->update($table_name, array('status' => $new_status), array('id' => $monitor_id));

	wp_send_json_success(array('status' => $new_status));
	exit();
}

// uf_get_monitor_stats
add_action('wp_ajax_uf_get_monitor_stats', 'uf_get_monitor_stats');

function uf_get_monitor_stats() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (INT)$_POST['monitor_id'];

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';

	$monitor = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $monitors_table WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$monitor) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	// All period stats in one query
	$period_stats = $wpdb->get_row($wpdb->prepare(
		"SELECT
			COUNT(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as total_24h,
			SUM(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND is_up = 1 THEN 1 ELSE 0 END) as up_24h,
			ROUND(AVG(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_24h,
			COUNT(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as total_7d,
			SUM(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_up = 1 THEN 1 ELSE 0 END) as up_7d,
			ROUND(AVG(CASE WHEN checked_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_7d,
			COUNT(*) as total_30d,
			SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_30d,
			ROUND(AVG(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_30d
		FROM $log_table
		WHERE monitor_id = %d AND checked_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
		$monitor_id
	));

	$stats = array(
		'24h' => array(
			'uptime' => $period_stats->total_24h > 0 ? round(($period_stats->up_24h / $period_stats->total_24h) * 100, 2) : 100,
			'avg_response' => (int)$period_stats->avg_24h,
			'total_checks' => (int)$period_stats->total_24h,
		),
		'7d' => array(
			'uptime' => $period_stats->total_7d > 0 ? round(($period_stats->up_7d / $period_stats->total_7d) * 100, 2) : 100,
			'avg_response' => (int)$period_stats->avg_7d,
			'total_checks' => (int)$period_stats->total_7d,
		),
		'30d' => array(
			'uptime' => $period_stats->total_30d > 0 ? round(($period_stats->up_30d / $period_stats->total_30d) * 100, 2) : 100,
			'avg_response' => (int)$period_stats->avg_30d,
			'total_checks' => (int)$period_stats->total_30d,
		),
	);

	// Chart data (1 query)
	$chart_data = $wpdb->get_results($wpdb->prepare(
		"SELECT
			DATE_FORMAT(checked_at, '%%H:00') as hour_label,
			AVG(response_time_ms) as avg_ms,
			MIN(response_time_ms) as min_ms,
			MAX(response_time_ms) as max_ms
		FROM $log_table
		WHERE monitor_id = %d AND checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
		GROUP BY DATE_FORMAT(checked_at, '%%Y-%%m-%%d %%H')
		ORDER BY checked_at ASC",
		$monitor_id
	));

	// Per-URL stats in one query
	$url_stats = array();
	$all_urls = array($monitor->url);
	if (!empty($monitor->sub_urls)) {
		$subs = json_decode($monitor->sub_urls, true);
		if (is_array($subs)) $all_urls = array_merge($all_urls, $subs);
	}

	if (count($all_urls) > 1) {
		$url_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT checked_url,
				COUNT(*) as total,
				SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_count,
				ROUND(AVG(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_resp
			FROM $log_table
			WHERE monitor_id = %d AND checked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
			GROUP BY checked_url",
			$monitor_id
		));
		$url_map = array();
		foreach ($url_rows as $ur) $url_map[$ur->checked_url] = $ur;

		foreach ($all_urls as $url) {
			$ur = isset($url_map[$url]) ? $url_map[$url] : null;
			$url_stats[] = array(
				'url' => $url,
				'uptime' => $ur && $ur->total > 0 ? round(($ur->up_count / $ur->total) * 100, 2) : 100,
				'avg_response' => $ur ? (int)$ur->avg_resp : 0,
				'total_checks' => $ur ? (int)$ur->total : 0,
				'is_up' => $ur ? ($ur->up_count == $ur->total ? 1 : 0) : 1,
			);
		}
	}

	wp_send_json_success(array(
		'stats' => $stats,
		'chart' => $chart_data,
		'url_stats' => $url_stats,
	));
	exit();
}

// uf_get_monitor_log
add_action('wp_ajax_uf_get_monitor_log', 'uf_get_monitor_log');

function uf_get_monitor_log() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (INT)$_POST['monitor_id'];

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';

	$monitor = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $monitors_table WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$monitor) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	$logs = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $log_table WHERE monitor_id = %d ORDER BY checked_at DESC LIMIT 100",
		$monitor_id
	));

	wp_send_json_success($logs);
	exit();
}

// uf_get_monitor_timeline
add_action('wp_ajax_uf_get_monitor_timeline', 'uf_get_monitor_timeline');

function uf_get_monitor_timeline() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (int)$_POST['monitor_id'];
	$period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30d';

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$monitor = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $monitors_table WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$monitor) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	// Determine date range
	switch ($period) {
		case '90d':  $days = 90; break;
		case '365d': $days = 365; break;
		case 'all':  $days = 9999; break;
		default:     $days = 30; break;
	}

	$date_from = date('Y-m-d', strtotime('-' . $days . ' days'));

	// Get daily aggregates (main URL only)
	$daily = $wpdb->get_results($wpdb->prepare(
		"SELECT
			DATE(checked_at) as day,
			COUNT(*) as total_checks,
			SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_checks,
			ROUND(AVG(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END)) as avg_response,
			MIN(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END) as min_response,
			MAX(CASE WHEN is_up = 1 THEN response_time_ms ELSE NULL END) as max_response
		FROM $log_table
		WHERE monitor_id = %d AND checked_url = %s AND DATE(checked_at) >= %s
		GROUP BY DATE(checked_at)
		ORDER BY day ASC",
		$monitor_id, $monitor->url, $date_from
	));

	// Build complete day range (fill gaps with null)
	$result_days = array();
	if ($daily && count($daily) > 0) {
		$first_day = $daily[0]->day;
		$last_day = $daily[count($daily) - 1]->day;
		$day_map = array();
		foreach ($daily as $d) {
			$day_map[$d->day] = $d;
		}

		$current = new DateTime($first_day);
		$end = new DateTime($last_day);
		$end->modify('+1 day');

		while ($current < $end) {
			$ds = $current->format('Y-m-d');
			if (isset($day_map[$ds])) {
				$d = $day_map[$ds];
				$uptime = $d->total_checks > 0 ? round(($d->up_checks / $d->total_checks) * 100, 2) : 100;
				$result_days[] = array(
					'day' => $ds,
					'day_label' => $current->format('d.m.'),
					'uptime' => $uptime,
					'total_checks' => (int)$d->total_checks,
					'avg_response' => (int)$d->avg_response,
					'min_response' => (int)$d->min_response,
					'max_response' => (int)$d->max_response,
				);
			} else {
				$result_days[] = array(
					'day' => $ds,
					'day_label' => $current->format('d.m.'),
					'uptime' => null,
					'total_checks' => 0,
					'avg_response' => null,
					'min_response' => null,
					'max_response' => null,
				);
			}
			$current->modify('+1 day');
		}
	}

	// Overall summary for period
	$total_checks = 0;
	$total_up = 0;
	$response_sum = 0;
	$response_count = 0;
	foreach ($result_days as $d) {
		$total_checks += $d['total_checks'];
		if ($d['uptime'] !== null) {
			$total_up += round($d['total_checks'] * $d['uptime'] / 100);
		}
		if ($d['avg_response']) {
			$response_sum += $d['avg_response'] * $d['total_checks'];
			$response_count += $d['total_checks'];
		}
	}

	// Incidents in period
	$incidents = (int)$wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
		$monitor_id, $date_from . ' 00:00:00'
	));
	$downtime = (int)$wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(duration_minutes), 0) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
		$monitor_id, $date_from . ' 00:00:00'
	));

	$summary = array(
		'uptime' => $total_checks > 0 ? round(($total_up / $total_checks) * 100, 2) : 100,
		'avg_response' => $response_count > 0 ? round($response_sum / $response_count) : 0,
		'total_checks' => $total_checks,
		'incidents' => $incidents,
		'downtime_min' => $downtime,
		'days' => count($result_days),
	);

	wp_send_json_success(array(
		'days' => $result_days,
		'summary' => $summary,
		'url' => $monitor->url,
		'label' => $monitor->label,
	));
	exit();
}

// uf_get_monitor_incidents
add_action('wp_ajax_uf_get_monitor_incidents', 'uf_get_monitor_incidents');

function uf_get_monitor_incidents() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$monitor_id = (INT)$_POST['monitor_id'];

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$monitor = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $monitors_table WHERE id = %d AND userid = %d",
		$monitor_id, $currentuserid
	));
	if (!$monitor) {
		wp_send_json_error('Nicht gefunden.');
		exit();
	}

	$incidents = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $incidents_table WHERE monitor_id = %d ORDER BY started_at DESC LIMIT 50",
		$monitor_id
	));

	wp_send_json_success($incidents);
	exit();
}

// uf_test_monitor_report
add_action('wp_ajax_uf_test_monitor_report', 'uf_test_monitor_report');

function uf_test_monitor_report() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$user = get_user_by('id', $currentuserid);

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$monitors = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $monitors_table WHERE userid = %d ORDER BY label ASC",
		$currentuserid
	));

	if (!$monitors) {
		wp_send_json_error('Keine Monitors vorhanden.');
		exit();
	}

	// German month names
	$months_de = array(1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember');

	$period_days = 7;
	$from_ts = strtotime('-' . $period_days . ' days midnight');
	$to_ts = strtotime('today midnight');
	$date_from_short = date('d.m.Y', $from_ts);
	$date_to_short = date('d.m.Y', $to_ts);
	$date_from_long = date('d. ', $from_ts) . $months_de[(int)date('n', $from_ts)] . date(' Y', $from_ts);
	$date_to_long = date('d. ', $to_ts) . $months_de[(int)date('n', $to_ts)] . date(' Y', $to_ts);
	$date_from_sql = date('Y-m-d 00:00:00', $from_ts);

	$subject = 'Test Uptime-Report (' . $date_from_short . ' – ' . $date_to_short . ') – Tallyr Monitor';

	// --- Collect per-monitor data ---
	$total_checks_all = 0;
	$total_up_all = 0;
	$total_outages_all = 0;
	$total_downtime_min_all = 0;
	$total_response_all = 0;
	$total_response_count = 0;
	$monitor_rows = array();

	foreach ($monitors as $m) {
		$all_urls = array($m->url);
		if (!empty($m->sub_urls)) {
			$subs = json_decode($m->sub_urls, true);
			if (is_array($subs)) $all_urls = array_merge($all_urls, $subs);
		}

		$url_rows = array();

		foreach ($all_urls as $url) {
			$total = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND checked_at >= %s",
				$m->id, $url, $date_from_sql
			));
			$up = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND is_up = 1 AND checked_at >= %s",
				$m->id, $url, $date_from_sql
			));
			$avg = $wpdb->get_var($wpdb->prepare(
				"SELECT AVG(response_time_ms) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND is_up = 1 AND checked_at >= %s",
				$m->id, $url, $date_from_sql
			));

			$uptime = $total > 0 ? round(($up / $total) * 100, 2) : 100;
			$avg_ms = $avg ? round($avg, 2) : 0;

			$url_rows[] = array(
				'url' => $url,
				'checks' => $total,
				'uptime' => $uptime,
				'avg_ms' => $avg_ms,
			);

			$total_checks_all += $total;
			$total_up_all += $up;
			if ($avg) {
				$total_response_all += $avg * $total;
				$total_response_count += $total;
			}
		}

		$outages = (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
			$m->id, $date_from_sql
		));
		$downtime_min = (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(duration_minutes), 0) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
			$m->id, $date_from_sql
		));

		$total_outages_all += $outages;
		$total_downtime_min_all += $downtime_min;

		$main_uptime = $url_rows[0]['uptime'];

		$monitor_rows[] = array(
			'label' => $m->label,
			'urls' => $url_rows,
			'outages' => $outages,
			'downtime_min' => $downtime_min,
			'uptime' => $main_uptime,
		);
	}

	// --- Summary ---
	$summary_uptime = $total_checks_all > 0 ? round(($total_up_all / $total_checks_all) * 100, 2) : 100;
	$summary_response = $total_response_count > 0 ? round($total_response_all / $total_response_count, 2) : 0;

	$fmt_downtime = function($mins) {
		if ($mins <= 0) return '–';
		if ($mins < 60) return $mins . ' Min';
		$h = floor($mins / 60);
		$m = $mins % 60;
		return $h . ' Std' . ($m > 0 ? ' ' . $m . ' Min' : '');
	};

	// --- Build Email ---
	$body = '<div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;background-color:#f5f5f5;padding:20px;">';

	// Header
	$body .= '<div style="background-color:#ffffff;padding:24px 20px;border-radius:8px 8px 0 0;border:1px solid #eeeeee;border-bottom:none;">';
	$body .= '<h2 style="margin:0;color:#333333;font-size:20px;font-weight:700;">Test Uptime-Report</h2>';
	$body .= '<p style="margin:6px 0 0;color:#999999;font-size:13px;">' . $date_from_long . ' 00:00 Uhr – ' . $date_to_long . ' 00:00 Uhr</p>';
	$body .= '</div>';

	// Summary box
	$body .= '<div style="background-color:#ffffff;padding:20px;border-left:1px solid #eeeeee;border-right:1px solid #eeeeee;">';
	$body .= '<h3 style="margin:0 0 15px;color:#333333;font-size:16px;font-weight:700;">Zusammenfassung ' . $date_from_long . ' – ' . $date_to_long . '</h3>';

	$th = 'style="padding:10px 12px;text-align:center;font-size:12px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;"';
	$td = 'style="padding:14px 12px;text-align:center;font-size:15px;color:#333333;font-weight:700;"';
	$body .= '<table style="width:100%;border-collapse:collapse;" cellpadding="0" cellspacing="0">';
	$body .= '<tr>';
	$body .= '<td ' . $th . '>Checks</td>';
	$body .= '<td ' . $th . '>Uptime</td>';
	$body .= '<td ' . $th . '>Ausfälle</td>';
	$body .= '<td ' . $th . '>Ausfallzeit</td>';
	$body .= '<td ' . $th . '>Response-Time</td>';
	$body .= '</tr><tr>';
	$body .= '<td ' . $td . '>' . number_format($total_checks_all, 0, ',', '.') . '</td>';
	$ucolor = $summary_uptime >= 99 ? '#388e3c' : ($summary_uptime >= 95 ? '#f57c00' : '#d32f2f');
	$body .= '<td style="padding:14px 12px;text-align:center;font-size:15px;color:' . $ucolor . ';font-weight:700;">' . $summary_uptime . '%</td>';
	$body .= '<td ' . $td . '>' . $total_outages_all . '</td>';
	$body .= '<td ' . $td . '>' . $fmt_downtime($total_downtime_min_all) . '</td>';
	$body .= '<td ' . $td . '>' . $summary_response . ' ms</td>';
	$body .= '</tr></table>';
	$body .= '</div>';

	// Divider
	$body .= '<div style="height:1px;background-color:#eeeeee;"></div>';

	// Detail section
	$body .= '<div style="background-color:#ffffff;padding:20px;border-left:1px solid #eeeeee;border-right:1px solid #eeeeee;border-radius:0 0 8px 8px;border-bottom:1px solid #eeeeee;">';
	$body .= '<h3 style="margin:0 0 15px;color:#333333;font-size:16px;font-weight:700;">Details pro Website</h3>';

	$body .= '<table style="width:100%;border-collapse:collapse;" cellpadding="0" cellspacing="0">';
	$dth = 'style="padding:8px 10px;font-size:11px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;text-align:left;"';
	$dthc = 'style="padding:8px 10px;font-size:11px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;text-align:center;"';
	$body .= '<tr>';
	$body .= '<td ' . $dth . '>Website</td>';
	$body .= '<td ' . $dthc . '>Uptime</td>';
	$body .= '<td ' . $dthc . '>Ausfälle</td>';
	$body .= '<td ' . $dthc . '>Ausfallzeit</td>';
	$body .= '<td ' . $dthc . '>Response</td>';
	$body .= '</tr>';

	foreach ($monitor_rows as $row) {
		$main = $row['urls'][0];
		$mc = $main['uptime'] >= 99 ? '#388e3c' : ($main['uptime'] >= 95 ? '#f57c00' : '#d32f2f');

		$body .= '<tr style="border-bottom:1px solid #f0f0f0;">';
		$body .= '<td style="padding:12px 10px;">';
		$body .= '<strong style="color:#333333;font-size:14px;">' . esc_html($row['label']) . '</strong><br>';
		$body .= '<span style="color:#999999;font-size:12px;">' . esc_html($main['url']) . '</span>';
		$body .= '</td>';
		$body .= '<td style="padding:12px 10px;text-align:center;color:' . $mc . ';font-weight:700;font-size:14px;">' . $main['uptime'] . '%</td>';
		$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $row['outages'] . '</td>';
		$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $fmt_downtime($row['downtime_min']) . '</td>';
		$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $main['avg_ms'] . ' ms</td>';
		$body .= '</tr>';

		if (count($row['urls']) > 1) {
			for ($i = 1; $i < count($row['urls']); $i++) {
				$sub = $row['urls'][$i];
				$sc = $sub['uptime'] >= 99 ? '#388e3c' : ($sub['uptime'] >= 95 ? '#f57c00' : '#d32f2f');

				$body .= '<tr style="border-bottom:1px solid #f5f5f5;background-color:#fafafa;">';
				$body .= '<td style="padding:8px 10px 8px 30px;">';
				$body .= '<span style="color:#999999;font-size:12px;">↳ ' . esc_html($sub['url']) . '</span>';
				$body .= '</td>';
				$body .= '<td style="padding:8px 10px;text-align:center;color:' . $sc . ';font-weight:600;font-size:13px;">' . $sub['uptime'] . '%</td>';
				$body .= '<td style="padding:8px 10px;text-align:center;color:#999999;font-size:13px;">–</td>';
				$body .= '<td style="padding:8px 10px;text-align:center;color:#999999;font-size:13px;">–</td>';
				$body .= '<td style="padding:8px 10px;text-align:center;color:#333333;font-size:13px;">' . $sub['avg_ms'] . ' ms</td>';
				$body .= '</tr>';
			}
		}
	}

	$body .= '</table>';
	$body .= '</div>';

	// Footer
	$body .= '<div style="padding:15px 20px;text-align:center;">';
	$body .= '<p style="margin:0;color:#999999;font-size:12px;">Tallyr Monitor – Test Report</p>';
	$body .= '</div>';

	$body .= '</div>';

	$headers = array('Content-Type: text/html; charset=UTF-8');
	$sent = wp_mail($user->user_email, $subject, $body, $headers);
	tallyr_log_email('report', $user->user_email, $subject, 'Test Report', $sent);

	wp_send_json_success('Report wurde an ' . $user->user_email . ' gesendet.');
	exit();
}

// uf_fetch_site_title
add_action('wp_ajax_uf_fetch_site_title', 'uf_fetch_site_title');

function uf_fetch_site_title() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	$url = esc_url_raw($_POST['url']);
	if (empty($url)) {
		wp_send_json_error('Keine URL.');
		exit();
	}

	$response = wp_remote_get($url, array(
		'timeout' => 8,
		'redirection' => 3,
		'sslverify' => false,
		'user-agent' => 'TallyrMonitor/1.0',
	));

	if (is_wp_error($response)) {
		wp_send_json_error('Nicht erreichbar.');
		exit();
	}

	$body = wp_remote_retrieve_body($response);
	$title = '';

	if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $body, $matches)) {
		$title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
		// Clean up common suffixes
		$title = preg_replace('/\s*[\|–—-]\s*$/', '', $title);
	}

	if (empty($title)) {
		// Fallback: use domain name
		$parsed = parse_url($url);
		$title = isset($parsed['host']) ? $parsed['host'] : $url;
	}

	wp_send_json_success(array('title' => $title));
	exit();
}

// uf_batch_import_monitors
add_action('wp_ajax_uf_batch_import_monitors', 'uf_batch_import_monitors');

function uf_batch_import_monitors() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$urls_raw = sanitize_textarea_field($_POST['urls']);
	$client_id = (INT)$_POST['client_id'];
	$category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
	$report_schedule = sanitize_text_field($_POST['report_schedule']);

	if (!in_array($report_schedule, array('none', 'weekly', 'monthly', 'both'))) {
		$report_schedule = 'both';
	}

	$lines = array_filter(array_map('trim', explode("\n", $urls_raw)));
	if (empty($lines)) {
		wp_send_json_error('Keine URLs angegeben.');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';
	$imported = 0;
	$errors = array();

	foreach ($lines as $line) {
		$url = esc_url_raw($line);
		if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
			$errors[] = $line;
			continue;
		}

		// Check for duplicate
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM $table_name WHERE userid = %d AND url = %s",
			$currentuserid, $url
		));
		if ($exists) {
			$errors[] = $url . ' (existiert bereits)';
			continue;
		}

		// Fetch title
		$title = '';
		$response = wp_remote_get($url, array(
			'timeout' => 8,
			'redirection' => 3,
			'sslverify' => false,
			'user-agent' => 'TallyrMonitor/1.0',
		));

		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $body, $matches)) {
				$title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
				$title = preg_replace('/\s*[\|–—-]\s*$/', '', $title);
			}
		}

		if (empty($title)) {
			$parsed = parse_url($url);
			$title = isset($parsed['host']) ? $parsed['host'] : $url;
		}

		$wpdb->insert($table_name, array(
			'userid' => $currentuserid,
			'url' => $url,
			'label' => $title,
			'client_id' => $client_id,
			'category' => $category,
			'report_schedule' => $report_schedule,
			'status' => 'up',
			'created' => current_time('mysql', 1),
		));
		$imported++;
	}

	wp_send_json_success(array(
		'imported' => $imported,
		'errors' => $errors,
	));
	exit();
}

// uf_get_monitor_cron_log
add_action('wp_ajax_uf_get_monitor_cron_log', 'uf_get_monitor_cron_log');

function uf_get_monitor_cron_log() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	if (!current_user_can('manage_options')) {
		wp_send_json_error('Kein Zugriff.');
		exit();
	}

	$log = get_option('tallyr_monitor_cron_log', array());
	$log = array_reverse($log); // newest first

	wp_send_json_success($log);
	exit();
}

// uf_get_monitor_email_log
add_action('wp_ajax_uf_get_monitor_email_log', 'uf_get_monitor_email_log');

function uf_get_monitor_email_log() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');

	if (!current_user_can('manage_options')) {
		wp_send_json_error('Kein Zugriff.');
		exit();
	}

	$log = get_option('tallyr_monitor_email_log', array());
	$log = array_reverse($log);

	wp_send_json_success($log);
	exit();
}

// uf_rename_monitor_category
add_action('wp_ajax_uf_rename_monitor_category', 'uf_rename_monitor_category');

function uf_rename_monitor_category() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$old_name = sanitize_text_field($_POST['old_name']);
	$new_name = sanitize_text_field($_POST['new_name']);

	if (empty($old_name) || empty($new_name)) {
		wp_send_json_error('Name darf nicht leer sein.');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';

	$updated = $wpdb->query($wpdb->prepare(
		"UPDATE $table_name SET category = %s WHERE userid = %d AND category = %s",
		$new_name, $currentuserid, $old_name
	));

	wp_send_json_success(array('updated' => $updated));
	exit();
}

// uf_bulk_update_monitors
add_action('wp_ajax_uf_bulk_update_monitors', 'uf_bulk_update_monitors');

function uf_bulk_update_monitors() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : array();
	$category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : null;

	if (empty($ids)) {
		wp_send_json_error('Keine Einträge ausgewählt.');
		exit();
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';

	$updated = 0;
	foreach ($ids as $id) {
		$data = array();
		if ($category !== null) {
			$data['category'] = $category;
		}
		if (!empty($data)) {
			$wpdb->update($table_name, $data, array('id' => $id, 'userid' => $currentuserid));
			$updated++;
		}
	}

	wp_send_json_success(array('updated' => $updated));
	exit();
}


// ========================================
// PROJEKTPLANNER
// ========================================

// Database tables
function createtable_projektplanner() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$table1 = $wpdb->prefix . 'tallyr_projektplanner';
	$sql1 = "CREATE TABLE IF NOT EXISTS $table1 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		client_id bigint(20) NOT NULL,
		title varchar(255) NOT NULL,
		period_from date DEFAULT NULL,
		period_to date DEFAULT NULL,
		quarter varchar(10) DEFAULT NULL,
		asana_project_gid varchar(100) DEFAULT '',
		asana_section_gid varchar(100) DEFAULT '',
		share_hash varchar(64) DEFAULT '',
		share_password varchar(255) DEFAULT '',
		plan_status varchar(50) DEFAULT 'entwurf',
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		state int NOT NULL DEFAULT 1,
		PRIMARY KEY (id),
		KEY userid (userid),
		KEY client_id (client_id)
	) $charset_collate;";

	$table2 = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		plan_id bigint(20) NOT NULL,
		type varchar(20) NOT NULL DEFAULT 'item',
		description text,
		date_from date DEFAULT NULL,
		date_to date DEFAULT NULL,
		timeframe varchar(100) DEFAULT '',
		ist_hours decimal(10,2) DEFAULT 0,
		planned_hours decimal(10,2) DEFAULT 0,
		responsible varchar(255) DEFAULT '',
		deadline varchar(100) DEFAULT '',
		is_done tinyint(1) DEFAULT 0,
		is_placeholder tinyint(1) DEFAULT 0,
		is_focus tinyint(1) DEFAULT 0,
		actual_hours varchar(100) DEFAULT '',
		notes text,
		asana_gid varchar(100) DEFAULT '',
		asana_url varchar(500) DEFAULT '',
		asana_task_name varchar(255) DEFAULT '',
		position int DEFAULT 0,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id),
		KEY type (type)
	) $charset_collate;";

	$table3 = $wpdb->prefix . 'tallyr_projektplanner_shares';
	$sql3 = "CREATE TABLE IF NOT EXISTS $table3 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		plan_id bigint(20) NOT NULL,
		user_id bigint(20) NOT NULL,
		permission varchar(20) NOT NULL DEFAULT 'read',
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY plan_user (plan_id, user_id),
		KEY user_id (user_id)
	) $charset_collate;";

	$table4 = $wpdb->prefix . 'tallyr_projektplanner_revisions';
	$sql4 = "CREATE TABLE IF NOT EXISTS $table4 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		plan_id bigint(20) NOT NULL,
		user_id bigint(20) NOT NULL,
		snapshot longtext NOT NULL,
		label varchar(255) DEFAULT '',
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql1);
	dbDelta($sql2);
	dbDelta($sql3);
	$table5 = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$sql5 = "CREATE TABLE IF NOT EXISTS $table5 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		plan_id bigint(20) NOT NULL,
		row_id bigint(20) DEFAULT 0,
		author_name varchar(255) DEFAULT '',
		feedback_type varchar(20) DEFAULT 'comment',
		message text,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id),
		KEY row_id (row_id)
	) $charset_collate;";

	dbDelta($sql4);
	dbDelta($sql5);

	// Budget table for monthly Soll targets per plan (overrides)
	$table6 = $wpdb->prefix . 'tallyr_projektplanner_budget';
	$sql6 = "CREATE TABLE IF NOT EXISTS $table6 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		plan_id bigint(20) NOT NULL,
		year smallint NOT NULL,
		month tinyint NOT NULL,
		soll_ts decimal(10,2) DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY plan_year_month (plan_id, year, month)
	) $charset_collate;";
	dbDelta($sql6);

	// Client-level budget (Soll + optional Ist override)
	$table7 = $wpdb->prefix . 'tallyr_client_budget';
	$sql7 = "CREATE TABLE IF NOT EXISTS $table7 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		client_id bigint(20) NOT NULL,
		year smallint NOT NULL,
		month tinyint NOT NULL,
		soll_ts decimal(10,2) DEFAULT 0,
		ist_override decimal(10,2) DEFAULT NULL,
		ist_note varchar(255) DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY client_year_month (client_id, year, month)
	) $charset_collate;";
	dbDelta($sql7);

	// Ensure ist_override/ist_note columns exist
	if ($wpdb->get_var("SHOW TABLES LIKE '$table7'")) {
		$cb_cols = $wpdb->get_col("SHOW COLUMNS FROM $table7", 0);
		if (!in_array('ist_override', $cb_cols)) {
			$wpdb->query("ALTER TABLE $table7 ADD COLUMN ist_override decimal(10,2) DEFAULT NULL AFTER soll_ts");
		}
		if (!in_array('ist_note', $cb_cols)) {
			$wpdb->query("ALTER TABLE $table7 ADD COLUMN ist_note varchar(255) DEFAULT '' AFTER ist_override");
		}
	}

	// Person share links
	$table8 = $wpdb->prefix . 'tallyr_person_shares';
	$sql8 = "CREATE TABLE IF NOT EXISTS $table8 (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		person_name varchar(255) NOT NULL,
		share_hash varchar(64) NOT NULL,
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY share_hash (share_hash),
		KEY userid_person (userid, person_name)
	) $charset_collate;";
	dbDelta($sql8);
}
createtable_projektplanner();

// Ensure new columns exist on existing tables
function tallyr_ensure_projektplanner_columns() {
	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	// Check if table exists
	if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;

	$cols = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);

	if (!in_array('date_from', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN date_from date DEFAULT NULL AFTER description");
	}
	if (!in_array('date_to', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN date_to date DEFAULT NULL AFTER date_from");
	}

	// Fix column types if they were created with old schema
	$deadline_col = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'deadline'");
	if ($deadline_col && strpos(strtolower($deadline_col->Type), 'date') !== false && strpos(strtolower($deadline_col->Type), 'datetime') === false) {
		$wpdb->query("ALTER TABLE $table MODIFY COLUMN deadline varchar(100) DEFAULT ''");
	}
	$actual_col = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'actual_hours'");
	if ($actual_col && strpos(strtolower($actual_col->Type), 'decimal') !== false) {
		$wpdb->query("ALTER TABLE $table MODIFY COLUMN actual_hours varchar(100) DEFAULT ''");
	}
	if (!in_array('timeframe', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN timeframe varchar(100) DEFAULT '' AFTER date_to");
	}
	if (!in_array('is_placeholder', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN is_placeholder tinyint(1) DEFAULT 0 AFTER is_done");
	}
	if (!in_array('is_focus', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN is_focus tinyint(1) DEFAULT 0 AFTER is_placeholder");
	}
	if (!in_array('ist_hours', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN ist_hours decimal(10,2) DEFAULT 0 AFTER date_to");
	}
	if (!in_array('asana_gid', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN asana_gid varchar(100) DEFAULT '' AFTER notes");
	}
	if (!in_array('no_ticket', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN no_ticket tinyint(1) DEFAULT 0 AFTER is_focus");
	}
	if (!in_array('lead_responsible', $cols)) {
		$wpdb->query("ALTER TABLE $table ADD COLUMN lead_responsible varchar(255) DEFAULT '' AFTER responsible");
	}

	// Ensure asana_project_gid on plans table
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	if ($wpdb->get_var("SHOW TABLES LIKE '$plans_table'")) {
		$plan_cols = $wpdb->get_col("SHOW COLUMNS FROM $plans_table", 0);
		if (!in_array('asana_project_gid', $plan_cols)) {
			$wpdb->query("ALTER TABLE $plans_table ADD COLUMN asana_project_gid varchar(100) DEFAULT '' AFTER quarter");
		}
		if (!in_array('asana_section_gid', $plan_cols)) {
			$wpdb->query("ALTER TABLE $plans_table ADD COLUMN asana_section_gid varchar(100) DEFAULT '' AFTER asana_project_gid");
		}
		if (!in_array('plan_status', $plan_cols)) {
			$wpdb->query("ALTER TABLE $plans_table ADD COLUMN plan_status varchar(50) DEFAULT 'aktiv' AFTER share_password");
		}
		if (!in_array('share_hash', $plan_cols)) {
			$wpdb->query("ALTER TABLE $plans_table ADD COLUMN share_hash varchar(64) DEFAULT '' AFTER asana_section_gid");
		}
		if (!in_array('share_password', $plan_cols)) {
			$wpdb->query("ALTER TABLE $plans_table ADD COLUMN share_password varchar(255) DEFAULT '' AFTER share_hash");
		}
	}

	// Ensure uebertrag_ts + abrechnungsmodus on clients table
	$clients_table = $wpdb->prefix . 'tallyr_clients';
	if ($wpdb->get_var("SHOW TABLES LIKE '$clients_table'")) {
		$c_cols = $wpdb->get_col("SHOW COLUMNS FROM $clients_table", 0);
		if (!in_array('uebertrag_ts', $c_cols)) {
			$wpdb->query("ALTER TABLE $clients_table ADD COLUMN uebertrag_ts decimal(10,2) DEFAULT 0");
		}
		if (!in_array('abrechnungsmodus', $c_cols)) {
			$wpdb->query("ALTER TABLE $clients_table ADD COLUMN abrechnungsmodus varchar(20) DEFAULT 'ts'");
		}
		if (!in_array('uebertrag_notiz', $c_cols)) {
			$wpdb->query("ALTER TABLE $clients_table ADD COLUMN uebertrag_notiz varchar(255) DEFAULT ''");
		}
	}
}
tallyr_ensure_projektplanner_columns();

// uf_pp_get_textbausteine - load text snippets from Asana project (cached 1 week)
add_action('wp_ajax_uf_pp_get_textbausteine', 'uf_pp_get_textbausteine');

function uf_pp_get_textbausteine() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$force = isset($_POST['force']) && $_POST['force'] === '1';

	$project_gid = get_user_meta($currentuserid, 'tallyr_pp_textbausteine_project', true);
	if (empty($project_gid)) {
		wp_send_json_success(array());
		exit();
	}

	$cache_key = 'tallyr_pp_textbausteine_' . $currentuserid;

	if (!$force) {
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			wp_send_json_success($cached);
			exit();
		}
	}

	$token = get_user_meta($currentuserid, 'tallyr_asana_token', true);
	if (empty($token)) {
		wp_send_json_error('Kein Asana Token.');
		exit();
	}

	$asana = new Tallyr_Asana_API($token);

	// Load ALL tasks from the project (not just incomplete)
	$all_tasks = array();
	$offset = null;
	do {
		$url = '/projects/' . $project_gid . '/tasks?opt_fields=name,gid,notes&limit=100';
		if ($offset) $url .= '&offset=' . $offset;
		$result = $asana->request_public($url);
		if (isset($result['data'])) {
			foreach ($result['data'] as $task) {
				if (!empty($task['name'])) {
					$all_tasks[] = array(
						'name' => $task['name'],
						'notes' => isset($task['notes']) ? $task['notes'] : '',
						'gid' => $task['gid'],
					);
				}
			}
		}
		$offset = isset($result['next_page']['offset']) ? $result['next_page']['offset'] : null;
	} while ($offset);

	// Cache for 1 week
	set_transient($cache_key, $all_tasks, WEEK_IN_SECONDS);

	wp_send_json_success($all_tasks);
	exit();
}

// uf_pp_export_report - server-side Excel export with PhpSpreadsheet
add_action('wp_ajax_uf_pp_export_report', 'uf_pp_export_report');

function uf_pp_export_report() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	$access = pp_check_access($plan_id, $currentuserid);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	$plan = $wpdb->get_row($wpdb->prepare(
		"SELECT p.*, c.title AS client_title, c.shortdesc AS client_short, c.url AS client_url
		 FROM $plans_table p LEFT JOIN $clients_table c ON c.id = p.client_id
		 WHERE p.id = %d", $plan_id
	));
	$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC", $plan_id));

	// Get kürzels
	$kuerzel_map = array();
	$users = get_users(array('fields' => array('ID', 'display_name')));
	foreach ($users as $u) {
		$k = get_user_meta($u->ID, 'tallyr_kuerzel', true);
		if ($k) $kuerzel_map[$u->display_name] = $k;
	}
	$custom_tags = get_user_meta($plan->userid, 'tallyr_pp_tags', true);
	$custom_tags = $custom_tags ? json_decode($custom_tags, true) : array();
	if (is_array($custom_tags)) {
		foreach ($custom_tags as $tag) $kuerzel_map[$tag['name']] = $tag['kuerzel'];
	}

	$pp_kuerzel_fn = function($name) use ($kuerzel_map) {
		$name = trim($name);
		if (isset($kuerzel_map[$name])) return $kuerzel_map[$name];
		return strtoupper(substr($name, 0, 3));
	};

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle(substr($plan->client_short ?: $plan->client_title ?: 'Report', 0, 31));

	// Column widths
	$sheet->getColumnDimension('A')->setWidth(57.5);
	$sheet->getColumnDimension('B')->setWidth(23);

	// Default font
	$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

	// All cells thin border style
	$thinBorder = [
		'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
	];
	$grayFill = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D8D8D8']]];

	$r = 1;
	$periodLabel = '';
	if ($plan->period_from && $plan->period_to) {
		$periodLabel = date('d.m.Y', strtotime($plan->period_from)) . ' – ' . date('d.m.Y', strtotime($plan->period_to));
	}

	// Zoom 150%
	$sheet->getSheetView()->setZoomScale(150);

	// R1: Header - Kundenname + Planname
	$sheet->setCellValue("A{$r}", $plan->client_title ?: '');
	$sheet->setCellValue("B{$r}", $plan->title ?: '');
	$sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
	$sheet->getStyle("B{$r}")->getFont()->setBold(true)->setSize(12);
	$sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$r++;

	// R2: URL + Datum/Periode
	$clientUrl = $plan->client_url ?: '';
	$sheet->setCellValue("A{$r}", $clientUrl);
	$sheet->setCellValue("B{$r}", $periodLabel);
	$sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
	$sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	// Hyperlink on first URL only
	if ($clientUrl) {
		$firstUrl = strtok($clientUrl, "\n");
		if (preg_match('/^https?:\/\//', trim($firstUrl))) {
			$sheet->getCell("A{$r}")->getHyperlink()->setUrl(trim($firstUrl));
		}
	}
	$r++;

	// R3: empty
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$r++;

	// R4: column header - bold, centered, gray bg, freeze pane below
	$sheet->setCellValue("B{$r}", 'Aufwand ca. in Std.');
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
	$sheet->getStyle("B{$r}")->getFont()->setBold(true);
	$sheet->getStyle("B{$r}")->getAlignment()
		->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
		->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
	$r++;

	// Freeze rows 1-4 (sticky header)
	$sheet->freezePane("A{$r}");

	// Helper: set cell B as numeric value, centered V+H
	$setHoursCell = function($sheet, $r, $val) use ($thinBorder) {
		if ($val) {
			$num = floatval($val);
			$sheet->setCellValueExplicit("B{$r}", $num, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
			// Format: no trailing zeros, no decimal point for whole numbers
			if ($num == floor($num)) {
				$sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0');
			} else {
				$sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0.##');
			}
		}
		$sheet->getStyle("B{$r}")->getAlignment()
			->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
			->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
	};

	$sectionOpen = false;
	$sectionHours = 0;
	$totalHours = 0;

	foreach ($rows as $row) {
		// Skip spacers and placeholder items
		if ($row->type === 'spacer') continue;
		if ($row->type === 'item' && intval($row->is_placeholder)) continue;

		if ($row->type === 'section') {
			if ($sectionOpen) {
				$sheet->setCellValue("A{$r}", 'Aufwand ca. in Std.');
				$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
				$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
				$setHoursCell($sheet, $r, $sectionHours);
				$r++;
				$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
				$r++;
			}
			$sectionOpen = true;
			$sectionHours = 0;
			$sheet->setCellValue("A{$r}", $row->description ?: '');
			$sheet->getStyle("A{$r}")->getFont()->setBold(true);
			$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
			$sheet->getStyle("B{$r}")->getAlignment()
				->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
				->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
			$r++;
			continue;
		}

		if ($row->type === 'note') {
			$sheet->setCellValue("A{$r}", $row->description ?: '');
			$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
			$sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
			$sheet->getStyle("B{$r}")->getAlignment()
				->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
				->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
			if (preg_match('/^https?:\/\//', $row->description)) {
				$sheet->getCell("A{$r}")->getHyperlink()->setUrl($row->description);
			}
			$r++;
			continue;
		}

		// Item
		$h = floatval($row->ist_hours);
		$sectionHours += $h;
		$totalHours += $h;

		$desc = $row->description ?: '';
		$suffix = array();
		if ($row->timeframe) $suffix[] = $row->timeframe;
		$seen_kz = array();
		// Lead responsible first
		$lead = isset($row->lead_responsible) ? trim($row->lead_responsible) : '';
		if ($lead) {
			$kz = $pp_kuerzel_fn($lead);
			$seen_kz[$kz] = true;
			$suffix[] = $kz;
		}
		// Then other responsible (no duplicates)
		if ($row->responsible) {
			foreach (explode(',', $row->responsible) as $name) {
				$name = trim($name);
				if (!$name) continue;
				$kz = $pp_kuerzel_fn($name);
				if (!isset($seen_kz[$kz])) {
					$seen_kz[$kz] = true;
					$suffix[] = $kz;
				}
			}
		}
		if ($suffix) $desc .= ' (' . implode(', ', $suffix) . ')';

		$sheet->setCellValue("A{$r}", $desc);
		$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
		$sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
		$setHoursCell($sheet, $r, $h);
		$r++;
	}

	// Close last section
	if ($sectionOpen) {
		$sheet->setCellValue("A{$r}", 'Aufwand ca. in Std.');
		$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
		$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
		$setHoursCell($sheet, $r, $sectionHours);
		$r++;
	}

	// Footer
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$r++;

	// Gesamtsumme
	$sheet->setCellValue("A{$r}", 'Aufwand ca. insgesamt in Std.');
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
	$sheet->getStyle("B{$r}")->getFont()->setBold(true);
	$setHoursCell($sheet, $r, $totalHours);
	$r++;

	// Tagessätze: auf halbe Tagessätze abrunden (8h = 1 TS, 4h = 0.5 TS)
	$ts = $totalHours > 0 ? floor($totalHours / 4) * 0.5 : 0;
	// Format: "9 Tagessätze" or "6,5 Tagessätze"
	$tsLabel = str_replace('.', ',', $ts) . ' Tagessätze';
	$sheet->setCellValue("A{$r}", 'Aufwand in Tagessätzen');
	$sheet->setCellValue("B{$r}", $tsLabel);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
	$sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
	$sheet->getStyle("B{$r}")->getAlignment()
		->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
		->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
	$r += 2;

	$sheet->setCellValue("A{$r}", 'Noch Rückfragen? Kontakt zur Thoxan Communications GmbH unter https://www.thoxan.com/kontakt');
	$sheet->getStyle("A{$r}")->getFont()->setItalic(true);

	// Let Excel auto-calculate row heights: set all rows to auto (-1)
	for ($i = 1; $i <= $r; $i++) {
		$sheet->getRowDimension($i)->setRowHeight(-1);
	}

	// Save to temp file
	$filename = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $plan->client_short ?: $plan->client_title ?: 'report'));
	$periodFile = $plan->period_from ? $plan->period_from . '-' . $plan->period_to : 'export';
	$tmpFile = tempnam(sys_get_temp_dir(), 'pp_') . '.xlsx';

	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save($tmpFile);

	// Return file URL or base64
	$data = base64_encode(file_get_contents($tmpFile));
	unlink($tmpFile);

	wp_send_json_success(array(
		'filename' => $filename . '-report-' . $periodFile . '.xlsx',
		'data' => $data,
	));
	exit();
}

// uf_pp_submit_feedback - public: submit feedback on a shared plan
add_action('wp_ajax_nopriv_uf_pp_submit_feedback', 'uf_pp_submit_feedback');
add_action('wp_ajax_uf_pp_submit_feedback', 'uf_pp_submit_feedback');

function uf_pp_submit_feedback() {
	$share_hash = sanitize_text_field($_POST['share_hash']);
	$row_id = (int)$_POST['row_id'];
	$author = sanitize_text_field(wp_unslash($_POST['author_name']));
	$type = sanitize_text_field($_POST['feedback_type']); // like, dislike, comment
	$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

	if (!in_array($type, array('like', 'dislike', 'comment'))) $type = 'comment';

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT id FROM $plans_table WHERE share_hash = %s AND state = 1 AND share_hash != ''", $share_hash));
	if (!$plan) { wp_send_json_error('Plan nicht gefunden.'); exit(); }

	$wpdb->insert($feedback_table, array(
		'plan_id' => $plan->id,
		'row_id' => $row_id,
		'author_name' => $author ?: 'Anonym',
		'feedback_type' => $type,
		'message' => $message,
	));

	wp_send_json_success();
	exit();
}

// uf_pp_mark_feedback_read
add_action('wp_ajax_uf_pp_mark_feedback_read', 'uf_pp_mark_feedback_read');

function uf_pp_mark_feedback_read() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$feedback_id = (int)$_POST['feedback_id'];

	global $wpdb;
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';

	// Add read_at column if not exists
	$cols = $wpdb->get_col("SHOW COLUMNS FROM $feedback_table", 0);
	if (!in_array('read_at', $cols)) {
		$wpdb->query("ALTER TABLE $feedback_table ADD COLUMN read_at datetime DEFAULT NULL");
	}

	$wpdb->update($feedback_table, array('read_at' => current_time('mysql')), array('id' => $feedback_id));
	wp_send_json_success();
	exit();
}

// uf_pp_delete_feedback
add_action('wp_ajax_uf_pp_delete_feedback', 'uf_pp_delete_feedback');
function uf_pp_delete_feedback() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$feedback_id = (int)$_POST['feedback_id'];
	global $wpdb;
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$fb = $wpdb->get_row($wpdb->prepare("SELECT * FROM $feedback_table WHERE id = %d", $feedback_id));
	if (!$fb) { wp_send_json_error('Nicht gefunden.'); exit(); }
	$access = pp_check_access($fb->plan_id, get_current_user_id(), true);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }
	$wpdb->delete($feedback_table, array('id' => $feedback_id));
	wp_send_json_success();
	exit();
}

// uf_pp_delete_feedback_public - delete own feedback via share hash
add_action('wp_ajax_nopriv_uf_pp_delete_feedback_public', 'uf_pp_delete_feedback_public');
add_action('wp_ajax_uf_pp_delete_feedback_public', 'uf_pp_delete_feedback_public');
function uf_pp_delete_feedback_public() {
	$feedback_id = (int)$_POST['feedback_id'];
	$share_hash = sanitize_text_field($_POST['share_hash']);
	global $wpdb;
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$fb = $wpdb->get_row($wpdb->prepare("SELECT f.*, p.share_hash FROM $feedback_table f JOIN $plans_table p ON p.id = f.plan_id WHERE f.id = %d", $feedback_id));
	if (!$fb || $fb->share_hash !== $share_hash) { wp_send_json_error('Nicht gefunden.'); exit(); }
	$wpdb->delete($feedback_table, array('id' => $feedback_id));
	wp_send_json_success();
	exit();
}

// uf_pp_edit_feedback_public - edit own feedback via share hash
add_action('wp_ajax_nopriv_uf_pp_edit_feedback_public', 'uf_pp_edit_feedback_public');
add_action('wp_ajax_uf_pp_edit_feedback_public', 'uf_pp_edit_feedback_public');
function uf_pp_edit_feedback_public() {
	$feedback_id = (int)$_POST['feedback_id'];
	$share_hash = sanitize_text_field($_POST['share_hash']);
	$message = sanitize_textarea_field(wp_unslash($_POST['message']));
	global $wpdb;
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$fb = $wpdb->get_row($wpdb->prepare("SELECT f.*, p.share_hash FROM $feedback_table f JOIN $plans_table p ON p.id = f.plan_id WHERE f.id = %d", $feedback_id));
	if (!$fb || $fb->share_hash !== $share_hash) { wp_send_json_error('Nicht gefunden.'); exit(); }
	$wpdb->update($feedback_table, array('message' => $message), array('id' => $feedback_id));
	wp_send_json_success();
	exit();
}

// uf_pp_get_unread_feedback_count - global unread count
add_action('wp_ajax_uf_pp_get_unread_feedback_count', 'uf_pp_get_unread_feedback_count');
function uf_pp_get_unread_feedback_count() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	global $wpdb;
	$fb_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';

	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT p.id as plan_id, p.title, c.shortdesc as client_short, COUNT(f.id) as unread_count
		 FROM $fb_table f
		 JOIN $plans_table p ON p.id = f.plan_id AND p.userid = %d
		 LEFT JOIN {$wpdb->prefix}tallyr_clients c ON c.id = p.client_id
		 WHERE (f.read_at IS NULL)
		 GROUP BY p.id
		 ORDER BY unread_count DESC",
		$currentuserid
	));

	wp_send_json_success($results);
	exit();
}

// uf_pp_get_feedback - get feedback for a plan (for plan owner)
add_action('wp_ajax_uf_pp_get_feedback', 'uf_pp_get_feedback');

function uf_pp_get_feedback() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$plan_id = (int)$_POST['plan_id'];
	$currentuserid = get_current_user_id();

	$access = pp_check_access($plan_id, $currentuserid);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }

	global $wpdb;
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$feedback = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $feedback_table WHERE plan_id = %d ORDER BY created DESC", $plan_id
	));

	wp_send_json_success($feedback);
	exit();
}

// Helper: check if user has access to a plan (owner or shared)
function pp_check_access($plan_id, $user_id, $need_write = false) {
	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$shares_table = $wpdb->prefix . 'tallyr_projektplanner_shares';

	// Owner?
	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND state = 1", $plan_id));
	if (!$plan) return false;
	if ($plan->userid == $user_id) return 'owner';

	// Shared?
	$share = $wpdb->get_row($wpdb->prepare("SELECT * FROM $shares_table WHERE plan_id = %d AND user_id = %d", $plan_id, $user_id));
	if (!$share) return false;
	if ($need_write && $share->permission !== 'write' && $share->permission !== 'edit') return false;
	return $share->permission;
}

// uf_pp_get_plans
add_action('wp_ajax_uf_pp_get_plans', 'uf_pp_get_plans');

function uf_pp_get_plans() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$client_filter = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	$where = $wpdb->prepare("p.userid = %d AND p.state = 1", $currentuserid);
	if ($client_filter > 0) {
		$where .= $wpdb->prepare(" AND p.client_id = %d", $client_filter);
	}

	$plans = $wpdb->get_results("
		SELECT p.*, c.title AS client_title, c.hexcolor AS client_color, c.shortdesc AS client_short, c.url AS client_url, c.asana_project_id AS client_asana_ids
		FROM $plans_table p
		LEFT JOIN $clients_table c ON c.id = p.client_id
		WHERE $where
		ORDER BY p.created DESC
	");

	wp_send_json_success($plans);
	exit();
}

// uf_pp_save_plan
add_action('wp_ajax_uf_pp_save_plan', 'uf_pp_save_plan');

function uf_pp_save_plan() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$plan_id = (int)$_POST['plan_id'];
	$client_id = (int)$_POST['client_id'];
	$title = sanitize_text_field($_POST['title']);
	$period_from = sanitize_text_field($_POST['period_from']);
	$period_to = sanitize_text_field($_POST['period_to']);
	$quarter = isset($_POST['quarter']) ? sanitize_text_field($_POST['quarter']) : '';
	$asana_project_gid = isset($_POST['asana_project_gid']) ? sanitize_text_field($_POST['asana_project_gid']) : '';
	$asana_section_gid = isset($_POST['asana_section_gid']) ? sanitize_text_field($_POST['asana_section_gid']) : '';

	if (empty($client_id) || empty($title)) {
		wp_send_json_error('Kunde und Titel sind Pflichtfelder.');
		exit();
	}

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';

	$data = array(
		'client_id' => $client_id,
		'title' => $title,
		'period_from' => $period_from ?: null,
		'period_to' => $period_to ?: null,
		'quarter' => $quarter,
		'asana_project_gid' => $asana_project_gid,
		'asana_section_gid' => $asana_section_gid,
		'plan_status' => isset($_POST['plan_status']) ? sanitize_text_field($_POST['plan_status']) : 'entwurf',
	);

	if ($plan_id > 0) {
		$existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
		if (!$existing) {
			wp_send_json_error('Plan nicht gefunden.');
			exit();
		}
		$wpdb->update($plans_table, $data, array('id' => $plan_id));
	} else {
		$data['userid'] = $currentuserid;
		$wpdb->insert($plans_table, $data);
		$plan_id = $wpdb->insert_id;
	}

	wp_send_json_success(array('plan_id' => $plan_id));
	exit();
}

// uf_pp_delete_plan
add_action('wp_ajax_uf_pp_delete_plan', 'uf_pp_delete_plan');

function uf_pp_delete_plan() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';

	$wpdb->update($plans_table, array('state' => 0), array('id' => $plan_id, 'userid' => $currentuserid));

	wp_send_json_success();
	exit();
}

// uf_pp_get_rows
add_action('wp_ajax_uf_pp_get_rows', 'uf_pp_get_rows');

function uf_pp_get_rows() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	$access = pp_check_access($plan_id, $currentuserid);
	if (!$access) {
		wp_send_json_error('Kein Zugriff.');
		exit();
	}

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC",
		$plan_id
	));

	// Load feedback for this plan
	$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
	$feedback = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $feedback_table WHERE plan_id = %d ORDER BY created ASC",
		$plan_id
	));
	$fb_by_row = array();
	foreach ($feedback as $fb) {
		$rid = (int)$fb->row_id;
		if (!isset($fb_by_row[$rid])) $fb_by_row[$rid] = array();
		$fb_by_row[$rid][] = $fb;
	}

	wp_send_json_success(array('rows' => $rows, 'feedback' => $fb_by_row));
	exit();
}

// uf_pp_save_row
add_action('wp_ajax_uf_pp_save_row', 'uf_pp_save_row');

function uf_pp_save_row() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	$row_id = (int)$_POST['row_id'];
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	// Verify plan access (owner or write permission)
	$access = pp_check_access($plan_id, $currentuserid, true);
	if (!$access) {
		wp_send_json_error('Kein Schreibzugriff.');
		exit();
	}

	$data = array(
		'plan_id' => $plan_id,
		'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'item',
		'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
		'date_from' => isset($_POST['date_from']) && !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null,
		'date_to' => isset($_POST['date_to']) && !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null,
		'timeframe' => isset($_POST['timeframe']) ? sanitize_text_field(wp_unslash($_POST['timeframe'])) : '',
		'ist_hours' => isset($_POST['ist_hours']) ? floatval($_POST['ist_hours']) : 0,
		'planned_hours' => isset($_POST['planned_hours']) ? floatval($_POST['planned_hours']) : 0,
		'responsible' => isset($_POST['responsible']) ? sanitize_text_field(wp_unslash($_POST['responsible'])) : '',
		'lead_responsible' => isset($_POST['lead_responsible']) ? sanitize_text_field(wp_unslash($_POST['lead_responsible'])) : '',
		'deadline' => isset($_POST['deadline']) ? sanitize_text_field(wp_unslash($_POST['deadline'])) : '',
		'is_done' => isset($_POST['is_done']) ? (int)$_POST['is_done'] : 0,
		'is_placeholder' => isset($_POST['is_placeholder']) ? (int)$_POST['is_placeholder'] : 0,
		'is_focus' => isset($_POST['is_focus']) ? (int)$_POST['is_focus'] : 0,
		'no_ticket' => isset($_POST['no_ticket']) ? (int)$_POST['no_ticket'] : 0,
		'actual_hours' => isset($_POST['actual_hours']) ? sanitize_text_field(wp_unslash($_POST['actual_hours'])) : '',
		'notes' => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
		'asana_gid' => isset($_POST['asana_gid']) ? sanitize_text_field($_POST['asana_gid']) : '',
		'asana_url' => isset($_POST['asana_url']) ? sanitize_text_field($_POST['asana_url']) : '',
		'asana_task_name' => isset($_POST['asana_task_name']) ? sanitize_text_field(wp_unslash($_POST['asana_task_name'])) : '',
	);

	if ($row_id > 0) {
		$existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rows_table WHERE id = %d AND plan_id = %d", $row_id, $plan_id));
		if (!$existing) {
			wp_send_json_error('Zeile nicht gefunden.');
			exit();
		}
		$wpdb->update($rows_table, $data, array('id' => $row_id));
	} else {
		$max_pos = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(position) FROM $rows_table WHERE plan_id = %d", $plan_id));
		$data['position'] = $max_pos + 1;
		$wpdb->insert($rows_table, $data);
		$row_id = $wpdb->insert_id;
	}

	wp_send_json_success(array('row_id' => $row_id));
	exit();
}

// uf_pp_delete_row
add_action('wp_ajax_uf_pp_delete_row', 'uf_pp_delete_row');

function uf_pp_delete_row() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$row_id = (int)$_POST['row_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	$row = $wpdb->get_row($wpdb->prepare("SELECT r.*, p.userid FROM $rows_table r JOIN $plans_table p ON p.id = r.plan_id WHERE r.id = %d", $row_id));
	if (!$row || $row->userid != $currentuserid) {
		wp_send_json_error('Nicht berechtigt.');
		exit();
	}

	$wpdb->delete($rows_table, array('id' => $row_id));

	wp_send_json_success();
	exit();
}

// uf_pp_reorder_rows
add_action('wp_ajax_uf_pp_reorder_rows', 'uf_pp_reorder_rows');

function uf_pp_reorder_rows() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];
	$positions = isset($_POST['positions']) ? $_POST['positions'] : array();

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
	if (!$plan) {
		wp_send_json_error('Plan nicht gefunden.');
		exit();
	}

	foreach ($positions as $pos) {
		$wpdb->update($rows_table, array('position' => (int)$pos['position']), array('id' => (int)$pos['id'], 'plan_id' => $plan_id));
	}

	wp_send_json_success();
	exit();
}

// uf_pp_generate_share_link - generate/toggle share link for a plan
add_action('wp_ajax_uf_pp_generate_share_link', 'uf_pp_generate_share_link');

function uf_pp_generate_share_link() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];
	$password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
	if (!$plan) { wp_send_json_error('Nicht gefunden.'); exit(); }

	$hash = md5($plan_id . time() . wp_generate_password(12, false));
	$hashed_pw = !empty($password) ? wp_hash_password($password) : '';

	$wpdb->update($plans_table, array(
		'share_hash' => $hash,
		'share_password' => $hashed_pw,
	), array('id' => $plan_id));

	wp_send_json_success(array(
		'share_hash' => $hash,
		'share_url' => site_url('/projektplan/?id=' . $hash),
	));
	exit();
}

// uf_pp_remove_share_link
add_action('wp_ajax_uf_pp_remove_share_link', 'uf_pp_remove_share_link');

function uf_pp_remove_share_link() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$wpdb->update($plans_table, array('share_hash' => '', 'share_password' => ''), array('id' => $plan_id, 'userid' => $currentuserid));
	wp_send_json_success();
	exit();
}

// uf_pp_get_shares - get user shares for a plan
add_action('wp_ajax_uf_pp_get_shares', 'uf_pp_get_shares');

function uf_pp_get_shares() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$shares_table = $wpdb->prefix . 'tallyr_projektplanner_shares';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
	if (!$plan) { wp_send_json_error('Nicht gefunden.'); exit(); }

	$shares = $wpdb->get_results($wpdb->prepare(
		"SELECT s.*, u.display_name FROM $shares_table s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE s.plan_id = %d",
		$plan_id
	));

	wp_send_json_success(array(
		'shares' => $shares,
		'share_hash' => $plan->share_hash,
		'share_url' => $plan->share_hash ? site_url('/projektplan/?id=' . $plan->share_hash) : '',
		'has_password' => !empty($plan->share_password),
	));
	exit();
}

// uf_pp_add_share - share plan with a user
add_action('wp_ajax_uf_pp_add_share', 'uf_pp_add_share');

function uf_pp_add_share() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];
	$share_user_id = (int)$_POST['share_user_id'];
	$permission = sanitize_text_field($_POST['permission']);

	if (!in_array($permission, array('read', 'write'))) $permission = 'read';

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$shares_table = $wpdb->prefix . 'tallyr_projektplanner_shares';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
	if (!$plan) { wp_send_json_error('Nicht gefunden.'); exit(); }

	// Upsert
	$existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $shares_table WHERE plan_id = %d AND user_id = %d", $plan_id, $share_user_id));
	if ($existing) {
		$wpdb->update($shares_table, array('permission' => $permission), array('id' => $existing->id));
	} else {
		$wpdb->insert($shares_table, array('plan_id' => $plan_id, 'user_id' => $share_user_id, 'permission' => $permission));
	}

	wp_send_json_success();
	exit();
}

// uf_pp_remove_share
add_action('wp_ajax_uf_pp_remove_share', 'uf_pp_remove_share');

function uf_pp_remove_share() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];
	$share_user_id = (int)$_POST['share_user_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$shares_table = $wpdb->prefix . 'tallyr_projektplanner_shares';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d", $plan_id, $currentuserid));
	if (!$plan) { wp_send_json_error('Nicht gefunden.'); exit(); }

	$wpdb->delete($shares_table, array('plan_id' => $plan_id, 'user_id' => $share_user_id));
	wp_send_json_success();
	exit();
}

// uf_pp_get_shared_plans - get plans shared with current user
add_action('wp_ajax_uf_pp_get_shared_plans', 'uf_pp_get_shared_plans');

function uf_pp_get_shared_plans() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$shares_table = $wpdb->prefix . 'tallyr_projektplanner_shares';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	$plans = $wpdb->get_results($wpdb->prepare(
		"SELECT p.*, s.permission, c.title AS client_title, c.hexcolor AS client_color, c.shortdesc AS client_short, c.url AS client_url,
		 u.display_name AS owner_name
		 FROM $shares_table s
		 JOIN $plans_table p ON p.id = s.plan_id AND p.state = 1
		 LEFT JOIN $clients_table c ON c.id = p.client_id
		 LEFT JOIN {$wpdb->users} u ON u.ID = p.userid
		 WHERE s.user_id = %d",
		$currentuserid
	));

	wp_send_json_success($plans);
	exit();
}

// uf_pp_get_public_plan - public access via share hash
add_action('wp_ajax_nopriv_uf_pp_get_public_plan', 'uf_pp_get_public_plan');
add_action('wp_ajax_uf_pp_get_public_plan', 'uf_pp_get_public_plan');

function uf_pp_get_public_plan() {
	$hash = sanitize_text_field($_POST['share_hash']);
	$password = isset($_POST['password']) ? $_POST['password'] : '';

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	$plan = $wpdb->get_row($wpdb->prepare(
		"SELECT p.*, c.title AS client_title, c.shortdesc AS client_short
		 FROM $plans_table p LEFT JOIN $clients_table c ON c.id = p.client_id
		 WHERE p.share_hash = %s AND p.state = 1 AND p.share_hash != ''",
		$hash
	));

	if (!$plan) { wp_send_json_error('Plan nicht gefunden.'); exit(); }

	// Check password
	if (!empty($plan->share_password)) {
		if (empty($password) || !wp_check_password($password, $plan->share_password)) {
			wp_send_json_error('password_required');
			exit();
		}
	}

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC",
		$plan->id
	));

	wp_send_json_success(array(
		'plan' => $plan,
		'rows' => $rows,
	));
	exit();
}

// uf_pp_save_revision - create a snapshot of the current plan
add_action('wp_ajax_uf_pp_save_revision', 'uf_pp_save_revision');

function uf_pp_save_revision() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];
	$label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';

	$access = pp_check_access($plan_id, $currentuserid, true);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }

	global $wpdb;
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$rev_table = $wpdb->prefix . 'tallyr_projektplanner_revisions';

	$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC", $plan_id));
	$snapshot = json_encode($rows);

	// Limit to 50 revisions per plan
	$count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $rev_table WHERE plan_id = %d", $plan_id));
	if ($count >= 50) {
		$oldest = $wpdb->get_var($wpdb->prepare("SELECT id FROM $rev_table WHERE plan_id = %d ORDER BY created ASC LIMIT 1", $plan_id));
		if ($oldest) $wpdb->delete($rev_table, array('id' => $oldest));
	}

	$wpdb->insert($rev_table, array(
		'plan_id' => $plan_id,
		'user_id' => $currentuserid,
		'snapshot' => $snapshot,
		'label' => $label,
	));

	wp_send_json_success(array('revision_id' => $wpdb->insert_id));
	exit();
}

// uf_pp_get_revisions - list revisions for a plan
add_action('wp_ajax_uf_pp_get_revisions', 'uf_pp_get_revisions');

function uf_pp_get_revisions() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	$access = pp_check_access($plan_id, $currentuserid);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }

	global $wpdb;
	$rev_table = $wpdb->prefix . 'tallyr_projektplanner_revisions';

	$revisions = $wpdb->get_results($wpdb->prepare(
		"SELECT r.id, r.label, r.created, u.display_name
		 FROM $rev_table r LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
		 WHERE r.plan_id = %d ORDER BY r.created DESC LIMIT 50",
		$plan_id
	));

	wp_send_json_success($revisions);
	exit();
}

// uf_pp_restore_revision - restore a revision
add_action('wp_ajax_uf_pp_restore_revision', 'uf_pp_restore_revision');

function uf_pp_restore_revision() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$revision_id = (int)$_POST['revision_id'];

	global $wpdb;
	$rev_table = $wpdb->prefix . 'tallyr_projektplanner_revisions';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	$rev = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rev_table WHERE id = %d", $revision_id));
	if (!$rev) { wp_send_json_error('Revision nicht gefunden.'); exit(); }

	$access = pp_check_access($rev->plan_id, $currentuserid, true);
	if (!$access) { wp_send_json_error('Kein Schreibzugriff.'); exit(); }

	// Save current state as revision before restoring
	$current_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC", $rev->plan_id));
	$wpdb->insert($rev_table, array(
		'plan_id' => $rev->plan_id,
		'user_id' => $currentuserid,
		'snapshot' => json_encode($current_rows),
		'label' => 'Vor Wiederherstellung',
	));

	// Delete current rows
	$wpdb->delete($rows_table, array('plan_id' => $rev->plan_id));

	// Restore from snapshot
	$snapshot_rows = json_decode($rev->snapshot, true);
	if (is_array($snapshot_rows)) {
		foreach ($snapshot_rows as $row) {
			unset($row['id']); // Remove old ID
			$row['plan_id'] = $rev->plan_id;
			$wpdb->insert($rows_table, $row);
		}
	}

	wp_send_json_success();
	exit();
}

// uf_pp_undo_delete - restore a deleted row
add_action('wp_ajax_uf_pp_undo_delete', 'uf_pp_undo_delete');

function uf_pp_undo_delete() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$row_data = isset($_POST['row_data']) ? $_POST['row_data'] : array();
	$plan_id = (int)$row_data['plan_id'];

	$access = pp_check_access($plan_id, $currentuserid, true);
	if (!$access) { wp_send_json_error('Kein Zugriff.'); exit(); }

	global $wpdb;
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	unset($row_data['id']);
	$wpdb->insert($rows_table, $row_data);

	wp_send_json_success(array('row_id' => $wpdb->insert_id));
	exit();
}

// uf_pp_get_dashboard_stats
add_action('wp_ajax_uf_pp_get_dashboard_stats', 'uf_pp_get_dashboard_stats');

function uf_pp_get_dashboard_stats() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
	$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
	$status_filter = isset($_POST['plan_status']) ? sanitize_text_field($_POST['plan_status']) : '';
	$client_filter = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	// Build plan query with filters
	$where_sql = "p.userid = " . intval($currentuserid) . " AND p.state = 1";
	if ($status_filter) {
		$where_sql .= $wpdb->prepare(" AND p.plan_status = %s", $status_filter);
	}
	if ($client_filter) {
		$where_sql .= $wpdb->prepare(" AND p.client_id = %d", $client_filter);
	}

	$plans = $wpdb->get_results(
		"SELECT p.id, p.title, p.period_from, p.period_to, p.plan_status,
		 c.title AS client_title, c.shortdesc AS client_short, c.hexcolor AS client_color
		 FROM $plans_table p
		 LEFT JOIN $clients_table c ON c.id = p.client_id
		 WHERE $where_sql
		 ORDER BY c.shortdesc ASC, p.title ASC"
	);

	$plan_ids = array_map(function($p) { return $p->id; }, $plans);
	if (empty($plan_ids)) {
		wp_send_json_success(array(
			'totals' => array('soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0),
			'by_person' => array(),
			'by_plan' => array(),
			'forecast' => array(),
			'done_tasks' => array(),
			'capacity' => array(),
		));
		exit();
	}

	$ids_str = implode(',', array_map('intval', $plan_ids));
	$rows = $wpdb->get_results("
		SELECT r.*, p.title AS plan_title, p.period_from AS plan_from, p.period_to AS plan_to,
		       c.shortdesc AS client_short, c.hexcolor AS client_color, c.title AS client_title
		FROM $rows_table r
		JOIN $plans_table p ON p.id = r.plan_id
		LEFT JOIN $clients_table c ON c.id = p.client_id
		WHERE r.plan_id IN ($ids_str) AND r.type = 'item' AND r.is_placeholder = 0
	");

	// Aggregate totals, by_person, by_plan, forecast, done_tasks, all_person_tasks
	$totals = array('soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0);
	$by_person = array();
	$by_plan = array();
	$forecast = array();
	$done_tasks = array();
	$all_person_tasks = array(); // person_name => [ task, task, ... ]

	foreach ($rows as $r) {
		$soll = floatval($r->planned_hours);
		$ist = floatval($r->ist_hours);
		$done = intval($r->is_done);
		$no_ticket = intval($r->no_ticket ?? 0);

		// Apply date filter: if date range set, only count items whose plan period overlaps
		if ($date_from && $r->plan_to && $r->plan_to < $date_from) continue;
		if ($date_to && $r->plan_from && $r->plan_from > $date_to) continue;

		// Skip no_ticket rows from totals
		if (!$no_ticket) {
			$totals['soll'] += $soll;
			$totals['ist'] += $ist;
		}
		$totals['total']++;
		if ($done) $totals['done']++;
		else $totals['open']++;

		// By person: use lead_responsible if set, otherwise fall back to responsible
		$lead = isset($r->lead_responsible) ? trim($r->lead_responsible) : '';
		if (!$no_ticket) {
			if ($lead) {
				// Hours count only for lead_responsible
				if (!isset($by_person[$lead])) $by_person[$lead] = array('soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0);
				$by_person[$lead]['soll'] += $soll;
				$by_person[$lead]['ist'] += $ist;
				if ($done) $by_person[$lead]['done']++;
				else $by_person[$lead]['open']++;
			} else {
				// Fallback: split across all responsible
				$names = array();
				if ($r->responsible) {
					$names = array_filter(array_map('trim', explode(',', $r->responsible)));
				}
				$share = count($names) > 0 ? 1.0 / count($names) : 0;
				foreach ($names as $name) {
					if (!isset($by_person[$name])) $by_person[$name] = array('soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0);
					$by_person[$name]['soll'] += $soll * $share;
					$by_person[$name]['ist'] += $ist * $share;
					if ($done) $by_person[$name]['done']++;
					else $by_person[$name]['open']++;
				}
			}
		}

		// By plan
		$pk = $r->plan_id;
		if (!isset($by_plan[$pk])) $by_plan[$pk] = array(
			'title' => ($r->client_short ?: '') . ' · ' . $r->plan_title,
			'color' => $r->client_color ?: '#999',
			'soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0
		);
		$by_plan[$pk]['soll'] += $soll;
		$by_plan[$pk]['ist'] += $ist;
		$by_plan[$pk]['total']++;
		if ($done) $by_plan[$pk]['done']++;
		else $by_plan[$pk]['open']++;

		// Forecast: distribute hours across plan months
		if (!$no_ticket && $soll > 0 && $r->plan_from && $r->plan_to && $r->plan_from !== '0000-00-00' && $r->plan_to !== '0000-00-00') {
			$pf = new DateTime($r->plan_from);
			$pt = new DateTime($r->plan_to);
			$interval = $pf->diff($pt);
			$months_count = max(1, $interval->m + $interval->y * 12 + ($interval->d > 0 ? 1 : 0));
			$soll_per_month = $soll / $months_count;
			$ist_per_month = $ist / $months_count;

			// Use lead_responsible for forecast if set
			$fc_names = array();
			if ($lead) {
				$fc_names = array($lead);
				$fc_share = 1.0;
			} else {
				$fc_names = $r->responsible ? array_filter(array_map('trim', explode(',', $r->responsible))) : array();
				$fc_share = count($fc_names) > 0 ? 1.0 / count($fc_names) : 0;
			}

			$cur = clone $pf;
			$cur->modify('first day of this month');
			$end = clone $pt;
			$end->modify('first day of this month');
			while ($cur <= $end) {
				$mk = $cur->format('Y-m');
				if (!isset($forecast[$mk])) $forecast[$mk] = array();
				foreach ($fc_names as $fname) {
					if (!isset($forecast[$mk][$fname])) $forecast[$mk][$fname] = array('soll' => 0, 'ist' => 0);
					$forecast[$mk][$fname]['soll'] += $soll_per_month * $fc_share;
					$forecast[$mk][$fname]['ist'] += $ist_per_month * $fc_share;
				}
				if (empty($fc_names)) {
					if (!isset($forecast[$mk]['_unassigned'])) $forecast[$mk]['_unassigned'] = array('soll' => 0, 'ist' => 0);
					$forecast[$mk]['_unassigned']['soll'] += $soll_per_month;
					$forecast[$mk]['_unassigned']['ist'] += $ist_per_month;
				}
				$cur->modify('+1 month');
			}
		}

		// Done tasks list (only completed items)
		if ($done) {
			$done_tasks[] = array(
				'description' => $r->description,
				'responsible' => $r->responsible ?: '',
				'soll' => $soll,
				'ist' => $ist,
				'plan' => ($r->client_short ?: '') . ' · ' . $r->plan_title,
				'color' => $r->client_color ?: '#999',
				'deadline' => $r->deadline ?: '',
			);
		}

		// Track all tasks per person (for detail view)
		$task_info = array(
			'description' => $r->description ?: '',
			'plan' => ($r->client_short ?: '') . ' · ' . $r->plan_title,
			'plan_id' => $r->plan_id,
			'row_id' => $r->id,
			'color' => $r->client_color ?: '#999',
			'soll' => $soll,
			'ist' => $ist,
			'done' => $done,
			'deadline' => $r->deadline ?: '',
			'timeframe' => $r->timeframe ?: '',
			'responsible' => $r->responsible ?: '',
			'lead_responsible' => $lead,
		);
		if ($lead) {
			if (!isset($all_person_tasks[$lead])) $all_person_tasks[$lead] = array();
			$all_person_tasks[$lead][] = array_merge($task_info, array('role' => 'lead'));
		}
		if ($r->responsible) {
			$resp_names = array_filter(array_map('trim', explode(',', $r->responsible)));
			foreach ($resp_names as $rn) {
				if ($rn === $lead) continue; // Don't double-list if also lead
				if (!isset($all_person_tasks[$rn])) $all_person_tasks[$rn] = array();
				$all_person_tasks[$rn][] = array_merge($task_info, array('role' => 'resp'));
			}
		}
	}

	// Sort by_person by soll desc
	uasort($by_person, function($a, $b) { return $b['soll'] <=> $a['soll']; });

	// Sort forecast by month
	ksort($forecast);

	// Load capacity data from team tags
	$custom_tags = get_user_meta($currentuserid, 'tallyr_pp_tags', true);
	$custom_tags = $custom_tags ? json_decode($custom_tags, true) : array();
	$capacity = array();
	if (is_array($custom_tags)) {
		foreach ($custom_tags as $tag) {
			if (!empty($tag['capacity'])) {
				$capacity[$tag['name']] = (int)$tag['capacity'];
				if (!empty($tag['kuerzel'])) {
					$capacity[$tag['kuerzel']] = (int)$tag['capacity'];
				}
			}
		}
	}
	// Also load WP users capacity (stored per-user if available)
	$wp_users = get_users(array('fields' => array('ID', 'display_name')));
	foreach ($wp_users as $u) {
		$user_cap = get_user_meta($u->ID, 'tallyr_capacity', true);
		if ($user_cap) {
			$capacity[$u->display_name] = (int)$user_cap;
		}
	}

	wp_send_json_success(array(
		'totals' => $totals,
		'by_person' => $by_person,
		'by_plan' => $by_plan,
		'forecast' => $forecast,
		'done_tasks' => $done_tasks,
		'person_tasks' => $all_person_tasks,
		'capacity' => $capacity,
	));
	exit();
}

// uf_pp_move_row - move a row to another plan
add_action('wp_ajax_uf_pp_move_row', 'uf_pp_move_row');

function uf_pp_move_row() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$row_id = (int)$_POST['row_id'];
	$target_plan_id = (int)$_POST['target_plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	// Verify row exists and user has access to source plan
	$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rows_table WHERE id = %d", $row_id));
	if (!$row) { wp_send_json_error('Zeile nicht gefunden.'); exit(); }

	$source_access = pp_check_access($row->plan_id, $currentuserid, true);
	if (!$source_access) { wp_send_json_error('Kein Zugriff auf Quellplan.'); exit(); }

	// Verify user has write access to target plan
	$target_access = pp_check_access($target_plan_id, $currentuserid, true);
	if (!$target_access) { wp_send_json_error('Kein Schreibzugriff auf Zielplan.'); exit(); }

	// Get max position in target plan
	$max_pos = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(position) FROM $rows_table WHERE plan_id = %d", $target_plan_id));

	// Move the row
	$wpdb->update($rows_table, array(
		'plan_id' => $target_plan_id,
		'position' => $max_pos + 1,
	), array('id' => $row_id));

	wp_send_json_success();
	exit();
}

// uf_pp_duplicate_plan
add_action('wp_ajax_uf_pp_duplicate_plan', 'uf_pp_duplicate_plan');

function uf_pp_duplicate_plan() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$plan_id = (int)$_POST['plan_id'];

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';

	$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND userid = %d AND state = 1", $plan_id, $currentuserid));
	if (!$plan) {
		wp_send_json_error('Plan nicht gefunden.');
		exit();
	}

	$new_title = isset($_POST['new_title']) ? sanitize_text_field($_POST['new_title']) : $plan->title . ' (Kopie)';
	$new_from = isset($_POST['new_period_from']) && !empty($_POST['new_period_from']) ? sanitize_text_field($_POST['new_period_from']) : $plan->period_from;
	$new_to = isset($_POST['new_period_to']) && !empty($_POST['new_period_to']) ? sanitize_text_field($_POST['new_period_to']) : $plan->period_to;
	$new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : 'entwurf';

	$wpdb->insert($plans_table, array(
		'userid' => $currentuserid,
		'client_id' => $plan->client_id,
		'title' => $new_title,
		'period_from' => $new_from ?: null,
		'period_to' => $new_to ?: null,
		'quarter' => '',
		'asana_project_gid' => $plan->asana_project_gid ?: '',
		'asana_section_gid' => $plan->asana_section_gid ?: '',
		'plan_status' => $new_status,
	));
	$new_plan_id = $wpdb->insert_id;

	$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC", $plan_id));
	foreach ($rows as $row) {
		$wpdb->insert($rows_table, array(
			'plan_id' => $new_plan_id,
			'type' => $row->type,
			'description' => $row->description,
			'date_from' => null,
			'date_to' => null,
			'timeframe' => '',
			'ist_hours' => 0,
			'planned_hours' => $row->planned_hours,
			'responsible' => $row->responsible,
			'lead_responsible' => $row->lead_responsible ?: '',
			'deadline' => '',
			'is_done' => 0,
			'is_placeholder' => $row->is_placeholder ?: 0,
			'is_focus' => 0,
			'no_ticket' => $row->no_ticket ?: 0,
			'actual_hours' => '',
			'notes' => $row->notes,
			'asana_gid' => $row->asana_gid ?: '',
			'asana_url' => $row->asana_url ?: '',
			'asana_task_name' => $row->asana_task_name ?: '',
			'position' => $row->position,
		));
	}

	wp_send_json_success(array('plan_id' => $new_plan_id));
	exit();
}

// ========================================
// BUDGET / SOLL-IST ABGLEICH
// ========================================

// uf_pp_save_client_budget - save client-level budget
add_action('wp_ajax_uf_pp_save_client_budget', 'uf_pp_save_client_budget');
function uf_pp_save_client_budget() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$client_id = (int)$_POST['client_id'];
	$year = (int)$_POST['year'];
	$month = (int)$_POST['month'];
	$soll_ts = floatval($_POST['soll_ts']);

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_client_budget';
	$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE client_id = %d AND year = %d AND month = %d", $client_id, $year, $month));
	if ($soll_ts > 0) {
		if ($existing) $wpdb->update($table, array('soll_ts' => $soll_ts), array('id' => $existing->id));
		else $wpdb->insert($table, array('client_id' => $client_id, 'year' => $year, 'month' => $month, 'soll_ts' => $soll_ts));
	} else {
		if ($existing) $wpdb->delete($table, array('id' => $existing->id));
	}
	wp_send_json_success();
	exit();
}

// uf_pp_save_client_budget_batch - batch save for multiple months
add_action('wp_ajax_uf_pp_save_client_budget_batch', 'uf_pp_save_client_budget_batch');
function uf_pp_save_client_budget_batch() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$client_id = (int)$_POST['client_id'];
	$year = (int)$_POST['year'];
	$soll_ts = floatval($_POST['soll_ts']);
	$months_raw = isset($_POST['months']) ? $_POST['months'] : array();

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_client_budget';
	foreach ($months_raw as $m) {
		$m = (int)$m;
		if ($m < 1 || $m > 12) continue;
		$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE client_id = %d AND year = %d AND month = %d", $client_id, $year, $m));
		if ($soll_ts > 0) {
			if ($existing) $wpdb->update($table, array('soll_ts' => $soll_ts), array('id' => $existing->id));
			else $wpdb->insert($table, array('client_id' => $client_id, 'year' => $year, 'month' => $m, 'soll_ts' => $soll_ts));
		} else {
			if ($existing) $wpdb->delete($table, array('id' => $existing->id));
		}
	}
	wp_send_json_success();
	exit();
}

// uf_pp_save_uebertrag - save carry-over for a client
add_action('wp_ajax_uf_pp_save_uebertrag', 'uf_pp_save_uebertrag');
function uf_pp_save_uebertrag() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$client_id = (int)$_POST['client_id'];
	$uebertrag_ts = floatval($_POST['uebertrag_ts']);
	$notiz = isset($_POST['notiz']) ? sanitize_text_field(wp_unslash($_POST['notiz'])) : '';
	$abrechnungsmodus = isset($_POST['abrechnungsmodus']) ? sanitize_text_field($_POST['abrechnungsmodus']) : '';

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_clients';
	$data = array('uebertrag_ts' => $uebertrag_ts, 'uebertrag_notiz' => $notiz);
	if ($abrechnungsmodus) $data['abrechnungsmodus'] = $abrechnungsmodus;
	$wpdb->update($table, $data, array('id' => $client_id));
	wp_send_json_success();
	exit();
}

// uf_pp_save_ist_override - save manual Ist override for a client/month
add_action('wp_ajax_uf_pp_save_ist_override', 'uf_pp_save_ist_override');
function uf_pp_save_ist_override() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$client_id = (int)$_POST['client_id'];
	$year = (int)$_POST['year'];
	$month = (int)$_POST['month'];
	$ist_h = isset($_POST['ist_h']) ? $_POST['ist_h'] : null;
	$ist_note = isset($_POST['ist_note']) ? sanitize_text_field(wp_unslash($_POST['ist_note'])) : '';
	$clear = isset($_POST['clear']) && $_POST['clear'] === '1';

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_client_budget';
	$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE client_id = %d AND year = %d AND month = %d", $client_id, $year, $month));

	if ($clear) {
		if ($existing) $wpdb->update($table, array('ist_override' => null, 'ist_note' => ''), array('id' => $existing->id));
	} else {
		$ist_val = $ist_h !== null && $ist_h !== '' ? floatval($ist_h) : null;
		if ($existing) {
			$wpdb->update($table, array('ist_override' => $ist_val, 'ist_note' => $ist_note), array('id' => $existing->id));
		} else {
			$wpdb->insert($table, array('client_id' => $client_id, 'year' => $year, 'month' => $month, 'soll_ts' => 0, 'ist_override' => $ist_val, 'ist_note' => $ist_note));
		}
	}
	wp_send_json_success();
	exit();
}

// uf_pp_get_budget - get client-based budget data
add_action('wp_ajax_uf_pp_get_budget', 'uf_pp_get_budget');
function uf_pp_get_budget() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
	$plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
	$year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
	$hours_per_ts = 8;

	global $wpdb;
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$client_budget_table = $wpdb->prefix . 'tallyr_client_budget';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	// Determine client_id: from param, from plan, or all
	if ($client_id <= 0 && $plan_id > 0) {
		$plan = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM $plans_table WHERE id = %d", $plan_id));
		if ($plan) $client_id = (int)$plan->client_id;
	}

	// Get ALL plans for this client (including archived state=2) to calculate Ist
	if ($client_id > 0) {
		$client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id = %d", $client_id));
		$all_plans = $wpdb->get_results($wpdb->prepare(
			"SELECT id, period_from, period_to FROM $plans_table WHERE client_id = %d AND userid = %d AND state IN (1,2)",
			$client_id, $currentuserid
		));
	} else {
		// Dashboard mode: all clients
		$client = null;
		$all_plans = $wpdb->get_results($wpdb->prepare(
			"SELECT p.id, p.client_id, p.period_from, p.period_to FROM $plans_table p WHERE p.userid = %d AND p.state IN (1,2)",
			$currentuserid
		));
	}

	$all_plan_ids = array_map(function($p) { return $p->id; }, $all_plans);
	if (empty($all_plan_ids)) {
		wp_send_json_success(array('months' => array(), 'client' => null, 'total_all' => array(), 'hours_per_ts' => $hours_per_ts, 'years' => array((int)date('Y'))));
		exit();
	}
	$ids_str = implode(',', array_map('intval', $all_plan_ids));

	// Determine target client IDs
	if ($client_id > 0) {
		$target_client_ids = array($client_id);
	} else {
		$target_client_ids = array_unique(array_filter(array_map(function($p) { return isset($p->client_id) ? $p->client_id : 0; }, $all_plans)));
	}
	$cids_str = implode(',', array_map('intval', $target_client_ids));

	// Load client budget entries for the year (Soll + Ist override)
	$budget_rows = $cids_str ? $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $client_budget_table WHERE client_id IN ($cids_str) AND year = %d", $year
	)) : array();
	$budget_map = array(); // client_id => month => {soll_ts, ist_override, ist_note}
	foreach ($budget_rows as $b) {
		$budget_map[$b->client_id][$b->month] = array(
			'soll_ts' => floatval($b->soll_ts),
			'ist_override' => $b->ist_override !== null ? floatval($b->ist_override) : null,
			'ist_note' => $b->ist_note ?: '',
		);
	}

	// Calculate Ist from all plans, distributed by plan period into months
	$ist_by_month = array(); // month => total ist_h
	foreach ($all_plans as $p) {
		// Filter by client if single-client mode
		if ($client_id > 0 && isset($p->client_id) && $p->client_id != $client_id) continue;

		$total_ist = (float)$wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(ist_hours),0) FROM $rows_table WHERE plan_id = %d AND type = 'item' AND is_placeholder = 0", $p->id
		));
		if ($total_ist <= 0) continue;

		$pf = $p->period_from && $p->period_from !== '0000-00-00' ? $p->period_from : $year . '-01-01';
		$pt = $p->period_to && $p->period_to !== '0000-00-00' ? $p->period_to : $year . '-12-31';
		$start = new DateTime($pf); $end = new DateTime($pt);
		$start->modify('first day of this month'); $end->modify('first day of this month');

		$plan_months = array();
		$cur = clone $start;
		while ($cur <= $end) { $plan_months[] = $cur->format('Y-m'); $cur->modify('+1 month'); }
		$mc = max(1, count($plan_months));
		$ist_pm = $total_ist / $mc;

		foreach ($plan_months as $ym) {
			if (substr($ym, 0, 4) == $year) {
				$m = (int)substr($ym, 5, 2);
				$ist_by_month[$m] = ($ist_by_month[$m] ?? 0) + $ist_pm;
			}
		}
	}

	// Build monthly data
	$months = array();
	for ($m = 1; $m <= 12; $m++) {
		$soll_ts = 0;
		foreach ($target_client_ids as $cid) {
			$soll_ts += isset($budget_map[$cid][$m]) ? $budget_map[$cid][$m]['soll_ts'] : 0;
		}
		$soll_h = $soll_ts * $hours_per_ts;
		$calc_ist = round($ist_by_month[$m] ?? 0, 2);

		// Check for manual Ist override (single client mode)
		$ist_h = $calc_ist;
		$ist_manual = false;
		$ist_note = '';
		if ($client_id > 0 && isset($budget_map[$client_id][$m]) && $budget_map[$client_id][$m]['ist_override'] !== null) {
			$ist_h = $budget_map[$client_id][$m]['ist_override'];
			$ist_manual = true;
			$ist_note = $budget_map[$client_id][$m]['ist_note'];
		}

		$months[$m] = array(
			'soll_ts' => $soll_ts, 'soll_h' => $soll_h,
			'ist_h' => round($ist_h, 2), 'ist_calc' => $calc_ist,
			'ist_manual' => $ist_manual, 'ist_note' => $ist_note,
			'diff_h' => round($ist_h - $soll_h, 2),
		);
	}

	// All-time totals
	$total_soll_ts_all = 0;
	if ($cids_str) {
		$total_soll_ts_all = (float)$wpdb->get_var("SELECT COALESCE(SUM(soll_ts),0) FROM $client_budget_table WHERE client_id IN ($cids_str)");
	}
	$total_ist_all = (float)$wpdb->get_var("SELECT COALESCE(SUM(ist_hours),0) FROM $rows_table WHERE plan_id IN ($ids_str) AND type = 'item' AND is_placeholder = 0");

	// Available years
	$years_raw = $cids_str ? $wpdb->get_col("SELECT DISTINCT year FROM $client_budget_table WHERE client_id IN ($cids_str)") : array();
	$years = array_unique(array_merge(array_map('intval', $years_raw), array((int)date('Y'), (int)date('Y') - 1)));
	sort($years);

	wp_send_json_success(array(
		'months' => $months,
		'client' => $client ? array(
			'id' => $client->id,
			'title' => $client->title ?: $client->shortdesc ?: '',
			'uebertrag_ts' => floatval($client->uebertrag_ts ?? 0),
			'uebertrag_notiz' => $client->uebertrag_notiz ?? '',
			'abrechnungsmodus' => $client->abrechnungsmodus ?? 'ts',
		) : null,
		'total_all' => array(
			'soll_ts' => $total_soll_ts_all,
			'soll_h' => $total_soll_ts_all * $hours_per_ts,
			'ist_h' => round($total_ist_all, 2),
			'diff_h' => round($total_ist_all - $total_soll_ts_all * $hours_per_ts, 2),
		),
		'hours_per_ts' => $hours_per_ts,
		'years' => $years,
		'year' => $year,
		'client_id' => $client_id,
	));
	exit();
}

// ========================================
// PERSON SHARE LINKS
// ========================================

// Generate or get person share link
add_action('wp_ajax_uf_pp_generate_person_share', 'uf_pp_generate_person_share');
function uf_pp_generate_person_share() {
	check_ajax_referer('dhAu21hdaZA721!naj!hdf', 'security');
	$currentuserid = get_current_user_id();
	$person_name = sanitize_text_field(wp_unslash($_POST['person_name']));
	if (!$person_name) { wp_send_json_error('Kein Name.'); exit(); }

	global $wpdb;
	$table = $wpdb->prefix . 'tallyr_person_shares';

	// Check if link already exists
	$existing = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table WHERE userid = %d AND person_name = %s", $currentuserid, $person_name
	));

	if ($existing) {
		wp_send_json_success(array(
			'share_hash' => $existing->share_hash,
			'share_url' => site_url('/projektplan-person/?id=' . $existing->share_hash),
		));
		exit();
	}

	$hash = md5($currentuserid . $person_name . time() . wp_generate_password(12, false));
	$wpdb->insert($table, array(
		'userid' => $currentuserid,
		'person_name' => $person_name,
		'share_hash' => $hash,
	));

	wp_send_json_success(array(
		'share_hash' => $hash,
		'share_url' => site_url('/projektplan-person/?id=' . $hash),
	));
	exit();
}

// Get public person tasks
add_action('wp_ajax_nopriv_uf_pp_get_person_tasks', 'uf_pp_get_person_tasks');
add_action('wp_ajax_uf_pp_get_person_tasks', 'uf_pp_get_person_tasks');
function uf_pp_get_person_tasks() {
	$share_hash = sanitize_text_field($_POST['share_hash'] ?? $_GET['id'] ?? '');
	if (!$share_hash) { wp_send_json_error('Kein Hash.'); exit(); }

	global $wpdb;
	$ps_table = $wpdb->prefix . 'tallyr_person_shares';
	$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
	$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
	$clients_table = $wpdb->prefix . 'tallyr_clients';

	$share = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ps_table WHERE share_hash = %s", $share_hash));
	if (!$share) { wp_send_json_error('Link ungültig.'); exit(); }

	$person = $share->person_name;
	$owner_id = $share->userid;

	// Get all active plans from this owner
	$plans = $wpdb->get_results($wpdb->prepare(
		"SELECT p.id, p.title, p.period_from, p.period_to, p.plan_status,
		 c.title AS client_title, c.shortdesc AS client_short, c.hexcolor AS client_color
		 FROM $plans_table p LEFT JOIN $clients_table c ON c.id = p.client_id
		 WHERE p.userid = %d AND p.state = 1
		 ORDER BY c.shortdesc ASC, p.title ASC", $owner_id
	));

	$tasks = array();
	foreach ($plans as $plan) {
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $rows_table WHERE plan_id = %d AND type = 'item' AND is_placeholder = 0 ORDER BY position ASC",
			$plan->id
		));
		foreach ($rows as $row) {
			// Check if person is lead_responsible or in responsible list
			$isLead = trim($row->lead_responsible ?? '') === $person;
			$isResp = false;
			if ($row->responsible) {
				$names = array_map('trim', explode(',', $row->responsible));
				$isResp = in_array($person, $names);
			}
			if (!$isLead && !$isResp) continue;

			$tasks[] = array(
				'client' => $plan->client_short ?: $plan->client_title ?: '',
				'client_color' => $plan->client_color ?: '#999',
				'plan' => $plan->title,
				'description' => $row->description ?: '',
				'planned_hours' => floatval($row->planned_hours),
				'ist_hours' => floatval($row->ist_hours),
				'is_done' => intval($row->is_done),
				'is_lead' => $isLead,
				'deadline' => $row->deadline ?: '',
				'timeframe' => $row->timeframe ?: '',
				'responsible' => $row->responsible ?: '',
				'lead_responsible' => $row->lead_responsible ?: '',
			);
		}
	}

	wp_send_json_success(array('person' => $person, 'tasks' => $tasks));
	exit();
}