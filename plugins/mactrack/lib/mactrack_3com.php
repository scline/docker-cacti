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
*/


/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_3Com_dot1dTpFdbEntry_ports");


/* complete_3com_ifName
	for buggy 3com SSII 1100 : they dont have ifName use ifDescr but it contains ':'
	making it unusable for "ignore port"
	so transform it in PortN/M where N is stackId and M portnumber
*/

function complete_3com_ifName(&$device, &$ifIndexes) {
	mactrack_debug("Start complete_3com_ifName");
	// device without ifName detection
	foreach($ifIndexes as $ifidx ) {
		if ($ifidx["ifName"] != "") {
			return;
		}else{
			break;
		}
	}
	$pattern      = '/RMON:V(\d+) Port (\d+) on Unit (\d+)/i';
	$pattern2     = '/RMON Port (\d+) on Unit (\d+)/i';
	$pattern3     = '/RMON:10\/100 Port (\d+) on Unit (\d+)/i';
	$replacement  = 'Port${3}/${2}';
	$replacement2 = 'Port${2}/${1}';
	$replacement3 = 'Port${2}/${1}';

	
	$local_graph_id = db_fetch_assoc("SELECT local_graph_id FROM mac_track_interface_graphs WHERE device_id=".$device['device_id']);
	sort($local_graph_id);
	// Get ifDescr and Format it
	$i=0;
	$device_descr_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.2",$device);
	if (sizeof($device_descr_array)) {
	foreach($device_descr_array as $key => $ifd ) {
		if($ifIndexes[$key]["ifName"] == "" ){
			$ifdesc = $device_descr_array[$key] ;
			$ifdesc = preg_replace($pattern, $replacement, $ifdesc);
			$ifdesc = preg_replace($pattern2, $replacement2, $ifdesc);
			$ifdesc = preg_replace($pattern3, $replacement3, $ifdesc);
			$ifIndexes[$key]["ifName"] = $ifdesc ;
			db_execute("UPDATE mac_track_interfaces SET ifName='".$ifdesc ."' WHERE device_id=".$device['device_id']." AND ifIndex=".$key."");
			if($i<sizeof($local_graph_id)){
				db_execute("UPDATE mac_track_interface_graphs SET ifIndex=".$key.", ifName='".$ifdesc ."' WHERE device_id=".$device['device_id']." AND local_graph_id=".$local_graph_id[$i]['local_graph_id']);
			}
			$i++;
		}
	}
	}
}

/* get_3Com_dot1dTpFdbEntry_ports
   same as get_dot1dTpFdbEntry_ports whith small modification for 3com devices
*/
function get_3Com_dot1dTpFdbEntry_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	complete_3com_ifName($device, $ifInterfaces);
	
	get_3Com_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}


/* same as get_base_dot1dTpFdbEntry_ports -
   but add iftype 117 gbit ethernet.
*/
function get_3Com_base_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;
	mactrack_debug("Start get_3Com_base_dot1dTpFdbEntry_ports");
	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_descr = array();
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
	/* get the consol port */
	$link_ports = get_link_port_status($device);

	$i = 0;
	foreach($active_ports_array as $port_info) {
		if (($ifInterfaces[$indexes[$i]]["ifType"] == 6)&&($ifInterfaces[$indexes[$i]]["linkPort"] != 1)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
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
		//$device["ignorePorts"] = $device["ignorePorts"].":Port1/50";
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {
				$ifname = $ifInterfaces[$bridgePortIfIndexes[$port_number]]["ifName"];
				if (!in_array($ifname, $ignore_ports)) {
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

				if (((($brPortIfType >= 6) &&
					($brPortIfType <= 9)) || $brPortIfType == 117 ) &&
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
					$new_port_key_array[$i]["port_name"] = $ifInterfaces[$brPortIfIndex]["ifName"];
					
					$i++;
				}
			}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array) > 0) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.1", $device, $snmp_readstring);

			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}

			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
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
