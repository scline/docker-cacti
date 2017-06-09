<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include('./include/auth.php');
include_once('plugins/syslog/functions.php');

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
	case 'newedit':
		top_header();

		syslog_action_edit();

		bottom_footer();
		break;
	default:
		top_header();

		syslog_alerts();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_alert')) && (isempty_request_var('add_dq_y'))) {
		$alertid = api_syslog_alert_save(get_nfilter_request_var('id'), get_nfilter_request_var('name'), 
			get_nfilter_request_var('report_method'), get_nfilter_request_var('num'), 
			get_nfilter_request_var('type'), get_nfilter_request_var('message'), 
			get_nfilter_request_var('email'), get_nfilter_request_var('notes'), 
			get_nfilter_request_var('enabled'), get_nfilter_request_var('severity'), 
			get_nfilter_request_var('command'), get_nfilter_request_var('repeat_alert'), 
			get_nfilter_request_var('open_ticket'));

		if ((is_error_message()) || (get_filter_request_var('id') != get_filter_request_var('_id'))) {
			header('Location: syslog_alerts.php?header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
		}else{
			header('Location: syslog_alerts.php?header=false');
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $syslog_actions, $fields_syslog_action_edit;

	include(dirname(__FILE__) . '/config.php');

	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP,
		 array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_alert_remove($selected_items[$i]);
				}
			}else if (get_request_var('drp_action') == '2') { /* disable */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_alert_disable($selected_items[$i]);
				}
			}else if (get_request_var('drp_action') == '3') { /* enable */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_alert_enable($selected_items[$i]);
				}
			}
		}

		header('Location: syslog_alerts.php?header=false');

		exit;
	}

	top_header();

	form_start('syslog_alerts.php');

	html_start_box($syslog_actions{get_request_var('drp_action')}, '60%', '', '3', 'center', '');

	/* setup some variables */
	$alert_array = array(); $alert_list = '';

	/* loop through each of the clusters selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$alert_info = syslog_db_fetch_cell('SELECT name FROM `' . $syslogdb_default . '`.`syslog_alert` WHERE id=' . $matches[1]);
			$alert_list .= '<li>' . $alert_info . '</li>';
			$alert_array[] = $matches[1];
		}
	}

	if (sizeof($alert_array)) {
		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following Syslog Alert Rule(s).') . "</p>
					<ul>$alert_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Delete Syslog Alert Rule(s)');
		}else if (get_request_var('drp_action') == '2') { /* disable */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Disable the following Syslog Alert Rule(s).') . "</p>
					<ul>$alert_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Disable Syslog Alert Rule(s)');
		}else if (get_request_var('drp_action') == '3') { /* enable */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Enable the following Syslog Alert Rule(s).') . "</p>
					<ul>$alert_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Enable Syslog Alert Rule(s)');
		}

		$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='$title'";
	}else{
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Syslog Alert Rule.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($alert_array) ? serialize($alert_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function api_syslog_alert_save($id, $name, $method, $num, $type, $message, $email, $notes,
	$enabled, $severity, $command, $repeat_alert, $open_ticket) {

	include(dirname(__FILE__) . '/config.php');

	/* get the username */
	$username = db_fetch_cell('SELECT username FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	if ($id) {
		$save['id'] = $id;
	}else{
		$save['id'] = '';
	}

	$save['name']         = form_input_validate($name,         'name',     '', false, 3);
	$save['num']          = form_input_validate($num,          'num',      '', false, 3);
	$save['message']      = form_input_validate($message,      'message',  '', false, 3);
	$save['email']        = form_input_validate(trim($email),  'email',    '', true, 3);
	$save['command']      = form_input_validate($command,      'command',  '', true, 3);
	$save['notes']        = form_input_validate($notes,        'notes',    '', true, 3);
	$save['enabled']      = ($enabled == 'on' ? 'on':'');
	$save['repeat_alert'] = form_input_validate($repeat_alert, 'repeat_alert', '', true, 3);
	$save['open_ticket']  = ($open_ticket == 'on' ? 'on':'');
	$save['type']         = $type;
	$save['severity']     = $severity;
	$save['method']       = $method;
	$save['user']         = $username;
	$save['date']         = time();

	if (!is_error_message()) {
		$id = 0;
		$id = syslog_sql_save($save, '`' . $syslogdb_default . '`.`syslog_alert`', 'id');
		if ($id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $id;
}

function api_syslog_alert_remove($id) {
	include(dirname(__FILE__) . '/config.php');
	syslog_db_execute("DELETE FROM `" . $syslogdb_default . "`.`syslog_alert` WHERE id='" . $id . "'");
}

function api_syslog_alert_disable($id) {
	include(dirname(__FILE__) . "/config.php");
	syslog_db_execute("UPDATE `" . $syslogdb_default . "`.`syslog_alert` SET enabled='' WHERE id='" . $id . "'");
}

function api_syslog_alert_enable($id) {
	include(dirname(__FILE__) . "/config.php");
	syslog_db_execute("UPDATE `" . $syslogdb_default . "`.`syslog_alert` SET enabled='on' WHERE id='" . $id . "'");
}

/* ---------------------
    Alert Functions
   --------------------- */

function syslog_get_alert_records(&$sql_where, $rows) {
	include(dirname(__FILE__) . '/config.php');

	if (get_request_var('filter') != '') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') .
			"(message LIKE '%" . get_request_var('filter') . "%' OR " .
			"email LIKE '%" . get_request_var('filter') . "%' OR " .
			"notes LIKE '%" . get_request_var('filter') . "%' OR " .
			"name LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('enabled') == '-1') {
		// Display all status'
	}elseif (get_request_var('enabled') == '1') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') .
			"enabled='on'";
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') .
			"enabled=''";
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$query_string = "SELECT *
		FROM `" . $syslogdb_default . "`.`syslog_alert`
		$sql_where
		$sql_order
		$sql_limit";

	return syslog_db_fetch_assoc($query_string);
}

function syslog_action_edit() {
	global $message_types, $severities;

	include(dirname(__FILE__) . '/config.php');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('type');
	get_filter_request_var('date');
	/* ==================================================== */

	if (!isempty_request_var('id') && get_nfilter_request_var('action') == 'edit') {
		$alert = syslog_db_fetch_row('SELECT *
			FROM `' . $syslogdb_default . '`.`syslog_alert`
			WHERE id=' . get_request_var('id'));

		if (sizeof($alert)) {
			$header_label = __('Alert Edit [edit: %s]' . $alert['name']);
		}else{
			$header_label = __('Alert Edit [new]');
		}
	}else if (isset_request_var('id') && get_nfilter_request_var('action') == 'newedit') {
		$syslog_rec = syslog_db_fetch_row("SELECT * FROM `" . $syslogdb_default . "`.`syslog` WHERE seq=" . get_request_var("id") . (isset_request_var('date') ? " AND logtime='" . get_request_var("date") . "'":""));

		$header_label = __('Alert Edit [new]');
		if (sizeof($syslog_rec)) {
			$alert['message'] = $syslog_rec['message'];
		}

		$alert['name'] = __('New Alert Rule');
	}else{
		$header_label = __('Alert Edit [new]');

		$alert['name'] = __('New Alert Rule');
	}

	$alert_retention = read_config_option('syslog_alert_retention');
	if ($alert_retention != '' && $alert_retention > 0 && $alert_retention < 365) {
		$repeat_end = ($alert_retention * 24 * 60) / 5;
	}

	$repeatarray = array(
		0    => __('Not Set'), 
		1    => __('%d Minutes', 5), 
		2    => __('%d Minutes', 10), 
		3    => __('%d Minutes', 15), 
		4    => __('%d Minutes', 20), 
		6    => __('%d Minutes', 30), 
		8    => __('%d Minutes', 45), 
		12   => __('%d Hour', 1), 
		24   => __('%d Hours', 2), 
		36   => __('%d Hours', 3), 
		48   => __('%d Hours', 4), 
		72   => __('%d Hours', 6), 
		96   => __('%d Hours', 8), 
		144  => __('%d Hours', 12), 
		288  => __('%d Day', 1), 
		576  => __('%d Days', 2), 
		2016 => __('%d Week', 1), 
		4032 => __('%d Weeks', 2), 
		8640 => __('Month')
	);

	if ($repeat_end) {
		foreach ($repeatarray as $i => $value) {
			if ($i > $repeat_end) {
				unset($repeatarray[$i]);
			}
		}
	}

	$fields_syslog_alert_edit = array(
		'spacer0' => array(
			'method' => 'spacer',
			'friendly_name' => __('Alert Details')
		),
		'name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Alert Name'),
			'description' => __('Please describe this Alert.'),
			'value' => '|arg1:name|',
			'max_length' => '250',
			'size' => 80
		),
		'severity' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Severity'),
			'description' => __('What is the Severity Level of this Alert?'),
			'value' => '|arg1:severity|',
			'array' => $severities,
			'default' => '1'
		),
		'report_method' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Reporting Method'),
			'description' => __('Define how to Alert on the syslog messages.'),
			'value' => '|arg1:method|',
			'array' => array('0' => __('Individual'), '1' => __('Threshold')),
			'default' => '0'
		),
		'num' => array(
			'method' => 'textbox',
			'friendly_name' => __('Threshold'),
			'description' => __('For the \'Threshold\' method, If the number seen is above this value an Alert will be triggered.'),
			'value' => '|arg1:num|',
			'size' => '4',
			'max_length' => '10',
			'default' => '1'
		),
		'type' => array(
			'method' => 'drop_array',
			'friendly_name' => __('String Match Type'),
			'description' => __('Define how you would like this string matched.  If using the SQL Expression type you may use any valid SQL expression
			to generate the alarm.  Available fields include \'message\', \'facility\', \'priority\', and \'host\'.'),
			'value' => '|arg1:type|',
			'array' => $message_types,
			'on_change' => 'changeTypes()',
			'default' => 'matchesc'
		),
		'message' => array(
			'friendly_name' => __('Syslog Message Match String'),
			'description' => __('Enter the matching component of the syslog message, the facility or host name, or the SQL where clause if using the SQL Expression Match Type.'),
			'textarea_rows' => '2',
			'textarea_cols' => '70',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:message|',
			'default' => ''
		),
		'enabled' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Alert Enabled'),
			'description' => __('Is this Alert Enabled?'),
			'value' => '|arg1:enabled|',
			'array' => array('on' => __('Enabled'), '' => __('Disabled')),
			'default' => 'on'
		),
		'repeat_alert' => array(
			'friendly_name' => __('Re-Alert Cycle'),
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => '0',
 			'description' => __('Do not resend this alert again for the same host, until this amount of time has elapsed. For threshold based alarms, this applies to all hosts.'),
			'value' => '|arg1:repeat_alert|'
		),
		'notes' => array(
			'friendly_name' => __('Alert Notes'),
			'textarea_rows' => '5',
			'textarea_cols' => '70',
			'description' => __('Space for Notes on the Alert'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:notes|',
			'default' => '',
		),
		'spacer1' => array(
			'method' => 'spacer',
			'friendly_name' => __('Alert Actions')
		),
		'open_ticket' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Open Ticket'),
			'description' => __('Should a Help Desk Ticket be opened for this Alert'),
			'value' => '|arg1:open_ticket|',
			'array' => array('on' => __('Yes'), '' => __('No')),
			'default' => ''
		),
		'email' => array(
			'method' => 'textarea',
			'friendly_name' => __('Emails to Notify'),
			'textarea_rows' => '5',
			'textarea_cols' => '70',
			'description' => __('Please enter a comma delimited list of Email addresses to inform.  If you
			wish to send out Email to a recipient in SMS format, please prefix that recipient\'s Email address
			with <b>\'sms@\'</b>.  For example, if the recipients SMS address is <b>\'2485551212@mycarrier.net\'</b>, you would
			enter it as <b>\'sms@2485551212@mycarrier.net\'</b> and it will be formatted as an SMS message.'),
			'class' => 'textAreaNotes',
			'value' => '|arg1:email|',
			'max_length' => '255'
		),
		'command' => array(
			'friendly_name' => __('Alert Command'),
			'textarea_rows' => '5',
			'textarea_cols' => '70',
			'description' => __('When an Alert is triggered, run the following command.  The following replacement variables
			are available <b>\'&lt;HOSTNAME&gt;\'</b>, <b>\'&lt;ALERTID&gt;\'</b>, <b>\'&lt;MESSAGE&gt;\'</b>,
			<b>\'&lt;FACILITY&gt;\'</b>, <b>\'&lt;PRIORITY&gt;\'</b>, <b>\'&lt;SEVERITY&gt;\'</b>.  Please
			note that <b>\'&lt;HOSTNAME&gt;\'</b> is only available on individual thresholds.'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:command|',
			'default' => '',
		),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
		),
		'_id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
		),
		'save_component_alert' => array(
			'method' => 'hidden',
			'value' => '1'
		)
	);

	form_start('syslog_alerts.php', 'syslog_edit');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_syslog_alert_edit, (sizeof($alert) ? $alert : array()))
		)
	);

	html_end_box();

	form_save_button('syslog_alerts.php', '', 'id');

	?>
	<script type='text/javascript'>

	function changeTypes() {
		if ($('#type').val() == 'sql') {
			$('#message').prep('rows', 6);
		}else{
			$('#message').prep('rows', 2);
		}
	}
	</script>

	<?php
}

function syslog_filter() {
	global $config, $item_rows;

	?>
	<tr class='even'>
		<td>
		<form id='alert' action='syslog_alerts.php' method='get'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Enabled');?>
					</td>
					<td>
						<select id='enabled' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('enabled') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('enabled') == '1') {?> selected<?php }?>><?php print __('Yes');?></option>
							<option value='0'<?php if (get_request_var('enabled') == '0') {?> selected<?php }?>><?php print __('No');?></option>
						</select>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='<?php print __('Go');?>'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __('Clear');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL = 'syslog_alerts.php?filter='+$('#filter').val()+'&enabled='+$('#enabled').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'syslog_alerts.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh').click(function() {
                    applyFilter();
			});

			$('#clear').click(function() {
                    clearFilter();
			});

			$('#alert').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}

function syslog_alerts() {
	global $syslog_actions, $config, $message_types, $severities;

	include(dirname(__FILE__) . '/config.php');

    /* ================= input validation and session storage ================= */
    $filters = array(
        'rows' => array(
            'filter' => FILTER_VALIDATE_INT,
            'pageset' => true,
            'default' => '-1',
            ),
        'page' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'enabled' => array(
            'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
            'default' => '-1'
			),
        'filter' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_column' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'name',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_direction' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => array('options' => 'sanitize_search_string')
            )
    );

    validate_store_request_vars($filters, 'sess_sysloga');
    /* ================= input validation ================= */

	html_start_box(__('Syslog Alert Filters'), '100%', '', '3', 'center', 'syslog_alerts.php?action=edit');

	syslog_filter();

	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	$alerts = syslog_get_alert_records($sql_where, $rows);

	$rows_query_string = "SELECT COUNT(*)
		FROM `" . $syslogdb_default . "`.`syslog_alert`
		$sql_where";

	$total_rows = syslog_db_fetch_cell($rows_query_string);

	$nav = html_nav_bar('syslog_alerts.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, __('Alerts'), 'page', 'main');

	form_start('syslog_alerts.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'     => array(__('Alert Name'), 'ASC'),
		'severity' => array(__('Severity'), 'ASC'),
		'method'   => array(__('Method'), 'ASC'),
		'num'      => array(__('Threshold Count'), 'ASC'),
		'enabled'  => array(__('Enabled'), 'ASC'),
		'type'     => array(__('Match Type'), 'ASC'),
		'message'  => array(__('Search String'), 'ASC'),
		'email'    => array(__('Email Addresses'), 'DESC'),
		'date'     => array(__('Last Modified'), 'ASC'),
		'user'     => array(__('By User'), 'DESC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($alerts)) {
		foreach ($alerts as $alert) {
			form_alternate_row('line' . $alert['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . $config['url_path'] . 'plugins/syslog/syslog_alerts.php?action=edit&id=' . $alert['id'] . "'>" . ((get_request_var('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $alert['name']) : $alert['name']) . '</a>', $alert['id']);
			form_selectable_cell($severities[$alert['severity']], $alert['id']);
			form_selectable_cell(($alert['method'] == 1 ? __('Threshold'):__('Individual')), $alert['id']);
			form_selectable_cell(($alert['method'] == 1 ? $alert['num']:__('N/A')), $alert['id']);
			form_selectable_cell((($alert['enabled'] == 'on') ? __('Yes'):__('No')), $alert['id']);
			form_selectable_cell($message_types[$alert['type']], $alert['id']);
			form_selectable_cell(title_trim($alert['message'],60), $alert['id']);
			form_selectable_cell((substr_count($alert['email'], ',') ? __('Multiple'):$alert['email']), $alert['id']);
			form_selectable_cell(date('Y-m-d H:i:s', $alert['date']), $alert['id']);
			form_selectable_cell($alert['user'], $alert['id']);
			form_checkbox_cell($alert['name'], $alert['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='4'><em>" . __('No Syslog Alerts Defined') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($alerts)) {
		print $nav;
	}

	draw_actions_dropdown($syslog_actions);

	form_end();
}

?>
