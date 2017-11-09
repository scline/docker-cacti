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
array_push($mactrack_scanning_functions, "get_enterasys_N7_switch_ports");

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, "get_CTAlias_table");

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_enterasys_N7_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

        /* get VLAN information */
	$vlan_ids = xform_dot1q_vlan_associations($device, $device["snmp_readstring"]);
	#$vlan_ids = xform_enterasys_N7_vlan_associations($device, $device["snmp_readstring"]);
#print_r($vlan_ids);
        /* get VLAN Trunk status: not (yet) implemented for Enterasys N7 */
        //$vlan_trunkstatus = xform_standard_indexed_data(".1.3.6.1.4.1.2272.1.3.3.1.4", $device);
        $device["vlans_total"] = sizeof($vlan_ids);
        mactrack_debug("VLAN data collected. There are " . (sizeof($vlan_ids)) . " VLANS.");

        /* get the ifIndexes for the device */
        $ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
        mactrack_debug("ifIndexes data collection complete: " . sizeof($ifIndexes));

        /* get and store the interfaces table */
        $ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);
#print_r($ifInterfaces);

	foreach($ifIndexes as $ifIndex) {
                if (($ifInterfaces[$ifIndex]["ifType"] >= 6) && ($ifInterfaces[$ifIndex]["ifType"] <= 9)) {
                        $device["ports_total"]++;
                }
        }
	mactrack_debug("ifInterfaces assembly complete: " . sizeof($ifIndexes));

	/* map vlans to bridge ports */
        if (sizeof($vlan_ids) > 0) {
                /* get the port status information */
                #$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $device["snmp_readstring"], FALSE, $lowPort, $highPort);
                $port_results = get_enterasys_N7_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $device["snmp_readstring"], FALSE, $lowPort, $highPort);
#print_r($port_results);
        	/* get the ifIndexes for the device */
        	$vlan_names = xform_standard_indexed_data(".1.3.6.1.2.1.17.7.1.4.3.1.1", $device);
#print_r($vlan_names);
                $i = 0;
                $j = 0;
                $port_array = array();
                foreach($port_results as $port_result) {
                        $ifIndex = $port_result["port_number"];
#print_r($port_result); print_r($ifInterfaces[$ifIndex]);
                        $ifType = $ifInterfaces[$ifIndex]["ifType"];

                        /* only output legitamate end user ports */
                        if (($ifType >= 6) && ($ifType <= 9)) {
                        	$port_array[$i]["vlan_id"] = @$vlan_ids[$port_result["key"]];
                                $port_array[$i]["vlan_name"] = @$vlan_names[$port_array[$i]["vlan_id"]];
                                $port_array[$i]["port_number"] = @$port_result["port_number"];
                                $port_array[$i]["port_name"] = @$ifInterfaces[$ifIndex]["ifName"];
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


/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_enterasys_N7_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;
	mactrack_debug("FUNCTION: get_enterasys_N7_dot1dTpFdbEntry_ports started");

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
	mactrack_debug("get active ports: " . sizeof($active_ports_array));
	$indexes = array_keys($active_ports_array);

	$i = 0;
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
		/* get bridge port to ifIndex mapping: dot1dBasePortIfIndex from dot1dBasePortTable
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.1: 1
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.2: 4
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.64: 12001
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.65: 12002
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.66: 12003
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.67: 12004
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.68: 12005
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.69: 12006
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.70: 12007
		where
		table index = bridge port (dot1dBasePort) and
		table value = ifIndex */
		/* -------------------------------------------- */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);
		mactrack_debug("get bridgePortIfIndexes: " . sizeof($bridgePortIfIndexes));

		/* get port status: dot1dTpFdbStatus from dot1dTpFdbTable
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.0.94.0.1.1: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.32.11.99: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.37.228.26: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.37.238.180: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.56.96.234: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.59.133.114: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.107.157.61: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.107.189.168: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.109.208.105: 3
		where
		table index = MAC Address (dot1dTpFdbAddress e.g. 0.0.94.0.1.1 = 00:00:5E:00:01:01) and
		table value = port status (other(1), invalid(2), learned(3), self(4), mgmt(5)*/
		/* -------------------------------------------- */
		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.3", $device, $snmp_readstring);
		mactrack_debug("get port_status: " . sizeof($port_status));

		/* get device active port numbers: dot1dTpFdbPort from dot1dTpFdbTable
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.0.94.0.1.1: 72
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.32.11.99: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.37.228.26: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.37.238.180: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.56.96.234: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.59.133.114: 69
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.107.157.61: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.107.189.168: 68
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105: 68
		where
		table index = MAC Address (dot1dTpFdbAddress e.g. 0.0.94.0.1.1 = 00:00:5E:00:01:01) and
		table value = bridge port */
		/* -------------------------------------------- */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.2", $device, $snmp_readstring);
		mactrack_debug("get port_numbers: " . sizeof($port_numbers));

		/* get VLAN information */
		/* -------------------------------------------- */
		#$vlan_ids = xform_enterasys_N7_vlan_associations($device, $snmp_readstring);
		$vlan_ids = xform_dot1q_vlan_associations($device, $snmp_readstring);
		mactrack_debug("get vlan_ids: " . sizeof($vlan_ids));
#print_r($vlan_ids);




		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		foreach ($port_numbers as $key => $port_number) {
			/* key = MAC Address from dot1dTpFdbTable */
			/* value = bridge port			  */
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {

				if (!in_array($port_number, $ignore_ports)) {
					if (@$port_status[$key] == "3") {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;
#print("i: $i, Key: " . $port_key_array[$i]["key"] . ", Number: $port_number\n");
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
#print("searching bridge port: " . $port_key["port_number"] .", Bridge: " . $bridgePortIfIndexes[$port_key["port_number"]] . "\n");
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
					$new_port_key_array[$i]["key"] = @$port_key["key"];
					$new_port_key_array[$i]["port_number"] = @$brPortIfIndex;
					$new_port_key_array[$i]["vlan_id"] = @$vlan_ids[$port_key["key"]];
#print_r($new_port_key_array[$i]);
					$i++;
				}
			}
		}
		mactrack_debug("Port number information collected: " . sizeof($new_port_key_array));

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
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}

			mactrack_debug("Port mac address information collected: " . sizeof($port_macs));
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


function enterasys_N7_convert_macs ($oldmac){
//    print ("==oldmac=[$oldmac] \n");
		$oldmac=substr($oldmac,stripos($oldmac,'.')+1);
//		print ("==old___=[$oldmac] \n");
		$oldmac=substr($oldmac,stripos($oldmac,'.'));
//		print ("==o_____=[$oldmac] \n");
		$piece = explode(".", $oldmac);
		$newmac = '';
   for ($i = 0; $i < 6; $i++)
   {
      $newmac = $newmac . dec2hex($piece[$i],2) . ":";
   }


		$newmac = substr($newmac,0,strlen($newmac)-1);
		//print ("=newmac=$newmac\n");
	return $newmac;

}

function xform_enterasys_N7_vlan_associations(&$device, $snmp_readstring = "") {
	/* get raw index data */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* initialize the output array */
	$output_array = array();

	/* obtain vlan associations: dot1qTpFdbStatus from dot1qTpFdbTable */
	$xformArray = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.7.1.2.2.1.2", $device["snmp_version"], "", "",
					"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$i = 0;
	foreach($xformArray as $xformItem) {
//print_r($xformItem);
		/* peel off the beginning of the OID */
		$key = $xformItem["oid"];
//print ("========= key=[$key]");
		$key = str_replace("iso", "1", $key);
		$key = str_replace("1.3.6.1.2.1.17.7.1.2.2.1.2.", "", $key);
//print ("========= key=[$key]\n");
		/* now grab the VLAN Id */
		$perPos = strpos($key, ".",1);
//print ("========= perPos=[$perPos]\n");
		$output_array[$i]["vlan_id"] = substr($key,1,$perPos-1);
//print ("========= i=[$i] [" . $output_array[$i]["vlan_id"] . "]\n");
		/* save the key=MAC Address for association with the dot1d table */
		$output_array[$i]["key"] = substr($key, $perPos);
		/* get VLAN name, if any: dot1qVlanStaticName from dot1qVlanStaticTable */
		$vlan_name = @cacti_snmp_get($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.7.1.4.3.1.1." . $output_array[$i]["vlan_id"], $device["snmp_version"], "", "",
					"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);
		$output_array[$i]["vlan_name"] = $vlan_name;
//print ("========= i=[$i] [" . $output_array[$i]["vlan_id"] . "] name=[" . $output_array[$i]["vlan_name"] . "]\n");
		$i++;
	}

	return array_rekey($output_array, "key", "vlan_id");
	#return $output_array;
}

function get_enterasys_N7_vlan_id($OID) {
		$perPos = strpos($OID, ".",1);
		$vlan_id = substr($OID,0,$perPos);
	return $vlan_id;
}


/*	get_CTAlias_table - This function reads a devices CTAlias table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table.
*/
function get_CTAlias_table($site, &$device) {
	global $debug, $scan_date;

	mactrack_debug("FUNCTION: get_CTAlias_table started");

	/* get the CTAlias Table for the device */
	$CTAliasInterfaces = xform_indexed_data(".1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.3", $device, 2);
	mactrack_debug("CTAliasInterfaces data collection complete: " . sizeof($CTAliasInterfaces));

	/* get the CTAliasMacAddress for the device */
	$CTAliasMacAddress = xform_indexed_data(".1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.4", $device, 2);
	mactrack_debug("CTAliasMacAddress data collection complete: " . sizeof($CTAliasMacAddress));

	/* convert the mac address if necessary */
	$keys = array_keys($CTAliasMacAddress);
	$i = 0;
	foreach($CTAliasMacAddress as $MacAddress) {
		$CTAliasMacAddress[$keys[$i]] = xform_mac_address($MacAddress);
		$i++;
	}

	/* get the CTAliasProtocol Table for the device */
	$CTAliasProtocol = xform_indexed_data(".1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.6", $device, 2);
	mactrack_debug("CTAliasProtocol data collection complete: " . sizeof($CTAliasProtocol));

	/* get the CTAliasAddressText for the device */
	$CTAliasAddressText = xform_indexed_data(".1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.9", $device, 2);
	mactrack_debug("CTAliasAddressText data collection complete: " . sizeof($CTAliasAddressText));

	/* get the ifNames for the device */
	$keys = array_keys($CTAliasInterfaces);
	$i = 0;
	$CTAliasEntries = array();
	foreach($CTAliasInterfaces as $ifIndex) {
		$CTAliasEntries[$i]["ifIndex"] = $ifIndex;
#		$CTAliasEntries[$i]["timestamp"] = @substr($keys[$i], 0, stripos($keys[$i], '.'));
		$CTAliasEntries[$i]["timestamp"] = $keys[$i];
		$CTAliasEntries[$i]["CTAliasProtocol"] = @$CTAliasProtocol[$keys[$i]];
		$CTAliasEntries[$i]["CTAliasMacAddress"] = @$CTAliasMacAddress[$keys[$i]];
#		$CTAliasEntries[$i]["CTAliasAddressText"] = @xform_net_address($CTAliasAddressText[$keys[$i]]);
		$CTAliasEntries[$i]["CTAliasAddressText"] = @$CTAliasAddressText[$keys[$i]];
		$i++;
	}
	mactrack_debug("CTAliasEntries assembly complete: " . sizeof($CTAliasEntries));

	/* output details to database */
	if (count($CTAliasEntries) > 0) {
		foreach($CTAliasEntries as $CTAliasEntry) {
			/* drop non-IP protocols */
			if ($CTAliasEntry["CTAliasProtocol"] != 1) continue;
			$insert_string = "REPLACE INTO mac_track_ips " .
				"(site_id,device_id,hostname,device_name,port_number," .
				"mac_address,ip_address,scan_date)" .
				" VALUES ('" .
				$device["site_id"] . "','" .
				$device["device_id"] . "','" .
				$device["hostname"] . "'," .
				db_qstr($device["device_name"]) . ",'" .
				$CTAliasEntry["ifIndex"] . "','" .
				$CTAliasEntry["CTAliasMacAddress"] . "','" .
				$CTAliasEntry["CTAliasAddressText"] . "','" .
				$scan_date . "')";

#			mactrack_debug("SQL: " . $insert_string);

			db_execute($insert_string);
		}
	}

	/* save ip information for the device */
	$device["ips_total"] = sizeof($CTAliasEntries);
	db_execute("UPDATE mac_track_devices SET ips_total ='" . $device["ips_total"] . "' WHERE device_id='" . $device["device_id"] . "'");

	mactrack_debug("HOST: " . $device["hostname"] . ", IP address information collection complete: " . $device["ips_total"]);
}
?>
