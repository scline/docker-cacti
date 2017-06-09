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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

define('NL', "\n");

set_default_action();

/* Record Start Time */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

$criticalities = array(
	0 => __('Disabled'),
	1 => __('Low'),
	2 => __('Medium'),
	3 => __('High'),
	4 => __('Mission Critical')
);

$iclasses = array(
	0 => 'deviceUnknown',
	1 => 'deviceDown',
	2 => 'deviceRecovering',
	3 => 'deviceUp',
	4 => 'deviceThreshold',
	5 => 'deviceDownMuted',
	6 => 'deviceUnmonitored',
	7 => 'deviceWarning',
	8 => 'deviceAlert',
);

$icolorsdisplay = array(
	0 => __('Unknown'),
	1 => __('Down'),
	2 => __('Recovering'), 
	3 => __('Up'), 
	4 => __('Triggered'), 
	5 => __('Down (Muted/Acked)'),
	6 => __('No Availability Check'),
	7 => __('Warning Ping'),
	8 => __('Alert Ping'),
);

$classes = array(
	'monitor_exsmall' => __('Extra Small'),
	'monitor_small'   => __('Small'),
	'monitor_medium'  => __('Medium'),
	'monitor_large'   => __('Large')
);

global $thold_alerts, $thold_hosts; 

if (!isset($_SESSION['muted_hosts'])) {
	$_SESSION['muted_hosts'] = array();
}

validate_request_vars(true);

check_tholds();

switch(get_nfilter_request_var('action')) {
	case 'ajax_status':
		ajax_status();
		break;
	case 'ajax_mute_all':
		mute_all_hosts();
		draw_page();
		break;
	case 'ajax_unmute_all':
		unmute_all_hosts();
		draw_page();
		break;
	case 'save':
		save_settings();
		break;
	default:
		draw_page();
}

exit;

function draw_page() {
	global $config, $iclasses, $icolorsdisplay;

	find_down_hosts();

	general_header();

	draw_filter_and_status();

	if (file_exists($config['base_path'] . '/plugins/monitor/themes/' . get_selected_theme() . '/monitor.css')) {
		print "<link href='" . $config['url_path'] . "plugins/monitor/themes/" . get_selected_theme() . "/monitor.css' type='text/css' rel='stylesheet' />\n";
	}else{
		print "<link href='" . $config['url_path'] . "plugins/monitor/monitor.css' type='text/css' rel='stylesheet' />\n";
	}

	print "<div class='center monitor'>\n";

	// Default with permissions = default_by_permissions
	// Tree  = group_by_tree
	$function = 'render_' . get_request_var('grouping');
	if (function_exists($function)) {
		print $function();
	} else {
		print render_default();
	}

	print '</div>';

	if (read_user_setting('monitor_legend', read_config_option('monitor_legend'))) {
		print "<div class='center monitor_legend'><table class='center'><tr>\n";
		foreach($iclasses as $index => $class) {
			print "<td class='center $class" . "Bg' style='width:11%;'>" . $icolorsdisplay[$index] . "</td>\n";
		}
		print "</tr></table></div>\n";
	}

	// If the host is down, we need to insert the embedded wav file
	$monitor_sound = get_monitor_sound();
	if (is_monitor_audible()) {
		print "<audio id='audio' loop autostart='0' src='" . htmlspecialchars($config['url_path'] . "plugins/monitor/sounds/" . $monitor_sound) . "'></audio>\n";
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds=99999999;
	var myTimer;

	function timeStep() {
		value = $('#timer').html() - 1;

		if (value <= 0) {
			applyFilter();
		} else {
			$('#timer').html(value);
			// What is a second, well if you are an 
			// emperial storm tropper, it's just a little more than a second.
			myTimer = setTimeout(timeStep, 1284);
		}
	}

	function muteUnmuteAudio(mute) {
		if (mute) {
			$('audio').each(function(){
				this.pause(); 
				this.currentTime = 0; 
			}); 
		} else if ($('#downhosts').val() == 'true') {
			$('audio').each(function(){
				this.play(); 
			}); 
		}
	}

	function closeTip() {
		$(document).tooltip('close');
	}

	function applyFilter() {
		clearTimeout(myTimer);
		$('.fa-server, .fa-first-order').unbind();

		strURL  = 'monitor.php?header=false';
		strURL += '&refresh='+$('#refresh').val();
		strURL += '&grouping='+$('#grouping').val();
		strURL += '&tree='+$('#tree').val();
		strURL += '&view='+$('#view').val();
		strURL += '&crit='+$('#crit').val();
		strURL += '&size='+$('#size').val();
		strURL += '&mute='+$('#mute').val();
		strURL += '&status='+$('#status').val();

		loadPageNoHeader(strURL);
	}

	function saveFilter() {
		url='monitor.php?action=save' +
			'&refresh='  + $('#refresh').val() +
			'&grouping=' + $('#grouping').val() +
			'&tree='     + $('#tree').val() +
			'&view='     + $('#view').val() +
			'&crit='     + $('#crit').val() +
			'&size='     + $('#size').val() +
			'&status='   + $('#status').val();

		$.get(url, function(data) {
			$('#text').show().text('Filter Settings Saved').fadeOut(2000);
		});
	}

	function setupTooltips() {
	}

	$('#go').click(function() {
		applyFilter();
	});

	$('#sound').click(function() {
		if ($('#mute').val() == 'false') {
			$('#mute').val('true');
			muteUnmuteAudio(true);
			$('#sound').val('<?php print get_unmute_text();?>');
			loadPageNoHeader('monitor.php?header=false&action=ajax_mute_all');
		} else {
			$('#mute').val('false');
			muteUnmuteAudio(false);
			$('#sound').val('<?php print get_mute_text();?>');
			loadPageNoHeader('monitor.php?header=false&action=ajax_unmute_all');
		}
	});

	$('#refresh, #view, #crit, #grouping, #size, #status, #tree').change(function() {
		applyFilter();
	});

	$('#save').click(function() {
		saveFilter();
	});

	$(function() {
		// Clear the timeout to keep countdown accurate
		clearTimeout(myTimer);

		// Servers need tooltips
		$(document).tooltip({
			items: '.fa-server, .fa-first-order',
			open: function(event, ui) {
				if (typeof(event.originalEvent) == 'undefined') {
					return false;
				}

				var $id = $(ui.tooltip).attr('id');

				$('div.ui-tooltip').not('#'+ $id).remove();
			},
			close: function(event, ui) {
				ui.tooltip.hover(
				function () {
					$(this).stop(true).fadeTo(400, 1);
				},
				function() {
					$(this).fadeOut('400', function() {
						$(this).remove();
					});
				});
			},
			position: {my: "left:15 top", at: "right center"},
			content: function(callback) {
				var id = $(this).attr('id');
				$.get('monitor.php?action=ajax_status&id='+id, function(data) {
					callback(data);
				});
			}
		});

		// Start the countdown
		myTimer = setTimeout(timeStep, 1000);

		// Attempt to reposition the tooltips on resize
		$(window).resize(function() {
			$(document).tooltip('option', 'position', {my: "1eft:15 top", at: "right center"});
		});

		if ($('#mute').val() == 'true') {
			muteUnmuteAudio(true);
		} else {
			muteUnmuteAudio(false);
		}

		$('#main').css('margin-right', '15px');
	});

	</script>
	<?php

	bottom_footer();
}

function is_monitor_audible() {
	$sound = get_monitor_sound();
	if ($sound != '' && $sound != __('None')) {
		return true;
	} else {
		return false;
	}
}

function get_monitor_sound() {
	return read_user_setting('monitor_sound', read_config_option('monitor_sound'));
}

function find_down_hosts() {
	$dhosts = get_hosts_down_by_permission();
	if (sizeof($dhosts)) {
		set_request_var('downhosts', 'true');
		if (isset($_SESSION['muted_hosts'])) {
			$unmuted_hosts = array_diff($dhosts, $_SESSION['muted_hosts']);
			if (sizeof($unmuted_hosts)) {
				set_request_var('mute', 'false');
			}
		} else {
			set_request_var('mute', 'false');
		}
	} else {
		$_SESSION['muted_hosts'] = array();
		set_request_var('mute', 'false');
		set_request_var('downhosts', 'false');
	}
}

function mute_all_hosts() {
	$_SESSION['muted_hosts'] = get_hosts_down_by_permission();
	set_request_var('mute', 'true');
}

function unmute_all_hosts() {
	$_SESSION['muted_hosts'] = array();
	set_request_var('mute', 'false');
}

function check_tholds() {
	global $thold_alerts, $thold_hosts;

	if (api_plugin_is_enabled('thold')) {
		$thold_alerts = array();
		$thold_hosts  = array();

		$result = db_fetch_assoc('SELECT rra_id FROM thold_data WHERE thold_alert > 0 AND thold_enabled = "on"', FALSE);

		if (count($result)) {
			foreach ($result as $row) {
				$thold_alerts[] = $row['rra_id'];
			}

			if (count($thold_alerts) > 0) {
				$result = db_fetch_assoc('SELECT id, host_id FROM data_local');

				foreach ($result as $h) {
					if (in_array($h['id'], $thold_alerts)) {
						$thold_hosts[] = $h['host_id'];
					}
				}
			}
		}
	}
}

function get_filter_text() {
	$filter = '';

	switch(get_request_var('status')) {
	case '-1':
		$filter = __('All Monitored Devices');
		break;
	case '0':
		$filter = __('Monitored Devices either Down or Recovering');
		break;
	case '1':
		$filter = __('Monitored Devices either Down, Recovering, with Breached Thresholds');
		break;
	}

	switch(get_request_var('crit')) {
	case '0':
		$filter .= __(', and All Criticalities');
		break;
	case '1':
		$filter .= __(', and of Low Criticality or Higher');
		break;
	case '2':
		$filter .= __(', and of Medium Criticality or Higher');
		break;
	case '3':
		$filter .= __(', and of High Criticality or Higher');
		break;
	case '4':
		$filter .= __(', and of Mission Critical Status');
		break;
	}

	$filter .= __('<br><b>Remember to first select eligible Devices to be Monitored from the Devices page!</b>');

	return $filter;
}

function draw_filter_and_status() {
	global $criticalities, $page_refresh_interval, $classes;

	print '<div class="center" style="display:table;margin-left:auto;margin-right:auto;"><form>' . NL;

	print '<select id="view" title="' . __('View Type') . '">' . NL;
	print '<option value="default"' . (get_nfilter_request_var('view') == 'default' ? ' selected':'') . '>' . __('Default') . '</option>';
	print '<option value="tiles"' . (get_nfilter_request_var('view') == 'tiles' ? ' selected':'') . '>' . __('Tiles') . '</option>';
	print '<option value="tilesadt"' . (get_nfilter_request_var('view') == 'tilesadt' ? ' selected':'') . '>' . __('Tiles & Time') . '</option>';
	print '</select>' . NL;

	print '<select id="grouping" title="' . __('Device Grouping') . '">' . NL;
	print '<option value="default"' . (get_nfilter_request_var('grouping') == 'default' ? ' selected':'') . '>' . __('Default') . '</option>';
	print '<option value="tree"' . (get_nfilter_request_var('grouping') == 'tree' ? ' selected':'') . '>' . __('Tree') . '</option>';
	print '<option value="template"' . (get_nfilter_request_var('grouping') == 'template' ? ' selected':'') . '>' . __('Device Template') . '</option>';
	print '</select>' . NL;

	if (get_request_var('grouping') == 'tree') {
		$trees = get_allowed_trees();
		if (sizeof($trees)) {
			print '<select id="tree" title="' . __('Select Tree') . '">' . NL;
			print '<option value="-1"' . (get_nfilter_request_var('tree') == '-1' ? ' selected':'') . '>' . __('All Trees') . '</option>';
			foreach($trees as $tree) {
				print "<option value='" . $tree['id'] . "'" . (get_nfilter_request_var('tree') == $tree['id'] ? ' selected':'') . '>' . $tree['name'] . '</option>';
			}
			print '<option value="-2"' . (get_nfilter_request_var('tree') == '-2' ? ' selected':'') . '>' . __('Non-Tree Devices') . '</option>';
			print '</select>' . NL;
		} else {
			print "<input type='hidden' id='tree' value='" . get_request_var('tree') . "'>\n";
		}
	} else {
		print "<input type='hidden' id='tree' value='" . get_request_var('tree') . "'>\n";
	}

	print '<select id="refresh" title="' . __('Refresh Frequency') . '">' . NL;
	foreach($page_refresh_interval as $id => $value) {
		print "<option value='$id'" . (get_nfilter_request_var('refresh') == $id ? ' selected':'') . '>' . $value . '</option>';
	}
	print '</select>' . NL;

	print '<select id="crit" title="' . __('Select Minimum Criticality') . '">' . NL;
	print '<option value="-1"' . (get_nfilter_request_var('crit') == '-1' ? ' selected':'') . '>' . __('All Criticalities') . '</option>';
	foreach($criticalities as $key => $value) {
		if ($key > 0) {
			print "<option value='" . $key . "'" . (get_nfilter_request_var('crit') == $key ? ' selected':'') . '>' . $value . '</option>';
		}
	}
	print '</select>' . NL;

	print '<select id="size" title="' . __('Device Icon Size') . '">' . NL;
	foreach($classes as $id => $value) {
		print "<option value='$id'" . (get_nfilter_request_var('size') == $id ? ' selected':'') . '>' . $value . '</option>';
	}
	print '</select>' . NL;

	print '<select id="status" title="' . __('Device Status') . '">' . NL;
	print '<option value="-1"' . (get_nfilter_request_var('status') == '-1' ? ' selected':'') . '>' . __('All Monitored') . '</option>';
	print '<option value="0"' . (get_nfilter_request_var('status') == '0' ? ' selected':'') . '>' . __('Not Up') . '</option>';
	print '<option value="1"' . (get_nfilter_request_var('status') == '1' ? ' selected':'') . '>' . __('Not Up or Triggered') . '</option>';
	print '</select>' . NL;

	print '<span style="white-space:nowrap;"><input type="button" value="' . __('Refresh') . '" id="go" title="' . __('Refresh the Device List') . '">' . NL;
	print '<input type="button" value="' . __('Save') . '" id="save" title="' . __('Save Filter Settings') . '">' . NL;

	print '<input type="button" value="' . (get_request_var('mute') == 'false' ? get_mute_text():get_unmute_text()) . '" id="sound" title="' . (get_request_var('mute') == 'false' ? __('%s Alert for downed Devices', get_mute_text()):__('%s Alerts for downed Devices', get_unmute_text())) . '">' . NL;
	print '<input id="downhosts" type="hidden" value="' . get_request_var('downhosts') . '"><input id="mute" type="hidden" value="' . get_request_var('mute') . '"></span>' . NL;

	print '</form></div>' . NL;

	// Display the Current Time
	print '<div class="center" style="display:table;margin-left:auto;margin-right:auto;"><span id="text" style="display:none;">Filter Settings Saved</span><br></div>';
	print '<div class="center" style="display:table;margin-left:auto;margin-right:auto;">' . __('Last Refresh: %s', date('g:i:s a', time())) . (get_request_var('refresh') < 99999 ? ', ' . __('Refresh Again in <i id="timer">%d</i> Seconds', get_request_var('refresh')):'') . '</div>';
	print '<div class="center" style="display:table;margin-left:auto;margin-right:auto;">' . get_filter_text() . '</div>';
}

function get_mute_text() {
	if (is_monitor_audible()) {
		return __('Mute');
	} else {
		return __('Acknowledge');
	}
}

function get_unmute_text() {
	if (is_monitor_audible()) {
		return __('Un-Mute');
	} else {
		return __('Reset');
	}
}

function save_settings() {
	validate_request_vars();

	if (sizeof($_REQUEST)) {
		foreach($_REQUEST as $var => $value) {
			switch($var) {
			case 'refresh':
				set_user_setting('monitor_refresh', get_request_var('refresh'));
				break;
			case 'grouping':
				set_user_setting('monitor_grouping', get_request_var('grouping'));
				break;
			case 'view':
				set_user_setting('monitor_view', get_request_var('view'));
				break;
			case 'crit':
				set_user_setting('monitor_crit', get_request_var('crit'));
				break;
			case 'mute':
				set_user_setting('monitor_mute', get_request_var('mute'));
				break;
			case 'size':
				set_user_setting('monitor_size', get_request_var('size'));
				break;
			case 'status':
				set_user_setting('monitor_status', get_request_var('status'));
				break;
			case 'tree':
				set_user_setting('monitor_tree', get_request_var('tree'));
				break;
			}
		}
	}

	validate_request_vars(true);
}

function validate_request_vars($force = false) {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_refresh', read_config_option('monitor_refresh'), $force)
		),
		'mute' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_mute', 'false', $force)
		),
		'grouping' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_grouping', read_config_option('monitor_grouping'), $force)
		),
		'view' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_view', read_config_option('monitor_view'), $force)
		),
		'size' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_size', 'monior_medium', $force)
		),
		'crit' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_crit', '-1', $force)
		),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_status', '-1', $force)
		),
		'tree' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_tree', '-1', $force)
		),
		'id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		)
	);

	validate_store_request_vars($filters, 'sess_monitor');
	/* ================= input validation ================= */
}

function render_where_join(&$sql_where, &$sql_join) {
	if (get_request_var('crit') > 0) {
		$crit = ' AND h.monitor_criticality>=' . get_request_var('crit');
	} else {
		$crit = '';
	}

	if (get_request_var('status') == '0') {
		$sql_join  = '';
		$sql_where = 'WHERE h.disabled = "" 
			AND h.monitor = "on" 
			AND h.status < 3 
			AND (availability_method>0 
				OR snmp_version>0 
				OR (cur_time >= monitor_warn 
					AND monitor_warn > 0) 
				OR (cur_time >= monitor_alert 
					AND monitor_alert > 0)
			)' . $crit;
	}elseif (get_request_var('status') == '1') {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = "" 
			AND h.monitor = "on" 
			AND (h.status < 3 
			OR (td.thold_enabled="on" AND td.thold_alert>0) 
			OR ((availability_method>0 OR snmp_version>0) 
				AND ((cur_time > monitor_warn AND monitor_warn > 0) 
				OR (cur_time > monitor_alert AND monitor_alert > 0))
			))' . $crit;
	} else {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = "" 
			AND h.monitor = "on" 
			AND (availability_method>0 OR snmp_version>0 
				OR (td.thold_enabled="on" AND td.thold_alert>0)
			)' . $crit;
	}
}

/* Render functions */
function render_default() {
	$result = '';

	$sql_where = '';
	$sql_join  = '';
	render_where_join($sql_where, $sql_join);

	$hosts  = db_fetch_assoc("SELECT DISTINCT h.*
		FROM host AS h
		$sql_join
		$sql_where
		ORDER BY description");

	if (sizeof($hosts)) {
		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description)) 
			FROM host AS h
			$sql_join 
			$sql_where");

		foreach($hosts as $host) {
			$result .= render_host($host, true, $maxlen);
		}
	}

	return $result;
}

function render_perms() {
	global $row_stripe;

	// Get the list of allowed devices first
	$hosts = get_allowed_devices();

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		$sql_where = '';
		$sql_join  = '';
		render_where_join($sql_where, $sql_join);

		// Now query for the hosts in that list that should be displayed
		$hosts  = db_fetch_assoc("SELECT DISTINCT h.*
			FROM host AS h
			$sql_join
			$sql_where
			AND h.id IN(" . implode(',', $host_ids) . ")
			ORDER BY description");

		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description)) 
			FROM host AS h
			WHERE id IN (" . implode(',', $host_ids) . ")");

		foreach ($host as $host) {
			$result .= render_host($host, true, $maxlen);
		}
	}

	return $result;
}

function render_template() {
	$result = '';

	$sql_where = '';
	$sql_join  = '';
	render_where_join($sql_where, $sql_join);

	$hosts  = db_fetch_assoc("SELECT DISTINCT
		h.*, ht.name AS host_template_name
		FROM host AS h
		INNER JOIN host_template AS ht
		ON h.host_template_id=ht.id
		$sql_join
		$sql_where
		ORDER BY ht.name, h.description");

	$ctemp = -1;
	$ptemp = -1;

	if (get_request_var('view') == 'tiles') {
		$offset  = 0;
		$offset2 = 0;
	} else {
		$offset  = 52;
		$offset2 = 38;
	}

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description)) 
			FROM host AS h
			WHERE id IN (" . implode(',', $host_ids) . ")");

		$class = get_request_var('size');

		foreach($hosts as $host) {
			$ctemp = $host['host_template_id'];

			if ($ctemp != $ptemp && $ptemp > 0) {
				$result .= "</td></tr></table></div></div>\n";
			}

			if ($ctemp != $ptemp) {
				$result .= "<div class='monitor_main $class'><div class='monitor_frame'><table class='odd'><tr class='tableHeader'><th class='left'>" . $host['host_template_name'] . "</th></tr><tr><td class='center $class'>\n";
			}

			$result .= render_host($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp) {
			$result .= "</td></tr></table></div></div>\n";
		}
	}

	return $result;
}

function render_tree() {
	$result = '';

	$leafs = array();

	if (get_request_var('tree') > 0) {
		$sql_where = 'gt.id=' . get_request_var('tree');
	} else {
		$sql_where = '';
	}

	if (get_request_var('tree') != -2) {
		$tree_list = get_allowed_trees(false, false, $sql_where);
	} else {
		$tree_list = array();
	}

	if (sizeof($tree_list)) {
		$ptree = '';
		foreach($tree_list as $tree) {
			$tree_ids[$tree['id']] = $tree['id'];
		}

		$branchWhost = db_fetch_assoc("SELECT DISTINCT gti.graph_tree_id, gti.parent
			FROM graph_tree_items AS gti
			WHERE gti.host_id>0 
			AND gti.parent > 0
			AND gti.graph_tree_id IN (" . implode(',', $tree_ids) . ") 
			ORDER BY gti.graph_tree_id");

		if (sizeof($branchWhost)) {
			foreach($branchWhost as $b) {
				$titles[$b['graph_tree_id'] . ':' . $b['parent']] = db_fetch_cell_prepared('SELECT title 
					FROM graph_tree_items 
					WHERE id = ? AND graph_tree_id = ?',
					array($b['parent'], $b['graph_tree_id']));
			}
			asort($titles);

			foreach($titles as $index => $title) {
				list($graph_tree_id, $parent) = explode(':', $index);

				$oid   = $parent;

				$sql_where = '';
				$sql_join  = '';
				render_where_join($sql_where, $sql_join);

				$hosts = db_fetch_assoc_prepared("SELECT DISTINCT h.* 
					FROM host AS h 
					INNER JOIN graph_tree_items AS gti 
					ON h.id=gti.host_id 
					$sql_join
					$sql_where
					AND parent = ?", array($oid));

				if (sizeof($hosts)) {
					foreach($hosts as $host) {
						$host_ids[] = $host['id'];
					}

					$class = get_request_var('size');

					// Determine the correct width of the cell
					$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description)) 
						FROM host AS h
						WHERE id IN (" . implode(',', $host_ids) . ")");

					$tree_name = db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($b['graph_tree_id']));
					if ($ptree != $tree_name) {
						if ($ptree != '') {
							$result .= '</div></td></tr></table></div>';
						}
						$result .= '<div class="monitor_tree_title"><table class="odd"><tr class="tableHeader"><th>' . $tree_name . '</th></tr><tr><td><div style="width:100%">';
						$ptree = $tree_name;
					}

					$title = $title !='' ? $title:'Root Folder';

					$result .= '<div class="monitor_tree_frame ' . $class . '"><table class="odd"><tr class="tableHeader"><th>' . $title . '</th></tr><tr><td class="center"><div>';
					foreach($hosts as $host) {
						$result .= render_host($host, true, $maxlen);
					}

					$result .= '</div></td></tr></table></div>';
				}
			}
		}

		$result .= '</div></td></tr></table></div>';
	}

	/* begin others - lets get the monitor items that are not associated with any tree */
	if (get_request_var('tree') < 0) {
		$hosts = get_host_non_tree_array();
		if (sizeof($hosts)) {
			foreach($hosts as $host) {
				$host_ids[] = $host['id'];
			}

			// Determine the correct width of the cell
			if (sizeof($host_ids)) {
				$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description)) 
					FROM host AS h
					WHERE id IN (" . implode(',', $host_ids) . ")");
			}else{
				$maxlen = 100;
			}

			$result .= '<div class="monitor_tree_title"><table class="odd"><tr class="tableHeader"><th>' . __('Non-Tree Devices') . '</th></tr><tr><td><div style="width:100%">';
			foreach($hosts as $leaf) {
				$result .= render_host($leaf, true, $maxlen);
			}

			$result .= '</div></td></tr></table></div>';
		}
	}

	return $result;
}

/* Branch rendering */
function render_branch($leafs, $title = '') {
	global $render_style;
	global $row_stripe;

	$row_stripe=false;

	if ($title == '') {
		foreach ($leafs as $row) {
			/* get our proper branch title */
			$title = $row['branch_name'];
			break;
		}
	}
	if ($title == '') {
		/* Insert a default title */
		$title = 'Items';
		$title .= ' (' . sizeof($leafs) . ')';
	}
	//$branch_percentup = '%' . leafs_percentup($leafs);
	//$title .= " - $branch_percentup";

	/* select function to render here */
	$function = "render_branch_$render_style";
	if (function_exists($function)) {
		/* Call the custom render_branch_ function */
		return $function($leafs, $title);
	} else {
		return render_branch_tree($leafs, $title);
	}
}

function get_host_status($host) {
	/* If the host has been muted, show the muted Icon */
	if (in_array($host['id'], $_SESSION['muted_hosts']) && $host['status'] == 1) {
		$host['status'] = 5;
	}elseif ($host['status'] == 3) {
		if ($host['cur_time'] > $host['monitor_alert'] && !empty($host['monitor_alert'])) {
			$host['status'] = 8;
		}elseif ($host['cur_time'] > $host['monitor_warn'] && !empty($host['monitor_warn'])) {
			$host['status'] = 7;
		}
	}

	return $host['status'];
}

/*Single host  rendering */
function render_host($host, $float = true, $maxlen = 0) {
	global $thold, $thold_hosts, $config, $icolorsdisplay, $iclasses, $classes;

	//throw out tree root items
	if (array_key_exists('name', $host))  {
		return;
	}

	if (!is_device_allowed($host['id'])) {
		return;
	}

	if ($host['id'] <= 0) {
		return;
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];
	if ($thold) {
		if ($host['status'] == 3 && in_array($host['id'], $thold_hosts)) {
			$host['status'] = 4;
			if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
			} else {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
			}
		}
	}

	$host['status'] = get_host_status($host);
	$host['iclass'] = $iclasses[$host['status']];

	$dt = '';
	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = monitor_print_host_time($host['status_fail_date']);
	}

	$function = 'render_host_' . get_request_var('view');

	if (function_exists($function)) {
		/* Call the custom render_host_ function */
		$result = $function($host);
	} else {
		$iclass = get_status_icon($host['status']);
		$fclass = get_request_var('size');

		if ($host['status'] <= 2 || $host['status'] == 5) {
			$result = "<div " . ($host['status'] == 1 ? 'class="' . $fclass . ' flash monitor_device_frame"':'class="' . $fclass . ' monitor_device_frame"') . " style='width:" . max(80, $maxlen*6) . "px;" . ($float ? 'float:left;':'') . "'><a href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $fclass $iclass " . $host['iclass'] . "'></i><br><span class='center'>" . trim($host['description']) . "</span><br><span style='font-size:10px;padding:2px;' class='deviceDown'>$dt</span></a></div>\n";
		} else {
			$result = "<div class='monitor_device_frame fclass' style='width:" . max(80, $maxlen*6) . "px;" . ($float ? 'float:left;':'') . "'><a href='" . $host['anchor'] . "'><i id=" . $host['id'] . " class='fa $fclass $iclass " . $host['iclass'] . "'></i><br>" . trim($host['description']) . "</a></div>\n";
		}
	}

	return $result;
}

function get_status_icon($status) {
	if ($status == 1 && read_user_setting('monitor_sound') == 'First Orders Suite.mp3') {
		return 'fa-first-order fa-spin';
	} else {
		return 'fa-server';
	}
}

function monitor_print_host_time($status_time, $seconds = false) {
	// If the host is down, make a downtime since message
	$dt   = '';
	if (is_numeric($status_time)) {
		$sfd  = round($status_time / 100,0);
	} else {
		$sfd  = time() - strtotime($status_time);
	}
	$dt_d = floor($sfd/86400);
	$dt_h = floor(($sfd - ($dt_d * 86400))/3600);
	$dt_m = floor(($sfd - ($dt_d * 86400) - ($dt_h * 3600))/60);
	$dt_s = $sfd - ($dt_d * 86400) - ($dt_h * 3600) - ($dt_m * 60);

	if ($dt_d > 0 ) {
		$dt .= $dt_d . 'd:' . $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_h > 0 ) {
		$dt .= $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_m > 0 ) {
		$dt .= $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');;
	} else {
		$dt .= ($seconds ? $dt_s . 's':__('Just Up'));
	}

	return $dt;
}

function ajax_status() {
	global $thold, $thold_hosts, $config, $icolorsdisplay, $iclasses, $criticalities;

	if (isset_request_var('id') && get_filter_request_var('id')) {
		$id = get_request_var('id');

		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($id));

		$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];
		if ($thold) {
			if ($host['status'] == 3 && in_array($host['id'], $thold_hosts)) {
				$host['status'] = 4;
				if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
					$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
				} else {
					$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
				}
			}
		}

		if ($host['availability_method'] == 0) {
			$host['status'] = 6;
		}

		$host['status'] = get_host_status($host);

		if (sizeof($host)) {
			if (api_plugin_user_realm_auth('host.php')) {
				$host_link = htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
			}

			// Get the number of graphs
			$graphs   = db_fetch_cell_prepared('SELECT count(*) FROM graph_local WHERE host_id = ?', array($host['id']));
			if ($graphs > 0) {
				$graph_link = htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
			}

			// Get the number of thresholds
			if (api_plugin_is_enabled('thold')) {
				$tholds     = db_fetch_cell_prepared('SELECT count(*) FROM thold_data WHERE host_id = ?', array($host['id']));
				if ($tholds > 0) {
					$thold_link = htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=1&status=-1&host_id=' . $host['id']);
				}
			} else {
				$tholds = 0;
			}

			// Get the number of syslogs
			if (api_plugin_is_enabled('syslog') && api_plugin_user_realm_auth('syslog.php')) {
				include($config['base_path'] . '/plugins/syslog/config.php');
				include_once($config['base_path'] . '/plugins/syslog/functions.php');
				$syslog_logs = syslog_db_fetch_cell_prepared('SELECT count(*) FROM syslog_logs WHERE host = ?', array($host['hostname']));
				$syslog_host = syslog_db_fetch_cell_prepared('SELECT host_id FROM syslog_hosts WHERE host = ?', array($host['hostname']));

				if ($syslog_logs && $syslog_host) {
					$syslog_log_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=alerts&host_id=' . $syslog_host);
				}
				if ($syslog_host) {
					$syslog_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=syslog&host_id=' . $syslog_host);
				}
			} else {
				$syslog_logs  = 0;
				$syslog_host  = 0;
			}

			$links = '';
			if (isset($host_link)) {
				$links .= '<a class="hyperLink" href="' . $host_link . '">' . __('Edit Device') . '</a>';
			}
			if (isset($graph_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $graph_link . '">' . __('View Graphs') . '</a>';
			}
			if (isset($thold_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $thold_link . '">' . __('View Thresholds') . '</a>';
			}
			if (isset($syslog_log_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $syslog_log_link . '">' . __('View Syslog Alerts') . '</a>';
			}
			if (isset($syslog_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $syslog_link . '">' . __('View Syslog Messages') . '</a>';
			}

			$iclass   = $iclasses[$host['status']];
			$sdisplay = $icolorsdisplay[$host['status']];

			print "<table class='monitorHover' style='padding:2px;margin:0px;width:overflow:hidden;max-width:500px;max-height:600px;vertical-align:top;'>
				<tr class='tableHeader'>
					<th class='left' colspan='2'>Device Status Information</th>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Device:') . "</td>
					<td style='vertical-align:top;'><a href='" . $host['anchor'] . "'><span>" . $host['description'] . "</span></a></td>
				</tr>" . (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Criticality:') . "</td>
					<td style='vertical-align:top;'>" . $criticalities[$host['monitor_criticality']] . "</td>
				</tr>":"") . "
				<tr>
					<td style='vertical-align:top;'>" . __('Status:') . "</td>
					<td class='$iclass' style='vertical-align:top;'>$sdisplay</td>
				</tr>" . ($host['status'] < 3 || $host['status'] == 5 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Admin Note:') . "</td>
					<td class='$iclass' style='vertical-align:top;'>" . $host['monitor_text'] . "</td>
				</tr>":"") . ($host['availability_method'] > 0 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('IP/Hostname:') . "</td>
					<td style='vertical-align:top;'>" . $host['hostname'] . "</td>
				</tr>":"") . ($host['notes'] != '' ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Notes:') . "</td>
					<td style='vertical-align:top;'>" . $host['notes'] . "</td>
				</tr>":"") . (($graphs || $syslog_logs || $syslog_host || $tholds) ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Links:') . "</td>
					<td style='vertical-align:top;'>" . $links . "
				 	</td>
				</tr>":"") . ($host['availability_method'] > 0 ? "
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Curr/Avg:') . "</td>
					<td style='vertical-align:top;'>" . __('%d ms', $host['cur_time']) . ' / ' .  __('%d ms', $host['avg_time']) . "</td>
				</tr>":"") . (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0) ? "
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Warn/Alert:') . "</td>
					<td style='vertical-align:top;'>" . __('%0.2d ms', $host['monitor_warn']) . ' / ' . __('%0.2d ms', $host['monitor_alert']) . "</td>
				</tr>":"") . "
				<tr>
					<td style='vertical-align:top;'>" . __('Last Fail:') . "</td>
					<td style='vertical-align:top;'>" . $host['status_fail_date'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Time In State:') . "</td>
					<td style='vertical-align:top;'>" . get_timeinstate($host) . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Availability:') . "</td>
					<td style='vertical-align:top;'>" . round($host['availability'],2) . " %</td>
				</tr>" . ($host['snmp_version'] > 0 && ($host['status'] == 3 || $host['status'] == 2) ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Agent Uptime:') . "</td>
					<td style='vertical-align:top;'>" . ($host['status'] == 3 || $host['status'] == 5 ? monitor_print_host_time($host['snmp_sysUpTimeInstance']):'N/A') . "</td>
				</tr>
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Sys Description:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysDescr'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Location:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysLocation'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Contact:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysContact'] . "</td>
				</tr>":"") . "
				</table>\n";
		}
	}
}

function render_host_tiles($host) {
	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$result = "<div class='monitor_device_frame'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class $fclass " . $host['iclass'] . "'></i></a></div>";

	return $result;
}

function render_host_tilesadt($host) {
	$dt = '';

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = monitor_print_host_time($host['status_fail_date']);

		$result = "<div class='monitor_device_frame'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class $fclass " . $host['iclass'] . "'></i><br><span class='monitor_device deviceDown'>$dt</span></a></div>\n";

		return $result;
	} else {
		if ($host['status_rec_date'] != '0000-00-00 00:00:00') {
			$dt = monitor_print_host_time($host['status_rec_date']);
		} else {
			$dt = __('Never');
		}

		$result = "<div class='monitor_device_frame'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class $fclass " . $host['iclass'] . "'></i><br><span class='monitor_device deviceUp'>$dt</span></a></div>\n";

		return $result;
	}

}

function get_hosts_down_by_permission() {
	global $render_style;

	$result = array();

	if (get_request_var('crit') > 0) {
		$sql_add_where = ' AND monitor_criticality >= ' . get_request_var('crit');
	}else{
		$sql_add_where = '';
	}

	if (get_request_var('grouping') == 'tree') {
		if (get_request_var('tree') > 0) {
			$devices = db_fetch_cell_prepared('SELECT GROUP_CONCAT(host_id) AS hosts 
				FROM graph_tree_items 
				WHERE host_id > 0 
				AND graph_tree_id = ?', 
				array(get_request_var('tree')));

			$sql_add_where .= ' AND h.id IN(' . $devices . ')';
		}
	}

	if ($render_style == 'default') {
		$hosts = get_allowed_devices("h.monitor='on' $sql_add_where AND h.disabled='' AND h.status < 2 AND (h.availability_method>0 OR h.snmp_version>0)");
		// do a quick loop through to pull the hosts that are down
		if (sizeof($hosts)) {
			foreach($hosts as $host) {
				$host_down = true;
				$result[] = $host['id'];
				sort($result);
			}
		}
	} else {
		/* Only get hosts */
		$hosts = get_allowed_devices("h.monitor='on' $sql_add_where AND h.disabled='' AND h.status < 2 AND (h.availability_method>0 OR h.snmp_version>0)");
		if (sizeof($hosts) > 0) {
			foreach ($hosts as $host) {
				$host_down = true;
				$result[] = $host['id'];
				sort($result);
			}
		}
	}

	return $result;
}

function get_host_tree_array() {
	return $leafs;
}

function get_host_non_tree_array() {
	$leafs = array();

	$sql_where = '';
	$sql_join  = '';

	render_where_join($sql_where, $sql_join);

	//$sql_where .= " AND ((host.disabled = '' AND host.monitor = 'on' AND (host.availability_method>0 OR host.snmp_version>0)) OR (title != ''))";

	$heirarchy = db_fetch_assoc("SELECT DISTINCT
		h.*, gti.title, gti.host_id, gti.host_grouping_type, gti.graph_tree_id
		FROM host AS h
		LEFT JOIN graph_tree_items AS gti 
		ON h.id=gti.host_id
		$sql_join
		$sql_where
		AND gti.graph_tree_id IS NULL
		ORDER BY h.description");

	if (sizeof($heirarchy) > 0) {
		$leafs = array();
		$branchleafs = 0;
		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}
	return $leafs;
}

/* Supporting functions */
function get_status_color($status=3) {
	$color = '#183C8F';
	switch ($status) {
		case 0: //error
			$color = '#993333';
			break;
		case 1: //error
			$color = '#993333';
			break;
		case 2: //recovering
			$color = '#7293B9';
			break;
		case 3: //ok
			$color = '#669966';
			break;
		case 4: //threshold
			$color = '#c56500';
			break;
		case 5: //muted
			$color = '#996666';
			break;
		default: //unknown
			$color = '#999999';
			break;
		}
	return $color;
}

function leafs_status_min($leafs) {
	global $thold;
	global $thold_hosts;
	$thold_breached = 0;
	$result = 3;
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($result > $status) {
			$result = $status;
		}
		if ($thold) {
			if ($status == 3 && in_array($row['id'], $thold_hosts)) {
				$thold_breached = 1;
			}
		}
	}
	if ($result == 3 && $thold_breached) {
		$result = 4;
	}
	return $result;
}

function leafs_percentup($leafs) {
	$result = 0;
	$countup = 0;
	$count = sizeof($leafs);
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($status >= 3) {
			$countup++;
		}
	}
	if ($countup>=$count){
		return 100;
	}
	$result = round($countup/$count*100,0);
	return $result;
}

