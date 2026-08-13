<?php

function decrypt_data($data, $key) {
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $encrypted_data = substr($data, $iv_length);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
}

function encrypt_data($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted); // Combine IV and encrypted data
}


// get_projects_and_times
add_action('wp_ajax_get_projects_and_times', 'get_projects_and_times');
add_action('wp_ajax_nopriv_get_projects_and_times', 'get_projects_and_times');

function get_projects_and_times() {
	check_ajax_referer( 'get_projects_and_times123', 'security' );
	$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	$password = normform_sanitize_text_or_array_field((STRING)$_POST['password']);
	$hashed = normform_sanitize_text_or_array_field((STRING)$_POST['hashed']);

	$client_id = (INT)$_POST['client_id'];

	// get Cookie timespassword
	if (!$hashed && !$password) {
		$hashed = $_COOKIE['timespassword_'.$client_id];
	}

	/* $_SESSION['times_id'] = $times_id;
		$_SESSION['pass'] = $pass */

	$userid = (INT)get_current_user_id();
	$times_id = (STRING)$_POST['times_id'];
	$start_date = (STRING)$_POST['start_date'];
	$end_date = (STRING)$_POST['end_date'];

	global $wpdb;
	
	$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE randomlink = '$times_id' AND id = '$client_id'");
	
	if (current_user_can('administrator') && $client->userid == $userid) {
		$isaadmin = true;
	};

	if (!$client) {
		wp_send_json_error('Kein Zugriff.');
		exit();
	}

	if (!$isaadmin) {
		// check password
		if ($password) {
			if (!wp_check_password($password, $client->pass)) {
				wp_send_json_error('Falsches Passwort.');
				exit();
			}
		} else if ($hashed) {
			// check if hashed is correct
			$encryption_key = 'wdj!837bdahb!Bhadshbj!8328!bhsda'; // Store this securely in wp-config.php
			$checkhashed = decrypt_data($hashed, $encryption_key);
			
			if (!wp_check_password($checkhashed, $client->pass)) {
				wp_send_json_error('Falsches Passwort.');
				exit();
			}
		} else {
			wp_send_json_error('Kein Zugriff');
			exit();
		}
	}

	if (!$hashed && $password) {
		$encryption_key = 'wdj!837bdahb!Bhadshbj!8328!bhsda'; // Store this securely in wp-config.php
		$hashed = encrypt_data($password, $encryption_key);
	}
	

	$html = createProjectTable($client_id, $start_date, $end_date, $client->userid);

	if ($html) {
		wp_send_json_success(array($html, $hashed));
	} else {
		wp_send_json_error('Keine Daten gefunden.');
	}

	wp_die();
	exit();
}


function createProjectTable($client_id, $start_date, $end_date, $userid = 0) {
	global $wpdb;

	$clients_to_show = array();

	// get clients where parentclient is client_id
	$table_name = $wpdb->prefix . 'tallyr_clients';
	$clients = $wpdb->get_results("SELECT * FROM $table_name WHERE parentclient = $client_id");

	foreach ($clients as $client) {
		$clients_to_show[$client->id] = array($client, NULL);
	}

	$client_single = $wpdb->get_results("SELECT * FROM $table_name WHERE id = $client_id");
	$clients_to_show[$client_id] = array($client_single[0], NULL);

	if ($userid) {
		$kuerzel = get_user_meta($userid, 'tallyr_kuerzel', true);
	}

	// get all projects from client
	$table_name_projects = $wpdb->prefix . 'tallyr_projects';
	$table_name_times = $wpdb->prefix . 'tallyr_times';
	foreach ($clients_to_show as $ckey => $client) {
		$projects = $wpdb->get_results("SELECT * FROM $table_name_projects WHERE clientid = $ckey");
		foreach ($projects as $pkey => $project) {
			$times = $wpdb->get_results("SELECT * FROM $table_name_times WHERE projectid = $project->id AND workdate BETWEEN '$start_date' AND '$end_date' AND state = 1 ORDER BY workdate DESC");
			if ($times) {
				$project->times = $times;
				$clients_to_show[$ckey][1][$pkey] = $project;
			} else {
				// remove project from array
				unset($projects[$pkey]);
			}
		}
	}

	$allhours = 0;
	$allhours_bill = 0;


	$html = '';

	foreach ($clients_to_show as $ceky => $client) {
		if (!$client[1]) {
			//remove
			unset($clients_to_show[$ceky]);
		}
	}

	if ($clients_to_show) {
		$html .= '<select id="erledigtfilter">';
			$html .= '<option value="0">Alle Zeiten</option>';
			$html .= '<option value="2">Nicht erledigte Zeiten</option>';
			$html .= '<option value="1">Nur erledigte Zeiten</option>';
		$html .= '</select>';		
	}

	// select filter
	if (count($clients_to_show) > 1) {
		// sort clients by title
		usort($clients_to_show, function($a, $b) {
			return $a[0]->shortdesc <=> $b[0]->shortdesc;
		});
		$html .= '<select id="clientfilter">';
			$html .= '<option value="0">Alle Kunden</option>';
			foreach ($clients_to_show as $client) {
				if (!$client[1]) {
					continue;
				}
				$html .= '<option value="'.$client[0]->id.'">'. $client[0]->shortdesc . ' – '.$client[0]->title.'</option>';
			}
		$html .= '</select>';
	}

	foreach ($clients_to_show as $client) {
		if (!$client[1]) {
			continue;
		}

		$clienthours = 0;
		$clienthours_bill = 0;

		$html .= '<div data-id="'.$client[0]->id.'" class="client" style="--clientcolor: '.$client[0]->hexcolor.'">';
		$html .= '<h2><strong>'. $client[0]->shortdesc . '</strong> ' . $client[0]->title . '</h2>';

		foreach ($client[1] as $project) {
			$hourstotal = 0;
			$hourstotal_bill = 0;

			$html .= '<div class="project">';
			$html .= '<h3>' . $project->title . '</h3>';
			$html .= '<table>';

			$html .= '<tr>';
			$html .= '<th style="width: 12%">Datum</th>';
			$html .= '<th style="width: 60%">Beschreibung</th>';
			$html .= '<th style="width: 10%">Stunden</th>';
			$html .= '<th style="width: 10%">Stundensatz</th>';
			$html .= '<th style="width: 10%">Total</th>';
			$html .= '<th>Abgerechnet</th>';
			$html .= '<th>Erledigt</th>';
			$html .= '</tr>';

			// reverse array
			$project->times = array_reverse($project->times);

			foreach ($project->times as $time) {
				$notbillable = '';
				if ($time->notbillable) {
					$notbillable = 'notbillable';
				} else {
					$hourstotal_bill += $time->hours;
				}

				$hourstotal += $time->hours;


				if (current_user_can('administrator')) {
					if ($time->billed_on) {
						$billeddate = '<div class="adminbtnss"><button class="unbill" data-timeid="'.$time->id.'"><i class="bx bx-check-circle"></i> Abgerechnet</button><button data-timeid="'.$time->id.'" class="deletetime"><i class="bx bx-trash"></i></button></div>';
					} else {
						$billeddate = '<div class="adminbtnss"><button class="bill" data-timeid="'.$time->id.'"><i class="bx bx-euro"></i></button><button data-timeid="'.$time->id.'" class="deletetime"><i class="bx bx-trash"></i></button></div>';
					}
				} else {
					if ($time->billed_on) {
						$billeddate = '<i class="bx bx-check-circle"></i>';
						$inputcheck = 'checked';
					} else {
						$billeddate = '<i class="bx bx-x-circle"></i>';
					}

					if ($time->done) {
						$time->done = date('d.m.Y', $time->done);
						$done = '<button title="'.$time->done.'" data-timeid="'.$time->id.'" class="done undone"><i class="bx bx-check-circle"></i></button>';
					} else {
						$done = '<button data-timeid="'.$time->id.'" class="done"><i class="bx bx-circle"></i></button>';
					}
				}

				$timehours = $time->hours;
				// if time is "3.00" output "3", if 3.50 output 3,5, if 3.75 output 3,75 (instead . make a comma and remove zeros if not necessary
				if (strpos($timehours, '.') !== false) {
					$timehours = rtrim(rtrim($timehours, '0'), '.');
				}
				// . to comma
				$timehours = str_replace('.', ',', $timehours);



				$html .= '<tr id="times_id_'.$time->id.'" class="'.$notbillable.' timerow">';
				$html .= '<td>' . date('d.m.Y', strtotime($time->workdate)) . '</td>';
				$html .= '<td><div class="copy">' . $time->description . ' ('.date('d.m.', strtotime($time->workdate)).', '.$kuerzel.')</div>';
				if ($time->linknotice) {
					$html .= '<div class="linknoticefront">'.tallyercheckIfLink($time->linknotice).'</div>';
				}

				// get comments from wpt_tallyr_messages where tid = $time->id
				$messages = $wpdb->get_results("SELECT * FROM wpt_tallyr_messages WHERE tid = $time->id ORDER BY date DESC");

				if ($messages) {
					$html .= '<div class="commentsbtns"><button class="commentthistask thera"><i class="bx bxs-message-rounded-add"></i> Kommentare</button></div>';
				} else {
					$html .= '<div class="commentsbtns"><button class="commentthistask"><i class="bx bxs-message-rounded-add"></i> Kommentare</button></div>';
				}
				$html .= '<div class="commentswrapp">';
				$html .= '<div class="comments">';
				if ($messages) {
					// turn around array to show oldest first
					$messagesout = array_reverse($messages);
					foreach ($messagesout as $message) {
						$class = '';
						$icon = '';
						if (!$message->message) {
							continue;
						}
						if ($message->fr) {
							$class .= ' fromclient';
						}
						if ($message->seenby) {
							$class .= ' seenmessage';
							$icon = '<i class="bx bx-check-double seen"></i>';
						}
						$html .= '<div class="comment'.$class.'">'.$icon.'<div class="date">'.date('d.m.Y H:i', strtotime($message->date)).'</div><div class="message">'.tallyercheckIfLink(nl2br($message->message)).'</div><i data-timeid="'.$message->tid.'" data-mid="'.$message->mid.'" class="deletemessage bx bx-trash-alt"></i></div>';
					}
				}
				$html .= '</div>';
				$html .= '<div class="commentbox"><textarea data-timeid="'.$time->id.'" placeholder="Kommentar hinzufügen...">'.$time->comment.'</textarea><button class="savecomment" data-timeid="'.$time->id.'">Speichern</button></div>';
				$html .= '</div>';
				$html .= '</td>';
				$html .= '<td data-time="'.$timehours.'" class="copy timepertask">' . $timehours . ' Std.</td>';
				$html .= '<td>' . $time->stundensatz . ' €</td>';
				$html .= '<td data-value="'.$time->hours * $time->stundensatz.'" class="calcgesamtbetrag">' . $time->hours * $time->stundensatz . ' €</td>';
				$html .= '<td class="tdi">' . $billeddate.'</td>';
				$html .= '<td>' . $done.'</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';

			$html .= '<div class="total totalprojects">';
				$html .= '<span>Gesamt: '.$hourstotal.' Std.</span>';
				//$html .= '<span>Abrechenbar: '.$hourstotal_bill.' Std.</span>';
			$html .= '</div>';

			$clienthours += $hourstotal;
			$clienthours_bill += $hourstotal_bill;


			$html .= '</div>';
		}

		$html .= '<div class="total totalclients">';
			$html .= '<span>Gesamt: '.$clienthours.' Std.</span>';
			//$html .= '<span>Abrechenbar: '.$clienthours_bill.' Std.</span>';
		$html .= '</div>';
		
		$html .= '</div>';

		$allhours += $clienthours;
		$allhours_bill += $clienthours_bill;
	}

	if ($clients_to_show) {
		$html .= '<div id="totaltotal" class="total">';
			$html .= '<span>Gesamt: '.$allhours.' Std.</span>';
			//$html .= '<span>Abrechenbar: '.$allhours_bill.' Std.</span>';
		$html .= '</div>';

		// hier der gesamtbetrag kalkuliert
		/* $gesamtbetrag = 0;
		foreach ($clients_to_show as $client) {
			foreach ($client[1] as $project) {
				foreach ($project->times as $time) {
					if (!$time->notbillable) {
						$gesamtbetrag += $time->hours * $time->stundensatz;
					}
				}
			}
		} */
		$html .= '<div id="totaltotalbetrag" class="total">';
			$html .= '<span>Gesamtbetrag: -</span>';
		$html .= '</div>';

	} else {
		$randomfunnystring = array(
			'Die Zeiten? Die haben sich hier scheinbar unsichtbar gemacht!',
			'Zeit? Welche Zeit? Hier herrscht absolute Funkstille!',
			'Hier gibt’s so wenig Zeiten, dass man fast glauben könnte, sie sind im Urlaub!',
			'Sieht so aus, als hätten die Zeiten verschlafen – keine Einträge!',
			'Hier sind die Zeiten wohl auf mysteriöse Weise verschwunden – keine Daten, keine Party!',
			'Hier ist tote Hose, was die Zeiten angeht – keine einzige erfasst!',
			'Die Zeiten haben sich hier wohl auf leisen Sohlen davon gemacht – nichts zu sehen!',
			'Hier haben die Zeiten wohl spontan einen freien Tag eingelegt – nichts zu finden!',
			'Die Zeiten? Die haben sich hier scheinbar unsichtbar gemacht!',
			'Die Zeiten haben sich wohl auf einen Kaffee verabschiedet – nix erfasst!',
			'Offenbar haben die Zeiten sich in Luft aufgelöst – nichts erfasst!',
			'Hier haben die Zeiten beschlossen, mal unsichtbar zu spielen – keine Spur!',
			'Die Zeiten sind wohl auf Abenteuerreise – jedenfalls nicht hier!',
			'Scheinbar haben die Zeiten einen Tag frei genommen – nichts da!',
			'Die Zeiten sind gerade schwer zu fassen – keine Einträge gefunden!',
			'Zeit? Welche Zeit? Hier herrscht komplette Leere!',
			'Hier sieht’s aus, als hätten die Zeiten einfach Pause gemacht – nichts registriert!',
			'Die Zeiten haben hier wohl beschlossen, nicht mitzumachen – keine Daten!',
			'Hier hat die Zeit wohl gestreikt – absolut nichts erfasst!',
			'Die Zeiten sind wohl heimlich abgehauen – kein Eintrag zu sehen!',
		);
		$html .= '<div id="timesnooo"><div>Keine Zeiten für diesen Zeitraum.</div><h2>'.$randomfunnystring[array_rand($randomfunnystring)].'</h2></div>';
	}

	$html .= '<div class="sppp_b"></div>';



	return $html;

	
	exit();
}


// done_time
add_action('wp_ajax_done_time', 'done_time');
add_action('wp_ajax_nopriv_done_time', 'done_time');

function done_time() {
	check_ajax_referer( 'get_projects_and_times123', 'security' );
	$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	$password = (STRING)$_POST['password'];
	$client_id_parent = (INT)$_POST['client_id_parent'];
	$client_id = (INT)$_POST['client_id'];
	$time_id = (INT)$_POST['time_id'];
	$state = (INT)$_POST['state'];

	$hashed = $_COOKIE['timespassword_'.$client_id_parent];

	if ($state == 2) {
		$state = time();
	} else {
		$state = 0;
	}
	global $wpdb;

	// first check password
	$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE id = '$client_id_parent'");

	if ($password) {
		if (!wp_check_password($password, $client->pass)) {
			wp_send_json_error('Falsches Passwort.');
			exit();
		} else if ($hashed) {
			// check if hashed is correct
			$encryption = 'wdj!837bdahb!Bhadshbj!8328!bhsda'; // Store this securely in wp-config.php
			$checkhashed = decrypt_data($hashed, $encryption);

			if (!wp_check_password($checkhashed, $client->pass)) {
				wp_send_json_error('Falsches Passwort.');
				exit();
			}
		} else {
			wp_send_json_error('Kein Zugriff');
			exit();
		}
	}

	// now get time by timeid and clientid
	$time = $wpdb->get_row("SELECT * FROM wpt_tallyr_times WHERE id = '$time_id' AND clientid = '$client_id'");
	if (!$time) {
		wp_send_json_error('Keine Zeit gefunden.');
		exit();
	}

	$wpdb->update('wpt_tallyr_times', array('done' => $state), array('id' => $time_id));

	wp_send_json_success();
	wp_die();
	exit();
}


function tallyercheckIfLink($string) {
	// if a link exists in string,if yes grab the link into an a tag with target_blank

	$regex = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/';
	$string = preg_replace($regex, '<a rel="nofollow noopener" href="$0" target="_blank">$0</a>', $string);

	return $string;
}

// done_time
add_action('wp_ajax_save_time_comment', 'save_time_comment');
add_action('wp_ajax_nopriv_save_time_comment', 'save_time_comment');

function save_time_comment() {
	check_ajax_referer( 'get_projects_and_times123', 'security' );
	$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	$comment = normform_sanitize_text_or_array_field((STRING)$_POST['comment']);
	$client_id_parent = (INT)$_POST['client_id_parent'];
	$client_id = (INT)$_POST['client_id'];
	$time_id = (INT)$_POST['time_id'];
	$password = (STRING)$_POST['password'];

	$hashed = $_COOKIE['timespassword_'.$client_id_parent];
	global $wpdb;

	// first check password
	$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE id = '$client_id_parent'");

	if ($password) {
		if (!wp_check_password($password, $client->pass)) {
			wp_send_json_error('Falsches Passwort.');
			exit();
		} else if ($hashed) {
			// check if hashed is correct
			$encryption = 'wdj!837bdahb!Bhadshbj!8328!bhsda'; // Store this securely in wp-config.php
			$checkhashed = decrypt_data($hashed, $encryption);

			if (!wp_check_password($checkhashed, $client->pass)) {
				wp_send_json_error('Falsches Passwort.');
				exit();
			}
		} else {
			wp_send_json_error('Kein Zugriff');
			exit();
		}
	}

	// now get time by timeid and clientid
	$time = $wpdb->get_row("SELECT * FROM wpt_tallyr_times WHERE id = '$time_id' AND clientid = '$client_id'");
	if (!$time) {
		wp_send_json_error('Keine Zeit gefunden.');
		exit();
	}

	$fr = $client_id;
	if ($client_id_parent) {
		$fr = $client_id_parent;
	}

	// create comment in wpt_tallyr_messages
	$wpdb->insert('wpt_tallyr_messages', array(
		'tid' => $time_id,
		'uid' => $time->userid,
		'fr' => $fr,
		'clientid' => $client_id,
		'clientid_parent' => $client_id_parent,
		'message' => $comment,
		'date' => current_time('mysql')
	));

	$html = '<div class="comment fromclient"><div class="date">'.date('d.m.Y H:i').'</div><div class="message">'.tallyercheckIfLink(nl2br($comment)).'</div><i data-timeid="'.$time_id.'" data-mid="'.$wpdb->insert_id.'" class="deletemessage bx bx-trash-alt"></i></div>';

	// send email to admin that a new comment has been added
	// get email from $time->userid
	$user = get_user_by('id', $time->userid);
	if ($user) {
		$to = $user->user_email;
		$subject = 'TALLYR: Neuer Kommentar zu »'.$time->description.' ('.date('d.m.', strtotime($time->workdate)).')«';
		$body = '<p>Es wurde ein neuer Kommentar zu deiner Zeit-Eintragung »'.$time->description.' ('.date('d.m.', strtotime($time->workdate)).')« hinzugefügt:</p>';
		$body .= '<p style="background-color:#eee">'.$comment . "</p>";
		$body .= '<p><a rel="nofollow noopener" href="https://tallyr.de/userdashboard/dashboard/?ccpage=view&pid=39">Jetzt antworten</a></p>';

		wp_mail($to, $subject, $body, $headers);
	}

	wp_send_json_success($html);
	wp_die();
	exit();
}


add_action('wp_ajax_delete_time_message', 'delete_time_message');
add_action('wp_ajax_nopriv_delete_time_message', 'delete_time_message');

function delete_time_message() {
	check_ajax_referer( 'get_projects_and_times123', 'security' );
	$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	$mid = (INT)$_POST['mid'];
	$time_id = (INT)$_POST['timeid'];
	$client_id_parent = (INT)$_POST['client_id_parent'];
	$client_id = (INT)$_POST['client_id'];
	$password = (STRING)$_POST['password'];

	$hashed = $_COOKIE['timespassword_'.$client_id_parent];
	global $wpdb;

	// first check password
	$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE id = '$client_id_parent'");

	if ($password) {
		if (!wp_check_password($password, $client->pass)) {
			wp_send_json_error('Falsches Passwort.');
			exit();
		} else if ($hashed) {
			// check if hashed is correct
			$encryption = 'wdj!837bdahb!Bhadshbj!8328!bhsda'; // Store this securely in wp-config.php
			$checkhashed = decrypt_data($hashed, $encryption);

			if (!wp_check_password($checkhashed, $client->pass)) {
				wp_send_json_error('Falsches Passwort.');
				exit();
			}
		} else {
			wp_send_json_error('Kein Zugriff');
			exit();
		}
	}

	// now get time by timeid and clientid
	$time = $wpdb->get_row("SELECT * FROM wpt_tallyr_times WHERE id = '$time_id' AND clientid = '$client_id'");
	if (!$time) {
		wp_send_json_error('Keine Zeit gefunden.');
		exit();
	}

	// create comment in wpt_tallyr_messages
	$wpdb->delete('wpt_tallyr_messages', array('mid' => $mid, 'tid' => $time_id, 'clientid' => $client_id, 'uid' => $time->userid));

	wp_send_json_success();
	wp_die();
	exit();
}