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
chdir('../../');
include('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');
include_once('./plugins/mactrack/mactrack_actions.php');

$device_actions = array(
	1 => __('Delete', 'mactrack'),
	2 => __('Enable', 'mactrack'),
	3 => __('Disable', 'mactrack'),
	4 => __('Change SNMP Options', 'mactrack'),
	5 => __('Change Device Port Values', 'mactrack'),
	6 => __('Connect to Cacti Host via Hostname', 'mactrack'),
	7 => __('Copy SNMP Settings from Cacti Host', 'mactrack')
);

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_mactrack_save();

		break;
	case 'actions':
		form_mactrack_actions();

		break;
	case 'edit':
		top_header();
		mactrack_device_edit();
		bottom_footer();

		break;
	case 'import':
		top_header();
		mactrack_device_import();
		bottom_footer();

		break;
	default:
		if (isset_request_var('import')) {
			header('Location: mactrack_devices.php?action=import');
		}elseif (isset_request_var('export')) {
			mactrack_device_export();
		}else{
			top_header();
			mactrack_device();
			bottom_footer();
		}

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_mactrack_save() {
	global $config;

	if ((isset_request_var('save_component_device')) && (isempty_request_var('add_dq_y'))) {
		$device_id = api_mactrack_device_save(get_nfilter_request_var('device_id'), get_nfilter_request_var('host_id'), 
			get_nfilter_request_var('site_id'), get_nfilter_request_var('hostname'), get_nfilter_request_var('device_name'), 
			get_nfilter_request_var('scan_type'), get_nfilter_request_var('snmp_options'), get_nfilter_request_var('snmp_readstring'),
			get_nfilter_request_var('snmp_version'), get_nfilter_request_var('snmp_username'), get_nfilter_request_var('snmp_password'), 
			get_nfilter_request_var('snmp_auth_protocol'), get_nfilter_request_var('snmp_priv_passphrase'), 
			get_nfilter_request_var('snmp_priv_protocol'), get_nfilter_request_var('snmp_context'),
			get_nfilter_request_var('snmp_engine_id'), get_nfilter_request_var('snmp_port'), 
			get_nfilter_request_var('snmp_timeout'), get_nfilter_request_var('snmp_retries'), 
			get_nfilter_request_var('max_oids'), get_nfilter_request_var('ignorePorts'), get_nfilter_request_var('notes'), 
			get_nfilter_request_var('user_name'), get_nfilter_request_var('user_password'), get_nfilter_request_var('term_type'), 
			get_nfilter_request_var('private_key_path'), (isset_request_var('disabled') ? get_nfilter_request_var('disabled') : ''));

		header('Location: mactrack_devices.php?action=edit&device_id=' . (empty($device_id) ? get_filter_request_var('device_id') : $device_id));
	}

	if (isset_request_var('save_component_import')) {
		if (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
			/* file upload */
			$csv_data = file($_FILES['import_file']['tmp_name']);

			/* obtain debug information if it's set */
			$debug_data = mactrack_device_import_processor($csv_data);
			if(sizeof($debug_data) > 0) {
				$_SESSION['import_debug_info'] = $debug_data;
			}
		}else{
			header('Location: mactrack_devices.php?action=import'); exit;
		}

		header('Location: mactrack_devices.php?action=import');
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_mactrack_actions() {
	global $config, $device_actions, $fields_mactrack_device_edit, $fields_mactrack_snmp_item;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	include_once($config['base_path'] . '/lib/functions.php');

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
			if (get_request_var('drp_action') == '2') { /* Enable Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE mac_track_devices SET disabled='' WHERE device_id = ?", array($selected_items[$i]));
				}
			}elseif (get_request_var('drp_action') == '3') { /* Disable Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE mac_track_devices SET disabled='on' WHERE device_id = ?", array($selected_items[$i]));
				}
			}elseif (get_request_var('drp_action') == '4') { /* change snmp options */
				for ($i=0;($i<count($selected_items));$i++) {
					reset($fields_mactrack_device_edit);
					while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
						if (isset_request_var("t_$field_name")) {
							db_execute_prepared("UPDATE mac_track_devices 
								SET $field_name = ?
								WHERE device_id = ?",
								array(get_request_var($field_name), $selected_items[$i]));
						}
					}
				}
			}elseif (get_request_var('drp_action') == '5') { /* change port settings for multiple devices */
				for ($i=0;($i<count($selected_items));$i++) {
					reset($fields_mactrack_device_edit);
					while (list($field_name, $field_array) = each($fields_host_edit)) {
						if (isset_request_var("t_$field_name")) {
							db_execute_prepared("UPDATE mac_track_devices 
								SET $field_name = ? WHERE id = ?", 
								array(get_request_var($field_name), $selected_items[$i]));
						}
					}
				}
			}elseif (get_request_var('drp_action') == '6') { /* Connect Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					$cacti_host = db_fetch_row_prepared('SELECT host.id, host.description FROM mac_track_devices 
						LEFT JOIN host ON (mac_track_devices.hostname=host.hostname) 
						WHERE mac_track_devices.device_id=?', array($selected_items[$i]));

					if (sizeof($cacti_host)) {
						db_execute_prepared('UPDATE mac_track_devices SET host_id = ?, device_name = ? WHERE device_id = ?', 
							array($cacti_host['id'],  $cacti_host['description'], $selected_items[$i]));
					}
				}
			}elseif (get_request_var('drp_action') == '7') { /* Copy SNMP Settings */
				for ($i=0;($i<count($selected_items));$i++) {
					$cacti_host = db_fetch_row_prepared("SELECT host.*, 
						host.snmp_community as snmp_readstring, 
						host.ping_retries as snmp_retries
						FROM mac_track_devices 
						LEFT JOIN host ON (mac_track_devices.hostname=host.hostname) 
						WHERE mac_track_devices.device_id = ?", array($selected_items[$i]));

					if (isset($cacti_host['id'])) {
						reset($fields_mactrack_snmp_item);
						$updates = '';
						while (list($field_name, $field_array) = each($fields_mactrack_snmp_item)) {
							if (isset($cacti_host[$field_name])) {
								$updates .= ($updates != '' ? ', ' : '') . $field_name . "='" . $cacti_host[$field_name] . "'";
							}
						}

						if (strlen($updates)) {
							db_execute('UPDATE mac_track_devices SET ' . $updates .	' WHERE device_id=' . $selected_items[$i]);
						}
					} else {
						# skip silently; possible enhacement: tell the user what we did
					}
				}
			}elseif (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_mactrack_device_remove($selected_items[$i]);
				}
			}

			header('Location: mactrack_devices.php');
			exit;
		}
	}

	/* setup some variables */
	$device_list = ''; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_info = db_fetch_row_prepared('SELECT hostname, device_name FROM mac_track_devices WHERE device_id = ?', array($matches[1]));
			$device_list .= '<li>' . $device_info['device_name'] . ' (' . $device_info['hostname'] . ')</li>';
			$device_array[] = $matches[1];
		}
	}

	top_header();

	form_start('mactrack_devices.php');

	html_start_box($device_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (!sizeof($device_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one device.', 'mactrack') . "</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='" . __esc('Continue', 'mactrack') . "' name='save'>";

		if (get_request_var('drp_action') == '2') { /* Enable Devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to enable the following devices.', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";
		}elseif (get_request_var('drp_action') == '3') { /* Disable Devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to disable the following devices.', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";
		}elseif (get_request_var('drp_action') == '4') { /* change snmp options */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to change SNMP parameters for the following devices, check the box next to the fields you want to update, and fill in the new value.', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";

			$form_array = array();
			while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
				if (preg_match('/^snmp_/', $field_name)) {
					$form_array += array($field_name => $fields_mactrack_device_edit[$field_name]);
	
					$form_array[$field_name]['value'] = '';
					$form_array[$field_name]['device_name'] = '';
					$form_array[$field_name]['form_id'] = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name' => 't_' . $field_name,
						'friendly_name' => 'Update this Field<br/>',
						'value' => ''
						);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);
		}elseif (get_request_var('drp_action') == '5') { /* change port settngs for multiple devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to change upper or lower port parameters for the following devices, check the box next to the fields you want to update, and fill in the new value.') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";

			$form_array = array();
			while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
				if (preg_match('/^port_/', $field_name)) {
					$form_array += array($field_name => $fields_mactrack_device_edit[$field_name]);

					$form_array[$field_name]['value'] = '';
					$form_array[$field_name]['device_name'] = '';
					$form_array[$field_name]['form_id'] = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name' => 't_' . $field_name,
						'friendly_name' => 'Update this Field',
						'value' => ''
						);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);
		}elseif (get_request_var('drp_action') == '6') { /* Connect Devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to connect the following devices to their respective Cacti Device.  The relation will be built on equal hostnames. Description will be updated as well.', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";
		}elseif (get_request_var('drp_action') == '7') { /* Copy SNMP Settings */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to copy SNMP Settings from connected Cacti Device to Device Tracking Device.  All not connected Devices will silently be skipped. SNMP retries will be taken from Ping retries.', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";
		}elseif (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Devices', 'mactrack') . "</p>
					<ul>$device_list</ul>
				</td>
			</tr>";
		}
	}

	print "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' name='cancel' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'mactrack') . "'>
			$save_html" : "<input type='button' onClick='cactiReturnTo()' name='cancel' value='" . __esc('Return', 'mactrack') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Mactrack Device Functions
   --------------------- */

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

	validate_store_request_vars($filters, 'sess_mt_devices');
	/* ================= input validation ================= */
}

function mactrack_device_export() {
	mactrack_device_request_validation();

	$sql_where = '';

	$devices = mactrack_get_devices($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, 'site_id, site_name, device_id, device_name, notes, ' .
		'hostname, snmp_options, snmp_readstring, snmp_version, ' .
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
			$device['snmp_options']         . '","' . $device['snmp_readstring']      . '","' .
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

	if (sizeof($xport_array)) {
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
	}
}

function mactrack_device_import() {
	global $config;

	?><form method='post' action='mactrack_devices.php?action=import' enctype='multipart/form-data'><?php

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results', 'mactrack'), '100%', '', '3', 'center', '');

		print "<tr class='even'><td><p class='textArea'>" . __('Cacti has imported the following items:', 'mactrack') . '</p>';

		if (sizeof($_SESSION['import_debug_info'])) {
			foreach($_SESSION['import_debug_info'] as $import_result) {
				print "<tr class='even'><td>" . $import_result . '</td>';
				print '</tr>';
			}
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Device Tracking Devices', 'mactrack'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'><?php print __('Import Devices from Local File', 'mactrack');?></font><br>
			<?php print __('Please specify the location of the CSV file containing your device information.', 'mactrack');?>
		</td>
		<td align='left'>
			<input type='file' name='import_file'>
		</td>
	</tr><?php
	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'><?php print __('Overwrite Existing Data?', 'mactrack');?></font><br>
			<?php print __('Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old row, only replace duplicate rows.', 'mactrack');?>
		</td>
		<td align='left'>
			<input type='checkbox' name='allow_update' id='allow_update'><?php print __('Allow Existing Rows to be Updated?', 'mactrack');?>
		</td><?php

	html_end_box(FALSE);

	html_start_box(__('Required File Format Notes', 'mactrack'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td><strong><?php print __('The file must contain a header row with the following column headings.', 'mactrack');?></strong>
			<br><br>
			<strong>site_id</strong><?php print __(' - The SiteID known to Device Tracking for this device', 'mactrack');?><br>
			<strong>device_name</strong><?php print __(' - A simple name for the device.  For example Cisco 6509 Switch', 'mactrack');?><br>
			<strong>hostname</strong><?php print __(' - The IP Address or DNS Name for the device', 'mactrack');?><br>
			<strong>notes</strong><?php print __(' - More detailed information about the device, including location, environmental conditions, etc.', 'mactrack');?><br>
			<strong>ignorePorts</strong><?php print __(' - A list of ports that should not be scanned for user devices', 'mactrack');?><br>
			<strong>scan_type</strong><?php print __(' - Redundant information indicating the intended device type.  See below for valid values.', 'mactrack');?><br>
			<strong>snmp_options</strong><?php print __(' - Id of a set of SNMP options', 'mactrack');?><br>
			<strong>snmp_readstring</strong><?php print __(' - The current snmp read string for the device', 'mactrack');?><br>
			<strong>snmp_version</strong><?php print __(' - The snmp version you wish to scan this device with.  Valid values are 1, 2 and 3', 'mactrack');?><br>
			<strong>snmp_port</strong><?php print __(' - The UDP port that the snmp agent is running on', 'mactrack');?><br>
			<strong>snmp_timeout</strong><?php print __(' - The timeout in milliseconds to wait for an snmp response before trying again', 'mactrack');?><br>
			<strong>snmp_retries</strong><?php print __(' - The number of times to retry a snmp request before giving up', 'mactrack');?><br>
			<strong>max_oids</strong><?php print __(' - Specified the number of OID\'s that can be obtained in a single SNMP Get request', 'mactrack');?><br>
			<strong>snmp_username</strong><?php print __(' - SNMP V3: SNMP username', 'mactrack');?><br>
			<strong>snmp_password</strong><?php print __(' - SNMP V3: SNMP password', 'mactrack');?><br>
			<strong>snmp_auth_protocol</strong><?php print __(' - SNMP V3: SNMP authentication protocol', 'matrack');?><br>
			<strong>snmp_priv_passphrase</strong><?php print __(' - SNMP V3: SNMP privacy passphrase', 'mactrack');?><br>
			<strong>snmp_priv_protocol</strong><?php print __(' - SNMP V3: SNMP privacy protocol', 'mactrack');?><br>
			<strong>snmp_context</strong><?php print __(' - SNMP V3: SNMP context', 'mactrack');?><br>
			<strong>snmp_engine_id</strong><?php print __(' - SNMP V3: SNMP engine id', 'mactrack');?><br>
			<br>
			<strong><?php print __('The primary key for this table is a combination of the following three fields:', 'mactrack');?></strong>
			<br><br>
			site_id, hostname, snmp_port
			<br><br>
			<strong><?php print __('Therefore, if you attempt to import duplicate devices, only the data you specify will be updated.', 'mactrack');?></strong>
			<br><br>
			<strong>scan_type</strong><?php print __(' is an integer field and must be one of the following:', 'mactrack');?>
			<br><br>
			<?php print __('1 - Switch/Hub', 'mactrack');?><br>
			<?php print __('2 - Switch/Router', 'mactrack');?><br>
			<?php print __('3 - Router', 'mactrack');?><br>
			<br>
		</td>
	</tr><?php

	form_hidden_box('save_component_import','1','');

	html_end_box();

	form_save_button('return', 'import');
}

function mactrack_device_import_processor(&$devices) {
	$i = 0;
	$return_array = array();

	if (sizeof($devices)) {
	foreach($devices as $device_line) {
		/* parse line */
		$line_array = explode(',', $device_line);

		/* header row */
		if ($i == 0) {
			$save_order = '(';
			$j = 0;
			$first_column = TRUE;
			$required = 0;
			$save_site_id_id = -1;
			$save_snmp_port_id = -1;
			$save_host_id = -1;
			$save_device_name_id = -1;
			$update_suffix = '';

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				$line_item = trim(str_replace("'", '', $line_item));
				$line_item = trim(str_replace('"', '', $line_item));

				switch ($line_item) {
					case 'snmp_options':
					case 'snmp_readstring':
					case 'snmp_timeout':
					case 'snmp_retries':
					case 'ignorePorts':
					case 'scan_type':
					case 'snmp_version':
					case 'snmp_username':
					case 'snmp_password':
					case 'snmp_auth_protocol':
					case 'snmp_priv_passphrase':
					case 'snmp_priv_protocol':
					case 'snmp_context':
					case 'snmp_engine_id':
					case 'max_oids':
					case 'notes':
					case 'disabled':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'snmp_port':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$save_snmp_port_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'site_id':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$save_site_id_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'hostname':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$save_host_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'device_name':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$save_device_name_id = $j;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					default:
						/* ignore unknown columns */
				}

				$j++;

			}
			}

			$save_order .= ')';

			if ($required >= 3) {
				array_push($return_array, __('HEADER LINE PROCESSED OK: <br>Columns found where: %s', $save_order, 'mactrack') . '<br>');
			}else{
				array_push($return_array, __('HEADER LINE PROCESSING ERROR: Missing required field <br>Columns found where: %s', $save_order, 'mactrack') . '<br>');
				break;
			}
		}else{
			$save_value = '(';
			$j = 0;
			$first_column = TRUE;
			$sql_where = '';

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				if (in_array($j, $insert_columns)) {
					$line_item = trim(str_replace("'", '', $line_item));
					$line_item = trim(str_replace('"', '', $line_item));

					if (!$first_column) {
						$save_value .= ',';
					}else{
						$first_column = FALSE;
					}

					if ($j == $save_site_id_id || $j == $save_snmp_port_id || $j == $save_host_id ) {
						if (strlen($sql_where)) {
							switch($j) {
							case $save_site_id_id:
								$sql_where .= " AND site_id='$line_item'";
								break;
							case $save_snmp_port_id:
								$sql_where .= " AND snmp_port='$line_item'";
								break;
							case $save_host_id:
								$sql_where .= " AND hostname='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}else{
							switch($j) {
							case $save_site_id_id:
								$sql_where .= "WHERE site_id='$line_item'";
								break;
							case $save_snmp_port_id:
								$sql_where .= "WHERE snmp_port='$line_item'";
								break;
							case $save_host_id:
								$sql_where .= "WHERE hostname='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}
					}

					if ($j == $save_snmp_port_id) {
						$snmp_port = $line_item;
					}

					if ($j == $save_site_id_id) {
						$site_id = $line_item;
					}

					if ($j == $save_host_id) {
						$hostname = $line_item;
					}

					if ($j == $save_device_name_id) {
						$device_name = $line_item;
					}

					$save_value .= "'" . $line_item . "'";
				}

				$j++;
			}
			}

			$save_value .= ')';

			if ($j > 0) {
				if (isset_request_var('allow_update')) {
					$sql_execute = 'INSERT INTO mac_track_devices ' . $save_order .
						' VALUES' . $save_value . $update_suffix;

					if (db_execute($sql_execute)) {
						array_push($return_array, __('INSERT SUCCEEDED: Hostname: SiteID: %s, Device Name: %s, Hostname %s, SNMP Port: %s', $site_id, $device_name, $hostname, $snmp_port, 'mactrack'));
					}else{
						array_push($return_array, __('INSERT FAILED: SiteID: %s, Device Name: %s, Hostname %s, SNMP Port: %s', $site_id, $device_name, $hostname, $snmp_port, 'mactrack'));
					}
				}else{
					/* perform check to see if the row exists */
					$existing_row = db_fetch_row("SELECT * FROM mac_track_devices $sql_where");

					if (sizeof($existing_row)) {
						array_push($return_array, __('INSERT SKIPPED, EXISTING: SiteID: %s, Device Name: %s, Hostname %s, SNMP Port: %s', $site_id, $device_name, $hostname, $snmp_port, 'mactrack'));
					}else{
						$sql_execute = "INSERT INTO mac_track_devices " . $save_order .
							" VALUES" . $save_value;

						if (db_execute($sql_execute)) {
							array_push($return_array, __('INSERT SUCCEEDED: Hostname: SiteID: %s, Device Name: %s, Hostname %s, SNMP Port: %s', $site_id, $device_name, $hostname, $snmp_port, 'mactrack'));
						}else{
							array_push($return_array, __('INSERT FAILED: SiteID: %s, Device Name: %s, Hostname %s, SNMP Port: %s', $site_id, $device_name, $hostname, $snmp_port, 'mactrack'));
						}
					}
				}
			}
		}

		$i++;
	}
	}

	return $return_array;
}

function mactrack_device_edit() {
	global $config, $fields_mactrack_device_edit;

	/* ================= input validation ================= */
	get_filter_request_var('device_id');
	/* ==================================================== */

	if (!isempty_request_var('device_id')) {
		$device = db_fetch_row_prepared('SELECT * 
			FROM mac_track_devices 
			WHERE device_id = ?', 
			array(get_request_var('device_id')));

		$header_label = __('Device Tracking Devices [edit: %s]', $device['device_name'], 'mactrack');
	}else{
		$device = array();

		$header_label = __('Device Tracking Devices [new]', 'mactrack');
	}

	if (!empty($device['device_id'])) {
		?>
		<table width='100%' align='center'>
			<tr>
				<td class='textInfo' colspan='2'>
					<?php print $device['device_name'];?> (<?php print $device['hostname'];?>)
				</td>
			</tr>
			<tr>
				<td class='textHeader'>
					<?php print __('SNMP Information', 'mactrack');?><br>

					<span style='font-size: 10px; font-weight: normal; font-family: monospace;'>
					<?php
					/* force php to return numeric oid's */
					cacti_oid_numeric_format();

					$snmp_system = cacti_snmp_get($device['hostname'], $device['snmp_readstring'], '.1.3.6.1.2.1.1.1.0', $device['snmp_version'], $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'], SNMP_WEBUI);

					if ($snmp_system == '') {
						print "<span style='color: #ff0000; font-weight: bold;'>SNMP error</span>\n";
					}else{
						$snmp_uptime = cacti_snmp_get($device['hostname'], $device['snmp_readstring'], '.1.3.6.1.2.1.1.3.0', $device['snmp_version'], $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'], SNMP_WEBUI);
						$snmp_hostname = cacti_snmp_get($device['hostname'], $device['snmp_readstring'], '.1.3.6.1.2.1.1.5.0', $device['snmp_version'], $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'], SNMP_WEBUI);
						$snmp_objid = cacti_snmp_get($device['hostname'], $device['snmp_readstring'], '.1.3.6.1.2.1.1.2.0', $device['snmp_version'], $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'], SNMP_WEBUI);

						$snmp_objid = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_objid);
						$snmp_objid = str_replace('OID: ', '', $snmp_objid);
						$snmp_objid = str_replace('.iso', '.1', $snmp_objid);

						print '<strong>' . __('System:', 'mactrack') . "</strong> $snmp_system<br>\n";
						print '<strong>' . __('Uptime:', 'mactrack') . "</strong> $snmp_uptime<br>\n";
						print '<strong>' . __('Hostname:', 'mactrack') . "</strong> $snmp_hostname<br>\n";
						print '<strong>' . __('ObjectID:', 'mactrack') . "</strong> $snmp_objid<br>\n";
					}
					?>
					</span>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

	form_start('mactrack_devices.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	/* preserve the devices site id between refreshes via a GET variable */
	if (!isempty_request_var('site_id')) {
		$fields_host_edit['site_id']['value'] = get_request_var('site_id');
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tab' => true),
			'fields' => inject_form_variables($fields_mactrack_device_edit, (isset($device) ? $device : array()))
		)
	);

	html_end_box();

	form_save_button('mactrack_devices.php', 'return', 'device_id');
}

function mactrack_get_devices(&$sql_where, $rows, $apply_limits = TRUE) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = (strlen($sql_where) ? ' AND ': 'WHERE ') . "(mtd.hostname like '%" . get_request_var('filter') . "%'
			OR mtd.device_name like '%" . get_request_var('filter') . "%'
			OR mtd.notes like '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	}elseif (get_request_var('status') == '-2') {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . "(mtd.disabled='on')";
	}elseif (get_request_var('status') == '5') {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mtd.host_id=0)';
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mtd.snmp_status=' . get_request_var('status') . " AND mtd.disabled = '')";
	}

	if (get_request_var('type_id') == '-1') {
		/* Show all items */
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mtd.scan_type=' . get_request_var('type_id') . ')';
	}

	if (get_request_var('device_type_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('device_type_id') == '-2') {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . "(mtdt.description='')";
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mtd.device_type_id=' . get_request_var('device_type_id') . ')';
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('site_id') == '-2') {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mts.site_id IS NULL)';
	}elseif (!isempty_request_var('site_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND ': 'WHERE ') . '(mtd.site_id=' . get_request_var('site_id') . ')';
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	}else{
		$sql_limit = '';
	}

	$query_string = "SELECT mtdt.description as device_type, mtd.*, mts.site_name
		FROM mac_track_sites AS mts
		RIGHT JOIN mac_track_devices AS mtd 
		ON mtd.site_id = mts.site_id
		LEFT JOIN mac_track_device_types AS mtdt
		ON mtd.device_type_id = mtdt.device_type_id
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($query_string);
}

function mactrack_device() {
	global $device_actions, $mactrack_device_types, $config, $item_rows;

	mactrack_device_request_validation();

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Tracking Device Filters', 'mactrack'), '100%', '', '3', 'center', 'mactrack_devices.php?action=edit&status=' . get_request_var('status'));
	mactrack_device_filter();
	html_end_box();

	$sql_where = '';

	$devices = mactrack_get_devices($sql_where, $rows);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM mac_track_sites AS mts
		RIGHT JOIN mac_track_devices AS mtd 
		ON mtd.site_id = mts.site_id
		LEFT JOIN mac_track_device_types AS mtdt
		ON mtd.device_type_id = mtdt.device_type_id
		$sql_where");

	$display_text = array(
		'device_name'      => array(__('Device Name', 'mactrack'), 'ASC'),
		'site_name'        => array(__('Site Name', 'mactrack'), 'ASC'),
		'snmp_status'      => array(__('Status', 'mactrack'), 'ASC'),
		'hostname'         => array(__('Hostname', 'mactrack'), 'ASC'),
		'device_type'      => array(__('Device Type', 'mactrack'), 'ASC'),
		'ips_total'        => array(__('Total IPs', 'mactrack'), 'DESC'),
		'ports_total'      => array(__('User Ports', 'mactrack'), 'DESC'),
		'ports_active'     => array(__('User Ports Up', 'mactrack'), 'DESC'),
		'ports_trunk'      => array(__('Trunk Ports', 'mactrack'), 'DESC'),
		'macs_active'      => array(__('Active Macs', 'mactrack'), 'DESC'),
		'last_runduration' => array(__('Last Duration', 'mactrack'), 'DESC')
	);

	$columns = sizeof($display_text) + 1;

	$nav = html_nav_bar('mactrack_devices.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Devices', 'mactrack'), 'page', 'main');

	form_start('mactrack_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($devices)) {
		foreach ($devices as $device) {
			form_alternate_row('line' . $device['device_id'], true);
			mactrack_format_device_row($device);
		}
	}else{
		print '<tr><td colspan="' . $columns  . '"><em>' . __('No Device Tracking Devices', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($devices)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	form_end();
}

function mactrack_device_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
		<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'matrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'matrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'matrack');?></option>
							<option value='-2'<?php if (get_request_var('site_id') == '-2') {?> selected<?php }?>><?php print __('None', 'matrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT site_id, site_name FROM mac_track_sites ORDER BY site_name');
							if (sizeof($sites)) {
							foreach ($sites as $site) {
								print '<option value="'. $site['site_id'] . '"';if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
							}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' value='<?php print __esc('Go', 'mactrack');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'mactrack');?>'>
							<input type='button' id='import' value='<?php print __esc('Import', 'mactrack');?>'>
							<input type='submit' id='export' value='<?php print __esc('Export', 'mactrack');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Type', 'matrack');?>
					</td>
					<td>
						<select id='type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'matrack');?></option>
							<option value='1'<?php if (get_request_var('type_id') == '1') {?> selected<?php }?>><?php print __('Switch/Hub', 'matrack');?></option>
							<option value='2'<?php if (get_request_var('type_id') == '2') {?> selected<?php }?>><?php print __('Switch/Router', 'matrack');?></option>
							<option value='3'<?php if (get_request_var('type_id') == '3') {?> selected<?php }?>><?php print __('Router', 'matrack');?></option>
						</select>
					</td>
					<td>
						<?php print __('SubType', 'matrack');?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'matrack');?></option>
							<option value='-2'<?php if (get_request_var('device_type_id') == '-2') {?> selected<?php }?>><?php print __('Not Detected', 'matrack');?></option>
							<?php
							if (get_request_var('type_id') != -1) {
								$device_types = db_fetch_assoc_prepared('SELECT DISTINCT
									mac_track_devices.device_type_id,
									mac_track_device_types.description,
									mac_track_device_types.sysDescr_match
									FROM mac_track_device_types
									INNER JOIN mac_track_devices 
									ON mac_track_device_types.device_type_id = mac_track_devices.device_type_id
									WHERE device_type = ?
									ORDER BY mac_track_device_types.description', array(get_request_var('type_id')));
							}else{
								$device_types = db_fetch_assoc('SELECT DISTINCT
									mac_track_devices.device_type_id,
									mac_track_device_types.description,
									mac_track_device_types.sysDescr_match
									FROM mac_track_device_types
									INNER JOIN mac_track_devices 
									ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
									ORDER BY mac_track_device_types.description');
							}
							if (sizeof($device_types) > 0) {
							foreach ($device_types as $device_type) {
								if ($device_type['device_type_id'] == 0) {
									$display_text = 'Unknown Device Type';
								}else{
									$display_text = $device_type['description'] . ' (' . $device_type['sysDescr_match'] . ')';
								}
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
						<?php print __('Status', 'matrack');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('Any', 'matrack');?></option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>><?php print __('Up', 'matrack');?></option>
							<option value='-2'<?php if (get_request_var('status') == '-2') {?> selected<?php }?>><?php print __('Disabled', 'matrack');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Down', 'matrack');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Unknown', 'matrack');?></option>
							<option value='4'<?php if (get_request_var('status') == '4') {?> selected<?php }?>><?php print __('Error', 'matrack');?></option>
							<option value='5'<?php if (get_request_var('status') == '5') {?> selected<?php }?>><?php print __('No Cacti Link', 'matrack');?></option>
						</select>
					</td>
					<td>
						<?php print __('Devices', 'matrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'matrack');?></option>
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
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = urlPath+'plugins/mactrack/mactrack_devices.php?header=false';
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&status=' + $('#status').val();
			strURL += '&type_id=' + $('#type_id').val();
			strURL += '&device_type_id=' + $('#device_type_id').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = urlPath+'plugins/mactrack/mactrack_devices.php?header=false&clear=true';
			loadPageNoHeader(strURL);
		}

		function exportRows() {
			strURL  = urlPath+'plugins/mactrack/mactrack_devices.php?export=true';
			document.location = strURL;
		}

		function importRows() {
			strURL  = urlPath+'plugins/mactrack/mactrack_devices.php?import=true';
			loadPageNoHeader(strURL);
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

			$('#import').click(function() {
				importRows();
			});
		});
		</script>
		</td>
	</tr>
	<?php
}
