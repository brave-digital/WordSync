(function( $ ) {
	'use strict';


	/** BraveWpSync-Job constants:
	 * These status constants should be kept in sync with the class-wordsync-job.php Constants on which they are based. **/
	var STATUS_NONE = 0;
	var STATUS_WAITING = 1;
	var STATUS_GATHERING = 2;
	var STATUS_REVIEWING = 3;
	var STATUS_UPDATING = 4;
	var STATUS_DONE = 5;


	var PROCESSOR_STATUS_WAITING = 0;
	var PROCESSOR_STATUS_PROCESSING = 1;
	var PROCESSOR_STATUS_DONEPROCESSING = 2;
	var PROCESSOR_STATUS_UPDATING = 3;
	var PROCESSOR_STATUS_DONEUPDATING = 4;


	var CHANGE_UPDATE = 'update';
	var CHANGE_REMOVE = 'remove';
	var CHANGE_CREATE = 'create';



	window.bravesyncjob = {id: 0, status: STATUS_NONE, processors:[]};

	function jobStatusToString(status)
	{
		switch (status)
		{
			case STATUS_NONE: return 'Idle';
			case STATUS_WAITING: return 'Waiting...';
			case STATUS_GATHERING: return 'Gathering Data...';
			case STATUS_REVIEWING: return 'Reviewing Data';
			case STATUS_UPDATING: return 'Applying Changes...';
			case STATUS_DONE: return 'Done!';
			default: return 'Unknown State';
		}

	}

	function processorStatusToString(status)
	{
		switch (status)
		{
			case PROCESSOR_STATUS_WAITING: return 'Waiting';
			case PROCESSOR_STATUS_PROCESSING: return 'Processing';
			case PROCESSOR_STATUS_DONEPROCESSING: return 'Done Processing';
			case PROCESSOR_STATUS_UPDATING: return 'Updating';
			case PROCESSOR_STATUS_DONEUPDATING: return 'Done Updating';
			default: return 'Unknown State';
		}
	}

	function doAjax(command, data, onSuccess, onFail)
	{
		$.getJSON(ajaxurl, {
				action: 'wordsync_admin',
				command: command,
				data: data
			},
			onSuccess)
			.fail(onFail);
	}


	function displayLog()
	{
		doAjax('getlog', {},
		function(response){
			if (response.hasOwnProperty('log'))
			{
				$('.log').val(response.log);
			}
		});
	}



	function updateUI(busymessage, errormessage)
	{
		if (busymessage)
		{
			$('#progress').html(busymessage);
		}

		$('.statusbox').toggleClass('hidden', (window.bravesyncjob.id == 0) && (window.bravesyncjob.status != STATUS_DONE));

		if (errormessage)
		{
			$('.error-message').removeClass('hidden').find('.error-text').html(errormessage);
		}
		else
		{
			$('.error-message').addClass('hidden');
		}

		$('.resultsbox, .btn-proceed').toggleClass('hidden', window.bravesyncjob.status != STATUS_REVIEWING);

		$('.btn-cancelsync').toggleClass('hidden', window.bravesyncjob.id == 0);
		$('.btn-sync').toggleClass('hidden', window.bravesyncjob.id != 0);


	}


	function createChangeGroup(processor, title, subtitle, rows)
	{
		var html =
			'<div class="changegroup '+(rows == '' ? 'collapsed' : '')+'" data-processor="'+processor+'">' +
			'<div class="changeheading">'+
			'<h3>'+title+' <span class="small">'+subtitle+'</span></h3>'+
			'<a href="#" class="downarrow"><span class="dashicons dashicons-arrow-down"></span></a>'+
			'</div>'+
			'<div class="changecontent">'+
			'<table class="wp-list-table widefat fixed striped table-changes">'+
			'<thead>'+
			'<tr>'+
			'<td class="check-column"><input class="checkall" type="checkbox" data-processor="'+processor+'" checked/></td>'+
			'<th class="small-column">#</th>'+
			'<th>Name</th>'+
			'<th>Field</th>'+
			'<th class="data-column">Local Value</th>'+
			'<th class="small-column"></th>'+
			'<th class="data-column">Remote Value</th>'+
			'</tr>'+
			'</thead>'+
			'<tbody>'+
			rows +
			'</tbody>'+
			'</table>'+
			'</div>'+
			'</div>';

		return html;
	}

	function createChangeRow(processor, id, name, field, local, remote, action)
	{
		var iconclass, icontitle;
		switch (action)
		{
			case CHANGE_CREATE:
				iconclass = 'create-mark dashicons-arrow-left-alt';
				icontitle = 'Create';
				break;
			case CHANGE_REMOVE:
				iconclass = 'delete-mark dashicons-no';
				icontitle = 'Remove';
				break;
			case CHANGE_UPDATE:
				iconclass = 'edit-mark dashicons-arrow-left-alt';
				icontitle = 'Update';
				break;
		}

		var html =
			'<tr>'+
			'<td><input type="checkbox" class="checkitem" checked name="'+processor+'[]" value="'+id+'"/></td>'+
			'<td>'+id+'</td>'+
			'<td>'+name+'</td>'+
			'<td>'+field+'</td>'+
			'<td class="data-cell">'+(typeof local != 'undefined' ? local : '-')+'</td>'+
			'<td><span class="dashicons '+iconclass+'" title="'+icontitle+'"></span></td>'+
			'<td class="data-cell">'+(typeof remote != 'undefined' ? remote : '-')+'</td>'+
			'</tr>';


		return html;
	}

	function createDifferenceRow(name, field, local, remote, action)
	{


		var iconclass, icontitle;
		switch (action)
		{
			case CHANGE_CREATE:
				iconclass = 'create-mark dashicons-arrow-left-alt';
				icontitle = 'Create';
				break;
			case CHANGE_REMOVE:
				iconclass = 'delete-mark dashicons-no';
				icontitle = 'Remove';
				break;
			case CHANGE_UPDATE:
				iconclass = 'edit-mark dashicons-arrow-left-alt';
				icontitle = 'Update';
				break;
		}

		var html =
			'<tr>'+
			'<td colspan="2"></td>'+
			'<td>'+name+'</td>'+
			'<td>'+field+'</td>'+
			'<td class="data-cell">'+(typeof local != 'undefined' ? local : '-')+'</td>'+
			'<td><span class="dashicons '+iconclass+'" title="'+icontitle+'"></span></td>'+
			'<td class="data-cell">'+(typeof remote != 'undefined' ? remote : '-')+'</td>'+
			'</tr>';


		return html;
	}

	function continueSyncJobAfterDelay(interval)
	{
		setTimeout(function() { continueSyncJob(jobStatusToString(window.bravesyncjob.status));	}, interval);
	}


	/**
	 * Acts on the response back from the server for each sync step.
	 * Assumes that the response is valid AND successful
	 * @param response
	 */
	function actOnProgress(response)
	{

		if (!response || !response.success)
		{
			updateUI(false, response.msg);
			return;
		}

		updateUI(jobStatusToString(response.status), false);

		if (response.status != STATUS_REVIEWING)
		{
			$('.changeslist').html('');
		}

		switch (response.status)
		{
			case STATUS_REVIEWING:

				if (response.hasOwnProperty('changes'))
				{

					window.bravesyncjob.changes = response.changes;

					var processors = response.changes;
					var html = '';
					for (var i = 0; i < processors.length; i++)
					{
						var rows = '';
						var changes = processors[i].changes;
						for (var j = 0; j < changes.length; j++)
						{
							if (changes[j].differences.length > 0)
							{
								if (changes[j].differences.length == 1)
								{
									var field = changes[j].differences[0];
									rows += createChangeRow(processors[i].slug, changes[j].id, (changes[j].lname ? changes[j].lname : changes[j].rname), field.fn + (field.k != '' ? ' > '+field.k : ''), (field ? field.l : undefined), (field ? field.r : undefined), changes[j].action);

								}
								else
								{
									var localdesc = changes[j].differences.length + " Differences";
									var remotedesc = localdesc;

									if (changes[j].action == CHANGE_REMOVE)
									{
										localdesc = 'Object';
										remotedesc = 'Doesnt Exist';
									}

									if (changes[j].action == CHANGE_CREATE)
									{
										localdesc = 'Doesnt Exist';
										remotedesc = 'Object';
									}
									rows += createChangeRow(processors[i].slug, changes[j].id, (changes[j].lname ? changes[j].lname : changes[j].rname), '', localdesc, remotedesc, changes[j].action);

									for (var k = 0; k < changes[j].differences.length; k++)
									{
										var field = changes[j].differences[k];
										rows += createDifferenceRow('', field.fn + (field.k != '' ? ' > '+field.k : ''), (field && field.hasOwnProperty("l") ? field.l : undefined), (field && field.hasOwnProperty("r") ? field.r : undefined), changes[j].action);
									}
								}


							}
							else
							{
								var localdesc = undefined;
								var remotedesc = undefined;

								if (changes[j].action == CHANGE_REMOVE)
								{
									localdesc = 'Object';
									remotedesc = 'Doesn\'t Exist';
								}

								if (changes[j].action == CHANGE_CREATE)
								{
									localdesc = 'Doesn\'t Exist';
									remotedesc = 'Object';
								}
								rows += createChangeRow(processors[i].slug, changes[j].id, changes[j].lname ? changes[j].lname : changes[j].rname, '', localdesc, remotedesc, changes[j].action);
							}
						}

						html += createChangeGroup(processors[i].slug, processors[i].name, '(' + changes.length + ' changes)', rows);
					}

					$('.changeslist').html(html);
				}


				break;

			case STATUS_DONE:

				resetJobStatus();

				updateUI("Done!");

				break;

			default:

				continueSyncJobAfterDelay(10);

		}
	}


	function setJobStatus(json)
	{
		window.bravesyncjob = window.bravesyncjob || {};

		if (json.hasOwnProperty("id")) window.bravesyncjob.id = json.id;
		window.bravesyncjob.status = json.status ? json.status : STATUS_GATHERING;
		if (json.hasOwnProperty("processors")) window.bravesyncjob.processors = json.processors;

		console.log("Job Status is now "+ jobStatusToString(window.bravesyncjob.status));

		for (var i = 0; i < window.bravesyncjob.processors.length; i++)
		{
				$('.processor[data-proc="'+window.bravesyncjob.processors[i].slug+'"] .status').html(processorStatusToString(window.bravesyncjob.processors[i].status));

		}

		if (window.bravesyncjob.id != 0 && window.bravesyncjob.id != "newjob")
		{
			var lifecycle = [STATUS_GATHERING, STATUS_REVIEWING, STATUS_UPDATING, STATUS_DONE];
			var percent = 0;

			var lifecycleinc = (1/lifecycle.length);
			for (var i = 0; i < lifecycle.length; i++)
			{
				percent += lifecycleinc;

				if (lifecycle[i] == window.bravesyncjob.status)
				{
					var targetstatus;
					if (window.bravesyncjob.status == STATUS_GATHERING)
					{
						targetstatus = PROCESSOR_STATUS_DONEPROCESSING;
					}
					if (window.bravesyncjob.status == STATUS_UPDATING)
					{
						targetstatus = PROCESSOR_STATUS_DONEUPDATING;
					}

					if (targetstatus)
					{
						var procinc = (1/window.bravesyncjob.processors.length)*lifecycleinc;
						for (var i = 0; i < window.bravesyncjob.processors.length; i++)
						{
							if (window.bravesyncjob.processors[i].status == targetstatus) percent += procinc;
						}
					}

					break;
				}

			}

			percent = Math.round(percent*100);

			$('.progressbar .bar').css('width', percent+"%");
			$('.progressbar .percent').html(percent+"%");
		}
		else
		{
			if (window.bravesyncjob.status == STATUS_DONE)
			{
				$('.progressbar .bar').css('width', "100%");
				$('.progressbar .percent').html("100%");
			}
			else
			{
				$('.progressbar .bar').css('width', "0%");
				$('.progressbar .percent').html("0%");
			}
		}


		Cookies.set('bravewordsync_currentjob', window.bravesyncjob, {expires: 1, path:''});
	}

	function resetJobStatus()
	{
		window.bravesyncjob = {id: 0, status: STATUS_NONE, processors:[]};
		Cookies.remove('bravewordsync_currentjob');
	}

	function loadJobStatus()
	{
		var cookie = Cookies.getJSON('bravewordsync_currentjob');
		if (cookie != null && typeof cookie == "object")
		{

			setJobStatus(cookie);
			console.log("Loaded JOB status from cookie: ",cookie);
			return true;
		}
		else
		{
			resetJobStatus();
			return false;
		}
	}

	function cancelJob(errorMsg)
	{

		//TODO: Cancel and delete job

		if (window.bravesyncjob.id != 0)
		{
			doAjax('canceljob',
				{
					jobid: window.bravesyncjob.id
				},
				function(response)
				{
					console.log("Deleted job!", response);
				},
				function()
				{

				}
			);

			setJobStatus({id:0, status: STATUS_NONE, processors: []});
		}

		updateUI(false, errorMsg);
	}

	/**
	 * Occurs after all event handlers are set and document has been loaded.
	 */
	function onStartup()
	{
		updateUI(false, false);
		if (loadJobStatus())
		{

			//If the loaded job was in the review phase, then display the change list:
			if (window.bravesyncjob.status == STATUS_REVIEWING)
			{
				updateUI(jobStatusToString(window.bravesyncjob.status), false);

				doAjax('getchanges',
					{
						jobid: window.bravesyncjob.id
					},
					function(response)
					{
						if (response.hasOwnProperty('success') && response.success)
						{
							//The getchanges command doesnt give a job status? Not sure why I did this...
							response.status = STATUS_REVIEWING;

							actOnProgress(response);

						}
						else
						{
							cancelJob((response && response.hasOwnProperty('success') ? response.msg : 'Recieved an invalid response back from the server.'));
						}

						displayLog();
					},
					function()
					{
						updateUI("", "Unable to retrieve changes list from the server. Did you lose connection? Please try again.");
					}
				);


			}

		}


	}

	function continueSyncJob(busyMsg)
	{
		updateUI(busyMsg, false);

		if (window.bravesyncjob.id == 0)
		{
			//The job has been deleted or cancelled or an error occured.
			return;
		}

		var payload = {
			jobid: window.bravesyncjob.id
		};

		if (window.bravesyncjob.status == STATUS_REVIEWING)
		{
			payload.selects = getSelectedChanges();
		}


		doAjax('continuesync',
			payload,
			function(response)
			{
				//console.log(response);
				if (response.hasOwnProperty('success') && response.success == true)
				{
					setJobStatus(response);

					actOnProgress(response);

				}
				else
				{
					cancelJob(response && response.hasOwnProperty('msg') ? response.msg : 'The server sent an invalid response.');
				}

				displayLog();

			},
			function(response)
			{
				updateUI(false, 'Oops! Something went wrong!');
				cancelJob();
			}
		);

	}


	function getSelectedChanges()
	{
		//Gather all the selected change items and ajax them to the backend.

		var selects = {};

		for (var i = 0; i < window.bravesyncjob.processors.length; i++)
		{
			var theseselects = [];
			var thisproc = window.bravesyncjob.processors[i].slug;

			$('.changegroup[data-processor="'+thisproc+'"] .checkitem:checked').each(function(e){
				theseselects.push($(this).val());
			});

			selects[thisproc] = theseselects;
		}

		console.log("Gathered selected changes. Change list is: ", selects);

		return selects;
	}

	$(document).ready(function(){

		$('.processors').on('click', '.processor', function(e){

			var btn = $(this);
			var inp = btn.find('input:checkbox');

			if (e.target == inp.get(0)) return true;

			e.preventDefault();

			inp.prop('checked', !inp.is(":checked"));
			inp.change();

			return false;
		});

		$('.processor input:checkbox').change(function(e){
			var btn = $(this).parents('.processor');

			if ($(this).is(':checked'))
			{
				btn.addClass('selected');
			}
			else
			{
				btn.removeClass('selected');
			}
		});

		$('.processor input:checkbox:checked').each(function(e){
			$(this).parents('.processor').addClass('selected');
		})

		$('.btn-showlog').click(function(e){
			$('.logbox .inside').toggleClass('hidden');
		});

		$('.btn-sync').click(function(e){
			e.preventDefault();

			resetJobStatus();
			setJobStatus({id:"newjob", status:STATUS_NONE}); //Needed for UpdateUI so that it can display the progress div.

			updateUI('Starting Sync Job...', false);

			var processors = [];

			$('input[name="processor_enabled[]"]:checked').each(function(){
				processors.push($(this).val());
			});

			if (processors.length == 0)
			{
				alert("Please select some data to synchronise.");
				return;
			}

			doAjax('startsync',
			{
				remoteurl: $('#remoteurl').val(),
				processors: processors
			},
			function(response)
			{
				if (response.hasOwnProperty('success'))
				{
					if (response.success == true)
					{
						setJobStatus(response);
						continueSyncJobAfterDelay(10);
					}
					else
					{
						updateUI(false, (response.success ? false : response.msg));
					}

				}
				else
				{
					updateUI( false, (response && response.hasOwnProperty('success') ? response.msg : 'Recieved an invalid response back from the server. Please try refresh the page.'));
				}

				displayLog();
			},
			function(response)
			{
				updateUI( false,'Oops! Something went wrong!');
				console.log(response);
			}
			);

		});




		$('.btn-proceed').click(function(e){
			e.preventDefault();

			if (window.bravesyncjob.status == STATUS_REVIEWING)
			{
				continueSyncJob("Applying Changes...");
			}
			else
			{
				updateUI(false, "You can't proceed when the job isnt at the REVIEW stage!")
			}
		});

		$('.btn-cancelsync').click(function(e)
		{
			e.preventDefault();
			cancelJob();



		});




		$('.bravewrap').on('click', '.changegroup .downarrow', function(e){
			//Expand and collapse change groups when the down arrow is clicked.

			e.preventDefault();
			var $grp = $(this).parents('.changegroup');
			if ($grp.hasClass('collapsed'))
			{
				$grp.removeClass('collapsed');
				$(this).find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
			}
			else
			{
				$grp.addClass('collapsed');
				$(this).find('.dashicons').addClass('dashicons-arrow-down').removeClass('dashicons-arrow-right');
			}

		});


		$('#changes').on('click', 'input.checkall', function(e){

			//Select all checkitems in this group when the "Check All" checkbox is clicked.

			var thisproc = $(this).attr('data-processor');

			$('.changegroup[data-processor="'+thisproc+'"] .checkitem').prop('checked', $(this).is(":checked"));
		});

		$('#changes').on('change', '.checkitem', function(e){

			//For each checkbox in this group, sync up the "Check All" checkbox when it changes.

			var $thisgroup = $(this).parentsUntil('.changegroup');

			if (!$(this).is(':checked'))
			{
				$thisgroup.find('.checkall').prop('checked', false);
			}
			else
			{
				$thisgroup.find('.checkall').prop('checked', ($thisgroup.find('.checkitem:not(:checked)').length == 0));
			}
		});



		onStartup();

	});
})( jQuery );
