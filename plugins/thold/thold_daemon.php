#!/usr/bin/php
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
 | This program is snmpagent in the hope that it will be useful,           |
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
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

/* check if poller daemon is already running */
exec('ps -ef | grep -v grep | grep -v "sh -c" | grep thold_daemon.php', $output);
if(sizeof($output)>=2) {
    fwrite( STDOUT, "Thold Daemon is still running\n");
    return;
}

/* we are not talking to the browser */
$no_http_headers = true;

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

/* we do not need so much memory */
ini_set('memory_limit', '32M');

chdir(dirname(__FILE__));
chdir('../../');

fwrite(STDOUT, 'Starting Thold Daemon ... ');

if(function_exists('pcntl_fork')) {
    /* fork the current process to bring a real new daemon on the road */
    $pid = pcntl_fork();
    if($pid == -1) {
        /* oha ... something went wrong :( */
        fwrite(STDOUT, '[FAILED]' . PHP_EOL);
        return false;
    }elseif($pid == 0) {
        /* the child should do nothing as long as the parent is still alive */
    }else {
        /* return the PID of the new child and kill the parent */
		fwrite(STDOUT, '[OK]' . PHP_EOL);
        return true;
    }
}else {
    fwrite(STDOUT, '[WARNING] This system does not support forking.' . PHP_EOL);
}

require_once('./include/global.php');
require_once($config['base_path'] . '/lib/poller.php');

db_execute("TRUNCATE plugin_thold_daemon_processes");
db_execute("TRUNCATE plugin_thold_daemon_data");
db_execute("UPDATE thold_data SET thold_daemon_pid = ''");

$path_php_binary = read_config_option('path_php_binary');

while(true) {
	if (thold_db_connection()) {
		/* initiate concurrent background processes as long as we do not hit the limits */
		$queue = db_fetch_assoc('SELECT * FROM `plugin_thold_daemon_processes` WHERE start = 0 ORDER BY `pid`');
		$queued_processes = sizeof($queue);

		if ($queued_processes) {
			$thold_max_concurrent_processes = read_config_option('thold_max_concurrent_processes');
			$running_processes              = db_fetch_cell('SELECT COUNT(*) FROM `plugin_thold_daemon_processes` WHERE start != 0 AND end = 0');
			$free_processes                 = $thold_max_concurrent_processes - $running_processes;

			if($free_processes > 0) {
				for($i=0; $i<$free_processes; $i++) {
					if(isset($queue[$i])) {
						$pid = $queue[$i]['pid'];
						exec($path_php_binary . ' ' . $config['base_path'] . '/plugins/thold/thold_process.php ' . "--pid=$pid > /dev/null &");
					}else {
						break;
					}
				}
			}
		}
	} else {
		/* try to reconnect */
		thold_db_reconnect();
	}

	sleep(2);
}

function thold_db_connection(){
	global $cnn_id;

	if ($cnn_id) {
		$cacti_version = db_fetch_cell("SELECT cacti FROM version");

		return is_null($cacti_version) ? FALSE : TRUE;
	}

	return FALSE;
}

function thold_db_reconnect(){
	global $cnn_id, $database_type, $database_default, $database_hostname, $database_username, $database_password, $database_port, $database_ssl;

	chdir(dirname(__FILE__));

	include_once("../../include/config.php");

	if (is_object($cnn_id)) {
		db_close();
	}

	/* connect to the database server */
	return db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);
}

?>
