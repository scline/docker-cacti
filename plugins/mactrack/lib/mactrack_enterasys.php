<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2005 Mark Webster                                         |
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
array_push($mactrack_scanning_functions, "get_enterasys_switch_ports");

function get_enterasys_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;
	$device["vlans_total"] = 0;

	/* get VLAN information */
	$vlan_ids = xform_standard_indexed_data(".1.3.6.1.4.1.52.4.1.2.16.4.4.1.2", $device);

	/* get VLAN Trunk status */
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.52.4.1.2.16.3.1.1.5.4", $device);
	$device["vlans_total"] = sizeof($vlan_trunkstatus);
	mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids)) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get the ifTypes for the device */
	$ifTypes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.3", $device);
	mactrack_debug("ifTypes data collection complete.");

	/* get the ifNames for the device */
	$ifNames = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.1", $device);
	mactrack_debug("ifNames data collection complete.");

	/* get ports that happen to be link ports */
	$link_ports = get_link_port_status($device);
	mactrack_debug("ipAddrTable scanning for link ports data collection complete.");

	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["ifIndex"] = $ifIndex;
		$ifInterfaces[$ifIndex]["ifName"] = @$ifNames[$ifIndex];
		$ifInterfaces[$ifIndex]["ifType"] = mactrack_strip_alpha($ifTypes[$ifIndex]);
		$ifInterfaces[$ifIndex]["linkPort"] = @$link_ports[$ifIndex];
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];
	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	/* calculate the number of end user ports */
	if (sizeof($ifTypes)) {
	foreach ($ifTypes as $ifType) {
		$ifType = mactrack_strip_alpha($ifType);

		if (($ifType >= 6) && ($ifType <= 9)) {
			$device["ports_total"]++;
		}
	}
	}
	mactrack_debug("Total Ports = " . $device["ports_total"]);

	/* calculate the number of trunk ports */
	if (sizeof($ifIndexes)) {
	foreach ($ifIndexes as $ifIndex) {
		if ($ifInterfaces[$ifIndex]["trunkPortState"] == 1) {
			$device["ports_trunk"]++;
		}
	}
	}
	mactrack_debug("Total Trunk Ports = " . $device["ports_trunk"]);

	/* get VLAN details */
	$i = 0;
	if (sizeof($vlan_ids)) {
	foreach ($vlan_ids as $vlan_id => $vlan_name) {
		$active_vlans[$i]["vlan_id"] = $vlan_id;
		$active_vlans[$i]["vlan_name"] = $vlan_name;
		$active_vlans++;
		$i++;
	}
	}

	if (sizeof($active_vlans)) {
		/* get the port status information */
		$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE);
		$port_vlan_data = xform_standard_indexed_data(".1.3.6.1.4.1.52.4.1.2.16.3.1.1.3.4", $device);

		$i = 0;
		$j = 0;
		$port_array = array();

		foreach ($port_results as $port_result) {
			mactrack_debug("Got Here");
			$ifIndex  = $port_result["port_number"];
			$ifType   = mactrack_strip_alpha($ifTypes[$ifIndex]);
			$ifName   = $ifNames[$ifIndex];
			$portName = $ifName;
			$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];

			/* only output legitimate end user ports */
			if (($ifType >= 6) && ($ifType <= 9)) {
				$port_array[$i]["vlan_id"]     = @$port_vlan_data[$port_result["port_number"]];
				$port_array[$i]["vlan_name"]   = @$vlan_ids[$port_array[$i]["vlan_id"]];
				$port_array[$i]["port_number"] = @$port_result["port_number"];
				$port_array[$i]["port_name"]   = $portName;
				$port_array[$i]["mac_address"] = xform_mac_address($port_result["mac_address"]);
				$device["ports_active"]++;

				mactrack_debug("VLAN: " . $port_array[$i]["vlan_id"] . ", " .
					"NAME: " . $port_array[$i]["vlan_name"] . ", " .
					"PORT: " . $ifIndex . ", " .
					"NAME: " . $port_array[$i]["port_name"] . ", " .
					"MAC: " . $port_array[$i]["mac_address"]);
				$i++;
			}
			$j++;
		}

		/* display completion message */
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $device["ports_total"] . ", ACTIVE PORTS: " . $device["ports_active"]);
		$device["last_runmessage"] = "Data collection completed ok";
		$device["macs_active"] = sizeof($port_array);
		db_store_device_port_results($device, $port_array, $scan_date);
	}else{
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", No active devices on this network device.");
		$device["snmp_status"] = HOST_UP;
		$device["last_runmessage"] = "Data collection completed ok. No active devices on this network device.";
	}

	return $device;
}
