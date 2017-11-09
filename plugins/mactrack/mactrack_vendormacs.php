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

set_default_action();

if (isset_request_var('export')) {
	mactrack_vmacs_export();
}else{
	top_header();
	mactrack_vmacs();
	bottom_footer();
}

function mactrack_vmacs_validate_request_vars() {
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
			'default' => 'vendor_mac',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_mt_vmacs');
	/* ================= input validation ================= */
}

function mactrack_vmacs_export() {
	global $site_actions, $config;

	mactrack_vmacs_validate_request_vars();

	$sql_where = '';

	$vmacs = mactrack_vmacs_get_vmac_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"vendor_mac","vendor_name","vendor_address"');

	if (sizeof($vmacs)) {
		foreach($vmacs as $vmac) {
			array_push($xport_array,'"' . $vmac['vendor_mac'] . '","' .
			$vmac['vendor_name'] . '","' .
			$vmac['vendor_address'] . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_site_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_vmacs_get_vmac_records(&$sql_where, $rows, $apply_limits = TRUE) {
	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (mac_track_oui_database.vendor_name LIKE '%" . get_request_var('filter') . "%' OR " .
			"mac_track_oui_database.vendor_mac LIKE '%" . get_request_var('filter') . "%' OR " .
			"mac_track_oui_database.vendor_address LIKE '%" . get_request_var('filter') . "%')";
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	}else{
		$sql_limit = '';
	}

	$query_string = "SELECT *
		FROM mac_track_oui_database
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($query_string);
}

function mactrack_vmacs() {
	global $site_actions, $config, $item_rows;

	mactrack_vmacs_validate_request_vars();

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Tracking Vendor Mac Filter', 'mactrack'), '100%', '', '3', 'center', '');
	mactrack_vmac_filter();
	html_end_box();

	$sql_where = '';

	$vmacs = mactrack_vmacs_get_vmac_records($sql_where, $rows);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM mac_track_oui_database
		$sql_where");

	$display_text = array(
		'vendor_mac'     => array(__('Vendor MAC', 'mactrack'), 'ASC'),
		'vendor_name'    => array(__('Corporation', 'mactrack'), 'ASC'),
		'vendor_address' => array(__('Address', 'mactrack'), 'ASC')
	);

	$columns = sizeof($display_text);

	$nav = html_nav_bar('mactrack_vendormacs.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Vendor Macs', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($vmacs)) {
		foreach ($vmacs as $vmac) {
			form_alternate_row();
				?>
				<td class='linkEditMain'><?php print $vmac['vendor_mac'];?></td>
				<td><?php print filter_value($vmac['vendor_name'], get_request_var('filter'));?></td>
				<td><?php print filter_value($vmac['vendor_address'], get_request_var('filter'));?></td>
			</tr>
			<?php
		}
	}else{
		print '<tr><td colspen="' . $columns . '"><em>' . __('No Device Tracking Vendor MACS Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($vmacs)) {
		print $nav;
	}
}

function mactrack_vmac_filter() {
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
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
							}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' value='<?php print __esc('Go', 'mactrack');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'mactrack');?>'>
							<input type='button' id='export' value='<?php print __esc('Export', 'mactrack');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_vendormacs.php?header=false';
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_vendormacs.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_vendormacs.php?export=true';
				document.location = strURL;
			}

			$(function() {
				$('#mactrack').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#export').click(function() {
					exportRows();
				});
			});

			</script>
		</td>
	</tr>
	<?php
}

