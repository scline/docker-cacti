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

function thold_upgrade_database () {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	thold_setup_database ();

	include_once($config['base_path'] . '/plugins/thold/setup.php');
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
	$v = plugin_thold_version();

	$oldv = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="thold"');

	db_execute('DELETE FROM settings WHERE name="plugin_thold_version"');

	// Check for needed Cacti Indexes
	if (!db_index_exists('graph_templates_item', 'task_item_id')) {
		db_add_index('graph_templates_item', 'INDEX', 'task_item_id', array('task_item_id'));
	}

	if (!db_index_exists('data_local', 'data_template_id')) {
		db_add_index('data_local', 'INDEX', 'data_template_id', array('data_template_id'));
	}

	if (!db_index_exists('data_local', 'snmp_query_id')) {
		db_add_index('data_local', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
	}

	if (!db_index_exists('host_snmp_cache', 'snmp_query_id')) {
		db_add_index('host_snmp_cache', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
	}

	if (!db_index_exists('data_template_rrd', 'data_source_name')) {
		db_add_index('data_template_rrd', 'INDEX', 'data_source_name', array('data_source_name'));
	}

	// Added in thold v0.4
	if ($oldv < 0.4) {
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'name',              'type' => 'varchar(100)', 'NULL' => false, 'default' => '', 'after' => 'id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_hi',           'type' => 'varchar(100)', 'NULL' => true, 'after' => 'thold_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_low',          'type' => 'varchar(100)', 'NULL' => true, 'after' => 'time_hi'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_low'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_fail_length',  'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_type',        'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'thold_enabled'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_type',         'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'notify_extra'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'percent_ds',        'type' => 'varchar(64)', 'NULL' => false, 'default' => 0, 'after' => 'cdef'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'tcheck',            'type' => 'int(1)', 'NULL' => false, 'default' => 0));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'exempt',            'type' => 'char(3)', 'NULL' => false, 'default' => ''));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'local_graph_id',    'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'data_id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'graph_id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_template_id',  'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'graph_template_id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'restored_alert',    'type' => 'char(3)', 'NULL' => false, 'default' => ''));

		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'name',              'type' => 'varchar(100)', 'NULL' => false, 'default' => '', 'after' => 'id'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_hi',           'type' => 'varchar(100)', 'NULL' => true, 'after' => 'thold_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_low',          'type' => 'varchar(100)', 'NULL' => true, 'after' => 'time_hi'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_low'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_fail_length',  'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'thold_enabled'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'data_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'syslog_priority'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => 0, 'after' => 'cdef'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => ''));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => ''));

		// Update our hooks
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "config_arrays"');
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "config_settings"');
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "draw_navigation_text"');
		db_execute('UPDATE plugin_hooks SET function = "thold_poller_bottom", file = "includes/polling.php" WHERE name = "thold" AND hook = "poller_bottom"');

		// Register the new hooks
		api_plugin_register_hook('thold', 'rrd_graph_graph_options', 'thold_rrd_graph_graph_options', 'setup.php');
		api_plugin_register_hook('thold', 'graph_buttons', 'thold_graph_button', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_array', 'thold_data_source_action_array', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_prepare', 'thold_data_source_action_prepare', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_execute', 'thold_data_source_action_execute', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_array', 'thold_graphs_action_array', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_prepare', 'thold_graphs_action_prepare', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_execute', 'thold_graphs_action_execute', 'setup.php');

		api_plugin_enable_hooks('thold');

		// Fix our realms
		db_execute('UPDATE plugin_realms SET file = "thold.php" WHERE display = "Configure Thresholds"');
		api_plugin_register_realm('thold', 'thold_templates.php', 'Configure Threshold Templates', 1);

		db_add_index('thold_data', 'INDEX', 'tcheck', array('tcheck'));
		db_add_index('thold_data', 'INDEX', 'local_graph_id', array('local_graph_id'));
		db_add_index('thold_data', 'INDEX', 'graph_template_id', array('graph_template_id'));
		db_add_index('thold_data', 'INDEX', 'data_template_id', array('data_template_id'));

		// Rename some columns
		db_execute('ALTER TABLE thold_data CHANGE COLUMN rra_id local_data_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN data_id data_template_rrd_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN template thold_template_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN data_template data_template_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN graph_id local_graph_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN graph_template graph_template_id int(11) UNSIGNED NOT NULL default "0"');

		db_execute('ALTER TABLE plugin_thold_log CHANGE COLUMN graph_id local_graph_id int(11) UNSIGNED NOT NULL default "0"');

		/* Set the default names on threshold and templates */
		db_execute("UPDATE thold_data, data_template_data, data_template_rrd SET
			 thold_data.name = CONCAT_WS('',data_template_data.name_cache, ' [', data_template_rrd.data_source_name, ']', '')
			 WHERE data_template_data.local_data_id = thold_data.local_data_id AND data_template_rrd.id = thold_data.data_template_rrd_id AND thold_data.name = ''");
		db_execute("UPDATE thold_template SET name = CONCAT_WS('', data_template_name, ' [', data_source_name, ']', '') WHERE name = ''");

		/* Set the graph_ids for all thresholds */
		db_execute('UPDATE thold_data, graph_templates_item, data_template_rrd
			 SET thold_data.local_graph_id = graph_templates_item.local_graph_id, 
				thold_data.graph_template_id = graph_templates_item.graph_template_id, 
				thold_data.data_template_id = data_template_rrd.data_template_id
			 WHERE data_template_rrd.local_data_id=thold_data.local_data_id AND data_template_rrd.id=graph_templates_item.task_item_id');
	}
	// End 0.4 Upgrade

	if (version_compare($oldv, '0.4.3', '<')) {
		// Fix a few hooks
		db_execute('DELETE FROM plugin_hooks WHERE name = "thold" AND hook = "config_insert"');
		db_execute('DELETE FROM plugin_hooks WHERE name = "thold" AND hook = "config_arrays"');

		api_plugin_register_hook('thold', 'config_insert', 'thold_config_insert', 'includes/settings.php');
		api_plugin_register_hook('thold', 'config_arrays', 'thold_config_arrays', 'includes/settings.php');

		api_plugin_enable_hooks('thold');

		$e = strtolower(db_fetch_cell("SELECT `value` FROM settings WHERE `name` = 'thold_from_email'"));
		if ($e == 'cacti@cactiusers.org') {
			db_execute("UPDATE settings SET `value`='cacti@localhost' WHERE `name`='thold_from_email'");
		}
	}

	if (version_compare($oldv, '0.4.4', '<')) {
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'lasttime', 'type' => 'TIMESTAMP', 'NULL' => false, 'after' => 'lastread'));
		db_execute('ALTER TABLE thold_data ADD COLUMN bl_thold_valid INT UNSIGNED NOT NULL DEFAULT 0', FALSE);
		db_execute('ALTER TABLE thold_data MODIFY name varchar(150) default NULL');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN bl_pct_down varchar(100)');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN bl_pct_up varchar(100)');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN bl_pct_down varchar(100)');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN bl_pct_up varchar(100)');
	}

	if (version_compare($oldv, '0.4.5', '<')) {
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0') );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_warning_low', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'notify_warning_extra', 'type' => 'text', 'NULL' => true) );

		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0') );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_warning_low', 'type' => 'varchar(100)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'notify_warning_extra', 'type' => 'text', 'NULL' => true) );

		db_execute('ALTER TABLE thold_data MODIFY COLUMN notify_extra text');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN notify_extra text');

		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => false);
		$data['columns'][] = array('name' => 'description', 'type' => 'varchar(512)', 'NULL' => false);
		$data['columns'][] = array('name' => 'emails', 'type' => 'varchar(512)', 'NULL' => false);
		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Notification Lists';
		api_plugin_db_table_create ('thold', 'plugin_notification_lists', $data);

		api_plugin_db_add_column ('thold', 'host', array('name' => 'thold_send_email', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1', 'after' => 'disabled'));
		api_plugin_db_add_column ('thold', 'host', array('name' => 'thold_host_email', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'after' => 'thold_send_email'));

		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'notify_warning', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1', 'after' => 'notify_warning_extra'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'notify_alert', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1', 'after' => 'notify_warning_extra'));

		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'notify_warning', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1', 'after' => 'notify_warning_extra'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'notify_alert', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1', 'after' => 'notify_warning_extra'));

		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => true, 'after' => 'id'));

		if (db_column_exists('thold_data', 'bl_enabled', false)) {
			db_execute("ALTER TABLE thold_data REMOVE COLUMN bl_enabled", FALSE);
		}

		if (db_column_exists('thold_template', 'bl_enabled', false)) {
			db_execute("ALTER TABLE thold_template REMOVE COLUMN bl_enabled", FALSE);
		}

		api_plugin_register_hook('thold', 'config_form', 'thold_config_form', 'includes/settings.php');
		api_plugin_register_realm('thold', 'notify_lists.php', 'Manage Notification Lists', 1);

		/* set unique hash values for all thold templates */
		$templates = db_fetch_assoc("SELECT id FROM thold_template");
		if (sizeof($templates)) {
			foreach($templates as $t) {
				$hash = get_hash_thold_template($t['id']);
				db_execute("UPDATE thold_template SET hash='$hash' WHERE id=" . $t['id']);
			}
		}
	}

	if (version_compare($oldv, '0.4.7', '<')) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'unsigned' => true, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
		$data['columns'][] = array('name' => 'host_id', 'type' => 'int(12)', 'unsigned' => true, 'NULL' => false);
		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Devices in a Down State';
		api_plugin_db_table_create ('thold', 'plugin_thold_host_failed', $data);

		db_execute("DELETE FROM settings WHERE name='thold_failed_hosts'");

		/* increase the size of the settings table */
		db_execute("ALTER TABLE settings MODIFY column `value` varchar(1024) not null default ''");
	}

	if (version_compare($oldv, '0.6', '<')) {
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3') );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2') );
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_daemon_pid', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '') );

		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true) );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3') );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2') );

		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false);
		$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
		$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
		$data['columns'][] = array('name' => 'rrd_reindexed', 'type' => 'varchar(600)', 'NULL' => false);
		$data['columns'][] = array('name' => 'rrd_time_reindexed', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false);

		$data['keys'][]    = array('name' => 'id', 'columns' => 'id`, `pid');
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Table of Poller Outdata needed for queued daemon processes';
		api_plugin_db_table_create ('thold', 'plugin_thold_daemon_data', $data);

		$data = array();
		$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
		$data['columns'][] = array('name' => 'start', 'type' => 'double', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'end', 'type' => 'double', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'processed_items', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');

		$data['primary']   = 'pid';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Table of Thold Daemon Processes being queued';
		api_plugin_db_table_create ('thold', 'plugin_thold_daemon_processes', $data);

		// Rename some columns
		db_execute('ALTER TABLE thold_data CHANGE COLUMN rra_id local_data_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN data_id data_template_rrd_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN template thold_template_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN data_template data_template_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN graph_id local_graph_id int(11) UNSIGNED NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data CHANGE COLUMN graph_template graph_template_id int(11) UNSIGNED NOT NULL default "0"');

		db_execute('ALTER TABLE plugin_thold_log CHANGE COLUMN graph_id local_graph_id int(11) UNSIGNED NOT NULL default "0"');
	}

	if (version_compare($oldv, '1.0', '<')) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'device_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'thold_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Device Template Threshold Templates';
		api_plugin_db_table_create ('thold', 'plugin_thold_device_template', $data);

		api_plugin_register_hook('thold', 'device_template_edit', 'thold_device_template_edit', 'setup.php');
		api_plugin_register_hook('thold', 'device_template_top', 'thold_device_template_top', 'setup.php');
		api_plugin_register_hook('thold', 'device_edit_pre_bottom', 'thold_device_edit_pre_bottom', 'setup.php');
		api_plugin_register_hook('thold', 'api_device_new', 'thold_api_device_new', 'setup.php');

		if (api_plugin_is_enabled('thold')) {
			api_plugin_enable_hooks('thold');
		}
	}

	if (version_compare($oldv, '1.0.1', '<')) {
		api_plugin_db_add_column ('thold', 'thold_data',     array('name' => 'thold_hrule_alert',   'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'after' => 'exempt'));
		api_plugin_db_add_column ('thold', 'thold_data',     array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'after' => 'thold_hrule_alert') );
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_hrule_alert',   'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'after' => 'exempt'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'after' => 'thold_hrule_alert') );
	}

	if (version_compare($oldv, '1.0.2', '<')) {
		db_execute('ALTER TABLE thold_data MODIFY COLUMN expression VARCHAR(128) NOT NULL DEFAULT ""');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN expression VARCHAR(128) NOT NULL DEFAULT ""');
	}

	if (version_compare($oldv, '1.0.3', '<')) {
		api_plugin_db_add_column ('thold', 'thold_data', 
			array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '', 'after' => 'thold_daemon_pid'));
		api_plugin_db_add_column ('thold', 'thold_template', 
			array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '', 'after' => 'snmp_event_warning_severity'));
	}

	if (!db_column_exists('plugin_thold_daemon_processes', 'poller_id')) {
		db_execute("ALTER TABLE plugin_thold_daemon_processes 
			ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' FIRST,
			MODIFY COLUMN start double NOT NULL default '0',
			MODIFY COLUMN end double NOT NULL default '0',
			DROP PRIMARY KEY, ADD PRIMARY KEY (`poller_id`, `pid`)");
	}

	if (!db_column_exists('plugin_thold_daemon_data', 'poller_id')) {
		db_execute("ALTER TABLE plugin_thold_daemon_data 
			ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' AFTER `id`,
			ADD KEY `poller_id` (`poller_id`),
			ADD PRIMARY KEY (`id`, `pid`),
			DROP KEY `id`");
	}

	if (!db_column_exists('plugin_thold_host_failed', 'poller_id')) {
		db_execute("ALTER TABLE plugin_thold_host_failed 
			ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' AFTER `id`,
			ADD KEY `poller_id` (`poller_id`)");
	}

	if (!db_index_exists('thold_data', 'thold_daemon_pid')) {
		db_execute('ALTER TABLE thold_data ADD INDEX thold_daemon_pid (thold_daemon_pid)');
	}

	db_execute('UPDATE settings SET value = "' . $v['version'] . '" WHERE name = "plugin_thold_version"');
	db_execute('UPDATE plugin_config SET version = "' . $v['version'] . '" WHERE directory = "thold"');
}

function thold_setup_database () {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'local_data_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_rrd_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_alert', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'lastread', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lasttime', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'oldvalue', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_default', 'type' => "enum('on','off')", 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_priority', 'type' => 'int(2)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'template_enabled', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tcheck', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hrule_alert', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'bl_thold_valid', 'type' => 'int(10)', 'NULL' => false, 'default' => '0', 'unsigned' => true);
	$data['columns'][] = array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2');
	$data['columns'][] = array('name' => 'thold_daemon_pid', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '');

	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'local_data_id', 'columns' => 'local_data_id');
	$data['keys'][] = array('name' => 'data_template_rrd_id', 'columns' => 'data_template_rrd_id');
	$data['keys'][] = array('name' => 'local_graph_id', 'columns' => 'local_graph_id');
	$data['keys'][] = array('name' => 'thold_template_id', 'columns' => 'thold_template_id');
	$data['keys'][] = array('name' => 'thold_enabled', 'columns' => 'thold_enabled');
	$data['keys'][] = array('name' => 'template_enabled', 'columns' => 'template_enabled');
	$data['keys'][] = array('name' => 'tcheck', 'columns' => 'tcheck');
	$data['keys'][] = array('name' => 'thold_daemon_pid', 'columns' => 'thold_daemon_pid');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Threshold data';
	api_plugin_db_table_create ('thold', 'thold_data', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_template_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_source_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_friendly', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_default', 'type' => "enum('on','off')", 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hrule_alert', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2');
	$data['columns'][] = array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '');

	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'id', 'columns' => 'id');
	$data['keys'][]    = array('name' => 'data_source_id', 'columns' => 'data_source_id');
	$data['keys'][]    = array('name' => 'data_template_id', 'columns' => 'data_template_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Thresholds defaults for graphs';
	api_plugin_db_table_create ('thold', 'thold_template', $data);

	$data = array();
	$data['columns'][]     = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]     = array('name' => 'user_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][]     = array('name' => 'type', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]     = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary']       = 'id';
	$data['keys'][]        = array('name' => 'type', 'columns' => 'type');
	$data['keys'][]        = array('name' => 'user_id', 'columns' => 'user_id');
	$data['unique_keys'][] = array('name' => 'user_id_type', 'columns' => 'user_id`, `type');
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Table of Threshold contacts';
	api_plugin_db_table_create ('thold', 'plugin_thold_contacts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'template_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'contact_id', 'type' => 'int(12)', 'NULL' => false);
	$data['keys'][]    = array('name' => 'template_id', 'columns' => 'template_id');
	$data['keys'][]    = array('name' => 'contact_id', 'columns' => 'contact_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Tholds Template Contacts';
	api_plugin_db_table_create ('thold', 'plugin_thold_template_contact', $data);

	$data = array();
	$data['columns'][] = array('name' => 'thold_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'contact_id', 'type' => 'int(12)', 'NULL' => false);

	$data['keys'][]    = array('name' => 'thold_id', 'columns' => 'thold_id');
	$data['keys'][]    = array('name' => 'contact_id', 'columns' => 'contact_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Tholds Threshold Contacts';
	api_plugin_db_table_create ('thold', 'plugin_thold_threshold_contact', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'time', 'type' => 'int(24)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_value', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'current', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'status', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'type', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(255)', 'NULL' => false);

	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'time', 'columns' => 'time');
	$data['keys'][]    = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][]    = array('name' => 'local_graph_id', 'columns' => 'local_graph_id');
	$data['keys'][]    = array('name' => 'threshold_id', 'columns' => 'threshold_id');
	$data['keys'][]    = array('name' => 'status', 'columns' => 'status');
	$data['keys'][]    = array('name' => 'type', 'columns' => 'type');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of All Threshold Breaches';
	api_plugin_db_table_create ('thold', 'plugin_thold_log', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(512)', 'NULL' => false);
	$data['columns'][] = array('name' => 'emails', 'type' => 'varchar(512)', 'NULL' => false);

	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Notification Lists';
	api_plugin_db_table_create ('thold', 'plugin_notification_lists', $data);

	api_plugin_register_hook('thold', 'host_edit_bottom', 'thold_host_edit_bottom', 'setup.php');

	api_plugin_db_add_column ('thold', 'host', array('name' => 'thold_send_email', 'type' => 'int(10)', 'NULL' => false, 'default' => '1', 'after' => 'disabled'));
	api_plugin_db_add_column ('thold', 'host', array('name' => 'thold_host_email', 'type' => 'int(10)', 'NULL' => true, 'after' => 'thold_send_email'));

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'unsigned' => true, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(12)', 'unsigned' => true, 'NULL' => false);

	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Devices in a Down State';
	api_plugin_db_table_create ('thold', 'plugin_thold_host_failed', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
	$data['columns'][] = array('name' => 'rrd_reindexed', 'type' => 'varchar(600)', 'NULL' => false);
	$data['columns'][] = array('name' => 'rrd_time_reindexed', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false);

	$data['keys'][]    = array('name' => 'id', 'columns' => 'id`, `pid');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Poller Outdata needed for queued daemon processes';
	api_plugin_db_table_create ('thold', 'plugin_thold_daemon_data', $data);

	$data = array();
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
	$data['columns'][] = array('name' => 'start', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'end', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'processed_items', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');

	$data['primary']   = 'pid';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Thold Daemon Processes being queued';
	api_plugin_db_table_create ('thold', 'plugin_thold_daemon_processes', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Table of Device Template Threshold Templates';
	api_plugin_db_table_create ('thold', 'plugin_thold_host_template', $data);

	if (!db_index_exists('data_local', 'data_template_id')) {
		db_add_index('data_local', 'INDEX', 'data_template_id', array('data_template_id'));
	}

	if (!db_index_exists('data_local', 'snmp_query_id')) {
		db_add_index('data_local', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
	}

	if (!db_index_exists('host_snmp_cache', 'snmp_query_id')) {
		db_add_index('host_snmp_cache', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
	}

	/* increase the size of the settings table */
	db_execute("ALTER TABLE settings MODIFY column `value` varchar(4096) not null default ''");
}
