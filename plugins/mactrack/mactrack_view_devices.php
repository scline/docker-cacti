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
include_once('./include/global_arrays.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

$title = __('Device Tracking - Device Report View', 'macktrack');

if (isset_request_var('export')) {
	mactrack_view_export_devices();
}else{
	mactrack_redirect();
	general_header();
	mactrack_view_devices();
	bottom_footer();
}

function mactrack_device_request_validation() {
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
			'default' => 'site_name',
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
		'type_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'device_type_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'detail' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'false',
			'options' => array('options' => 'sanitize_search_string')
			),
	);

	validate_store_request_vars($filters, 'sess_mtv_devices');
	/* ================= input validation ================= */
}

function mactrack_view_export_devices() {
	mactrack_device_request_validation();

	$sql_where = '';

	$devices = mactrack_view_get_device_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, 'site_id, site_name, device_id, device_name, notes, ' .
		'hostname, snmp_readstring, snmp_readstrings, snmp_version, ' .
		'snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, ' .
		'snmp_priv_protocol, snmp_context, snmp_engine_id, ' .
		'snmp_port, snmp_timeout, snmp_retries, max_oids, snmp_sysName, snmp_sysLocation, ' .
		'snmp_sysContact, snmp_sysObjectID, snmp_sysDescr, snmp_sysUptime, ' .
		'ignorePorts, scan_type, disabled, ports_total, ports_active, ' .
		'ports_trunk, macs_active, last_rundate, last_runduration');

	if (sizeof($devices)) {
		foreach($devices as $device) {
			array_push($xport_array,'"'     .
			$device['site_id']              . '","' . $device['site_name']            . '","' .
			$device['device_id']            . '","' . $device['device_name']          . '","' .
			$device['notes']                . '","' . $device['hostname']             . '","' .
			$device['snmp_readstring']      . '","' . $device['snmp_readstrings']     . '","' .
			$device['snmp_version']         . '","' . $device['snmp_username']        . '","' .
			$device['snmp_password']        . '","' . $device['snmp_auth_protocol']   . '","' .
			$device['snmp_priv_passphrase'] . '","' . $device['snmp_priv_protocol']   . '","' .
			$device['snmp_context']         . '","' . $device['snmp_engine_id']       . '","' .
			$device['snmp_port']            . '","' . $device['snmp_timeout']         . '","' . 
			$device['snmp_retries']         . '","' . $device['max_oids']             . '","' . 
			$device['snmp_sysName']         . '","' . $device['snmp_sysLocation']     . '","' . 
			$device['snmp_sysContact']      . '","' . $device['snmp_sysObjectID']     . '","' . 
			$device['snmp_sysDescr']        . '","' . $device['snmp_sysUptime']       . '","' . 
			$device['ignorePorts']          . '","' . $device['scan_type']            . '","' . 
			$device['disabled']             . '","' . $device['ports_total']          . '","' . 
			$device['ports_active']         . '","' . $device['ports_trunk']          . '","' . 
			$device['macs_active']          . '","' . $device['last_rundate']         . '","' . 
			$device['last_runduration']     . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_device_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_device_records(&$sql_where, $rows, $apply_limits = TRUE) {
	$device_type_info = db_fetch_row_prepared('SELECT * FROM mac_track_device_types WHERE device_type_id = ?', array(get_request_var('device_type_id')));

	/* if the device type is not the same as the type_id, then reset it */
	if ((sizeof($device_type_info)) && (get_request_var('type_id') != -1)) {
		if ($device_type_info['device_type'] != get_request_var('type_id')) {
			$device_type_info = array();
		}
	}else{
		if (get_request_var('device_type_id') == 0) {
			$device_type_info = array('device_type_id' => 0, 'description' => __('Unknown Device Type', 'mactrack'));
		}
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . "(mac_track_devices.hostname LIKE '%" . get_request_var('filter') . "%' OR " .
			"mac_track_devices.notes LIKE '%" . get_request_var('filter') . "%' OR " .
			"mac_track_devices.device_name LIKE '%" . get_request_var('filter') . "%' OR " .
			"mac_track_sites.site_name LIKE '%" . get_request_var('filter') . "%')";
	}

	if (sizeof($device_type_info)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.device_type_id=' . $device_type_info['device_type_id'] . ')';
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	}elseif (get_request_var('status') == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.disabled="on")';
	}elseif (get_request_var('status') == '5') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.host_id=0)';
	}else {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.snmp_status=' . get_request_var('status') . ') AND (mac_track_devices.disabled = "")';
	}

	/* scan types matching */
	if (get_request_var('type_id') == '-1') {
		/* Show all items */
	}else {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.scan_type=' . get_request_var('type_id') . ')';
	}

	/* device types matching */
	if (get_request_var('device_type_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('device_type_id') == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_device_types.description="")';
	}else {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.device_type_id=' . get_request_var('device_type_id') . ')';
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('site_id') == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_sites.site_id IS NULL)';
	}elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.site_id=' . get_request_var('site_id') . ')';
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	}else{
		$sql_limit = '';
	}

	$sql_query = "SELECT
		mac_track_devices.*,
		mac_track_device_types.description AS device_type,
		mac_track_sites.site_name
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON (mac_track_devices.site_id=mac_track_sites.site_id)
		LEFT JOIN mac_track_device_types ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($sql_query);
}

function mactrack_view_devices() {
	global $title, $report, $mactrack_search_types, $mactrack_device_types, $rows_selector, $config, $item_rows;

	mactrack_device_request_validation();

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	$webroot = $config['url_path'] . '/plugins/mactrack/';

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_device_filter2();
	html_end_box();

	$sql_where = '';

	$devices = mactrack_view_get_device_records($sql_where, $rows);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_devices.device_id)
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices 
		ON mac_track_devices.site_id = mac_track_sites.site_id
		LEFT JOIN mac_track_device_types 
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		$sql_where");

	$display_text = array(
		'nosort'           => array(__('Actions', 'mactrack'), ''),
		'device_name'      => array(__('Device Name', 'mactrack'), 'ASC'),
		'site_name'        => array(__('Site Name', 'mactrack'), 'ASC'),
		'snmp_status'      => array(__('Status', 'mactrack'), 'ASC'),
		'hostname'         => array(__('Hostname', 'mactrack'), 'ASC'),
		'device_type'      => array(__('Device Type', 'mactrack'), 'ASC'),
		'ips_total'        => array(__('Total IP\'s', 'mactrack'), 'DESC'),
		'ports_total'      => array(__('User Ports', 'mactrack'), 'DESC'),
		'ports_active'     => array(__('User Ports Up', 'mactrack'), 'DESC'),
		'ports_trunk'      => array(__('Trunk Ports', 'mactrack'), 'DESC'),
		'macs_active'      => array(__('Active Macs', 'mactrack'), 'DESC'),
		'vlans_total'      => array(__('Total VLAN\'s', 'mactrack'), 'DESC'),
		'last_runduration' => array(__('Last Duration', 'mactrack'), 'DESC')
	);

	$columns = sizeof($display_text);

	$nav = html_nav_bar('mactrack_view_devices.php?report=devices', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Devices', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (sizeof($devices) > 0) {
		foreach ($devices as $device) {
			$hostinfo['hostname'] = $device['hostname'];
			$hostinfo['user']     = $device['user_name'];
			switch($device['term_type']) {
			case 0:
				$hostinfo['transport'] = 'none';
				break;
			case 1:
				$hostinfo['transport'] = 'telnet';
				break;
			case 2:
				$hostinfo['transport'] = 'ssh';
				break;
			case 3:
				$hostinfo['transport'] = 'http';
				break;
			case 4:
				$hostinfo['transport'] = 'https';
				break;
			}

			form_alternate_row();
				?>
				<td style='width:1px;'>
					<?php if (api_user_realm_auth('mactrack_sites.php')) {?>
					<a href='<?php print htmlspecialchars($webroot . 'mactrack_devices.php?action=edit&device_id=' . $device['device_id']);?>' title='<?php print __esc('Edit Device', 'mactrack');?>'><img src='<?php print $webroot;?>images/edit_object.png'></a>
					<?php api_plugin_hook_function('remote_link', $hostinfo); } ?>
					<?php if ($device['host_id'] > 0) {?>
					<a href='<?php print htmlspecialchars($webroot . 'mactrack_view_graphs.php?action=preview&report=graphs&style=selective&graph_list=&host_id=' . $device['host_id'] . '&graph_template_id=0&filter=');?>' title='<?php print __esc('View Graphs', 'mactrack');?>'><img src='<?php print $webroot;?>images/view_graphs.gif'></a>
					<?php }else{?>
					<img title='<?php print __esc('Device Not Mapped to Cacti Device', 'mactrack');?>' src='<?php print $webroot;?>images/view_graphs_disabled.gif'>
					<?php }?>
					<a href='<?php print htmlspecialchars($webroot . 'mactrack_view_macs.php?report=macs&reset&device_id=-1&scan_date=3&site_id=' . get_request_var('site_id') . '&device_id=' . $device['device_id']);?>' title='<?php print __esc('View MAC Addresses', 'mactrack');?>'><img src='<?php print $webroot;?>images/view_macs.gif'></a>
					<a href='<?php print htmlspecialchars($webroot . 'mactrack_view_interfaces.php?report=interfaces&reset&site=' . get_request_var('site_id') . '&device=' . $device['device_id']);?>' title='<?php print __esc('View Interfaces', 'mactrack');?>'><img src='<?php print $webroot;?>images/view_interfaces.gif'></a>
				</td>
				<td class='hyperLink'>
					<?php print filter_value($device['device_name'], get_request_var('filter'));?>
				</td>
				<td><?php print filter_value($device['site_name'], get_request_var('filter'));?>
				<td><?php print get_colored_device_status(($device['disabled'] == 'on' ? true : false), $device['snmp_status']);?></td>
				<td><?php print filter_value($device['hostname'], get_request_var('filter'));?>
				<td><?php print $device['device_type'];?></td)>
				<td><?php print ($device['scan_type'] == '1' ? __('N/A', 'mactrack') : number_format_i18n($device['ips_total']));?></td>
				<td><?php print ($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_total']));?></td>
				<td><?php print ($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_active']));?></td>
				<td><?php print ($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_trunk']));?></td>
				<td><?php print ($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['macs_active']));?></td>
				<td><?php print ($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['vlans_total']));?></td>
				<td><?php print number_format($device['last_runduration'], 1);?></td>
			</tr>
			<?php
		}
	}else{
		print '<tr><td colspan="10"><em>' . __('No Device Tracking Devices Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($devices)) {
		print $nav;
		mactrack_display_stats();
	}
}

function mactrack_device_filter2() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'mactrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<option value='-2'<?php if (get_request_var('site_id') == '-2') {?> selected<?php }?>><?php print __('None', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT site_id, site_name FROM mac_track_sites ORDER BY site_name');
							if (sizeof($sites)) {
								foreach ($sites as $site) {
									print '<option value="' . $site['site_id'] . '"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
								}
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
						<select id='type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<option value='1'<?php if (get_request_var('type_id') == '1') {?> selected<?php }?>><?php print __('Hub/Switch', 'mactrack');?></option>
							<option value='2'<?php if (get_request_var('type_id') == '2') {?> selected<?php }?>><?php print __('Switch/Router', 'mactrack');?></option>
							<option value='3'<?php if (get_request_var('type_id') == '3') {?> selected<?php }?>><?php print __('Router', 'mactrack');?></option>
						</select>
					</td>
					<td>
						<?php print __('SubType', 'mactrack');?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<option value='-2'<?php if (get_request_var('device_type_id') == '-2') {?> selected<?php }?>><?php print __('Not Detected', 'mactrack');?></option>
							<?php
							if (get_request_var('type_id') != -1) {
								$device_types = db_fetch_assoc_prepared('SELECT DISTINCT
									mac_track_devices.device_type_id,
									mac_track_device_types.description,
									mac_track_device_types.sysDescr_match
									FROM mac_track_device_types
									INNER JOIN mac_track_devices 
									ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
									WHERE device_type = ?
									ORDER BY mac_track_device_types.description', array(get_request_var('type_id')));
							}else{
								$device_types = db_fetch_assoc('SELECT DISTINCT
									mac_track_devices.device_type_id,
									mac_track_device_types.description,
									mac_track_device_types.sysDescr_match
									FROM mac_track_device_types
									INNER JOIN mac_track_devices 
									ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
									ORDER BY mac_track_device_types.description');
							}

							if (sizeof($device_types)) {
								foreach ($device_types as $device_type) {
									$display_text = $device_type['description'] . ' (' . $device_type['sysDescr_match'] . ')';
									print '<option value="' . $device_type['device_type_id'] . '"'; if (get_request_var('device_type_id') == $device_type['device_type_id']) { print ' selected'; } print '>' . $display_text . '</option>';
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
						<?php print __('Status', 'mactrack');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>><?php print __('Up', 'mactrack');?></option>
							<option value='-2'<?php if (get_request_var('status') == '-2') {?> selected<?php }?>><?php print __('Disabled', 'mactrack');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Down', 'mactrack');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Unknown', 'mactrack');?></option>
							<option value='4'<?php if (get_request_var('status') == '4') {?> selected<?php }?>><?php print __('Error', 'mactrack');?></option>
							<option value='5'<?php if (get_request_var('status') == '5') {?> selected<?php }?>><?php print __('No Cacti Link', 'mactrack');?></option>
						</select>
					</td>
					<td>
						<?php print __('Devices', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'mactrack');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='report' value='devices'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_devices.php?report=devices&header=false';
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&status=' + $('#status').val();
				strURL += '&type_id=' + $('#type_id').val();
				strURL += '&device_type_id=' + $('#device_type_id').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_devices.php?report=devices&header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_devices.php?report=devices&export=true';
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
	</tr>
	<?php
}

