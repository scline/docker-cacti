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
array_push($mactrack_scanning_functions, "get_JEX_switch_ports");

function mach ($macd, $del = ":") {
	$result = "";
	$macsd  = explode (".", $macd);
	foreach ($macsd as $d) {
		$hex     = strtoupper(sprintf("%02x$del", $d));
		$result .= $hex;
	}
	$result = substr ($result, 0, -1);
	return ($result);
}

/* get_JEX_switch_ports
        obtains port associations for Juniper Ex Switches.
*/
function get_JEX_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"]  = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"]  = 0;

	/* get VLAN information */
	$vlan_ids   = xform_standard_indexed_data(".1.3.6.1.4.1.2636.3.40.1.5.1.5.1.5", $device);
	$vlan_names = xform_standard_indexed_data(".1.3.6.1.4.1.2636.3.40.1.5.1.5.1.2", $device);

	/* get VLAN Trunk status */
	$device["vlans_total"] = sizeof($vlan_ids) - 1;
	mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids) - 1) . " VLANS.");

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["trunkPortState"] = @$vlan_trunkstatus[$ifIndex];

		if (($ifInterfaces[$ifIndex]["ifType"] == "propVirtual(53)" ) or ($ifInterfaces[$ifIndex]["ifType"] == "ieee8023adLag(161)" )) {
			$device["ports_total"]++;
		}

		if ($ifInterfaces[$ifIndex]["trunkPortState"] == 3) {
			$device["ports_trunk"]++;
		}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	$i = 0;
	foreach($vlan_ids as $vlan_id => $vlan_num) {
		$active_vlans[$vlan_id]["vlan_id"] = $vlan_num;
		$active_vlans[$vlan_id]["vlan_name"] = $vlan_names[$vlan_id];
		$active_vlans++;

		$i++;
	}
	mactrack_debug("Vlan assembly complete.");

	if (sizeof($active_vlans) > 0) {
		$i = 0;
		/* get the port status information */
		//$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", "", FALSE);
		$mac_results  = xform_stripped_oid ( ".1.3.6.1.2.1.17.7.1.2.2.1.2", $device );
		$port_results = xform_stripped_oid ( ".1.3.6.1.2.1.17.1.4.1.2", $device );


		$i = 0;
		$j = 0;
		$port_array = array();
		foreach($mac_results as $num => $mac_result) {
			if ( $mac_result != 0 ) {
				$Xvlanid = substr ( $num, 0, strpos ( $num, "." ) );
				$Xmac    = mach ( substr ( $num, strpos ( $num, ".") + 1 ) );

				$ifIndex = $port_results[$mac_result];
				$ifType = $ifInterfaces[$ifIndex]["ifType"];
				$ifName = $ifInterfaces[$ifIndex]["ifName"];
				$portName = $ifName;
				$portTrunkStatus = @$ifInterfaces[$ifIndex]["trunkPortState"];

				/* only output legitamate end user ports */
				//if ((($ifType >= 6) && ($ifType <= 9)) and ( $portName != "" or $portName != "1" )) {
				if ( $portName != "" and $portName != "1" ) {
					$port_array[$i]["vlan_id"] = $active_vlans[$Xvlanid]["vlan_id"];//@$vlan_ids[$Xvlanid];
					$port_array[$i]["vlan_name"] = $active_vlans[$Xvlanid]["vlan_name"];//@$vlan_names[$Xvlandid];
					$port_array[$i]["port_number"] = @$port_results[$mac_result];
					$port_array[$i]["port_name"] = trim ( $ifName );
					$port_array[$i]["mac_address"] = xform_mac_address($Xmac);
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
