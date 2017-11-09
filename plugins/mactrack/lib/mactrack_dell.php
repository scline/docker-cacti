<?php
/* This file was modified from the default dot1q functions provided in mactrack_functions.php
	 specifically written and tested to work with Dell 5300 and 3400 series switches
*/

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_dell_dot1q_switch_ports");


/*	get_dell_dot1q_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_dell_dot1q_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, TRUE);

	/* sanitize ifInterfaces by removing text from ifType field */
	if (sizeof($ifInterfaces)) {
	foreach($ifInterfaces as $key => $tempInterfaces){
		preg_match("/[0-9]{1,3}/", $tempInterfaces["ifType"], $newType);
		$ifInterfaces[$key]["ifType"] = $newType[0];
	}
	}

	get_base_dell_dot1qFdb_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}
/*	get_base_dell_dot1qFdb_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
  This was mainly copied from the default dot1q function in mactrack_functions.php
  but was modified to work with Dell switches
*/
function get_base_dell_dot1qFdb_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
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
	$snmp_readstring = $device["snmp_readstring"];

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	/* Sanitize active ports array, removing text junk as the dell's don't return just a plain numeric value */
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $key => $tempPorts){
		preg_match("/[0-9]{1,3}/",$tempPorts,$newStatus);
		$active_ports_array[$key]=$newStatus[0];
	}
	}

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
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
		print("\nINFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
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
		/* Sanitize port_status array, removing text junk as the dell's don't return just a plain numeric value*/
		if (sizeof($port_status)) {
		foreach($port_status as $key => $tempStatus){
			preg_match("/[0-9]{1,3}/",$tempStatus,$newStatus);
			$port_status[$key]=$newStatus[0];
		}
		}
		//print_r($port_status);
		/* get device active port numbers
		This is the OID that shows the mac address as the index and the port as the value*/
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
				if (sizeof($bridgePortIfIndexes) != 0) {
					$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"] = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"] = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$new_port_key_array[$i]["port_name"] = $ifInterfaces[$port_key["port_number"]]["ifAlias"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_mac) {
				$new_port_key_array[$key]["mac_address"] = dell_mac_address_convert($port_mac["key"]);
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}

			/* Map Vlan names to pvid's */
			$vlan_names = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.4.3.1.1", $device, $snmp_readstring);


			/* map pvid's to ports with vlan names*/
			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port){
				$temp_array = explode(".", $port["key"]);
				$new_port_key_array[$key]["vlan_id"] = $temp_array[0];
				$new_port_key_array[$key]["vlan_name"] = @$vlan_names[$new_port_key_array[$key]["vlan_id"]];
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
		}elseif (sizeof($new_port_key_array) > 0) {
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

function dell_mac_address_convert($mac_address) {
	if (strlen($mac_address) == 0) {
		$mac_address = "NOT USER";
	}elseif (strlen($mac_address) > 10) { /* return is in ascii */
		$mac_address = trim(str_replace("\"", "", $mac_address));
		$mac_address = str_replace(".", read_config_option("mt_mac_delim"), $mac_address);
		$mac_address = str_replace(":", read_config_option("mt_mac_delim"), $mac_address);
		$mac = explode(read_config_option("mt_mac_delim"),$mac_address);
		foreach ($mac as $key => $mac_item){
			$mac_item = dechex($mac_item);
			if(strlen($mac_item) < 2){
				$mac_item = "0".$mac_item;
			}
			$mac[$key] = strtoupper($mac_item);
		}
		$new_mac = "";
		for($i = 1; $i < 6; $i++){
			$new_mac .= $mac[$i].read_config_option("mt_mac_delim");
		}
		$new_mac .= $mac[$i];
		$mac_address = $new_mac;
	}

	return $mac_address;
}
