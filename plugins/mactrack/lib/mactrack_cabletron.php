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

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_cabletron_switch_ports");
array_push($mactrack_scanning_functions, "get_repeater_rev4_ports");

function get_cabletron_switch_ports($site, &$device, $lowPort, $highPort) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;
	$device["vlans_total"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, FALSE, FALSE);

	$securefast_marker = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
							".1.3.6.1.4.1.52.4.2.4.2.1.1.1.1.1.1.1", $device["snmp_version"],
							$device["snmp_username"], $device["snmp_password"], $device["snmp_auth_protocol"],
							$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
							$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);
	mactrack_debug("Cabletron securefast marker obtained");

	if (empty($securefast_marker)) {
		get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);
	}else{
		get_base_sfps_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);
	}

	return $device;
}

function get_base_sfps_ports($site, &$device, &$ifInterfaces, $snmp_readstring, $store_to_db, $lowPort, $highPort) {
	global $debug, $scan_date;

	/* initialize variables */
	$port_number = 0;
	$ports_active = 0;
	$ports_total = 0;

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	/* get the ignore ports list */
	$ignore_ports = port_list_to_array($device["ignorePorts"]);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		if (($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) {
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
	}

	/* now obtain securefast port information */
	$sfps_A_ports = xform_indexed_data(".1.3.6.1.4.1.52.4.2.4.2.2.3.6.1.1.6", $device, 3);
	$sfps_A_mac_addresses = xform_indexed_data(".1.3.6.1.4.1.52.4.2.4.2.2.3.6.1.1.8", $device, 3);

	$sfps_A_keys = array_keys($sfps_A_ports);
	$sfps_A_size = sizeof($sfps_A_ports);

	$j = 0;
	$i = 0;
	while($j < $sfps_A_size) {
		$port_number = $sfps_A_ports[$sfps_A_keys[$j]];
		$mac_address = $sfps_A_mac_addresses[$sfps_A_keys[$j]];

		if (($port_number >= $lowPort) && ($port_number <= $highPort)) {
			if (!in_array($port_number, $ignore_ports)) {
				$temp_port_A_array[$i]["port_number"] = $port_number;
				$temp_port_A_array[$i]["mac_address"] = xform_mac_address($mac_address);
				$i++;
			}
		}
		$j++;
	}

	$j = 0;
	$port_array = array();
	for ($i=0;$i < sizeof($temp_port_A_array);$i++) {
		$port_array[$temp_port_A_array[$i]["port_number"]]["vlan_id"] = "N/A";
		$port_array[$temp_port_A_array[$i]["port_number"]]["vlan_name"] = "N/A";
		$port_array[$temp_port_A_array[$i]["port_number"]]["port_name"] = "N/A";
		$port_array[$temp_port_A_array[$i]["port_number"]]["port_number"] = $temp_port_A_array[$i]["port_number"];
		$port_array[$temp_port_A_array[$i]["port_number"]]["mac_address"] = $temp_port_A_array[$i]["mac_address"];
	}

	if ($store_to_db) {
		if (sizeof($port_array) > 0) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($port_array);
			db_store_device_port_results($device, $port_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $port_array;
	}

}

/*	get_repeater_snmp_readstring - Cabletron SEHI's are quite odd.  They have potentially
	5 distinct snmp_readstrings for each of 5 agent structures.  If the read_string
	for the port information is different than sysObjectID, then let's find it and
	set it.
*/
function get_repeater_snmp_readstring(&$device) {
	$active_ports = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
							".1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0", $device["snmp_version"],
							$device["snmp_username"], $device["snmp_password"],
							$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
							$device["snmp_priv_protocol"], $device["snmp_context"],
							$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

	if (strlen($active_ports) > 0) {
		mactrack_debug("Repeater readstring is: " . $device["snmp_readstring"]);
		return $device["snmp_readstring"];
	}else{
		/* loop through the default and then other common for the correct answer */
		$read_strings = explode(":",$device["snmp_readstrings"]);

		if (sizeof($read_strings)) {
		foreach($read_strings as $snmp_readstring) {
			$active_ports = @cacti_snmp_get($device["hostname"], $snmp_readstring,
								".1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0", $device["snmp_version"],
								$device["snmp_username"], $device["snmp_password"],
								$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
								$device["snmp_priv_protocol"], $device["snmp_context"],
								$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

			if (strlen($active_ports) > 0) {
				mactrack_debug("Repeater readstring is: " . $snmp_readstring);
				return $snmp_readstring;
			}
		}
		}
	}

	return "";
}

function get_repeater_rev4_ports($site, &$device, $lowPort, $highPort) {
	global $debug, $scan_date;

	$snmp_readstring = get_repeater_snmp_readstring($device);

	if (strlen($snmp_readstring) > 0) {
		$ports_active = @cacti_snmp_get($device["hostname"], $snmp_readstring,
								".1.3.6.1.4.1.52.4.1.1.1.4.1.1.5.0", $device["snmp_version"],
								$device["snmp_username"], $device["snmp_password"],
								$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
								$device["snmp_priv_protocol"], $device["snmp_context"],
								$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]) - 1;

		$ports_total = @cacti_snmp_get($device["hostname"], $snmp_readstring,
								".1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0", $device["snmp_version"],
								$device["snmp_username"], $device["snmp_password"],
								$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
								$device["snmp_priv_protocol"], $device["snmp_context"],
								$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]) - 1;

		/* get the ignore ports list */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", ACTIVE PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["vlans_total"] = 0;
		$device["ports_total"] = $ports_total;

		if ($ports_active >= 0) {
			$device["ports_active"] = $ports_active;
		}else{
			$device["ports_active"] = 0;
		}

		if ($device["snmp_version"] == 2) {
			$snmp_version = "2c";
		}else{
			$snmp_version = $device["snmp_version"];
		}

		$port_keys = array();
		$return_array = array();
		$new_port_key_array = array();
		$port_number = 0;
		$nextOID = ".1.3.6.1.4.1.52.4.1.1.1.4.1.5.2.1.2";
		$to = ceil($device["snmp_timeout"]/1000);

		$i = 0;
		$previous_port = 0;
		while (1) {
			$exec_string = trim(read_config_option("path_snmpgetnext") .
				" -c " . $snmp_readstring .
				" -OnUQ -v " . $snmp_version .
				" -r " . $device["snmp_retries"] .
				" -t " . $to . " " .
				$device["hostname"] . ":" . $device["snmp_port"] . " " .
				$nextOID);

			exec($exec_string, $return_array, $return_code);

			list($nextOID, $port_number) = explode("=", $return_array[$i]);

			if ($port_number < $previous_port) {
				break;
			}

			if (($port_number <= $highPort) && ($port_number >= $lowPort)) {
				if (!in_array($port_number, $ignore_ports)) {
					/* set defaults for devices in case they don't have/support vlans */
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["port_name"] = "N/A";

					$new_port_key_array[$i]["key"] = trim(substr($nextOID,36));
					$new_port_key_array[$i]["port_number"] = trim(strtr($port_number," ",""));
				}

				$previous_port = trim(strtr($port_number," ",""));
			}else{
				break;
			}


			mactrack_debug("CMD: " . $exec_string . ", PORT: " . $port_number);
			$i++;
			$port_number = "";
		}

		if (sizeof($new_port_key_array) > 0) {
			/* map mac address */
			$i=0;
			foreach ($new_port_key_array as $port_key) {
				$OID = ".1.3.6.1.4.1.52.4.1.1.1.4.1.5.2.1.1." . $port_key["key"];

				$mac_address = @cacti_snmp_get($device["hostname"], $snmp_readstring,
								$OID, $device["snmp_version"], $device["snmp_username"],
								$device["snmp_password"], $device["snmp_auth_protocol"],
								$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
								$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

				$new_port_key_array[$i]["mac_address"] = xform_mac_address($mac_address);

				mactrack_debug("OID: " . $OID . ", MAC ADDRESS: " . $new_port_key_array[$i]["mac_address"]);
				$i++;
			}

			$device["last_runmessage"] = "Data collection completed ok";
		}else{
			mactrack_debug("INFO: The following device has no active ports: " . $site . "/" . $device["hostname"] . "\n");
			$device["last_runmessage"] = "Data collection completed ok";
		}
	}else{
		mactrack_debug("ERROR: Could not determine snmp_readstring for host: " . $site . "/" . $device["hostname"] . "\n");
		$device["snmp_status"] = HOST_ERROR;
		$device["last_runmessage"] = "ERROR: Could not determine snmp_readstring for host.";
	}

	if(!$debug) {
		print(" - Complete\n");
	}

	$device["ports_active"] = $ports_active;
	$device["macs_active"] = sizeof($new_port_key_array);
	db_store_device_port_results($device, $new_port_key_array, $scan_date);

	return $device;
}
