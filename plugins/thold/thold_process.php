<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2017 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Thold Sub Process terminated by user', FALSE, 'thold');

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = TRUE;

chdir(dirname(__FILE__));
chdir('../../');

require_once('./include/global.php');
require($config['base_path'] . '/plugins/thold/includes/arrays.php');
require_once($config['base_path'] . '/plugins/thold/thold_functions.php');
require_once($config['library_path'] . '/snmp.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
list($micro,$seconds) = explode(' ', microtime());
$start = $seconds + $micro;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);
$pid			= false;
$debug          = false;

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
			case '-pid':
			case '--pid':
				@list($partA, $partB) = @explode('_', $value);
				if(is_numeric($partA) && is_numeric($partB)) {
					$pid = $value;
				}else {
					print 'ERROR: Invalid Process ID ' . $arg . "\n\n";
					display_help();
					exit;
				}
				break;
			case '-v':
			case '--version':
			case '-V':
				display_version();
				exit;
			case '--help':
			case '-h':
			case '-H':
				display_help();
				exit;
			exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
		}
	}
}

if($pid === false) {
	display_help();
}else {
	db_execute("UPDATE `plugin_thold_daemon_processes` SET `start` = " . time() . " WHERE `pid` = '" . $pid . "'");
}

$sql_query = "SELECT tdd.id, tdd.rrd_reindexed, tdd.rrd_time_reindexed, 
	td.id AS thold_id td.name AS thold_name, td.local_graph_id,
	td.percent_ds, td.expression, td.data_type, td.cdef, td.local_data_id,
	td.data_template_rrd_id, td.lastread,
	UNIX_TIMESTAMP(td.lasttime) AS lasttime, td.oldvalue,
	dtr.data_source_name as name, dtr.data_source_type_id, 
	dtd.rrd_step, dtr.rrd_maximum
	FROM plugin_thold_daemon_data AS tdd
	INNER JOIN thold_data AS td
	ON td.id = tdd.id
	LEFT JOIN data_template_rrd AS dtr
	ON dtr.id = td.data_template_rrd_id
	LEFT JOIN data_template_data AS dtd
	ON dtd.local_data_id = td.local_data_id
	WHERE tdd.pid = '$pid'
	AND dtr.data_source_name!=''";

$tholds = db_fetch_assoc($sql_query, false);

if (sizeof($tholds)) {
	$rrd_reindexed = array();
	$rrd_time_reindexed = array();

	foreach ($tholds as $thold_data) {
		thold_debug("Checking Threshold Name: '" . $thold_data['thold_name'] . "', Graph: '" . $thold_data['local_graph_id'] . "'");
		$item = array();
		$rrd_reindexed[$thold_data['local_data_id']] = unserialize($thold_data['thold_server_rrd_reindexed']);
		$rrd_time_reindexed[$thold_data['local_data_id']] = $thold_data['thold_server_rrd_time_reindexed'];
		$currenttime = 0;
		$currentval = thold_get_currentval($thold_data, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);

		switch ($thold_data['data_type']) {
		case 0:
			break;
		case 1:
			if ($thold_data['cdef'] != 0) {
				$currentval = thold_build_cdef($thold_data['cdef'], $currentval, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			}
			break;
		case 2:
			if ($thold_data['percent_ds'] != '') {
				$currentval = thold_calculate_percent($thold_data, $currentval, $rrd_reindexed);
			}
			break;
		case 3:
			if ($thold_data['expression'] != '') {
				$currentval = thold_calculate_expression($thold_data, $currentval, $rrd_reindexed, $rrd_time_reindexed);
			}
			break;
		}

		if (is_numeric($currentval)) {
			$currentval = round($currentval, 4);
		}else{
			$currentval = '';
		}

		db_execute("UPDATE thold_data SET 
			tcheck=1, lastread='$currentval',
			lasttime='" . date('Y-m-d H:i:s', $currenttime) . "',
			oldvalue='" . $item[$thold_data['name']] . "'
			WHERE id = " . $thold_data['thold_id']);
	}

	/* check all thresholds */
	$sql_query = "SELECT td.*
		FROM plugin_thold_daemon_data AS tdd
		INNER JOIN thold_data AS td
		ON td.id = tdd.id
		LEFT JOIN data_template_rrd AS dtr
		ON dtr.id = td.data_template_rrd_id
		WHERE tdd.pid = '$pid' 
		AND td.thold_enabled='on' 
		AND td.tcheck=1";

	$tholds = api_plugin_hook_function('thold_get_live_hosts', db_fetch_assoc($sql_query));

	$total_tholds = sizeof($tholds);
	foreach ($tholds as $thold) {
		thold_check_threshold($thold);
	}

	db_execute("UPDATE thold_data SET thold_data.thold_server_pid = '', tcheck=0 WHERE thold_data.thold_server_pid = '$pid'");
	db_execute("DELETE FROM `plugin_thold_daemon_data` WHERE `pid` = '$pid'");
	db_execute("UPDATE `plugin_thold_daemon_processes` SET `end` = " . time() . ", `processed_items` = " . $total_tholds);
}

function display_version() {
	global $config;
	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	echo "Threshold Processor, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: thold_process.php --pid=N [--debug]\n\n";
	print "The main Threshold processor for the Thold Plugin.\n";
}
