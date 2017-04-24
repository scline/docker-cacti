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
*/

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset ($_SERVER['argv'][0]) || isset ($_SERVER['REQUEST_METHOD']) || isset ($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

/* record the start time */
$poller_start = microtime(true);
$start_date   = date('Y-m-d H:i:s');

include('../../include/global.php');
include_once($config['base_path'] . '/lib/reports.php');

global $config, $database_default, $purged_r, $purged_n;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = FALSE;
$force    = FALSE;
$purged_r = 0;
$purged_n = 0;

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version' :
			case '-V' :
			case '-v' :
				display_version();
				exit;
			case '--help' :
			case '-H' :
			case '-h' :
				display_help();
				exit;
			case '--force' :
				$force = true;
				break;
			case '--debug' :
				$debug = true;
				break;
			default :
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

monitor_debug('Monitor Starting Checks');

list($reboots, $recent_down) = monitor_uptime_checker();

$warning_criticality = read_config_option('monitor_warn_criticality');
$alert_criticality   = read_config_option('monitor_alert_criticality');

$lists               = array();
$notifications       = 0;
$global_list         = array();
$notify_list         = array();
$last_time           = date("Y-m-d H:i:s", time() - read_config_option('monitor_resend_frequency') * 60);

if ($warning_criticality > 0 || $alert_criticality > 0) {
	monitor_debug('Monitor Notification Enabled for Devices');
	// Get hosts that are above threshold.  Start with Alert, and then Warning
	if ($alert_criticality) {
		get_hosts_by_list_type('alert', $alert_criticality, $global_list, $notify_list, $lists);
	}

	if ($warning_criticality) {
		get_hosts_by_list_type('warn', $warning_criticality, $global_list, $notify_list, $lists);
	}

	flatten_lists($global_list, $notify_list);

	monitor_debug("Lists Flattened there are " . sizeof($global_list) . " Global Notifications and " . sizeof($notify_list) . " Notification List Notifications.");

	if (strlen(read_config_option('alert_email')) == 0) {
		monitor_debug('WARNING: No Global List Defined.  Please set under Settings -> Thresholds');
		cacti_log('WARNING: No Global Notification List defined.  Please set under Settings -> Thresholds', false, 'MONITOR');
		
	}

	if (sizeof($global_list) || sizeof($notify_list)) {
		// array of email[list|'g'] = true;
		$notification_emails = get_emails_and_lists($lists);

		// Send out emails to each emails address with all notifications in one
		if (sizeof($notification_emails)) {
			foreach($notification_emails as $email => $lists) {
				monitor_debug('Processing the email address: ' . $email);

				process_email($email, $lists, $global_list, $notify_list);

				$notifications++;
			}
		}
	}
}else{
	monitor_debug('Both Warning and Alert Notification are Disabled.');
}

list($purge_n, $purge_r) = purge_event_records();

$poller_end = microtime(true);

cacti_log('MONITOR STATS: Time:' . round($poller_end-$poller_start, 2) . ' Reboots:' . $reboots . ' DownDevices:' . $recent_down . ' Notifications:' . $notifications . ' Purges:' . ($purge_n + $purge_r), false, 'SYSTEM');

exit;

function monitor_uptime_checker() {
	monitor_debug('Checking for Uptime of Devices');

	$start = date('Y-m-d H:i:s');

	// Get the rebooted devices
	$rebooted_hosts = db_fetch_assoc("SELECT h.id, h.snmp_sysUpTimeInstance, mu.uptime
		FROM host AS h
		LEFT JOIN plugin_monitor_uptime AS mu
		ON h.id=mu.host_id
		WHERE h.snmp_version>0 AND status IN (2,3)
		AND (mu.uptime IS NULL OR mu.uptime > h.snmp_sysUpTimeInstance)");

	if (sizeof($rebooted_hosts)) {
		foreach($rebooted_hosts as $host) {
			db_execute_prepared('INSERT INTO plugin_monitor_reboot_history (host_id, reboot_time) VALUES (?, ?)', 
				array($host['id'], date('Y-m-d H:i:s', time()-$host['snmp_sysUpTimeInstance'])));
		}
	}

	// Freshen the uptimes
	db_execute("REPLACE INTO plugin_monitor_uptime (host_id, uptime) 
		SELECT id, snmp_sysUpTimeInstance FROM host WHERE snmp_version>0 AND status IN(2,3)");

	// Log Recently Down
	db_execute("INSERT IGNORE INTO plugin_monitor_notify_history 
		(host_id, notify_type, notification_time, notes) 
		SELECT h.id, '3' AS notify_type, status_fail_date AS notification_time, status_last_error AS notes
		FROM host AS h
		WHERE status=1 AND status_event_count=1");

	$recent = db_affected_rows();

	return array(sizeof($rebooted_hosts), $recent);
}

function process_email($email, $lists, $global_list, $notify_list) {
	define('BR', "\n");

	monitor_debug('Into Processing');
	$alert_hosts = array();
	$warn_hosts  = array();

	$criticalities = array(
		0 => __('Disabled'),
		1 => __('Low'),
		2 => __('Medium'),
		3 => __('High'),
		4 => __('Mission Critical')
	);

	foreach($lists as $list) {
		switch($list) {
		case 'global':
			$hosts = array();
			if (isset($global_list['alert'])) {
				$alert_hosts += explode(',', $global_list['alert']);
			}
			if (isset($global_list['warn'])) {
				$warn_hosts += explode(',', $global_list['warn']);
			}
			break;
		default:
			if (isset($notify_list[$list]['alert'])) {
				$alert_hosts = explode(',', $notify_list[$list]['alert']);
			}
			if (isset($notify_list[$list]['warn'])) {
				$warn_hosts = explode(',', $notify_list[$list]['warn']);
			}
			break;
		}
	}

	monitor_debug('Lists Processed');

	if (sizeof($alert_hosts)) {
		$alert_hosts = array_unique($alert_hosts, SORT_NUMERIC);

		log_messages('alert', $alert_hosts);
	}

	if (sizeof($warn_hosts)) {
		$warn_hosts = array_unique($warn_hosts, SORT_NUMERIC);

		log_messages('warn', $alert_hosts);
	}

	monitor_debug('Found ' . sizeof($alert_hosts) . ' Alert Hosts, and ' . sizeof($warn_hosts) . ' Warn Hosts');

	if (sizeof($alert_hosts) || sizeof($warn_hosts)) {
		monitor_debug('Formatting Email');
		$freq    = read_config_option('monitor_resend_frequency');
		$subject = __('Cacti Monitor Plugin Ping Threshold Notification');

		$body  = '<h1>' . __('Cacti Monitor Plugin Ping Threshold Notification') . '</h1>' . BR;

		$body .= '<p>' . __('The following report will identify Devices that have eclipsed their ping
			latency thresholds.  You are receiving this report since you are subscribed to a Device 
			associated with the Cacti system located at the following URL below.') . '</p>' . BR;

		$body .= '<h2><a href="' . read_config_option('base_url') . '">Cacti Monitoring Site</a></h2>' . BR;

		if ($freq > 0) {
			$body .= '<p>' . __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq) . '</p>' . BR;
		}else{
			$body .= '<p>' . __('You will receive notifications every time the Device is above its threshold.') . '</p>' . BR;
		}

		if (sizeof($alert_hosts)) {
			$body .= '<p>' . __('The following Devices have breached their Alert Notification Threshold.') . '</p>' . BR;
			$body .= '<table class="report_table">' . BR;
			$body .= '<tr class="header_row">' . BR;
			$body .= '<th class="left">Hostname</th><th class="left">Criticality</th><th class="right">Alert Ping</th><th class="right">Current Ping</th>' . BR;
			$body .= '</tr>' . BR;

			$hosts = db_fetch_assoc('SELECT * FROM host WHERE id IN(' . implode(',', $alert_hosts) . ')');
			if (sizeof($hosts)) {
				foreach($hosts as $host) {
					$body .= '<tr>' . BR;
					$body .= '<td class="left"><a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . $host['description']  . '</a></td>' . BR;
					$body .= '<td class="left">' . $criticalities[$host['monitor_criticality']]  . '</td>' . BR;
					$body .= '<td class="right">' . round($host['monitor_alert'],2)  . ' ms</td>' . BR;
					$body .= '<td class="right">' . round($host['cur_time'],2)  . ' ms</td>' . BR;
					$body .= '</tr>' . BR;
				}
			}
			$body .= '</table>' . BR;
		}

		if (sizeof($warn_hosts)) {
			$body .= '<p>' . __('The following Devices have breached their Warning Notification Threshold.') . '</p>' . BR;

			$body .= '<table class="report_table">' . BR;
			$body .= '<tr class="header_row">' . BR;
			$body .= '<th class="left">Hostname</th><th class="left">Criticality</th><th class="right">Alert Ping</th><th class="right">Current Ping</th>' . BR;
			$body .= '</tr>' . BR;

			$hosts = db_fetch_assoc('SELECT * FROM host WHERE id IN(' . implode(',', $warn_hosts) . ')');
			if (sizeof($hosts)) {
				foreach($hosts as $host) {
					$body .= '<tr>' . BR;
					$body .= '<td class="left"><a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . $host['description']  . '</a></td>' . BR;
					$body .= '<td class="left">' . $criticalities[$host['monitor_criticality']]  . '</td>' . BR;
					$body .= '<td class="right">' . round($host['monitor_warn'],2)  . ' ms</td>' . BR;
					$body .= '<td class="right">' . round($host['cur_time'],2)  . ' ms</td>' . BR;
					$body .= '</tr>' . BR;
				}
			}
			$body .= '</table>' . BR;
		}

		$output     = '';
		$report_tag = '';
		$theme      = 'modern';

		monitor_debug('Loading Format File');

		$format_ok = reports_load_format_file(read_config_option('monitor_format_file'), $output, $report_tag, $theme);

		monitor_debug('Format File Loaded, Format is ' . ($format_ok ? 'Ok':'Not Ok') . ', Report Tag is ' . $report_tag);

		if ($format_ok) {
			if ($report_tag) {
				$output = str_replace('<REPORT>', $body, $output);
			} else {
				$output = $output . "\n" . $body;
			}
		} else {
			$output = $body;
		}

		monitor_debug('HTML Processed');

		$v = db_fetch_cell('SELECT cacti FROM version');
		$headers['User-Agent'] = 'Cacti-Monitor-v' . $v;

		$from_email = read_config_option('settings_from_email');
		if ($from_email == '') {
			$from_email = 'root@localhost';
		}

		$from_name  = read_config_option('settings_from_name');
		if ($from_name == '') {
			$from_name = 'Cacti Reporting';
		}

		monitor_debug("Sending Email to '$email'");

		$error = mailer(
			array($from_email, $from_name),
			$email,
			'',
			'',
			'',
			$subject,
			$output,
			'Cacti Monitor Plugin requires an html based Email client',
			'',
			$headers
	    );

		monitor_debug("The return from the mailer was '$error'");

		if (strlen($error)) {
            cacti_log("WARNING: Monitor had problems sending Notification Report to '$email'.  The error was '$error'", false, 'MONITOR');
		}else{
			cacti_log("NOTICE: Email Notification Sent to '$email' for " . 
				(sizeof($alert_hosts) ? sizeof($alert_hosts) . ' Alert Notificaitons':'') . 
				(sizeof($warn_hosts) ? (sizeof($alert_hosts) ? ', and ':'') . 
					sizeof($warn_hosts) . ' Warning Notifications':''). '.', false, 'MONITOR');
		}
	}
}

function log_messages($type, $alert_hosts) {
	global $start_date;
	static $processed = array();

	if ($type == 'warn') {
		$type   = '0';
		$column = 'monitor_warn';
	}elseif ($type == 'alert') {
		$type = '1';
		$column = 'monitor_alert';
	}

	foreach($alert_hosts as $id) {
		if (!isset($processed[$id])) {
			db_execute_prepared('INSERT INTO plugin_monitor_notify_history 
				(host_id, notify_type, ping_time, ping_threshold, notification_time) 
				SELECT id, ?, cur_time, ?, ? FROM host WHERE id = ?', array($type, $column, $start_date, $id));
		}

		$processed[$id] = true;
	}
}

function get_hosts_by_list_type($type, $criticality, &$global_list, &$notify_list, &$lists) {
	global $force;

	$last_time = date('Y-m-d H:i:s', time() - read_config_option('monitor_resend_frequency') * 60);

	$hosts = db_fetch_cell_prepared("SELECT count(*)
		FROM host 
		WHERE status=3 
		AND thold_send_email>0 
		AND monitor_criticality >= ?
		AND cur_time > monitor_$type", array($criticality));

	if ($type == 'warn') {
		$htype = 1;
	}else{
		$htype = 0;
	}

	if ($hosts > 0) {
		$groups = db_fetch_assoc_prepared("SELECT 
			thold_send_email, thold_host_email, GROUP_CONCAT(host.id) AS id
			FROM host
			LEFT JOIN (
				SELECT host_id, MAX(notification_time) AS notification_time 
				FROM plugin_monitor_notify_history 
				WHERE notify_type = ?
				GROUP BY host_id
			) AS nh
			ON host.id=nh.host_id
			WHERE status=3 
			AND thold_send_email>0 
			AND monitor_criticality >= ?
			AND cur_time > monitor_$type " . ($type == "warn" ? " AND cur_time < monitor_alert":"") ."
			AND (notification_time < ? OR notification_time IS NULL)
			GROUP BY thold_host_email, thold_send_email
			ORDER BY thold_host_email, thold_send_email", array($htype, $criticality, $last_time));

		if (sizeof($groups)) {
			foreach($groups as $entry) {
				switch($entry['thold_send_email']) {
				case '1': // Global List
					$global_list[$type][] = $entry;
					break;
				case '2': // Notification List
					if ($entry['thold_host_email'] > 0) {
						$notify_list[$type][$entry['thold_host_email']][] = $entry;
						$lists[$entry['thold_host_email']] = $entry['thold_host_email'];
					}
					break;
				case '3': // Both Notification and Global
					$global_list[$type][] = $entry;
					if ($entry['thold_host_email'] > 0) {
						$notify_list[$type][$entry['thold_host_email']][] = $entry;
						$lists[$entry['thold_host_email']] = $entry['thold_host_email'];
					}
				}
			}
		}
	}
}

function flatten_lists(&$global_list, &$notify_list) {
	if (sizeof($global_list)) {
		foreach($global_list as $severity => $list) {
			foreach($list as $item) {
				$new_global[$severity] = (isset($new_global[$severity]) ? $new_global[$severity] . ',':'') . $item['id'];
			}
		}
		$global_list = $new_global;
	}

	if (sizeof($notify_list)) {
		foreach($notify_list as $severity => $lists) {
			foreach($lists as $id => $list) {
				foreach($list as $item) {
					$new_list[$severity][$id] = (isset($new_list[$severity][$id]) ? $new_list[$severity][$id] . ',':'') . $item['id'];
				}
			}
		}
		$notify_list = $new_list;
	}
}

function get_emails_and_lists($lists) {
	$notification_emails = array();

	$global_emails = explode(',', read_config_option('alert_email'));
	foreach($global_emails as $index => $user) {
		if (trim($user) != '') {
			$notification_emails[trim($user)]['global'] = true;
		}
	}

	if (sizeof($lists)) {
		$list_emails = db_fetch_assoc('SELECT id, emails 
			FROM plugin_notification_lists 
			WHERE id IN (' . implode(',', $lists) . ')');

		foreach($list_emails as $email) {
			$emails = explode(',', $email['emails']);
			foreach($emails as $user) {
				if (trim($user) != '') {
					$notification_emails[trim($user)][$email['id']] = true;
				}
			}
		}
	}

	return $notification_emails;
}

function purge_event_records() {
	// Purge old records
	$days = read_config_option('monitor_log_storage');

	if (empty($days)) {
		$days = 120;
	}

	db_execute_prepared('DELETE FROM plugin_monitor_notify_history 
		WHERE notification_time<FROM_UNIXTIME(UNIX_TIMESTAMP()-(? * 86400))', array($days));
	$purge_n = db_affected_rows();

	db_execute_prepared('DELETE FROM plugin_monitor_reboot_history 
		WHERE log_time<FROM_UNIXTIME(UNIX_TIMESTAMP()-(? * 86400))', array($days));
	$purge_r = db_affected_rows();

	return(array($purge_n, $purge_r));
}

function monitor_debug($message) {
	global $debug;

	if ($debug) {
		echo trim($message) . "\n";
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_monitor_version')) {
		include_once($config['base_path'] . '/plugins/monitor/setup.php');
	}

	$info = plugin_monitor_version();
	print "Cacti Monitor Poller, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	display_version();

	print "\nusage: poller_monitor.php [--force] [--debug] [--help] [--version]\n\n";
	print "--force       - force execution, e.g. for testing\n";
	print "--debug       - debug execution, e.g. for testing\n\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}
