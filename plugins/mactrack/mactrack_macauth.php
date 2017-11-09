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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/
chdir('../../');

include('./include/auth.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

$maca_actions = array(
	1 => __('Delete', 'mactrack')
);

set_default_action();

switch (get_request_var('action')) {
case 'save':
	form_save();

	break;
case 'actions':
	form_actions();

	break;
case 'edit':
	top_header();
	mactrack_maca_edit();
	bottom_footer();
	break;
default:
	top_header();
	mactrack_maca();
	bottom_footer();

	break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_maca')) && (isempty_request_var('add_dq_y'))) {
		$mac_id = api_mactrack_maca_save(get_filter_request_var('mac_id'), 
			get_nfilter_request_var('mac_address'), 
			get_nfilter_request_var('description'));

		header('Location: mactrack_macauth.php?action=edit&id=' . (empty($mac_id) ? get_request_var('mac_id') : $mac_id));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $maca_actions, $fields_mactrack_maca_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_mactrack_maca_remove($selected_items[$i]);
				}
			}

			header('Location: mactrack_macauth.php');
			exit;
		}
	}

	/* setup some variables */
	$maca_list = ''; $i = 0;

	/* loop through each of the mac authorization items selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$maca_info = db_fetch_cell_prepared('SELECT mac_address 
				FROM mac_track_macauth 
				WHERE mac_id = ?', 
				array($matches[1]));

			$maca_list .= '<li>' . $maca_info . '</li>';
			$maca_array[] = $matches[1];
		}
	}

	top_header();

	html_start_box($maca_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	form_start('mactrack_macauth.php');

	if (!isset($maca_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Authorized Mac to delete.', 'mactrack') . "</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' name='save' value='" . __esc('Continue', 'mactrack') . "'>";

		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Authorized Mac\'s?', 'mactrack') . "</p>
					<ul>$maca_list</ul>
				</td>
			</tr>";
		}
	}

	print "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($maca_array) ? serialize($maca_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'mactrack') . "'>
			$save_html" : "<input type='button' onClick='cactiReturnTo()' value='" . __esc('Return', 'mactrack') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	bottom_footer();
}

function api_mactrack_maca_save($mac_id, $mac_address, $description) {
	$save['mac_id']      = $mac_id;
	$save['mac_address'] = form_input_validate($mac_address, 'mac_address', '', false, 3);
	$save['description'] = form_input_validate($description, 'description', '', false, 3);
	$save['added_date']  = date('Y-m-d h:i:s');
	$save['added_by']    = $_SESSION['sess_user_id'];

	$mac_id = 0;
	if (!is_error_message()) {
		$mac_id = sql_save($save, 'mac_track_macauth', 'mac_address', false);

		if ($mac_id) {
			db_execute('UPDATE mac_track_ports 
				SET authorized=1 
				WHERE mac_address LIKE "' . $mac_address . '%"');

			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $mac_id;
}

function api_mactrack_maca_remove($mac_id) {
	$mac_address = db_fetch_cell_prepared('SELECT mac_address 
		WHERE mac_id = ?', 
		array($mac_id));

	db_execute_prepared('DELETE FROM mac_track_macauth 
		WHERE mac_id = ?', 
		array($mac_id));

	db_execute('UPDATE mac_track_ports 
		SET authorized=0 
		WHERE mac_address LIKE "' . $mac_address . '%"');
}

/* ---------------------
    MacAuth Functions
   --------------------- */

function mactrack_maca_get_maca_records(&$sql_where, $rows, $apply_limits = TRUE) {
	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (mac_address LIKE '%" . get_request_var('filter') . "%' OR " .
			"description LIKE '%" . get_request_var('filter') . "%')";
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	}else{
		$sql_limit = '';
	}

	$query_string = "SELECT *
		FROM mac_track_macauth
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($query_string);
}

function mactrack_maca_edit() {
	global $fields_mactrack_maca_edit;

	/* ================= input validation ================= */
	get_filter_request_var('mac_id');
	/* ==================================================== */

	display_output_messages();

	if (!isempty_request_var('mac_id')) {
		$mac_record   = db_fetch_row_prepared('SELECT * 
			FROM mac_track_macauth 
			WHERE mac_id = ?', 
			array(get_request_var('mac_id')));

		$header_label = __('Device Tracking MacAuth [edit: %s]', $mac_record['mac_address'], 'mactrack');
	}else{
		$header_label = __('Device Tracking MacAuth [new]', 'mactrack');
	}

	form_start('mactrack_macauth.php', 'mactrack_macauth');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_mactrack_maca_edit, (isset($mac_record) ? $mac_record : array()))
		)
	);

	html_end_box();

	form_save_button('mactrack_macauth.php', 'return');
}

function mactrack_maca() {
	global $maca_actions, $config, $item_rows;

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
			'default' => 'mac_address',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_mt_maca');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Tracking MacAuth Filters', 'mactrack'), '100%', '', '3', 'center', 'mactrack_macauth.php?action=edit');
	mactrack_maca_filter();
	html_end_box();

	$sql_where = '';

	$maca = mactrack_maca_get_maca_records($sql_where, $rows);

	$total_rows = db_fetch_cell("SELECT count(*)
		FROM mac_track_macauth
		$sql_where");

	$display_text = array(
		'mac_address'    => array(__('Mac Address', 'mactrack'), 'ASC'),
		'nosort'         => array(__('Reason', 'mactrack'), 'ASC'),
		'added_date'     => array(__('Added/Modified', 'mactrack'), 'ASC'),
		'date_last_seen' => array(__('By', 'mactrack'), 'ASC')
	);

	$columns = sizeof($display_text) + 1;

	$nav = html_nav_bar('mactrack_macauth.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Authorized Mac Addresses', 'mactrack'), 'page', 'main');

	form_start('mactrack_macauth.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($maca)) {
		foreach ($maca as $mac) {
			form_alternate_row('line' . $mac['mac_id'], true);
			form_selectable_cell(filter_value($mac['mac_address'], get_request_var('filter'), 'mactrack_macauth.php?action=edit&mac_id=' . $mac['mac_id']), $mac['mac_id']);
			form_selectable_cell(filter_value($mac['description'], get_request_var('filter')), $mac['mac_id']);
			form_selectable_cell($mac['added_date'], $mac['mac_id']);
			form_selectable_cell(db_fetch_cell_prepared('SELECT full_name FROM user_auth WHERE id = ?', array($mac['added_by'])), $mac['mac_id']);
			form_checkbox_cell($mac['mac_address'], $mac['mac_id']);
			form_end_row();
		}
	}else{
		print '<tr><td colspan="' . $columns . '"><em>' . __('No Authorized Mac Addresses Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($maca)) {
		print $nav;
	}

	draw_actions_dropdown($maca_actions);
}

function mactrack_maca_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'mactrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('MAC\'s', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'mactrack');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' value='<?php print __esc('Go');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_macauth.php?header=false';
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_macauth.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#mactrack').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php
}

