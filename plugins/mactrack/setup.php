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

function plugin_mactrack_install() {
	api_plugin_register_hook('mactrack', 'top_header_tabs',       'mactrack_show_tab',             'setup.php');
	api_plugin_register_hook('mactrack', 'top_graph_header_tabs', 'mactrack_show_tab',             'setup.php');
	api_plugin_register_hook('mactrack', 'config_arrays',         'mactrack_config_arrays',        'setup.php');
	api_plugin_register_hook('mactrack', 'draw_navigation_text',  'mactrack_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('mactrack', 'config_form',           'mactrack_config_form',          'setup.php');
	api_plugin_register_hook('mactrack', 'config_settings',       'mactrack_config_settings',      'setup.php');
	api_plugin_register_hook('mactrack', 'poller_bottom',         'mactrack_poller_bottom',        'setup.php');
	api_plugin_register_hook('mactrack', 'page_head',             'mactrack_page_head',            'setup.php');

	# device hook: intercept on device save
	api_plugin_register_hook('mactrack', 'api_device_save', 'sync_cacti_to_mactrack', 'mactrack_actions.php');
	# device hook: Add a new dropdown Action for Device Management
	api_plugin_register_hook('mactrack', 'device_action_array', 'mactrack_device_action_array', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: prepare the list of devices for a confirmation request
	api_plugin_register_hook('mactrack', 'device_action_prepare', 'mactrack_device_action_prepare', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: execute list of device
	api_plugin_register_hook('mactrack', 'device_action_execute', 'mactrack_device_action_execute', 'mactrack_actions.php');

	# Register our realms
	api_plugin_register_realm('mactrack', 'mactrack_view_ips.php,mactrack_view_arp.php,mactrack_view_macs.php,mactrack_view_sites.php,mactrack_view_devices.php,mactrack_view_interfaces.php,mactrack_view_graphs.php,mactrack_ajax.php', 'Device Tracking Viewer', 1);
	api_plugin_register_realm('mactrack', 'mactrack_ajax_admin.php,mactrack_devices.php,mactrack_snmp.php,mactrack_sites.php,mactrack_device_types.php,mactrack_utilities.php,mactrack_macwatch.php,mactrack_macauth.php,mactrack_vendormacs.php', 'Device Tracking Administrator', 1);

	mactrack_setup_table_new ();
}

function plugin_mactrack_uninstall () {
	db_execute('DROP TABLE IF EXISTS `mac_track_approved_macs`');
	db_execute('DROP TABLE IF EXISTS `mac_track_device_types`');
	db_execute('DROP TABLE IF EXISTS `mac_track_devices`');
	db_execute('DROP TABLE IF EXISTS `mac_track_interfaces`');
	db_execute('DROP TABLE IF EXISTS `mac_track_interface_graphs`');
	db_execute('DROP TABLE IF EXISTS `mac_track_ip_ranges`');
	db_execute('DROP TABLE IF EXISTS `mac_track_ips`');
	db_execute('DROP TABLE IF EXISTS `mac_track_macauth`');
	db_execute('DROP TABLE IF EXISTS `mac_track_macwatch`');
	db_execute('DROP TABLE IF EXISTS `mac_track_oui_database`');
	db_execute('DROP TABLE IF EXISTS `mac_track_ports`');
	db_execute('DROP TABLE IF EXISTS `mac_track_processes`');
	db_execute('DROP TABLE IF EXISTS `mac_track_scan_dates`');
	db_execute('DROP TABLE IF EXISTS `mac_track_scanning_functions`');
	db_execute('DROP TABLE IF EXISTS `mac_track_sites`');
	db_execute('DROP TABLE IF EXISTS `mac_track_temp_ports`');
	db_execute('DROP TABLE IF EXISTS `mac_track_vlans`');
	db_execute('DROP TABLE IF EXISTS `mac_track_aggregated_ports`');
	db_execute('DROP TABLE IF EXISTS `mac_track_snmp`');
	db_execute('DROP TABLE IF EXISTS `mac_track_snmp_items`');
}

function plugin_mactrack_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/mactrack/INFO', true);
	return $info['info'];
}

function plugin_mactrack_check_config () {
	/* Here we will check to ensure everything is configured */
	mactrack_check_upgrade();
	return true;
}

function plugin_mactrack_upgrade () {
	/* Here we will upgrade to the newest version */
	mactrack_check_upgrade();
	return false;
}

function mactrack_check_upgrade () {
	global $config;

	$files = array('index.php', 'plugins.php', 'mactrack_devices.php');
	if (!in_array(get_current_page(), $files)) {
		return;
	}

	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');
	
	$current = plugin_mactrack_version();
	$current = $current['version'];

	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='mactrack'");
	if (!sizeof($old) || $current != $old['version']) {
		/* if the plugin is installed and/or active */
		if (!sizeof($old) || $old['status'] == 1 || $old['status'] == 4) {
			/* re-register the hooks */
			plugin_mactrack_install();
			if (api_plugin_is_enabled('mactrack')) {
				# may sound ridiculous, but enables new hooks
				api_plugin_enable_hooks('mactrack');
			}

			/* perform a database upgrade */
			mactrack_database_upgrade();
		}

		if (read_config_option('mt_convert_readstrings', true) != 'on') {
			convert_readstrings();
		}

		// If are realms are not present in plugin_realms recreate them with the old realm ids (minus 100) so that upgraded installs are not broken
		if (!db_fetch_cell("SELECT id FROM plugin_realms WHERE plugin = 'mactrack'")) {
			db_execute("INSERT INTO plugin_realms (id, plugin, file, display) VALUES (2020, 'mactrack', 'mactrack_view_ips.php,mactrack_view_arp.php,mactrack_view_macs.php,mactrack_view_sites.php,mactrack_view_devices.php,mactrack_view_interfaces.php,mactrack_view_graphs.php,mactrack_ajax.php', 'Device Tracking Viewer')");
			db_execute("INSERT INTO plugin_realms (id, plugin, file, display) VALUES (2021, 'mactrack', 'mactrack_ajax_admin.php,mactrack_devices.php,mactrack_snmp.php,mactrack_sites.php,mactrack_device_types.php,mactrack_utilities.php,mactrack_macwatch.php,mactrack_macauth.php,mactrack_vendormacs.php', 'Device Tracking Administrator')");
		}

		/* rebuild the scanning functions */
		mactrack_rebuild_scanning_funcs();

		/* update the plugin information */
		$info = plugin_mactrack_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='mactrack'");

		db_execute("UPDATE plugin_config
			SET name='" . $info['longname'] . "',
			author='"   . $info['author']   . "',
			webpage='"  . $info['homepage'] . "',
			version='"  . $info['version']  . "'
			WHERE id='$id'");
	}
}

function mactrack_db_table_exists($table) {
	return sizeof(db_fetch_assoc("SHOW TABLES LIKE '$table'"));
}

function mactrack_db_column_exists($table, $column) {
	$found = false;

	if (mactrack_db_table_exists($table)) {
		$columns  = db_fetch_assoc("SHOW COLUMNS FROM $table");
		if (sizeof($columns)) {
			foreach($columns as $row) {
				if ($row['Field'] == $column) {
					$found = true;
					break;
				}
			}
		}
	}

	return $found;
}

function mactrack_db_key_exists($table, $index) {
	$found = false;

	if (mactrack_db_table_exists($table)) {
		$keys  = db_fetch_assoc("SHOW INDEXES FROM $table");
		if (sizeof($keys)) {
			foreach($keys as $key) {
				if ($key['Key_name'] == $index) {
					$found = true;
					break;
				}
			}
		}
	}

	return $found;
}

function mactrack_execute_sql($message, $syntax) {
	$result = db_execute($syntax);
}

function mactrack_create_table($table, $syntax) {
	if (!mactrack_db_table_exists($table)) {
		db_execute($syntax);
	}
}

function mactrack_add_column($table, $column, $syntax) {
	if (!mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_add_index($table, $index, $syntax) {
	if (!mactrack_db_key_exists($table, $index)) {
		db_execute($syntax);
	}
}

function mactrack_modify_column($table, $column, $syntax) {
	if (mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_delete_column($table, $column, $syntax) {
	if (mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_database_upgrade () {
	mactrack_add_column('mac_track_interfaces', 'ifHighSpeed',           "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifHighSpeed` int(10) unsigned NOT NULL default '0' AFTER `ifSpeed`");
	mactrack_add_column('mac_track_interfaces', 'ifDuplex',              "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifDuplex` int(10) unsigned NOT NULL default '0' AFTER `ifHighSpeed`");
	mactrack_add_column('mac_track_interfaces', 'int_ifInDiscards',      "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInDiscards` int(10) unsigned NOT NULL default '0' AFTER `ifOutErrors`");
	mactrack_add_column('mac_track_interfaces', 'int_ifInErrors',        "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifInDiscards`");
	mactrack_add_column('mac_track_interfaces', 'int_ifInUnknownProtos', "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInUnknownProtos` int(10) unsigned NOT NULL default '0' AFTER `int_ifInErrors`");
	mactrack_add_column('mac_track_interfaces', 'int_ifOutDiscards',     "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutDiscards` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUnknownProtos`");
	mactrack_add_column('mac_track_interfaces', 'int_ifOutErrors',       "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutDiscards`");
	mactrack_add_column('mac_track_devices',    'host_id',               "ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` int(10) unsigned NOT NULL default '0' AFTER `device_id`");
	mactrack_add_column('mac_track_macwatch',   'date_last_notif',       "ALTER TABLE `mac_track_macwatch` ADD COLUMN `date_last_notif` TIMESTAMP DEFAULT '0000-00-00 00:00:00' AFTER `date_last_seen`");
	mactrack_execute_sql('Add length to Device Types Match Fields', "ALTER TABLE `mac_track_device_types` MODIFY COLUMN `sysDescr_match` VARCHAR(100) NOT NULL default '', MODIFY COLUMN `sysObjectID_match` VARCHAR(100) NOT NULL default ''");
	mactrack_execute_sql('Correct a Scanning Function Bug', "DELETE FROM mac_track_scanning_functions WHERE scanning_function='Not Applicable - Hub/Switch'");
	mactrack_add_column('mac_track_devices', 'host_id', "ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` INTEGER UNSIGNED NOT NULL default '0' AFTER `device_id`");
	mactrack_add_index('mac_track_devices', 'host_id', 'ALTER TABLE `mac_track_devices` ADD INDEX `host_id`(`host_id`)');
	mactrack_add_index('mac_track_ports', 'scan_date', 'ALTER TABLE `mac_track_ports` ADD INDEX `scan_date` USING BTREE(`scan_date`)');

	if (!mactrack_db_column_exists('mac_track_interfaces', 'sysUptime')) {
		db_execute("ALTER TABLE mac_track_interfaces
		ADD COLUMN `sysUptime` int(10) unsigned NOT NULL default '0' AFTER `device_id`,
		ADD COLUMN `ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `vlan_trunk_status`,
		ADD COLUMN `ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `ifInOctets`,
		ADD COLUMN `ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifOutOctets`,
		ADD COLUMN `ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifHCInOctets`,
		ADD COLUMN `ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifHCOutOctets`,
		ADD COLUMN `ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInUcastPkts`,
		ADD COLUMN `ifInMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifOutUcastPkts`,
		ADD COLUMN `ifOutMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInMulticastPkts`,
		ADD COLUMN `ifInBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifOutMulticastPkts`,
		ADD COLUMN `ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInBroadcastPkts`,
		ADD COLUMN `inBound` double NOT NULL default '0' AFTER `ifOutErrors`,
		ADD COLUMN `outBound` double NOT NULL default '0' AFTER `inBound`,
		ADD COLUMN `int_ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `outBound`,
		ADD COLUMN `int_ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `int_ifInOctets`,
		ADD COLUMN `int_ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifOutOctets`,
		ADD COLUMN `int_ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifHCInOctets`,
		ADD COLUMN `int_ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifHCOutOctets`,
		ADD COLUMN `int_ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUcastPkts`
		ADD COLUMN `int_ifInMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutUcastPkts`,
		ADD COLUMN `int_ifOutMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInMulticastPkts`,
		ADD COLUMN `int_ifInBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutMulticastPkts`,
		ADD COLUMN `int_ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInBroadcastPkts`,");
			
	}

	if (!mactrack_db_key_exists('mac_track_ports', 'site_id_device_id')) {
		db_execute('ALTER TABLE `mac_track_ports` ADD INDEX `site_id_device_id`(`site_id`, `device_id`);');
	}

	# new for 2.1.2
	# SNMP V3
	mactrack_add_column('mac_track_devices',    'term_type',            "ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");
	mactrack_add_column('mac_track_devices',    'user_name',            "ALTER TABLE `mac_track_devices` ADD COLUMN `user_name` varchar(40) default NULL AFTER `term_type`");
	mactrack_add_column('mac_track_devices',    'user_password',        "ALTER TABLE `mac_track_devices` ADD COLUMN `user_password` varchar(40) default NULL AFTER `user_name`");
	mactrack_add_column('mac_track_devices',    'private_key_path',     "ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");
	mactrack_add_column('mac_track_devices',    'snmp_options',         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_options` int(10) unsigned NOT NULL default '0' AFTER `private_key_path`");
	mactrack_add_column('mac_track_devices',    'snmp_username',        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_username` varchar(50) default NULL AFTER `snmp_status`");
	mactrack_add_column('mac_track_devices',    'snmp_password',        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_password` varchar(50) default NULL AFTER `snmp_username`");
	mactrack_add_column('mac_track_devices',    'snmp_auth_protocol',   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_auth_protocol` char(5) default '' AFTER `snmp_password`");
	mactrack_add_column('mac_track_devices',    'snmp_priv_passphrase', "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");
	mactrack_add_column('mac_track_devices',    'snmp_priv_protocol',   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_protocol` char(6) default '' AFTER `snmp_priv_passphrase`");
	mactrack_add_column('mac_track_devices',    'snmp_context',         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_context` varchar(64) default '' AFTER `snmp_priv_protocol`");
	mactrack_add_column('mac_track_devices',    'max_oids',             "ALTER TABLE `mac_track_devices` ADD COLUMN `max_oids` int(12) unsigned default '10' AFTER `snmp_context`");
	mactrack_add_column('mac_track_devices',    'snmp_engine_id',       "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_engine_id` varchar(64) default '' AFTER `snmp_context`");

	mactrack_add_column('mac_track_snmp_items', 'snmp_engine_id',       "ALTER TABLE `mac_track_snmp_items` ADD COLUMN `snmp_engine_id` varchar(64) default '' AFTER `snmp_context`");

	if (!mactrack_db_table_exists('mac_track_snmp')) {
		mactrack_create_table('mac_track_snmp', "CREATE TABLE `mac_track_snmp` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(100) NOT NULL default '',
			PRIMARY KEY  (`id`))
			ENGINE=InnoDB COMMENT='Group of SNMP Option Sets';");
	}

	if (!mactrack_db_table_exists('mac_track_snmp_items')) {
		mactrack_create_table('mac_track_snmp_items', "CREATE TABLE `mac_track_snmp_items` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`snmp_id` int(10) unsigned NOT NULL default '0',
			`sequence` int(10) unsigned NOT NULL default '0',
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`max_oids` int(12) unsigned default '10',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			`snmp_engine_id` varchar(64) default '',
			PRIMARY KEY  (`id`,`snmp_id`))
			ENGINE=InnoDB COMMENT='Set of SNMP Options';");
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'mac_track_interface_graphs'"))) {
		db_execute("CREATE TABLE `mac_track_interface_graphs` (
			`device_id` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL,
			`ifName` varchar(20) NOT NULL default '',
			`host_id` int(11) NOT NULL default '0',
			`local_graph_id` int(10) unsigned NOT NULL,
			`snmp_query_id` int(11) NOT NULL default '0',
			`graph_template_id` int(11) NOT NULL default '0',
			`field_name` varchar(20) NOT NULL default '',
			`field_value` varchar(25) NOT NULL default '',
			`present` tinyint(4) default '1',
			PRIMARY KEY  (`local_graph_id`,`device_id`,`ifIndex`, `host_id`),
			KEY `host_id` (`host_id`),
			KEY `device_id` (`device_id`)
			) ENGINE=InnoDB;"
		);
	}

	mactrack_add_column('mac_track_devices',
		'term_type',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");
	mactrack_add_column('mac_track_devices',
		'private_key_path',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");
	mactrack_add_column('mac_track_interfaces',
		'ifMauAutoNegAdminStatus',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegAdminStatus` integer UNSIGNED NOT NULL default '0' AFTER `ifDuplex`");
	mactrack_add_column('mac_track_interfaces',
		'ifMauAutoNegRemoteSignaling',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegRemoteSignaling` integer UNSIGNED NOT NULL default '0' AFTER `ifMauAutoNegAdminStatus`");
	mactrack_add_column('mac_track_device_types',
		'serial_number_oid',
		"ALTER TABLE `mac_track_device_types` ADD COLUMN `serial_number_oid` varchar(100) default '' AFTER `ip_scanning_function`");
	mactrack_add_column('mac_track_sites',
		'customer_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `customer_contact` varchar(150) default '' AFTER `site_name`");
	mactrack_add_column('mac_track_sites',
		'netops_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `netops_contact` varchar(150) default '' AFTER `customer_contact`");
	mactrack_add_column('mac_track_sites',
		'facilities_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `facilities_contact` varchar(150) default '' AFTER `netops_contact`");
	mactrack_add_column('mac_track_sites',
		'site_info',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `site_info` text AFTER `facilities_contact`");
	mactrack_add_column('mac_track_devices',
		'device_name',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `device_name` varchar(100) default '' AFTER `host_id`");
	mactrack_add_column('mac_track_devices',
		'notes',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `notes` text AFTER `hostname`");
	mactrack_add_column('mac_track_scanning_functions',
		'type',
		"ALTER TABLE `mac_track_scanning_functions` ADD COLUMN `type` int(10) unsigned NOT NULL default '0' AFTER `scanning_function`");
	mactrack_add_column('mac_track_temp_ports',
		'device_name',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");
	mactrack_add_column('mac_track_temp_ports',
		'vendor_mac',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`");
	mactrack_add_column('mac_track_temp_ports',
		'authorized',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `updated`");
	mactrack_add_column('mac_track_ports',
		'device_name',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");
	mactrack_add_column('mac_track_ports',
		'vendor_mac',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`");
	mactrack_add_column('mac_track_ports',
		'authorized',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `scan_date`");
	mactrack_add_column('mac_track_ips',
		'mac_track_ips',
		"ALTER TABLE `mac_track_ips` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	db_execute("ALTER TABLE mac_track_ips MODIFY COLUMN port_number varchar(20) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_temp_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");
}

function mactrack_check_dependencies() {
	global $plugins, $config;

	return true;
}

function mactrack_setup_table_new () {
	if (!mactrack_db_table_exists('mac_track_approved_macs')) {
		db_execute("CREATE TABLE `mac_track_approved_macs` (
			`mac_prefix` varchar(20) NOT NULL,
			`vendor` varchar(50) NOT NULL,
			`description` varchar(255) NOT NULL,
			PRIMARY KEY  (`mac_prefix`)) ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_device_types')) {
		db_execute("CREATE TABLE `mac_track_device_types` (
			`device_type_id` int(10) unsigned NOT NULL auto_increment,
			`description` varchar(100) NOT NULL default '',
			`vendor` varchar(40) NOT NULL default '',
			`device_type` varchar(10) NOT NULL default '0',
			`sysDescr_match` varchar(20) NOT NULL default '',
			`sysObjectID_match` varchar(40) NOT NULL default '',
			`scanning_function` varchar(100) NOT NULL default '',
			`ip_scanning_function` varchar(100) NOT NULL,
			`serial_number_oid` varchar(100) default '',
			`lowPort` int(10) unsigned NOT NULL default '0',
			`highPort` int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (`sysDescr_match`,`sysObjectID_match`,`device_type`),
			KEY `device_type` (`device_type`),
			KEY `device_type_id` (`device_type_id`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_devices')) {
		db_execute("CREATE TABLE `mac_track_devices` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL auto_increment,
			`host_id` INTEGER UNSIGNED NOT NULL default '0',
			`device_name` varchar(100) default '',
			`device_type_id` int(10) unsigned default '0',
			`hostname` varchar(40) NOT NULL default '',
			`notes` text,
			`disabled` char(2) default '',
			`ignorePorts` varchar(255) default NULL,
			`ips_total` int(10) unsigned NOT NULL default '0',
			`vlans_total` int(10) unsigned NOT NULL default '0',
			`ports_total` int(10) unsigned NOT NULL default '0',
			`ports_active` int(10) unsigned NOT NULL default '0',
			`ports_trunk` int(10) unsigned NOT NULL default '0',
			`macs_active` int(10) unsigned NOT NULL default '0',
			`scan_type` tinyint(11) NOT NULL default '1',
			`term_type` tinyint(11) NOT NULL default '1',
			`user_name` varchar(40) default NULL,
			`user_password` varchar(40) default NULL,
			`private_key_path` varchar(128) default '',
			`snmp_options` int(10) unsigned NOT NULL default '0',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_readstrings` varchar(255) default NULL,
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`snmp_sysName` varchar(100) default '',
			`snmp_sysLocation` varchar(100) default '',
			`snmp_sysContact` varchar(100) default '',
			`snmp_sysObjectID` varchar(100) default NULL,
			`snmp_sysDescr` varchar(100) default NULL,
			`snmp_sysUptime` varchar(100) default NULL,
			`snmp_status` int(10) unsigned NOT NULL default '0',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			`snmp_engine_id` varchar(64) default '',
			`max_oids` int(12) unsigned default '10',
			`last_runmessage` varchar(100) default '',
			`last_rundate` datetime NOT NULL default '0000-00-00 00:00:00',
			`last_runduration` decimal(10,5) NOT NULL default '0.00000',
			PRIMARY KEY  (`hostname`,`snmp_port`,`site_id`),
			KEY `site_id` (`site_id`),
			KEY `host_id`(`host_id`),
			KEY `device_id` (`device_id`),
			KEY `snmp_sysDescr` (`snmp_sysDescr`),
			KEY `snmp_sysObjectID` (`snmp_sysObjectID`),
			KEY `device_type_id` (`device_type_id`),
			KEY `device_name` (`device_name`))
			ENGINE=InnoDB COMMENT='Devices to be scanned for MAC addresses';");
	}

	if (!mactrack_db_table_exists('mac_track_interfaces')) {
		db_execute("CREATE TABLE `mac_track_interfaces` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`sysUptime` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL default '0',
			`ifName` varchar(128) NOT NULL,
			`ifAlias` varchar(255) NOT NULL,
			`ifDescr` varchar(128) NOT NULL,
			`ifType` int(10) unsigned NOT NULL default '0',
			`ifMtu` int(10) unsigned NOT NULL default '0',
			`ifSpeed` int(10) unsigned NOT NULL default '0',
			`ifHighSpeed` int(10) unsigned NOT NULL default '0',
			`ifDuplex` int(10) unsigned NOT NULL default '0',
			`ifMauAutoNegAdminStatus` integer UNSIGNED NOT NULL default '0',
			`ifMauAutoNegRemoteSignaling` integer UNSIGNED NOT NULL default '0',
			`ifPhysAddress` varchar(20) NOT NULL,
			`ifAdminStatus` int(10) unsigned NOT NULL default '0',
			`ifOperStatus` int(10) unsigned NOT NULL default '0',
			`ifLastChange` int(10) unsigned NOT NULL default '0',
			`linkPort` tinyint(3) unsigned NOT NULL default '0',
			`vlan_id` int(10) unsigned NOT NULL,
			`vlan_name` varchar(128) NOT NULL,
			`vlan_trunk` tinyint(3) unsigned NOT NULL,
			`vlan_trunk_status` int(10) unsigned NOT NULL,
			`ifInOctets` int(10) unsigned NOT NULL default '0',
			`ifOutOctets` int(10) unsigned NOT NULL default '0',
			`ifHCInOctets` bigint(20) unsigned NOT NULL default '0',
			`ifHCOutOctets` bigint(20) unsigned NOT NULL default '0',
			`ifInMulticastPkts` int(10) unsigned NOT NULL default '0',
                        `ifOutMulticastPkts` int(10) unsigned NOT NULL default '0',
			`ifInBroadcastPkts` int(10) unsigned NOT NULL default '0',
			`ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0',
			`ifInUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifOutUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifInDiscards` int(10) unsigned NOT NULL default '0',
			`ifInErrors` int(10) unsigned NOT NULL default '0',
			`ifInUnknownProtos` int(10) unsigned NOT NULL default '0',
			`ifOutDiscards` int(10) unsigned default '0',
			`ifOutErrors` int(10) unsigned default '0',
			`inBound` double NOT NULL default '0',
			`outBound` double NOT NULL default '0',
			`int_ifInOctets` int(10) unsigned NOT NULL default '0',
			`int_ifOutOctets` int(10) unsigned NOT NULL default '0',
			`int_ifHCInOctets` bigint(20) unsigned NOT NULL default '0',
			`int_ifHCOutOctets` bigint(20) unsigned NOT NULL default '0',
			`int_ifInNUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutNUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInMulticastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutMulticastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInBroadcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInDiscards` float unsigned NOT NULL default '0',
			`int_ifInErrors` float unsigned NOT NULL default '0',
			`int_ifInUnknownProtos` float unsigned NOT NULL default '0',
			`int_ifOutDiscards` float unsigned NOT NULL default '0',
			`int_ifOutErrors` float unsigned NOT NULL default '0',
			`last_up_time` timestamp NOT NULL default '0000-00-00 00:00:00',
			`last_down_time` timestamp NOT NULL default '0000-00-00 00:00:00',
			`stateChanges` int(10) unsigned NOT NULL default '0',
			`int_discards_present` tinyint(3) unsigned NOT NULL default '0',
			`int_errors_present` tinyint(3) unsigned NOT NULL default '0',
			`present` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`site_id`,`device_id`,`ifIndex`),
			KEY `ifDescr` (`ifDescr`),
			KEY `ifType` (`ifType`),
			KEY `ifSpeed` (`ifSpeed`),
			KEY `ifMTU` (`ifMtu`),
			KEY `ifAdminStatus` (`ifAdminStatus`),
			KEY `ifOperStatus` (`ifOperStatus`),
			KEY `ifInDiscards` USING BTREE (`ifInUnknownProtos`),
			KEY `ifInErrors` USING BTREE (`ifInUnknownProtos`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_ip_ranges')) {
		db_execute("CREATE TABLE `mac_track_ip_ranges` (
			`ip_range` varchar(20) NOT NULL default '',
			`site_id` int(10) unsigned NOT NULL default '0',
			`ips_max` int(10) unsigned NOT NULL default '0',
			`ips_current` int(10) unsigned NOT NULL default '0',
			`ips_max_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`ips_current_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`ip_range`,`site_id`),
			KEY `site_id` (`site_id`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_ips')) {
		db_execute("CREATE TABLE `mac_track_ips` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`port_number` varchar(20) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`scan_date`,`ip_address`,`mac_address`,`site_id`),
			KEY `ip` (`ip_address`),
			KEY `port_number` (`port_number`),
			KEY `mac` (`mac_address`),
			KEY `device_id` (`device_id`),
			KEY `site_id` (`site_id`),
			KEY `hostname` (`hostname`),
			KEY `scan_date` (`scan_date`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_macauth')) {
		db_execute("CREATE TABLE `mac_track_macauth` (
			`mac_address` varchar(20) NOT NULL,
			`mac_id` int(10) unsigned NOT NULL auto_increment,
			`description` varchar(100) NOT NULL,
			`added_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`added_by` varchar(20) NOT NULL,
			PRIMARY KEY  (`mac_address`),
			KEY `mac_id` (`mac_id`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_macwatch')) {
		db_execute("CREATE TABLE `mac_track_macwatch` (
			`mac_address` varchar(20) NOT NULL,
			`mac_id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(45) NOT NULL,
			`description` varchar(255) NOT NULL,
			`ticket_number` varchar(45) NOT NULL,
			`notify_schedule` tinyint(3) unsigned NOT NULL,
			`email_addresses` varchar(255) NOT NULL default '',
			`discovered` tinyint(3) unsigned NOT NULL,
			`date_first_seen` timestamp NOT NULL default '0000-00-00 00:00:00',
			`date_last_seen` timestamp NOT NULL default '0000-00-00 00:00:00',
			`date_last_notif` timestamp NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`mac_address`),
			KEY `mac_id` (`mac_id`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_oui_database')) {
		db_execute("CREATE TABLE `mac_track_oui_database` (
			`vendor_mac` varchar(8) NOT NULL,
			`vendor_name` varchar(100) NOT NULL,
			`vendor_address` text NOT NULL,
			`present` tinyint(3) unsigned NOT NULL default '1',
			PRIMARY KEY  (`vendor_mac`),
			KEY `vendor_name` (`vendor_name`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_ports')) {
		db_execute("CREATE TABLE `mac_track_ports` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`vlan_id` varchar(5) NOT NULL default 'N/A',
			`vlan_name` varchar(50) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`vendor_mac` varchar(8) default NULL,
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`port_number` varchar(20) NOT NULL default '',
			`port_name` varchar(50) NOT NULL default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`authorized` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`port_number`,`scan_date`,`mac_address`,`device_id`),
			KEY `site_id` (`site_id`),
			KEY `scan_date` USING BTREE(`scan_date`),
			KEY `description` (`device_name`),
			KEY `mac` (`mac_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `ip_address` (`ip_address`),
			KEY `port_name` (`port_name`),
			KEY `dns_hostname` (`dns_hostname`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`))
			ENGINE=InnoDB COMMENT='Database for Tracking Device MACs'");
	}

	if (!mactrack_db_table_exists('mac_track_processes')) {
		db_execute("CREATE TABLE `mac_track_processes` (
			`device_id` int(11) NOT NULL default '0',
			`process_id` int(10) unsigned default NULL,
			`status` varchar(20) NOT NULL default 'Queued',
			`start_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`device_id`))
			ENGINE=InnoDB");
	}

	if (!mactrack_db_table_exists('mac_track_scan_dates')) {
		db_execute("CREATE TABLE `mac_track_scan_dates` (
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`scan_date`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_scanning_functions')) {
		db_execute("CREATE TABLE `mac_track_scanning_functions` (
			`scanning_function` varchar(100) NOT NULL default '',
			`type` int(10) unsigned NOT NULL default '0',
			`description` varchar(200) NOT NULL default '',
			PRIMARY KEY  (`scanning_function`))
			ENGINE=InnoDB
			COMMENT='Registered Scanning Functions';");
	}

	if (!mactrack_db_table_exists('mac_track_sites')) {
		db_execute("CREATE TABLE `mac_track_sites` (
			`site_id` int(10) unsigned NOT NULL auto_increment,
			`site_name` varchar(100) NOT NULL default '',
			`customer_contact` varchar(150) default '',
			`netops_contact` varchar(150) default '',
			`facilities_contact` varchar(150) default '',
			`site_info` text,
			`total_devices` int(10) unsigned NOT NULL default '0',
			`total_device_errors` int(10) unsigned NOT NULL default '0',
			`total_macs` int(10) unsigned NOT NULL default '0',
			`total_ips` int(10) unsigned NOT NULL default '0',
			`total_user_ports` int(11) NOT NULL default '0',
			`total_oper_ports` int(10) unsigned NOT NULL default '0',
			`total_trunk_ports` int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (`site_id`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_temp_ports')) {
		db_execute("CREATE TABLE `mac_track_temp_ports` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`vlan_id` varchar(5) NOT NULL default 'N/A',
			`vlan_name` varchar(50) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`vendor_mac` varchar(8) default NULL,
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`port_number` varchar(20) NOT NULL default '',
			`port_name` varchar(50) NOT NULL default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`updated` tinyint(3) unsigned NOT NULL default '0',
			`authorized` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`port_number`,`scan_date`,`mac_address`,`device_id`),
			KEY `site_id` (`site_id`),
			KEY `device_name` (`device_name`),
			KEY `ip_address` (`ip_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `mac` (`mac_address`),
			KEY `updated` (`updated`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`))
			ENGINE=InnoDB
			COMMENT='Database for Storing Temporary Results for Tracking Device MACS';");
	}

	if (!mactrack_db_table_exists('mac_track_vlans')) {
		db_execute("CREATE TABLE `mac_track_vlans` (
			`vlan_id` int(10) unsigned NOT NULL,
			`site_id` int(10) unsigned NOT NULL,
			`device_id` int(10) unsigned NOT NULL,
			`vlan_name` varchar(128) NOT NULL,
			`present` tinyint(3) unsigned NOT NULL default '1',
			PRIMARY KEY  (`vlan_id`,`site_id`,`device_id`),
			KEY `vlan_name` (`vlan_name`))
			ENGINE=InnoDB;");
	}

	if (!mactrack_db_table_exists('mac_track_aggregated_ports')) {
		db_execute("CREATE TABLE `mac_track_aggregated_ports` (
			`row_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`site_id` int(10) unsigned NOT NULL DEFAULT '0',
			`device_id` int(10) unsigned NOT NULL DEFAULT '0',
			`hostname` varchar(40) NOT NULL DEFAULT '',
			`device_name` varchar(100) NOT NULL DEFAULT '',
			`vlan_id` varchar(5) NOT NULL DEFAULT 'N/A',
			`vlan_name` varchar(50) NOT NULL DEFAULT '',
			`mac_address` varchar(20) NOT NULL DEFAULT '',
			`vendor_mac` varchar(8) DEFAULT NULL,
			`ip_address` varchar(20) NOT NULL DEFAULT '',
			`dns_hostname` varchar(200) DEFAULT '',
			`port_number` varchar(20) NOT NULL DEFAULT '',
			`port_name` varchar(50) NOT NULL DEFAULT '',
			`date_last` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`first_scan_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`count_rec` int(10) unsigned NOT NULL DEFAULT '0',
			`active_last` tinyint(1) unsigned NOT NULL DEFAULT '0',
			`authorized` tinyint(3) unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (`row_id`),
			UNIQUE KEY `port_number` USING BTREE (`port_number`,`mac_address`,`ip_address`,`device_id`,`site_id`,`vlan_id`,`authorized`),
			KEY `site_id` (`site_id`),
			KEY `device_name` (`device_name`),
			KEY `mac` (`mac_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `ip_address` (`ip_address`),
			KEY `port_name` (`port_name`),
			KEY `dns_hostname` (`dns_hostname`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`),
			KEY `site_id_device_id` (`site_id`,`device_id`)
			) ENGINE=InnoDB COMMENT='Database for aggregated date for Tracking Device MAC''s';");
	}

	if (!mactrack_db_table_exists('mac_track_snmp')) {
		db_execute("CREATE TABLE `mac_track_snmp` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(100) NOT NULL default '',
			PRIMARY KEY  (`id`))
			ENGINE=InnoDB COMMENT='Group of SNMP Option Sets';");
	}

	if (!mactrack_db_table_exists('mac_track_snmp_items')) {
		db_execute("CREATE TABLE `mac_track_snmp_items` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`snmp_id` int(10) unsigned NOT NULL default '0',
			`sequence` int(10) unsigned NOT NULL default '0',
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`max_oids` int(12) unsigned default '10',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			`snmp_engine_id` varchar(64) default '',
			PRIMARY KEY  (`id`,`snmp_id`))
			ENGINE=InnoDB COMMENT='Set of SNMP Options';");
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'mac_track_interface_graphs'"))) {
		db_execute("CREATE TABLE `mac_track_interface_graphs` (
			`device_id` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL,
			`ifName` varchar(20) NOT NULL default '',
			`host_id` int(11) NOT NULL default '0',
			`local_graph_id` int(10) unsigned NOT NULL,
			`snmp_query_id` int(11) NOT NULL default '0',
			`graph_template_id` int(11) NOT NULL default '0',
			`field_name` varchar(20) NOT NULL default '',
			`field_value` varchar(25) NOT NULL default '',
			`present` tinyint(4) default '1',
			PRIMARY KEY  (`local_graph_id`,`device_id`,`ifIndex`, `host_id`),
			KEY `host_id` (`host_id`),
			KEY `device_id` (`device_id`)
			) ENGINE=InnoDB;"
		);
	}
}

function mactrack_page_head() {
	global $config;

	if (substr_count(get_current_page(), 'mactrack_')) {
		if (!isset($config['base_path'])) {
			print "<script type='text/javascript' src='" . URL_PATH . "plugins/mactrack/mactrack.js'></script>\n";
		}else{
			if (file_exists($config['base_path'] . '/plugins/mactrack/themes/' . get_selected_theme() . '/mactrack.css')) {
				print "<link type='text/css' href='" . $config['url_path'] . "plugins/mactrack/themes/" . get_selected_theme() . "/mactrack.css' rel='stylesheet'>\n";
			}else{
				print "<link type='text/css' href='" . $config['url_path'] . "plugins/mactrack/mactrack.css' rel='stylesheet'>\n";
			}
		}
		print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/mactrack/mactrack.js'></script>\n";
		print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/mactrack/mactrack_snmp.js'></script>\n";
	}
}

function mactrack_poller_bottom () {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');
	include_once($config['base_path'] . '/lib/data_query.php');
	include_once($config['base_path'] . '/lib/rrd.php');

	$command_string = read_config_option('path_php_binary');
	$extra_args = '-q ' . $config['base_path'] . '/plugins/mactrack/poller_mactrack.php';
	exec_background($command_string, $extra_args);
}

function mactrack_config_settings () {
	global $tabs, $settings, $snmp_versions, $mactrack_poller_frequencies,
	$mactrack_data_retention, $mactrack_macauth_frequencies, $mactrack_update_policies;

	$tabs['mactrack'] = __('Device Tracking', 'mactrack');

	$settings['mactrack'] = array(
		'mactrack_hdr_timing' => array(
			'friendly_name' => __('General Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_collection_timing' => array(
			'friendly_name' => __('Scanning Frequency', 'mactrack'),
			'description' => __('Choose when to collect MAC and IP Addresses and Interface statistics from your network devices.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => $mactrack_poller_frequencies,
			),
		'mt_processes' => array(
			'friendly_name' => __('Concurrent Processes', 'mactrack'),
			'description' => __('Specify how many devices will be polled simultaneously until all devices have been polled.', 'mactrack'),
			'default' => '7',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_script_runtime' => array(
			'friendly_name' => __('Scanner Max Runtime', 'mactrack'),
			'description' => __('Specify the number of minutes a device scanning function will be allowed to run prior to the system assuming it has been completed.  This setting will correct for abended scanning jobs.', 'mactrack'),
			'default' => '20',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_base_time' => array(
			'friendly_name' => __('Start Time for Data Collection', 'mactrack'),
			'description' => __('When would you like the first data collection to take place.  All future data collection times will be based upon this start time.  A good example would be 12:00AM.', 'mactrack'),
			'default' => '1:00am',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '8'
			),
		'mt_maint_time' => array(
			'friendly_name' => __('Database Maintenance Time', 'mactrack'),
			'description' => __('When should old database records be removed from the database.  Please note that no access will be permitted to the port database while this action is taking place.', 'mactrack'),
			'default' => '12:00am',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '8'
			),
		'mt_data_retention' => array(
			'friendly_name' => __('Data Retention', 'mactrack'),
			'description' => __('How long should port MAC details be retained in the database.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '2weeks',
			'array' => $mactrack_data_retention,
			),
		'mt_ignorePorts_delim' => array(
			'friendly_name' => __('Switch Level Ignore Ports Delimiter', 'mactrack'),
			'description' => __('What delimiter should Device Tracking use when parsing the Ignore Ports string for each switch.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '-1',
			'array' => array(
				'-1' => __('Auto Detect', 'mactrack'), 
				':' => __('Colon [:]', 'mactrack'), 
				'|' => __('Pipe [|]', 'mactrack'), 
				' ' => __('Space [ ]', 'mactrack')
				)
			),
		'mt_mac_delim' => array(
			'friendly_name' => __('Mac Address Delimiter', 'mactrack'),
			'description' => __('How should each octet of the MAC address be delimited.', 'mactrack'),
			'method' => 'drop_array',
			'default' => ':',
			'array' => array(':' => __('Colon [:]', 'mactrack'), '-' => __('Dash [-]', 'mactrack'))
			),
		'mt_ignorePorts' => array(
			'method' => 'textbox',
			'friendly_name' => __('Ports to Ignore', 'mactrack'),
			'description' => __('Provide a regular expression of ifNames or ifDescriptions of ports to ignore in the interface list.  For example, (Vlan|Loopback|Null).', 'mactrack'),
			'class' => 'textAreaNotes',
			'defaults' => '(Vlan|Loopback|Null)',
			'max_length' => '255',
			'size' => '80'
			),
		'mt_interface_high' => array(
			'friendly_name' => __('Bandwidth Usage Threshold', 'mactrack'),
			'description' => __('When reviewing network interface statistics, what bandwidth threshold do you want to view by default.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '70',
			'array' => array(
				'-1' => __('N/A', 'mactrack'),
				'10' => __('%d Percent', 10, 'mactrack'),
				'20' => __('%d Percent', 20, 'mactrack'),
				'30' => __('%d Percent', 30, 'mactrack'),
				'40' => __('%d Percent', 40, 'mactrack'),
				'50' => __('%d Percent', 50, 'mactrack'),
				'60' => __('%d Percent', 60, 'mactrack'),
				'70' => __('%d Percent', 70, 'mactrack'),
				'80' => __('%d Percent', 80, 'mactrack'),
				'90' => __('%d Percent', 90, 'mactrack')
				)
			),
		'mt_hdr_rdns' => array(
			'friendly_name' => __('DNS Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_reverse_dns' => array(
			'friendly_name' => __('Perform Reverse DNS Name Resolution', 'mactrack'),
			'description' => __('Should Device Tracking perform reverse DNS lookup of the IP addresses associated with ports. CAUTION: If DNS is not properly setup, this will slow scan time significantly.', 'mactrack'),
			'default' => '',
			'method' => 'checkbox'
			),
		'mt_dns_primary' => array(
			'friendly_name' => __('Primary DNS IP Address', 'mactrack'),
			'description' => __('Enter the primary DNS IP Address to utilize for reverse lookups.', 'mactrack'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30',
			'size' => '18'
			),
		'mt_dns_secondary' => array(
			'friendly_name' => __('Secondary DNS IP Address', 'mactrack'),
			'description' => __('Enter the secondary DNS IP Address to utilize for reverse lookups.', 'mactrack'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30',
			'size' => '18'
			),
		'mt_dns_timeout' => array(
			'friendly_name' => __('DNS Timeout', 'mactrack'),
			'description' => __('Please enter the DNS timeout in milliseconds.  Device Tracking uses a PHP based DNS resolver.', 'mactrack'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_dns_prime_interval' => array(
			'friendly_name' => __('DNS Prime Interval', 'mactrack'),
			'description' => __('How often, in seconds do Device Tracking scanning IP\'s need to be resolved to MAC addresses for DNS resolution.  Using a larger number when you have several thousand devices will increase performance.', 'mactrack'),
			'method' => 'textbox',
			'default' => '120',
			'max_length' => '10',
			'size' => '4'
			),
		'mactrack_hdr_notification' => array(
			'friendly_name' => __('Notification Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_from_email' => array(
			'friendly_name' => __('Source Address', 'mactrack'),
			'description' => __('The source Email address for Device Tracking Emails.', 'mactrack'),
			'method' => 'textbox',
			'default' => 'thewitness@cacti.net',
			'max_length' => '100',
			'size' => '30'
			),
		'mt_from_name' => array(
			'friendly_name' => __('Source Email Name', 'mactrack'),
			'description' => __('The Source Email name for Device Tracking Emails.', 'mactrack'),
			'method' => 'textbox',
			'default' => __('MACTrack Administrator', 'mactrack'),
			'max_length' => '100',
			'size' => '30'
			),
		'mt_macwatch_description' => array(
			'friendly_name' => __('MacWatch Default Body', 'mactrack'),
			'description' => htmlspecialchars(__('The Email body preset for Device Tracking MacWatch Emails.  The body can contain ' .
			'any valid html tags.  It also supports replacement tags that will be processed when sending an Email.  ' .
			'Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>.', 'mactrack')),
			'method' => 'textarea',
			'default' => __('Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, and Port Name <PORTNAME>', 'mactrack'),
			'class' => 'textAreaNotes',
			'max_length' => '512',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			),
		'mt_macauth_emails' => array(
			'friendly_name' => __('MacAuth Report Email Addresses', 'mactrack'),
			'description' => __('A comma delimited list of users to recieve the MacAuth Email notifications.', 'mactrack'),
			'method' => 'textarea',
			'default' => '',
			'class' => 'textAreaNotes',
			'max_length' => '255',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			),
		'mt_macauth_email_frequency' => array(
			'friendly_name' => __('MacAuth Report Frequency', 'mactrack'),
			'description' => __('How often will the MacAuth Reports be Emailed.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => $mactrack_macauth_frequencies,
			),
		'mactrack_hdr_arpwatch' => array(
			'friendly_name' => __('Device Tracking ArpWatch Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_arpwatch' => array(
			'friendly_name' => __('Enable ArpWatch', 'mactrack'),
			'description' => __('Should Device Tracking also use ArpWatch data to supplement Mac to IP/DNS resolution?', 'mactrack'),
			'default' => '',
			'method' => 'checkbox'
			),
		'mt_arpwatch_path' => array(
			'friendly_name' => __('ArpWatch Database Path', 'mactrack'),
			'description' => __('The location of the ArpWatch Database file on the Cacti server.', 'mactrack'),
			'method' => 'filepath',
			'default' => '',
			'max_length' => '255',
			'size' => '60'
			),
		'mactrack_hdr_general' => array(
			'friendly_name' => __('SNMP Presets', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_update_policy' => array(
			'friendly_name' => __('Update Policy for SNMP Options', 'mactrack'),
			'description' => __('Policy for synchronization of SNMP Options between Cacti devices and Device Tracking Devices.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $mactrack_update_policies,
			),
		'mt_snmp_ver' => array(
			'friendly_name' => __('Version', 'mactrack'),
			'description' => __('Default SNMP version for all new hosts.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => $snmp_versions,
			),
		'mt_snmp_community' => array(
			'friendly_name' => __('Community', 'mactrack'),
			'description' => __('Default SNMP read community for all new hosts.', 'mactrack'),
			'method' => 'textbox',
			'default' => 'public',
			'max_length' => '100',
			'size' => '20'
			),
		'mt_snmp_communities' => array(
			'friendly_name' => __('Communities', 'mactrack'),
			'description' => __('Fill in the list of available SNMP read strings to test for this device. Each read string must be separated by a colon \':\'.  These read strings will be tested sequentially if the primary read string is invalid.', 'mactrack'),
			'method' => 'textbox',
			'default' => 'public:private:secret',
			'max_length' => '255'
			),
		'mt_snmp_port' => array(
			'friendly_name' => __('Port', 'mactrack'),
			'description' => __('The UDP/TCP Port to poll the SNMP agent on.', 'mactrack'),
			'method' => 'textbox',
			'default' => '161',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_snmp_timeout' => array(
			'friendly_name' => __('Timeout', 'mactrack'),
			'description' => __('Default SNMP timeout in milli-seconds.', 'mactrack'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_snmp_retries' => array(
			'friendly_name' => __('Retries', 'mactrack'),
			'description' => __('The number times the SNMP poller will attempt to reach the host before failing.', 'mactrack'),
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '4'
			)
		);

	$ts = array();
	foreach ($settings['path'] as $t => $ta) {
		$ts[$t] = $ta;
		if ($t == 'path_snmpget') {
			$ts['path_snmpbulkwalk'] = array(
				'friendly_name' => __('snmpbulkwalk Binary Path', 'mactrack'),
				'description' => __('The path to your snmpbulkwalk binary.', 'mactrack'),
				'method' => 'textbox',
				'max_length' => '255'
			);
		}
	}
	$settings['path']=$ts;

	mactrack_check_upgrade();
}

function mactrack_draw_navigation_text ($nav) {
	$nav['mactrack_devices.php:'] = array('title' => __('Device Tracking Devices', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_devices.php', 'level' => '1');
	$nav['mactrack_devices.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_devices.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_devices.php:import'] = array('title' => __('(Import)', 'mactrack'), 'mapping' => 'index.php:,mactrack_devices.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_devices.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_devices.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_snmp.php:'] = array('title' => __('Device Tracking SNMP Options', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_snmp.php', 'level' => '1');
	$nav['mactrack_snmp.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_snmp.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_snmp.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_snmp.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_snmp.php:item_edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_snmp.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_device_types.php:'] = array('title' => __('Device Tracking Device Types', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_device_types.php', 'level' => '1');
	$nav['mactrack_device_types.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_device_types.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_device_types.php:import'] = array('title' => __('(Import)', 'mactrack'), 'mapping' => 'index.php:,mactrack_device_types.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_device_types.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_device_types.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_sites.php:'] = array('title' => __('Device Tracking Sites', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_sites.php', 'level' => '1');
	$nav['mactrack_sites.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_sites.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_sites.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_sites.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_macwatch.php:'] = array('title' => __('Mac Address Tracking Utility', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_macwatch.php', 'level' => '1');
	$nav['mactrack_macwatch.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_macwatch.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_macwatch.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_macwatch.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_macauth.php:'] = array('title' => __('Mac Address Authorization Utility', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_macauth.php', 'level' => '1');
	$nav['mactrack_macauth.php:edit'] = array('title' => __('(Edit)', 'mactrack'), 'mapping' => 'index.php:,mactrack_macauth.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_macauth.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'index.php:,mactrack_macauth.php:', 'url' => '', 'level' => '2');
	$nav['mactrack_vendormacs.php:'] = array('title' => __('Device Tracking Vendor Macs', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_vendormacs.php', 'level' => '1');
	$nav['mactrack_view_macs.php:'] = array('title' => __('Device Tracking View Macs', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_macs.php', 'level' => '0');
	$nav['mactrack_view_macs.php:actions'] = array('title' => __('Actions', 'mactrack'), 'mapping' => 'mactrack_view_macs.php:', 'url' => '', 'level' => '1');
	$nav['mactrack_view_arp.php:'] = array('title' => __('Device Tracking IP Address Viewer', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_arp.php', 'level' => '0');
	$nav['mactrack_view_interfaces.php:'] = array('title' => __('Device Tracking View Interfaces', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_interfaces.php', 'level' => '0');
	$nav['mactrack_view_sites.php:'] = array('title' => __('Device Tracking View Sites', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_sites.php', 'level' => '0');
	$nav['mactrack_view_ips.php:'] = array('title' => __('Device Tracking View IP Ranges', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_ips.php', 'level' => '0');
	$nav['mactrack_view_devices.php:'] = array('title' => __('Device Tracking View Devices', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_devices.php', 'level' => '0');
	$nav['mactrack_utilities.php:'] = array('title' => __('Device Tracking Utilities', 'mactrack'), 'mapping' => 'index.php:', 'url' => 'mactrack_utilities.php', 'level' => '1');
	$nav['mactrack_utilities.php:mactrack_utilities_perform_db_maint'] = array('title' => __('Perform Database Maintenance', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_utilities_purge_scanning_funcs'] = array('title' => __('Refresh Scanning Functions', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_utilities_truncate_ports_table'] = array('title' => __('Truncate Port Results Table', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_utilities_purge_aggregated_data'] = array('title' => __('Truncate Aggregated Port Results Table', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_utilities_recreate_aggregated_data'] = array('title' => __('Truncate and Re-create Aggregated Port Results Table', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_proc_status'] = array('title' => __('View Device Tracking Process Status', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_utilities.php:mactrack_refresh_oui_database'] = array('title' => __('Refresh/Update Vendor MAC Database from IEEE', 'mactrack'), 'mapping' => 'index.php:,mactrack_utilities.php:', 'url' => 'mactrack_utilities.php', 'level' => '2');
	$nav['mactrack_view_graphs.php:'] = array('title' => __('Device Tracking Graph Viewer', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_graphs.php', 'level' => '0');
	$nav['mactrack_view_graphs.php:preview'] = array('title' => __('Device Tracking Graph Viewer', 'mactrack'), 'mapping' => '', 'url' => 'mactrack_view_graphs.php', 'level' => '0');
	return $nav;
}

function mactrack_show_tab () {
	global $config, $user_auth_realm_filenames;

	if (api_user_realm_auth('mactrack_view_macs.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'mactrack_view_')) {
			print '<a href="' . $config['url_path'] . 'plugins/mactrack/mactrack_view_macs.php"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack_down.png" alt="' . __('Device Tracking', 'mactrack') . '"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/mactrack/mactrack_view_macs.php"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack.png" alt="' . __('Device Tracking', 'mactrack') . '"></a>';
		}
	}
}

function mactrack_config_arrays () {
	global $mactrack_device_types, $mactrack_search_types, $messages;
	global $menu, $menu_glyphs, $config, $rows_selector;
	global $mactrack_poller_frequencies, $mactrack_data_retention, $refresh_interval;
	global $mactrack_macauth_frequencies, $mactrack_duplexes, $mactrack_update_policies;

	if (isset($_SESSION['mactrack_message']) && $_SESSION['mactrack_message'] != '') {
		$messages['mactrack_message'] = array('message' => $_SESSION['mactrack_message'], 'type' => 'info');
		kill_session_var('mactrack_message');
	}

	$refresh_interval = array(
		5   => __('%d Seconds', 5),
		10  => __('%d Seconds', 10),
		20  => __('%d Seconds', 20),
		30  => __('%d Seconds', 30),
		60  => __('%d Minute', 1),
		300 => __('%d Minutes', 5)
	);

	$mactrack_device_types = array(
		1 => __('Switch/Hub', 'mactrack'),
		2 => __('Switch/Router', 'mactrack'),
		3 => __('Router', 'mactrack')
	);

	$mactrack_search_types = array(
		1 => '',
		2 => __('Matches', 'mactrack'),
		3 => __('Contains', 'mactrack'),
		4 => __('Begins With', 'mactrack'),
		5 => __('Does Not Contain', 'mactrack'),
		6 => __('Does Not Begin With', 'mactrack'),
		7 => __('Is Null', 'mactrack'),
		8 => __('Is Not Null', 'mactrack')
	);

	$mactrack_duplexes = array(
		1 => __('Unknown', 'mactrack'),
		2 => __('Half', 'mactrack'),
		3 => __('Full', 'mactrack')
	);

	$mactrack_update_policies = array(
		1 => __('None', 'mactrack'),
		2 => __('Sync Cacti Device to Device Tracking Device', 'mactrack'),
		3 => __('Sync Device Tracking Device to Cacti Device', 'mactrack')
	);

	$rows_selector = array(
		-1   => __('Default', 'mactrack'),
		10   => '10',
		15   => '15',
		20   => '20',
		30   => '30',
		50   => '50',
		100  => '100',
		500  => '500',
		1000 => '1000',
		-2   => __('All', 'mactrack')
	);

	$mactrack_poller_frequencies = array(
		'disabled' => __('Disabled', 'mactrack'),
		'10'       => __('Every %d Minutes', 10, 'mactrack'),
		'15'       => __('Every %d Minutes', 15, 'mactrack'),
		'20'       => __('Every %d Minutes', 20, 'mactrack'),
		'30'       => __('Every %d Minutes', 30, 'mactrack'),
		'60'       => __('Every %d Hour', 1, 'mactrack'),
		'120'      => __('Every %d Hours', 2, 'mactrack'),
		'240'      => __('Every %d Hours', 4, 'mactrack'),
		'480'      => __('Every %d Hours', 8, 'mactrack'),
		'720'      => __('Every %d Hours', 12, 'mactrack'),
		'1440'     => __('Every Day', 'mactrack')
	);

	$mactrack_data_retention = array(
		'3'   => __('%d Days', 3, 'mactrack'),
		'7'   => __('%d Days', 7, 'mactrack'),
		'10'  => __('%d Days', 10, 'mactrack'),
		'14'  => __('%d Days', 14, 'mactrack'),
		'20'  => __('%d Days', 20, 'mactrack'),
		'30'  => __('%d Month', 1, 'mactrack'),
		'60'  => __('%d Months', 2, 'mactrack'),
		'120' => __('%d Months', 4, 'mactrack'),
		'240' => __('%d Months', 8, 'mactrack'),
		'365' => __('%d Year', 1, 'mactrack')
	);

	$mactrack_macauth_frequencies = array(
		'disabled' => __('Disabled', 'mactrack'),
		'0'        => __('On Scan Completion', 'mactrack'),
		'720'      => __('Every %d Hours', 12),
		'1440'     => __('Every Day', 'mactrack'),
		'2880'     => __('Every %d Days', 2),
		'10080'    => __('Every Week', 'mactrack')
	);

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Management')) {
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_sites.php']        = __('Sites', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_devices.php']      = __('Devices', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_snmp.php']         = __('SNMP Options', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_device_types.php'] = __('Device Types', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_vendormacs.php']   = __('Vendor Macs', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_macwatch.php']      = __('Mac Watch', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_macauth.php']       = __('Mac Authorizations', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_utilities.php']     = __('Tracking Utilities', 'mactrack');
		}
	}
	$menu = $menu2;

	$menu_glyphs[__('Device Tracking', 'mactrack')] = 'fa fa-shield';
	$menu_glyphs[__('Tracking Tools', 'mactrack')] = 'fa fa-bullhorn';
}

function mactrack_config_form () {
	global $fields_mactrack_device_type_edit, $fields_mactrack_device_edit, $fields_mactrack_site_edit;
	global $fields_mactrack_snmp_edit, $fields_mactrack_snmp_item, $fields_mactrack_snmp_item_edit;
	global $mactrack_device_types, $snmp_versions, $fields_mactrack_macw_edit, $fields_mactrack_maca_edit;
	global $snmp_priv_protocols, $snmp_auth_protocols;

	/* file: mactrack_device_types.php, action: edit */
	$fields_mactrack_device_type_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('Device Scanning Function Options', 'mactrack')
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description', 'mactrack'),
		'description' => __('Give this device type a meaningful description.', 'mactrack'),
		'value' => '|arg1:description|',
		'max_length' => '250'
		),
	'vendor' => array(
		'method' => 'textbox',
		'friendly_name' => __('Vendor', 'mactrack'),
		'description' => __('Fill in the name for the vendor of this device type.', 'mactrack'),
		'value' => '|arg1:vendor|',
		'max_length' => '250'
		),
	'device_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Device Type', 'mactrack'),
		'description' => __('Choose the type of device.', 'mactrack'),
		'value' => '|arg1:device_type|',
		'default' => 1,
		'array' => $mactrack_device_types
		),
	'sysDescr_match' => array(
		'method' => 'textbox',
		'friendly_name' => __('System Description Match', 'mactrack'),
		'description' => __('Provide key information to help Device Tracking detect the type of device.  The wildcard character is the \'%\' sign.', 'mactrack'),
		'value' => '|arg1:sysDescr_match|',
		'max_length' => '250'
		),
	'sysObjectID_match' => array(
		'method' => 'textbox',
		'friendly_name' => __('Vendor SNMP Object ID Match', 'mactrack'),
		'description' => __('Provide key information to help Device Tracking detect the type of device.  The wildcard character is the \'%\' sign.', 'mactrack'),
		'value' => '|arg1:sysObjectID_match|',
		'max_length' => '250'
		),
	'scanning_function' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('MAC Address Scanning Function', 'mactrack'),
		'description' => __('The Device Tracking scanning function to call in order to obtain and store port details.  The function name is all that is required.  The following four parameters are assumed and will always be appended: \'my_function($site, &$device, $lowport, $highport)\'.  There is no function required for a pure router.', 'mactrack'),
		'value' => '|arg1:scanning_function|',
		'default' => 1,
		'sql' => 'select scanning_function as id, scanning_function as name from mac_track_scanning_functions where type="1" order by scanning_function'
		),
	'ip_scanning_function' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('IP Address Scanning Function', 'mactrack'),
		'description' => __('The Device Tracking scanning function specific to Layer3 devices that track IP Addresses.', 'mactrack'),
		'value' => '|arg1:ip_scanning_function|',
		'default' => 1,
		'sql' => 'SELECT scanning_function AS id, scanning_function AS name FROM mac_track_scanning_functions WHERE type="2" ORDER BY scanning_function'
		),
	'serial_number_oid' => array(
		'method' => 'textbox',
		'friendly_name' => __('Serial Number Base OID', 'mactrack'),
		'description' => __('The SNMP OID used to obtain this device types serial number to be stored in the Device Tracking Asset Information table.', 'mactrack'),
		'value' => '|arg1:serial_number_oid|',
		'max_length' => '100',
		'default' => ''
		),
	'serial_number_oid_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Serial Number Collection Method', 'mactrack'),
		'description' => __('How is the serial number collected for this OID.  If \'SNMP Walk\', we assume multiple serial numbers.  If \'Get\', it will be only one..', 'mactrack'),
		'value' => '|arg1:serial_number_oid_method|',
		'default' => 'get',
		'array' => array('get' => __('SNMP Get', 'mactrack'), 'walk' => __('SNMP Walk', 'mactrack'))
		),
	'lowPort' => array(
		'method' => 'textbox',
		'friendly_name' => __('Low User Port Number', 'mactrack'),
		'description' => __('Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.', 'mactrack'),
		'value' => '|arg1:lowPort|',
		'default' => read_config_option('mt_port_lowPort'),
		'max_length' => '100',
		'size' => '10'
		),
	'highPort' => array(
		'method' => 'textbox',
		'friendly_name' => __('High User Port Number', 'mactrack'),
		'description' => __('Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.', 'mactrack'),
		'value' => '|arg1:highPort|',
		'default' => read_config_option('mt_port_highPort'),
		'max_length' => '100',
		'size' => '10'
		),
	'device_type_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_type_id|'
		),
	'_device_type_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_type_id|'
		),
	'save_component_device_type' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_snmp.php, action: edit */
	$fields_mactrack_snmp_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name', 'mactrack'),
		'description' => __('Fill in the name of this SNMP option set.', 'mactrack'),
		'value' => '|arg1:name|',
		'default' => '',
		'max_length' => '100',
		'size' => '40'
		),
	);

	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item = array(
	'snmp_version' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Version', 'mactrack'),
		'description' => __('Choose the SNMP version for this host.', 'mactrack'),
		'value' => '|arg1:snmp_version|',
		'default' => read_config_option('mt_snmp_ver'),
		'array' => $snmp_versions
		),
	'snmp_readstring' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Community String', 'mactrack'),
		'description' => __('Fill in the SNMP read community for this device.', 'mactrack'),
		'value' => '|arg1:snmp_readstring|',
		'default' => read_config_option('mt_snmp_community'),
		'max_length' => '100',
		'size' => '20'
		),
	'snmp_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Port', 'mactrack'),
		'description' => __('The UDP/TCP Port to poll the SNMP agent on.', 'mactrack'),
		'value' => '|arg1:snmp_port|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_port'),
		'size' => '10'
		),
	'snmp_timeout' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Timeout', 'mactrack'),
		'description' => __('The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).', 'mactrack'),
		'value' => '|arg1:snmp_timeout|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_timeout'),
		'size' => '10'
		),
	'snmp_retries' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Retries', 'mactrack'),
		'description' => __('The maximum number of attempts to reach a device via an SNMP readstring prior to giving up.', 'mactrack'),
		'value' => '|arg1:snmp_retries|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_retries'),
		'size' => '10'
		),
	'max_oids' => array(
		'method' => 'textbox',
		'friendly_name' => __('Maximum OID\'s Per Get Request', 'mactrack'),
		'description' => __('Specified the number of OID\'s that can be obtained in a single SNMP Get request.', 'mactrack'),
		'value' => '|arg1:max_oids|',
		'max_length' => '8',
		'default' => read_config_option('max_get_size'),
		'size' => '15'
		),
	'snmp_username' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Username (v3)', 'mactrack'),
		'description' => __('SNMP v3 username for this device.', 'mactrack'),
		'value' => '|arg1:snmp_username|',
		'default' => read_config_option('snmp_username'),
		'max_length' => '50',
		'size' => '15'
		),
	'snmp_password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('SNMP Password (v3)', 'mactrack'),
		'description' => __('SNMP v3 password for this device.', 'mactrack'),
		'value' => '|arg1:snmp_password|',
		'default' => read_config_option('snmp_password'),
		'max_length' => '50',
		'size' => '15'
		),
	'snmp_auth_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Auth Protocol (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Authorization Protocol.', 'mactrack'),
		'value' => '|arg1:snmp_auth_protocol|',
		'default' => read_config_option('snmp_auth_protocol'),
		'array' => $snmp_auth_protocols,
		),
	'snmp_priv_passphrase' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Privacy Passphrase (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Privacy Passphrase.', 'mactrack'),
		'value' => '|arg1:snmp_priv_passphrase|',
		'default' => read_config_option('snmp_priv_passphrase'),
		'max_length' => '200',
		'size' => '40'
		),
	'snmp_priv_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Privacy Protocol (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Privacy Protocol.', 'mactrack'),
		'value' => '|arg1:snmp_priv_protocol|',
		'default' => read_config_option('snmp_priv_protocol'),
		'array' => $snmp_priv_protocols,
		),
	'snmp_context' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Context (v3)', 'mactrack'),
		'description' => __('Enter the SNMP v3 Context to use for this device.', 'mactrack'),
		'value' => '|arg1:snmp_context|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	'snmp_engine_id' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Engine ID (v3)', 'mactrack'),
		'description' => __('Enter the SNMP v3 Engine ID to use for this device.', 'mactrack'),
		'value' => '|arg1:snmp_engine_id|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	);

	/* file: mactrack_devices.php, action: edit */
	$fields_mactrack_device_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Device Settings', 'mactrack')
		),
	'device_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Device Name', 'mactrack'),
		'description' => __('Give this device a meaningful name.', 'mactrack'),
		'value' => '|arg1:device_name|',
		'max_length' => '250'
		),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname', 'mactrack'),
		'description' => __('Fill in the fully qualified hostname for this device.', 'mactrack'),
		'value' => '|arg1:hostname|',
		'max_length' => '250'
		),
	'host_id' => array(
		'friendly_name' => __('Related Cacti Host', 'mactrack'),
		'description' => __('Given Device Tracking Host is connected to this Cacti Host.', 'mactrack'),
		#'method' => 'view',
		'method' => 'drop_sql',
		'value' => '|arg1:host_id|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select id,CONCAT_WS("",description," (",hostname,")") as name from host order by description,hostname'
		),
	'scan_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Scan Type', 'mactrack'),
		'description' => __('Choose the scan type you wish to perform on this device.', 'mactrack'),
		'value' => '|arg1:scan_type|',
		'default' => 1,
		'array' => $mactrack_device_types
		),
	'site_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Site Name', 'mactrack'),
		'description' => __('Choose the site to associate with this device.', 'mactrack'),
		'value' => '|arg1:site_id|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select site_id as id,site_name as name from mac_track_sites order by name'
		),
	'notes' => array(
		'method' => 'textarea',
		'friendly_name' => __('Device Notes', 'mactrack'),
		'description' => __('This field value is useful to save general information about a specific device.', 'mactrack'),
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'value' => '|arg1:notes|',
		'max_length' => '255'
		),
	'disabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Disable Device', 'mactrack'),
		'description' => __('Check this box to disable all checks for this host.', 'mactrack'),
		'value' => '|arg1:disabled|',
		'default' => '',
		'form_id' => false
		),
	'spacer1' => array(
		'method' => 'spacer',
		'friendly_name' => __('Switch/Hub, Switch/Router Settings', 'mactrack')
		),
	'ignorePorts' => array(
		'method' => 'textarea',
		'friendly_name' => __('Ports to Ignore', 'mactrack'),
		'description' => __('Provide a list of ports on a specific switch/hub whose MAC results should be ignored.  Ports such as link/trunk ports that can not be distinguished from other user ports are examples.  Each port number must be separated by a colon, pipe, or a space \':\', \'|\', \' \'.  For example, \'Fa0/1: Fa1/23\' or \'Fa0/1 Fa1/23\' would be acceptable for some manufacturers switch types.', 'mactrack'),
		'value' => '|arg1:ignorePorts|',
		'default' => '',
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'max_length' => '255'
		),
	'spacer2' => array(
		'method' => 'spacer',
		'friendly_name' => __('SNMP Options', 'mactrack')
		),
	'snmp_options' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('SNMP Options', 'mactrack'),
		'description' => __('Select a set of SNMP options to try.', 'mactrack'),
		'value' => '|arg1:snmp_options|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select * from mac_track_snmp order by name'
		),
	'snmp_readstrings' => array(
		'method' => 'view',
		'friendly_name' => __('Read Strings', 'mactrack'),
		'description' => __('<strong>DEPRECATED:</strong> SNMP community strings', 'mactrack'),
		'value' => '|arg1:snmp_readstrings|',
		),
	'spacer3' => array(
		'method' => 'spacer',
		'friendly_name' => __('Specific SNMP Settings', 'mactrack')
		),
	);

	$fields_mactrack_device_edit += $fields_mactrack_snmp_item;

	$fields_mactrack_device_edit += array(
	'spacer4' => array(
		'method' => 'spacer',
		'friendly_name' => __('Connectivity Options', 'mactrack')
		),
	'term_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Terminal Type', 'mactrack'),
		'description' => __('Choose the terminal type that you use to connect to this device.', 'mactrack'),
		'value' => '|arg1:term_type|',
		'default' => 1,
		'array' => array(
			0 => __('None', 'mactrack'), 
			1 => __('Telnet', 'mactrack'), 
			2 => __('SSH', 'mactrack'), 
			3 => __('HTTP', 'mactrack'), 
			4 => __('HTTPS', 'mactrack'))
		),
	'user_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('User Name', 'mactrack'),
		'description' => __('The user name to be used for your custom authentication method.  Examples include SSH, RSH, HTML, etc.', 'mactrack'),
		'value' => '|arg1:user_name|',
		'default' => '',
		'max_length' => '40',
		'size' => '20'
		),
	'user_password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password', 'mactrack'),
		'description' => __('The password to be used for your custom authentication.', 'mactrack'),
		'value' => '|arg1:user_password|',
		'default' => '',
		'max_length' => '40',
		'size' => '20'
		),
	'private_key_path' => array(
		'method' => 'filepath',
		'friendly_name' => __('Private Key Path', 'mactrack'),
		'description' => __('The path to the private key used for SSH authentication.', 'mactrack'),
		'value' => '|arg1:private_key_path|',
		'default' => '',
		'max_length' => '128',
		'size' => '40'
		),
	'device_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_id|'
		),
	'_device_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_id|'
		),
	'save_component_device' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);


	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item_edit = $fields_mactrack_snmp_item + array(
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence', 'mactrack'),
		'description' => __('Sequence of Item.', 'mactrack'),
		'value' => '|arg1:sequence|'),
	);

	/* file: mactrack_sites.php, action: edit */
	$fields_mactrack_site_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Site Settings', 'mactrack')
		),
	'site_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Site Name', 'mactrack'),
		'description' => __('Please enter a reasonable name for this site.', 'mactrack'),
		'value' => '|arg1:site_name|',
		'size' => '70',
		'max_length' => '250'
		),
	'customer_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('Primary Customer Contact', 'mactrack'),
		'description' => __('The principal customer contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:customer_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'netops_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('NetOps Contact', 'mactrack'),
		'description' => __('Please principal network support contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:netops_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'facilities_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('Facilities Contact', 'mactrack'),
		'description' => __('Please principal facilities/security contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:facilities_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'site_info' => array(
		'method' => 'textarea',
		'friendly_name' => __('Site Information', 'mactrack'),
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'description' => __('Provide any site-specific information, in free form, that allows you to better manage this location.', 'mactrack'),
		'value' => '|arg1:site_info|',
		'max_length' => '255'
		),
	'site_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:site_id|'
		),
	'_site_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:site_id|'
		),
	'save_component_site' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_macw_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Mac Address Tracking Settings', 'mactrack')
		),
	'mac_address' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Address', 'mactrack'),
		'description' => __('Please enter the MAC Address to be watched for.', 'mactrack'),
		'value' => '|arg1:mac_address|',
		'default' => '',
		'max_length' => '40'
		),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Tracking Name/Email Subject', 'mactrack'),
		'description' => __('Please enter a reasonable name for this MAC Tracking entry.  This information will be in the subject line of your Email', 'mactrack'),
		'value' => '|arg1:name|',
		'size' => '70',
		'max_length' => '250'
		),
	'description' => array(
		'friendly_name' => __('MacWatch Default Body', 'mactrack'),
		'description' => htmlspecialchars(__('The Email body preset for Device Tracking MacWatch Emails.  The body can contain any valid html tags.  It also supports replacement tags that will be processed when sending an Email.  Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>.', 'mactrack')),
		'method' => 'textarea',
		'class' => 'textAreaNotes',
		'value' => '|arg1:description|',
		'default' => __('Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, and Port Name <PORTNAME>', 'mactrack'),
		'max_length' => '512',
		'textarea_rows' => '5',
		'textarea_cols' => '80',
		),
	'ticket_number' => array(
		'method' => 'textbox',
		'friendly_name' => __('Ticket Number', 'mactrack'),
		'description' => __('Ticket number for cross referencing with your corporate help desk system(s).', 'mactrack'),
		'value' => '|arg1:ticket_number|',
		'size' => '70',
		'max_length' => '150'
		),
	'notify_schedule' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Notification Schedule', 'mactrack'),
		'description' => __('Choose how often an Email should be generated for this Mac Watch item.', 'mactrack'),
		'value' => '|arg1:notify_schedule|',
		'default' => '1',
		'array' => array(
			1    => __('First Occurrence Only', 'mactrack'),
			2    => __('All Occurrences', 'mactrack'),
			60   => __('Every Hour', 'mactrack'),
			240  => __('Every %d Hours', 4, 'mactrack'),
			1800 => __('Every %d Hours', 12, 'mactrack'),
			3600 => __('Every Day', 'mactrack'))
		),
	'email_addresses' => array(
		'method' => 'textbox',
		'friendly_name' => __('Email Addresses', 'mactrack'),
		'description' => __('Enter a semicolon separated of Email addresses that will be notified where this MAC address is.', 'mactrack'),
		'value' => '|arg1:email_addresses|',
		'size' => '90',
		'max_length' => '255'
		),
	'mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'_mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'save_component_macw' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_maca_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Mac Address Authorization Settings', 'mactrack')
		),
	'mac_address' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Address Match', 'mactrack'),
		'description' => __('Please enter the MAC Address or Mac Address Match string to be automatically authorized.  If you wish to authorize a group of MAC Addresses, you can use the wildcard character of \'%\' anywhere in the MAC Address.', 'mactrack'),
		'value' => '|arg1:mac_address|',
		'default' => '',
		'max_length' => '40'
		),
	'description' => array(
		'method' => 'textarea',
		'friendly_name' => __('Reason', 'mactrack'),
		'class' => 'textAreaNotes',
		'description' => __('Please add a reason for this entry.', 'mactrack'),
		'value' => '|arg1:description|',
		'textarea_rows' => '4',
		'textarea_cols' => '80'
		),
	'mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'_mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'save_component_maca' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);
}

function convert_readstrings() {
	global $config;

	include_once($config['base_path'] . '/lib/functions.php');

	$sql = 'SELECT DISTINCT ' .
		'snmp_readstrings, ' .
		'snmp_version, ' .
		'snmp_port, ' .
		'snmp_timeout, ' .
		'snmp_retries ' .
		'FROM mac_track_devices';

	$devices = db_fetch_assoc($sql);

	if (sizeof($devices)) {
		$i = 0;
		foreach($devices as $device) {
			# create new SNMP Option Set
			unset($save);
			$save['id'] = 0;
			$save['name'] = 'Custom_' . $i++;
			$snmp_id = sql_save($save, 'mac_track_snmp');

			# add each single option derived from readstrings
			$read_strings = explode(':',$device['snmp_readstrings']);
			if (sizeof($read_strings)) {
				foreach($read_strings as $snmp_readstring) {
					unset($save);
					$save['id']						= 0;
					$save['snmp_id'] 				= $snmp_id;
					$save['sequence'] 				= get_sequence('', 'sequence', 'mac_track_snmp_items', 'snmp_id=' . $snmp_id);

					$save['snmp_readstring'] 		= $snmp_readstring;
					$save['snmp_version'] 			= $device['snmp_version'];
					$save['snmp_port']				= $device['snmp_port'];
					$save['snmp_timeout']			= $device['snmp_timeout'];
					$save['snmp_retries']			= $device['snmp_retries'];
					$save['snmp_username']			= '';
					$save['snmp_password']			= '';
					$save['snmp_auth_protocol']		= '';
					$save['snmp_priv_passphrase']	= '';
					$save['snmp_priv_protocol']		= '';
					$save['snmp_context']			= '';
					$save['snmp_engine_id']         = '';
					$save['max_oids']				= '';

					$item_id = sql_save($save, 'mac_track_snmp_items');
				}
			} # each readstring added as SNMP Option item

			# now, let's find all devices, that used this snmp_readstrings
			$sql = 'UPDATE mac_track_devices SET snmp_options=' . $snmp_id .
					" WHERE snmp_readstrings='" . $device['snmp_readstrings'] .
					"' AND snmp_version=" . $device['snmp_version'] .
					' AND snmp_port=' . $device['snmp_port'] .
					' AND snmp_timeout=' . $device['snmp_timeout'] .
					' AND snmp_retries=' . $device['snmp_retries'];

			$ok = db_execute($sql);
		}
	}
	db_execute("REPLACE INTO settings (name,value) VALUES ('mt_convert_readstrings', 'on')");
	# we keep the field:snmp_readstrings in mac_track_devices, it should be deprecated first
	# next mactrack release may delete that field, then
}
