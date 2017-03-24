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

function thold_show_tab () {
	global $config;
	if (api_user_realm_auth('thold_graph.php')) {
		$cp = false;
		if (basename($_SERVER['PHP_SELF']) == 'thold_graph.php' || basename($_SERVER['PHP_SELF']) == 'thold_view_failures.php' || basename($_SERVER['PHP_SELF']) == 'thold_view_normal.php')
			$cp = true;

		print '<a href="' . $config['url_path'] . 'plugins/thold/thold_graph.php"><img src="' . $config['url_path'] . 'plugins/thold/images/tab_thold' . ($cp ? '_down': '') . '.gif" alt="thold" align="absmiddle" border="0"></a>';
	}
}
