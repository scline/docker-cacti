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

global $syslog_debug, $syslog_facilities, $syslog_levels;

$syslog_debug = false;
$forcer = false;

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
			case '--force-report':
			case '-F':
				$forcer = true;

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
				echo "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

/* record the start time */
list($micro,$seconds) = explode(' ', microtime());
$start_time = $seconds + $micro;

if ($config['poller_id'] > 1) {
	exit;
}

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

/* initialize some uninitialized variables */
$r = read_config_option('syslog_retention');
if ($r == '' or $r < 0 or $r > 365) {
	if ($r == '') {
		$sql = 'REPLACE INTO `' . $database_default . "`.`settings` (name, value) VALUES ('syslog_retention','30')";
	}else{
		$sql = 'UPDATE `' . $database_default . "`.`settings` SET value='30' WHERE name='syslog_retention'";
	}

	$result = db_execute($sql);

	kill_session_var('sess_config_array');
}

$alert_retention = read_config_option('syslog_alert_retention');
if ($alert_retention == '' || $alert_retention < 0 || $alert_retention > 365) {
	if ($alert_retention == '') {
		$sql = 'REPLACE INTO `' . $database_default . "`.`settings` (name, value) VALUES ('syslog_alert_retention','30')";
	}else{
		$sql = 'UPDATE `' . $database_default . "`.`settings` SET value='30' WHERE name='syslog_alert_retention'";
	}

	$result = db_execute($sql);

	kill_session_var('sess_config_array');
}

/* delete old syslog and syslog soft messages */
if (!syslog_is_partitioned()) {
	syslog_debug('Syslog Table is NOT Partitioned');
	$syslog_deleted = syslog_traditional_manage();
}else{
	syslog_debug('Syslog Table IS Partitioned');
	$syslog_deleted = syslog_partition_manage();
}

/* get a uniqueID to allow moving of records to done table */
while (1) {
	$uniqueID = rand(1, 127);
	$count    = syslog_db_fetch_cell('SELECT count(*) FROM `' . $syslogdb_default . '`.`syslog_incoming` WHERE status=' . $uniqueID);

	if ($count == 0) {
		break;
	}
}

syslog_debug('Unique ID = ' . $uniqueID);

/* flag all records with the uniqueID prior to moving */
syslog_db_execute('UPDATE `' . $syslogdb_default . '`.`syslog_incoming` SET status=' . $uniqueID . ' WHERE status=0');

api_plugin_hook('plugin_syslog_before_processing');

$syslog_incoming = db_affected_rows($syslog_cnn);

syslog_debug('Found   ' . $syslog_incoming .  ', New Message(s) to process');

/* strip domains if we have requested to do so */
$syslog_domains = read_config_option('syslog_domains');
if ($syslog_domains != '') {
	$domains = explode(',', trim($syslog_domains));

	foreach($domains as $domain) {
		syslog_db_execute('UPDATE `' . $syslogdb_default . "`.`syslog_incoming`
			SET host=SUBSTRING_INDEX(host,'.',1)
			WHERE host LIKE '%$domain'");
	}
}

/* correct for invalid hosts */
if (read_config_option('syslog_validate_hostname') == 'on') {
	$hosts = syslog_db_fetch_assoc('SELECT DISTINCT host FROM `' . $syslogdb_default . '`.`syslog_incoming`');
	foreach($hosts as $host) {
		if ($host['host'] == gethostbyname($host['host'])) {
			syslog_db_execute('UPDATE `' . $syslogdb_default . "`.`syslog_incoming` SET host='invalid_host' WHERE host='" . $host['host'] . "'");
		}
	}
}

syslog_db_execute('INSERT INTO `' . $syslogdb_default . '`.`syslog_programs` (program) SELECT DISTINCT program FROM `' . $syslogdb_default . '`.`syslog_incoming` WHERE status=' . $uniqueID . ' ON DUPLICATE KEY UPDATE program=VALUES(program), last_updated=NOW()');

syslog_db_execute('INSERT INTO `' . $syslogdb_default . '`.`syslog_hosts` (host) SELECT DISTINCT host FROM `' . $syslogdb_default . '`.`syslog_incoming` WHERE status=' . $uniqueID . ' ON DUPLICATE KEY UPDATE host=VALUES(host), last_updated=NOW()');

syslog_db_execute('INSERT INTO `' . $syslogdb_default . '`.`syslog_host_facilities`
	(host_id, facility_id)
	SELECT host_id, facility_id
	FROM ((SELECT DISTINCT host, facility_id
		FROM `' . $syslogdb_default . "`.`syslog_incoming` WHERE status=$uniqueID) AS s
		INNER JOIN `" . $syslogdb_default . '`.`syslog_hosts` AS sh
		ON s.host=sh.host)
	ON DUPLICATE KEY UPDATE host_id=VALUES(host_id), last_updated=NOW()');

/* tally statistics for this interval */
if (read_config_option('syslog_statistics') == 'on') {
	syslog_db_execute('INSERT INTO `' . $syslogdb_default . '`.`syslog_statistics` (host_id, facility_id, priority_id, program_id, insert_time, records)
		SELECT host_id, facility_id, priority_id, program_id, NOW(), SUM(records) AS records
		FROM (SELECT host_id, facility_id, priority_id, program_id, COUNT(*) AS records
			FROM syslog_incoming AS si
			INNER JOIN syslog_hosts AS sh
			ON sh.host=si.host
			INNER JOIN syslog_programs AS sp
			ON sp.program=si.program
			WHERE status=' . $uniqueID . '
			GROUP BY host_id, priority_id, facility_id, program_id) AS merge
		GROUP BY host_id, priority_id, facility_id, program_id');

	$stats = db_affected_rows($syslog_cnn);

	syslog_debug('Stats   ' . $stats . ",  Record(s) to the 'syslog_statistics' table");
}

/* remote records that don't need to to be transferred */
$syslog_items   = syslog_remove_items('syslog_incoming', $uniqueID);
$syslog_removed = $syslog_items['removed'];
$syslog_xferred = $syslog_items['xferred'];

/* send out the alerts */
$query = syslog_db_fetch_assoc('SELECT * FROM `' . $syslogdb_default . "`.`syslog_alert` WHERE enabled='on'");
$syslog_alerts  = sizeof($query);

if (read_config_option('syslog_html') == 'on') {
	$html = true;
}else{
	$html = false;
}

$from_email = read_config_option('settings_from_email');
if ($from_email == '') {
	$from_email = 'root@localhost';
}

$from_name  = read_config_option('settings_from_name');
if ($from_name == '') {
	$from_name = 'Cacti Reporting';
}

$from = array($from_email, $from_name);

syslog_debug('Found   ' . $syslog_alerts . ',  Alert Rule' . ($syslog_alerts == 1 ? '' : 's' ) . ' to process');

$syslog_alarms = 0;
if (sizeof($query)) {
	foreach($query as $alert) {
		$sql      = '';
		$alertm   = '';
		$htmlm    = '';
		$smsalert = '';
		$th_sql   = '';

		if ($alert['type'] == 'facility') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE ' . $syslog_incoming_config['facilityField'] . "='" . $alert['message'] . "'
				AND status=" . $uniqueID;
		} else if ($alert['type'] == 'messageb') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE ' . $syslog_incoming_config['textField'] . "
				LIKE '" . $alert['message'] . "%'
				AND status=" . $uniqueID;
		} else if ($alert['type'] == 'messagec') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE ' . $syslog_incoming_config['textField'] . "
				LIKE '%" . $alert['message'] . "%'
				AND status=" . $uniqueID;
		} else if ($alert['type'] == 'messagee') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE ' . $syslog_incoming_config['textField'] . "
				LIKE '%" . $alert['message'] . "'
				AND status=" . $uniqueID;
		} else if ($alert['type'] == 'host') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE ' . $syslog_incoming_config['hostField'] . "='" . $alert['message'] . "'
				AND status=" . $uniqueID;
		} else if ($alert['type'] == 'sql') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog_incoming`
				WHERE (' . $alert['message'] . ')
				AND status=' . $uniqueID;
		}

		if ($sql != '') {
			if ($alert['method'] == '1') {
				$th_sql = str_replace('*', 'count(*)', $sql);
				$count = syslog_db_fetch_cell($th_sql);
			}

			if (($alert['method'] == '1' && $count >= $alert['num']) || ($alert['method'] == '0')) {
				$at = syslog_db_fetch_assoc($sql);

				/* get a date for the repeat alert */
				if ($alert['repeat_alert']) {
					$date = date('Y-m-d H:i:s', time() - ($alert['repeat_alert'] * read_config_option('poller_interval')));
				}

				if (sizeof($at)) {
					$htmlm .= "<html><head><style type='text/css'>";
					$htmlm .= file_get_contents($config['base_path'] . '/plugins/syslog/syslog.css');
					$htmlm .= '</style></head>';

					if ($alert['method'] == '1') {
						$alertm .= "-----------------------------------------------\n";
						$alertm .= __('WARNING: A Syslog Plugin Instance Count Alert has Been Triggered') . "\n";
						$alertm .= __('Name:')           . ' ' . htmlspecialchars($alert['name'], ENT_QUOTES, 'UTF-8') . "\n";
						$alertm .= __('Severity:')       . ' ' . $severities[$alert['severity']] . "\n";
						$alertm .= __('Threshold:')      . ' ' . $alert['num'] . "\n";
						$alertm .= __('Count:')          . ' ' . sizeof($at)       . "\n";
						$alertm .= __('Message String:') . ' ' . htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') . "\n";

						$htmlm  .= '<body><h1>' . __("Cacti Syslog Plugin Threshold Alert '%s'", $alert['name']) . '</h1>';
						$htmlm  .= '<table cellspacing="0" cellpadding="3" border="1">';
						$htmlm  .= '<tr><th>' . __('Alert Name') . '</th><th>' . __('Severity') . '</th><th>' . __('Threshold') . '</th><th>' . __('Count') . '</th><th>' . __('Match String') . '</th></tr>';
						$htmlm  .= '<tr><td>' . htmlspecialchars($alert['name'], ENT_QUOTES, 'UTF-8') . '</td>';
						$htmlm  .= '<td>'     . $severities[$alert['severity']]  . '</td>';
						$htmlm  .= '<td>'     . $alert['num']     . '</td>';
						$htmlm  .= '<td>'     . sizeof($at)       . '</td>';
						$htmlm  .= '<td>'     . htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') . '</td></tr></table><br>';
					}else{
						$htmlm .= '<body><h1>' . __('Cacti Syslog Plugin Alert \'%s\'', $alert['name']) . '</h1>';
					}

					$htmlm .= '<table>';
					$htmlm .= '<tr><th>' . __('Hostname') . '</th><th>' . __('Date') . '</th><th>' . __('Severity') . '</th><th>' . __('Level') . '</th><th>' . __('Message') . '</th></tr>';

					$max_alerts  = read_config_option('syslog_maxrecords');
					$alert_count = 0;
					$htmlh       = $htmlm;
					$alerth      = $alertm;
					$hostlist    = array();
					foreach($at as $a) {
						$a['message'] = str_replace('  ', "\n", $a['message']);
						$a['message'] = trim($a['message']);

						if (($alert['method'] == 1 && $alert_count < $max_alerts) || $alert['method'] == 0) {
							if ($alert['method'] == 0) $alertm  = $alerth;
							$alertm .= "-----------------------------------------------\n";
							$alertm .= __('Hostname:') . ' ' . htmlspecialchars($a['host'], ENT_QUOTES, 'UTF-8') . "\n";
							$alertm .= __('Date:')     . ' ' . $a['date'] . ' ' . $a['time'] . "\n";
							$alertm .= __('Severity:') . ' ' . $severities[$alert['severity']] . "\n\n";
							$alertm .= __('Level:')    . ' ' . $syslog_levels[$a['priority_id']] . "\n\n";
							$alertm .= __('Message:')  . ' ' . "\n" . htmlspecialchars($a['message'], ENT_QUOTES, 'UTF-8') . "\n";

							if ($alert['method'] == 0) $htmlm   = $htmlh;
							$htmlm  .= '<tr><td>' . $a['host']                        . '</td>';
							$htmlm  .= '<td>'     . $a['date'] . ' ' . $a['time']     . '</td>';
							$htmlm  .= '<td>'     . $severities[$alert['severity']]   . '</td>';
							$htmlm  .= '<td>'     . $syslog_levels[$a['priority_id']] . '</td>';
							$htmlm  .= '<td>'     . htmlspecialchars($a['message'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
						}

						$syslog_alarms++;
						$alert_count++;

						$ignore = false;
						if ($alert['method'] != '1') {
							if ($alert['repeat_alert'] > 0) {
								$ignore = syslog_db_fetch_cell('SELECT count(*)
									FROM syslog_logs
									WHERE alert_id=' . $alert['id'] . "
									AND logtime>'$date'
									AND host='" . $a['host'] . "'");
							}

							if (!$ignore) {
								$hostlist[] = $a['host'];
								$htmlm  .= '</table></body></html>';
								$sequence = syslog_log_alert($alert['id'], $alert['name'], $alert['severity'], $a, 1, $htmlm);
								$smsalert = __('Sev:') . $severities[$alert['severity']] . __(', Host:') . $a['host'] . __(', URL:') . read_config_option('base_url') . '/plugins/syslog/syslog.php?tab=current&id=' . $sequence;

								syslog_sendemail(trim($alert['email']), $from, __('Event Alert - %s', $alert['name']), ($html ? $htmlm:$alertm), $smsalert);

								if ($alert['open_ticket'] == 'on' && strlen(read_config_option('syslog_ticket_command'))) {
									if (is_executable(read_config_option('syslog_ticket_command'))) {
										exec(read_config_option('syslog_ticket_command') .
											" --alert-name='" . clean_up_name($alert['name']) . "'" .
											" --severity='"   . $alert['severity'] . "'" .
											" --hostlist='"   . implode(',',$hostlist) . "'" .
											" --message='"    . $alert['message'] . "'");
									}
								}
							}
						}else{
							/* get a list of hosts impacted */
							$hostlist[] = $a['host'];
						}

						if (trim($alert['command']) != '' && !$ignore) {
							$command = alert_replace_variables($alert, $a);
							cacti_log("SYSLOG NOTICE: Executing '$command'", true, 'SYSTEM');
							exec_background('/bin/sh', $command);
						}
					}

					$htmlm  .= '</table></body></html>';
					$alertm .= "-----------------------------------------------\n\n";

					if ($alert['method'] == 1) {
						$sequence = syslog_log_alert($alert['id'], $alert['name'], $alert['severity'], $at[0], sizeof($at), $htmlm, $hostlist);
						$smsalert = __('Sev:') . $severities[$alert['severity']] . __(', Count:') . sizeof($at) . __(', URL:') . read_config_option('base_url') . '/plugins/syslog/syslog.php?tab=current&id=' . $sequence;
					}

					syslog_debug("Alert Rule '" . $alert['name'] . "' has been activated");
				}
			}
		}

		if ($alertm != '' && $alert['method'] == 1) {
			$resend = true;
			if ($alert['repeat_alert'] > 0) {
				$found = syslog_db_fetch_cell('SELECT count(*)
					FROM syslog_logs
					WHERE alert_id=' . $alert['id'] . "
					AND logtime>'$date'");

				if ($found) $resend = false;
			}

			if ($resend) {
				syslog_sendemail(trim($alert['email']), $from, __('Event Alert - %s', $alert['name']), ($html ? $htmlm:$alertm), $smsalert);

				if ($alert['open_ticket'] == 'on' && strlen(read_config_option('syslog_ticket_command'))) {
					if (is_executable(read_config_option('syslog_ticket_command'))) {
						exec(read_config_option('syslog_ticket_command') .
							" --alert-name='" . clean_up_name($alert['name']) . "'" .
							" --severity='"   . $alert['severity'] . "'" .
							" --hostlist='"   . implode(',',$hostlist) . "'" .
							" --message='"    . $alert['message'] . "'");
					}
				}
			}
		}
	}
}

api_plugin_hook('plugin_syslog_after_processing');

/* move syslog records to the syslog table */
syslog_db_execute('INSERT INTO `' . $syslogdb_default . '`.`syslog` (logtime, priority_id, facility_id, program_id, host_id, message)
	SELECT TIMESTAMP(`' . $syslog_incoming_config['dateField'] . '`, `' . $syslog_incoming_config['timeField']     . '`),
	priority_id, facility_id, program_id, host_id, message
	FROM (SELECT date, time, priority_id, facility_id, program_id, host_id, message
		FROM syslog_incoming AS si
		INNER JOIN syslog_hosts AS sh
		ON sh.host=si.host
		INNER JOIN syslog_programs AS sp
		ON sp.program=si.program
		WHERE status=' . $uniqueID . ') AS merge');

$moved = db_affected_rows($syslog_cnn);

syslog_debug('Moved   ' . $moved . ",  Message(s) to the 'syslog' table");

/* remove flagged messages */
syslog_db_execute('DELETE FROM `' . $syslogdb_default . '`.`syslog_incoming` WHERE status=' . $uniqueID);

syslog_debug('Deleted ' . db_affected_rows($syslog_cnn) . ',  Already Processed Message(s) from incoming');

/* remove stats messages */
if (read_config_option('syslog_statistics') == 'on') {
	if (read_config_option('syslog_retention') > 0) {
		syslog_db_execute('DELETE FROM `' . $syslogdb_default . "`.`syslog_statistics`
			WHERE insert_time<'" . date('Y-m-d H:i:s', time()-(read_config_option('syslog_retention')*86400)) . "'");
		syslog_debug('Deleted ' . db_affected_rows($syslog_cnn) . ',  Syslog Statistics Record(s)');
	}
}else{
	syslog_db_execute('TRUNCATE `' . $syslogdb_default . '`.`syslog_statistics`');
}

/* remove alert log messages */
if (read_config_option('syslog_alert_retention') > 0) {
	$delete_time=date('Y-m-d H:i:s',time()-(read_config_option('syslog_alert_retention')*86400));
	api_plugin_hook_function('syslog_delete_hostsalarm', $delete_time);
	syslog_db_execute('DELETE FROM `' . $syslogdb_default . "`.`syslog_logs`
		WHERE logtime<'" . date('Y-m-d H:i:s', time()-(read_config_option('syslog_alert_retention')*86400)) . "'");

	syslog_debug('Deleted ' . db_affected_rows($syslog_cnn) . ',  Syslog alarm log Record(s)');

	syslog_db_execute('DELETE FROM `' . $syslogdb_default . "`.`syslog_hosts`
		WHERE last_updated<'" . date('Y-m-d H:i:s', time()-(read_config_option('syslog_alert_retention')*86400)) . "'");

	syslog_db_execute('DELETE FROM `' . $syslogdb_default . "`.`syslog_programs`
		WHERE last_updated<'" . date('Y-m-d H:i:s', time()-(read_config_option('syslog_alert_retention')*86400)) . "'");

	syslog_debug('Deleted ' . db_affected_rows($syslog_cnn) . ',  Syslog Host Record(s)');

	syslog_db_execute('DELETE FROM `' . $syslogdb_default . "`.`syslog_host_facilities`
		WHERE last_updated<'" . date('Y-m-d H:i:s', time()-(read_config_option('syslog_alert_retention')*86400)) . "'");

	syslog_debug('Deleted ' . db_affected_rows($syslog_cnn) . ',  Syslog Host/Facility Record(s)');
}

/* OPTIMIZE THE TABLES ONCE A DAY, JUST TO HELP CLEANUP */
if (date('G') == 0 && date('i') < 5) {
	syslog_debug('Optimizing Tables');
	if (!syslog_is_partitioned()) {
		syslog_db_execute('OPTIMIZE TABLE
			`' . $syslogdb_default . '`.`syslog_incoming`,
			`' . $syslogdb_default . '`.`syslog`,
			`' . $syslogdb_default . '`.`syslog_remove`,
			`' . $syslogdb_default . '`.`syslog_removed`,
			`' . $syslogdb_default . '`.`syslog_alert`');
	}else{
		syslog_db_execute('OPTIMIZE TABLE
			`' . $syslogdb_default . '`.`syslog_incoming`,
			`' . $syslogdb_default . '`.`syslog_remove`,
			`' . $syslogdb_default . '`.`syslog_alert`');
	}
}

syslog_debug('Processing Reports...');

/* Lets run the reports */
$reports = syslog_db_fetch_assoc('SELECT * FROM `' . $syslogdb_default . "`.`syslog_reports` WHERE enabled='on'");
$syslog_reports = sizeof($reports);

syslog_debug('We have ' . $syslog_reports . ' Reports in the database');

if (sizeof($reports)) {
foreach($reports as $syslog_report) {
	print '   Report: ' . $syslog_report['name'] . "\n";

	$base_start_time = $syslog_report['timepart'];
	$last_run_time   = $syslog_report['lastsent'];
	$time_span       = $syslog_report['timespan'];
	$seconds_offset  = read_config_option('cron_interval');

	$current_time = time();
	if (empty($last_run_time)) {

		$start_time = strtotime(date('Y-m-d 00:00', $current_time)) + $base_start_time;

		if ($current_time > $start_time) {
			/* if timer expired within a polling interval, then poll */
			if (($current_time - $seconds_offset) < $start_time) {
				$next_run_time = $start_time;
			}else{
				$next_run_time = $start_time + 3600*24;
			}
		}else{
			$next_run_time = $start_time;
		}
	}else{
		$next_run_time = strtotime(date('Y-m-d 00:00', $last_run_time)) + $base_start_time + $time_span;
	}
	$time_till_next_run = $next_run_time - $current_time;

	if ($time_till_next_run < 0 || $forcer) {
		syslog_db_execute_prepared('UPDATE `' . $syslogdb_default . '`.`syslog_reports` 
			SET lastsent = ? 
			WHERE id = ?', 
			array(time(), $syslog_report['id']));

		print '       Next Send: Now' . "\n";
		print "       Creating Report...\n";

		$sql     = '';
		$reptext = '';
		if ($syslog_report['type'] == 'messageb') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog`
				WHERE message LIKE ' . "'" . $syslog_report['message'] . "%'";
		}

		if ($syslog_report['type'] == 'messagec') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog`
				WHERE message LIKE '. "'%" . $syslog_report['message'] . "%'";
		}

		if ($syslog_report['type'] == 'messagee') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog`
				WHERE message LIKE ' . "'%" . $syslog_report['message'] . "'";
		}

		if ($syslog_report['type'] == 'host') {
			$sql = 'SELECT sl.*, sh.host FROM `' . $syslogdb_default . '`.`syslog` AS sl 
				INNER JOIN `' . $syslogdb_default . '`.`syslog_hosts` AS sh
				ON sl.host_id = sh.host_id
				WHERE sh.host=' . "'" . $syslog_report['message'] . "'";
		}

		if ($syslog_report['type'] == 'facility') {
			$sql = 'SELECT sl.*, sf.facility FROM `' . $syslogdb_default . '`.`syslog` AS sl
				INNER JOIN `' . $syslogdb_default . '`.`syslog_facilities` AS sf
				ON sl.facility_id = sf.facility_id
				WHERE sf.facility=' . "'" . $syslog_report['message'] . "'";
		}

		if ($syslog_report['type'] == 'sql') {
			$sql = 'SELECT * FROM `' . $syslogdb_default . '`.`syslog`
				WHERE (' . $syslog_report['message'] . ')';
		}

		if ($sql != '') {
			$date2 = date('Y-m-d H:i:s', $current_time);
			$date1 = date('Y-m-d H:i:s', $current_time - $time_span);
			$sql  .= " AND logtime BETWEEN '". $date1 . "' AND '" . $date2 . "'";
			$sql  .= ' ORDER BY logtime DESC';
			$items = syslog_db_fetch_assoc($sql);

			syslog_debug('We have ' . db_affected_rows($syslog_cnn) . ' items for the Report');

			$classes = array('even', 'odd');

			$i = 0;
			if (sizeof($items)) {
				$class = $classes[$i % 2];
				foreach($items as $item) {
					$reptext .= '<tr class="' . $class . '"><td class="date">' . $item['logtime'] . '</td><td class="message">' . htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8') . "</td></tr>\n";
				}
				$i++;
			}

			if ($reptext != '') {
				$headtext  = "<html><head><style type='text/css'>\n";
				$headtext .= file_get_contents($config['base_path'] . '/plugins/syslog/syslog.css');
				$headtext .= "</style></head>\n";

				$headtext .= "<body>\n";

				$headtext .= "<h1>Cacti Syslog Report - " . $syslog_report['name'] . "</h1>\n";
				$headtext .= "<hr>\n";
				$headtext .= "<p>" . $syslog_report['body'] . "</p>";
				$headtext .= "<hr>\n";

				$headtext .= "<table>\n";
				$headtext .= "<tr><th>" . __('Date') . "</th><th>" . __('Message') . "</th></tr>\n";

				$headtext .= $reptext;

				$headtext .= "</table>\n";

				$headtext .= "</body>\n";
				$headtext .= "</html>\n";

				$smsalert  = '';

				syslog_sendemail($syslog_report['email'], $from, __('Event Report - %s', $syslog_report['name']), $headtext, $smsalert);
			}
		}
	} else {
		print '       Next Send: ' . date('Y-m-d H:i:s', $next_run_time) . "\n";
	}
}
}

syslog_debug('Finished processing Reports...');

syslog_process_log($start_time, $syslog_deleted, $syslog_incoming, $syslog_removed, $syslog_xferred, $syslog_alerts, $syslog_alarms, $syslog_reports);

function syslog_process_log($start_time, $deleted, $incoming, $removed, $xferred, $alerts, $alarms, $reports) {
	global $database_default;

	/* record the end time */
	list($micro,$seconds) = explode(' ', microtime());
	$end_time = $seconds + $micro;

	cacti_log('SYSLOG STATS:Time:' . round($end_time-$start_time,2) . ' Deletes:' . $deleted . ' Incoming:' . $incoming . ' Removes:' . $removed . ' XFers:' . $xferred . ' Alerts:' . $alerts . ' Alarms:' . $alarms . ' Reports:' . $reports, true, 'SYSTEM');

	db_execute('REPLACE INTO `' . $database_default . "`.`settings` SET name='syslog_stats', value='time:" . round($end_time-$start_time,2) . ' deletes:' . $deleted . ' incoming:' . $incoming . ' removes:' . $removed . ' xfers:' . $xferred . ' alerts:' . $alerts . ' alarms:' . $alarms . ' reports:' . $reports . "'");
}

/*  display_version - displays version information */
function display_version() {
	global $config;

	if (!function_exists('plugin_syslog_version')) {
		include_once($config['base_path'] . '/plugins/syslog/setup.php');
	}

	$version = plugin_syslog_version();
	echo "Syslog Poller, Version " . trim($version['version']) . ", " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "The main Syslog poller process script for Cacti Syslogging.\n\n";
	echo "usage: syslog_process.php [--debug] [--force-report]\n\n";
	echo "options:\n";
	echo "    --force-report   Send email reports now.\n";
	echo "    --debug          Provide more verbose debug output.\n\n";
}

function alert_replace_variables($alert, $a) {
	global $severities, $syslog_levels, $syslog_facilities;

	$command = $alert['command'];

	$command = str_replace('<ALERTID>',  $alert['id'], $command);
	$command = str_replace('<HOSTNAME>', $a['host'], $command);
	$command = str_replace('<PRIORITY>', $syslog_levels[$a['priority_id']], $command);
	$command = str_replace('<FACILITY>', $syslog_facilities[$a['facility_id']], $command);
	$command = str_replace('<MESSAGE>',  $a['message'], $command);
	$command = str_replace('<SEVERITY>', $severities[$alert['severity']], $command);

	return $command;
}
