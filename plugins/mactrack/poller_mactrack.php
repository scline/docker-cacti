#!/usr/bin/php -q
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

/* We are not talking to the browser */
$no_http_headers = true;

$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

/* Start Initialization Section */
include('./include/global.php');
include_once('./lib/poller.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

/* get the mactrack polling cycle */
$max_run_duration = read_config_option('mt_collection_timing');
$poller_interval  = read_config_option('poller_interval');

if (is_numeric($max_run_duration)) {
	/* let PHP a 5 minutes less than the rerun frequency */
	$max_run_duration = ($max_run_duration * 60) - $poller_interval;
	ini_set('max_execution_time', $max_run_duration);
}

/* Disable Mib File Loading */
putenv('MIBS=RFC-1215');

global $debug, $web, $track_errors;

/* initialize variables */
$site_id  = '';
$debug    = false;
$forcerun = false;
$web      = false;

$track_errors = 0;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-sid':
				$site_id = $value;
				break;
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '-f':
			case '--force':
				$forcerun = true;
				break;
			case '-w':
			case '--web':
				$web = true;
				break;
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
}

// Get rid of old/hung processes
clear_old_processes($site_id);

if (read_config_option('mt_collection_timing') == 'disabled') {
	echo "WARNING: Device Tracking is disabled, exiting\n";
	exit(1);
}else{
	/* for manual scans, verify if we should run or not */
	$running_processes = db_fetch_cell('SELECT count(*) FROM mac_track_processes');
	if ($running_processes) {
		$start_date = db_fetch_cell('SELECT MIN(start_date) FROM mac_track_processes');

		if (strtotime($start_date) > (time() - $max_run_duration) && !$forcerun) {
			mactrack_debug('ERROR: Can not start MAC Tracking process.  There is already one in progress');
			exit;
		}else if ($forcerun) {
			mactrack_debug('WARNING: Forcing Collection although Collection Appears in Process', true, 'MACTRACK');
			db_execute('TRUNCATE mac_track_processes');
		}else{
			mactrack_debug('WARNING: Stale data found in Device Tracking process table', true, 'MACTRACK');
			db_execute('TRUNCATE mac_track_processes');
		}
	}

	/* we don't want to see errors in the cacti log */
	errors_disable();

	if ($site_id != '') {
		mactrack_debug('About to enter Device Tracking Site Scan Processing');

		/* take time and log performance data */
		$start = microtime(true);

		collect_mactrack_data($start, $site_id);
	}else{
		mactrack_debug('About to enter Device Tracking poller processing');
		$seconds_offset = read_config_option('mt_collection_timing');
		if (($seconds_offset <> 'disabled') || $forcerun) {
			mactrack_debug('Into Processing.  Checking to determine if it\'s time to run.');
			$seconds_offset           = $seconds_offset * 60;
			/* find out if it's time to collect device information */
			$base_start_time          = read_config_option('mt_base_time');
			$database_maint_time      = read_config_option('mt_maint_time');
			$last_run_time            = read_config_option('mt_last_run_time');
			$last_db_maint_time       = read_config_option('mt_last_db_maint_time');
			$previous_base_start_time = read_config_option('mt_prev_base_time');
			$previous_db_maint_time   = read_config_option('mt_prev_db_maint_time');

			/* see if the user desires a new start time */
			mactrack_debug('Checking if user changed the start time');
			if (!empty($previous_base_start_time)) {
				if ($base_start_time <> $previous_base_start_time) {
					mactrack_debug('Detected that user changed the start time');
					unset($last_run_time);
					db_execute("DELETE FROM settings WHERE name='mt_last_run_time'");
				}
			}

			/* see if the user desires a new db maintenance time */
			mactrack_debug('Checking if user changed the maintenance time');
			if (!empty($previous_db_maint_time)) {
				if ($database_maint_time <> $previous_db_maint_time) {
					mactrack_debug('Detected that user changed the db maintenance time');
					unset($last_db_maint_time);
					db_execute("DELETE FROM settings WHERE name='mt_last_db_maint_time'");
				}
			}

			/* set to detect if the user cleared the time between polling cycles */
			db_execute_prepared('REPLACE INTO settings 
				(name, value) VALUES 
				("mt_prev_base_time", ?)', 
				array($base_start_time));

			db_execute_prepared('REPLACE INTO settings 
				(name, value) VALUES 
				("mt_prev_db_maint_time", ?)', 
				array($database_maint_time));

			/* determine the next start time */
			$current_time = strtotime('now');
			if (empty($last_run_time)) {
				if ($current_time > strtotime($base_start_time)) {
					/* if timer expired within a polling interval, then poll */
					if (($current_time - 300) < strtotime($base_start_time)) {
						$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
					}else{
						$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time) + 3600*24;
					}
				}else{
					$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
				}
			}else{
				$next_run_time = $last_run_time + $seconds_offset;
			}
			$time_till_next_run = $next_run_time - $current_time;

			if ($time_till_next_run < 0) {
				mactrack_debug('The next run time has been determined to be NOW');
			}else{
				mactrack_debug("The next run time has been determined to be at '" . date('Y-m-d G:i:s', $next_run_time) . "'");
			}

			if (empty($last_db_maint_time)) {
				if (strtotime($database_maint_time) < $current_time) {
					$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time) + 3600*24;
				}else{
					$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time);
				}
			}else{
				$next_db_maint_time = $last_db_maint_time + 24*3600;
			}

			$time_till_next_db_maint = $next_db_maint_time - $current_time;
			if ($time_till_next_db_maint < 0) {
				mactrack_debug('The next database maintenance run time has been determined to be NOW');
			}else{
				mactrack_debug("The next database maintenance run time has been determined to be at '" . date('Y-m-d G:i:s', $next_db_maint_time) . "'");
			}

			if ($time_till_next_run < 0 || $forcerun == true) {
				mactrack_debug('Either a scan has been forced, or it\'s time to check for macs');

				/* take time and log performance data */
				$start = microtime(true);

				db_execute_prepared('REPLACE INTO settings 
					(name, value) VALUES 
					("mt_last_run_time", ?)',
					array($current_time));

				collect_mactrack_data($start, $site_id);
				log_mactrack_statistics('collect');
			}

			if ($time_till_next_db_maint < 0) {
				/* take time and log performance data */
				$start = microtime(true);

				db_execute_prepared('REPLACE INTO settings 
					(name, value) VALUES 
					("mt_last_db_maint_time", ?)',
					array($current_time));

				perform_mactrack_db_maint();
				log_mactrack_statistics('maint');
			}
		}
	}

	/* show errors now */
	errors_restore();
}

function errors_disable() {
	global $track_errors;
	$track_errors = ini_get('track_errors');
	ini_set('track_errors', 0); 

	set_error_handler('mactrack_error_handler');
}

function errors_restore() {
	global $track_errors;
	ini_set('track_errors', $track_errors); 

	restore_error_handler();
}

function mactrack_error_handler($level, $message, $file, $line, $context) {
	global $phperrors;

	if (IgnoreErrorHandler($message)) {
		return true;
	}

	preg_match("/.*\/plugins\/([\w-]*)\/.*/", $file, $output_array);

	$plugin = (isset($output_array[1]) ? $output_array[1] : '');
	$error  = 'PHP ' . $phperrors[$level] . ($plugin != '' ? " in  Plugin '$plugin'" : '') . ": $message in file: $file  on line: $line";

	switch ($level) {
		case E_COMPILE_ERROR:
		case E_CORE_ERROR:
		case E_ERROR:
		case E_PARSE:
			if ($plugin != '') {
				api_plugin_disable_all($plugin);
				cacti_log("ERRORS DETECTED - DISABLING PLUGIN '$plugin'");
			}
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR PARSE');
			break;
		case E_RECOVERABLE_ERROR:
		case E_USER_ERROR:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR');
			break;
		case E_COMPILE_WARNING:
		case E_CORE_WARNING:
		case E_USER_WARNING:
		case E_WARNING:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR WARNING');
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			break;
		case E_STRICT:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR STRICT');
			break;
		default:
       		cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR');
	}

	return false;
}

function display_version() {
	global $config;

	if (!function_exists('plugin_macktrack_version')) {
		include_once($config['base_path'] . '/plugins/mactrack/setup.php');
	}

	$info = plugin_mactrack_version();
	print 'Device Tracking Master Poller, Version ' . $info['version'] . ', ' .  COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: poller_mactrack.php [-sid=site_id] [--web] [--force] [--debug]\n\n";
	print "-sid=site_id  - The mac_track_sites site_id to scan\n";
	print "-w | --web    - Display output suitable for the web\n";
	print "-f | --force  - Force the execution of a collection process\n";
	print "-d | --debug  - Display verbose output during execution\n";
}

function clear_old_processes($site_id) {
	/* get the max script runtime and kill old scripts */
	$max_script_runtime = read_config_option('mt_script_runtime');
	$delete_time = date('Y-m-d H:i:s', strtotime('-' . $max_script_runtime . ' Minutes'));

	/* remove old processes from the system if they exist */
	$old_procs = db_fetch_assoc_prepared('SELECT mtp.* 
		FROM mac_track_processes AS mtp
		INNER JOIN mac_track_devices AS mtd
		ON mtp.device_id=mtd.device_id 
		WHERE start_date < ?' . ($site_id > 0 ? ' AND site_id=' . $site_id:''), 
		array($delete_time));

	if (sizeof($old_procs)) {
		foreach($old_procs as $p) {
			if ($p['process_id'] > 0) {
				if (strstr(PHP_OS, 'WIN')) {
					exec('taskkill /pid ' . $p['process_id']);
				}else{
					exec('kill ' . $p['process_id']);
				}

				cacti_log("WARNING: Removing Hung Device Tracking Process for Device '" . $p['device_id'] . "' With Status '" . $p['status'] . "'");
			}else{
				cacti_log("WARNING: Removing Hung Device Tracking Process Entry for Device '" . $p['device_id'] . "' With Status '" . $p['status'] . "'");
			}

			db_execute_prepared('DELETE FROM mac_track_processes 
				WHERE process_id = ?', 
				array($p));
		}
	}
}

function collect_mactrack_data($start, $site_id = 0) {
	global $max_run_duration, $config, $debug, $scan_date;

	if (defined('CACTI_BASE_PATH')) {
		$config['base_path'] = CACTI_BASE_PATH;
	}

	/* dns resolver binary */
	$resolver_launched = false;

	if (read_config_option('mt_reverse_dns') == 'on') {
		$dns_resolver_required = true;
	}else{
		$dns_resolver_required = false;
	}

	/* get php binary path */
	$command_string = read_config_option('path_php_binary');

	/* save the scan date information */
	if ($site_id == '') {
		$scan_date = date('Y-m-d H:i:s');
		db_execute_prepared("REPLACE INTO settings 
			(name, value) VALUES 
			('mt_scan_date', ?)",
			array($scan_date));
	}

	/* just in case we've run too long */
	$exit_mactrack = false;

	/* start mainline processing, order by site_id to keep routers grouped with switches */
	if ($site_id > 0) {
		$device_ids = db_fetch_assoc_prepared('SELECT device_id 
			FROM mac_track_devices 
			WHERE site_id = ?
			AND disabled=""',
			array($site_id));
	}else{
		$device_ids = db_fetch_assoc("SELECT device_id 
			FROM mac_track_devices 
			WHERE disabled='' 
			ORDER BY site_id");
	}

	$total_devices = sizeof($device_ids);

	$concurrent_processes = read_config_option('mt_processes');

	if ($debug == true) {
		$e_debug = ' -d';
	}else{
		$e_debug = '';
	}

	if ($site_id) {
		$e_site = " -sid=$site_id";
	}else{
		$e_site = '';
	}

	/* add the parent process to the process list */
	db_process_add('-1');

	if ($total_devices > 0) {
		/* grab arpwatch data */
		if (read_config_option('mt_arpwatch') == 'on') {
			$arp_db     = read_config_option('mt_arpwatch_path');
			$delim      = read_config_option('mt_mac_delim');
			$mac_ip_dns = array();

			if (file_exists($arp_db)) {
				$arp_dat = fopen($arp_db, 'r');

				if ($arp_dat) {
					while (!feof($arp_dat)) {
						$line = fgets($arp_dat, 4096);

						if ($line != null) {
							$line = explode ('	', $line);

							$mac_ad = explode(':',$line[0]);
							for ($k=0;$k<6;$k++) {
								$mac_ad[$k] = strtoupper($mac_ad[$k]);
								if (1 == strlen($mac_ad[$k])) {
									$mac_ad[$k] = '0' . $mac_ad[$k];
								}
							}

							/* create the mac address */
							$mac = $mac_ad[0] . $delim . $mac_ad[1] . $delim . $mac_ad[2] . $delim . $mac_ad[3] . $delim . $mac_ad[4] . $delim . $mac_ad[5];

							/* update the array */
							$mac_ip_dns[$mac]['ip']  = $line[1];
							$mac_ip_dns[$mac]['dns'] = $line[3];
						}
					}
					fclose($arp_dat);

					mactrack_debug('ARPWATCH: IP, DNS & MAC collection complete with ArpWatch');
				}else{
					cacti_log("ERROR: cannot open file ArpWatch database '$arp_db'");exit;
				}
			}
		}

		/* scan through all devices */
		$j = 0;
		$i = 0;
		$last_time = strtotime('now');
		$processes_available = $concurrent_processes;
		while ($j < $total_devices) {
			/* retreive the number of concurrent mac_track processes to run */
			/* default to 10 for now */
			$concurrent_processes = db_fetch_cell("SELECT value FROM settings WHERE name='mt_processes'");

			for ($i = 0; $i < $processes_available; $i++) {
				if (($j+$i) >= $total_devices) break;

				$extra_args = ' -q ' . $config['base_path'] . '/plugins/mactrack/mactrack_scanner.php -id=' . $device_ids[$i+$j]['device_id'] . $e_debug;
				mactrack_debug('CMD: ' . $command_string . $extra_args);
				exec_background($command_string, $extra_args);
			}
			$j = $j + $i;

			/* launch the dns resolver if it hasn't been yet */
			if (($dns_resolver_required) && (!$resolver_launched)) {
				sleep(2);
				exec_background($command_string, ' -q ' . $config['base_path'] . '/plugins/mactrack/mactrack_resolver.php' . $e_debug . $e_site);
				$resolver_launched = true;
				mactrack_debug('DNS Resolver process launched');
			}

			mactrack_debug('A process cycle launch just completed.');

			/* wait the correct number of seconds for proccesses prior to
			   attempting to update records */
			sleep(2);
			$current_time = strtotime('now');
			if (($current_time - $last_time) > read_config_option('mt_dns_prime_interval')) {
				/* resolve some ip's to mac addresses to let the resolver knock them out */
				db_execute_prepared('UPDATE mac_track_temp_ports
					INNER JOIN mac_track_ips
					ON mac_track_temp_ports.mac_address=mac_track_ips.mac_address
					AND mac_track_temp_ports.site_id=mac_track_ips.site_id
					SET mac_track_temp_ports.ip_address=mac_track_ips.ip_address,
					mac_track_temp_ports.updated=1
					WHERE mac_track_temp_ports.updated=0 
					AND mac_track_ips.scan_date = ?',
					array($scan_date));

				mactrack_debug('Interum IP addresses to MAC addresses association pass complete.');

				$last_time = $current_time;
			}

			$processes_running = db_fetch_cell('SELECT count(*) FROM mac_track_processes');

			if ($dns_resolver_required) {
				$processes_available = $concurrent_processes - $processes_running + 1;
			}else{
				$processes_available = $concurrent_processes - $processes_running;
			}

			/* take time to check for an exit condition */
			$current = microtime(true);

			/* exit if we've run too long */
			if (($current - $start) > $max_run_duration) {
				$exit_mactrack = true;
				cacti_log('ERROR: Device Trackin timed out during main script processing.');
				db_execute("DELETE FROM settings WHERE name='mactrack_process_status'");
				db_process_remove('-1');
				break;
			}else{
				db_execute("REPLACE INTO settings SET name='mactrack_process_status', value='Total:$total_devices Completed:$j'");
			}
		}

		/* wait for last process to exit */
		$processes_running = db_fetch_cell('SELECT count(*) FROM mac_track_processes WHERE device_id > 0');
		while (($processes_running > 0) && (!$exit_mactrack)) {
			$processes_running = db_fetch_cell('SELECT count(*) 
				FROM mac_track_processes 
				WHERE device_id > 0');

			$devices_running = db_fetch_cell("SELECT group_concat(CAST(`device_id` as CHAR) SEPARATOR ', ') as t 
				FROM mac_track_processes");

			/* wait the correct number of seconds for proccesses prior to
			   attempting to update records */
			sleep(2);
			$current_time = strtotime('now');

			if (($current_time - $last_time) > read_config_option('mt_dns_prime_interval')) {
				/* resolve some ip's to mac addresses to let the resolver knock them out */
				db_execute_prepared('UPDATE mac_track_temp_ports
					INNER JOIN mac_track_ips
					ON mac_track_temp_ports.mac_address=mac_track_ips.mac_address
					AND mac_track_temp_ports.site_id=mac_track_ips.site_id
					SET mac_track_temp_ports.ip_address=mac_track_ips.ip_address,
					mac_track_temp_ports.updated=1
					WHERE mac_track_temp_ports.updated=0 
					AND mac_track_ips.scan_date = ?',
					array($scan_date));

				mactrack_debug('Interum IP addresses to MAC addresses association pass complete.');
			}

			/* take time to check for an exit condition */
			$current = microtime(true);

			/* exit if we've run too long */
			if (($current - $start) > $max_run_duration) {
				$exit_mactrack = true;
				cacti_log('ERROR: Device Tracking timed out during main script processing.');
				break;
			}

			mactrack_debug('Waiting on ' . $processes_running . ' with id = [' . $devices_running .'] to complete prior to exiting.');
		}

		/* if arpwatch is enabled, let's let it pick up the stragglers, based upon IP address first */
		if ((read_config_option('mt_arpwatch') == 'on') && (sizeof($mac_ip_dns))) {
			$ports = db_fetch_assoc('SELECT site_id, device_id, mac_address
				FROM mac_track_temp_ports
				WHERE updated = 0');

			if (sizeof($ports)) {
				foreach($ports as $port) {
					if (isset($mac_ip_dns[$port['mac_address']])) {
						db_execute("UPDATE mac_track_temp_ports
							SET updated=1, ip_address='" . $mac_ip_dns[$port['mac_address']]['ip'] . "'" .
							($mac_ip_dns[$port['mac_address']]['dns'] != '' ? ", dns_hostname='" . $mac_ip_dns[$port['mac_address']]['dns'] . "'" : '') . '
							WHERE site_id=' . $port['site_id'] . '
							AND device_id=' . $port['device_id'] . "
							AND mac_address='" . $port['mac_address'] . "'");
					}
				}
			}
		}

		/* resolve some ip's to mac addresses to let the resolver knock them out */
		db_execute_prepared('UPDATE mac_track_temp_ports
			INNER JOIN mac_track_ips
			ON mac_track_temp_ports.mac_address=mac_track_ips.mac_address
			AND mac_track_temp_ports.site_id=mac_track_ips.site_id
			SET mac_track_temp_ports.ip_address=mac_track_ips.ip_address
			WHERE mac_track_temp_ports.updated=0 
			AND mac_track_ips.scan_date = ?', 
			array($scan_date));

		mactrack_debug('Interum IP addresses to MAC addresses association pass complete.');

		/* populate the vendor_macs for this pass */
		db_execute('UPDATE mac_track_temp_ports 
			SET vendor_mac=SUBSTRING(mac_address,1,8)');

		mactrack_debug('MAC addresses to Vendor MACS association pass complete.');

		/* update the vlan id's table */
		db_execute("UPDATE mac_track_vlans SET present='0'");

		db_execute("INSERT INTO mac_track_vlans 
			(vlan_id, site_id, device_id, vlan_name, present) 
			SELECT vlan_id, site_id, device_id, vlan_name, '1' AS present 
			FROM mac_track_temp_ports 
			ON DUPLICATE KEY UPDATE vlan_name=VALUES(vlan_name), present=VALUES(present);");

		db_execute("DELETE FROM mac_track_vlans WHERE present='0'");

		mactrack_debug('MAC VLANs in VLAN Table Updated.');

		/* let the resolver know that the parent process is finished and then wait
		   for the resolver if applicable */
		db_process_remove('-1');

		while (!$exit_mactrack) {
			/* checking to see if the resolver is running */
			$resolver_running = db_fetch_row('SELECT * 
				FROM mac_track_processes 
				WHERE device_id = 0');

			if (sizeof($resolver_running) == 0) {
				break;
			}

			/* take time to check for an exit condition */
			$current = microtime(true);

			/* exit if we've run too long */
			if (($current - $start) > $max_run_duration) {
				$exit_mactrack = true;
				cacti_log('ERROR: Device Tracking timed out during main script processing.');
				break;
			}
		}

		/* transfer temp port results into permanent table */
		db_execute('INSERT INTO mac_track_ports
			(site_id, device_id, hostname, dns_hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address,
			port_number, port_name, scan_date, authorized)
			SELECT site_id, device_id, hostname, dns_hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address,
			port_number, port_name, scan_date, authorized
			FROM mac_track_temp_ports
			ON DUPLICATE KEY UPDATE site_id=VALUES(site_id), hostname=VALUES(hostname),
			device_name=VALUES(device_name), vlan_id=VALUES(vlan_id), vlan_name=VALUES(vlan_name),
			vendor_mac=VALUES(vendor_mac), ip_address=VALUES(ip_address), dns_hostname=VALUES(dns_hostname),
			port_name=VALUES(port_name), authorized=VALUES(authorized)');

		mactrack_debug('Finished transferring scan results to main table.');

		/* transfer the subnet information, although primative, into the ip_ranges table */
		$ip_ranges = db_fetch_assoc("SELECT SUBSTRING_INDEX(`ip_address`,'.',3) AS ip_range,
			site_id,
			COUNT(DISTINCT ip_address) AS ips_current,
			scan_date AS ips_current_date
			FROM mac_track_temp_ports
			WHERE ip_address != ''
			GROUP BY ip_range, site_id");

		if (is_array($ip_ranges)) {
			foreach($ip_ranges as $ip_range) {
				$range_record = db_fetch_row_prepared('SELECT * 
					FROM mac_track_ip_ranges 
					WHERE ip_range = ?
					AND site_id = ?', 
					array($ip_range['ip_range'], $ip_range['site_id']));

				if (sizeof($range_record) == 0) {
					db_execute_prepared('REPLACE INTO `mac_track_ip_ranges`
						(ip_range, site_id, ips_current, ips_current_date)
						VALUES (?, ?, ?, ?)',
						array(
							$ip_range['ip_range'],
							$ip_range['site_id'],
							$ip_range['ips_current'],
							$ip_range['ips_current_date']
						)
					);
				}else{
					db_execute_prepared('UPDATE mac_track_ip_ranges
						SET ips_current = ?,
						ips_current_date = ?, 
						WHERE ip_range = ?
						AND site_id = ?', 
						array(
							$ip_range['ips_current'], 
							$ip_range['ips_current_date'], 
							$range_record['ip_range'], 
							$range_record['site_id']
						)
					);
				}
			}
		}

		/* update the max values if required */
		db_execute('UPDATE `mac_track_ip_ranges`
			SET ips_max=ips_current, ips_max_date=ips_current_date
			WHERE ips_current > ips_max');

		/* collect statistics */
		if ($site_id == 0) {
			$stats = db_fetch_assoc('SELECT site_id,
				count(device_id) as total_devices,
				sum(ports_active) as total_oper_ports,
				sum(macs_active) as total_macs,
				sum(ports_total) as total_user_ports
				FROM mac_track_devices
				GROUP BY site_id');
		}else{
			$stats = db_fetch_assoc_prepared('SELECT site_id,
				count(device_id) as total_devices,
				sum(ports_active) as total_oper_ports,
				sum(macs_active) as total_macs,
				sum(ports_total) as total_user_ports
				FROM mac_track_devices
				WHERE site_id = ?
				GROUP BY site_id',
				array($site_id));
		}

		/* collect total device errors */
		$errors = db_fetch_assoc('SELECT site_id, snmp_status, count(device_id) as total_device_errors
			FROM mac_track_devices
			GROUP BY site_id, snmp_status');

		$ips = array_rekey(
			db_fetch_assoc_prepared('SELECT site_id, count(ip_address) as total_ips
				FROM mac_track_ips
				WHERE scan_date = ?
				GROUP BY site_id',
				array($scan_date)), 
			'site_id', 'total_ips'
		);

		foreach($errors as $error) {
			if (!isset($error_count[$error['site_id']])) {
				$error_count[$error['site_id']] = 0;
			}

			if ($error['snmp_status'] <> 3) {
				$error_count[$error['site_id']] += $error['total_device_errors'];
			}
		}

		foreach($stats as $stat) {
			if (isset($ips[$stat['site_id']])) {
				$num_ips = $ips[$stat['site_id']];
			} else {
				$num_ips = 0;
			}

			db_execute_prepared('UPDATE mac_track_sites 
				SET total_devices = ?, total_ips = ?, total_macs = ?, 
				total_oper_ports = ?, total_user_ports = ?, total_device_errors = ?  
				WHERE site_id = ?',
				array(
					$stat['total_devices'],
					$num_ips,
					$stat['total_macs'],
					$stat['total_oper_ports'],
					$stat['total_user_ports'],
					$error_count[$stat['site_id']],
					$stat['site_id']
				)
			);
		}

		mactrack_debug('Finished updating site table with collection statistics.');

		/* process macwatch data */
		$macwatches = db_fetch_assoc('SELECT * FROM mac_track_macwatch');
		if (sizeof($macwatches)) {
			$from     = read_config_option('mt_from_email');
			$fromname = read_config_option('mt_from_name');

			foreach($macwatches as $record) {
				/* determine if we should check this one */
				$found = db_fetch_row_prepared('SELECT *
					FROM mac_track_temp_ports
					WHERE mac_address = ?',
					array($record['mac_address']));

				if (sizeof($found)) {
					/* set the subject */
					$subject = "MACAUTH Notification: Mac Address '" . $record['mac_address'] . "' Found, For: '" . $record['name'] . "'";

					/* set the message with replacements */
					$message = str_replace('<IP>', $found['ip_address'], $record['description']);
					$message = str_replace('<MAC>', $found['mac_address'], $message);
					$message = str_replace('<TICKET>', $record['ticket_number'], $message);
					$message = str_replace('<SITENAME>', db_fetch_cell_prepared('SELECT site_name FROM mac_track_sites WHERE site_id = ?', array($found['site_id'])), $message);
					$message = str_replace('<DEVICEIP>', $found['hostname'], $message);
					$message = str_replace('<DEVICENAME>', $found['device_name'], $message);
					$message = str_replace('<PORTNUMBER>', $found['port_number'], $message);
					$message = str_replace('<PORTNAME>', $found['port_name'], $message);

					/* send out the email */
					if (!$record['discovered'] || $record['notify_schedule'] >= '2') {
						$mail = true;

						if ($record['notify_schedule'] > 2) {
							if (strtotime($record['date_last_notif']) + $record['notify_schedule'] > time()) {
								$mail = false;
							}
						}

						if ($mail) {
							mactrack_mail($record['email_addresses'], $from, $fromname, $subject, $message, $headers = '');
						}
					}

					/* update the the correct information */
					db_execute_prepared('UPDATE mac_track_macwatch
						SET discovered=1, date_last_seen=NOW()' .
						(strtotime($record['date_first_seen']) == 0 ? ', date_first_seen=NOW()':'') . '
						WHERE mac_address = ?', 
						array($record['mac_address']));
				}
			}
		}

		/* process macauth data */
		$mac_auth_frequency = read_config_option('mt_macauth_email_frequency');
		if ($mac_auth_frequency != 'disabled') {
			$last_macauth_time = read_config_option('mt_last_macauth_time');

			/* if it's time to e-mail */
			if (($last_macauth_time + ($mac_auth_frequency*60) > time()) ||
				($mac_auth_frequency == 0)) {
				mactrack_process_mac_auth_report($mac_auth_frequency, $last_macauth_time);
			}
		}

		/* process aggregated data */
		db_execute('UPDATE mac_track_aggregated_ports SET active_last=0');
		db_execute('INSERT INTO mac_track_aggregated_ports
			(site_id, device_id, hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address, dns_hostname,
			port_number, port_name, date_last, first_scan_date, count_rec, active_last, authorized)
			SELECT site_id, device_id, hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address, dns_hostname,
			port_number, port_name, scan_date, scan_date, 1, 1, authorized
			FROM mac_track_temp_ports
			ON DUPLICATE KEY UPDATE count_rec=count_rec+1, active_last=1, date_last=mac_track_temp_ports.scan_date');

		/* purge the ip address and temp port table */
		db_execute('TRUNCATE TABLE mac_track_temp_ports');

		db_execute_prepared('DELETE FROM mac_track_ips WHERE scan_date < ?', array($scan_date));

		db_execute('OPTIMIZE TABLE mac_track_ips');
		db_execute('TRUNCATE TABLE mac_track_scan_dates');
		db_execute('REPLACE INTO mac_track_scan_dates (SELECT DISTINCT scan_date from mac_track_ports)');
	}else{
		cacti_log('NOTE: MACTRACK has no devices to process at this time', false, 'MACTRACK');
	}
}

function mactrack_process_mac_auth_report($mac_auth_frequency, $last_macauth_time) {
	if ($mac_auth_frequency == 0) {
		$ports = db_fetch_assoc('SELECT mac_track_temp_ports.*, mac_track_sites.site_name
			FROM mac_track_temp_ports
			LEFT JOIN mac_track_sites
			ON mac_track_sites.site_id=mac_track_temp_ports.site_id
			WHERE authorized=0');
	}else{
		$ports = db_fetch_assoc('SELECT mac_track_ports.*, mac_track_sites.site_name
			FROM mac_track_ports
			LEFT JOIN mac_track_sites
			ON mac_track_sites.site_id=mac_track_temp_ports.site_id
			WHERE authorized=0');
	}

	if (sizeof($ports)) {
		foreach($ports as $port) {
			/* create the report */
		}

		/* email the report */
	}else{
		if ($mac_auth_frequency > 0) {
			/* send out an empty report */
		}
	}
}

function log_mactrack_statistics($type = 'collect') {
	global $start, $site_id;

	/* let's get the number of devices */
	if (is_numeric($site_id)) {
		$devices = db_fetch_cell_prepared('SELECT Count(*) FROM mac_track_devices WHERE site_id = ?', array($site_id));
	}else{
		$devices = db_fetch_cell('SELECT Count(*) FROM mac_track_devices');
	}

	$concurrent_processes = read_config_option('mt_processes');

	/* take time and log performance data */
	$end = microtime(true);

	if ($type == 'collect') {
		$cacti_stats = sprintf(
			'Time:%01.4f ' .
			'ConcurrentProcesses:%s ' .
			'Devices:%s ',
			round($end-$start,4),
			$concurrent_processes,
			$devices);

		/* log to the database */
		db_execute_prepared('REPLACE INTO settings 
			(name,value) VALUES 
			("stats_mactrack", ?)',
			array($cacti_stats));

		/* log to the logfile */
		cacti_log('MACTRACK STATS: ' . $cacti_stats, true, 'SYSTEM');
	}else{
		$cacti_stats = sprintf('Time:%01.4f', round($end-$start,4));

		/* log to the database */
		db_execute('REPLACE INTO settings 
			(name,value) VALUES 
			("stats_mactrack_maint", ?)',
			array($cacti_stats));

		/* log to the logfile */
		cacti_log('MACTRACK MAINT STATS: ' . $cacti_stats, true, 'SYSTEM');
	}
}

