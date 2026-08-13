jQuery(document).ready(function ($) {
	// times
	// click on #monthpicker toggle #coolpick
	$('#monthpicker').click(function () {
		$('#coolpick').slideToggle(300);
	});

	$('#yearstopick button').click(function () {
		$('#yearstopick button').removeClass('active');
		$(this).addClass('active');

		insertdateininputs();
	});

	$('#monthstopick button').click(function () {
		$('#monthstopick button').removeClass('active');
		$(this).addClass('active');

		insertdateininputs();
	});


	// save cookie functino
	function setCookieTallyr(cname, cvalue, exdays) {
		var d = new Date();
		d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
		var expires = "expires=" + d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
	}

	// get cookie function
	function getCookieTallyr(cname) {
		var name = cname + "=";
		var decodedCookie = decodeURIComponent(document.cookie);
		var ca = decodedCookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) == ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(name) == 0) {
				return c.substring(name.length, c.length);
			}
		}
		return "";
	}


	function insertdateininputs() {
		var input_date_start = $('#datepicker_start');
		var input_date_end = $('#datepicker_end');

		var month = $('#monthstopick button.active').data('month');
		var year = parseInt($('#yearstopick button.active').text());

		// insert start and end date in date inputs // reuqired format is yyyy-MM-dd
		if (!month) {
			return;
		}

		if (!year) {
			year = new Date().getFullYear();
			// add class active
			$('#yearstopick button').each(function () {
				if ($(this).text() == year) {
					$(this).addClass('active');
				}
			});
		}

		// start date is the first day of the selected month
		//console.log(year + '-' + month + '-01');
		input_date_start.val(year + '-' + month + '-01');

		// end date is the last day of the selected month
		// get the last day in month by the selected month and year
		var lastDay = new Date(year, month, 0).getDate();
		//console.log(year + '-' + month + '-' + lastDay);
		input_date_end.val(year + '-' + month + '-' + lastDay);

		// close the coolpick
		$('#coolpick').slideUp(300);


		// save date in cookie

		getProjectsAndTimes();
	}

	// on input manual change, deacitvate the buttons
	$('#datepicker_start, #datepicker_end').change(function () {
		$('#monthstopick button').removeClass('active');
		$('#yearstopick button').removeClass('active');

		getProjectsAndTimes();
	});

	// get cookie value password
	var client_id = parseInt($('#timespage').data('client_id'));
	var timespassword_hashed = getCookieTallyr('timespassword_' + client_id);
	if (timespassword_hashed) {
		getProjectsAndTimes(timespassword_hashed)
	}

	// ajax function
	function getProjectsAndTimes(hashed) {

		// get datepicker values
		var input_date_start = $('#datepicker_start');
		var input_date_end = $('#datepicker_end');

		setCookieTallyr('datepicker_start', input_date_start.val(), 365);
		setCookieTallyr('datepicker_end', input_date_end.val(), 365);

		var nonce = $('#timespage').data('nonce');
		var client_id = parseInt($('#timespage').data('client_id'));
		var times_id = $('#timespage').data('times_id');
		var password = $('#timespage #passform input[name="password"]').val();

		if (!nonce || !client_id || !times_id) {
			return;
		}

		$('#projects_and_times').html('<div class="load"><i class="bx bx-spin bx-loader"></i></div>');

		$.ajax({
			url: ajaxcall.url,
			type: 'post',
			data: {
				security: nonce,
				action: 'get_projects_and_times',
				start_date: $('#datepicker_start').val(),
				end_date: $('#datepicker_end').val(),
				client_id: client_id,
				times_id: times_id,
				password: password,
				hashed: hashed
			},
			success: function (response) {

				// wait 2 seconds
				setTimeout(function () {
					if (response.success) {
						console.log(response);
						$('#passform').hide();
						$('#projects_and_times').html(response.data[0]);
						$('#times_datepicker').fadeIn();
						$('.wrong').hide();

						// save password in cookie
						if (response.data[1]) {
							setCookieTallyr('timespassword_' + client_id, response.data[1], 365);
						}

					} else {
						$('#passform').show();
						$('#projects_and_times').html('');
						$('#times_datepicker').hide();
						$('.wrong').text(response.data);
						$('.wrong').show();
					}
					filtertimesby_erledigtfilter();
				}, 150);
			}
		});
	}


	if ($('#loadadmintimessuhau28129').length > 0) {
		getProjectsAndTimes();
	}


	$(document).on('click', 'button.done', function (e) {
		e.preventDefault();

		var state = 1;
		var btn = $(this);

		if (btn.hasClass('undone')) {
			btn.removeClass('undone');
		} else {
			btn.addClass('undone');
			state = 2;
		}

		btn.prop('disabled', true);

		btn.html('<i class="bx bx-loader bx-spin"></i>');

		var time_id = parseInt(btn.data('timeid'));
		var password = $('#timespage #passform input[name="password"]').val();
		var nonce = $('#timespage').data('nonce');
		var client_id = parseInt(btn.parents('div.client').data('id'));
		var client_id_parent = parseInt($('#timespage').data('client_id'));


		$.ajax({
			url: ajaxcall.url,
			type: 'post',
			data: {
				security: nonce,
				action: 'done_time',
				client_id: client_id,
				client_id_parent: client_id_parent,
				time_id: time_id,
				password: password,
				state: state,
			},
			success: function (response) {
				if (response.success) {
					if (state == 1) {
						btn.removeClass('undone');
						btn.html('<i class="bx bx-circle"></i>');
					} else {
						btn.addClass('undone');
						btn.html('<i class="bx bx-check-circle"></i>');
					}
				} else {
					alert(response.data);
				}
				btn.prop('disabled', false);
				filtertimesby_erledigtfilter();
			}
		});


	});

	// select clientfilter
	$(document).on('change', '#clientfilter', function () {
		/* var client_id = parseInt($(this).val());
		if (client_id > 0) {
			// .client with data-id show
			$('.client').hide();
			$('.client[data-id="' + client_id + '"]').show();
			$('#totaltotal').hide();
		} else {
			$('.client').show();
			$('#totaltotal').show();
		} */
		filtertimesby_erledigtfilter();
	});

	// add to clipboard when click on .copy
	$(document).on('click', '.copy', function () {
		var text = $(this).text();

		// create a textarea
		var $temp = $("<textarea>");
		// add text to textarea
		$("body").append($temp);
		$temp.val(text).select();
		document.execCommand("copy");
		$temp.remove();

		// let div blink 2 times
		$(this).fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);
		// on success show t
	});


	// form submit #passform ajax request
	$('#passform').submit(function (e) {
		e.preventDefault();

		getProjectsAndTimes();
	});


	function filtertimesby_erledigtfilter() {

		var filter = $('#erledigtfilter').val();
		if (filter == 0) {
			$('.timerow').slideDown(150);
			$('.timerow').removeClass('hidden');
		} else if (filter == 1) {
			// if timerow has a button with class undone
			$('.timerow').each(function () {
				if ($(this).find('button.done').hasClass('undone')) {
					$(this).slideDown(150);
					$(this).removeClass('hidden');
				} else {
					$(this).slideUp(150);
					$(this).addClass('hidden');
				}
			});
		} else if (filter == 2) {
			// if timerow has a button with class undone
			$('.timerow').each(function () {
				if ($(this).find('button.done').hasClass('undone')) {
					$(this).slideUp(150);
					$(this).addClass('hidden');
				} else {
					$(this).slideDown(150);
					$(this).removeClass('hidden');
				}
			});
		}

		$('.client').each(function () {
			if ($(this).find('.timerow:not(.hidden)').length == 0) {
				$(this).slideUp(150);
			} else {
				$(this).slideDown(150);
			}
		})

		$('.project').each(function () {
			if ($(this).find('.timerow:not(.hidden)').length == 0) {
				$(this).slideUp(150);
			} else {
				$(this).slideDown(150);
			}
		});


		// calulcate totalprojects, totalclient and totaltotal
		var totalclient = 0;
		var totaltotal = 0;

		$('.project').each(function () {
			var totalproject = 0;
			var totalproject_betrag = 0;
			$(this).find('.timerow:not(.hidden)').each(function () {
				// replace comma with dot
				var time = parseFloat($(this).find('.timepertask').attr('data-time').replace(',', '.'));
				if (time) {
					totalproject += time;
				}
				var betrag = parseFloat($(this).find('.calcgesamtbetrag').attr('data-value').replace(',', '.'));
				if (betrag) {
					totalproject_betrag += betrag;
				}
			});

			$(this).find('.totalprojects span').text('Gesamt: ' + totalproject + ' Std.' + ' // ' + totalproject_betrag.toFixed(2).replace('.', ',') + ' €');
		
		});

		$('.client').each(function () {
			var totalclient = 0;
			var totalclient_betrag = 0;
			$(this).find('.timerow:not(.hidden)').each(function () {
				// replace comma with dot
				var time = parseFloat($(this).find('.timepertask').attr('data-time').replace(',', '.'));
				if (time) {
					totalclient += time;
					totaltotal += time;
				}
				var betrag = parseFloat($(this).find('.calcgesamtbetrag').attr('data-value').replace(',', '.'));
				if (betrag) {
					totalclient_betrag += betrag;
				}
			});

			$(this).find('.totalclients span').text('Gesamt: ' + totalclient + ' Std. // ' + totalclient_betrag.toFixed(2).replace('.', ',') + ' €');
		});

		$('#totaltotal span').text('Gesamt: ' + totaltotal + ' Std.');

		var client_id = parseInt($('#clientfilter').val());
		if (client_id > 0) {
			$('.client').hide();
			$('.client[data-id="' + client_id + '"]').show();
			$('#totaltotal').hide();
		} else {
			$('.client').show();
			$('#totaltotal').show();
		}


		var totaltotalbetrag = 0;
		// each calcgesamtbetrag of visible timerow
		$('.timerow:not(.hidden)').each(function () {
			var betrag = parseFloat($(this).find('.calcgesamtbetrag').attr('data-value').replace(',', '.'));
			if (betrag) {
				totaltotalbetrag += betrag;
			}
		});
		// show totaltotalbetrag in #totaltotalbetrag span
		console.log(totaltotalbetrag);
		$('#totaltotalbetrag span').text('Gesamtbetrag: ' + totaltotalbetrag.toFixed(2).replace('.', ',') + ' €');

	}

	$(document).on('change', '#erledigtfilter', function () {
		filtertimesby_erledigtfilter();
	});

	// comment box
	$(document).on('click', 'button.commentthistask', function (e) {
		e.preventDefault();
		var box = $(this).parents('.timerow').find('.commentswrapp');
		box.slideToggle(150);
	});

	// toggle comments
	$(document).on('click', 'button.togglecomments', function () {
		var box = $(this).parents('.timerow').find('.comments');
		if (box.is(':visible')) {
			box.slideUp(150);
		} else {
			box.slideDown(150);
		}
	});

	// save comment
	$(document).on('click', 'button.savecomment', function () {
		var btn = $(this);
		var textarea = btn.prev('textarea');
		var comment = textarea.val();
		var time_id = parseInt(textarea.data('timeid'));
		var password = $('#timespage #passform input[name="password"]').val();
		var nonce = $('#timespage').data('nonce');
		var client_id = parseInt(btn.parents('div.client').data('id'));
		var client_id_parent = parseInt($('#timespage').data('client_id'));

		if (!comment) {
			alert('Bitte gebe einen Kommentar ein.');
			return;
		}

		// if comment is shorter than 3 characters
		if (comment.length < 3) {
			alert('Bitte schreib in ganzen Sätzen, wer soll das verstehen?');
			return;
		}

		if (!time_id || !nonce) {
			return;
		}

		btn.prop('disabled', true);
		btn.html('<i class="bx bx-loader bx-spin"></i>');

		$.ajax({
			url: ajaxcall.url,
			type: 'post',
			data: {
				security: nonce,
				action: 'save_time_comment',
				time_id: time_id,
				password: password,
				comment: comment,
				client_id: client_id,
				client_id_parent: client_id_parent
			},
			success: function (response) {
				if (response.success) {
					btn.html('Gespeichert');
					setTimeout(function () {
						btn.html('Speichern');
						btn.prop('disabled', false);
					}, 1000);

					// close commentbox
					textarea.val('');

					// add response to comments
					$('.comments').append(response.data);

				} else {
					alert(response.data);
					btn.html('Speichern');
					btn.prop('disabled', false);
				}
			}
		});

	});


	// ========================================
	// EXCEL EXPORT FUNCTIONS
	// ========================================

	// Collect visible time entries data
	function collectTimeEntriesData() {
		var entries = [];

		$('.timerow:not(.hidden)').each(function () {
			var row = $(this);
			var clientDiv = row.closest('.client');
			var projectDiv = row.closest('.project');

			var entry = {
				client: clientDiv.find('h2 strong').text().trim() || clientDiv.find('h2').first().text().trim(),
				project: projectDiv.find('h3').text().trim(),
				date: row.find('td:first').text().trim(),
				description: row.find('.copy').first().text().trim(),
				hours: parseFloat(row.find('.timepertask').attr('data-time').replace(',', '.')) || 0,
				rate: parseFloat(row.find('.stundensatzval').attr('data-value')) || 0,
				amount: parseFloat(row.find('.calcgesamtbetrag').attr('data-value').replace(',', '.')) || 0,
				status: row.find('button.done').hasClass('undone') ? 'Erledigt' : 'Offen',
				billable: row.find('.notbillable').length > 0 ? 'Nein' : 'Ja'
			};

			entries.push(entry);
		});

		return entries;
	}

	// Export detailed time entries to Excel
	function exportDetailedExcel() {
		if (typeof XLSX === 'undefined') {
			alert('Excel-Export-Bibliothek nicht geladen.');
			return;
		}

		var entries = collectTimeEntriesData();

		if (entries.length === 0) {
			alert('Keine Zeiteinträge zum Exportieren vorhanden.');
			return;
		}

		// Create worksheet data
		var wsData = [
			['Kunde', 'Projekt', 'Datum', 'Beschreibung', 'Stunden', 'Stundensatz (€)', 'Betrag (€)', 'Status', 'Abrechenbar']
		];

		entries.forEach(function (entry) {
			wsData.push([
				entry.client,
				entry.project,
				entry.date,
				entry.description,
				entry.hours,
				entry.rate,
				entry.amount,
				entry.status,
				entry.billable
			]);
		});

		// Add totals row
		var totalHours = entries.reduce(function (sum, e) { return sum + e.hours; }, 0);
		var totalAmount = entries.reduce(function (sum, e) { return sum + e.amount; }, 0);
		wsData.push([]);
		wsData.push(['', '', '', 'GESAMT:', totalHours, '', totalAmount, '', '']);

		// Create workbook and worksheet
		var wb = XLSX.utils.book_new();
		var ws = XLSX.utils.aoa_to_sheet(wsData);

		// Set column widths
		ws['!cols'] = [
			{ wch: 20 }, // Kunde
			{ wch: 25 }, // Projekt
			{ wch: 12 }, // Datum
			{ wch: 50 }, // Beschreibung
			{ wch: 10 }, // Stunden
			{ wch: 15 }, // Stundensatz
			{ wch: 12 }, // Betrag
			{ wch: 10 }, // Status
			{ wch: 12 }  // Abrechenbar
		];

		XLSX.utils.book_append_sheet(wb, ws, 'Zeiteinträge');

		// Generate filename with date range
		var startDate = $('#datepicker_start').val();
		var endDate = $('#datepicker_end').val();
		var clientName = $('#choosaclient option:selected').text() || $('.clientsel').text().trim() || 'Zeiten';
		clientName = clientName.replace(/[^a-zA-Z0-9äöüÄÖÜß\-_]/g, '_');

		var filename = 'Zeiten_' + clientName + '_' + startDate + '_bis_' + endDate + '.xlsx';

		// Download file
		XLSX.writeFile(wb, filename);
	}

	// Export cumulated data to Excel (by day)
	function exportCumulatedExcel() {
		if (typeof XLSX === 'undefined') {
			alert('Excel-Export-Bibliothek nicht geladen.');
			return;
		}

		var entries = collectTimeEntriesData();

		if (entries.length === 0) {
			alert('Keine Zeiteinträge zum Exportieren vorhanden.');
			return;
		}

		// Get date range and client name
		var startDate = $('#datepicker_start').val();
		var endDate = $('#datepicker_end').val();
		var clientName = $('#choosaclient option:selected').text() || $('.clientsel').text().trim() || '';

		// Aggregate by date
		var byDate = {};

		entries.forEach(function (entry) {
			var date = entry.date;
			if (!byDate[date]) {
				byDate[date] = {
					date: date,
					hours: 0,
					tasks: 0
				};
			}
			byDate[date].hours += entry.hours;
			byDate[date].tasks += 1;
		});

		// Convert to array and sort by date
		var dateArray = Object.values(byDate).sort(function (a, b) {
			// Parse German date format (DD.MM.YYYY) for sorting
			var partsA = a.date.split('.');
			var partsB = b.date.split('.');
			var dateA = new Date(partsA[2], partsA[1] - 1, partsA[0]);
			var dateB = new Date(partsB[2], partsB[1] - 1, partsB[0]);
			return dateA - dateB;
		});

		// Create worksheet data
		var wsData = [
			[clientName],
			['Zeitraum: ' + startDate + ' bis ' + endDate],
			[],
			['Datum', 'Stunden', 'Anzahl Tasks']
		];

		dateArray.forEach(function (item) {
			wsData.push([
				item.date,
				Math.round(item.hours * 100) / 100,
				item.tasks
			]);
		});

		// Add totals
		var totalHours = dateArray.reduce(function (sum, e) { return sum + e.hours; }, 0);
		var totalTasks = dateArray.reduce(function (sum, e) { return sum + e.tasks; }, 0);
		wsData.push([]);
		wsData.push(['GESAMT', Math.round(totalHours * 100) / 100, totalTasks]);

		// Create workbook
		var wb = XLSX.utils.book_new();
		var ws = XLSX.utils.aoa_to_sheet(wsData);

		// Set column widths
		ws['!cols'] = [
			{ wch: 15 },
			{ wch: 12 },
			{ wch: 15 }
		];

		// Merge cells for headers
		ws['!merges'] = [
			{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } },
			{ s: { r: 1, c: 0 }, e: { r: 1, c: 2 } }
		];

		XLSX.utils.book_append_sheet(wb, ws, 'Tagesübersicht');

		// Generate filename
		var fileClientName = clientName.replace(/[^a-zA-Z0-9äöüÄÖÜß\-_]/g, '_') || 'Zeiten';

		var filename = 'Zeiten_Kumuliert_' + fileClientName + '_' + startDate + '_bis_' + endDate + '.xlsx';

		XLSX.writeFile(wb, filename);
	}

	// Export button handlers
	$(document).on('click', '#export-detailed', function () {
		exportDetailedExcel();
	});

	$(document).on('click', '#export-cumulated', function () {
		exportCumulatedExcel();
	});

	// deletemessage with ajax
	$(document).on('click', 'i.deletemessage', function () {
		if (!confirm('Möchtest du diese Nachricht wirklich löschen?')) {
			return;
		}

		var btn = $(this);
		var mid = parseInt(btn.data('mid'));
		var timeid = parseInt(btn.data('timeid'));
		var password = $('#timespage #passform input[name="password"]').val();
		var nonce = $('#timespage').data('nonce');
		var client_id = parseInt(btn.parents('div.client').data('id'));
		var client_id_parent = parseInt($('#timespage').data('client_id'));

		if (!mid || !nonce) {
			return;
		}

		var box = btn.parents('.comment');
		box.css('opacity', '0.5');

		$.ajax({
			url: ajaxcall.url,
			type: 'post',
			data: {
				security: nonce,
				action: 'delete_time_message',
				mid: mid,
				timeid: timeid,
				password: password,
				client_id: client_id,
				client_id_parent: client_id_parent
			},
			success: function (response) {
				if (response.success) {
					box.slideUp(150, function () {
						box.remove();
					});
				} else {
					alert(response.data);
				}
			}
		});

	});

});