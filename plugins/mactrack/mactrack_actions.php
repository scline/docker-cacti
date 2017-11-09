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

function api_mactrack_device_save($device_id, $host_id, $site_id, $hostname,
	$device_name, $scan_type, $snmp_options, $snmp_readstring,
	$snmp_version, $snmp_username, $snmp_password, $snmp_auth_protocol,
	$snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context,
	$snmp_engine_id, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids,
	$ignorePorts, $notes, $user_name, $user_password, $term_type,
	$private_key_path, $disabled) {
	global $config;
	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

	$save['device_id'] = $device_id;
	$save['host_id']   = $host_id;
	$save['site_id']   = $site_id;
	$save['hostname']             = form_input_validate($hostname, 'hostname', '', false, 3);
	$save['device_name']          = form_input_validate($device_name, 'device_name', '', false, 3);
	$save['notes']                = form_input_validate($notes, 'notes', '', true, 3);
	$save['scan_type']            = form_input_validate($scan_type, 'scan_type', '', false, 3);
	$save['snmp_options']         = form_input_validate($snmp_options, 'snmp_options', '^[0-9]+$', true, 3);
	$save['snmp_readstring']      = form_input_validate($snmp_readstring, 'snmp_readstring', '', true, 3); # for SNMP V3, this is optional
	$save['snmp_version']         = form_input_validate($snmp_version, 'snmp_version', '', false, 3);
	$save['snmp_username']        = form_input_validate($snmp_username, 'snmp_username', '', true, 3);
	$save['snmp_password']        = form_input_validate($snmp_password, 'snmp_password', '', true, 3);
	$save['snmp_auth_protocol']   = form_input_validate($snmp_auth_protocol, 'snmp_auth_protocol', '', true, 3);
	$save['snmp_priv_passphrase'] = form_input_validate($snmp_priv_passphrase, 'snmp_priv_passphrase', '', true, 3);
	$save['snmp_priv_protocol']   = form_input_validate($snmp_priv_protocol, 'snmp_priv_protocol', '', true, 3);
	$save['snmp_context']         = form_input_validate($snmp_context, 'snmp_context', '', true, 3);
	$save['snmp_engine_id']       = form_input_validate($snmp_engine_id, 'snmp_engine_id', '', true, 3);
	$save['snmp_port']            = form_input_validate($snmp_port, 'snmp_port', '^[0-9]+$', false, 3);
	$save['snmp_timeout']         = form_input_validate($snmp_timeout, 'snmp_timeout', '^[0-9]+$', false, 3);
	$save['snmp_retries']         = form_input_validate($snmp_retries, 'snmp_retries', '^[0-9]+$', false, 3);
	$save['max_oids']             = form_input_validate($max_oids, 'max_oids', '^[0-9]+$', false, 3);
	$save['user_name']            = form_input_validate($user_name, 'user_name', '', true, 3);
	$save['user_password']        = form_input_validate($user_password, 'user_password', '', true, 3);
	$save['ignorePorts']          = form_input_validate($ignorePorts, 'ignorePorts', '', true, 3);
	$save['term_type']            = form_input_validate($term_type, 'term_type', '', true, 3);
	$save['private_key_path']     = form_input_validate($private_key_path, 'private_key_path', '', true, 3);
	$save['disabled']             = form_input_validate($disabled, 'disabled', '', true, 3);

	$device_id = 0;
	if (!is_error_message()) {
		$device_id = sql_save($save, 'mac_track_devices', 'device_id');

		if ($device_id) {
			raise_message(1);
			sync_mactrack_to_cacti($save);
		}else{
			raise_message(2);
			mactrack_debug("ERROR: Cacti Device: ($device_id/$host_id): $hostname, error on save: " . serialize($save));
		}
	} else {
		mactrack_debug("ERROR: Cacti Device: ($device_id/$host_id): $hostname, error on verify: " . serialize($save));
	}

	return $device_id;
}

function api_mactrack_device_remove($device_id){
	db_execute('DELETE FROM mac_track_devices WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_aggregated_ports WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_interfaces WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_ips WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_ports WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_processes WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_processes WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_temp_ports WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_vlans WHERE device_id=' . $device_id);
	db_execute('DELETE FROM mac_track_interface_graphs WHERE device_id=' . $device_id);
}

function api_mactrack_site_save($site_id, $site_name, $customer_contact, $netops_contact, $facilities_contact, $site_info) {
	$save['site_id']            = $site_id;
	$save['site_name']          = form_input_validate($site_name, 'site_name', '', false, 3);
	$save['site_info']          = form_input_validate($site_info, 'site_info', '', true, 3);
	$save['customer_contact']   = form_input_validate($customer_contact, 'customer_contact', '', true, 3);
	$save['netops_contact']     = form_input_validate($netops_contact, 'netops_contact', '', true, 3);
	$save['facilities_contact'] = form_input_validate($facilities_contact, 'facilities_contact', '', true, 3);

	$site_id = 0;
	if (!is_error_message()) {
		$site_id = sql_save($save, 'mac_track_sites', 'site_id');

		if ($site_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $site_id;
}

function api_mactrack_site_remove($site_id) {
	db_execute('DELETE FROM mac_track_sites WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_devices WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_aggregated_ports WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_interfaces WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_ips WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_ip_ranges WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_ports WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_temp_ports WHERE site_id=' . $site_id);
	db_execute('DELETE FROM mac_track_vlans WHERE site_id=' . $site_id);
}

function sync_mactrack_to_cacti($mt_device) {
	global $config;

	include_once($config['base_path'] . '/lib/functions.php');
	include_once($config['base_path'] . '/lib/api_device.php');
	include_once($config['base_path'] . '/lib/utility.php'); # required due to missing include in lib/api_device.php

	/* do we want to 'Sync Device Tracking Device to Cacti Device'
	 * AND has the device already been assigned a 'valid' host_id
	 * (aka: has the device been saved successfully) */
	if ((read_config_option('mt_update_policy', true) == 3) &&
		($mt_device['host_id'] > 0)) {

		if (!isset($mt_device['snmp_engine_id'])) {
			$mt_device['snmp_engine_id'] = '';
		}

		# fetch current data for cacti device
		$cacti_device = db_fetch_row('SELECT * FROM host WHERE id=' . $mt_device['host_id']);

		if(sizeof($cacti_device)) {

			# update cacti device
			api_device_save($cacti_device['id'], $cacti_device['host_template_id'],
				$cacti_device['description'], $cacti_device['hostname'],
				$mt_device['snmp_readstring'], $mt_device['snmp_version'], $mt_device['snmp_username'],
				$mt_device['snmp_password'], $mt_device['snmp_port'], $mt_device['snmp_timeout'],
				$cacti_device['disabled'], $cacti_device['availability_method'], $cacti_device['ping_method'], $cacti_device['ping_port'],
				$cacti_device['ping_timeout'], $cacti_device['ping_retries'], $cacti_device['notes'],
				$mt_device['snmp_auth_protocol'], $mt_device['snmp_priv_passphrase'], $mt_device['snmp_priv_protocol'],
				$mt_device['snmp_context'], $mt_device['snmp_engine_id'], $mt_device['max_oids']);

			mactrack_debug('Cacti Device: (' . $cacti_device['id'] . ') successfully updated');
		}
	}

}

function sync_cacti_to_mactrack($device) {
	/* do we want to 'Sync Cacti Device to Device Tracking Device'
	 * AND has the device already been assigned a 'valid' Device Tracking device id
	 * (aka: has the device been saved successfully) */
	if ((read_config_option('mt_update_policy', true) == 2) && ($device['id'] > 0)) {
		# $devices holds the whole row from host table
		# now fetch the related device from mac_track_devices, if any
		$mt_device = db_fetch_row('SELECT * from mac_track_devices WHERE host_id=' . $device['id']);

		if (!isset($mt_device['snmp_engine_id'])) {
			$mt_device['snmp_engine_id'] = '';
		}

		if (is_array($mt_device)) {
			# update mac_track_device
			$device_id = api_mactrack_device_save(
				$mt_device['device_id'], 			# not a host column
				$device['id'],
				$mt_device['site_id'],				# not a host column (wait for 088)
				$device['hostname'],
				$device['description'],
				$mt_device['scan_type'],			# not a host column
				$mt_device['snmp_options'],			# not a host column
				$device['snmp_community'],
				$device['snmp_version'],
				$device['snmp_username'],
				$device['snmp_password'],
				$device['snmp_auth_protocol'],
				$device['snmp_priv_passphrase'],
				$device['snmp_priv_protocol'],
				$device['snmp_context'],
				$device['snmp_engine_id'],
				$device['snmp_port'],
				$device['snmp_timeout'],
				$mt_device['snmp_retries'],
				$device['max_oids'],
				$mt_device['ignorePorts'],			# not a host column
				$device['notes'],
				$mt_device['user_name'], 			# not a host column
				$mt_device['user_password'],		# not a host column
				$mt_device['term_type'],
				$mt_device['private_key_path'],
				(isset($mt_device['disabled']) ? $mt_device['disabled'] : '') # not a host column
			);

			mactrack_debug(__('Device Tracking Device: (%s) successfully updated', $mt_device['device_id'], 'mactrack'));
		}
	}

	# for use with next hook in chain
	return $device;
}

/**
 * Setup the new dropdown action for Device Management
 * @arg $action		actions to be performed from dropdown
 */
function mactrack_device_action_array($action) {
	$action['plugin_mactrack_device'] = __('Import into Device Tracking Database', 'mactrack');
	return $action;
}

function mactrack_device_action_prepare($save) {
	# globals used
	global $config, $fields_mactrack_device_edit;

	# it's our turn
	if ($save['drp_action'] == 'plugin_mactrack_device') { /* mactrack */
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset($save['host_array'])) {
			/* list affected hosts */
			print '<tr>';
			print "<td colspan='2' class='textArea'>" .
				'<p>' . __('Click \'Continue\' to import the following Device to Device Tracking?  Please specify additional Device Tracking device options as given below.', 'mactrack') . '</p>';
			print '</tr>';

			$form_array = array();
			while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
				/* show only those fields to the user, that cannot been taken from the device field */
				if (preg_match('(site_id|scan_type|snmp_options|snmp_retries|ignorePorts|user_name|user_password|disabled|term_type|private_key_path)', $field_name)) {
					$form_array += array($field_name => $fields_mactrack_device_edit[$field_name]);

					$form_array[$field_name]['value']       = '';
					$form_array[$field_name]['description'] = '';
					$form_array[$field_name]['form_id']     = 0;
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			print '<tr>';
			print "<td colspan='2' class='textArea'>" .
				'<p>' . __('We will use these devices SNMP options for the Device Tracking device as well.', 'mactrack') . '</p>' .
				'<ul>' . $save['host_list'] . '</ul></td>';
			print '</tr>';
		}
	}

	return $save; # required for next hook in chain
}

/**
 * perform mactrack_device execute action
 * @arg $action				action to be performed
 * return				-
 *  */
function mactrack_device_action_execute($action) {
	global $config;

	# it's our turn
	if ($action == 'plugin_mactrack_device') { /* mactrack */
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset_request_var('selected_items')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				/* work on all selected hosts */
				for ($i=0;($i<count($selected_items));$i++) {
					# fetch row from host table
					$device = db_fetch_row_prepared('SELECT * from host WHERE id = ?', array($selected_items[$i]));

					# now fetch the related device from mac_track_devices, if any
					$mt_device = db_fetch_row_prepared('SELECT * from mac_track_devices WHERE host_id = ?', array($device['id']));

					if (is_array($device)) {
						# update mac_track_device
						$device_id = api_mactrack_device_save(
							(isset($mt_device['device_id']) ? $mt_device['device_id'] : '0'), 	# not a host column
							$device['id'],
							get_request_var('site_id'),         # not a host column (wait for 088)
							$device['hostname'],
							$device['description'],
							get_request_var('scan_type'),       # not a host column
							get_request_var('snmp_options'),    # not a host column
							$device['snmp_community'],
							$device['snmp_version'],
							$device['snmp_username'],
							$device['snmp_password'],
							$device['snmp_auth_protocol'],
							$device['snmp_priv_passphrase'],
							$device['snmp_priv_protocol'],
							$device['snmp_context'],
							$device['snmp_engine_id'],
							$device['snmp_port'],
							$device['snmp_timeout'],
							get_request_var('snmp_retries'),
							$device['max_oids'],
							get_request_var('ignorePorts'),     # not a host column
							$device['notes'],
							get_request_var('user_name'),       # not a host column
							get_request_var('user_password'),   # not a host column
							get_request_var('term_type'),
							get_request_var('private_key_path'),
							(isset_request_var('disabled') ? get_request_var('disabled') : '') # not a host column
						);
					}
				}
			}
		}
	}

	return $action;
}

