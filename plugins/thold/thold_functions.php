<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
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

function thold_update_contacts() {
	$users = db_fetch_assoc("SELECT id, 'email' AS type, email_address FROM user_auth WHERE email_address!=''");
	if (sizeof($users)) {
		foreach($users as $u) {
			$cid = db_fetch_cell('SELECT id FROM plugin_thold_contacts WHERE type="email" AND user_id=' . $u['id']);
			
			if ($cid) {
				db_execute("REPLACE INTO plugin_thold_contacts (id, user_id, type, data) VALUES ($cid, " . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			}else{
				db_execute("REPLACE INTO plugin_thold_contacts (user_id, type, data) VALUES (" . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			}
		}
	}
}

function thold_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs = array(
		'thold'    => __('Thresholds'),
		'log'      => __('Log'),
		'hoststat' => __('Device Status')
	);

	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));

	load_current_session_value('tab', 'sess_thold_graph_tab', 'general');
	$current_tab = get_request_var('action');

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") . 
				" href='" . htmlspecialchars($config['url_path'] .
				'plugins/thold/thold_graph.php?' .
				'action=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";
		}
	}

	print "</ul></nav></div>\n";
}

function thold_debug($txt) {
	global $debug;

	if (read_config_option('thold_log_debug') == 'on' || $debug) {
		thold_cacti_log($txt);
	}
}

function thold_initialize_rusage() {
	global $thold_start_rusage;

	if (function_exists('getrusage')) {
		$thold_start_rusage = getrusage();
	}

	$thold_start_rusage['microtime'] = microtime();
}

function thold_display_rusage() {
	global $thold_start_rusage;

	if (function_exists('getrusage')) {
		$dat = getrusage();

		html_start_box('', '100%', '', '3', 'left', '');
		print '<tr>';

		if (!isset($thold_start_rusage)) {
			print "<td colspan='10'>ERROR: Can not display RUSAGE please call thold_initialize_rusage first</td>";
		} else {
			$i_u_time = $thold_start_rusage['ru_utime.tv_sec'] + ($thold_start_rusage['ru_utime.tv_usec'] * 1E-6);
			$i_s_time = $thold_start_rusage['ru_stime.tv_sec'] + ($thold_start_rusage['ru_stime.tv_usec'] * 1E-6);
			$s_s      = $thold_start_rusage['ru_nswap'];
			$s_pf     = $thold_start_rusage['ru_majflt'];

			list($micro,$seconds) = explode(' ', $thold_start_rusage['microtime']);
			$start_time = $seconds + $micro;
			list($micro,$seconds) = explode(' ', microtime());
			$end_time   = $seconds + $micro;

			$utime    = ($dat['ru_utime.tv_sec'] + ($dat['ru_utime.tv_usec'] * 1E-6)) - $i_u_time;
			$stime    = ($dat['ru_stime.tv_sec'] + ($dat['ru_stime.tv_usec'] * 1E-6)) - $i_s_time;
			$swaps    = $dat['ru_nswap'] - $s_s;
			$pages    = $dat['ru_majflt'] - $s_pf;

			print "<td colspan='10' width='1%' style='text-align:left;'>";
			print '<b>' . __('Time:') . '</b>&nbsp;'   . round($end_time - $start_time,2) . ' seconds, ';
			print '<b>' . __('User:') . '</b>&nbsp;'   . round($utime,2) . ' seconds, ';
			print '<b>' . __('System:') . '</b>&nbsp;' . round($stime,2) . ' seconds, ';
			print '<b>' . __('Swaps:') . '</b>&nbsp;'  . ($swaps) . ' swaps, ';
			print '<b>' . __('Pages:') . '</b>&nbsp;'  . ($pages) . ' pages';
			print '</td>';
		}

		print '</tr>';
		html_end_box(false);
	}

}

function thold_legend() {
	global $thold_states;

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr>';
	foreach($thold_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display'] . "</td>";
	}
	print "</tr>";

	html_end_box(false);
}

function host_legend() {
	global $thold_host_states;

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr>';
	foreach($thold_host_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display'] . "</td>";
	}
	print '</tr>';

	html_end_box(false);
}

function log_legend() {
	global $thold_log_states;

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr>';
	foreach($thold_log_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display_short'] . "</td>";
	}
	print "</tr>";

	html_end_box(false);
}

function thold_expression_rpn_pop(&$stack) {
	global $rpn_error;

	if (sizeof($stack)) {
		return array_pop($stack);
	} else {
		$rpn_error = true;
		return false;
	}
}

function thold_expression_math_rpn($operator, &$stack) {
	global $rpn_error;

	switch($operator) {
	case '+':
	case '-':
	case '/':
	case '*':
	case '%':
	case '^':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = 'U';

		if (!$rpn_error) {
			@eval("\$v3 = " . $v2 . ' ' . $operator . ' ' . $v1 . ';');
			array_push($stack, $v3);
		}
		break;
	case 'SIN':
	case 'COS':
	case 'TAN':
	case 'ATAN':
	case 'SQRT':
	case 'FLOOR':
	case 'CEIL':
	case 'DEG2RAD':
	case 'RAD2DEG':
	case 'ABS':
	case 'EXP':
	case 'LOG':
		$v1 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			eval("\$v2 = " . $operator . '(' . $v1 . ');');
			array_push($stack, $v2);
		}
		break;
	case 'ATAN2':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			$v3 = atan2($v1, $v2);
			array_push($stack, $v3);
		}
		break;
	case 'ADDNAN':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			if ($v1 == 'NAN' || $v1 == 'U') $v1 = 0;
			if ($v2 == 'NAN' || $v2 == 'U') $v2 = 0;
			array_push($stack, $v1 + $v2);
		}
		break;
	}
}

function thold_expression_boolean_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'UN') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'U' || $v1 == 'NAN') {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'ISINF') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'INF' || $v1 == 'NEGINF') {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'AND') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 && $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'OR') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 || $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'IF') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if ($v3 == 0) {
			array_push($stack, $v1);
		} else {
			array_push($stack, $v2);
		}
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		/* deal with unknown or infinite data */
		if (($v1 == 'INF' || $v2 == 'INF') ||
			($v1 == 'NAN' || $v2 == 'NAN') ||
			($v1 == 'U' || $v2 == 'U') ||
			($v1 == 'NEGINF' || $v2 == 'NEGINF')) {
			array_push($stack, '0');
		}

		switch($operator) {
		case 'LT':
			if ($v1 < $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'GT':
			if ($v1 > $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'LE':
			if ($v1 <= $v2) {
			array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'GE':
			if ($v1 >= $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'EQ':
			if ($v1 == $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'NE':
			if ($v1 != $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		}
	}
}

function thold_expression_compare_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'MAX' || $operator == 'MIN') {
		$v[0] = thold_expression_rpn_pop($stack);
		$v[1] = thold_expression_rpn_pop($stack);

		if (in_array('INF', $v)) {
			array_push($stack, 'INF');
		}elseif (in_array('NEGINF', $v)) {
			array_push($stack, 'NEGINF');
		}elseif (in_array('U', $v)) {
			array_push($stack, 'U');
		}elseif (in_array('NAN', $v)) {
			array_push($stack, 'NAN');
		}elseif ($operator == 'MAX') {
			array_push($stack, max($v));
		} else {
			array_push($stack, min($v));
		}
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if (($v1 == 'U' || $v1 == 'NAN') ||
			($v2 == 'U' || $v2 == 'NAN') ||
			($v3 == 'U' || $v3 == 'NAN')) {
			array_push($stack, 'U');
		}elseif (($v1 == 'INF' || $v1 == 'NEGINF') ||
			($v2 == 'INF' || $v2 == 'NEGINF') ||
			($v3 == 'INF' || $v3 == 'NEGINF')) {
			array_push($stack, 'U');
		}elseif ($v1 < $v2) {
			if ($v3 >= $v1 && $v3 <= $v2) {
				array_push($stack, $v3);
			} else {
				array_push($stack, 'U');
			}
		} else {
			if ($v3 >= $v2 && $v3 <= $v1) {
				array_push($stack, $v3);
			} else {
				array_push($stack, 'U');
			}
		}
	}
}

function thold_expression_specvals_rpn($operator, &$stack, $count) {
	global $rpn_error;

	if ($operator == 'UNKN') {
		array_push($stack, 'U');
	}elseif ($operator == 'INF') {
		array_push($stack, 'INF');
	}elseif ($operator == 'NEGINF') {
		array_push($stack, 'NEGINF');
	}elseif ($operator == 'COUNT') {
		array_push($stack, $count);
	}elseif ($operator == 'PREV') {
		/* still have to figure this out */
	}
}

function thold_expression_stackops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'DUP') {
		$v1 = thold_expression_rpn_pop($stack);
		array_push($stack, $v1);
		array_push($stack, $v1);
	}elseif ($operator == 'POP') {
		thold_expression_rpn_pop($stack);
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		array_push($stack, $v2);
		array_push($stack, $v1);
	}
}

function thold_expression_time_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'NOW') {
		array_push($stack, time());
	}elseif ($operator == 'TIME') {
		/* still need to figure this one out */
	}elseif ($operator == 'LTIME') {
		/* still need to figure this one out */
	}
}

function thold_expression_setops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'SORT') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			sort($v, SORT_NUMERIC);

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'REV') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			$v = array_reverse($v);

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'AVG') {
		$count = thold_expression_rpn_pop($stack);
		if ($count > 0) {
			$total  = 0;
			$inf    = false;
			$neginf = false;
			for($i = 0; $i < $count; $i++) {
				$v = thold_expression_rpn_pop($stack);
				if ($v == 'INF') {
					$inf = true;
				}elseif ($v == 'NEGINF') {
					$neginf = true;
				} else {
					$total += $v;
				}
			}

			if ($inf) {
				array_push($stack, 'INF');
			}elseif ($neginf) {
				array_push($stack, 'NEGINF');
			} else {
				array_push($stack, $total/$count);
			}
		}
	}
}

function thold_expression_ds_value($operator, &$stack, $data_sources) {
	global $rpn_error;

	if (sizeof($data_sources)) {
	foreach($data_sources as $rrd_name => $value) {
		if (strtoupper($rrd_name) == $operator) {
			array_push($stack, $value);
			return;
		}
	}
	}

	array_push($stack, 0);
}

function thold_expression_specialtype_rpn($operator, &$stack, $local_data_id, $currentval) {
	switch ($operator) {
	case 'CURRENT_DATA_SOURCE':
		array_push($stack, $currentval);
		break;
	case 'CURRENT_GRAPH_MAXIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'upper_limit'));
		break;
	case 'CURRENT_GRAPH_MINIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'lower_limit'));
		break;
	case 'CURRENT_DS_MINIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'rrd_minimum'));
		break;
	case 'CURRENT_DS_MAXIMUM_VALUE':
		array_push($stack, get_current_value($local_data_id, 'rrd_maximum'));
		break;
	case 'VALUE_OF_HDD_TOTAL':
		array_push($stack, get_current_value($local_data_id, 'hdd_total'));
		break;
	case 'ALL_DATA_SOURCES_NODUPS':
	case 'ALL_DATA_SOURCES_DUPS':
		$v1 = 0;
		$all_dsns = array();
		$all_dsns = db_fetch_assoc_prepared('SELECT data_source_name 
			FROM data_template_rrd 
			WHERE local_data_id = ?', 
			array($local_data_id));

		if (is_array($all_dsns)) {
			foreach ($all_dsns as $dsn) {
				$v1 += get_current_value($local_data_id, $dsn['data_source_name']);
			}
		}

		array_push($stack, $v1);
		break;
	default:
		cacti_log('WARNING: CDEF property not implemented yet: ' . $operator, false, 'THOLD');
		array_push($stack, $currentval);
		break;
	}
}

function thold_get_currentval(&$thold_data, &$rrd_reindexed, &$rrd_time_reindexed, &$item, &$currenttime) {
	/* adjust the polling interval by the last read, if applicable */
	$currenttime = $rrd_time_reindexed[$thold_data['local_data_id']];
	if ($thold_data['lasttime'] > 0) {
		$polling_interval = $currenttime - $thold_data['lasttime'];
	} else {
		$polling_interval = $thold_data['rrd_step'];
	}

	if (empty($polling_interval)) {
		$polling_interval = read_config_option('poller_interval');
	}

	$currentval = 0;

	if (isset($rrd_reindexed[$thold_data['local_data_id']])) {
		$item = $rrd_reindexed[$thold_data['local_data_id']];
		if (isset($item[$thold_data['name']])) {
			switch ($thold_data['data_source_type_id']) {
			case 2:	// COUNTER
				if ($thold_data['oldvalue'] != 0) {
					if ($item[$thold_data['name']] >= $thold_data['oldvalue']) {
						// Everything is normal
						$currentval = $item[$thold_data['name']] - $thold_data['oldvalue'];
					} else {
						// Possible overflow, see if its 32bit or 64bit
						if ($thold_data['oldvalue'] > 4294967295) {
							$currentval = (18446744073709551615 - $thold_data['oldvalue']) + $item[$thold_data['name']];
						} else {
							$currentval = (4294967295 - $thold_data['oldvalue']) + $item[$thold_data['name']];
						}
					}

					$currentval = $currentval / $polling_interval;

					/* assume counter reset if greater than max value */
					if ($thold_data['rrd_maximum'] > 0 && $currentval > $thold_data['rrd_maximum']) {
						$currentval = $item[$thold_data['name']] / $polling_interval;
					}elseif ($thold_data['rrd_maximum'] == 0 && $currentval > 4.25E+9) {
						$currentval = $item[$thold_data['name']] / $polling_interval;
					}
				} else {
					$currentval = 0;
				}
				break;
			case 3:	// DERIVE
				$currentval = ($item[$thold_data['name']] - $thold_data['oldvalue']) / $polling_interval;
				break;
			case 4:	// ABSOLUTE
				$currentval = $item[$thold_data['name']] / $polling_interval;
				break;
			case 1:	// GAUGE
			default:
				$currentval = $item[$thold_data['name']];
				break;
			}
		}
	}

	return $currentval;
}

function thold_calculate_expression($thold, $currentval, &$rrd_reindexed, &$rrd_time_reindexed) {
	global $rpn_error;

	/* set an rpn error flag */
	$rpn_error = false;

	/* operators to support */
	$math       = array('+', '-', '*', '/', '%', '^', 'ADDNAN', 'SIN', 'COS', 'LOG', 'EXP',
		'SQRT', 'ATAN', 'ATAN2', 'FLOOR', 'CEIL', 'DEG2RAD', 'RAD2DEG', 'ABS');
	$boolean    = array('LT', 'LE', 'GT', 'GE', 'EQ', 'NE', 'UN', 'ISNF', 'IF', 'AND', 'OR');
	$comparison = array('MIN', 'MAX', 'LIMIT');
	$setops     = array('SORT', 'REV', 'AVG');
	$specvals   = array('UNKN', 'INF', 'NEGINF', 'PREV', 'COUNT');
	$stackops   = array('DUP', 'POP', 'EXC');
	$time       = array('NOW', 'TIME', 'LTIME');
	$spectypes  = array('CURRENT_DATA_SOURCE','CURRENT_GRAPH_MINIMUM_VALUE',
		'CURRENT_GRAPH_MINIMUM_VALUE','CURRENT_DS_MINIMUM_VALUE',
		'CURRENT_DS_MAXIMUM_VALUE','VALUE_OF_HDD_TOTAL',
		'ALL_DATA_SOURCES_NODUPS','ALL_DATA_SOURCES_DUPS');

	/* our expression array */
	$expression = explode(',', $thold['expression']);

	/* out current data sources */
	$data_sources = $rrd_reindexed[$thold['local_data_id']];
	if (sizeof($data_sources)) {
		foreach($data_sources as $key => $value) {
			$key = strtolower($key);
			$nds[$key] = $value;
		}
		$data_sources = $nds;
	}

	/* replace all data tabs in the rpn with values */
	if (sizeof($expression)) {
	foreach($expression as $key => $item) {
		if (substr_count($item, '|ds:')) {
			$dsname = strtolower(trim(str_replace('|ds:', '', $item), " |\n\r"));

			$thold_item = db_fetch_row("SELECT thold_data.id, thold_data.local_graph_id,
				thold_data.percent_ds, thold_data.expression,
				thold_data.data_type, thold_data.cdef, thold_data.local_data_id,
				thold_data.data_template_rrd_id, thold_data.lastread,
				UNIX_TIMESTAMP(thold_data.lasttime) AS lasttime, thold_data.oldvalue,
				data_template_rrd.data_source_name as name,
				data_template_rrd.data_source_type_id, data_template_data.rrd_step,
				data_template_rrd.rrd_maximum
				FROM thold_data
				LEFT JOIN data_template_rrd
				ON data_template_rrd.id = thold_data.data_template_rrd_id
				LEFT JOIN data_template_data
				ON data_template_data.local_data_id=thold_data.local_data_id
				WHERE data_template_rrd.data_source_name='$dsname'
				AND thold_data.local_data_id=" . $thold['local_data_id'], false);

			if (sizeof($thold_item)) {
				$item = array();
				$currenttime = 0;
				$expression[$key] = thold_get_currentval($thold_item, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);
			} else {
				$value = '';
				if (api_plugin_is_enabled('dsstats') && read_config_option('dsstats_enable') == 'on') {
					$value = db_fetch_cell('SELECT calculated
						FROM data_source_stats_hourly_last
						WHERE local_data_id=' . $thold['rrd_id'] . "
						AND rrd_name='$dsname'");
				}

				if (empty($value) || $value == '-90909090909') {
					$expression[$key] = get_current_value($thold['local_data_id'], $dsname);
				} else {
					$expression[$key] = $value;
				}
				cacti_log($expression[$key]);
			}

			if ($expression[$key] == '') $expression[$key] = '0';
		}elseif (substr_count($item, '|')) {
			$gl = db_fetch_row('SELECT * FROM graph_local WHERE id=' . $thold['local_graph_id']);

			if (sizeof($gl)) {
				$expression[$key] = thold_expand_title($thold, $gl['host_id'], $gl['snmp_query_id'], $gl['snmp_index'], $item);
			} else {
				$expression[$key] = '0';
				cacti_log("WARNING: Query Replacement for '$item' Does Not Exist");
			}

			if ($expression[$key] == '') $expression[$key] = '0';
		} else {
			/* normal operator */
		}
	}
	}

	//cacti_log(implode(',', array_keys($data_sources)));
	//cacti_log(implode(',', $data_sources));
	//cacti_log(implode(',', $expression));

	/* now let's process the RPN stack */
	$x = count($expression);

	if ($x == 0) return $currentval;

	/* operation stack for RPN */
	$stack = array();

	/* current pointer in the RPN operations list */
	$cursor = 0;

	while($cursor < $x) {
		$operator = strtoupper(trim($expression[$cursor]));

		/* is the operator a data source */
		if (is_numeric($operator)) {
			//cacti_log("NOTE: Numeric '$operator'", false, "THOLD");
			array_push($stack, $operator);
		}elseif (array_key_exists($operator, $data_sources)) {
			//cacti_log("NOTE: DS Value '$operator'", false, "THOLD");
			thold_expression_ds_value($operator, $stack, $data_sources);
		}elseif (in_array($operator, $comparison)) {
			//cacti_log("NOTE: Compare '$operator'", false, "THOLD");
			thold_expression_compare_rpn($operator, $stack);
		}elseif (in_array($operator, $boolean)) {
			//cacti_log("NOTE: Boolean '$operator'", false, "THOLD");
			thold_expression_boolean_rpn($operator, $stack);
		}elseif (in_array($operator, $math)) {
			//cacti_log("NOTE: Math '$operator'", false, "THOLD");
			thold_expression_math_rpn($operator, $stack);
		}elseif (in_array($operator, $setops)) {
			//cacti_log("NOTE: SetOps '$operator'", false, "THOLD");
			thold_expression_setops_rpn($operator, $stack);
		}elseif (in_array($operator, $specvals)) {
			//cacti_log("NOTE: SpecVals '$operator'", false, "THOLD");
			thold_expression_specvals_rpn($operator, $stack, $cursor + 2);
		}elseif (in_array($operator, $stackops)) {
			//cacti_log("NOTE: StackOps '$operator'", false, "THOLD");
			thold_expression_stackops_rpn($operator, $stack);
		}elseif (in_array($operator, $time)) {
			//cacti_log("NOTE: Time '$operator'", false, "THOLD");
			thold_expression_time_rpn($operator, $stack);
		}elseif (in_array($operator, $spectypes)) {
			//cacti_log("NOTE: SpecialTypes '$operator'", false, "THOLD");
			thold_expression_specialtype_rpn($operator, $stack, $thold['local_data_id'], $currentval);
		} else {
			cacti_log("WARNING: Unsupported Field '$operator'", false, "THOLD");
			$rpn_error = true;
		}

		$cursor++;

		if ($rpn_error) {
			cacti_log("ERROR: RPN Expression is invalid! THold:'" . $thold['thold_name'] . "', Value:'" . $currentval . "', Expression:'" . $thold['expression'] . "'", false, 'THOLD');
			return 0;
		}
	}

	return $stack[0];
}

function thold_expand_title($thold, $host_id, $snmp_query_id, $snmp_index, $string) {
	if (strstr($string, '|query_') && !empty($host_id)) {
		$value = thold_substitute_snmp_query_data($string, $host_id, $snmp_query_id, $snmp_index, read_config_option('max_data_query_field_length'));

		if ($value == '|query_ifHighSpeed|') {
			$value = thold_substitute_snmp_query_data('|query_ifSpeed|', $host_id, $snmp_query_id, $snmp_index, read_config_option('max_data_query_field_length')) / 1000000;
		}

		if (strstr($value, '|')) {
			cacti_log("WARNING: Expression Replacment for '$string' in THold '" . $thold['thold_name'] . "' Failed, A Reindex may be required!");
			return '0';
		}

		return $value;
	}elseif ((strstr($string, '|host_')) && (!empty($host_id))) {
		return thold_substitute_host_data($string, '|', '|', $host_id);
	}else{
		return $string;
	}
}

function thold_substitute_snmp_query_data($string, $host_id, $snmp_query_id, $snmp_index, $max_chars = 0) {
	$field_name = trim(str_replace('|query_', '', $string),"| \n\r");
	$snmp_cache_data = db_fetch_cell("SELECT field_value
		FROM host_snmp_cache
		WHERE host_id=$host_id
		AND snmp_query_id=$snmp_query_id
		AND snmp_index='$snmp_index'
		AND field_name='$field_name'");

	if ($snmp_cache_data != '') {
		return $snmp_cache_data;
	}else{
		return $string;
	}
}

function thold_substitute_host_data($string, $l_escape_string, $r_escape_string, $host_id) {
	$field_name = trim(str_replace('|host_', '', $string),"| \n\r");
	if (!isset($_SESSION['sess_host_cache_array'][$host_id])) {
		$host = db_fetch_row("SELECT * FROM host WHERE id=$host_id");
		$_SESSION['sess_host_cache_array'][$host_id] = $host;
	}

	if (isset($_SESSION['sess_host_cache_array'][$host_id][$field_name])) {
		return $_SESSION['sess_host_cache_array'][$host_id][$field_name];
	}

	$string = str_replace($l_escape_string . 'host_management_ip' . $r_escape_string, $_SESSION['sess_host_cache_array'][$host_id]['hostname'], $string);
	$temp = api_plugin_hook_function('substitute_host_data', array('string' => $string, 'l_escape_string' => $l_escape_string, 'r_escape_string' => $r_escape_string, 'host_id' => $host_id));
	$string = $temp['string'];

	return $string;
}

function thold_calculate_percent($thold, $currentval, $rrd_reindexed) {
	$ds = $thold['percent_ds'];
	if (isset($rrd_reindexed[$thold['local_data_id']][$ds])) {
		$t = $rrd_reindexed[$thold['local_data_id']][$thold['percent_ds']];
		if ($t != 0) {
			$currentval = ($currentval / $t) * 100;
		} else {
			$currentval = 0;
		}
	} else {
		$currentval = '';
	}
	return $currentval;
}

function get_allowed_thresholds($sql_where = '', $order_by = 'td.name', $limit = '', &$total_rows = 0, $user = 0, $graph_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.id=$graph_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option('auth_method') != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		}else{
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 
			'group' AS type, policy_graphs, policy_hosts, policy_graph_templates 
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on' AND uagm.user_id = ?", array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id = ?", array($user));
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . '))';
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$tholds = db_fetch_assoc("SELECT td.*, tt.name AS template_name,
			$sql_select
			FROM thold_data AS td
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			LEFT JOIN thold_template AS tt
			ON tt.id=td.thold_template_id
			$sql_join
			$sql_where
			$sql_having
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				SELECT $sql_select
				FROM thold_data AS td
				INNER JOIN graph_local AS gl 
				ON gl.id=td.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				LEFT JOIN thold_template AS tt
				ON tt.id=td.thold_template_id
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		$tholds = db_fetch_assoc("SELECT td.*, tt.name AS template_name
			FROM thold_data AS td
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			LEFT JOIN thold_template AS tt
			ON tt.id=td.thold_template_id
			$sql_where
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM thold_data AS td
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			LEFT JOIN thold_template AS tt
			ON tt.id=td.thold_template_id
			$sql_where");
	}

	return $tholds;
}

function get_allowed_threshold_logs($sql_where = '', $order_by = 'td.name', $limit = '', &$total_rows = 0, $user = 0, $graph_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.id=$graph_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option('auth_method') != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		}else{
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 
			'group' AS type, policy_graphs, policy_hosts, policy_graph_templates 
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on' AND uagm.user_id = ?", array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id = ?", array($user));
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . '))';
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$tholds = db_fetch_assoc("SELECT tl.*, h.description AS hdescription, td.name, gtg.title_cache,
			$sql_select
			FROM plugin_thold_log AS tl
			INNER JOIN thold_data AS td
			ON tl.threshold_id=td.id
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			$sql_join
			$sql_where
			$sql_having
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				SELECT $sql_select
				FROM plugin_thold_log AS tl
				INNER JOIN thold_data AS td
				ON tl.threshold_id=td.id
				INNER JOIN graph_local AS gl 
				ON gl.id=td.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN graph_templates_graph AS gtg
				ON gtg.local_graph_id=gl.id
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		$tholds = db_fetch_assoc("SELECT tl.*, h.description AS hdescription, td.name, gtg.title_cache
			FROM plugin_thold_log AS tl
			INNER JOIN thold_data AS td
			ON tl.threshold_id=td.id
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			$sql_where
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM plugin_thold_log AS tl
			INNER JOIN thold_data AS td
			ON tl.threshold_id=td.id
			INNER JOIN graph_local AS gl 
			ON gl.id=td.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
			$sql_where");
	}

	return $tholds;
}

function thold_user_auth_threshold($rra) {
	$graph = db_fetch_cell_prepared('SELECT gl.id
		FROM data_template_rrd AS dtr
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		LEFT JOIN graph_local AS gl
		ON gl.id=gti.local_graph_id
		WHERE dtr.local_data_id = ?', array($rra));

	if (!empty($graph) && is_graph_allowed($graph)) {
		return true;
	}

	return false;
}

function thold_log($save){
	global $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$save['id'] = 0;
	if (read_config_option('thold_log_cacti') == 'on') {
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $save['threshold_id'], FALSE);
		$dt    = db_fetch_cell('SELECT data_template_id FROM data_template_data WHERE local_data_id=' . $thold['local_data_id'], FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $dt, FALSE);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id'], FALSE);

		if ($save['status'] == 0) {
			$desc = 'Threshold Restored  ID: ' . $save['threshold_id'];
		} else {
			$desc = 'Threshold Breached  ID: ' . $save['threshold_id'];
		}
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;

		$desc .= '  Type: ' . $thold_types[$thold['thold_type']];
		$desc .= '  Enabled: ' . $thold['thold_enabled'];
		switch ($thold['thold_type']) {
		case 0:
			$desc .= '  Current: ' . $save['current'];
			$desc .= '  High: ' . $thold['thold_hi'];
			$desc .= '  Low: ' . $thold['thold_low'];
			$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['thold_fail_trigger'], 'alert');
			$desc .= '  Warning High: ' . $thold['thold_warning_hi'];
			$desc .= '  Warning Low: ' . $thold['thold_warning_low'];
			$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['thold_warning_fail_trigger'], 'alert');
			break;
		case 1:
			$desc .= '  Current: ' . $save['current'];
			break;
		case 2:
			$desc .= '  Current: ' . $save['current'];
			$desc .= '  High: ' . $thold['time_hi'];
			$desc .= '  Low: ' . $thold['time_low'];
			$desc .= '  Trigger: ' . $thold['time_fail_trigger'];
			$desc .= '  Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['time_fail_length'], 'time');
			$desc .= '  Warning High: ' . $thold['time_warning_hi'];
			$desc .= '  Warning Low: ' . $thold['time_warning_low'];
			$desc .= '  Warning Trigger: ' . $thold['time_warning_fail_trigger'];
			$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['time_warning_fail_length'], 'time');
			break;
		}

		$desc .= '  SentTo: ' . $save['emails'];
		if ($save['status'] == ST_RESTORAL || $save['status'] == ST_NOTIFYRS) {
			thold_cacti_log($desc);
		}
	}
	unset($save['emails']);

	$id = sql_save($save, 'plugin_thold_log');
}

function plugin_thold_duration_convert($rra, $data, $type, $field = 'local_data_id') {
	global $config, $repeatarray, $alertarray, $timearray;

	/* handle a null data value */
	if ($data == '') {
		return '';
	}

	include_once($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$step = db_fetch_cell("SELECT rrd_step FROM data_template_data WHERE $field=$rra");

	switch ($type) {
	case 'repeat':
		return (isset($repeatarray[$data]) ? $repeatarray[$data] : $data);
		break;
	case 'alert':
		return (isset($alertarray[$data]) ? $alertarray[$data] : $data);
		break;
	case 'time':
		return (isset($timearray[$data]) ? $timearray[$data] : $data);
		break;
	}

	return $data;
}

function plugin_thold_log_changes($id, $changed, $message = array()) {
	global $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$desc = '';

	if (read_config_option('thold_log_changes') != 'on') {
		return;
	}

	if (isset($_SESSION['sess_user_id'])) {
		$user = db_fetch_row('SELECT username FROM user_auth WHERE id = ' . $_SESSION['sess_user_id']);
		$user = $user['username'];
	} else {
		$user = 'Unknown';
	}

	switch ($changed) {
	case 'enabled_threshold':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Enabled Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?local_data_id=' . $thold['local_data_id'] . '&view_rrd=' . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'disabled_threshold':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Disabled Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold.php?local_data_id=" . $thold['local_data_id'] . "&view_rrd=" . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'reapply_name':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id=' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Reapply Threshold Name User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold.php?local_data_id=" . $thold['local_data_id'] . "&view_rrd=" . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'enabled_host':
		$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
		$desc = "User: $user  Enabled Device[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
		break;
	case 'disabled_host':
		$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
		$desc = "User: $user  Disabled Device[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
		break;
	case 'auto_created':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Auto-created Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold.php?local_data_id=" . $thold['local_data_id'] . "&view_rrd=" . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'created':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Created Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold.php?local_data_id=" . $thold['local_data_id'] . "&view_rrd=" . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'deleted':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc  = "Deleted Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold.php?local_data_id=" . $thold['local_data_id'] . "&view_rrd=" . $thold['data_template_rrd_id']) . "'>$id</a>";

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'deleted_template':
		$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);

		$desc  = "Deleted Template  User: $user  ID: $id";
		$desc .= '  DataTemplate: ' . $thold['data_template_name'];
		$desc .= '  DataSource: ' . $thold['data_source_name'];
		break;
	case 'modified':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);

		$rows  = db_fetch_assoc('SELECT plugin_thold_contacts.data
			FROM plugin_thold_contacts, plugin_thold_threshold_contact
			WHERE plugin_thold_contacts.id=plugin_thold_threshold_contact.contact_id
			AND plugin_thold_threshold_contact.thold_id=' . $id);

		$alert_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$alert_emails = array();
			if (count($rows)) {
				foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
				}
			}
			$alert_emails = implode(',', $alert_emails);
			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
		}

		$alert_emails .= (strlen($alert_emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Modified Threshold  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?local_data_id=' . $thold['local_data_id'] . '&view_rrd=' . $thold['data_template_rrd_id']) . "'>$id</a>";
		} else {
			$desc = "Created Threshold  User: $user  ID:  <a href='" . htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?local_data_id=' . $thold['local_data_id'] . '&view_rrd=' . $thold['data_template_rrd_id']) . "'>$id</a>";
		}

		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template_id']);
		$ds    = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_template_rrd_id']);

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;

		if ($message['template_enabled'] == 'on') {
			$desc .= '  Use Template: On';
		} else {
			$desc .= '  Type: ' . $thold_types[$thold['thold_type']];
			$desc .= '  Enabled: ' . $message['thold_enabled'];
			switch ($message['thold_type']) {
			case 0:
				$desc .= '  High: ' . $message['thold_hi'];
				$desc .= '  Low: ' . $message['thold_low'];
				$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['thold_fail_trigger'], 'alert');
				$desc .= '  Warning High: ' . $message['thold_warning_hi'];
				$desc .= '  Warning Low: ' . $message['thold_warning_low'];
				$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['thold_warning_fail_trigger'], 'alert');

				break;
			case 1:
				$desc .= '  Range: ' . $message['bl_ref_time_range'];
				$desc .= '  Dev Up: ' . $message['bl_pct_up'];
				$desc .= '  Dev Down: ' . $message['bl_pct_down'];
				$desc .= '  Trigger: ' . $message['bl_fail_trigger'];

				break;
			case 2:
				$desc .= '  High: ' . $message['time_hi'];
				$desc .= '  Low: ' . $message['time_low'];
				$desc .= '  Trigger: ' . $message['time_fail_trigger'];
				$desc .= '  Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['time_fail_length'], 'time');
				$desc .= '  Warning High: ' . $message['time_warning_hi'];
				$desc .= '  Warning Low: ' . $message['time_warning_low'];
				$desc .= '  Warning Trigger: ' . $message['time_warning_fail_trigger'];
				$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['time_warning_fail_length'], 'time');

				break;
			}
			$desc .= '  CDEF: ' . $message['cdef'];
			$desc .= '  ReAlert: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['repeat_alert'], 'alert');
			$desc .= '  Alert Emails: ' . $alert_emails;
			$desc .= '  Warning Emails: ' . $warning_emails;
		}

		break;
	case 'modified_template':
		$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);

		$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data
			FROM plugin_thold_contacts, plugin_thold_template_contact
			WHERE plugin_thold_contacts.id=plugin_thold_template_contact.contact_id
			AND plugin_thold_template_contact.template_id=' . $id);

		$alert_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$alert_emails = array();
			if (count($rows)) {
				foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
				}
			}
			$alert_emails = implode(',', $alert_emails);
			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
		}

		$alert_emails .= (strlen($alert_emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Modified Template  User: $user  ID: <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id") . "'>$id</a>";
		} else {
			$desc = "Created Template  User: $user  ID:  <a href='" . htmlspecialchars($config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id") . "'>$id</a>";
		}

		$desc .= '  DataTemplate: ' . $thold['data_template_name'];
		$desc .= '  DataSource: ' . $thold['data_source_name'];

		$desc .= '  Type: ' . $thold_types[$message['thold_type']];
		$desc .= '  Enabled: ' . $message['thold_enabled'];

		switch ($message['thold_type']) {
		case 0:
			$desc .= '  High: ' . (isset($message['thold_hi']) ? $message['thold_hi'] : '');
			$desc .= '  Low: ' . (isset($message['thold_low']) ? $message['thold_low'] : '');
			$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id');
			$desc .= '  Warning High: ' . (isset($message['thold_warning_hi']) ? $message['thold_warning_hi'] : '');
			$desc .= '  Warning Low: ' . (isset($message['thold_warning_low']) ? $message['thold_warning_low'] : '');
			$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_warning_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id');

			break;
		case 1:
			$desc .= '  Range: ' . $message['bl_ref_time_range'];
			$desc .= '  Dev Up: ' . (isset($message['bl_pct_up'])? $message['bl_pct_up'] : '' );
			$desc .= '  Dev Down: ' . (isset($message['bl_pct_down'])? $message['bl_pct_down'] : '' );
			$desc .= '  Trigger: ' . $message['bl_fail_trigger'];

			break;
		case 2:
			$desc .= '  High: ' . $message['time_hi'];
			$desc .= '  Low: ' . $message['time_low'];
			$desc .= '  Trigger: ' . $message['time_fail_trigger'];
			$desc .= '  Time: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_fail_length'], 'alert', 'data_template_id');
			$desc .= '  Warning High: ' . $message['time_warning_hi'];
			$desc .= '  Warning Low: ' . $message['time_warning_low'];
			$desc .= '  Warning Trigger: ' . $message['time_warning_fail_trigger'];
			$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_warning_fail_length'], 'alert', 'data_template_id');

			break;
		}

		$desc .= '  CDEF: ' . (isset($message['cdef']) ? $message['cdef']: '');
		$desc .= '  ReAlert: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['repeat_alert'], 'alert', 'data_template_id');
		$desc .= '  Alert Emails: ' . $alert_emails;
		$desc .= '  Warning Emails: ' . $warning_emails;

		break;
	}

	if ($desc != '') {
		thold_cacti_log($desc);
	}
}

function thold_datasource_required($name, $data_source) {
	$thold_show_datasource = read_config_option('thold_show_datasource');

	if ($thold_show_datasource == 'on') {
		if (strstr($name, "[$data_source]") !== false) {
			return false;
		}
	}else{
		return false;
	}

	return true;
}

function thold_check_threshold(&$thold_data) {
	global $config, $plugins, $debug, $thold_types;

	$name  = db_fetch_cell_prepared('SELECT data_source_name FROM data_template_rrd WHERE id = ?', array($thold_data['data_template_rrd_id']));

	thold_debug('Checking Threshold:' .
		' Name: ' . $name . 
		', local_data_id: ' . $thold_data['local_data_id'] . 
		', data_template_rrd_id: ' . $thold_data['data_template_rrd_id'] . 
		', value: ' . $thold_data['lastread']);

	$debug = false;

	if (!defined('STAT_HI')) {
		define('STAT_HI', 2);
	}

	if (!defined('STAT_LO')) {
		define('STAT_LO', 1);
	}

	if (!defined('STAT_NORMAL')) {
		define('STAT_NORMAL', 0);
	}

	// Do not proceed if we have chosen to globally disable all alerts
	if (read_config_option('thold_disable_all') == 'on') {
		thold_debug('Threshold checking is disabled globally');
		return;
	}

	$alert_exempt = read_config_option('alert_exempt');
	/* check for exemptions */
	$weekday = date('l');
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $alert_exempt == 'on') {
		thold_debug('Threshold checking is disabled by global weekend exemption');
		return;
	}

	/* check for the weekend exemption on the threshold level */
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $thold_data['exempt'] == 'on') {
		thold_debug('Threshold checking is disabled by global weekend exemption');
		return;
	}

	/* don't alert for this host if it's selected for maintenance */
	if (api_plugin_is_enabled('maint') || in_array('maint', $plugins)) {
		include_once($config['base_path'] . '/plugins/maint/functions.php');

		if (plugin_maint_check_cacti_host ($thold_data['host_id'])) {
			thold_debug('Threshold checking is disabled by maintenance schedule');
			return;
		}
	}

	$local_graph_id = $thold_data['local_graph_id'];

	/* only alert if Device is in UP mode (not down, unknown, or recovering) */
	$h = db_fetch_row('SELECT * FROM host WHERE id=' . $thold_data['host_id']);
	if (sizeof($h) && $h['status'] != 3) {
		thold_debug('Threshold checking halted by Device Status (' . $h['status'] . ')' );
		return;
	}

	/* ensure that Cacti will make of individual defined SNMP Engine IDs */
	$overwrite['snmp_engine_id'] = $h['snmp_engine_id'];
	
	/* pull the cached name, if not present, it means that the graph hasn't polled yet */
	$t = db_fetch_assoc('SELECT id, name, name_cache
		FROM data_template_data
		WHERE local_data_id = ' . $thold_data['local_data_id'] . '
		ORDER BY id
		LIMIT 1');

	/* pull a few default settings */
	$global_alert_address  = read_config_option('alert_email');
	$global_notify_enabled = (read_config_option('alert_notify_default') == 'on');
	$logset                = (read_config_option('alert_syslog') == 'on');
	$deadnotify            = (read_config_option('alert_deadnotify') == 'on');
	$realert               = read_config_option('alert_repeat');
	$alert_trigger         = read_config_option('alert_trigger');
	$alert_bl_trigger      = read_config_option('alert_bl_trigger');
	$httpurl               = read_config_option('base_url');
	$thold_send_text_only  = read_config_option('thold_send_text_only');

	$thold_snmp_traps             = (read_config_option('thold_alert_snmp') == 'on');
	$thold_snmp_warning_traps     = (read_config_option('thold_alert_snmp_warning') != 'on');
	$thold_snmp_normal_traps      = (read_config_option('thold_alert_snmp_normal') != 'on');
	$cacti_polling_interval       = read_config_option('poller_interval');

	/* remove this after adding an option for it */
	$thold_show_datasource = thold_datasource_required($thold_data['name'], $name);

	$trigger         = ($thold_data['thold_fail_trigger'] == '' ? $alert_trigger : $thold_data['thold_fail_trigger']);
	$warning_trigger = ($thold_data['thold_warning_fail_trigger'] == '' ? $alert_trigger : $thold_data['thold_warning_fail_trigger']);
	$alertstat       = $thold_data['thold_alert'];

	$alert_emails    = get_thold_alert_emails($thold_data);
	$warning_emails  = get_thold_warning_emails($thold_data);

	$alert_msg       = get_thold_alert_text($name, $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);
	$warn_msg        = get_thold_warning_text($name, $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

	$thold_snmp_data = get_thold_snmp_data($name, $thold_data, $h, $thold_data['lastread']);

	$file_array = array();
	if ($thold_send_text_only != 'on') {
		if (!empty($thold_data['local_graph_id'])) {
			$file_array = array(
				'local_graph_id' => $thold_data['local_graph_id'], 
				'local_data_id'  => $thold_data['local_data_id'], 
				'rra_id'         => 0, 
				'file'           => "$httpurl/graph_image.php?local_graph_id=" . $thold_data['local_graph_id'] . '&rra_id=0&view_type=tree', 
				'mimetype'       => 'image/png', 
				'filename'       => clean_up_name($thold_data['name'])
			);
		}
	}

	$url = $httpurl . '/graph.php?local_graph_id=' . $thold_data['local_graph_id'] . '&rra_id=all';

	switch ($thold_data['thold_type']) {
	case 0:	/* hi/low */
		if ($thold_data['lastread'] != '') {
			$breach_up           = ($thold_data['thold_hi'] != '' && $thold_data['lastread'] > $thold_data['thold_hi']);
			$breach_down         = ($thold_data['thold_low'] != '' && $thold_data['lastread'] < $thold_data['thold_low']);
			$warning_breach_up   = ($thold_data['thold_warning_hi'] != '' && $thold_data['lastread'] > $thold_data['thold_warning_hi']);
			$warning_breach_down = ($thold_data['thold_warning_low'] != '' && $thold_data['lastread'] < $thold_data['thold_warning_low']);
		} else {
			$breach_up           = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		/* is in alert status */
		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low check breached HI:' . $thold_data['thold_hi'] . '  LOW:' . $thold_data['thold_low'] . ' VALUE:' . $thold_data['lastread']);

			$thold_data['thold_fail_count']++;
			$thold_data['thold_alert'] = ($breach_up ? STAT_HI : STAT_LO);

			/* Re-Alert? */
			$ra = ($thold_data['thold_fail_count'] > $trigger && $thold_data['repeat_alert'] != 0 && $thold_data['thold_fail_count'] % $thold_data['repeat_alert'] == 0);

			if ($thold_data['thold_fail_count'] == $trigger || $ra) {
				$notify = true;
			}

			$subject = 'ALERT: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']) . ' with ' . $thold_data['lastread'];
			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($thold_data['name'], ($ra ? 'realert':'alert'), ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']), $thold_data['lastread'], $trigger, $thold_data['thold_fail_count'], $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data['eventClass'] = 3;
					$thold_snmp_data['eventSeverity'] = $thold_data['snmp_event_severity'];
					$thold_snmp_data['eventStatus'] = $thold_data['thold_alert']+1;
					$thold_snmp_data['eventRealertStatus'] = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
					$thold_snmp_data['eventFailCount'] = $thold_data['thold_fail_count'];
					$thold_snmp_data['eventFailDuration'] = $thold_data['thold_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data['eventFailDurationTrigger'] = $trigger * $cacti_polling_interval;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
					    $thold_snmp_data['eventDescription']
					);
					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}

			db_execute('UPDATE thold_data
				SET thold_alert=' . $thold_data['thold_alert'] . ',
				thold_fail_count=' . $thold_data['thold_fail_count'] . ",
				thold_warning_fail_count=0
				WHERE id=" . $thold_data['id']); 
		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low Warning check breached HI:' . $thold_data['thold_warning_hi'] . '  LOW:' . $thold_data['thold_warning_low'] . ' VALUE:' . $thold_data['lastread']);

			$thold_data['thold_warning_fail_count']++;
			$thold_data['thold_alert'] = ($warning_breach_up ? STAT_HI:STAT_LO);

			/* re-alert? */
			$ra = ($thold_data['thold_warning_fail_count'] > $warning_trigger && $thold_data['repeat_alert'] != 0 && $thold_data['thold_warning_fail_count'] % $thold_data['repeat_alert'] == 0);

			if ($thold_data['thold_warning_fail_count'] == $warning_trigger || $ra) {
				$notify = true;
			}

			$subject = ($notify ? 'WARNING: ':'TRIGGER: ') . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($warning_breach_up ? 'above' : 'below') . ' threshold of ' . ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']) . ' with ' . $thold_data['lastread'];

			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($thold_data['name'], ($ra ? 'rewarning':'warning'), ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']), $thold_data['lastread'], $warning_trigger, $thold_data['thold_warning_fail_count'], $url);
				}

				if (trim($warning_emails) != '') {
					thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data['eventClass'] = 2;
					$thold_snmp_data['eventSeverity'] = $thold_data['snmp_event_warning_severity'];
					$thold_snmp_data['eventStatus'] = $thold_data['thold_alert']+1;
					$thold_snmp_data['eventRealertStatus'] = ($ra ? ($warning_breach_up ? 3:2) :1);
					$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRA:ST_NOTIFYWA)+1;
					$thold_snmp_data['eventFailCount'] = $thold_data['thold_warning_fail_count'];
					$thold_snmp_data['eventFailDuration'] = $thold_data['thold_warning_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data['eventFailDurationTrigger'] = $warning_trigger * $cacti_polling_interval;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
					    $thold_snmp_data['eventDescription']
					);
					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYWA),
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}elseif (($thold_data['thold_warning_fail_count'] >= $warning_trigger) && ($thold_data['thold_fail_count'] >= $trigger)) {
				$subject = 'ALERT -> WARNING: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' Changed to Warning Threshold with Value ' . $thold_data['lastread'];

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data['eventClass'] = 2;
					$thold_snmp_data['eventSeverity'] = $thold_data['snmp_event_warning_severity'];
					$thold_snmp_data['eventStatus'] = $thold_data['thold_alert']+1;
					$thold_snmp_data['eventNotificationType'] = ST_NOTIFYAW+1;
					$thold_snmp_data['eventFailCount'] = $thold_data['thold_warning_fail_count'];
					$thold_snmp_data['eventFailDuration'] = $thold_data['thold_warning_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data['eventFailDurationTrigger'] = $trigger * $cacti_polling_interval;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
					    $thold_snmp_data['eventDescription']
					);

					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYAW,
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}

			db_execute('UPDATE thold_data
				SET thold_alert=' . $thold_data['thold_alert'] . ',
				thold_warning_fail_count=' . $thold_data['thold_warning_fail_count'] . ',
				thold_fail_count=0
				WHERE id=' . $thold_data['id']);
		} else {
			thold_debug('Threshold HI / Low check is normal HI:' . $thold_data['thold_hi'] . '  LOW:' . $thold_data['thold_low'] . ' VALUE:' . $thold_data['lastread']);

			/* if we were at an alert status before */
			if ($alertstat != 0) {
				$subject = 'NORMAL: '. $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' Restored to Normal Threshold with Value ' . $thold_data['lastread'];

				db_execute("UPDATE thold_data
					SET thold_alert=0, 
					thold_fail_count=0, 
					thold_warning_fail_count=0
					WHERE id=" . $thold_data['id']);

				if ($thold_data['thold_warning_fail_count'] >= $warning_trigger && $thold_data['restored_alert'] != 'on') {
					if ($logset == 1) {
						logger($thold_data['name'], 'ok', 0, $thold_data['lastread'], $warning_trigger, $thold_data['thold_warning_fail_count'], $url);
					}

					if (trim($warning_emails) != '' && $thold_data['restored_alert'] != 'on') {
						thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data['eventClass'] = 1;
						$thold_snmp_data['eventSeverity'] = 1;
						$thold_snmp_data['eventStatus'] = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);
						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}

					thold_log(array(
						'type'            => 0,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRS,
						'description'     => $subject,
						'emails'          => $warning_emails)
					);
				} elseif ($thold_data['thold_fail_count'] >= $trigger && $thold_data['restored_alert'] != 'on') {
					if ($logset == 1) {
						logger($thold_data['name'], 'ok', 0, $thold_data['lastread'], $trigger, $thold_data['thold_fail_count'], $url);
					}

					if (trim($alert_emails) != '' && $thold_data['restored_alert'] != 'on') {
						thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data['eventClass'] = 1;
						$thold_snmp_data['eventSeverity'] = 1;
						$thold_snmp_data['eventStatus'] = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}

					thold_log(array(
						'type'            => 0,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRS,
						'description'     => $subject,
						'emails'          => $alert_emails)
					);
				}
			}
		}

		break;
	case 1:	/* baseline */
		$bl_alert_prev    = $thold_data['bl_alert'];
		$bl_count_prev    = $thold_data['bl_fail_count'];
		$bl_fail_trigger  = ($thold_data['bl_fail_trigger'] == '' ? $alert_bl_trigger : $thold_data['bl_fail_trigger']);
		$thold_data['bl_alert'] = thold_check_baseline($thold_data['local_data_id'], $name, $thold_data['lastread'], $thold_data);

		switch($thold_data['bl_alert']) {
		case -2:	/* exception is active, Future Release 'todo' */
			break;
		case -1:	/* reference value not available, Future Release 'todo' */
			break;
		case 0:		/* all clear */
			/* if we were at an alert status before */
			if ($alertstat != 0) {
				thold_debug('Threshold Baseline check is normal');

				if ($thold_data['bl_fail_count'] >= $bl_fail_trigger && $thold_data['restored_alert'] != 'on') {
					thold_debug('Threshold Baseline check returned to normal');

					if ($logset == 1) {
						logger($thold_data['name'], 'ok', 0, $thold_data['lastread'], $thold_data['bl_fail_trigger'], $thold_data['bl_fail_count'], $url);
					}

					$subject = 'NORMAL: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' restored to normal threshold with value ' . $thold_data['lastread'];

					if (trim($alert_emails) != '') {
						thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data['eventClass'] = 1;
						$hold_snmp_data['eventSeverity'] = 1;
						$thold_snmp_data['eventStatus'] = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}

					thold_log(array(
						'type'            => 1,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRA,
						'description'     => $subject,
						'emails'          => $alert_emails)
					);
				}
			}

			$thold_data['bl_fail_count'] = 0;

			break;
		case 1: /* value is below calculated threshold */
		case 2: /* value is above calculated threshold */
			$thold_data['bl_fail_count']++;
			$breach_up   = ($thold_data['bl_alert'] == STAT_HI);
			$breach_down = ($thold_data['bl_alert'] == STAT_LO);

			thold_debug('Threshold Baseline check breached');

			/* re-alert? */
			$ra = ($thold_data['bl_fail_count'] > $bl_fail_trigger && ($thold_data['bl_fail_count'] % ($thold_data['repeat_alert'] == '' ? $realert : $thold_data['repeat_alert'])) == 0);

			if ($thold_data['bl_fail_count'] == $bl_fail_trigger || $ra) {
				thold_debug('Alerting is necessary');

				$subject = 'ALERT: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' calculated baseline threshold ' . ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']) . ' with ' . $thold_data['lastread'];

				if ($logset == 1) {
					logger($thold_data['name'], ($ra ? 'realert':'alert'), ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']), $thold_data['lastread'], $thold_data['bl_fail_trigger'], $thold_data['bl_fail_count'], $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data['eventClass']            = 3;
					$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_severity'];
					$thold_snmp_data['eventStatus']           = $thold_data['bl_alert']+1;
					$thold_snmp_data['eventRealertStatus']    = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
					$thold_snmp_data['eventFailCount']        = $thold_data['bl_fail_count'];
					$thold_snmp_data['eventFailDuration']     = $thold_data['bl_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data['eventFailCountTrigger'] = $bl_fail_trigger;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
					    $thold_snmp_data['eventDescription']
					);


					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 1,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['bl_pct_up'] : $thold_data['bl_pct_down']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			} else {
				$subject = 'Thold Baseline Cache Log';

				thold_log(array(
					'type'            => 1,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['bl_pct_up'] : $thold_data['bl_pct_down']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_TRIGGERA,
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}

			break;
		}

		db_execute("UPDATE thold_data 
			SET thold_alert=0, 
			thold_fail_count=0,
			bl_alert='" . $thold_data['bl_alert'] . "',
			bl_fail_count='" . $thold_data['bl_fail_count'] . "',
			thold_low='" . $thold_data['thold_low'] . "',
			thold_hi='" . $thold_data['thold_hi'] . "',
			bl_thold_valid='" . $thold_data['bl_thold_valid'] . "'
			WHERE id=" . $thold_data['id']);

		break;
	case 2:	/* time based */
		if ($thold_data['lastread'] != '') {
			$breach_up           = ($thold_data['time_hi']          != '' && $thold_data['lastread'] > $thold_data['time_hi']);
			$breach_down         = ($thold_data['time_low']         != '' && $thold_data['lastread'] < $thold_data['time_low']);
			$warning_breach_up   = ($thold_data['time_warning_hi']  != '' && $thold_data['lastread'] > $thold_data['time_warning_hi']);
			$warning_breach_down = ($thold_data['time_warning_low'] != '' && $thold_data['lastread'] < $thold_data['time_warning_low']);
		} else {
			$breach_up           = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $local_data_id, FALSE);

		/* alerts */
		$trigger  = $thold_data['time_fail_trigger'];
		$time     = time() - ($thold_data['time_fail_length'] * $step);
		$failures = db_fetch_cell('SELECT count(id) 
			FROM plugin_thold_log 
			WHERE threshold_id=' . $thold_data['id'] . ' 
			AND status IN (' . ST_TRIGGERA . ',' . ST_NOTIFYRA . ',' . ST_NOTIFYAL . ') 
			AND time>' . $time);

		/* warnings */
		$warning_trigger  = $thold_data['time_warning_fail_trigger'];
		$warning_time     = time() - ($thold_data['time_warning_fail_length'] * $step);
		$warning_failures = db_fetch_cell('SELECT count(id) 
			FROM plugin_thold_log 
			WHERE threshold_id=' . $thold_data['id'] . ' 
			AND status IN (' . ST_NOTIFYWA . ',' . ST_TRIGGERW . ') 
			AND time>' . $warning_time) + $failures;

		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold Time Based check breached HI:' . $thold_data['time_hi'] . ' LOW:' . $thold_data['time_low'] . ' VALUE:' . $thold_data['lastread']);

			$thold_data['thold_alert']      = ($breach_up ? STAT_HI:STAT_LO);
			$thold_data['thold_fail_count'] = $failures;

			/* we should only re-alert X minutes after last email, not every 5 pollings, etc...
			   re-alert? */
			$realerttime   = ($thold_data['repeat_alert']-1) * $step;
			$lastemailtime = db_fetch_cell('SELECT time
				FROM plugin_thold_log
				WHERE threshold_id=' . $thold_data['id'] . '
				AND status IN (' . ST_NOTIFYRA . ',' . ST_NOTIFYAL . ')
				ORDER BY time DESC
				LIMIT 1', FALSE);

			$ra = ($failures > $trigger && $thold_data['repeat_alert'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			$failures++;

			thold_debug("Alert Time:'$time', Alert Trigger:'$trigger', Alert Failures:'$failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");


			if ($failures == $trigger || $ra) {
				$notify = true;
			}

			$subject = ($notify ? 'ALERT: ':'TRIGGER: ') . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($failures > $trigger ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']) . ' with ' . $thold_data['lastread'];

			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($thold_data['name'], ($failures > $trigger ? 'realert':'alert'), ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']), $thold_data['lastread'], $trigger, $failures, $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data['eventClass'] = 3;
					$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_severity'];
					$thold_snmp_data['eventStatus']           = $thold_data['thold_alert']+1;
					$thold_snmp_data['eventRealertStatus']    = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data['eventNotificationType'] = ($failures > $trigger ? ST_NOTIFYAL:ST_NOTIFYRA)+1;
					$thold_snmp_data['eventFailCount']        = $failures;
					$thold_snmp_data['eventFailCountTrigger'] = $trigger;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace('<FAIL_COUNT>', $thold_snmp_data['eventFailCount'], $thold_snmp_data['eventDescription']);

					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($failures > $trigger ? ST_NOTIFYAL:ST_NOTIFYRA),
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			} else {
				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_TRIGGERA,
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}

			db_execute('UPDATE thold_data
				SET thold_alert=' . $thold_data['thold_alert'] . ",
				thold_fail_count=$failures
				WHERE id=" . $thold_data['id']);
		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			$thold_data['thold_alert']              = ($warning_breach_up ? STAT_HI:STAT_LO);
			$thold_data['thold_warning_fail_count'] = $warning_failures;

			/* we should only re-alert X minutes after last email, not every 5 pollings, etc...
			   re-alert? */
			$realerttime   = ($thold_data['time_warning_fail_length']-1) * $step;
			$lastemailtime = db_fetch_cell('SELECT time
				FROM plugin_thold_log
				WHERE threshold_id=' . $thold_data['id'] . '
				AND status IN (' . ST_NOTIFYRA . ',' . ST_NOTIFYWA . ')
				ORDER BY time DESC
				LIMIT 1', FALSE);

			$ra = ($warning_failures > $warning_trigger && $thold_data['time_warning_fail_length'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			$warning_failures++;

			thold_debug("Warn Time:'$warning_time', Warn Trigger:'$warning_trigger', Warn Failures:'$warning_failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");

			if ($warning_failures == $warning_trigger || $ra) {
				$notify = true;;
			}

			$subject = ($notify ? 'WARNING: ':'TRIGGER: ') . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($warning_failures > $warning_trigger ? 'is still' : 'went') . ' ' . ($warning_breach_up ? 'above' : 'below') . ' threshold of ' . ($warning_breach_up ? $thold_data['time_warning_hi'] : $thold_data['time_warning_low']) . ' with ' . $thold_data['lastread'];

			if ($notify) {
				if ($logset == 1) {
					logger($thold_data['name'], ($warning_failures > $warning_trigger ? 'rewarning':'warning'), ($warning_breach_up ? $thold_data['time_warning_hi'] : $thold_data['time_warning_low']), $thold_data['lastread'], $warning_trigger, $warning_failures, $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data['eventClass']            = 2;
					$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_warning_severity'];
					$thold_snmp_data['eventStatus']           = $thold_data['thold_alert']+1;
					$thold_snmp_data['eventRealertStatus']    = ($ra ? ($warning_breach_up ? 3:2) :1);
					$thold_snmp_data['eventNotificationType'] = ($warning_failures > $warning_trigger ? ST_NOTIFYRA:ST_NOTIFYWA)+1;
					$thold_snmp_data['eventFailCount']        = $warning_failures;
					$thold_snmp_data['eventFailCountTrigger'] = $warning_trigger;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					$thold_snmp_data['eventDescription'] = str_replace('<FAIL_COUNT>', $thold_snmp_data['eventFailCount'], $thold_snmp_data['eventDescription']);

					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($warning_failures > $warning_trigger ? ST_NOTIFYRA:ST_NOTIFYWA),
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			} elseif ($alertstat != 0 && $warning_failures < $warning_trigger && $failures < $trigger) {
				$subject = 'ALERT -> WARNING: '. $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' restored to warning threshold with value ' . $thold_data['lastread'];

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYAW,
					'description'     => $subject,
					'emails'          => $alert_emails)
				);
			}else{
				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_TRIGGERW,
					'description'     => $subject,
					'emails'          => $warning_emails)
				);
			}

			db_execute('UPDATE thold_data
				SET thold_alert=' . $thold_data['thold_alert'] . ",
				thold_warning_fail_count=$warning_failures,
				thold_fail_count=$failures
				WHERE id=" . $thold_data['id']);
		} else {
			thold_debug('Threshold Time Based check is normal HI:' . $thold_data['time_hi'] . ' LOW:' . $thold_data['time_low'] . ' VALUE:' . $thold_data['lastread']);

			if ($alertstat != 0 && $warning_failures < $warning_trigger && $thold_data['restored_alert'] != 'on') {
				if ($logset == 1) {
					logger($thold_data['name'], 'ok', 0, $thold_data['lastread'], $warning_trigger, $thold_data['thold_warning_fail_count'], $url);
				}

				$subject = 'NORMAL: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' restored to normal threshold with value ' . $thold_data['lastread'];

				if (trim($warning_emails) != '' && $thold_data['restored_alert'] != 'on') {
					thold_mail($warning_emails, '', $subject, $alert_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_normal_traps) {
					$thold_snmp_data['eventClass'] = 1;
					$thold_snmp_data['eventSeverity'] = 1;
					$thold_snmp_data['eventStatus'] = 1;
					$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => '',
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYRS,
					'description'     => $subject,
					'emails'          => $warning_emails)
				);

				db_execute("UPDATE thold_data
					SET thold_alert=0, 
					thold_warning_fail_count=$warning_failures, 
					thold_fail_count=$failures
					WHERE id=" . $thold_data['id']);
			} elseif ($alertstat != 0 && $failures < $trigger && $thold_data['restored_alert'] != 'on') {
				if ($logset == 1) {
					logger($thold_data['name'], 'ok', 0, $thold_data['lastread'], $trigger, $thold_data['thold_fail_count'], $url);
				}

				$subject = 'NORMAL: ' . $thold_data['name'] . ($thold_show_datasource ? " [$name]" : '') . ' restored to warning threshold with value ' . $thold_data['lastread'];

				if (trim($alert_emails) != '' && $thold_data['restored_alert'] != 'on') {
					thold_mail($alert_emails, '', $subject, $alert_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_normal_traps) {
					$thold_snmp_data['eventClass']            = 1;
					$thold_snmp_data['eventSeverity']         = 1;
					$thold_snmp_data['eventStatus']           = 1;
					$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
					$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

					thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => '',
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYRS,
					'description'     => $subject,
					'emails'          => $alert_emails)
				);

				db_execute("UPDATE thold_data
					SET thold_alert=0, 
					thold_warning_fail_count=$warning_failures, 
					thold_fail_count=$failures
					WHERE id=" . $thold_data['id']);
			} else {
				db_execute("UPDATE thold_data
					SET thold_fail_count=$failures,
					thold_warning_fail_count=$warning_failures
					WHERE id=" . $thold_data['id']);
			}
		}

		break;
	}
}

function get_thold_snmp_data($name, $thold, $h, $currentval) {
	global $thold_types;

	// Do some replacement of variables
	$thold_snmp_data = array(
		'eventDateRFC822'			=> date(DATE_RFC822),
		'eventClass'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventSeverity'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventCategory'				=> ($thold['snmp_event_category'] ? $thold['snmp_event_category'] : ''),
		'eventSource'				=> $thold['name'],
		'eventDescription'			=> '',						// default - see CACTI-THOLD-MIB
		'eventDevice'				=> $h['hostname'],
		'eventDataSource'			=> $name,
		'eventCurrentValue'			=> $currentval,
		'eventHigh'					=> ($thold['thold_type'] == 0 ? $thold['thold_hi'] : ($thold['thold_type'] == 2 ? $thold['time_warning_hi'] : '')),
		'eventLow'					=> ($thold['thold_type'] == 0 ? $thold['thold_low'] : ($thold['thold_type'] == 2 ? $thold['time_warning_low'] : '')),
		'eventThresholdType'		=> $thold_types[$thold['thold_type']] + 1,
		'eventNotificationType'		=> 5,						// default - see CACTI-THOLD-MIB
		'eventStatus'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventRealertStatus'		=> 1,						// default - see CACTI-THOLD-MIB
		'eventFailDuration'			=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailCount'			=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailDurationTrigger'	=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailCountTrigger'		=> 0,						// default - see CACTI-THOLD-MIB
	);

	$snmp_event_description = read_config_option('thold_snmp_event_description');

	$snmp_event_description = str_replace('<THRESHOLDNAME>', $thold_snmp_data['eventSource'], $snmp_event_description);
	$snmp_event_description = str_replace('<HOSTNAME>', $thold_snmp_data['eventDevice'], $snmp_event_description);
	$snmp_event_description = str_replace('<TEMPLATE_ID>', ($thold['thold_template_id'] ? $thold['thold_template_id'] : 'none'), $snmp_event_description);
	$snmp_event_description = str_replace('<TEMPLATE_NAME>', (isset($thold['name']) ? $thold['name'] : 'none'), $snmp_event_description);
	$snmp_event_description = str_replace('<THR_TYPE>', $thold_snmp_data['eventThresholdType'], $snmp_event_description);
	$snmp_event_description = str_replace('<DS_NAME>', $thold_snmp_data['eventDataSource'], $snmp_event_description);
	$snmp_event_description = str_replace('<HI>', $thold_snmp_data['eventHigh'], $snmp_event_description);
	$snmp_event_description = str_replace('<LOW>', $thold_snmp_data['eventLow'], $snmp_event_description);
	$snmp_event_description = str_replace('<EVENT_CATEGORY>', $thold_snmp_data['eventCategory'], $snmp_event_description);
	$thold_snmp_data['eventDescription'] = $snmp_event_description;

	return $thold_snmp_data;
}

function get_thold_alert_text($name, $thold, $h, $currentval, $local_graph_id) {
	global $thold_types;

	$alert_text = read_config_option('thold_alert_text');
	$httpurl    = read_config_option('base_url');

	/* make sure the alert text has been set */
	if (!isset($alert_text) || $alert_text == '') {
		$alert_text = __('<html><body>An alert has been issued that requires your attention.<br><br><b>Device</b>: <DESCRIPTION> (<HOSTNAME>)<br><b>URL</b>: <URL><br><b>Message</b>: <SUBJECT><br><br><GRAPH></body></html>');
	}

	// Do some replacement of variables
	$alert_text = str_replace('<DESCRIPTION>',   $h['description'], $alert_text);
	$alert_text = str_replace('<HOSTNAME>',      $h['hostname'], $alert_text);
	$alert_text = str_replace('<TIME>',          time(), $alert_text);
	$alert_text = str_replace('<GRAPHID>',       $local_graph_id, $alert_text);

	$alert_text = str_replace('<CURRENTVALUE>',  $currentval, $alert_text);
	$alert_text = str_replace('<THRESHOLDNAME>', $thold['name'], $alert_text);
	$alert_text = str_replace('<DSNAME>',        $name, $alert_text);
	$alert_text = str_replace('<THOLDTYPE>',     $thold_types[$thold['thold_type']], $alert_text);
	$alert_text = str_replace('<NOTES>',         $thold['notes'], $alert_text);

	if ($thold['thold_type'] == 0) {
		$alert_text = str_replace('<HI>',        $thold['thold_hi'], $alert_text);
		$alert_text = str_replace('<LOW>',       $thold['thold_low'], $alert_text);
		$alert_text = str_replace('<TRIGGER>',   $thold['thold_fail_trigger'], $alert_text);
		$alert_text = str_replace('<DURATION>',  '', $alert_text);
	}elseif ($thold['thold_type'] == 2) {
		$alert_text = str_replace('<HI>',        $thold['time_hi'], $alert_text);
		$alert_text = str_replace('<LOW>',       $thold['time_low'], $alert_text);
		$alert_text = str_replace('<TRIGGER>',   $thold['time_fail_trigger'], $alert_text);
		$alert_text = str_replace('<DURATION>',  plugin_thold_duration_convert($thold['local_data_id'], $thold['time_fail_length'], 'time'), $alert_text);
	}else{
		$alert_text = str_replace('<HI>',        '', $alert_text);
		$alert_text = str_replace('<LOW>',       '', $alert_text);
		$alert_text = str_replace('<TRIGGER>',   '', $alert_text);
		$alert_text = str_replace('<DURATION>',  '', $alert_text);
	}

	$alert_text = str_replace('<DATE_RFC822>',   date(DATE_RFC822), $alert_text);
	$alert_text = str_replace('<DEVICENOTE>',    $h['notes'], $alert_text);

	$alert_text = str_replace('<URL>',           "<a href='" . htmlspecialchars("$httpurl/graph.php?local_graph_id=$local_graph_id") . "'>" . __('Link to Graph in Cacti') . "</a>", $alert_text);

	return $alert_text;
}

function get_thold_warning_text($name, $thold, $h, $currentval, $local_graph_id) {
	global $thold_types;

	$warning_text = read_config_option('thold_warning_text');
	$httpurl      = read_config_option('base_url');

	/* make sure the warning text has been set */
	if (!isset($warning_text) || $warning_text == '') {
		$warning_text = __('<html><body>A warning has been issued that requires your attention.<br><br><b>Device</b>: <DESCRIPTION> (<HOSTNAME>)<br><b>URL</b>: <URL><br><b>Message</b>: <SUBJECT><br><br><GRAPH></body></html>');
	}

	// Do some replacement of variables
	$warning_text = str_replace('<DESCRIPTION>',   $h['description'], $warning_text);
	$warning_text = str_replace('<HOSTNAME>',      $h['hostname'], $warning_text);
	$warning_text = str_replace('<TIME>',          time(), $warning_text);
	$warning_text = str_replace('<GRAPHID>',       $local_graph_id, $warning_text);
	$warning_text = str_replace('<CURRENTVALUE>',  $currentval, $warning_text);
	$warning_text = str_replace('<THRESHOLDNAME>', $thold['name'], $warning_text);
	$warning_text = str_replace('<DSNAME>',        $name, $warning_text);
	$warning_text = str_replace('<THOLDTYPE>',     $thold_types[$thold['thold_type']], $warning_text);
	$warning_text = str_replace('<NOTES>',         $thold['notes'], $warning_text);

	if ($thold['thold_type'] == 0) {
		$warning_text = str_replace('<HI>',        $thold['thold_hi'], $warning_text);
		$warning_text = str_replace('<LOW>',       $thold['thold_low'], $warning_text);
		$warning_text = str_replace('<TRIGGER>',   $thold['thold_warning_fail_trigger'], $warning_text);
		$warning_text = str_replace('<DURATION>',  '', $warning_text);
	}elseif ($thold['thold_type'] == 2) {
		$warning_text = str_replace('<HI>',        $thold['time_warning_hi'], $warning_text);
		$warning_text = str_replace('<LOW>',       $thold['time_warning_low'], $warning_text);
		$warning_text = str_replace('<TRIGGER>',   $thold['time_warning_fail_trigger'], $warning_text);
		$warning_text = str_replace('<DURATION>',  plugin_thold_duration_convert($thold['local_data_id'], $thold['time_warning_fail_length'], 'time'), $warning_text);
	}else{
		$warning_text = str_replace('<HI>',       '', $warning_text);
		$warning_text = str_replace('<LOW>',      '', $warning_text);
		$warning_text = str_replace('<TRIGGER>',  '', $warning_text);
		$warning_text = str_replace('<DURATION>', '', $warning_text);
	}

	$warning_text = str_replace('<DATE_RFC822>',  date(DATE_RFC822), $warning_text);
	$warning_text = str_replace('<DEVICENOTE>',   $h['notes'], $warning_text);

	$warning_text = str_replace('<URL>',          "<a href='" . htmlspecialchars("$httpurl/graph.php?local_graph_id=$local_graph_id") . "'>" . __('Link to Graph in Cacti') . "</a>", $warning_text);

	return $warning_text;
}

function thold_format_number($value, $digits = 2, $baseu = 1024) {
	if ($value == '') {
		return '-';
	}elseif (strlen(round($value, 0)) == strlen($value) && $value < 1E4) {
		return number_format_i18n($value, 0, $baseu);
	}else {
		return number_format_i18n($value, $digits, $baseu);
	}
}

function thold_format_name($template, $local_graph_id, $local_data_id, $data_source_name) {
	$desc = db_fetch_cell_prepared('SELECT name_cache FROM data_template_data WHERE local_data_id = ? LIMIT 1', array($local_data_id));

	if (isset($template['name']) && strpos($template['name'], '|') !== false) {
		$gl = db_fetch_row_prepared("SELECT * FROM graph_local WHERE id = ?", array($local_graph_id));

		if (sizeof($gl)) {
			$name = expand_title($gl['host_id'], $gl['snmp_query_id'], $gl['snmp_index'], $template['name']);
		} else {
			$name = $desc . ' [' . $data_source_name . ']';
		}
	} else {
		$name = $desc . ' [' . $data_source_name . ']';
	}

	return $name;
}

function get_reference_types($rra = 0, $step = 300) {
	global $config, $timearray;

	include_once($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$rra_steps = db_fetch_assoc('SELECT DISTINCT dspr.steps
		FROM data_template_data AS dtd
		JOIN data_source_profiles_rra AS dspr
		ON dtd.data_source_profile_id=dspr.data_source_profile_id
		WHERE dspr.steps>1 ' .  ($rra > 0 ? "AND dtd.local_data_id=$rra":'') . '
		ORDER BY steps');

	$reference_types = array();
	if (sizeof($rra_steps)) {
		foreach($rra_steps as $rra_step) {
			$seconds = $step * $rra_step['steps'];
			if (isset($timearray[$rra_step['steps']])) {
				$reference_types[$seconds] = $timearray[$rra_step['steps']] . ' Average' ;
			}
		}
	}

	return $reference_types;
}

function logger($desc, $breach_up, $threshld, $currentval, $trigger, $triggerct, $urlbreach) {
	$syslog_level = read_config_option('thold_syslog_level');
	$syslog_facility = read_config_option('thold_syslog_facility');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}
	if (!isset($syslog_facility)) {
		$syslog_facility = LOG_DAEMON;
	}

	openlog('CactiTholdLog', LOG_PID | LOG_PERROR, $syslog_facility);

	if (strval($breach_up) == 'ok') {
		syslog($syslog_level, $desc . ' restored to normal with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct . " - ". $urlbreach);
	} else {
		syslog($syslog_level, $desc . ' went ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . $threshld . ' with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct . " - ". $urlbreach);
	}
}

function thold_cdef_get_usable () {
	$cdef_items = db_fetch_assoc("SELECT * FROM cdef_items WHERE value = 'CURRENT_DATA_SOURCE' ORDER BY cdef_id");
	$cdef_usable = array();
	if (sizeof($cdef_items)) {
		foreach ($cdef_items as $cdef_item) {
				$cdef_usable[] =  $cdef_item['cdef_id'];
		}
	}

	return $cdef_usable;
}

function thold_cdef_select_usable_names () {
	$ids   = thold_cdef_get_usable();
	$cdefs = db_fetch_assoc('SELECT id, name FROM cdef');
	$cdef_names[0] = '';
	if (sizeof($cdefs)) {
		foreach ($cdefs as $cdef) {
			if (in_array($cdef['id'], $ids)) {
				$cdef_names[$cdef['id']] =  $cdef['name'];
			}
		}
	}
	return $cdef_names;
}

function thold_build_cdef($cdef, $value, $rra, $ds) {
	$oldvalue   = $value;

	$cdefs      = db_fetch_assoc_prepared('SELECT * 
		FROM cdef_items 
		WHERE cdef_id = ? 
		ORDER BY sequence', array($cdef));

	$cdef_array = array();

	if (sizeof($cdefs)) {
	foreach ($cdefs as $cdef) {
		if ($cdef['type'] == 4) {
			$cdef['type'] = 6;

			switch ($cdef['value']) {
			case 'CURRENT_DATA_SOURCE':
				$cdef['value'] = $oldvalue; // get_current_value($rra, $ds, 0);
				break;
			case 'CURRENT_GRAPH_MAXIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'upper_limit');
				break;
			case 'CURRENT_GRAPH_MINIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'lower_limit');
				break;
			case 'CURRENT_DS_MINIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'rrd_minimum');
				break;
			case 'CURRENT_DS_MAXIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'rrd_maximum');
				break;
			case 'VALUE_OF_HDD_TOTAL':
				$cdef['value'] = get_current_value($rra, 'hdd_total');
				break;
			case 'ALL_DATA_SOURCES_NODUPS': // you can't have DUPs in a single data source, really...
			case 'ALL_DATA_SOURCES_DUPS':
				$cdef['value'] = 0;
				$all_dsns = array();
				$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = $rra");
				if (is_array($all_dsns)) {
					foreach ($all_dsns as $dsn) {
						$cdef['value'] += get_current_value($rra, $dsn['data_source_name']);
					}
				}
				break;
			default:
				print 'CDEF property not implemented yet: ' . $cdef['value'];
				return $oldvalue;
				break;
			}
		} elseif ($cdef['type'] == 6) {
			$regresult = preg_match('/^\|query_([A-Za-z0-9_]+)\|$/', $cdef['value'], $matches);

			if ($regresult > 0) {
				$sql_query = "SELECT host_snmp_cache.field_value
					FROM data_local 
					INNER JOIN host_snmp_cache 
					ON host_snmp_cache.host_id = data_local.host_id
					AND host_snmp_cache.snmp_query_id = data_local.snmp_query_id
					AND host_snmp_cache.snmp_index = data_local.snmp_index
					WHERE data_local.id = $rra AND host_snmp_cache.field_name = '" . $matches[1] . "'";
					
					$cdef['value'] = db_fetch_cell($sql_query);
			}
		}

		$cdef_array[] = $cdef;
	}
	}

	$x = count($cdef_array);

	if ($x == 0) return $oldvalue;

	$stack = array(); // operation stack for RPN
	array_push($stack, $cdef_array[0]); // first one always goes on
	$cursor = 1; // current pointer through RPN operations list

	while($cursor < $x) {
		$type = $cdef_array[$cursor]['type'];
		switch($type) {
		case 6:
			array_push($stack, $cdef_array[$cursor]);

			break;
		case 2:
			// this is a binary operation. pop two values, and then use them.
			$v1 = thold_expression_rpn_pop($stack);
			$v2 = thold_expression_rpn_pop($stack);
			$result = thold_rpn($v2['value'], $v1['value'], $cdef_array[$cursor]['value']);

			// put the result back on the stack.
			array_push($stack, array('type' => 6, 'value' => $result));

			break;
		default:
			cacti_log('Unknown RPN type: ' . $cdef_array[$cursor]['type'], false);;
			return($oldvalue);

			break;
		}

		$cursor++;
	}

	return $stack[0]['value'];
}

function thold_rpn ($x, $y, $z) {
	switch ($z) {
	case 1:
		return $x + $y;

		break;
	case 2:
		return $x - $y;

		break;
	case 3:
		return $x * $y;

		break;
	case 4:
		if ($y == 0) {
			return (-1);
		}
		return $x / $y;

		break;
	case 5:
		return $x % $y;

		break;
	}

	return '';
}

function delete_old_thresholds() {
	$tholds = db_fetch_assoc('SELECT td.id, td.data_template_rrd_id, td.local_data_id 
		FROM thold_data AS td 
		LEFT JOIN data_template_rrd AS dtr 
		ON dtr.id=td.data_template_rrd_id 
		WHERE data_source_name IS NULL');

	if (sizeof($tholds)) {
		foreach ($tholds as $thold_data) {
			db_execute('DELETE FROM thold_data WHERE id=' . $thold_data['id']);
			db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $thold_data['id']);
		}
	}
}

function thold_rrd_last($local_data_id) {
	$last_time_entry = @rrdtool_execute('last ' . trim(get_data_source_path($local_data_id, true)), false, RRDTOOL_OUTPUT_STDOUT);

	return trim($last_time_entry);
}

function get_current_value($local_data_id, $data_template_rrd_id, $cdef = 0) {
	/* get the information to populate into the rrd files */
	if (function_exists("boost_check_correct_enabled") && boost_check_correct_enabled()) {
		boost_process_poller_output(TRUE, $local_data_id);
	}

	$last_time_entry = thold_rrd_last($local_data_id);

	// This should fix and 'did you really mean month 899 errors', this is because your RRD has not polled yet
	if ($last_time_entry == -1) {
		$last_time_entry = time();
	}

	$data_template_data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?', array($local_data_id));

	$step = $data_template_data['rrd_step'];

	// Round down to the nearest 100
	$last_time_entry = (intval($last_time_entry /100) * 100) - $step;
	$last_needed = $last_time_entry + $step;

	$result = rrdtool_function_fetch($local_data_id, trim($last_time_entry), trim($last_needed));

	// Return Blank if the data source is not found (Newly created?)
	if (!isset($result['data_source_names'])) {
		return '';
	}

	$idx = array_search($data_template_rrd_id, $result['data_source_names']);

	// Return Blank if the value was not found (Cache Cleared?)
	if (!isset($result['values'][$idx][0])) {
		return '';
	}

	$value = $result['values'][$idx][0];
	if ($cdef != 0) {
		$value = thold_build_cdef($cdef, $value, $local_data_id, $data_template_rrd_id);
	}

	return round($value, 4);
}

function thold_get_ref_value($local_data_id, $name, $ref_time, $time_range) {
	$result = rrdtool_function_fetch($local_data_id, $ref_time-$time_range, $ref_time-1, $time_range);

	$idx = array_search($name, $result['data_source_names']);

	if (!isset($result['values'][$idx]) || count($result['values'][$idx]) == 0) {
		return false;
	}

	return $result['values'][$idx];
}

/* thold_check_exception_periods
 @to-do: This function should check 'globally' declared exceptions, like
 holidays etc., as well as exceptions bound to the specific $local_data_id. $local_data_id
 should inherit exceptions that are assigned on the higher level (i.e. device).

*/
function thold_check_exception_periods($local_data_id, $ref_time, $ref_range) {
	// TO-DO
	// Check if the reference time falls into global exceptions
	// Check if the current time falls into global exceptions
	// Check if $local_data_id + $data_template_rrd_id have an exception (again both reference time and current time)
	// Check if there are inheritances

	// More on the exception concept:
	// -Exceptions can be one time and recurring
	// -Exceptions can be global and assigned to:
	// 	-templates
	//	-devices
	//	-data sources
	//

	return false;
}

/* thold_check_baseline -
 Should be called after hard limits have been checked and only when they are OK

 The function "goes back in time" $ref_time seconds and retrieves the data
 for $ref_range seconds. Then it finds minimum and maximum values and calculates
 allowed deviations from those values.

 @arg $local_data_id - the data source to check the data
 @arg $data_template_rrd_id - Index of the data_source in the RRD
 @arg $ref_time - Integer value representing reference offset in seconds
 @arg $ref_range - Integer value indicating reference time range in seconds
 @arg $current_value - Current "value" of the data source
 @arg $pct_down - Allowed baseline deviation in % - if set to false will not be considered
 @arg $pct_up - Allowed baseline deviation in % - if set to false will not be considered

 @returns (integer) - integer value that indicates status
   -2 if the exception is active
   -1 if the reference value is not available
   0 if the current value is within the boundaries
   1 if the current value is below the calculated threshold
   2 if the current value is above the calculated threshold
 */
function thold_check_baseline($local_data_id, $name, $current_value, &$thold_data) {
	global $debug;

	$now = time();

	// See if we have a valid cached thold_high and thold_low value
	if ($thold_data['bl_thold_valid'] && $now < $thold_data['bl_thold_valid']) {
		if ($thold_data['thold_hi'] && $current_value > $thold_data['thold_hi']) {
			$failed = 2;
		} elseif ($thold_data['thold_low'] && $current_value < $thold_data['thold_low']) {
			$failed = 1;
		} else {
			$failed= 0;
		}
	} else {
		$midnight =  gmmktime(0,0,0);
		$t0 = $midnight + floor(($now - $midnight) / $thold_data['bl_ref_time_range']) * $thold_data['bl_ref_time_range'];

		$ref_values    = thold_get_ref_value($thold_data['local_data_id'], $name, $t0, $thold_data['bl_ref_time_range']);
		if ($ref_values === false || sizeof($ref_values) == 0) {
			return -1;
		}

		if (sizeof($ref_values) > 1) {
			$ref_value_min = min($ref_values);
			$ref_value_max = max($ref_values);
		}else{
			$ref_value_min = $ref_values[0];
			$ref_value_max = $ref_values[0];
		}

		if ($thold_data['cdef'] != 0) {
			$ref_value_min = thold_build_cdef($thold_data['cdef'], $ref_value_min, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			$ref_value_max = thold_build_cdef($thold_data['cdef'], $ref_value_max, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		}

		$blt_low  = '';
		$blt_high = '';

		if ($thold_data['bl_pct_down'] != '') {
			$blt_low  = $ref_value_min - ($ref_value_min * $thold_data['bl_pct_down'] / 100);
		}

		if ($thold_data['bl_pct_up'] != '') {
			$blt_high = $ref_value_max + ($ref_value_max * $thold_data['bl_pct_up'] / 100);
		}

		// Cache the calculated or empty values
		$thold_data['thold_low']      = $blt_low;
		$thold_data['thold_hi']       = $blt_high;
		$thold_data['bl_thold_valid'] = $t0 + $thold_data['bl_ref_time_range'];

		$failed = 0;

		// Check low boundary
		if ($blt_low != '' && $current_value < $blt_low) {
			$failed = 1;
		}

		// Check up boundary
		if ($failed == 0 && $blt_high != '' && $current_value > $blt_high) {
			$failed = 2;
		}
	}

	if ($debug) {
		echo 'Local Data Id: '     . $local_data_id . ':' . $thold['data_template_rrd_id'] . "\n";
		echo 'Ref. values count: ' . (isset($ref_values) ? count($ref_values):"N/A") . "\n";
		echo 'Ref. value (min): '  . (isset($ref_value_min) ? $ref_value_min:'N/A') . "\n";
		echo 'Ref. value (max): '  . (isset($ref_value_max) ? $ref_value_max:'N/A') . "\n";
		echo 'Cur. value: '        . $current_value . "\n";
		echo 'Low bl thresh: '     . (isset($blt_low) ? $blt_low:'N/A') . "\n";
		echo 'High bl thresh: '    . (isset($blt_high) ? $blt_high:'N/A') . "\n";
		echo 'Check against baseline: ';
		switch($failed) {
			case 0:
			echo 'OK';
			break;

			case 1:
			echo 'FAIL: Below baseline threshold!';
			break;

			case 2:
			echo 'FAIL: Above baseline threshold!';
			break;
		}
		echo "\n";
		echo "------------------\n";
	}

	return $failed;
}

function save_thold() {
	global $banner;

	$host_id              = get_filter_request_var('host_id');
	$local_data_id        = get_filter_request_var('local_data_id');
	$local_graph_id       = get_filter_request_var('local_graph_id');
	$data_template_rrd_id = get_filter_request_var('data_template_rrd_id');

	$template_enabled     = isset_request_var('template_enabled') && get_nfilter_request_var('template_enabled') == 'on' ? 'on' : '';

	if ($template_enabled == 'on') {
		if (!thold_user_auth_threshold ($local_data_id)) {
			$banner = "<span class='textError'>" . __('Permission Denied') . "</span>";

			$_SESSION['thold_message'] = $banner;
			raise_message('thold_message');

			return;
		}

		$data    = db_fetch_row_prepared('SELECT id, thold_template_id 
			FROM thold_data 
			WHERE local_data_id = ?
			AND data_template_rrd_id = ?', array($local_data_id, $data_template_rrd_id));

		thold_template_update_threshold($data['id'], $data['thold_template_id']);

		$banner = "<span class='textInfo'>" . __('Record Updated') . "</span>";

		plugin_thold_log_changes($data['id'], 'modified', array('id' => $data['id'], 'template_enabled' => 'on'));

		$_SESSION['thold_message'] = $banner;
		raise_message('thold_message');

		return get_filter_request_var('id');
	}

	get_filter_request_var('thold_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_fail_trigger');
	get_filter_request_var('thold_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_fail_trigger');
	get_filter_request_var('repeat_alert');
	get_filter_request_var('cdef');
	get_filter_request_var('local_data_id');
	get_filter_request_var('data_template_rrd_id');
	get_filter_request_var('thold_type');
	get_filter_request_var('time_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_fail_trigger');
	get_filter_request_var('time_fail_length');
	get_filter_request_var('time_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_fail_trigger');
	get_filter_request_var('time_warning_fail_length');
	get_filter_request_var('data_type');
	get_filter_request_var('notify_warning');
	get_filter_request_var('notify_alert');
	get_filter_request_var('bl_ref_time_range');
	get_filter_request_var('bl_pct_down', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_pct_up', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_fail_trigger');

	if (isset_request_var('id')) {
		/* Do Some error Checks */
		$banner = "<span class='textError'>";
		if (get_request_var('thold_type') == 0 && 
			get_request_var('thold_hi') == '' && 
			get_request_var('thold_low') == '' && 
			get_request_var('thold_fail_trigger') != 0) {
			$banner .= __('You must specify either &quot;High Alert Threshold&quot; or &quot;Low Alert Threshold&quot; or both!<br>RECORD NOT UPDATED!</span>');

			$_SESSION['thold_message'] = $banner;
			raise_message('thold_message');

			return get_request_var('id');
		}

		if (get_request_var('thold_type') == 0 && 
			get_request_var('thold_warning_hi') == '' && 
			get_request_var('thold_warning_low') == '' && 
			get_request_var('thold_warning_fail_trigger') != 0) {
			$banner .= __('You must specify either &quot;High Warning Threshold&quot; or &quot;Low Warning Threshold&quot; or both!<br>RECORD NOT UPDATED!</span>');

			$_SESSION['thold_message'] = $banner;
			raise_message('thold_message');

			return get_request_var('id');
		}

		if (get_request_var('thold_type') == 0 && 
			get_request_var('thold_hi') != '' && 
			get_request_var('thold_low') != '' && 
			round(get_request_var('thold_low'),4) >= round(get_request_var('thold_hi'), 4)) {
			$banner .= __('Impossible thresholds: &quot;High Threshold&quot; smaller than or equal to &quot;Low Threshold&quot;<br>RECORD NOT UPDATED!</span>');

			$_SESSION['thold_message'] = $banner;
			raise_message('thold_message');

			return get_request_var('id');
		}

		if (get_request_var('thold_type') == 0 && 
			get_request_var('thold_warning_hi') != '' && 
			get_request_var('thold_warning_low') != '' && 
			round(get_request_var('thold_warning_low'),4) >= round(get_request_var('thold_warning_hi'), 4)) {
			$banner .= __('Impossible thresholds: &quot;High Warning Threshold&quot; smaller than or equal to &quot;Low Warning Threshold&quot;<br>RECORD NOT UPDATED!</span>');

			$_SESSION['thold_message'] = $banner;
			raise_message('thold_message');

			return get_request_var('id');
		}

		if (get_request_var('thold_type') == 1) {
			$banner .= __('With baseline thresholds enabled.');

			if (!thold_mandatory_field_ok('bl_ref_time_range', 'Time reference in the past')) {
				$banner .= '</span>';

				$_SESSION['thold_message'] = $banner;
				raise_message('thold_message');

				return get_request_var('id');
			}

			if (isempty_request_var('bl_pct_down') && isempty_request_var('bl_pct_up')) {
				$banner .= __('You must specify either &quot;Baseline Deviation UP&quot; or &quot;Baseline Deviation DOWN&quot; or both!<br>RECORD NOT UPDATED!</span>');

				$_SESSION['thold_message'] = $banner;
				raise_message('thold_message');

				return get_request_var('id');
			}
		}
	}

	$save = array();

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = '0';
		$save['thold_template_id'] = '';
	}

	if (isset_request_var('snmp_event_category')) {
		set_request_var('snmp_event_category', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_category'))));
	}

	if (isset_request_var('snmp_event_severity')) {
		get_filter_request_var('snmp_event_severity');
	}

	if (isset_request_var('snmp_event_warning_severity')) {
		get_filter_request_var('snmp_event_warning_severity');
	}

	if (!isempty_request_var('name')) {
		$name = str_replace(array("\\", '"', "'"), '', get_nfilter_request_var('name'));
	}elseif (isset_request_var('data_template_rrd_id')) {
		$data_source_name  = db_fetch_cell_prepared('SELECT data_source_name FROM data_template_rrd WHERE id = ?', array(get_request_var('data_template_rrd_id')));
		$data_template     = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE id = ?', array(get_request_var('data_template_id')));

		$local_data_id     = get_request_var('local_data_id');
		$local_graph_id    = get_request_var('local_graph_id');

		$name = thold_format_name($data_template, $local_graph_id, $local_data_id, $data_source_name);
	}

	$save['name']                        = trim_round_request_var('name');
	$save['host_id']                     = $host_id;
	$save['data_template_rrd_id']        = get_request_var('data_template_rrd_id');
	$save['local_data_id']               = get_request_var('local_data_id');
	$save['thold_enabled']               = isset_request_var('thold_enabled') ? 'on' : 'off';
	$save['exempt']                      = isset_request_var('exempt') ? 'on' : '';

	$save['thold_hrule_warning']         = get_nfilter_request_var('thold_hrule_warning');
	$save['thold_hrule_alert']           = get_nfilter_request_var('thold_hrule_alert');

	$save['restored_alert']              = isset_request_var('restored_alert') ? 'on' : '';
	$save['thold_type']                  = get_request_var('thold_type');
	$save['template_enabled']            = isset_request_var('template_enabled') ? 'on' : '';

	// High / Low
	$save['thold_hi']                    = trim_round_request_var('thold_hi', 4);
	$save['thold_low']                   = trim_round_request_var('thold_low', 4);
	$save['thold_fail_trigger']          = isempty_request_var('thold_fail_trigger') ? 
			read_config_option('alert_trigger') : get_nfilter_request_var('thold_fail_trigger');

	// Time Based
	$save['time_hi']                     = trim_round_request_var('time_hi', 4);
	$save['time_low']                    = trim_round_request_var('time_low', 4);
	$save['time_fail_trigger']           = isempty_request_var('time_fail_trigger') ? 
			read_config_option('thold_warning_time_fail_trigger') : get_nfilter_request_var('time_fail_trigger');

	$save['time_fail_length']            = isempty_request_var('time_fail_length') ? 
			(read_config_option('thold_warning_time_fail_length') > 0 ? 
			read_config_option('thold_warning_time_fail_length') : 1) : get_nfilter_request_var('time_fail_length');

	// Warning High / Low
	$save['thold_warning_hi']            = trim_round_request_var('thold_warning_hi', 4);
	$save['thold_warning_low']           = trim_round_request_var('thold_warning_low', 4);
	$save['thold_warning_fail_trigger']  = isempty_request_var('thold_warning_fail_trigger') ? 
			read_config_option('alert_trigger') : get_nfilter_request_var('thold_warning_fail_trigger');

	// Warning Time Based
	$save['time_warning_hi']             = trim_round_request_var('time_warning_hi', 4);
	$save['time_warning_low']            = trim_round_request_var('time_warning_low', 4);
	$save['time_warning_fail_trigger']   = isempty_request_var('time_warning_fail_trigger') ? 
		read_config_option('thold_warning_time_fail_trigger') : get_nfilter_request_var('time_warning_fail_trigger');

	$save['time_warning_fail_length']    = isempty_request_var('time_warning_fail_length') ? 
		(read_config_option('thold_warning_time_fail_length') > 0 ? 
		read_config_option('thold_warning_time_fail_length') : 1) : get_nfilter_request_var('time_warning_fail_length');

	// Baseline
	$save['bl_thold_valid']              = '0';
	$save['bl_ref_time_range']           = isempty_request_var('bl_ref_time_range') ? 
		read_config_option('alert_bl_timerange_def') : get_nfilter_request_var('bl_ref_time_range');

	$save['bl_pct_down']                 = trim_round_request_var('bl_pct_down');
	$save['bl_pct_up']                   = trim_round_request_var('bl_pct_up');
	$save['bl_fail_trigger']             = isempty_request_var('bl_fail_trigger') ? 
		read_config_option("alert_bl_trigger") : get_nfilter_request_var('bl_fail_trigger');

	$save['repeat_alert']                = trim_round_request_var('repeat_alert');

	// Notification
	$save['notify_extra']                = trim_round_request_var('notify_extra');
	$save['notify_warning_extra']        = trim_round_request_var('notify_warning_extra');
	$save['notify_warning']              = trim_round_request_var('notify_warning');
	$save['notify_alert']                = trim_round_request_var('notify_alert');

	// Notes
	$save['notes']                       = get_nfilter_request_var('notes');

	// Data Manipulation
	$save['data_type'] = get_nfilter_request_var('data_type');
	if (isset_request_var('percent_ds')) {
		$save['percent_ds'] = get_nfilter_request_var('percent_ds');
	} else {
		$save['percent_ds'] = '';
	}

	$save['cdef']                        = trim_round_request_var('cdef');

	if (isset_request_var('expression')) {
		$save['expression'] = get_nfilter_request_var('expression');
	} else {
		$save['expression'] = '';
	}

	// SNMP Information
	$save['snmp_event_category']         = trim_round_request_var('snmp_event_category');

	$save['snmp_event_severity']         = isset_request_var('snmp_event_severity') ? 
		get_nfilter_request_var('snmp_event_severity') : 4;

	$save['snmp_event_warning_severity'] = isset_request_var('snmp_event_warning_severity') ? 
		get_nfilter_request_var('snmp_event_warning_severity') : 3;

	/* Get the Data Template, Graph Template, and Graph */
	$rrdsql = db_fetch_row('SELECT id, data_template_id 
		FROM data_template_rrd 
		WHERE local_data_id=' . $save['local_data_id'] . ' 
		ORDER BY id');

	$rrdlookup = $rrdsql['id'];

	$grapharr = db_fetch_row("SELECT local_graph_id, graph_template_id 
		FROM graph_templates_item 
		WHERE task_item_id=$rrdlookup 
		AND local_graph_id <> '' 
		LIMIT 1");

	$save['local_graph_id']    = $grapharr['local_graph_id'];
	$save['graph_template_id'] = $grapharr['graph_template_id'];
	$save['data_template_id']  = $rrdsql['data_template_id'];

	if (!thold_user_auth_threshold($save['local_data_id'])) {
		$banner = "<span class='textError'>Permission Denied</span>";

		$_SESSION['thold_message'] = $banner;
		raise_message('thold_message');

		return '';
	}

	$id = sql_save($save , 'thold_data');

	if (isset_request_var('notify_accounts') && is_array(get_nfilter_request_var('notify_accounts'))) {
		thold_save_threshold_contacts ($id, get_nfilter_request_var('notify_accounts'));
	} elseif (!isset_request_var('notify_accounts')) {
		thold_save_threshold_contacts ($id, array());
	}

	if ($id) {
		plugin_thold_log_changes($id, 'modified', $save);

		$thold = db_fetch_row_prepared('SELECT * FROM thold_data WHERE id= ?', array($id));

		if ($thold['thold_type'] == 1) {
			thold_check_threshold($thold);
		}

		set_request_var('id', $id);
	}else{
		set_request_var('id', '0');
	}

	$banner = "<span class='textInfo'>" . __('Record Updated') . "</span>";

	$_SESSION['thold_message'] = $banner;
	raise_message('thold_message');

	return $id;
}

function trim_round_request_var($variable, $digits = 0) {
	$variable = trim(get_nfilter_request_var($variable));

	if ($variable == '0') {
		return '0';
	}elseif (empty($variable)) {
		return '';
	}elseif ($digits > 0) {
		return round($variable, $digits);
	}else{
		return $variable;
	}
}

function thold_save_template_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id = ' . $id);
	// ADD SOME SECURITY!!
	if (!empty($contacts)) {
		foreach ($contacts as $contact) {
			db_execute("INSERT INTO plugin_thold_template_contact (template_id, contact_id) VALUES ($id, $contact)");
		}
	}
}

function thold_save_threshold_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ' . $id);

	// ADD SOME SECURITY!!
	foreach ($contacts as $contact) {
		db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) VALUES ($id, $contact)");
	}
}

function thold_mandatory_field_ok($name, $friendly_name) {
	global $banner;

	if (!isset_request_var($name) || (isset_request_var($name) && 
		(trim(get_nfilter_request_var($name)) == '' || get_nfilter_request_var($name) <= 0))) {
		$banner .= __('&quot;%s&quot; must be set to positive integer value!<br>RECORD NOT UPDATED!</span>', $friendly_name);

		return false;
	}

	return true;
}

// Create tholds for all possible data elements for a host
function autocreate($host_id) {
	$created = 0;
	$message = '';

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', array($host_id));

	$template_list = array_rekey(db_fetch_assoc_prepared('SELECT tt.data_template_id, tt.id
		FROM thold_template AS tt
		INNER JOIN plugin_thold_host_template AS ptht
		ON tt.id=ptht.thold_template_id
		WHERE ptht.host_template_id = ?', array($host_template_id)), 'data_template_id', 'id');

	if (!sizeof($template_list)) {
		$_SESSION['thold_message'] = '<font size=-2>' . __('No Thresholds Templates associated with the Host\'s Template.') . '</font>';
		return 0;
	}

	foreach($template_list as $data_template_id => $thold_template_id) {
		$data_templates[$data_template_id] = $data_template_id;
		$thold_template_ids[$thold_template_id] = $thold_template_id;
	}

	$rralist = db_fetch_assoc_prepared('SELECT id, data_template_id 
		FROM data_local 
		WHERE host_id = ? 
		AND data_template_id IN (' . implode(',', array_keys($data_templates)) . ')', 
		array($host_id));

	foreach ($rralist as $row) {
		$local_data_id      = $row['id'];
		$data_template_id   = $row['data_template_id'];

		if (sizeof($thold_template_ids)) {
			foreach($thold_template_ids as $ttid) {
				$thold_template_id = $ttid;

				$template = db_fetch_row_prepared('SELECT * FROM thold_template WHERE id = ?', array($thold_template_id));

				$existing = db_fetch_row_prepared('SELECT td.id 
					FROM thold_data AS td
					INNER JOIN data_template_rrd AS dtr
					ON dtr.id=td.data_template_rrd_id
					WHERE td.local_data_id = ?
					AND td.data_template_id = ?
					AND td.thold_template_id = ?
					AND td.thold_type = ?
					AND dtr.data_source_name = ?', 
					array($local_data_id, $data_template_id, $thold_template_id, $template['thold_type'], $template['data_source_name']));

				if (!sizeof($existing)) {
					$data_template_rrd_id = db_fetch_cell_prepared('SELECT id 
						FROM data_template_rrd 
						WHERE local_data_id = ?
						AND data_source_name = ?
						ORDER BY id LIMIT 1', array($local_data_id, $template['data_source_name']));

					$graph  = db_fetch_row_prepared('SELECT local_graph_id, graph_template_id 
						FROM graph_templates_item 
						WHERE task_item_id = ? 
						AND local_graph_id > 0 
						LIMIT 1', array($data_template_rrd_id));
	
					if (sizeof($graph)) {
						$data_source_name = $template['data_source_name'];

						$desc = db_fetch_cell_prepared('SELECT name_cache FROM data_template_data WHERE local_data_id = ? LIMIT 1', array($local_data_id));

						$insert                         = array();
						$insert['id']                   = 0;
						$insert['name']                 = $desc . ' [' . $data_source_name . ']';
						$insert['local_data_id']        = $local_data_id;
						$insert['data_template_rrd_id'] = $data_template_rrd_id;
						$insert['local_graph_id']       = $graph['local_graph_id'];
						$insert['graph_template_id']    = $graph['graph_template_id'];
						$insert['data_template_id']     = $data_template_id;

						$insert['thold_hi']             = $template['thold_hi'];
						$insert['thold_low']            = $template['thold_low'];
						$insert['thold_fail_trigger']   = $template['thold_fail_trigger'];

						$insert['time_hi']              = $template['time_hi'];
						$insert['time_low']             = $template['time_low'];
						$insert['time_fail_trigger']    = $template['time_fail_trigger'];
						$insert['time_fail_length']     = $template['time_fail_length'];

						$insert['thold_warning_hi']           = $template['thold_warning_hi'];
						$insert['thold_warning_low']          = $template['thold_warning_low'];
						$insert['thold_warning_fail_trigger'] = $template['thold_warning_fail_trigger'];
						$insert['thold_warning_fail_count']   = $template['thold_warning_fail_count'];

						$insert['time_warning_hi']            = $template['time_warning_hi'];
						$insert['time_warning_low']           = $template['time_warning_low'];
						$insert['time_warning_fail_trigger']  = $template['time_warning_fail_trigger'];
						$insert['time_warning_fail_length']   = $template['time_warning_fail_length'];

						$insert['thold_alert']          = 0;
						$insert['thold_enabled']        = $template['thold_enabled'];
						$insert['thold_type']           = $template['thold_type'];

						$insert['bl_ref_time_range']    = $template['bl_ref_time_range'];
						$insert['bl_pct_down']          = $template['bl_pct_down'];
						$insert['bl_pct_up']            = $template['bl_pct_up'];
						$insert['bl_fail_trigger']      = $template['bl_fail_trigger'];
						$insert['bl_fail_count']        = $template['bl_fail_count'];
						$insert['bl_alert']             = $template['bl_alert'];

						$insert['repeat_alert']         = $template['repeat_alert'];
						$insert['notify_default']       = $template['notify_default'];
						$insert['notify_extra']         = $template['notify_extra'];
						$insert['notify_warning_extra'] = $template['notify_warning_extra'];
						$insert['notify_warning']       = $template['notify_warning'];
						$insert['notify_alert']         = $template['notify_alert'];

						$insert['host_id']              = $host_id;

						$insert['notes']                = $template['notes'];

						$insert['cdef']                 = $template['cdef'];
						$insert['percent_ds']           = $template['percent_ds'];
						$insert['expression']           = $template['expression'];
						$insert['thold_template_id']    = $template['id'];
						$insert['template_enabled']     = 'on';

						$id = sql_save($insert, 'thold_data');

						if ($id) {
							$graph_name = get_graph_title($graph['local_graph_id']);

							thold_template_update_threshold($id, $insert['thold_template_id']);
							plugin_thold_log_changes($id, 'auto_created', $insert['name']);

							$message .= __('Created threshold for the Graph \'<i>%s</i>\' using the Data Source \'<i>%s</i>\'', $graph_name, $data_source_name)  . "<br>";
							$created++;
						}
					}
				}
			}
		}
	}

	$_SESSION['thold_message'] = "<font size=-2>$message</font>";

	return $created;
}

/* Sends a group of graphs to a user */
function thold_mail($to_email, $from_email, $subject, $message, $filename, $headers = '') {
	thold_debug('Preparing to send email');

	$subject = trim($subject);
	$message = str_replace('<SUBJECT>', $subject, $message);

	if ($from_email == '') {
		$from_email = read_config_option('thold_from_email');
		$from_name  = read_config_option('thold_from_name');

		if ($from_email == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from_email = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from_email = 'Cacti@localhost';
			}
		}

		if ($from_name == '') {
			$from_name = 'Cacti'; 
		}
	}

	if ($to_email == '') {
		return __('Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert Email</b> setting.');
	}

	$attachments = array();

	if (is_array($filename) && sizeof($filename) && strstr($message, '<GRAPH>') !== 0) {
		if (isset($filename['local_data_id'])) {
			$tmp      = array();
			$tmp[]    = $filename;
			$filename = $tmp;
		}

		foreach($filename as $val) {
			$graph_data_array = array(
				'graph_start'   => time()-86400,
				'graph_end'     => time(),
				'image_format'  => 'png',
				'graph_theme'   => 'modern',
				'output_flag'   => RRDTOOL_OUTPUT_STDOUT,
				'disable_cache' => true
			);

			$attachments[] = array(
				'attachment'     => rrdtool_function_graph($val['local_graph_id'], '', $graph_data_array, ''),
				'filename'       => 'graph_' . $val['local_graph_id'] . '.png',
				'mime_type'      => 'image/png',
				'local_graph_id' => $val['local_graph_id'],
				'local_data_id'  => $val['local_data_id'],
				'inline'         => 'inline'
			);
		}
	}

	$text = array('text' => '', 'html' => '');
	if ($filename == '') {
		$text['html'] = $message . '<br>';

		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

    $version = db_fetch_cell("SELECT version 
		FROM plugin_config 
		WHERE name='thold'");

    $headers['X-Mailer']   = 'Cacti-Thold-v' . $version;
    $headers['User-Agent'] = 'Cacti-Thold-v' . $version;

	if (read_config_option('thold_email_prio') == 'on') {
		$headers['X-Priority'] = '1';
	}

	thold_debug("Sending email to '" . trim($to_email,', ') . "'");

	$error = mailer(
		array($from_email, $from_name),
		$to_email,
		'',
		'',
		'',
		$subject,
		$text['html'],
		$text['text'],
		$attachments,
		$headers
    );

	if (strlen($error)) {
		cacti_log('ERROR: Sending Email Failed.  Error was ' . $error, true, 'THOLD');

		return $error;
	}

	return '';
}

function thold_template_update_threshold ($id, $template) {
	db_execute_prepared("UPDATE thold_data, thold_template
		SET
		thold_data.template_enabled = 'on',
		thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_warning_hi = thold_template.thold_warning_hi,
		thold_data.thold_warning_low = thold_template.thold_warning_low,
		thold_data.thold_warning_fail_trigger = thold_template.thold_warning_fail_trigger,
		thold_data.time_warning_hi = thold_template.time_warning_hi,
		thold_data.time_warning_low = thold_template.time_warning_low,
		thold_data.time_warning_fail_trigger = thold_template.time_warning_fail_trigger,
		thold_data.time_warning_fail_length = thold_template.time_warning_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.bl_thold_valid = 0,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.notify_warning_extra = thold_template.notify_warning_extra,
		thold_data.notify_warning = thold_template.notify_warning,
		thold_data.notify_alert = thold_template.notify_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.expression = thold_template.expression,
		thold_data.exempt = thold_template.exempt,
		thold_data.thold_hrule_alert = thold_template.thold_hrule_alert,
		thold_data.thold_hrule_warning = thold_template.thold_hrule_warning,
		thold_data.data_template_id = thold_template.data_template_id,
		thold_data.restored_alert = thold_template.restored_alert,
		thold_data.snmp_event_category = thold_template.snmp_event_category,
		thold_data.snmp_event_severity = thold_template.snmp_event_severity,
		thold_data.snmp_event_warning_severity = thold_template.snmp_event_warning_severity
		WHERE thold_data.id = ? AND thold_template.id = ?", array($id, $template));

	db_execute_prepared('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ?', array($id));

	db_execute_prepared('INSERT INTO plugin_thold_threshold_contact 
		(thold_id, contact_id) 
		SELECT ?, contact_id 
		FROM plugin_thold_template_contact 
		WHERE template_id = ?', array($id, $template));
}

function thold_template_update_thresholds($id) {
	db_execute_prepared("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_warning_hi = thold_template.thold_warning_hi,
		thold_data.thold_warning_low = thold_template.thold_warning_low,
		thold_data.thold_warning_fail_trigger = thold_template.thold_warning_fail_trigger,
		thold_data.time_warning_hi = thold_template.time_warning_hi,
		thold_data.time_warning_low = thold_template.time_warning_low,
		thold_data.time_warning_fail_trigger = thold_template.time_warning_fail_trigger,
		thold_data.time_warning_fail_length = thold_template.time_warning_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.bl_thold_valid = 0,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.notify_warning_extra = thold_template.notify_warning_extra,
		thold_data.notify_warning = thold_template.notify_warning,
		thold_data.notify_alert = thold_template.notify_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.expression = thold_template.expression,
		thold_data.exempt = thold_template.exempt,
		thold_data.thold_hrule_alert = thold_template.thold_hrule_alert,
		thold_data.thold_hrule_warning = thold_template.thold_hrule_warning,
		thold_data.data_template_id = thold_template.data_template_id,
		thold_data.restored_alert = thold_template.restored_alert,
		thold_data.snmp_event_category = thold_template.snmp_event_category,
		thold_data.snmp_event_severity = thold_template.snmp_event_severity,
		thold_data.snmp_event_warning_severity = thold_template.snmp_event_warning_severity
		WHERE thold_data.thold_template_id = ? AND thold_data.template_enabled='on' AND thold_template.id = ?", array($id, $id));

	$rows = db_fetch_assoc_prepared("SELECT id, thold_template_id 
		FROM thold_data 
		WHERE thold_data.thold_template_id = ?
		AND thold_data.template_enabled='on'", array($id));

	foreach ($rows as $row) {
		db_execute_prepared('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ?', array($row['id']));

		db_execute_prepared('INSERT INTO plugin_thold_threshold_contact 
			(thold_id, contact_id) 
			SELECT ?, contact_id 
			FROM plugin_thold_template_contact 
			WHERE template_id = ?', array($row['id'], $row['thold_template_id']));
	}
}

function thold_cacti_log($string) {
	global $config;

	$environ = 'THOLD';
	/* fill in the current date for printing in the log */
	$date = date('m/d/Y h:i:s A');

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = read_config_option('path_cactilog');

	/* format the message */
	$message = "$date - " . $environ . ': ' . $string . "\n";

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option('log_verbosity') != POLLER_VERBOSITY_NONE)) {
		if ($logfile == '') {
			$logfile = $config['base_path'] . '/log/cacti.log';
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, 'a');

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$string = strip_tags($string);
		$log_type = '';
		if (substr_count($string,'ERROR:'))
			$log_type = 'err';
		else if (substr_count($string,'WARNING:'))
			$log_type = 'warn';
		else if (substr_count($string,'STATS:'))
			$log_type = 'stat';
		else if (substr_count($string,'NOTICE:'))
			$log_type = 'note';

		if (strlen($log_type)) {
			if ($config['cacti_server_os'] == 'win32')
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_USER);
			else
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_SYSLOG);

			if (($log_type == 'err') && (read_config_option('log_perror'))) {
				syslog(LOG_CRIT, $environ . ': ' . $string);
			}

			if (($log_type == 'warn') && (read_config_option('log_pwarn'))) {
				syslog(LOG_WARNING, $environ . ': ' . $string);
			}

			if ((($log_type == 'stat') || ($log_type == 'note')) && (read_config_option('log_pstats'))) {
				syslog(LOG_INFO, $environ . ': ' . $string);
			}

			closelog();
		}
	}
}

function thold_threshold_enable($id) {
	db_execute_prepared("UPDATE thold_data 
		SET thold_enabled='on', 
		thold_fail_count=0, 
		thold_warning_fail_count=0, 
		bl_fail_count=0, 
		thold_alert=0, 
		bl_alert=0 
		WHERE id = ?", array($id));
}

function thold_threshold_disable($id) {
	db_execute_prepared("UPDATE thold_data 
		SET thold_enabled='off', 
		thold_fail_count=0, 
		thold_warning_fail_count=0, 
		bl_fail_count=0, 
		thold_alert=0, 
		bl_alert=0 
		WHERE id = ?", array($id));
}

function get_thold_warning_emails($thold) {
	$warning_emails = '';
	if (read_config_option('thold_disable_legacy') != 'on') {
		$warning_emails = $thold['notify_warning_extra'];
	}

	$warning_emails .= (strlen($warning_emails) ? ',':'') . get_thold_notification_emails($thold['notify_warning']);

	return $warning_emails;
}

function get_thold_alert_emails($thold) {
	$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data
		FROM plugin_thold_contacts, plugin_thold_threshold_contact
		WHERE plugin_thold_contacts.id=plugin_thold_threshold_contact.contact_id
		AND plugin_thold_threshold_contact.thold_id = ' . $thold['id']);

	$alert_emails = '';
	if (read_config_option('thold_disable_legacy') != 'on') {
		$alert_emails = array();
		if (count($rows)) {
			foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
			}
		}

		$alert_emails = implode(',', $alert_emails);
		if ($alert_emails != '') {
			$alert_emails .= ',' . $thold['notify_extra'];
		} else {
			$alert_emails = $thold['notify_extra'];
		}
	}

	$alert_emails .= (strlen($alert_emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert']);

	return $alert_emails;
}

function get_thold_notification_emails($id) {
	if (!empty($id)) {
		return trim(db_fetch_cell_prepared('SELECT emails FROM plugin_notification_lists WHERE id = ?', array($id)));
	} else {
		return '';
	}
}

/* get_hash_thold_template - returns the current unique hash for a thold_template
   @arg $id - (int) the ID of the thold template to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_thold_template($id) {
    $hash = db_fetch_cell_prepared('SELECT hash FROM thold_template WHERE id = ?', array($id));

    if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
        return $hash;
    } else {
        return generate_hash();
    }
}

function ia2xml($array) {
	$xml = '';
	if (sizeof($array)) {
	foreach ($array as $key=>$value) {
		if (is_array($value)) {
			$xml .= "\t<$key>" . ia2xml($value) . "</$key>\n";
		} else {
			$xml .= "\t<$key>" . htmlspecialchars($value) . "</$key>\n";
		}
	}
	}
	return $xml;
}

function array2xml($array, $tag = 'template') {
	static $index = 1;

	$xml = "<$tag$index>\n" . ia2xml($array) . "</$tag$index>\n";

	$index++;

	return $xml;
}

function thold_snmptrap($varbinds, $severity = SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite = false){
	if (function_exists('snmpagent_notification')) {
		if(isset($varbinds['eventDescription']) && isset($varbinds['eventDeviceIp'])) {
			$varbinds['eventDescription'] = str_replace('<HOSTIP>', $varbinds['eventDeviceIp'], $varbinds['eventDescription']);
		}
		snmpagent_notification('tholdNotify', 'CACTI-THOLD-MIB', $varbinds, $severity, $overwrite);
	}else {
		cacti_log("ERROR: THOLD was unable to generate SNMP notifications. Cacti SNMPAgent plugin is current missing or inactive.");
	}
}
