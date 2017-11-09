<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2009 Susanin (gthe)                                       |
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
*/


/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_linux_switch_ports");

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_linux_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get the ifTypes for the device */
	$ifTypes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.3", $device);
	mactrack_debug("ifTypes data collection complete.");

	/* get the ifNames for the device */
	$ifNames = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.2", $device);
	mactrack_debug("ifNames data collection complete.");
	
	foreach($ifNames as $ifkey => $value) {
		if (substr_count($value, ".") > 0 ){
			$ifVlan[$ifkey] = substr($value, strpos($value, ".")+1);
		}else{
			$ifVlan[$ifkey] = "N/A";
		}
	}
	
	
	/* get ports that happen to be link ports */
	$link_ports = get_link_port_status($device);
	mactrack_debug("ipAddrTable scanning for link ports data collection complete.");

	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["ifIndex"] = $ifIndex;
		$ifInterfaces[$ifIndex]["ifName"] = @$ifNames[$ifIndex];
		$ifInterfaces[$ifIndex]["ifType"] = convert_port_state_data($ifTypes[$ifIndex]);  
		$ifInterfaces[$ifIndex]["vlan_id"] = $ifVlan[$ifIndex];
		//$ifInterfaces[$ifIndex]["linkPort"] = @$link_ports[$ifIndex];
	}
	mactrack_debug("ifInterfaces assembly complete.");

	get_linux_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}

/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_linux_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
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
    foreach($active_ports_array as $port_info) {
        $port_info = convert_port_state_data($port_info);
        if (($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
            ($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) {
            if ($port_info == 1) {
                $ports_active++;
            }
            $ports_total++;
        }
        $i++;
    }

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL INTERFACES: " . $ports_total . ", OPER INTERFACES: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.4", $device, $snmp_readstring);
        foreach ($port_status as $key_status => $status_value) { 
            $port_status[$key_status]=convert_port_state_data($status_value);
        }
        
		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.1", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {

				if (!in_array($port_number, $ignore_ports)) {
					if (@$port_status[$key] == "3") {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;

						$i++;
					}
				}
			}
		}

		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes) != 0) {
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
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"] = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"] = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$new_port_key_array[$i]["vlan_id"] = @$ifInterfaces[$port_key["port_number"]]["vlan_id"];
					$i++;
				}
			}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array) > 0) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.2", $device, $snmp_readstring);

			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}

			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				//mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
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

function convert_port_state_data($old_port_type) {
		if (substr_count($old_port_type, "(") > 0) {
			$pos1 = strpos($old_port_type, "(");
			$pos2 = strpos($old_port_type, ")");
			$rezult = substr($old_port_type, $pos1+1, $pos2-$pos1-1);
		} else{
			$rezult=$old_port_type;
		}
		
return $rezult;  
}

?>
