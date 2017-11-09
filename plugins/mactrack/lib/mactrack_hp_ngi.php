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

/*
   get_procurve_ngi_switch_ports V1.0, Thomas Klein
   ProCurve Next Generation Improved :-)
   Tested with HP ProCurve 5308xl
*/

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_procurve_ngi_switch_ports");

function get_procurve_ngi_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize local variable to store the number of vlans per Interface */
	$nrVlans = array();

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get VLAN information */
	$vlan_ids = xform_standard_indexed_data(".1.3.6.1.2.1.17.7.1.4.3.1.1", $device);
	$device["vlans_total"] = sizeof($vlan_ids);

	/* get VLAN Trunk status */
	$vlan_trunkstatus = local_xform_indexed_data(".1.3.6.1.4.1.11.2.14.11.5.1.7.1.15.3.1.1", $device, $xformLevel = 1);
	if (sizeof($vlan_trunkstatus)) {
	foreach($vlan_trunkstatus as $vlan_trunk) {
		$ifIndex = $vlan_trunk["key"];
		$vlan = $vlan_trunk["value"];
		if (!isset($nrVlans[$ifIndex])) {
			$nrVlans[$ifIndex] = 1;
		}else{
			$nrVlans[$ifIndex]++;
		}
	}
	}
	mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids)) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	foreach($ifIndexes as $ifIndex) {
		if (($ifInterfaces[$ifIndex]["ifType"] >= 6) && ($ifInterfaces[$ifIndex]["ifType"] <= 9)) {
			$device["ports_total"]++;
		}

		/* A port with more than one vlan is a trunk */
		if (isset($nrVlans[$ifIndex]) && $nrVlans[$ifIndex] > 1) {
			$device["ports_trunk"]++;
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

	if (sizeof($active_vlans) > 0) {
		$i = 0;
		/* get the port status information */
		$ifNames = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.18", $device);
		$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE, $lowPort, $highPort);

		$port_vlan_data = xform_standard_indexed_data(".1.3.6.1.2.1.17.7.1.4.5.1.1", $device);
		$port_alias = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.18", $device);

		$i = 0;
		$j = 0;
		$trunk = 0;

		$port_array = array();
		if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			$ifIndex = $port_result["port_number"];
			$ifType = $ifInterfaces[$ifIndex]["ifType"];
			$ifName = $ifInterfaces[$ifIndex]["ifName"];
			$portName = $ifName;

			/* A port with more than one vlan is a trunk */
			if (isset($nrVlans[$ifIndex]) && $nrVlans[$ifIndex] > 1) {
				$trunk = 1;
			}

			/* only output legitamate end user ports */
			if (($ifType >= 6) && ($ifType <= 9) && ($trunk == 0)) {
				$port_array[$i]["vlan_id"]     = @$port_vlan_data[$port_result["port_number"]];
				$port_array[$i]["vlan_name"]   = @$vlan_ids[$port_array[$i]["vlan_id"]];
				$port_array[$i]["port_number"] = $ifName;
				if (isset($port_alias[$port_result["port_number"]])) {
					$port_array[$i]["port_name"]   = @$port_alias[$port_result["port_number"]];
				}else{
					$port_array[$i]["port_name"]   = @$ifNames[$port_result["port_number"]];
				}
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
			$trunk = 0;
		}
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

/*      local_xform_indexed_data - copy of xform_indexed_data without array_rekey before return
  This function is similar to other the other xform_* functions
  in that it takes the end of each OID and uses the last $xformLevel positions as the
  index.  Therefore, if $xformLevel = 3, the return value would be as follows:
  array[1.2.3] = value.
*/
function local_xform_indexed_data($xformOID, &$device, $xformLevel = 1) {
	global $debug;

	/* get raw index data */
	$xformArray = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
		$xformOID, $device["snmp_version"], $device["snmp_username"],
		$device["snmp_password"], $device["snmp_auth_protocol"],
		$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"], $device["snmp_context"],
		$device["snmp_port"], $device["snmp_timeout"],
		$device["snmp_retries"], $device["max_oids"]);

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

	return $output_array;
}
