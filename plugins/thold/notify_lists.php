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

chdir('../..');
include('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

$actions = array(
	1 => __('Delete', 'thold'),
	2 => __('Duplicate', 'thold')
);

$assoc_actions = array(
    1 => __('Associate', 'thold'),
    2 => __('Disassociate', 'thold')
);

/* present a tabbed interface */
$tabs_thold = array(
    'general'   => __('General', 'thold'),
    'hosts'     => __('Devices', 'thold'),
    'tholds'    => __('Thresholds', 'thold'),
    'templates' => __('Templates', 'thold')
);

set_default_action('');

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();
		edit();
		bottom_footer();
		break;
	default:
		top_header();
		lists();
		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component')) {
		$save['id']          = get_filter_request_var('id');
		$save['name']        = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description'] = form_input_validate(get_nfilter_request_var('description'), 'description', '', false, 3);
		$save['emails']      = form_input_validate(get_nfilter_request_var('emails'), 'emails', '', false, 3);

		if (!is_error_message()) {
			$id = sql_save($save, 'plugin_notification_lists');

			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	}

	header('Location: notify_lists.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $actions, $assoc_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		if (isset_request_var('save_list')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_request_var('drp_action') == '1') { /* delete */
					db_execute('DELETE FROM plugin_notification_lists WHERE ' . array_to_sql_or($selected_items, 'id'));
					db_execute('UPDATE host SET thold_send_email = 0 WHERE thold_send_email = 2 AND ' . array_to_sql_or($selected_items, 'thold_host_email'));
					db_execute('UPDATE host SET thold_send_email = 1 WHERE thold_send_email = 3 AND ' . array_to_sql_or($selected_items, 'thold_host_email'));
					db_execute('UPDATE host SET thold_host_email = 0 WHERE ' . array_to_sql_or($selected_items, 'thold_host_email'));
				} elseif (get_request_var('drp_action') == '2') { /* duplicate */
					$i = 1;

					foreach($selected_items as $item) {
						/* get list to be duplicated */
						$list = db_fetch_row_prepared('SELECT * 
							FROM plugin_notification_lists 
							WHERE id = ?', 
							array($item));

						/* see if there is already a list with the new name */
						$exists = db_fetch_cell_prepared('SELECT COUNT(*) 
							FROM plugin_notification_lists 
							WHERE name = ?', 
							array(get_nfilter_request_var('name')));

						if ($exists > 0) {
							$name = get_nfilter_request_var('name') . ' (' . $i . ')';
							$i ++;
						} else {
							$name = get_nfilter_request_var('name');
						}

						$save['id']          = 0;
						$save['name']        = $name;
						$save['description'] = $list['description'];
						$save['emails']      = $list['emails'];

						$id = sql_save($save, 'plugin_notification_lists');

						if ($id) {
							raise_message(1);
						} else {
							raise_message(2);
						}
					}
				}
			}

			header('Location: notify_lists.php?header=false');
			exit;
		} elseif (isset_request_var('save_associate')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				get_filter_request_var('notification_action');

				if (get_request_var('drp_action') == '1') { /* associate */
					for ($i=0;($i<count($selected_items));$i++) {
						/* set the notification list */
						db_execute('UPDATE host SET thold_host_email=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
						/* set the global/list election */
						db_execute('UPDATE host SET thold_send_email=' . get_request_var('notification_action') . ' WHERE id=' . $selected_items[$i]);
					}
				} elseif (get_request_var('drp_action') == '2') { /* disassociate */
					for ($i=0;($i<count($selected_items));$i++) {
						/* set the notification list */
						db_execute('UPDATE host SET thold_host_email=0 WHERE id=' . $selected_items[$i]);
						/* set the global/list election */
						db_execute('UPDATE host SET thold_send_email=' . get_request_var('notification_action') . ' WHERE id=' . $selected_items[$i]);
					}
				}
			}

			header('Location: notify_lists.php?header=false&action=edit&tab=hosts&id=' . get_request_var('id'));
			exit;
		} elseif (isset_request_var('save_templates')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				get_filter_request_var('notification_action');

				if (get_request_var('drp_action') == '1') { /* associate */
					for ($i=0;($i<count($selected_items));$i++) {
						if (get_request_var('notification_warning_action') > 0) {
							/* clear other settings */
							if (get_request_var('notification_warning_action') == 1) {
								/* set the notification list */
								db_execute('UPDATE thold_template SET notify_warning=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
								/* clear other items */
								db_execute("UPDATE thold_template SET notify_warning_extra='' WHERE id=" . $selected_items[$i]);
							}else{
								/* set the notification list */
								db_execute('UPDATE thold_template SET notify_warning=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
							}
						}

						if (get_request_var('notification_alert_action') > 0) {
							/* clear other settings */
							if (get_request_var('notification_alert_action') == 1) {
								/* set the notification list */
								db_execute('UPDATE thold_template SET notify_alert=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
								/* clear other items */
								db_execute("UPDATE thold_template SET notify_extra='' WHERE id=" . $selected_items[$i]);
								db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id=' . $selected_items[$i]);
							}else{
								/* set the notification list */
								db_execute('UPDATE thold_template SET notify_alert=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
							}
						}
					}
				} elseif (get_request_var('drp_action') == '2') { /* disassociate */
					for ($i=0;($i<count($selected_items));$i++) {
						if (get_request_var('notification_warning_action') > 0) {
							/* set the notification list */
							db_execute('UPDATE thold_template SET notify_warning=0 WHERE id=' . $selected_items[$i] . ' AND notify_warning=' . get_request_var('id'));
						}

						if (get_request_var('notification_alert_action') > 0) {
							/* set the notification list */
							db_execute('UPDATE thold_template SET notify_alert=0 WHERE id=' . $selected_items[$i] . ' AND notify_alert=' . get_request_var('id'));
						}
					}
				}
			}

			header('Location: notify_lists.php?header=false&action=edit&tab=templates&id=' . get_request_var('id'));
			exit;
		} elseif (isset_request_var('save_tholds')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				get_filter_request_var('notification_action');

				if (get_request_var('drp_action') == '1') { /* associate */
					for ($i=0;($i<count($selected_items));$i++) {
						if (get_request_var('notification_warning_action') > 0) {
							/* clear other settings */
							if (get_request_var('notification_warning_action') == 1) {
								/* set the notification list */
								db_execute('UPDATE thold_data SET notify_warning=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
								/* clear other items */
								db_execute("UPDATE thold_data SET notify_warning_extra='' WHERE id=" . $selected_items[$i]);
							}else{
								/* set the notification list */
								db_execute('UPDATE thold_data SET notify_warning=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
							}
						}

						if (get_request_var('notification_alert_action') > 0) {
							/* clear other settings */
							if (get_request_var('notification_alert_action') == 1) {
								/* set the notification list */
								db_execute('UPDATE thold_data SET notify_alert=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
								/* clear other items */
								db_execute("UPDATE thold_data SET notify_extra='' WHERE id=" . $selected_items[$i]);
								db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $selected_items[$i]);
							}else{
								/* set the notification list */
								db_execute('UPDATE thold_data SET notify_alert=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
							}
						}
					}
				} elseif (get_request_var('drp_action') == '2') { /* disassociate */
					for ($i=0;($i<count($selected_items));$i++) {
						if (get_request_var('notification_warning_action') > 0) {
							/* set the notification list */
							db_execute('UPDATE thold_data SET notify_warning=0 WHERE id=' . $selected_items[$i] . ' AND notify_warning=' . get_request_var('id'));
						}

						if (get_request_var('notification_alert_action') > 0) {
							/* set the notification list */
							db_execute('UPDATE thold_data SET notify_alert=0 WHERE id=' . $selected_items[$i] . ' AND notify_alert=' . get_request_var('id'));
						}
					}
				}
			}

			header('Location: notify_lists.php?header=false&action=edit&tab=tholds&id=' . get_request_var('id'));
			exit;
		}
	}

	/* setup some variables */
	$list = ''; $array = array(); $list_name = '';
	if (isset_request_var('id')) {
		$list_name = db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . get_filter_request_var('id'));
	}

	if (isset_request_var('save_list')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_notification_lists WHERE id = ?', array($matches[1]))) . '</li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('notify_lists.php');

		html_start_box($actions{get_request_var('drp_action')} . " $list_name", '60%', '', '3', 'center', '');

		if (sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* delete */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Delete Notification Lists(s).  Any Device(s) or Threshold(s) associated with the List(s) will be reverted to the default.', 'thold'). "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Delete Notification List(s)', 'thold') . "'>";
			} elseif (get_request_var('drp_action') == '2') { /* duplicate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Duplicate the following Notification List(s).', 'thold') . "</p>
						<ul>$list</ul>
					<p>" . __('New List Name') . '<br>';
					form_text_box('name', __('New Notification List'), '', '255', '40', 'text');

				print "</p></td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Duplicate Notification List(s)', 'thold') . "'>";
			}
		} else {
			print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Notification List.', 'thold') . "</span></td></tr>\n";
			$save_html = "<input type='button' value='" . __esc('Return', 'thold'). "' onClick='cactiReturnTo()'>";
		}

		print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='save_list' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	} elseif (isset_request_var('save_templates')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= '<li><b>' . db_fetch_cell('SELECT name FROM thold_template WHERE id=' . $matches[1]) . '</b></li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('notify_lists.php');

		html_start_box(__('%s Threshold Template(s)', $assoc_actions[get_request_var('drp_action')], 'thold'), '60%', '', '3', 'center', '');

		if (sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* associate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Association the Notification List \'<b>%s</b>\' with the Threshold Template(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Warning Membership:', 'thold') . "</b><br>"; form_dropdown('notification_warning_action', array(0 => __('No Change', 'thold'), 1 => __('Notification List Only', 'thold'), 2 => __('Notification List, Retain Other Settings', 'thold')), '', '', 1, '', ''); print "</p>
						<p><b>" . __('Alert Membership:', 'thold') . "</b><br>"; form_dropdown('notification_alert_action', array(0 => __('No Change', 'thold'), 1 => __('Notification List Only', 'thold'), 2 => __('Notification List, Retain Other Settings', 'thold')), '', '', 1, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Associate Notification List(s)', 'thold') . "'>";
			} elseif (get_request_var('drp_action') == '2') { /* disassociate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Disassociate the Notification List \'<b>%s</b>\' from the Thresholds Template(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Warning Membership:', 'thold') . "</b><br>"; form_dropdown('notification_warning_action', array(0 => __('No Change', 'thold'), 1 => __('Remove List', 'thold')), '', '', 1, '', ''); print "</p>
						<p><b>" . __('Alert Membership:', 'thold') . "</b><br>"; form_dropdown('notification_alert_action', array(0 => __('No Change', 'thold'), 1 => __('Remove List', 'thold')), '', '', 1, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Disassociate Notification List(s)', 'thold') . "'>";
			}
		} else {
			print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Threshold Template.', 'thold') . "</span></td></tr>\n";
			$save_html = "<input type='button' value='" . __esc('Return', 'thold') . "' onClick='cactiReturnTo()'>";
		}

		print "	<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				<input type='hidden' name='save_templates' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	} elseif (isset_request_var('save_tholds')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= '<li><b>' . db_fetch_cell('SELECT name FROM thold_data WHERE id=' . $matches[1]) . '</b></li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('notify_lists.php');

		html_start_box(__('%s Threshold(s)', $assoc_actions[get_request_var('drp_action')], 'thold'), '60%', '', '3', 'center', '');

		if (sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* associate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Associate the Notification List \'<b>%s</b>\' with the Threshold(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Warning Membership:', 'thold') . "</b><br>"; form_dropdown('notification_warning_action', array(0 => __('No Change', 'thold'), 1 => __('Notification List Only', 'thold'), 2 => __('Notification List, Retain Other Settings', 'thold')), '', '', 1, '', ''); print "</p>
						<p><b>" . __('Alert Membership:', 'thold') . "</b><br>"; form_dropdown('notification_alert_action', array(0 => __('No Change', 'thold'), 1 => __('Notification List Only', 'thold'), 2 => __('Notification List, Retain Other Settings', 'thold')), '', '', 1, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Associate Notification List(s)', 'thold') . "'>";
			} elseif (get_request_var('drp_action') == '2') { /* disassociate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Disassociate the Notification List \'<b>%s</b>\' from the Thresholds(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Warning Membership:', 'thold') . "</b><br>"; form_dropdown('notification_warning_action', array(0 => __('No Change', 'thold'), 1 => __('Remove List', 'thold')), '', '', 1, '', ''); print "</p>
						<p><b>" . __('Alert Membership:', 'thold') . "</b><br>"; form_dropdown('notification_alert_action', array(0 => __('No Change', 'thold'), 1 => __('Remove List', 'thold')), '', '', 1, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold') . "' title='" . __esc('Disassociate Notification List(s)', 'thold') . "'>";
			}
		} else {
			print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Threshold.', 'thold') . "</span></td></tr>\n";

			$save_html = "<input type='button' value='" . __esc('Return', 'thold') . "' onClick='cactiReturnTo()'>";
		}

		print "	<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				<input type='hidden' name='save_tholds' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	}else{
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= '<li>' . db_fetch_cell('SELECT description FROM host WHERE id=' . $matches[1]) . '</li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('notify_lists.php');

		html_start_box($assoc_actions{get_request_var('drp_action')} . ' Device(s)', '60%', '', '3', 'center', '');

		if (sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* associate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Associate the Notification List \'<b>%s</b>\' with the Device(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Resulting Membership:', 'thold'). "<br>"; form_dropdown('notification_action', array(2 => __('Notification List Only', 'thold'), 3 => __('Notification and Global Lists', 'thold')), '', '', 2, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'thold'). "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'thold'). "' title='" . __esc('Associate Notification List(s)', 'thold'). "'>";
			} elseif (get_request_var('drp_action') == '2') { /* disassociate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Disassociate the Notification List \'<b>%s</b>\' from the Device(s) below.', $list_name, 'thold') . "</p>
						<ul>$list</ul>
						<p><b>" . __('Resulting Membership:', 'thold') . "</b><br>"; form_dropdown('notification_action', array(1 => __('Global List', 'thold'), 0 => __('Disabled', 'thold')), '', '', 1, '', ''); print "</p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __('Cancel', 'thold') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue', 'thold') . "' title='" . __('Disassociate Notification List(s)', 'thold') . "'>";
			}
		} else {
			print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Device.', 'thold') . "</span></td></tr>\n";

			$save_html = "<input type='button' value='" . __esc('Return', 'thold'). "' onClick='cactiReturnTo()'>";
		}

		print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				<input type='hidden' name='save_associate' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	}
}

/* ----------------------------
   Notification List Edit
   ---------------------------- */

function get_notification_header_label() {
	if (!isempty_request_var('id')) {
		$list = db_fetch_row('SELECT * FROM plugin_notification_lists WHERE id=' . get_filter_request_var('id'));
		$header_label = __('[edit: %s]', $list['name'], 'thold');
	} else {
		$header_label = __('[new]', 'thold');
	}

	return $header_label;
}

function edit() {
	global $tabs_thold, $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	/* ==================================================== */

	/* set the default tab */
	load_current_session_value('tab', 'sess_thold_notify_tab', 'general');
	$current_tab = get_request_var('tab');

	if (sizeof($tabs_thold) && isset_request_var('id')) {
		print "<div class='tabs'><nav><ul>\n";

		if (sizeof($tabs_thold)) {
			foreach (array_keys($tabs_thold) as $tab_short_name) {
				print "<li><a class='pic" . (($tab_short_name == $current_tab) ? ' selected' : '') .  "' href='" . $config['url_path'] .
					'plugins/thold/notify_lists.php' .
					'?action=edit&id=' . get_filter_request_var('id') .
					'&tab=' . $tab_short_name .
					"'>" . $tabs_thold[$tab_short_name] . "</a></li>\n";
			}
		}

		print "</ul></nav></div>\n";
	}

	$header_label = get_notification_header_label();

	if (isset_request_var('id')) {
		$list = db_fetch_row('SELECT * FROM plugin_notification_lists WHERE id=' . get_request_var('id'));
	} else {
		$list = array();
		$current_tab = 'general';
	}

	if ($current_tab == 'general') {
		form_start('notify_lists.php');

		html_start_box(__('List General Settings', 'thold') . ' ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

		$fields_notification = array(
			'name' => array(
				'method' => 'textbox',
				'friendly_name' => __('Name', 'thold'),
				'description' => __('Enter a name for this Notification List.', 'thold'),
				'value' => '|arg1:name|',
				'max_length' => '80'
			),
			'description' => array(
				'method' => 'textarea',
				'friendly_name' => __('Description', 'thold'),
				'description' => __('Enter a description for this Notification List.', 'thold'),
				'value' => '|arg1:description|',
				'class' => 'textAreaNotes',
				'textarea_rows' => '2',
				'textarea_cols' => '80'
			),
			'emails' => array(
				'method' => 'textarea',
				'friendly_name' => __('Email Addresses', 'thold'),
				'description' => __('Enter a comma separated list of Email addresses for this Notification List.', 'thold'),
				'value' => '|arg1:emails|',
				'class' => 'textAreaNotes',
				'textarea_rows' => '4',
				'textarea_cols' => '80'
			),
			'id' => array(
				'method' => 'hidden_zero',
				'value' => '|arg1:id|'
			),
			'save_component' => array(
				'method' => 'hidden',
				'value' => '1'
			)
		);

		draw_edit_form(array(
			'config' => array(),
			'fields' => inject_form_variables($fields_notification, (isset($list) ? $list : array()))
			));

		html_end_box();

		form_save_button('notify_lists.php', 'return');
	} elseif ($current_tab == 'hosts') {
		hosts($header_label);
	} elseif ($current_tab == 'tholds') {
		tholds($header_label);
	}else{
		templates($header_label);
	}
}

function hosts($header_label) {
	global $assoc_actions, $item_rows;

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
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_nlh');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Associated Devices', 'thold') . ' ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='form_devices' method='post' action='notify_lists.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Type', 'thold');?>
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'thold');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$host_templates = db_fetch_assoc('select id,name from host_template order by name');

							if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . htmlspecialchars($host_template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Devices', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Associated', 'thold');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters', 'thold');?>'>
							<input type='button' name='clearf' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters', 'thold');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = '?header=false&action=edit&id=<?php print get_request_var('id');?>'
			strURL += '&rows=' + $('#rows').val();
			strURL += '&host_template_id=' + $('#host_template_id').val();
			strURL += '&associated=' + $('#associated').is(':checked');
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'notify_lists.php?header=false&action=edit&id=<?php print get_request_var('id');?>&clearf=true'
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

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = "WHERE (host.hostname LIKE '%" . get_request_var('filter') . "%' OR host.description LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' host.host_template_id=0';
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' host.host_template_id=' . get_request_var('host_template_id');
	}

	if (get_request_var('associated') == 'false') {
		/* Show all items */
	} else {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (host.thold_send_email>1 AND host.thold_host_email=' . get_request_var('id') . ')';
	}

	$total_rows = db_fetch_cell("select
		COUNT(host.id)
		from host
		$sql_where");

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	$sql_query = "SELECT *
		FROM host $sql_where
		LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$hosts = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('notify_lists.php?action=edit&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Devices', 'thold'), 'page', 'main');

	form_start('notify_lists.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		__('Description', 'thold'),
		__('ID', 'thold'),
		__('Associated Lists', 'thold'),
		__('Graphs', 'thold'),
		__('Data Sources', 'thold'),
		__('Status', 'thold'),
		__('Hostname', 'thold')
	);

	html_header_checkbox($display_text);

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['id'], true);
			form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id'], 250);
			form_selectable_cell(round(($host['id']), 2), $host['id']);
			if ($host['thold_send_email'] == 0) {
				form_selectable_cell('<span style="color:blue;font-weight:bold;">' . __('Disabled', 'thold') . '</span>', $host['id']);
			} elseif ($host['thold_send_email'] == 1) {
				form_selectable_cell('<span style="color:purple;font-weight:bold;">' . __('Global List', 'thold') . '</span>', $host['id']);
			} elseif ($host['thold_host_email'] == get_request_var('id')) {
				if ($host['thold_send_email'] == 2) {
					form_selectable_cell('<span style="color:green;font-weight:bold;">' . __('Current List Only', 'thold') . '</span>', $host['id']);
				}else{
					form_selectable_cell('<span style="color:green;font-weight:bold;">' . __('Current and Global List(s)', 'thold') . '</span>', $host['id']);
				}
			} elseif ($host['thold_host_email'] == '0') {
				form_selectable_cell('<span style="color:green;font-weight:bold;">' . __('None', 'thold') . '</span>', $host['id']);
			}else{
				form_selectable_cell('<span style="color:red;font-weight:bold;">' . db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . get_request_var('id')) . '</span>', $host['id']);
			}
			form_selectable_cell((isset($host_graphs[$host['id']]) ? $host_graphs[$host['id']] : 0), $host['id']);
			form_selectable_cell((isset($host_data_sources[$host['id']]) ? $host_data_sources[$host['id']] : 0), $host['id']);
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (sizeof($display_text) + 1) . '"><em>' . __('No Associated Devices Found', 'thold') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($hosts)) {
		print $nav;
	}

	form_hidden_box('tab', 'hosts', '');
	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('save_associate', '1', '');

	draw_actions_dropdown($assoc_actions);

	form_end();
}

function tholds($header_label) {
	global $item_rows, $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$thold_actions = array(1 => __('Associate', 'thold'), 2 => __('Disassociate', 'thold'));

	thold_request_validation();

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if (get_request_var('state') == '0') { $statefilter = "td.thold_enabled='off'"; }
			if (get_request_var('state') == '2') { $statefilter = "td.thold_enabled='on'"; }
			if (get_request_var('state') == '1') { $statefilter = '(td.thold_alert!=0 OR td.bl_alert>0)'; }
			if (get_request_var('state') == '3') { $statefilter = '(td.thold_alert!=0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert>0 AND td.bl_fail_count >= td.bl_fail_trigger)'; }
		}
	}

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	$sort  = get_request_var('sort_column') . ' ' . get_request_var('sort_direction');
	$limit = ($rows*(get_request_var('page')-1)) . ", $rows";

	if (!isempty_request_var('template') && get_request_var('template') != '-1') {
		$sql_where .= (!strlen($sql_where) ? '' : ' AND ') . 'td.data_template_id = ' . get_request_var('template');
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (!strlen($sql_where) ? '' : ' AND ') . "td.name LIKE '%" . get_request_var('filter') . "%'";
	}

	if ($statefilter != '') {
		$sql_where .= (!strlen($sql_where) ? '' : ' AND ') . $statefilter;
	}

	if (get_request_var('associated') == 'true') {
		$sql_where .= (!strlen($sql_where) ? '' : ' AND ') . '(td.notify_warning=' . get_request_var('id') . ' OR td.notify_alert=' . get_request_var('id') . ')';
	}

	$result = get_allowed_thresholds($sql_where, $sort, $limit, $total_rows);

	$data_templates = db_fetch_assoc('SELECT DISTINCT dt.id, dt.name
		FROM data_template AS dt
		INNER JOIN thold_data AS td
		ON td.data_template_id = dt.id
		ORDER BY dt.name');

	html_start_box(__('Associated Thresholds', 'thold') . ' ' . htmlspecialchars($header_label) , '100%', '', '3', 'center', '');
	?>
	<tr class='even'>
		<td>
		<form id='listthold' method='get' action='notify_lists.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Template', 'thold');?>
					</td>
					<td>
						<select id='template' onChange='applyFilter()'>
							<option value='-1'><?php print __('Any', 'thold');?></option>
							<?php
							foreach ($data_templates as $row) {
								echo "<option value='" . $row['id'] . "'" . (isset_request_var('template') && $row['id'] == get_request_var('template') ? ' selected' : '') . '>' . $row['name'] . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('State', 'thold');?>
					</td>
					<td>
						<select id='state' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('state') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='1'<?php if (get_request_var('state') == '1') {?> selected<?php }?>><?php print __('Breached', 'thold');?></option>
							<option value='3'<?php if (get_request_var('state') == '3') {?> selected<?php }?>><?php print __('Triggered', 'thold');?></option>
							<option value='2'<?php if (get_request_var('state') == '2') {?> selected<?php }?>><?php print __('Enabled', 'thold');?></option>
							<option value='0'<?php if (get_request_var('state') == '0') {?> selected<?php }?>><?php print __('Disabled', 'thold');?></option>
						</select>
					</td>
					<td>
						<?php print __('Thresholds', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Associated', 'thold');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters', 'thold');?>'>
							<input type='button' name='clearf' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters', 'thold');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'notify_lists.php?header=false&action=edit&tab=tholds&id=<?php print get_request_var('id');?>'
			strURL += '&associated=' + $('#associated').is(':checked');;
			strURL += '&state=' + $('#state').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&template=' + $('#template').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'notify_lists.php?header=false&action=edit&tab=tholds&id=<?php print get_request_var('id');?>&clearf=true'
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

	$nav = html_nav_bar('notify_lists.php?action=edit&tab=tholds', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Thresholds', 'thold'), 'page', 'main');

	form_start('notify_lists.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'name'          => array(__('Name', 'thold'), 'ASC'),
		'id'            => array(__('ID', 'thold'), 'ASC'),
		'nosort1'       => array(__('Warning Lists', 'thold'), 'ASC'),
		'nosort2'       => array(__('Alert Lists', 'thold'), 'ASC'),
		'thold_type'    => array(__('Type', 'thold'), 'ASC'),
		'thold_alert'   => array(__('Triggered', 'thold'), 'ASC'),
		'thold_enabled' => array(__('Enabled', 'thold'), 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'notify_lists.php?action=edit&tab=tholds&id=' . get_filter_request_var('id'));

	$c=0;
	$i=0;
	if (count($result)) {
		foreach ($result as $row) {
			$c++;
			$alertstat='no';
			$bgcolor='green';
			if ($row['thold_type'] != 1) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
				}
			} else {
				if ($row['bl_alert'] == 1) {
					$alertstat='baseline-LOW';
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat='baseline-HIGH';
				}
			};

			/* show alert stats first */
			$alert_stat = '';
			$list = db_fetch_cell('SELECT count(*) FROM plugin_thold_threshold_contact WHERE thold_id=' . $row['id']);
			if ($list > 0) {
				$alert_stat = "<span style='font-weight:bold;color:green;'>" . __('Select Users', 'thold') . "</span>";
			}

			if (strlen($row['notify_extra'])) {
				$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:purple;'>" . __('Specific Emails', 'thold') . "</span>";
			}

			if (!empty($row['notify_alert'])) {
				if (get_request_var('id') == $row['notify_alert']) {
					$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:green;'>" . __('Current List', 'thold') . "</span>";
				}else{
					$alert_list = db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . $row['notify_alert']);
					$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:red;'>" . $alert_list . '</span>';
				}
			}

			if (!strlen($alert_stat)) {
				$alert_stat = "<span style='font-weight:bold;color:blue;'>" . __('Log Only', 'thold') . "</span>";
			}

			/* show warning stats first */
			$warn_stat = '';
			if (strlen($row['notify_warning_extra'])) {
				$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:purple;'>" . __('Specific Emails', 'thold') . "</span>";
			}

			if (!empty($row['notify_warning'])) {
				if (get_request_var('id') == $row['notify_warning']) {
					$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:green;'>" . __('Current List', 'thold') . "</span>";
				}else{
					$warn_list = db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . $row['notify_warning']);
					$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:red;'>" . $warn_list . '</span>';
				}
			}

			if ((!strlen($warn_stat)) &&
				(($row['thold_type'] == 0 && $row['thold_warning_hi'] == '' && $row['thold_warning_low'] == '') ||
				($row['thold_type'] == 2 && $row['time_warning_hi'] == '' && $row['time_warning_low'] == ''))) {
				$warn_stat  = "<span style='font-weight:bold;color:red;'>" . __('None', 'thold') . "</span>";
			} elseif (!strlen($warn_stat)) {
				$warn_stat  = "<span style='font-weight:bold;color:blue;'>" . __('Log Only', 'thold'). "</span>";
			}

			if ($row['name'] != '') {
				$name = $row['name'];
			}else{
				$name = $row['name_cache'] . ' [' . $row['data_source_name'] . ']';
			}

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell(filter_value($name, get_request_var('filter')), $row['id']);
			form_selectable_cell($row['id'], $row['id']);
			form_selectable_cell($warn_stat, $row['id']);
			form_selectable_cell($alert_stat, $row['id']);
			form_selectable_cell($thold_types[$row['thold_type']], $row['id']);
			form_selectable_cell($alertstat, $row['id']);
			form_selectable_cell((($row['thold_enabled'] == 'off') ? __('Disabled', 'thold'): __('Enabled', 'thold')), $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		print "<tr class='even' <td colspan='" . (sizeof($display_text) + 1) . "'><i>" . __('No Thresholds', 'thold'). "</i></td></tr>\n";
	}

	html_end_box(false);

	if (count($result)) {
		print $nav;
	}

	form_hidden_box('tab', 'tholds', '');
	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('save_tholds', '1', '');

	draw_actions_dropdown($thold_actions);

	form_end();
}

function templates($header_label) {
	global $config, $item_rows;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$thold_actions = array(1 => __('Associate', 'thold'), 2 => __('Disassociate', 'thold'));

	thold_template_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if (get_request_var('associated') == 'true') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . '(notify_warning=' . get_request_var('id') . ' OR notify_alert=' . get_request_var('id') . ')';
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_template.name LIKE '%" . get_request_var('filter') . "%'";
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql = "SELECT * FROM thold_template
		$sql_where
		$sql_order
		$sql_limit";

	$result = db_fetch_assoc($sql);

	html_start_box(__('Associated Templates', 'thold') . ' ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');
	?>
	<tr class='even'>
		<td>
		<form id='listthold' method='get' action='notify_lists.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Rows', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Associated', 'thold');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters', 'thold');?>'>
							<input type='button' id='clearf' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters', 'thold');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'notify_lists.php?header=false&action=edit&tab=templates&id=<?php print get_request_var('id');?>'
			strURL += '&associated=' + $('#associated').is(':checked');
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'notify_lists.php?header=false&action=edit&tab=templates&id=<?php print get_request_var('id');?>&clearf=true'
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

	$total_rows = db_fetch_cell("SELECT count(*)
		FROM thold_template
		$sql_where");

	$nav = html_nav_bar('notify_lists.php?action=edit&tab=templates&id=' . get_filter_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, 'Lists', 'page', 'main');

	form_start('notify_lists.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'name'       => array(__('Name', 'thold'), 'ASC'),
		'id'         => array(__('ID', 'thold'), 'ASC'),
		'nosort1'    => array(__('Warning Lists', 'thold'), 'ASC'),
		'nosort2'    => array(__('Alert Lists', 'thold'), 'ASC'),
		'thold_type' => array(__('Type', 'thold'), 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'notify_lists.php?action=edit&tab=templates&id=' . get_filter_request_var('id'));

	$c=0;
	$i=0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			$c++;

			/* show alert stats first */
			$alert_stat = '';
			$list = db_fetch_cell("SELECT count(*) FROM plugin_thold_template_contact WHERE template_id=" . $row["id"]);
			if ($list > 0) {
				$alert_stat = "<span style='font-weight:bold;color:green;'>" . __('Select Users', 'thold') . "</span>";
			}

			if (strlen($row['notify_extra'])) {
				$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:purple;'>" . __('Specific Emails', 'thold') . "</span>";
			}

			if (!empty($row['notify_alert'])) {
				if (get_request_var('id') == $row['notify_alert']) {
					$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:green;'>" . __('Current List', 'thold') . "</span>";
				}else{
					$alert_list = db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . $row['notify_alert']);
					$alert_stat .= (strlen($alert_stat) ? ', ':'') . "<span style='font-weight:bold;color:red;'>" . $alert_list . '</span>';
				}
			}

			if (!strlen($alert_stat)) {
				$alert_stat = "<span style='font-weight:bold;color:blue;'>" . __('Log Only', 'thold') . "</span>";
			}

			/* show warning stats first */
			$warn_stat = '';
			if (strlen($row['notify_warning_extra'])) {
				$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:purple;'>" . __('Specific Emails', 'thold') . "</span>";
			}

			if (!empty($row['notify_warning'])) {
				if (get_request_var('id') == $row['notify_warning']) {
					$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:green;'>" . __('Current List', 'thold'). "</span>";
				}else{
					$warn_list = db_fetch_cell('SELECT name FROM plugin_notification_lists WHERE id=' . $row['notify_warning']);
					$warn_stat .= (strlen($warn_stat) ? ', ':'') . "<span style='font-weight:bold;color:red;'>" . $warn_list . '</span>';
				}
			}

			if ((!strlen($warn_stat)) &&
				(($row['thold_type'] == 0 && $row['thold_warning_hi'] == '' && $row['thold_warning_low'] == '') ||
				($row['thold_type'] == 2 && $row['thold_time_warning_hi'] == '' && $row['thold_time_warning_low'] == ''))) {
				$warn_stat  = "<span style='font-weight:bold;color:red;'>" . __('None', 'thold') . "</span>";
			} elseif (!strlen($warn_stat)) {
				$warn_stat  = "<span style='font-weight:bold;color:blue;'>" . __('Log Only', 'thold') . "</span>";
			}

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell(filter_value($row['name'], get_request_var('filter')), $row['id']);
			form_selectable_cell($row['id'], $row['id']);
			form_selectable_cell($warn_stat, $row['id']);
			form_selectable_cell($alert_stat, $row['id']);
			form_selectable_cell($thold_types[$row['thold_type']], $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		print "<tr class='even'><td colspan='" . (sizeof($display_text) + 1) . "'><i>" . __('No Templates', 'thold') . "</i></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($result)) {
		print $nav;
	}

	form_hidden_box('tab', 'templates', '');
	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('save_templates', '1', '');

	draw_actions_dropdown($thold_actions);

	form_end();
}

function thold_template_request_validation() {
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

	validate_store_request_vars($filters, 'sess_nltt');
	/* ================= input validation ================= */
}

function thold_request_validation() {
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
			'default' => 'thold_alert',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
			),
		'template' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
			),
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_nlt');
	/* ================= input validation ================= */
}

function lists() {
	global $actions, $item_rows;

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
			)
	);

	validate_store_request_vars($filters, 'sess_lists');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Notification Lists', 'thold'), '100%', '', '3', 'center', 'notify_lists.php?action=edit');

	?>
	<tr class='even'>
		<td>
		<form id='lists' action='notify_lists.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold')?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						<?php print __('Lists', 'thold')?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' title='<?php print __esc('Set/Refresh Filters', 'thold');?>' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' title='<?php print __esc('Clear Filters', 'thold');?>' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'notify_lists.php?header=false';
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'notify_lists.php?header=false&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#lists').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%' OR
		description LIKE '%" . get_request_var('filter') . "%' OR
		emails LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM plugin_notification_lists
		$sql_where");

	$lists = db_fetch_assoc("SELECT id, name, description, emails
		FROM plugin_notification_lists
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);

	$nav = html_nav_bar('notify_lists.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Lists', 'thold'), 'page', 'main');

	form_start('notify_lists.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'        => array(__('List Name', 'thold'), 'ASC'),
		'description' => array(__('Description', 'thold'), 'ASC'),
		'emails'      => array(__('Emails', 'thold'), 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($lists)) {
		foreach ($lists as $item) {
			form_alternate_row('line' . $item['id'], true);
			form_selectable_cell(filter_value($item['name'], get_request_var('filter'), 'notify_lists.php?action=edit&id=' . $item['id']), $item['id'], '25%');
			form_selectable_cell(filter_value($item['description'], get_request_var('filter')), $item['id'], '35%');
			form_selectable_cell(filter_value($item['emails'], get_request_var('filter')), $item['id']);
			form_checkbox_cell($item['name'], $item['id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (sizeof($display_text) + 1) . '"><em>' . __('No Notification Lists', 'thold') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($lists)) {
		print $nav;
	}

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}
