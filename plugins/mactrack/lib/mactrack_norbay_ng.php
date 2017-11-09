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
array_push($mactrack_scanning_functions, "get_norbay_ng_switch_ports");

/* get_norbay_ng_switch_ports
	obtains port associations for Nortel ERS and latest Firmware BayStacks.  Designed after the
	ERS5520 & BS420 [adapted from mactrack_hp_ng.php]

*/
function get_norbay_ng_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get VLAN information */
	$vlan_ids = xform_standard_indexed_data("SNMPv2-SMI::enterprises.2272.1.3.2.1.2", $device);

	/* get VLAN Trunk status */
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.11.2.14.11.5.1.7.1.15.3.1.1.1.25", $device);
	$device["vlans_total"] = sizeof($vlan_ids);
	mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids)) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];

		if (($ifInterfaces[$ifIndex]["ifType"] >= 6) && ($ifInterfaces[$ifIndex]["ifType"] <= 9)) {
			$device["ports_total"]++;
		}

		if ($ifInterfaces[$ifIndex]["trunkPortState"] == 3) {
			$device["ports_trunk"]++;
		}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	$i = 0;
	foreach($vlan_ids as $vlan_id => $vlan_name) {
		$active_vlans[$i]["vlan_id"] = $vlan_id;
		$active_vlans[$i]["vlan_name"] = $vlan_name;
		$active_vlans++;

		$i++;
	}

	if (sizeof($active_vlans) > 0) {
		$i = 0;
		/* get the port status information */
		$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE);
		$port_vlan_data = xform_standard_indexed_data("SNMPv2-SMI::enterprises.2272.1.3.3.1.7", $device);
		$port_alias = xform_standard_indexed_data("IF-MIB::ifAlias", $device);

		$i = 0;
		$j = 0;
		$port_array = array();
		foreach($port_results as $port_result) {
			$ifIndex = $port_result["port_number"];
			$ifType = $ifInterfaces[$ifIndex]["ifType"];
			$ifName = $ifInterfaces["ifAlias"][$ifIndex];
			$portName = $ifName;
			$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];

			/* only output legitamate end user ports */
			if (($ifType >= 6) && ($ifType <= 9)) {
				$port_array[$i]["vlan_id"]     = @$port_vlan_data[$port_result["port_number"]];
				$port_array[$i]["vlan_name"]   = @$vlan_ids[$port_array[$i]["vlan_id"]];
				$port_array[$i]["port_number"] = @$port_result["port_number"];
				$port_array[$i]["port_name"]   = @$port_alias[$port_result["port_number"]];;
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
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", No active devcies on this network device.");
		$device["snmp_status"] = HOST_UP;
		$device["last_runmessage"] = "Data collection completed ok. No active devices on this network device.";
	}

	return $device;
}
