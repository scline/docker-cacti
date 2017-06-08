<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/global.php');
include_once('./lib/poller.php');
include('./plugins/syslog/config.php');
include_once('./plugins/syslog/functions.php');

/* Let it run for an hour if it has to, to clear up any big
 * bursts of incoming syslog events
 */
ini_set('max_execution_time', 3600);
ini_set('memory_limit', '256M');

global $syslog_debug;

$syslog_debug = true;

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
			case '--debug':
			case '-d':
				$syslog_debug = true;

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
				exit(0);
			default:
				echo "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

/* record the start time */
list($micro,$seconds) = explode(' ', microtime());
$start_time = $seconds + $micro;

/* Connect to the Syslog Database */
global $syslog_cnn, $cnn_id, $database_default;
if (empty($syslog_cnn)) {
	if ((strtolower($database_hostname) == strtolower($syslogdb_hostname)) &&
		($database_default == $syslogdb_default)) {
		/* move on, using Cacti */
		$syslog_cnn = $cnn_id;
	}else{
		if (!isset($syslogdb_port)) {
			$syslogdb_port = '3306';
		}
		$syslog_cnn = db_connect_real($syslogdb_hostname, $syslogdb_username, $syslogdb_password, $syslogdb_default, $syslogdb_type, $syslogdb_port);
	}
}

/* If Syslog Collection is Disabled, Exit Here */
if (read_config_option('syslog_enabled') == '') {
	print "NOTE: Syslog record transferral and alerting/reporting is disabled.  Exiting\n";
	exit -1;
}

/* remove records that don't need to to be transferred */
syslog_debug('Syslog Batch Transfer / Remove Process started ...... ');
$syslog_items   = syslog_manage_items('syslog', 'syslog_removed');
$syslog_removed = $syslog_items['removed'];
$syslog_xferred = $syslog_items['xferred'];
syslog_debug("Removed     " . $syslog_removed . ",  Message(s) from the 'syslog' table");
syslog_debug("Xferred     " . $syslog_xferred . ",  Message(s) to the 'syslog_removed' table");

syslog_debug('Finished processing...');

function display_version() {
	global $config;

	if (!function_exists('plugin_syslog_version')) {
		include_once($config['base_path'] . '/plugins/syslog/setup.php');
	}

	$info = plugin_syslog_version();
	echo "Syslog Batch Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: syslog_batch_transfer.php [--debug|-d]\n\n";
	echo "The Syslog batch process script for Cacti Syslogging.\n";
	echo "This script removes old messages from main view.\n";
}


