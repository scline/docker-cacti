<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2005 Electric Sheep Studios                               |
 | Originally by Shitworks, 2004                                           |
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
 | h.aloe: a syslog monitoring addon for Ian Berry's Cacti	               |
 +-------------------------------------------------------------------------+
 | Originally released as aloe by: sidewinder at shitworks.com             |
 | Modified by: Harlequin <harlequin@cyberonic.com>                        |
 | 2005-11-10 -- ver 0.1.1 beta                                            |
 |   - renamed to h.aloe                                                   |
 |   - updated to work with Cacti 8.6g                                     |
 |   - included Cacti time selector                                        |
 |   - various other modifications                                         |
 +-------------------------------------------------------------------------+
*/

function syslog_sendemail($to, $from, $subject, $message, $smsmessage = '') {
	syslog_debug("Sending Alert email to '" . $to . "'");

	$sms    = '';
	$nonsms = '';
	/* if there are SMS emails, process separately */
	if (substr_count($to, 'sms@')) {
		$emails = explode(',', $to);

		if (sizeof($emails)) {
			foreach($emails as $email) {
				if (substr_count($email, 'sms@')) {
					$sms .= (strlen($sms) ? ', ':'') . str_replace('sms@', '', trim($email));
				} else {
					$nonsms .= (strlen($nonsms) ? ', ':'') . trim($email);
				}
			}
		}
	} else {
		$nonsms = $to;
	}

	if (strlen($sms) && $smsmessage != '') {
		mailer($from, $sms, '', '', '', $subject, '', $smsmessage);
	}

	if (strlen($nonsms)) {
		if (read_config_option('syslog_html') == 'on') {
			mailer($from, $nonsms, '', '', '', $subject, $message, __('Please use an HTML Email Client', 'syslog'));
		} else {
			$message = strip_tags(str_replace('<br>', "\n", $message));
			mailer($from, $nonsms, '', '', '', $subject, '', $message);
		}
	}
}

function syslog_is_partitioned() {
	global $syslogdb_default;

	/* see if the table is partitioned */
	$syntax = syslog_db_fetch_row("SHOW CREATE TABLE `" . $syslogdb_default . "`.`syslog`");
	if (substr_count($syntax['Create Table'], 'PARTITION')) {
		return true;
	} else {
		return false;
	}
}

/**
 * This function will manage old data for non-partitioned tables
 */
function syslog_traditional_manage() {
	global $syslogdb_default, $syslog_cnn;

	/* determine the oldest date to retain */
	if (read_config_option('syslog_retention') > 0) {
		$retention = date('Y-m-d', time() - (86400 * read_config_option('syslog_retention')));
	}

	/* delete from the main syslog table first */
	syslog_db_execute("DELETE FROM `" . $syslogdb_default . "`.`syslog` WHERE logtime < '$retention'");

	$syslog_deleted = db_affected_rows($syslog_cnn);

	/* now delete from the syslog removed table */
	syslog_db_execute("DELETE FROM `" . $syslogdb_default . "`.`syslog_removed` WHERE logtime < '$retention'");

	$syslog_deleted += db_affected_rows($syslog_cnn);

	syslog_debug("Deleted " . $syslog_deleted .
		",  Syslog Message(s)" .
		" (older than $retention)");

	return $syslog_deleted;
}

/**
 * This function will manage a partitioned table by checking for time to create
 */
function syslog_partition_manage() {
	$syslog_deleted = 0;

	if (syslog_partition_check('syslog')) {
		syslog_partition_create('syslog');
		$syslog_deleted = syslog_partition_remove('syslog');
	}

	if (syslog_partition_check('syslog_removed')) {
		syslog_partition_create('syslog_removed');
		$syslog_deleted += syslog_partition_remove('syslog_removed');
	}

	return $syslog_deleted;
}

/**
 * This function will create a new partition for the specified table.
 */
function syslog_partition_create($table) {
	global $syslogdb_default;

	/* determine the format of the table name */
	$time    = time();
	$cformat = 'd' . date('Ymd', $time);
	$lnow    = date('Y-m-d', $time+86400);

	cacti_log("SYSLOG: Creating new partition '$cformat'", false, 'SYSTEM');
	syslog_debug("Creating new partition '$cformat'");
	syslog_db_execute("ALTER TABLE `" . $syslogdb_default . "`.`$table` REORGANIZE PARTITION dMaxValue INTO (
		PARTITION $cformat VALUES LESS THAN (TO_DAYS('$lnow')),
		PARTITION dMaxValue VALUES LESS THAN MAXVALUE)");
}

/**
 * This function will remove all old partitions for the specified table.
 */
function syslog_partition_remove($table) {
	global $syslogdb_default;

	$syslog_deleted = 0;
	$number_of_partitions = syslog_db_fetch_assoc("SELECT *
		FROM `information_schema`.`partitions`
		WHERE table_schema='" . $syslogdb_default . "' AND table_name='syslog'
		ORDER BY partition_ordinal_position");

	$days     = read_config_option('syslog_retention');
	syslog_debug("There are currently '" . sizeof($number_of_partitions) . "' Syslog Partitions, We will keep '$days' of them.");

	if ($days > 0) {
		$user_partitions = sizeof($number_of_partitions) - 1;
		if ($user_partitions >= $days) {
			$i = 0;
			while ($user_partitions > $days) {
				$oldest = $number_of_partitions[$i];
				cacti_log("SYSLOG: Removing old partition 'd" . $oldest['PARTITION_NAME'] . "'", false, 'SYSTEM');
				syslog_debug("Removing partition '" . $oldest['PARTITION_NAME'] . "'");
				syslog_db_execute("ALTER TABLE `" . $syslogdb_default . "`.`$table` DROP PARTITION " . $oldest['PARTITION_NAME']);
				$i++;
				$user_partitions--;
				$syslog_deleted++;
			}
		}
	}

	return $syslog_deleted;
}

function syslog_partition_check($table) {
	global $syslogdb_default;

	/* find date of last partition */
	$last_part = syslog_db_fetch_cell("SELECT PARTITION_NAME
		FROM `information_schema`.`partitions`
		WHERE table_schema='" . $syslogdb_default . "' AND table_name='syslog'
		ORDER BY partition_ordinal_position DESC
		LIMIT 1,1;");

	$lformat   = str_replace('d', '', $last_part);
	$cformat   = date('Ymd');

	if ($cformat > $lformat) {
		return true;
	} else {
		return false;
	}
}

function syslog_check_changed($request, $session) {
	if ((isset_request_var($request)) && (isset($_SESSION[$session]))) {
		if (get_request_var($request) != $_SESSION[$session]) {
			return 1;
		}
	}
}

function syslog_remove_items($table, $uniqueID) {
	global $config, $syslog_cnn, $syslog_incoming_config;

	include(dirname(__FILE__) . '/config.php');

	if ($table == 'syslog') {
		$rows = syslog_db_fetch_assoc("SELECT * FROM `" . $syslogdb_default . "`.`syslog_remove` WHERE enabled='on' AND id=$uniqueID");
	} else {
		$rows = syslog_db_fetch_assoc("SELECT * FROM `" . $syslogdb_default . "`.`syslog_remove` WHERE enabled='on'");
	}

	syslog_debug("Found   " . sizeof($rows) . ",  Removal Rule(s) to process");

	$removed = 0;
	$xferred = 0;

	if ($table == 'syslog_incoming') {
		$total = syslog_db_fetch_cell("SELECT count(*) FROM `" . $syslogdb_default . "`.`syslog_incoming` WHERE status=$uniqueID");
	} else {
		$total = 0;
	}

	if (sizeof($rows)) {
	foreach($rows as $remove) {
		$sql  = '';
		$sql1 = '';
		if ($remove['type'] == 'facility') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField']     . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE " . $syslog_incoming_config["facilityField"] . "='" . $remove['message'] . "' AND status=" . $uniqueID . ") AS merge";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE " . $syslog_incoming_config['facilityField'] . "='" . $remove['message'] . "' AND status='" . $uniqueID . "'";
			} else {
				$facility_id = syslog_db_fetch_cell("SELECT facility_id FROM `" . $syslogdb_default . "`.`syslog_facilities` WHERE facility='" . $remove['message'] . "'");

				if (!empty($facility_id)) {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, program_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE facility_id=$facility_id";
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE facility_id=$facility_id";
				}
			}
		}elseif ($remove['type'] == 'host') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField']     . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE host='" . $remove['message'] . "' AND status=" . $uniqueID . ") AS merge";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE host='" . $remove['message'] . "' AND status='" . $uniqueID . "'";
			} else {
				$host_id = syslog_db_fetch_cell("SELECT host_id FROM `" . $syslogdb_default . "`.`syslog_hosts` WHERE host='" . $remove['message'] . "'");

				if (!empty($host_id)) {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, program_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE host_id=$host_id";
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE host_id=$host_id";
				}
			}
		} elseif ($remove['type'] == 'messageb') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField'] . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE message LIKE '" . $remove['message'] . "%' AND status=" . $uniqueID . ") AS merge";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE message LIKE '" . $remove['message'] . "%' AND status='" . $uniqueID . "'";
			} else {
				if ($remove['message'] != '') {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, program_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE message LIKE '" . $remove['message'] . "%'";
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE message LIKE '" . $remove['message'] . "%'";
				}
			}
		} elseif ($remove['type'] == 'messagec') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField'] . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE message LIKE '%" . $remove['message'] . "%' AND status=" . $uniqueID . ") AS merge";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE message LIKE '%" . $remove['message'] . "%' AND status='" . $uniqueID . "'";
			} else {
				if ($remove['message'] != '') {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, program_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE message LIKE '%" . $remove['message'] . "%'";
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE message LIKE '%" . $remove['message'] . "%'";
				}
			}
		} elseif ($remove['type'] == 'messagee') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField'] . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE message LIKE '%" . $remove['message'] . "' AND status=" . $uniqueID . ") AS merge";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE message LIKE '%" . $remove['message'] . "' AND status='" . $uniqueID . "'";
			} else {
				if ($remove['message'] != '') {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, program_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE message LIKE '%" . $remove['message'] . "'";
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE message LIKE '%" . $remove['message'] . "'";
				}
			}
		}elseif ($remove['type'] == 'sql') {
			if ($table == 'syslog_incoming') {
				if ($remove['method'] != 'del') {
					$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
						(logtime, priority_id, facility_id, program_id, host_id, message)
						SELECT TIMESTAMP(`" . $syslog_incoming_config['dateField'] . "`, `" . $syslog_incoming_config['timeField'] . "`),
						priority_id, facility_id, program_id, host_id, message
						FROM (SELECT si.date, si.time, si.priority_id, si.facility_id, spg.program_id, sh.host_id, si.message
							FROM `" . $syslogdb_default . "`.`syslog_incoming` AS si
							INNER JOIN `" . $syslogdb_default . "`.`syslog_facilities` AS sf
							ON sf.facility_id=si.facility_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_priorities` AS sp
							ON sp.priority_id=si.priority_id
							INNER JOIN `" . $syslogdb_default . "`.`syslog_programs` AS spg
							ON spg.program=si.program
							INNER JOIN `" . $syslogdb_default . "`.`syslog_hosts` AS sh
							ON sh.host=si.host
							WHERE status=" . $uniqueID . ") AS merge
						WHERE (" . $remove['message'] . ")";
				}

				$sql = "DELETE
					FROM `" . $syslogdb_default . "`.`syslog_incoming`
					WHERE (" . $remove['message'] . ") AND status='" . $uniqueID . "'";
			} else {
				if ($remove['message'] != '') {
					if ($remove['method'] != 'del') {
						$sql1 = "INSERT INTO `" . $syslogdb_default . "`.`syslog_removed`
							(logtime, priority_id, facility_id, program_id, host_id, message)
							SELECT logtime, priority_id, facility_id, host_id, message
							FROM `" . $syslogdb_default . "`.`syslog`
							WHERE " . $remove['message'];
					}

					$sql  = "DELETE FROM `" . $syslogdb_default . "`.`syslog`
						WHERE " . $remove['message'];
				}
			}
		}

		if ($sql != '' || $sql1 != '') {
			$debugm = '';
			/* process the removal rule first */
			if ($sql1 != '') {
				/* now delete the remainder that match */
				syslog_db_execute($sql1);
			}

			/* now delete the remainder that match */
			syslog_db_execute($sql);
			$removed += db_affected_rows($syslog_cnn);
			$debugm   = 'Deleted ' . $removed . ', ';
			if ($sql1 != '') {
				$xferred += db_affected_rows($syslog_cnn);
				$debugm   = 'Moved   ' . $xferred . ', ';
			}

			syslog_debug($debugm . ' Message' . (db_affected_rows($syslog_cnn) == 1 ? '' : 's' ) .
					" for removal rule '" . $remove['name'] . "'");
		}
	}
	}

	if ($removed == 0) $xferred = $total;

	return array('removed' => $removed, 'xferred' => $xferred);
}

/** function syslog_log_row_color()
 *  This function set's the CSS for each row of the syslog table as it is displayed
 *  it supports both the legacy as well as the new approach to controlling these
 *  colors.
*/
function syslog_log_row_color($severity, $tip_title) {
	switch($severity) {
	case '':
	case '0':
		$class = 'logInfo';
		break;
	case '1':
		$class = 'logWarning';
		break;
	case '2':
		$class = 'logAlert';
		break;
	}

	print "<tr class='$class'>\n";
}

/** function syslog_row_color()
 *  This function set's the CSS for each row of the syslog table as it is displayed
 *  it supports both the legacy as well as the new approach to controlling these
 *  colors.
*/
function syslog_row_color($priority, $message) {
	switch($priority) {
	case '0':
		$class = 'logEmerg';
		break;
	case '1':
		$class = 'logAlert';
		break;
	case '2':
		$class = 'logCritical';
		break;
	case '3':
		$class = 'logError';
		break;
	case '4':
		$class = 'logWarning';
		break;
	case '5':
		$class = 'logNotice';
		break;
	case '6':
		$class = 'logInfo';
		break;
	case '7':
		$class = 'logDebug';
		break;
	}

	print "<tr title='" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "' class='$class syslogRow'>\n";
}

function sql_hosts_where($tab) {
	global $hostfilter, $syslog_incoming_config;

	$hostfilter  = '';

	if (!isempty_request_var('host') && get_request_var('host') != 'null') {
		$hostarray = explode(' ', get_request_var('host'));
		if ($hostarray[0] != '0') {
			foreach($hostarray as $host_id) {
				input_validate_input_number($host_id);
			}

			$hostfilter .= (strlen($hostfilter) ? ' AND ':'') . ' host_id IN(' . implode(',', $hostarray) . ')';
		}
	}
}

function syslog_export($tab) {
	global $syslog_incoming_config, $severities;

	include(dirname(__FILE__) . '/config.php');

	if ($tab == 'syslog') {
		header('Content-type: application/excel');
		header('Content-Disposition: attachment; filename=syslog_view-' . date('Y-m-d',time()) . '.csv');

		$sql_where  = '';
		$messages   = get_syslog_messages($sql_where, 100000, $tab);

		$hosts      = array_rekey(syslog_db_fetch_assoc('SELECT host_id, host FROM `' . $syslogdb_default . '`.`syslog_hosts`'), 'host_id', 'host');
		$facilities = array_rekey(syslog_db_fetch_assoc('SELECT facility_id, facility FROM `' . $syslogdb_default . '`.`syslog_facilities`'), 'facility_id', 'facility');
		$priorities = array_rekey(syslog_db_fetch_assoc('SELECT priority_id, priority FROM `' . $syslogdb_default . '`.`syslog_priorities`'), 'priority_id', 'priority');
		$programs = array_rekey(syslog_db_fetch_assoc('SELECT program_id, program FROM `' . $syslogdb_default . '`.`syslog_programs`'), 'program_id', 'program');

		print 'host, facility, priority, program, date, message' . "\r\n";

		if (sizeof($messages)) {
			foreach ($messages as $message) {
				print
					'"' .
					$hosts[$message['host_id']]                    . '","' .
					ucfirst($facilities[$message['facility_id']])  . '","' .
					ucfirst($priorities[$message['priority_id']])  . '","' .
					ucfirst($programs[$message['program_id']])     . '","' .
					$message['logtime']                            . '","' .
					$message[$syslog_incoming_config['textField']] . '"'   . "\r\n";
			}
		}
	} else {
		header('Content-type: application/excel');
		header('Content-Disposition: attachment; filename=alert_log_view-' . date('Y-m-d',time()) . '.csv');

		$sql_where  = '';
		$messages   = get_syslog_messages($sql_where, 100000, $tab);

		print 'name, severity, date, message, host, facility, priority, count' . "\r\n";

		if (sizeof($messages)) {
			foreach ($messages as $message) {
				print
					'"' .
					$message['name']                  . '","' .
					$severities[$message['severity']] . '","' .
					$message['logtime']               . '","' .
					$message['logmsg']                . '","' .
					$message['host']                  . '","' .
					ucfirst($message['facility'])     . '","' .
					ucfirst($message['priority'])     . '","' .
					$message['count']                 . '"'   . "\r\n";
			}
		}
	}
}

function syslog_debug($message) {
	global $syslog_debug;

	if ($syslog_debug) {
		echo 'SYSLOG: ' . trim($message) . "\n";
	}
}

function syslog_log_alert($alert_id, $alert_name, $severity, $msg, $count = 1, $html) {
	global $config, $severities;

	include(dirname(__FILE__) . '/config.php');

	if ($count <= 1) {
		$save['seq']         = '';
		$save['alert_id']    = $alert_id;
		$save['logseq']      = $msg['seq'];
		$save['logtime']     = $msg['date'] . ' ' . $msg['time'];
		$save['logmsg']      = db_qstr($msg['message']);
		$save['host']        = $msg['host'];
		$save['facility_id'] = $msg['facility_id'];
		$save['priority_id'] = $msg['priority_id'];
		$save['count']       = 1;
		$save['html']        = db_qstr($html);

		$id = 0;
		$id = syslog_sql_save($save, '`' . $syslogdb_default . '`.`syslog_logs`', 'seq');

		cacti_log("WARNING: The Syslog Alert '$alert_name' with Severity '" . $severities[$severity] . "', has been Triggered on Host '" . $msg['host'] . "', and Sequence '$id'", false, 'SYSLOG');

		return $id;
	} else {
		$save['seq']         = '';
		$save['alert_id']    = $alert_id;
		$save['logseq']      = 0;
		$save['logtime']     = date('Y-m-d H:i:s');
		$save['logmsg']      = db_qstr($alert_name);
		$save['host']        = 'N/A';
		$save['facility_id'] = $msg['facility_id'];
		$save['priority_id'] = $msg['priority_id'];
		$save['count']       = $count;
		$save['html']        = db_qstr($html);

		$id = 0;
		$id = syslog_sql_save($save, '`' . $syslogdb_default . '`.`syslog_logs`', 'seq');

		cacti_log("WARNING: The Syslog Intance Alert '$alert_name' with Severity '" . $severities[$severity] . "', has been Triggered, Count was '" . $count . "', and Sequence '$id'", false, 'SYSLOG');

		return $id;
	}
}

function syslog_manage_items($from_table, $to_table) {
	global $config, $syslog_cnn, $syslog_incoming_config;

	include(dirname(__FILE__) . '/config.php');

	/* Select filters to work on */
	$rows = syslog_db_fetch_assoc('SELECT * FROM `' . $syslogdb_default . "`.`syslog_remove` WHERE enabled='on'");

	syslog_debug('Found   ' . sizeof($rows) .  ',  Removal Rule(s)' .  ' to process');

	$removed = 0;
	$xferred = 0;
	$total   = 0;

	if (sizeof($rows)) {
		foreach($rows as $remove) {
			syslog_debug('Processing Rule  - ' . $remove['message']);

			$sql_sel = '';
			$sql_dlt = '';

			if ($remove['type'] == 'facility') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq FROM `" . $syslogdb_default . "`. $from_table
						WHERE facility_id IN
							(SELECT distinct facility_id from `". $syslogdb_default . "`syslog_facilities
							WHERE facility ='". $remove['message']."')";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE facility_id IN
							(SELECT distinct facility_id from `". $syslogdb_default . "`syslog_facilities
							WHERE facility ='". $remove['message']."')";
				}

			} elseif ($remove['type'] == 'host') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq
						FROM `" . $syslogdb_default . "`. $from_table
						WHERE host_id in
							(SELECT distinct host_id from `". $syslogdb_default . "`syslog_hosts
							WHERE host ='". $remove['message']."')";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE host_id in
							(SELECT distinct host_id from `". $syslogdb_default . "`syslog_hosts
							WHERE host ='". $remove['message']."')";
				}
			} elseif ($remove['type'] == 'messageb') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '" . $remove['message'] . "%' ";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '" . $remove['message'] . "%' ";
				}

			} elseif ($remove['type'] == 'messagec') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '%" . $remove['message'] . "%' ";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '%" . $remove['message'] . "%' ";
				}
			} elseif ($remove['type'] == 'messagee') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '%" . $remove['message'] . "' ";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE message LIKE '%" . $remove['message'] . "' ";
				}
			} elseif ($remove['type'] == 'sql') {
				if ($remove['method'] != 'del') {
					$sql_sel = "SELECT seq FROM `" . $syslogdb_default . "`. $from_table
						WHERE message (" . $remove['message'] . ") ";
				} else {
					$sql_dlt = "DELETE FROM `" . $syslogdb_default . "`. $from_table
						WHERE message (" . $remove['message'] . ") ";
				}
			}

			if ($sql_sel != '' || $sql_dlt != '') {
				$debugm = '';
				/* process the removal rule first */
				if ($sql_sel != '') {
					$move_count = 0;
					/* first insert, then delete */
					$move_records = syslog_db_fetch_assoc($sql_sel);
					syslog_debug("Found   ". sizeof($move_records) . " Message(s)");

					if (sizeof($move_records)) {
						$all_seq = '';
						$messages_moved = 0;
						foreach($move_records as $move_record) {
							$all_seq = $all_seq . ", " . $move_record['seq'];
						}

						$all_seq = preg_replace('/^,/i', '', $all_seq);
						syslog_db_execute("INSERT INTO `". $syslogdb_default . "`.`". $to_table ."`
							(facility_id, priority_id, host_id, logtime, message)
							(SELECT facility_id, priority_id, host_id, logtime, message
							FROM `". $syslogdb_default . "`.". $from_table ."
							WHERE seq in (" . $all_seq ."))");

						$messages_moved = db_affected_rows($syslog_cnn);

						if ($messages_moved > 0) {
							syslog_db_execute("DELETE FROM `". $syslogdb_default . "`.`" . $from_table ."`
								WHERE seq in (" . $all_seq .")" );
						}

						$xferred += $messages_moved;
						$move_count = $messages_moved;
					}
					$debugm   = "Moved   " . $move_count . " Message(s)";
				}

				if ($sql_dlt != '') {
					/* now delete the remainder that match */
					syslog_db_execute($sql_dlt);
					$removed += db_affected_rows($syslog_cnn);
					$debugm   = "Deleted " . $removed . " Message(s)";
				}

				syslog_debug($debugm);
			}
		}
	}

	return array('removed' => $removed, 'xferred' => $xferred);
}

