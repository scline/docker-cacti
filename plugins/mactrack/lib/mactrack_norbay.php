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
array_push($mactrack_scanning_functions, "get_norbay_switch_ports");
array_push($mactrack_scanning_functions, "get_norbay_accelar_switch_ports");

/* get_norbay_accelar_switch_ports
 obtains port associations for Bay Network Swtiches.  Designed after the
 425-24T Series of Nortel/Bay Network Swtich.
*/
function get_norbay_accelar_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get VLAN information */
	$vlan_ids = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.2.1.2", $device);

	/* get VLAN Trunk status */
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.3.1.4", $device);
	$device["vlans_total"] = sizeof($vlan_trunkstatus);
	mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids)) . " VLANS.");

	/* get the vlan by port info from the RC mib */
	$vlan_id_by_int = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.3.1.3", $device);
	mactrack_debug("vlans assigned by interface data collection complete");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, FALSE, FALSE);

	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];
		$ifInterfaces[$ifIndex]["vlannum"] = hexdec($vlan_id_by_int[$ifIndex]);

		if ($ifInterfaces[$ifIndex]["ifType"] == 6) {
			$device["ports_total"]++;
		}

		if ($ifInterfaces[$ifIndex]["trunkPortState"] == 2) {
			$device["ports_trunk"]++;
		}

	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	$i = 0;
	if (sizeof($vlan_ids)) {
	foreach($vlan_ids as $vlan_id => $vlan_name) {
		$active_vlans[$i]["vlan_id"] = $vlan_id;
		$active_vlans[$i]["vlan_name"] = $vlan_name;
		$active_vlans++;
		$i++;
	}
	}

	if (sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE);
		$port_vlan_data = xform_dot1q_vlan_associations($device);

		$i = 0;
		$j = 0;
		$port_array = array();

		if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			$ifIndex = $port_result["port_number"];
			$ifType = $ifInterfaces[$ifIndex]["ifType"];
			$ifName = $ifInterfaces[$ifIndex]["ifName"];
			$portName = "";
			$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];
			$vlannum = @$ifInterfaces[$ifindex]["vlannum"];

			/* only output legitamate end user ports */
			if ((($ifType == 6) && ($portTrunkStatus == 1))) {
			/*    $port_array[$i]["vlan_id"] = @$port_vlan_data[$port_result["key"]]; */
				$port_array[$i]["vlan_id"]     = $vlannum;
				$port_array[$i]["vlan_name"]   = @$vlan_ids[$vlannum];
				$port_array[$i]["port_number"] = @$port_result["port_number"];
				$port_array[$i]["port_name"]   = "";
				$port_array[$i]["mac_address"] = xform_mac_address($port_result["mac_address"]);
				$device["ports_active"]++;

				mactrack_debug("VLAN: " . $port_array[$i]["vlan_id"] . ", " .
					"NAME: " . $port_array[$i]["vlan_name"] . ", " .
					"PORT: " . $ifInterfaces[$ifIndex]["ifName"] . ", " .
					"NAME: " . $port_array[$i]["port_name"] . ", " .
					"MAC: " . $port_array[$i]["mac_address"]);

				$i++;
			}

			$j++;
		}
		}

		/* display completion message */
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . trim(substr($device["snmp_sysDescr"],0,40)) . ", TOTAL PORTS: " . $device["ports_total"] . ", ACTIVE PORTS: " . $device["ports_active"] . "\n");
		$device["last_runmessage"] = "Data collection completed ok";
		$device["macs_active"] = sizeof($port_array);
		mactrack_debug("macs active on this switch:" . $device["macs_active"]);
		db_store_device_port_results($device, $port_array, $scan_date);
	}else{
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", No active devcies on this network device.\n");
		$device["snmp_status"] = HOST_UP;
		$device["last_runmessage"] = "Data collection completed ok. No active devices on this network device.";
	}

	return $device;
}

/* get_norbay_switch_ports
	obtains port associations for Bay Network Swtiches.  Designed after the
	425-24T Series of Nortel/Bay Network Swtich.
*/
function get_norbay_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get VLAN information */
	$vlan_ids         = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.2.1.2", $device);
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.3.1.4", $device);

	$device["vlans_total"] = sizeof($vlan_ids);
	mactrack_debug("There are " . (sizeof($vlan_ids)) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];
		$ifInterfaces[$ifIndex]["vlannum"]        = @$vlan_ids[$ifIndex];
		$ifInterfaces[$ifIndex]["ifOperStatus"]   =  mactrack_strip_alpha($ifInterfaces[$ifIndex]["ifOperStatus"]);

		if ($ifInterfaces[$ifIndex]["ifType"] == 6) {
			$device["ports_total"]++;
		}

		if ($ifInterfaces[$ifIndex]["ifOperStatus"] == 1) {
			$device["ports_active"]++;
		}

		if ($ifInterfaces[$ifIndex]["trunkPortState"] == 2) {
			mactrack_debug("Found Port Trunk: " . $ifInterfaces[$ifIndex]["ifDescr"]);
			$device["ports_trunk"]++;
		}
	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	$i = 0;
	if (sizeof($vlan_ids)) {
	foreach($vlan_ids as $vlan_id => $vlan_name) {
		$active_vlans[$i]["vlan_id"] = $vlan_id;
		$active_vlans[$i]["vlan_name"] = $vlan_name;
		$active_vlans++;

		$i++;
	}
	}

	if (sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE);
		$port_vlan_data = xform_dot1q_vlan_associations($device);

		$i = 0;
		$j = 0;
		$port_array = array();

		if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			$ifIndex = $port_result["port_number"];
			$ifType = $ifInterfaces[$ifIndex]["ifType"];
			$ifName = $ifInterfaces[$ifIndex]["ifName"];
			$ifDescr = $ifInterfaces[$ifIndex]["ifDescr"];

			if ( strpos($ifDescr, "BayStack") === false ) {
				$portName = preg_replace("/ifc[0-9]+ /", "", $ifName);
			}else{
				$portName = preg_replace("/BayStack - /", "", $ifDescr);
			}

			$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];

			/* only output legitamate end user ports */
			if ((($ifType == 6) && ($portTrunkStatus == 1))) {
				$port_array[$i]["vlan_id"] = @$port_vlan_data[$port_result["key"]];
				$port_array[$i]["vlan_name"] = @$vlan_ids[$port_array[$i]["vlan_id"]];
				$port_array[$i]["port_number"] = @$port_result["port_number"];
				$port_array[$i]["port_name"] = $portName;
				$port_array[$i]["mac_address"] = xform_mac_address($port_result["mac_address"]);
				$device["ports_active"]++;

				foreach ($port_array as $test_array) {
					if (($test_array["port_name"] == $portName) && ($test_array["mac_address"] != $port_result["mac_address"])) {
						$port_array[$i]["port_number"] = @$port_result["port_number"] . " - *";
					}
				}

				mactrack_debug("VLAN: " . $port_array[$i]["vlan_id"] . ", " .
					"NAME: " . $port_array[$i]["vlan_name"] . ", " .
					"PORT: " . $ifInterfaces[$ifIndex]["ifName"] . ", " .
					"NUMBER: " . $port_array[$i]["port_number"] . ", " .
					"NAME: " . $port_array[$i]["port_name"] . ", " .
					"MAC: " . $port_array[$i]["mac_address"]);

				$i++;
			}

			$j++;
		}
		}

		/* display completion message */
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . trim(substr($device["snmp_sysDescr"],0,40)) . ", TOTAL PORTS: " . $device["ports_total"] . ", ACTIVE PORTS: " . $device["ports_active"]);
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
