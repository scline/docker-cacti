<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

$guest_account = true;
chdir('../../');
include('./include/auth.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');
ini_set('memory_limit', '256M');

$title = __('Device Tracking - Network Interfaces View', 'mactrack');

/* check actions */
if (isset_request_var('export')) {
	mactrack_export_records();
}else{
	mactrack_redirect();
	mactrack_view();
}

function mactrack_get_records(&$sql_where, $apply_limits = TRUE, $rows = '30') {
	global $timespan, $group_function, $summary_stats;

	$match = read_config_option('mt_ignorePorts', TRUE);
	if ($match == '') {
		$match = '(Vlan|Loopback|Null)';
		db_execute_prepared('REPLACE INTO settings SET name="mt_ignorePorts", value = ?', array($match));
	}
	$ignore = "(ifName NOT REGEXP '" . $match . "' AND ifDescr NOT REGEXP '" . $match . "')";

	/* issues sql where */
	if (get_request_var('issues') == '-2') { // All Interfaces
		/* do nothing all records */
	} elseif (get_request_var('issues') == '-3') { // Non Ignored Interfaces
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . $ignore;
	} elseif (get_request_var('issues') == '-4') { // Ignored Interfaces
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . ' NOT ' . $ignore;
	} elseif (get_request_var('issues') == '-1') { // With Issues
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . "((int_errors_present=1 OR int_discards_present=1) AND $ignore)";
	} elseif (get_request_var('issues') == '0') { // Up Interfaces
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . "(ifOperStatus=1 AND $ignore)";
	} elseif (get_request_var('issues') == '1') { // Up w/o Alias
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . "(ifOperStatus=1 AND ifAlias='' AND $ignore)";
	} elseif (get_request_var('issues') == '2') { // Errors Up
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . "(int_errors_present=1 AND $ignore)";
	} elseif (get_request_var('issues') == '3') { // Discards Up
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . "(int_discards_present=1 AND $ignore)";
	} elseif (get_request_var('issues') == '7') { // Change < 24 Hours
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . '(mac_track_interfaces.sysUptime-ifLastChange < 8640000) AND ifLastChange > 0 AND (mac_track_interfaces.sysUptime-ifLastChange > 0)';
	} elseif (get_request_var('issues') == '9' && get_filter_request_var('bwusage') != '-1') { // In/Out over 70%
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . '((inBound>' . get_request_var('bwusage') . ' OR outBound>' . get_request_var('bwusage') . ") AND $ignore)";
	} elseif (get_request_var('issues') == '10' && get_filter_request_var('bwusage') != '-1') { // In over 70%
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . '(inBound>' . get_request_var('bwusage') . " AND $ignore)";
	} elseif (get_request_var('issues') == '11' && get_filter_request_var('bwusage') != '-1') { // Out over 70%
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . '(outBound>' . get_request_var('bwusage') . " AND $ignore)";
	} else {

	}

	/* filter sql where */
	$filter_where = mactrack_create_sql_filter(get_request_var('filter'), array('ifAlias', 'hostname', 'ifName', 'ifDescr'));

	if (strlen($filter_where)) {
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . $filter_where;
	}

	/* device_id sql where */
	if (get_filter_request_var('device_id') == '-1') {
		/* do nothing all states */
	} else {
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . 'mac_track_interfaces.device_id=' . get_request_var('device_id');
	}

	/* site sql where */
	if (get_filter_request_var('site_id') == '-1') {
		/* do nothing all sites */
	} else {
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . 'mac_track_interfaces.site_id=' . get_request_var('site_id');
	}

	/* type sql where */
	if (get_filter_request_var('device_type_id') == '-1') {
		/* do nothing all states */
	} else {
		$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . 'mac_track_devices.device_type_id=' . get_request_var('device_type_id');
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	}else{
		$sql_limit = '';
	}

	$sql_query = "SELECT mac_track_interfaces.*,
		mac_track_device_types.description AS device_type,
		mac_track_devices.device_name,
		mac_track_devices.host_id,
		mac_track_devices.disabled,
		mac_track_devices.last_rundate
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		$sql_where
		$sql_order
		$sql_limit";

	//echo $sql_query;

	return db_fetch_assoc($sql_query);
}

function mactrack_interfaces_request_validation() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
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
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'device_name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'device_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'device_type_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'issues' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-2',
			'pageset' => true
			),
		'period' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-2',
			'pageset' => true
			),
		'bwusage' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('mt_interface_high'),
			'pageset' => true
			),
		'totals' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			),
	);

	validate_store_request_vars($filters, 'sess_mtv_int');
	/* ================= input validation ================= */
}

function mactrack_export_records() {
	mactrack_interfaces_request_validation();

	$sql_where  = '';

	$stats = mactrack_get_records($sql_where, TRUE, 10000);

	$xport_array = array();

	array_push($xport_array, '"device_name","device_type",' .
		'"sysUptime",' .
		'"ifIndex","ifName",' .
		'"ifAlias","ifDescr",' .
		'"ifType","ifMtu",' .
		'"ifSpeed","ifHighSpeed",' .
		'"ifPhysAddress","ifAdminStatus",' .
		'"ifOperStatus","ifLastChange",' .
		'"ifHCInOctets","ifHCOutOctets",' .
		'"ifInDiscards","ifInErrors",' .
		'"ifInUnknownProtos","ifOutDiscards",' .
		'"ifOutErrors","last_up_time",' .
		'"last_down_time","stateChanges",');

	if (sizeof($stats)) {
	foreach($stats as $stat) {
		array_push($xport_array,'"' .
			$stat['device_name']       . '","' . $stat["device_type"]       . '","' .
			$stat['sysUptime']         . '","' . $stat['ifIndex']           . '","' .
			$stat['ifName']            . '","' . $stat['ifAlias']           . '","' .
			$stat['ifDescr']           . '","' . $stat['ifType']            . '","' .
			$stat['ifMtu']             . '","' . $stat['ifSpeed']           . '","' .
			$stat['ifHighSpeed']       . '","' . $stat['ifPhysAddress']     . '","' .
			$stat['ifAdminStatus']     . '","' . $stat['ifOperStatus']      . '","' .
			$stat['ifLastChange']      . '","' . $stat['ifHCInOctets']      . '","' .
			$stat['ifHCOutOctets']     . '","' . $stat['ifInDiscards']      . '","' .
			$stat['ifInErrors']        . '","' . $stat['ifInUnknownProtos'] . '","' .
			$stat['ifOutDiscards']     . '","' . $stat['ifOutErrors']       . '","' .
			$stat['last_up_time']      . '","' . $stat['last_down_time']    . '","' .
			$stat['stateChanges']      . '"');
	}
	}

	header('Content-type: application/csv');
	header('Cache-Control: max-age=15');
	header('Content-Disposition: attachment; filename=device_mactrack_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view() {
	global $title, $mactrack_rows, $config;

	mactrack_interfaces_request_validation();

	general_header();

	$sql_where  = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 99999999;
	}else{
		$rows = get_request_var('rows');
	}

	$stats = mactrack_get_records($sql_where, TRUE, $rows);

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_filter_table();
	html_end_box();

	$rows_query_string = "SELECT COUNT(*)
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		$sql_where";

	$total_rows = db_fetch_cell($rows_query_string);

	$display_text = mactrack_display_array();

	$columns = sizeof($display_text);

	$nav = html_nav_bar('mactrack_view_interfaces.php?report=interfaces', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Interfaces', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (sizeof($stats)) {
		foreach ($stats as $stat) {
			/* find the background color and enclose it */
			$class = mactrack_int_row_class($stat);

			if ($class) {
				print "<tr id='row_" . $stat['device_id'] . '_' . $stat['ifName'] . "' class='$class'>\n"; $i++;
			}else{
				if (($i % 2) == 1) {
					$class = 'odd';
				}else{
					$class = 'even';
				}

				print "<tr id='row_" . $stat['device_id'] . "' class='$class'>\n"; $i++;
			}

			print mactrack_format_interface_row($stat);
		}
	}else{
		print '<tr><td colspan="7"><em>' . __('No Device Tracking Interfaces Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($stats)) {
		print $nav;
	}

	print '<div class="center" style="position:fixed;left:0;bottom:0;display:table;margin-left:auto;margin-right:auto;width:100%;">';

	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr>';
	mactrack_legend_row('int_up', __('Interface Up', 'mactrack'));
	mactrack_legend_row('int_up_wo_alias', __('No Alias', 'mactrack'));
	mactrack_legend_row('int_errors', __('Errors Present', 'mactrack'));
	mactrack_legend_row('int_discards', __('Discards Present', 'mactrack'));
	mactrack_legend_row('int_no_graph', __('No Graphs', 'mactrack'));
	mactrack_legend_row('int_down', __('Interface Down', 'mactrack'));
	print '</tr>';
	html_end_box(false);

	print '</div>';

	if (sizeof($stats)) {
		mactrack_display_stats();
	}

	print '<div id="response"></div>';

	bottom_footer();
}

function mactrack_display_array() {
	$display_text = array();
	$display_text += array('nosort'            => array(__('Actions', 'mactrack'), 'ASC'));
	$display_text += array('hostname'          => array(__('Hostname', 'mactrack'), 'ASC'));
	$display_text += array('device_type'       => array(__('Type', 'mactrack'), 'ASC'));
	$display_text += array('ifName'            => array(__('Name', 'mactrack'), 'ASC'));
	$display_text += array('ifDescr'           => array(__('Description', 'mactrack'), 'ASC'));
	$display_text += array('ifAlias'           => array(__('Alias', 'mactrack'), 'ASC'));
	$display_text += array('inBound'           => array(__('InBound %', 'mactrack'), 'DESC'));
	$display_text += array('outBound'          => array(__('OutBound %', 'mactrack'), 'DESC'));
	$display_text += array('int_ifHCInOctets'  => array(__('In (B/S)', 'mactrack'), 'DESC'));
	$display_text += array('int_ifHCOutOctets' => array(__('Out (B/S)', 'mactrack'), 'DESC'));

	if (get_request_var('totals') == 'true') {
		$display_text += array('ifInErrors'            => array(__('In Err Total', 'mactrack'), 'DESC'));
		$display_text += array('ifInDiscards'          => array(__('In Disc Total', 'mactrack'), 'DESC'));
		$display_text += array('ifInUnknownProtos'     => array(__('UProto Total', 'mactrack'), 'DESC'));
		$display_text += array('ifOutErrors'           => array(__('Out Err Total', 'mactrack'), 'DESC'));
		$display_text += array('ifOutDiscards'         => array(__('Out Disc Total', 'mactrack'), 'DESC'));
	}else{
		$display_text += array('int_ifInErrors'        => array(__('In Err (E/S)', 'mactrack'), 'DESC'));
		$display_text += array('int_ifInDiscards'      => array(__('In Disc (D/S)', 'mactrack'), 'DESC'));
		$display_text += array('int_ifInUnknownProtos' => array(__('UProto (UP/S)', 'mactrack'), 'DESC'));
		$display_text += array('int_ifOutErrors'       => array(__('Out Err (OE/S)', 'mactrack'), 'DESC'));
		$display_text += array('int_ifOutDiscards'     => array(__('Out Disc (OD/S)', 'mactrack'), 'DESC'));
	}

	$display_text += array('ifOperStatus' => array(__('Status', 'mactrack'), 'ASC'));
	$display_text += array('ifLastChange' => array(__('Last Change', 'mactrack'), 'ASC'));
	$display_text += array('last_rundate' => array(__('Last Scanned', 'mactrack'), 'ASC'));

	return $display_text;
}

function mactrack_filter_table() {
	global $config, $rows_selector;

	?>
	<tr class='even'>
		<td>
		<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT site_id, site_name FROM mac_track_sites ORDER BY site_name');
							if (sizeof($sites)) {
								foreach ($sites as $site) {
									print '<option value="' . $site['site_id'] .'"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Filters', 'mactrack');?>
					</td>
					<td>
						<select id='issues' onChange='applyFilter()'>
							<option value='-2'<?php if (get_request_var('issues') == '-2') {?> selected<?php }?>><?php print __('All Interfaces', 'mactrack');?></option>
							<option value='-3'<?php if (get_request_var('issues') == '-3') {?> selected<?php }?>><?php print __('All Non-Ignored Interfaces', 'mactrack');?></option>
							<option value='-4'<?php if (get_request_var('issues') == '-4') {?> selected<?php }?>><?php print __('All Ignored Interfaces', 'mactrack');?></option>
							<?php if (get_request_var('bwusage') != '-1') {?><option value='9'<?php if (get_request_var('issues') == '9' && get_request_var('bwusage') != '-1') {?> selected<?php }?>><?php print __('High In/Out Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack');?></option><?php }?>
							<?php if (get_request_var('bwusage') != '-1') {?><option value='10'<?php if (get_request_var('issues') == '10' && get_request_var('bwusage') != '-1') {?> selected<?php }?>><?php print __('High In Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack');?></option><?php }?>
							<?php if (get_request_var('bwusage') != '-1') {?><option value='11'<?php if (get_request_var('issues') == '11' && get_request_var('bwusage') != '-1') {?> selected<?php }?>><?php print __('High Out Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack');?></option><?php }?>
							<option value='-1'<?php if (get_request_var('issues') == '-1') {?> selected<?php }?>><?php print __('With Issues', 'mactrack');?></option>
							<option value='0'<?php if (get_request_var('issues') == '0') {?> selected<?php }?>><?php print __('Up Interfaces', 'mactrack');?></option>
							<option value='1'<?php if (get_request_var('issues') == '1') {?> selected<?php }?>><?php print __('Up Interfaces No Alias', 'mactrack');?></option>
							<option value='2'<?php if (get_request_var('issues') == '2') {?> selected<?php }?>><?php print __('Errors Accumulating', 'mactrack');?></option>
							<option value='3'<?php if (get_request_var('issues') == '3') {?> selected<?php }?>><?php print __('Discards Accumulating', 'mactrack');?></option>
							<option value='7'<?php if (get_request_var('issues') == '7') {?> selected<?php }?>><?php print __('Changed in Last Day', 'mactrack');?></option>
						</select><BR>
					<td>
						<?php print __('Bandwidth', 'mactrack');?>
					</td>
					<td>
						<select id='bwusage' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('bwusage') == '-1') {?> selected<?php }?>><?php print __('N/A', 'mactrack');?></option>
							<?php
							for ($bwpercent = 10; $bwpercent <100; $bwpercent+=10) {
								?><option value='<?php print $bwpercent; ?>' <?php if (isset_request_var('bwusage') and (get_request_var('bwusage') == $bwpercent)) {?> selected<?php }?>> >=<?php print $bwpercent; ?>%</option><?php
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' value='<?php print __esc('Go', 'mactrack');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'mactrack');?>'>
							<input type='button' id='export' value='<?php print __esc('Export', 'mactrack');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Type', 'mactrack');?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_type_id') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<?php
							$sql_where = '';
							if (get_request_var('site_id') != -1) {
								$sql_where .= ' WHERE mac_track_devices.site_id=' . get_request_var('site_id');
							}else{
								$sql_where  = '';
							}

							$types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id, 
								mac_track_device_types.description AS device_type
								FROM mac_track_device_types
								INNER JOIN mac_track_devices
								ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
								$sql_where
								ORDER BY device_type");

							if (sizeof($types)) {
								foreach ($types as $type) {
									print '<option value="' . $type['device_type_id'] .'"'; if (get_request_var('device_type_id') == $type['device_type_id']) { print ' selected'; } print '>' . $type['device_type'] . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Device', 'mactrack');?>
					</td>
					<td>
						<select id='device_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_id') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<?php
							$sql_where = '';
							if (get_request_var('site_id') != -1) {
								$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . 'site_id=' . get_request_var('site_id');
							}

							if (get_request_var('device_type_id') != '-1') {
								$sql_where .= (strlen($sql_where) ? ' AND ' : 'WHERE ') . 'device_type_id=' . get_request_var('device_type_id');
							}

							$devices = array_rekey(db_fetch_assoc("SELECT device_id, device_name FROM mac_track_devices $sql_where ORDER BY device_name"), "device_id", "device_name");
							if (sizeof($devices)) {
								foreach ($devices as $device_id => $device_name) {
									print '<option value="' . $device_id .'"'; if (get_request_var('device_id') == $device_id) { print " selected"; } print ">" . $device_name . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Interfaces', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($rows_selector)) {
								foreach ($rows_selector as $key => $value) {
									print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print 'selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'mactrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<input type='checkbox' id='totals' onChange='applyFilter()' <?php print (get_request_var('totals') == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='totals'><?php print __('Show Totals', 'mactrack');?></label>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?report=interfaces&header=false';
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&device_id=' + $('#device_id').val();
				strURL += '&issues=' + $('#issues').val();
				strURL += '&bwusage=' + $('#bwusage').val();
				strURL += '&device_type_id=' + $('#device_type_id').val();
				strURL += '&totals=' + $('#totals').is(':checked');
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?export=true';
				document.location = strURL;
			}

			$(function() {
				$('#mactrack').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#export').click(function() {
					exportRows();
				});
			});

			</script>
		</td>
	</tr><?php
}

