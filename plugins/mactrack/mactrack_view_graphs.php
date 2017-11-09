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

$guest_account = true;
chdir('../../');
include('./include/auth.php');
include('./lib/html_tree.php');
include('./plugins/mactrack/lib/mactrack_functions.php');
include('./lib/timespan_settings.php');

$title = __('Device Tracking - Monitored Device Graph View', 'mactrack');

set_default_action();

mactrack_redirect();
general_header();
mactrack_tabs();
mactrack_view_graphs();
bottom_footer();

function mactrack_view_graphs() {
	global $title, $current_user, $colors, $config, $host_template_hashes, $graph_template_hashes;

	include('./lib/html_graph.php');

	html_graph_validate_preview_request_vars();

	if (!isset($_SESSION['sess_mt_gt'])) {
		$_SESSION['sess_mt_gt'] = implode(',', array_rekey(db_fetch_assoc('SELECT DISTINCT gl.graph_template_id 
			FROM graph_local AS gl 
			WHERE gl.host_id IN(
				SELECT host_id 
				FROM mac_track_devices
			)'), 'graph_template_id', 'graph_template_id'));
	}
	$gt = $_SESSION['sess_mt_gt'];

	if (!isset($_SESSION['sess_mt_hosts'])) {
		$_SESSION['sess_mt_hosts'] = implode(',', array_rekey(db_fetch_assoc('SELECT h.id 
			FROM host AS h 
			WHERE h.id IN (
				SELECT host_id 
				FROM mac_track_devices
			) 
			ORDER BY id DESC'), 'id', 'id'));
	}
	$hosts = $_SESSION['sess_mt_hosts'];

	/* include graph view filter selector */
	html_start_box($title . (isset_request_var('style') && strlen(get_request_var('style')) ? ' [ ' . __('Custom Graph List Applied - Filtering from List', 'mactrack') . ' ]':''), '100%', '', '3', 'center', '');

	if ($hosts != '') {
		$hq = 'h.id IN (' . $hosts . ')';
	}else{
		$hq = 'h.id = 0';
	}

	if ($gt != '') {
		$gq = 'gt.id IN (' . $gt . ')';
	}else{
		$gq = 'gt.id = 0';
	}

	html_graph_preview_filter('mactrack_view_graphs.php', 'graphs', $hq, $gq);

	html_end_box();

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	$sql_or = '';
	if (isset_request_var('style')) {
		if (get_request_var('style') == 'selective') {
			/* process selected graphs */
			if (!isempty_request_var('graph_list')) {
				foreach (explode(',',get_request_var('graph_list')) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}

			if (!isempty_request_var('graph_add')) {
				foreach (explode(',',get_request_var('graph_add')) as $item) {
					$graph_list[$item] = 1;
				}
			}

			/* remove items */
			if (!isempty_request_var('graph_remove')) {
				foreach (explode(',',get_request_var('graph_remove')) as $item) {
					unset($graph_list[$item]);
				}
			}

			$graph_array = array_keys($graph_list);

			if (sizeof($graph_array)) {
				$sql_or = array_to_sql_or($graph_array, 'gl.id');
			}
		}
	}

	$total_graphs = 0;

	// Filter sql_where
	$sql_where  = (get_request_var('filter') != '' ? "gtg.title_cache LIKE '%" . get_request_var('filter') . "%'":'');
	$sql_where .= ($sql_or != '' && $sql_where != '' ? ' AND ':'') . $sql_or;
	$sql_where .= ($sql_or != '' && $sql_where != '' ? ' AND ':'') . $hq . ' AND ' . $gq;

	// Host Id sql_where
	if (get_request_var('host_id') > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' gl.host_id=' . get_request_var('host_id');
	}

	// Graph Template Id sql_where
	if (get_request_var('graph_template_id') > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND':'') . ' gl.graph_template_id=' . get_request_var('graph_template_id');
	}

	$limit  = (get_request_var('graphs')*(get_request_var('page')-1)) . ',' . get_request_var('graphs');
	$order  = 'gtg.title_cache';

	$graphs = get_allowed_graphs($sql_where, $order, $limit, $total_graphs);	

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (preg_match('/page=[0-9]+/',basename($_SERVER['QUERY_STRING']))) {
		$nav_url = str_replace('&page=' . get_request_var('page'), '', get_browser_query_string());
	}else{
		$nav_url = get_browser_query_string() . '&host_id=' . get_request_var('host_id');
	}

	$nav_url = preg_replace('/((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)/', '', $nav_url);

	$nav = html_nav_bar($nav_url, MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_graphs, get_request_var('columns'), __('Graphs', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (get_request_var('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}else{
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}

	html_end_box();

	if ($total_graphs > 0) {
		print $nav;
	}
}

