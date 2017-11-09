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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

include('./include/global.php');
include_once('./lib/snmp.php');
include_once('./lib/ping.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');
include_once('./plugins/mactrack/lib/mactrack_vendors.php');

/* Let the scanner run for no more that 25 minutes */
ini_set('max_execution_time', 1500);

/* obtain some date/times for later use */
$scan_date = read_config_option('mt_scan_date');
list($micro,$seconds) = explode(' ', microtime());
$start_time = $seconds + $micro;

/* drop a few environment variables to minimize net-snmp load times */
putenv('MIBS=RFC-1215');
ini_set('max_execution_time', '0');
ini_set('memory_limit', '256M');

/* establish constants */
define('DEVICE_HUB_SWITCH', 1);
define('DEVICE_SWITCH_ROUTER', 2);
define('DEVICE_ROUTER', 3);

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $web, $debug;

$debug     = FALSE;
$web       = FALSE;
$test_mode = FALSE;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-id':
				$device_id = $value;
				break;
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			case '-w':
			case '--web':
				$web = TRUE;
				break;
			case '-t':
				$test_mode = TRUE;
				exit;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}else{
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

/* place a process marker in the database */
if (!$test_mode) {
	db_process_add($device_id, TRUE);
}

/* get device information */
$device = db_fetch_row_prepared('SELECT * FROM mac_track_devices WHERE device_id = ?', array($device_id));
if (sizeof($device) == 0) {
	mactrack_debug("ERROR: Device with Id of '$device_id' not found in database.  Can not continue.");
	db_process_remove($device_id);
	exit;
}

/* get the site name */
$site = db_fetch_cell_prepared('SELECT site_name FROM mac_track_sites WHERE site_id = ?', array($device['site_id']));
if (strlen($site) == 0) {
	mactrack_debug('ERROR: Site not found in database. Can not continue.');
	db_process_remove($device_id);
	exit;
}

/* get device types */
$device_types = db_fetch_assoc('SELECT * FROM mac_track_device_types');
if (sizeof($device_types) == 0) {
	mactrack_debug('ERROR: No device types have been found.');
	db_process_remove($device_id);
	exit;
}

/* check the devices read string for validity, set to new if changed */
if (valid_snmp_device($device)) {
	mactrack_debug('HOST: ' . $device['hostname'] . ' is alive, processing has begun.');
	$host_up = TRUE;

	/* locate the device type to obtain scanning function and low and high ports */
	$device_type = find_scanning_function($device, $device_types);

	/* for switches/hubs, we need to determine the mac to port mappings */
	if (($device['scan_type'] == DEVICE_HUB_SWITCH) ||
			($device['scan_type'] == DEVICE_SWITCH_ROUTER)) {

		/* verify that the scanning function is not null and call it as applicable */
		if (isset($device_type['scanning_function'])) {
			if (strlen($device_type['scanning_function']) > 0) {
				if (function_exists($device_type['scanning_function'])) {
					mactrack_debug('Scanning function is ' . $device_type['scanning_function']);
					$device['device_type_id'] = $device_type['device_type_id'];
					$device['scan_type'] = $device_type['device_type'];
					$device = call_user_func_array($device_type['scanning_function'], array($site, &$device, $device_type['lowPort'], $device_type['highPort']));
				}else{
					mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: Scanning Function Does Not Exist.');
					$device['last_runmessage'] = 'WARNING: Scanning Function Does Not Exist.';
					$device['snmp_status'] = HOST_ERROR;
				}
			}else{
				mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: Scanning Function in Device Type Table Is Null.');
				$device['last_runmessage'] = 'WARNING: Scanning Function in Device Type Table Is Null.';
				$device['snmp_status'] = HOST_ERROR;
			}
		}else{
			mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: Device Type Not Found in Device Type Table.');
			$device['last_runmessage'] = 'WARNING: Device Type Not Found in Device Type Table.';
			$device['snmp_status'] = HOST_ERROR;
		}
	}

	/* for routers and switch/routers we need to push the ARP table to mac_track_ip table */
	if (($device['scan_type'] == DEVICE_SWITCH_ROUTER) ||
		($device['scan_type'] == DEVICE_ROUTER)) {

		/* verify that the scanning function is not null and call it as applicable */
		if (isset($device_type['ip_scanning_function'])) {
			if (strlen($device_type['ip_scanning_function']) > 0) {
				if (function_exists($device_type['ip_scanning_function'])) {
					mactrack_debug('Scanning function is ' . $device_type['ip_scanning_function']);
					$device['device_type_id'] = $device_type['device_type_id'];
					$device['scan_type'] = $device_type['device_type'];
					call_user_func_array($device_type['ip_scanning_function'], array($site, &$device));
				}else{
					mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: IP Address Scanning Function Does Not Exist.');
					$device['last_runmessage'] = 'WARNING: Scanning Function Does Not Exist.';
					$device['snmp_status'] = HOST_ERROR;
				}
			}else{
				mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: IP Scanning Function in Device Type Table Is Null.');
				$device['last_runmessage'] = 'WARNING: Scanning Function in Device Type Table Is Null.';
				$device['snmp_status'] = HOST_ERROR;
			}
		}else{
			mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: Device Type Not Found in Device Type Table.');
			$device['last_runmessage'] = 'WARNING: Device Type Not Found in Device Type Table.';
			$device['snmp_status'] = HOST_ERROR;
		}
	}
}else{
	mactrack_debug('WARNING: SITE: ' . $site . ', IP: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', ERROR: Device unreachable.');
	$device['last_runmessage'] = 'Device unreachable.';

	$host_up = FALSE;
}

/* update the database with device status information */
db_update_device_status($device, $host_up, $scan_date, $start_time);
db_process_remove($device_id);
exit;

function display_version() {
	global $config;

	if (!function_exists('plugin_mactrack_version')) {
		include_once($config['base_path'] . '/plugins/mactrack/setup.php');
	}

	$info = plugin_mactrack_version();
	print "Network Device Tracking, Version " . $info['version'] .", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: mactrack_device.php -id=host_id [-w] [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-id=host_id   - the mac_track_devices host_id to scan\n";
	print "-d | --debug  - Display verbose output during execution\n";
	print "-w | --web    - Display web compatible output during execution\n";
	print "-t            - Test mode, don't log a process id and interfere with system\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}

