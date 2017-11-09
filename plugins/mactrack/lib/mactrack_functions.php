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

/* register these scanning functions */
global $mactrack_scanning_functions;
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_generic_dot1q_switch_ports', 'get_generic_switch_ports', 'get_generic_wireless_ports');

global $mactrack_scanning_functions_ip;
if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, 'get_standard_arp_table', 'get_netscreen_arp_table');

function mactrack_debug($message) {
	global $debug, $web, $config;
	include_once($config['base_path'] . '/lib/functions.php');

	if (isset($web) && $web && !substr_count($message, 'SQL')) {
		print('<p>' . $message . '</p>');
	}elseif ($debug) {
		print('DEBUG: ' . $message . "\n");
	}

	if (substr_count($message, 'ERROR:')) {
		cacti_log($message, false, 'MACTRACK');
	}
}

function mactrack_rebuild_scanning_funcs() {
	global $config, $mactrack_scanning_functions_ip, $mactrack_scanning_functions;

	if (defined('CACTI_BASE_PATH')) {
		$config['base_path'] = CACTI_BASE_PATH;
	}

	db_execute('TRUNCATE TABLE mac_track_scanning_functions');

	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');
	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_vendors.php');

	/* store the list of registered mactrack scanning functions */
	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('Not Applicable - Router', '1')");
	if (isset($mactrack_scanning_functions)) {
	foreach($mactrack_scanning_functions as $scanning_function) {
		db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('" . $scanning_function . "', '1')");
	}
	}

	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('Not Applicable - Switch/Hub', '2')");
	if (isset($mactrack_scanning_functions_ip)) {
	foreach($mactrack_scanning_functions_ip as $scanning_function) {
		db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('" . $scanning_function . "', '2')");
	}
	}
}

function mactrack_strip_alpha($string = '') {
	return trim($string, 'abcdefghijklmnopqrstuvwzyzABCDEFGHIJKLMNOPQRSTUVWXYZ()[]{}');
}

function mactrack_check_user_realm($realm_id) {
	if (empty($_SESSION['sess_user_id'])) {
		return FALSE;
	}elseif (!empty($_SESSION['sess_user_id'])) {
		if ((!db_fetch_assoc("select
			user_auth_realm.realm_id
			from
			user_auth_realm
			where user_auth_realm.user_id='" . $_SESSION['sess_user_id'] . "'
			and user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id))) {
			return FALSE;
		}else{
			return TRUE;
		}
	}
}

/* valid_snmp_device - This function validates that the device is reachable via snmp.
  It first attempts	to utilize the default snmp readstring.  If it's not valid, it
  attempts to find the correct read string and then updates several system
  information variable. it returns the status	of the host (up=true, down=false)
 */
function valid_snmp_device(&$device) {
	global $config;
	include_once($config['base_path'] . '/plugins/mactrack/mactrack_actions.php');

	/* initialize variable */
	$host_up = FALSE;
	$device['snmp_status'] = HOST_DOWN;

	/* force php to return numeric oid's */
	cacti_oid_numeric_format();

	/* if the first read did not work, loop until found */
	$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
		'.1.3.6.1.2.1.1.2.0', $device['snmp_version'],
		$device['snmp_username'], $device['snmp_password'],
		$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
		$device['snmp_priv_protocol'], $device['snmp_context'],
		$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

	$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);

	if ((strlen($snmp_sysObjectID) > 0) &&
		(!substr_count($snmp_sysObjectID, 'No Such Object')) &&
		(!substr_count($snmp_sysObjectID, 'Error In'))) {
		$snmp_sysObjectID = trim(str_replace('"','', $snmp_sysObjectID));
		$host_up = TRUE;
		$device['snmp_status'] = HOST_UP;
	}else{
		/* loop through the default and then other common for the correct answer */
		$snmp_options = db_fetch_assoc_prepared('SELECT * from mac_track_snmp_items WHERE snmp_id = ? ORDER BY sequence', array($device['snmp_options']));

		if (sizeof($snmp_options)) {
		foreach($snmp_options as $snmp_option) {
			# update $device for later db update via db_update_device_status
			$device['snmp_readstring'] = $snmp_option['snmp_readstring'];
			$device['snmp_version'] = $snmp_option['snmp_version'];
			$device['snmp_username'] = $snmp_option['snmp_username'];
			$device['snmp_password'] = $snmp_option['snmp_password'];
			$device['snmp_auth_protocol'] = $snmp_option['snmp_auth_protocol'];
			$device['snmp_priv_passphrase'] = $snmp_option['snmp_priv_passphrase'];
			$device['snmp_priv_protocol'] = $snmp_option['snmp_priv_protocol'];
			$device['snmp_context'] = $snmp_option['snmp_context'];
			$device['snmp_port'] = $snmp_option['snmp_port'];
			$device['snmp_timeout'] = $snmp_option['snmp_timeout'];
			$device['snmp_retries'] = $snmp_option['snmp_retries'];

			$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.2.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'],
					$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
					$device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout'],
					$device['snmp_retries']);

			$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
			$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
			$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);

			if ((strlen($snmp_sysObjectID) > 0) &&
				(!substr_count($snmp_sysObjectID, 'No Such Object')) &&
				(!substr_count($snmp_sysObjectID, 'Error In'))) {
				$snmp_sysObjectID = trim(str_replace("'", '', $snmp_sysObjectID));
				$device['snmp_readstring'] = $snmp_option['snmp_readstring'];
				$device['snmp_status'] = HOST_UP;
				$host_up = TRUE;
				# update cacti device, if required
				sync_mactrack_to_cacti($device);
				# update to mactrack itself is done by db_update_device_status in mactrack_scanner.php
				# TODO: if db_update_device_status would use api_mactrack_device_save, there would be no need to call sync_mactrack_to_cacti here
				# but currently the parameter set doesn't match
				mactrack_debug('Result found on Option Set (' . $snmp_option['snmp_id'] . ') Sequence (' . $snmp_option['sequence'] . '): ' . $snmp_sysObjectID);
				break; # no need to continue if we have a match
			}else{
				$device['snmp_status'] = HOST_DOWN;
				$host_up = FALSE;
			}
		}
		}
	}

	if ($host_up) {
		$device['snmp_sysObjectID'] = $snmp_sysObjectID;

		/* get system name */
		$snmp_sysName = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.5.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if (strlen($snmp_sysName) > 0) {
			$snmp_sysName = trim(strtr($snmp_sysName,'"',' '));
			$device['snmp_sysName'] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.6.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if (strlen($snmp_sysLocation) > 0) {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,'"',' '));
			$device['snmp_sysLocation'] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.4.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if (strlen($snmp_sysContact) > 0) {
			$snmp_sysContact = trim(strtr($snmp_sysContact,'"',' '));
			$device['snmp_sysContact'] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.1.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if (strlen($snmp_sysDescr) > 0) {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,'"',' '));
			$device['snmp_sysDescr'] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.3.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if (strlen($snmp_sysUptime) > 0) {
			$snmp_sysUptime = trim(strtr($snmp_sysUptime,'"',' '));
			$device['snmp_sysUptime'] = $snmp_sysUptime;
		}
	}

	return $host_up;
}

/*	find_scanning_function - This function scans the mac_track_device_type database
  for a valid scanning function and then returns an array with the current device
  type and it's characteristics for the main mac_track_scanner function to call.
*/
function find_scanning_function(&$device, &$device_types) {
	/* scan all device_types to determine the function to call */
	if (sizeof($device_types)) {
	foreach($device_types as $device_type) {
		/* by default none match */
		$sysDescr_match = FALSE;
		$sysObjectID_match = FALSE;

		/* search for a matching snmp_sysDescr */
		if (substr_count($device_type['sysDescr_match'], '*') > 0) {
			/* need to assume mixed string */
			$parts = explode('*', $device_type['sysDescr_match']);
			if (sizeof($parts)) {
			foreach($parts as $part) {
				if (substr_count($device['sysDescr_match'],$part) > 0) {
					$sysDescr_match = TRUE;
				}else{
					$sysDescr_match = FALSE;
				}
			}
			}
		}else{
			if (strlen($device_type['sysDescr_match']) == 0) {
				$sysDescr_match = TRUE;
			}else{
				if (substr_count($device['snmp_sysDescr'], $device_type['sysDescr_match'])) {
					$sysDescr_match = TRUE;
				}else{
					$sysDescr_match = FALSE;
				}
			}
		}

		/* search for a matching snmp_sysObjectID */
		$len = strlen($device_type['sysObjectID_match']);
		if (substr($device['snmp_sysObjectID'],0,$len) == $device_type['sysObjectID_match']) {
			$sysObjectID_match = TRUE;
		}

		if (($sysObjectID_match == TRUE) && ($sysDescr_match == TRUE)) {
			$device['device_type_id'] = $device_type['device_type_id'];
			$device['scan_type'] = $device_type['device_type'];
			return $device_type;
		}
	}
	}

	return array();
}

/*	port_list_to_array - Takes a text list of ports and builds a trimmed array of
  the resulting array.  Returns the array
*/
function port_list_to_array($port_list, $delimiter = ':') {
	$port_array = array();

	if (read_config_option('mt_ignorePorts_delim') == '-1') {
		/* find the delimiter */
		$t1 = sizeof(explode(':', $port_list));
		$t2 = sizeof(explode('|', $port_list));
		$t3 = sizeof(explode(' ', $port_list));

		if ($t1 > $t2 && $t1 > $t3) {
			$delimiter = ':';
		}elseif ($t2 > $t1 && $t2 > $t3) {
			$delimiter = '|';
		}elseif ($t3 > $t1 && $t3 > $t2) {
			$delimiter = ' ';
		}
	}else{
		$delimiter = read_config_option('mt_ignorePorts_delim');
	}

	$ports = explode($delimiter, $port_list);

	if (sizeof($ports)) {
	foreach ($ports as $port) {
		array_push($port_array, trim($port));
	}
	}

	return $port_array;
}

/*	get_standard_arp_table - This function reads a devices ARP table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table.
*/
function get_standard_arp_table($site, &$device) {
	global $debug, $scan_date;

	/* get the atifIndexes for the device */
	$atifIndexes = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.1', $device);
	$atEntries   = array();

	if (sizeof($atifIndexes)) {
		mactrack_debug('atifIndexes data collection complete');
		$atPhysAddress = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.2', $device);
		mactrack_debug('atPhysAddress data collection complete');
		$atNetAddress  = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.3', $device);
		mactrack_debug('atNetAddress data collection complete');
	}else{
		/* second attempt for Force10 Gear */
		$atifIndexes   = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.1', $device);
		mactrack_debug('atifIndexes data collection complete');
		$atPhysAddress = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.2', $device);
		mactrack_debug('atPhysAddress data collection complete');
		$atNetAddress = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.3', $device);
		mactrack_debug('atNetAddress data collection complete');
	}

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (sizeof($atPhysAddress)) {
	foreach($atPhysAddress as $atAddress) {
		$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
		$i++;
	}
	}
	mactrack_debug('atPhysAddress MAC Address Conversion Completed');

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (sizeof($atifIndexes)) {
	foreach($atifIndexes as $atifIndex) {
		$atEntries[$i]['atifIndex'] = $atifIndex;
		$atEntries[$i]['atPhysAddress'] = isset($atPhysAddress[$keys[$i]]) ? $atPhysAddress[$keys[$i]]:'';
		$atEntries[$i]['atNetAddress'] = isset($atNetAddress[$keys[$i]]) ? xform_net_address($atNetAddress[$keys[$i]]):'';
		$i++;
	}
	}
	mactrack_debug('atEntries assembly complete.');

	/* output details to database */
	if (sizeof($atEntries)) {
	foreach($atEntries as $atEntry) {
		$insert_string = 'REPLACE INTO mac_track_ips 
			(site_id,device_id,hostname,device_name,port_number,
			mac_address,ip_address,scan_date)
			VALUES (' .
			$device['site_id'] . ',' .
			$device['device_id'] . ',' .
			db_qstr($device['hostname']) . ',' .
			db_qstr($device['device_name']) . ',' .
			db_qstr($atEntry['atifIndex']) . ',' .
			db_qstr($atEntry['atPhysAddress']) . ',' .
			db_qstr($atEntry['atNetAddress']) . ',' .
			db_qstr($scan_date) . ')';

		//mactrack_debug("SQL: " . $insert_string);

		db_execute($insert_string);
	}
	}

	/* save ip information for the device */
	$device['ips_total'] = sizeof($atEntries);
	db_execute('UPDATE mac_track_devices SET ips_total =' . $device['ips_total'] . ' WHERE device_id=' . $device['device_id']);

	mactrack_debug('HOST: ' . $device['hostname'] . ', IP address information collection complete');
}

/*	build_InterfacesTable - This is a basic function that will scan Interfaces table
  and return data.  It also stores data in the mac_track_interfaces table.  Some of the
  data is also used for scanning purposes.
*/
function build_InterfacesTable(&$device, &$ifIndexes, $getLinkPorts = FALSE, $getAlias = FALSE) {
	/* initialize the interfaces array */
	$ifInterfaces = array();

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete. \'' . sizeof($ifIndexes) . '\' rows found!');

	$ifTypes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.3', $device);
	if (sizeof($ifTypes)) {
	foreach($ifTypes as $key => $value) {
		if (!is_numeric($value)) {
			$parts = explode('(', $value);
			$piece = $parts[1];
			$ifTypes[$key] = str_replace(')', '', trim($piece));
		}
	}
	}
	mactrack_debug('ifTypes data collection complete. \'' . sizeof($ifTypes) . '\' rows found!');

	$ifNames = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.1', $device);
	mactrack_debug('ifNames data collection complete. \'' . sizeof($ifNames) . '\' rows found!');

	/* get ports names through use of ifAlias */
	if ($getAlias) {
		$ifAliases = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.18', $device);
		mactrack_debug('ifAlias data collection complete. \'' . sizeof($ifAliases) . '\' rows found!');
	}

	/* get ports that happen to be link ports */
	if ($getLinkPorts) {
		$link_ports = get_link_port_status($device);
		mactrack_debug("ipAddrTable scanning for link ports data collection complete. '" . sizeof($link_ports) . "' rows found!");
	}

	/* required only for interfaces table */
	$db_data = db_fetch_assoc("SELECT * FROM mac_track_interfaces WHERE device_id='" . $device["device_id"] . "' ORDER BY ifIndex");

	if (sizeof($db_data)) {
		foreach($db_data as $interface) {
			$db_interface[$interface["ifIndex"]] = $interface;
		}
	}

	/* mark all interfaces as not present */
	db_execute("UPDATE mac_track_interfaces SET present=0 WHERE device_id=" . $device["device_id"]);

	$insert_prefix = "INSERT INTO mac_track_interfaces (site_id, device_id, sysUptime, ifIndex, ifType, ifName, ifAlias, linkPort, vlan_id," .
		" vlan_name, vlan_trunk_status, ifSpeed, ifHighSpeed, ifDuplex, " .
		" ifDescr, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus, ifLastChange, ".
		" ifInOctets, ifOutOctets, ifHCInOctets, ifHCOutOctets, ifInUcastPkts, ifOutUcastPkts, " .
		" ifInDiscards, ifInErrors, ifInUnknownProtos, ifOutDiscards, ifOutErrors, " .
		" ifInMulticastPkts, ifOutMulticastPkts, ifInBroadcastPkts, ifOutBroadcastPkts, " .
		" int_ifInOctets, int_ifOutOctets, int_ifHCInOctets, int_ifHCOutOctets, int_ifInUcastPkts, int_ifOutUcastPkts, " .
		" int_ifInDiscards, int_ifInErrors, int_ifInUnknownProtos, int_ifOutDiscards, int_ifOutErrors, int_ifInMulticastPkts, int_ifOutMulticastPkts, " .
		"  int_ifInBroadcastPkts, int_ifOutBroadcastPkts, int_discards_present, int_errors_present, " .
		" last_down_time, last_up_time, stateChanges, present) VALUES ";

	$insert_suffix = " ON DUPLICATE KEY UPDATE sysUptime=VALUES(sysUptime), ifType=VALUES(ifType), ifName=VALUES(ifName), ifAlias=VALUES(ifAlias), linkPort=VALUES(linkPort)," .
		" vlan_id=VALUES(vlan_id), vlan_name=VALUES(vlan_name), vlan_trunk_status=VALUES(vlan_trunk_status)," .
		" ifSpeed=VALUES(ifSpeed), ifHighSpeed=VALUES(ifHighSpeed), ifDuplex=VALUES(ifDuplex), ifDescr=VALUES(ifDescr), ifMtu=VALUES(ifMtu), ifPhysAddress=VALUES(ifPhysAddress), ifAdminStatus=VALUES(ifAdminStatus)," .
		" ifOperStatus=VALUES(ifOperStatus), ifLastChange=VALUES(ifLastChange), " .
		" ifInOctets=VALUES(ifInOctets), ifOutOctets=VALUES(ifOutOctets), ifHCInOctets=VALUES(ifHCInOctets), ifHCOutOctets=VALUES(ifHCOutOctets), " .
		" ifInUcastPkts=VALUES(ifInUcastPkts), ifOutUcastPkts=VALUES(ifOutUcastPkts), " .
		" ifInDiscards=VALUES(ifInDiscards), ifInErrors=VALUES(ifInErrors)," .
		" ifInUnknownProtos=VALUES(ifInUnknownProtos), ifOutDiscards=VALUES(ifOutDiscards), ifOutErrors=VALUES(ifOutErrors)," .
		" ifInMulticastPkts=VALUES(ifInMulticastPkts), ifOutMulticastPkts=VALUES(ifOutMulticastPkts), ifInBroadcastPkts=VALUES(ifInBroadcastPkts),  ifOutBroadcastPkts=VALUES(ifOutBroadcastPkts)," .
		" int_ifInOctets=VALUES(int_ifInOctets), int_ifOutOctets=VALUES(int_ifOutOctets), int_ifHCInOctets=VALUES(int_ifHCInOctets), int_ifHCOutOctets=VALUES(int_ifHCOutOctets), " .
		" int_ifInUcastPkts=VALUES(int_ifInUcastPkts), int_ifOutUcastPkts=VALUES(int_ifOutUcastPkts), " .
		" int_ifInDiscards=VALUES(int_ifInDiscards), int_ifInErrors=VALUES(int_ifInErrors)," .
		" int_ifInUnknownProtos=VALUES(int_ifInUnknownProtos), int_ifOutDiscards=VALUES(int_ifOutDiscards)," .
		" int_ifOutErrors=VALUES(int_ifOutErrors), int_ifInMulticastPkts=VALUES(int_ifInMulticastPkts), int_ifOutMulticastPkts=VALUES(int_ifOutMulticastPkts), int_ifInBroadcastPkts=VALUES(int_ifInBroadcastPkts),  " .
		" int_ifOutBroadcastPkts=VALUES(int_ifOutBroadcastPkts)," .
		" int_discards_present=VALUES(int_discards_present), int_errors_present=VALUES(int_errors_present)," .
		" last_down_time=VALUES(last_down_time), last_up_time=VALUES(last_up_time)," .
		" stateChanges=VALUES(stateChanges), present='1'";

	$insert_vals = "";

	$ifSpeed = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.5", $device);
	mactrack_debug("ifSpeed data collection complete. '" . sizeof($ifSpeed) . "' rows found!");

	$ifHighSpeed = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.15", $device);
	mactrack_debug("ifHighSpeed data collection complete. '" . sizeof($ifHighSpeed) . "' rows found!");

	$ifDuplex = xform_standard_indexed_data(".1.3.6.1.2.1.10.7.2.1.19", $device);
	mactrack_debug("ifDuplex data collection complete. '" . sizeof($ifDuplex) . "' rows found!");

	$ifDescr = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.2", $device);
	mactrack_debug("ifDescr data collection complete. '" . sizeof($ifDescr) . "' rows found!");

	$ifMtu = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.4", $device);
	mactrack_debug("ifMtu data collection complete. '" . sizeof($ifMtu) . "' rows found!");

	$ifPhysAddress = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.6", $device);
	mactrack_debug("ifPhysAddress data collection complete. '" . sizeof($ifPhysAddress) . "' rows found!");

	$ifAdminStatus = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.7", $device);
	if (sizeof($ifAdminStatus)) {
	foreach($ifAdminStatus as $key => $value) {
		if ((substr_count(strtolower($value), "up")) || ($value == "1")) {
			$ifAdminStatus[$key] = 1;
		}else{
			$ifAdminStatus[$key] = 0;
		}
	}
	}
	mactrack_debug("ifAdminStatus data collection complete. '" . sizeof($ifAdminStatus) . "' rows found!");

	$ifOperStatus = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	if (sizeof($ifOperStatus)) {
	foreach($ifOperStatus as $key=>$value) {
		if ((substr_count(strtolower($value), "up")) || ($value == "1")) {
			$ifOperStatus[$key] = 1;
		}else{
			$ifOperStatus[$key] = 0;
		}
	}
	}
	mactrack_debug("ifOperStatus data collection complete. '" . sizeof($ifOperStatus) . "' rows found!");

	$ifLastChange = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.9", $device);
	mactrack_debug("ifLastChange data collection complete. '" . sizeof($ifLastChange) . "' rows found!");

	/* get timing for rate information */
	$prev_octets_time = strtotime($device["last_rundate"]);
	$cur_octets_time  = time();

	if ($prev_octets_time == 0) {
		$divisor = FALSE;
	}else{
		$divisor = $cur_octets_time - $prev_octets_time;
	}

	/* if the device is snmpv2 use high speed and don't bother with the low speed stuff */
	$ifInOctets = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.10", $device);
	mactrack_debug("ifInOctets data collection complete. '" . sizeof($ifInOctets) . "' rows found!");

	$ifOutOctets = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.16", $device);
	mactrack_debug("ifOutOctets data collection complete. '" . sizeof($ifOutOctets) . "' rows found!");

	if ($device["snmp_version"] > 1) {
		$ifHCInOctets = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.6", $device);
		mactrack_debug("ifHCInOctets data collection complete. '" . sizeof($ifHCInOctets) . "' rows found!");

		$ifHCOutOctets = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.10", $device);
		mactrack_debug("ifHCOutOctets data collection complete. '" . sizeof($ifHCOutOctets) . "' rows found!");
	}


	$ifInMulticastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.2", $device);
	mactrack_debug("ifInMulticastPkts data collection complete. '" . sizeof($ifInMulticastPkts) . "' rows found!");

	$ifOutMulticastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.4", $device);
	mactrack_debug("ifOutMulticastPkts data collection complete. '" . sizeof($ifOutMulticastPkts) . "' rows found!");

	$ifInBroadcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.3", $device);
	mactrack_debug("ifInBroadcastPkts data collection complete. '" . sizeof($ifInBroadcastPkts) . "' rows found!");

	$ifOutBroadcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.5", $device);
	mactrack_debug("ifOutBroadcastPkts data collection complete. '" . sizeof($ifOutBroadcastPkts) . "' rows found!");
	
	$ifInUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.11", $device);
	mactrack_debug("ifInUcastPkts data collection complete. '" . sizeof($ifInUcastPkts) . "' rows found!");

	$ifOutUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.17", $device);
	mactrack_debug("ifOutUcastPkts data collection complete. '" . sizeof($ifOutUcastPkts) . "' rows found!");

	/* get information on error conditions */
	$ifInDiscards = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.13", $device);
	mactrack_debug("ifInDiscards data collection complete. '" . sizeof($ifInDiscards) . "' rows found!");

	$ifInErrors = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.14", $device);
	mactrack_debug("ifInErrors data collection complete. '" . sizeof($ifInErrors) . "' rows found!");

	$ifInUnknownProtos = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.15", $device);
	mactrack_debug("ifInUnknownProtos data collection complete. '" . sizeof($ifInUnknownProtos) . "' rows found!");

	$ifOutDiscards = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.19", $device);
	mactrack_debug("ifOutDiscards data collection complete. '" . sizeof($ifOutDiscards) . "' rows found!");

	$ifOutErrors = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.20", $device);
	mactrack_debug("ifOutErrors data collection complete. '" . sizeof($ifOutErrors) . "' rows found!");

	$vlan_id    = "";
	$vlan_name  = "";
	$vlan_trunk = "";

	$i = 0;
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["ifIndex"] = $ifIndex;
		$ifInterfaces[$ifIndex]["ifName"] = (isset($ifNames[$ifIndex]) ? $ifNames[$ifIndex] : '');
		$ifInterfaces[$ifIndex]["ifType"] = (isset($ifTypes[$ifIndex]) ? $ifTypes[$ifIndex] : '');

		if ($getLinkPorts) {
			$ifInterfaces[$ifIndex]["linkPort"] = (isset($link_ports[$ifIndex]) ? $link_ports[$ifIndex] : '');
			$linkPort = (isset($link_ports[$ifIndex]) ? $link_ports[$ifIndex] : '');
		}else{
			$linkPort = 0;
		}

		if (($getAlias) && (sizeof($ifAliases))) {
			$ifInterfaces[$ifIndex]["ifAlias"] = (isset($ifAliases[$ifIndex]) ? $ifAliases[$ifIndex] : '');
			$ifAlias = (isset($ifAliases[$ifIndex]) ? $ifAliases[$ifIndex] : '');
		}else{
			$ifAlias = "";
		}

		/* update the last up/down status */
		if (!isset($db_interface[$ifIndex]["ifOperStatus"])) {
			if ($ifOperStatus[$ifIndex] == 1) {
				$last_up_time = date("Y-m-d H:i:s");
				$stateChanges = 0;
				$last_down_time = 0;
			}else{
				$stateChanges = 0;
				$last_up_time   = 0;
				$last_down_time = date("Y-m-d H:i:s");
			}
		}else{
			$last_up_time   = $db_interface[$ifIndex]["last_up_time"];
			$last_down_time = $db_interface[$ifIndex]["last_down_time"];
			$stateChanges   = $db_interface[$ifIndex]["stateChanges"];

			if ($db_interface[$ifIndex]["ifOperStatus"] == 0) { /* interface previously not up */
				if ($ifOperStatus[$ifIndex] == 1) {
					/* the interface just went up, mark the time */
					$last_up_time = date("Y-m-d H:i:s");
					$stateChanges += 1;

					/* if the interface has never been marked down before, make it the current time */
					if ($db_interface[$ifIndex]["last_down_time"] == '0000-00-00 00:00:00') {
						$last_down_time = $last_up_time;
					}
				}else{
					/* if the interface has never been down, make the current time */
					$last_down_time = date("Y-m-d H:i:s");

					/* if the interface stayed down, set the last up time if not set before */
					if ($db_interface[$ifIndex]["last_up_time"] == '0000-00-00 00:00:00') {
						$last_up_time = date("Y-m-d H:i:s");
					}
				}
			}else{
				if ($ifOperStatus[$ifIndex] == 0) {
					/* the interface just went down, mark the time */
					$last_down_time = date("Y-m-d H:i:s");
					$stateChanges += 1;

					/* if the interface has never been up before, mark it the current time */
					if ($db_interface[$ifIndex]["last_up_time"] == '0000-00-00 00:00:00') {
						$last_up_time = date("Y-m-d H:i:s");
					}
				}else{
					$last_up_time = date("Y-m-d H:i:s");

					if ($db_interface[$ifIndex]["last_down_time"] == '0000-00-00 00:00:00') {
						$last_down_time = date("Y-m-d H:i:s");
					}
				}
			}
		}

		/* do the in octets */
		$int_ifInOctets = get_link_int_value("ifInOctets", $ifIndex, $ifInOctets, $db_interface, $divisor, "traffic");

		/* do the out octets */
		$int_ifOutOctets = get_link_int_value("ifOutOctets", $ifIndex, $ifOutOctets, $db_interface, $divisor, "traffic");

		if ($device["snmp_version"] > 1) {
			/* do the in octets */
			$int_ifHCInOctets = get_link_int_value("ifHCInOctets", $ifIndex, $ifHCInOctets, $db_interface, $divisor, "traffic", "64");

			/* do the out octets */
			$int_ifHCOutOctets = get_link_int_value("ifHCOutOctets", $ifIndex, $ifHCOutOctets, $db_interface, $divisor, "traffic", "64");
		}

		/* accomodate values in high speed octets for interfaces that don't support 64 bit */
		if (isset($ifInOctets[$ifIndex])) {
			if (!isset($ifHCInOctets[$ifIndex])) {
				$ifHCInOctets[$ifIndex] = $ifInOctets[$ifIndex];
				$int_ifHCInOctets = $int_ifInOctets;
			}
		}

		if (isset($ifOutOctets[$ifIndex])) {
			if (!isset($ifHCOutOctets[$ifIndex])) {
				$ifHCOutOctets[$ifIndex] = $ifOutOctets[$ifIndex];
				$int_ifHCOutOctets = $int_ifOutOctets;
			}
		}

		
		$int_ifInMulticastPkts  = get_link_int_value("ifInMulticastPkts", $ifIndex, $ifInMulticastPkts, $db_interface, $divisor, "traffic");

		$int_ifOutMulticastPkts = get_link_int_value("ifOutMulticastPkts", $ifIndex, $ifOutMulticastPkts, $db_interface, $divisor, "traffic");

		$int_ifInBroadcastPkts  = get_link_int_value("ifInBroadcastPkts", $ifIndex, $ifInBroadcastPkts, $db_interface, $divisor, "traffic");

		$int_ifOutBroadcastPkts = get_link_int_value("ifOutBroadcastPkts", $ifIndex, $ifOutBroadcastPkts, $db_interface, $divisor, "traffic");
		
		$int_ifInUcastPkts   = get_link_int_value("ifInUcastPkts", $ifIndex, $ifInUcastPkts, $db_interface, $divisor, "traffic");

		$int_ifOutUcastPkts  = get_link_int_value("ifOutUcastPkts", $ifIndex, $ifOutUcastPkts, $db_interface, $divisor, "traffic");

		/* see if in error's have been increasing */
		$int_ifInErrors      = get_link_int_value("ifInErrors", $ifIndex, $ifInErrors, $db_interface, $divisor, "errors");

		/* see if out error's have been increasing */
		$int_ifOutErrors     = get_link_int_value("ifOutErrors", $ifIndex, $ifOutErrors, $db_interface, $divisor, "errors");

		if ($int_ifInErrors > 0 || $int_ifOutErrors > 0) {
			$int_errors_present = TRUE;
		}else{
			$int_errors_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInDiscards    = get_link_int_value("ifInDiscards", $ifIndex, $ifInDiscards, $db_interface, $divisor, "errors");

		/* see if out discards's have been increasing */
		$int_ifOutDiscards   = get_link_int_value("ifOutDiscards", $ifIndex, $ifOutDiscards, $db_interface, $divisor, "errors");

		if ($int_ifInDiscards > 0 || $int_ifOutDiscards > 0) {
			$int_discards_present = TRUE;
		}else{
			$int_discards_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInUnknownProtos = get_link_int_value("ifInUnknownProtos", $ifIndex, $ifInUnknownProtos, $db_interface, $divisor, "errors");

		/* format the update packet */
		if ($i == 0) {
			$insert_vals .= " ";
		}else{
			$insert_vals .= ",";
		}
		
		$mac_address = isset($ifPhysAddress[$ifIndex]) ? xform_mac_address($ifPhysAddress[$ifIndex]):'';
		$insert_vals .= "('" .
			@$device["site_id"]                 . "', '" . @$device["device_id"]         . "', '" .
			@$device["snmp_sysUptime"]          . "', '" . @$ifIndex                     . "', '" .
			@$ifTypes[$ifIndex]                 . "', "  . @db_qstr(@$ifNames[$ifIndex]) . ", "  .
			@db_qstr($ifAlias)                  . ", '"  . @$linkPort                    . "', '" .
			@$vlan_id                           . "', "  . @db_qstr(@$vlan_name)         . ", '"  .
			@$vlan_trunk                        . "', '" . @$ifSpeed[$ifIndex]           . "', '" .
			(isset($ifHighSpeed[$ifIndex]) ? $ifHighSpeed[$ifIndex] : '')               . "', '" .
                        (isset($ifDuplex[$ifIndex]) ? $ifDuplex[$ifIndex] : '')                     . "', " .
			@db_qstr(@$ifDescr[$ifIndex])       . ", '"  . 
			(isset($ifMtu[$ifIndex]) ? $ifMtu[$ifIndex] : '')             		     . "', '" .
			$mac_address                        . "', '" . @$ifAdminStatus[$ifIndex]     . "', '" .
			@$ifOperStatus[$ifIndex]            . "', '" . @$ifLastChange[$ifIndex]      . "', '" .
			(isset($ifInOctets[$ifIndex]) ? $ifInOctets[$ifIndex] : '')                 . "', '" . 
			(isset($ifOutOctets[$ifIndex]) ? $ifOutOctets[$ifIndex] : '')      	     . "', '" .
			(isset($ifHCInOctets[$ifIndex]) ? $ifHCInOctets[$ifIndex] : '')             . "', '" . 
			(isset($ifHCOutOctets[$ifIndex]) ? $ifHCOutOctets[$ifIndex] : '')     	     . "', '" .
			(isset($ifInUcastPkts[$ifIndex]) ? $ifInUcastPkts[$ifIndex] : '')           . "', '" . 
			(isset($ifOutUcastPkts[$ifIndex]) ? $ifOutUcastPkts[$ifIndex] : '')         . "', '" .
			(isset($ifInDiscards[$ifIndex]) ? $ifInDiscards[$ifIndex] : '')             . "', '" . 
			(isset($ifInErrors[$ifIndex]) ? $ifInErrors[$ifIndex] : '')        	     . "', '" .
			(isset($ifInUnknownProtos[$ifIndex]) ? $ifInUnknownProtos[$ifIndex] : '')   . "', '" . 
			(isset($ifOutDiscards[$ifIndex]) ? $ifOutDiscards[$ifIndex] : '')	     . "', '" .
			(isset($ifOutErrors[$ifIndex]) ? $ifOutErrors[$ifIndex] : '')               . "', '" .
                        (isset($ifInMulticastPkts[$ifIndex]) ? $ifInMulticastPkts[$ifIndex] : '')   . "', '" .
                        (isset($ifOutMulticastPkts[$ifIndex]) ? $ifOutMulticastPkts[$ifIndex] : '') . "', '" .
                        (isset($ifInBroadcastPkts[$ifIndex]) ? $ifInBroadcastPkts[$ifIndex] : '')   . "', '" .
                        (isset($ifOutBroadcastPkts[$ifIndex]) ? $ifOutBroadcastPkts[$ifIndex] : '') . "', '" .
			@$int_ifInOctets                    . "', '" . @$int_ifOutOctets             . "', '" .
			@$int_ifHCInOctets                  . "', '" . @$int_ifHCOutOctets           . "', '" .
			@$int_ifInMulticastPkts		    . "', '" . @$int_ifOutMulticastPkts      . "', '" .
			@$int_ifInBroadcastPkts		    . "', '" . @$int_ifOutBroadcastPkts      . "', '" .
			@$int_ifInUcastPkts                 . "', '" . @$int_ifOutUcastPkts          . "', '" .
			@$int_ifInDiscards                  . "', '" . @$int_ifInErrors              . "', '" .
			@$int_ifInUnknownProtos             . "', '" . @$int_ifOutDiscards           . "', '" .
			@$int_ifOutErrors                   . "', '" . @$int_discards_present        . "', '" .
			$int_errors_present                 . "', '" .  $last_down_time              . "', '" .
			$last_up_time                       . "', '" .  $stateChanges                . "', '" . "1')";

		$i++;
	}
	mactrack_debug("ifInterfaces assembly complete: " . strlen($insert_prefix . $insert_vals . $insert_suffix));

	if (strlen($insert_vals)) {
		/* add/update records in the database */
		db_execute($insert_prefix . $insert_vals . $insert_suffix);

		/* remove all obsolete records from the database */
		db_execute("DELETE FROM mac_track_interfaces WHERE present=0 AND device_id=" . $device["device_id"]);

		/* set the percent utilized fields, you can't do this for vlans */
		db_execute("UPDATE mac_track_interfaces
			SET inBound=(int_ifHCInOctets*8)/(ifHighSpeed*10000), outBound=(int_ifHCOutOctets*8)/(ifHighSpeed*10000)
			WHERE ifHighSpeed>0 AND ifName NOT LIKE 'Vl%' AND device_id=" . $device["device_id"]);

		mactrack_debug("Adding IfInterfaces Records");
	}

	if ($device["host_id"] > 0) {
		mactrack_find_host_graphs($device["device_id"], $device["host_id"]);
	}

	return $ifInterfaces;
}

function mactrack_find_host_graphs($device_id, $host_id) {
	$field_name = "ifName";

	$local_data_ids = db_fetch_assoc("SELECT
		data_local.*,
		host_snmp_cache.field_name,
		host_snmp_cache.field_value
		FROM (data_local,data_template_data)
		LEFT JOIN data_input ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template ON (data_local.data_template_id=data_template.id)
		LEFT JOIN host_snmp_cache ON (host_snmp_cache.snmp_query_id=data_local.snmp_query_id
		AND host_snmp_cache.host_id=data_local.host_id
		AND host_snmp_cache.snmp_index=data_local.snmp_index)
		WHERE data_local.id=data_template_data.local_data_id
		AND host_snmp_cache.host_id='$host_id'
		AND field_name='$field_name'");

	$output_array    = array();
	if(sizeof($local_data_ids)) {
	foreach($local_data_ids as $local_data_id) {
		$local_graph_ids = array_rekey(db_fetch_assoc("SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
			graph_templates_graph.graph_template_id
			FROM (graph_templates_graph
			INNER JOIN graph_templates_item ON graph_templates_graph.local_graph_id=graph_templates_item.local_graph_id)
			INNER JOIN data_template_rrd
			ON graph_templates_item.task_item_id=data_template_rrd.id
			WHERE graph_templates_graph.local_graph_id>0
			AND data_template_rrd.local_data_id=" . $local_data_id["id"]), "id", "graph_template_id");

		if (sizeof($local_graph_ids)) {
		foreach($local_graph_ids as $local_graph_id => $graph_template_id) {
			$output_array[$local_data_id["field_value"]][$local_graph_id] = array($graph_template_id, $local_data_id["snmp_query_id"]);
		}
		}
	}
	}

	$sql = "";
	$found = 0;
	if (sizeof($output_array)) {
		$interfaces = array_rekey(db_fetch_assoc("SELECT device_id, ifIndex, $field_name
			FROM mac_track_interfaces
			WHERE device_id=$device_id"), $field_name, array("device_id", "ifIndex"));

		if(sizeof($interfaces)) {
		foreach($interfaces as $key => $data) {
			if (isset($output_array[$key])) {
				foreach($output_array[$key] as $local_graph_id => $graph_details) {
					$sql .= (strlen($sql) ? ", (" : "(") .
						$data["ifIndex"]   . ",'" .
						$key               . "'," .
						$local_graph_id    . ","  .
						$device_id         . ","  .
						$host_id           . ","  .
						$graph_details[0]  . ","  .
						$graph_details[1]  . ",'" .
						$key . "','" . $field_name . "', 1)";
					$found++;
				}
			}
		}
		}
	}

	if ($found) {
		/* let's make sure we mark everthing gone first */
		db_execute("UPDATE mac_track_interface_graphs SET present=0
			WHERE device_id=$device_id AND host_id=$host_id");
		db_execute("INSERT INTO mac_track_interface_graphs
			(ifIndex, ifName, local_graph_id, device_id, host_id, snmp_query_id, graph_template_id, field_value, field_name, present) VALUES " .
			$sql .
			" ON DUPLICATE KEY UPDATE snmp_query_id=VALUES(snmp_query_id), graph_template_id=VALUES(graph_template_id), field_value=VALUES(field_value), field_name=VALUES(field_name), present=VALUES(present)");
		db_execute("DELETE FROM mac_track_interface_graphs WHERE present=0 AND device_id=$device_id AND host_id=$host_id");
	}
}

function get_link_int_value($snmp_oid, $ifIndex, &$snmp_array, &$db_interface, $divisor, $type = "errors", $bits = "32") {
	/* 32bit and 64bit Integer Overflow Value */
	if ($bits == "32") {
		$overflow   = 4294967295;
		/* fudge factor */
		$fudge      = 3000000001;
	}else{
		$overflow = 18446744065119617025;
		/* fudge factor */
		$fudge      = 300000000001;
	}

	/* see if values have been increasing */
	$int_value = 0;
	if (!isset($db_interface[$ifIndex][$snmp_oid])) {
		$int_value = 0;
	}else if (!isset($snmp_array[$ifIndex])) {
		$int_value = 0;
	}else if ($snmp_array[$ifIndex] <> $db_interface[$ifIndex][$snmp_oid]) {
		/* account for 2E32 rollover */
		/* there are two types of rollovers one rolls to 0 */
		/* the other counts backwards.  let's make an educated guess */
		if ($db_interface[$ifIndex][$snmp_oid] > $snmp_array[$ifIndex]) {
			/* errors count backwards from overflow */
			if ($type == "errors") {
				if (($overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex]) < $fudge) {
					$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
				}else{
					$int_value = $db_interface[$ifIndex][$snmp_oid] - $snmp_array[$ifIndex];
				}
			}else{
				$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
			}
		}else{
			$int_value = $snmp_array[$ifIndex] - $db_interface[$ifIndex][$snmp_oid];
		}

		/* account for counter resets */
		$frequency = read_config_option("mt_collection_timing") * 60;
		if ($db_interface[$ifIndex]["ifHighSpeed"] > 0) {
			if ($int_value > ($db_interface[$ifIndex]["ifHighSpeed"] * 1000000 * $frequency * 1.1)) {
				$int_value = $snmp_array[$ifIndex];
			}
		}else{
			if ($int_value > ($db_interface[$ifIndex]["ifSpeed"] * $frequency * 1.1 / 8)) {
				$int_value = $snmp_array[$ifIndex];
			}
		}
	}else{
		$int_value = 0;
	}

	if (!$divisor) {
		return 0;
	}else{
		return $int_value / $divisor;
	}
}

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}

/*	get_generic_dot1q_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_dot1q_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	get_base_dot1qTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}

/*	get_generic_wireless_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_wireless_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, FALSE, FALSE);

	get_base_wireless_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}

/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;

	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_number = 0;
	$ports_active = 0;
	$active_ports = 0;
	$ports_total = 0;

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if (isset($indexes[$i]) && isset($ifInterfaces[$indexes[$i]]["ifType"])) {
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			}
			$ports_total++;
		}

		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;

						$i++;
					}
				}
			}
		}
		}

		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					/* some hubs do not always return a port number in the bridge table.
					   test for it by isset and substiture the port number from the ifTable
					   if it isnt in the bridge table
					*/
					if (isset($bridgePortIfIndexes[$port_key["port_number"]])) {
						$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					}else{
						$brPortIfIndex = @$port_key["port_number"];
					}
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if (($brPortIfType >= 6) &&
					($brPortIfType <= 9) &&
					(!isset($ifInterfaces[$brPortIfIndex]["portLink"]))) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = (isset($port_macs[$port_key["key"]]) ? $port_macs[$port_key["key"]]:'' );
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $new_port_key_array;
	}
}

/*	get_base_wireless_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_wireless_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;

	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_number = 0;
	$ports_active = 0;
	$active_ports = 0;
	$ports_total = 0;

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* get the bridge root port so we don't capture active ports on it */
		$bridge_root_port = @cacti_snmp_get($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.2.7.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort) &&
				($bridge_root_port != $port_number))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"]         = $key;
						$port_key_array[$i]["port_number"] = $port_number;

						$i++;
					}
				}
			}
		}
		}

		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $new_port_key_array;
	}
}

/*	get_base_dot1qTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1qTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;

	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_number = 0;
	$ports_active = 0;
	$active_ports = 0;
	$ports_total = 0;

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* get the bridge root port so we don't capture active ports on it */
		$bridge_root_port = @cacti_snmp_get($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.2.7.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort) &&
				($bridge_root_port != $port_number))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"]         = $key;
						$port_key_array[$i]["port_number"] = $port_number;

						$i++;
					}
				}
			}
		}
		}

		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $new_port_key_array;
	}
}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function mactrack_get_dns_from_ip($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode(".", $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return "ERROR";

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return "ERROR";
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info["timed_out"]) {
		return "timed_out";
	}

	/* more error handling */
	if ($response == "") { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack("s", substr($response, $requestsize+2));

	if ($type[1] == 0x0C00) {
		/* set up our variables */
		$host = "";
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack("c", substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return substr($host, 0, strlen($host) -1);
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . ".";

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return $ip;
	}

	/* error - return the hostname */
	return $ip;
}

/*  get_link_port_status - This function walks an the ip mib for ifIndexes with
  ip addresses aka link ports and then returns that list if ifIndexes with a
  TRUE array value if an IP exists on that ifIndex.
*/
function get_link_port_status(&$device) {
	$return_array = array();

	$walk_array = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.4.20.1.2", $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

	if (sizeof($walk_array)) {
	foreach ($walk_array as $walk_item) {
		$return_array[$walk_item["value"]] = TRUE;
	}
	}

	return $return_array;
}

/*  xform_stripped_oid - This function walks an OID and then strips the seed OID
  from the complete OID.  It returns the stripped OID as the key and the return
  value as the value of the resulting array
*/
function xform_stripped_oid($OID, &$device, $snmp_readstring = "") {
	$return_array = array();

	if (!strlen($snmp_readstring)) {
		$snmp_readstring = $device["snmp_readstring"];
	}

	if ($device["snmp_version"] == "3" && substr_count($snmp_readstring,"vlan-")) {
		$snmp_context = $snmp_readstring;
	} else {
		$snmp_context = $device["snmp_context"];
	}

	$walk_array = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					$OID, $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$snmp_context, $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

	$OID = preg_replace("/^\./", "", $OID);

	$i = 0;

	if (sizeof($walk_array)) {
	foreach ($walk_array as $walk_item) {
		$key = $walk_item["oid"];
		$key = str_replace("iso", "1", $key);
		$key = str_replace($OID . ".", "", $key);
		$return_array[$i]["key"] = $key;
		$return_array[$i]["value"] = $walk_item["value"];

		$i++;
	}
	}

	return array_rekey($return_array, "key", "value");
}

/*  xform_net_address - This function will return the IP address.  If the agent or snmp
  returns a differently formated IP address, then this function will convert it to dotted
  decimal notation and return.
*/
function xform_net_address($ip_address) {
	if (substr_count($ip_address, "Network Address:")) {
		$ip_address = trim(str_replace("Network Address:", "", $ip_address));
	}

	if (substr_count($ip_address, ":") != 0) {
		if (strlen($ip_address) > 11) {
			/* ipv6, don't alter */
		}else{
			$new_address = "";
			while (1) {
				$new_address .= hexdec(substr($ip_address, 0, 2));
				$ip_address = substr($ip_address, 3);
				if (!substr_count($ip_address, ":")) {
					if (strlen($ip_address)) {
						$ip_address = trim($new_address . "." . hexdec(trim($ip_address)));
					}else{
						$ip_address = trim($new_address . $ip_address);
					}
					break;
				}else{
					$new_address .= ".";
				}
			}
		}
	}

	return $ip_address;
}

/*	xform_mac_address - This function will take a variable that is either formated as
  hex or as a string representing hex and convert it to what the mactrack scanning
  function expects.
*/
function xform_mac_address($mac_address) {
	if (strlen($mac_address) == 0) {
		$mac_address = "NOT USER";
	}else{
		if (strlen($mac_address) > 10) { /* return is in ascii */
			$mac_address = str_replace("HEX-00:", "", strtoupper($mac_address));
			$mac_address = str_replace("HEX-:", "", strtoupper($mac_address));
			$mac_address = str_replace("HEX-", "", strtoupper($mac_address));
			$mac_address = trim(str_replace("\"", "", $mac_address));
			$mac_address = str_replace(" ", read_config_option("mt_mac_delim"), $mac_address);
			$mac_address = str_replace(":", read_config_option("mt_mac_delim"), $mac_address);
		}else{ /* return is hex */
			$mac = "";
			for ($j = 0; $j < strlen($mac_address); $j++) {
				$mac .= bin2hex($mac_address[$j]) . read_config_option("mt_mac_delim");
			}
			$mac_address = $mac;
		}
	}

	return $mac_address;
}

/*	xform_standard_indexed_data - This function takes an OID, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  OID and returns the data in array[index] = value format.
*/
function xform_standard_indexed_data($xformOID, &$device, $snmp_readstring = "") {
	/* get raw index data */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	if ($device["snmp_version"] == "3" && substr_count($snmp_readstring,"vlan-")) {
		$snmp_context = $snmp_readstring;
	} else {
		$snmp_context = $device["snmp_context"];
	}

	$xformArray = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					$xformOID, $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$snmp_context, $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		$perPos = strrpos($xformItem["oid"], ".");
		$xformItemID = substr($xformItem["oid"], $perPos+1);
		$xformArray[$i]["oid"] = $xformItemID;
		$i++;
	}
	}

	return array_rekey($xformArray, "oid", "value");
}

/*	xform_dot1q_vlan_associations - This function takes an OID, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  OID and returns the data in array[index] = value format.
*/
function xform_dot1q_vlan_associations(&$device, $snmp_readstring = "") {
	/* get raw index data */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* initialize the output array */
	$output_array = array();

	/* obtain vlan associations */
	$xformArray = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.7.1.2.2.1.2", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		/* peel off the beginning of the OID */
		$key = $xformItem["oid"];
		$key = str_replace("iso", "1", $key);
		$key = str_replace("1.3.6.1.2.1.17.7.1.2.2.1.2.", "", $key);

		/* now grab the VLAN */
		$perPos = strpos($key, ".");
		$output_array[$i]["vlan_id"] = substr($key,0,$perPos);
		/* save the key for association with the dot1d table */
		$output_array[$i]["key"] = substr($key, $perPos+1);
		$i++;
	}
	}

	return array_rekey($output_array, "key", "vlan_id");
}

/*	xform_cisco_workgroup_port_data - This function is specific to Cisco devices that
  use the last two OID values from each complete OID string to represent the switch
  card and port.  The function returns data in the format array[card.port] = value.
*/
function xform_cisco_workgroup_port_data($xformOID, &$device) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
							$xformOID, $device["snmp_version"], $device["snmp_username"],
							$device["snmp_password"], $device["snmp_auth_protocol"],
							$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
							$device["snmp_context"], $device["snmp_port"],
							$device["snmp_timeout"], $device["snmp_retries"], $device["max_oids"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		$perPos = strrpos($xformItem["oid"], ".");
		$xformItem_piece1 = substr($xformItem["oid"], $perPos+1);
		$xformItem_remainder = substr($xformItem["oid"], 0, $perPos);
		$perPos = strrpos($xformItem_remainder, ".");
		$xformItem_piece2 = substr($xformItem_remainder, $perPos+1);
		$xformArray[$i]["oid"] = $xformItem_piece2 . "/" . $xformItem_piece1;

		$i++;
	}
	}

	return array_rekey($xformArray, "oid", "value");
}

/*	xform_indexed_data - This function is similar to other the other xform_* functions
  in that it takes the end of each OID and uses the last $xformLevel positions as the
  index.  Therefore, if $xformLevel = 3, the return value would be as follows:
  array[1.2.3] = value.
*/
function xform_indexed_data($xformOID, &$device, $xformLevel = 1) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
						$xformOID, $device["snmp_version"], $device["snmp_username"],
						$device["snmp_password"], $device["snmp_auth_protocol"],
						$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
						$device["snmp_context"], $device["snmp_port"],
						$device["snmp_timeout"], $device["snmp_retries"], $device["max_oids"]);

	$i = 0;
	$output_array = array();

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		/* break down key */
		$OID = $xformItem["oid"];
		for ($j = 0; $j < $xformLevel; $j++) {
			$perPos = strrpos($OID, ".");
			$xformItem_piece[$j] = substr($OID, $perPos+1);
			$OID = substr($OID, 0, $perPos);
		}

		/* reassemble key */
		$key = "";
		for ($j = $xformLevel-1; $j >= 0; $j--) {
			$key .= $xformItem_piece[$j];
			if ($j > 0) {
				$key .= ".";
			}
		}

		$output_array[$i]["key"] = $key;
		$output_array[$i]["value"] = $xformItem["value"];

		$i++;
	}
	}

	return array_rekey($output_array, "key", "value");
}

/*	db_process_add - This function adds a process to the process table with the entry
  with the device_id as key.
*/
function db_process_add($device_id, $storepid = FALSE) {
    /* store the PID if required */
	if ($storepid) {
		$pid = getmypid();
	}else{
		$pid = 0;
	}

	/* store pseudo process id in the database */
	db_execute("INSERT INTO mac_track_processes (device_id, process_id, status, start_date) VALUES ('" . $device_id . "', '" . $pid . "', 'Running', NOW())");
}

/*	db_process_remove - This function removes a devices entry from the processes
  table indicating that the device is done processing and the next device may start.
*/
function db_process_remove($device_id) {
	db_execute("DELETE FROM mac_track_processes WHERE device_id='" . $device_id . "'");
}

/*	db_update_device_status - This function is used by the scanner to save the status
  of the current device including the number of ports, it's readstring, etc.
*/
function db_update_device_status(&$device, $host_up, $scan_date, $start_time) {
	global $debug;

	list($micro,$seconds) = explode(" ", microtime());
	$end_time = $seconds + $micro;
	$runduration = $end_time - $start_time;

	if ($host_up == TRUE) {
		$update_string = "UPDATE mac_track_devices " .
			"SET ports_total='" . $device["ports_total"] . "'," .
			"device_type_id='" . $device["device_type_id"] . "'," .
			"scan_type = '" . $device ["scan_type"] . "'," .
			"vlans_total='" . $device["vlans_total"] . "'," .
			"ports_active='" . $device["ports_active"] . "'," .
			"ports_trunk='" . $device["ports_trunk"] . "'," .
			"macs_active='" . $device["macs_active"] . "'," .
			"snmp_version='" . $device["snmp_version"] . "'," .
			"snmp_readstring='" . $device["snmp_readstring"] . "'," .
			"snmp_port='" . $device["snmp_port"] . "'," .
			"snmp_timeout='" . $device["snmp_timeout"] . "'," .
			"snmp_retries='" . $device["snmp_retries"] . "'," .
			"max_oids='" . $device["max_oids"] . "'," .
			"snmp_username='" . $device["snmp_username"] . "'," .
			"snmp_password='" . $device["snmp_password"] . "'," .
			"snmp_auth_protocol='" . $device["snmp_auth_protocol"] . "'," .
			"snmp_priv_passphrase='" . $device["snmp_priv_passphrase"] . "'," .
			"snmp_priv_protocol='" . $device["snmp_priv_protocol"] . "'," .
			"snmp_context='" . $device["snmp_context"] . "'," .
			"snmp_sysName=" . db_qstr($device["snmp_sysName"]) . "," .
			"snmp_sysLocation=" . db_qstr($device["snmp_sysLocation"]) . "," .
			"snmp_sysContact=" . db_qstr($device["snmp_sysContact"]) . "," .
			"snmp_sysObjectID='" . $device["snmp_sysObjectID"] . "'," .
			"snmp_sysDescr=" . db_qstr($device["snmp_sysDescr"]) . "," .
			"snmp_sysUptime='" . $device["snmp_sysUptime"] . "'," .
			"snmp_status='" . $device["snmp_status"] . "'," .
			"last_runmessage='" . $device["last_runmessage"] . "'," .
			"last_rundate='" . $scan_date . "'," .
			"last_runduration='" . round($runduration,4) . "' " .
			"WHERE device_id ='" . $device["device_id"] . "'";
	}else{
		$update_string = "UPDATE mac_track_devices " .
			"SET snmp_status='" . $device["snmp_status"] . "'," .
			"device_type_id='" . $device["device_type_id"] . "'," .
			"scan_type = '" . $device ["scan_type"] . "'," .
			"vlans_total='0'," .
			"ports_active='0'," .
			"ports_trunk='0'," .
			"macs_active='0'," .
			"last_runmessage='Device Unreachable', " .
			"last_rundate='" . $scan_date . "'," .
			"last_runduration='" . round($runduration,4) . "' " .
			"WHERE device_id ='" . $device["device_id"] . "'";
	}

	//mactrack_debug("SQL: " . $update_string);

	db_execute($update_string);
}

/*	db_store_device_results - This function stores each of the port results into
  the temporary port results table for future processes once all devices have been
  scanned.
*/
function db_store_device_port_results(&$device, $port_array, $scan_date) {
	global $debug;

	/* output details to database */
	if (sizeof($port_array)) {
	foreach($port_array as $port_value) {
		if (($port_value["port_number"] <> "NOT USER") &&
			(($port_value["mac_address"] <> "NOT USER") && (strlen($port_value["mac_address"]) > 0))){

			$mac_authorized = db_check_auth($port_value["mac_address"]);
			mactrack_debug("MAC Address '" . $port_value["mac_address"] . "' on device '" . $device["device_name"] . "' is " . (strlen($mac_authorized) ? "":"NOT") . " Authorized");

			if (strlen($mac_authorized)) {
				$authorized_mac = 1;
			} else {
				$authorized_mac = 0;
			}

			$insert_string = "REPLACE INTO mac_track_temp_ports " .
				"(site_id,device_id,hostname,device_name,vlan_id,vlan_name," .
				"mac_address,port_number,port_name,scan_date,authorized)" .
				" VALUES ('" .
				$device["site_id"] . "','" .
				$device["device_id"] . "'," .
				db_qstr($device["hostname"]) . "," .
				db_qstr($device["device_name"]) . ",'" .
				$port_value["vlan_id"] . "'," .
				db_qstr($port_value["vlan_name"]) . ",'" .
				$port_value["mac_address"] . "','" .
				$port_value["port_number"] . "'," .
				db_qstr($port_value["port_name"]) . ",'" .
				$scan_date . "','" .
				$authorized_mac . "')";

			db_execute($insert_string);
		}
	}
	}
}

/* db_check_auth - This function checks whether the mac address exists in the mac_track+macauth table
*/
function db_check_auth($mac_address) {
	$check_string = "SELECT mac_id FROM mac_track_macauth WHERE mac_address LIKE '%%" . $mac_address . "%%'";

	$query = db_fetch_cell($check_string);

	return $query;
}

/*	perform_mactrack_db_maint - This utility removes stale records from the database.
*/
function perform_mactrack_db_maint() {
	global $database_default;

	/* remove stale records from the poller database */
	$retention = read_config_option("mt_data_retention");
	if (is_numeric($retention)) {
		$retention_date = date("Y-m-d H:i:s", time() - ($retention *  86400));
		$days           = $retention;
	}else{
		switch ($retention) {
		case "2days":
			$retention_date = date("Y-m-d H:i:s", strtotime("-2 Days"));
			break;
		case "5days":
			$retention_date = date("Y-m-d H:i:s", strtotime("-5 Days"));
			break;
		case "1week":
			$retention_date = date("Y-m-d H:i:s", strtotime("-1 Week"));
			break;
		case "2weeks":
			$retention_date = date("Y-m-d H:i:s", strtotime("-2 Week"));
			break;
		case "3weeks":
			$retention_date = date("Y-m-d H:i:s", strtotime("-3 Week"));
			break;
		case "1month":
			$retention_date = date("Y-m-d H:i:s", strtotime("-1 Month"));
			break;
		case "2months":
			$retention_date = date("Y-m-d H:i:s", strtotime("-2 Months"));
			break;
		default:
			$retention_date = date("Y-m-d H:i:s", strtotime("-2 Days"));
		}

		$days = ceil((time() - strtotime($retention_date)) / 86400);
	}

	db_execute("REPLACE INTO `settings` SET name='mt_data_retention', value='$days'");

	mactrack_debug("Started deleting old records from the main database.");

	$syntax = db_fetch_row("SHOW CREATE TABLE mac_track_ports");
	if (substr_count($syntax["Create Table"], "PARTITION")) {
		$partitioned = true;
	}else{
		$partitioned = false;
	}

	/* delete old syslog and syslog soft messages */
	if ($retention > 0 || $partitioned) {
		if (!$partitioned) {
			db_execute("DELETE QUICK FROM mac_track_ports WHERE scan_date < '$retention_date'");
			db_execute("OPTIMIZE TABLE mac_track_ports");
		}else{
			$syslog_deleted = 0;
			$number_of_partitions = db_fetch_assoc("SELECT *
				FROM `information_schema`.`partitions`
				WHERE table_schema='" . $database_default . "' AND table_name='mac_track_ports'
				ORDER BY partition_ordinal_position");

			$time     = time();
			$now      = date('Y-m-d', $time);
			$format   = date('Ymd', $time);
			$cur_day  = db_fetch_row("SELECT TO_DAYS('$now') AS today");
			$cur_day  = $cur_day["today"];

			$lday_ts  = read_config_option("mactrack_lastday_timestamp");
			$lnow     = date('Y-m-d', $lday_ts);
			$lformat  = date('Ymd', $lday_ts);
			$last_day = db_fetch_row("SELECT TO_DAYS('$lnow') AS today");
			$last_day = $last_day["today"];

			mactrack_debug("There are currently '" . sizeof($number_of_partitions) . "' Device Tracking Partitions, We will keep '$days' of them.");
			mactrack_debug("The current day is '$cur_day', the last day is '$last_day'");

			if ($cur_day != $last_day) {
				db_execute("REPLACE INTO `settings` SET name='mactrack_lastday_timestamp', value='$time'");

				if ($lday_ts != '') {
					cacti_log("MACTRACK: Creating new partition 'd" . $lformat . "'", false, "SYSTEM");
					mactrack_debug("Creating new partition 'd" . $lformat . "'");
					db_execute("ALTER TABLE mac_track_ports REORGANIZE PARTITION dMaxValue INTO (
						PARTITION d" . $lformat . " VALUES LESS THAN (TO_DAYS('$lnow')),
						PARTITION dMaxValue VALUES LESS THAN MAXVALUE)");

					if ($days > 0) {
						$user_partitions = sizeof($number_of_partitions) - 1;
						if ($user_partitions >= $days) {
							$i = 0;
							while ($user_partitions > $days) {
								$oldest = $number_of_partitions[$i];
								cacti_log("MACTRACK: Removing old partition 'd" . $oldest["PARTITION_NAME"] . "'", false, "SYSTEM");
								mactrack_debug("Removing partition '" . $oldest["PARTITION_NAME"] . "'");
								db_execute("ALTER TABLE mac_track_ports DROP PARTITION " . $oldest["PARTITION_NAME"]);
								$i++;
								$user_partitions--;
								$mactrack_deleted++;
							}
						}
					}
				}
			}
		}
	}

	db_execute("REPLACE INTO mac_track_scan_dates (SELECT DISTINCT scan_date FROM mac_track_ports);");
	db_execute("DELETE FROM mac_track_scan_dates WHERE scan_date NOT IN (SELECT DISTINCT scan_date FROM mac_track_ports)");
	mactrack_debug("Finished deleting old records from the main database.");
}

function import_oui_database($type = 'ui', $oui_file = 'http://standards-oui.ieee.org/oui.txt') {
	$oui_alternate = 'https://services13.ieee.org/RST/standards-ra-web/rest/assignments/download/?registry=MA-L&format=txt';
	if ($type != 'ui') {
		html_start_box(__('Device Tracking OUI Database Import Results', 'mactrack'), '100%', '', '1', 'center', '');
		echo '<tr><td>' . __('Getting OUI Database from IEEE', 'mactrack') . '</td></tr>';
	}else{
		echo __('Getting OUI Database from the IEEE', 'mactrack') . "\n";
	}

	$oui_database = file($oui_file);

	if ($type != 'ui') print '<tr><td>';

	if (sizeof($oui_database)) {
		echo __('OUI Database Download from IEEE Complete', 'mactrack') . "\n";
	}else{
		echo __('OUI Database Download from IEEE FAILED', 'mactrack') . "\n";
	}

	if ($type != 'ui') print '</td></tr>';

	if (sizeof($oui_database)) {
		db_execute('UPDATE mac_track_oui_database SET present=0');

		/* initialize some variables */
		$begin_vendor = FALSE;
		$vendor_mac     = '';
		$vendor_name    = '';
		$vendor_address = '';
		$i = 0;
		$sql = '';

		if ($type != 'ui') echo '<tr><td>';

		if (sizeof($oui_database)) {
			foreach ($oui_database as $row) {
				$row = str_replace("\t", ' ', $row);
				if (($begin_vendor) && (strlen(trim($row)) == 0)) {
					if (substr($vendor_address,0,1) == ',') $vendor_address = substr($vendor_address,1);
					if (substr($vendor_name,0,1) == ',')    $vendor_name    = substr($vendor_name,1);

					$sql .= ($sql != '' ? ',':'') . 
						'(' . 
						db_qstr($vendor_mac) . ', ' . 
						db_qstr(ucwords(strtolower($vendor_name))) . ', ' . 
						db_qstr(str_replace("\n", ', ', ucwords(strtolower(trim($vendor_address))))) . ', 1)';

//					db_execute("REPLACE INTO mac_track_oui_database
//						(vendor_mac, vendor_name, vendor_address, present)
//						VALUES ('" . $vendor_mac . "'," .
//						db_qstr(ucwords(strtolower($vendor_name))) . ',' .
//						db_qstr(str_replace("\n", ', ', ucwords(strtolower(trim($vendor_address))))) . ",'1')");

					/* let the user know you are working */
					if ((($i % 1000) == 0) && ($type == 'ui')) {
						echo '.';

						db_execute('REPLACE INTO mac_track_oui_database
							(vendor_mac, vendor_name, vendor_address, present)
							VALUES ' . $sql);

						$sql = '';
					}

					$i++;

					/* reinitialize variables */
					$begin_vendor   = FALSE;
					$vendor_mac     = '';
					$vendor_name    = '';
					$vendor_address = '';
				}else{
					if ($begin_vendor) {
						if (strpos($row, '(base 16)')) {
							$address_start = strpos($row, '(base 16)') + 10;
							$vendor_address .= trim(substr($row,$address_start)) . "\n";
						}else{
							$vendor_address .= trim($row) . "\n";
						}
					}else{
						$vendor_address = '';
					}
				}

				if (substr_count($row, '(hex)')) {
					$begin_vendor = TRUE;
					$vendor_mac = str_replace('-', ':', substr(trim($row), 0, 8));
					$hex_end = strpos($row, '(hex)') + 5;
					$vendor_name= trim(substr($row,$hex_end));
				}
			}
		}

		if ($sql != '') {
			db_execute('REPLACE INTO mac_track_oui_database
				(vendor_mac, vendor_name, vendor_address, present)
				VALUES ' . $sql);
		}

		if ($type != 'ui') print '</td></tr>';

		/* count bogus records */
		$j = db_fetch_cell('SELECT count(*) FROM mac_track_oui_database WHERE present=0');

		/* get rid of old records */
		db_execute('DELETE FROM mac_track_oui_database WHERE present=0');

		/* report some information */
		if ($type != 'ui') print '<tr><td>';
		echo "\n" . __('There were \'%d\' Entries Added/Updated in the database.', $i, 'mactrack');
		if ($type != "ui") print "</td></td><tr><td>";
		echo "\n" . __('There were \'%d\' Records Removed from the database.', $j, 'mactrack') . "\n";
		if ($type != 'ui') print '</td></tr>';

		if ($type != 'ui') html_end_box();
	}
}

function get_netscreen_arp_table($site, &$device) {
	global $debug, $scan_date;

	/* get the atifIndexes for the device */
	$atifIndexes = xform_indexed_data('.1.3.6.1.2.1.3.1.1.1', $device, 6);

	if (sizeof($atifIndexes)) {
		$ifIntcount = 1;
	}else{
		$ifIntcount = 0;
	}

	if ($ifIntcount != 0) {
		$atifIndexes = xform_indexed_data('.1.3.6.1.2.1.4.22.1.1', $device, 5);
	}
	mactrack_debug(__('atifIndexes data collection complete', 'mactrack'));

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atPhysAddress = xform_indexed_data('.1.3.6.1.2.1.4.22.1.2', $device, 5);
	} else {
		$atPhysAddress = xform_indexed_data('.1.3.6.1.2.1.3.1.1.2', $device, 6);
	}

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (sizeof($atPhysAddress)) {
	foreach($atPhysAddress as $atAddress) {
		$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
		$i++;
	}
	}
	mactrack_debug(__('atPhysAddress data collection complete', 'mactrack'));

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atNetAddress = xform_indexed_data('.1.3.6.1.2.1.4.22.1.3', $device, 5);
	} else {
		$atNetAddress = xform_indexed_data('.1.3.6.1.2.1.3.1.1.3', $device, 6);
	}
	mactrack_debug(__('atNetAddress data collection complete', 'mactrack'));

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (sizeof($atifIndexes)) {
	foreach($atifIndexes as $atifIndex) {
		$atEntries[$i]['atifIndex']     = $atifIndex;
		$atEntries[$i]['atPhysAddress'] = $atPhysAddress[$keys[$i]];
		$atEntries[$i]['atNetAddress']  = xform_net_address($atNetAddress[$keys[$i]]);
		$i++;
	}
	}
	mactrack_debug(__('atEntries assembly complete.', 'mactrack'));

	/* output details to database */
	if (sizeof($atEntries)) {
		foreach($atEntries as $atEntry) {
			$insert_string = 'REPLACE INTO mac_track_ips 
				(site_id,device_id,hostname,device_name,port_number,
				mac_address,ip_address,scan_date)
				 VALUES (' .
				$device['site_id']                 . ',' .
				$device['device_id']               . ',' .
				db_qstr($device['hostname'])       . ',' .
				db_qstr($device['device_name'])    . ',' .
				db_qstr($atEntry['atifIndex'])     . ',' .
				db_qstr($atEntry['atPhysAddress']) . ',' .
				db_qstr($atEntry['atNetAddress'])  . ',' .
				db_qstr($scan_date)                . ')';
			db_execute($insert_string);
		}
	}

	/* save ip information for the device */
	$device['ips_total'] = sizeof($atEntries);

	db_execute('UPDATE mac_track_devices SET ips_total =' . $device['ips_total'] . ' WHERE device_id=' . $device['device_id']);

	mactrack_debug(__('HOST: %s, IP address information collection complete', $device['hostname'], 'mactrack'));
}

function mactrack_interface_actions($device_id, $ifName, $show_rescan = TRUE) {
	global $config;

	$row    = '';
	$rescan = '';

	$device = db_fetch_row_prepared('SELECT host_id, disabled FROM mac_track_devices WHERE device_id = ?', array($device_id));

	if ($show_rescan) {
		if (api_user_realm_auth('mactrack_sites.php')) {
			if ($device['disabled'] == '') {
				$rescan = "<img id='r_" . $device_id . '_' . $ifName . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='scan_device_interface(" . $device_id . ",\"" . $ifName . "\")' title='" . __esc('Rescan Device', 'mactrack') . "'>";
			}else{
				$rescan = "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt=''>";
			}
		}else{
			$rescan = "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt=''>";
		}
	}

	if ($device['host_id'] != 0) {
		/* get non-interface graphs */
		$graphs = db_fetch_assoc_prepared('SELECT DISTINCT graph_local.id AS local_graph_id
			FROM mac_track_interface_graphs
			RIGHT JOIN graph_local
			ON graph_local.host_id=mac_track_interface_graphs.host_id
			AND graph_local.id=mac_track_interface_graphs.local_graph_id
			WHERE graph_local.host_id = ? 
			AND mac_track_interface_graphs.device_id IS NULL', array($device['host_id']));

		if (sizeof($graphs)) {
			$url  = $config['url_path'] . 'plugins/mactrack/mactrack_view_graphs.php?action=preview&report=graphs&style=selective&graph_list=';
			$list = '';
			foreach($graphs as $graph) {
				$list .= (strlen($list) ? ',': '') . $graph['local_graph_id'];
			}
			$row .= "<a href='" . htmlspecialchars($url . $list . '&page=1') . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_graphs.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='" . __esc('View Non Interface Graphs', 'mactrack') . "'></a>";
		}else{
			$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_graphs_disabled.gif' alt='' title='" . __esc('No Non Interface Graphs in Cacti', 'mactrack') . "'/>";
		}

		/* get interface graphs */
		$graphs = db_fetch_assoc_prepared('SELECT local_graph_id
			FROM mac_track_interface_graphs
			WHERE host_id = ? AND ifName = ?', array($device['host_id'], $ifName));

		if (sizeof($graphs)) {
			$url  = $config['url_path'] . 'plugins/mactrack/mactrack_view_graphs.php?action=preview&report=graphs&style=selective&graph_list=';
			$list = '';
			foreach($graphs as $graph) {
				$list .= (strlen($list) ? ',': '') . $graph['local_graph_id'];
			}
			$row .= "<a href='" . htmlspecialchars($url . $list . '&page=1') . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_interface_graphs.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='" . __esc('View Interface Graphs', 'mactrack') . "'></a>";
		}else{
			$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt=''>";
		}
	}else{
		$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_graphs_disabled.gif' alt='' title='" . __esc('Device Not in Cacti', 'mactrack') . "'/>";
	}
	$row .= $rescan;

	return $row;
}

function mactrack_format_interface_row($stat) {
	global $config;

	/* we will make a row string */
	$row = '';

	/* calculate a human readable uptime */
	if ($stat['ifLastChange'] == 0) {
		$upTime = __('Since Restart', 'mactrack');
	}else{
		if ($stat['ifLastChange'] > $stat['sysUptime']) {
			$upTime = __('Since Restart', 'mactrack');
		}else{
			$time = $stat['sysUptime'] - $stat['ifLastChange'];
			$days      = intval($time / (60*60*24*100));
			$remainder = $time % (60*60*24*100);
			$hours     = intval($remainder / (60*60*100));
			$remainder = $remainder % (60*60*100);
			$minutes   = intval($remainder / (60*100));
			$upTime    = $days . 'd:' . $hours . 'h:' . $minutes . 'm';
		}
	}

	$row .= "<td nowrap style='width:1%;white-space:nowrap;'>" . mactrack_interface_actions($stat['device_id'], $stat['ifName']) . '</td>';
	$row .= '<td><b>' . $stat['device_name']                     . '</b></td>';
	$row .= '<td>' . strtoupper($stat['device_type'])            . '</td>';
	$row .= '<td><b>' . $stat['ifName']                          . '</b></td>';
	$row .= '<td>' . $stat['ifDescr']                            . '</td>';
	$row .= '<td>' . $stat['ifAlias']                            . '</td>';
	$row .= '<td>' . round($stat['inBound'],1) . ' %'            . '</td>';
	$row .= '<td>' . round($stat['outBound'],1) . ' %'           . '</td>';
	$row .= '<td>' . mactrack_display_Octets($stat['int_ifHCInOctets'])  . '</td>';
	$row .= '<td>' . mactrack_display_Octets($stat['int_ifHCOutOctets']) . '</td>';
	if (get_request_var('totals') == 'true' || get_request_var('totals') == 'on') {
		$row .= '<td>' . $stat['ifInErrors']                     . '</td>';
		$row .= '<td>' . $stat['ifInDiscards']                   . '</td>';
		$row .= '<td>' . $stat['ifInUnknownProtos']              . '</td>';
		$row .= '<td>' . $stat['ifOutErrors']                    . '</td>';
		$row .= '<td>' . $stat['ifOutDiscards']                  . '</td>';
	}else{
		$row .= '<td>' . round($stat['int_ifInErrors'],1)        . '</td>';
		$row .= '<td>' . round($stat['int_ifInDiscards'],1)      . '</td>';
		$row .= '<td>' . round($stat['int_ifInUnknownProtos'],1) . '</td>';
		$row .= '<td>' . round($stat['int_ifOutErrors'],1)       . '</td>';
		$row .= '<td>' . round($stat['int_ifOutDiscards'],1)     . '</td>';
	}
	$row .= '<td>' . ($stat['ifOperStatus'] == 1 ? 'Up':'Down') . '</td>';
	$row .= "<td style='white-space:nowrap;'>" . $upTime        . '</td>';
	$row .= "<td style='white-space:nowrap;'>" . mactrack_date($stat['last_rundate'])        . '</td>';
	return $row;
}

function mactrack_display_Octets($octets) {
	$suffix = '';
	while ($octets > 1024) {
		$octets = $octets / 1024;
		switch($suffix) {
		case '':
			$suffix = 'k';
			break;
		case 'k':
			$suffix = 'm';
			break;
		case 'M':
			$suffix = 'G';
			break;
		case 'G':
			$suffix = 'P';
			break 2;
		default:
			$suffix = '';
			break 2;
		}
	}

	$octets = round($octets,4);
	$octets = substr($octets,0,5);

	return $octets . ' ' . $suffix;
}

function mactrack_rescan($web = FALSE) {
	global $config;

	$device_id = get_request_var('device_id');
	$ifName    = get_request_var('ifName');
	$dbinfo    = db_fetch_row_prepared('SELECT * FROM mac_track_devices WHERE device_id = ?', array($device_id));

	if (sizeof($dbinfo)) {
		if ($dbinfo['disabled'] == '') {
			/* log the trasaction to the database */
			mactrack_log_action(__('Device Rescan \'%s\'', $dbinfo['hostname'], 'mactrack'));

			/* create the command script */
			$command_string = $config['base_path'] . '/plugins/mactrack/mactrack_scanner.php';
			$extra_args     = ' -id=' . $dbinfo['device_id'] . ($web ? ' --web':'');

			/* print out the type, and device_id */
			print 'rescan!!!!' . get_request_var('device_id') . '!!!!' . (strlen($ifName) ? $ifName . '!!!!':'');

			/* add the cacti header */
			print "\n<form action='mactrack_devices.php'>\n";

			html_start_box('', '100%', '', '3', 'center', '');

			print "\t\t\t\t\t<input type='button' onClick='clearScanResults()' value='" . __esc('Clear Results', 'mactrack') . "'>\n";

			/* exeucte the command, and show the results */
			$command = read_config_option('path_php_binary') . ' -q ' . $command_string . $extra_args;
			passthru($command);

			/* close the box */
			html_end_box();
			print "\n</form>\n";
		}
	}
}

function mactrack_site_scan($web = FALSE) {
	global $config, $web;

	$site_id = get_request_var('site_id');
	$dbinfo  = db_fetch_row_prepared('SELECT * FROM mac_track_sites WHERE site_id = ?', array($site_id));

	if (sizeof($dbinfo)) {
		/* log the trasaction to the database */
		mactrack_log_action(__('Site scan \'%s\'', $dbinfo['site_name'], 'mactrack'));

		/* create the command script */
		$command_string = $config['base_path'] . '/plugins/mactrack/poller_mactrack.php';
		$extra_args     = ' --web -sid=' . $dbinfo['site_id'];

		/* print out the type, and device_id */
		print 'sitescan!!!!' . get_request_var('site_id') . '!!!!';

		/* add the cacti header */
		print "\n<form action='mactrack_sites.php'>\n";
		html_start_box('', '100%', '', '3', 'center', '');

		print "\t\t\t\t\t<input type='button' onClick='clearScanResults()' value='" . __esc('Clear Results', 'mactrack') . "'>\n";

		/* exeucte the command, and show the results */
		$command = read_config_option('path_php_binary') . ' -q ' . $command_string . $extra_args;
		passthru($command);

		/* close the box */
		html_end_box();
		print "\n</form>\n";
	}
}

function mactrack_enable() {
	/* ================= input validation ================= */
	get_filter_request_var('device_id');
	/* ==================================================== */

	$dbinfo = db_fetch_row_prepared('SELECT * FROM mac_track_devices WHERE device_id = ?', array(get_request_var('device_id')));

	/* log the trasaction to the database */
	mactrack_log_action(__('Device Enable \'%s\'', $dbinfo['hostname'], 'mactrack'));

	db_execute_prepared("UPDATE mac_track_devices SET disabled='' WHERE device_id = ?", array(get_request_var('device_id')));

	/* get the new html */
	$html = mactrack_format_device_row($dbinfo);

	/* send the response back to the browser */
	print 'enable!!!!' . $dbinfo['device_id'] . '!!!!' . $html;
}

function mactrack_disable() {
	/* ================= input validation ================= */
	get_filter_request_var('device_id');
	/* ==================================================== */

	$dbinfo = db_fetch_row_prepared('SELECT * FROM mactrack_devices WHERE device_id = ?', array(get_request_var('device_id')));

	/* log the trasaction to the database */
	mactrack_log_action(__('Device Disable \'%d\'', $dbinfo['hostname'], 'mactrack'));

	db_execute_prepared("UPDATE mactack_devices SET disabled='on' WHERE device_id = ?", array(get_request_var('device_id')));

	/* get the new html */
	$html = mactrack_format_device_row($stat);

	/* send the response back to the browser */
	print 'disable!!!!' . $stat['device_id'] . '!!!!' . $html;
}

function mactrack_log_action($message) {
	$user = db_fetch_row_prepared('SELECT username, full_name FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	cacti_log('MACTRACK: ' . $message . ", by '" . $user['full_name'] . '(' . $user['username'] . ")'", false, 'SYSTEM');
}

function mactrack_date($date) {
	$year = date('Y');
	return (substr_count($date, $year) ? substr($date,5) : $date);
}

function mactrack_int_row_class($stat) {
	if ($stat['int_errors_present'] == '1') {
		return 'int_errors';
	} elseif ($stat['int_discards_present'] == '1') {
		return 'int_discards';
	} elseif ($stat['ifOperStatus'] == '1' && $stat['ifAlias'] == '') {
		return 'int_up_wo_alias';
	} elseif ($stat['ifOperStatus'] == '0') {
		return 'int_down';
	} else {
		return 'int_up';
	}
}

/* mactrack_create_sql_filter - this routine will take a filter string and process it into a
     sql where clause that will be returned to the caller with a formated SQL where clause
     that can then be integrated into the overall where clause.
     The filter takes the following forms.  The default is to find occurance that match "all"
     Any string prefixed by a "-" will mean "exclude" this search string.  Boolean expressions
     are currently not supported.
   @arg $filter - (string) The filter provided by the user
   @arg $fields - (array) A list of field names to include in the where clause. They can also
     contain the table name in cases where joins are important.
   @returns - (string) The formatted SQL syntax */
function mactrack_create_sql_filter($filter, $fields) {
	$query = '';

	/* field names are required */
	if (!sizeof($fields)) return;

	/* the filter must be non-blank */
	if (!strlen($filter)) return;

	$elements = explode(' ', $filter);

	foreach($elements as $element) {
		if (substr($element, 0, 1) == '-') {
			$filter   = substr($element, 1);
			$type     = 'NOT';
			$operator = 'AND';
		} else {
			$filter   = $element;
			$type     = '';
			$operator = 'OR';
		}

		$field_no = 1;
		foreach ($fields as $field) {
			if (($field_no == 1) && (strlen($query) > 0)) {
				$query .= ') AND (';
			}elseif ($field_no == 1) {
				$query .= '(';
			}

			$query .= ($field_no == 1 ? '':" $operator ") . "($field $type LIKE '%" . $filter . "%')";

			$field_no++;
		}
	}

	return $query . ')';
}

function mactrack_display_hours($value) {
	if ($value == '') {
		return __('N/A', 'mactrack');
	}else if ($value < 60) {
		return __('%d Minutes', round($value,0), 'mactrack');
	}else{
		$value = $value / 60;
		if ($value < 24) {
			return __('%d Hours', round($value,0), 'mactrack');
		}else{
			$value = $value / 24;
			if ($value < 7) {
				return __('%d Days', round($value,0), 'mactrack');
			}else{
				$value = $value / 7;
				return __('%d Weeks', round($value,0), 'mactrack');
			}
		}
	}
}

function mactrack_display_stats() {
	/* check if scanning is running */
	$processes = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes');
	$frequency = read_config_option('mt_collection_timing', TRUE) * 60;
	$mactrack_stats = read_config_option('stats_mactrack', TRUE);
	$time  = __('Not Recorded', 'mactrack');
	$proc  = __('N/A', 'mactrack');
	$devs  = __('N/A', 'mactrack');
	if ($mactrack_stats != '') {
		$stats = explode(' ', $mactrack_stats);

		if (sizeof($stats == 3)) {
			$time = explode(':', $stats[0]);
			$time = $time[1];

			$proc = explode(':', $stats[1]);
			$proc = $proc[1];

			$devs = explode(':', $stats[2]);
			$devs = $devs[1];
		}
	}

	if ($processes > 0) {
		$message = __('Status: Running, Processes: %d, Progress: %s, LastRuntime: %f', $processes, read_config_option('mactrack_process_status', TRUE), round($time,1), 'mactrack');
	}else{
		$message = __('Status: Idle, LastRuntime: %f seconds, Processes: %d processes, Devices: %d, Next Run Time: %s', 
			round($time,1), $proc , $devs, 
			date('Y-m-d H:i:s', strtotime(read_config_option('mt_scan_date', TRUE)) + $frequency), 'mactrack');
	}

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr>';
	print '<td>' . __('Scanning Rate: Every %s', mactrack_display_hours(read_config_option('mt_collection_timing')), 'mactrack') . ', ' . $message . '</td>';
	print '</tr>';

	html_end_box();
}

function mactrack_legend_row($class, $text) {
	print "<td width='16.67%' class='$class' style='text-align:center;;'>$text</td>";
}

function mactrack_redirect() {
	/* set the default tab */
    get_filter_request_var('report', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));

	load_current_session_value('report', 'sess_mt_report', 'devices');
	$current_tab = get_nfilter_request_var('report');

	$current_page = str_replace('mactrack_', '', str_replace('view_', '', str_replace('.php', '', get_current_page())));
	$current_dir  = dirname(get_current_page(false));

	if ($current_page != $current_tab) {
		header('Location: ' . $current_dir . '/mactrack_view_' . $current_tab . '.php');
	}
}

function mactrack_format_device_row($device, $actions=false) {
	global $config, $mactrack_device_types;

	/* viewer level */
	if ($actions) {
		$row = "<a href='" . htmlspecialchars($config['url_path'] . 'plugins/mactrack/mactrack_interfaces.php?device_id=' . $device['device_id'] . '&issues=0&page=1') . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_interfaces.gif' alt='' title='" . __('View Interfaces', 'mactrack') . "'></a>";

		/* admin level */
		if (api_user_realm_auth('mactrack_sites.php')) {
			if ($device['disabled'] == '') {
				$row .= "<img id='r_" . $device['device_id'] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onClick='scan_device(" . $device['device_id'] . ")' title='" . __('Rescan Device', 'mactrack') . "'>";
			}else{
				$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt=''>";
			}
		}

		print "<td style='width:40px;'>" . $row . "</td>";
	}

	form_selectable_cell(filter_value($device['device_name'], get_request_var('filter'), "mactrack_devices.php?action=edit&device_id=" . $device['device_id']), $device['device_id']);
	form_selectable_cell($device['site_name'], $device['device_id']);
	form_selectable_cell(get_colored_device_status(($device['disabled'] == 'on' ? true : false), $device['snmp_status']), $device['device_id']);
	form_selectable_cell(filter_value($device['hostname'], get_request_var('filter')), $device['device_id']);
	form_selectable_cell(($device['device_type'] == '' ? __('Not Detected', 'mactrack') : $device['device_type']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '1' ? __('N/A', 'mactrack') : $device['ips_total']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : $device['ports_total']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : $device['ports_active']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : $device['ports_trunk']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : $device['macs_active']), $device['device_id']);
	form_selectable_cell(number_format($device['last_runduration'], 1), $device['device_id']);
	form_checkbox_cell($device['device_name'], $device['device_id']);
	form_end_row();

}

function mactrack_mail($to, $from, $fromname, $subject, $message, $headers = '') {
	global $config;
	include_once($config['base_path'] . '/plugins/settings/include/mailer.php');

	$subject = trim($subject);

	$message = str_replace('<SUBJECT>', $subject, $message);

	$how = read_config_option('settings_how');
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host     = read_config_option('settings_smtp_host');
		$smtp_port     = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from     = read_config_option('mt_from_email');
		$fromname = read_config_option('mt_from_name');
		if ($from == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'thewitness@cacti.net';
			}
		}
		if ($fromname == '') {
			$fromname = 'Cacti';
		}

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			cacti_log('ERROR: ' . $Mailer->error(), true, 'MACTRACK');
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			cacti_log('ERROR: ' . $Mailer->error(), true, 'MACTRACK');
			return $Mailer->error();
		}
	}

	if ($to == '') {
		return 'Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert Email</b> setting.';
	}
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set('To', $t)) {
			cacti_log('ERROR: ' . $Mailer->error(), true, 'MACTRACK');
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '') {
		$wordwrap = 76;
	}else if ($wordwrap > 9999) {
		$wordwrap = 9999;
	}else if ($wordwrap < 0) {
		$wordwrap = 76;
	}

	$Mailer->Config['Mail']['WordWrap'] = $wordwrap;

	if (! $Mailer->header_set('Subject', $subject)) {
		cacti_log('ERROR: ' . $Mailer->error(), true, 'MACTRACK');
		return $Mailer->error();
	}

	$text = array('text' => '', 'html' => '');
	$text['html'] = $message . '<br>';
	$text['text'] = strip_tags(str_replace('<br>', "\n", $message));

	$v = mactrack_version();
	$Mailer->header_set('X-Mailer', 'Cacti-MacTrack-v' . $v['version']);
	$Mailer->header_set('User-Agent', 'Cacti-MacTrack-v' . $v['version']);

	if ($Mailer->send($text) == false) {
		cacti_log('ERROR: ' . $Mailer->error(), true, 'MACTRACK');
		return $Mailer->error();
	}

	return '';
}

function mactrack_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs_mactrack = array(
		'sites'      => __('Sites', 'mactrack'),
		'devices'    => __('Devices', 'mactrack'),
		'ips'        => __('IP Ranges', 'mactrack'),
		'arp'        => __('IP Address', 'mactrack'),
		'macs'       => __('MAC Address', 'mactrack'),
		'interfaces' => __('Interfaces', 'mactrack'),
		'graphs'     => __('Graphs', 'mactrack')
	);

	/* set the default tab */
	$current_tab = get_request_var('report');

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (sizeof($tabs_mactrack)) {
		foreach ($tabs_mactrack as $tab_short_name => $tab_name) {
			print '<li><a class="tab' . (($tab_short_name == $current_tab) ? ' selected"' : '"') . " href='" . htmlspecialchars($config['url_path'] .
				'plugins/mactrack/mactrack_view_' . $tab_short_name . '.php?' .
				'report=' . $tab_short_name) .
				"'>$tab_name</a></li>\n";
		}
	}

	print "</ul></nav></div>\n";
}

function mactrack_get_vendor_name($mac) {
	$vendor_mac = substr($mac,0,8);

	$vendor_name = db_fetch_cell_prepared('SELECT vendor_name FROM mac_track_oui_database WHERE vendor_mac = ?', array($vendor_mac));

	if (strlen($vendor_name)) {
		return $vendor_name;
	}else{
		return __('Unknown', 'mactrack');
	}
}

function mactrack_site_filter($page = 'mactrack_sites.php') {
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
						<?php print __('Sites', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'mactrack');?></option>
							<?php
								if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
								}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='detail' <?php if (get_request_var('detail') == 'true') print ' checked="true"';?> onClick='applyFilter()'>
					</td>
					<td>
						<label for='detail'><?php print __('Show Device Details', 'mactrack');?></label>
					</td>
					<td>
						<input type='submit' id='go' value='<?php print __('Go', 'mactrack');?>'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear', 'mactrack');?>'>
					</td>
					<td>
						<input type='button' id='export' value='<?php print __('Export', 'mactrack');?>'>
					</td>
				</tr>
			<?php
			if (!(get_request_var('detail') == 'false')) { ?>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT * FROM mac_track_sites ORDER BY site_name');
							if (sizeof($sites) > 0) {
							foreach ($sites as $site) {
								print '<option value="' . $site['site_id'] . '"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('SubType', 'mactrack');?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<?php
							$device_types = db_fetch_assoc('SELECT DISTINCT mac_track_device_types.device_type_id,
								mac_track_device_types.description, mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices 
								ON mac_track_device_types.device_type_id = mac_track_devices.device_type_id
								ORDER BY mac_track_device_types.description');

							if (sizeof($device_types)) {
							foreach ($device_types as $device_type) {
								print '<option value="' . $device_type['device_type_id'] . '"'; if (get_request_var('device_type_id') == $device_type['device_type_id']) { print ' selected'; } print '>' . $device_type['description'] . ' (' . $device_type['sysDescr_match'] . ')</option>';
							}
							}
							?>
						</select>
					</td>
				</tr>
			<?php }?>
			</table>
			<?php
			if (get_request_var('detail') == 'false') { ?>
			<input type='hidden' id='device_type_id' value='-1'>
			<input type='hidden' id='site_id' value='-1'>
			<?php }?>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?header=false';
				strURL += '&report=sites';
				strURL += '&device_type_id=' + $('#device_type_id').val();
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&detail=' + $('#detail').is(':checked');
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?export=true';
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
