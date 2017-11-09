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
array_push($mactrack_scanning_functions, "get_catalyst_dot1dTpFdbEntry_ports");
array_push($mactrack_scanning_functions, "get_IOS_dot1dTpFdbEntry_ports");

/* get_catalyst_doet1dTpFdbEntry_ports
	obtains port associations for Cisco Catalyst Swtiches.  Catalyst
	switches are unique in that they support a different snmp_readstring for
	every VLAN interface on the switch.
*/
function get_catalyst_dot1dTpFdbEntry_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"]  = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"]  = 0;
	$device["vlans_total"]  = 0;

	/* Variables to determine VLAN information */
	$vlan_ids         = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.3.1.1.2", $device);
	$vlan_names       = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.3.1.1.4", $device);
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.6.1.1.14", $device);

	$device["vlans_total"] = sizeof($vlan_ids) - 3;
	mactrack_debug("There are " . (sizeof($vlan_ids)-3) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	/* get the Voice VLAN information if it exists */
	$portVoiceVLANs = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.87.1.4.1.1.37.0", $device);
	if (sizeof($portVoiceVLANs)) {
		$vvlans = TRUE;
	}else{
		$portVoiceVLANs = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.68.1.5.1.1.1", $device);
		if (sizeof($portVoiceVLANs)) {
			$vvlans = TRUE;
		}else{
			$vvlans = FALSE;
		}
	}
	mactrack_debug("Cisco Voice VLAN collection complete");
	if ($vvlans) {
		mactrack_debug("Voice VLANs exist on this device");
	}else{
		mactrack_debug("Voice VLANs do not exist on this device");
	}

	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];
		if ($vvlans) {
			$ifInterfaces[$ifIndex]["vVlanID"] = @$portVoiceVLANs[$ifIndex];
		}

		if ($ifInterfaces[$ifIndex]["ifType"] == 6) {
			$device["ports_total"]++;
		}
	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	/* get the portNames */
	$portNames = xform_cisco_workgroup_port_data(".1.3.6.1.4.1.9.5.1.4.1.1.4", $device);
	mactrack_debug("portNames data collected.");

	/* get trunking status */
	$portTrunking = xform_cisco_workgroup_port_data(".1.3.6.1.4.1.9.5.1.9.3.1.8", $device);
	mactrack_debug("portTrunking data collected.");

	/* calculate the number of end user ports */
	if (sizeof($portTrunking)) {
	foreach ($portTrunking as $portTrunk) {
		if ($portTrunk == 1) {
			$device["ports_trunk"]++;
		}
	}
	}

	/* build VLAN array from results */
	$i = 0;
	$j = 0;
	$active_vlans = array();

	if (sizeof($vlan_ids)) {
	foreach($vlan_ids as $vlan_number => $vlanStatus) {
		$vlanName = $vlan_names[$vlan_number];

		if ($vlanStatus == 1) { /* vlan is operatinal */
			switch ($vlan_number) {
			case "1002":
			case "1003":
			case "1004":
			case "1005":
				$active_vlan_ports = 0;
				break;
			default:
				if ($device["snmp_version"] < "3") {
					$snmp_readstring = $device["snmp_readstring"] . "@" . $vlan_number;
					$active_vlan_ports = cacti_snmp_get($device["hostname"], $snmp_readstring,
						".1.3.6.1.2.1.17.1.2.0", $device["snmp_version"],
						$device["snmp_username"], $device["snmp_password"],
						$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
						$device["snmp_priv_protocol"], $device["snmp_context"],
						$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);
				}else{
					$active_vlan_ports = cacti_snmp_get($device["hostname"], "",
						".1.3.6.1.2.1.17.1.2.0", $device["snmp_version"],
						$device["snmp_username"], $device["snmp_password"],
						$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
						$device["snmp_priv_protocol"], "vlan-" . $vlan_number,
						$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);
				}

				if ((!is_numeric($active_vlan_ports)) || ($active_vlan_ports) < 0) {
					$active_vlan_ports = 0;
				}

				mactrack_debug("VLAN Analysis for VLAN: " . $vlan_number . "/" . $vlanName . " is complete. ACTIVE PORTS: " . $active_vlan_ports);

				if ($active_vlan_ports > 0) { /* does the vlan have active ports on it */
					$active_vlans[$j]["vlan_id"] = $vlan_number;
					$active_vlans[$j]["vlan_name"] = $vlanName;
					$active_vlans[$j]["active_ports"] = $active_vlan_ports;
					$active_vlans++;

					$j++;
				}
			}
		}

		$i++;
	}
	}

	if (sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		foreach($active_vlans as $active_vlan) {
			/* ignore empty vlans */
			if ($active_vlan["active_ports"] <= $device["ports_trunk"]) {
				$active_vlans[$i]["port_results"] = array();
				$i++;
				continue;
			}

			if ($device["snmp_version"] < "3") {
				$snmp_readstring = $device["snmp_readstring"] . "@" . $active_vlan["vlan_id"];
			}else{
				$snmp_readstring = "cisco@" . $active_vlan["vlan_id"];
			}

			mactrack_debug("Processing has begun for VLAN: " . $active_vlan["vlan_id"]);

			if ($highPort == 0) {
				$active_vlans[$i]["port_results"] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, FALSE);
			}else {
				$active_vlans[$i]["port_results"] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, FALSE, $lowPort, $highPort);
			}

			/* get bridge port mappings */
			/* get bridge port to ifIndex mappings */
			mactrack_debug("Bridge port information about to be collected.");
			mactrack_debug("VLAN_ID: " . $active_vlans[$i]["vlan_id"] . ", VLAN_NAME: " . $active_vlans[$i]["vlan_name"] . ", ACTIVE PORTS: " . sizeof($active_vlans[$i]["port_results"]));

			if (sizeof($active_vlans[$i]["port_results"]) > 0) {
				$brPorttoifIndexes[$i] = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);
				mactrack_debug("Bridge port information collection complete.");
			}

			$i++;
		}

		mactrack_debug("Final cross check's now being performed.");
		$i = 0;
		$j = 0;
		$port_array = array();

		if (sizeof($active_vlans)) {
		foreach($active_vlans as $active_vlan) {
			if (sizeof($active_vlan["port_results"])) {
			foreach($active_vlan["port_results"] as $port_result) {
				$ifIndex         = @$brPorttoifIndexes[$j][$port_result["port_number"]];
				$ifType          = (isset($ifInterfaces[$ifIndex]["ifType"]) ? $ifInterfaces[$ifIndex]["ifType"] : '');
				$ifName          = (isset($ifInterfaces[$ifIndex]["ifName"]) ? $ifInterfaces[$ifIndex]["ifName"] : '');
				$portName        = (isset($portNames[$ifName]) ? $portNames[$ifName] : '');
				$portTrunk       = (isset($portTrunking[$ifName]) ? $portTrunking[$ifName] : '');
				$portTrunkStatus = (isset($ifInterfaces[$ifIndex]["trunkPortState"]) ? $ifInterfaces[$ifIndex]["trunkPortState"] : '');

				if ($vvlans) {
					$vVlanID = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
				}else{
					$vVlanID = -1;
				}

				/* only output legitamate end user ports */
				if (($ifType == 6) && ($portTrunk == 2)) {
					if (($portTrunkStatus == "2")||($portTrunkStatus == "4")||($portTrunkStatus =="")) {
						$port_array[$i]["vlan_id"]     = $active_vlan["vlan_id"];
						$port_array[$i]["vlan_name"]   = $active_vlan["vlan_name"];
						$port_array[$i]["port_number"] = $ifInterfaces[$ifIndex]["ifName"];
						$port_array[$i]["port_name"]   = $portName;
						$port_array[$i]["mac_address"] = xform_mac_address($port_result["mac_address"]);
						$device["ports_active"]++;
						$i++;

						mactrack_debug("VLAN: " . $active_vlan["vlan_id"] . ", " .
							"NAME: " . $active_vlan["vlan_name"] . ", " .
							"PORT: " . $ifInterfaces[$ifIndex]["ifName"] . ", " .
							"NAME: " . $portName . ", " .
							"MAC: " . $port_result["mac_address"]);
					}
				}
			}
			}

			$j++;
		}
		}

		/* display completion message */
		print("\nINFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $device["ports_total"] . ", ACTIVE PORTS: " . $device["ports_active"] . "\n");
		$device["last_runmessage"] = "Data collection completed ok";
		$device["macs_active"] = sizeof($port_array);
		db_store_device_port_results($device, $port_array, $scan_date);
	}else{
		print("\nINFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", No active devcies on this network device.\n");
		$device["snmp_status"] = HOST_UP;
		$device["last_runmessage"] = "Data collection completed ok. No active devices on this network device.";
	}

	return $device;
}

/* get_IOS_dot1dTpFdbEntry_ports
	obtains port associations for Cisco Catalyst Swtiches.  Catalyst
	switches are unique in that they support a different snmp_readstring for
	every VLAN interface on the switch.
*/
function get_IOS_dot1dTpFdbEntry_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"]  = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"]  = 0;
	$device["vlans_total"]  = 0;

	/* Variables to determine VLAN information */
	$vlan_ids         = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.3.1.1.2", $device);
	$vlan_names       = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.3.1.1.4", $device);
	$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.46.1.6.1.1.14", $device);

	$device["vlans_total"] = sizeof($vlan_ids) - 3;
	mactrack_debug("There are " . (sizeof($vlan_ids)-3) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, TRUE);

	/* get the Voice VLAN information if it exists */
	$portVoiceVLANs = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.87.1.4.1.1.37.0", $device);
	if (sizeof($portVoiceVLANs) > 0) {
		$vvlans = TRUE;
	}else{
		$portVoiceVLANs = xform_standard_indexed_data(".1.3.6.1.4.1.9.9.68.1.5.1.1.1", $device);
		if (sizeof($portVoiceVLANs) > 0) {
			$vvlans = TRUE;
		}else{
			$vvlans = FALSE;
		}
	}
	mactrack_debug("Cisco Voice VLAN collection complete");
	if ($vvlans) {
		mactrack_debug("Voice VLANs exist on this device");
	}else{
		mactrack_debug("Voice VLANs do not exist on this device");
	}

	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = (isset($vlan_trunkstatus[$ifIndex]) ? $vlan_trunkstatus[$ifIndex] : '');
		if ($vvlans) {
			$ifInterfaces[$ifIndex]["vVlanID"] = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
		}

		if ($ifInterfaces[$ifIndex]["ifType"] == 6) {
			$device["ports_total"]++;
		}

		if ($ifInterfaces[$ifIndex]["trunkPortState"] == "1") {
			$device["ports_trunk"]++;
		}
	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	/* build VLAN array from results */
	$i = 0;
	$j = 0;
	$active_vlans = array();

	if (sizeof($vlan_ids)) {
	foreach($vlan_ids as $vlan_number => $vlanStatus) {
		$vlanName = $vlan_names[$vlan_number];

		if ($vlanStatus == 1) { /* vlan is operatinal */
			switch ($vlan_number) {
			case "1002":
			case "1003":
			case "1004":
			case "1005":
				$active_vlan_ports = 0;
				break;
			default:
				if ($device["snmp_version"] < "3") {
					$snmp_readstring = $device["snmp_readstring"] . "@" . $vlan_number;
					$active_vlan_ports = cacti_snmp_get($device["hostname"], $snmp_readstring,
						".1.3.6.1.2.1.17.1.2.0", $device["snmp_version"],
						$device["snmp_username"], $device["snmp_password"],
						$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
						$device["snmp_priv_protocol"], $device["snmp_context"],
						$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);
				}else{
					$active_vlan_ports = cacti_snmp_get($device["hostname"], "vlan-" . $vlan_number,
						".1.3.6.1.2.1.17.1.2.0", $device["snmp_version"],
						$device["snmp_username"], $device["snmp_password"],
						$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
						$device["snmp_priv_protocol"], "vlan-" . $vlan_number,
						$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);
				}

				if ((!is_numeric($active_vlan_ports)) || ($active_vlan_ports) < 0) {
					$active_vlan_ports = 0;
				}

				mactrack_debug("VLAN Analysis for VLAN: " . $vlan_number . "/" . $vlanName . " is complete. ACTIVE PORTS: " . $active_vlan_ports);

				if ($active_vlan_ports > 0) { /* does the vlan have active ports on it */
					$active_vlans[$j]["vlan_id"] = $vlan_number;
					$active_vlans[$j]["vlan_name"] = $vlanName;
					$active_vlans[$j]["active_ports"] = $active_vlan_ports;
					$active_vlans++;

					$j++;
				}
			}
		}

		$i++;
	}
	}

	if (sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		foreach($active_vlans as $active_vlan) {
			if ($device["snmp_version"] < "3") {
				$snmp_readstring = $device["snmp_readstring"] . "@" . $active_vlan["vlan_id"];
			}else{
				$snmp_readstring = "vlan-" . $active_vlan["vlan_id"];
			}

			mactrack_debug("Processing has begun for VLAN: " . $active_vlan["vlan_id"]);
			if ($highPort == 0) {
				$active_vlans[$i]["port_results"] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, FALSE);
			}else {
				$active_vlans[$i]["port_results"] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, FALSE, $lowPort, $highPort);
			}

			/* get bridge port mappings */
			/* get bridge port to ifIndex mappings */
			mactrack_debug("Bridge port information about to be collected.");
			mactrack_debug("VLAN_ID: " . $active_vlans[$i]["vlan_id"] . ", VLAN_NAME: " . $active_vlans[$i]["vlan_name"] . ", ACTIVE PORTS: " . sizeof($active_vlans[$i]["port_results"]));

			if (sizeof($active_vlans[$i]["port_results"]) > 0) {
				$brPorttoifIndexes[$i] = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);
				mactrack_debug("Bridge port information collection complete.");
			}
			$i++;
		}

		$i = 0;
		$j = 0;
		$port_array = array();

		mactrack_debug("Final cross check's now being performed.");
		if (sizeof($active_vlans)) {
		foreach($active_vlans as $active_vlan) {
			if (sizeof($active_vlan["port_results"])) {
			foreach($active_vlan["port_results"] as $port_result) {
				$ifIndex    = @$brPorttoifIndexes[$j][$port_result["port_number"]];
				$ifType     = (isset($ifInterfaces[$ifIndex]["ifType"]) ? $ifInterfaces[$ifIndex]["ifType"] : '');
				$ifName     = (isset($ifInterfaces[$ifIndex]["ifName"]) ? $ifInterfaces[$ifIndex]["ifName"] : '');
				$portNumber = (isset($ifInterfaces[$ifIndex]["ifName"]) ? $ifInterfaces[$ifIndex]["ifName"] : '');
				$portName   = (isset($ifInterfaces[$ifIndex]["ifAlias"]) ? $ifInterfaces[$ifIndex]["ifAlias"] : '');
				$portTrunk  = (isset($portTrunking[$ifName]) ? $portTrunking[$ifName] : '');
				if ($vvlans) {
					$vVlanID = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
				}else{
					$vVlanID = -1;
				}

				$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];

				/* only output legitamate end user ports */
				if ($ifType == 6) {
					if (($portTrunkStatus == "2") ||
						(empty($portTrunkStatus)) ||
						(($vVlanID > 0) && ($vVlanID <= 1000))) {
						$port_array[$i]["vlan_id"]     = $active_vlan["vlan_id"];
						$port_array[$i]["vlan_name"]   = $active_vlan["vlan_name"];
						$port_array[$i]["port_number"] = $portNumber;
						$port_array[$i]["port_name"]   = $portName;
						$port_array[$i]["mac_address"] = xform_mac_address($port_result["mac_address"]);
						$device["ports_active"]++;
						$i++;

						mactrack_debug("VLAN: " . $active_vlan["vlan_id"] . ", " .
							"NAME: " . $active_vlan["vlan_name"] . ", " .
							"PORT: " . $portNumber . ", " .
							"NAME: " . $portName . ", " .
							"MAC: " . $port_result["mac_address"]);
					}
				}
			}
			}

			$j++;
		}
		}

		/* display completion message */
		print("\nINFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $device["ports_total"] . ", ACTIVE PORTS: " . $device["ports_active"] . "\n");
		$device["last_runmessage"] = "Data collection completed ok";
		$device["macs_active"] = sizeof($port_array);
		db_store_device_port_results($device, $port_array, $scan_date);
	}else{
		print("\nINFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", No active end devices on this device.\n");
		$device["snmp_status"] = HOST_UP;
		$device["last_runmessage"] = "Data collection completed ok.  No active end devices on this device.";
	}

	return $device;
}
