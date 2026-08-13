// @codekit-append "sweetalert.min.js";
// @codekit-append "tsparticles.confetti.bundle.min.js";

jQuery(document).ready(function ($) {

	toastr.options = {
		"positionClass": "toast-bottom-right",
		"timeOut": "2000",
		"preventDuplicates": false,
	}

	// Mobile: Add back buttons to #neuropager panels
	if (window.innerWidth <= 768 && $('#neuropager').length) {
		$('#chopro #sidetitle, #chotimes .sidetitlepro, #captime #neuroheader .container').each(function () {
			if (!$(this).find('.mobile-back').length) {
				$(this).prepend('<button type="button" class="mobile-back" style="background:none;border:none;font-size:18px;cursor:pointer;margin-right:8px;padding:4px;color:inherit;vertical-align:middle;"><i class="bx bx-chevron-left"></i></button>');
			}
		});
	}

	$(document).on('click', '.mobile-back', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var panel = $(this).closest('.first, .second');
		if (panel.is('#captime')) {
			$('#captime').removeClass('open');
		} else if (panel.is('#chotimes')) {
			$('#chotimes').removeClass('open');
		} else if (panel.is('#chopro')) {
			$('#chopro').removeClass('open');
		}
	});

	// Toggle password visibility
	$(document).on('click', '.toggle-password', function () {
		var targetId = $(this).data('target');
		var input = $('#' + targetId);
		var icon = $(this).find('i');

		if (input.attr('type') === 'password') {
			input.attr('type', 'text');
			icon.removeClass('bx-hide').addClass('bx-show');
		} else {
			input.attr('type', 'password');
			icon.removeClass('bx-show').addClass('bx-hide');
		}
	});

	// createclient
	$(document).on('submit', '#createclient', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitbutton = $('button[type="submit"]', form);

		// get p_ansprache, p_lang, p_desc, p_title
		var client_id = form.data('projectid');
		var c_title = $('#c_title', form).val();
		var c_shortdesc = $('#c_shortdesc', form).val();
		var stundensatz = $('#stundensatz', form).val();
		var c_hexcolor = $('#c_hexcolor', form).val();
		var password = $('#passworder', form).val();
		var parentclient = $('#parentclient', form).val();
		var asana_project_id = $('#asana_project_id', form).val() || '';

		if (!c_title) {
			alert('Bitte gebe einen Titel ein.');
			return;
		}

		submitbutton.prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_createclient',
				c_title: c_title,
				c_shortdesc: c_shortdesc,
				stundensatz: stundensatz,
				password: password,
				c_hexcolor: c_hexcolor,
				parentclient: parentclient,
				client_id: client_id,
				asana_project_id: asana_project_id,
			},
			success: function (result) {
				location.reload();
				submitbutton.prop('disabled', false);
			},
			error: function (result) {
				console.log(result);
				submitbutton.prop('disabled', false);
			}
		});
	});


	// Simple client search 

	/*  <div id="sidetitle">
			Kunde wählen
		</div>
		<input type="text" id="searchclient" placeholder="Suche Kunde">
		<div id="dbresults">
			<?php foreach ($clients as $pro) { ?>
				<div class="item">
					<a class="clientcho" href="#"><span class="ccolor" style="background:<?php echo $pro->hexcolor; ?>"></span><?php echo $pro->title; ?> (<?php echo $pro->shortdesc; ?>)</a>
				</div>
			<?php } ?>
				<a class="creat" id="createnewproject" href="https://tallyr.de/userdashboard/dashboard/?ccpage=view&pid=10"><i class='bx bx-plus'></i>Kunde anlegen</a>
		</div> */

	$(document).on('keyup', '.searchin', function (e) {
		e.preventDefault();
		var search = $(this).val();
		var items = $(this).parents('.first').find('.item');
		items.hide();
		items.each(function () {
			var item = $(this);
			var text = item.text().toLowerCase();
			if (text.indexOf(search.toLowerCase()) >= 0) {
				item.show();
			}
		});

		var visibleitems = $(this).parents('.first').find('.item:visible');
		if (visibleitems.length == 1) {
			visibleitems.find('a').click();
		}
	});

	$(document).on('click', '.clientcho', function (e) {
		e.preventDefault();
		$('#chopro').removeClass('open');
		$('#captime').removeClass('open');

		resetform();

		$('#chotimes').removeClass('open');
		$('#dbresultstimes .results').html('');

		$('.clientcho').removeClass('current');
		$(this).addClass('current');
		$('#capturetime input#stundensatz').val($(this).data('stundensatz'));
		$('#capturetime select#parentclient').val($(this).data('parentclient'));

		$('.sidetitlepro, #sidetitle, #editproject button[type="submit"]').css('background', $(this).find('.ccolor').css('background'));

		// loading icon in $('#dbresultspro .results')
		$('#dbresultspro .results').html('<div class="loadingin"><i class="bx bx-loader-alt bx-spin"></i></div>');

		// get all projects from this client
		var client_id = $(this).data('id');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_getprojectsfromclient',
				client_id: client_id,
			},
			success: function (result) {
				$('#chopro').addClass('open');
				$('#dbresultspro .results').html(result);
			},
			error: function (result) {
				console.log(result);
			}
		});
	});

	$(document).on('click', '#createnewtime', function (e) {
		e.preventDefault();
		resetform();
	});


	// Stoppclock: when click on #starttimer start the stopclock, and set label to stop timer
	// when click a second time, fill #time with the time recoreded and set label to start timer
	// a hour is 1, 15 minutes is 0.25, 30 minutes is 0.5, 45 minutes is 0.75
	// minimum time is 0.25, the time shoudld always be uprounded to 0,25, so if 16 minutes are recorded, 0.5 (is 30 minutes) should be saved
	// intervall should be checked every minute
	// when timer is stopped, the time should be saved in the form

	$(document).on('click', '#starttimer', function (e) {
		e.preventDefault();

		if (!timerRunning) {
			startTimer();
		} else {
			stopTimer();
		}
	});

	let timerRunning = false;
	let startTime = 0;
	let interval;

	function startTimer() {
		startTime = Date.now();
		timerRunning = true;
		$('#starttimer').html('<i class="bx bx-spin bx-loader-alt"></i>');
		// prevent closing window
		$(window).on('beforeunload', function () {
			return 'Wenn du die Seite verlässt, wird die Zeit nicht gespeichert.';
		});

		interval = setInterval(updateTime, 60000); // Update every minute
	}

	function stopTimer() {
		clearInterval(interval);
		timerRunning = false;
		let elapsedMinutes = (Date.now() - startTime) / 60000;
		let recordedTime = roundToQuarterHour(elapsedMinutes);
		$('#time').val(recordedTime);
		$('#starttimer').html('Start Timer');
		$(window).off('beforeunload');
	}

	function roundToQuarterHour(minutes) {
		let quarters = Math.ceil(minutes / 15);
		let timeValue = quarters * 0.25;
		return Math.max(timeValue, 0.25);
	}

	function updateTime() {
		let elapsedMinutes = (Date.now() - startTime) / 60000;
		let currentTime = roundToQuarterHour(elapsedMinutes);
		$('#time').val(currentTime);
	}





	$(document).on('click', '#dbresultspro .item a', function (e) {
		e.preventDefault();

		resetform();

		var project_id = $(this).data('id');
		if (!project_id) {
			return;
		}

		var projectstundensatz = $(this).data('stundensatz');
		if (projectstundensatz) {
			$('#capturetime input#stundensatz').val(projectstundensatz);
		}

		var projectparentclient = $('#dbresults a.current').data('parentclient');
		if (projectparentclient) {
			$('#capturetime select#parentclient').val(projectparentclient);
		}

		$('#dbresultspro .item a').removeClass('current');
		$(this).addClass('current');

		$('#capturetime button[type="submit"]').prop('disabled', false);
		$('#captime').addClass('open');

		// loading icon in $('#dbresultstimes .results')
		$('#dbresultstimes .results').html('<div class="loadingin"><i class="bx bx-loader-alt bx-spin"></i></div>');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_gettimesfromproject',
				project_id: project_id,
			},
			success: function (result) {
				$('#chotimes').addClass('open');
				$('#dbresultstimes .results').html(result);

			},
			error: function (result) {
				console.log(result);
			}
		});
	});

	$(document).on('click', '#dbresultstimes .item a', function (e) {
		e.preventDefault();

		// not class i.copythis
		if ($(e.target).hasClass('copythis')) {
			return;
		}

		var times_id = $(this).data('id');
		if (!times_id) {
			$('form#capturetime').data('times_id', 0);
			return;
		}

		$('#dbresultstimes .item a').removeClass('current');
		$(this).addClass('current');

		$('form#capturetime').data('times_id', times_id);

		/* 			$timehtml .= '<a data-parentclient="'.$item->parentclient.'" data-notbillable="'.$time->notbillable.'" data-time="'.$time->hours.'" data-workdate="'.$time->workdate.'" data-id="'.$time->id.'">';
 */
		$('#capturetime #time').val($(this).data('time'));
		$('#capturetime #date').val($(this).data('workdate'));
		$('#capturetime #stundensatz').val($(this).data('stundensatz'));
		$('#capturetime #parentclient').val($(this).data('parentclient'));
		if ($(this).data('notbillable') == 1) {
			$('#abrechenbar').prop('checked', true);
		} else {
			$('#abrechenbar').prop('checked', false);
		}
		$('#capturetime #description').val($(this).find('.desc').text());
		$('#capturetime #linknotice').val($(this).find('.linknotice').text());
		$('#neuroheader h1').text('Zeit bearbeiten');
		$('#editproject button[type="submit"]').text('Speichern');
	});

	function resetform() {
		$('#capturetime #time').val('');

		// date to current date
		var d = new Date();
		var month = d.getMonth() + 1;
		var day = d.getDate();
		var output = d.getFullYear() + '-' +
			(month < 10 ? '0' : '') + month + '-' +
			(day < 10 ? '0' : '') + day;
		$('#capturetime #date').val(output);

		$('#capturetime #parentclient').val('');
		$('#capturetime #abrechenbar').prop('checked', false);
		$('#capturetime #description').val('');
		$('#capturetime #linknotice').val('');
		$('#capturetime').data('times_id', 0);
		$('#dbresultstimes .item a').removeClass('current');
		$('#neuroheader h1').text('Neue Zeit erfassen');
		$('#editproject button[type="submit"]').text('Zeit erfassen');
	}


	$(document).on('click', '#createnewprojecter', function (e) {
		e.preventDefault();

		resetform();

		// open modal
		$('#modalcreateproject').show();
		$('#createprojectform').data('projectid', 0);

		// focus first input
		$('#projecttitle').focus();
	});

	// close modal 
	$(document).on('click', '.closemodal', function (e) {
		e.preventDefault();
		$(this).parents('.custommodal').hide();
		$(this).parents('.custommodal').find('input').val('');
		$(this).parents('.custommodal').find('textarea').val('');
	});


	// edit porject in modal, fill form
	/* 			<a data-title="''" data-description="'.$project->description.'" data-maxhours="'.$project->maxhours.'" data-stundensatz="'.$project->stundensatz.'" data-id="'.$project->id.'">'.$project->title.'<span class="dater">'.date('d.m.Y', strtotime($project->created)).'</span><i class="bx bx-edit editpro"></i><i class="bx bx-archive deleteproject"></i></a>
 */
	$(document).on('click', '#dbresultspro .editpro', function (e) {

		e.preventDefault();
		var project_id = $(this).parent('a').data('id');
		var title = $(this).parent('a').data('title');
		var description = $(this).parent('a').data('description');
		var maxhours = $(this).parent('a').data('maxhours');
		var stundensatz = $(this).parent('a').data('stundensatz');

		$('#modalcreateproject').show();
		$('#createprojectform').data('projectid', project_id);
		$('#projecttitle').val(title);
		$('#projectdesc').val(description);
		$('#projectmaxhours').val(maxhours);
		$('#projectstundensatz').val(stundensatz);
	});


	$(document).on('click', '.kioutform', function (e) {
		e.preventDefault();

		var kibtn = $(this);

		var textarea = $('#' + kibtn.data('id'));
		if (!textarea.val()) {
			alert('Bitte gebe einen Wert ein.');
			return;
		}
		var text = textarea.val();
		//ajax
		var btn_text = kibtn.text();
		kibtn.html('<i class="bx bx-loader-alt bx-spin"></i>');
		kibtn.prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_kiausformulieren',
				prompttext: text,
			},
			success: function (result) {
				if (result) {
					textarea.val(result);
				}
				kibtn.html(btn_text);
				kibtn.prop('disabled', false);
			},
			error: function (result) {
				console.log(result);
			}
		});


	});



	// create new project
	/* <form id="createprojectform">
			<input required type="text" id="projecttitle" placeholder="Projektname">
			<textarea required id="projectdesc" placeholder="Projektbeschreibung"></textarea>
			<input type="number" id="projectmaxhours" placeholder="Verfügbare Stunden">
			<input type="number" id="projectstundensatz" placeholder="Stundensatz">
			<button id="createproject">Projekt anlegen</button>
		</form> */
	$(document).on('submit', '#createprojectform', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitbutton = $('button[type="submit"]', form);

		var client_id = $('#dbresults a.current').data('id');
		var p_title = $('#projecttitle', form).val();
		var p_desc = $('#projectdesc', form).val();
		var p_maxhours = $('#projectmaxhours', form).val();
		var p_stundensatz = $('#projectstundensatz', form).val();
		var projectid = form.data('projectid');

		if (!client_id) {
			alert('Bitte wähle einen Kunden.');
			return;
		}
		if (!p_title) {
			alert('Bitte gebe einen Projekttitel ein.');
			return;
		}

		submitbutton.prop('disabled', true);

		$('#modalcreateproject').hide();

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_createproject',
				client_id: client_id,
				p_title: p_title,
				p_desc: p_desc,
				p_maxhours: p_maxhours,
				p_stundensatz: p_stundensatz,
				projectid: projectid,
			},
			success: function (result) {
				// reload projects
				$('#dbresultspro .results').html(result);
				// show swal success
				toastr.success('Projekt gespeichert.');
				submitbutton.prop('disabled', false);

				// close modal
				$('#modalcreateproject').hide();
				$('#createprojectform input').val('');
				$('#createprojectform textarea').val('');

			},
			error: function (result) {
				console.log(result);
				submitbutton.prop('disabled', false);
			}
		});
	});

	$(document).on('click', '#dbresultspro .deleteproject', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var project_id = $(this).parents('a').data('id');
		var item = $(this).parents('.item');

		Swal.fire({
			title: 'Projekt archivieren',
			text: "Soll das Projekt wirklich archiviert werden?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			cancelButtonColor: '#3085d6',
			confirmButtonText: 'Ja, löschen'
		}).then((result) => {
			if (result.isConfirmed) {

				item.slideUp();

				jQuery.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_deleteproject',
						project_id: project_id,
					},
					success: function (result) {
						// remove item
						item.remove();
						// show swal success
						toastr.success('Projekt wurde gelöscht.');
					},
					error: function (result) {
						console.log(result);
					}
				});
			}
		});
	}
	);


	$(document).on('click', '#dbresultstimes .deletetime, .adminbtnss .deletetime', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var times_id = $(this).parents('a').data('id');
		var item = $(this).parents('.item');

		if (!times_id) {
			times_id = $(this).data('timeid');
			item = $('#times_id_' + times_id);
		}

		if (!times_id) {
			alert('Fehler');
			return;
		}

		Swal.fire({
			title: 'Zeit löschen',
			text: "Soll die Zeit wirklich gelöscht werden?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			cancelButtonColor: '#3085d6',
			confirmButtonText: 'Ja, löschen'
		}).then((result) => {
			if (result.isConfirmed) {

				item.slideUp();

				jQuery.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_deletetime',
						times_id: times_id,
					},
					success: function (result) {
						// remove item
						item.remove();
						// show swal success
						toastr.success('Zeit wurde gelöscht.');
						getTimes();
					},
					error: function (result) {
						console.log(result);
					}
				});
			}
		});
	}
	);




	$(document).on('submit', '#capturetime', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitbutton = $('button[type="submit"]', form);

		// get p_ansprache, p_lang, p_desc, p_title
		var project_id = parseInt($('#dbresultspro .item a.current').data('id'));
		var description = $('#description', form).val();
		var linknotice = $('#linknotice', form).val();
		var time = $('#time', form).val();
		var date = $('#date', form).val();
		var stundensatz = $('#stundensatz', form).val();
		var parentclient = $('#parentclient', form).val();
		var abrechenbar = 0;
		if ($('#abrechenbar', form).is(':checked')) {
			abrechenbar = 1;
		}
		var client_id = parseInt($('#dbresults .item a.current').data('id'));
		var times_id = form.data('times_id');

		if (!client_id) {
			alert('Bitte wähle einen Kunden.');
			return;
		}
		if (!project_id) {
			alert('Bitte wähle ein Projekt.');
			return;
		}


		submitbutton.prop('disabled', true);
		$('#dbresultstimes .results').html('<div class="loadingin"><i class="bx bx-loader-alt bx-spin"></i></div>');

		/* confetti({
			particleCount: 100,
			spread: 70,
			scalar: 3,
			origin: { y: 0.6 },
			shapes: ["emoji"],
			shapeOptions: {
				emoji: {
					value: ["💶", "🤑", "💰", "💲"],
				},
			},
		}); */

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_createtime',
				project_id: project_id,
				description: description,
				linknotice: linknotice,
				time: time,
				date: date,
				stundensatz: stundensatz,
				parentclient: parentclient,
				abrechenbar: abrechenbar,
				client_id: client_id,
				times_id: times_id,
			},
			success: function (result) {
				// reload times
				$('#dbresultstimes .results').html(result);

				countProjectHours(project_id);

				// show swal success
				if (times_id) {
					toastr.success('Zeit wurde bearbeitet.');
				} else {
					//toastr.success('Neue Zeit wurde erfasst.');

					if (stundensatz > 0 && time) {

						const end = Date.now() + 1 * 1000;

						var headlines = ['💪 Stark!',
							'👍 Super!',
							'🎉 Wow!',
							'🥳 Genial!',
							'🚀 Perfekt!',
							'🤑 Klasse!',
							'💰 Top!',
							'💲 Super!',
							'👏 Du Maschine!',
							'🤩 Hammer!',
							'🎊 Cool!',
							'🎈 Gut gemacht!',
							'🔥 Weiter so!',
						];

						var verdient = stundensatz * time;
						// pick random headline
						var headline = headlines[Math.floor(Math.random() * headlines.length)];

						$('#userdash').append('<div id="superb"><h3>' + headline + '</h3><p>Du hast gerade <strong>' + verdient + ' €</strong> verdient!</p></div>');

						// add class active to #superb after 100 ms
						setTimeout(function () {
							$('#superb').addClass('active');
						}, 100);


						setTimeout(function () {
							$('#superb').removeClass('active');
						}, 2000);

						setTimeout(function () {
							$('#superb').remove()
						}, 2500);

						(function frame() {
							confetti({
								particleCount: 2,
								angle: 60,
								scalar: 2,
								spread: 55,
								origin: { x: 0 },
								shapes: ["emoji"],
								shapeOptions: {
									emoji: {
										value: ["💶", "🤑", "💰", "💲"],
									},
								},
							});

							confetti({
								particleCount: 2,
								angle: 120,
								scalar: 2,
								spread: 55,
								origin: { x: 1 },
								shapes: ["emoji"],
								shapeOptions: {
									emoji: {
										value: ["💶", "🤑", "💰", "💲"],
									},
								},
							});

							if (Date.now() < end) {
								requestAnimationFrame(frame);
							}
						})();
					} else {
						toastr.success('Neue Zeit wurde erfasst.');
					}
				}
				submitbutton.prop('disabled', false);
				resetform();
				getTimes();
			},
			error: function (result) {
				console.log(result);
				submitbutton.prop('disabled', false);
			}
		});
	});


	// get today times in hours from all tasks and put into #todaytimesinhours on page load
	function getTimes() {

		// <div id="todaytimesdiagram">
		//<div class="pie pieanimate" style="--p:80;"><span id="todaytimesinhour"></span>/8</div>
		//</div>

		var datatime = $('#todaytimesdiagram').data('time');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_gettodaytimes',
			},
			success: function (result) {
				$('#todaytimesdiagram').addClass('open');
				$('#todaytimesinhour').html(result);
				// calulate percentage
				var percent = (result / datatime) * 100;
				$('#todaytimesdiagram .pie').css('--p', percent);
			},
			error: function (result) {
			}
		});
	}
	// on document fully loaded
	getTimes();

	$(document).on('click', 'button.unbill, button.bill', function (e) {
		e.preventDefault();
		var timeid = $(this).data('timeid');
		var btnn = $(this);
		var billed = 0;
		if ($(this).hasClass('bill')) {
			billed = 1;

			$(this).removeClass('bill');
			$(this).addClass('unbill');
			$(this).html('<i class="bx bx-check-circle"></i> Abgerechnet');

		} else {
			billed = 0;
			$(this).removeClass('unbill');
			$(this).addClass('bill');
			$(this).html('<i class="bx bx-euro"></i>');
		}

		$(this).prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_markasbilled',
				times_id: timeid,
				billed: billed,
			},
			success: function (result) {
				btnn.prop('disabled', false);
				toastr.success(result);

			},
			error: function (result) {
			}
		});


	});


	// on change times_datepicker_admin
	$(document).on('change', '#times_datepicker_admin', function () {
		gettimesbydate();
	});

	$(document).on('click', '#times_datepicker_admin #monthstopick', function () {
		gettimesbydate();
	});

	$(document).on('click', 'i.copythis', function (e) {
		e.preventDefault();

		resetform();

		var item = $(this).parents('.item');
		var desc = item.find('.desc').text();
		var linknotice = item.find('.linknotice').text();

		if (desc) {
			$('#description').val(desc);
		}
		if (linknotice) {
			$('#linknotice').val(linknotice);
		}

		// change icons class for 2 seconds add checkmark
		$(this).removeClass('bx-copy-alt');
		$(this).addClass('bx-check');

		setTimeout(function () {
			$('i.copythis').removeClass('bx-check');
			$('i.copythis').addClass('bx-copy-alt');
		}, 1000);

	});

	// ========================================
	// STATISTICS DASHBOARD
	// ========================================

	var statsData = null;
	var filteredEntries = [];

	function gettimesbydate() {
		var start_date = $('#datepicker_start').val();
		var end_date = $('#datepicker_end').val();

		// Show loading
		$('#stats-loading').show();
		$('#kpi-cards, #period-comparison, #stats-filters, #charts-row, #projects-overview, #entries-section').css('opacity', '0.5');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_gettimesbydate',
				start_date: start_date,
				end_date: end_date,
			},
			success: function (response) {
				$('#stats-loading').hide();
				$('#kpi-cards, #period-comparison, #stats-filters, #charts-row, #projects-overview, #entries-section').css('opacity', '1');

				if (response.success && response.data) {
					statsData = response.data;
					renderStatsDashboard();
				}
			},
			error: function (result) {
				$('#stats-loading').hide();
				$('#kpi-cards, #period-comparison, #stats-filters, #charts-row, #projects-overview, #entries-section').css('opacity', '1');
			}
		});
	}

	function formatCurrency(value) {
		return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
	}

	function formatNumber(value, decimals) {
		return new Intl.NumberFormat('de-DE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
	}

	function renderStatsDashboard() {
		if (!statsData) return;

		var stats = statsData.stats;
		var comparison = statsData.comparison;

		// KPI Cards
		$('#kpi-total-hours').text(formatNumber(stats.total_hours, 2) + ' h');
		$('#kpi-billable-hours').text(formatNumber(stats.billable_hours, 2) + ' h abrechenbar');
		$('#kpi-total-revenue').text(formatCurrency(stats.total_revenue));
		$('#kpi-open-revenue').text(formatCurrency(stats.open_revenue) + ' offen');
		$('#kpi-avg-rate').text(formatCurrency(stats.avg_rate) + '/h');
		$('#kpi-billed-revenue').text(formatCurrency(stats.billed_revenue) + ' abgerechnet');
		$('#kpi-project-count').text(stats.project_count);
		$('#kpi-entry-count').text(stats.entry_count + ' Einträge');

		// Period Comparison
		if (comparison.prev_hours > 0 || comparison.prev_revenue > 0) {
			$('#period-comparison').show();

			var hoursIcon = comparison.hours_change >= 0 ? '<i class="bx bx-up-arrow-alt up"></i>' : '<i class="bx bx-down-arrow-alt down"></i>';
			var hoursClass = comparison.hours_change >= 0 ? 'positive' : 'negative';
			$('#comp-hours').html(hoursIcon + ' Stunden: <span class="' + hoursClass + '">' + formatNumber(comparison.hours_change, 1) + '%</span>');

			var revIcon = comparison.revenue_change >= 0 ? '<i class="bx bx-up-arrow-alt up"></i>' : '<i class="bx bx-down-arrow-alt down"></i>';
			var revClass = comparison.revenue_change >= 0 ? 'positive' : 'negative';
			$('#comp-revenue').html(revIcon + ' Umsatz: <span class="' + revClass + '">' + formatNumber(comparison.revenue_change, 1) + '%</span>');
		} else {
			$('#period-comparison').hide();
		}

		// Populate client filter
		var clientSelect = $('#filter-client');
		clientSelect.find('option:not(:first)').remove();
		if (statsData.all_clients) {
			statsData.all_clients.forEach(function(client) {
				clientSelect.append('<option value="' + client.id + '">' + client.title + '</option>');
			});
		}

		// Render charts
		renderClientChart();
		renderWeekdayChart();

		// Render projects table
		renderProjectsTable();

		// Apply filters and render entries
		applyFilters();
	}

	function renderClientChart() {
		var container = $('#client-bars');
		container.empty();

		if (!statsData.clients || statsData.clients.length === 0) {
			container.html('<div class="no-data">Keine Daten</div>');
			return;
		}

		var maxHours = Math.max.apply(null, statsData.clients.map(function(c) { return c.hours; }));

		statsData.clients.forEach(function(client) {
			var percent = maxHours > 0 ? (client.hours / maxHours * 100) : 0;
			var html = '<div class="bar-item">';
			html += '<div class="bar-label">' + client.name + '</div>';
			html += '<div class="bar-track"><div class="bar-fill" style="width:' + percent + '%;background:' + client.color + '"></div></div>';
			html += '<div class="bar-value">' + formatNumber(client.hours, 2) + ' h</div>';
			html += '</div>';
			container.append(html);
		});
	}

	function renderWeekdayChart() {
		var container = $('#weekday-bars');
		container.empty();

		if (!statsData.weekdays || statsData.weekdays.length === 0) {
			container.html('<div class="no-data">Keine Daten</div>');
			return;
		}

		var maxHours = Math.max.apply(null, statsData.weekdays.map(function(d) { return d.hours; }));

		statsData.weekdays.forEach(function(day) {
			var percent = maxHours > 0 ? (day.hours / maxHours * 100) : 0;
			var html = '<div class="weekday-bar">';
			html += '<div class="weekday-fill" style="height:' + percent + '%"></div>';
			html += '<div class="weekday-label">' + day.day + '</div>';
			html += '<div class="weekday-value">' + formatNumber(day.hours, 1) + '</div>';
			html += '</div>';
			container.append(html);
		});
	}

	function renderProjectsTable() {
		var tbody = $('#projects-tbody');
		tbody.empty();

		if (!statsData.projects || statsData.projects.length === 0) {
			tbody.html('<tr><td colspan="5" class="no-data">Keine Projekte im Zeitraum</td></tr>');
			return;
		}

		statsData.projects.forEach(function(project) {
			var html = '<tr>';
			html += '<td><span class="client-dot" style="background:' + project.client_color + '"></span>' + project.name + '</td>';
			html += '<td>' + project.client + '</td>';
			html += '<td class="num">' + formatNumber(project.hours, 2) + ' h</td>';
			html += '<td class="num">' + formatCurrency(project.revenue) + '</td>';
			html += '<td class="num">' + formatCurrency(project.avg_rate) + '</td>';
			html += '</tr>';
			tbody.append(html);
		});
	}

	function applyFilters() {
		if (!statsData || !statsData.entries) return;

		var clientFilter = $('#filter-client').val();
		var billableFilter = $('#filter-billable').val();
		var billedFilter = $('#filter-billed').val();

		filteredEntries = statsData.entries.filter(function(entry) {
			if (clientFilter && entry.client_id != clientFilter) return false;
			if (billableFilter === 'billable' && !entry.billable) return false;
			if (billableFilter === 'notbillable' && entry.billable) return false;
			if (billedFilter === 'billed' && !entry.billed) return false;
			if (billedFilter === 'notbilled' && entry.billed) return false;
			return true;
		});

		renderEntriesTable();
	}

	function renderEntriesTable() {
		var tbody = $('#entries-tbody');
		tbody.empty();
		$('#entries-count').text(filteredEntries.length);

		if (filteredEntries.length === 0) {
			tbody.html('<tr><td colspan="9" class="no-data">Keine Einträge gefunden</td></tr>');
			return;
		}

		filteredEntries.forEach(function(entry) {
			var statusHtml = '';
			if (!entry.billable) {
				statusHtml = '<span class="status-badge notbillable">Nicht abrechenbar</span>';
			} else if (entry.billed) {
				statusHtml = '<span class="status-badge billed">Abgerechnet</span>';
			} else {
				statusHtml = '<span class="status-badge open">Offen</span>';
			}

			var billBtn = '';
			if (entry.billable && !entry.billed) {
				billBtn = '<button class="bill mini-btn" data-timeid="' + entry.id + '"><i class="bx bx-euro"></i></button>';
			} else if (entry.billable && entry.billed) {
				billBtn = '<button class="unbill mini-btn" data-timeid="' + entry.id + '"><i class="bx bx-check-circle"></i></button>';
			}

			var html = '<tr id="times_id_' + entry.id + '" class="' + entry.class + '">';
			html += '<td>' + entry.date + '</td>';
			html += '<td>' + entry.client + '</td>';
			html += '<td>' + entry.project + '</td>';
			html += '<td class="desc-cell">' + (entry.description || '-') + '</td>';
			html += '<td class="num">' + formatNumber(entry.hours, 2) + '</td>';
			html += '<td class="num">' + formatCurrency(entry.rate) + '</td>';
			html += '<td class="num">' + formatCurrency(entry.amount) + '</td>';
			html += '<td>' + statusHtml + '</td>';
			html += '<td class="adminbtnss">' + billBtn + '<button class="deletetime mini-btn" data-timeid="' + entry.id + '"><i class="bx bx-trash"></i></button></td>';
			html += '</tr>';
			tbody.append(html);
		});
	}

	// Filter change handlers
	$(document).on('change', '#filter-client, #filter-billable, #filter-billed', function() {
		applyFilters();
	});

	// Excel Export for Statistics
	$(document).on('click', '#stats-export-btn', function() {
		if (!statsData || !filteredEntries || filteredEntries.length === 0) {
			toastr.warning('Keine Daten zum Exportieren.');
			return;
		}

		var startDate = $('#datepicker_start').val();
		var endDate = $('#datepicker_end').val();

		// Create workbook
		var wb = XLSX.utils.book_new();

		// Sheet 1: Summary
		var summaryData = [
			['Statistik-Export'],
			['Zeitraum: ' + startDate + ' bis ' + endDate],
			[],
			['Kennzahl', 'Wert'],
			['Stunden gesamt', statsData.stats.total_hours],
			['Abrechenbare Stunden', statsData.stats.billable_hours],
			['Nicht abrechenbar', statsData.stats.not_billable_hours],
			['Umsatz gesamt', statsData.stats.total_revenue],
			['Umsatz offen', statsData.stats.open_revenue],
			['Umsatz abgerechnet', statsData.stats.billed_revenue],
			['Ø Stundensatz', statsData.stats.avg_rate],
			['Anzahl Projekte', statsData.stats.project_count],
			['Anzahl Einträge', statsData.stats.entry_count]
		];
		var wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
		XLSX.utils.book_append_sheet(wb, wsSummary, 'Übersicht');

		// Sheet 2: Clients
		var clientsData = [['Kunde', 'Stunden', 'Umsatz', 'Einträge']];
		statsData.clients.forEach(function(c) {
			clientsData.push([c.name, c.hours, c.revenue, c.entries]);
		});
		var wsClients = XLSX.utils.aoa_to_sheet(clientsData);
		XLSX.utils.book_append_sheet(wb, wsClients, 'Kunden');

		// Sheet 3: Projects
		var projectsData = [['Projekt', 'Kunde', 'Stunden', 'Umsatz', 'Ø Stundensatz']];
		statsData.projects.forEach(function(p) {
			projectsData.push([p.name, p.client, p.hours, p.revenue, p.avg_rate]);
		});
		var wsProjects = XLSX.utils.aoa_to_sheet(projectsData);
		XLSX.utils.book_append_sheet(wb, wsProjects, 'Projekte');

		// Sheet 4: Entries (filtered)
		var entriesData = [['Datum', 'Kunde', 'Projekt', 'Beschreibung', 'Stunden', 'Stundensatz', 'Betrag', 'Abrechenbar', 'Abgerechnet']];
		filteredEntries.forEach(function(e) {
			entriesData.push([
				e.date,
				e.client,
				e.project,
				e.description,
				e.hours,
				e.rate,
				e.amount,
				e.billable ? 'Ja' : 'Nein',
				e.billed ? 'Ja' : 'Nein'
			]);
		});
		var wsEntries = XLSX.utils.aoa_to_sheet(entriesData);
		XLSX.utils.book_append_sheet(wb, wsEntries, 'Zeiteinträge');

		// Download
		var filename = 'Statistik_' + startDate + '_' + endDate + '.xlsx';
		XLSX.writeFile(wb, filename);
		toastr.success('Export erstellt: ' + filename);
	});

	// if #times_datepicker_admin
	if ($('#times_datepicker_admin').length > 0 && $('#stats-dashboard').length > 0) {
		gettimesbydate();
	}

	// Drag and drop for times to projects

	// allowDrop on .pitem
	$(document).on('dragover', '.pitem', function (e) {
		e.preventDefault();
		$(this).addClass('dragover-highlight'); // Add your custom class
	});

	$(document).on('dragleave drop', '.pitem', function (e) {
		$(this).removeClass('dragover-highlight'); // Remove the custom class
	});

	// on drag start
	$(document).on('dragstart', '.titem', function (e) {

		/* <div class="item titem" draggable="true"><a data-stundensatz="100" data-parentclient="0" data-notbillable="0" data-time="2.00" data-workdate="2025-01-26" data-id="120"><div class="dater"><span>26.01.2025</span><span>2.00 h</span><i class="copythis bx bx-copy-alt"></i></div><div class="desc">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</div><div class="linknotice" style="display:none">asd</div><i class="bx bx-archive deletetime"></i></a></div> */
		var times_id = parseInt($(this).find('a').data('id'));

		if (!times_id) {
			alert('Fehler, bitte lade die Seite neu und versuche es erneut.');
			return;
		}

		e.originalEvent.dataTransfer.setData("time_id", times_id);

	});

	// on drop
	$(document).on('drop', '.pitem', function (e) {
		e.preventDefault();
		var time_id = e.originalEvent.dataTransfer.getData("time_id");
		var new_project_id = $(this).find('a').data('id');
		var current_project_id = $('#dbresultspro .item a.current').data('id');

		if (!time_id || !new_project_id || !current_project_id) {
			alert('Fehler, bitte lade die Seite neu und versuche es erneut.');
			return;
		}

		if (new_project_id == current_project_id) {
			alert('Du kannst keine Zeit in das gleiche Projekt verschieben.');
			return;
		}

		$('a[data-id=' + time_id + ']').parent('.titem').slideUp();

		// ajax
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_movetimetoproject',
				project_id: new_project_id,
				times_id: time_id,
			},
			success: function (result) {
				// remove item with animation
				$('a[data-id=' + time_id + ']').parent('.titem').slideUp();
				countProjectHours(new_project_id);
				countProjectHours(current_project_id);
			},
			error: function (result) {
			}
		});
	});


	// count current project hours and update span.phou
	function countProjectHours(project_id) {

		jQuery.ajax({
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
		});
	}


	// button readandaccept
	$(document).on('click', 'button.readandaccept', function (e) {
		e.preventDefault();
		var btn = $(this);

		var mid = btn.data('mid');
		var timeid = btn.data('timeid');


		btn.prop('disabled', true);
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_readandaccept',
				mid: mid,
				timeid: timeid,
			},
			success: function (result) {
				if (result == 'ok') {
					location.reload();
				} else {
					btn.prop('disabled', false);
					alert('Fehler, bitte lade die Seite neu und versuche es erneut.');
				}
			},
			error: function (result) {
				btn.prop('disabled', false);
				//alert('Fehler, bitte lade die Seite neu und versuche es erneut..');
			}
		});
	});

	// button settoundone
	$(document).on('click', 'button.settoundone', function (e) {
		e.preventDefault();
		var btn = $(this);

		var mid = btn.data('mid');
		var timeid = btn.data('timeid');
		btn.prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_settoundone',
				mid: mid,
				timeid: timeid,
			},
			success: function (result) {
				if (result == 'ok') {
					location.reload();
				} else {
					btn.prop('disabled', false);
					alert('Fehler, bitte lade die Seite neu und versuche es erneut.');
				}
			},
			error: function (result) {
				btn.prop('disabled', false);
				//alert('Fehler, bitte lade die Seite neu und versuche es erneut..');
			}
		});
	});


	// form submit sendreply
	$(document).on('submit', '.sendreply', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitbutton = $('button[type="submit"]', form);
		var mid = form.data('mid');
		var tid = form.data('tid');
		var message = $('textarea', form).val();

		if (!message) {
			alert('Bitte gebe eine Nachricht ein.');
			return;
		}

		submitbutton.prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_sendreply',
				mid: mid,
				tid: tid,
				message: message,
			},
			success: function (result) {
				if (result == 'ok') {
					location.reload();
				} else {
					submitbutton.prop('disabled', false);
					alert('Fehler, bitte lade die Seite neu und versuche es erneut.');
				}
			},
			error: function (result) {
				submitbutton.prop('disabled', false);
				//alert('Fehler, bitte lade die Seite neu und versuche es erneut..');
			}
		});
	});


	// OLD BEGINS HERE


	// ========================================
	// ASANA INTEGRATION
	// ========================================

	// Save Asana Token
	$(document).on('click', '#save_asana_token', function (e) {
		e.preventDefault();
		var btn = $(this);
		var input = $('#asana_token_input');
		var status = $('#asana_token_status');
		var token = input.val();

		// Don't send placeholder asterisks
		if (token === '********') {
			toastr.info('Token ist bereits gespeichert.');
			return;
		}

		btn.prop('disabled', true).text('Überprüfe...');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_save_asana_token',
				asana_token: token
			},
			success: function (response) {
				if (response.success) {
					status.removeClass('error').addClass('success').text(response.data.message);
					if (response.data.user_name) {
						status.append(' (Verbunden als: ' + response.data.user_name + ')');
					}
					toastr.success(response.data.message);
					if (token) {
						input.val('********');
					}
					// Reload to show/hide Asana project dropdown
					setTimeout(function () {
						location.reload();
					}, 1500);
				} else {
					status.removeClass('success').addClass('error').text(response.data);
					toastr.error(response.data);
				}
				btn.prop('disabled', false).text('Speichern');
			},
			error: function () {
				status.removeClass('success').addClass('error').text('Fehler beim Speichern.');
				btn.prop('disabled', false).text('Speichern');
			}
		});
	});

	// Store all Asana projects for filtering
	var allAsanaProjects = [];
	var selectedAsanaProjects = [];
	var asanaProjectsCacheKey = 'tallyr_asana_projects_cache';

	// Load cached projects from localStorage
	function loadCachedAsanaProjects() {
		try {
			var cached = localStorage.getItem(asanaProjectsCacheKey);
			if (cached) {
				return JSON.parse(cached);
			}
		} catch (e) {}
		return null;
	}

	// Save projects to localStorage cache
	function cacheAsanaProjects(projects) {
		try {
			localStorage.setItem(asanaProjectsCacheKey, JSON.stringify(projects));
		} catch (e) {}
	}

	// Load Asana Projects for multiselect
	function loadAsanaProjects() {
		var wrapper = $('#asana_project_wrapper');
		if (!wrapper.length) return;

		// Load current values immediately
		var currentValue = $('#asana_project_id').val();
		if (currentValue) {
			selectedAsanaProjects = currentValue.split(',').filter(function (v) { return v && v.trim(); }).map(function (v) { return String(v.trim()); });
		} else {
			selectedAsanaProjects = [];
		}

		// Try to show from cache immediately
		var cachedProjects = loadCachedAsanaProjects();
		if (cachedProjects && cachedProjects.length > 0) {
			allAsanaProjects = cachedProjects;
			renderSelectedTags();
			renderAsanaProjectOptions('');
		}

		// Show loading indicator if no cache
		if (!cachedProjects) {
			wrapper.find('.searchable-multiselect-input').attr('placeholder', 'Lade Projekte...');
		}

		// Load fresh data from API
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_asana_projects'
			},
			success: function (response) {
				if (response.success && response.data.length > 0) {
					// Sort projects alphabetically by name
					allAsanaProjects = response.data.sort(function (a, b) {
						return a.name.localeCompare(b.name, 'de', { sensitivity: 'base' });
					});
					// Cache for next time
					cacheAsanaProjects(allAsanaProjects);
				} else {
					allAsanaProjects = [];
				}

				renderSelectedTags();
				renderAsanaProjectOptions('');
				wrapper.find('.searchable-multiselect-input').attr('placeholder', selectedAsanaProjects.length > 0 ? '' : 'Projekt suchen...');
			},
			error: function () {
				wrapper.find('.searchable-multiselect-input').attr('placeholder', 'Fehler beim Laden');
			}
		});
	}

	// Update hidden input with selected values
	function updateAsanaProjectInput() {
		$('#asana_project_id').val(selectedAsanaProjects.join(','));
	}

	// Render selected tags
	function renderSelectedTags() {
		var wrapper = $('#asana_project_wrapper');
		var tagsContainer = wrapper.find('.selected-tags');
		var html = '';

		selectedAsanaProjects.forEach(function (gid) {
			var project = allAsanaProjects.find(function (p) { return String(p.gid) === String(gid); });
			if (project) {
				html += '<span class="tag" data-value="' + gid + '">' + project.name + '<i class="bx bx-x remove-tag"></i></span>';
			}
		});

		tagsContainer.html(html);

		// Update placeholder
		var input = wrapper.find('.searchable-multiselect-input');
		if (selectedAsanaProjects.length > 0) {
			input.attr('placeholder', '');
		} else {
			input.attr('placeholder', 'Projekt suchen...');
		}
	}

	// Render Asana project options (with optional filter)
	function renderAsanaProjectOptions(filter) {
		var wrapper = $('#asana_project_wrapper');
		var optionsContainer = wrapper.find('.searchable-multiselect-options');
		var filterLower = (filter || '').toLowerCase();
		var html = '';

		allAsanaProjects.forEach(function (project) {
			// Filter by search text
			if (filter && project.name.toLowerCase().indexOf(filterLower) === -1) {
				return;
			}
			var projectGid = String(project.gid);
			var workspaceName = project.workspace_name ? ' <span class="workspace">(' + project.workspace_name + ')</span>' : '';
			var isSelected = selectedAsanaProjects.indexOf(projectGid) !== -1;
			var selectedClass = isSelected ? ' selected' : '';
			var checkIcon = isSelected ? '<i class="bx bx-check"></i>' : '';
			html += '<div class="option' + selectedClass + '" data-value="' + projectGid + '">' + checkIcon + '<span class="option-text">' + project.name + workspaceName + '</span></div>';
		});

		if (!html) {
			html = '<div class="no-results">Keine Projekte gefunden</div>';
		}

		optionsContainer.html(html);
	}

	// Open multiselect dropdown
	$(document).on('click', '.searchable-multiselect-display', function (e) {
		if ($(e.target).hasClass('remove-tag') || $(e.target).closest('.remove-tag').length) {
			return;
		}
		e.stopPropagation();
		var wrapper = $(this).closest('.searchable-multiselect');
		var isOpen = wrapper.hasClass('open');

		// Close all other dropdowns
		$('.searchable-multiselect').removeClass('open');

		if (!isOpen) {
			wrapper.addClass('open');
			wrapper.find('.searchable-multiselect-input').val('').focus();
			renderAsanaProjectOptions('');
		}
	});

	// Focus input when clicking on display area
	$(document).on('focus', '.searchable-multiselect-display', function () {
		$(this).find('.searchable-multiselect-input').focus();
	});

	// Search in multiselect
	$(document).on('input', '.searchable-multiselect-input', function () {
		var searchText = $(this).val();
		var wrapper = $(this).closest('.searchable-multiselect');
		if (!wrapper.hasClass('open')) {
			wrapper.addClass('open');
		}
		renderAsanaProjectOptions(searchText);
	});

	// Toggle option selection in multiselect
	$(document).on('click', '.searchable-multiselect-options .option', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var value = String($(this).attr('data-value'));
		var index = selectedAsanaProjects.indexOf(value);

		if (index === -1) {
			// Add to selection
			selectedAsanaProjects.push(value);
		} else {
			// Remove from selection
			selectedAsanaProjects.splice(index, 1);
		}

		updateAsanaProjectInput();
		renderSelectedTags();
		renderAsanaProjectOptions($('.searchable-multiselect-input').val());

		// Keep dropdown open and refocus input
		$('.searchable-multiselect-input').focus();
	});

	// Remove tag
	$(document).on('click', '.selected-tags .remove-tag', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var tag = $(this).closest('.tag');
		var value = String(tag.attr('data-value'));
		var index = selectedAsanaProjects.indexOf(value);

		if (index !== -1) {
			selectedAsanaProjects.splice(index, 1);
		}

		updateAsanaProjectInput();
		renderSelectedTags();
		renderAsanaProjectOptions($('.searchable-multiselect-input').val());
	});

	// Close dropdown when clicking outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.searchable-multiselect').length) {
			$('.searchable-multiselect').removeClass('open');
		}
	});

	// Keyboard navigation for multiselect
	$(document).on('keydown', '.searchable-multiselect-input', function (e) {
		if (e.key === 'Escape') {
			$(this).closest('.searchable-multiselect').removeClass('open');
		} else if (e.key === 'Backspace' && !$(this).val()) {
			// Remove last tag on backspace when input is empty
			if (selectedAsanaProjects.length > 0) {
				selectedAsanaProjects.pop();
				updateAsanaProjectInput();
				renderSelectedTags();
				renderAsanaProjectOptions('');
			}
		}
	});

	// Initialize Asana projects dropdown on page load
	if ($('#asana_project_wrapper').length) {
		loadAsanaProjects();
	}

	// ========================================
	// ASANA AUTOCOMPLETE FOR DESCRIPTION
	// ========================================

	var asanaAutocompleteTimeout = null;
	var asanaDropdown = null;
	var asanaEnabled = false;
	var asanaIsLoading = false;

	// Create autocomplete dropdown
	function initAsanaAutocomplete() {
		var descWrapper = $('#capturetime .description-wrapper');
		if (!descWrapper.length) {
			return;
		}

		// Create dropdown if not exists
		if (!$('#asana-autocomplete-dropdown').length) {
			descWrapper.css('position', 'relative');
			descWrapper.append('<div id="asana-autocomplete-dropdown"></div>');
		}
		asanaDropdown = $('#asana-autocomplete-dropdown');
		asanaEnabled = true;
	}

	// Show/hide loading indicator
	function setAsanaLoading(loading) {
		asanaIsLoading = loading;
		if (loading) {
			$('#asana-loading').show();
			$('#asana-tasks-btn').hide();
		} else {
			$('#asana-loading').hide();
			$('#asana-tasks-btn').show();
		}
	}

	// Search Asana tasks
	function searchAsanaTasks(searchText, clientId, showAll) {
		if (!asanaEnabled) {
			initAsanaAutocomplete();
		}

		setAsanaLoading(true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_search_asana_tasks',
				search_text: searchText || '',
				client_id: clientId
			},
			success: function (response) {
				setAsanaLoading(false);
				if (response.success && response.data && response.data.length > 0) {
					showAsanaDropdown(response.data, showAll);
				} else {
					if (showAll) {
						showAsanaDropdown([], showAll);
					} else {
						if (asanaDropdown) asanaDropdown.hide();
					}
				}
			},
			error: function () {
				setAsanaLoading(false);
				if (asanaDropdown) asanaDropdown.hide();
			}
		});
	}

	// Show autocomplete dropdown
	function showAsanaDropdown(tasks, showAll) {
		if (!asanaDropdown || !asanaDropdown.length) {
			initAsanaAutocomplete();
			asanaDropdown = $('#asana-autocomplete-dropdown');
		}

		if (!asanaDropdown || !asanaDropdown.length) return;

		var html = '';

		if (tasks.length === 0) {
			html = '<div class="asana-no-results">Keine Aufgaben gefunden</div>';
		} else {
			tasks.forEach(function (task) {
				var escapedName = $('<div>').text(task.name).html();
				var escapedUrl = $('<div>').text(task.url || '').html();
				html += '<div class="asana-task-item" data-name="' + escapedName + '" data-url="' + escapedUrl + '">';
				html += escapedName;
				html += '</div>';
			});
		}

		asanaDropdown.html(html).show();

		if (showAll) {
			asanaDropdown.addClass('show-all');
		} else {
			asanaDropdown.removeClass('show-all');
		}
	}

	// On description input - trigger autocomplete
	$(document).on('input', '#capturetime #description', function () {
		var searchText = $(this).val();
		var clientId = $('#dbresults a.current').data('id') || 0;

		// Clear previous timeout
		if (asanaAutocompleteTimeout) {
			clearTimeout(asanaAutocompleteTimeout);
		}

		// Hide dropdown if less than 2 characters
		if (searchText.length < 2) {
			if (asanaDropdown) asanaDropdown.hide();
			return;
		}

		// Debounce - wait 300ms before searching
		asanaAutocompleteTimeout = setTimeout(function () {
			searchAsanaTasks(searchText, clientId, false);
		}, 300);
	});

	// Manual button to show all tasks
	$(document).on('click', '#asana-tasks-btn', function (e) {
		e.preventDefault();
		var clientId = $('#dbresults a.current').data('id') || 0;

		if (!clientId) {
			toastr.info('Bitte zuerst einen Kunden auswählen.');
			return;
		}

		// Toggle dropdown
		if (asanaDropdown && asanaDropdown.is(':visible') && asanaDropdown.hasClass('show-all')) {
			asanaDropdown.hide();
		} else {
			searchAsanaTasks('', clientId, true);
		}
	});

	// Clean up Asana task name (remove 2-3 letter prefix codes like "WIT ", "AB ", etc.)
	function cleanAsanaTaskName(name) {
		if (!name) return name;
		// Remove 2-3 uppercase letters followed by space(s) at the beginning
		return name.replace(/^[A-Z]{2,3}\s+/, '');
	}

	// Handle task selection
	$(document).on('click', '.asana-task-item', function () {
		var taskName = $(this).attr('data-name');
		var taskUrl = $(this).attr('data-url');

		// Clean up the task name
		taskName = cleanAsanaTaskName(taskName);

		$('#capturetime #description').val(taskName);
		$('#capturetime #linknotice').val(taskUrl);
		if (asanaDropdown) asanaDropdown.hide();
	});

	// Hide dropdown when clicking outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('#description, #asana-autocomplete-dropdown, #asana-tasks-btn').length) {
			if (asanaDropdown) asanaDropdown.hide();
		}
	});

	// Hide on Escape key
	$(document).on('keydown', '#capturetime #description', function (e) {
		if (e.key === 'Escape') {
			if (asanaDropdown) asanaDropdown.hide();
		}
	});

	// Initialize autocomplete on document ready
	initAsanaAutocomplete();

	// ========================================
	// RECURRING INVOICES
	// ========================================

	var recurringCategories = [];

	// Load recurring data
	function loadRecurring() {
		if (!$('#recurring-dashboard').length) return;

		$('#recurring-loading').show();

		var clientFilter = $('#filter-recurring-client').val();
		var categoryFilter = $('#filter-recurring-category').val();
		var statusFilter = $('#filter-recurring-status').val();

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_recurring',
				client_id: clientFilter,
				category_id: categoryFilter,
				status: statusFilter
			},
			success: function (response) {
				$('#recurring-loading').hide();
				if (response.success) {
					renderRecurring(response.data);
					// Populate partner filter
					var partners = {};
					var allItems = (response.data.due || []).concat(response.data.active || []).concat(response.data.other || []);
					allItems.forEach(function (item) {
						if (item.partner) partners[item.partner] = true;
					});
					var $pf = $('#filter-recurring-partner');
					var curVal = $pf.val();
					$pf.html('<option value="">Alle Partner</option>');
					Object.keys(partners).sort().forEach(function (p) {
						$pf.append('<option value="' + escAttr(p) + '">' + escHtml(p) + '</option>');
					});
					if (curVal) $pf.val(curVal);
					// Apply partner filter after render
					ppApplyRecurringPartnerFilter();
				}
			},
			error: function () {
				$('#recurring-loading').hide();
			}
		});
	}

	// Load categories
	function loadRecurringCategories(callback) {
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_recurring_categories'
			},
			success: function (response) {
				if (response.success) {
					recurringCategories = response.data;
					populateCategoryDropdowns();
					if (callback) callback();
				}
			}
		});
	}

	// Populate category dropdowns
	function populateCategoryDropdowns() {
		var filterHtml = '<option value="">Alle Kategorien</option>';
		var formHtml = '<option value="">Bitte wählen</option>';

		recurringCategories.forEach(function (cat) {
			filterHtml += '<option value="' + cat.id + '">' + cat.title + '</option>';
			formHtml += '<option value="' + cat.id + '">' + cat.title + '</option>';
		});

		$('#filter-recurring-category').html(filterHtml);
		$('#recurring-category').html(formHtml);
	}

	// Render recurring as table
	function renderRecurring(data) {
		$('#due-count').text(data.due_count);
		$('#monthly-total').text(formatCurrency(data.monthly_total));
		$('#monthly-waiting').text(formatCurrency(data.monthly_waiting || 0));

		// Combine all items into one table
		var allItems = (data.due || []).concat(data.active || []).concat(data.other || []);

		// Hide old sections, use active-list for the table
		$('#recurring-due-section').hide();
		$('#recurring-other-section').hide();

		if (!allItems.length) {
			$('#recurring-active-list').html('<div class="no-items">Keine Einträge</div>');
			return;
		}

		var html = '<table class="rec-table"><thead><tr>';
		html += '<th style="width:4px;"></th>';
		html += '<th>Titel</th>';
		html += '<th>Kunde</th>';
		html += '<th>Partner</th>';
		html += '<th>Kategorie</th>';
		html += '<th style="text-align:right;">Betrag</th>';
		html += '<th>Intervall</th>';
		html += '<th>Nächste</th>';
		html += '<th>Zuletzt</th>';
		html += '<th>Status</th>';
		html += '<th style="width:80px;"></th>';
		html += '</tr></thead><tbody>';

		var partnerFilter = $('#filter-recurring-partner').val() || '';

		allItems.forEach(function (item) {
			// Client-side partner filter
			if (partnerFilter && (item.partner || '') !== partnerFilter) return;
			var intervalText = { monthly: 'Mtl.', quarterly: 'Qtl.', yearly: 'Jährl.' }[item.billing_interval] || item.billing_interval;
			var isDue = item.is_due;
			var statusLabel = { active: 'Aktiv', waiting: 'Wartend', paused: 'Pausiert', cancelled: 'Beendet' }[item.status] || item.status;
			var statusCls = 'rec-st-' + item.status;
			var rowCls = isDue ? ' rec-row-due' : (item.status !== 'active' ? ' rec-row-inactive' : '');

			html += '<tr class="recurring-item' + rowCls + '" data-id="' + item.id + '" data-partner="' + escAttr(item.partner || '') + '">';
			html += '<td style="background:' + (item.client_color || '#999') + ';padding:0;width:4px;"></td>';
			html += '<td class="rec-td-title"><strong>' + escHtml(item.title) + '</strong></td>';
			html += '<td class="rec-td-client">' + escHtml(item.client_name || '') + '</td>';
			html += '<td class="rec-td-partner">' + escHtml(item.partner || '') + '</td>';
			html += '<td class="rec-td-cat">' + escHtml(item.category_name || '-') + '</td>';
			html += '<td class="rec-td-amount">' + formatCurrency(item.amount) + '</td>';
			html += '<td class="rec-td-interval">' + intervalText + '</td>';
			html += '<td class="rec-td-next' + (isDue ? ' rec-due' : '') + '">' + formatDateDE(item.next_billing) + '</td>';
			html += '<td class="rec-td-last">' + (item.last_billed ? formatDateDE(item.last_billed) : '-') + '</td>';
			html += '<td><span class="rec-status ' + statusCls + '">' + statusLabel + '</span></td>';
			html += '<td class="rec-td-actions">';
			if (item.status === 'active') html += '<button type="button" class="btn-bill" data-id="' + item.id + '" title="Abgerechnet"><i class="bx bx-check"></i></button>';
			html += '<button type="button" class="btn-log" data-id="' + item.id + '" title="Historie"><i class="bx bx-history"></i></button>';
			html += '<button type="button" class="btn-edit" data-id="' + item.id + '" title="Bearbeiten"><i class="bx bx-edit"></i></button>';
			html += '<button type="button" class="btn-delete" data-id="' + item.id + '" title="Löschen"><i class="bx bx-trash"></i></button>';
			html += '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#recurring-active-list').html(html);
	}

	// Keep for backward compat
	function renderRecurringList(items, isDue) { return ''; }

	// Format date to German format
	function formatDateDE(dateStr) {
		if (!dateStr) return '-';
		var d = new Date(dateStr);
		return d.toLocaleDateString('de-DE');
	}

	// Open add/edit modal
	function openRecurringModal(item) {
		var modal = $('#modal-recurring');
		var isEdit = item && item.id;

		$('#modal-recurring-title').text(isEdit ? 'Rechnung bearbeiten' : 'Neue wiederkehrende Rechnung');

		// Reset or populate form
		$('#recurring-id').val(isEdit ? item.id : 0);
		$('#recurring-client').val(isEdit ? item.client_id : '');
		$('#recurring-title').val(isEdit ? item.title : '');
		$('#recurring-partner').val(isEdit ? (item.partner || '') : '');
		// Populate partner datalist
		var partnerVals = {};
		$('.recurring-item').each(function () { var p = $(this).attr('data-partner'); if (p) partnerVals[p] = true; });
		$('#recurring-partner-list').html('');
		Object.keys(partnerVals).sort().forEach(function (p) { $('#recurring-partner-list').append('<option value="' + p + '">'); });
		// Default category: find "Wartung" if exists
		var defaultCat = '';
		if (!isEdit) {
			$('#recurring-category option').each(function () {
				if ($(this).text().toLowerCase().indexOf('wartung') > -1) defaultCat = $(this).val();
			});
		}
		$('#recurring-category').val(isEdit ? item.category_id : defaultCat);
		$('#recurring-status').val(isEdit ? item.status : 'waiting');
		$('#recurring-amount').val(isEdit ? item.amount : 50);
		$('#recurring-interval').val(isEdit ? item.billing_interval : 'monthly');
		$('#recurring-description').val(isEdit ? (item.description || '') : '');
		$('#recurring-start').val(isEdit ? item.start_date : '');
		$('#recurring-end').val(isEdit && item.end_date ? item.end_date : '');
		$('#recurring-next').val(isEdit ? item.next_billing : '');

		modal.show();
	}

	// Close modals
	$(document).on('click', '.recurring-modal .modal-close, .recurring-modal .modal-overlay', function () {
		$(this).closest('.recurring-modal').hide();
	});

	// Add new recurring
	$(document).on('click', '#add-recurring-btn', function () {
		openRecurringModal(null);
	});

	// Edit recurring
	$(document).on('click', '.recurring-item .btn-edit', function () {
		var id = $(this).data('id');
		var item = $(this).closest('.recurring-item');

		// Get full data via AJAX (simpler approach: store in data attributes or fetch)
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_recurring',
				status: ''
			},
			success: function (response) {
				if (response.success) {
					var allItems = response.data.due.concat(response.data.active, response.data.other);
					var foundItem = allItems.find(function (i) { return i.id == id; });
					if (foundItem) {
						openRecurringModal(foundItem);
					}
				}
			}
		});
	});

	// Save recurring
	$(document).on('submit', '#form-recurring', function (e) {
		e.preventDefault();

		var btn = $(this).find('button[type="submit"]');
		btn.prop('disabled', true);

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_save_recurring',
				recurring_id: $('#recurring-id').val(),
				client_id: $('#recurring-client').val(),
				category_id: $('#recurring-category').val(),
				title: $('#recurring-title').val(),
				description: $('#recurring-description').val(),
				partner: $('#recurring-partner').val(),
				amount: $('#recurring-amount').val(),
				billing_interval: $('#recurring-interval').val(),
				start_date: $('#recurring-start').val(),
				end_date: $('#recurring-end').val(),
				next_billing: $('#recurring-next').val(),
				status: $('#recurring-status').val()
			},
			success: function (response) {
				btn.prop('disabled', false);
				if (response.success) {
					$('#modal-recurring').hide();
					toastr.success('Gespeichert');
					loadRecurring();
				} else {
					toastr.error(response.data || 'Fehler');
				}
			},
			error: function () {
				btn.prop('disabled', false);
				toastr.error('Fehler');
			}
		});
	});

	// Delete recurring
	$(document).on('click', '.recurring-item .btn-delete', function () {
		var id = $(this).data('id');
		var item = $(this).closest('.recurring-item');

		Swal.fire({
			title: 'Löschen?',
			text: 'Eintrag und Historie werden gelöscht.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Ja, löschen'
		}).then(function (result) {
			if (result.isConfirmed) {
				item.slideUp();
				$.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_delete_recurring',
						recurring_id: id
					},
					success: function () {
						toastr.success('Gelöscht');
						loadRecurring();
					}
				});
			}
		});
	});

	// Mark as billed
	$(document).on('click', '.recurring-item .btn-bill', function () {
		var id = $(this).data('id');
		var btn = $(this);

		btn.prop('disabled', true);

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_mark_recurring_billed',
				recurring_id: id,
				notes: ''
			},
			success: function (response) {
				btn.prop('disabled', false);
				if (response.success) {
					toastr.success('Als abgerechnet markiert');
					loadRecurring();
				}
			},
			error: function () {
				btn.prop('disabled', false);
			}
		});
	});

	// Show log
	$(document).on('click', '.recurring-item .btn-log', function () {
		var id = $(this).data('id');

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_recurring_log',
				recurring_id: id
			},
			success: function (response) {
				if (response.success) {
					var html = '';
					if (response.data.length === 0) {
						html = '<div class="no-items">Noch keine Abrechnungen</div>';
					} else {
						html = '<table class="log-table"><thead><tr><th>Datum</th><th>Betrag</th><th>Notiz</th></tr></thead><tbody>';
						response.data.forEach(function (log) {
							html += '<tr>';
							html += '<td>' + formatDateDE(log.billed_date) + '</td>';
							html += '<td>' + formatCurrency(log.amount) + '</td>';
							html += '<td>' + (log.notes || '-') + '</td>';
							html += '</tr>';
						});
						html += '</tbody></table>';
					}
					$('#log-list').html(html);
					$('#modal-log').show();
				}
			}
		});
	});

	// Manage categories
	$(document).on('click', '#manage-categories-btn', function () {
		renderCategoriesList();
		$('#modal-categories').show();
	});

	function renderCategoriesList() {
		var html = '';
		if (recurringCategories.length === 0) {
			html = '<div class="no-items">Keine Kategorien</div>';
		} else {
			recurringCategories.forEach(function (cat) {
				html += '<div class="category-item" data-id="' + cat.id + '">';
				html += '<span class="category-title">' + cat.title + '</span>';
				html += '<button type="button" class="btn-delete-category" data-id="' + cat.id + '"><i class="bx bx-trash"></i></button>';
				html += '</div>';
			});
		}
		$('#categories-list').html(html);
	}

	// Add category
	$(document).on('click', '#add-category-btn', function () {
		var input = $('#new-category-name');
		var title = input.val().trim();
		if (!title) return;

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_save_recurring_category',
				title: title
			},
			success: function (response) {
				if (response.success) {
					input.val('');
					loadRecurringCategories(function () {
						renderCategoriesList();
					});
					toastr.success('Kategorie erstellt');
				}
			}
		});
	});

	// Quick add category from form
	$(document).on('click', '#quick-add-category', function () {
		var title = prompt('Neue Kategorie:');
		if (!title) return;

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_save_recurring_category',
				title: title
			},
			success: function (response) {
				if (response.success) {
					loadRecurringCategories(function () {
						$('#recurring-category').val(response.data.id);
					});
					toastr.success('Kategorie erstellt');
				}
			}
		});
	});

	// Delete category
	$(document).on('click', '.btn-delete-category', function () {
		var id = $(this).data('id');
		var item = $(this).closest('.category-item');

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_delete_recurring_category',
				category_id: id
			},
			success: function (response) {
				if (response.success) {
					item.slideUp(function () { $(this).remove(); });
					loadRecurringCategories();
					toastr.success('Gelöscht');
				} else {
					toastr.error(response.data || 'Fehler');
				}
			}
		});
	});

	// Filter change
	$(document).on('change', '#filter-recurring-client, #filter-recurring-category, #filter-recurring-status, #filter-recurring-partner', function () {
		loadRecurring();
	});

	// Right-click context menu on recurring items
	$(document).on('contextmenu', '.recurring-item', function (e) {
		e.preventDefault();
		$('.pp-row-context').remove();

		var $item = $(this);
		var itemId = $item.data('id');
		// Read status from the status badge text
		var statusBadge = $item.find('.rec-status').text().trim().toLowerCase();
		var currentStatus = statusBadge === 'wartend' ? 'waiting' : (statusBadge === 'pausiert' ? 'paused' : (statusBadge === 'beendet' ? 'cancelled' : 'active'));

		var menu = '<div class="pp-row-context" data-recid="' + itemId + '">';
		menu += '<div class="pp-rctx-item" data-action="rec-status" data-val="active"><i class="bx bx-play-circle"></i> Aktiv' + (currentStatus === 'active' ? ' ✓' : '') + '</div>';
		menu += '<div class="pp-rctx-item" data-action="rec-status" data-val="waiting"><i class="bx bx-time"></i> Wartend' + (currentStatus === 'waiting' ? ' ✓' : '') + '</div>';
		menu += '<div class="pp-rctx-item" data-action="rec-status" data-val="paused"><i class="bx bx-pause-circle"></i> Pausiert' + (currentStatus === 'paused' ? ' ✓' : '') + '</div>';
		menu += '<div class="pp-rctx-item" data-action="rec-status" data-val="cancelled"><i class="bx bx-stop-circle"></i> Beendet' + (currentStatus === 'cancelled' ? ' ✓' : '') + '</div>';
		menu += '<div class="pp-rctx-sep"></div>';
		menu += '<div class="pp-rctx-item" data-action="rec-category"><i class="bx bx-category"></i> Kategorie ändern</div>';
		menu += '</div>';

		$(menu).appendTo('body').css({ position: 'fixed', top: e.clientY, left: e.clientX, zIndex: 99999 });
	});

	// Quick status change for recurring
	$(document).on('click', '.pp-rctx-item[data-action="rec-status"]', function () {
		var status = $(this).data('val');
		var id = $(this).closest('.pp-row-context').data('recid');
		$('.pp-row-context').remove();
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_quick_update_recurring', id: id, field: 'status', value: status },
			success: function (res) { if (res.success) { loadRecurring(); toastr.success('Status geändert'); } }
		});
	});

	// Quick category change for recurring
	$(document).on('click', '.pp-rctx-item[data-action="rec-category"]', function () {
		var id = $(this).closest('.pp-row-context').data('recid');
		$('.pp-row-context').remove();
		// Build category options from loaded data
		var catHtml = '<option value="0">Keine</option>';
		$('#filter-recurring-category option').each(function () {
			if ($(this).val()) catHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
		});
		Swal.fire({
			title: 'Kategorie ändern',
			html: '<select id="swal-rec-cat" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;font-size:13px;">' + catHtml + '</select>',
			showCancelButton: true, confirmButtonText: 'Speichern', cancelButtonText: 'Abbrechen', width: 340,
			preConfirm: function () { return document.getElementById('swal-rec-cat').value; }
		}).then(function (result) {
			if (result.isConfirmed) {
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_quick_update_recurring', id: id, field: 'category_id', value: result.value },
					success: function (res) { if (res.success) { loadRecurring(); toastr.success('Kategorie geändert'); } }
				});
			}
		});
	});

	// Add partner field to recurring item rendering
	var origRenderRecurringList = typeof renderRecurringList === 'function' ? renderRecurringList : null;

	// Partner filter - reload to apply
	$(document).on('change', '#filter-recurring-partner', function () {
		ppApplyRecurringPartnerFilter();
	});

	function ppApplyRecurringPartnerFilter() {
		var filter = $('#filter-recurring-partner').val();
		if (!filter) {
			$('tr.recurring-item').show();
			return;
		}
		$('tr.recurring-item').each(function () {
			var partner = $(this).attr('data-partner') || '';
			$(this).toggle(partner === filter);
		});
	}

	// Initialize recurring on page load
	if ($('#recurring-dashboard').length) {
		loadRecurringCategories(function () {
			loadRecurring();
		});
	}

	// ========================================
	// WEEKLY PLANNER
	// ========================================

	var plannerWeekStart = null;
	var plannerData = null;
	var draggedTask = null;

	// German day names
	var dayNamesShort = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
	var dayNamesFull = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

	// Get Monday of current week
	function getMondayOfWeek(date) {
		var d = new Date(date);
		var day = d.getDay();
		var diff = d.getDate() - day + (day === 0 ? -6 : 1);
		return new Date(d.setDate(diff));
	}

	// Format date as YYYY-MM-DD
	function formatDateISO(date) {
		var d = new Date(date);
		return d.getFullYear() + '-' +
			String(d.getMonth() + 1).padStart(2, '0') + '-' +
			String(d.getDate()).padStart(2, '0');
	}

	// Format date as DD.MM.
	function formatDateShort(date) {
		var d = new Date(date);
		return String(d.getDate()).padStart(2, '0') + '.' +
			String(d.getMonth() + 1).padStart(2, '0') + '.';
	}

	// Get week number
	function getWeekNumber(date) {
		var d = new Date(date);
		d.setHours(0, 0, 0, 0);
		d.setDate(d.getDate() + 4 - (d.getDay() || 7));
		var yearStart = new Date(d.getFullYear(), 0, 1);
		return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
	}

	// Load tasks for week
	function loadPlannerTasks() {
		$('#planner-loading').show();

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_tasks',
				week_start: plannerWeekStart,
				include_backlog: true
			},
			success: function (response) {
				$('#planner-loading').hide();
				if (response.success) {
					plannerData = response.data;
					renderPlanner();
					updatePlannerStats();
				} else {
					toastr.error('Fehler beim Laden');
				}
			},
			error: function () {
				$('#planner-loading').hide();
				toastr.error('Verbindungsfehler');
			}
		});
	}

	// Render planner UI
	function renderPlanner() {
		if (!plannerData) return;

		// Update week label
		var weekEnd = new Date(plannerWeekStart);
		weekEnd.setDate(weekEnd.getDate() + 6);
		var weekNum = getWeekNumber(plannerWeekStart);
		$('#planner-week-range').html('KW ' + weekNum + ' <span class="week-dates">(' + formatDateShort(plannerWeekStart) + ' - ' + formatDateShort(weekEnd) + ')</span>');

		// Render days
		var daysHtml = '';
		var today = formatDateISO(new Date());

		plannerData.days.forEach(function (day, index) {
			var dayDate = new Date(day.date);
			var dayName = dayNamesFull[dayDate.getDay()];
			var isToday = day.date === today;
			var isWeekend = dayDate.getDay() === 0 || dayDate.getDay() === 6;

			daysHtml += '<div class="planner-day' + (isToday ? ' is-today' : '') + (isWeekend ? ' is-weekend' : '') + '" data-date="' + day.date + '">';
			daysHtml += '<div class="day-header">';
			daysHtml += '<span class="day-name">' + dayName + '</span>';
			daysHtml += '<span class="day-date">' + formatDateShort(day.date) + '</span>';
			if (isToday) {
				daysHtml += '<span class="today-badge">Heute</span>';
			}
			daysHtml += '</div>';
			daysHtml += '<div class="day-tasks" data-date="' + day.date + '">';

			// Sort tasks: pending first, completed at bottom
			var sortedTasks = day.tasks.slice().sort(function(a, b) {
				if (a.status === 'completed' && b.status !== 'completed') return 1;
				if (a.status !== 'completed' && b.status === 'completed') return -1;
				return 0;
			});

			sortedTasks.forEach(function (task) {
				daysHtml += renderTaskCard(task);
			});

			daysHtml += '</div>';
			daysHtml += '<button type="button" class="add-task-day" data-date="' + day.date + '"><i class="bx bx-plus"></i></button>';
			daysHtml += '</div>';
		});

		$('#planner-days').html(daysHtml);

		// Render backlog
		var backlogHtml = '';
		plannerData.backlog.forEach(function (task) {
			backlogHtml += renderTaskCard(task);
		});
		if (plannerData.backlog.length === 0) {
			backlogHtml = '<div class="empty-backlog">Keine ungeplanten Aufgaben</div>';
		}
		$('#backlog-tasks').html(backlogHtml);

		// Initialize drag and drop
		initDragAndDrop();
	}

	// Render single task card
	function renderTaskCard(task) {
		var isCompleted = task.status === 'completed';
		var isRecurring = task.is_recurring == 1;
		var hours = parseFloat(task.estimated_hours) || 0;

		var html = '<div class="task-card' + (isCompleted ? ' completed' : '') + '" data-id="' + task.id + '" draggable="true">';
		html += '<div class="task-main">';
		html += '<button type="button" class="task-check' + (isCompleted ? ' checked' : '') + '" data-id="' + task.id + '">';
		html += '<i class="bx bx-check"></i>';
		html += '</button>';
		html += '<div class="task-content">';
		html += '<div class="task-title">' + escapeHtml(task.title) + '</div>';
		if (task.description) {
			html += '<div class="task-desc">' + escapeHtml(task.description).substring(0, 50) + '</div>';
		}
		html += '</div>';
		html += '</div>';
		html += '<div class="task-meta">';
		if (hours > 0) {
			html += '<span class="task-hours"><i class="bx bx-time-five"></i> ' + hours + 'h</span>';
		}
		if (isRecurring) {
			html += '<span class="task-recurring" title="Wiederkehrend"><i class="bx bx-refresh"></i></span>';
		}
		html += '<button type="button" class="task-edit" data-id="' + task.id + '"><i class="bx bx-pencil"></i></button>';
		html += '</div>';
		html += '</div>';

		return html;
	}

	// Update stats
	function updatePlannerStats() {
		if (!plannerData) return;

		// Count completed and open tasks from week
		var completedCount = 0;
		var openCount = 0;
		plannerData.days.forEach(function(day) {
			day.tasks.forEach(function(task) {
				if (task.status === 'completed') {
					completedCount++;
				} else {
					openCount++;
				}
			});
		});

		$('#stat-completed-count').text(completedCount);
		$('#stat-open-count').text(openCount);
		$('#stat-backlog-count').text(plannerData.stats.backlog_count);
	}

	// Motivating quotes
	var plannerQuotes = [
		"Plane deine Woche, bevor sie dich plant.",
		"Jede erledigte Aufgabe ist ein kleiner Sieg.",
		"Ein guter Plan heute ist besser als ein perfekter Plan morgen.",
		"Produktivität beginnt mit Klarheit.",
		"Schritt für Schritt zum Ziel.",
		"Fokus schlägt Multitasking.",
		"Die beste Zeit anzufangen ist jetzt.",
		"Kleine Schritte führen zu großen Ergebnissen.",
		"Erledigt ist besser als perfekt.",
		"Wer plant, gewinnt Zeit.",
		"Deine Zukunft wird von dem bestimmt, was du heute tust.",
		"Erfolg ist die Summe kleiner Anstrengungen.",
		"Mach es einfach. Mach es jetzt.",
		"Prioritäten setzen heißt Nein sagen können.",
		"Jeder Tag ist eine neue Chance.",
		"Weniger planen, mehr machen.",
		"Der Weg zum Erfolg beginnt mit Organisation.",
		"Zeit ist deine wertvollste Ressource.",
		"Ordnung im Kopf beginnt mit Ordnung im Plan.",
		"Du schaffst das!"
	];

	function showRandomQuote() {
		var quote = plannerQuotes[Math.floor(Math.random() * plannerQuotes.length)];
		$('#planner-quote-text').text(quote);
	}

	// Escape HTML
	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Initialize Drag and Drop
	function initDragAndDrop() {
		var taskCards = document.querySelectorAll('.task-card');
		var dropZones = document.querySelectorAll('.day-tasks, #backlog-tasks');
		var trashZone = document.getElementById('planner-trash');

		taskCards.forEach(function (card) {
			card.addEventListener('dragstart', handleDragStart);
			card.addEventListener('dragend', handleDragEnd);
		});

		dropZones.forEach(function (zone) {
			zone.addEventListener('dragover', handleDragOver);
			zone.addEventListener('dragleave', handleDragLeave);
			zone.addEventListener('drop', handleDrop);
		});

		// Trash zone events
		if (trashZone) {
			trashZone.addEventListener('dragover', handleTrashDragOver);
			trashZone.addEventListener('dragleave', handleTrashDragLeave);
			trashZone.addEventListener('drop', handleTrashDrop);
		}
	}

	function handleDragStart(e) {
		draggedTask = this;
		this.classList.add('dragging');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', this.dataset.id);
		// Show trash zone
		$('#planner-trash').addClass('visible');
	}

	function handleDragEnd(e) {
		this.classList.remove('dragging');
		document.querySelectorAll('.day-tasks, #backlog-tasks').forEach(function (zone) {
			zone.classList.remove('drag-over');
		});
		// Hide trash zone
		$('#planner-trash').removeClass('visible drag-over');
		draggedTask = null;
	}

	function handleTrashDragOver(e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		this.classList.add('drag-over');
	}

	function handleTrashDragLeave(e) {
		this.classList.remove('drag-over');
	}

	function handleTrashDrop(e) {
		e.preventDefault();
		this.classList.remove('drag-over');

		if (!draggedTask) return;

		var taskId = draggedTask.dataset.id;
		var taskCard = draggedTask;

		// Delete task
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_delete_task',
				task_id: taskId
			},
			success: function (response) {
				if (response.success) {
					$(taskCard).fadeOut(200, function () {
						$(this).remove();
					});
					toastr.success('Aufgabe gelöscht');
					// Update stats
					loadPlannerTasks();
				} else {
					toastr.error('Fehler beim Löschen');
				}
			}
		});

		$('#planner-trash').removeClass('visible');
		draggedTask = null;
	}

	function handleDragOver(e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		this.classList.add('drag-over');
	}

	function handleDragLeave(e) {
		this.classList.remove('drag-over');
	}

	function handleDrop(e) {
		e.preventDefault();
		this.classList.remove('drag-over');

		if (!draggedTask) return;

		var taskId = draggedTask.dataset.id;
		var newDate = this.dataset.date || null;
		var targetZone = this;

		// Move task visually
		targetZone.appendChild(draggedTask);

		// Remove empty backlog message if exists
		var emptyMsg = targetZone.querySelector('.empty-backlog');
		if (emptyMsg) emptyMsg.remove();

		// Calculate new position
		var cards = targetZone.querySelectorAll('.task-card');
		var newPosition = Array.from(cards).indexOf(draggedTask);

		// Save to server
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_move_task',
				task_id: taskId,
				new_date: newDate,
				new_position: newPosition
			},
			success: function (response) {
				if (!response.success) {
					toastr.error('Fehler beim Verschieben');
					loadPlannerTasks(); // Reload on error
				}
			}
		});

		// Update backlog count
		var backlogCount = $('#backlog-tasks .task-card').length;
		$('#stat-backlog-count').text(backlogCount);
	}

	// Navigation
	$(document).on('click', '#planner-prev-week', function () {
		var d = new Date(plannerWeekStart);
		d.setDate(d.getDate() - 7);
		plannerWeekStart = formatDateISO(d);
		loadPlannerTasks();
	});

	$(document).on('click', '#planner-next-week', function () {
		var d = new Date(plannerWeekStart);
		d.setDate(d.getDate() + 7);
		plannerWeekStart = formatDateISO(d);
		loadPlannerTasks();
	});

	$(document).on('click', '#planner-today', function () {
		plannerWeekStart = formatDateISO(getMondayOfWeek(new Date()));
		loadPlannerTasks();
	});

	// Open task modal
	function openTaskModal(taskId, defaultDate) {
		var modal = $('#modal-task');
		$('#task-id').val(taskId || 0);
		$('#task-title').val('');
		$('#task-description').val('');
		$('#task-estimated').val('');
		$('#task-date').val(defaultDate || '');
		$('#task-date-hidden').val(defaultDate || '');
		$('#task-recurring').prop('checked', false);
		$('#recurring-options').hide();
		$('#task-recurring-interval').val('weekly');
		$('#delete-task-btn').toggle(taskId > 0);
		$('#modal-task-title').text(taskId ? 'Aufgabe bearbeiten' : 'Neue Aufgabe');

		if (taskId) {
			// Find task in data
			var task = findTaskById(taskId);
			if (task) {
				$('#task-title').val(task.title);
				$('#task-description').val(task.description || '');
				$('#task-estimated').val(task.estimated_hours || '');
				$('#task-date').val(task.planned_date || '');
				$('#task-recurring').prop('checked', task.is_recurring == 1);
				if (task.is_recurring == 1) {
					$('#recurring-options').show();
					$('#task-recurring-interval').val(task.recurring_interval || 'weekly');
				}
			}
		}

		modal.show();
		$('#task-title').focus();
	}

	// Find task by ID in plannerData
	function findTaskById(id) {
		if (!plannerData) return null;
		id = parseInt(id);

		for (var i = 0; i < plannerData.days.length; i++) {
			for (var j = 0; j < plannerData.days[i].tasks.length; j++) {
				if (parseInt(plannerData.days[i].tasks[j].id) === id) {
					return plannerData.days[i].tasks[j];
				}
			}
		}
		for (var k = 0; k < plannerData.backlog.length; k++) {
			if (parseInt(plannerData.backlog[k].id) === id) {
				return plannerData.backlog[k];
			}
		}
		return null;
	}

	// Add task button
	$(document).on('click', '#add-task-btn', function () {
		openTaskModal(0, '');
	});

	// Add task to specific day (button click)
	$(document).on('click', '.add-task-day', function () {
		var date = $(this).data('date');
		openTaskModal(0, date);
	});

	// Add task by double-clicking on day area
	$(document).on('dblclick', '.day-tasks', function (e) {
		// Only trigger if clicking on the day-tasks area itself, not on a task card
		if ($(e.target).closest('.task-card').length === 0) {
			var date = $(this).data('date');
			openTaskModal(0, date);
		}
	});

	// Add task by double-clicking on backlog area
	$(document).on('dblclick', '#backlog-tasks', function (e) {
		if ($(e.target).closest('.task-card').length === 0) {
			openTaskModal(0, '');
		}
	});

	// Edit task
	$(document).on('click', '.task-edit', function (e) {
		e.stopPropagation();
		var id = $(this).data('id');
		openTaskModal(id);
	});

	// Toggle recurring options
	$(document).on('change', '#task-recurring', function () {
		$('#recurring-options').toggle(this.checked);
	});

	// Close modals
	$(document).on('click', '.planner-modal .modal-close, .planner-modal .modal-overlay', function () {
		$(this).closest('.planner-modal').hide();
	});

	// Task title autocomplete
	var taskSearchTimeout = null;
	$(document).on('input', '#task-title', function () {
		var input = $(this);
		var search = input.val().trim();
		var dropdown = $('#task-autocomplete');

		// Remove existing dropdown
		if (dropdown.length === 0) {
			input.parent().css('position', 'relative');
			input.after('<div id="task-autocomplete" class="task-autocomplete-dropdown"></div>');
			dropdown = $('#task-autocomplete');
		}

		clearTimeout(taskSearchTimeout);

		if (search.length < 2) {
			dropdown.hide().empty();
			return;
		}

		taskSearchTimeout = setTimeout(function () {
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce,
					action: 'uf_search_task_titles',
					search: search
				},
				success: function (response) {
					if (response.success && response.data.length > 0) {
						var html = '';
						response.data.forEach(function (task) {
							var hours = task.estimated_hours ? ' <span class="ac-hours">' + task.estimated_hours + 'h</span>' : '';
							html += '<div class="ac-item" data-title="' + escapeHtml(task.title) + '" data-desc="' + escapeHtml(task.description || '') + '" data-hours="' + (task.estimated_hours || '') + '">';
							html += escapeHtml(task.title) + hours;
							html += '</div>';
						});
						dropdown.html(html).show();
					} else {
						dropdown.hide().empty();
					}
				}
			});
		}, 200);
	});

	// Select autocomplete item
	$(document).on('click', '.ac-item', function () {
		var title = $(this).data('title');
		var desc = $(this).data('desc');
		var hours = $(this).data('hours');

		$('#task-title').val(title);
		if (desc) $('#task-description').val(desc);
		if (hours) $('#task-estimated').val(hours);

		$('#task-autocomplete').hide().empty();
	});

	// Hide autocomplete on blur (with delay for click)
	$(document).on('blur', '#task-title', function () {
		setTimeout(function () {
			$('#task-autocomplete').hide();
		}, 200);
	});

	// Save task
	$(document).on('submit', '#form-task', function (e) {
		e.preventDefault();

		var taskId = $('#task-id').val();
		var data = {
			security: ajaxuser.nonce,
			action: 'uf_save_task',
			task_id: taskId,
			title: $('#task-title').val().trim(),
			description: $('#task-description').val().trim(),
			estimated_hours: $('#task-estimated').val() || 0,
			planned_date: $('#task-date').val() || null,
			is_recurring: $('#task-recurring').is(':checked') ? 1 : 0,
			recurring_interval: $('#task-recurring-interval').val()
		};

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: data,
			success: function (response) {
				if (response.success) {
					$('#modal-task').hide();
					loadPlannerTasks();
					toastr.success(taskId > 0 ? 'Aufgabe aktualisiert' : 'Aufgabe erstellt');
				} else {
					toastr.error(response.data || 'Fehler');
				}
			}
		});
	});

	// Delete task
	$(document).on('click', '#delete-task-btn', function () {
		if (!confirm('Aufgabe wirklich löschen?')) return;

		var taskId = $('#task-id').val();

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_delete_task',
				task_id: taskId
			},
			success: function (response) {
				if (response.success) {
					$('#modal-task').hide();
					loadPlannerTasks();
					toastr.success('Aufgabe gelöscht');
				} else {
					toastr.error('Fehler beim Löschen');
				}
			}
		});
	});

	// Task check (complete/uncomplete)
	$(document).on('click', '.task-check', function (e) {
		e.stopPropagation();
		var taskId = $(this).data('id');
		var task = findTaskById(taskId);
		var isCompleted = $(this).hasClass('checked');

		if (isCompleted) {
			// Uncomplete
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce,
					action: 'uf_uncomplete_task',
					task_id: taskId
				},
				success: function (response) {
					if (response.success) {
						loadPlannerTasks();
					}
				}
			});
		} else {
			// Show completion modal
			$('#complete-task-id').val(taskId);
			$('#complete-task-summary').html('<strong>' + escapeHtml(task.title) + '</strong>');
			$('#complete-hours').val(task.estimated_hours || '0');
			$('#complete-create-entry').prop('checked', false);
			$('#time-entry-options').hide();
			$('#complete-client').val('').prop('required', false);
			$('#complete-project').html('<option value="0">Kein Projekt</option>');
			$('#modal-complete').show();
		}
	});

	// Toggle time entry options
	$(document).on('change', '#complete-create-entry', function () {
		$('#time-entry-options').toggle(this.checked);
		$('#complete-client').prop('required', this.checked);
	});

	// Load projects when client changes
	$(document).on('change', '#complete-client', function () {
		var clientId = $(this).val();
		var projectSelect = $('#complete-project');
		projectSelect.html('<option value="0">Kein Projekt</option>');

		if (!clientId) return;

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_getprojectsjson',
				client_id: clientId
			},
			success: function (response) {
				if (response.success && response.data && response.data.length) {
					response.data.forEach(function (project) {
						projectSelect.append('<option value="' + project.id + '">' + project.title + '</option>');
					});
				}
			}
		});
	});

	// Complete task form submit
	$(document).on('submit', '#form-complete', function (e) {
		e.preventDefault();

		var createEntry = $('#complete-create-entry').is(':checked');
		var clientId = $('#complete-client').val();

		if (createEntry && !clientId) {
			toastr.error('Bitte Kunde auswählen');
			return;
		}

		var data = {
			security: ajaxuser.nonce,
			action: 'uf_complete_task',
			task_id: $('#complete-task-id').val(),
			actual_hours: $('#complete-hours').val() || 0,
			create_time_entry: createEntry ? 1 : 0,
			client_id: clientId || 0,
			project_id: $('#complete-project').val() || 0
		};

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: data,
			success: function (response) {
				if (response.success) {
					$('#modal-complete').hide();
					loadPlannerTasks();
					if (response.data.time_entry_id) {
						toastr.success('Erledigt! ' + response.data.hours_logged + 'h erfasst');
					} else {
						toastr.success('Erledigt!');
					}
				} else {
					toastr.error(response.data || 'Fehler');
				}
			}
		});
	});

	// Initialize planner on page load
	if ($('#planner-dashboard').length) {
		plannerWeekStart = formatDateISO(getMondayOfWeek(new Date()));
		loadPlannerTasks();
		showRandomQuote();
	}

	// ========================================
	// WEBSITE MONITOR
	// ========================================

	var monitorData = [];
	var monitorView = getCookie('tallyr_monitor_view') || 'grid';
	var monitorActiveCategory = getCookie('tallyr_monitor_cat') || '';
	var monitorLargeFont = getCookie('tallyr_monitor_font') === 'large';

	function getCookie(name) {
		var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
		return v ? v.pop() : '';
	}

	function setCookie(name, value) {
		document.cookie = name + '=' + value + ';path=/;max-age=31536000';
	}

	function loadMonitors() {
		$('#monitor-loading').show();
		$('#monitor-list').hide();

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitors',
			},
			success: function (result) {
				$('#monitor-loading').hide();
				$('#monitor-list').show();

				if (!result.success || !result.data.length) {
					$('#monitor-list').html('<div class="no-items">Noch keine Websites hinzugefügt.</div>');
					return;
				}

				monitorData = result.data;
				buildCategoryFilters();
				renderMonitors();
			},
			error: function () {
				$('#monitor-loading').hide();
				toastr.error('Fehler beim Laden der Monitors.');
			}
		});
	}

	function renderMonitors() {
		var search = $('#monitor-search').val().toLowerCase().trim();
		var filtered = monitorData;

		// Filter by category
		if (monitorActiveCategory === '__none__') {
			filtered = filtered.filter(function (m) {
				return !(m.category || '').trim();
			});
		} else if (monitorActiveCategory) {
			filtered = filtered.filter(function (m) {
				return (m.category || '') === monitorActiveCategory;
			});
		}

		if (search) {
			filtered = filtered.filter(function (m) {
				return (m.label && m.label.toLowerCase().indexOf(search) !== -1)
					|| (m.url && m.url.toLowerCase().indexOf(search) !== -1)
					|| (m.client_title && m.client_title.toLowerCase().indexOf(search) !== -1)
					|| (m.category && m.category.toLowerCase().indexOf(search) !== -1);
			});
		}

		// Hide bulk bar on re-render
		$('#monitor-bulk-bar').hide();

		// Update view toggle active state
		$('.monitor-view-btn').removeClass('active');
		$('.monitor-view-btn[data-view="' + monitorView + '"]').addClass('active');

		if (!filtered.length) {
			$('#monitor-list').html('<div class="no-items">' + (search ? 'Keine Treffer für "' + escHtml(search) + '".' : 'Noch keine Websites hinzugefügt.') + '</div>');
			return;
		}

		var html = '';

		if (monitorView === 'list') {
			html = renderMonitorList(filtered);
		} else {
			html = renderMonitorGrid(filtered);
		}

		$('#monitor-list').html(html);
	}

	function monitorCardParts(m) {
		var statusClass = m.status === 'up' ? 'status-up' : (m.status === 'paused' ? 'status-paused' : 'status-down');
		var statusLabel = m.status === 'up' ? 'Online' : (m.status === 'paused' ? 'Pausiert' : 'Offline');
		var uptimeColor = m.uptime_24h >= 99 ? '#388e3c' : (m.uptime_24h >= 95 ? '#f57c00' : '#d32f2f');
		var clientBadge = '';
		if (m.client_title) {
			clientBadge = '<span class="monitor-client" style="background:' + (m.client_color || '#eee') + '20;color:' + (m.client_color || '#777') + ';border:1px solid ' + (m.client_color || '#ddd') + '40;">' + escHtml(m.client_title) + '</span>';
		}
		var lastCheck = m.last_check ? monitorTimeAgo(m.last_check) : '–';
		var subCount = 0;
		if (m.sub_urls) {
			try { subCount = JSON.parse(m.sub_urls).length; } catch(e) {}
		}
		var subBadge = subCount > 0 ? '<span class="monitor-sub-badge">+' + subCount + ' Unterseiten</span>' : '';
		var catBadge = (m.category || '').trim() ? '<span class="monitor-cat-badge">' + escHtml(m.category) + '</span>' : '';
		var actions = '';
		actions += '<button class="monitor-btn-detail" data-id="' + m.id + '" title="Details"><i class="bx bx-bar-chart-alt-2"></i></button>';
		actions += '<button class="monitor-btn-edit" data-id="' + m.id + '" title="Bearbeiten"><i class="bx bx-edit"></i></button>';
		actions += '<button class="monitor-btn-toggle" data-id="' + m.id + '" title="' + (m.status === 'paused' ? 'Aktivieren' : 'Pausieren') + '"><i class="bx bx-' + (m.status === 'paused' ? 'play' : 'pause') + '"></i></button>';
		actions += '<button class="monitor-btn-delete" data-id="' + m.id + '" title="Löschen"><i class="bx bx-trash"></i></button>';
		return { statusClass: statusClass, statusLabel: statusLabel, uptimeColor: uptimeColor, clientBadge: clientBadge, subBadge: subBadge, catBadge: catBadge, lastCheck: lastCheck, actions: actions };
	}

	function renderMonitorGrid(items) {
		var html = '<div class="monitor-grid">';
		items.forEach(function (m) {
			var p = monitorCardParts(m);
			html += '<div class="monitor-card ' + p.statusClass + '" data-id="' + m.id + '">';
			html += '<div class="monitor-card-header"><label class="monitor-check-wrap" onclick="event.stopPropagation()"><input type="checkbox" class="monitor-bulk-check" value="' + m.id + '"></label><div class="monitor-status-dot"></div><span class="monitor-status-label">' + p.statusLabel + '</span>';
			html += '<div class="monitor-card-actions">' + p.actions + '</div></div>';
			html += '<div class="monitor-card-body" data-id="' + m.id + '">';
			html += '<div class="monitor-card-label">' + escHtml(m.label) + '</div>';
			html += '<div class="monitor-card-url">' + escHtml(m.url) + '</div>';
			html += '<div class="monitor-badges">' + p.clientBadge + p.catBadge + p.subBadge + '</div>';
			html += '</div>';
			html += '<div class="monitor-card-footer">';
			html += '<div class="monitor-stat"><span style="color:' + p.uptimeColor + ';font-weight:700;">' + m.uptime_24h + '%</span><small>Uptime 24h</small></div>';
			html += '<div class="monitor-stat"><span>' + m.avg_response + 'ms</span><small>Response</small></div>';
			html += '<div class="monitor-stat"><span>' + (m.last_status_code || '–') + '</span><small>Status</small></div>';
			html += '<div class="monitor-stat"><span>' + p.lastCheck + '</span><small>Letzter Check</small></div>';
			html += '</div></div>';
		});
		html += '</div>';
		return html;
	}

	function renderMonitorList(items) {
		var html = '<div class="monitor-list-view">';
		html += '<div class="monitor-list-header">';
		html += '<div class="ml-check"></div>';
		html += '<div class="ml-status">Status</div>';
		html += '<div class="ml-label">Website</div>';
		html += '<div class="ml-client">Kunde</div>';
		html += '<div class="ml-uptime">Uptime</div>';
		html += '<div class="ml-response">Response</div>';
		html += '<div class="ml-code">Code</div>';
		html += '<div class="ml-last">Letzter Check</div>';
		html += '<div class="ml-actions"></div>';
		html += '</div>';
		items.forEach(function (m) {
			var p = monitorCardParts(m);
			html += '<div class="monitor-list-row ' + p.statusClass + '" data-id="' + m.id + '">';
			html += '<div class="ml-check" onclick="event.stopPropagation()"><input type="checkbox" class="monitor-bulk-check" value="' + m.id + '"></div>';
			html += '<div class="ml-status"><div class="monitor-status-dot"></div> ' + p.statusLabel + '</div>';
			html += '<div class="ml-label monitor-card-body" data-id="' + m.id + '"><strong>' + escHtml(m.label) + '</strong><small>' + escHtml(m.url) + '</small>' + p.catBadge + p.subBadge + '</div>';
			html += '<div class="ml-client">' + (p.clientBadge || '<span style="color:#ccc;">–</span>') + '</div>';
			html += '<div class="ml-uptime" style="color:' + p.uptimeColor + ';font-weight:700;">' + m.uptime_24h + '%</div>';
			html += '<div class="ml-response">' + m.avg_response + 'ms</div>';
			html += '<div class="ml-code">' + (m.last_status_code || '–') + '</div>';
			html += '<div class="ml-last">' + p.lastCheck + '</div>';
			html += '<div class="ml-actions">' + p.actions + '</div>';
			html += '</div>';
		});
		html += '</div>';
		return html;
	}

	function escHtml(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	function monitorTimeAgo(dateStr) {
		var d = new Date(dateStr + ' UTC');
		var now = new Date();
		var diff = Math.floor((now - d) / 1000);
		if (diff < 60) return 'gerade';
		if (diff < 3600) return Math.floor(diff / 60) + ' Min';
		if (diff < 86400) return Math.floor(diff / 3600) + ' Std';
		return Math.floor(diff / 86400) + ' Tage';
	}

	// Toggle grid/list view
	$(document).on('click', '.monitor-view-btn', function () {
		monitorView = $(this).data('view');
		setCookie('tallyr_monitor_view', monitorView);
		renderMonitors();
	});

	// Search monitors
	var monitorSearchTimer = null;
	$(document).on('input', '#monitor-search', function () {
		clearTimeout(monitorSearchTimer);
		monitorSearchTimer = setTimeout(function () {
			renderMonitors();
		}, 200);
	});

	// Auto-fetch title when URL loses focus or fetch button is clicked
	var monitorFetchTimeout = null;

	function fetchSiteTitle() {
		var url = $('#monitor-url').val().trim();
		if (!url || url.length < 8) return;

		$('#monitor-label-loading').show();
		$('#monitor-fetch-title').prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_fetch_site_title',
				url: url,
			},
			success: function (result) {
				$('#monitor-label-loading').hide();
				$('#monitor-fetch-title').prop('disabled', false);
				if (result.success && result.data.title) {
					$('#monitor-label').val(result.data.title);
				}
			},
			error: function () {
				$('#monitor-label-loading').hide();
				$('#monitor-fetch-title').prop('disabled', false);
			}
		});
	}

	$(document).on('click', '#monitor-fetch-title', function () {
		fetchSiteTitle();
	});

	$(document).on('blur', '#monitor-url', function () {
		// Only auto-fetch if label is empty
		if (!$('#monitor-label').val().trim()) {
			fetchSiteTitle();
		}
	});

	// Open add modal
	$(document).on('click', '#monitor-add-btn', function () {
		$('#monitor-edit-id').val(0);
		$('#monitor-modal-title').text('Website hinzufügen');
		$('#monitor-url').val('');
		$('#monitor-label').val('');
		$('#monitor-sub-urls').val('');
		$('#monitor-category').val('');
		$('#monitor-client').val(0);
		$('#monitor-alert-email').val('');
		$('#monitor-report').val('both');
		$('#monitor-modal').show();
	});

	// Close modals
	$(document).on('click', '.monitor-modal .modal-overlay, .monitor-modal .modal-close', function () {
		$(this).closest('.monitor-modal').hide();
	});

	// Save monitor
	$(document).on('submit', '#monitor-form', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitBtn = form.find('button[type="submit"]');
		submitBtn.prop('disabled', true);

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_save_monitor',
				monitor_id: $('#monitor-edit-id').val(),
				url: $('#monitor-url').val(),
				label: $('#monitor-label').val(),
				sub_urls: $('#monitor-sub-urls').val(),
				category: $('#monitor-category').val(),
				client_id: $('#monitor-client').val(),
				alert_email: $('#monitor-alert-email').val(),
				report_schedule: $('#monitor-report').val(),
			},
			success: function (result) {
				submitBtn.prop('disabled', false);
				if (result.success) {
					$('#monitor-modal').hide();
					toastr.success('Monitor gespeichert.');
					loadMonitors();
				} else {
					toastr.error(result.data || 'Fehler beim Speichern.');
				}
			},
			error: function () {
				submitBtn.prop('disabled', false);
				toastr.error('Fehler beim Speichern.');
			}
		});
	});

	// Edit monitor
	$(document).on('click', '.monitor-btn-edit', function (e) {
		e.stopPropagation();
		var card = $(this).closest('.monitor-card');
		var id = $(this).data('id');

		// Load current data via the card
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitors',
			},
			success: function (result) {
				if (!result.success) return;
				var m = result.data.find(function (x) { return x.id == id; });
				if (!m) return;

				$('#monitor-edit-id').val(m.id);
				$('#monitor-modal-title').text('Website bearbeiten');
				$('#monitor-url').val(m.url);
				$('#monitor-label').val(m.label);
				var subText = '';
				if (m.sub_urls) {
					try { subText = JSON.parse(m.sub_urls).join('\n'); } catch(e) {}
				}
				$('#monitor-sub-urls').val(subText);
				$('#monitor-category').val(m.category || '');
				$('#monitor-client').val(m.client_id);
				$('#monitor-alert-email').val(m.alert_email);
				$('#monitor-report').val(m.report_schedule);
				$('#monitor-modal').show();
			}
		});
	});

	// Delete monitor
	$(document).on('click', '.monitor-btn-delete', function (e) {
		e.stopPropagation();
		var id = $(this).data('id');
		Swal.fire({
			title: 'Monitor löschen?',
			text: 'Alle Logs und Incidents werden ebenfalls gelöscht.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			cancelButtonColor: '#999',
			confirmButtonText: 'Ja, löschen',
			cancelButtonText: 'Abbrechen',
		}).then(function (result) {
			if (result.isConfirmed) {
				jQuery.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_delete_monitor',
						monitor_id: id,
					},
					success: function (result) {
						if (result.success) {
							toastr.success('Monitor gelöscht.');
							loadMonitors();
						} else {
							toastr.error(result.data || 'Fehler.');
						}
					}
				});
			}
		});
	});

	// Toggle (pause/resume)
	$(document).on('click', '.monitor-btn-toggle', function (e) {
		e.stopPropagation();
		var id = $(this).data('id');
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_toggle_monitor',
				monitor_id: id,
			},
			success: function (result) {
				if (result.success) {
					toastr.success(result.data.status === 'paused' ? 'Monitor pausiert.' : 'Monitor aktiviert.');
					loadMonitors();
				}
			}
		});
	});

	// Show detail modal
	$(document).on('click', '.monitor-btn-detail, .monitor-card-body', function (e) {
		e.stopPropagation();
		var id = $(this).data('id');
		var monitor = null;
		for (var i = 0; i < monitorData.length; i++) {
			if (monitorData[i].id == id) { monitor = monitorData[i]; break; }
		}
		$('#monitor-detail-modal').show();
		$('#monitor-detail-title').text(monitor ? monitor.label : 'Details');
		$('#monitor-detail-stats').html('<div class="monitor-detail-loading"><i class="bx bx-loader-alt bx-spin"></i> Lade...</div>');
		$('#monitor-detail-urls').html('');
		$('#monitor-detail-chart').html('');
		$('#monitor-detail-incidents').html('');
		$('#monitor-detail-log').html('');

		// Load stats
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_stats',
				monitor_id: id,
			},
			success: function (result) {
				if (!result.success) return;
				var s = result.data.stats;
				var html = '<div class="monitor-stats-grid">';
				var periods = [
					{ key: '24h', label: 'Letzte 24 Stunden' },
					{ key: '7d', label: 'Letzte 7 Tage' },
					{ key: '30d', label: 'Letzte 30 Tage' }
				];
				periods.forEach(function (p) {
					var d = s[p.key];
					var color = d.uptime >= 99 ? '#388e3c' : (d.uptime >= 95 ? '#f57c00' : '#d32f2f');
					html += '<div class="monitor-stat-card">';
					html += '<div class="stat-period">' + p.label + '</div>';
					html += '<div class="stat-uptime" style="color:' + color + ';">' + d.uptime + '%</div>';
					html += '<div class="stat-meta">' + d.avg_response + 'ms avg · ' + d.total_checks + ' Checks</div>';
					html += '</div>';
				});
				html += '</div>';
				$('#monitor-detail-stats').html(html);

				// Per-URL breakdown
				var urlStats = result.data.url_stats;
				if (urlStats && urlStats.length > 0) {
					var urlHtml = '<h4>URLs im Detail (24h)</h4>';
					urlHtml += '<table class="log-table url-stats-table"><thead><tr><th>URL</th><th>Status</th><th>Uptime</th><th>Response</th><th>Checks</th></tr></thead><tbody>';
					urlStats.forEach(function (u) {
						var color = u.uptime >= 99 ? '#388e3c' : (u.uptime >= 95 ? '#f57c00' : '#d32f2f');
						var statusDot = u.is_up ? '<span class="url-status-dot url-up"></span>' : '<span class="url-status-dot url-down"></span>';
						urlHtml += '<tr>';
						urlHtml += '<td class="url-cell">' + escHtml(u.url) + '</td>';
						urlHtml += '<td>' + statusDot + '</td>';
						urlHtml += '<td style="color:' + color + ';font-weight:600;">' + u.uptime + '%</td>';
						urlHtml += '<td>' + u.avg_response + 'ms</td>';
						urlHtml += '<td>' + u.total_checks + '</td>';
						urlHtml += '</tr>';
					});
					urlHtml += '</tbody></table>';
					$('#monitor-detail-urls').html(urlHtml);
				}

				// Render chart
				var chart = result.data.chart;
				if (chart && chart.length) {
					var maxMs = Math.max.apply(null, chart.map(function (c) { return parseFloat(c.avg_ms); }));
					var chartHtml = '<h4>Response-Time (letzte 24h)</h4><div class="monitor-chart">';
					chart.forEach(function (c) {
						var h = maxMs > 0 ? (parseFloat(c.avg_ms) / maxMs * 100) : 0;
						chartHtml += '<div class="monitor-chart-bar">';
						chartHtml += '<div class="bar-fill" style="height:' + h + '%;"></div>';
						chartHtml += '<div class="bar-label">' + c.hour_label + '</div>';
						chartHtml += '<div class="bar-value">' + Math.round(parseFloat(c.avg_ms)) + 'ms</div>';
						chartHtml += '</div>';
					});
					chartHtml += '</div>';
					$('#monitor-detail-chart').html(chartHtml);
				}
			}
		});

		// Load timeline
		loadMonitorTimeline(id, '30d');

		// Load incidents
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_incidents',
				monitor_id: id,
			},
			success: function (result) {
				if (!result.success || !result.data.length) {
					$('#monitor-detail-incidents').html('<h4>Incidents</h4><p class="no-items">Keine Incidents.</p>');
					return;
				}
				var html = '<h4>Incidents</h4><table class="log-table"><thead><tr><th>Start</th><th>Ende</th><th>Dauer</th></tr></thead><tbody>';
				result.data.forEach(function (inc) {
					var ended = inc.ended_at ? monitorFormatDate(inc.ended_at) : '<span style="color:#d32f2f;">Offen</span>';
					var dur = inc.duration_minutes ? inc.duration_minutes + ' Min' : '–';
					html += '<tr><td>' + monitorFormatDate(inc.started_at) + '</td><td>' + ended + '</td><td>' + dur + '</td></tr>';
				});
				html += '</tbody></table>';
				$('#monitor-detail-incidents').html(html);
			}
		});

		// Load check log
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_log',
				monitor_id: id,
			},
			success: function (result) {
				if (!result.success || !result.data.length) {
					$('#monitor-detail-log').html('<h4>Check-Log</h4><p class="no-items">Noch keine Einträge.</p>');
					return;
				}
				var hasSubUrls = false;
				result.data.forEach(function (l) {
					if (l.checked_url && monitor && l.checked_url !== monitor.url) hasSubUrls = true;
				});
				var html = '<h4>Check-Log <small>(letzte 100)</small></h4>';
				html += '<table class="log-table check-log-table"><thead><tr>';
				if (hasSubUrls) html += '<th>URL</th>';
				html += '<th>Status</th><th>Code</th><th>Response</th><th>Zeitpunkt</th></tr></thead><tbody>';
				result.data.forEach(function (l) {
					var isUp = parseInt(l.is_up);
					var rowClass = isUp ? '' : ' class="log-row-down"';
					html += '<tr' + rowClass + '>';
					if (hasSubUrls) {
						var shortUrl = l.checked_url || '–';
						if (monitor && shortUrl.indexOf(monitor.url) === 0 && shortUrl === monitor.url) {
							shortUrl = '(Haupt-URL)';
						} else if (shortUrl.length > 40) {
							shortUrl = shortUrl.substring(0, 40) + '…';
						}
						html += '<td class="url-cell" title="' + escHtml(l.checked_url || '') + '">' + escHtml(shortUrl) + '</td>';
					}
					html += '<td>' + (isUp ? '<span class="log-status-up">OK</span>' : '<span class="log-status-down">DOWN</span>') + '</td>';
					html += '<td>' + l.status_code + '</td>';
					html += '<td>' + l.response_time_ms + 'ms</td>';
					html += '<td>' + monitorFormatDate(l.checked_at) + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				$('#monitor-detail-log').html(html);
			}
		});
	});

	function monitorFormatDate(dateStr) {
		if (!dateStr) return '–';
		var d = new Date(dateStr + ' UTC');
		var day = String(d.getDate()).padStart(2, '0');
		var month = String(d.getMonth() + 1).padStart(2, '0');
		var hours = String(d.getHours()).padStart(2, '0');
		var mins = String(d.getMinutes()).padStart(2, '0');
		return day + '.' + month + '.' + d.getFullYear() + ' ' + hours + ':' + mins;
	}

	// ========================================
	// MONITOR TIMELINE
	// ========================================

	var currentTimelineMonitorId = null;

	$(document).on('click', '.timeline-period', function () {
		$('.timeline-period').removeClass('active');
		$(this).addClass('active');
		var period = $(this).data('period');
		if (currentTimelineMonitorId) {
			loadMonitorTimeline(currentTimelineMonitorId, period);
		}
	});

	function loadMonitorTimeline(monitorId, period) {
		currentTimelineMonitorId = monitorId;
		$('#timeline-summary').html('<div class="monitor-detail-loading"><i class="bx bx-loader-alt bx-spin"></i></div>');
		$('#timeline-uptime-bar').html('');
		var canvas = document.getElementById('timeline-canvas');
		if (canvas) {
			var ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
		}

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_timeline',
				monitor_id: monitorId,
				period: period,
			},
			success: function (result) {
				if (!result.success) return;
				var data = result.data;
				var days = data.days;
				var summary = data.summary;

				// Summary
				var ucolor = summary.uptime >= 99 ? '#388e3c' : (summary.uptime >= 95 ? '#f57c00' : '#d32f2f');
				var downStr = summary.downtime_min <= 0 ? '–' : (summary.downtime_min < 60 ? summary.downtime_min + ' Min' : Math.floor(summary.downtime_min / 60) + ' Std ' + (summary.downtime_min % 60) + ' Min');
				var sumHtml = '<div class="timeline-summary-grid">';
				sumHtml += '<div class="tl-sum"><span class="tl-sum-val" style="color:' + ucolor + ';">' + summary.uptime + '%</span><span class="tl-sum-label">Uptime</span></div>';
				sumHtml += '<div class="tl-sum"><span class="tl-sum-val">' + summary.avg_response + ' ms</span><span class="tl-sum-label">Ø Response</span></div>';
				sumHtml += '<div class="tl-sum"><span class="tl-sum-val">' + summary.incidents + '</span><span class="tl-sum-label">Ausfälle</span></div>';
				sumHtml += '<div class="tl-sum"><span class="tl-sum-val">' + downStr + '</span><span class="tl-sum-label">Ausfallzeit</span></div>';
				sumHtml += '<div class="tl-sum"><span class="tl-sum-val">' + summary.days + '</span><span class="tl-sum-label">Tage</span></div>';
				sumHtml += '</div>';
				$('#timeline-summary').html(sumHtml);

				if (!days.length) {
					$('#timeline-uptime-bar').html('<p class="no-items">Noch keine Daten für diesen Zeitraum.</p>');
					return;
				}

				// Uptime bar
				var barHtml = '<div class="tl-uptime-label">Verfügbarkeit pro Tag</div><div class="tl-bar-container">';
				days.forEach(function (d) {
					var color, title;
					if (d.uptime === null) {
						color = '#e0e0e0';
						title = d.day_label + ': Keine Daten';
					} else if (d.uptime >= 99) {
						color = '#4caf50';
						title = d.day_label + ': ' + d.uptime + '% (' + d.avg_response + 'ms)';
					} else if (d.uptime >= 95) {
						color = '#ff9800';
						title = d.day_label + ': ' + d.uptime + '% (' + d.avg_response + 'ms)';
					} else if (d.uptime > 0) {
						color = '#f44336';
						title = d.day_label + ': ' + d.uptime + '% (' + d.avg_response + 'ms)';
					} else {
						color = '#d32f2f';
						title = d.day_label + ': Komplett ausgefallen';
					}
					barHtml += '<div class="tl-bar-segment" style="background:' + color + ';" title="' + title + '"></div>';
				});
				barHtml += '</div>';
				// Date labels under bar
				barHtml += '<div class="tl-bar-dates">';
				var labelInterval = Math.max(1, Math.floor(days.length / 8));
				for (var i = 0; i < days.length; i++) {
					if (i === 0 || i === days.length - 1 || i % labelInterval === 0) {
						var leftPct = (i / (days.length - 1)) * 100;
						barHtml += '<span class="tl-bar-date" style="left:' + leftPct + '%;">' + days[i].day_label + '</span>';
					}
				}
				barHtml += '</div>';
				// Legend
				barHtml += '<div class="tl-legend">';
				barHtml += '<span><i style="background:#4caf50;"></i> ≥99%</span>';
				barHtml += '<span><i style="background:#ff9800;"></i> 95–99%</span>';
				barHtml += '<span><i style="background:#f44336;"></i> <95%</span>';
				barHtml += '<span><i style="background:#e0e0e0;"></i> Keine Daten</span>';
				barHtml += '</div>';
				$('#timeline-uptime-bar').html(barHtml);

				// Response time canvas chart
				renderTimelineCanvas(days);
			}
		});
	}

	function renderTimelineCanvas(days) {
		// Delay to ensure container is visible and has width
		setTimeout(function () { _drawTimelineCanvas(days); }, 50);
	}

	function _drawTimelineCanvas(days) {
		var canvas = document.getElementById('timeline-canvas');
		if (!canvas) return;
		var container = canvas.parentElement;
		var cssW = container.offsetWidth;
		var cssH = 160;
		if (cssW < 50) return; // not visible yet

		// HiDPI / Retina support
		var dpr = window.devicePixelRatio || 1;
		canvas.width = cssW * dpr;
		canvas.height = cssH * dpr;
		canvas.style.width = cssW + 'px';
		canvas.style.height = cssH + 'px';
		var ctx = canvas.getContext('2d');
		ctx.scale(dpr, dpr);

		// Use CSS dimensions for drawing
		var w = cssW;
		var h = cssH;
		var padTop = 25, padBottom = 30, padLeft = 50, padRight = 15;
		var chartW = w - padLeft - padRight;
		var chartH = h - padTop - padBottom;

		ctx.clearRect(0, 0, w, h);

		// Filter days with data
		var points = [];
		days.forEach(function (d, i) {
			if (d.avg_response !== null && d.avg_response > 0) {
				points.push({ x: i, val: d.avg_response, min: d.min_response, max: d.max_response, label: d.day_label });
			}
		});

		if (!points.length) {
			ctx.fillStyle = '#999';
			ctx.font = '13px Arial';
			ctx.textAlign = 'center';
			ctx.fillText('Keine Response-Daten für diesen Zeitraum', w / 2, h / 2);
			return;
		}

		var maxVal = Math.max.apply(null, points.map(function (p) { return p.max || p.val; }));
		maxVal = Math.ceil(maxVal * 1.15);
		if (maxVal < 100) maxVal = 100;

		function xPos(i) { return padLeft + (i / Math.max(days.length - 1, 1)) * chartW; }
		function yPos(v) { return padTop + chartH - (v / maxVal) * chartH; }

		// Grid lines
		ctx.strokeStyle = '#f0f0f0';
		ctx.lineWidth = 1;
		var gridSteps = 4;
		for (var g = 0; g <= gridSteps; g++) {
			var gy = padTop + (g / gridSteps) * chartH;
			ctx.beginPath();
			ctx.moveTo(padLeft, gy);
			ctx.lineTo(w - padRight, gy);
			ctx.stroke();
			var gVal = Math.round(maxVal - (g / gridSteps) * maxVal);
			ctx.fillStyle = '#999';
			ctx.font = '11px Arial';
			ctx.textAlign = 'right';
			ctx.fillText(gVal + ' ms', padLeft - 6, gy + 4);
		}

		// Min/Max range fill
		if (points.length > 1) {
			ctx.fillStyle = 'rgba(52, 152, 219, 0.1)';
			ctx.beginPath();
			ctx.moveTo(xPos(points[0].x), yPos(points[0].min || points[0].val));
			for (var i = 1; i < points.length; i++) {
				ctx.lineTo(xPos(points[i].x), yPos(points[i].min || points[i].val));
			}
			for (var i = points.length - 1; i >= 0; i--) {
				ctx.lineTo(xPos(points[i].x), yPos(points[i].max || points[i].val));
			}
			ctx.closePath();
			ctx.fill();
		}

		// Avg line
		ctx.strokeStyle = '#3498db';
		ctx.lineWidth = 2;
		ctx.lineJoin = 'round';
		ctx.lineCap = 'round';
		ctx.beginPath();
		for (var i = 0; i < points.length; i++) {
			var px = xPos(points[i].x);
			var py = yPos(points[i].val);
			if (i === 0) ctx.moveTo(px, py);
			else ctx.lineTo(px, py);
		}
		ctx.stroke();

		// Dots (only if not too many)
		if (points.length <= 90) {
			points.forEach(function (p) {
				ctx.beginPath();
				ctx.arc(xPos(p.x), yPos(p.val), 2.5, 0, Math.PI * 2);
				ctx.fillStyle = '#3498db';
				ctx.fill();
			});
		}

		// X-axis labels
		ctx.fillStyle = '#999';
		ctx.font = '10px Arial';
		ctx.textAlign = 'center';
		var xLabelCount = Math.min(8, days.length);
		var xLabelInterval = Math.max(1, Math.floor(days.length / xLabelCount));
		for (var i = 0; i < days.length; i++) {
			if (i === 0 || i === days.length - 1 || i % xLabelInterval === 0) {
				ctx.fillText(days[i].day_label, xPos(i), h - 5);
			}
		}

		// Title
		ctx.fillStyle = '#666';
		ctx.font = '12px Arial';
		ctx.textAlign = 'left';
		ctx.fillText('Ø Response-Time (ms)', padLeft, 14);

		// Legend line + area
		var legX = padLeft + 160;
		ctx.strokeStyle = '#3498db';
		ctx.lineWidth = 2;
		ctx.beginPath();
		ctx.moveTo(legX, 10);
		ctx.lineTo(legX + 20, 10);
		ctx.stroke();
		ctx.fillStyle = '#999';
		ctx.font = '11px Arial';
		ctx.textAlign = 'left';
		ctx.fillText('Durchschnitt', legX + 25, 14);

		ctx.fillStyle = 'rgba(52, 152, 219, 0.15)';
		ctx.fillRect(legX + 115, 5, 14, 10);
		ctx.fillStyle = '#999';
		ctx.fillText('Min/Max', legX + 134, 14);
	}

	// Toggle cron info
	$(document).on('click', '#monitor-show-cron', function () {
		$('#monitor-cron-info').slideToggle(200);
	});

	// Copy cron URL
	$(document).on('click', '#monitor-copy-cron', function () {
		var input = document.getElementById('monitor-cron-url');
		input.select();
		document.execCommand('copy');
		toastr.success('Cron-URL kopiert.');
	});

	// Show cron log
	$(document).on('click', '#monitor-show-cron-log', function () {
		var $log = $('#monitor-cron-log');
		if ($log.is(':visible')) {
			$log.slideUp(200);
			return;
		}
		$log.html('<div class="monitor-detail-loading"><i class="bx bx-loader-alt bx-spin"></i> Lade...</div>').slideDown(200);
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_cron_log',
			},
			success: function (result) {
				if (!result.success || !result.data.length) {
					$log.html('<p style="color:#999;padding:10px 0;">Noch keine Cron-Ausführungen.</p>');
					return;
				}
				var html = '<table class="log-table"><thead><tr><th>Zeitpunkt</th><th>Monitors geprüft</th></tr></thead><tbody>';
				result.data.forEach(function (entry) {
					html += '<tr><td>' + monitorFormatDate(entry.time) + '</td><td>' + entry.count + '</td></tr>';
				});
				html += '</tbody></table>';
				$log.html(html);
			}
		});
	});

	// Show email log
	$(document).on('click', '#monitor-show-email-log', function () {
		var $log = $('#monitor-email-log');
		if ($log.is(':visible')) {
			$log.slideUp(200);
			return;
		}
		$log.html('<div class="monitor-detail-loading"><i class="bx bx-loader-alt bx-spin"></i> Lade...</div>').slideDown(200);
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_get_monitor_email_log',
			},
			success: function (result) {
				if (!result.success || !result.data.length) {
					$log.html('<p style="color:#999;padding:10px 0;">Noch keine E-Mails gesendet.</p>');
					return;
				}
				var typeLabels = { down: 'Alert', recovery: 'Recovery', report: 'Report' };
				var typeColors = { down: '#d32f2f', recovery: '#388e3c', report: '#1a73e8' };
				var html = '<table class="log-table"><thead><tr><th>Zeitpunkt</th><th>Typ</th><th>An</th><th>Betreff</th><th>Status</th></tr></thead><tbody>';
				result.data.forEach(function (entry) {
					var typeLabel = typeLabels[entry.type] || entry.type;
					var typeColor = typeColors[entry.type] || '#666';
					var statusIcon = entry.sent ? '<span style="color:#388e3c;">Gesendet</span>' : '<span style="color:#d32f2f;">Fehler</span>';
					html += '<tr>';
					html += '<td>' + monitorFormatDate(entry.time) + '</td>';
					html += '<td><span style="color:' + typeColor + ';font-weight:600;">' + typeLabel + '</span></td>';
					html += '<td>' + escHtml(entry.to) + '</td>';
					html += '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escHtml(entry.subject) + '">' + escHtml(entry.subject) + '</td>';
					html += '<td>' + statusIcon + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				$log.html(html);
			}
		});
	});

	// Test report
	$(document).on('click', '#monitor-test-report', function () {
		var btn = $(this);
		btn.prop('disabled', true);
		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_test_monitor_report',
			},
			success: function (result) {
				btn.prop('disabled', false);
				if (result.success) {
					toastr.success(result.data);
				} else {
					toastr.error(result.data || 'Fehler.');
				}
			},
			error: function () {
				btn.prop('disabled', false);
				toastr.error('Fehler.');
			}
		});
	});

	// Open batch modal
	$(document).on('click', '#monitor-batch-btn', function () {
		$('#monitor-batch-urls').val('');
		$('#monitor-batch-client').val(0);
		$('#monitor-batch-category').val('');
		$('#monitor-batch-report').val('both');
		$('#monitor-batch-modal').show();
	});

	// Submit batch import
	$(document).on('submit', '#monitor-batch-form', function (e) {
		e.preventDefault();
		var form = $(this);
		var submitBtn = form.find('button[type="submit"]');
		var urls = $('#monitor-batch-urls').val().trim();

		if (!urls) {
			toastr.error('Bitte URLs eingeben.');
			return;
		}

		var lineCount = urls.split('\n').filter(function (l) { return l.trim(); }).length;
		submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Importiere ' + lineCount + ' URLs...');

		jQuery.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_batch_import_monitors',
				urls: urls,
				client_id: $('#monitor-batch-client').val(),
				category: $('#monitor-batch-category').val(),
				report_schedule: $('#monitor-batch-report').val(),
			},
			success: function (result) {
				submitBtn.prop('disabled', false).html('<i class="bx bx-import"></i> Importieren');
				if (result.success) {
					$('#monitor-batch-modal').hide();
					var msg = result.data.imported + ' Website(s) importiert.';
					if (result.data.errors && result.data.errors.length) {
						msg += ' ' + result.data.errors.length + ' übersprungen.';
						toastr.warning(msg);
					} else {
						toastr.success(msg);
					}
					loadMonitors();
				} else {
					toastr.error(result.data || 'Fehler.');
				}
			},
			error: function () {
				submitBtn.prop('disabled', false).html('<i class="bx bx-import"></i> Importieren');
				toastr.error('Fehler beim Import.');
			}
		});
	});

	// Build category filter pills
	function buildCategoryFilters() {
		var cats = {};
		monitorData.forEach(function (m) {
			var c = (m.category || '').trim();
			if (c) cats[c] = (cats[c] || 0) + 1;
		});
		var keys = Object.keys(cats).sort();
		if (!keys.length) {
			$('#monitor-filters').hide();
			return;
		}
		var html = '<button class="monitor-filter-pill' + (!monitorActiveCategory ? ' active' : '') + '" data-cat="">Alle (' + monitorData.length + ')</button>';
		keys.forEach(function (k) {
			html += '<button class="monitor-filter-pill' + (monitorActiveCategory === k ? ' active' : '') + '" data-cat="' + escHtml(k) + '">' + escHtml(k) + ' (' + cats[k] + ') <i class="bx bx-edit-alt monitor-cat-edit" data-cat="' + escHtml(k) + '"></i></button>';
		});
		var uncategorized = monitorData.filter(function (m) { return !(m.category || '').trim(); }).length;
		if (uncategorized > 0 && keys.length > 0) {
			html += '<button class="monitor-filter-pill' + (monitorActiveCategory === '__none__' ? ' active' : '') + '" data-cat="__none__">Ohne Kategorie (' + uncategorized + ')</button>';
		}
		$('#monitor-filters').html(html).show();

		// Update datalist for form
		var dlHtml = '';
		keys.forEach(function (k) { dlHtml += '<option value="' + escHtml(k) + '">'; });
		$('#monitor-category-list').html(dlHtml);
	}

	// Category filter click
	$(document).on('click', '.monitor-filter-pill', function () {
		$('.monitor-filter-pill').removeClass('active');
		$(this).addClass('active');
		monitorActiveCategory = $(this).data('cat');
		setCookie('tallyr_monitor_cat', monitorActiveCategory);
		renderMonitors();
	});

	// Font size toggle
	$(document).on('click', '#monitor-font-toggle', function () {
		monitorLargeFont = !monitorLargeFont;
		setCookie('tallyr_monitor_font', monitorLargeFont ? 'large' : '');
		$('#monitor-dashboard').toggleClass('monitor-large', monitorLargeFont);
		$(this).toggleClass('active', monitorLargeFont);
	});

	// Rename category
	$(document).on('click', '.monitor-cat-edit', function (e) {
		e.stopPropagation();
		e.preventDefault();
		var oldName = $(this).data('cat');
		Swal.fire({
			title: 'Kategorie umbenennen',
			input: 'text',
			inputValue: oldName,
			inputPlaceholder: 'Neuer Name...',
			showCancelButton: true,
			confirmButtonText: 'Umbenennen',
			cancelButtonText: 'Abbrechen',
			inputValidator: function (value) {
				if (!value || !value.trim()) return 'Bitte einen Namen eingeben.';
			}
		}).then(function (result) {
			if (result.isConfirmed && result.value) {
				jQuery.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_rename_monitor_category',
						old_name: oldName,
						new_name: result.value.trim(),
					},
					success: function (res) {
						if (res.success) {
							if (monitorActiveCategory === oldName) {
								monitorActiveCategory = result.value.trim();
								setCookie('tallyr_monitor_cat', monitorActiveCategory);
							}
							toastr.success(res.data.updated + ' Monitor(s) aktualisiert.');
							loadMonitors();
						} else {
							toastr.error(res.data || 'Fehler.');
						}
					}
				});
			}
		});
	});

	// Bulk selection
	$(document).on('change', '.monitor-bulk-check', function (e) {
		e.stopPropagation();
		updateBulkBar();
	});

	function getSelectedIds() {
		var ids = [];
		$('.monitor-bulk-check:checked').each(function () {
			ids.push($(this).val());
		});
		return ids;
	}

	function updateBulkBar() {
		var ids = getSelectedIds();
		if (ids.length > 0) {
			$('#monitor-bulk-bar').slideDown(200);
			$('#monitor-bulk-count').text(ids.length);
		} else {
			$('#monitor-bulk-bar').slideUp(200);
		}
	}

	// Select all toggle
	$(document).on('click', '#monitor-bulk-select-all', function () {
		var allChecked = $('.monitor-bulk-check').length === $('.monitor-bulk-check:checked').length;
		$('.monitor-bulk-check').prop('checked', !allChecked);
		updateBulkBar();
	});

	// Bulk set category
	$(document).on('click', '#monitor-bulk-categorize', function () {
		var ids = getSelectedIds();
		if (!ids.length) return;

		// Build options from existing categories
		var cats = {};
		monitorData.forEach(function (m) {
			var c = (m.category || '').trim();
			if (c) cats[c] = true;
		});
		var catOptions = { '': '– Keine Kategorie –' };
		Object.keys(cats).sort().forEach(function (k) { catOptions[k] = k; });

		var catKeys = Object.keys(cats).sort();
		var dlOptions = '';
		catKeys.forEach(function (k) { dlOptions += '<option value="' + escHtml(k) + '">'; });
		var inputHtml = '<label style="display:block;text-align:left;font-size:13px;color:#666;margin-bottom:6px;">Kategorie auswählen oder neue eingeben:</label>';
		inputHtml += '<input id="swal-bulk-cat" list="swal-bulk-cat-list" type="text" class="swal2-input" style="margin:0;width:100%;" placeholder="Auswählen oder neu eingeben...">';
		inputHtml += '<datalist id="swal-bulk-cat-list"><option value="">– Keine Kategorie –</option>' + dlOptions + '</datalist>';

		Swal.fire({
			title: ids.length + ' Monitor(s) kategorisieren',
			html: inputHtml,
			showCancelButton: true,
			confirmButtonText: 'Zuweisen',
			cancelButtonText: 'Abbrechen',
			focusConfirm: false,
			preConfirm: function () {
				return document.getElementById('swal-bulk-cat').value;
			},
		}).then(function (result) {
			if (result.isConfirmed) {
				jQuery.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_bulk_update_monitors',
						ids: ids,
						category: (result.value || '').trim(),
					},
					success: function (res) {
						if (res.success) {
							toastr.success(res.data.updated + ' Monitor(s) aktualisiert.');
							loadMonitors();
						} else {
							toastr.error(res.data || 'Fehler.');
						}
					}
				});
			}
		});
	});

	// Initialize monitor on page load
	if ($('#monitor-dashboard').length) {
		monitorView = getCookie('tallyr_monitor_view') || 'grid';
		monitorLargeFont = getCookie('tallyr_monitor_font') === 'large';
		$('.monitor-view-btn').removeClass('active');
		$('.monitor-view-btn[data-view="' + monitorView + '"]').addClass('active');
		if (monitorLargeFont) {
			$('#monitor-dashboard').addClass('monitor-large');
			$('#monitor-font-toggle').addClass('active');
		}
		loadMonitors();
	}

	// ========================================
	// CLIENTS TABLE VIEW
	// ========================================

	if ($('#clients-table-view').length) {
		var ctSaveTimers = {};

		// Auto-save on contenteditable input
		$(document).on('input', '#ct-table-body .pp-field', function () {
			var $tr = $(this).closest('tr');
			var clientId = $tr.data('id');
			var field = $(this).data('field');
			var value;

			if ($(this).is('[contenteditable]')) {
				var html = $(this)[0].innerHTML;
				value = html.replace(/<div>/gi, '\n').replace(/<\/div>/gi, '').replace(/<br\s*\/?>/gi, '\n').replace(/&nbsp;/g, ' ').replace(/<[^>]+>/g, '');
				var tmp = document.createElement('textarea');
				tmp.innerHTML = value;
				value = tmp.value.trim();
			} else if ($(this).is('select') || $(this).is('input')) {
				value = $(this).val();
			}

			$(this).addClass('pp-saving');

			if (ctSaveTimers[clientId + '_' + field]) clearTimeout(ctSaveTimers[clientId + '_' + field]);
			ctSaveTimers[clientId + '_' + field] = setTimeout(function () {
				$.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_quick_save_client',
						client_id: clientId,
						field: field,
						value: value,
					},
					success: function (res) {
						$tr.find('[data-field="' + field + '"]').removeClass('pp-saving').addClass('pp-saved');
						setTimeout(function () {
							$tr.find('[data-field="' + field + '"]').removeClass('pp-saved');
						}, 800);
						if (!res.success) toastr.error(res.data || 'Fehler');
					},
					error: function () {
						$tr.find('[data-field="' + field + '"]').removeClass('pp-saving').addClass('pp-error');
						toastr.error('Speichern fehlgeschlagen');
					}
				});
			}, 600);
		});

		// Also handle change for select and color
		$(document).on('change', '#ct-table-body .pp-field', function () {
			$(this).trigger('input');
		});

		// Paste as plain text
		$(document).on('paste', '#ct-table-body [contenteditable]', function (e) {
			e.preventDefault();
			var text = (e.originalEvent.clipboardData || window.clipboardData).getData('text/plain');
			document.execCommand('insertText', false, text);
		});

		// Password change via Swal
		$(document).on('click', '.ct-pw-btn', function () {
			var $tr = $(this).closest('tr');
			var clientId = $tr.data('id');
			var $btn = $(this);

			Swal.fire({
				title: 'Passwort ändern',
				input: 'password',
				inputPlaceholder: 'Neues Passwort (leer = unverändert)',
				width: 360,
				showCancelButton: true,
				confirmButtonText: 'Speichern',
				cancelButtonText: 'Abbrechen',
			}).then(function (result) {
				if (result.isConfirmed && result.value) {
					$.ajax({
						type: 'POST',
						url: ajaxuser.url,
						data: {
							security: ajaxuser.nonce,
							action: 'uf_quick_save_client',
							client_id: clientId,
							field: 'pass',
							value: result.value,
						},
						success: function (res) {
							if (res.success) {
								$btn.find('i').attr('class', 'bx bx-lock-alt');
								toastr.success('Passwort gespeichert');
							}
						}
					});
				}
			});
		});

		// Asana project linking via Swal
		$(document).on('click', '.ct-asana-btn', function () {
			var $tr = $(this).closest('tr');
			var clientId = $tr.data('id');
			var currentIds = ($tr.attr('data-asana') || '').split(',').filter(function (s) { return s.trim(); });
			var $btn = $(this);

			// Load Asana projects
			Swal.fire({
				title: 'Asana Projekte',
				width: 500,
				html: '<div id="swal-asana-proj-loading" style="text-align:center;padding:20px;"><i class="bx bx-loader-alt bx-spin"></i> Lade Projekte...</div><div id="swal-asana-proj-list" style="display:none;max-height:350px;overflow-y:auto;"></div>',
				showCancelButton: true,
				confirmButtonText: 'Speichern',
				cancelButtonText: 'Abbrechen',
				didOpen: function (popup) {
					$.ajax({
						type: 'POST',
						url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_get_asana_projects' },
						success: function (res) {
							$(popup).find('#swal-asana-proj-loading').hide();
							if (!res.success || !res.data.length) {
								$(popup).find('#swal-asana-proj-list').html('<p style="padding:10px;color:#999;">Keine Projekte gefunden.</p>').show();
								return;
							}
							var h = '<input type="text" id="swal-asana-proj-search" placeholder="Filtern..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;margin-bottom:8px;box-sizing:border-box;">';
							res.data.forEach(function (p) {
								var checked = currentIds.indexOf(p.gid) > -1 ? ' checked' : '';
								h += '<label class="swal-asana-proj-item" style="display:flex;align-items:center;gap:8px;padding:6px 10px;cursor:pointer;border-bottom:1px solid #f5f5f5;font-size:13px;">';
								h += '<input type="checkbox" class="swal-asana-proj-cb" value="' + p.gid + '"' + checked + '> ' + escHtml(p.name);
								if (p.workspace_name) h += ' <small style="color:#aaa;">(' + escHtml(p.workspace_name) + ')</small>';
								h += '</label>';
							});
							$(popup).find('#swal-asana-proj-list').html(h).show();

							// Filter
							$(popup).find('#swal-asana-proj-search').on('input', function () {
								var q = $(this).val().toLowerCase();
								$(popup).find('.swal-asana-proj-item').each(function () {
									$(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
								});
							});
						}
					});
				},
				preConfirm: function () {
					var selected = [];
					$('.swal-asana-proj-cb:checked').each(function () {
						selected.push($(this).val());
					});
					return selected.join(',');
				}
			}).then(function (result) {
				if (result.isConfirmed) {
					var newVal = result.value || '';
					$.ajax({
						type: 'POST',
						url: ajaxuser.url,
						data: {
							security: ajaxuser.nonce,
							action: 'uf_quick_save_client',
							client_id: clientId,
							field: 'asana_project_id',
							value: newVal,
						},
						success: function (res) {
							if (res.success) {
								$tr.attr('data-asana', newVal);
								var count = newVal ? newVal.split(',').filter(function (s) { return s; }).length : 0;
								$btn.html(count ? '<span class="ct-asana-count">' + count + ' Projekt' + (count > 1 ? 'e' : '') + '</span>' : '<i class="bx bx-link"></i>');
								toastr.success('Asana Projekte gespeichert');
							}
						}
					});
				}
			});
		});

		// Add new client
		// Add new client
		$(document).on('click', '#ct-add-row', function () {
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_quick_create_client' },
				success: function (res) {
					if (!res.success) { toastr.error(res.data); return; }
					var c = res.data;
					var parentOpts = '<option value="0">–</option>';
					$('#ct-table-body tr').each(function () {
						parentOpts += '<option value="' + $(this).data('id') + '">' + escHtml($(this).find('[data-field="title"]').text().trim()) + '</option>';
					});

					var row = '<tr data-id="' + c.id + '" data-asana="">';
					row += '<td class="ct-td-title"><div class="pp-cell pp-field" data-field="title" contenteditable="true">' + escHtml(c.title) + '</div></td>';
					row += '<td class="ct-td-short"><div class="pp-cell pp-field" data-field="shortdesc" contenteditable="true"></div></td>';
					row += '<td class="ct-td-url"><div class="pp-cell pp-field" data-field="url" contenteditable="true"></div></td>';
					row += '<td class="ct-td-rate"><div class="pp-cell pp-cell-num pp-field" data-field="stundensatz" contenteditable="true">0</div></td>';
					row += '<td class="ct-td-color"><input type="color" class="ct-color pp-field" data-field="hexcolor" value="' + c.hexcolor + '"></td>';
					row += '<td class="ct-td-parent"><select class="ct-select pp-field" data-field="parentclient">' + parentOpts + '</select></td>';
					row += '<td class="ct-td-pw"><button type="button" class="ct-pw-btn" title="Passwort ändern"><i class="bx bx-lock-open-alt"></i></button></td>';
					if ($('.ct-td-asana').length) {
						row += '<td class="ct-td-asana"><button type="button" class="ct-asana-btn" title="Asana Projekte"><i class="bx bx-link"></i></button></td>';
					}
					row += '<td class="ct-td-link"></td>';
					row += '<td class="ct-td-del"><button type="button" class="ct-delete-btn" title="Kunde löschen"><i class="bx bx-trash"></i></button></td>';
					row += '</tr>';

					$('#ct-table-body').prepend(row);
					$('#ct-table-body tr:first [data-field="title"]').focus();
					toastr.success('Kunde angelegt');
				}
			});
		});

		// Delete client
		$(document).on('click', '.ct-delete-btn', function () {
			var $tr = $(this).closest('tr');
			var clientId = $tr.data('id');
			var clientName = $tr.find('[data-field="title"]').text().trim();

			Swal.fire({
				title: 'Kunde löschen?',
				text: clientName + ' wird archiviert.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Ja, löschen',
				cancelButtonText: 'Abbrechen',
			}).then(function (result) {
				if (result.isConfirmed) {
					$.ajax({
						type: 'POST',
						url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_delete_client', client_id: clientId },
						success: function (res) {
							if (res.success) {
								$tr.fadeOut(200, function () { $tr.remove(); });
								toastr.success('Kunde gelöscht');
							} else {
								toastr.error(res.data);
							}
						}
					});
				}
			});
		});

	}

	// ========================================
	// TALLYR SETTINGS
	// ========================================

	if ($('#tallyr-settings').length) {
		// Save setting fields
		$(document).on('click', '.ts-save-btn', function () {
			var field = $(this).data('field');
			var inputId = $(this).data('input');
			var val = $('#' + inputId).val();
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: field, value: val },
				success: function (res) {
					if (res.success) toastr.success('Gespeichert');
					else toastr.error(res.data);
				}
			});
		});

		// Refresh Asana cache
		$(document).on('click', '#ts-refresh-asana', function () {
			var $btn = $(this);
			$btn.find('i').addClass('bx-spin');
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_refresh_asana_cache' },
				success: function () {
					$btn.find('i').removeClass('bx-spin');
					toastr.success('Asana Cache geleert');
				}
			});
		});

		// Team Tags management
		function tsSaveTags() {
			var tags = [];
			$('#ts-tags-list .ts-tag').each(function () {
				var cap = $(this).data('capacity');
				tags.push({ kuerzel: $(this).data('kuerzel'), name: $(this).data('name'), capacity: cap ? parseInt(cap) : 0 });
			});
			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: 'tallyr_pp_tags', value: JSON.stringify(tags) },
				success: function (res) { if (res.success) toastr.success('Tags gespeichert'); }
			});
		}

		$(document).on('click', '#ts-tag-add', function () {
			var kuerzel = $('#ts-tag-kuerzel').val().trim();
			var name = $('#ts-tag-name').val().trim();
			var capacity = parseInt($('#ts-tag-capacity').val()) || 0;
			if (!kuerzel) return;
			if (!name) name = kuerzel;
			$('#ts-tags-list').append(
				'<span class="ts-tag" data-name="' + escAttr(name) + '" data-kuerzel="' + escAttr(kuerzel) + '" data-capacity="' + capacity + '">' +
				escHtml(kuerzel) + ' <small style="color:#999;">' + escHtml(name) + '</small>' +
				(capacity ? '<small style="color:#aaa;margin-left:2px;">(' + capacity + 'h)</small>' : '') +
				'<i class="bx bx-x ts-tag-remove" style="cursor:pointer;margin-left:2px;color:#ccc;"></i></span>'
			);
			$('#ts-tag-kuerzel').val('');
			$('#ts-tag-name').val('');
			$('#ts-tag-capacity').val('');
			$('#ts-tag-kuerzel').focus();
			tsSaveTags();
		});

		$(document).on('click', '.ts-tag-remove', function () {
			$(this).parent('.ts-tag').remove();
			tsSaveTags();
		});

		// Enter in tag inputs
		$(document).on('keydown', '#ts-tag-kuerzel, #ts-tag-name, #ts-tag-capacity', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); $('#ts-tag-add').click(); }
		});

		// Textbausteine project selector
		if ($('#ts-tb-project').length) {
			// Load Asana projects into select
			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_get_asana_projects' },
				success: function (res) {
					if (!res.success) return;
					var saved = '<?php echo esc_js(get_user_meta(get_current_user_id(), "tallyr_pp_textbausteine_project", true)); ?>';
					var h = '<option value="">Kein Projekt</option>';
					res.data.forEach(function (p) {
						var sel = p.gid === saved ? ' selected' : '';
						h += '<option value="' + p.gid + '"' + sel + '>' + p.name + '</option>';
					});
					$('#ts-tb-project').html(h);
				}
			});

			$(document).on('click', '#ts-tb-save', function () {
				var val = $('#ts-tb-project').val();
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: 'tallyr_pp_textbausteine_project', value: val },
					success: function (res) {
						if (res.success) {
							toastr.success('Gespeichert');
							if (val) $('#ts-tb-refresh').click(); // auto-refresh cache
						}
					}
				});
			});

			$(document).on('click', '#ts-tb-refresh', function () {
				var $btn = $(this);
				$btn.find('i').addClass('bx-spin');
				$('#ts-tb-status').text('Lade Textbausteine...');
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_pp_get_textbausteine', force: '1' },
					success: function (res) {
						$btn.find('i').removeClass('bx-spin');
						var count = res.success ? res.data.length : 0;
						$('#ts-tb-status').text(count + ' Textbausteine geladen');
						toastr.success(count + ' Textbausteine aktualisiert');
					}
				});
			});
		}
	}

	// ========================================
	// PROJEKTPLANNER
	// ========================================

	var ppCurrentPlanId = null;
	var ppPlans = [];
	var ppRows = [];
	var ppSaveTimers = {};
	var ppFeedbackByRow = {};
	var ppCurrentPlan = null;

	if ($('#projektplanner-dashboard').length) {
		ppLoadPlans();
		ppLoadTextbausteine();
		ppLoadFeedbackBanner();
		ppInitStickyHeader();

		// Apply saved font size
		var ppFontSize = parseInt($('#projektplanner-dashboard').data('fontsize')) || 13;
		ppApplyFontSize(ppFontSize);

		// Apply saved column widths
		ppApplyColWidths();

		// Init column resize on headers
		ppInitColResize();
	}

	// JS-based sticky header (CSS sticky broken by parent overflow:clip)
	function ppInitStickyHeader() {
		if (!$('#pp-sticky-wrap').length) {
			$('body').append('<div id="pp-sticky-wrap" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9990;overflow:hidden;"></div>');
		}

		function updateSticky() {
			var $header = $('#pp-plan-header');
			var $thead = $('#pp-table thead');
			var $scroll = $('.pp-table-scroll');
			var $wrap = $('#pp-sticky-wrap');

			if (!$header.is(':visible') || !$thead.length) {
				$wrap.hide();
				return;
			}

			var theadRect = $thead[0].getBoundingClientRect();
			var scrolledPast = theadRect.top < 0;

			if (scrolledPast) {
				var scrollRect = $scroll[0].getBoundingClientRect();
				var scrollLeft = $scroll.scrollLeft();
				var containerLeft = scrollRect.left;
				var containerWidth = scrollRect.width;

				// Info bar
				var html = '<div style="background:#f8f9fa;padding:6px 16px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-left:' + containerLeft + 'px;width:' + containerWidth + 'px;box-sizing:border-box;">';
				html += '<span style="font-weight:600;font-size:13px;">' + $('#pp-client-badge').text() + ' · ' + $('#pp-plan-title-display').text() + '</span>';
				html += '<span style="font-size:12px;color:#666;">' + ($('.pp-plan-stats').html() || '') + '</span>';
				html += '</div>';

				// Thead clone - offset by horizontal scroll
				var tableWidth = $('#pp-table').outerWidth();
				html += '<div style="margin-left:' + containerLeft + 'px;width:' + containerWidth + 'px;overflow:hidden;">';
				html += '<table class="pp-table" style="width:' + tableWidth + 'px;border-collapse:collapse;table-layout:fixed;margin-left:-' + scrollLeft + 'px;">';
				html += '<thead>' + $thead.html() + '</thead></table></div>';

				$wrap.html(html).show();

				// Copy column widths
				var $origThs = $thead.find('th');
				var $cloneThs = $wrap.find('th');
				$origThs.each(function (i) {
					if ($cloneThs[i]) $($cloneThs[i]).css('width', $(this).outerWidth() + 'px');
				});
			} else {
				$wrap.hide();
			}
		}

		$(window).off('scroll.ppsticky').on('scroll.ppsticky', updateSticky);
		$('.pp-table-scroll').off('scroll.ppsticky').on('scroll.ppsticky', updateSticky);
	}

	function ppApplyColWidths() {
		var raw = $('#projektplanner-dashboard').attr('data-colwidths');
		if (!raw) return;
		try {
			var widths = JSON.parse(raw);
			$('#pp-table thead th').each(function (i) {
				if (widths[i]) $(this).css('width', widths[i] + 'px');
			});
		} catch (e) {}
	}

	function ppSaveColWidths() {
		var widths = [];
		$('#pp-table thead th').each(function () {
			widths.push($(this).outerWidth());
		});
		var json = JSON.stringify(widths);
		$('#projektplanner-dashboard').attr('data-colwidths', json);
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: 'tallyr_pp_colwidths', value: json }
		});
	}

	function ppInitColResize() {
		var $table = $('#pp-table');
		var $ths = $table.find('thead th');
		var dragging = false, startX, startW, $th;

		$ths.each(function () {
			var $handle = $('<div class="pp-col-resize-handle"></div>');
			$(this).css('position', 'relative');
			$(this).append($handle);
		});

		$(document).on('mousedown', '.pp-col-resize-handle', function (e) {
			e.preventDefault();
			$th = $(this).parent('th');
			dragging = true;
			startX = e.pageX;
			startW = $th.outerWidth();
			$('body').css('cursor', 'col-resize');
		});

		$(document).on('mousemove', function (e) {
			if (!dragging) return;
			var diff = e.pageX - startX;
			var newW = Math.max(30, startW + diff);
			$th.css('width', newW + 'px');
		});

		$(document).on('mouseup', function () {
			if (!dragging) return;
			dragging = false;
			$('body').css('cursor', '');
			ppSaveColWidths();
		});
	}

	function ppLoadFeedbackBanner() {
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_unread_feedback_count' },
			success: function (res) {
				if (!res.success || !res.data || !res.data.length) {
					$('#pp-feedback-banner').hide();
					return;
				}
				var total = 0;
				var items = '';
				res.data.forEach(function (r) {
					total += parseInt(r.unread_count);
					items += '<span class="pp-fbb-item" data-planid="' + r.plan_id + '">' + escHtml(r.client_short || '') + ' · ' + escHtml(r.title) + ' <strong>(' + r.unread_count + ')</strong></span>';
				});
				$('#pp-feedback-banner').html('<i class="bx bx-comment-dots"></i> <strong>' + total + ' neue Kommentare:</strong> ' + items).show();
			}
		});
	}

	// Dashboard toggle
	$(document).on('click', '#pp-dashboard-btn', function () {
		var $dash = $('#pp-dashboard');
		if ($dash.is(':visible')) {
			$dash.hide();
			$('.pp-plan-selector, .pp-selector-divider, #pp-plan-header, #pp-table-container, #pp-empty-state').show();
			return;
		}
		$('.pp-plan-selector, .pp-selector-divider, #pp-plan-header, #pp-table-container, #pp-empty-state, #pp-share-banner').hide();
		$dash.show();
		ppLoadDashboard();
	});

	$(document).on('click', '#pp-dashboard-close', function () {
		$('#pp-dashboard').hide();
		$('.pp-plan-selector, .pp-selector-divider, #pp-empty-state').show();
		if (ppSelectedPlanIds.length) {
			$('#pp-plan-header, #pp-table-container').show();
		}
	});

	// Dashboard tabs
	$(document).on('click', '.ppd-tab', function () {
		var tab = $(this).data('tab');
		$('.ppd-tab').removeClass('active');
		$(this).addClass('active');
		$('.ppd-tab-content').removeClass('active');
		$('.ppd-tab-' + tab).addClass('active');
	});

	// Dashboard filter apply
	$(document).on('click', '#ppd-apply-filter', function () { ppLoadDashboard(); });
	$(document).on('click', '#ppd-reset-filter', function () {
		$('#ppd-date-from, #ppd-date-to').val('');
		$('#ppd-status-filter').val('aktiv');
		$('#ppd-client-filter, #ppd-person-filter').val('');
		$('.ppd-preset').removeClass('active');
		ppLoadDashboard();
	});

	// Date presets
	function ppPresetDates(range) {
		var now = new Date();
		var y = now.getFullYear(), m = now.getMonth();
		var from = '', to = '';
		if (range === 'month') { from = y + '-' + String(m+1).padStart(2,'0') + '-01'; to = new Date(y, m+1, 0).toISOString().split('T')[0]; }
		else if (range === 'quarter') { var q = Math.floor(m/3)*3; from = y + '-' + String(q+1).padStart(2,'0') + '-01'; to = new Date(y, q+3, 0).toISOString().split('T')[0]; }
		else if (range === 'q1') { from = y + '-01-01'; to = y + '-03-31'; }
		else if (range === 'q2') { from = y + '-04-01'; to = y + '-06-30'; }
		else if (range === 'q3') { from = y + '-07-01'; to = y + '-09-30'; }
		else if (range === 'q4') { from = y + '-10-01'; to = y + '-12-31'; }
		else if (range === 'h1') { from = y + '-01-01'; to = y + '-06-30'; }
		else if (range === 'h2') { from = y + '-07-01'; to = y + '-12-31'; }
		else if (range === 'year') { from = y + '-01-01'; to = y + '-12-31'; }
		else if (range === 'last-month') { var lm = m === 0 ? 11 : m-1; var ly = m === 0 ? y-1 : y; from = ly + '-' + String(lm+1).padStart(2,'0') + '-01'; to = new Date(ly, lm+1, 0).toISOString().split('T')[0]; }
		else if (range === 'last-quarter') { var cq = Math.floor(m/3); var pq = cq === 0 ? 3 : cq-1; var py = cq === 0 ? y-1 : y; from = py + '-' + String(pq*3+1).padStart(2,'0') + '-01'; to = new Date(py, pq*3+3, 0).toISOString().split('T')[0]; }
		else { from = ''; to = ''; }
		return { from: from, to: to };
	}

	// Set default to current quarter on first load
	(function () {
		var def = ppPresetDates('quarter');
		$('#ppd-date-from').val(def.from);
		$('#ppd-date-to').val(def.to);
	})();

	$(document).on('click', '.ppd-preset', function () {
		$('.ppd-preset').removeClass('active');
		$(this).addClass('active');
		var dates = ppPresetDates($(this).data('range'));
		$('#ppd-date-from').val(dates.from);
		$('#ppd-date-to').val(dates.to);
		ppLoadDashboard();
	});

	var ppDashData = null;
	var ppDashSort = { col: 'soll', dir: 'desc' }; // default sort for tables

	function ppLoadDashboard() {
		$('#ppd-kpis').html('<div style="text-align:center;padding:20px;color:#999;"><i class="bx bx-loader-alt bx-spin"></i></div>');
		$('#ppd-persons, #ppd-plans, #ppd-forecast, #ppd-done').empty();

		var postData = {
			security: ajaxuser.nonce,
			action: 'uf_pp_get_dashboard_stats',
			date_from: $('#ppd-date-from').val() || '',
			date_to: $('#ppd-date-to').val() || '',
			plan_status: $('#ppd-status-filter').val() || '',
			client_id: $('#ppd-client-filter').val() || ''
		};

		$.ajax({
			type: 'POST', url: ajaxuser.url, data: postData,
			success: function (res) {
				if (!res.success) return;
				ppDashData = res.data;
				// Populate person filter from data
				var $pf = $('#ppd-person-filter');
				var curP = $pf.val();
				$pf.html('<option value="">Alle Personen</option>');
				var pNames = Object.keys(ppDashData.by_person).sort();
				pNames.forEach(function (n) {
					$pf.append('<option value="' + escAttr(n) + '">' + escHtml(ppGetKuerzel(n)) + ' – ' + escHtml(n) + '</option>');
				});
				if (curP) $pf.val(curP);
				ppRenderDash();
			}
		});
	}

	// Re-render all dashboard sections (called after filter or sort change)
	function ppRenderDash() {
		ppRenderDashKpis();
		ppRenderDashPersons();
		ppRenderDashPlans();
		ppRenderDashForecast();
		ppRenderDashDone();
	}

	// Person filter changes re-render without reload
	$(document).on('change', '#ppd-person-filter', function () { ppRenderDash(); });

	function ppDashPersonFilter() { return $('#ppd-person-filter').val() || ''; }

	function ppRenderDashKpis() {
		var t = ppDashData.totals;
		var personF = ppDashPersonFilter();

		// If person filter active, recalculate from by_person
		var soll = t.soll, ist = t.ist, done = t.done, total = t.total, open = t.open;
		if (personF && ppDashData.by_person[personF]) {
			var p = ppDashData.by_person[personF];
			soll = p.soll; ist = p.ist; done = p.done; open = p.open; total = done + open;
		}

		var pct = total > 0 ? Math.round((done / total) * 100) : 0;
		var diff = ist - soll;
		var diffClass = diff > 0 ? 'ppd-kpi-neg' : (diff < 0 ? 'ppd-kpi-pos' : '');
		var diffSign = diff > 0 ? '+' : '';
		var diffLabel = diff !== 0 ? diffSign + ppFmtNum(diff) + ' h' : 'Im Plan';

		var h = '';
		h += '<div class="ppd-kpi ppd-kpi-accent"><div class="ppd-kpi-icon"><i class="bx bx-target-lock"></i></div><div class="ppd-kpi-val">' + ppFmtNum(soll) + ' h</div><div class="ppd-kpi-label">Soll</div></div>';
		h += '<div class="ppd-kpi"><div class="ppd-kpi-icon"><i class="bx bx-time-five"></i></div><div class="ppd-kpi-val">' + ppFmtNum(ist) + ' h</div><div class="ppd-kpi-label">Ist</div></div>';
		h += '<div class="ppd-kpi ' + diffClass + '"><div class="ppd-kpi-icon"><i class="bx bx-transfer-alt"></i></div><div class="ppd-kpi-val">' + diffLabel + '</div><div class="ppd-kpi-label">Differenz</div></div>';
		h += '<div class="ppd-kpi"><div class="ppd-kpi-icon"><i class="bx bx-check-double"></i></div><div class="ppd-kpi-val">' + done + '<small class="ppd-muted"> / ' + total + '</small></div><div class="ppd-kpi-label">Erledigt</div></div>';
		h += '<div class="ppd-kpi"><div class="ppd-kpi-icon"><i class="bx bx-loader-circle"></i></div><div class="ppd-kpi-val">' + pct + '%</div><div class="ppd-kpi-label">Fortschritt</div><div class="ppd-kpi-bar"><div class="ppd-kpi-bar-fill" style="width:' + pct + '%;background:' + (pct >= 75 ? '#4caf50' : pct >= 40 ? '#ff9800' : '#e53935') + ';"></div></div></div>';
		$('#ppd-kpis').html(h);
	}

	function ppGetCapMap() {
		var capMap = {};
		if (typeof ppUsersData !== 'undefined') {
			ppUsersData.forEach(function (u) {
				if (u.capacity) capMap[u.name] = u.capacity;
				if (u.kuerzel && u.capacity) capMap[u.kuerzel] = u.capacity;
			});
		}
		var capacity = ppDashData.capacity || {};
		for (var ck in capacity) { capMap[ck] = capacity[ck]; }
		return capMap;
	}
	function ppGetCap(capMap, name) {
		if (capMap[name]) return capMap[name];
		var k = ppGetKuerzel(name);
		return (k && capMap[k]) ? capMap[k] : 0;
	}

	function ppRenderDashPersons() {
		var persons = ppDashData.by_person;
		var personF = ppDashPersonFilter();
		var keys = Object.keys(persons);
		if (personF) keys = keys.filter(function (n) { return n === personF; });
		if (!keys.length) { $('#ppd-persons').html('<p class="ppd-empty">Keine Daten' + (personF ? ' für ' + escHtml(personF) : '') + '</p>'); return; }

		var capMap = ppGetCapMap();

		// Sort by soll desc
		keys.sort(function (a, b) { return persons[b].soll - persons[a].soll; });

		var h = '<table class="ppd-table ppd-table-hover"><thead><tr>';
		h += '<th>Person</th><th class="ppd-r ppd-sortable" data-sort="soll">Soll (h) <i class="bx bx-sort-down"></i></th>';
		h += '<th class="ppd-r ppd-sortable" data-sort="ist">Ist (h)</th>';
		h += '<th class="ppd-r">Kapazität</th><th class="ppd-r">Verfügbar</th>';
		h += '<th style="width:25%;">Auslastung</th><th>Aufgaben</th>';
		h += '</tr></thead><tbody>';

		var totalSoll = 0, totalIst = 0, totalCap = 0, totalDone = 0, totalOpen = 0;
		keys.forEach(function (name) {
			var p = persons[name];
			var kuerzel = ppGetKuerzel(name);
			var cap = ppGetCap(capMap, name);
			var avail = cap > 0 ? cap - p.soll : 0;
			var capPct = cap > 0 ? Math.round(p.soll / cap * 100) : 0;
			var barColor = capPct > 100 ? '#e53935' : (capPct > 80 ? '#ff9800' : 'var(--accent)');

			totalSoll += p.soll; totalIst += p.ist; totalCap += cap;
			totalDone += p.done; totalOpen += p.open;

			h += '<tr class="ppd-person-row" data-name="' + escAttr(name) + '">';
			h += '<td><span class="ppd-avatar">' + escHtml(kuerzel) + '</span> <span class="ppd-name">' + escHtml(name) + '</span></td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtNum(p.soll) + '</td>';
			h += '<td class="ppd-r">' + ppFmtNum(p.ist) + '</td>';
			h += '<td class="ppd-r">' + (cap > 0 ? cap + ' h' : '<span class="ppd-muted">—</span>') + '</td>';
			h += '<td class="ppd-r">' + (cap > 0 ? '<span class="' + (avail < 0 ? 'ppd-neg' : 'ppd-pos') + '">' + ppFmtNum(avail) + ' h</span>' : '<span class="ppd-muted">—</span>') + '</td>';
			h += '<td><div class="ppd-bar-wrap"><div class="ppd-bar" style="width:' + Math.min(capPct, 100) + '%;background:' + barColor + ';"></div>';
			if (capPct > 100) h += '<div class="ppd-bar-overflow" style="width:' + Math.min(capPct - 100, 50) + '%;"></div>';
			h += '</div>' + (cap > 0 ? '<small class="ppd-muted">' + capPct + '%</small>' : '') + '</td>';
			h += '<td><span class="ppd-chip ppd-chip-done">' + p.done + '</span> <span class="ppd-chip ppd-chip-open">' + p.open + '</span></td>';
			h += '</tr>';
		});

		h += '<tr class="ppd-total-row"><td><strong>Gesamt (' + keys.length + ')</strong></td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalSoll) + '</td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalIst) + '</td>';
		h += '<td class="ppd-r ppd-bold">' + (totalCap > 0 ? totalCap + ' h' : '—') + '</td>';
		h += '<td class="ppd-r ppd-bold">' + (totalCap > 0 ? ppFmtNum(totalCap - totalSoll) + ' h' : '—') + '</td>';
		h += '<td></td><td><span class="ppd-chip ppd-chip-done">' + totalDone + '</span> <span class="ppd-chip ppd-chip-open">' + totalOpen + '</span></td></tr>';

		h += '</tbody></table>';

		// If person filtered: show task detail list
		var personF = ppDashPersonFilter();
		if (personF && ppDashData.person_tasks && ppDashData.person_tasks[personF]) {
			var tasks = ppDashData.person_tasks[personF];
			h += '<div class="ppd-person-detail">';
			h += '<h4 style="margin:16px 0 8px;font-size:13px;color:#555;"><i class="bx bx-list-check"></i> Aufgaben von ' + escHtml(ppGetKuerzel(personF)) + ' <small class="ppd-muted">(' + tasks.length + ')</small></h4>';
			h += '<table class="ppd-table ppd-table-hover"><thead><tr>';
			h += '<th>Plan / Kunde</th><th>Aufgabe</th><th>Rolle</th><th class="ppd-r">Soll</th><th class="ppd-r">Ist</th><th>Status</th>';
			h += '</tr></thead><tbody>';

			tasks.forEach(function (t) {
				var roleLabel = t.role === 'lead' ? '<span class="ppd-chip" style="background:#fff3e0;color:#e65100;">HV</span>' : '<span class="ppd-chip" style="background:#f5f5f5;color:#888;">Umsetzung</span>';
				var statusLabel = t.done ? '<span style="color:#4caf50;">&#10003;</span>' : '<span class="ppd-muted">offen</span>';
				var rowClass = t.done ? ' style="opacity:0.5;"' : '';
				h += '<tr' + rowClass + '>';
				h += '<td><span class="ppd-color-dot" style="background:' + t.color + ';"></span><small>' + escHtml(t.plan) + '</small></td>';
				h += '<td>' + escHtml(t.description) + '</td>';
				h += '<td>' + roleLabel + '</td>';
				h += '<td class="ppd-r">' + (t.soll ? ppFmtNum(t.soll) : '<span class="ppd-muted">—</span>') + '</td>';
				h += '<td class="ppd-r">' + (t.ist ? ppFmtNum(t.ist) : '<span class="ppd-muted">—</span>') + '</td>';
				h += '<td>' + statusLabel + '</td>';
				h += '</tr>';
			});

			h += '</tbody></table></div>';
		}

		// Also check for duplicate names (similar names that might be the same person)
		if (!personF) {
			var allNames = Object.keys(persons);
			var dupes = [];
			for (var i = 0; i < allNames.length; i++) {
				for (var j = i + 1; j < allNames.length; j++) {
					var a = allNames[i].toLowerCase().trim();
					var b = allNames[j].toLowerCase().trim();
					// Check: same after trim, or one contains the other, or levenshtein-ish
					if (a === b || a.indexOf(b) > -1 || b.indexOf(a) > -1) {
						dupes.push([allNames[i], allNames[j]]);
					}
				}
			}
			if (dupes.length) {
				h += '<div class="ppd-dupes-warn"><i class="bx bx-error-circle"></i> <strong>Mögliche Duplikate:</strong> ';
				dupes.forEach(function (d) {
					h += '<span class="ppd-dupe-pair">"' + escHtml(d[0]) + '" / "' + escHtml(d[1]) + '"</span> ';
				});
				h += '<small class="ppd-muted">— Auf eine Person klicken um deren Aufgaben zu sehen und Zuordnung zu prüfen.</small></div>';
			}
		}

		$('#ppd-persons').html(h);
	}

	// Click person row → set person filter
	$(document).on('click', '.ppd-person-row', function () {
		var name = $(this).data('name');
		$('#ppd-person-filter').val(name);
		ppRenderDash();
	});

	function ppRenderDashPlans() {
		var plans = ppDashData.by_plan;
		var keys = Object.keys(plans);
		if (!keys.length) { $('#ppd-plans').html('<p class="ppd-empty">Keine Daten</p>'); return; }

		keys.sort(function (a, b) { return plans[b].soll - plans[a].soll; });

		var h = '<table class="ppd-table ppd-table-hover"><thead><tr><th>Plan</th><th class="ppd-r">Soll (h)</th><th class="ppd-r">Ist (h)</th><th class="ppd-r">Diff</th><th style="width:25%;">Fortschritt</th><th class="ppd-r">Aufgaben</th></tr></thead><tbody>';
		var totalSoll = 0, totalIst = 0, totalDone = 0, totalAll = 0;

		keys.forEach(function (pid) {
			var pl = plans[pid];
			var total = pl.done + pl.open;
			var pct = total > 0 ? Math.round(pl.done / total * 100) : 0;
			var diff = pl.ist - pl.soll;
			totalSoll += pl.soll; totalIst += pl.ist; totalDone += pl.done; totalAll += total;

			var pctColor = pct >= 75 ? '#4caf50' : (pct >= 40 ? '#ff9800' : '#e53935');
			h += '<tr>';
			h += '<td><span class="ppd-color-dot" style="background:' + pl.color + ';"></span>' + escHtml(pl.title) + '</td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtNum(pl.soll) + '</td>';
			h += '<td class="ppd-r">' + ppFmtNum(pl.ist) + '</td>';
			h += '<td class="ppd-r"><span class="' + (diff > 0 ? 'ppd-neg' : 'ppd-pos') + '">' + (diff > 0 ? '+' : '') + ppFmtNum(diff) + '</span></td>';
			h += '<td><div class="ppd-progress"><div class="ppd-progress-fill" style="width:' + pct + '%;background:' + pctColor + ';"></div></div><small class="ppd-muted">' + pct + '%</small></td>';
			h += '<td class="ppd-r"><span class="ppd-chip ppd-chip-done">' + pl.done + '</span><span class="ppd-muted"> / ' + total + '</span></td>';
			h += '</tr>';
		});

		var totalDiff = totalIst - totalSoll;
		h += '<tr class="ppd-total-row"><td><strong>' + keys.length + ' Pläne</strong></td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalSoll) + '</td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalIst) + '</td>';
		h += '<td class="ppd-r ppd-bold"><span class="' + (totalDiff > 0 ? 'ppd-neg' : 'ppd-pos') + '">' + (totalDiff > 0 ? '+' : '') + ppFmtNum(totalDiff) + '</span></td>';
		h += '<td></td><td class="ppd-r"><span class="ppd-chip ppd-chip-done">' + totalDone + '</span><span class="ppd-muted"> / ' + totalAll + '</span></td></tr>';
		h += '</tbody></table>';
		$('#ppd-plans').html(h);
	}

	function ppRenderDashForecast() {
		var forecast = ppDashData.forecast;
		var capacity = ppDashData.capacity || {};
		var months = Object.keys(forecast);
		if (!months.length) { $('#ppd-forecast').html('<p class="ppd-empty">Keine Forecast-Daten (Pläne benötigen Zeitraum Von/Bis)</p>'); return; }

		// Collect all person names across all months
		var allPersons = {};
		months.forEach(function (m) {
			for (var name in forecast[m]) {
				allPersons[name] = true;
			}
		});
		var personKeys = Object.keys(allPersons).sort();

		// Build capacity map
		var capMap = {};
		if (typeof ppUsersData !== 'undefined') {
			ppUsersData.forEach(function (u) {
				if (u.capacity) { capMap[u.name] = u.capacity; if (u.kuerzel) capMap[u.kuerzel] = u.capacity; }
			});
		}
		for (var ck in capacity) { capMap[ck] = capacity[ck]; }
		function getCapacity(name) {
			if (capMap[name]) return capMap[name];
			var k = ppGetKuerzel(name);
			return (k && capMap[k]) ? capMap[k] : 0;
		}

		// Month names
		var monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
		function fmtMonth(ym) {
			var parts = ym.split('-');
			return monthNames[parseInt(parts[1]) - 1] + ' ' + parts[0];
		}

		var h = '<div class="ppd-forecast-scroll"><table class="ppd-table ppd-forecast-table"><thead><tr><th>Person</th>';
		months.forEach(function (m) { h += '<th class="ppd-r">' + fmtMonth(m) + '</th>'; });
		h += '<th class="ppd-r">Gesamt</th></tr></thead><tbody>';

		// Per-person rows
		personKeys.forEach(function (name) {
			var label = name === '_unassigned' ? '<em class="ppd-muted">Nicht zugewiesen</em>' : '<strong>' + escHtml(ppGetKuerzel(name)) + '</strong>';
			var cap = getCapacity(name);
			var rowTotal = 0;

			h += '<tr><td>' + label + '</td>';
			months.forEach(function (m) {
				var val = forecast[m][name] ? forecast[m][name].soll : 0;
				rowTotal += val;
				var cellClass = '';
				if (cap > 0 && val > cap) cellClass = ' class="ppd-over-cap"';
				h += '<td class="ppd-r"' + (cellClass ? ' style="color:#e53935;font-weight:600;"' : '') + '>' + (val > 0 ? ppFmtNum(val) : '<span class="ppd-muted">—</span>') + '</td>';
			});
			h += '<td class="ppd-r ppd-bold">' + ppFmtNum(rowTotal) + '</td></tr>';
		});

		// Total row per month
		h += '<tr class="ppd-total-row"><td><strong>Gesamt</strong></td>';
		var grandTotal = 0;
		months.forEach(function (m) {
			var mTotal = 0;
			for (var name in forecast[m]) { mTotal += forecast[m][name].soll; }
			grandTotal += mTotal;
			h += '<td class="ppd-r ppd-bold">' + ppFmtNum(mTotal) + '</td>';
		});
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(grandTotal) + '</td></tr>';

		// Capacity row (if any capacity defined)
		var hasCap = personKeys.some(function (n) { return getCapacity(n) > 0; });
		if (hasCap) {
			h += '<tr class="ppd-cap-row"><td><strong>Kapazität</strong></td>';
			months.forEach(function (m) {
				var mCap = 0;
				personKeys.forEach(function (name) {
					if (name !== '_unassigned') mCap += getCapacity(name);
				});
				h += '<td class="ppd-r">' + (mCap > 0 ? mCap + ' h' : '—') + '</td>';
			});
			h += '<td class="ppd-r"></td></tr>';

			// Available row
			h += '<tr class="ppd-avail-row"><td><strong>Verfügbar</strong></td>';
			months.forEach(function (m) {
				var mTotal = 0;
				for (var name in forecast[m]) { mTotal += forecast[m][name].soll; }
				var mCap = 0;
				personKeys.forEach(function (name) {
					if (name !== '_unassigned') mCap += getCapacity(name);
				});
				var avail = mCap - mTotal;
				h += '<td class="ppd-r" style="color:' + (avail < 0 ? '#e53935' : '#4caf50') + ';font-weight:600;">' + (mCap > 0 ? ppFmtNum(avail) + ' h' : '—') + '</td>';
			});
			h += '<td class="ppd-r"></td></tr>';
		}

		h += '</tbody></table></div>';
		$('#ppd-forecast').html(h);
	}

	function ppRenderDashDone() {
		var tasks = ppDashData.done_tasks || [];
		var personF = ppDashPersonFilter();
		if (personF) {
			tasks = tasks.filter(function (t) {
				return (t.responsible || '').split(',').map(function (n) { return n.trim(); }).indexOf(personF) > -1;
			});
		}
		if (!tasks.length) { $('#ppd-done').html('<p class="ppd-empty">Keine erledigten Aufgaben' + (personF ? ' für ' + escHtml(personF) : '') + '</p>'); return; }

		var h = '<table class="ppd-table ppd-table-hover"><thead><tr><th>Aufgabe</th><th>Plan</th><th>Verantwortlich</th><th class="ppd-r">Soll (h)</th><th class="ppd-r">Ist (h)</th><th class="ppd-r">Diff</th></tr></thead><tbody>';
		var totalSoll = 0, totalIst = 0;

		tasks.forEach(function (t) {
			totalSoll += t.soll;
			totalIst += t.ist;
			var diff = t.ist - t.soll;
			h += '<tr>';
			h += '<td>' + escHtml(t.description || '—') + '</td>';
			h += '<td><span class="ppd-color-dot" style="background:' + t.color + ';"></span><small>' + escHtml(t.plan) + '</small></td>';
			h += '<td>' + escHtml(t.responsible || '—') + '</td>';
			h += '<td class="ppd-r">' + ppFmtNum(t.soll) + '</td>';
			h += '<td class="ppd-r">' + ppFmtNum(t.ist) + '</td>';
			h += '<td class="ppd-r"><span class="' + (diff > 0 ? 'ppd-neg' : 'ppd-pos') + '">' + (diff > 0 ? '+' : '') + ppFmtNum(diff) + '</span></td>';
			h += '</tr>';
		});

		var totalDiff = totalIst - totalSoll;
		h += '<tr class="ppd-total-row"><td><strong>' + tasks.length + ' Aufgaben</strong></td><td></td><td></td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalSoll) + '</td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(totalIst) + '</td>';
		h += '<td class="ppd-r ppd-bold"><span class="' + (totalDiff > 0 ? 'ppd-neg' : 'ppd-pos') + '">' + (totalDiff > 0 ? '+' : '') + ppFmtNum(totalDiff) + '</span></td></tr>';
		h += '</tbody></table>';
		$('#ppd-done').html(h);
	}

	// ========================================
	// BUDGET / SOLL-IST ABGLEICH
	// ========================================

	var ppBudgetMonthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
	var ppBudgetCycle = 'quarterly';
	var ppHoursPerTs = 8;

	// Customer-friendly rounding: up to 4h remainder = round down, then 0.5 steps
	function ppHoursToTsGlobal(hours) {
		if (!hours || hours <= 0) return 0;
		var fullDays = Math.floor(hours / ppHoursPerTs);
		var remainder = hours - (fullDays * ppHoursPerTs);
		if (remainder < (ppHoursPerTs / 2)) return fullDays;
		return fullDays + 0.5;
	}

	function ppFmtTsGlobal(ts) {
		if (!ts && ts !== 0) return '—';
		return ts % 1 === 0 ? ts.toFixed(0) : ts.toFixed(1).replace('.', ',');
	}

	// Cycle switch — re-render without reload
	$(document).on('click', '.ppb-cycle-btn', function () {
		ppBudgetCycle = $(this).data('cycle');
		$('.ppb-cycle-btn').removeClass('active');
		$(this).addClass('active');
		// Re-render from cached data
		var $container = $(this).closest('#ppb-modal, #ppd-budget');
		if ($container.length && $container.data('ppb-data')) {
			var editable = $container.attr('id') === 'ppb-modal';
			ppRenderBudgetTable($container.data('ppb-data'), '#' + $container.attr('id'), editable);
		}
	});

	function ppRenderBudgetTable(data, container, editable) {
		// Cache data for cycle re-render
		$(container).data('ppb-data', data);
		var d = data;
		var hpt = d.hours_per_ts || 8;
		var clientId = d.client_id || 0;
		var clientTitle = d.client ? d.client.title : 'Alle Kunden';
		var h = '';

		// Header
		if (d.client) {
			h += '<div class="ppb-client-label"><i class="bx bx-building-house"></i> <strong>' + escHtml(clientTitle) + '</strong> — Soll/Ist Übersicht</div>';
		}

		// Year switcher
		h += '<div class="ppb-year-bar">';
		(d.years || []).forEach(function (y) {
			h += '<button class="ppb-year-btn' + (y == d.year ? ' active' : '') + '" data-year="' + y + '">' + y + '</button>';
		});
		h += '</div>';

		// Batch input
		if (editable && clientId) {
			h += '<div class="ppb-batch">';
			h += '<span class="ppb-batch-label">Soll setzen:</span> ';
			h += '<input type="number" id="ppb-batch-ts" class="ppb-soll-input" step="0.5" min="0" placeholder="TS">';
			h += '<button type="button" class="ppb-batch-btn ppb-batch-q" data-months="1,2,3,4,5,6,7,8,9,10,11,12">Ganzes Jahr</button>';
			h += '<button type="button" class="ppb-batch-btn ppb-batch-q" data-months="1,2,3">Q1</button>';
			h += '<button type="button" class="ppb-batch-btn ppb-batch-q" data-months="4,5,6">Q2</button>';
			h += '<button type="button" class="ppb-batch-btn ppb-batch-q" data-months="7,8,9">Q3</button>';
			h += '<button type="button" class="ppb-batch-btn ppb-batch-q" data-months="10,11,12">Q4</button>';
			h += '</div>';
		}

		// Übertrag (carry-over) section
		var uebertrag = d.client ? (d.client.uebertrag_ts || 0) : 0;
		var uebertragNotiz = d.client ? (d.client.uebertrag_notiz || '') : '';
		var abrModus = d.client ? (d.client.abrechnungsmodus || 'ts') : 'ts';

		if (editable && clientId) {
			h += '<div class="ppb-uebertrag">';
			h += '<div class="ppb-uebertrag-row">';
			h += '<span class="ppb-uebertrag-label"><i class="bx bx-transfer-alt"></i> Übertrag:</span>';
			h += '<input type="number" id="ppb-uebertrag-val" class="ppb-soll-input" step="0.5" value="' + (uebertrag || '') + '" placeholder="0" title="+Überhang / -Rückstand">';
			h += '<span style="font-size:12px;color:#888;">TS</span>';
			h += '<input type="text" id="ppb-uebertrag-notiz" class="ppb-soll-input" value="' + escAttr(uebertragNotiz) + '" placeholder="Notiz (z.B. Übertrag 2025)" style="flex:1;width:auto !important;">';
			h += '<select id="ppb-abr-modus" class="ppb-soll-input" style="width:auto !important;">';
			h += '<option value="ts"' + (abrModus === 'ts' ? ' selected' : '') + '>Abrechnung in TS</option>';
			h += '<option value="stunden"' + (abrModus === 'stunden' ? ' selected' : '') + '>Abrechnung nach Aufwand (h)</option>';
			h += '<option value="keine"' + (abrModus === 'keine' ? ' selected' : '') + '>Keine Abrechnung</option>';
			h += '</select>';
			h += '<button type="button" id="ppb-uebertrag-save" class="ppb-batch-btn">Speichern</button>';
			h += '</div>';
			if (uebertrag !== 0) {
				var ueClass = uebertrag > 0 ? 'ppd-pos' : 'ppd-neg';
				var ueLabel = uebertrag > 0 ? '+' + ppFmtTsGlobal(uebertrag) + ' TS Überhang (abbummeln)' : ppFmtTsGlobal(uebertrag) + ' TS Rückstand (aufholen)';
				h += '<div class="ppb-uebertrag-info"><span class="' + ueClass + '">' + ueLabel + '</span>';
				if (uebertragNotiz) h += ' <small class="ppd-muted">— ' + escHtml(uebertragNotiz) + '</small>';
				h += '</div>';
			}
			h += '</div>';
		} else if (uebertrag !== 0) {
			var ueClass = uebertrag > 0 ? 'ppd-pos' : 'ppd-neg';
			var ueLabel = uebertrag > 0 ? '+' + ppFmtTsGlobal(uebertrag) + ' TS Überhang' : ppFmtTsGlobal(uebertrag) + ' TS Rückstand';
			h += '<div class="ppb-uebertrag"><div class="ppb-uebertrag-info"><i class="bx bx-transfer-alt"></i> Übertrag: <span class="' + ueClass + '">' + ueLabel + '</span>';
			if (uebertragNotiz) h += ' <small class="ppd-muted">— ' + escHtml(uebertragNotiz) + '</small>';
			h += '</div></div>';
		}

		// Round hours to customer-friendly TS: up to 4h extra = round down, then 0.5 steps
		function ppHoursToTs(hours) {
			if (!hours || hours <= 0) return 0;
			var fullDays = Math.floor(hours / hpt);
			var remainder = hours - (fullDays * hpt);
			if (remainder < (hpt / 2)) return fullDays;
			return fullDays + 0.5;
		}

		function ppFmtTs(ts) {
			if (!ts && ts !== 0) return '—';
			return ts % 1 === 0 ? ts.toFixed(0) : ts.toFixed(1).replace('.', ',');
		}

		// Billing cycle selector
		var cycle = ppBudgetCycle || 'quarterly';
		h += '<div class="ppb-cycle-bar">';
		h += '<span class="ppb-cycle-label">Abrechnungszyklus:</span>';
		var cycleOpts = [['monthly','Monatlich'],['bimonthly','2-monatlich'],['quarterly','Quartalsweise'],['halfyear','Halbjährlich'],['yearly','Jährlich']];
		cycleOpts.forEach(function (o) {
			h += '<button class="ppb-cycle-btn' + (cycle === o[0] ? ' active' : '') + '" data-cycle="' + o[0] + '">' + o[1] + '</button>';
		});
		h += '</div>';

		// Build billing periods based on cycle
		var periods = [];
		if (cycle === 'monthly') {
			for (var i = 1; i <= 12; i++) periods.push({ label: ppBudgetMonthNames[i-1], months: [i] });
		} else if (cycle === 'bimonthly') {
			for (var i = 1; i <= 12; i += 2) periods.push({ label: ppBudgetMonthNames[i-1] + '–' + ppBudgetMonthNames[i], months: [i, i+1] });
		} else if (cycle === 'quarterly') {
			periods = [
				{ label: 'Q1 (Jan–Mär)', months: [1,2,3] },
				{ label: 'Q2 (Apr–Jun)', months: [4,5,6] },
				{ label: 'Q3 (Jul–Sep)', months: [7,8,9] },
				{ label: 'Q4 (Okt–Dez)', months: [10,11,12] },
			];
		} else if (cycle === 'halfyear') {
			periods = [
				{ label: 'H1 (Jan–Jun)', months: [1,2,3,4,5,6] },
				{ label: 'H2 (Jul–Dez)', months: [7,8,9,10,11,12] },
			];
		} else {
			periods = [{ label: d.year + ' Gesamt', months: [1,2,3,4,5,6,7,8,9,10,11,12] }];
		}

		// Table
		h += '<table class="ppd-table ppb-table"><thead><tr>';
		h += '<th>Zeitraum</th>';
		h += '<th class="ppd-r">Soll (TS)</th>';
		h += '<th class="ppd-r">Ist (h)</th>';
		h += '<th class="ppd-r">Ist (TS)</th>';
		h += '<th class="ppd-r">Diff (TS)</th>';
		h += '<th style="width:15%;">Status</th>';
		h += '</tr></thead><tbody>';

		// Soll input rows (monthly, always shown for editable mode)
		if (editable) {
			h += '<tr class="ppb-soll-header"><td colspan="6" style="background:#f0f0f0;font-size:11px;font-weight:600;color:#888;padding:6px 8px;">Soll pro Monat (TS)</td></tr>';
			for (var m = 1; m <= 12; m++) {
				var md = d.months[m] || {};
				h += '<tr class="ppb-soll-row" data-month="' + m + '">';
				h += '<td style="padding-left:16px;color:#888;font-size:12px;">' + ppBudgetMonthNames[m-1] + '</td>';
				h += '<td class="ppd-r"><input type="number" class="ppb-soll-input ppb-client-soll" data-month="' + m + '" value="' + (md.soll_ts || '') + '" step="0.5" min="0" placeholder="—"></td>';
				// Ist override
				var istVal = md.ist_manual ? md.ist_h : '';
				var istPh = ppFmtNum(md.ist_calc || 0) + ' (auto)';
				h += '<td class="ppd-r"><input type="number" class="ppb-soll-input ppb-ist-input' + (md.ist_manual ? ' ppb-ist-manual' : '') + '" data-month="' + m + '" value="' + istVal + '" step="0.5" min="0" placeholder="' + istPh + '" title="' + (md.ist_note ? escAttr(md.ist_note) : 'Leer = auto') + '">';
				if (md.ist_manual) h += '<button class="ppb-clear-ist" data-month="' + m + '" title="Auto"><i class="bx bx-revision"></i></button>';
				h += '</td>';
				h += '<td colspan="3"></td></tr>';
			}
			h += '<tr><td colspan="6" style="height:8px;"></td></tr>';
		}

		// Determine current month for past/current/future detection
		var nowY = new Date().getFullYear();
		var nowM = new Date().getMonth() + 1; // 1-12

		// Period summary rows
		h += '<tr class="ppb-soll-header"><td colspan="6" style="background:#f0f0f0;font-size:11px;font-weight:600;color:#888;padding:6px 8px;">Abrechnung (' + cycleOpts.find(function(o){return o[0]===cycle;})[1] + ')</td></tr>';

		var ySollTs = 0, yIstH = 0, yIstTs = 0; // nur bis inkl. laufende Periode
		var yPlanSollTs = 0, yPlanIstH = 0, yPlanIstTs = 0; // Gesamtjahr inkl. Zukunft
		periods.forEach(function (period) {
			var pSollTs = 0, pIstH = 0;
			period.months.forEach(function (m) {
				var md = d.months[m] || {};
				pSollTs += md.soll_ts || 0;
				pIstH += md.ist_h || 0;
			});
			var pIstTs = ppHoursToTs(pIstH);
			var pDiffTs = pIstTs - pSollTs;

			// Determine period status
			var lastMonth = period.months[period.months.length - 1];
			var firstMonth = period.months[0];
			var isFuture = d.year > nowY || (d.year == nowY && firstMonth > nowM);
			var isPast = d.year < nowY || (d.year == nowY && lastMonth < nowM);
			var isCurrent = !isFuture && !isPast;

			// Only count past + current in running totals
			if (!isFuture) {
				ySollTs += pSollTs; yIstH += pIstH; yIstTs += pIstTs;
			}
			yPlanSollTs += pSollTs; yPlanIstH += pIstH; yPlanIstTs += pIstTs;

			var barPct = pSollTs > 0 ? Math.min(Math.round(pIstTs / pSollTs * 100), 150) : 0;
			var barColor = barPct > 100 ? '#e53935' : (barPct > 80 ? '#ff9800' : '#4caf50');
			var diffClass = pDiffTs > 0 ? 'ppd-pos' : (pDiffTs < 0 ? 'ppd-neg' : '');

			var rowClass = isFuture ? ' class="ppb-future-row"' : (isCurrent ? ' class="ppb-current-row"' : '');
			var statusTag = isFuture ? ' <small class="ppb-tag ppb-tag-future">Planung</small>' : (isCurrent ? ' <small class="ppb-tag ppb-tag-current">Laufend</small>' : '');

			h += '<tr' + rowClass + '>';
			h += '<td><strong>' + period.label + '</strong>' + statusTag + '</td>';
			h += '<td class="ppd-r ppd-bold">' + (pSollTs ? ppFmtTs(pSollTs) : '<span class="ppd-muted">—</span>') + '</td>';
			h += '<td class="ppd-r">' + (pIstH ? ppFmtNum(pIstH) : '<span class="ppd-muted">—</span>') + '</td>';
			h += '<td class="ppd-r ppd-bold">' + (pIstTs ? ppFmtTs(pIstTs) : '<span class="ppd-muted">—</span>') + '</td>';
			if (isFuture) {
				h += '<td class="ppd-r"><span class="ppd-muted">—</span></td>';
			} else {
				h += '<td class="ppd-r"><span class="' + diffClass + '">' + (pDiffTs !== 0 ? (pDiffTs > 0 ? '+' : '') + ppFmtTs(pDiffTs) + ' TS' : '—') + '</span></td>';
			}
			h += '<td><div class="ppd-progress" style="height:6px;"><div class="ppd-progress-fill" style="width:' + (isFuture ? 0 : Math.min(barPct, 100)) + '%;background:' + barColor + ';"></div></div></td>';
			h += '</tr>';
		});

		// Aktuelle Bilanz (nur abgeschlossene + laufende Perioden)
		var yDiffTs = yIstTs - ySollTs;
		h += '<tr class="ppd-total-row"><td><strong>Bilanz (bis heute)</strong></td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtTs(ySollTs) + ' TS</td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtNum(yIstH) + ' h</td>';
		h += '<td class="ppd-r ppd-bold">' + ppFmtTs(yIstTs) + ' TS</td>';
		h += '<td class="ppd-r ppd-bold"><span class="' + (yDiffTs > 0 ? 'ppd-pos' : (yDiffTs < 0 ? 'ppd-neg' : '')) + '">' + (yDiffTs !== 0 ? (yDiffTs > 0 ? '+' : '') + ppFmtTs(yDiffTs) + ' TS' : 'ausgeglichen') + '</span></td>';
		h += '<td></td></tr>';

		// Gesamtjahr (inkl. Zukunft) — als Planung gekennzeichnet
		if (yPlanSollTs !== ySollTs) {
			h += '<tr class="ppb-plan-row"><td><strong>' + d.year + ' Gesamt</strong> <small class="ppb-tag ppb-tag-future">inkl. Planung</small></td>';
			h += '<td class="ppd-r">' + ppFmtTs(yPlanSollTs) + ' TS</td>';
			h += '<td class="ppd-r">' + ppFmtNum(yPlanIstH) + ' h</td>';
			h += '<td class="ppd-r">' + ppFmtTs(yPlanIstTs) + ' TS</td>';
			h += '<td class="ppd-r ppd-muted">—</td>';
			h += '<td></td></tr>';
		}

		// Effektives Budget (Soll + Übertrag)
		if (uebertrag !== 0) {
			var effTs = ySollTs + uebertrag;
			var effDiffTs = yIstTs - effTs;
			h += '<tr class="ppb-eff-row"><td><strong>Effektiv (inkl. Übertrag)</strong></td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtTs(effTs) + ' TS</td>';
			h += '<td class="ppd-r"></td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtTs(yIstTs) + ' TS</td>';
			h += '<td class="ppd-r ppd-bold"><span class="' + (effDiffTs > 0 ? 'ppd-pos' : (effDiffTs < 0 ? 'ppd-neg' : '')) + '">' + (effDiffTs > 0 ? '+' : '') + ppFmtTs(effDiffTs) + ' TS</span></td>';
			h += '<td></td></tr>';
		}

		// All-time total
		if (d.total_all) {
			var ta = d.total_all;
			var taIstTs = ppHoursToTs(ta.ist_h);
			var taDiffTs = taIstTs - ta.soll_ts;
			h += '<tr class="ppb-alltime-row"><td><strong>Gesamt (alle Jahre)</strong></td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtTs(ta.soll_ts) + ' TS</td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtNum(ta.ist_h) + ' h</td>';
			h += '<td class="ppd-r ppd-bold">' + ppFmtTs(taIstTs) + ' TS</td>';
			h += '<td class="ppd-r ppd-bold"><span class="' + (taDiffTs > 0 ? 'ppd-pos' : 'ppd-neg') + '">' + (taDiffTs > 0 ? '+' : '') + ppFmtTs(taDiffTs) + ' TS</span></td>';
			h += '<td></td></tr>';
		}

		h += '</tbody></table>';
		h += '<p style="font-size:10px;color:#aaa;margin-top:6px;">1 TS = ' + hpt + ' h · Stunden werden pro Abrechnungszeitraum summiert, dann gerundet (bis ' + (hpt/2) + 'h Rest = abgerundet)</p>';

		$(container).html(h);
	}

	// Budget button on plan header → client-based modal
	$(document).on('click', '#pp-budget-btn', function () {
		if (!ppCurrentPlanId || !ppCurrentPlan) return;
		var clientTitle = ppCurrentPlan.client_short || ppCurrentPlan.client_title || 'Kunde';

		Swal.fire({
			title: 'Soll/Ist – ' + clientTitle,
			width: 700,
			html: '<div id="ppb-modal" style="text-align:left;"><div style="text-align:center;padding:20px;color:#999;"><i class="bx bx-loader-alt bx-spin"></i></div></div>',
			showConfirmButton: false,
			showCancelButton: true,
			cancelButtonText: 'Schließen',
			didOpen: function () { ppLoadBudgetModal(ppCurrentPlanId, new Date().getFullYear()); }
		}).then(function () {
			// Refresh budget stats after modal closes
			ppCalculateSubtotals();
		});
	});

	function ppLoadBudgetModal(planId, year) {
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_budget', plan_id: planId, year: year },
			success: function (res) {
				if (!res.success) return;
				var d = res.data;
				var clientId = d.client_id || 0;
				ppRenderBudgetTable(d, '#ppb-modal', true);

				// Year switch
				$('#ppb-modal').off('click', '.ppb-year-btn').on('click', '.ppb-year-btn', function () {
					ppLoadBudgetModal(planId, $(this).data('year'));
				});

				// Übertrag save
				$('#ppb-modal').off('click', '#ppb-uebertrag-save').on('click', '#ppb-uebertrag-save', function () {
					if (!clientId) return;
					var ue = parseFloat($('#ppb-uebertrag-val').val()) || 0;
					var notiz = $('#ppb-uebertrag-notiz').val().trim();
					var modus = $('#ppb-abr-modus').val();
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_save_uebertrag', client_id: clientId, uebertrag_ts: ue, notiz: notiz, abrechnungsmodus: modus },
						success: function () { ppLoadBudgetModal(planId, year); toastr.success('Übertrag gespeichert'); }
					});
				});

				// Soll save (client-level, per month)
				$('#ppb-modal').off('change', '.ppb-client-soll').on('change', '.ppb-client-soll', function () {
					if (!clientId) return;
					var month = $(this).data('month');
					var val = parseFloat($(this).val()) || 0;
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_save_client_budget', client_id: clientId, year: year, month: month, soll_ts: val },
						success: function () { ppLoadBudgetModal(planId, year); }
					});
				});

				// Ist override save — inline note prompt instead of Swal (which would close the modal)
				$('#ppb-modal').off('change', '.ppb-ist-input').on('change', '.ppb-ist-input', function () {
					if (!clientId) return;
					var $inp = $(this);
					var month = $inp.data('month');
					var val = $inp.val();
					if (val === '' || val === null) return;
					// Show inline note input next to the field
					var $tr = $inp.closest('tr');
					$('.ppb-note-inline').remove();
					var noteHtml = '<tr class="ppb-note-inline"><td></td><td colspan="5" style="padding:4px 8px;"><div style="display:flex;gap:4px;align-items:center;">' +
						'<input type="text" class="ppb-soll-input ppb-note-field" placeholder="Grund/Hinweis (optional, Enter zum Speichern)" style="flex:1;">' +
						'<button type="button" class="ppb-batch-btn ppb-note-save">Speichern</button>' +
						'<button type="button" class="ppb-batch-btn ppb-note-cancel">Abbrechen</button></div></td></tr>';
					$tr.after(noteHtml);
					$tr.next('.ppb-note-inline').find('.ppb-note-field').focus();

					function saveIst(note) {
						$('.ppb-note-inline').remove();
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_pp_save_ist_override', client_id: clientId, year: year, month: month, ist_h: val, ist_note: note },
							success: function () { ppLoadBudgetModal(planId, year); toastr.success('Ist gespeichert'); }
						});
					}

					$('#ppb-modal').off('click', '.ppb-note-save').on('click', '.ppb-note-save', function () {
						saveIst($('.ppb-note-field').val().trim());
					});
					$('#ppb-modal').off('keydown', '.ppb-note-field').on('keydown', '.ppb-note-field', function (e) {
						if (e.key === 'Enter') { e.preventDefault(); saveIst($(this).val().trim()); }
						if (e.key === 'Escape') { $('.ppb-note-inline').remove(); ppLoadBudgetModal(planId, year); }
					});
					$('#ppb-modal').off('click', '.ppb-note-cancel').on('click', '.ppb-note-cancel', function () {
						$('.ppb-note-inline').remove();
						ppLoadBudgetModal(planId, year);
					});
				});

				// Clear Ist override
				$('#ppb-modal').off('click', '.ppb-clear-ist').on('click', '.ppb-clear-ist', function () {
					if (!clientId) return;
					var month = $(this).data('month');
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_save_ist_override', client_id: clientId, year: year, month: month, clear: '1' },
						success: function () { ppLoadBudgetModal(planId, year); }
					});
				});

				// Batch
				$('#ppb-modal').off('click', '.ppb-batch-q').on('click', '.ppb-batch-q', function () {
					if (!clientId) { toastr.error('Kein Kunde zugeordnet'); return; }
					var ts = parseFloat($('#ppb-batch-ts').val());
					if (isNaN(ts)) { toastr.warning('Bitte TS-Wert eingeben'); return; }
					var months = String($(this).data('months')).split(',');
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_save_client_budget_batch', client_id: clientId, year: year, soll_ts: ts, months: months },
						success: function () { ppLoadBudgetModal(planId, year); toastr.success('Budget gespeichert'); }
					});
				});
			}
		});
	}

	// Dashboard Budget tab
	function ppRenderDashBudget() {
		$('#ppd-budget').html('<div style="text-align:center;padding:20px;color:#999;"><i class="bx bx-loader-alt bx-spin"></i></div>');
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_budget', plan_id: 0, client_id: 0, year: ppDashBudgetYear || new Date().getFullYear() },
			success: function (res) {
				if (!res.success) return;
				ppRenderBudgetTable(res.data, '#ppd-budget', false);

				$('#ppd-budget').off('click', '.ppb-year-btn').on('click', '.ppb-year-btn', function () {
					ppDashBudgetYear = $(this).data('year');
					ppRenderDashBudget();
				});
			}
		});
	}
	var ppDashBudgetYear = new Date().getFullYear();

	$(document).on('click', '.ppd-tab[data-tab="budget"]', function () {
		ppRenderDashBudget();
	});

	// Select all visible plans
	$(document).on('click', '#pp-select-all', function () {
		ppSelectedPlanIds = [];
		$('.pp-grid-card:visible').each(function () {
			ppSelectedPlanIds.push($(this).data('id'));
		});
		ppUpdateSelection();
		ppTriggerPlanLoad();
	});

	// Cmd+A to select all visible plans
	$(document).on('keydown', function (e) {
		if (!$('#projektplanner-dashboard').length) return;
		if ((e.metaKey || e.ctrlKey) && e.key === 'a') {
			// Only intercept if not focused in an input/contenteditable
			var tag = (document.activeElement.tagName || '').toLowerCase();
			var isEditing = tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement.isContentEditable;
			if (!isEditing) {
				e.preventDefault();
				$('#pp-select-all').click();
			}
		}
	});

	// Click on feedback banner chip → select that plan
	$(document).on('click', '.pp-fbb-item', function () {
		var planId = $(this).data('planid');
		if (planId) {
			ppSelectedPlanIds = [parseInt(planId)];
			ppUpdateSelection();
			ppSelectPlan(parseInt(planId));
		}
	});

	function ppApplyFontSize(size) {
		$('#pp-table tbody').css('font-size', size + 'px');
		$('#pp-table .pp-cell, #pp-table .pp-input-ghost, #pp-table .pp-resp-tag, #pp-table .pp-resp-input, #pp-table .pp-asana-link-name, #pp-table .pp-asana-btn, #pp-table input[type="date"], #pp-table .pp-section-subtotals').css('font-size', size + 'px');
		$('#pp-font-label').text(size);
	}

	// Font size controls
	$(document).on('click', '#pp-font-up', function () {
		var size = parseInt($('#pp-font-label').text()) || 13;
		if (size >= 20) return;
		size++;
		ppApplyFontSize(size);
		$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: 'tallyr_pp_fontsize', value: size } });
	});

	$(document).on('click', '#pp-font-down', function () {
		var size = parseInt($('#pp-font-label').text()) || 13;
		if (size <= 9) return;
		size--;
		ppApplyFontSize(size);
		$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_save_tallyr_settings', field: 'tallyr_pp_fontsize', value: size } });
	});

	// Toggle sidebar
	$(document).on('click', '#pp-toggle-sidebar', function () {
		$('#userdashmenu').toggle();
		$('.userdashbody #userdashpage').toggleClass('pp-sidebar-collapsed');
		$(this).find('i').toggleClass('bx-sidebar bx-menu');
	});

	// Client filter change
	$(document).on('change', '#pp-client-filter', function () {
		ppSelectedPlanIds = [];
		ppCurrentPlanId = null;
		ppCurrentPlan = null;
		$('#pp-plan-header').hide();
		$('#pp-table-container').hide();
		$('#pp-empty-state').show();
		$('#pp-selected-plans').hide();
		ppLoadPlans();
	});

	// Load plans
	function ppLoadPlans(selectPlanId) {
		var clientId = $('#pp-client-filter').val() || '';
		var ownLoaded = false, sharedLoaded = false, sharedPlans = [];

		function renderPlans() {
			if (!ownLoaded || !sharedLoaded) return;

			// Merge shared plans
			sharedPlans.forEach(function (p) {
				if (clientId && p.client_id != clientId) return;
				p._shared = true;
				ppPlans.push(p);
			});

			// Sort by client short (Kürzel) → title
			ppPlans.sort(function (a, b) {
				var ca = (a.client_short || a.client_title || '').toLowerCase();
				var cb = (b.client_short || b.client_title || '').toLowerCase();
				if (ca !== cb) return ca < cb ? -1 : 1;
				return (a.title || '').toLowerCase() < (b.title || '').toLowerCase() ? -1 : 1;
			});

			ppRenderPlanGrid();

			if (selectPlanId) {
				ppSelectedPlanIds = [selectPlanId];
				ppUpdateSelection();
				ppSelectPlan(selectPlanId);
			}
		}

		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_plans', client_id: clientId },
			success: function (res) {
				ppPlans = res.success ? res.data : [];
				ownLoaded = true;
				renderPlans();
			}
		});
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_shared_plans' },
			success: function (res) {
				sharedPlans = res.success ? res.data : [];
				sharedLoaded = true;
				renderPlans();
			}
		});
	}

	// Select a plan
	var ppSelectedPlanIds = [];

	function ppRenderPlanGrid() {
		var search = ($('#pp-plan-search').val() || '').toLowerCase();
		var statusFilter = $('#pp-status-filter').val() || '';
		var html = '<div class="pp-grid-cards">';

		ppPlans.forEach(function (p) {
			var pStatus = p.plan_status || 'aktiv';
			if (statusFilter && pStatus !== statusFilter) return;
			if (search && (p.title || '').toLowerCase().indexOf(search) === -1 && (p.client_title || '').toLowerCase().indexOf(search) === -1 && (p.client_short || '').toLowerCase().indexOf(search) === -1) return;

			var isSelected = ppSelectedPlanIds.indexOf(p.id) > -1;
			var period = '';
			if (p.period_from && p.period_to) period = ppFormatDate(p.period_from) + ' – ' + ppFormatDate(p.period_to);
			var statusBadge = pStatus !== 'aktiv' ? '<span class="pp-status-badge pp-status-' + pStatus + '">' + pStatus + '</span>' : '';
			var permLabels = { read: 'Lesen', edit: 'Editor', write: 'Vollzugriff' };
			var sharedBadge = p._shared ? '<span class="pp-card-perm pp-perm-' + (p.permission || 'read') + '">' + (permLabels[p.permission] || 'Lesen') + '</span>' : '';
			var clientColor = p.client_color || '#999';
			var clientShort = p.client_short || (p.client_title || '').substring(0, 3).toUpperCase();

			html += '<div class="pp-grid-card' + (isSelected ? ' selected' : '') + '" data-id="' + p.id + '">';
			html += '<div class="pp-grid-card-top"><span class="pp-grid-card-client" style="background:' + clientColor + ';">' + escHtml(clientShort) + '</span>';
			html += '<span class="pp-status-badge pp-status-' + pStatus + '">' + pStatus + '</span>';
			html += sharedBadge + '</div>';
			html += '<div class="pp-grid-card-title">' + escHtml(p.title) + '</div>';
			if (period) html += '<div class="pp-grid-card-period">' + period + '</div>';
			html += '</div>';
		});

		html += '</div>';
		if (ppPlans.length === 0 || html === '<div class="pp-grid-cards"></div>') html = '<span class="pp-no-plans">Keine Pläne gefunden.</span>';
		$('#pp-plans-grid').html(html);
	}

	function ppUpdateSelection() {
		$('.pp-grid-card').each(function () {
			$(this).toggleClass('selected', ppSelectedPlanIds.indexOf($(this).data('id')) > -1);
		});

		// Update chips bar
		if (ppSelectedPlanIds.length > 0) {
			var chips = '';
			ppSelectedPlanIds.forEach(function (pid) {
				var plan = null;
				for (var i = 0; i < ppPlans.length; i++) { if (ppPlans[i].id == pid) { plan = ppPlans[i]; break; } }
				if (!plan) return;
				chips += '<span class="pp-sel-chip" data-id="' + pid + '"><span class="pp-sel-chip-dot" style="background:' + (plan.client_color || '#ccc') + ';"></span>' + escHtml(plan.client_short || '') + ' · ' + escHtml(plan.title) + '<i class="bx bx-x pp-sel-chip-x"></i></span>';
			});
			$('#pp-selected-chips').html(chips);
			$('#pp-selected-plans').show();
		} else {
			$('#pp-selected-plans').hide();
		}
	}

	// Right-click on card → quick status change
	$(document).on('contextmenu', '.pp-grid-card', function (e) {
		e.preventDefault();
		var $card = $(this);
		var planId = $card.data('id');
		var plan = null;
		for (var i = 0; i < ppPlans.length; i++) { if (ppPlans[i].id == planId) { plan = ppPlans[i]; break; } }
		if (!plan) return;

		var currentStatus = plan.plan_status || 'aktiv';
		$('.pp-context-menu').remove();

		var statuses = ['entwurf', 'aktiv', 'einzelprojekt', 'reporting', 'abgeschlossen', 'archiviert'];
		var labels = { entwurf: 'Entwurf', aktiv: 'Aktiv', einzelprojekt: 'Einzelprojekt', reporting: 'Fertig für Reporting', abgeschlossen: 'Abgeschlossen', archiviert: 'Archiviert' };
		var icons = { entwurf: 'bx-pencil', aktiv: 'bx-play-circle', einzelprojekt: 'bx-folder', reporting: 'bx-file', abgeschlossen: 'bx-check-circle', archiviert: 'bx-archive' };

		var menu = '<div class="pp-context-menu">';
		statuses.forEach(function (s) {
			var active = s === currentStatus ? ' pp-ctx-active' : '';
			menu += '<div class="pp-ctx-item' + active + '" data-status="' + s + '" data-plan="' + planId + '"><i class="bx ' + icons[s] + '"></i> ' + labels[s] + '</div>';
		});
		menu += '</div>';

		$(menu).appendTo('body').css({
			position: 'fixed',
			top: e.clientY,
			left: e.clientX,
			zIndex: 99999
		});
	});

	$(document).on('click', '.pp-ctx-item', function () {
		var status = $(this).data('status');
		var planId = $(this).data('plan');
		$('.pp-context-menu').remove();

		// Update local + server
		for (var i = 0; i < ppPlans.length; i++) {
			if (ppPlans[i].id == planId) { ppPlans[i].plan_status = status; break; }
		}
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_save_plan', plan_id: planId, plan_status: status,
				client_id: ppPlans.find(function(p){return p.id==planId;}).client_id || 0,
				title: ppPlans.find(function(p){return p.id==planId;}).title || '',
				period_from: ppPlans.find(function(p){return p.id==planId;}).period_from || '',
				period_to: ppPlans.find(function(p){return p.id==planId;}).period_to || '',
				quarter: '', asana_project_gid: ppPlans.find(function(p){return p.id==planId;}).asana_project_gid || '',
				asana_section_gid: ppPlans.find(function(p){return p.id==planId;}).asana_section_gid || '',
			},
			success: function () { ppRenderPlanGrid(); toastr.success('Status geändert'); }
		});
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pp-context-menu').length) $('.pp-context-menu').remove();
	});

	// Card click: normal = toggle single, Cmd/Ctrl = multi toggle
	$(document).on('click', '.pp-grid-card', function (e) {
		var id = $(this).data('id');
		var isMulti = e.ctrlKey || e.metaKey;
		var idx = ppSelectedPlanIds.indexOf(id);

		if (isMulti) {
			if (idx > -1) ppSelectedPlanIds.splice(idx, 1);
			else ppSelectedPlanIds.push(id);
		} else {
			// Toggle: if already the only selected one, deselect it
			if (ppSelectedPlanIds.length === 1 && idx > -1) {
				ppSelectedPlanIds = [];
			} else {
				ppSelectedPlanIds = [id];
			}
		}
		ppUpdateSelection();
		ppTriggerPlanLoad();
	});

	// Remove chip
	$(document).on('click', '.pp-sel-chip-x', function (e) {
		e.stopPropagation();
		var id = $(this).closest('.pp-sel-chip').data('id');
		ppSelectedPlanIds = ppSelectedPlanIds.filter(function (x) { return x != id; });
		ppUpdateSelection();
		ppTriggerPlanLoad();
	});

	// Filter changes
	$(document).on('change', '#pp-status-filter', function () { ppRenderPlanGrid(); });
	$(document).on('input', '#pp-plan-search', function () { ppRenderPlanGrid(); });

	function ppTriggerPlanLoad() {
		if (ppSelectedPlanIds.length === 0) {
			$('#pp-plan-header').hide();
			$('#pp-table-container').hide();
			$('#pp-empty-state').show();
		} else if (ppSelectedPlanIds.length === 1) {
			ppSelectPlan(ppSelectedPlanIds[0]);
		} else {
			ppLoadMultiplePlans(ppSelectedPlanIds);
		}
	}

	function ppLoadMultiplePlans(planIds) {
		ppCurrentPlanId = null;
		ppCurrentPlan = null;
		ppRows = [];

		// Update header for multi mode
		var planNames = [];
		planIds.forEach(function (pid) {
			for (var i = 0; i < ppPlans.length; i++) {
				if (ppPlans[i].id == pid) { planNames.push(ppPlans[i].client_short + ' · ' + ppPlans[i].title); break; }
			}
		});
		$('#pp-client-badge').text(planIds.length + ' Pläne').css('background', '#666');
		$('#pp-plan-title-display').text(planNames.join(' | '));
		$('#pp-plan-period').text('');

		// Hide edit/delete buttons in multi mode
		$('#pp-edit-plan, #pp-delete-plan, #pp-share-plan, #pp-duplicate-plan, #pp-export-plan, #pp-revisions-btn, #pp-feedback-btn, #pp-budget-btn').hide();
		$('#pp-share-banner').remove();
		$('#pp-plan-header').show();
		$('#pp-table-container').show();
		$('#pp-empty-state').hide();
		$('.pp-add-row-bar').hide();

		// Load all plans' rows
		var loaded = 0;
		var allRows = [];

		planIds.forEach(function (pid) {
			var plan = null;
			for (var i = 0; i < ppPlans.length; i++) { if (ppPlans[i].id == pid) { plan = ppPlans[i]; break; } }

			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_pp_get_rows', plan_id: pid },
				success: function (res) {
					loaded++;
					if (res.success && res.data.rows) {
						// Add a section header for this plan
						allRows.push({
							_planId: pid,
							_planLabel: (plan ? plan.client_short + ' · ' + plan.title : 'Plan ' + pid),
							_planColor: plan ? plan.client_color : '#ccc',
							rows: res.data.rows
						});
					}
					if (loaded === planIds.length) {
						ppRenderMultiPlan(allRows);
					}
				}
			});
		});
	}

	function ppRenderMultiPlan(allPlanRows) {
		// Sort by client name alphabetically
		allPlanRows.sort(function (a, b) {
			return (a._planLabel || '').toLowerCase() < (b._planLabel || '').toLowerCase() ? -1 : 1;
		});

		// Flatten all rows into ppRows so filters work
		ppRows = [];
		allPlanRows.forEach(function (pr) {
			// Add a virtual section for the plan header
			ppRows.push({
				id: 'plan_' + pr._planId, type: 'plan_header',
				_planLabel: pr._planLabel, _planColor: pr._planColor,
				description: pr._planLabel
			});
			pr.rows.forEach(function (row) {
				row._planId = pr._planId;
				ppRows.push(row);
			});
		});

		// Render using the same editable cells as single-plan mode
		var html = '';
		ppRows.forEach(function (row) {
			var id = row.id;
			var planId = row._planId || row.plan_id || '';

			if (row.type === 'plan_header') {
				html += '<tr class="pp-multi-plan-header" data-id="' + id + '" data-type="plan_header"><td colspan="13" style="background:' + (row._planColor || '#666') + ';color:#fff;font-weight:700;padding:8px 12px;font-size:13px;">' + escHtml(row._planLabel || '') + '</td></tr>';
				return;
			}
			if (row.type === 'section') {
				html += '<tr class="pp-section-row" data-id="' + id + '" data-type="section" data-plan="' + planId + '" draggable="true"><td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td><td colspan="10"><div class="pp-cell pp-field" data-field="description" contenteditable="true">' + escHtmlBr(row.description || '') + '</div></td><td class="pp-section-subtotals">Ist <span class="pp-sub-ist">0</span> / Soll <span class="pp-sub-planned">0</span> h</td><td></td></tr>';
				return;
			}
			if (row.type === 'spacer') {
				html += '<tr class="pp-spacer-row" data-id="' + id + '" data-type="spacer" data-plan="' + planId + '"><td colspan="13">&nbsp;</td></tr>';
				return;
			}
			if (row.type === 'note') {
				html += '<tr class="pp-note-row" data-id="' + id + '" data-type="note" data-plan="' + planId + '"><td class="pp-drag-handle"></td><td colspan="11"><div class="pp-cell pp-cell-note pp-field" data-field="description" contenteditable="true">' + escHtmlBr(row.description || '') + '</div></td><td></td></tr>';
				return;
			}
			if (row.type !== 'item') return;

			var isDone = parseInt(row.is_done) === 1;
			var isPlaceholder = parseInt(row.is_placeholder) === 1;
			var doneClass = isDone ? ' pp-row-done' : '';
			var phClass = isPlaceholder ? ' pp-row-placeholder' : '';

			var respTags = ppRenderResponsibleTags(row.responsible || '');
			var hasAsana = row.asana_gid || row.asana_url;
			var asanaHtml = '';
			if (hasAsana) {
				asanaHtml = '<a href="' + escAttr(row.asana_url || '') + '" target="_blank" class="pp-asana-link-name" title="' + escAttr(row.asana_task_name || '') + '">' + escHtml(row.asana_task_name || 'Asana') + '</a>';
				asanaHtml += '<button type="button" class="pp-asana-btn pp-asana-change" title="Ändern"><i class="bx bx-pencil"></i></button>';
				asanaHtml += '<button type="button" class="pp-asana-remove" title="Entfernen"><i class="bx bx-x"></i></button>';
			} else if (parseInt(row.no_ticket) === 1) {
				asanaHtml = '<span class="pp-no-ticket" title="Kein Ticket notwendig"><i class="bx bx-check-shield"></i> Kein Ticket</span>';
				asanaHtml += '<button type="button" class="pp-no-ticket-remove" title="Zurücksetzen"><i class="bx bx-x"></i></button>';
			} else {
				asanaHtml = '<button type="button" class="pp-asana-btn" title="Bestehende verknüpfen"><i class="bx bx-link"></i></button>';
				asanaHtml += '<button type="button" class="pp-asana-create" title="Neu anlegen & verknüpfen"><i class="bx bx-plus-circle"></i></button>';
				asanaHtml += '<button type="button" class="pp-no-ticket-btn" title="Kein Ticket notwendig"><i class="bx bx-check-shield"></i></button>';
			}

			// Lead responsible for multi-plan
			var leadNameM = row.lead_responsible || '';
			var leadHtmlM = '';
			if (leadNameM) {
				leadHtmlM = '<span class="pp-lead-tag" title="' + escAttr(leadNameM) + '">' + escHtml(ppGetKuerzel(leadNameM)) + '<i class="bx bx-x pp-lead-x"></i></span>';
			}
			leadHtmlM += '<input type="text" class="pp-lead-input" placeholder="+" autocomplete="off" style="' + (leadNameM ? 'display:none;' : '') + '">';
			leadHtmlM += '<div class="pp-lead-suggest" style="display:none;"></div>';

			var focusClassMulti = parseInt(row.is_focus) === 1 ? ' pp-row-focus' : '';
			html += '<tr class="pp-item-row' + doneClass + phClass + focusClassMulti + '" data-id="' + id + '" data-type="item" data-plan="' + planId + '" draggable="true">';
			html += '<td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td>';
			var isFocus = parseInt(row.is_focus) === 1;
				html += '<td class="pp-td-check"><button type="button" class="pp-icon-toggle pp-toggle-done' + (isDone ? ' active' : '') + '" data-field="is_done" title="Erledigt"><i class="bx ' + (isDone ? 'bxs-check-circle' : 'bx-circle') + '"></i></button><button type="button" class="pp-icon-toggle pp-toggle-ph' + (isPlaceholder ? ' active' : '') + '" data-field="is_placeholder" title="Platzhalter"><i class="bx ' + (isPlaceholder ? 'bxs-hourglass' : 'bx-hourglass') + '"></i></button><button type="button" class="pp-icon-toggle pp-toggle-focus' + (isFocus ? ' active' : '') + '" data-field="is_focus" title="Fokus"><i class="bx ' + (isFocus ? 'bxs-flag-alt' : 'bx-flag') + '"></i></button></td>';
			html += '<td class="pp-td-desc"><div class="pp-cell pp-field" data-field="description" contenteditable="true" placeholder="Aufgabe...">' + escHtmlBr(row.description || '') + '</div></td>';
			html += '<td class="pp-td-period"><div class="pp-cell pp-field" data-field="timeframe" contenteditable="true" placeholder="z.B. 17.-18.02.">' + escHtmlBr(row.timeframe || '') + '</div></td>';
			html += '<td class="pp-td-num"><div class="pp-cell pp-cell-num pp-field" data-field="ist_hours" contenteditable="true">' + ppFmtNum(row.ist_hours) + '</div></td>';
			html += '<td class="pp-td-num"><div class="pp-cell pp-cell-num pp-field" data-field="planned_hours" contenteditable="true">' + ppFmtNum(row.planned_hours) + '</div></td>';
			html += '<td class="pp-td-lead"><div class="pp-lead-cell" data-current="' + escAttr(leadNameM) + '">' + leadHtmlM + '</div></td>';
			html += '<td class="pp-td-resp"><div class="pp-resp-cell" data-current="' + escAttr(row.responsible || '') + '">' + respTags + '</div></td>';
			html += '<td class="pp-td-deadline"><div class="pp-cell pp-field" data-field="deadline" contenteditable="true" placeholder="—">' + escHtmlBr(row.deadline || '') + '</div></td>';
			html += '<td class="pp-td-actual"><div class="pp-cell pp-field" data-field="actual_hours" contenteditable="true" placeholder="—">' + escHtmlBr(row.actual_hours || '') + '</div></td>';
			html += '<td class="pp-td-notes"><div class="pp-cell pp-field" data-field="notes" contenteditable="true" placeholder="—">' + escHtmlBr(row.notes || '') + '</div></td>';
			html += '<td class="pp-td-asana"><div class="pp-asana-cell">' + asanaHtml + '</div></td>';
			html += '<td class="pp-td-actions"><button type="button" class="pp-dup-row" title="Duplizieren"><i class="bx bx-copy"></i></button><button type="button" class="pp-delete-row" title="Löschen"><i class="bx bx-trash"></i></button></td>';
			html += '</tr>';
		});

		$('#pp-table-body').html(html);
		ppCalculateSubtotals();
		ppPopulateFilterDropdowns();
		ppInitDragDrop();
		ppMarkSectionItems();

		var fs = parseInt($('#pp-font-label').text()) || 13;
		ppApplyFontSize(fs);

		// Apply active filters
		if (ppActiveFilters.length > 0 || $('#pp-filter-search').val() || $('#pp-filter-responsible').val() || Object.keys(ppColFilters).length) {
			ppApplyFilters();
		}
	}

	function ppSelectPlan(planId) {
		ppCurrentPlanId = planId;
		ppCurrentPlan = null;
		for (var i = 0; i < ppPlans.length; i++) {
			if (ppPlans[i].id == planId) { ppCurrentPlan = ppPlans[i]; break; }
		}
		if (!ppCurrentPlan) return;

		// Update header
		var colorBg = ppCurrentPlan.client_color || '#999';
		$('#pp-client-badge').css('background', colorBg).text(ppCurrentPlan.client_short || ppCurrentPlan.client_title || '');
		$('#pp-plan-title-display').text(ppCurrentPlan.title);
		var period = '';
		if (ppCurrentPlan.quarter) period = ppCurrentPlan.quarter;
		else if (ppCurrentPlan.period_from && ppCurrentPlan.period_to) period = ppFormatDate(ppCurrentPlan.period_from) + ' – ' + ppFormatDate(ppCurrentPlan.period_to);
		$('#pp-plan-period').text(period);

		// Show/hide share banner + permission handling
		$('#pp-share-banner').remove();
		$('body').removeClass('pp-perm-read pp-perm-edit');
		if (ppCurrentPlan._shared || ppCurrentPlan.permission) {
			var perm = ppCurrentPlan.permission || 'read';
			var ownerName = ppCurrentPlan.owner_name || '';
			var permLabels = { read: 'Nur lesen', edit: 'Editor (Status & Reporting)', write: 'Vollzugriff' };
			var permIcons = { read: 'bx-show', edit: 'bx-edit-alt', write: 'bx-wrench' };
			var banner = '<div id="pp-share-banner" class="pp-share-banner pp-share-perm-' + perm + '">';
			banner += '<i class="bx ' + (permIcons[perm] || 'bx-show') + '"></i> ';
			banner += 'Geteilt von <strong>' + escHtml(ownerName) + '</strong> · Zugriff: <strong>' + (permLabels[perm] || 'Lesen') + '</strong>';
			if (perm === 'read') banner += ' <span style="color:#999;"> – Änderungen sind nicht möglich</span>';
			if (perm === 'edit') banner += ' <span style="color:#999;"> – Status, Ist-Stunden und Bemerkungen bearbeitbar</span>';
			banner += '</div>';
			$('#pp-plan-header').before(banner);

			if (perm === 'read') {
				// Read: nothing editable
				$('#pp-edit-plan, #pp-delete-plan, #pp-share-plan, #pp-duplicate-plan').hide();
				$('#pp-export-plan, #pp-budget-btn').show();
				$('.pp-add-row-bar').hide();
				$('body').addClass('pp-perm-read');
			} else if (perm === 'edit') {
				// Editor: can change status, ist_hours, actual_hours, notes — but not structure
				$('#pp-edit-plan, #pp-delete-plan, #pp-share-plan, #pp-duplicate-plan').hide();
				$('#pp-export-plan, #pp-revisions-btn, #pp-feedback-btn, #pp-budget-btn').show();
				$('.pp-add-row-bar').hide();
				$('body').addClass('pp-perm-edit');
			} else {
				// Write: full access (except delete plan & share management)
				$('#pp-edit-plan, #pp-duplicate-plan, #pp-export-plan, #pp-revisions-btn, #pp-feedback-btn, #pp-budget-btn').show();
				$('#pp-delete-plan, #pp-share-plan').hide();
				$('.pp-add-row-bar').show();
			}
		} else {
			$('#pp-edit-plan, #pp-delete-plan, #pp-share-plan, #pp-duplicate-plan, #pp-export-plan, #pp-revisions-btn, #pp-feedback-btn, #pp-budget-btn').show();
			$('.pp-add-row-bar').show();
		}

		// Show share link button if plan has a share_hash
		if (ppCurrentPlan.share_hash) {
			var shareUrl = window.location.origin + '/tallyr/projektplan/?id=' + ppCurrentPlan.share_hash;
			$('#pp-share-link-btn').attr('href', shareUrl).show();
		} else {
			$('#pp-share-link-btn').hide();
		}

		$('#pp-plan-header').show();
		$('#pp-table-container').show();
		$('#pp-empty-state').hide();

		ppLoadRows();
	}

	// Load rows
	function ppLoadRows(callback) {
		if (!ppCurrentPlanId) return;
		$('#pp-table-body').html('<tr><td colspan="13" style="text-align:center;padding:20px;"><i class="bx bx-loader-alt bx-spin"></i> Lade...</td></tr>');

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_pp_get_rows',
				plan_id: ppCurrentPlanId,
			},
			success: function (res) {
				if (!res.success) return;
				ppRows = res.data.rows || [];
				ppFeedbackByRow = res.data.feedback || {};
				ppRenderTable();
				if (typeof callback === 'function') callback();
			}
		});
	}

	// Render table
	function ppRenderTable() {
		var html = '';
		ppRows.forEach(function (row) {
			var id = row.id;
			var isDone = parseInt(row.is_done) === 1;
			var isPlaceholderRow = parseInt(row.is_placeholder) === 1;
			var doneClass = isDone ? ' pp-row-done' : '';
			var phClass = isPlaceholderRow ? ' pp-row-placeholder' : '';
			var focusClass = parseInt(row.is_focus) === 1 ? ' pp-row-focus' : '';

			if (row.type === 'section') {
				html += '<tr class="pp-section-row" data-id="' + id + '" data-type="section" draggable="true">';
				html += '<td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td>';
				html += '<td colspan="10"><div class="pp-cell pp-field" data-field="description" contenteditable="true" placeholder="Sektionsname...">' + escHtmlBr(row.description || '') + '</div></td>';
				html += '<td class="pp-section-subtotals">Ist <span class="pp-sub-ist">0</span> / Soll <span class="pp-sub-planned">0</span> h</td>';
				html += '<td class="pp-section-actions"><button type="button" class="pp-section-move" data-dir="up" title="Sektion nach oben"><i class="bx bx-chevron-up"></i></button><button type="button" class="pp-section-move" data-dir="down" title="Sektion nach unten"><i class="bx bx-chevron-down"></i></button><button type="button" class="pp-section-ph" title="Alle Aufgaben in Sektion als Platzhalter"><i class="bx bx-hourglass"></i></button><button type="button" class="pp-section-toggle" title="Sektion ein-/ausblenden"><i class="bx bx-hide"></i></button><button type="button" class="pp-delete-row" title="Löschen"><i class="bx bx-trash"></i></button></td>';
				html += '</tr>';
			} else if (row.type === 'spacer') {
				html += '<tr class="pp-spacer-row" data-id="' + id + '" data-type="spacer" draggable="true">';
				html += '<td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td>';
				html += '<td colspan="11">&nbsp;</td>';
				html += '<td><button type="button" class="pp-delete-row" title="Löschen"><i class="bx bx-trash"></i></button></td>';
				html += '</tr>';
			} else if (row.type === 'note') {
				html += '<tr class="pp-note-row" data-id="' + id + '" data-type="note" draggable="true">';
				html += '<td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td>';
				html += '<td colspan="11"><div class="pp-cell pp-cell-note pp-field" data-field="description" contenteditable="true" placeholder="Notiz, URL oder Kommentar...">' + escHtmlBr(row.description || '') + '</div></td>';
				html += '<td><button type="button" class="pp-delete-row" title="Löschen"><i class="bx bx-trash"></i></button></td>';
				html += '</tr>';
			} else {
				// Responsible: inline tags
				var respTags = ppRenderResponsibleTags(row.responsible || '');

				// Asana: icon button
				var hasAsana = row.asana_gid || row.asana_url;
				var asanaHtml = '';
				if (hasAsana) {
					var aUrl = row.asana_url || 'https://app.asana.com/0/0/' + row.asana_gid;
					var aName = row.asana_task_name || row.asana_gid || 'Asana';
					asanaHtml = '<a href="' + escAttr(aUrl) + '" target="_blank" class="pp-asana-link-name" title="In Asana öffnen">' + escHtml(aName) + '</a>';
					asanaHtml += '<button type="button" class="pp-asana-btn pp-asana-change" title="Ändern"><i class="bx bx-pencil"></i></button>';
					asanaHtml += '<button type="button" class="pp-asana-remove" title="Entfernen"><i class="bx bx-x"></i></button>';
				} else if (parseInt(row.no_ticket) === 1) {
					asanaHtml = '<span class="pp-no-ticket" title="Kein Ticket notwendig"><i class="bx bx-check-shield"></i> Kein Ticket</span>';
					asanaHtml += '<button type="button" class="pp-no-ticket-remove" title="Zurücksetzen"><i class="bx bx-x"></i></button>';
				} else {
					asanaHtml = '<button type="button" class="pp-asana-btn" title="Bestehende verknüpfen"><i class="bx bx-link"></i></button>';
					asanaHtml += '<button type="button" class="pp-asana-create" title="Neu anlegen & verknüpfen"><i class="bx bx-plus-circle"></i></button>';
					asanaHtml += '<button type="button" class="pp-no-ticket-btn" title="Kein Ticket notwendig"><i class="bx bx-check-shield"></i></button>';
				}

				// Lead responsible (single person)
				var leadHtml = '';
				var leadName = row.lead_responsible || '';
				if (leadName) {
					leadHtml = '<span class="pp-lead-tag" title="' + escAttr(leadName) + '">' + escHtml(ppGetKuerzel(leadName)) + '<i class="bx bx-x pp-lead-x"></i></span>';
				}
				leadHtml += '<input type="text" class="pp-lead-input" placeholder="+" autocomplete="off" style="' + (leadName ? 'display:none;' : '') + '">';
				leadHtml += '<div class="pp-lead-suggest" style="display:none;"></div>';

				// Spalten: Drag | Erledigt | Beschreibung | Zeitraum | Ist | Soll | Hauptverantw. | Umsetzung | Deadline | Aufwand | Bemerkungen | Asana | Actions
				html += '<tr class="pp-item-row' + doneClass + phClass + focusClass + '" data-id="' + id + '" data-type="item" draggable="true">';
				html += '<td class="pp-drag-handle"><i class="bx bx-grid-vertical"></i></td>';
				var isPlaceholder = parseInt(row.is_placeholder) === 1;
				html += '<td class="pp-td-check">';
				html += '<button type="button" class="pp-icon-toggle pp-toggle-done' + (isDone ? ' active' : '') + '" data-field="is_done" title="Erledigt"><i class="bx ' + (isDone ? 'bxs-check-circle' : 'bx-circle') + '"></i></button>';
				html += '<button type="button" class="pp-icon-toggle pp-toggle-ph' + (isPlaceholder ? ' active' : '') + '" data-field="is_placeholder" title="Platzhalter"><i class="bx ' + (isPlaceholder ? 'bxs-hourglass' : 'bx-hourglass') + '"></i></button>';
				var isFocusSingle = parseInt(row.is_focus) === 1;
				html += '<button type="button" class="pp-icon-toggle pp-toggle-focus' + (isFocusSingle ? ' active' : '') + '" data-field="is_focus" title="Fokus"><i class="bx ' + (isFocusSingle ? 'bxs-flag-alt' : 'bx-flag') + '"></i></button>';
				// Feedback indicator
				var rowFb = ppFeedbackByRow[id] || [];
				var unreadFb = rowFb.filter(function (f) { return !f.read_at; });
				if (rowFb.length) {
					if (unreadFb.length) {
						html += '<span class="pp-fb-indicator pp-fb-unread" data-rowid="' + id + '" title="' + unreadFb.length + ' ungelesen"><i class="bx bx-comment-dots"></i><span class="pp-fb-count">' + unreadFb.length + '</span></span>';
					} else {
						html += '<span class="pp-fb-indicator pp-fb-read" data-rowid="' + id + '" title="' + rowFb.length + ' Kommentare (gelesen)"><i class="bx bx-comment-check"></i></span>';
					}
				}
				html += '</td>';
				html += '<td class="pp-td-desc"><div class="pp-cell pp-field" data-field="description" contenteditable="true" placeholder="Aufgabe...">' + escHtmlBr(row.description || '') + '</div></td>';
				html += '<td class="pp-td-period"><div class="pp-cell pp-field" data-field="timeframe" contenteditable="true" placeholder="z.B. 17.-18.02.">' + escHtmlBr(row.timeframe || '') + '</div></td>';
				html += '<td class="pp-td-num"><div class="pp-cell pp-cell-num pp-field" data-field="ist_hours" contenteditable="true">' + ppFmtNum(row.ist_hours) + '</div></td>';
				html += '<td class="pp-td-num"><div class="pp-cell pp-cell-num pp-field" data-field="planned_hours" contenteditable="true">' + ppFmtNum(row.planned_hours) + '</div></td>';
				html += '<td class="pp-td-lead"><div class="pp-lead-cell" data-current="' + escAttr(leadName) + '">' + leadHtml + '</div></td>';
				html += '<td class="pp-td-resp"><div class="pp-resp-cell" data-current="' + escAttr(row.responsible || '') + '">' + respTags + '</div></td>';
				html += '<td class="pp-td-deadline"><div class="pp-cell pp-field" data-field="deadline" contenteditable="true" placeholder="—">' + escHtmlBr(row.deadline || '') + '</div></td>';
				html += '<td class="pp-td-actual"><div class="pp-cell pp-field" data-field="actual_hours" contenteditable="true" placeholder="—">' + escHtmlBr(row.actual_hours || '') + '</div></td>';
				html += '<td class="pp-td-notes"><div class="pp-cell pp-field" data-field="notes" contenteditable="true" placeholder="—">' + escHtmlBr(row.notes || '') + '</div></td>';
				html += '<td class="pp-td-asana"><div class="pp-asana-cell">' + asanaHtml + '</div></td>';
				html += '<td class="pp-td-actions"><button type="button" class="pp-dup-row" title="Duplizieren"><i class="bx bx-copy"></i></button><button type="button" class="pp-delete-row" title="Löschen"><i class="bx bx-trash"></i></button></td>';
				html += '</tr>';
			}
		});

		if (!ppRows.length) {
			html = '<tr><td colspan="13" class="pp-empty-table">Noch keine Einträge. Füge Sektionen und Aufgaben hinzu.</td></tr>';
		}

		$('#pp-table-body').html(html);
		ppCalculateSubtotals();
		ppInitDragDrop();
		ppMarkSectionItems();
		ppPopulateFilterDropdowns();
		if (ppActiveFilters.length > 0 || $('#pp-filter-search').val() || $('#pp-filter-responsible').val()) {
			ppApplyFilters();
		}
		var fs = parseInt($('#pp-font-label').text()) || 13;
		ppApplyFontSize(fs);
		ppInitDragDrop();
		ppMarkSectionItems();
	}

	// Calculate subtotals
	function ppCalculateSubtotals() {
		var totalPlanned = 0, totalIst = 0, totalDone = 0, totalItems = 0;
		var totalPlannedPh = 0, totalIstPh = 0; // inkl. Platzhalter
		var sectionPlanned = 0, sectionIst = 0;
		var sectionPlannedPh = 0, sectionIstPh = 0;
		var currentSectionRow = null;

		function flushSection() {
			if (!currentSectionRow) return;
			var subIst = ppFmtNum(sectionIst) || '0';
			var subPlanned = ppFmtNum(sectionPlanned) || '0';
			if (sectionPlannedPh > sectionPlanned) {
				subPlanned += ' <small style="color:#bbb;">(' + ppFmtNum(sectionPlannedPh) + ')</small>';
			}
			$('tr[data-id="' + currentSectionRow.id + '"] .pp-sub-ist').html(subIst);
			$('tr[data-id="' + currentSectionRow.id + '"] .pp-sub-planned').html(subPlanned);
		}

		ppRows.forEach(function (row) {
			if (row.type === 'section') {
				flushSection();
				currentSectionRow = row;
				sectionPlanned = 0;
				sectionIst = 0;
				sectionPlannedPh = 0;
				sectionIstPh = 0;
			} else if (row.type === 'item') {
				var ph = parseFloat(row.planned_hours) || 0;
				var ih = parseFloat(row.ist_hours) || 0;
				var isPh = parseInt(row.is_placeholder) === 1;

				// Always count for "inkl. Platzhalter"
				sectionPlannedPh += ph;
				sectionIstPh += ih;
				totalPlannedPh += ph;
				totalIstPh += ih;

				if (!isPh) {
					sectionPlanned += ph;
					sectionIst += ih;
					totalPlanned += ph;
					totalIst += ih;
					totalItems++;
					if (parseInt(row.is_done) === 1) totalDone++;
				}
			}
		});
		flushSection();

		var istTs = ppHoursToTsGlobal(totalIst);
		var plannedTs = ppHoursToTsGlobal(totalPlanned);

		var istText = ppFmtNum(totalIst) + ' h <small style="color:#999;">(' + ppFmtTsGlobal(istTs) + ' TS)</small>';
		var plannedText = ppFmtNum(totalPlanned) + ' h <small style="color:#999;">(' + ppFmtTsGlobal(plannedTs) + ' TS)</small>';
		if (totalPlannedPh > totalPlanned) {
			plannedText += ' <small style="color:#bbb;">(' + ppFmtNum(totalPlannedPh) + ' h inkl. PH)</small>';
		}
		if (totalIstPh > totalIst) {
			istText += ' <small style="color:#bbb;">(' + ppFmtNum(totalIstPh) + ' h inkl. PH)</small>';
		}
		$('#pp-total-ist').html(istText);
		$('#pp-total-planned').html(plannedText);
		$('#pp-total-done').text(totalDone);
		$('#pp-total-items').text(totalItems);

		// Load budget Soll for the plan's period from client budget
		ppLoadBudgetStats(totalPlanned);
	}

	function ppLoadBudgetStats(planSollH) {
		if (!ppCurrentPlanId || !ppCurrentPlan) { $('#pp-budget-stat').hide(); return; }

		// Determine plan's month range
		var pFrom = ppCurrentPlan.period_from;
		var pTo = ppCurrentPlan.period_to;
		if (!pFrom || !pTo || pFrom === '0000-00-00' || pTo === '0000-00-00') {
			$('#pp-budget-stat').hide();
			return;
		}

		var startY = parseInt(pFrom.substring(0, 4));
		var startM = parseInt(pFrom.substring(5, 7));
		var endY = parseInt(pTo.substring(0, 4));
		var endM = parseInt(pTo.substring(5, 7));

		// Collect all year/month pairs in the plan period
		var planMonths = []; // [{year, month}]
		var cy = startY, cm = startM;
		while (cy < endY || (cy === endY && cm <= endM)) {
			planMonths.push({ year: cy, month: cm });
			cm++;
			if (cm > 12) { cm = 1; cy++; }
		}

		// We may need data from multiple years
		var yearsNeeded = [];
		planMonths.forEach(function (pm) {
			if (yearsNeeded.indexOf(pm.year) === -1) yearsNeeded.push(pm.year);
		});

		// Load budget for each year, then sum
		var loaded = 0;
		var allMonthData = {}; // 'YYYY-MM' => soll_h
		yearsNeeded.forEach(function (yr) {
			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: { security: ajaxuser.nonce, action: 'uf_pp_get_budget', plan_id: ppCurrentPlanId, year: yr },
				success: function (res) {
					loaded++;
					if (res.success && res.data && res.data.months) {
						for (var m = 1; m <= 12; m++) {
							if (res.data.months[m]) {
								allMonthData[yr + '-' + String(m).padStart(2, '0')] = res.data.months[m].soll_h || 0;
							}
						}
					}
					if (loaded === yearsNeeded.length) {
						// Sum only the plan period months
						var budgetSollH = 0;
						planMonths.forEach(function (pm) {
							var key = pm.year + '-' + String(pm.month).padStart(2, '0');
							budgetSollH += allMonthData[key] || 0;
						});
						var ueTs = (res.data.client && res.data.client.uebertrag_ts) ? res.data.client.uebertrag_ts : 0;
						ppUpdateBudgetStats(budgetSollH, planSollH, ueTs);
					}
				}
			});
		});
	}

	function ppUpdateBudgetStats(budgetSollH, planSollH, uebertragTs) {
		if (budgetSollH <= 0 && !uebertragTs) { $('#pp-budget-stat').hide(); $('#pp-budget-gap').hide(); return; }
		var budgetTs = budgetSollH / ppHoursPerTs;
		var ue = uebertragTs || 0;
		var effTs = budgetTs + ue;
		var effH = effTs * ppHoursPerTs;

		// Budget Soll anzeigen (inkl. Übertrag wenn vorhanden)
		var sollLabel = ppFmtTsGlobal(budgetTs) + ' TS';
		if (ue !== 0) {
			sollLabel += ' <small style="color:#888;">' + (ue > 0 ? '+' : '') + ppFmtTsGlobal(ue) + ' Übertrag = <strong>' + ppFmtTsGlobal(effTs) + ' TS eff.</strong></small>';
		}
		$('#pp-budget-soll').html(sollLabel);
		$('#pp-budget-stat')
			.removeClass('pp-stat-ok pp-stat-warn pp-stat-over')
			.addClass('pp-stat-ok')
			.show();

		// Gap = effektive Stunden minus geplante Stunden = noch zu verplanen
		var gapH = effH - planSollH;
		if (gapH > 0) {
			// Noch Stunden zu verplanen
			$('#pp-budget-gap-val').text(ppFmtNum(gapH) + ' h noch nicht verplant');
			$('#pp-budget-gap')
				.removeClass('pp-stat-gap-ok pp-stat-gap-warn pp-stat-gap-over')
				.addClass('pp-stat-gap-warn')
				.show();
		} else if (gapH < 0) {
			// Überplant
			$('#pp-budget-gap-val').text(ppFmtNum(Math.abs(gapH)) + ' h überplant');
			$('#pp-budget-gap')
				.removeClass('pp-stat-gap-ok pp-stat-gap-warn pp-stat-gap-over')
				.addClass('pp-stat-gap-over')
				.show();
		} else {
			$('#pp-budget-gap').hide();
		}
	}

	// Paste as plain text in contenteditable cells
	$(document).on('paste', '#pp-table-body [contenteditable]', function (e) {
		e.preventDefault();
		var text = (e.originalEvent.clipboardData || window.clipboardData).getData('text/plain');
		document.execCommand('insertText', false, text);
	});

	// Toggle done / placeholder via icon buttons
	$(document).on('click', '.pp-icon-toggle', function (e) {
		e.stopPropagation();
		var $btn = $(this);
		var $tr = $btn.closest('tr');
		var rowId = $tr.data('id');
		var field = $btn.data('field');
		var isActive = $btn.hasClass('active');
		var newVal = isActive ? 0 : 1;

		$btn.toggleClass('active');

		if (field === 'is_done') {
			$btn.find('i').attr('class', newVal ? 'bx bxs-check-circle' : 'bx bx-circle');
			$tr.toggleClass('pp-row-done', !!newVal);
		} else if (field === 'is_placeholder') {
			$btn.find('i').attr('class', newVal ? 'bx bxs-hourglass' : 'bx bx-hourglass');
			$tr.toggleClass('pp-row-placeholder', !!newVal);
		} else if (field === 'is_focus') {
			$btn.find('i').attr('class', newVal ? 'bx bxs-flag-alt' : 'bx bx-flag');
			$tr.toggleClass('pp-row-focus', !!newVal);
		}

		// Update local state + save
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i][field] = newVal; break; }
		}
		ppCalculateSubtotals();

		if (ppSaveTimers[rowId]) clearTimeout(ppSaveTimers[rowId]);
		ppSaveTimers[rowId] = setTimeout(function () { ppSaveRow(rowId); }, 300);
	});

	// Toggle section visibility
	$(document).on('click', '.pp-section-toggle', function (e) {
		e.stopPropagation();
		var $section = $(this).closest('tr');
		var sectionId = $section.data('id');
		var isHidden = $section.hasClass('pp-section-collapsed');

		if (isHidden) {
			// Show: remove collapsed class, show all rows until next section
			$section.removeClass('pp-section-collapsed');
			$(this).find('i').attr('class', 'bx bx-hide');
			$section.nextAll('tr').each(function () {
				if ($(this).data('type') === 'section') return false;
				$(this).show();
			});
		} else {
			// Hide: add collapsed class, hide all rows until next section
			$section.addClass('pp-section-collapsed');
			$(this).find('i').attr('class', 'bx bx-show');
			$section.nextAll('tr').each(function () {
				if ($(this).data('type') === 'section') return false;
				$(this).hide();
			});
		}
	});

	// Section → toggle all items as placeholder
	$(document).on('click', '.pp-section-ph', function (e) {
		e.stopPropagation();
		var $section = $(this).closest('tr');
		var sectionId = $section.data('id');

		// Find all items in this section (until next section)
		var sectionItems = [];
		var sIdx = -1;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == sectionId) { sIdx = i; continue; }
			if (sIdx >= 0) {
				if (ppRows[i].type === 'section' || ppRows[i].type === 'plan_header') break;
				if (ppRows[i].type === 'item') sectionItems.push(ppRows[i]);
			}
		}
		if (!sectionItems.length) return;

		// Check if all are already placeholders → then un-placeholder, otherwise make all placeholder
		var allPh = sectionItems.every(function (r) { return parseInt(r.is_placeholder) === 1; });
		var newVal = allPh ? 0 : 1;

		sectionItems.forEach(function (r) {
			r.is_placeholder = newVal;
			ppSaveRow(r.id);
		});

		ppRenderTable();
		toastr.success(allPh ? 'Platzhalter entfernt' : 'Sektion als Platzhalter markiert');
	});

	// ========================================
	// TEXTBAUSTEINE AUTOCOMPLETE
	// ========================================

	var ppTextbausteine = [];

	function ppLoadTextbausteine() {
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_textbausteine' },
			success: function (res) {
				ppTextbausteine = res.success ? res.data : [];
			}
		});
	}

	// Show autocomplete when typing in description cells
	var ppTbTimer = null;
	var ppTbLastCell = null;

	$(document).on('input', '#pp-table-body .pp-cell[data-field="description"]', function () {
		var $cell = $(this);
		ppTbLastCell = $cell;
		var text = $cell.text().trim().toLowerCase();
		$('.pp-tb-dropdown').remove();

		if (!text || text.length < 2 || !ppTextbausteine.length) return;

		if (ppTbTimer) clearTimeout(ppTbTimer);
		ppTbTimer = setTimeout(function () {
			var matches = [];
			ppTextbausteine.forEach(function (tb) {
				if (tb.name.toLowerCase().indexOf(text) > -1) {
					matches.push(tb);
				}
			});
			if (!matches.length || matches.length > 20) return;

			var dd = '<div class="pp-tb-dropdown">';
			matches.forEach(function (m) {
				var preview = m.notes ? m.notes.substring(0, 80) : '';
				// Extract hours badge from name
				var hMatch = m.name.match(/\/\/\s*([\d,\.]+|tbd|x)\s*Std/i);
				var hBadge = hMatch ? '<span class="pp-tb-hours">' + hMatch[1] + ' h</span>' : '';
				// Clean name for display
				var cleanName = m.name.replace(/\s*\/\/.*$/, '').trim();
				dd += '<div class="pp-tb-item" data-name="' + escAttr(m.name) + '" data-notes="' + escAttr(m.notes || '') + '">';
				dd += '<div class="pp-tb-item-name">' + escHtml(cleanName) + ' ' + hBadge + '</div>';
				if (preview) dd += '<div class="pp-tb-item-preview">' + escHtml(preview) + (m.notes.length > 80 ? '...' : '') + '</div>';
				dd += '</div>';
			});
			dd += '</div>';

			var offset = $cell.offset();
			$(dd).appendTo('body').css({
				position: 'absolute',
				top: offset.top + $cell.outerHeight(),
				left: offset.left,
				width: Math.min($cell.outerWidth(), 400),
				zIndex: 9999
			});
		}, 200);
	});

	// Select textbaustein - use mousedown instead of click (fires before blur)
	$(document).on('mousedown', '.pp-tb-item', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var name = $(this).attr('data-name');
		var notes = $(this).attr('data-notes');
		var $cell = ppTbLastCell;

		if ($cell && $cell.length) {
			var text = notes || name;
			// Remove the // hours suffix from the inserted text
			text = text.replace(/\s*\/\/\s*[\d,\.]+\s*Std\.?\s*$/i, '').replace(/\s*\/\/\s*(tbd|x)\s*Std\.?\s*$/i, '').trim();
			$cell.text(text);
			$cell.trigger('input');
			$cell.focus();

			// Extract hours from task name: "// 3,5 Std." or "// 0,5 Std."
			var hoursMatch = name.match(/\/\/\s*([\d,\.]+)\s*Std/i);
			if (hoursMatch) {
				var hours = hoursMatch[1].replace(',', '.');
				var num = parseFloat(hours);
				// Skip if tbd, x, or invalid
				if (!isNaN(num) && num > 0) {
					// Find the Soll cell in the same row
					var $tr = $cell.closest('tr');
					var $sollCell = $tr.find('.pp-cell[data-field="planned_hours"]');
					var sollVal = $sollCell.text().trim();
					if ($sollCell.length && (!sollVal || sollVal === '0')) {
						$sollCell.text(String(num).replace('.', ','));
						$sollCell.trigger('input');
					}
				}
			}
		}
		$('.pp-tb-dropdown').remove();
	});

	// Close on blur/click outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pp-tb-dropdown').length) {
			$('.pp-tb-dropdown').remove();
		}
	});

	$(document).on('blur', '#pp-table-body .pp-cell[data-field="description"]', function () {
		setTimeout(function () { $('.pp-tb-dropdown').remove(); }, 200);
	});

	// ========================================
	// FILTERS
	// ========================================

	var ppActiveFilters = []; // multi-select: ['open', 'no-asana', ...]

	// Build set of known person names from ppUsersData
	function ppKnownNames() {
		var known = {};
		if (typeof ppUsersData !== 'undefined') {
			ppUsersData.forEach(function (u) {
				if (u.name) known[u.name] = true;
				if (u.kuerzel) known[u.kuerzel] = true;
			});
		}
		return known;
	}

	// Populate both filter dropdowns — only show known team members
	function ppPopulateFilterDropdowns() {
		var known = ppKnownNames();
		var leadNames = {};
		var respNames = {};
		ppRows.forEach(function (r) {
			if (r.lead_responsible) {
				r.lead_responsible.split(',').forEach(function (n) {
					n = n.trim();
					if (n && known[n]) leadNames[n] = true;
				});
			}
			if (r.responsible) {
				r.responsible.split(',').forEach(function (n) {
					n = n.trim();
					if (n && known[n]) respNames[n] = true;
				});
			}
		});

		// Lead filter
		var $lead = $('#pp-filter-lead');
		var curLead = $lead.val();
		$lead.html('<option value="">Hauptverantw.</option>');
		Object.keys(leadNames).sort().forEach(function (n) {
			$lead.append('<option value="' + escAttr(n) + '">' + escHtml(ppGetKuerzel(n)) + ' – ' + escHtml(n) + '</option>');
		});
		if (curLead) $lead.val(curLead);

		// Responsible filter
		var $sel = $('#pp-filter-responsible');
		var current = $sel.val();
		$sel.html('<option value="">Umsetzung</option>');
		Object.keys(respNames).sort().forEach(function (n) {
			$sel.append('<option value="' + escAttr(n) + '">' + escHtml(ppGetKuerzel(n)) + ' – ' + escHtml(n) + '</option>');
		});
		if (current) $sel.val(current);
	}

	function ppApplyFilters() {
		var filters = ppActiveFilters;
		var search = ($('#pp-filter-search').val() || '').toLowerCase().trim();
		var leadFilter = $('#pp-filter-lead').val() || '';
		var respFilter = $('#pp-filter-responsible').val() || '';
		var hasColFilters = Object.keys(ppColFilters).length > 0;
		var anyFilterActive = filters.length > 0 || search || leadFilter || respFilter || hasColFilters;

		// Don't hide the row that's currently being edited
		var $focusedRow = $(document.activeElement).closest('#pp-table-body tr');
		var focusedRowId = $focusedRow.length ? $focusedRow.data('id') : null;

		$('#pp-table-body tr').each(function () {
			var $tr = $(this);
			var type = $tr.data('type');
			var id = $tr.data('id');

			// Plan headers and sections are handled in a second pass
			if (type === 'plan_header' || type === 'section') { $tr.show(); return; }
			// Spacers/notes: hide when any filter is active
			if (type === 'spacer' || type === 'note') {
				$tr.toggle(!anyFilterActive);
				return;
			}

			// Find row data
			var row = null;
			for (var i = 0; i < ppRows.length; i++) {
				if (ppRows[i].id == id) { row = ppRows[i]; break; }
			}
			if (!row) return;

			var show = true;

			// Status filters (multi-select, AND — row must match ALL active pills)
			if (show && filters.length) {
				for (var fi = 0; fi < filters.length; fi++) {
					var f = filters[fi];
					if (f === 'open' && (parseInt(row.is_done) || parseInt(row.is_placeholder))) { show = false; break; }
					if (f === 'done' && parseInt(row.is_done) !== 1) { show = false; break; }
					if (f === 'placeholder' && parseInt(row.is_placeholder) !== 1) { show = false; break; }
					if (f === 'no-asana' && (row.asana_gid || row.asana_url || parseInt(row.no_ticket))) { show = false; break; }
					if (f === 'no-ticket' && parseInt(row.no_ticket) !== 1) { show = false; break; }
					if (f === 'focus' && parseInt(row.is_focus) !== 1) { show = false; break; }
				}
			}

			// Text search
			if (show && search) {
				var text = (row.description + ' ' + row.responsible + ' ' + row.notes + ' ' + row.deadline + ' ' + row.timeframe).toLowerCase();
				show = text.indexOf(search) > -1;
			}

			// Lead responsible filter (top bar)
			if (show && leadFilter) {
				var leadVal = (row.lead_responsible || '').trim();
				show = leadVal === leadFilter;
			}

			// Responsible/Umsetzung filter (top bar) — exact match in comma list
			if (show && respFilter) {
				var respNames = (row.responsible || '').split(',').map(function (n) { return n.trim(); });
				show = respNames.indexOf(respFilter) > -1;
			}

			// Column filters (AND with previous)
			if (show && hasColFilters) {
				for (var colKey in ppColFilters) {
					var colVal = ppColFilters[colKey];
					var rowVal = String(row[colKey] || '');
					if (colKey === 'responsible' || colKey === 'lead_responsible') {
						var cNames = rowVal.split(',').map(function (n) { return n.trim(); });
						if (cNames.indexOf(colVal) === -1) show = false;
					} else {
						if (rowVal.trim() !== colVal) show = false;
					}
					if (!show) break;
				}
			}

			// Never hide the currently focused/edited row
			if (focusedRowId && id == focusedRowId) show = true;
			$tr.toggle(show);
		});

		// Second pass: hide sections with no visible items below them
		$('#pp-table-body tr.pp-section-row').each(function () {
			if (!anyFilterActive) { $(this).show(); return; }
			var hasVisible = false;
			$(this).nextAll('tr').each(function () {
				var t = $(this).data('type');
				if (t === 'section' || t === 'plan_header') return false;
				if (t === 'item' && $(this).is(':visible')) { hasVisible = true; return false; }
			});
			$(this).toggle(hasVisible);
		});

		// Third pass: hide plan headers with no visible items/sections below them
		$('#pp-table-body tr.pp-multi-plan-header').each(function () {
			if (!anyFilterActive) { $(this).show(); return; }
			var hasVisible = false;
			$(this).nextAll('tr').each(function () {
				if ($(this).data('type') === 'plan_header') return false;
				if ($(this).is(':visible') && ($(this).data('type') === 'item' || $(this).data('type') === 'section')) { hasVisible = true; return false; }
			});
			$(this).toggle(hasVisible);
		});

		// Show/hide filter active banner with hours summary
		if (anyFilterActive) {
			var count = 0, filteredSoll = 0, filteredIst = 0;
			var total = $('#pp-table-body tr.pp-item-row').length;
			$('#pp-table-body tr.pp-item-row:visible').each(function () {
				count++;
				var rid = $(this).data('id');
				for (var i = 0; i < ppRows.length; i++) {
					if (ppRows[i].id == rid) {
						filteredSoll += parseFloat(ppRows[i].planned_hours) || 0;
						filteredIst += parseFloat(ppRows[i].ist_hours) || 0;
						break;
					}
				}
			});
			if (!$('#pp-filter-active-bar').length) {
				$('.pp-filter-bar').after('<div id="pp-filter-active-bar"></div>');
			}
			var hoursInfo = ' — <strong>' + ppFmtNum(filteredSoll) + ' h Soll</strong> / ' + ppFmtNum(filteredIst) + ' h Ist';
			$('#pp-filter-active-bar').html('<i class="bx bx-filter-alt"></i> Filter aktiv: ' + count + ' von ' + total + ' Aufgaben' + hoursInfo + ' <button id="pp-filter-reset" style="margin-left:8px;">Alle Filter zurücksetzen</button>').show();
		} else {
			$('#pp-filter-active-bar').remove();
		}
	}

	$(document).on('click', '.pp-filter-pill', function () {
		var f = $(this).data('filter');
		if (f === 'all') {
			// "Alle" deselects everything
			ppActiveFilters = [];
			$('.pp-filter-pill').removeClass('active');
			$('.pp-filter-pill[data-filter="all"]').addClass('active');
		} else {
			// Toggle this filter
			$('.pp-filter-pill[data-filter="all"]').removeClass('active');
			var idx = ppActiveFilters.indexOf(f);
			if (idx > -1) {
				ppActiveFilters.splice(idx, 1);
				$(this).removeClass('active');
			} else {
				ppActiveFilters.push(f);
				$(this).addClass('active');
			}
			// If nothing selected, reactivate "Alle"
			if (!ppActiveFilters.length) {
				$('.pp-filter-pill[data-filter="all"]').addClass('active');
			}
		}
		ppApplyFilters();
	});

	// Reset all filters
	$(document).on('click', '#pp-filter-reset', function () {
		ppActiveFilters = [];
		ppColFilters = {};
		$('.pp-filter-pill').removeClass('active');
		$('.pp-filter-pill[data-filter="all"]').addClass('active');
		$('#pp-filter-search').val('');
		$('#pp-filter-lead').val('');
		$('#pp-filter-responsible').val('');
		$('.pp-col-filter-icon').removeClass('pp-col-filter-active');
		ppApplyFilters();
	});

	$(document).on('input', '#pp-filter-search', function () {
		ppApplyFilters();
	});

	$(document).on('change', '#pp-filter-lead', function () {
		ppApplyFilters();
		ppTogglePersonActions();
	});

	$(document).on('change', '#pp-filter-responsible', function () {
		ppApplyFilters();
		ppTogglePersonActions();
	});

	function ppTogglePersonActions() {
		var lead = $('#pp-filter-lead').val();
		var resp = $('#pp-filter-responsible').val();
		var person = lead || resp;
		if (person) {
			$('#pp-person-share-btn, #pp-person-export-btn').show();
		} else {
			$('#pp-person-share-btn, #pp-person-export-btn').hide();
		}
	}

	// Person share link
	$(document).on('click', '#pp-person-share-btn', function () {
		var person = $('#pp-filter-lead').val() || $('#pp-filter-responsible').val();
		if (!person) return;
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_generate_person_share', person_name: person },
			success: function (res) {
				if (!res.success) { toastr.error('Fehler'); return; }
				Swal.fire({
					title: 'Aufgabenliste für ' + person,
					html: '<div style="text-align:left;">' +
						'<p style="font-size:13px;color:#666;margin:0 0 10px;">Read-Only Link zu allen Aufgaben dieser Person:</p>' +
						'<div style="display:flex;gap:6px;">' +
						'<input type="text" id="pp-person-share-url" value="' + res.data.share_url + '" readonly style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;">' +
						'<button type="button" id="pp-person-share-copy" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;">Kopieren</button>' +
						'</div></div>',
					showConfirmButton: false,
					showCancelButton: true,
					cancelButtonText: 'Schließen',
					didOpen: function () {
						$(document).on('click', '#pp-person-share-copy', function () {
							$('#pp-person-share-url').select();
							document.execCommand('copy');
							toastr.success('Link kopiert');
						});
					}
				});
			}
		});
	});

	// Person Excel export (filtered visible rows)
	$(document).on('click', '#pp-person-export-btn', function () {
		var person = $('#pp-filter-lead').val() || $('#pp-filter-responsible').val();
		if (!person) return;
		var kuerzel = ppGetKuerzel(person);

		var data = [['Kunde', 'Plan', 'Aufgabe', 'Soll (h)', 'Ist (h)', 'Status', 'Zeitraum', 'Deadline', 'Beteiligte']];
		$('#pp-table-body tr.pp-item-row:visible').each(function () {
			var rid = $(this).data('id');
			var row = null;
			for (var i = 0; i < ppRows.length; i++) { if (ppRows[i].id == rid) { row = ppRows[i]; break; } }
			if (!row) return;
			var plan = ppCurrentPlan;
			if (!plan && (row._planId || row.plan_id)) {
				for (var j = 0; j < ppPlans.length; j++) { if (ppPlans[j].id == (row._planId || row.plan_id)) { plan = ppPlans[j]; break; } }
			}
			data.push([
				plan ? (plan.client_short || plan.client_title || '') : '',
				plan ? plan.title : '',
				row.description || '',
				row.planned_hours || 0,
				row.ist_hours || 0,
				parseInt(row.is_done) ? 'Erledigt' : 'Offen',
				row.timeframe || '',
				row.deadline || '',
				row.responsible || '',
			]);
		});

		if (typeof XLSX !== 'undefined') {
			var ws = XLSX.utils.aoa_to_sheet(data);
			var wb = XLSX.utils.book_new();
			XLSX.utils.book_append_sheet(wb, ws, 'Aufgaben');
			XLSX.writeFile(wb, 'Aufgaben_' + kuerzel + '.xlsx');
			toastr.success('Excel exportiert');
		} else {
			toastr.error('Excel-Export nicht verfügbar');
		}
	});

	// Column filters
	var ppColFilters = {}; // { colName: 'value' }

	var ppColFilterCol = null; // track which column's dropdown is open

	$(document).on('click', '.pp-col-filter-icon', function (e) {
		e.stopPropagation();
		var $icon = $(this);
		var $th = $icon.closest('th');
		var col = $th.data('col');

		// Close existing
		$('.pp-col-filter-dd').remove();

		// Collect unique values
		var values = {};
		ppRows.forEach(function (r) {
			if (r.type !== 'item') return;
			var val = String(r[col] || '').trim();
			if (col === 'responsible' && val) {
				val.split(',').forEach(function (n) {
					n = n.trim();
					if (n) values[n] = (values[n] || 0) + 1;
				});
			} else if (val) {
				values[val] = (values[val] || 0) + 1;
			}
		});

		var currentFilter = ppColFilters[col] || '';
		var dd = '<div class="pp-col-filter-dd" data-col="' + col + '">';
		dd += '<div class="pp-col-filter-item" data-val="">Alle anzeigen</div>';
		Object.keys(values).sort().forEach(function (v) {
			var isActive = currentFilter === v ? ' active' : '';
			var label = col === 'responsible' ? escHtml(ppGetKuerzel(v)) + ' – ' + escHtml(v) : escHtml(v);
			dd += '<div class="pp-col-filter-item' + isActive + '" data-val="' + escAttr(v) + '">' + label + ' <small style="color:#bbb;">(' + values[v] + ')</small></div>';
		});
		dd += '</div>';

		// Append to body, position below the th
		var offset = $th.offset();
		$(dd).appendTo('body').css({
			position: 'absolute',
			top: offset.top + $th.outerHeight(),
			left: offset.left,
			zIndex: 9999
		});
		ppColFilterCol = col;
	});

	$(document).on('click', '.pp-col-filter-item', function (e) {
		e.stopPropagation();
		var val = String($(this).attr('data-val') || '');
		var col = $(this).closest('.pp-col-filter-dd').data('col');

		if (val) {
			ppColFilters[col] = val;
			$('th[data-col="' + col + '"] .pp-col-filter-icon').addClass('pp-col-filter-active');
		} else {
			delete ppColFilters[col];
			$('th[data-col="' + col + '"] .pp-col-filter-icon').removeClass('pp-col-filter-active');
		}

		$('.pp-col-filter-dd').remove();
		ppApplyFilters();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pp-col-filter-dd, .pp-col-filter-icon').length) {
			$('.pp-col-filter-dd').remove();
		}
	});

	// ========================================
	// MOVE ENTIRE SECTIONS
	// ========================================

	$(document).on('click', '.pp-section-move', function (e) {
		e.stopPropagation();
		var dir = $(this).data('dir'); // 'up' or 'down'
		var $section = $(this).closest('tr');
		var sectionId = $section.data('id');

		// Find section index in ppRows
		var secIdx = -1;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == sectionId) { secIdx = i; break; }
		}
		if (secIdx === -1) return;

		// Find section block: from secIdx until next section or end
		var blockEnd = ppRows.length;
		for (var i = secIdx + 1; i < ppRows.length; i++) {
			if (ppRows[i].type === 'section') { blockEnd = i; break; }
		}
		var block = ppRows.splice(secIdx, blockEnd - secIdx);

		// Find target position
		if (dir === 'up') {
			// Find previous section
			var targetIdx = 0;
			for (var i = secIdx - 1; i >= 0; i--) {
				if (ppRows[i].type === 'section') { targetIdx = i; break; }
			}
			// Insert block before target section
			for (var i = 0; i < block.length; i++) {
				ppRows.splice(targetIdx + i, 0, block[i]);
			}
		} else {
			// Find next section after where the block was
			var targetIdx = ppRows.length;
			for (var i = secIdx; i < ppRows.length; i++) {
				if (ppRows[i].type === 'section') {
					// Find end of this next section's block
					var nextEnd = ppRows.length;
					for (var j = i + 1; j < ppRows.length; j++) {
						if (ppRows[j].type === 'section') { nextEnd = j; break; }
					}
					targetIdx = nextEnd;
					break;
				}
			}
			for (var i = 0; i < block.length; i++) {
				ppRows.splice(targetIdx + i, 0, block[i]);
			}
		}

		// Re-number positions
		ppRows.forEach(function (r, idx) { r.position = idx; });

		// Re-render
		ppRenderTable();

		// Scroll to moved section
		var $moved = $('tr[data-id="' + sectionId + '"]');
		if ($moved.length) $moved[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

		// Save positions to server
		var positions = [];
		ppRows.forEach(function (r, idx) { positions.push({ id: r.id, position: idx }); });
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_reorder_rows', plan_id: ppCurrentPlanId, positions: positions }
		});
	});

	// Excel-like cell behavior: single click = select all (typing replaces), double click = edit in place
	var ppCellEditMode = {};

	$(document).on('click', '#pp-table-body .pp-cell', function (e) {
		var el = this;
		var field = $(el).data('field');
		var id = $(el).closest('tr').data('id') + '_' + field;

		// Skip select-all for description and notes fields (normal text editing)
		if (field === 'description' || field === 'notes') {
			if (!el.dataset.undo) el.dataset.undo = el.textContent;
			return;
		}

		if (ppCellEditMode[id]) return;

		// Store undo value
		if (!el.dataset.undo) el.dataset.undo = el.textContent;

		// Select all content → typing replaces (only for short fields)
		var range = document.createRange();
		range.selectNodeContents(el);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	});

	$(document).on('dblclick', '#pp-table-body .pp-cell', function (e) {
		var el = this;
		var id = $(el).closest('tr').data('id') + '_' + $(el).data('field');
		ppCellEditMode[id] = true;

		// Store undo value
		if (!el.dataset.undo) el.dataset.undo = el.textContent;

		// Let browser handle cursor placement naturally (don't select all)
		setTimeout(function () { ppCellEditMode[id] = false; }, 300);
	});

	// Escape = undo to previous value
	$(document).on('keydown', '#pp-table-body .pp-cell', function (e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			if (this.dataset.undo !== undefined) {
				this.textContent = this.dataset.undo;
				$(this).trigger('input'); // trigger auto-save with reverted value
			}
			this.blur();
		}
		// Tab = move to next editable cell (including lead + resp input)
		if (e.key === 'Tab') {
			e.preventDefault();
			var $editables = $('#pp-table-body .pp-cell, #pp-table-body .pp-lead-input, #pp-table-body .pp-resp-input');
			var idx = $editables.index(this);
			var next = e.shiftKey ? idx - 1 : idx + 1;
			if ($editables[next]) { $editables[next].focus(); if ($editables[next].click) $editables[next].click(); }
		}
	});

	// Clear undo after blur (commit) + auto-format timeframe
	$(document).on('blur', '#pp-table-body .pp-cell', function () {
		if ($(this).data('field') === 'timeframe') {
			var formatted = ppFormatTimeframe(this.textContent.trim());
			if (formatted !== this.textContent.trim()) {
				this.textContent = formatted;
				$(this).trigger('input');
			}
		}
		this.dataset.undo = this.textContent;
	});

	// Smart timeframe formatter
	// Resolve typed text to a known user name (by kürzel, name, partial match)
	function ppResolveResponsible(val) {
		if (!val || typeof ppUsersData === 'undefined') return val;
		var lval = val.toLowerCase().trim();
		// 1. Exact kürzel match (case-insensitive)
		for (var i = 0; i < ppUsersData.length; i++) {
			if (ppUsersData[i].kuerzel && ppUsersData[i].kuerzel.toLowerCase() === lval) return ppUsersData[i].name;
		}
		// 2. Exact name match (case-insensitive)
		for (var i = 0; i < ppUsersData.length; i++) {
			if (ppUsersData[i].name.toLowerCase() === lval) return ppUsersData[i].name;
		}
		// 3. Kürzel starts with input
		for (var i = 0; i < ppUsersData.length; i++) {
			if (ppUsersData[i].kuerzel && ppUsersData[i].kuerzel.toLowerCase().indexOf(lval) === 0) return ppUsersData[i].name;
		}
		// 4. Name contains input
		for (var i = 0; i < ppUsersData.length; i++) {
			if (ppUsersData[i].name.toLowerCase().indexOf(lval) > -1) return ppUsersData[i].name;
		}
		// No match → return as-is (free text)
		return val;
	}

	function ppFormatTimeframe(val) {
		if (!val) return val;
		// Already well-formatted or text like "März", "Q2" → keep
		if (val.match(/^[A-Za-zÄÖÜäöü]/)) return val;

		// Strip all spaces
		var s = val.replace(/\s+/g, '');

		// Single date: 17.02 or 17.2 → 17.02.
		var single = s.match(/^(\d{1,2})\.(\d{1,2})\.?$/);
		if (single) {
			return single[1].padStart(2, '0') + '.' + single[2].padStart(2, '0') + '.';
		}

		// Range same month: 17-18.02 or 17.-18.02. or 17-18.2
		var sameMonth = s.match(/^(\d{1,2})[\.\-]+(\d{1,2})\.(\d{1,2})\.?$/);
		if (sameMonth) {
			return sameMonth[1].padStart(2, '0') + '.-' + sameMonth[2].padStart(2, '0') + '.' + sameMonth[3].padStart(2, '0') + '.';
		}

		// Range different months: 17.01-28.02 or 17.01.-28.02.
		var diffMonth = s.match(/^(\d{1,2})\.(\d{1,2})\.?\-(\d{1,2})\.(\d{1,2})\.?$/);
		if (diffMonth) {
			return diffMonth[1].padStart(2, '0') + '.' + diffMonth[2].padStart(2, '0') + '.-' + diffMonth[3].padStart(2, '0') + '.' + diffMonth[4].padStart(2, '0') + '.';
		}

		return val;
	}

	// Auto-save on field change (inputs + contenteditable)
	$(document).on('input change', '#pp-table-body .pp-field', function () {
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		var field = $(this).data('field');
		var value;

		if ($(this).is(':checkbox')) {
			value = $(this).is(':checked') ? 1 : 0;
			if (field === 'is_done') $tr.toggleClass('pp-row-done', value === 1);
			if (field === 'is_placeholder') $tr.toggleClass('pp-row-placeholder', value === 1);
		} else if ($(this).is('[contenteditable]')) {
			// Preserve line breaks: convert <br>, <div> to \n, then decode HTML entities
			var html = $(this)[0].innerHTML;
			value = html.replace(/<div>/gi, '\n').replace(/<\/div>/gi, '').replace(/<br\s*\/?>/gi, '\n').replace(/&nbsp;/g, ' ').replace(/<[^>]+>/g, '');
			// Decode HTML entities (&amp; → &, &lt; → <, etc.)
			var tmp = document.createElement('textarea');
			tmp.innerHTML = value;
			value = tmp.value.trim();
		} else {
			value = $(this).val();
		}

		// For planned_hours: convert comma to dot for storage, keep comma display
		if (field === 'planned_hours' || field === 'ist_hours') {
			value = String(value).replace(',', '.');
		}

		// Update local state
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (row) {
			row[field] = value;
			ppCalculateSubtotals();
		}

		$(this).addClass('pp-saving');

		if (ppSaveTimers[rowId]) clearTimeout(ppSaveTimers[rowId]);
		ppSaveTimers[rowId] = setTimeout(function () {
			ppSaveRow(rowId);
		}, 600);
	});

	function ppSaveRow(rowId) {
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (!row) return;

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_pp_save_row',
				row_id: row.id,
				plan_id: row._planId || row.plan_id || ppCurrentPlanId,
				type: row.type,
				description: row.description || '',
				date_from: row.date_from || '',
				date_to: row.date_to || '',
				timeframe: row.timeframe || '',
				ist_hours: row.ist_hours || 0,
				planned_hours: row.planned_hours || 0,
				responsible: row.responsible || '',
				lead_responsible: row.lead_responsible || '',
				deadline: row.deadline || '',
				is_done: row.is_done || 0,
				is_placeholder: row.is_placeholder || 0,
				is_focus: row.is_focus || 0,
				no_ticket: row.no_ticket || 0,
				actual_hours: row.actual_hours || '',
				notes: row.notes || '',
				asana_gid: row.asana_gid || '',
				asana_url: row.asana_url || '',
				asana_task_name: row.asana_task_name || '',
			},
			success: function () {
				$('tr[data-id="' + rowId + '"] .pp-saving').removeClass('pp-saving').addClass('pp-saved');
				setTimeout(function () {
					$('tr[data-id="' + rowId + '"] .pp-saved').removeClass('pp-saved');
				}, 800);
			},
			error: function () {
				$('tr[data-id="' + rowId + '"] .pp-saving').removeClass('pp-saving').addClass('pp-error');
				toastr.error('Speichern fehlgeschlagen');
			}
		});
	}

	// Add rows
	$(document).on('click', '#pp-add-item', function () { ppAddRow('item'); });
	$(document).on('click', '#pp-add-section', function () { ppAddRow('section'); });
	$(document).on('click', '#pp-add-note', function () { ppAddRow('note'); });
	$(document).on('click', '#pp-add-spacer', function () { ppAddRow('spacer'); });

	// Add task from Asana
	$(document).on('click', '#pp-add-asana', function () {
		if (!ppCurrentPlanId || !ppCurrentPlan) return;
		var clientId = ppCurrentPlan.client_id;

		ppAsanaSearchDialog('Aufgabe aus Asana', clientId, function (gid, url, name, notes) {
			$.ajax({
				type: 'POST',
				url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce,
					action: 'uf_pp_save_row',
					row_id: 0,
					plan_id: ppCurrentPlanId,
					type: 'item',
					description: notes || name,
					asana_gid: gid,
					asana_url: url,
					asana_task_name: name,
				},
				success: function (res) {
					if (res.success) {
						ppLoadRows();
						toastr.success('Aufgabe hinzugefügt');
					}
				}
			});
		});
	});

	// Refresh Asana cache
	$(document).on('click', '#pp-refresh-asana', function () {
		var $btn = $(this);
		$btn.find('i').addClass('bx-spin');
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_refresh_asana_cache' },
			success: function () {
				$btn.find('i').removeClass('bx-spin');
				toastr.success('Asana Cache aktualisiert');
			}
		});
	});

	// Import from Excel
	$(document).on('click', '#pp-import-btn', function () {
		$('#pp-import-file').click();
	});

	$(document).on('change', '#pp-import-file', function (e) {
		var file = e.target.files[0];
		if (!file) return;
		$(this).val(''); // reset for re-import

		var reader = new FileReader();
		reader.onload = function (ev) {
			try {
				var data = new Uint8Array(ev.target.result);
				var wb = XLSX.read(data, { type: 'array' });
				var ws = wb.Sheets[wb.SheetNames[0]];
				var rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
				ppParseImport(rows, file.name);
			} catch (err) {
				toastr.error('Fehler beim Lesen: ' + err.message);
			}
		};
		reader.readAsArrayBuffer(file);
	});

	function ppParseImport(rows, filename) {
		if (rows.length < 3) { toastr.error('Datei zu kurz'); return; }

		// Row 0: Kundenname | Zeitraum
		var clientName = String(rows[0][0] || '').trim();
		var periodLabel = String(rows[0][1] || '').trim();
		// Row 1: URL | Planbezeichnung
		var clientUrl = String(rows[1][0] || '').trim();
		var planTitle = String(rows[1][1] || '').trim();

		// Try to match client by name
		var matchedClientId = 0;
		if (typeof ppAllClients !== 'undefined') {
			ppAllClients.forEach(function (c) {
				if (c.title.toLowerCase() === clientName.toLowerCase()) matchedClientId = c.id;
			});
		}

		// Parse rows: detect sections, items, notes, spacers, subtotals
		var importRows = [];
		for (var i = 2; i < rows.length; i++) {
			var r = rows[i];
			var a = String(r[0] || '').trim();  // Beschreibung
			var b = String(r[1] || '').trim();  // Ist
			var d = String(r[3] || '').trim();  // Soll
			var e_col = String(r[4] || '').trim();  // Verantwortlich
			var f = String(r[5] || '').trim();  // Deadline
			var g = String(r[6] || '').trim();  // Erledigt
			var h = String(r[7] || '').trim();  // Aufwand freitext
			var notes = String(r[8] || '').trim();  // Bemerkungen
			var asana = String(r[9] || '').trim();  // Asana URL

			// Skip empty rows → spacer
			var allEmpty = !a && !b && !d && !e_col && !f;
			if (allEmpty) {
				importRows.push({ type: 'spacer' });
				continue;
			}

			// Skip subtotal rows and header rows
			if (a === 'Aufwand ca. in Std.' || a === 'Aufwand ca. insgesamt in Std.' || a === 'Aufwand in Tagessätzen') continue;
			if (a.indexOf('Noch Rückfragen?') === 0) continue;
			if (d === 'Aufwand ca. in Std.' || d === 'Stunden-Forecast') continue;
			if (e_col === 'Name' && f === 'Termin') continue; // header row

			// Detect section: starts with number + dot, no data in other cols
			if (a.match(/^\d+\.\s/) && !b && !d && !e_col) {
				importRows.push({ type: 'section', description: a });
				continue;
			}

			// Detect note/URL: only col A has content, starts with http or is short text without data
			if (a && !b && !d && !e_col && !f && !g) {
				if (a.match(/^https?:\/\//) || a === 'tba.' || a.length < 50) {
					importRows.push({ type: 'note', description: a });
					continue;
				}
			}

			// Item row
			var isDone = (g === 'X' || g === 'x' || g === '1') ? 1 : 0;
			var istH = parseFloat(String(b).replace(',', '.')) || 0;
			var sollH = parseFloat(String(d).replace(',', '.')) || 0;
			var asanaGid = '';
			if (asana) {
				var gidMatch = asana.match(/\/(\d{10,})/);
				if (gidMatch) asanaGid = gidMatch[1];
			}

			importRows.push({
				type: 'item',
				description: a,
				ist_hours: istH,
				planned_hours: sollH,
				responsible: e_col,
				deadline: f,
				is_done: isDone,
				actual_hours: h,
				notes: notes,
				asana_gid: asanaGid,
				asana_url: asana,
				asana_task_name: asana ? a.substring(0, 60) : '',
			});
		}

		// Remove trailing spacers
		while (importRows.length && importRows[importRows.length - 1].type === 'spacer') importRows.pop();

		// Show preview dialog
		var itemCount = importRows.filter(function (r) { return r.type === 'item'; }).length;
		var sectionCount = importRows.filter(function (r) { return r.type === 'section'; }).length;

		var clientOpts = '<option value="0">Neuen Kunden anlegen</option>';
		if (typeof ppAllClients !== 'undefined') {
			ppAllClients.forEach(function (c) {
				var sel = c.id == matchedClientId ? ' selected' : '';
				clientOpts += '<option value="' + c.id + '"' + sel + '>' + escHtml(c.title) + '</option>';
			});
		}

		Swal.fire({
			title: 'Excel importieren',
			width: 480,
			html: '<div style="text-align:left;font-size:13px;">' +
				'<p style="color:#888;margin:0 0 12px;">' + sectionCount + ' Sektionen, ' + itemCount + ' Aufgaben, ' + importRows.length + ' Zeilen gesamt</p>' +
				'<label style="display:block;margin-bottom:3px;font-weight:600;color:#555;">Kunde</label>' +
				'<select id="swal-imp-client" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;margin-bottom:8px;">' + clientOpts + '</select>' +
				'<label style="display:block;margin-bottom:3px;font-weight:600;color:#555;">Bezeichnung</label>' +
				'<input id="swal-imp-title" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;margin-bottom:8px;box-sizing:border-box;" value="' + escAttr(planTitle || periodLabel || filename.replace(/\.xlsx?$/i, '')) + '">' +
				'<div style="display:flex;gap:8px;">' +
				'<div style="flex:1;"><label style="display:block;margin-bottom:3px;font-weight:600;color:#555;">Von</label><input id="swal-imp-from" type="date" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;"></div>' +
				'<div style="flex:1;"><label style="display:block;margin-bottom:3px;font-weight:600;color:#555;">Bis</label><input id="swal-imp-to" type="date" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;"></div>' +
				'</div></div>',
			showCancelButton: true,
			confirmButtonText: 'Importieren',
			cancelButtonText: 'Abbrechen',
			preConfirm: function () {
				var cid = document.getElementById('swal-imp-client').value;
				var title = document.getElementById('swal-imp-title').value.trim();
				if (!title) { Swal.showValidationMessage('Bezeichnung ist Pflichtfeld'); return false; }
				return {
					client_id: cid,
					title: title,
					period_from: document.getElementById('swal-imp-from').value,
					period_to: document.getElementById('swal-imp-to').value,
				};
			}
		}).then(function (result) {
			if (!result.isConfirmed) return;
			var d = result.value;

			toastr.info('Importiere ' + importRows.length + ' Zeilen...');

			// Create plan
			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce, action: 'uf_pp_save_plan',
					plan_id: 0, client_id: d.client_id, title: d.title,
					period_from: d.period_from, period_to: d.period_to,
					quarter: '', asana_project_gid: '', asana_section_gid: '',
				},
				success: function (res) {
					if (!res.success) { toastr.error(res.data); return; }
					var newPlanId = res.data.plan_id;

					// Create rows sequentially (to preserve order)
					var idx = 0;
					function saveNext() {
						if (idx >= importRows.length) {
							toastr.success('Import abgeschlossen!');
							ppLoadPlans(newPlanId);
							return;
						}
						var r = importRows[idx];
						var rowData = {
							security: ajaxuser.nonce, action: 'uf_pp_save_row',
							row_id: 0, plan_id: newPlanId, type: r.type,
							description: r.description || '',
							timeframe: '',
							date_from: '', date_to: '',
							ist_hours: r.ist_hours || 0,
							planned_hours: r.planned_hours || 0,
							responsible: r.responsible || '',
							deadline: r.deadline || '',
							is_done: r.is_done || 0,
							is_placeholder: 0,
							actual_hours: r.actual_hours || '',
							notes: r.notes || '',
							asana_gid: r.asana_gid || '',
							asana_url: r.asana_url || '',
							asana_task_name: r.asana_task_name || '',
						};
						idx++;
						$.ajax({
							type: 'POST', url: ajaxuser.url, data: rowData,
							success: function () { saveNext(); },
							error: function () { saveNext(); } // skip failed rows
						});
					}
					saveNext();
				}
			});
		});
	}

	function ppAddRow(type) {
		if (!ppCurrentPlanId) return;
		var defaultDesc = type === 'section' ? 'Neue Sektion' : '';
		var maxPos = 0;
		ppRows.forEach(function (r) { var p = parseInt(r.position) || 0; if (p > maxPos) maxPos = p; });

		// Create a temporary local row immediately
		var tempId = 'new_' + Date.now();
		var newRow = {
			id: tempId, plan_id: ppCurrentPlanId, type: type,
			description: defaultDesc, timeframe: '', ist_hours: 0, planned_hours: 0,
			responsible: '', deadline: '', is_done: 0, is_placeholder: 0,
			actual_hours: '', notes: '', asana_gid: '', asana_url: '', asana_task_name: '',
			position: maxPos + 1, date_from: null, date_to: null
		};
		ppRows.push(newRow);
		ppRenderTable();

		// Scroll + focus
		var $newRow = $('tr[data-id="' + tempId + '"]');
		if ($newRow.length) {
			$newRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(function () {
				var $cell = $newRow.find('.pp-cell[data-field="description"]');
				if ($cell.length) { $cell.focus(); $cell.click(); }
			}, 50);
		}

		// Save to server in background, then update the temp ID
		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_pp_save_row',
				row_id: 0,
				plan_id: ppCurrentPlanId,
				type: type,
				description: defaultDesc,
			},
			success: function (res) {
				if (res.success) {
					var realId = res.data.row_id;
					// Update local state with real ID
					for (var i = 0; i < ppRows.length; i++) {
						if (ppRows[i].id === tempId) { ppRows[i].id = realId; break; }
					}
					// Update DOM
					$('tr[data-id="' + tempId + '"]').attr('data-id', realId).data('id', realId);
				}
			}
		});
	}

	// Right-click context menu on rows
	$(document).on('contextmenu', '#pp-table-body tr.pp-item-row', function (e) {
		e.preventDefault();
		$('.pp-row-context').remove();

		var $tr = $(this);
		var rowId = $tr.data('id');
		var row = null;
		for (var i = 0; i < ppRows.length; i++) { if (ppRows[i].id == rowId) { row = ppRows[i]; break; } }
		if (!row) return;

		var isDone = parseInt(row.is_done) === 1;
		var isPh = parseInt(row.is_placeholder) === 1;
		var isFocus = parseInt(row.is_focus) === 1;

		var menu = '<div class="pp-row-context">';
		menu += '<div class="pp-rctx-item" data-action="duplicate"><i class="bx bx-copy"></i> Duplizieren</div>';
		menu += '<div class="pp-rctx-item" data-action="done"><i class="bx ' + (isDone ? 'bxs-check-circle' : 'bx-circle') + '"></i> ' + (isDone ? 'Als offen markieren' : 'Als erledigt markieren') + '</div>';
		menu += '<div class="pp-rctx-item" data-action="focus"><i class="bx ' + (isFocus ? 'bxs-flag-alt' : 'bx-flag') + '"></i> ' + (isFocus ? 'Fokus entfernen' : 'Fokus setzen') + '</div>';
		menu += '<div class="pp-rctx-item" data-action="placeholder"><i class="bx ' + (isPh ? 'bxs-hourglass' : 'bx-hourglass') + '"></i> ' + (isPh ? 'Platzhalter entfernen' : 'Als Platzhalter') + '</div>';
		menu += '<div class="pp-rctx-sep"></div>';
		menu += '<div class="pp-rctx-item" data-action="move"><i class="bx bx-transfer"></i> In anderen Plan verschieben</div>';
		menu += '<div class="pp-rctx-item pp-rctx-danger" data-action="delete"><i class="bx bx-trash"></i> Löschen</div>';
		menu += '</div>';

		$(menu).appendTo('body').css({ position: 'fixed', top: e.clientY, left: e.clientX, zIndex: 99999 })
			.attr('data-rowid', rowId);
	});

	$(document).on('click', '.pp-rctx-item', function () {
		var action = $(this).data('action');
		var rowId = $(this).closest('.pp-row-context').attr('data-rowid');
		$('.pp-row-context').remove();

		var $tr = $('tr[data-id="' + rowId + '"]');

		if (action === 'duplicate') {
			$tr.find('.pp-dup-row').click();
		} else if (action === 'delete') {
			$tr.find('.pp-delete-row').click();
		} else if (action === 'done') {
			$tr.find('.pp-toggle-done').click();
		} else if (action === 'focus') {
			$tr.find('.pp-toggle-focus').click();
		} else if (action === 'placeholder') {
			$tr.find('.pp-toggle-ph').click();
		} else if (action === 'move') {
			ppMoveRowToPlan(rowId);
		}
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pp-row-context').length) $('.pp-row-context').remove();
	});

	// Move row to another plan
	function ppMoveRowToPlan(rowId) {
		var row = null;
		for (var i = 0; i < ppRows.length; i++) { if (ppRows[i].id == rowId) { row = ppRows[i]; break; } }
		if (!row) return;

		var currentPlanId = row._planId || row.plan_id || ppCurrentPlanId;

		// Build plan options (exclude current plan)
		var planOpts = '';
		ppPlans.forEach(function (p) {
			if (p.id == currentPlanId) return;
			planOpts += '<option value="' + p.id + '">' + escHtml((p.client_short || p.client_title || '') + ' · ' + p.title) + '</option>';
		});

		if (!planOpts) {
			toastr.warning('Keine anderen Pläne verfügbar');
			return;
		}

		Swal.fire({
			title: 'In anderen Plan verschieben',
			html: '<select id="swal-move-plan" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;">' +
				'<option value="">Zielplan wählen</option>' + planOpts + '</select>',
			showCancelButton: true,
			confirmButtonText: 'Verschieben',
			cancelButtonText: 'Abbrechen',
			width: 400,
			preConfirm: function () {
				var val = document.getElementById('swal-move-plan').value;
				if (!val) { Swal.showValidationMessage('Bitte Plan wählen'); return false; }
				return val;
			}
		}).then(function (result) {
			if (!result.isConfirmed) return;
			var targetPlanId = result.value;

			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce,
					action: 'uf_pp_move_row',
					row_id: rowId,
					target_plan_id: targetPlanId,
				},
				success: function (res) {
					if (res.success) {
						// Remove from local state
						ppRows = ppRows.filter(function (r) { return r.id != rowId; });
						$('tr[data-id="' + rowId + '"]').fadeOut(200, function () { $(this).remove(); });
						ppCalculateSubtotals();
						ppMarkSectionItems();

						var targetPlan = null;
						for (var i = 0; i < ppPlans.length; i++) { if (ppPlans[i].id == targetPlanId) { targetPlan = ppPlans[i]; break; } }
						var targetName = targetPlan ? (targetPlan.client_short || '') + ' · ' + targetPlan.title : 'Plan';
						toastr.success('Verschoben nach: ' + targetName);
					} else {
						toastr.error(res.data || 'Fehler');
					}
				}
			});
		});
	}

	// Duplicate row
	$(document).on('click', '.pp-dup-row', function (e) {
		e.stopPropagation();
		var rowId = $(this).closest('tr').data('id');
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (!row) return;

		// Insert copy locally after this row
		var idx = ppRows.indexOf(row);
		var tempId = 'dup_' + Date.now();
		var copy = $.extend({}, row, { id: tempId, is_done: 0, is_placeholder: 0 });
		ppRows.splice(idx + 1, 0, copy);
		ppRows.forEach(function (r, i) { r.position = i; });
		ppRenderTable();

		var $newRow = $('tr[data-id="' + tempId + '"]');
		if ($newRow.length) $newRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

		// Save to server
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce, action: 'uf_pp_save_row',
				row_id: 0, plan_id: ppCurrentPlanId, type: row.type,
				description: row.description || '', timeframe: row.timeframe || '',
				date_from: row.date_from || '', date_to: row.date_to || '',
				ist_hours: row.ist_hours || 0, planned_hours: row.planned_hours || 0,
				responsible: row.responsible || '', lead_responsible: row.lead_responsible || '',
				deadline: row.deadline || '',
				is_done: 0, is_placeholder: 0, no_ticket: row.no_ticket || 0,
				actual_hours: row.actual_hours || '', notes: row.notes || '',
				asana_gid: row.asana_gid || '', asana_url: row.asana_url || '',
				asana_task_name: row.asana_task_name || '',
			},
			success: function (res) {
				if (res.success) {
					var realId = res.data.row_id;
					for (var i = 0; i < ppRows.length; i++) {
						if (ppRows[i].id === tempId) { ppRows[i].id = realId; break; }
					}
					$('tr[data-id="' + tempId + '"]').attr('data-id', realId).data('id', realId);
					// Save positions
					var positions = [];
					ppRows.forEach(function (r, i) { positions.push({ id: r.id, position: i }); });
					$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_pp_reorder_rows', plan_id: ppCurrentPlanId, positions: positions } });
				}
			}
		});
		toastr.success('Dupliziert');
	});

	// Delete row with undo
	var ppLastDeleted = null;

	$(document).on('click', '.pp-delete-row', function (e) {
		e.stopPropagation();
		var rowId = $(this).closest('tr').data('id');
		var $tr = $(this).closest('tr');

		// Save row data for undo
		var deletedRow = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { deletedRow = $.extend({}, ppRows[i]); break; }
		}

		$tr.fadeOut(150, function () { $tr.remove(); });
		ppRows = ppRows.filter(function (r) { return r.id != rowId; });
		ppCalculateSubtotals();
		ppMarkSectionItems();

		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_delete_row', row_id: rowId }
		});

		// Show undo toast
		if (deletedRow) {
			ppLastDeleted = deletedRow;
			var typeName = deletedRow.type === 'section' ? 'Sektion' : (deletedRow.type === 'note' ? 'Notiz' : 'Aufgabe');
			toastr.options.timeOut = 8000;
			toastr.options.closeButton = true;
			toastr.options.onclick = function () {
				ppUndoDelete();
			};
			toastr.warning('<span style="cursor:pointer;"><b>Rückgängig</b> – ' + typeName + ' gelöscht</span>', '', {
				timeOut: 8000,
				onclick: function () { ppUndoDelete(); }
			});
			toastr.options.timeOut = 2000;
			toastr.options.closeButton = false;
			toastr.options.onclick = null;
		}
	});

	function ppUndoDelete() {
		if (!ppLastDeleted) return;
		var row = ppLastDeleted;
		ppLastDeleted = null;

		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce, action: 'uf_pp_save_row',
				row_id: 0, plan_id: row.plan_id, type: row.type,
				description: row.description || '', timeframe: row.timeframe || '',
				date_from: row.date_from || '', date_to: row.date_to || '',
				ist_hours: row.ist_hours || 0, planned_hours: row.planned_hours || 0,
				responsible: row.responsible || '', deadline: row.deadline || '',
				is_done: row.is_done || 0, is_placeholder: row.is_placeholder || 0,
				actual_hours: row.actual_hours || '', notes: row.notes || '',
				asana_gid: row.asana_gid || '', asana_url: row.asana_url || '',
				asana_task_name: row.asana_task_name || '',
			},
			success: function (res) {
				if (res.success) {
					toastr.success('Wiederhergestellt');
					ppLoadRows();
				}
			}
		});
	}

	// Auto-save revision periodically (every 5 minutes while editing)
	var ppRevisionTimer = null;
	function ppScheduleAutoRevision() {
		if (ppRevisionTimer) clearTimeout(ppRevisionTimer);
		ppRevisionTimer = setTimeout(function () {
			if (ppCurrentPlanId) {
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_pp_save_revision', plan_id: ppCurrentPlanId, label: 'Auto-Sicherung' }
				});
			}
		}, 5 * 60 * 1000); // 5 min
	}

	// Trigger auto-revision timer on edits
	$(document).on('input change', '#pp-table-body .pp-field, #pp-table-body .pp-resp-input', function () {
		ppScheduleAutoRevision();
	});

	// Manual revision save + revision list
	// Inline feedback click - show comments and mark as read
	$(document).on('click', '.pp-fb-indicator', function (e) {
		e.stopPropagation();
		var rowId = $(this).data('rowid');
		var $indicator = $(this);
		var $tr = $indicator.closest('tr');

		// Toggle existing popup
		if ($tr.next('.pp-fb-inline-row').length) {
			$tr.next('.pp-fb-inline-row').remove();
			return;
		}

		var fbList = ppFeedbackByRow[rowId] || [];
		if (!fbList.length) return;

		var html = '<tr class="pp-fb-inline-row"><td></td><td colspan="12"><div class="pp-fb-inline">';
		fbList.forEach(function (fb) {
			var isUnread = !fb.read_at;
			var time = (fb.created || '').replace(/-/g, '.').replace(' ', ' · ');
			html += '<div class="pp-fb-inline-item' + (isUnread ? ' pp-fb-unread' : '') + '" data-fbid="' + fb.id + '">';
			html += '<strong>' + escHtml(fb.author_name || 'Anonym') + '</strong>';
			if (fb.message) html += ': ' + escHtml(fb.message);
			html += ' <span style="color:#bbb;font-size:9px;">' + time + '</span>';
			if (isUnread) html += ' <button class="pp-fb-mark-read" data-fbid="' + fb.id + '" title="Als gelesen markieren"><i class="bx bx-check"></i></button>';
			html += ' <button class="pp-fb-delete-admin" data-fbid="' + fb.id + '" title="Löschen"><i class="bx bx-trash"></i></button>';
			html += '</div>';
		});
		html += '</div></td></tr>';

		$tr.after(html);

		// Mark all as read
		fbList.forEach(function (fb) {
			if (!fb.read_at) {
				$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_pp_mark_feedback_read', feedback_id: fb.id } });
				fb.read_at = 'now';
			}
		});
		// Update indicator to read state
		$indicator.removeClass('pp-fb-unread').addClass('pp-fb-read');
		$indicator.find('i').attr('class', 'bx bx-comment-check');
		$indicator.find('.pp-fb-count').remove();
	});

	$(document).on('click', '.pp-fb-delete-admin', function (e) {
		e.stopPropagation();
		var fbId = $(this).data('fbid');
		var $item = $(this).closest('.pp-fb-inline-item');
		$item.fadeOut(150, function () { $item.remove(); });
		$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_pp_delete_feedback', feedback_id: fbId } });
	});

	$(document).on('click', '.pp-fb-mark-read', function (e) {
		e.stopPropagation();
		var fbId = $(this).data('fbid');
		$(this).closest('.pp-fb-inline-item').removeClass('pp-fb-unread');
		$(this).remove();
		$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_pp_mark_feedback_read', feedback_id: fbId } });
	});

	// Feedback viewer (dialog - shows all)
	$(document).on('click', '#pp-feedback-btn', function () {
		if (!ppCurrentPlanId) return;

		Swal.fire({
			title: 'Kunden-Feedback',
			width: 560,
			html: '<div id="pp-fb-dialog" style="text-align:left;"><div style="text-align:center;padding:15px;"><i class="bx bx-loader-alt bx-spin"></i></div></div>',
			showConfirmButton: false,
			showCancelButton: true,
			cancelButtonText: 'Schließen',
			didOpen: function (popup) {
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_pp_get_feedback', plan_id: ppCurrentPlanId },
					success: function (res) {
						if (!res.success || !res.data.length) {
							$(popup).find('#pp-fb-dialog').html('<p style="color:#999;font-size:13px;">Noch kein Feedback vorhanden.</p>');
							return;
						}
						var h = '';
						var byRow = {};
						res.data.forEach(function (fb) {
							var rid = fb.row_id || 0;
							if (!byRow[rid]) byRow[rid] = [];
							byRow[rid].push(fb);
						});

						Object.keys(byRow).forEach(function (rid) {
							var items = byRow[rid];
							// Find row description
							var rowDesc = '(Allgemein)';
							for (var i = 0; i < ppRows.length; i++) {
								if (ppRows[i].id == rid) { rowDesc = (ppRows[i].description || '').substring(0, 60); break; }
							}

							h += '<div style="margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:8px;">';
							h += '<div style="font-size:12px;font-weight:600;color:#333;margin-bottom:4px;">' + escHtml(rowDesc) + '</div>';
							items.forEach(function (fb) {
								var icon = fb.feedback_type === 'like' ? '👍' : (fb.feedback_type === 'dislike' ? '👎' : '💬');
								var time = fb.created.replace(/-/g, '.').replace(' ', ' · ');
								h += '<div style="font-size:12px;padding:3px 0;color:#555;">';
								h += icon + ' <strong>' + escHtml(fb.author_name) + '</strong>';
								if (fb.message) h += ': ' + escHtml(fb.message);
								h += ' <span style="color:#bbb;font-size:10px;">' + time + '</span>';
								h += '</div>';
							});
							h += '</div>';
						});

						$(popup).find('#pp-fb-dialog').html('<div style="max-height:400px;overflow-y:auto;">' + h + '</div>');
					}
				});
			}
		});
	});

	$(document).on('click', '#pp-revisions-btn', function () {
		if (!ppCurrentPlanId) return;

		Swal.fire({
			title: 'Versionen',
			width: 520,
			html: '<div id="pp-rev-dialog" style="text-align:left;"><div style="text-align:center;padding:15px;"><i class="bx bx-loader-alt bx-spin"></i></div></div>',
			showConfirmButton: false,
			showCancelButton: true,
			cancelButtonText: 'Schließen',
			didOpen: function (popup) {
				ppLoadRevisions(popup);
			}
		});
	});

	function ppLoadRevisions(popup) {
		// First save current state as revision
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_save_revision', plan_id: ppCurrentPlanId, label: 'Aktueller Stand' },
			success: function () {
				// Then load all revisions
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_pp_get_revisions', plan_id: ppCurrentPlanId },
					success: function (res) {
						if (!res.success) return;
						var revs = res.data;
						var h = '<div style="margin-bottom:10px;display:flex;gap:6px;">';
						h += '<input type="text" id="pp-rev-label" placeholder="Beschreibung (optional)" style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">';
						h += '<button type="button" id="pp-rev-save" style="padding:6px 12px;border:1px solid #ddd;border-radius:4px;font-size:12px;cursor:pointer;background:#f8f8f8;">Sicherungspunkt erstellen</button>';
						h += '</div>';

						if (revs.length) {
							h += '<div style="max-height:300px;overflow-y:auto;border:1px solid #eee;border-radius:5px;">';
							revs.forEach(function (r, idx) {
								var d = r.created.replace(/-/g, '.').replace(' ', ' · ');
								var label = r.label || '';
								var isFirst = idx === 0;
								h += '<div class="pp-rev-item" data-id="' + r.id + '" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid #f5f5f5;' + (isFirst ? 'background:#f0faf0;' : '') + '">';
								h += '<div style="flex:1;"><div style="font-size:12px;color:#333;">' + escHtml(label || 'Version') + '</div>';
								h += '<div style="font-size:10px;color:#999;">' + d + (r.display_name ? ' · ' + escHtml(r.display_name) : '') + '</div></div>';
								if (!isFirst) h += '<button class="pp-rev-restore" data-id="' + r.id + '" style="padding:4px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;background:#fff;color:#555;">Wiederherstellen</button>';
								else h += '<span style="font-size:10px;color:#4caf50;font-weight:600;">Aktuell</span>';
								h += '</div>';
							});
							h += '</div>';
						} else {
							h += '<p style="color:#999;font-size:12px;">Keine Versionen vorhanden.</p>';
						}

						$(popup).find('#pp-rev-dialog').html(h);

						// Save manual revision
						$(popup).on('click', '#pp-rev-save', function () {
							var label = $(popup).find('#pp-rev-label').val().trim() || 'Manueller Sicherungspunkt';
							$.ajax({
								type: 'POST', url: ajaxuser.url,
								data: { security: ajaxuser.nonce, action: 'uf_pp_save_revision', plan_id: ppCurrentPlanId, label: label },
								success: function () {
									toastr.success('Sicherungspunkt erstellt');
									ppLoadRevisions(popup);
								}
							});
						});

						// Restore revision
						$(popup).on('click', '.pp-rev-restore', function () {
							var revId = $(this).data('id');
							Swal.fire({
								title: 'Version wiederherstellen?',
								text: 'Der aktuelle Stand wird vorher gesichert.',
								icon: 'question',
								showCancelButton: true,
								confirmButtonText: 'Wiederherstellen',
								cancelButtonText: 'Abbrechen',
							}).then(function (result) {
								if (result.isConfirmed) {
									$.ajax({
										type: 'POST', url: ajaxuser.url,
										data: { security: ajaxuser.nonce, action: 'uf_pp_restore_revision', revision_id: revId },
										success: function (res) {
											if (res.success) {
												toastr.success('Version wiederhergestellt');
												ppLoadRows();
												Swal.close();
											} else {
												toastr.error(res.data);
											}
										}
									});
								}
							});
						});
					}
				});
			}
		});
	}

	// New plan
	$(document).on('click', '#pp-new-plan-btn', function () {
		ppOpenPlanModal(0);
	});

	// Edit plan
	$(document).on('click', '#pp-edit-plan', function () {
		if (ppCurrentPlanId) ppOpenPlanModal(ppCurrentPlanId);
	});

	function ppOpenPlanModal(planId) {
		var plan = null;
		if (planId) {
			for (var i = 0; i < ppPlans.length; i++) {
				if (ppPlans[i].id == planId) { plan = ppPlans[i]; break; }
			}
		}

		var clientOptions = '';
		if (typeof ppAllClients !== 'undefined') {
			ppAllClients.forEach(function (c) {
				var sel = plan && plan.client_id == c.id ? ' selected' : '';
				clientOptions += '<option value="' + c.id + '"' + sel + '>' + escHtml(c.title) + '</option>';
			});
		}

		var f = 'style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;"';
		var lbl = 'style="display:block;margin-bottom:3px;font-weight:600;color:#555;font-size:12px;"';

		Swal.fire({
			title: planId ? 'Plan bearbeiten' : 'Neuer Plan',
			width: 440,
			html:
				'<div style="text-align:left;">' +
				'<label ' + lbl + '>Kunde *</label>' +
				'<select id="swal-pp-client" ' + f + ' style="margin-bottom:8px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;background:#fff;">' +
				'<option value="">Bitte wählen</option>' + clientOptions + '</select>' +
				'<label ' + lbl + '>Bezeichnung *</label>' +
				'<input id="swal-pp-title" ' + f + ' style="margin-bottom:8px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;" placeholder="z.B. Monatliche Optimierung, Q2 2026" value="' + escAttr(plan ? plan.title : '') + '">' +
				'<div style="display:flex;gap:8px;margin-bottom:8px;">' +
				'<div style="flex:1;"><label ' + lbl + '>Von *</label>' +
				'<input id="swal-pp-from" type="date" ' + f + ' value="' + (plan ? plan.period_from || '' : '') + '"></div>' +
				'<div style="flex:1;"><label ' + lbl + '>Bis *</label>' +
				'<input id="swal-pp-to" type="date" ' + f + ' value="' + (plan ? plan.period_to || '' : '') + '"></div>' +
				'</div>' +
				'<label ' + lbl + '>Status</label>' +
				'<select id="swal-pp-status" ' + f + ' style="margin-bottom:8px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;background:#fff;">' +
				'<option value="entwurf"' + (plan && plan.plan_status === 'entwurf' ? ' selected' : (!plan ? ' selected' : '')) + '>Entwurf</option>' +
				'<option value="aktiv"' + (plan && plan.plan_status === 'aktiv' ? ' selected' : '') + '>Aktiv</option>' +
				'<option value="einzelprojekt"' + (plan && plan.plan_status === 'einzelprojekt' ? ' selected' : '') + '>Einzelprojekt</option>' +
				'<option value="reporting"' + (plan && plan.plan_status === 'reporting' ? ' selected' : '') + '>Fertig für Reporting</option>' +
				'<option value="abgeschlossen"' + (plan && plan.plan_status === 'abgeschlossen' ? ' selected' : '') + '>Abgeschlossen</option>' +
				'<option value="archiviert"' + (plan && plan.plan_status === 'archiviert' ? ' selected' : '') + '>Archiviert</option>' +
				'</select>' +
				'<label ' + lbl + '>Asana <small style="font-weight:400;color:#aaa;">(für Task-Erstellung)</small></label>' +
				'<div style="display:flex;gap:8px;">' +
				'<div style="flex:1;"><select id="swal-pp-asana-proj" ' + f + ' style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;background:#fff;">' +
				'<option value="">Kein Projekt</option></select></div>' +
				'<div style="flex:1;"><select id="swal-pp-asana-sec" ' + f + ' style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;background:#fff;" disabled>' +
				'<option value="">Keine Spalte</option></select></div>' +
				'</div></div>',
			showCancelButton: true,
			confirmButtonText: 'Speichern',
			cancelButtonText: 'Abbrechen',
			didOpen: function (popup) {
				var $p = $(popup);
				var savedSectionGid = plan ? plan.asana_section_gid || '' : '';

				function loadSectionsForPlan(projGid) {
					var $sec = $p.find('#swal-pp-asana-sec');
					if (!projGid) { $sec.html('<option value="">Keine Spalte</option>').prop('disabled', true); return; }
					$sec.html('<option value="">Lade...</option>').prop('disabled', true);
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_get_asana_sections', project_gid: projGid },
						success: function (res) {
							var h = '<option value="">Keine Spalte</option>';
							if (res.success && res.data && res.data.length) {
								res.data.forEach(function (s) {
									var sel = savedSectionGid === s.gid ? ' selected' : '';
									h += '<option value="' + escAttr(s.gid) + '"' + sel + '>' + escHtml(s.name) + '</option>';
								});
							}
							$sec.html(h).prop('disabled', false);
						},
						error: function () {
							$sec.html('<option value="">Fehler</option>').prop('disabled', false);
						}
					});
				}

				// Load Asana projects
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_get_asana_projects' },
					success: function (res) {
						if (!res.success) return;
						var $sel = $p.find('#swal-pp-asana-proj');
						var h = '<option value="">Kein Projekt</option>';
						res.data.forEach(function (p) {
							var sel = plan && plan.asana_project_gid == p.gid ? ' selected' : '';
							h += '<option value="' + escAttr(p.gid) + '"' + sel + '>' + escHtml(p.name) + '</option>';
						});
						$sel.html(h);
						// Load sections for pre-selected project
						var pre = $sel.val();
						if (pre) loadSectionsForPlan(pre);
					}
				});

				$p.find('#swal-pp-asana-proj').on('change', function () {
					savedSectionGid = ''; // reset when project changes
					loadSectionsForPlan($(this).val());
				});
			},
			preConfirm: function () {
				var client = document.getElementById('swal-pp-client').value;
				var title = document.getElementById('swal-pp-title').value;
				var from = document.getElementById('swal-pp-from').value;
				var to = document.getElementById('swal-pp-to').value;
				if (!client || !title || !from || !to) {
					Swal.showValidationMessage('Alle Felder sind Pflichtfelder');
					return false;
				}
				return {
					client_id: client,
					title: title,
					quarter: '',
					period_from: from,
					period_to: to,
					plan_status: document.getElementById('swal-pp-status').value,
					asana_project_gid: document.getElementById('swal-pp-asana-proj').value,
					asana_section_gid: document.getElementById('swal-pp-asana-sec').value,
				};
			}
		}).then(function (result) {
			if (result.isConfirmed) {
				var d = result.value;
				$.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_pp_save_plan',
						plan_id: planId || 0,
						client_id: d.client_id,
						title: d.title,
						quarter: d.quarter,
						period_from: d.period_from,
						period_to: d.period_to,
						plan_status: d.plan_status,
						asana_project_gid: d.asana_project_gid,
						asana_section_gid: d.asana_section_gid,
					},
					success: function (res) {
						if (res.success) {
							toastr.success('Plan gespeichert');
							// Add client to filter if not present
							var cid = d.client_id;
							if (!$('#pp-client-filter option[value="' + cid + '"]').length) {
								var cName = '';
								ppAllClients.forEach(function (c) { if (c.id == cid) cName = c.title; });
								$('#pp-client-filter').append('<option value="' + cid + '">' + escHtml(cName) + '</option>');
							}
							ppLoadPlans(res.data.plan_id);
						} else {
							toastr.error(res.data);
						}
					}
				});
			}
		});
	}

	// Delete plan
	// Share plan dialog
	$(document).on('click', '#pp-share-plan', function () {
		if (!ppCurrentPlanId) return;

		Swal.fire({
			title: 'Plan freigeben',
			width: 500,
			html: '<div id="pp-share-dialog" style="text-align:left;"><div style="text-align:center;padding:10px;"><i class="bx bx-loader-alt bx-spin"></i></div></div>',
			showConfirmButton: false,
			showCancelButton: true,
			cancelButtonText: 'Schließen',
			didOpen: function (popup) {
				ppLoadShareDialog(popup);
			}
		});
	});

	function ppLoadShareDialog(popup) {
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_get_shares', plan_id: ppCurrentPlanId },
			success: function (res) {
				if (!res.success) return;
				var d = res.data;
				var h = '<div style="text-align:left;">';

				// Client link section
				h += '<h4 style="margin:0 0 8px;font-size:13px;color:#555;">Kunden-Link (Read-Only)</h4>';
				if (d.share_hash) {
					h += '<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">';
					h += '<input type="text" id="pp-share-url" value="' + escAttr(d.share_url) + '" readonly style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;color:#666;">';
					h += '<button type="button" id="pp-share-copy" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;background:#f8f8f8;">Kopieren</button>';
					h += '<button type="button" id="pp-share-remove-link" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;background:#f8f8f8;color:#d32f2f;">Deaktivieren</button>';
					h += '</div>';
					h += '<small style="color:#aaa;font-size:10px;">' + (d.has_password ? 'Passwortgeschützt' : 'Ohne Passwort') + '</small>';
				} else {
					h += '<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">';
					h += '<input type="password" id="pp-share-pw" placeholder="Passwort (optional)" style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">';
					h += '<button type="button" id="pp-share-generate" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;background:#f8f8f8;">Link erstellen</button>';
					h += '</div>';
				}

				// User sharing section
				h += '<hr style="margin:12px 0;border:none;border-top:1px solid #eee;">';
				h += '<h4 style="margin:0 0 8px;font-size:13px;color:#555;">Tallyr-Nutzer</h4>';

				if (d.shares && d.shares.length) {
					d.shares.forEach(function (s) {
						h += '<div class="pp-share-user" data-uid="' + s.user_id + '" style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #f5f5f5;">';
						h += '<span style="flex:1;font-size:12px;">' + escHtml(s.display_name) + '</span>';
						h += '<select class="pp-share-perm-select" data-uid="' + s.user_id + '" style="padding:4px 6px;border:1px solid #ddd;border-radius:3px;font-size:11px;">';
						h += '<option value="read"' + (s.permission === 'read' ? ' selected' : '') + '>Lesen</option>';
						h += '<option value="edit"' + (s.permission === 'edit' ? ' selected' : '') + '>Editor</option>';
						h += '<option value="write"' + (s.permission === 'write' ? ' selected' : '') + '>Vollzugriff</option>';
						h += '</select>';
						h += '<button class="pp-share-remove-user" data-uid="' + s.user_id + '" style="border:none;background:transparent;color:#ccc;font-size:16px;cursor:pointer;">&times;</button>';
						h += '</div>';
					});
				}

				h += '<div style="display:flex;gap:6px;margin-top:8px;">';
				h += '<select id="pp-share-add-user" style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;"><option value="">Nutzer hinzufügen...</option></select>';
				h += '<select id="pp-share-add-perm" style="padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;"><option value="read">Lesen</option><option value="edit" selected>Editor</option><option value="write">Vollzugriff</option></select>';
				h += '<button type="button" id="pp-share-add-btn" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;cursor:pointer;background:#f8f8f8;">+</button>';
				h += '</div>';

				h += '</div>';
				$(popup).find('#pp-share-dialog').html(h);

				// Load WP users for the add dropdown (separate AJAX call since ppUsersData no longer has WP users)
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_get_wp_users_for_share' },
					success: function (res) {
						if (!res.success || !res.data) return;
						var existingUids = {};
						if (d.shares) d.shares.forEach(function (s) { existingUids[s.user_id] = true; });
						res.data.forEach(function (u) {
							if (!existingUids[u.id]) {
								$('#pp-share-add-user').append('<option value="' + u.id + '">' + escHtml(u.name) + '</option>');
							}
						});
					}
				});

				// Event handlers inside Swal
				$(popup).on('click', '#pp-share-copy', function () {
					var inp = document.getElementById('pp-share-url');
					inp.select();
					document.execCommand('copy');
					toastr.success('Link kopiert');
				});

				$(popup).on('click', '#pp-share-generate', function () {
					var pw = $(popup).find('#pp-share-pw').val();
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_generate_share_link', plan_id: ppCurrentPlanId, password: pw },
						success: function (res) {
							ppLoadShareDialog(popup);
							if (res.success && res.data && res.data.share_hash) {
								ppCurrentPlan.share_hash = res.data.share_hash;
								var shareUrl = window.location.origin + '/tallyr/projektplan/?id=' + res.data.share_hash;
								$('#pp-share-link-btn').attr('href', shareUrl).show();
							}
						}
					});
				});

				$(popup).on('click', '#pp-share-remove-link', function () {
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_remove_share_link', plan_id: ppCurrentPlanId },
						success: function () {
							ppLoadShareDialog(popup);
							ppCurrentPlan.share_hash = '';
							$('#pp-share-link-btn').hide();
						}
					});
				});

				$(popup).on('click', '#pp-share-add-btn', function () {
					var uid = $(popup).find('#pp-share-add-user').val();
					var perm = $(popup).find('#pp-share-add-perm').val();
					if (!uid) return;
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_add_share', plan_id: ppCurrentPlanId, share_user_id: uid, permission: perm },
						success: function () { ppLoadShareDialog(popup); toastr.success('Nutzer hinzugefügt'); }
					});
				});

				$(popup).on('change', '.pp-share-perm-select', function () {
					var uid = $(this).data('uid');
					var perm = $(this).val();
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_add_share', plan_id: ppCurrentPlanId, share_user_id: uid, permission: perm },
						success: function () { toastr.success('Berechtigung geändert'); }
					});
				});

				$(popup).on('click', '.pp-share-remove-user', function () {
					var uid = $(this).data('uid');
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_pp_remove_share', plan_id: ppCurrentPlanId, share_user_id: uid },
						success: function () { ppLoadShareDialog(popup); toastr.success('Freigabe entfernt'); }
					});
				});
			}
		});
	}

	$(document).on('click', '#pp-delete-plan', function () {
		if (!ppCurrentPlanId) return;
		Swal.fire({
			title: 'Plan löschen?',
			text: 'Der Plan und alle Einträge werden gelöscht.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Ja, löschen',
			cancelButtonText: 'Abbrechen',
		}).then(function (result) {
			if (result.isConfirmed) {
				$.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_pp_delete_plan',
						plan_id: ppCurrentPlanId,
					},
					success: function () {
						ppCurrentPlanId = null;
						$('#pp-plan-header').hide();
						$('#pp-table-container').hide();
						$('#pp-empty-state').show();
						ppLoadPlans();
						toastr.success('Plan gelöscht');
					}
				});
			}
		});
	});

	// Duplicate plan
	$(document).on('click', '#pp-duplicate-plan', function () {
		if (!ppCurrentPlanId || !ppCurrentPlan) return;
		var f = 'style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;"';
		var lbl = 'style="display:block;margin-bottom:3px;font-weight:600;color:#555;font-size:12px;"';

		Swal.fire({
			title: 'Plan duplizieren',
			width: 440,
			html: '<div style="text-align:left;">' +
				'<label ' + lbl + '>Bezeichnung</label>' +
				'<input id="swal-dup-title" ' + f + ' value="' + escAttr(ppCurrentPlan.title) + '" style="margin-bottom:8px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;">' +
				'<div style="display:flex;gap:8px;margin-bottom:8px;">' +
				'<div style="flex:1;"><label ' + lbl + '>Von</label><input id="swal-dup-from" type="date" ' + f + '></div>' +
				'<div style="flex:1;"><label ' + lbl + '>Bis</label><input id="swal-dup-to" type="date" ' + f + '></div>' +
				'</div>' +
				'<label ' + lbl + '>Status</label>' +
				'<select id="swal-dup-status" ' + f + ' style="margin-bottom:4px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;background:#fff;">' +
				'<option value="entwurf" selected>Entwurf</option><option value="aktiv">Aktiv</option></select>' +
				'</div>',
			showCancelButton: true,
			confirmButtonText: 'Duplizieren',
			cancelButtonText: 'Abbrechen',
			preConfirm: function () {
				return {
					title: document.getElementById('swal-dup-title').value,
					period_from: document.getElementById('swal-dup-from').value,
					period_to: document.getElementById('swal-dup-to').value,
					status: document.getElementById('swal-dup-status').value,
				};
			}
		}).then(function (result) {
			if (result.isConfirmed) {
				$.ajax({
					type: 'POST',
					url: ajaxuser.url,
					data: {
						security: ajaxuser.nonce,
						action: 'uf_pp_duplicate_plan',
						plan_id: ppCurrentPlanId,
						new_title: result.value.title,
						new_period_from: result.value.period_from,
						new_period_to: result.value.period_to,
						new_status: result.value.status,
					},
					success: function (res) {
						if (res.success) {
							toastr.success('Plan dupliziert');
							ppLoadPlans(res.data.plan_id);
						}
					}
				});
			}
		});
	});

	// Excel export
	$(document).on('click', '#pp-export-plan', function () {
		if (!ppCurrentPlan || !ppCurrentPlanId) return;
		var $btn = $(this);
		$btn.find('i').attr('class', 'bx bx-loader-alt bx-spin');

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: { security: ajaxuser.nonce, action: 'uf_pp_export_report', plan_id: ppCurrentPlanId },
			success: function (res) {
				$btn.find('i').attr('class', 'bx bx-download');
				if (!res.success) { toastr.error(res.data || 'Export fehlgeschlagen'); return; }

				// Download base64 as file
				var byteChars = atob(res.data.data);
				var byteNumbers = new Array(byteChars.length);
				for (var i = 0; i < byteChars.length; i++) byteNumbers[i] = byteChars.charCodeAt(i);
				var byteArray = new Uint8Array(byteNumbers);
				var blob = new Blob([byteArray], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
				var link = document.createElement('a');
				link.href = URL.createObjectURL(blob);
				link.download = res.data.filename;
				link.click();
				URL.revokeObjectURL(link.href);
				toastr.success('Report exportiert');
			},
			error: function () {
				$btn.find('i').attr('class', 'bx bx-download');
				toastr.error('Export fehlgeschlagen');
			}
		});
	});

	// Drag and drop
	// Mark items belonging to a section with a colored left border
	var ppSectionColors = ['#6b8cae', '#7b9e6b', '#ae856b', '#8b6bae', '#ae6b8c', '#6bae9e'];
	// Insert row between existing rows
	var ppInsertLine = $('<div class="pp-insert-line"><button class="pp-insert-btn" data-type="item" title="Aufgabe"><i class="bx bx-plus"></i></button><button class="pp-insert-btn" data-type="section" title="Sektion"><i class="bx bx-heading"></i></button><button class="pp-insert-btn" data-type="note" title="Notiz"><i class="bx bx-note"></i></button><button class="pp-insert-btn" data-type="spacer" title="Abstand"><i class="bx bx-minus"></i></button></div>');
	var ppInsertAfterRowId = null;
	var ppInsertTimeout = null;

	$(document).on('mousemove', '#pp-table-body tr', function (e) {
		// Don't show insert line while editing a cell
		if (document.activeElement && $(document.activeElement).closest('#pp-table-body').length) return;

		var $tr = $(this);
		var rect = $tr[0].getBoundingClientRect();
		var bottomZone = e.clientY > rect.bottom - 8;

		if (bottomZone) {
			if (ppInsertTimeout) clearTimeout(ppInsertTimeout);
			ppInsertAfterRowId = $tr.data('id');
			var offset = $tr.offset();
			ppInsertLine.css({
				top: offset.top + $tr.outerHeight() - 2,
				left: offset.left,
				width: $tr.outerWidth()
			}).appendTo('body').show();
		} else {
			if (ppInsertTimeout) clearTimeout(ppInsertTimeout);
			ppInsertTimeout = setTimeout(function () { ppInsertLine.hide(); }, 200);
		}
	});

	$(document).on('mouseleave', '#pp-table-body', function () {
		ppInsertTimeout = setTimeout(function () { ppInsertLine.hide(); }, 200);
	});

	// Hide when focus enters a cell
	$(document).on('focus', '#pp-table-body .pp-cell, #pp-table-body input, #pp-table-body .pp-resp-input', function () {
		ppInsertLine.hide();
	});

	ppInsertLine.on('mouseenter', function () {
		if (ppInsertTimeout) clearTimeout(ppInsertTimeout);
	}).on('mouseleave', function () {
		ppInsertTimeout = setTimeout(function () { ppInsertLine.hide(); }, 300);
	});

	$(document).on('click', '.pp-insert-btn', function (e) {
		e.stopPropagation();
		if (!ppCurrentPlanId || !ppInsertAfterRowId) return;
		var type = $(this).data('type');
		ppInsertLine.hide();

		// Find index in ppRows of the row we're inserting after
		var afterIdx = -1;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == ppInsertAfterRowId) { afterIdx = i; break; }
		}

		// Create temp row locally
		var tempId = 'ins_' + Date.now();
		var newRow = {
			id: tempId, plan_id: ppCurrentPlanId, type: type,
			description: type === 'section' ? 'Neue Sektion' : '', timeframe: '',
			ist_hours: 0, planned_hours: 0, responsible: '', deadline: '',
			is_done: 0, is_placeholder: 0, actual_hours: '', notes: '',
			asana_gid: '', asana_url: '', asana_task_name: '',
			position: afterIdx + 1, date_from: null, date_to: null
		};

		// Insert into local array at the right position
		ppRows.splice(afterIdx + 1, 0, newRow);

		// Re-number positions
		ppRows.forEach(function (r, idx) { r.position = idx; });

		// Render immediately
		ppRenderTable();

		// Focus the new row
		var $newRow = $('tr[data-id="' + tempId + '"]');
		if ($newRow.length) {
			$newRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(function () {
				var $cell = $newRow.find('.pp-cell[data-field="description"]');
				if ($cell.length) { $cell.focus(); $cell.click(); }
			}, 50);
		}

		// Save to server in background
		$.ajax({
			type: 'POST', url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce, action: 'uf_pp_save_row',
				row_id: 0, plan_id: ppCurrentPlanId, type: type,
				description: type === 'section' ? 'Neue Sektion' : '',
			},
			success: function (res) {
				if (!res.success) return;
				var realId = res.data.row_id;
				// Update local ID
				for (var i = 0; i < ppRows.length; i++) {
					if (ppRows[i].id === tempId) { ppRows[i].id = realId; break; }
				}
				$('tr[data-id="' + tempId + '"]').attr('data-id', realId).data('id', realId);

				// Save all positions to server
				var positions = [];
				ppRows.forEach(function (r, idx) { positions.push({ id: r.id, position: idx }); });
				$.ajax({
					type: 'POST', url: ajaxuser.url,
					data: { security: ajaxuser.nonce, action: 'uf_pp_reorder_rows', plan_id: ppCurrentPlanId, positions: positions }
				});
			}
		});
	});

	function ppMarkSectionItems() {
		var colorIdx = 0;
		var currentColor = '';
		$('#pp-table-body tr td:first-child').css('border-left', '');
		$('#pp-table-body tr').each(function () {
			if ($(this).data('type') === 'section') {
				currentColor = ppSectionColors[colorIdx % ppSectionColors.length];
				colorIdx++;
			}
			if (currentColor) {
				$(this).find('td:first-child').css('border-left', '3px solid ' + currentColor);
			}
		});
	}

	function ppInitDragDrop() {
		var rows = document.querySelectorAll('#pp-table-body tr[draggable="true"]');
		var dragRow = null;

		rows.forEach(function (row) {
			row.addEventListener('dragstart', function (e) {
				dragRow = this;
				this.classList.add('pp-dragging');
				e.dataTransfer.effectAllowed = 'move';
			});

			row.addEventListener('dragend', function () {
				this.classList.remove('pp-dragging');
				document.querySelectorAll('.pp-drag-over, .pp-drag-above, .pp-drag-below').forEach(function (el) {
					el.classList.remove('pp-drag-over', 'pp-drag-above', 'pp-drag-below');
				});
				dragRow = null;
			});

			row.addEventListener('dragover', function (e) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				if (this === dragRow) return;
				// Detect upper/lower half of target row
				var rect = this.getBoundingClientRect();
				var midY = rect.top + rect.height / 2;
				this.classList.remove('pp-drag-above', 'pp-drag-below', 'pp-drag-over');
				if (e.clientY < midY) {
					this.classList.add('pp-drag-above');
				} else {
					this.classList.add('pp-drag-below');
				}
			});

			row.addEventListener('dragleave', function () {
				this.classList.remove('pp-drag-over', 'pp-drag-above', 'pp-drag-below');
			});

			row.addEventListener('drop', function (e) {
				e.preventDefault();
				// Calculate drop position directly from mouse Y (don't rely on CSS class which can flicker)
				var rect = this.getBoundingClientRect();
				var dropAbove = e.clientY < rect.top + rect.height / 2;
				this.classList.remove('pp-drag-over', 'pp-drag-above', 'pp-drag-below');
				if (dragRow && this !== dragRow) {
					var dragPlan = dragRow.getAttribute('data-plan') || '';
					var dropPlan = this.getAttribute('data-plan') || '';

					var tbody = this.parentNode;
					// Insert based on which half was targeted
					if (dropAbove) {
						tbody.insertBefore(dragRow, this);
					} else {
						tbody.insertBefore(dragRow, this.nextSibling);
					}

					// If cross-plan drag, move the row to the other plan
					if (dragPlan && dropPlan && dragPlan !== dropPlan) {
						var dragId = dragRow.getAttribute('data-id');
						dragRow.setAttribute('data-plan', dropPlan);
						// Update local state
						for (var i = 0; i < ppRows.length; i++) {
							if (ppRows[i].id == dragId) { ppRows[i]._planId = dropPlan; ppRows[i].plan_id = dropPlan; break; }
						}
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_pp_move_row', row_id: dragId, target_plan_id: dropPlan }
						});
						toastr.success('Verschoben');
					}

					// Reorder within the plan where the row now lives
					var reorderPlanId = dragRow.getAttribute('data-plan') || ppCurrentPlanId;
					var isMultiPlan = !!dragRow.getAttribute('data-plan');

					var newOrder = [];
					var posCounter = 0;
					Array.from(tbody.children).forEach(function (tr) {
						var id = tr.getAttribute('data-id');
						if (!id) return;
						// In single-plan mode: all rows belong to current plan
						// In multi-plan mode: only rows matching the target plan
						if (isMultiPlan) {
							var trPlan = tr.getAttribute('data-plan') || '';
							if (trPlan != reorderPlanId) return;
						}
						newOrder.push({ id: id, position: posCounter });
						for (var i = 0; i < ppRows.length; i++) {
							if (ppRows[i].id == id) { ppRows[i].position = posCounter; break; }
						}
						posCounter++;
					});

					// Rebuild ppRows array to match current DOM order
					if (isMultiPlan) {
						// Multi-plan: rebuild entire ppRows from DOM order
						var newPpRows = [];
						Array.from(tbody.children).forEach(function (tr) {
							var id = tr.getAttribute('data-id');
							if (!id) return;
							for (var i = 0; i < ppRows.length; i++) {
								if (ppRows[i].id == id) { newPpRows.push(ppRows[i]); break; }
							}
						});
						ppRows = newPpRows;
					} else {
						// Single-plan: sort by position
						ppRows.sort(function (a, b) { return (a.position || 0) - (b.position || 0); });
					}

					ppCalculateSubtotals();
					ppMarkSectionItems();

					if (newOrder.length) {
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_pp_reorder_rows', plan_id: reorderPlanId, positions: newOrder }
						});
					}
				}
			});
		});
	}

	// Asana: shared search+preview dialog (used by link and add-from-asana)
	function ppAsanaSearchDialog(title, clientId, onConfirm) {
		Swal.fire({
			title: title,
			width: 520,
			html: '<div style="text-align:left;">' +
				'<input id="swal-a-q" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;" placeholder="Aufgabe suchen..." autofocus>' +
				'<div id="swal-a-results" style="max-height:220px;overflow-y:auto;margin-top:8px;border:1px solid #eee;border-radius:5px;display:none;"></div>' +
				'<div id="swal-a-preview" style="display:none;margin-top:10px;padding:12px;background:#f8f9fa;border-radius:5px;border:1px solid #e0e0e0;">' +
					'<label style="font-size:10px;text-transform:uppercase;color:#999;letter-spacing:0.5px;display:block;margin-bottom:4px;">Aufgabe</label>' +
					'<input id="swal-a-name" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;margin-bottom:6px;" placeholder="Titel">' +
					'<label style="font-size:10px;text-transform:uppercase;color:#999;letter-spacing:0.5px;display:block;margin-bottom:4px;">Beschreibung aus Asana</label>' +
					'<textarea id="swal-a-notes" rows="3" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:12px;box-sizing:border-box;margin-bottom:6px;resize:vertical;" placeholder="Lade..."></textarea>' +
					'<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">' +
						'<input id="swal-a-url" style="flex:1;padding:5px 8px;border:1px solid #eee;border-radius:4px;font-size:10px;color:#888;box-sizing:border-box;" readonly>' +
						'<span id="swal-a-gid" style="font-size:10px;color:#bbb;"></span>' +
					'</div>' +
					'<button type="button" id="swal-a-confirm" style="padding:8px 18px;background:var(--accent,#333);color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer;width:100%;">Übernehmen</button>' +
				'</div>' +
				'<div style="margin-top:10px;border-top:1px solid #eee;padding-top:8px;">' +
					'<label style="font-size:10px;color:#bbb;display:block;margin-bottom:3px;">Oder URL einfügen:</label>' +
					'<div style="display:flex;gap:6px;"><input id="swal-a-manual" style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;box-sizing:border-box;" placeholder="https://app.asana.com/...">' +
					'<button type="button" id="swal-a-manual-ok" style="padding:6px 12px;border:1px solid #ddd;border-radius:4px;font-size:12px;cursor:pointer;background:#f8f8f8;">OK</button></div>' +
				'</div></div>',
			showConfirmButton: false,
			showCancelButton: true,
			cancelButtonText: 'Schließen',
			didOpen: function (popup) {
				var $p = $(popup);
				var timer = null;

				$p.find('#swal-a-q').on('input', function () {
					var q = $(this).val().trim();
					$p.find('#swal-a-preview').hide();
					if (q.length < 2) { $p.find('#swal-a-results').hide(); return; }
					if (timer) clearTimeout(timer);
					timer = setTimeout(function () {
						$p.find('#swal-a-results').html('<div style="padding:10px;color:#999;font-size:12px;"><i class="bx bx-loader-alt bx-spin"></i></div>').show();
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_search_asana_tasks', search_text: q, client_id: clientId },
							success: function (res) {
								if (!res.success || !res.data || !res.data.length) {
									$p.find('#swal-a-results').html('<div style="padding:10px;color:#999;font-size:12px;">Keine Ergebnisse</div>');
									return;
								}
								var h = '';
								res.data.forEach(function (t) {
									h += '<div class="swal-a-pick" data-gid="' + escAttr(t.gid || '') + '" data-url="' + escAttr(t.permalink_url || t.url || '') + '" data-name="' + escAttr(t.name || '') + '" style="padding:8px 10px;cursor:pointer;border-bottom:1px solid #f5f5f5;font-size:12px;">' + escHtml(t.name) + '</div>';
								});
								$p.find('#swal-a-results').html(h).show();
							}
						});
					}, 300);
				});

				// Click result → load details, show preview
				$p.find('#swal-a-results').on('click', '.swal-a-pick', function () {
					var gid = $(this).attr('data-gid');
					var url = $(this).attr('data-url');
					var name = $(this).attr('data-name');
					$p.find('#swal-a-results').hide();
					$p.find('#swal-a-name').val(name);
					$p.find('#swal-a-url').val(url);
					$p.find('#swal-a-gid').text('GID: ' + gid).attr('data-gid', gid);
					$p.find('#swal-a-notes').val('').attr('placeholder', 'Lade Beschreibung...');
					$p.find('#swal-a-preview').show();

					// Fetch task details for notes/description
					if (gid) {
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_get_asana_task_detail', task_gid: gid },
							success: function (res) {
								if (res.success && res.data.notes) {
									$p.find('#swal-a-notes').val(res.data.notes).attr('placeholder', 'Beschreibung aus Asana');
								} else {
									$p.find('#swal-a-notes').attr('placeholder', 'Keine Beschreibung in Asana');
								}
							}
						});
					}
					$p.find('#swal-a-name').focus().select();
				});

				// Confirm from preview
				$p.find('#swal-a-confirm').on('click', function () {
					var gid = $p.find('#swal-a-gid').attr('data-gid') || '';
					var url = $p.find('#swal-a-url').val() || '';
					var name = $p.find('#swal-a-name').val() || '';
					var notes = $p.find('#swal-a-notes').val() || '';
					Swal.close();
					onConfirm(gid, url, name, notes);
				});

				// Manual URL
				$p.find('#swal-a-manual-ok').on('click', function () {
					var url = $p.find('#swal-a-manual').val().trim();
					if (!url) return;
					var gid = '';
					var m = url.match(/\/(\d{10,})/);
					if (m) gid = m[1];
					$p.find('#swal-a-name').val(url);
					$p.find('#swal-a-url').val(url);
					$p.find('#swal-a-notes').val('').attr('placeholder', 'Keine Beschreibung');
					$p.find('#swal-a-gid').text(gid ? 'GID: ' + gid : '').attr('data-gid', gid);
					$p.find('#swal-a-preview').show();

					// If GID found, try to load details
					if (gid) {
						$p.find('#swal-a-notes').attr('placeholder', 'Lade...');
						$.ajax({
							type: 'POST', url: ajaxuser.url,
							data: { security: ajaxuser.nonce, action: 'uf_get_asana_task_detail', task_gid: gid },
							success: function (res) {
								if (res.success) {
									if (res.data.name) $p.find('#swal-a-name').val(res.data.name);
									if (res.data.notes) $p.find('#swal-a-notes').val(res.data.notes);
									else $p.find('#swal-a-notes').attr('placeholder', 'Keine Beschreibung');
								}
							}
						});
					}
					$p.find('#swal-a-name').focus().select();
				});
			}
		});
	}

	// Link Asana to existing row
	var ppAsanaTargetRowId = null;

	$(document).on('click', '.pp-asana-btn', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		ppAsanaTargetRowId = $tr.data('id');
		// Find plan for this row (works in single and multi-plan mode)
		var plan = ppCurrentPlan;
		if (!plan) {
			var rowPlanId = $tr.data('plan');
			if (rowPlanId) {
				for (var i = 0; i < ppPlans.length; i++) {
					if (ppPlans[i].id == rowPlanId) { plan = ppPlans[i]; break; }
				}
			}
		}
		var clientId = plan ? plan.client_id : 0;

		ppAsanaSearchDialog('Asana verknüpfen', clientId, function (gid, url, name, notes) {
			// If row has no description, fill it from Asana notes (or title as fallback)
			var row = null;
			for (var i = 0; i < ppRows.length; i++) {
				if (ppRows[i].id == ppAsanaTargetRowId) { row = ppRows[i]; break; }
			}
			if (row && (!row.description || !row.description.trim())) {
				row.description = notes || name;
			}
			ppApplyAsana(ppAsanaTargetRowId, gid, url, name);
		});
	});

	function ppApplyAsana(rowId, gid, url, name) {
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (!row) return;

		row.asana_gid = gid;
		row.asana_url = url;
		row.asana_task_name = name;

		$.ajax({
			type: 'POST',
			url: ajaxuser.url,
			data: {
				security: ajaxuser.nonce,
				action: 'uf_pp_save_row',
				row_id: row.id,
				plan_id: row._planId || row.plan_id || ppCurrentPlanId,
				type: row.type,
				description: row.description || '',
				date_from: row.date_from || '',
				date_to: row.date_to || '',
				timeframe: row.timeframe || '',
				ist_hours: row.ist_hours || 0,
				planned_hours: row.planned_hours || 0,
				responsible: row.responsible || '',
				deadline: row.deadline || '',
				is_done: row.is_done || 0,
				is_placeholder: row.is_placeholder || 0,
				is_focus: row.is_focus || 0,
				actual_hours: row.actual_hours || '',
				notes: row.notes || '',
				asana_gid: gid,
				asana_url: url,
				asana_task_name: name,
			},
			success: function (res) {
				if (res.success) {
					ppRenderTable();
					toastr.success('Asana verknüpft');
				} else {
					toastr.error('Fehler: ' + (res.data || 'Unbekannt'));
				}
			},
			error: function () {
				toastr.error('Speichern fehlgeschlagen');
			}
		});
	}

	// Asana: CREATE task in Asana and link to row
	$(document).on('click', '.pp-asana-create', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (!row) return;

		// In multi-plan mode, find the plan from the row's plan_id
		var plan = ppCurrentPlan;
		if (!plan && (row._planId || row.plan_id)) {
			var pid = row._planId || row.plan_id;
			for (var i = 0; i < ppPlans.length; i++) {
				if (ppPlans[i].id == pid) { plan = ppPlans[i]; break; }
			}
		}

		var clientShort = plan ? (plan.client_short || plan.client_title || '') : '';
		var planAsanaGid = plan ? (plan.asana_project_gid || '') : '';
		var planSectionGid = plan ? (plan.asana_section_gid || '') : '';
		var today = new Date().toISOString().split('T')[0];
		var taskTitle = (clientShort ? clientShort + ' ' : '') + (row.description || '').split('\n')[0];

		// Load projects only (members load dynamically based on project)
		$.ajax({ type: 'POST', url: ajaxuser.url, data: { security: ajaxuser.nonce, action: 'uf_get_asana_projects' },
			success: function (r) {
				var projects = r.success ? r.data : [];
				ppOpenCreateAsanaDialog(rowId, row, taskTitle, today, planAsanaGid, planSectionGid, projects);
			}
		});
	});

	function ppOpenCreateAsanaDialog(rowId, row, taskTitle, today, planAsanaGid, planSectionGid, projects) {
		var projOptions = '<option value="">Bitte wählen</option>';
		projects.forEach(function (p) {
			var sel = planAsanaGid && planAsanaGid === p.gid ? ' selected' : '';
			projOptions += '<option value="' + escAttr(p.gid) + '"' + sel + '>' + escHtml(p.name) + '</option>';
		});

		var s = 'style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;"';
		var lbl = 'style="display:block;margin-bottom:3px;font-weight:600;color:#555;font-size:12px;"';

		Swal.fire({
			title: 'Asana Task anlegen',
			width: 520,
			html: '<div style="text-align:left;">' +
				'<label ' + lbl + '>Titel *</label>' +
				'<input id="swal-ct-name" ' + s + ' value="' + escAttr(taskTitle) + '" style="margin-bottom:8px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;box-sizing:border-box;">' +

				'<label ' + lbl + '>Beschreibung</label>' +
				'<textarea id="swal-ct-notes" rows="3" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:12px;box-sizing:border-box;margin-bottom:8px;resize:vertical;">' + escHtml(row.description || '') + '</textarea>' +

				'<div style="display:flex;gap:8px;margin-bottom:8px;">' +
				'<div style="flex:1;"><label ' + lbl + '>Projekt *</label>' +
				'<select id="swal-ct-project" ' + s + '>' + projOptions + '</select></div>' +
				'<div style="flex:1;"><label ' + lbl + '>Spalte</label>' +
				'<select id="swal-ct-section" ' + s + ' disabled><option value="">–</option></select></div>' +
				'</div>' +

				'<div style="display:flex;gap:8px;margin-bottom:8px;">' +
				'<div style="flex:1;"><label ' + lbl + '>Zuweisen an</label>' +
				'<select id="swal-ct-assignee" ' + s + '><option value="">Lade...</option></select></div>' +
				'<div style="flex:1;"><label ' + lbl + '>Fällig am</label>' +
				'<input id="swal-ct-due" type="date" ' + s + ' value="' + today + '"></div>' +
				'</div></div>',
			showCancelButton: true,
			confirmButtonText: 'Anlegen & Verknüpfen',
			cancelButtonText: 'Abbrechen',
			didOpen: function (popup) {
				var $p = $(popup);

				function loadProjectData(projGid) {
					if (!projGid) {
						$p.find('#swal-ct-section').html('<option value="">–</option>').prop('disabled', true);
						$p.find('#swal-ct-assignee').html('<option value="">Erst Projekt wählen</option>');
						return;
					}

					// Load sections, pre-select plan's default
					$p.find('#swal-ct-section').html('<option value="">Lade...</option>').prop('disabled', true);
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_get_asana_sections', project_gid: projGid },
						success: function (res) {
							var h = '<option value="">Keine Spalte</option>';
							if (res.success && res.data && res.data.length) {
								res.data.forEach(function (sc) {
									var sel = planSectionGid === sc.gid ? ' selected' : '';
									h += '<option value="' + escAttr(sc.gid) + '"' + sel + '>' + escHtml(sc.name) + '</option>';
								});
							}
							$p.find('#swal-ct-section').html(h).prop('disabled', false);
						},
						error: function () {
							$p.find('#swal-ct-section').html('<option value="">–</option>').prop('disabled', false);
						}
					});

					// Load project members and auto-select based on responsible
					$p.find('#swal-ct-assignee').html('<option value="">Lade...</option>');
					$.ajax({
						type: 'POST', url: ajaxuser.url,
						data: { security: ajaxuser.nonce, action: 'uf_get_asana_project_members', project_gid: projGid },
						success: function (res) {
							var h = '<option value="">Nicht zuweisen</option>';
							var autoGid = '';
							if (res.success && res.data) {
								// Try to match row.responsible to a member
								var resp = (row.responsible || '').split(',')[0].trim().toLowerCase();
								// If resp is a kuerzel, resolve to full name
								if (resp && typeof ppUsersData !== 'undefined') {
									for (var k = 0; k < ppUsersData.length; k++) {
										if (ppUsersData[k].kuerzel && ppUsersData[k].kuerzel.toLowerCase() === resp) {
											resp = ppUsersData[k].name.toLowerCase();
											break;
										}
									}
								}
								res.data.forEach(function (u) {
									var uName = (u.name || '').toLowerCase();
									var parts = resp.split(/\s+/);
									// Match: full name, first name, or last name
									var match = resp && (uName === resp || uName.indexOf(resp) > -1 || parts.some(function (p) { return p.length > 2 && uName.indexOf(p) > -1; }));
									if (match && !autoGid) autoGid = u.gid;
									h += '<option value="' + escAttr(u.gid) + '"' + (match && !autoGid ? '' : '') + '>' + escHtml(u.name) + '</option>';
								});
							}
							$p.find('#swal-ct-assignee').html(h);
							if (autoGid) $p.find('#swal-ct-assignee').val(autoGid);
						}
					});
				}

				// Load data for pre-selected project
				var preSelected = $p.find('#swal-ct-project').val();
				if (preSelected) loadProjectData(preSelected);
				else $p.find('#swal-ct-assignee').html('<option value="">Erst Projekt wählen</option>');

				$p.find('#swal-ct-project').on('change', function () {
					loadProjectData($(this).val());
				});
			},
			preConfirm: function () {
				var projGid = document.getElementById('swal-ct-project').value;
				var name = document.getElementById('swal-ct-name').value.trim();
				if (!projGid || !name) {
					Swal.showValidationMessage('Projekt und Titel sind Pflichtfelder');
					return false;
				}
				return {
					project_gid: projGid,
					section_gid: document.getElementById('swal-ct-section').value,
					name: name,
					notes: document.getElementById('swal-ct-notes').value,
					assignee_gid: document.getElementById('swal-ct-assignee').value,
					due_on: document.getElementById('swal-ct-due').value,
				};
			}
		}).then(function (result) {
			if (!result.isConfirmed) return;
			var d = result.value;
			toastr.info('Erstelle Asana Task...');

			$.ajax({
				type: 'POST', url: ajaxuser.url,
				data: {
					security: ajaxuser.nonce,
					action: 'uf_create_asana_task',
					project_gid: d.project_gid,
					section_gid: d.section_gid,
					name: d.name,
					notes: d.notes,
					assignee_gid: d.assignee_gid,
					due_on: d.due_on,
				},
				success: function (res) {
					if (res.success) {
						ppApplyAsana(rowId, res.data.gid, res.data.permalink_url, d.name);
						toastr.success('Asana Task erstellt & verknüpft');
					} else {
						toastr.error(res.data || 'Fehler beim Erstellen');
					}
				},
				error: function () { toastr.error('Verbindungsfehler'); }
			});
		});
	}

	// Asana: remove link (x button)
	$(document).on('click', '.pp-asana-remove', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) {
				ppRows[i].asana_gid = '';
				ppRows[i].asana_url = '';
				ppRows[i].asana_task_name = '';
				break;
			}
		}
		ppSaveRow(rowId);
		ppRenderTable();
	});

	// Asana: change link (pencil button) – same as .pp-asana-btn, handled above

	// No ticket toggle
	$(document).on('click', '.pp-no-ticket-btn', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i].no_ticket = 1; break; }
		}
		ppSaveRow(rowId);
		ppRenderTable();
	});

	$(document).on('click', '.pp-no-ticket-remove', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i].no_ticket = 0; break; }
		}
		ppSaveRow(rowId);
		ppRenderTable();
	});

	// Lead responsible: single person input with autocomplete
	$(document).on('focus', '.pp-lead-input', function () {
		var $cell = $(this).closest('.pp-lead-cell');
		var current = $cell.data('current') || '';
		if (current) return; // already has a lead
		var $suggest = $cell.find('.pp-lead-suggest');
		var html = '';
		if (typeof ppUsersData !== 'undefined') {
			ppUsersData.forEach(function (u) {
				html += '<div class="pp-lead-option" data-name="' + escAttr(u.name) + '">' + escHtml(u.kuerzel || u.name) + ' <small style="color:#999;">' + escHtml(u.name) + '</small></div>';
			});
		}
		$suggest.html(html).show();
	});

	$(document).on('input', '.pp-lead-input', function () {
		var q = $(this).val().toLowerCase().trim();
		$(this).closest('.pp-lead-cell').find('.pp-lead-option').each(function () {
			var name = $(this).data('name').toLowerCase();
			var text = $(this).text().toLowerCase();
			$(this).toggle(!q || name.indexOf(q) > -1 || text.indexOf(q) > -1);
		});
	});

	$(document).on('click', '.pp-lead-option', function (e) {
		e.stopPropagation();
		var name = $(this).data('name');
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i].lead_responsible = name; break; }
		}
		ppSaveRow(rowId);
		ppRenderTable();
	});

	$(document).on('keydown', '.pp-lead-input', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			var val = $(this).val().trim();
			if (!val) return;
			val = ppResolveResponsible(val);
			if (!ppIsKnownPerson(val)) {
				toastr.warning('Person "' + $(this).val().trim() + '" nicht in den Team-Tags hinterlegt');
				return;
			}
			var $tr = $(this).closest('tr');
			var rowId = $tr.data('id');
			for (var i = 0; i < ppRows.length; i++) {
				if (ppRows[i].id == rowId) { ppRows[i].lead_responsible = val; break; }
			}
			ppSaveRow(rowId);
			ppRenderTable();
		}
		if (e.key === 'Escape') { $(this).val('').blur(); $(this).closest('.pp-lead-cell').find('.pp-lead-suggest').hide(); }
	});

	$(document).on('blur', '.pp-lead-input', function () {
		var $suggest = $(this).closest('.pp-lead-cell').find('.pp-lead-suggest');
		setTimeout(function () { $suggest.hide(); }, 200);
	});

	$(document).on('click', '.pp-lead-x', function (e) {
		e.stopPropagation();
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i].lead_responsible = ''; break; }
		}
		ppSaveRow(rowId);
		ppRenderTable();
	});

	// Move person from responsible to lead: right-click on resp tag
	$(document).on('contextmenu', '.pp-resp-tag', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var name = $(this).find('.pp-resp-x').data('name');
		var $tr = $(this).closest('tr');
		var rowId = $tr.data('id');
		var row = null;
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { row = ppRows[i]; break; }
		}
		if (!row || !name) return;

		// Show mini context menu
		$('.pp-resp-context').remove();
		var $menu = $('<div class="pp-resp-context"><div class="pp-resp-ctx-item" data-action="to-lead"><i class="bx bx-crown"></i> Als Hauptverantw. setzen</div></div>');
		$menu.css({ position: 'fixed', left: e.clientX + 'px', top: e.clientY + 'px', zIndex: 99999 });
		$('body').append($menu);

		$menu.on('click', '.pp-resp-ctx-item', function () {
			row.lead_responsible = name;
			// Remove from Umsetzung
			var names = (row.responsible || '').split(',').map(function (n) { return n.trim(); }).filter(function (n) { return n && n !== name; });
			row.responsible = names.join(', ');
			ppSaveRow(rowId);
			ppRenderTable();
			$menu.remove();
		});

		$(document).one('click', function () { $menu.remove(); });
	});

	// Responsible: lookup kuerzel from ppUsersData, fallback to first 3 chars
	function ppGetKuerzel(name) {
		name = name.trim();
		if (!name) return '?';
		if (typeof ppUsersData !== 'undefined') {
			for (var i = 0; i < ppUsersData.length; i++) {
				if (ppUsersData[i].name === name && ppUsersData[i].kuerzel) {
					return ppUsersData[i].kuerzel;
				}
			}
		}
		// Fallback: first 3 chars uppercase
		return name.substring(0, 3).toUpperCase();
	}

	function ppRenderResponsibleTags(responsible) {
		var html = '';
		if (responsible) {
			responsible.split(',').forEach(function (n) {
				n = n.trim();
				if (!n) return;
				html += '<span class="pp-resp-tag" title="' + escAttr(n) + '">' + escHtml(ppGetKuerzel(n)) + '<i class="bx bx-x pp-resp-x" data-name="' + escAttr(n) + '"></i></span>';
			});
		}
		html += '<input type="text" class="pp-resp-input" placeholder="+" autocomplete="off">';
		html += '<div class="pp-resp-suggest" style="display:none;"></div>';
		return html;
	}

	// Remove tag
	$(document).on('click', '.pp-resp-x', function (e) {
		e.stopPropagation();
		var nameToRemove = $(this).data('name');
		var $cell = $(this).closest('.pp-resp-cell');
		ppUpdateResponsible($cell, function (names) {
			return names.filter(function (n) { return n !== nameToRemove; });
		});
	});

	// Typing in responsible input → show suggestions
	$(document).on('input', '.pp-resp-input', function () {
		var q = $(this).val().toLowerCase().trim();
		var $suggest = $(this).siblings('.pp-resp-suggest');
		if (q.length < 1) { $suggest.hide(); return; }

		var html = '';
		if (typeof ppUsersData !== 'undefined') {
			ppUsersData.forEach(function (u) {
				var match = u.name.toLowerCase().indexOf(q) > -1 || (u.kuerzel && u.kuerzel.toLowerCase().indexOf(q) > -1);
				if (match) {
					html += '<div class="pp-resp-sug-item" data-name="' + escAttr(u.name) + '">' + escHtml(u.kuerzel || u.name) + ' <small style="color:#aaa;">' + escHtml(u.name) + '</small></div>';
				}
			});
		}
		if (html) { $suggest.html(html).show(); }
		else { $suggest.hide(); }
	});

	// Click suggestion
	$(document).on('click', '.pp-resp-sug-item', function (e) {
		e.stopPropagation();
		var name = $(this).data('name');
		var $cell = $(this).closest('.pp-resp-cell');
		$cell.find('.pp-resp-input').val('');
		$cell.find('.pp-resp-suggest').hide();
		ppUpdateResponsible($cell, function (names) {
			if (names.indexOf(name) === -1) names.push(name);
			return names;
		});
	});

	// Commit current resp input text as tag
	var ppRespCommitting = false;
	// Check if a name is a known team member (from ppUsersData)
	function ppIsKnownPerson(name) {
		if (!name || typeof ppUsersData === 'undefined') return false;
		var lname = name.toLowerCase().trim();
		for (var i = 0; i < ppUsersData.length; i++) {
			if (ppUsersData[i].name.toLowerCase() === lname) return true;
			if (ppUsersData[i].kuerzel && ppUsersData[i].kuerzel.toLowerCase() === lname) return true;
		}
		return false;
	}

	function ppCommitRespInput($input) {
		if (ppRespCommitting) return false;
		var val = $input.val().trim().replace(/,/g, '');
		if (!val) return false;
		if (!$input.closest('.pp-resp-cell').length) return false;
		var matched = ppResolveResponsible(val);
		// Only allow known team members
		if (!ppIsKnownPerson(matched)) {
			toastr.warning('Person "' + val + '" nicht in den Team-Tags hinterlegt');
			return false;
		}
		ppRespCommitting = true;
		var $cell = $input.closest('.pp-resp-cell');
		$input.val('');
		$cell.find('.pp-resp-suggest').hide();
		ppUpdateResponsible($cell, function (names) {
			if (names.indexOf(matched) === -1) names.push(matched);
			return names;
		});
		ppRespCommitting = false;
		return true;
	}

	// Keydown in resp input
	$(document).on('keydown', '.pp-resp-input', function (e) {
		// Tab → commit text first, then move
		if (e.key === 'Tab') {
			e.preventDefault();
			ppCommitRespInput($(this));
			setTimeout(function () {
				var $editables = $('#pp-table-body .pp-cell, #pp-table-body .pp-lead-input, #pp-table-body .pp-resp-input');
				var $focused = $(':focus');
				var idx = $editables.index($focused[0]);
				if (idx === -1) idx = $editables.index($(e.target));
				var next = e.shiftKey ? idx - 1 : idx + 1;
				if ($editables[next]) { $editables[next].focus(); if ($editables[next].click) $editables[next].click(); }
			}, 20);
			return;
		}
		// Enter or Comma → add tag
		if (e.key === 'Enter' || e.key === ',') {
			e.preventDefault();
			ppCommitRespInput($(this));
			return;
		}
		// Backspace on empty → remove last tag
		if (e.key === 'Backspace' && !$(this).val()) {
			var $cell = $(this).closest('.pp-resp-cell');
			ppUpdateResponsible($cell, function (names) {
				names.pop();
				return names;
			});
		}
	});

	// Blur → commit any remaining text
	$(document).on('blur', '.pp-resp-input', function () {
		ppCommitRespInput($(this));
	});

	// Close suggestions on outside click
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pp-resp-cell').length) {
			$('.pp-resp-suggest').hide();
		}
	});

	function ppUpdateResponsible($cell, modifier) {
		var $tr = $cell.closest('tr');
		var rowId = $tr.data('id');
		var current = $cell.data('current') || '';
		var names = current ? current.split(',').map(function (n) { return n.trim(); }).filter(Boolean) : [];
		names = modifier(names);
		var newVal = names.join(', ');
		$cell.data('current', newVal);
		for (var i = 0; i < ppRows.length; i++) {
			if (ppRows[i].id == rowId) { ppRows[i].responsible = newVal; break; }
		}
		ppSaveRow(rowId);
		$cell.html(ppRenderResponsibleTags(newVal));
		// Apply current font size to new tags
		var fs = parseInt($('#pp-font-label').text()) || 13;
		$cell.find('.pp-resp-tag, .pp-resp-input').css('font-size', fs + 'px');
		// Re-focus the input (slight delay for DOM to settle)
		setTimeout(function () {
			$cell.find('.pp-resp-input').focus();
		}, 10);
	}

	// Helper
	function ppFormatDate(d) {
		if (!d) return '';
		var parts = d.split('-');
		if (parts.length === 3) return parts[2] + '.' + parts[1] + '.' + parts[0];
		return d;
	}

	function escAttr(s) {
		return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// Escape + convert \n to <br> for contenteditable
	function escHtmlBr(s) {
		if (!s) return '';
		// Manual escape that preserves \n, then convert to <br>
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/\n/g, '<br>');
	}

	// Format number: . → , for display
	function ppFmtNum(v) {
		var n = parseFloat(v);
		if (!n && n !== 0) return '';
		return n.toFixed(2).replace('.', ',');
	}

});
