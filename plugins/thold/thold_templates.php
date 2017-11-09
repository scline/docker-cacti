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

chdir('../../');

include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

$thold_actions = array(
	1 => __('Export', 'thold'),
	2 => __('Delete', 'thold')
);

set_default_action();

$action = get_nfilter_request_var('action');

if (isset_request_var('drp_action') && get_filter_request_var('drp_action') == 2) {
	$action = 'delete';
}

if (isset_request_var('drp_action') && get_filter_request_var('drp_action') == 1) {
	$action = 'export';
}

if (isset_request_var('import')) {
	$action = 'import';
}

switch ($action) {
	case 'add':
		template_add();
		break;
	case 'save':
		if (isset_request_var('save_component_import')) {
			template_import();
		}elseif (isset_request_var('save') && get_nfilter_request_var('save') == 'edit') {
			template_save_edit();

			if (isset($_SESSION['graph_return'])) {
				$return_to = $_SESSION['graph_return'];
				unset($_SESSION['graph_return']);
				kill_session_var('graph_return');
				header('Location: ' . $return_to);
			}else{
				header('Location: thold_templates.php?header=false');
			}
		} elseif (isset_request_var('save') && get_nfilter_request_var('save') == 'add') {

		}

		break;
	case 'delete':
		template_delete();

		break;
	case 'export':
		template_export();

		break;
	case 'import':
		top_header();
		import();
		bottom_footer();

		break;
	case 'edit':
		top_header();
		template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		templates();
		bottom_footer();

		break;
}

exit;

function template_export() {
	$output = "<templates>\n";
	if (sizeof($_POST)) {
		foreach($_POST as $t => $v) {
			if (substr($t, 0,4) == 'chk_') {
				$id = substr($t, 4);

				if (is_numeric($id)) {
					$data = db_fetch_row_prepared('SELECT *
						FROM thold_template
						WHERE id = ?',
						array($id));

					if (sizeof($data)) {
						$data_template_hash = db_fetch_cell_prepared('SELECT hash
							FROM data_template
							WHERE id = ?',
							array($data['data_template_id']));

						$data_source_hash   = db_fetch_cell_prepared('SELECT hash
							FROM data_template_rrd
							WHERE id = ?',
							array($data['data_source_id']));

						unset($data['id']);
						$data['data_template_id'] = $data_template_hash;
						$data['data_source_id']   = $data_source_hash;
						$output .= array2xml($data);
					}
				}
			}
		}
	}

	$output .= "</templates>\n";

	header('Content-type: application/xml');
	header('Content-Disposition: attachment; filename=thold_template_export.xml');

	print $output;

	exit;
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);

			input_validate_input_number($id);

			plugin_thold_log_changes($id, 'deleted_template', array('id' => $id));

			db_execute_prepared('DELETE FROM thold_template WHERE id = ? LIMIT 1', array($id));
			db_execute_prepared('DELETE FROM plugin_thold_template_contact WHERE template_id = ?', array($id));
			db_execute_prepared('DELETE FROM plugin_thold_host_template WHERE thold_template_id = ?', array($id));
			db_execute_prepared("UPDATE thold_data SET thold_template_id = '', template_enabled = '' WHERE thold_template_id = ?", array($id));
		}
	}

	header('Location: thold_templates.php?header=false');
	exit;
}

function template_add() {
	if ((!isset_request_var('save')) || (get_nfilter_request_var('save') == '')) {
		$data_templates = array_rekey(db_fetch_assoc('SELECT id, name FROM data_template ORDER BY name'), 'id', 'name');

		top_header();

		form_start('thold_templates.php', 'tholdform');

		html_start_box(__('Threshold Template Creation Wizard', 'thold'), '50%', '', '3', 'center', '');

		if (!isset_request_var('data_template_id')) {
			$data_template_id = 0;
		}else{
			$data_template_id = get_filter_request_var('data_template_id');
		}

		if (!isset_request_var('data_source_id')) {
			$data_source_id = 0;
		}else{
			$data_source_id = get_filter_request_var('data_source_id');
		}

		if ($data_template_id == 0) {
			print '<tr><td class="center">' . __('Please select a Data Template', 'thold') . '</td></tr>';
		} else if ($data_source_id == 0) {
			print '<tr><td class="center">' . __('Please select a Data Source', 'thold') . '</td></tr>';
		} else {
			print '<tr><td class="center">' . __('Please press \'Create\' to create your Threshold Template', 'thold') . '</td></tr>';
		}

		html_end_box();

		html_start_box('', '50%', '', '3', 'center', '');

		/* display the data template dropdown */
		?>
		<tr><td><table class='filterTable' align='center'>
			<tr>
				<td>
					<?php print __('Data Template', 'thold');?>
				</td>
				<td>
					<select id='data_template_id' name='data_template_id' onChange='applyFilter("dt")'>
						<option value=''><?php print __('None', 'thold');?></option><?php
						foreach ($data_templates as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == $data_template_id ? ' selected' : '') . '>' . htmlspecialchars($name, ENT_QUOTES) . '</option>';
						}?>
					</select>
				</td>
			</tr><?php

		if ($data_template_id != 0) {
			$data_fields = array();

			$temp = db_fetch_assoc_prepared('SELECT id, local_data_template_rrd_id,
				data_source_name, data_input_field_id
				FROM data_template_rrd
				WHERE local_data_template_rrd_id = 0
				AND data_template_id = ?',
				array($data_template_id));

			foreach ($temp as $d) {
				if ($d['data_input_field_id'] != 0) {
					$temp2 = db_fetch_assoc_prepared('SELECT name, data_name
						FROM data_input_fields
						WHERE id = ?',
						array($d['data_input_field_id']));

					$data_fields[$d['id']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
				} else {
					$temp2[0]['name'] = $d['data_source_name'];
					$data_fields[$d['id']] = $temp2[0]['name'];
				}
			}

			/* display the data source dropdown */
			?>
			<tr>
				<td>
					<?php print __('Data Source', 'thold');?>
				</td>
				<td>
					<select id='data_source_id' name='data_source_id' onChange='applyFilter("ds")'>
						<option value=''><?php print __('None', 'thold');?></option><?php
						foreach ($data_fields as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == $data_source_id ? ' selected' : '') . '>' . htmlspecialchars($name, ENT_QUOTES) . '</option>';
						}?>
					</select>
				</td>
			</tr>
			<?php
		}else{
			echo "<tr><td><input type='hidden' id='data_source_id' value=''></td></tr>\n";
		}

		if ($data_source_id != 0) {
			echo "<tr><td colspan='2'><input type='hidden' name='action' value='add'><input id='save' type='hidden' name='save' value='save'><br><center><input id='go' type='button' value='" . __esc('Create', 'thold') . "'></center></td></tr>";
		} else {
			echo "<tr><td colspan=2><input type=hidden name=action value='add'><br><br><br></td></tr>";
		}

		echo "</table></td></tr>\n";

		html_end_box();

		form_end();

		?>
		<script type='text/javascript'>

		function applyFilter(type) {
			if (type == 'dt' && $('#data_source_id')) {
				$('#data_source_id').val('');
			}

			if ($('#save')) {
				$('#save').val('');
			}

			loadPageNoHeader('thold_templates.php?action=add&header=false&data_template_id='+$('#data_template_id').val()+'&data_source_id='+$('#data_source_id').val());
		}

		$(function() {
			$('#go').button().click(function() {
				strURL = $('#tholdform').attr('action');
				json   = $('input, select').serializeObject();
				$.post(strURL, json).done(function(data) {
					$('#main').html(data);
					applySkin();
					window.scrollTo(0, 0);
				});
			});
		});

		</script>
		<?php

		bottom_footer();
	} else {
		if (!isset_request_var('data_template_id')) {
			$data_template_id = 0;
		}else{
			$data_template_id = get_filter_request_var('data_template_id');
		}

		if (!isset_request_var('data_source_id')) {
			$data_source_id = 0;
		}else{
			$data_source_id = get_filter_request_var('data_source_id');
		}

		$temp = db_fetch_row_prepared('SELECT id, name
			FROM data_template
			WHERE id = ?
			LIMIT 1',
			array($data_template_id));

		$save['id']   = '';
		$save['hash'] = get_hash_thold_template(0);
		$save['name'] = $temp['name'];

		$save['data_template_id']   = $data_template_id;
		$save['data_template_name'] = $temp['name'];
		$save['data_source_id']     = $data_source_id;

		$temp = db_fetch_row_prepared('SELECT id, local_data_template_rrd_id,
			data_source_name, data_input_field_id
			FROM data_template_rrd
			WHERE id = ?
			LIMIT 1',
			array($data_source_id));

		$save['data_source_name']  = $temp['data_source_name'];
		$save['name']             .= ' [' . $temp['data_source_name'] . ']';

		if ($temp['data_input_field_id'] != 0) {
			$temp2['name'] = db_fetch_cell_prepared('SELECT name
				FROM data_input_fields
				WHERE id = ? LIMIT 1',
				array($temp['data_input_field_id']));
		} else {
			$temp2['name'] = $temp['data_source_name'];
		}

		$save['data_source_friendly'] = $temp2['name'];
		$save['thold_enabled']        = 'on';
		$save['thold_type']           = 0;
		$save['repeat_alert']         = read_config_option('alert_repeat');

		$id = sql_save($save, 'thold_template');

		if ($id) {
			plugin_thold_log_changes($id, 'modified_template', $save);
			Header("Location: thold_templates.php?action=edit&id=$id&header=false");
			exit;
		} else {
			raise_message('thold_save');
			Header('Location: thold_templates.php?action=add&header=false');
			exit;
		}
	}
}

function template_save_edit() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('thold_type');
	get_filter_request_var('thold_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_fail_trigger');
	get_filter_request_var('time_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_fail_trigger');
	get_filter_request_var('time_fail_length');
	get_filter_request_var('thold_warning_type');
	get_filter_request_var('thold_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_fail_trigger');
	get_filter_request_var('time_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_fail_trigger');
	get_filter_request_var('time_warning_fail_length');
	get_filter_request_var('bl_ref_time_range');
	get_filter_request_var('bl_pct_down', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_pct_up', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_fail_trigger');
	get_filter_request_var('repeat_alert');
	get_filter_request_var('data_type');
	get_filter_request_var('cdef');
	get_filter_request_var('notify_warning');
	get_filter_request_var('notify_alert');
	get_filter_request_var('snmp_event_severity');
	get_filter_request_var('snmp_event_warning_severity');
	/* ==================================================== */

	/* clean up date1 string */
	if (isset_request_var('name')) {
		set_request_var('name', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('name'))));
	}

	if (isset_request_var('snmp_trap_category')) {
		set_request_var('snmp_event_category', db_qstr(trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_category')))));
	}

	/* save: data_template */
	$save['id']                 = get_nfilter_request_var('id');
	$save['hash']               = get_hash_thold_template($save['id']);
	$save['name']               = get_nfilter_request_var('name');
	$save['thold_type']         = get_nfilter_request_var('thold_type');

	// High / Low
	$save['thold_hi']           = get_nfilter_request_var('thold_hi');
	$save['thold_low']          = get_nfilter_request_var('thold_low');
	$save['thold_fail_trigger'] = get_nfilter_request_var('thold_fail_trigger');
	// Time Based
	$save['time_hi']            = get_nfilter_request_var('time_hi');
	$save['time_low']           = get_nfilter_request_var('time_low');

	$save['time_fail_trigger']  = get_nfilter_request_var('time_fail_trigger');
	$save['time_fail_length']   = get_nfilter_request_var('time_fail_length');

	if (isset_request_var('thold_fail_trigger') && get_nfilter_request_var('thold_fail_trigger') != '') {
		$save['thold_fail_trigger'] = get_nfilter_request_var('thold_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_fail_trigger'] = 5;
		}
	}

	/***  Warnings  ***/
	// High / Low Warnings
	$save['thold_warning_hi']           = get_nfilter_request_var('thold_warning_hi');
	$save['thold_warning_low']          = get_nfilter_request_var('thold_warning_low');
	$save['thold_warning_fail_trigger'] = get_nfilter_request_var('thold_warning_fail_trigger');

	// Time Based Warnings
	$save['time_warning_hi']            = get_nfilter_request_var('time_warning_hi');
	$save['time_warning_low']           = get_nfilter_request_var('time_warning_low');

	$save['time_warning_fail_trigger']  = get_nfilter_request_var('time_warning_fail_trigger');
	$save['time_warning_fail_length']   = get_nfilter_request_var('time_warning_fail_length');

	if (isset_request_var('thold_warning_fail_trigger') && get_nfilter_request_var('thold_warning_fail_trigger') != '') {
		$save['thold_warning_fail_trigger'] = get_nfilter_request_var('thold_warning_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_warning_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_warning_fail_trigger'] = 5;
		}
	}

	$save['thold_enabled']  = isset_request_var('thold_enabled')  ? 'on' : 'off';
	$save['exempt']         = isset_request_var('exempt')         ? 'on' : '';

	$save['thold_hrule_warning'] = get_nfilter_request_var('thold_hrule_warning');
	$save['thold_hrule_alert']   = get_nfilter_request_var('thold_hrule_alert');

	$save['restored_alert'] = isset_request_var('restored_alert') ? 'on' : '';

	if (isset_request_var('bl_ref_time_range') && get_nfilter_request_var('bl_ref_time_range') != '') {
		$save['bl_ref_time_range'] = get_nfilter_request_var('bl_ref_time_range');
	} else {
		$alert_bl_timerange_def = read_config_option('alert_bl_timerange_def');
		if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def)) {
			$save['bl_ref_time_range'] = $alert_bl_timerange_def;
		} else {
			$save['bl_ref_time_range'] = 10800;
		}
	}

	$save['bl_pct_down'] = get_nfilter_request_var('bl_pct_down');
	$save['bl_pct_up']   = get_nfilter_request_var('bl_pct_up');

	if (isset_request_var('bl_fail_trigger') && get_nfilter_request_var('bl_fail_trigger') != '') {
		$save['bl_fail_trigger'] = get_nfilter_request_var('bl_fail_trigger');
	} else {
		$alert_bl_trigger = read_config_option('alert_bl_trigger');
		if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger)) {
			$save['bl_fail_trigger'] = $alert_bl_trigger;
		} else {
			$save['bl_fail_trigger'] = 3;
		}
	}

	if (isset_request_var('repeat_alert') && get_nfilter_request_var('repeat_alert') != '') {
		$save['repeat_alert'] = get_nfilter_request_var('repeat_alert');
	} else {
		$alert_repeat = read_config_option('alert_repeat');
		if ($alert_repeat != '' && is_numeric($alert_repeat)) {
			$save['repeat_alert'] = $alert_repeat;
		} else {
			$save['repeat_alert'] = 12;
		}
	}

	if (isset_request_var('snmp_event_category')) {
		$save['snmp_event_category'] = get_nfilter_request_var('snmp_event_category');
		$save['snmp_event_severity'] = get_nfilter_request_var('snmp_event_severity');
	}
	if (isset_request_var('snmp_event_warning_severity')) {
		if (get_nfilter_request_var('snmp_event_warning_severity') > get_nfilter_request_var('snmp_event_severity')) {
			$save['snmp_event_warning_severity'] = get_nfilter_request_var('snmp_event_severity');
		}else {
			$save['snmp_event_warning_severity'] = get_nfilter_request_var('snmp_event_warning_severity');
		}
	}

	$save['notify_extra']         = get_nfilter_request_var('notify_extra');
	$save['notify_warning_extra'] = get_nfilter_request_var('notify_warning_extra');
	$save['notify_warning']       = get_nfilter_request_var('notify_warning');
	$save['notify_alert']         = get_nfilter_request_var('notify_alert');
	$save['cdef']                 = get_nfilter_request_var('cdef');

	$save['notes']                = get_nfilter_request_var('notes');

	$save['data_type']            = get_nfilter_request_var('data_type');
	$save['percent_ds']           = get_nfilter_request_var('percent_ds');
	$save['expression']           = get_nfilter_request_var('expression');

	if (!is_error_message()) {
		$id = sql_save($save, 'thold_template');
		if ($id) {
			raise_message(1);
			if (isset_request_var('notify_accounts') && is_array(get_nfilter_request_var('notify_accounts'))) {
				thold_save_template_contacts($id, get_nfilter_request_var('notify_accounts'));
			} elseif (!isset_request_var('notify_accounts')) {
				thold_save_template_contacts($id, array());
			}
			thold_template_update_thresholds ($id);

			plugin_thold_log_changes($id, 'modified_template', $save);
		} else {
			raise_message(2);
		}
	}

	if ((is_error_message()) || (isempty_request_var('id'))) {
		header('Location: thold_templates.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
	} else {
		header('Location: thold_templates.php?header=false');
	}
}

function template_edit() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$id = get_request_var('id');

	$thold_data = db_fetch_row_prepared('SELECT *
		FROM thold_template
		WHERE id = ?
		LIMIT 1',
		array($id));

	$temp = db_fetch_row_prepared('SELECT id, name
		FROM data_template
		WHERE id = ?',
		array($thold_data['data_template_id']));

	$data_templates[$temp['id']] = $temp['name'];

	$temp = db_fetch_row_prepared('SELECT id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE id = ?',
		array($thold_data['data_source_id']));

	$data_fields = array();
	if (sizeof($temp)) {
		$source_id = $temp['data_input_field_id'];

		if ($source_id != 0) {
			$temp2 = db_fetch_row_prepared('SELECT id, name
				FROM data_input_fields
				WHERE id = ?',
				array($source_id));

			$data_fields[$temp2['id']] = $temp2['name'];
			$data_source_name = $temp2['name'];
		} else {
			$data_fields[$temp['id']]  = $temp['data_source_name'];
			$data_source_name = $temp['data_source_name'];
		}
	}else{
		/* should not be reached */
		cacti_log('ERROR: Thold Template ID:' . $thold_data['id'] . ' references a deleted Data Source.');
		$data_source_name = '';
	}

	$send_notification_array = array();

	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data,
		plugin_thold_contacts.type, user_auth.full_name
		FROM plugin_thold_contacts, user_auth
		WHERE user_auth.id=plugin_thold_contacts.user_id
		AND plugin_thold_contacts.data!=''
		ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

	if (!empty($users)) {
		foreach ($users as $user) {
			$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
		}
	}
	if (isset($thold_data['id'])) {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=' . $thold_data['id'];
	} else {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=0';
	}

	$step = db_fetch_cell_prepared('SELECT rrd_step
		FROM data_template_data
		WHERE data_template_id = ?',
		array($thold_data['data_template_id']));

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$rra_steps = db_fetch_assoc_prepared('SELECT dspr.steps
		FROM data_template_data AS dtd
		INNER JOIN data_source_profiles AS dsp
	    ON dsp.id=dtd.data_source_profile_id
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
	    WHERE dspr.steps > 1
		AND dtd.data_template_id = ?
	    AND dtd.local_data_template_data_id=0
		ORDER BY steps',
		array($thold_data['data_template_id']));

	$reference_types = array();
	foreach($rra_steps as $rra_step) {
	    $seconds = $step * $rra_step['steps'];
		$reference_types[$seconds] = template_calculate_reference_avg($seconds, 'avg');
	}

	/* calculate percentage ds data sources */
	$data_fields2 = array();
	$temp = db_fetch_assoc_prepared('SELECT id, local_data_template_rrd_id, data_source_name,
		data_input_field_id
		FROM data_template_rrd
		WHERE local_data_template_rrd_id = 0
		AND data_source_name NOT IN(?)
		AND data_template_id = ?',
		array($data_source_name, $thold_data['data_template_id']));

	if (sizeof($temp)) {
		foreach ($temp as $d) {
			if ($d['data_input_field_id'] != 0) {
				$temp2 = db_fetch_row_prepared('SELECT id, name, data_name
					FROM data_input_fields
					WHERE id = ?
					ORDER BY data_name',
					array($d['data_input_field_id']));

				$data_fields2[$d['data_source_name']] = $temp2['data_name'] . ' (' . $temp2['name'] . ')';
			} else {
				$data_fields2[$d['data_source_name']] = $d['data_source_name'];
			}
		}
	}

	$replacements = db_fetch_assoc_prepared('SELECT DISTINCT field_name
		FROM data_local AS dl
		INNER JOIN (
			SELECT DISTINCT field_name, snmp_query_id
			FROM host_snmp_cache
		) AS hsc
		ON dl.snmp_query_id=hsc.snmp_query_id
		WHERE dl.data_template_id = ?',
		array($thold_data['data_template_id']));

	$nr = array();
	if (sizeof($replacements)) {
		foreach($replacements as $r) {
			$nr[] = "<span style='color:blue;'>|query_" . $r['field_name'] . "|</span>";
		}
	}

	$vhf = explode("|", trim(VALID_HOST_FIELDS, "()"));
	if (sizeof($vhf)) {
		foreach($vhf as $r) {
			$nr[] = "<span style='color:blue;'>|" . $r . "|</span>";
		}
	}

	$replacements = '<br>' . __('Replacement Fields: %s', implode(', ', $nr), 'thold');

	$dss = db_fetch_assoc_prepared('SELECT data_source_name
		FROM data_template_rrd
		WHERE data_template_id= ?
		AND local_data_id=0',
		array($thold_data['data_template_id']));

	if (sizeof($dss)) {
		foreach($dss as $ds) {
			$dsname[] = "<span style='color:blue;'>|ds:" . $ds["data_source_name"] . "|</span>";
		}
	}

	$datasources = '<br>' . __('Data Sources: %s', implode(', ', $dsname), 'thold');

	$form_array = array(
		'general_header' => array(
			'friendly_name' => __('General Settings', 'thold'),
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => __('Template Name', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => '60',
			'default' => $thold_data['data_template_name'] . ' [' . $thold_data['data_source_name'] . ']',
			'description' => __('Provide the Threshold Template a meaningful name.  Device Substitution and Data Query Substitution variables can be used as well as |graph_title| for the Graph Title', 'thold'),
			'value' => isset($thold_data['name']) ? $thold_data['name'] : ''
		),
		'data_template_name' => array(
			'friendly_name' => __('Data Template', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Data Template that you are using. (This cannot be changed)', 'thold'),
			'value' => $thold_data['data_template_id'],
			'array' => $data_templates,
		),
		'data_field_name' => array(
			'friendly_name' => __('Data Field', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Data Field that you are using. (This cannot be changed)', 'thold'),
			'value' => $thold_data['id'],
			'array' => $data_fields,
		),
		'thold_enabled' => array(
			'friendly_name' => __('Enabled', 'thold'),
			'method' => 'checkbox',
			'default' => 'on',
			'description' => __('Whether or not this Threshold will be checked and alerted upon.', 'thold'),
			'value' => isset($thold_data['thold_enabled']) ? $thold_data['thold_enabled'] : ''
		),
		'exempt' => array(
			'friendly_name' => __('Weekend Exemption', 'thold'),
			'description' => __('If this is checked, this Threshold will not alert on weekends.', 'thold'),
			'method' => 'checkbox',
			'default' => '',
			'value' => isset($thold_data['exempt']) ? $thold_data['exempt'] : ''
			),
		'thold_hrule_warning' => array(
			'friendly_name' => __('Warning HRULE Color', 'thold'),
			'description' => __('Please choose a Color for the Graph HRULE for the Warning Thresholds.  Choose \'None\' for No HRULE.  Note: This features is supported for Data Manipulation types \'Exact Value\' and \'Percentage\' only at this time.', 'thold'),
			'method' => 'drop_color',
			'none_value' => __('None', 'thold'),
			'default' => '0',
			'value' => isset($thold_data['thold_hrule_warning']) ? $thold_data['thold_hrule_warning'] : '0'
			),
		'thold_hrule_alert' => array(
			'friendly_name' => __('Alert HRULE Color', 'thold'),
			'description' => __('Please choose a Color for the Graph HRULE for the Alert Thresholds.  Choose \'None\' for No HRULE.  Note: This features is supported for Data Manipulation types \'Exact Value\' and \'Percentage\' only at this time.', 'thold'),
			'method' => 'drop_color',
			'none_value' => __('None', 'thold'),
			'default' => '0',
			'value' => isset($thold_data['thold_hrule_alert']) ? $thold_data['thold_hrule_alert'] : '0'
			),
		'restored_alert' => array(
			'friendly_name' => __('Disable Restoration Email', 'thold'),
			'description' => __('If this is checked, Threshold will not send an alert when the Threshold has returned to normal status.', 'thold'),
			'method' => 'checkbox',
			'default' => '',
			'value' => isset($thold_data['restored_alert']) ? $thold_data['restored_alert'] : ''
			),
		'thold_type' => array(
			'friendly_name' => __('Threshold Type', 'thold'),
			'method' => 'drop_array',
			'on_change' => 'changeTholdType()',
			'array' => $thold_types,
			'default' => read_config_option('thold_type'),
			'description' => __('The type of Threshold that will be monitored.', 'thold'),
			'value' => isset($thold_data['thold_type']) ? $thold_data['thold_type'] : ''
		),
		'repeat_alert' => array(
			'friendly_name' => __('Re-Alert Cycle', 'thold'),
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => read_config_option('alert_repeat'),
			'description' => __('Repeat alert after this amount of time has pasted since the last alert.', 'thold'),
			'value' => isset($thold_data['repeat_alert']) ? $thold_data['repeat_alert'] : ''
		),
		'thold_warning_header' => array(
			'friendly_name' => __('Warning - High / Low Settings', 'thold'),
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes above this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['thold_warning_hi']) ? $thold_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes below this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['thold_warning_low']) ? $thold_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => __('Min Trigger Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the data source must be in a breach condition for an alert to be raised.', 'thold'),
			'value' => isset($thold_data['thold_warning_fail_trigger']) ? $thold_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => __('Alert - High / Low Settings', 'thold'),
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes above this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['thold_hi']) ? $thold_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes below this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['thold_low']) ? $thold_data['thold_low'] : ''
		),
		'thold_fail_trigger' => array(
			'friendly_name' => __('Min Trigger Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the data source must be in a breach condition for an alert to be raised.', 'thold'),
			'value' => isset($thold_data['thold_fail_trigger']) ? $thold_data['thold_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_header' => array(
			'friendly_name' => __('Warning - Time Based Settings', 'thold'),
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes above this number, warning will be triggered', 'thold'),
			'value' => isset($thold_data['time_warning_hi']) ? $thold_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes below this number, warning will be triggered', 'thold'),
			'value' => isset($thold_data['time_warning_low']) ? $thold_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => __('Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'default' => read_config_option('thold_warning_time_fail_trigger'),
			'description' => __('The number of times the data source must be in breach condition prior to issuing a warning.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_trigger']) ? $thold_data['time_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => __('Time Period Length', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_length']) ? $thold_data['time_warning_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
		),
		'time_header' => array(
			'friendly_name' => __('Alert - Time Based Settings', 'thold'),
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes above this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['time_hi']) ? $thold_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and data source value goes below this number, alert will be triggered', 'thold'),
			'value' => isset($thold_data['time_low']) ? $thold_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => __('Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'description' => __('The number of times the data source must be in breach condition prior to issuing an alert.', 'thold'),
			'value' => isset($thold_data['time_fail_trigger']) ? $thold_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => __('Time Period Length', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_fail_length']) ? $thold_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 2)
		),
		'baseline_header' => array(
			'friendly_name' => __('Baseline Monitoring', 'thold'),
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => __('Time reference in the past', 'thold'),
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => __('Specifies the point in the past (based on rrd resolution) that will be used as a reference', 'thold'),
			'value' => isset($thold_data['bl_ref_time_range']) ? $thold_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => __('Baseline Deviation UP', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the upper bound Threshold. If not set, upper bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_up']) ? $thold_data['bl_pct_up'] : read_config_option('alert_bl_percent_def')
		),
		'bl_pct_down' => array(
			'friendly_name' => __('Baseline Deviation DOWN', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the lower bound Threshold. If not set, lower bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_down']) ? $thold_data['bl_pct_down'] : read_config_option('alert_bl_percent_def')
		),
		'bl_fail_trigger' => array(
			'friendly_name' => __('Baseline Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Number of consecutive times the data source must be in a breached condition for an alert to be raised.<br>Leave empty to use default value (Default: %s cycles', read_config_option('alert_bl_trigger'), 'thold'),
			'value' => isset($thold_data['bl_fail_trigger']) ? $thold_data['bl_fail_trigger'] : read_config_option('alert_bl_trigger')
		),
		'data_manipulation' => array(
			'friendly_name' => __('Data Manipulation', 'thold'),
			'method' => 'spacer',
		),
		'data_type' => array(
			'friendly_name' => __('Data Type', 'thold'),
			'method' => 'drop_array',
			'on_change' => 'changeDataType()',
			'array' => $data_types,
			'description' => __('Special formatting for the given data.', 'thold'),
			'value' => isset($thold_data['data_type']) ? $thold_data['data_type'] : read_config_option('data_type')
		),
		'cdef' => array(
			'friendly_name' => __('Threshold CDEF', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Apply this CDEF before returning the data.', 'thold'),
			'value' => isset($thold_data['cdef']) ? $thold_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => __('Percent Datasource', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Second Datasource Item to use as total value to calculate percentage from.', 'thold'),
			'value' => isset($thold_data['percent_ds']) ? $thold_data['percent_ds'] : 0,
			'array' => $data_fields2,
		),
		'expression' => array(
			'friendly_name' => __('RPN Expression', 'thold'),
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 80,
			'default' => '',
			'description' => __('An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes all functions below in addition to both Device and Data Query replacement expressions such as <span style="color:blue;">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span style="color:blue;">|ds:dsname|</span>.  For example, <span style="color:blue;">|ds:traffic_in|</span> will get the current value of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br>Math Operators: <span style="color:blue;">+, -, /, *, &#37;, ^</span><br>Functions: <span style="color:blue;">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br>Flow Operators: <span style="color:blue;">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br>Comparison Functions: <span style="color:blue;">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>%s %s', $replacements, $datasources, 'thold'),
			'value' => isset($thold_data['expression']) ? $thold_data['expression'] : ''
		),
		'other_header' => array(
			'friendly_name' => __('Other Settings', 'thold'),
			'method' => 'spacer',
		),
		'notify_warning' => array(
			'friendly_name' => __('Warning Notification List', 'thold'),
			'method' => 'drop_sql',
			'description' => __('You may specify choose a Notification List to receive Warnings for this Data Source', 'thold'),
			'value' => isset($thold_data['notify_warning']) ? $thold_data['notify_warning'] : '',
			'none_value' => __('None', 'thold'),
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		),
		'notify_alert' => array(
			'friendly_name' => __('Alert Notification List', 'thold'),
			'method' => 'drop_sql',
			'description' => __('You may specify choose a Notification List to receive Alerts for this Data Source', 'thold'),
			'value' => isset($thold_data['notify_alert']) ? $thold_data['notify_alert'] : '',
			'none_value' => __('None', 'thold'),
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		)
	);

	if (read_config_option('thold_alert_snmp') == 'on') {
		$extra = array(
			'snmp_event_category' => array(
				'friendly_name' => __('SNMP Notification - Event Category', 'thold'),
				'method' => 'textbox',
				'description' => __('To allow a NMS to categorize different SNMP notifications more easily please fill in the category SNMP notifications for this template should make use of. E.g.: "disk_usage", "link_utilization", "ping_test", "nokia_firewall_cpu_utilization" ...', 'thold'),
				'value' => isset($thold_data['snmp_event_category']) ? $thold_data['snmp_event_category'] : '',
				'default' => '',
				'max_length' => '255',
			),
			'snmp_event_severity' => array(
				'friendly_name' => __('SNMP Notification - Alert Event Severity', 'thold'),
				'method' => 'drop_array',
				'default' => '3',
				'description' => __('Severity to be used for alerts. (Low impact -> Critical impact)', 'thold'),
				'value' => isset($thold_data['snmp_event_severity']) ? $thold_data['snmp_event_severity'] : 3,
				'array' => array(1 => __('Low', 'thold'), 2 => __('Medium', 'thold'), 3 => __('High', 'thold'), 4 => __('Critical', 'thold')),
			),
		);
		$form_array += $extra;

		if (read_config_option('thold_alert_snmp_warning') != 'on') {
			$extra = array(
				'snmp_event_warning_severity' => array(
					'friendly_name' => __('SNMP Notification - Warning Event Severity', 'thold'),
					'method' => 'drop_array',
					'default' => '2',
					'description' => __('Severity to be used for warnings. (Low impact -> Critical impact).<br>Note: The severity of warnings has to be equal or lower than the severity being defined for alerts.', 'thold'),
					'value' => isset($thold_data['snmp_event_warning_severity']) ? $thold_data['snmp_event_warning_severity'] : 2,
					'array' => array(1 => __('Low', 'thold'), 2 => __('Medium', 'thold'), 3 => __('High', 'thold'), 4 => __('Critical', 'thold')),
				),
			);
		}
		$form_array += $extra;
	}

	if (read_config_option('thold_disable_legacy') != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => __('Notify accounts', 'thold'),
				'method' => 'drop_multi',
				'description' => __('This is a listing of accounts that will be notified when this Threshold is breached.<br><br><br><br>', 'thold'),
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => __('Alert Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive alerts for this data source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_extra']) ? $thold_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => __('Warning Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive warnings for this data source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_warning_extra']) ? $thold_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	} else {
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore',
			),
			'notify_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_data['notify_extra']) ? $thold_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_data['notify_warning_extra']) ? $thold_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}

	$extra = array(
		'notes' => array(
			'friendly_name' => __('Operator Notes', 'thold'),
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 50,
			'description' => __('Enter instructions here for an operator who may be receiving the threshold message.', 'thold'),
			'value' => isset($thold_data['notes']) ? $thold_data['notes'] : ''
		)
	);
	$form_array += $extra;

	form_start('thold_templates.php', 'thold');

	html_start_box('', '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	form_hidden_box('save', 'edit', '');
	form_hidden_box('id', $id, '');

	html_end_box();

	form_save_button('thold_templates.php?id=' . $id);

	?>
	<script type='text/javascript'>

	function changeTholdType() {
		switch($('#thold_type').val()) {
		case '0': // Hi/Low
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		case '1': // Baseline
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').hide();
			$('#row_thold_hrule_alert').hide();

			break;
		case '2': // Time Based
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		}
	}

	function changeDataType() {
		switch($('#data_type').val()) {
		case '0':
			$('#row_cdef, #row_percent_ds, #row_expression').hide();

			break;
		case '1':
			$('#row_cdef').show();
			$('#row_percent_ds, #row_expression').hide();

			break;
		case '2':
			$('#row_cdef').hide();
			$('#row_percent_ds, #row_expression').show();

			break;
		case '3':
			$('#row_cdef').hide();
			$('#row_percent_ds').hide();
			$('#row_expression').show();

			break;
		}
	}

	function thold_toggle_hilow(status) {
		if (status == '') {
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').show();
			$('#row_thold_warning_header, #row_thold_warning_hi').show();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').show();
		}else{
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').hide();
			$('#row_thold_warning_header, #row_thold_warning_hi').hide();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').hide();
		}
	}

	function thold_toggle_baseline(status) {
		if (status == '') {
			$('#row_baseline_header, #row_bl_ref_time_range').show();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').show();
		}else{
			$('#row_baseline_header, #row_bl_ref_time_range').hide();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').hide();
		}
	}

	function thold_toggle_time(status) {
		if (status == '') {
			$('#row_time_header, #row_time_hi, #row_time_low').show();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').show();
			$('#row_time_warning_hi, #row_time_warning_low').show();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').show();
		}else{
			$('#row_time_header, #row_time_hi, #row_time_low').hide();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').hide();
			$('#row_time_warning_hi, #row_time_warning_low').hide();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').hide();
		}
	}

	changeTholdType();
	changeDataType();

	if ($('#notify_accounts option').length == 0) {
		$('#row_notify_accounts').hide();
	}

	if ($('#notify_warning option').length == 0) {
		$('#row_notify_warning').hide();
	}

	if ($('#notify_alert option').length == 0) {
		$('#row_notify_alert').hide();
	}

	$('#notify_accounts').multiselect({
		minWidth: '400',
		noneSelectedText: 'Select Users(s)',
		selectedText: function(numChecked, numTotal, checkedItems) {
			myReturn = numChecked + ' Users Selected';
			$.each(checkedItems, function(index, value) {
				if (value.value == '0') {
				myReturn='All Users Selected';
					return false;
				}
			});
			return myReturn;
		},
		checkAllText: 'All',
		uncheckAllText: 'None',
		uncheckall: function() {
			$(this).multiselect('widget').find(':checkbox:first').each(function() {
				$(this).prop('checked', true);
			});
		},
		open: function() {
			size = $('#notify_accounts option').length * 20 + 20;
			if (size > 140) {
				size = 140;
			}
			$('ul.ui-multiselect-checkboxes').css('height', size + 'px');
		},
		click: function(event, ui) {
			checked=$(this).multiselect('widget').find('input:checked').length;

			if (ui.value == '0') {
				if (ui.checked == true) {
					$('#host').multiselect('uncheckAll');
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).prop('checked', true);
					});
				}
			}else if (checked == 0) {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).click();
				});
			}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
				if (checked > 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
						$(this).prop('disable', true);
					});
				}
			}
		}
	}).multiselectfilter( {
		label: 'Search', width: '150'
	});

	</script>
	<?php
}

function template_calculate_reference_avg($seconds, $suffix = 'avg') {
	$s = ($seconds % 60);
	$m = floor(($seconds % 3600) / 60);
	$h = floor(($seconds % 86400) / 3600);
	$d = floor(($seconds % 2592000) / 86400);
	$M = floor($seconds / 2592000);

	if ($M > 0) {
		if ($suffix == 'avg') {
			return __('%d Months, %d Days, %d Hours, %d Minutes, %d Seconds (Average)', $M, $d, $h, $m, $s, 'thold');
		}else{
			return __('%d Months, %d Days, %d Hours, %d Minutes, %d Seconds', $M, $d, $h, $m, $s, 'thold');
		}
	}elseif ($d > 0) {
		if ($suffix == 'avg') {
			return __('%d Days, %d Hours, %d Minutes, %d Seconds (Average)', $d, $h, $m, $s, 'thold');
		}else{
			return __('%d Days, %d Hours, %d Minutes, %d Seconds', $d, $h, $m, $s, 'thold');
		}
	}elseif ($h > 0) {
		if ($suffix == 'avg') {
			return __('%d Hours, %d Minutes, %d Seconds (Average)', $h, $m, $s, 'thold');
		}else{
			return __('%d Hours, %d Minutes, %d Seconds', $h, $m, $s, 'thold');
		}
	}elseif ($m > 0) {
		if ($suffix == 'avg') {
			return __('%d Minutes, %d Seconds (Average)', $m, $s, 'thold');
		}else{
			return __('%d Minutes, %d Seconds', $m, $s, 'thold');
		}
	}else{
		if ($suffix == 'avg') {
			return __('%d Seconds (Average)', $s, 'thold');
		}else{
			return __('%d Seconds', $s, 'thold');
		}
	}
}

function template_request_validation() {
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
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_tt');
	/* ================= input validation ================= */
}

function templates() {
	global $config, $thold_actions, $item_rows;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	template_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Threshold Templates', 'thold'), '100%', '', '3', 'center', 'thold_templates.php?action=add');

	?>
	<tr class='even'>
		<td>
			<form id='listthold' action='thold_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Templates', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
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
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()'>
							<input id='import' type='button' value='<?php print __esc('Import', 'thold');?>' onClick='importTemplate()'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'thold_templates.php?header=false&rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = 'thold_templates.php?header=false&clear=1';
				loadPageNoHeader(strURL);
			}

			function importTemplate() {
				strURL = 'thold_templates.php?header=false&action=import';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#listthold').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND': 'WHERE') . " thold_template.name LIKE '%" . get_request_var('filter') . "%'";
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows    = db_fetch_cell('SELECT count(*) FROM thold_template');
	$template_list = db_fetch_assoc("SELECT * FROM thold_template $sql_where $sql_order $sql_limit");

	$nav = html_nav_bar('thold_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Templates', 'thold'), 'page', 'main');

	form_start('thold_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'               => array('display' => __('Name', 'thold'), 'sort' => 'ASC', 'align' => 'left'),
		'data_template_name' => array('display' => __('Data Template', 'thold'), 'sort' => 'ASC', 'align' => 'left'),
		'thold_type'         => array('display' => __('Type', 'thold'), 'sort' => 'ASC', 'align' => 'right'),
		'data_source_name'   => array('display' => __('DS Name', 'thold'), 'sort' => 'ASC', 'align' => 'right'),
		'nosort1'            => array('display' => __('High', 'thold'), 'sort' => '', 'align' => 'right'),
		'nosort2'            => array('display' => __('Low', 'thold'), 'sort' => '', 'align' => 'right'),
		'nosort3'            => array('display' => __('Trigger', 'thold'), 'sort' => '', 'align' => 'right'),
		'nosort4'            => array('display' => __('Duration', 'thold'), 'sort' => '', 'align' => 'right'),
		'nosort5'            => array('display' => __('Repeat', 'thold'), 'sort' => '', 'align' => 'right')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($template_list)) {
		foreach ($template_list as $template) {
			switch ($template['thold_type']) {
			case 0:					# hi/lo
				$value_hi               = thold_format_number($template['thold_hi'], 2, 1000);
				$value_lo               = thold_format_number($template['thold_low'], 2, 1000);
				$value_trig             = $template['thold_fail_trigger'];
				$value_duration         = '';
				$value_warning_hi       = thold_format_number($template['thold_warning_hi'], 2, 1000);
				$value_warning_lo       = thold_format_number($template['thold_warning_low'], 2, 1000);
				$value_warning_trig     = $template['thold_warning_fail_trigger'];
				$value_warning_duration = '';

				break;
			case 1:					# baseline
				$value_hi   = $template['bl_pct_up'] . (strlen($template['bl_pct_up']) ? '%':'-');
				$value_lo   = $template['bl_pct_down'] . (strlen($template['bl_pct_down']) ? '%':'-');
				$value_warning_hi = '-';
				$value_warning_lo = '-';
				$value_trig = $template['bl_fail_trigger'];

				$step = db_fetch_cell_prepared('SELECT rrd_step
					FROM data_template_data
					WHERE data_template_id = ?
					LIMIT 1', array($template['data_template_id']));

				$value_duration = $template['bl_ref_time_range'] / $step;;

				break;
			case 2:					#time
				$value_hi         = thold_format_number($template['time_hi'], 2, 1000);
				$value_lo         = thold_format_number($template['time_low'], 2, 1000);
				$value_warning_hi = thold_format_number($template['thold_warning_hi'], 2, 1000);
				$value_warning_lo = thold_format_number($template['thold_warning_low'], 2, 1000);
				$value_trig       = $template['time_fail_trigger'];
				$value_duration   = $template['time_fail_length'];

				break;
			}

			$name = ($template['name'] == '' ? $template['data_template_name'] . ' [' . $template['data_source_name'] . ']' : $template['name']);
			$name = filter_value($name, get_request_var('filter'));

			form_alternate_row('line' . $template['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('thold_templates.php?action=edit&id=' . $template['id']) . '">' . $name  . '</a>', $template['id']);
			form_selectable_cell(filter_value($template['data_template_name'], get_request_var('filter')), $template['id']);
			form_selectable_cell($thold_types[$template['thold_type']], $template['id'], '', 'right');
			form_selectable_cell($template['data_source_name'], $template['id'], '', 'right');
			form_selectable_cell($value_hi . ' / ' . $value_warning_hi, $template['id'], '', 'right');
			form_selectable_cell($value_lo . ' / ' . $value_warning_lo, $template['id'], '', 'right');

			$trigger =  plugin_thold_duration_convert($template['data_template_id'], $value_trig, 'alert', 'data_template_id');
			form_selectable_cell((strlen($trigger) ? '<i>' . $trigger . '</i>':'-'), $template['id'], '', 'right');

			$duration = plugin_thold_duration_convert($template['data_template_id'], $value_duration, 'time', 'data_template_id');
			form_selectable_cell((strlen($duration) ? $duration:'-'), $template['id'], '', 'right');
			form_selectable_cell(plugin_thold_duration_convert($template['data_template_id'], $template['repeat_alert'], 'repeat', 'data_template_id'), $template['id'], '', 'right');
			form_checkbox_cell($template['data_template_name'], $template['id']);
			form_end_row();
		}
	} else {
		print "<tr><td><em>" . __('No Threshold Templates', 'thold') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($thold_actions);

	thold_form_end();
}

function import() {
	$form_data = array(
		'import_file' => array(
			'friendly_name' => __('Import Template from Local File', 'thold'),
			'description' => __('If the XML file containing Threshold Template data is located on your local machine, select it here.', 'thold'),
			'method' => 'file'
		),
		'import_text' => array(
			'method' => 'textarea',
			'friendly_name' => __('Import Template from Text', 'thold'),
			'description' => __('If you have the XML file containing Threshold Template data as text, you can paste it into this box to import it.', 'thold'),
			'value' => '',
			'default' => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class' => 'textAreaNotes'
		)
	);

	?>
	<form method='post' action='thold_templates.php' enctype='multipart/form-data'>
	<?php

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results', 'thold'), '100%', '', '3', 'center', '');

		print '<tr><td>' . __('Cacti has imported the following items:', 'thold'). '</td></tr>';
		foreach($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Threshold Templates', 'thold'), '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => $form_data
		));

	html_end_box();
	form_hidden_box('save_component_import','1','');

	form_save_button('', 'import');
}

function template_import() {
	include_once('./lib/xml.php');

	if (trim(get_nfilter_request_var('import_text') != '')) {
		/* textbox input */
		$xml_data = get_nfilter_request_var('import_text');
	}elseif (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
		/* file upload */
		$fp = fopen($_FILES['import_file']['tmp_name'],'r');
		$xml_data = fread($fp,filesize($_FILES['import_file']['tmp_name']));
		fclose($fp);
	}else{
		header('Location: thold_templates.php?header=false');
		exit;
	}

	/* obtain debug information if it's set */
	$xml_array = xml2array($xml_data);

	$debug_data = array();

	if (sizeof($xml_array)) {
	foreach($xml_array as $template => $contents) {
		$error = false;
		$save  = array();
		if (sizeof($contents)) {
		foreach($contents as $name => $value) {
			switch($name) {
			case 'data_template_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell_prepared('SELECT id
					FROM data_template
					WHERE hash = ?',
					array($value));

				if (!empty($found)) {
					$save['data_template_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>" . __('ERROR:', 'thold') . "</span> " . __('Threshold Template Subordinate Data Template Not Found!', 'thold');
				}

				break;
			case 'data_source_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell_prepared('SELECT id
					FROM data_template_rrd
					WHERE hash = ?',
					array($value));

				if (!empty($found)) {
					$save['data_source_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>" . __('ERROR:', 'thold'). "</span> " . __('Threshold Template Subordinate Data Source Not Found!', 'thold');
				}

				break;
			case 'hash':
				// See if the hash exists, if it does, update the thold
				$found = db_fetch_cell_prepared('SELECT id
					FROM thold_template
					WHERE hash = ?',
					array($value));

				if (!empty($found)) {
					$save['hash'] = $value;
					$save['id']   = $found;
				}else{
					$save['hash'] = $value;
					$save['id']   = 0;
				}

				break;
			case 'name':
				$tname = $value;
				$save['name'] = $value;

				break;
			default:
				if (db_column_exists('thold_template', $name)) {
					$save[$name] = $value;
				}

				break;
			}
		}
		}

		if (!$error) {
			$id = sql_save($save, 'thold_template');

			if ($id) {
				$debug_data[] = "<span style='font-weight:bold;color:green;'>" . __('NOTE:', 'thold') . "</span> " . __('Threshold Template \'%s\' %s!', $tname, ($save['id'] > 0 ? __('Updated', 'thold'):__('Imported', 'thold')), 'thold');
			}else{
				$debug_data[] = "<span style='font-weight:bold;color:red;'>" . __('ERROR:', 'thold'). "</span> " . __('Threshold Template \'%s\' %s Failed!', $tname, ($save['id'] > 0 ? __('Update', 'thold'):__('Import', 'thold')), 'thold');
			}
		}
	}
	}

	if(sizeof($debug_data) > 0) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: thold_templates.php?action=import');
}

/* form_end - draws post form end. To be combined with form_start() */
function thold_form_end($ajax = true) {
	global $form_id, $form_action;

	print "</form>\n";

	if ($ajax) { ?>
		<script type='text/javascript'>
		$(function() {
			$('#<?php print $form_id;?>').submit(function(event) {
				if ($('#drp_action').val() != '1') {
					event.preventDefault();
					strURL = '<?php print $form_action;?>';
					strURL += (strURL.indexOf('?') >= 0 ? '&':'?') + 'header=false';
					json =  $('#<?php print $form_id;?>').serializeObject();
					$.post(strURL, json).done(function(data) {
						$('#main').html(data);
						applySkin();
						window.scrollTo(0, 0);
					});
				}
			});
		});
		</script>
		<?php
	}
}
