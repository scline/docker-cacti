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
 | Originally released as aloe by: sidewinder at shitworks.com             |
 | Modified by: Harlequin <harlequin@cyberonic.com>                        |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* allow guest account to see this page */
$guest_account = true;

/* initialize cacti environment */
chdir('../../');
include('./include/auth.php');

/* syslog specific database setup and functions */
include('./plugins/syslog/config.php');
include_once('./plugins/syslog/functions.php');

set_default_action();

if (get_request_var('action') == 'ajax_programs') {
	return get_ajax_programs(true);
}

$title = __('Syslog Viewer', 'syslog');

$trimvals = array(
	'1024' => __('All Text', 'syslog'),
	'30'   => __('%d Chars', 30, 'syslog'),
	'50'   => __('%d Chars', 50, 'syslog'),
	'75'   => __('%d Chars', 75, 'syslog'),
	'100'  => __('%d Chars', 100, 'syslog'),
	'150'  => __('%d Chars', 150, 'syslog'),
	'300'  => __('%d Chars', 300, 'syslog')
);

/* set the default tab */
get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));

load_current_session_value('tab', 'sess_syslog_tab', 'syslog');
$current_tab = get_request_var('tab');

if (get_request_var('action') == 'save') {
	save_settings();
	exit;
}

/* validate the syslog post/get/request information */;
if ($current_tab != 'stats') {
	syslog_request_validation($current_tab);
}

if (isset_request_var('refresh')) {
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = $config['url_path'] . 'plugins/syslog/syslog.php?header=false&tab=' . $current_tab;
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);
}

/* draw the tabs */
/* display the main page */
if (isset_request_var('export')) {
	syslog_export($current_tab);

	/* clear output so reloads wont re-download */
	unset_request_var('output');
}else{
	general_header();

	syslog_display_tabs($current_tab);

	if ($current_tab == 'current') {
		syslog_view_alarm();
	}elseif ($current_tab == 'stats') {
		syslog_statistics();
	}else{
		syslog_messages($current_tab);
	}

	bottom_footer();
}

$_SESSION['sess_nav_level_cache'] = '';

function syslog_display_tabs($current_tab) {
	global $config;

	/* present a tabbed interface */
	$tabs_syslog['syslog'] = __('System Logs', 'syslog');
	if (read_config_option('syslog_statistics') == 'on') {
		$tabs_syslog['stats']  = __('Statistics', 'syslog');
	}
	$tabs_syslog['alerts'] = __('Alert Logs', 'syslog');

	/* if they were redirected to the page, let's set that up */
	if (!isempty_request_var('id') || $current_tab == 'current') {
		$current_tab = 'current';
	}

	load_current_session_value('id', 'sess_syslog_id', '0');
	if (!isempty_request_var('id') || $current_tab == 'current') {
		$tabs_syslog['current'] = __('Selected Alert', 'syslog');
	}

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (sizeof($tabs_syslog)) {
		foreach (array_keys($tabs_syslog) as $tab_short_name) {
			print '<li><a class="tab ' . (($tab_short_name == $current_tab) ? 'selected"':'"') . " href='" . htmlspecialchars($config['url_path'] .
				'plugins/syslog/syslog.php?' .
				'tab=' . $tab_short_name) .
				"'>" . $tabs_syslog[$tab_short_name] . "</a></li>\n";
		}
	}
	print "</ul></nav></div>\n";
}

function syslog_view_alarm() {
	global $config;

	include(dirname(__FILE__) . '/config.php');

	echo "<table class='cactiTable'>";
	echo "<tr class='tableHeader'><td class='textHeaderDark'>" . __('Syslog Alert View', 'syslog') . "</td></tr>";
	echo "<tr><td class='odd'>\n";

	$html = syslog_db_fetch_cell('SELECT html FROM `' . $syslogdb_default . '`.`syslog_logs` WHERE seq=' . get_request_var('id'));
	echo trim($html, "' ");

	echo '</td></tr></table>';

	exit;
}

/** function syslog_statistics()
 *  This function paints a table of summary statistics for syslog
 *  messages by host, facility, priority, and time range.
*/
function syslog_statistics() {
	global $title, $rows, $config;

	include(dirname(__FILE__) . '/config.php');

    /* ================= input validation and session storage ================= */
    $filters = array(
        'rows' => array(
            'filter' => FILTER_VALIDATE_INT,
            'pageset' => true,
            'default' => '-1',
            ),
        'refresh' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_config_option('syslog_refresh'),
            ),
        'timespan' => array(
            'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
            'default' => '300',
            ),
        'page' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'filter' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'host' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'facility' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'priority' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'eprogram' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_column' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'host',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_direction' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => array('options' => 'sanitize_search_string')
            )
    );

    validate_store_request_vars($filters, 'sess_syslogs');
    /* ================= input validation ================= */

	html_start_box(__('Syslog Statistics Filter', 'syslog'), '100%', '', '3', 'center', '');

	syslog_stats_filter();

	html_end_box();

	$sql_where   = '';
	$sql_groupby = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	$records = get_stats_records($sql_where, $sql_groupby, $rows);

	$rows_query_string = "SELECT COUNT(*)
		FROM `" . $syslogdb_default . "`.`syslog_statistics` AS ss
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
		ON ss.facility_id=sf.facility_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
		ON ss.priority_id=sp.priority_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spr
		ON ss.program_id=spr.program_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
		ON ss.host_id=sh.host_id
		$sql_where
		$sql_groupby";

	$total_rows = syslog_db_fetch_cell('SELECT COUNT(*) FROM ('. $rows_query_string  . ') as temp');

	$nav = html_nav_bar('syslog.php?tab=stats&filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 4, __('Messages', 'syslog'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'host'        => array('display' => __('Device Name', 'syslog'), 'sort' => 'ASC',  'align' => 'left'),
		'facility'    => array('display' => __('Facility', 'syslog'),    'sort' => 'ASC',  'align' => 'left'),
		'priority'    => array('display' => __('Priority', 'syslog'),    'sort' => 'ASC',  'align' => 'left'),
		'program'     => array('display' => __('Program', 'syslog'),     'sort' => 'ASC',  'align' => 'left'),
		'insert_time' => array('display' => __('Date', 'syslog'),        'sort' => 'DESC', 'align' => 'right'),
		'records'     => array('display' => __('Records', 'syslog'),     'sort' => 'DESC', 'align' => 'right'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (get_request_var('timespan') < 3600) {
		$date_format = 'Y-m-d H:i';
	}elseif (get_request_var('timespan') < 86400) {
		$date_format = 'Y-m-d H:00';
	}else{
		$date_format = 'Y-m-d 00:00';
	}

	if (sizeof($records)) {
		foreach ($records as $r) {
			$time = date($date_format, strtotime($r['insert_time']));

			form_alternate_row();
			echo '<td>' . (get_request_var('host') != '-2' ? $r['host']:'-') . '</td>';
			echo '<td>' . (get_request_var('facility') != '-2' ? ucfirst($r['facility']):'-') . '</td>';
			echo '<td>' . (get_request_var('priority') != '-2' ? ucfirst($r['priority']):'-') . '</td>';
			echo '<td>' . (get_request_var('program') != '-2' ? ucfirst($r['program']):'-') . '</td>';
			//echo '<td class="right">' . $r['insert_time'] . '</td>';
			echo '<td class="right">' . $time . '</td>';
			echo '<td class="right">' . number_format_i18n($r['records'], -1)     . '</td>';
			form_end_row();
		}
	}else{
		print "<tr><td colspan='4'><em>" . __('No Syslog Statistics Found', 'syslog') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($records)) {
		print $nav;
	}
}

function get_stats_records(&$sql_where, &$sql_groupby, $rows) {
	include(dirname(__FILE__) . '/config.php');

	$sql_where   = '';
	$sql_groupby = 'GROUP BY sh.host';

	/* form the 'where' clause for our main sql query */
	if (!isempty_request_var('filter')) {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "sh.host LIKE '%" . get_request_var('filter') . "%'";
	}

	if (get_request_var('host') == '-2') {
		// Do nothing
	}elseif (get_request_var('host') != '-1' && get_request_var('host') != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . 'ss.host_id=' . get_request_var('host');
		$sql_groupby .= ', sh.host';
	}else{
		$sql_groupby .= ', sh.host';
	}

	if (get_request_var('facility') == '-2') {
		// Do nothing
	}elseif (get_request_var('facility') != '-1' && get_request_var('facility') != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . 'ss.facility_id=' . get_request_var('facility');
		$sql_groupby .= ', sf.facility';
	}else{
		$sql_groupby .= ', sf.facility';
	}

	if (get_request_var('priority') == '-2') {
		// Do nothing
	}elseif (get_request_var('priority') != '-1' && get_request_var('priority') != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ': ' AND ') . 'ss.priority_id=' . get_request_var('priority');
		$sql_groupby .= ', sp.priority';
	}else{
		$sql_groupby .= ', sp.priority';
	}

	if (get_request_var('eprogram') == '-2') {
		// Do nothing
	}elseif (get_request_var('eprogram') != '-1' && get_request_var('program') != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ': ' AND ') . 'ss.program_id=' . get_request_var('eprogram');
		$sql_groupby .= ', spr.program';
	}else{
		$sql_groupby .= ', spr.program';
	}

	if (get_request_var('timespan') != '-1') {
		$sql_groupby .= ', UNIX_TIMESTAMP(insert_time) DIV ' . get_request_var('timespan');
	}

	$sql_order = get_order_string();
	if (!isset_request_var('export')) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	} else {
		$sql_limit = ' LIMIT 10000';
	}

	$time = 'FROM_UNIXTIME(TRUNCATE(UNIX_TIMESTAMP(insert_time)/' . get_request_var('timespan') . ',0)*' . get_request_var('timespan') . ') AS insert_time';

	$query_sql = "SELECT sh.host, sf.facility, sp.priority, spr.program, sum(ss.records) AS records, $time
		FROM `" . $syslogdb_default . "`.`syslog_statistics` AS ss
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
		ON ss.facility_id=sf.facility_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
		ON ss.priority_id=sp.priority_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spr
		ON ss.program_id=spr.program_id
		LEFT JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
		ON ss.host_id=sh.host_id
		$sql_where
		$sql_groupby
		$sql_order
		$sql_limit";

	return syslog_db_fetch_assoc($query_sql);
}

function syslog_stats_filter() {
	global $config, $item_rows;
	?>
	<tr class='even'>
		<td>
		<form id='stats_form' action='syslog.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Device', 'syslog');?>
					</td>
					<td>
						<select id='host' onChange='applyFilter(document.stats)'>
							<option value='-1'<?php if (get_request_var('host') == '-1') { ?> selected<?php } ?>><?php print __('All', 'syslog');?></option>
							<option value='-2'<?php if (get_request_var('host') == '-2') { ?> selected<?php } ?>><?php print __('None', 'syslog');?></option>
							<?php
							$facilities = syslog_db_fetch_assoc('SELECT DISTINCT host_id, host
								FROM syslog_hosts AS sh
								ORDER BY host');

							if (sizeof($facilities)) {
							foreach ($facilities as $r) {
								print '<option value="' . $r['host_id'] . '"'; if (get_request_var('host') == $r['host_id']) { print ' selected'; } print '>' . $r['host'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Facility', 'syslog');?>
					</td>
					<td>
						<select id='facility' onChange='applyFilter(document.stats)'>
							<option value='-1'<?php if (get_request_var('facility') == '-1') { ?> selected<?php } ?>><?php print __('All', 'syslog');?></option>
							<option value='-2'<?php if (get_request_var('facility') == '-2') { ?> selected<?php } ?>><?php print __('None', 'syslog');?></option>
							<?php
							$facilities = syslog_db_fetch_assoc('SELECT DISTINCT facility_id, facility
								FROM syslog_facilities AS sf
								ORDER BY facility');

							if (sizeof($facilities)) {
							foreach ($facilities as $r) {
								print '<option value="' . $r['facility_id'] . '"'; if (get_request_var('facility') == $r['facility_id']) { print ' selected'; } print '>' . ucfirst($r['facility']) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Priority', 'syslog');?>
					</td>
					<td>
						<select id='priority' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('priority') == '-1') { ?> selected<?php } ?>><?php print __('All', 'syslog');?></option>
							<option value='-2'<?php if (get_request_var('priority') == '-2') { ?> selected<?php } ?>><?php print __('None', 'syslog');?></option>
							<?php
							$priorities = syslog_db_fetch_assoc('SELECT DISTINCT priority_id, priority
								FROM syslog_priorities AS sp
								ORDER BY priority');

							if (sizeof($priorities)) {
							foreach ($priorities as $r) {
								print '<option value="' . $r['priority_id'] . '"'; if (get_request_var('priority') == $r['priority_id']) { print ' selected'; } print '>' . ucfirst($r['priority']) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<?php print html_program_filter(get_request_var('eprogram'));?>
					<td>
						<input id='go' type='button' value='<?php print __esc('Go', 'syslog');?>'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __esc('Clear', 'syslog');?>'>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'syslog');?>
					</td>
					<td>
						<input type='text' id='filter' size='30' value='<?php print get_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Time Range', 'syslog');?>
					</td>
					<td>
						<select id='timespan' onChange='applyFilter()'>
							<option value='60'<?php if (get_request_var('timespan') == '60') { ?> selected<?php } ?>><?php print __('%d Minute', 1, 'syslog');?></option>
							<option value='120'<?php if (get_request_var('timespan') == '120') { ?> selected<?php } ?>><?php print __('%d Minutes', 2, 'syslog');?></option>
							<option value='300'<?php if (get_request_var('timespan') == '300') { ?> selected<?php } ?>><?php print __('%d Minutes', 5, 'syslog');?></option>
							<option value='600'<?php if (get_request_var('timespan') == '600') { ?> selected<?php } ?>><?php print __('%d Minutes', 10, 'syslog');?></option>
							<option value='1800'<?php if (get_request_var('timespan') == '1800') { ?> selected<?php } ?>><?php print __('%d Minutes', 30, 'syslog');?></option>
							<option value='3600'<?php if (get_request_var('timespan') == '3600') { ?> selected<?php } ?>><?php print __('%d Hour', 1, 'syslog');?></option>
							<option value='7200'<?php if (get_request_var('timespan') == '7200') { ?> selected<?php } ?>><?php print __('%d Hours', 2, 'syslog');?></option>
							<option value='14400'<?php if (get_request_var('timespan') == '14400') { ?> selected<?php } ?>><?php print __('%d Hours', 4, 'syslog');?></option>
							<option value='28880'<?php if (get_request_var('timespan') == '28880') { ?> selected<?php } ?>><?php print __('%d Hours', 8, 'syslog');?></option>
							<option value='86400'<?php if (get_request_var('timespan') == '86400') { ?> selected<?php } ?>><?php print __('%d Day', 1, 'syslog');?></option>
						</select>
					</td>
					<td>
						<?php print __('Entries', 'syslog');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
						<option value='-1'<?php if (get_request_var('rows') == '-1') { ?> selected<?php } ?>><?php print __('Default', 'syslog');?></option>
						<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
						?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
		</form>
		</td>
		<script type='text/javascript'>

		function clearFilter() {
			strURL = 'syslog.php?tab=stats&clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#go').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
		});

		function applyFilter() {
			strURL  = 'syslog.php?header=false&facility=' + $('#facility').val();
			strURL += '&host=' + $('#host').val();
			strURL += '&priority=' + $('#priority').val();
			strURL += '&program=' + $('#program').val();
			strURL += '&timespan=' + $('#timespan').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		</script>
	</tr>
	<?php
}

/** function syslog_request_validation()
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function syslog_request_validation($current_tab, $force = false) {
	global $title, $rows, $config, $reset_multi;

	include_once('./lib/timespan_settings.php');

	if ($current_tab != 'alerts' && isset_request_var('host') && get_nfilter_request_var('host') == -1) {
		kill_session_var('sess_syslog_' . $current_tab . '_hosts');
		unset_request_var('host');
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
        'rows' => array(
            'filter' => FILTER_VALIDATE_INT,
            'pageset' => true,
            'default' => read_user_setting('syslog_rows', '-1', $force)
            ),
        'page' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => ''
            ),
        'removal' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('syslog_removal', '1', $force)
            ),
        'refresh' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('syslog_refresh', read_config_option('syslog_refresh'), $force)
            ),
        'trimval' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('syslog_trimval', '75', $force)
            ),
        'enabled' => array(
            'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
            'default' => '-1'
			),
        'host' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '0',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'efacility' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => read_user_setting('syslog_efacility', '-1', $force),
            'options' => array('options' => 'sanitize_search_string')
            ),
        'epriority' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => read_user_setting('syslog_epriority', '-1', $force),
            'options' => array('options' => 'sanitize_search_string')
            ),
        'eprogram' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => read_user_setting('syslog_eprogram', '-1', $force),
            'options' => array('options' => 'sanitize_search_string')
            ),
        'filter' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_column' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'logtime',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_direction' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'DESC',
            'options' => array('options' => 'sanitize_search_string')
            )
    );

    validate_store_request_vars($filters, 'sess_syslogs_' . $current_tab);
    /* ================= input validation ================= */

	api_plugin_hook_function('syslog_request_val');

	if (isset_request_var('host')) {
		$_SESSION['sess_syslog_' . $current_tab . '_hosts'] = get_nfilter_request_var('host');
	} else if (isset($_SESSION['sess_syslog_' . $current_tab . '_hosts'])) {
		set_request_var('host', $_SESSION['sess_syslog_' . $current_tab . '_hosts']);
	} else {
		set_request_var('host', '-1');
	}
}

function get_syslog_messages(&$sql_where, $rows, $tab) {
	global $sql_where, $hostfilter, $current_tab, $syslog_incoming_config;

	include(dirname(__FILE__) . '/config.php');

	$sql_where = '';
	/* form the 'where' clause for our main sql query */
	if (get_request_var('host') == -1 && $tab != 'syslog') {
		$sql_where .=  "WHERE sl.host='N/A'";
	}else{
		if (!isempty_request_var('host')) {
			sql_hosts_where($tab);
			if (strlen($hostfilter)) {
				$sql_where .=  'WHERE ' . $hostfilter;
			}
		}
	}

	if (isset($_SESSION['sess_current_date1'])) {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') .
			"logtime BETWEEN '" . $_SESSION['sess_current_date1'] . "'
				AND '" . $_SESSION['sess_current_date2'] . "'";
	}

	if (isset_request_var('id') && $current_tab == 'current') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') .
			'sa.id=' . get_request_var('id');
	}

	if (!isempty_request_var('filter')) {
		if ($tab == 'syslog') {
			$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "message LIKE '%" . get_request_var('filter') . "%'";
		}else{
			$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "logmsg LIKE '%" . get_request_var('filter') . "%'";
		}
	}

	if (get_request_var('eprogram') != '-1') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "syslog.program_id='" . get_request_var('eprogram') . "'";
	}

	if (get_request_var('efacility') != '-1') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "syslog.facility_id='" . get_request_var('efacility') . "'";
	}

	if (isset_request_var('epriority') && get_request_var('epriority') != '-1') {
		$priorities = '';

		switch(get_request_var('epriority')) {
		case '0':
			$priorities = "=0";
			break;
		case '1o':
			$priorities = "=1";
			break;
		case '1':
			$priorities = "<1";
			break;
		case '2o':
			$priorities = "=2";
			break;
		case '2':
			$priorities = "<=2";
			break;
		case '3o':
			$priorities = "=3";
			break;
		case '3':
			$priorities = "<=3";
			break;
		case '4o':
			$priorities = "=4";
			break;
		case '4':
			$priorities = "<=4";
			break;
		case '5o':
			$priorities = "=5";
			break;
		case '5':
			$priorities = "<=5";
			break;
		case '6o':
			$priorities = "=6";
			break;
		case '6':
			$priorities = "<=6";
			break;
		case '7':
			$priorities = "=7";
			break;
		}

		$sql_where .= (!strlen($sql_where) ? 'WHERE ': ' AND ') . 'syslog.priority_id ' . $priorities;
	}

	$sql_where = api_plugin_hook_function('syslog_sqlwhere', $sql_where);

	$sql_order = get_order_string();
	if (!isset_request_var('export')) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	} else {
		$sql_limit = ' LIMIT 10000';
	}

	if ($tab == 'syslog') {
		if (get_request_var('removal') == '-1') {
			$query_sql = "SELECT syslog.*, syslog_programs.program, 'main' AS mtype
				FROM `" . $syslogdb_default . "`.`syslog`
				LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs`
				ON syslog.program_id=syslog_programs.program_id " .
				$sql_where . "
				$sql_order
				$sql_limit";
		}elseif (get_request_var('removal') == '1') {
			$query_sql = "(SELECT syslog.*, syslog_programs.program, 'main' AS mtype
				FROM `" . $syslogdb_default . "`.`syslog` AS syslog
				LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs`
				ON syslog.program_id=syslog_programs.program_id " .
				$sql_where . "
				) UNION (SELECT syslog.*, syslog_programs.program, 'remove' AS mtype
				FROM `" . $syslogdb_default . "`.`syslog_removed` AS syslog
				LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs`
				ON syslog.program_id=syslog_programs.program_id " .
				$sql_where . ")
				$sql_order
				$sql_limit";
		}else{
			$query_sql = "SELECT syslog.*, syslog_programs.program, 'remove' AS mtype
				FROM `" . $syslogdb_default . "`.`syslog_removed` AS syslog
				LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs` AS syslog_programs
				ON syslog.program_id=syslog_programs.program_id " .
				$sql_where . "
				$sql_order
				$sql_limit";
		}
	}else{
		$query_sql = "SELECT syslog.*, sf.facility, sp.priority, spr.program, sa.name, sa.severity
			FROM `" . $syslogdb_default . "`.`syslog_logs` AS syslog
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
			ON syslog.facility_id=sf.facility_id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
			ON syslog.priority_id=sp.priority_id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_alert` AS sa
			ON syslog.alert_id=sa.id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spr
			ON syslog.program_id=spr.program_id " .
			$sql_where . "
			$sql_order
			$sql_limit";
	}

	//echo $query_sql;

	return syslog_db_fetch_assoc($query_sql);
}

function syslog_filter($sql_where, $tab) {
	global $config, $graph_timespans, $graph_timeshifts, $reset_multi, $page_refresh_interval, $item_rows, $trimvals;

	include(dirname(__FILE__) . '/config.php');

	$unprocessed = syslog_db_fetch_cell("SELECT COUNT(*) FROM `" . $syslogdb_default . "`.`syslog_incoming`");

	if (isset($_SESSION['sess_current_date1'])) {
		$filter_text = __(' [ Start: \'%s\' to End: \'%s\', Unprocessed Messages: %s ]', $_SESSION['sess_current_date1'], $_SESSION['sess_current_date2'], $unprocessed, 'syslog');
	}else{
		$filter_text = __('[ Unprocessed Messages: %s ]', $unprocessed, 'syslog');
	}

	?>
	<script type='text/javascript'>

	var date1Open = false;
	var date2Open = false;

	$(function() {
		$('#syslog_form').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});

		$('#host').multiselect({
			noneSelectedText: '<?php print __('Select Device(s)', 'syslog');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Devices Selected', 'syslog');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Devices Selected', 'syslog');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'syslog');?>',
			uncheckAllText: '<?php print __('None', 'syslog');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == '0') {
					if (ui.checked == true) {
						$('#host').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter( {
			label: '<?php print __('Search', 'syslog');?>', width: '150'
		});

		$('#save').click(function() {
			saveSettings();
		});

		$('#go').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#export').click(function() {
			exportRecords();
		});

		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			}else{
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			}else{
				date2Open = true;
				$('#date2').datetimepicker('show');
			}
		});

		$('#date1').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#date2').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$(window).resize(function() {
			resizeHostSelect();
		});

		resizeHostSelect();
	});

	function resizeHostSelect() {
		position = $('#host').offset();
		$('#host').css('height', ($(window).height()-position.top)+'px');
	}

	function applyTimespan() {
		strURL  = urlPath+'plugins/syslog/syslog.php?header=false&predefined_timespan=' + $('#predefined_timespan').val();
		strURL += '&predefined_timeshift=' + $('#predefined_timeshift').val();
		loadPageNoHeader(strURL);
	}

	function applyFilter() {
		strURL = 'syslog.php?header=false'+
			'&date1='+$('#date1').val()+
			'&date2='+$('#date2').val()+
			'&host='+$('#host').val()+
			'&filter='+$('#filter').val()+
			'&efacility='+$('#efacility').val()+
			'&epriority='+$('#epriority').val()+
			'&eprogram='+$('#eprogram').val()+
			'&rows='+$('#rows').val()+
			'&trimval='+$('#trimval').val()+
			'&removal='+$('#removal').val()+
			'&refresh='+$('#refresh').val();

		loadPageNoHeader(strURL);
	}

	function exportRecords() {
		document.location = 'syslog.php?export=true';
	}

	function clearFilter() {
		strURL = 'syslog.php?header=false&clear=true';

		loadPageNoHeader(strURL);
	}

	function saveSettings() {
		strURL = 'syslog.php?action=save'+
			'&trimval='+$('#trimval').val()+
			'&rows='+$('#rows').val()+
			'&removal='+$('#removal').val()+
			'&refresh='+$('#refresh').val()+
			'&efacility='+$('#efacility').val()+
			'&epriority='+$('#epriority').val()+
			'&eprogram='+$('#eprogram').val();

		$.get(strURL, function() {
			$('#text').show().text('Filter Settings Saved').fadeOut(2000);
		});
	}

	function timeshiftFilterLeft() {
		var json = {
			move_left_x: 1,
			move_left_y: 1,
			date1: $('#date1').val(),
			date2: $('#date2').val(),
			predefined_timespan: $('#predefined_timespan').val(),
			predefined_timeshift: $('#predefined_timeshift').val(),
			__csrf_magic: csrfMagicToken
		};

		var href = urlPath+'plugins/syslog/syslog.php?action='+pageAction+'&header=false';
		$.post(href, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function timeshiftFilterRight() {
		var json = {
			move_right_x: 1,
			move_right_y: 1,
			date1: $('#date1').val(),
			date2: $('#date2').val(),
			predefined_timespan: $('#predefined_timespan').val(),
			predefined_timeshift: $('#predefined_timeshift').val(),
			__csrf_magic: csrfMagicToken
		};

		var href = urlPath+'plugins/syslog/syslog.php?action='+pageAction+'&header=false';
		$.post(href, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	</script>
	<?php

	html_start_box(__('Syslog Message Filter %s', $filter_text, 'syslog'), '100%', '', '3', 'center', '');?>
		<tr class='even noprint'>
			<td class='noprint'>
			<form id='syslog_form' action='syslog.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<select id='predefined_timespan' onChange='applyTimespan()'>
								<?php
								if ($_SESSION['custom']) {
									$graph_timespans[GT_CUSTOM] = __('Custom', 'syslog');
									set_request_var('predefined_timespan', GT_CUSTOM);
									$start_val = 0;
									$end_val = sizeof($graph_timespans);
								} else {
									if (isset($graph_timespans[GT_CUSTOM])) {
										asort($graph_timespans);
										array_shift($graph_timespans);
									}
									$start_val = 1;
									$end_val = sizeof($graph_timespans)+1;
								}

								if (sizeof($graph_timespans) > 0) {
									for ($value=$start_val; $value < $end_val; $value++) {
										print "<option value='$value'"; if (get_request_var('predefined_timespan') == $value) { print ' selected'; } print '>' . title_trim($graph_timespans[$value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('From', 'syslog');?>
						</td>
						<td>
							<input type='text' id='date1' size='15' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
						</td>
						<td>
							<i title='<?php print __esc('Start Date Selector', 'syslog');?>' class='calendar fa fa-calendar' id='startDate'></i>
						</td>
						<td>
							<?php print __('To', 'syslog');?>
						</td>
						<td>
							<input type='text' id='date2' size='15' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
						</td>
						<td>
							<i title='<?php print __esc('End Date Selector', 'syslog');?>' class='calendar fa fa-calendar' id='endDate'></i>
						</td>
						<td>
							<i title='<?php print __esc('Shift Time Backward', 'syslog');?>' onclick='timeshiftFilterLeft()' class='shiftArrow fa fa-backward'></i>
						</td>
						<td>
							<select id='predefined_timeshift' title='<?php print __esc('Define Shifting Interval', 'syslog');?>' onChange='applyTimespan()'>
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if (get_request_var('predefined_timeshift') == $shift_value) { print ' selected'; } print '>' . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<i title='<?php print __esc('Shift Time Forward', 'syslog');?>' onclick='timeshiftFilterRight()' class='shiftArrow fa fa-forward'></i>
						</td>
						<td>
							<input id='go' type='button' value='<?php print __esc('Go', 'syslog');?>'>
						</td>
						<td>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'syslog');?>' title='<?php print __esc('Return filter values to their user defined defaults', 'syslog');?>'>
						</td>
						<td>
							<input id='export' type='button' value='<?php print __esc('Export', 'syslog');?>' title='<?php print __esc('Export Records to CSV', 'syslog');?>'>
						</td>
						<td>
							<input id='save' type='button' value='<?php print __esc('Save', 'syslog');?>' title='<?php print __esc('Save Default Settings', 'syslog');?>'>
						</td>
						<?php if (api_plugin_user_realm_auth('syslog_alerts.php')) { ?>
						<td align='right' style='white-space:nowrap;'>
							<input type='button' value='<?php print __esc('Alerts', 'syslog');?>' title='<?php print __esc('View Syslog Alert Rules', 'syslog');?>' onClick='javascript:document.location="<?php print $config['url_path'] . "plugins/syslog/syslog_alerts.php";?>"'>
						</td>
						<td>
							<input type='button' value='<?php print __esc('Removals', 'syslog');?>' title='<?php print __esc('View Syslog Removal Rules', 'syslog');?>' onClick='javascript:document.location="<?php print $config['url_path'] . "plugins/syslog/syslog_removal.php";?>"'>
						</td>
						<td>
							<input type='button' value='<?php print __esc('Reports', 'syslog');?>' title='<?php print __esc('View Syslog Reports', 'syslog');?>' onClick='javascript:document.location="<?php print $config['url_path'] . "plugins/syslog/syslog_reports.php";?>"'>
						</td>
						<?php } ?>
						<td>
							<span id='text'></span>
							<input type='hidden' name='action' value='actions'>
							<input type='hidden' name='syslog_pdt_change' value='false'>
						</td>
					</tr>
				</table>
				<table class='filterTable'>
					<tr>
						<td>
							<input type='text' id='filter' size='30' value='<?php print get_request_var('filter');?>' onChange='applyFilter()'>
						</td>
						<td class='even'>
							<select id='host' multiple style='width: 150px; overflow: scroll;'>
								<?php if ($tab == 'syslog') { ?><option id='host_all' value='0'<?php if (get_request_var('host') == 'null' || get_request_var('host') == '0' || $reset_multi) { ?> selected<?php } ?>><?php print __('Show All Devices', 'syslog');?></option><?php }else{ ?>
								<option id='host_all' value='0'<?php if (get_request_var('host') == 'null' || get_request_var('host') == 0 || $reset_multi) { ?> selected<?php } ?>><?php print __('Show All Logs', 'syslog');?></option>
								<option id='host_none' value='-1'<?php if (get_request_var('host') == '-1') { ?> selected<?php } ?>><?php print __('Threshold Logs', 'syslog');?></option><?php } ?>
								<?php
								$hosts_where = '';
								$hosts_where = api_plugin_hook_function('syslog_hosts_where', $hosts_where);
								$hosts       = syslog_db_fetch_assoc("SELECT host_id, host
									FROM `" . $syslogdb_default . "`.`syslog_hosts`
									$hosts_where
									ORDER BY host");


								$selected    = explode(' ', get_request_var('host'));
								if (sizeof($hosts)) {
									foreach ($hosts as $host) {
										if (!is_ipaddress($host['host'])) {
											$parts = explode('.', $host['host']);
											$host['host'] = $parts[0];
										}

										print "<option value='" . $host["host_id"] . "'";
										if (sizeof($selected)) {
											if (in_array($host['host_id'], $selected)) {
												print ' selected';
											}
										}
										print '>';
										print $host['host'] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()' title='<?php print __esc('Display Rows', 'syslog');?>'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') { ?> selected<?php } ?>><?php print __('Default', 'syslog');?></option>
								<?php
								foreach($item_rows AS $rows => $display_text) {
									print "<option value='" . $rows . "'"; if (get_request_var('rows') == $rows) { print ' selected'; } print '>' . __('%d Messages', $display_text, 'syslog') . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							<select id='trimval' onChange='applyFilter()' title='<?php print __esc('Message Trim', 'syslog');?>'>
								<?php
								foreach($trimvals AS $seconds => $display_text) {
									print "<option value='" . $seconds . "'"; if (get_request_var('trimval') == $seconds) { print ' selected'; } print '>' . $display_text . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							<select id='refresh' onChange='applyFilter()'>
								<?php
								foreach($page_refresh_interval AS $seconds => $display_text) {
									print "<option value='" . $seconds . "'"; if (get_request_var('refresh') == $seconds) { print ' selected'; } print '>' . $display_text . "</option>\n";
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<table class='filterTable'>
					<tr>
						<?php api_plugin_hook('syslog_extend_filter');?>
						<?php html_program_filter(get_request_var('eprogram'));?>
						<td>
							<select id='efacility' onChange='applyFilter()' title='<?php print __esc('Facilities to filter on', 'syslog');?>'>
								<option value='-1'<?php if (get_request_var('efacility') == '0') { ?> selected<?php } ?>><?php print __('All Facilities', 'syslog');?></option>
								<?php
								if (!isset($hostfilter)) $hostfilter = '';
								$efacilities = syslog_db_fetch_assoc('SELECT DISTINCT f.facility_id, f.facility
									FROM `' . $syslogdb_default . '`.`syslog_host_facilities` AS fh
									INNER JOIN `' . $syslogdb_default . '`.`syslog_facilities` AS f
									ON f.facility_id=fh.facility_id ' . (strlen($hostfilter) ? 'WHERE ':'') . $hostfilter . '
									ORDER BY facility');

								if (sizeof($efacilities)) {
								foreach ($efacilities as $efacility) {
									print "<option value='" . $efacility['facility_id'] . "'"; if (get_request_var('efacility') == $efacility['facility_id']) { print ' selected'; } print '>' . ucfirst($efacility['facility']) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<select id='epriority' onChange='applyFilter()' title='<?php print __('Priority Levels', 'syslog');?>'>
								<option value='-1'<?php if (get_request_var('epriority') == '-1') { ?> selected<?php } ?>><?php print __('All Priorities', 'syslog');?></option>
								<option value='0'<?php if (get_request_var('epriority') == '0') { ?> selected<?php } ?>><?php print __('Emergency', 'syslog');?></option>
								<option value='1'<?php if (get_request_var('epriority') == '1') { ?> selected<?php } ?>><?php print __('Critical++', 'syslog');?></option>
								<option value='1o'<?php if (get_request_var('epriority') == '1o') { ?> selected<?php } ?>><?php print __('Critical', 'syslog');?></option>
								<option value='2'<?php if (get_request_var('epriority') == '2') { ?> selected<?php } ?>><?php print __('Alert++', 'syslog');?></option>
								<option value='2o'<?php if (get_request_var('epriority') == '2o') { ?> selected<?php } ?>><?php print __('Alert', 'syslog');?></option>
								<option value='3'<?php if (get_request_var('epriority') == '3') { ?> selected<?php } ?>><?php print __('Error++', 'syslog');?></option>
								<option value='3o'<?php if (get_request_var('epriority') == '3o') { ?> selected<?php } ?>><?php print __('Error', 'syslog');?></option>
								<option value='4'<?php if (get_request_var('epriority') == '4') { ?> selected<?php } ?>><?php print __('Warning++', 'syslog');?></option>
								<option value='4o'<?php if (get_request_var('epriority') == '4o') { ?> selected<?php } ?>><?php print __('Warning', 'syslog');?></option>
								<option value='5'<?php if (get_request_var('epriority') == '5') { ?> selected<?php } ?>><?php print __('Notice++', 'syslog');?></option>
								<option value='5o'<?php if (get_request_var('epriority') == '5o') { ?> selected<?php } ?>><?php print __('Notice', 'syslog');?></option>
								<option value='6'<?php if (get_request_var('epriority') == '6') { ?> selected<?php } ?>><?php print __('Info++', 'syslog');?></option>
								<option value='6o'<?php if (get_request_var('epriority') == '6o') { ?> selected<?php } ?>><?php print __('Info', 'syslog');?></option>
								<option value='7'<?php if (get_request_var('epriority') == '7') { ?> selected<?php } ?>><?php print __('Debug', 'syslog');?></option>
							</select>
						</td>
						<?php if (get_nfilter_request_var('tab') == 'syslog') { ?>
						<td>
							<select id='removal' onChange='applyFilter()' title='<?php print __esc('Removal Handling', 'syslog');?>'>
								<option value='1'<?php if (get_request_var('removal') == '1') { ?> selected<?php } ?>><?php print __('All Records', 'syslog');?></option>
								<option value='-1'<?php if (get_request_var('removal') == '-1') { ?> selected<?php } ?>><?php print __('Main Records', 'syslog');?></option>
								<option value='2'<?php if (get_request_var('removal') == '2') { ?> selected<?php } ?>><?php print __('Removed Records', 'syslog');?></option>
							</select>
						</td>
						<?php }else{ ?>
						<input type='hidden' id='removal' value='<?php print get_request_var('removal');?>'>
						<?php } ?>
					</tr>
				</table>
			</form>
			</td>
		</tr>
	<?php html_end_box(false);
}

/** function syslog_syslog_legend()
 *  This function displays the foreground and background colors for the syslog syslog legend
*/
function syslog_syslog_legend() {
	global $disabled_color, $notmon_color, $database_default;

	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr>';
	print "<td width='10%' class='logEmergency'>" . __('Emergency', 'syslog') . '</td>';
	print "<td width='10%' class='logCritical'>"  . __('Critical', 'syslog')  . '</td>';
	print "<td width='10%' class='logAlert'>"     . __('Alert', 'syslog')     . '</td>';
	print "<td width='10%' class='logError'>"     . __('Error', 'syslog')     . '</td>';
	print "<td width='10%' class='logWarning'>"   . __('Warning', 'syslog')   . '</td>';
	print "<td width='10%' class='logNotice'>"    . __('Notice', 'syslog')    . '</td>';
	print "<td width='10%' class='logInfo'>"      . __('Info', 'syslog')      . '</td>';
	print "<td width='10%' class='logDebug'>"     . __('Debug', 'syslog')     . '</td>';
	print '</tr>';
	html_end_box(false);
}

/** function syslog_log_legend()
 *  This function displays the foreground and background colors for the syslog log legend
*/
function syslog_log_legend() {
	global $disabled_color, $notmon_color, $database_default;

	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr>';
	print "<td width='10%' class='logCritical'>" . __('Critical', 'syslog')      . '</td>';
	print "<td width='10%' class='logWarning'>"  . __('Warning', 'syslog')       . '</td>';
	print "<td width='10%' class='logNotice'>"   . __('Notice', 'syslog')        . '</td>';
	print "<td width='10%' class='logInfo'>"     . __('Informational', 'syslog') . '</td>';
	print '</tr>';
	html_end_box(false);
}

/** function syslog_messages()
 *  This is the main page display function in Syslog.  Displays all the
 *  syslog messages that are relevant to Syslog.
*/
function syslog_messages($tab = 'syslog') {
	global $sql_where, $hostfilter, $severities;
	global $config, $syslog_incoming_config, $reset_multi, $syslog_levels;

	include(dirname(__FILE__) . '/config.php');
	include('./include/global_arrays.php');

	/* force the initial timespan to be 30 minutes for performance reasons */
	if (!isset($_SESSION['sess_syslog_init'])) {
		$_SESSION['sess_current_timespan'] = 1;
		$_SESSION['sess_syslog_init'] = 1;
	}

	$url_curr_page = get_browser_query_string();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	$syslog_messages = get_syslog_messages($sql_where, $rows, $tab);

	syslog_filter($sql_where, $tab);

	if ($tab == 'syslog') {
		if (get_request_var('removal') == 1) {
			$total_rows = syslog_db_fetch_cell("SELECT SUM(totals)
				FROM (
					SELECT count(*) AS totals
					FROM `" . $syslogdb_default . "`.`syslog` AS syslog
					$sql_where
					UNION
					SELECT count(*) AS totals
					FROM `" . $syslogdb_default . "`.`syslog_removed` AS syslog
					$sql_where
				) AS rowcount");
		}elseif (get_request_var("removal") == -1){
			$total_rows = syslog_db_fetch_cell("SELECT count(*)
				FROM `" . $syslogdb_default . "`.`syslog` AS syslog
				$sql_where");
		}else{
			$total_rows = syslog_db_fetch_cell("SELECT count(*)
				FROM `" . $syslogdb_default . "`.`syslog_removed` AS syslog
				$sql_where");
		}
	}else{
		$total_rows = syslog_db_fetch_cell("SELECT count(*)
			FROM `" . $syslogdb_default . "`.`syslog_logs` AS syslog
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
			ON syslog.facility_id=sf.facility_id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
			ON syslog.priority_id=sp.priority_id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_alert` AS sa
			ON syslog.alert_id=sa.id
			LEFT JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spr
			ON syslog.program_id=spr.program_id " .
			$sql_where);
	}

	if ($tab == 'syslog') {
		$nav = html_nav_bar("syslog.php?tab=$tab", MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 7, __('Messages', 'syslog'), 'page', 'main');

		if (api_plugin_user_realm_auth('syslog_alerts.php')) {
			$display_text = array(
				'nosortt'     => array(__('Actions', 'syslog'), 'ASC'),
				'logtime'     => array(__('Date', 'syslog'), 'ASC'),
				'host_id'     => array(__('Device', 'syslog'), 'ASC'),
				'program'     => array(__('Program', 'syslog'), 'ASC'),
				'message'     => array(__('Message', 'syslog'), 'ASC'),
				'facility_id' => array(__('Facility', 'syslog'), 'ASC'),
				'priority_id' => array(__('Priority', 'syslog'), 'ASC'));
		}else{
			$display_text = array(
				'logtime'     => array(__('Date', 'syslog'), 'ASC'),
				'host_id'     => array(__('Device', 'syslog'), 'ASC'),
				'program'     => array(__('Program', 'syslog'), 'ASC'),
				'message'     => array(__('Message', 'syslog'), 'ASC'),
				'facility_id' => array(__('Facility', 'syslog'), 'ASC'),
				'priority_id' => array(__('Priority', 'syslog'), 'ASC'));
		}

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		$hosts      = array_rekey(syslog_db_fetch_assoc('SELECT host_id, host FROM `' . $syslogdb_default . '`.`syslog_hosts`'), 'host_id', 'host');
		$facilities = array_rekey(syslog_db_fetch_assoc('SELECT facility_id, facility FROM `' . $syslogdb_default . '`.`syslog_facilities`'), 'facility_id', 'facility');
		$priorities = array_rekey(syslog_db_fetch_assoc('SELECT priority_id, priority FROM `' . $syslogdb_default . '`.`syslog_priorities`'), 'priority_id', 'priority');

		if (sizeof($syslog_messages)) {
			foreach ($syslog_messages as $syslog_message) {
				$title   = htmlspecialchars($syslog_message['message'], ENT_QUOTES);

				syslog_row_color($syslog_message['priority_id'], $syslog_message['message']);

				if (api_plugin_user_realm_auth('syslog_alerts.php')) {
					print "<td class='nowrap left' style='width:1%:padding:1px !important;'>";
					if ($syslog_message['mtype'] == 'main') {
						print "<a style='padding:1px' href='" . htmlspecialchars('syslog_alerts.php?id=' . $syslog_message[$syslog_incoming_config['id']] . '&action=newedit&type=0') . "'><img src='images/add.png'></a>
						<a style='padding:1px' href='" . htmlspecialchars('syslog_removal.php?id=' . $syslog_message[$syslog_incoming_config['id']] . '&action=newedit&type=new&type=0') . "'><img src='images/delete.png'></a>\n";
					}
					print "</td>\n";
				}
				print '<td class="left nowrap">' . $syslog_message['logtime'] . "</td>\n";
				print '<td class="left nowrap">' . $hosts[$syslog_message['host_id']] . "</td>\n";
				print '<td class="left nowrap">' . $syslog_message['program'] . "</td>\n";
				print '<td class="left syslogMessage">' . filter_value(title_trim($syslog_message[$syslog_incoming_config['textField']], get_request_var_request('trimval')), get_request_var('filter')) . "</td>\n";
				print '<td class="left nowrap">' . ucfirst($facilities[$syslog_message['facility_id']]) . "</td>\n";
				print '<td class="left nowrap">' . ucfirst($priorities[$syslog_message['priority_id']]) . "</td>\n";
			}
		}else{
			print "<tr><td class='center' colspan='7'><em>" . __('No Syslog Messages', 'syslog') . "</em></td></tr>";
		}

		html_end_box(false);

		if (sizeof($syslog_messages)) {
			print $nav;
		}

		syslog_syslog_legend();

		?>
		<script type='text/javascript'>
		$(function() {
			$('.syslogRow').tooltip({
				track: true,
				show: {
					effect: 'fade',
					duration: 250,
					delay: 125
				},
				position: { my: 'left+15 center', at: 'right center' }
			});

			$('button').tooltip({
				closed: true
			}).on('focus', function() {
				$('#filter').tooltip('close')
			}).on('click', function() {
				$(this).tooltip('close');
			});
		});
		</script>
		<?php
	}else{
		$nav = html_nav_bar("syslog.php?tab=$tab", MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 8, __('Alert Log Rows', 'syslog'), 'page', 'main');

		print $nav;

		$display_text = array(
			'name'        => array('display' => __('Alert Name', 'syslog'), 'sort' => 'ASC', 'align' => 'left'),
			'severity'    => array('display' => __('Severity', 'syslog'),   'sort' => 'ASC', 'align' => 'left'),
			'logtime'     => array('display' => __('Date', 'syslog'),       'sort' => 'ASC', 'align' => 'left'),
			'logmsg'      => array('display' => __('Message', 'syslog'),    'sort' => 'ASC', 'align' => 'left'),
			'count'       => array('display' => __('Count', 'syslog'),      'sort' => 'ASC', 'align' => 'right'),
			'host'        => array('display' => __('Device', 'syslog'),     'sort' => 'ASC', 'align' => 'right'),
			'facility_id' => array('display' => __('Facility', 'syslog'),   'sort' => 'ASC', 'align' => 'right'),
			'priority_id' => array('display' => __('Priority', 'syslog'),   'sort' => 'ASC', 'align' => 'right')
		);

		html_start_box('', '100%', '', '3', 'center', '');

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		if (sizeof($syslog_messages)) {
			foreach ($syslog_messages as $log) {
				$title   = htmlspecialchars($log['logmsg'], ENT_QUOTES);

				syslog_log_row_color($log['severity'], $title);

				print "<td class='left'><a class='linkEditMain' href='" . htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog.php?id=' . $log['seq'] . '&tab=current') . "'>" . (strlen($log['name']) ? $log['name']:__('Alert Removed', 'syslog')) . "</a></td>\n";
				print '<td class="left nowrap">' . (isset($severities[$log['severity']]) ? $severities[$log['severity']]:'Unknown') . "</td>\n";
				print '<td class="left nowrap">' . $log['logtime'] . "</td>\n";
				print '<td class="left syslogMessage">' . filter_value(title_trim($log['logmsg'], get_request_var_request('trimval')), get_request_var('filter')) . "</td>\n";
				print '<td class="right nowrap">' . $log['count'] . "</td>\n";
				print '<td class="right nowrap">' . $log['host'] . "</td>\n";
				print '<td class="right nowrap">' . ucfirst($log['facility']) . "</td>\n";
				print '<td class="right nowrap">' . ucfirst($log['priority']) . "</td>\n";
				print "</tr>\n";
			}
		}else{
			print "<tr><td colspan='11'><em>" . __('No Alert Log Messages', 'syslog') . "</em></td></tr>";
		}

		html_end_box(false);

		if (sizeof($syslog_messages)) {
			print $nav;
		}

		syslog_log_legend();
	}
}

function save_settings() {
	global $current_tab;

	syslog_request_validation($current_tab);

	if (sizeof($_REQUEST)) {
		foreach($_REQUEST as $var => $value) {
			switch($var) {
			case 'rows':
				set_user_setting('syslog_rows', get_request_var('rows'));
				break;
			case 'refresh':
				set_user_setting('syslog_refresh', get_request_var('refresh'));
				break;
			case 'removal':
				set_user_setting('syslog_removal', get_request_var('removal'));
				break;
			case 'trimval':
				set_user_setting('syslog_trimval', get_request_var('trimval'));
				break;
			case 'efacility':
				set_user_setting('syslog_efacility', get_request_var('efacility'));
				break;
			case 'epriority':
				set_user_setting('syslog_epriority', get_request_var('epriority'));
				break;
			case 'eprogram':
				set_user_setting('syslog_eprogram', get_request_var('eprogram'));
				break;
			}
		}
	}

	syslog_request_validation($current_tab, true);
}

function html_program_filter($program_id = '-1', $call_back = 'applyFilter', $sql_where = '') {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	if ($theme == 'classic') {
		?>
		<td>
			<select id='eprogram' name='eprogram' onChange='<?php print $call_back;?>'>
				<option value='-1'<?php if (get_request_var('eprogram') == '-1') {?> selected<?php }?>><?php print __('All Programs', 'syslog');?></option>
				<?php

				$programs = syslog_db_fetch_assoc('SELECT DISTINCT program_id, program
					FROM syslog_programs AS spr
					ORDER BY program');

				if (sizeof($programs)) {
					foreach ($programs as $program) {
						print "<option value='" . $program['program_id'] . "'"; if (get_request_var('eprogram') == $program['program_id']) { print ' selected'; } print '>' . title_trim(htmlspecialchars($program['program']), 40) . "</option>\n";
					}
				}
				?>
			</select>
		</td>
		<?php
	} else {
		if ($program_id > 0) {
			$program = syslog_db_fetch_cell("SELECT program FROM syslog_programs WHERE program_id = $program_id");
		} else {
			$program = __('All Programs', 'syslog');
		}

		?>
		<td>
			<span id='program_wrapper' style='width:200px;' class='ui-selectmenu-button ui-widget ui-state-default ui-corner-all'>
				<span id='program_click' class='ui-icon ui-icon-triangle-1-s'></span>
				<input size='28' id='program' value='<?php print $program;?>'>
			</span>
			<input type='hidden' id='eprogram' name='eprogram' value='<?php print $program_id;?>'>
			<input type='hidden' id='call_back' value='<?php print $call_back;?>'>
		</td>
		<script type='text/javascript'>
		$(function() {
			$('#program').unbind().autocomplete({
				source: pageName+'?action=ajax_programs',
				autoFocus: true,
				minLength: 0,
				select: function(event,ui) {
					$('#eprogram').val(ui.item.id);
					callBack = $('#call_back').val();
					if (callBack != 'undefined') {
						eval(callBack);
					}else{
						<?php print $call_back;?>;
					}
				}
			}).addClass('ui-state-default ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

			$('#program_click').css('z-index', '4');
			$('#program_wrapper').unbind().dblclick(function() {
				programOpen = false;
				clearTimeout(programTimer);
				clearTimeout(clickProgramTimeout);
				$('#program').autocomplete('close');
			}).click(function() {
				if (programOpen) {
					$('#program').autocomplete('close');
					clearTimeout(programTimer);
					programOpen = false;
				}else{
					clickProgramTimeout = setTimeout(function() {
						$('#program').autocomplete('search', '');
						clearTimeout(programTimer);
						programOpen = true;
					}, 200);
				}
			}).on('mouseenter', function() {
				$(this).addClass('ui-state-hover');
				$('input#program').addClass('ui-state-hover');
			}).on('mouseleave', function() {
				$(this).removeClass('ui-state-hover');
				$('#program').removeClass('ui-state-hover');
				programTimer = setTimeout(function() { $('#program').autocomplete('close'); }, 800);
			});

			var programPrefix = '';
			$('#program').autocomplete('widget').each(function() {
				programPrefix=$(this).attr('id');

				if (programPrefix != '') {
					$('ul[id="'+programPrefix+'"]').on('mouseenter', function() {
						clearTimeout(programTimer);
					}).on('mouseleave', function() {
						programTimer = setTimeout(function() { $('#program').autocomplete('close'); }, 800);
						$(this).removeClass('ui-state-hover');
						$('input#program').removeClass('ui-state-hover');
					});
				}
			});
		});
		</script>
	<?php
	}
}

function get_ajax_programs($include_any = true, $sql_where = '') {
	$return    = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . "program LIKE '%$term%'";
	}

	if (get_request_var('term') == '') {
		if ($include_any) {
			$return[] = array('label' => 'All Programs', 'value' => 'All Programs', 'id' => '-1');
		}
	}

	$programs = syslog_db_fetch_assoc("SELECT program_id, program FROM syslog_programs $sql_where ORDER BY program LIMIT 20");
	if (sizeof($programs)) {
		foreach($programs as $program) {
			$return[] = array('label' => $program['program'], 'value' => $program['program'], 'id' => $program['program_id']);
		}
	}

	print json_encode($return);
}

