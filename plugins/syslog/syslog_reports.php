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
include_once('./plugins/syslog/functions.php');

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();

		syslog_action_edit();

		bottom_footer();
		break;
	default:
		top_header();

		syslog_report();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_report')) && (isempty_request_var('add_dq_y'))) {
		$reportid = api_syslog_report_save(get_filter_request_var('id'), get_nfilter_request_var('name'), 
			get_nfilter_request_var('type'), get_nfilter_request_var('message'), 
			get_nfilter_request_var('timespan'), get_nfilter_request_var('timepart'), 
			get_nfilter_request_var('body'), get_nfilter_request_var('email'), 
			get_nfilter_request_var('notes'), get_nfilter_request_var('enabled'));

		if ((is_error_message()) || (get_filter_request_var('id') != get_filter_request_var('_id'))) {
			header('Location: syslog_reports.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		}else{
			header('Location: syslog_reports.php?header=false');
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $colors, $config, $syslog_actions, $fields_syslog_action_edit;

	include(dirname(__FILE__) . '/config.php');

	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP,
		 array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_report_remove($selected_items[$i]);
				}
			}else if (get_request_var('drp_action') == '2') { /* disable */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_report_disable($selected_items[$i]);
				}
			}else if (get_request_var('drp_action') == '3') { /* enable */
				for ($i=0; $i<count($selected_items); $i++) {
					api_syslog_report_enable($selected_items[$i]);
				}
			}
		}

		header('Location: syslog_reports.php?header=false');

		exit;
	}

	top_header();

	form_start('syslog_reports.php');

	html_start_box($syslog_actions{get_request_var('drp_action')}, '60%', '', '3', 'center', '');

	/* setup some variables */
	$report_array = array(); $report_list = '';

	/* loop through each of the clusters selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$report_info = syslog_db_fetch_cell('SELECT name FROM `' . $syslogdb_default . '`.`syslog_reports` WHERE id=' . $matches[1]);
			$report_list  .= '<li>' . $report_info . '</li>';
			$report_array[] = $matches[1];
		}
	}

	if (sizeof($report_array)) {
		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following Syslog Report(s).') . "</p>
					<ul>$report_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Delete Syslog Report(s)');
		}else if (get_request_var('drp_action') == '2') { /* disable */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Disable the following Syslog Report(s).') . "</p>
					<ul>$report_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Disable Syslog Report(s)');
		}else if (get_request_var('drp_action') == '3') { /* enable */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Enable the following Syslog Report(s).') . "</p>
					<ul>$report_list</ul>";
					print "</td></tr>
				</td>
			</tr>\n";

			$title = __('Enable Syslog Report(s)');
		}

		$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='$title'";
	}else{
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one Syslog Report.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($report_array) ? serialize($report_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function api_syslog_report_save($id, $name, $type, $message, $timespan, $timepart, $body,
	$email, $notes, $enabled) {
	global $config;

	include(dirname(__FILE__) . '/config.php');

	/* get the username */
	$username = db_fetch_cell('SELECT username FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	if ($id) {
		$save['id'] = $id;
	}else{
		$save['id'] = '';
	}

	$hour   = intval($timepart / 60);
	$minute = $timepart % 60;

	$save['name']     = form_input_validate($name,     'name',     '', false, 3);
	$save['type']     = form_input_validate($type,     'type',     '', false, 3);
	$save['message']  = form_input_validate($message,  'message',  '', false, 3);
	$save['timespan'] = form_input_validate($timespan, 'timespan', '', false, 3);
	$save['timepart'] = form_input_validate($timepart, 'timepart', '', false, 3);
	$save['body']     = form_input_validate($body,     'body',     '', false, 3);
	$save['email']    = form_input_validate($email,    'email',    '', true, 3);
	$save['notes']    = form_input_validate($notes,    'notes',    '', true, 3);
	$save['enabled']  = ($enabled == 'on' ? 'on':'');
	$save['date']     = time();
	$save['user']     = $username;

	if (!is_error_message()) {
		$id = 0;
		$id = syslog_sql_save($save, '`' . $syslogdb_default . '`.`syslog_reports`', 'id');

		if ($id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $id;
}

function api_syslog_report_remove($id) {
	include(dirname(__FILE__) . '/config.php');
	syslog_db_execute('DELETE FROM `' . $syslogdb_default . '`.`syslog_reports` WHERE id=' . $id);
}

function api_syslog_report_disable($id) {
	include(dirname(__FILE__) . '/config.php');
	syslog_db_execute('UPDATE `' . $syslogdb_default . "`.`syslog_reports` SET enabled='' WHERE id=" . $id);
}

function api_syslog_report_enable($id) {
	include(dirname(__FILE__) . '/config.php');
	syslog_db_execute('UPDATE `' . $syslogdb_default . "`.`syslog_reports` SET enabled='on' WHERE id=" . $id);
}

/* ---------------------
    Reports Functions
   --------------------- */

function syslog_get_report_records(&$sql_where, $rows) {
	include(dirname(__FILE__) . '/config.php');

	if (get_request_var('filter') != '') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') .
			"(message LIKE '%%" . get_request_var('filter') . "%%' OR " .
			"email LIKE '%%" . get_request_var('filter') . "%%' OR " .
			"notes LIKE '%%" . get_request_var('filter') . "%%' OR " .
			"name LIKE '%%" . get_request_var('filter') . "%%')";
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

	$query_string = 'SELECT *
		FROM `' . $syslogdb_default . "`.`syslog_reports`
		$sql_where
		$sql_order
		$sql_limit";

	return syslog_db_fetch_assoc($query_string);
}

function syslog_action_edit() {
	global $colors, $message_types, $syslog_freqs, $syslog_times;

	include(dirname(__FILE__) . '/config.php');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('type');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$report = syslog_db_fetch_row('SELECT *
			FROM `' . $syslogdb_default . '`.`syslog_reports`
			WHERE id=' . get_request_var('id'));

		if (sizeof($report)) {
			$header_label = __('Report Edit [edit: %s]', $report['name']);
		}else{
			$header_label = __('Report Edit [new]');

			$report['name'] = __('New Report Record');
		}
	}else{
		$header_label = __('Report Edit [new]');

		$report['name'] = __('New Report Record');
	}

	$fields_syslog_report_edit = array(
		'spacer0' => array(
			'method' => 'spacer',
			'friendly_name' => __('Report Details')
		),
		'name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Report Name'),
			'description' => __('Please describe this Report.'),
			'value' => '|arg1:name|',
			'max_length' => '250'
		),
		'enabled' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Enabled?'),
			'description' => __('Is this Report Enabled?'),
			'value' => '|arg1:enabled|',
			'array' => array('on' => __('Enabled'), '' => __('Disabled')),
			'default' => 'on'
		),
		'type' => array(
			'method' => 'drop_array',
			'friendly_name' => __('String Match Type'),
			'description' => __('Define how you would like this string matched.'),
			'value' => '|arg1:type|',
			'array' => $message_types,
			'default' => 'matchesc'
		),
		'message' => array(
			'method' => 'textbox',
			'friendly_name' => __('Syslog Message Match String'),
			'description' => __('The matching component of the syslog message.'),
			'value' => '|arg1:message|',
			'default' => '',
			'max_length' => '255'
		),
		'timespan' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Report Frequency'),
			'description' => __('How often should this Report be sent to the distribution list?'),
			'value' => '|arg1:timespan|',
			'array' => $syslog_freqs,
			'default' => 'del'
		),
		'timepart' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Send Time'),
			'description' => __('What time of day should this report be sent?'),
			'value' => '|arg1:timepart|',
			'array' => $syslog_times,
			'default' => 'del'
		),
		'message' => array(
			'friendly_name' => __('Syslog Message Match String'),
			'description' => __('The matching component of the syslog message.'),
			'method' => 'textbox',
			'max_length' => '255',
			'value' => '|arg1:message|',
			'default' => '',
		),
		'body' => array(
			'friendly_name' => __('Report Body Text'),
			'textarea_rows' => '5',
			'textarea_cols' => '60',
			'description' => __('The information that will be contained in the body of the report.'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:body|',
			'default' => '',
		),
		'email' => array(
			'friendly_name' => __('Report Email Addresses'),
			'textarea_rows' => '3',
			'textarea_cols' => '60',
			'description' => __('Comma delimited list of Email addresses to send the report to.'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:email|',
			'default' => '',
		),
		'notes' => array(
			'friendly_name' => __('Report Notes'),
			'textarea_rows' => '3',
			'textarea_cols' => '60',
			'description' => __('Space for Notes on the Report'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'value' => '|arg1:notes|',
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
		'save_component_report' => array(
			'method' => 'hidden',
			'value' => '1'
		)
	);

	form_start('syslog_reports.php', 'syslog_edit');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_syslog_report_edit, (isset($report) ? $report : array()))
		)
	);

	html_end_box();

	form_save_button('syslog_reports.php', '', 'id');
}

function syslog_filter() {
	global $colors, $config, $item_rows;
	?>
	<tr class='even'>
		<td>
		<form id='reports'>
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
		</td>
		<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL = 'syslog_reports.php?filter='+$('#filter').val()+'&enabled='+$('#enabled').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'syslog_reports.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh').click(function() {
                    applyFilter();
			});

			$('#clear').click(function() {
                    clearFilter();
			});

			$('#removal').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
	</tr>
	<?php
}

function syslog_report() {
	global $colors, $syslog_actions, $message_types, $syslog_freqs, $syslog_times, $config;

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

    validate_store_request_vars($filters, 'sess_syslogrep');
    /* ================= input validation ================= */

	html_start_box(__('Syslog Report Filters'), '100%', '', '3', 'center', 'syslog_reports.php?action=edit&type=1');

	syslog_filter();

	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	$reports   = syslog_get_report_records($sql_where, $rows);

	$rows_query_string = 'SELECT COUNT(*)
		FROM `' . $syslogdb_default . "`.`syslog_reports`
		$sql_where";

	$total_rows = syslog_db_fetch_cell($rows_query_string);

	$nav = html_nav_bar('syslog_reports.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Reports'), 'page', 'main');

	form_start('syslog_reports.php', 'chk');

	print $nav;

	html_start_box('', '100%', $colors['header'], '3', 'center', '');

	$display_text = array(
		'name'     => array(__('Report Name'), 'ASC'),
		'enabled'  => array(__('Enabled'), 'ASC'),
		'type'     => array(__('Match Type'), 'ASC'),
		'message'  => array(__('Search String'), 'ASC'),
		'timespan' => array(__('Frequency'), 'ASC'),
		'timepart' => array(__('Send Time'), 'ASC'),
		'lastsent' => array(__('Last Sent'), 'ASC'),
		'date'     => array(__('Last Modified'), 'ASC'),
		'user'     => array(__('By User'), 'DESC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($reports)) {
		foreach ($reports as $report) {
			form_alternate_row('line' . $report['id']);
			form_selectable_cell(filter_value(title_trim($report['name'], read_config_option('max_title_length')), get_request_var('filter'), $config['url_path'] . 'plugins/syslog/syslog_reports.php?action=edit&id=' . $report['id']), $report['id']);
			form_selectable_cell((($report['enabled'] == 'on') ? __('Yes'):__('No')), $report['id']);
			form_selectable_cell($message_types[$report['type']], $report['id']);
			form_selectable_cell($report['message'], $report['id']);
			form_selectable_cell($syslog_freqs[$report['timespan']], $report['id']);
			form_selectable_cell($syslog_times[$report['timepart']], $report['id']);
			form_selectable_cell(($report['lastsent'] == 0 ? __('Never'): date('Y-m-d H:i:s', $report['lastsent'])), $report['id']);
			form_selectable_cell(date('Y-m-d H:i:s', $report['date']), $report['id']);
			form_selectable_cell($report['user'], $report['id']);
			form_checkbox_cell($report['name'], $report['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='4'><em>" . __('No Syslog Reports Defined') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($reports)) {
		print $nav;
	}

	draw_actions_dropdown($syslog_actions);

	form_end();
}

