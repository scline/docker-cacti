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

function thold_add_graphs_action_execute() {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$host_id           = get_filter_request_var('host_id');
	$local_graph_id    = get_filter_request_var('local_graph_id');
	$thold_template_id = get_filter_request_var('thold_template_id');

	$message = '';

	$template = db_fetch_row_prepared('SELECT * FROM thold_template WHERE id = ?', array($thold_template_id));

	$temp = db_fetch_row_prepared('SELECT dtr.*
		FROM data_template_rrd AS dtr
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		LEFT JOIN graph_local AS gl
		ON gl.id=gti.local_graph_id
		WHERE gl.id = ?
		LIMIT 1' ,
		array($local_graph_id));

	$data_template_id = $temp['data_template_id'];
	$local_data_id    = $temp['local_data_id'];

	$data_source      = db_fetch_row_prepared('SELECT *
		FROM data_local
		WHERE id = ?',
		array($local_data_id));

	$data_template_id = $data_source['data_template_id'];

	/* allow duplicate thresholds, but only from differing templates */
	$existing = db_fetch_assoc('SELECT id
		FROM thold_data
		WHERE local_data_id=' . $local_data_id . '
		AND data_template_rrd_id=' . $data_template_id . '
		AND thold_template_id=' . $template['id'] . " AND template_enabled='on'");

	if (count($existing) == 0 && count($template)) {
		if ($local_graph_id) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id ORDER BY id LIMIT 1");
			$grapharr  = db_fetch_row("SELECT graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup AND local_graph_id = $local_graph_id");

			$desc = db_fetch_cell_prepared('SELECT name_cache FROM data_template_data WHERE local_data_id = ? LIMIT 1', array($local_data_id));

			$data_source_name = $template['data_source_name'];
			$insert = array();

			$name = thold_format_name($template, $local_graph_id, $local_data_id, $data_source_name);

			$insert['name']               = $name;
			$insert['host_id']            = $data_source['host_id'];
			$insert['local_data_id']      = $local_data_id;
			$insert['local_graph_id']     = $local_graph_id;
			$insert['data_template_id']   = $data_template_id;
			$insert['graph_template_id']  = $grapharr['graph_template_id'];
			$insert['thold_hi']           = $template['thold_hi'];
			$insert['thold_low']          = $template['thold_low'];
			$insert['thold_fail_trigger'] = $template['thold_fail_trigger'];
			$insert['thold_enabled']      = $template['thold_enabled'];
			$insert['thold_warning_hi']           = $template['thold_warning_hi'];
			$insert['thold_warning_low']          = $template['thold_warning_low'];
			$insert['thold_warning_fail_trigger'] = $template['thold_warning_fail_trigger'];
			$insert['bl_ref_time_range']  = $template['bl_ref_time_range'];
			$insert['bl_pct_down']        = $template['bl_pct_down'];
			$insert['bl_pct_up']          = $template['bl_pct_up'];
			$insert['bl_fail_trigger']    = $template['bl_fail_trigger'];
			$insert['bl_alert']           = $template['bl_alert'];
			$insert['repeat_alert']       = $template['repeat_alert'];
			$insert['notify_extra']       = $template['notify_extra'];
			$insert['cdef']               = $template['cdef'];
			$insert['thold_template_id']  = $template['id'];
			$insert['notes']              = $template['notes'];
			$insert['template_enabled']   = 'on';

			$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id
				FROM data_template_rrd
				WHERE local_data_id='$local_data_id'
				AND data_source_name='$data_source_name'");

			$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');

			foreach ($rrdlist as $rrdrow) {
				$data_rrd_id = $rrdrow['id'];
				$insert['data_template_rrd_id'] = $data_rrd_id;

				$existing = db_fetch_assoc("SELECT id
					FROM thold_data
					WHERE local_data_id='$local_data_id'
					AND data_template_rrd_id='$data_rrd_id'
					AND thold_template_id='" . $template['id'] . "' AND template_enabled='on'");

				if (count($existing) == 0) {
					$insert['id'] = 0;
					$id = sql_save($insert, 'thold_data');
					if ($id) {
						thold_template_update_threshold($id, $insert['thold_template_id']);

						$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id");
						$tname = $l[0]['name'];

						$name = $data_source_name;
						if ($rrdrow['data_input_field_id'] != 0) {
							$l = db_fetch_assoc('SELECT name FROM data_input_fields where id=' . $rrdrow['data_input_field_id']);
							$name = $l[0]['name'];
						}
						plugin_thold_log_changes($id, 'created', " $tname [$name]");
						$message .= "Created Threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<br>";
					}
				}
			}
		}
	}

	if (strlen($message)) {
		$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	}else{
		$_SESSION['thold_message'] = "<font size=-2>" . __('Threshold(s) Already Exists - No Thresholds Created', 'thold') . "</font>";
	}

	raise_message('thold_message');

	if (isset($_SESSION['graph_return'])) {
		$return_to = $_SESSION['graph_return'];

		unset($_SESSION['graph_return']);

		kill_session_var('graph_return');

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');
	}else{
		header('Location:' . $config['url_path'] . 'plugins/thold/thold.php?header=false');
	}
}

function thold_add_graphs_action_prepare() {
	global $config;

	$local_graph_id = get_filter_request_var('local_graph_id');
	$host_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	top_header();

	form_start($config['url_path'] . 'plugins/thold/thold.php?action=add', 'tholdform');

	html_start_box(__('Create Threshold from Template', 'thold'), '60%', '', '3', 'center', '');

	/* get the valid thold templates
	 * remove those hosts that do not have any valid templates
	 */
	$templates  = '';
	$found_list = '';
	$not_found  = '';

	$data_template_id = db_fetch_cell_prepared('SELECT dtr.data_template_id
		 FROM data_template_rrd AS dtr
		 LEFT JOIN graph_templates_item AS gti
		 ON gti.task_item_id=dtr.id
		 LEFT JOIN graph_local AS gl
		 ON gl.id=gti.local_graph_id
		 WHERE gl.id = ?', array($local_graph_id));

	if ($data_template_id != '') {
		if (sizeof(db_fetch_assoc_prepared('SELECT id FROM thold_template WHERE data_template_id = ?', array($data_template_id)))) {
			$found_list .= '<li>' . get_graph_title($local_graph_id) . '</li>';
			if (strlen($templates)) {
				$templates .= ", $data_template_id";
			}else{
				$templates  = "$data_template_id";
			}
		}else{
			$not_found .= '<li>' . get_graph_title($local_graph_id) . '</li>';
		}
	}else{
		$not_found .= '<li>' . get_graph_title($local_graph_id) . '</li>';
	}

	if (strlen($templates)) {
		$sql = 'SELECT id, name FROM thold_template WHERE data_template_id IN (' . $templates . ') ORDER BY name';
	}else{
		$sql = 'SELECT id, name FROM thold_template ORDER BY name';
	}

	print "<tr><td colspan='2' class='textArea'>\n";

	if (strlen($found_list)) {
		if (strlen($not_found)) {
			print '<p>' . __('The following Graph has no Threshold Templates associated with them.', 'thold') . '</p>';
			print '<ul>' . $not_found . '</ul>';
		}

		print '<p>' . __('Press \'Continue\' after you have selected the Threshold Template to utilize.', 'thold') . '
			<ul>' . $found_list . "</ul>
			</td>
		</tr>\n";

		if (isset_request_var('tree_id')) {
			get_filter_request_var('tree_id');
		}else{
			set_request_var('tree_id', '');
		}

		if (isset_request_var('leaf_id')) {
			get_filter_request_var('leaf_id');
		}else{
			set_request_var('leaf_id', '');
		}

		$form_array = array(
			'general_header' => array(
				'friendly_name' => __('Available Threshold Templates', 'thold'),
				'method' => 'spacer',
			),
			'thold_template_id' => array(
				'method' => 'drop_sql',
				'friendly_name' => __('Select a Threshold Template', 'thold'),
				'description' => '',
				'none_value' => __('None', 'thold'),
				'value' => __('None', 'thold'),
				'sql' => $sql
			),
			'usetemplate' => array(
				'method' => 'hidden',
				'value' => 1
			),
			'local_graph_id' => array(
				'method' => 'hidden',
				'value' => $local_graph_id
			),
			'host_id' => array(
				'method' => 'hidden',
				'value' => $host_id
			)
		);

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
				)
			);
	}else{
		if (strlen($not_found)) {
			print '<p>' . __('There are no Threshold Templates associated with the following Graph.', 'thold') . '</p>';
			print '<ul>' . $not_found . '</ul>';
		}

		$form_array = array(
			'general_header' => array(
				'friendly_name' => __('Please select an action', 'thold'),
				'method' => 'spacer',
			),
			'doaction' => array(
				'method' => 'drop_array',
				'friendly_name' => __('Threshold Action', 'thold'),
				'description' => __('You may either create a new Threshold Template, or an non-templated Threshold from this screen.', 'thold'),
				'value' => 'None',
				'array' => array(1 => __('Create a new Threshold', 'thold'), 2 => __('Create a Threshold Template', 'thold'))
			),
			'usetemplate' => array(
				'method' => 'hidden',
				'value' => 1
			),
			'local_graph_id' => array(
				'method' => 'hidden',
				'value' => $local_graph_id
			)
		);

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);
	}

	if (!strlen($not_found)) {
		$save_html = "<input type='submit' value='" . __esc('Continue', 'thold') . "'>";

		print "<tr>
			<td colspan='2' class='saveRow'>
				<input type='hidden' id='action' value='actions'>
				<input id='cancel' type='button' value='" . __esc('Cancel', 'thold'). "' title='" . __esc('Cancel', 'thold') . "'>
				$save_html
			</td>
		</tr>\n";
	} else {
		$save_html = "<input type='submit' value='" . __esc('Continue', 'thold') . "'>";

		print "<tr>
			<td colspan='2' class='saveRow'>
				<input id='cancel' type='button' value='" . __esc('Cancel', 'thold') . "' title='" . __esc('Cancel', 'thold') . "'>
				$save_html
			</td>
		</tr>\n";
	}

	html_end_box();

	form_end(false);

	if (isset($_SERVER['HTTP_REFERER'])) {
		$backto = $_SERVER['HTTP_REFERER'];
	}else{
		$backto = $config['url_path'] . 'plugins/thold/thold.php';
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$('#cancel').click(function() {
			document.location = '<?php print $backto;?>';
		});

		$('#tholdform').submit(function(event) {
			event.preventDefault();
			strURL = $(this).attr('action');
			if ($('#thold_template_id').length && $('#thold_template_id').val() > 0) {
				json =  $('#tholdform').serializeObject();
				$.post(strURL, json).done(function(data) {
					document.location = '<?php print $backto;?>';
				});
			}else if ($('#doaction').length) {
				strURL += (strURL.indexOf('?') >- 0 ? '&':'?');
				strURL += 'doaction='+$('#doaction').val();
				strURL += '&local_graph_id='+$('#local_graph_id').val();
				strURL += '&host_id='+$('#host_id').val();
				strURL += '&usetemplate=1'+$('#usetemplate').val();
				document.location = strURL;
			}else{
				strURL += (strURL.indexOf('?') >- 0 ? '&':'?');
				strURL += 'thold_template_id='+$('#thold_template_id').val();
				strURL += '&local_graph_id='+$('#local_graph_id').val();
				strURL += '&host_id='+$('#host_id').val();
				strURL += '&usetemplate=1'+$('#usetemplate').val();
				document.location = strURL;
			}
		});
	});
	</script>
	<?php

	bottom_footer();
}

function thold_add_graphs_action_array($action) {
	$action['plugin_thold_create'] = __('Create Threshold from Template', 'thold');

	return $action;
}

function thold_add_select_host() {
	global $config;

	$host_id              = get_filter_request_var('host_id');
	$local_graph_id       = get_filter_request_var('local_graph_id');
	$data_template_rrd_id = get_filter_request_var('data_template_rrd_id');

	$hosts = get_allowed_devices();

	top_header();

	form_start('thold.php?action=save', 'tholdform');

	html_start_box(__('Threshold Creation Wizard', 'thold'), '50%', '', '3', 'center', '');

	if ($host_id == '') {
		print '<tr><td class="center">' . __('Please select a Device', 'thold') . '</td></tr>';
	} else if ($local_graph_id == '') {
		print '<tr><td class="center">' . __('Please select a Graph', 'thold') . '</td></tr>';
	} else if ($data_template_rrd_id == '') {
		print '<tr><td class="center">' . __('Please select a Data Source', 'thold') . '</td></tr>';
	} else {
		print '<tr><td class="center">' . __('Please press \'Create\' to activate your Threshold', 'thold') . '</td></tr>';
	}

	html_end_box();

	html_start_box('', '50%', '', '3', 'center', '');

	/* display the host dropdown */
	?>
	<tr><td><table class='filterTable' align='center'>
		<tr>
			<?php print html_host_filter(get_request_var('host_id'));?>
		</tr><?php

	if ($host_id != '') {
		$graphs = get_allowed_graphs('gl.host_id=' . $host_id);

		?>
		<tr>
			<td>
				<?php print __('Graph', 'thold');?>
			</td>
			<td>
				<select id='local_graph_id' name='local_graph_id' onChange='applyFilter("graph")'>
					<option value=''></option><?php
					foreach ($graphs as $row) {
						echo "<option value='" . $row['local_graph_id'] . "'" . ($row['local_graph_id'] == $local_graph_id ? ' selected' : '') . '>' . htmlspecialchars($row['title_cache'], ENT_QUOTES) . '</option>';
					}?>
				</select>
			</td>
		</tr><?php
	} else {
		?>
		<tr>
			<td>
				<input type='hidden' id='local_graph_id' name='local_graph_id' value=''>
			</td>
		</tr><?php
	}

	if ($local_graph_id != '') {
		$dt_sql = 'SELECT DISTINCT dtr.local_data_id
			FROM data_template_rrd AS dtr
			LEFT JOIN graph_templates_item AS gti
			ON gti.task_item_id=dtr.id
			LEFT JOIN graph_local AS gl
			ON gl.id=gti.local_graph_id
			WHERE gl.id = ' . $local_graph_id;

		$local_data_id = db_fetch_cell($dt_sql);

		$dss = db_fetch_assoc('SELECT DISTINCT id, data_source_name
			FROM data_template_rrd
			WHERE local_data_id IN (' . $dt_sql . ') ORDER BY data_source_name');

		/* show the data source options */
		?>
		<tr>
			<td>
				<?php print __('Data Source', 'thold');?>
			</td>
			<td>
				<input type='hidden' id='local_data_id' name='local_data_id' value='<?php print $local_data_id;?>'>
				<select id='data_template_rrd_id' name='data_template_rrd_id' onChange='applyFilter("ds")'>
					<option value=''></option><?php
					foreach ($dss as $row) {
						echo "<option value='" . $row['id'] . "'" . ($row['id'] == $data_template_rrd_id ? ' selected' : '') . '>' . htmlspecialchars($row['data_source_name'], ENT_QUOTES) . '</option>';
					}?>
				</select>
			</td>
		</tr></table></td></tr><?php
	} else {
		?>
		<tr>
			<td>
				<input type='hidden' id='data_template_rrd_id' name='data_template_rrd_id' value=''>
			</td>
		</tr></table></td></tr><?php
	}

	if ($data_template_rrd_id != '') {
		echo "<tr><td class='center' colspan='2'><input type='hidden' name='save' id='save' value='save'><input id='go' type='button' value='" . __esc('Create', 'thold') . "' title='" . __esc('Create Threshold', 'thold') . "'></td></tr>";
	} else {
		echo "<tr><td class='center' colspan='2'></td></tr>";
	}

	html_end_box();

	form_end();

	html_start_box('', '50%', '', '3', 'center', '');

	if ($local_graph_id != '') {
		print "<tr><td style='text-align:center'><img id='graphi' src='../../graph_image.php?local_graph_id=$local_graph_id&rra_id=0'></td></tr>";
	}

	html_end_box();

	?>
	<script type='text/javascript'>

	function applyFilter(target) {
		strURL = 'thold.php?action=add&header=false&host_id=' + $('#host_id').val();
		if (target != 'host_id') {
			strURL += '&local_graph_id=' + $('#local_graph_id').val();
		}
		if (target == 'ds') {
			strURL += '&data_template_rrd_id=' + $('#data_template_rrd_id').val();
		}
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#go').button().click(function(event) {
			event.preventDefault();
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
}
