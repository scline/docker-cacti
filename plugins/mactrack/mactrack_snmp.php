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
include_once('./lib/snmp.php');

$mactrack_snmp_actions = array(
	1 => __('Delete', 'mactrack'),
	2 => __('Duplicate', 'mactrack'),
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
case 'save':
	form_mactrack_snmp_save();

	break;
case 'actions':
	form_mactrack_snmp_actions();

	break;
case 'item_movedown':
	mactrack_snmp_item_movedown();

	header('Location: mactrack_snmp.php?action=edit&id=' . get_filter_request_var('id'));
	break;
case 'item_moveup':
	mactrack_snmp_item_moveup();

	header('Location: mactrack_snmp.php?action=edit&id=' . get_filter_request_var('id'));
	break;
case 'item_remove':
	mactrack_snmp_item_remove();

	header('Location: mactrack_snmp.php?header=false&action=edit&id=' . get_filter_request_var('id'));
	break;
case 'item_edit':
	top_header();
	mactrack_snmp_item_edit();
	bottom_footer();

	break;
case 'edit':
	top_header();
	mactrack_snmp_edit();
	bottom_footer();

	break;
default:
	top_header();
	mactrack_snmp();
	bottom_footer();

	break;
}

function form_mactrack_snmp_save() {
	if (isset_request_var('save_component_mactrack_snmp')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']     = get_filter_request_var('id');
		$save['name']   = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);

		if (!is_error_message()) {
			$id = sql_save($save, 'mac_track_snmp');
			if ($id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: mactrack_snmp.php?header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
	}elseif (isset_request_var('save_component_mactrack_snmp_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('item_id');
		get_filter_request_var('id');
		/* ==================================================== */

		$save = array();
		$save['id']                   = form_input_validate(get_nfilter_request_var('item_id'), '', '^[0-9]+$', false, 3);
		$save['snmp_id']              = form_input_validate(get_nfilter_request_var('id'), 'snmp_id', '^[0-9]+$', false, 3);
		$save['sequence']             = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['snmp_readstring']      = form_input_validate(get_nfilter_request_var('snmp_readstring'), 'snmp_readstring', '', false, 3);
		$save['snmp_version']         = form_input_validate(get_nfilter_request_var('snmp_version'), 'snmp_version', '', false, 3);
		$save['snmp_username']        = form_input_validate(get_nfilter_request_var('snmp_username'), 'snmp_username', '', true, 3);
		$save['snmp_password']        = form_input_validate(get_nfilter_request_var('snmp_password'), 'snmp_password', '', true, 3);
		$save['snmp_auth_protocol']   = form_input_validate(get_nfilter_request_var('snmp_auth_protocol'), 'snmp_auth_protocol', '', true, 3);
		$save['snmp_priv_passphrase'] = form_input_validate(get_nfilter_request_var('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
		$save['snmp_priv_protocol']   = form_input_validate(get_nfilter_request_var('snmp_priv_protocol'), 'snmp_priv_protocol', '', true, 3);
		$save['snmp_context']         = form_input_validate(get_nfilter_request_var('snmp_context'), 'snmp_context', '', true, 3);
		$save['snmp_port']            = form_input_validate(get_nfilter_request_var('snmp_port'), 'snmp_port', '^[0-9]+$', false, 3);
		$save['snmp_timeout']         = form_input_validate(get_nfilter_request_var('snmp_timeout'), 'snmp_timeout', '^[0-9]+$', false, 3);
		$save['snmp_retries']         = form_input_validate(get_nfilter_request_var('snmp_retries'), 'snmp_retries', '^[0-9]+$', false, 3);
		$save['max_oids']             = form_input_validate(get_nfilter_request_var('max_oids'), 'max_oids', '^[0-9]+$', false, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'mac_track_snmp_items');

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: mactrack_snmp.php?header=false&action=item_edit&id=' . get_nfilter_request_var('id') . '&item_id=' . (empty($item_id) ? get_nfilter_request_var('id') : $item_id));
		}else{
			header('Location: mactrack_snmp.php?header=false&action=edit&id=' . get_nfilter_request_var('id'));
		}
	} else {
		raise_message(2);
		header('Location: mactrack_snmp.php?header=false');
	}

	exit;
}


/* ------------------------
 The 'actions' function
 ------------------------ */
function form_mactrack_snmp_actions() {
	global $config, $mactrack_snmp_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM mac_track_snmp WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM mac_track_snmp_items WHERE ' . str_replace('id', 'snmp_id', array_to_sql_or($selected_items, 'id')));
			}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_mactrack($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			}

			header('Location: mactrack_snmp.php?header=false');
			exit;
		}
	}

	/* setup some variables */
	$snmp_groups = ''; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$snmp_groups .= '<li>' . db_fetch_cell_prepared('SELECT name FROM mac_track_snmp WHERE id = ?', array($matches[1])) . '</li>';
			$mactrack_array[$i] = $matches[1];
			$i++;
		}
	}

	general_header();

	display_output_messages();

	?>
	<script type='text/javascript'>
	function goTo(strURL) {
		loadPageNoHeader(strURL);
	}
	</script>
	<?php

	form_start('mactrack_snmp.php', 'mactrack');

	html_start_box($mactrack_snmp_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (!isset($mactrack_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one SNMP Option.', 'mactrack') . "</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='" . __esc('Continue', 'mactrack') . "' name='save'>";

		if (get_nfilter_request_var("drp_action") == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following SNMP Option(s).', 'mactrack') . "</p>
					<ul>$snmp_groups</ul>
				</td>
			</tr>";
		}elseif (get_nfilter_request_var("drp_action") == '2') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following SNMP Option(s). You can optionally change the title format for the new SNMP Options.', 'mactrack') . "</p>
					<ul>$snmp_groups</ul>
					<p>" . __('Name Format:', 'mactrack') . '<br>'; form_text_box('name_format', __('<name> (1)', 'mactrack'), '', '255', '30', 'text'); print "</p>
				</td>
			</tr>";
		}
	}

	print "	<tr>
		<td align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($mactrack_array) ? serialize($mactrack_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var("drp_action") . "'>
			<input type='button' onClick='goTo(\"" . "mactrack_snmp.php" . "\")' value='" . ($save_html == '' ? __esc('Return', 'mactrack'):__esc('Cancel', 'mactrack')) . "' name='cancel'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	bottom_footer();
}

/* --------------------------
 mactrack Item Functions
 -------------------------- */
function mactrack_snmp_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('id');
	/* ==================================================== */

	move_item_down('mac_track_snmp_items', get_request_var('item_id'), 'snmp_id=' . get_request_var('id'));
}

function mactrack_snmp_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('id');
	/* ==================================================== */

	move_item_up('mac_track_snmp_items', get_request_var('item_id'), 'snmp_id=' . get_request_var('id'));
}

function mactrack_snmp_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM mac_track_snmp_items 
		WHERE id = ?', 
		array(get_request_var('item_id')));
}

function mactrack_snmp_item_edit() {
	global $config;
	global $fields_mactrack_snmp_item_edit;

	include_once($config['base_path'].'/plugins/mactrack/lib/mactrack_functions.php');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	/* ==================================================== */

	# fetch the current mactrack snmp record
	$snmp_option = db_fetch_row_prepared('SELECT * 
		FROM mac_track_snmp 
		WHERE id = ?', 
		array(get_request_var('id')));

	# if an existing item was requested, fetch data for it
	if (get_request_var('item_id', '') !== '') {
		$mactrack_snmp_item = db_fetch_row_prepared('SELECT * 
			FROM mac_track_snmp_items 
			WHERE id = ?', 
			array(get_request_var('item_id')));

		$header_label = __('SNMP Options [edit %s]', $snmp_option['name'], 'mactrack');
	}else{
		$header_label = __('SNMP Options [new]', 'mactrack');

		$mactrack_snmp_item = array();
		$mactrack_snmp_item['snmp_id'] = get_request_var('id');
		$mactrack_snmp_item['sequence'] = get_sequence('', 'sequence', 'mac_track_snmp_items', 'snmp_id=' . get_request_var('id'));
	}

	form_start(get_current_page(), 'mactrack_item_edit');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_mactrack_snmp_item_edit, (isset($mactrack_snmp_item) ? $mactrack_snmp_item : array()))
		)
	);

	html_end_box();

	form_hidden_box('item_id', (isset_request_var('item_id') ? get_request_var('item_id') : '0'), '');
	form_hidden_box('id', (isset($mactrack_snmp_item['snmp_id']) ? $mactrack_snmp_item['snmp_id'] : '0'), '');
	form_hidden_box('save_component_mactrack_snmp_item', '1', '');

	form_save_button(htmlspecialchars('mactrack_snmp.php?action=edit&id=' . get_request_var('id')));

	?>
	<script type='text/javascript'>
	$(function() {
		setSNMP();
		$('#snmp_version').change(function() {
			setSNMP();
		});
	});
	</script>
	<?php
}

/* ---------------------
 mactrack Functions
 --------------------- */

function mactrack_snmp_edit() {
	global $config, $fields_mactrack_snmp_edit;

	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('page');
	/* ==================================================== */

	/* clean up rule name */
	if (isset_request_var('name')) {
		set_request_var('name', sanitize_search_string(get_request_var('name')));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_mt_snmp_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* display the mactrack snmp option set */
	$snmp_group = array();
	if (!isempty_request_var('id')) {
		$snmp_group = db_fetch_row_prepared('SELECT * 
			FROM mac_track_snmp 
			WHERE id = ?', 
			array(get_request_var('id')));

		$header_label = __('SNMP Option Set [edit: %s]', $snmp_group['name'], 'mactrack');
	}else{
		$header_label = __('SNMP Option Set [new]', 'mactrack');
	}

	form_start('mactrack_snmp.php', 'mactrack_snmp_group');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_mactrack_snmp_edit, $snmp_group)
		)
	);

	html_end_box();
	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('save_component_mactrack_snmp', '1', '');

	if (!isempty_request_var('id')) {
		$items = db_fetch_assoc_prepared('SELECT * 
			FROM mac_track_snmp_items 
			WHERE snmp_id= ? 
			ORDER BY sequence', 
			array(get_request_var('id')));

		html_start_box(__('Device Tracking SNMP Options', 'mactrack'), '100%', '', '3', 'center', 'mactrack_snmp.php?action=item_edit&id=' . get_request_var('id'));

		print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Item', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Version', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Community', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Port', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Timeout', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Retries', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Max OIDs', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Username', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Auth Proto', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Priv Proto', 'mactrack'),'',1);
		DrawMatrixHeaderItem(__('Actions', 'mactrack'),'',1);
		print '</tr>';

		$i = 1;
		if (sizeof($items)) {
			foreach ($items as $item) {
				form_alternate_row();
				$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars('mactrack_snmp.php?action=item_edit&item_id=' . $item['id'] . '&id=' . $item['snmp_id']) . '">Item#' . $i . '</a></td>';
				$form_data .= '<td>' . 	$item['snmp_version'] . '</td>';
				$form_data .= '<td>' . 	($item['snmp_version'] == 3 ? __('N/A', 'mactrack') : $item['snmp_readstring']) . '</td>';
				$form_data .= '<td>' . 	$item['snmp_port'] . '</td>';
				$form_data .= '<td>' . 	$item['snmp_timeout'] . '</td>';
				$form_data .= '<td>' . 	$item['snmp_retries'] . '</td>';
				$form_data .= '<td>' . 	$item['max_oids'] . '</td>';
				$form_data .= '<td>' . 	($item['snmp_version'] == 3 ? $item['snmp_username'] : __('N/A', 'mactrack')) . '</td>';
				$form_data .= '<td>' . 	($item['snmp_version'] == 3 ? $item['snmp_auth_protocol'] : __('N/A', 'mactrack')) . '</td>';
				$form_data .= '<td>' . 	($item['snmp_version'] == 3 ? $item['snmp_priv_protocol'] : __('N/A', 'mactrack')) . '</td>';
				$form_data .= '<td class="right">' .
					($i < sizeof($items) ? '<a class="remover fa fa-caret-down moveArrow" href="' . htmlspecialchars($config['url_path'] . 'plugins/mactrack/mactrack_snmp.php?action=item_movedown&item_id=' . $item["id"] . '&id=' . $item["snmp_id"]) . '"></a>':'<span class="moveArrowNone"></span>') .
					($i > 1 ? '<a class="remover fa fa-caret-up moveArrow" href="' . htmlspecialchars($config['url_path'] . 'plugins/mactrack/mactrack_snmp.php?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $item["snmp_id"]) .'"></a>':'<span class="moveArrowNone"></span>');

				$form_data .= '<a class="delete deleteMarker fa fa-remove" href="' . htmlspecialchars($config['url_path'] . 'plugins/mactrack/mactrack_snmp.php?action=item_remove&item_id=' . $item["id"] .	'&id=' . $item["snmp_id"]) . '"></a>' . '</td></tr>';

				print $form_data;

				$i++;
			}
		} else {
			print '<tr><td colspan="5"><em>' . __('No SNMP Items Found', 'mactrack') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('mactrack_snmp.php');
}

function mactrack_snmp() {
	global $config, $item_rows;
	global $mactrack_snmp_actions;

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
			)
	);

	validate_store_request_vars($filters, 'sess_mt_snmp');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Tracking SNMP Options', 'mactrack'), '100%', '', '3', 'center', 'mactrack_snmp.php?action=edit');
	snmp_options_filter();
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (get_request_var('filter') != '') {
		$sql_where .= "WHERE (mac_track_snmp.name LIKE '%" . get_request_var('filter') . "%')";
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_snmp.id)
		FROM mac_track_snmp
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;

	$snmp_groups = db_fetch_assoc("SELECT *
		FROM mac_track_snmp
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(__('Title of SNMP Option Set', 'mactrack'), 'ASC'),
	);

	$columns = sizeof($display_text) + 1;

	$nav = html_nav_bar('mactrack_snmp.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('SNMP Settings', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($snmp_groups)) {
		foreach ($snmp_groups as $snmp_group) {
			form_alternate_row('line' . $snmp_group['id'], true);
			form_selectable_cell(filter_value($snmp_group['name'], get_request_var('filter'), 'mactrack_snmp.php?action=edit&id=' . $snmp_group['id'] . '&page=1'), $snmp_group['id']);
			form_checkbox_cell($snmp_group['name'], $snmp_group['id']);
			form_end_row();
		}
	}else{
		print '<tr><td colspan="3"><em>' . __('No SNMP Option Sets Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($snmp_groups)) {
		print $nav;
	}

	draw_actions_dropdown($mactrack_snmp_actions);

	form_end();
}

function snmp_options_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
		<form id='mactrack_snmp' action='mactrack_snmp.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'mactrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Options', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected':'');?>><?php print __('Default', 'mactrack');?></option>
							<?php if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"';
									if (get_request_var('rows') == $key) {
										print ' selected';
									}
									print '>' . $value . '</option>';
								}
							} ?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' value='<?php print __esc('Go', 'mactrack');?>' id='go'>
							<input type='button' value='<?php print __esc('Clear', 'mactrack');?>' id='clear'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('page');?>'>
		</td>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'mactrack_snmp.php?header=false';
			strURL += '&filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();

			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'mactrack_snmp.php?header=false&clear=true';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#go').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#mactrack_snmp').unbind().submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
	</tr><?php
}

