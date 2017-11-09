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

/* We are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

/* Start Initialization Section */
include('./include/global.php');

if (read_config_option('mt_collection_timing') != 'disabled') {
	global $debug;

	/* initialize variables */
	$debug    = FALSE;
	$engine   = 'InnoDB';
	$days     = 30;

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
				case '-d':
				case '--debug':
					$debug = TRUE;
					break;
				case '--days':
					$days = $value;
					break;
				case '-e':
				case '--engine':
					$engine = $value;
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

	$engine = strtoupper($engine);

	if ($engine != 'MYISAM' && $engine != 'INNODB') {
		print "FATAL: Only MyISAM and InnoDB Available '$engine' not recognized\n";
		exit -1;
	}

	if (!is_numeric($days) || $days > 360 || $days < 10) {
		print "FATAL: Days Range is from 10 - 360, Value '$days' Invalid\n";
		exit -1;
	}

	$partitioning = db_fetch_cell("SHOW GLOBAL VARIABLES LIKE 'have_partitioning'");

	if ($partioning == 'YES') {
		mactrack_create_partitioned_table($engine, $days, true);
	}else{
		echo "FATAL: Partitioning Not Available, Exiting!\n";
	}
}

function mactrack_create_partitioned_table($engine = 'InnoDB', $days = 30, $migrate = false) {
	global $config;

	/* rename the original table */
	db_execute('RENAME TABLE `mac_track_ports` TO `mac_track_ports_backup`');

	$sql = "CREATE TABLE `mac_track_ports` (
		`site_id` int(10) unsigned NOT NULL default '0',
		`device_id` int(10) unsigned NOT NULL default '0',
		`hostname` varchar(40) NOT NULL default '',
		`device_name` varchar(100) NOT NULL default '',
		`vlan_id` varchar(5) NOT NULL default 'N/A',
		`vlan_name` varchar(50) NOT NULL default '',
		`mac_address` varchar(20) NOT NULL default '',
		`vendor_mac` varchar(8) default NULL,
		`ip_address` varchar(20) NOT NULL default '',
		`dns_hostname` varchar(200) default '',
		`port_number` varchar(10) NOT NULL default '',
		`port_name` varchar(50) NOT NULL default '',
		`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
		`authorized` tinyint(3) unsigned NOT NULL default '0',
		PRIMARY KEY  (`port_number`,`scan_date`,`mac_address`,`device_id`),
		KEY `site_id` (`site_id`),
		KEY `scan_date` USING BTREE (`scan_date`),
		KEY `description` (`device_name`),
		KEY `mac` (`mac_address`),
		KEY `hostname` (`hostname`),
		KEY `vlan_name` (`vlan_name`),
		KEY `vlan_id` (`vlan_id`),
		KEY `device_id` (`device_id`),
		KEY `ip_address` (`ip_address`),
		KEY `port_name` (`port_name`),
		KEY `dns_hostname` (`dns_hostname`),
		KEY `vendor_mac` (`vendor_mac`),
		KEY `authorized` (`authorized`),
		KEY `site_id_device_id` (`site_id`,`device_id`))
		ENGINE=$engine
		COMMENT='Database for Tracking Device MACs'
		PARTITION BY RANGE (TO_DAYS(scan_date))\n";

	$now = time();

	$parts = '';

	for($i = $days; $i > 0; $i--) {
		$timestamp = $now - ($i * 86400);
		$date     = date('Y-m-d', $timestamp);
		$format   = date('Ymd', $timestamp);
		$parts .= ($parts != '' ? ",\n":'(') . ' PARTITION d' . $format . " VALUES LESS THAN (TO_DAYS('" . $date . "'))";
	}

	$parts .= ",\nPARTITION dMaxValue VALUES LESS THAN MAXVALUE);";

	$return_value = db_execute($sql . $parts);

	if ($return_value) {
		if ($migrate) {
			print "NOTE: Migrating Old Data to Partitioned Tables\n";

			$scan_dates = db_fetch_assoc('SELECT DISTINCT scan_date 
				FROM mac_track_ports_backup');

			if (sizeof($scan_dates)) {
				foreach($scan_dates as $sd) {
					db_execute_prepared('INSERT INTO mac_track_ports 
						SELECT * 
						FROM mac_track_ports_backups 
						WHERE scan_date = ?', 
						array($sd['scan_date']));

					db_execute_prepared('DELETE FROM mac_track_ports_backup 
						WHERE scan_date = ?',
						array($sd['scan_date']));
				}
			}
		}

		db_execute('DROP TABLE mac_track_ports_backup');

		db_execute('REPLACE INTO `settings` 
			SET name = "mt_data_retention", value = ?',
			array($days));
	}else{
		print "FATAL: Conversion to Partitioned Table Failed\n";

		/* rename the original table */
		db_execute('RENAME TABLE `mac_track_ports_backup` TO `mac_track_ports`');
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_mactrack_version')) {
		include_once($config['base_path'] . '/plugins/mactrack/setup.php');
	}

	$info = plugin_mactrack_version();

	print 'Device Tracking Convert Partitioned, Version ' . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: mactrack_convert.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "--engine=N    - Database Engine.  Value are 'MyISAM' or 'InnoDB'\n";
	print "--days=30     - Days to Retain.  Valid Range is 10-360\n";
	print "-d | --debug  - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - Display this help message\n";
}

