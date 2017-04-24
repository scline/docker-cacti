<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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

function plugin_monitor_install () {
	/* core plugin functionality */
	api_plugin_register_hook('monitor', 'top_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_refresh', 'monitor_top_graph_refresh', 'setup.php');

	api_plugin_register_hook('monitor', 'draw_navigation_text', 'monitor_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('monitor', 'config_form', 'monitor_config_form', 'setup.php');
	api_plugin_register_hook('monitor', 'config_settings', 'monitor_config_settings', 'setup.php');
	api_plugin_register_hook('monitor', 'poller_bottom', 'monitor_poller_bottom', 'setup.php');

	/* device actions and interaction */
	api_plugin_register_hook('monitor', 'api_device_save', 'monitor_api_device_save', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_array', 'monitor_device_action_array', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_execute', 'monitor_device_action_execute', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_prepare', 'monitor_device_action_prepare', 'setup.php');
	api_plugin_register_hook('monitor', 'device_remove', 'monitor_device_remove', 'setup.php');

	/* add new filter for device */
	api_plugin_register_hook('monitor', 'device_filters', 'monitor_device_filters', 'setup.php');
	api_plugin_register_hook('monitor', 'device_sql_where', 'monitor_device_sql_where', 'setup.php');
	api_plugin_register_hook('monitor', 'device_table_bottom', 'monitor_device_table_bottom', 'setup.php');

	api_plugin_register_realm('monitor', 'monitor.php', 'View Monitoring Dashboard', 1);

	monitor_setup_table();
}

function monitor_device_filters($filters) {

	$filters['criticality'] = array(
		'filter' => FILTER_VALIDATE_INT,
		'pageset' => true,
		'default' => '-1'
	);

	return $filters;
}

function monitor_device_sql_where($sql_where) {
	if (get_request_var('criticality') >= 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' monitor_criticality = ' . get_request_var('criticality');
	}

	return $sql_where;
}

function monitor_device_table_bottom() {
	$criticalities = array(
		'-1' => __('Any'),
		'0'  => __('None'),
		'1'  => __('Low'),
		'2'  => __('Medium'),
		'3'  => __('High'),
		'4'  => __('Mission Critical')
	);

	$select = '<td>' . __('Criticality') . '</td><td><select id="criticality">';
	foreach($criticalities as $index => $crit) {
		if ($index == get_request_var('criticality')) {
			$select .= '<option selected value="' . $index . '">' . $crit . '</option>';
		}else{
			$select .= '<option value="' . $index . '">' . $crit . '</option>';
		}
	}
	$select .= '</select></td>';

    ?>
    <script type='text/javascript'>
	$(function() {
		$('#rows').parent().after('<?php print $select;?>');
		<?php if (get_selected_theme() != 'classic') {?>
		$('#criticality').selectmenu({
			change: function() { 
				applyFilter(); 
			}
		});
		<?php } else { ?>
		$('#criticality').change(function() {
			applyFilter(); 
		});
		<?php } ?>
	});

	applyFilter = function() {
		strURL  = 'host.php?host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&site_id=' + $('#site_id').val();
		strURL += '&criticality=' + $('#criticality').val();
		strURL += '&poller_id=' + $('#poller_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	};

	</script>
	<?php
}

function plugin_monitor_uninstall () {
	db_execute('DROP TABLE IF EXISTS plugin_monitor_notify_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_reboot_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_uptime');
}

function plugin_monitor_check_config () {
	global $config;
	// Here we will check to ensure everything is configured
	monitor_check_upgrade ();

	include_once($config['library_path'] . '/database.php');
	$r = read_config_option('monitor_refresh');
	$result = db_fetch_assoc("SELECT * FROM settings WHERE name='monitor_refresh'");
	if (!isset($result[0]['name'])) {
		$r = NULL;
	}

	if ($r == '' or $r < 1 or $r > 300) {
		if ($r == '') {
			$sql = "REPLACE INTO settings VALUES ('monitor_refresh','300')";
		} else if ($r == NULL) {
			$sql = "INSERT INTO settings VALUES ('monitor_refresh','300')";
		} else {
			$sql = "UPDATE settings SET value = '300' WHERE name = 'monitor_refresh'";
		}

		$result = db_execute($sql);
		kill_session_var('sess_config_array');
	}

	return true;
}

function plugin_monitor_upgrade () {
	// Here we will upgrade to the newest version
	monitor_check_upgrade ();
	return false;
}

function monitor_check_upgrade () {
	$version = plugin_monitor_version ();
	$current = $version['version'];
	$old     = read_config_option('plugin_monitor_version');
	if ($current != $old) {
		monitor_setup_table ();

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='monitor'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='"    . $version['longname'] . "', 
			author='"  . $version['author'] . "', 
			webpage='" . $version['homepage'] . "' 
			WHERE directory='" . $version['name'] . "' ");
	}
}

function plugin_monitor_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/monitor/INFO', true);
	return $info['info'];
}

function monitor_device_action_execute($action) {
	global $config, $fields_host_edit;

	if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
		return $action;
	}

	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		if ($action == 'monitor_enable' || $action == 'monitor_disable') {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				if ($action == 'monitor_enable') {
					db_execute("UPDATE host SET monitor='on' WHERE id='" . $selected_items[$i] . "'");
				}else if ($action == 'monitor_disable') {
					db_execute("UPDATE host SET monitor='' WHERE id='" . $selected_items[$i] . "'");
				}
			}
		}else{
			for ($i = 0; ($i < count($selected_items)); $i++) {
				reset($fields_host_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset_request_var("t_$field_name")) {
						if ($field_name == 'monitor_alert_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($selected_items[$i]));
							if ($cur_time > 0) {
								db_execute_prepared("UPDATE host SET monitor_alert = CEIL(avg_time*?) WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
							}
						}elseif ($field_name == 'monitor_warn_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($selected_items[$i]));
							if ($cur_time > 0) {
								db_execute_prepared("UPDATE host SET monitor_warn = CEIL(avg_time*?) WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
							}
						}else{
							db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
						}
					}
				}
			}
		}
	}

	return $action;
}

function monitor_device_remove($devices) {
	db_execute('DELETE FROM plugin_monitor_notify_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_reboot_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_uptime WHERE host_id IN(' . implode(',', $devices) . ')');

	return $devices;
}

function monitor_device_action_prepare($save) {
	global $host_list, $fields_host_edit;

	$action = $save['drp_action'];

	if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
		return $save;
	}

	if ($action == 'monitor_enable' || $action == 'monitor_disable') {
		if ($action == 'monitor_enable') {
			$action_description = 'enable';
		} else if ($action == 'monitor_disable') {
			$action_description = 'disable';
		}

		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to %s monitoring on these Device(s)', $action_description) . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
			</td>
		</tr>";
	} else {
		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to Change the Monitoring settings for the following Device(s). Remember to check \'Update this Field\' to indicate which columns to update.') . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
			</td>
		</tr>";

		$form_array = array();
		$fields = array(
			'monitor', 
			'monitor_text', 
			'monitor_criticality', 
			'monitor_warn', 
			'monitor_alert', 
			'monitor_warn_baseline', 
			'monitor_alert_baseline'
		);

		foreach($fields as $field) {
			$form_array += array($field => $fields_host_edit[$field]);

			$form_array[$field]['value'] = '';
			$form_array[$field]['form_id'] = 0;
			$form_array[$field]['sub_checkbox'] = array(
				'name' => 't_' . $field,
				'friendly_name' => __('Update this Field'),
				'value' => ''
			);
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);
	}
}

function monitor_device_action_array($device_action_array) {
	$device_action_array['monitor_settings'] = __('Change Monitoring Options');
	$device_action_array['monitor_enable']   = __('Enable Monitoring');
	$device_action_array['monitor_disable']  = __('Disable Monitoring');

	return $device_action_array;
}

function monitor_scan_dir() {
	global $config;

	$ext   = array('.wav', '.mp3');
	$d     = dir($config['base_path'] . '/plugins/monitor/sounds/');
	$files = array('None' => 'None');

	while (false !== ($entry = $d->read())) {
		if ($entry != '.' && $entry != '..' && in_array(strtolower(substr($entry,-4)),$ext)) {
			$files[$entry] = $entry;
		}
	}
	$d->close();

	return $files;
}

function monitor_config_settings() {
	global $tabs, $settings, $criticalities, $page_refresh_interval, $config, $settings_user, $tabs_graphs;

	include_once($config['base_path'] . '/lib/reports.php');
 
	$formats = reports_get_format_files();

	$criticalities = array(
		0 => __('Disabled'),
		1 => __('Low'),
		2 => __('Medium'),
		3 => __('High'),
		4 => __('Mission Critical')
	);

	$log_retentions = array(
		'-1'  => __('Indefinately'),
		'31'  => __('%d Month', 1),
		'62'  => __('%d Months', 2),
		'93'  => __('%d Months', 3),
		'124' => __('%d Months', 4),
		'186' => __('%d Months', 6),
		'365' => __('%d Year', 1)
	);

	$tabs_graphs += array('monitor' => 'Monitor Settings');

	$settings_user += array(
		'monitor' => array(
			'monitor_sound' => array(
				'friendly_name' => __('Alarm Sound'),
				'description' => __('This is the sound file that will be played when a Device goes down.'),
				'method' => 'drop_array',
				'array' => monitor_scan_dir(),
				'default' => 'attn-noc.wav',
			),
			'monitor_legend' => array(
				'friendly_name' => __('Show Icon Legend'),
				'description' => __('Check this to show an icon legend on the Monitor display'),
				'method' => 'checkbox',
			)
		)
	);

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php') {
		return;
	}

	$tabs['monitor'] = 'Monitor';

	$temp = array(
		'monitor_header' => array(
			'friendly_name' => __('Monitor Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_log_storage' => array(
			'friendly_name' => __('Notification/Reboot Log Retention'),
			'description' => __('Keep Notification and Reboot Logs for this number of days.'),
			'method' => 'drop_array',
			'default' => '31',
			'array' => $log_retentions
		),
		'monitor_sound' => array(
			'friendly_name' => __('Alarm Sound'),
			'description' => __('This is the sound file that will be played when a Device goes down.'),
			'method' => 'drop_array',
			'array' => monitor_scan_dir(),
			'default' => 'attn-noc.wav',
		),
		'monitor_warn_criticality' => array(
			'friendly_name' => __('Warning Latency Notification'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Warning Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_alert_criticality' => array(
			'friendly_name' => __('Alert Latency Notification'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Alert Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_format_file' => array(
			'friendly_name' => __('Format File to Use'),
			'method' => 'drop_array',
			'default' => 'default.format',
			'description' => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.
			These files are located in the \'formats\' directory.'),
			'array' => $formats
		),
		'monitor_resend_frequency' => array(
			'friendly_name' => __('How Often to Resend Emails'),
			'description' => __('How often should emails notifications be sent to subscribers for these hosts if they are exceeding their latency thresholds'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'   => __('Every Occurrence'),
				'20'  => __('Every %d Minutes', 20),
				'30'  => __('Every %d Minutes', 30),
				'60'  => __('Every Hour'),
				'120' => __('Every %d Hours', 2),
				'240' => __('Every %d Hours', 4)
			)
		),
		'monitor_refresh' => array(
			'friendly_name' => __('Refresh Interval'),
			'description' => __('This is the time in seconds before the page refreshes.  (1 - 300)'),
			'method' => 'drop_array',
			'default' => '60',
			'array' => $page_refresh_interval
		),
		'monitor_legend' => array(
			'friendly_name' => __('Show Icon Legend'),
			'description' => __('Check this to show an icon legend on the Monitor display'),
			'method' => 'checkbox',
		),
		'monitor_grouping' => array(
			'friendly_name' => __('Grouping'),
			'description' => __('This is how monitor will Group Devices.'),
			'method' => 'drop_array',
			'default' => __('Default'),
			'array' => array(
				'default'                  => __('Default'),
				'default_by_permissions'   => __('Default with permissions'),
				'group_by_tree'            => __('Tree'),
				'group_by_device_template' => __('Device Template'),
			)
		),
		'monitor_view' => array(
			'friendly_name' => __('View'),
			'description' => __('This is how monitor will render Devices.'),
			'method' => 'drop_array',
			'default' => __('Default'),
			'array' => array(
				'default'  => __('Default'),
				'tiles'    => __('Tiles'),
				'tilesadt' => __('Tiles & Downtime')
			)
		)
	);

	if (isset($settings['monitor'])) {
		$settings['monitor'] = array_merge($settings['monitor'], $temp);
	} else {
		$settings['monitor'] = $temp;
	}
}

function monitor_top_graph_refresh($refresh) {
	if (basename($_SERVER['PHP_SELF']) != 'monitor.php') {
		return $refresh;
	}

	$r = read_config_option('monitor_refresh');

	if ($r == '' or $r < 1) {
		return $refresh;
	}

	return $r;
}

function monitor_show_tab() {
	global $config;

	monitor_check_upgrade ();

	if (api_user_realm_auth('monitor.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'monitor.php')) {
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor_down.gif" alt="' . __('Monitor') . '" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor.gif" alt="' . __('Monitor') . '" align="absmiddle" border="0"></a>';
		}
	}
}

function monitor_config_form () {
	global $fields_host_edit, $criticalities;

	$baselines = array(
		'0'   => __('Do not Change'),
		'1.20'  => __('%d Percent Above Average', 20),
		'1.30'  => __('%d Percent Above Average', 30),
		'1.40'  => __('%d Percent Above Average', 40),
		'1.50'  => __('%d Percent Above Average', 50),
		'1.60'  => __('%d Percent Above Average', 60),
		'1.70'  => __('%d Percent Above Average', 70),
		'1.80'  => __('%d Percent Above Average', 80),
		'1.90'  => __('%d Percent Above Average', 90),
		'2.00'  => __('%d Percent Above Average', 100),
		'2.20'  => __('%d Percent Above Average', 120),
		'2.40'  => __('%d Percent Above Average', 140),
		'2.50'  => __('%d Percent Above Average', 150),
		'3.00'  => __('%d Percent Above Average', 200),
		'4.00'  => __('%d Percent Above Average', 300),
		'5.00'  => __('%d Percent Above Average', 400),
		'6.00'  => __('%d Percent Above Average', 500)
	);

	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['monitor_header'] = array(
				'friendly_name' => __('Device Monitoring Settings'),
				'method' => 'spacer',
				'collapsible' => 'true'
			);
			$fields_host_edit3['monitor'] = array(
				'method' => 'checkbox',
				'friendly_name' => __('Monitor Device'),
				'description' => __('Check this box to monitor this Device on the Monitor Tab.'),
				'value' => '|arg1:monitor|',
				'default' => '',
				'form_id' => false
			);
			$fields_host_edit3['monitor_criticality'] = array(
				'friendly_name' => __('Device Criticality'),
				'description' => __('What is the Criticality of this Device.'),
				'method' => 'drop_array',
				'array' => $criticalities,
				'value' => '|arg1:monitor_criticality|',
				'default' => '0',
			);
			$fields_host_edit3['monitor_warn'] = array(
				'friendly_name' => __('Ping Warning Threshold'),
				'description' => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log a warning or send email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => 'milliseconds',
				'value' => '|arg1:monitor_warn|',
				'default' => '',
			);
			$fields_host_edit3['monitor_alert'] = array(
				'friendly_name' => __('Ping Alert Threshold'),
				'description' => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log an alert or send an email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => 'milliseconds',
				'value' => '|arg1:monitor_alert|',
				'default' => '',
			);
			$fields_host_edit3['monitor_warn_baseline'] = array(
				'friendly_name' => __('Re-Baseline Warning'),
				'description' => __('The percentage above the current average ping time to consider a Warning Threshold.  If updated, this will automatically adjust the Ping Warning Threshold.'),
				'method' => 'drop_array',
				'default' => '0',
				'value' => '0',
				'array' => $baselines
			);
			$fields_host_edit3['monitor_alert_baseline'] = array(
				'friendly_name' => __('Re-Baseline Alert'),
				'description' => __('The percentage above the current average ping time to consider a Alert Threshold.  If updated, this will automatically adjust the Ping Alert Threshold.'),
				'method' => 'drop_array',
				'default' => '0',
				'value' => '0',
				'array' => $baselines
			);
			$fields_host_edit3['monitor_text'] = array(
				'friendly_name' => __('Down Device Message'),
				'description' => __('This is the message that will be displayed when this Device is reported as down.'),
				'method' => 'textarea',
				'max_length' => 1000,
				'textarea_rows' => 2,
				'textarea_cols' => 80,
				'value' => '|arg1:monitor_text|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function monitor_api_device_save($save) {
	if (isset_request_var('monitor')) {
		$save['monitor'] = form_input_validate(get_nfilter_request_var('monitor'), 'monitor', '', true, 3);
	} else {
		$save['monitor'] = form_input_validate('', 'monitor', '', true, 3);
	}

	if (isset_request_var('monitor_text')) {
		$save['monitor_text'] = form_input_validate(get_nfilter_request_var('monitor_text'), 'monitor_text', '', true, 3);
	} else {
		$save['monitor_text'] = form_input_validate('', 'monitor_text', '', true, 3);
	}

	if (isset_request_var('monitor_criticality')) {
		$save['monitor_criticality'] = form_input_validate(get_nfilter_request_var('monitor_criticality'), 'monitor_criticality', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_criticality'] = form_input_validate('', 'monitor_criticality', '', true, 3);
	}

	if (isset_request_var('monitor_warn')) {
		$save['monitor_warn'] = form_input_validate(get_nfilter_request_var('monitor_warn'), 'monitor_warn', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_warn'] = form_input_validate('', 'monitor_warn', '', true, 3);
	}

	if (isset_request_var('monitor_alert')) {
		$save['monitor_alert'] = form_input_validate(get_nfilter_request_var('monitor_alert'), 'monitor_alert', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_alert'] = form_input_validate('', 'monitor_alert', '', true, 3);
	}

	if (!isempty_request_var('monitor_alert_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($save['id']));
		if ($cur_time > 0) {
			$save['monitor_alert'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	if (!isempty_request_var('monitor_warn_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($save['id']));
		if ($cur_time > 0) {
			$save['monitor_warn'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	return $save;
}

function monitor_draw_navigation_text ($nav) {
   $nav['monitor.php:'] = array('title' => __('Monitoring'), 'mapping' => '', 'url' => 'monitor.php', 'level' => '1');

   return $nav;
}

function monitor_setup_table() {
	if (!db_table_exists('plugin_monitor_notify_history')) {
		db_execute("CREATE TABLE plugin_monitor_notify_history (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			host_id int(10) unsigned DEFAULT NULL,
			notify_type tinyint(3) unsigned DEFAULT NULL,
			ping_time double DEFAULT NULL,
			ping_threshold int(10) unsigned DEFAULT NULL,
			notification_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			notes varchar(255) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_key (host_id,notify_type,notification_time)) 
			ENGINE=InnoDB 
			COMMENT='Stores Notification Event History'");
	}

	if (!db_table_exists('plugin_monitor_reboot_history')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_reboot_history (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			host_id int(10) unsigned DEFAULT NULL,
			reboot_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			log_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY host_id (host_id),
			KEY log_time (log_time),
			KEY reboot_time (reboot_time))
			ENGINE=InnoDB 
			COMMENT='Keeps Track of Device Reboot Times'");
	}

	if (!db_table_exists('plugin_monitor_uptime')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_uptime (
			host_id int(10) unsigned DEFAULT '0',
			uptime int(10) unsigned DEFAULT '0',
			PRIMARY KEY (host_id),
			KEY uptime (uptime)) 
			ENGINE=InnoDB 
			COMMENT='Keeps Track of the Devices last uptime to track agent restarts and reboots'");
	}

	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor', 'type' => 'char(3)', 'NULL' => false, 'default' => 'on', 'after' => 'disabled'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_text', 'type' => 'varchar(1024)', 'default' => '', 'NULL' => false, 'after' => 'monitor'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_criticality', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'monitor_text'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_warn', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_criticality'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_alert', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_warn'));
}

function monitor_poller_bottom() {
	global $config;

	include_once($config['library_path'] . '/poller.php');

    $command_string = trim(read_config_option('path_php_binary'));

    if (trim($command_string) == '') {
        $command_string = 'php';
	}

    $extra_args = ' -q ' . $config['base_path'] . '/plugins/monitor/poller_monitor.php > /tmp/monitor.php';

    exec_background($command_string, $extra_args);
}
