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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/includes/database.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

thold_initialize_rusage();

plugin_thold_upgrade();

delete_old_thresholds();

set_default_action('thold');

switch(get_request_var('action')) {
	case 'ajax_hosts':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM thold_data)');

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false, false, 'h.id IN (SELECT host_id FROM thold_data)');

		break;
	case 'thold':
		general_header();
		thold_tabs();
		tholds();
		bottom_footer();

		break;
	case 'disable':
		thold_threshold_disable(get_filter_request_var('id'));

		header('Location: thold_graph.php');

		exit;
	case 'enable':
		thold_threshold_enable(get_filter_request_var('id'));

		header('Location: thold_graph.php');

		exit;
	case 'hoststat':
		general_header();
		thold_tabs();
		hosts();
		bottom_footer();

		break;
	default:
		general_header();
		thold_tabs();
		thold_show_log();
		bottom_footer();

		break;
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION['sess_nav_level_cache'] = '';

function form_thold_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='thold' action='thold_graph.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='data_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('data_template_id') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='0'<?php if (get_request_var('data_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$data_templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name 
								FROM thold_data 
								LEFT JOIN data_template ON thold_data.data_template_id=data_template.id ' .
								(get_request_var('host_id') > 0 ? 'WHERE thold_data.host_id=' . get_request_var('host_id'):'') .
								' ORDER by data_template.name');

							if (sizeof($data_templates)) {
								foreach ($data_templates as $data_template) {
									print "<option value='" . $data_template['id'] . "'"; if (get_request_var('data_template_id') == $data_template['id']) { print ' selected'; } print '>' . $data_template['name'] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Breached');?></option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>><?php print __('Triggered');?></option>
							<option value='2'<?php if (get_request_var('status') == '2') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Disabled');?></option>
						</select>
					</td>
					<td>
						<?php print __('Thresholds');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input type='submit' value='<?php print __('Go');?>'>
					</td>
					<td>
						<input id='clear' name='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='tab' value='thold'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold&status=' + $('#status').val();
			strURL += '&data_template_id=' + $('#data_template_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#thold').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	
		</script>
		</td>
	</tr>
	<?php
}

function tholds() {
	global $config, $device_actions, $item_rows, $thold_classes, $thold_states;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'data_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_thold');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Threshold Status'), '100%', '', '3', 'center', '');
	form_thold_filter();
	html_end_box();

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('lastread', 'lastread/1', $sql_order);
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$sql_where = '';

	/* status filter */
	if (get_request_var('status') == '-1') {
		/* return all rows */
	} else {
		if (get_request_var('status') == '0') { $sql_where = "(td.thold_enabled='off'"; } /*disabled*/
		if (get_request_var('status') == '2') { $sql_where = "(td.thold_enabled='on'"; } /* enabled */
		if (get_request_var('status') == '1') { $sql_where = "((td.thold_alert!=0 OR td.bl_alert>0)"; } /* breached */
		if (get_request_var('status') == '3') { $sql_where = "(((td.thold_alert!=0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert>0 AND td.bl_fail_count >= td.bl_fail_trigger))"; } /* status */
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . " td.name LIKE '%" . get_request_var('filter') . "%'";
	}

	/* data template id filter */
	if (get_request_var('data_template_id') != '-1') {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . ' td.data_template_id=' . get_request_var('data_template_id');
	}

	/* host id filter */
	if (get_request_var('host_id') != '-1') {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . ' td.host_id=' . get_request_var('host_id');
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

	$tholds = get_allowed_thresholds($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=thold', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, 'Thresholds', 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'nosort'        => array('display' => __('Actions'),     'sort' => '',      'align' => 'left'),
		'name'          => array('display' => __('Name'),        'sort' => 'ASC',   'align' => 'left'),
		'id'            => array('display' => __('ID'),          'sort' => 'ASC',   'align' => 'right'),
		'thold_type'    => array('display' => __('Type'),        'sort' => 'ASC',   'align' => 'left'),
		'lastread'      => array('display' => __('Current'),     'sort' => 'ASC',   'align' => 'right'),
		'nosort4'       => array('display' => __('Warn Hi/Lo'),  'sort' => 'ASC',   'align' => 'right'),
		'nosort5'       => array('display' => __('Alert Hi/Lo'), 'sort' => 'ASC',   'align' => 'right'),
		'nosort6'       => array('display' => __('BL Hi/Lo'),    'sort' => 'ASC',   'align' => 'right'),
		'nosort2'       => array('display' => __('Trigger'),     'sort' => 'ASC',   'align' => 'right'),
		'nosort3'       => array('display' => __('Duration'),    'sort' => 'ASC',   'align' => 'right'),
		'repeat_alert'  => array('display' => __('Repeat'),      'sort' => 'ASC',   'align' => 'right'),
		'thold_alert'   => array('display' => __('Triggered'),   'sort' => 'ASC',   'align' => 'right'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=thold');

	$step = read_config_option('poller_interval');

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$c=0;
	$i=0;

	if (sizeof($tholds)) {
		foreach ($tholds as $row) {
			$c++;
			$alertstat = 'No';
			$bgcolor   = 'green';
			if ($row['thold_type'] == 0) {
				if ($row['thold_alert'] != 0) {
					$alertstat = __('Yes');
					if ( $row['thold_fail_count'] >= $row['thold_fail_trigger'] ) {
						$bgcolor = 'red';
					} elseif ( $row['thold_warning_fail_count'] >= $row['thold_warning_fail_trigger'] ) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} elseif ($row['thold_type'] == 2) {
				if ($row['thold_alert'] != 0) {
					$alertstat='Yes';
					if ($row['thold_fail_count'] >= $row['time_fail_trigger']) {
						$bgcolor = 'red';
					} elseif ($row['thold_warning_fail_count'] >= $row['time_warning_fail_trigger']) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} else {
				if ($row['bl_alert'] == 1) {
					$alertstat = __('Baseline-LOW');
					$bgcolor   = ($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat = __('Baseline-HIGH');
					$bgcolor   = ($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				}
			};

			$baseu = db_fetch_cell_prepared('SELECT base_value 
				FROM graph_templates_graph 
				WHERE local_graph_id = ?', 
				array($row['local_graph_id']));

			if ($row['thold_enabled'] == 'off') {
				print "<tr class='selectable " . $thold_states['grey']['class'] . "' id='line" . $row['id'] . "'>\n";
			}else{
				print "<tr class='selectable " . $thold_states[$bgcolor]['class'] . "' id='line" . $row['id'] . "'>\n";
			}

			print "<td width='1%' style='white-space:nowrap;'>";

			if (api_user_realm_auth('thold.php')) {
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?action=edit&id=' . $row['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="' . __('Edit Threshold') . '"></a>';
			}

			if ($row['thold_enabled'] == 'on') {
				print '<a class="hyperLink" href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=disable&id=' . $row['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/disable_thold.png" border="0" alt="" title="' . __('Disable Threshold') . '"></a>';
			}else{
				print '<a class="hyperLink" href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=enable&id=' . $row['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/enable_thold.png" border="0" alt="" title="' . __('Enable Threshold') . '"></a>';
			}

			print "<a href='". htmlspecialchars($config['url_path'] . 'graph.php?local_graph_id=' . $row['local_graph_id'] . '&rra_id=all') . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='" . __('View Graph') . "'></a>";

			print "<a class='hyperLink' href='". htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=log&threshold_id=' . $row['id'] . '&status=-1') . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_log.gif' border='0' alt='' title='" . __('View Threshold History') . "'></a>";

			print '</td>';
			print "<td class='left nowrap'>" . ($row['name'] != '' ? filter_value($row['name'], get_request_var('filter')) : __('No name set')) . '</td>';
			//print "<td class='left nowrap'>" . ($row['name'] != '' ? $row['name'] : 'No name set') . '</td>';
			print "<td class='right'>" . $row['id'] . '</td>';
			print "<td class='left nowrap'>" . $thold_types[$row['thold_type']] . '</td>';
			print "<td class='right'>" . thold_format_number($row['lastread'], 2, $baseu) . '</td>';
			print "<td class='right nowrap'>" . ($row['thold_type'] == 1 ? __('N/A'):($row['thold_type'] == 2 ? thold_format_number($row['time_warning_hi'], 2, $baseu) . '/' . thold_format_number($row['time_warning_low'], 2, $baseu) : thold_format_number($row['thold_warning_hi'], 2, $baseu) . '/' . thold_format_number($row['thold_warning_low'], 2, $baseu))) . '</td>';
			print "<td class='right'>" . ($row['thold_type'] == 1 ? __('N/A'):($row['thold_type'] == 2 ? thold_format_number($row['time_hi'], 2, $baseu) . '/' . thold_format_number($row['time_low'], 2, $baseu) : thold_format_number($row['thold_hi'], 2, $baseu) . '/' . thold_format_number($row['thold_low'], 2, $baseu))) . '</td>';
			print "<td class='right'>" . ($row['thold_type'] == 1 ? $row['bl_pct_up'] . (strlen($row['bl_pct_up']) ? '%':'-') . '/' . $row['bl_pct_down'] . (strlen($row['bl_pct_down']) ? '%':'-'): __('N/A')) . '</td>';

			switch($row['thold_type']) {
				case 0:
					print "<td class='right nowrap'><i>" . plugin_thold_duration_convert($row['local_data_id'], $row['thold_fail_trigger'], 'alert') . '</i></td>';
					print "<td class='right'>" . __('N/A') . "</td>";
					break;
				case 1:
					print "<td class='right nowrap'><i>" . plugin_thold_duration_convert($row['local_data_id'], $row['bl_fail_trigger'], 'alert') . '</i></td>';
					print "<td class='right nowrap'>" . $timearray[$row['bl_ref_time_range']/300]. '</td>';;
					break;
				case 2:
					print "<td class='right nowrap'><i>" . $row['time_fail_trigger'] . ' Triggers</i></td>';
					print "<td class='right nowrap'>" . plugin_thold_duration_convert($row['local_data_id'], $row['time_fail_length'], 'time') . '</td>';;
					break;
				default:
					print "<td class='right'>" . __('N/A') . "</td>";
					print "<td class='right'>" . __('N/A') . "</td>";
			}

			print "<td class='right nowrap'>" . ($row['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($row['local_data_id'], $row['repeat_alert'], 'repeat')) . '</td>';
			print "<td class='right'>" . $alertstat . '</td>';

			form_end_row();
		}
	} else {
		print '<tr class="even"><td class="center" colspan="13">' . __('No Thresholds'). '</td></tr>';
	}

	html_end_box(false);

	if (sizeof($tholds)) {
		print $nav;
	}

	thold_legend();

	//thold_display_rusage();
}


/* form_host_status_row_color - returns a color to use based upon the host's current status*/
function form_host_status_row_color($status, $disabled) {
	global $thold_host_states;

	// Determine the color to use
	if ($disabled) {
		$class = $thold_host_states['disabled']['class'];
	} else {
		$class = $thold_host_states[$status]['class'];
	}

	print "<tr class='$class'>\n";

	return $class;
}

function get_uncolored_device_status($disabled, $status) {
	if ($disabled) {
		return __('Disabled');
	}else{
		switch ($status) {
			case HOST_DOWN:
				return __('Down');
				break;
			case HOST_RECOVERING:
				return __('Recovering');
				break;
			case HOST_UP:
				return __('Up');
				break;
			case HOST_ERROR:
				return __('Error');
				break;
			default:
				return __('Unknown');
				break;
		}
	}
}

function hosts() {
	global $config, $device_actions, $item_rows;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-4'
			)
	);

	validate_store_request_vars($filters, 'sess_thold_hstatus');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Status'), '100%', '', '3', 'center', '');
	form_host_filter();
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (get_request_var('filter') != '') {
		$sql_where = "((h.hostname LIKE '%" . get_request_var('filter') . "%' OR h.description LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_status') == '-2') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.disabled='on'";
	}elseif (get_request_var('host_status') == '-3') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.disabled=''";
	}elseif (get_request_var('host_status') == '-4') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.status!='3' OR h.disabled='on')";
	}elseif (get_request_var('host_status') == '-5') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.availability_method=0)";
	}elseif (get_request_var('host_status') == '3') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.availability_method!=0 AND h.status=3 AND h.disabled='')";
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.status=" . get_request_var('host_status') . " AND h.disabled = '')";
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.host_template_id=0'";
	}elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.host_template_id=" . get_request_var('host_template_id');
	}

	$sql_where .= (strlen($sql_where) ? ')':'');

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	$hosts = get_allowed_devices($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=hoststat', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'nosort'                 => array('display' => __('Actions'),      'align' => 'left',   'sort' => '',     'tip' => __('Hover over icons for help')),
		'description'            => array('display' => __('Description'),  'align' => 'left',   'sort' => 'ASC',  'tip' => __('A description for the Device')),
		'id'                     => array('display' => __('ID'),           'align' => 'right',  'sort' => 'ASC',  'tip' => __('A Cacti unique identifier for the Device')),
		'nosort1'                => array('display' => __('Graphs'),       'align' => 'right',  'sort' => 'ASC',  'tip' => __('The number of Graphs for this Device')),
		'nosort2'                => array('display' => __('Data Sources'), 'align' => 'right',  'sort' => 'ASC',  'tip' => __('The number of Data Sources for this Device')),
		'status'                 => array('display' => __('Status'),       'align' => 'center', 'sort' => 'ASC',  'tip' => __('The status for this Device as of the last time it was polled')),
		'nosort3'                => array('display' => __('In State'),     'align' => 'right',  'sort' => 'ASC',  'tip' => __('The last time Cacti found an issue with this Device.  It can be higher than the Uptime for the Device, if it was rebooted between Cacti polling cycles')),
		'snmp_sysUpTimeInstance' => array('display' => __('Uptime'),       'align' => 'right',  'sort' => 'ASC',  'tip' => __('The official uptime of the Device as reported by SNMP')),
		'hostname'               => array('display' => __('Hostname'),     'align' => 'right',  'sort' => 'ASC',  'tip' => __('The official hostname for this Device')),
		'cur_time'               => array('display' => __('Current (ms)'), 'align' => 'right',  'sort' => 'DESC', 'tip' => __('The current response time for the Cacti Availability check')),
		'avg_time'               => array('display' => __('Average (ms)'), 'align' => 'right',  'sort' => 'DESC', 'tip' => __('The average response time for the Cacti Availability check')),
		'availability'           => array('display' => __('Availability'), 'align' => 'right',  'sort' => 'ASC',  'tip' => __('The overall Availability of this Device since the last counter reset in Cacti'))
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=hoststat');

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if ($host['disabled'] == '' && 
				($host['status'] == HOST_RECOVERING || $host['status'] == HOST_UP) &&
				($host['availability_method'] != AVAIL_NONE && $host['availability_method'] != AVAIL_PING)) { 
				$snmp_uptime = $host['snmp_sysUpTimeInstance'];
				$days      = intval($snmp_uptime / (60*60*24*100));
				$remainder = $snmp_uptime % (60*60*24*100);
				$hours     = intval($remainder / (60*60*100));
				$remainder = $remainder % (60*60*100);
				$minutes   = intval($remainder / (60*100));
				$uptime    = $days . 'd:' . substr('00' . $hours, -2) . 'h:' . substr('00' . $minutes, -2) . 'm';
			}else{
				$uptime    = __('N/A');
			}

			if (isset($host_graphs[$host['id']])) {
				$graphs = $host_graphs[$host['id']];
			}else{
				$graphs = 0;
			}

			if (isset($host_data_sources[$host['id']])) {
				$ds = $host_data_sources[$host['id']];
			}else{
				$ds = 0;
			}

			if ($host['availability_method'] != 0) {
				form_host_status_row_color($host['status'], $host['disabled']); 
				print "<td width='1%' class='nowrap'>";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="' . __('Edit Device') . '"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview&graph_template_id=0&filter=&host_id=' . $host['id']) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='" . __('View Graphs') . "'></a>";
				print '</td>';
				?>
				<td style='text-align:left'>
					<?php print filter_value($host['description'], get_request_var('filter'));?>
				</td>
				<td style='text-align:right'><?php print round(($host['id']), 2);?></td>
				<td style='text-align:right'><i><?php print number_format_i18n($graphs);?></i></td>
				<td style='text-align:right'><i><?php print number_format_i18n($ds);?></i></td>
				<td style='text-align:center'><?php print get_uncolored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']);?></td>
				<td style='text-align:right'><?php print get_timeinstate($host);?></td>
				<td style='text-align:right'><?php print $uptime;?></td>
				<td style='text-align:right'><?php print filter_value($host['hostname'], get_request_var('filter'));?></td>
				<td style='text-align:right'><?php print round(($host['cur_time']), 2);?></td>
				<td style='text-align:right'><?php print round(($host['avg_time']), 2);?></td>
				<td style='text-align:right'><?php print round($host['availability'], 2);?> %</td>
				<?php
			}else{
				print "<tr class='deviceNotMonFull'>\n";
				print "<td width='1%' class='nowrap'>\n";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host["id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="' . __('Edit Device') . '"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . "graph_view.php?action=preview&graph_template_id=0&filter=&host_id=" . $host["id"]) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='" . __('View Graphs') . "'></a>";
				print "</td>";
				?>
				<td style='text-align:left'>
					<?php print filter_value($host['description'], get_request_var('filter'));?>
				</td>
				<td style='text-align:right'><?php print $host['id'];?></td>
				<td style='text-align:right'><i><?php print number_format_i18n($graphs);?></i></td>
				<td style='text-align:right'><i><?php print number_format_i18n($ds);?></i></td>
				<td style='text-align:center'><?php print 'Not Monitored';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print $uptime;?></td>
				<td style='text-align:right'><?php print filter_value($host['hostname'], get_request_var('filter'));?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<?php
			}

			form_end_row();
		}
	}else{
		print '<tr><td class="center" colspan="12">' . __('No Devices') . '</td></tr>';
	}

	html_end_box(false);

	if (sizeof($hosts)) {
		print $nav;
	}

	host_legend();

	//thold_display_rusage();
}

function form_host_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_devices' action='thold_graph.php?action=hoststat'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Type');?>
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$host_templates = db_fetch_assoc('select id,name from host_template order by name');

							if (sizeof($host_templates)) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='host_status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>><?php print __('Disabled');?></option>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up');?></option>
							<option value='-5'<?php if (get_request_var('host_status') == '-5') {?> selected<?php }?>><?php print __('Not Monitored');?></option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up');?></option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down');?></option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering');?></option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown');?></option>
						</select>
					</td>
					<td>
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='<?php print __('Go');?>' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='tab' value='hoststat'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=hoststat&host_status=' + $('#host_status').val();
			strURL += '&host_template_id=' + $('#host_template_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=hoststat&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#form_devices').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}

function thold_show_log() {
	global $config, $item_rows, $thold_log_states, $thold_status, $thold_types, $thold_log_retention;

	$step = read_config_option('poller_interval');

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'time',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'threshold_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_thold_log');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$days = read_config_option('thold_log_storage');

	if (isset($thold_log_retention[$days])) {
		$days = $thold_log_retention[$days];
	}else{
		$days = __('%d Days', $days);
	}

	html_start_box(__('Threshold Log for [ %s ]', $days), '100%', '', '3', 'center', '');
	form_thold_log_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' h.id IS NULL';
	}elseif (!isempty_request_var('host_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' tl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('threshold_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('threshold_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' td.id IS NULL';
	}elseif (!isempty_request_var('threshold_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' tl.threshold_id=' . get_request_var('threshold_id');
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' tl.status=' . get_request_var('status');
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . " tl.description LIKE '%" . get_request_var('filter') . "%'";
	}

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$logs = get_allowed_threshold_logs($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=log', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Log Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'hdescription'    => array('display' => __('Device'),            'sort' => 'ASC', 'align' => 'left'),
		'time'            => array('display' => __('Time'),              'sort' => 'ASC', 'align' => 'left'),
		'type'            => array('display' => __('Type'),              'sort' => 'DESC', 'align' => 'left'),
		'description'     => array('display' => __('Event Description'), 'sort' => 'ASC', 'align' => 'left'),
		'threshold_value' => array('display' => __('Alert Value'),       'sort' => 'ASC', 'align' => 'right'),
		'current'         => array('display' => __('Measured Value'),    'sort' => 'ASC', 'align' => 'right')
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=log');

	$i = 0;
	if (sizeof($logs)) {
		foreach ($logs as $l) {
			$baseu = db_fetch_cell_prepared('SELECT base_value 
				FROM graph_templates_graph 
				WHERE local_graph_id = ?', 
				array($l['local_graph_id']));

			?>
			<tr class='<?php print $thold_log_states[$l['status']]['class'];?>'>
			<td class='left nowrap'><?php print $l['hdescription'];?></td>
			<td class='left nowrap'><?php print date('Y-m-d H:i:s', $l['time']);?></td>
			<td class='left nowrap'><?php print $thold_types[$l['type']];?></td>
			<td class='left nowrap'><?php print (strlen($l['description']) ? $l['description']:__('Restoral Event'));?></td>
			<td class='right'><?php print ($l['threshold_value'] != '' ? thold_format_number($l['threshold_value'], 2, $baseu):__('N/A'));?></td>
			<td class='right'><?php print ($l['current'] != '' ? thold_format_number($l['current'], 2, $baseu):__('N/A'));?></td>
			<?php

			form_end_row();
		}
	}else{
		print '<tr><td class="center" colspan="8">' . __('No Threshold Logs Found'). '</td></tr>';
	}

	html_end_box(false);

	if (sizeof($logs)) {
		print $nav;
	}

	log_legend();
}

function form_thold_log_filter() {
	global $item_rows, $thold_log_states, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_log' action='thold_graph.php?action=log'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Threshold');?>
					</td>
					<td>
						<select id='threshold_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('threshold_id') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<?php
							$tholds = db_fetch_assoc('SELECT DISTINCT thold_data.id, thold_data.name
								FROM thold_data
								INNER JOIN plugin_thold_log ON thold_data.id=plugin_thold_log.threshold_id ' .
								(get_request_var('host_id') > 0 ? 'WHERE thold_data.host_id=' . get_request_var('host_id'):'') .
								' ORDER by thold_data.name');

							if (sizeof($tholds)) {
								foreach ($tholds as $thold) {
									print "<option value='" . $thold['id'] . "'"; if (get_request_var('threshold_id') == $thold['id']) { print ' selected'; } print '>' . $thold['name'] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<?php
							if (sizeof($thold_log_states)) {
							foreach ($thold_log_states as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('status') == $key) { print " selected"; } print ">" . $value['display'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Entries');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='<?php print __('Go');?>' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('filter');?>'>
			<input type='hidden' name='tab' value='log'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=log&status=' + $('#status').val();
			strURL += '&threshold_id=' + $('#threshold_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=log&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#form_log').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}
