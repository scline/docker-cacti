<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
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

include_once($config['base_path'] . '/plugins/syslog/database.php');

function plugin_syslog_install() {
	global $config, $syslog_upgrade;
	static $bg_inprocess = false;

	if (file_exists(dirname(__FILE__) . '/config.php')) {
		include(dirname(__FILE__) . '/config.php');
	}else{
		$_SESSION['clog_error'] = __('Please rename your config.php.dist file in the syslog directory, and change setup your database before installing.'); 
		raise_message('clog_error');
		header('Location:' . $config['url_path'] . 'plugins.php?header=false');
		exit;
	}

	syslog_connect();

	$syslog_exists = sizeof(syslog_db_fetch_row('SHOW TABLES FROM `' . $syslogdb_default . "` LIKE 'syslog'"));
	$db_version    = syslog_get_mysql_version('syslog');

	/* ================= input validation ================= */
	get_filter_request_var('days');
	/* ==================================================== */

	api_plugin_register_hook('syslog', 'config_arrays',         'syslog_config_arrays',        'setup.php');
	api_plugin_register_hook('syslog', 'draw_navigation_text',  'syslog_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('syslog', 'config_settings',       'syslog_config_settings',      'setup.php');
	api_plugin_register_hook('syslog', 'top_header_tabs',       'syslog_show_tab',             'setup.php');
	api_plugin_register_hook('syslog', 'top_graph_header_tabs', 'syslog_show_tab',             'setup.php');
	api_plugin_register_hook('syslog', 'top_graph_refresh',     'syslog_top_graph_refresh',    'setup.php');
	api_plugin_register_hook('syslog', 'poller_bottom',         'syslog_poller_bottom',        'setup.php');
	api_plugin_register_hook('syslog', 'graph_buttons',         'syslog_graph_buttons',        'setup.php');
	api_plugin_register_hook('syslog', 'config_insert',         'syslog_config_insert',        'setup.php');
	api_plugin_register_hook('syslog', 'utilities_list',        'syslog_utilities_list',       'setup.php');
	api_plugin_register_hook('syslog', 'utilities_action',      'syslog_utilities_action',     'setup.php');

	api_plugin_register_realm('syslog', 'syslog.php', 'Plugin -> Syslog User', 1);
	api_plugin_register_realm('syslog', 'syslog_alerts.php,syslog_removal.php,syslog_reports.php', 'Plugin -> Syslog Administration', 1);

	if (isset_request_var('install')) {
		if (!$bg_inprocess) {
			syslog_execute_update($syslog_exists, $_REQUEST);
			$bg_inprocess = true;

			return true;
		}
	}elseif (isset_request_var('cancel')) {
		header('Location:' . $config['url_path'] . 'plugins.php?mode=uninstall&id=syslog&uninstall&uninstall_method=all');
		exit;
	}else{
		syslog_install_advisor($syslog_exists, $db_version);
		exit;
	}
}

function syslog_execute_update($syslog_exists, $options) {
	global $config;

	if (isset($options['cancel'])) {
		header('Location:' . $config['url_path'] . 'plugins.php?mode=uninstall&id=syslog&uninstall&uninstall_method=all');
		exit;
	}elseif (isset($options['return'])) {
		db_execute("DELETE FROM plugin_config WHERE directory='syslog'");
		db_execute("DELETE FROM plugin_realms WHERE plugin='syslog'");
		db_execute("DELETE FROM plugin_db_changes WHERE plugin='syslog'");
		db_execute("DELETE FROM plugin_hooks WHERE name='syslog'");
	}elseif (isset($options["upgrade_type"])) {
		if ($options["upgrade_type"] == "truncate") {
			syslog_setup_table_new($options);
		}
	}else{
		syslog_setup_table_new($options);
	}

	db_execute("REPLACE INTO settings SET name='syslog_retention', value='" . $options['days'] . "'");
}

function plugin_syslog_uninstall () {
	global $config, $cnn_id, $syslog_incoming_config, $database_default, $database_hostname, $database_username;

	/* database connection information, must be loaded always */
	include(dirname(__FILE__) . '/config.php');
	include_once(dirname(__FILE__) . '/functions.php');

	syslog_connect();

	if (isset_request_var('cancel') || isset_request_var('return')) {
		header('Location:' . $config['url_path'] . 'plugins.php?header=false');
		exit;
	}elseif (isset_request_var('uninstall_method')) {
		if (get_nfilter_request_var('uninstall_method') == 'all') {
			/* do the big tables first */
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_removed`');

			/* do the settings tables last */
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_incoming`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_alert`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_remove`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_reports`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_facilities`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_statistics`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_host_facilities`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_priorities`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_logs`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_hosts`');
		}else{
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog`');
			syslog_db_execute('DROP TABLE IF EXISTS `' . $syslogdb_default . '`.`syslog_removed`');
		}
	}else{
		syslog_uninstall_advisor();
		exit;
	}
}

function plugin_syslog_check_config() {
	/* Here we will check to ensure everything is configured */
	syslog_check_upgrade();
	return true;
}

function plugin_syslog_upgrade() {
	/* Here we will upgrade to the newest version */
	syslog_check_upgrade();
	return false;
}

function syslog_connect() {
	global $config, $cnn_id, $syslog_cnn, $database_default;

	include(dirname(__FILE__) . '/config.php');
	include_once(dirname(__FILE__) . '/functions.php');

	/* Connect to the Syslog Database */
	if (empty($syslog_cnn)) {
		if ((strtolower($database_hostname) == strtolower($syslogdb_hostname)) &&
			($database_default == $syslogdb_default)) {
			/* move on, using Cacti */
			$syslog_cnn = $cnn_id;
		}else{
			if (!isset($syslogdb_port)) {
				$syslogdb_port = '3306';
			}
			$syslog_cnn = syslog_db_connect_real($syslogdb_hostname, $syslogdb_username, $syslogdb_password, $syslogdb_default, $syslogdb_type, $syslogdb_port);
			if ($syslog_cnn == false) {
					echo "Can not connect\n";
					return FALSE;
			}
		}
	}
}

function syslog_check_upgrade() {
	global $config, $cnn_id, $syslog_levels, $database_default, $syslog_upgrade;

	include(dirname(__FILE__) . '/config.php');

	syslog_connect();

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'syslog.php', 'syslog_removal.php', 'syslog_alerts.php', 'syslog_reports.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$present = syslog_db_fetch_row('SHOW TABLES FROM `' . $syslogdb_default . "` LIKE 'syslog'");
	$old_pia = false;
	if (sizeof($present)) {
		$old_table = syslog_db_fetch_row('SHOW COLUMNS FROM `' . $syslogdb_default . "`.`syslog` LIKE 'time'");
		if (sizeof($old_table)) {
			$old_pia = true;
		}
	}

	/* don't let this script timeout */
	ini_set('max_execution_time', 0);

	$version = plugin_syslog_version();
	$current = $version['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='syslog'");

	if ($current != $old) {
		if ($old_pia || $old < 2) {
			echo __('Syslog 2.0 Requires an Entire Reinstall.  Please uninstall Syslog and Remove all Data before Installing.  Migration is possible, but you must plan this in advance.  No automatic migration is supported.') . "\n";
			exit;
		}elseif ($old == 2) {
			db_execute('ALTER TABLE syslog_statistics 
				ADD COLUMN id BIGINT UNSIGNED auto_increment FIRST, 
				DROP PRIMARY KEY, 
				ADD PRIMARY KEY(id), 
				ADD UNIQUE INDEX (`host_id`,`facility_id`,`priority_id`,`program_id`,`insert_time`)');

		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='syslog'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='" . $version['longname'] . "', 
			author='" . $version['author'] . "', 
			webpage='" . $version['url'] . "' 
			WHERE directory='" . $version['name'] . "' ");
		}
	}
}

function syslog_get_mysql_version($db = 'cacti') {
	if ($db == 'cacti') {
		$dbInfo = db_fetch_row("SHOW GLOBAL VARIABLES LIKE 'version'");
	}else{
		$dbInfo = syslog_db_fetch_row("SHOW GLOBAL VARIABLES LIKE 'version'");

	}

	if (sizeof($dbInfo)) {
		return floatval($dbInfo['Value']);
	}
	return '';
}

function syslog_create_partitioned_syslog_table($engine = 'InnoDB', $days = 30) {
	global $config, $mysqlVersion, $cnn_id, $syslog_incoming_config, $syslog_levels, $database_default, $database_hostname, $database_username;

	include(dirname(__FILE__) . '/config.php');

	$sql = "CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog` (
		facility_id int(10) unsigned default NULL,
		priority_id int(10) unsigned default NULL,
		program_id int(10) unsigned default NULL,
		host_id int(10) unsigned default NULL,
		logtime DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		message " . ($mysqlVersion > 5 ? "varchar(1024)":"text") . " NOT NULL default '',
		seq bigint unsigned NOT NULL auto_increment,
		INDEX (seq),
		INDEX logtime (logtime),
		INDEX program_id (program_id),
		INDEX host_id (host_id),
		INDEX priority_id (priority_id),
		INDEX facility_id (facility_id)) ENGINE=$engine
		PARTITION BY RANGE (TO_DAYS(logtime))\n";

	$now = time();

	$parts = '';
	for($i = $days; $i >= -1; $i--) {
		$timestamp = $now - ($i * 86400);
		$date     = date('Y-m-d', $timestamp);
		$format   = date('Ymd', $timestamp - 86400);
		$parts .= ($parts != '' ? ",\n":"(") . " PARTITION d" . $format . " VALUES LESS THAN (TO_DAYS('" . $date . "'))";
	}
	$parts .= ",\nPARTITION dMaxValue VALUES LESS THAN MAXVALUE);";

	syslog_db_execute($sql . $parts);
}

function syslog_setup_table_new($options) {
	global $config, $cnn_id, $settings, $mysqlVersion, $syslog_incoming_config, $syslog_levels, $database_default, $database_hostname, $database_username;

	include(dirname(__FILE__) . '/config.php');

	$tables  = array();

	$syslog_levels = array(
		0 => 'emerg',
		1 => 'crit',
		2 => 'alert',
		3 => 'err',
		4 => 'warn',
		5 => 'notice',
		6 => 'info',
		7 => 'debug',
		8 => 'other'
	);

	syslog_connect();

	/* validate some simple information */
	$mysqlVersion = syslog_get_mysql_version('syslog');
	$truncate     = ((isset($options['upgrade_type']) && $options['upgrade_type'] == 'truncate') ? true:false);
	$engine       = ((isset($options['engine']) && $options['engine'] == 'innodb') ? 'InnoDB':'MyISAM');
	$partitioned  = ((isset($options['db_type']) && $options['db_type'] == 'part') ? true:false);
	$syslogexists = sizeof(syslog_db_fetch_row("SHOW TABLES FROM `" . $syslogdb_default . "` LIKE 'syslog'"));

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog`");
	if (!$partitioned) {
		syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog` (
			facility_id int(10) unsigned default NULL,
			priority_id int(10) unsigned default NULL,
			program_id int(10) unsigned default NULL,
			host_id int(10) unsigned default NULL,
			logtime TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
			message varchar(1024) NOT NULL default '',
			seq bigint unsigned NOT NULL auto_increment,
			PRIMARY KEY (seq),
			INDEX logtime (logtime),
			INDEX program_id (program_id),
			INDEX host_id (host_id),
			INDEX priority_id (priority_id),
			INDEX facility_id (facility_id)) ENGINE=$engine;");
	}else{
		syslog_create_partitioned_syslog_table($engine, $options['days']);
	}

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_alert`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_alert` (
		`id` int(10) NOT NULL auto_increment,
		`name` varchar(255) NOT NULL default '',
		`severity` int(10) UNSIGNED NOT NULL default '0',
		`method` int(10) unsigned NOT NULL default '0',
		`num` int(10) unsigned NOT NULL default '1',
		`type` varchar(16) NOT NULL default '',
		`enabled` CHAR(2) default 'on',
		`repeat_alert` int(10) unsigned NOT NULL default '0',
		`open_ticket` CHAR(2) default '',
		`message` VARCHAR(128) NOT NULL default '',
		`user` varchar(32) NOT NULL default '',
		`date` int(16) NOT NULL default '0',
		`email` varchar(255) default NULL,
		`command` varchar(255) default NULL,
		`notes` varchar(255) default NULL,
		PRIMARY KEY (id)) ENGINE=$engine;");

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_incoming`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_incoming` (
		facility_id int(10) unsigned default NULL,
		priority_id int(10) unsigned default NULL,
		program varchar(40) default NULL,
		`date` date default NULL,
		`time` time default NULL,
		host varchar(64) default NULL,
		message varchar(1024) NOT NULL DEFAULT '',
		seq bigint unsigned NOT NULL auto_increment,
		`status` tinyint(4) NOT NULL default '0',
		PRIMARY KEY (seq),
		INDEX program (program),
		INDEX `status` (`status`)) ENGINE=$engine;");

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_remove`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_remove` (
		id int(10) NOT NULL auto_increment,
		name varchar(255) NOT NULL default '',
		`type` varchar(16) NOT NULL default '',
		enabled CHAR(2) DEFAULT 'on',
		method CHAR(5) DEFAULT 'del',
		message VARCHAR(128) NOT NULL default '',
		`user` varchar(32) NOT NULL default '',
		`date` int(16) NOT NULL default '0',
		notes varchar(255) default NULL,
		PRIMARY KEY (id)) ENGINE=$engine;");

	$present = syslog_db_fetch_row("SHOW TABLES FROM `" . $syslogdb_default . "` LIKE 'syslog_reports'");
	if (sizeof($present)) {
		$newreport = sizeof(syslog_db_fetch_row("SHOW COLUMNS FROM `" . $syslogdb_default . "`.`syslog_reports` LIKE 'body'"));
	}else{
		$newreport = true;
	}
	if ($truncate || !$newreport) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_reports`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_reports` (
		id int(10) NOT NULL auto_increment,
		name varchar(255) NOT NULL default '',
		`type` varchar(16) NOT NULL default '',
		enabled CHAR(2) DEFAULT 'on',
		timespan int(16) NOT NULL default '0',
		timepart char(5) NOT NULL default '00:00',
		lastsent int(16) NOT NULL default '0',
		body " . ($mysqlVersion > 5 ? "varchar(1024)":"text") . " default NULL,
		message varchar(128) default NULL,
		`user` varchar(32) NOT NULL default '',
		`date` int(16) NOT NULL default '0',
		email varchar(255) default NULL,
		notes varchar(255) default NULL,
		PRIMARY KEY (id)) ENGINE=$engine;");

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_hosts`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_programs` (
		`program_id` int(10) unsigned NOT NULL auto_increment,
		`program` VARCHAR(40) NOT NULL,
		`last_updated` TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`program`),
		INDEX host_id (`program_id`),
		INDEX last_updated (`last_updated`)) ENGINE=$engine
		COMMENT='Contains all programs currently in the syslog table'");

	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_hosts` (
		`host_id` int(10) unsigned NOT NULL auto_increment,
		`host` VARCHAR(64) NOT NULL,
		`last_updated` TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`host`),
		INDEX host_id (`host_id`),
		INDEX last_updated (`last_updated`)) ENGINE=$engine
		COMMENT='Contains all hosts currently in the syslog table'");

	syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_facilities`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `". $syslogdb_default . "`.`syslog_facilities` (
		`facility_id` int(10) unsigned NOT NULL,
		`facility` varchar(10) NOT NULL,
		`last_updated` TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`facility_id`),
		INDEX last_updated (`last_updated`)) ENGINE=$engine;");

	syslog_db_execute("INSERT INTO `" .  $syslogdb_default . "`.`syslog_facilities` (facility_id, facility) VALUES 
		(0,'kern'), (1,'user'), (2,'mail'), (3,'daemon'), (4,'auth'), (5,'syslog'), (6,'lpd'), (7,'news'), 
		(8,'uucp'), (9,'crond'), (10,'authpriv'), (11,'ftpd'), (12,'ntpd'), (13,'logaudit'), (14,'logalert'), 
		(15,'crond'), (16,'local0'), (17,'local1'), (18,'local2'), (19,'local3'), (20,'local4'), (21,'local5'), 
		(22,'local6'), (23,'local7');");

	syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_priorities`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `". $syslogdb_default . "`.`syslog_priorities` (
		`priority_id` int(10) unsigned NOT NULL,
		`priority` varchar(10) NOT NULL,
		`last_updated` TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`priority_id`),
		INDEX last_updated (`last_updated`)) 
		ENGINE=$engine;");

	syslog_db_execute("INSERT INTO `" .  $syslogdb_default . "`.`syslog_priorities` (priority_id, priority) VALUES 
		(0,'emerg'), (1,'alert'), (2,'crit'), (3,'err'), (4,'warning'), (5,'notice'), (6,'info'), (7,'debug'), (8,'other');");

	syslog_db_execute("CREATE TABLE IF NOT EXISTS `". $syslogdb_default . "`.`syslog_host_facilities` (
		`host_id` int(10) unsigned NOT NULL,
		`facility_id` int(10) unsigned NOT NULL,
		`last_updated` TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`host_id`,`facility_id`)) 
		ENGINE=$engine;");

	if ($truncate) syslog_db_execute("DROP TABLE IF EXISTS `" . $syslogdb_default . "`.`syslog_removed`");
	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_removed` LIKE `" . $syslogdb_default . "`.`syslog`");

	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_logs` (
		alert_id int(10) unsigned not null default '0',
		logseq bigint unsigned NOT NULL,
		logtime TIMESTAMP NOT NULL default '0000-00-00 00:00:00',
		logmsg varchar(1024) default NULL,
		host varchar(64) default NULL,
		facility_id int(10) unsigned default NULL,
		priority_id int(10) unsigned default NULL,
		program_id int(10) unsigned default NULL,
		count integer unsigned NOT NULL default '0',
		html blob default NULL,
		seq bigint unsigned NOT NULL auto_increment,
		PRIMARY KEY (seq),
		INDEX `logseq` (`logseq`),
		INDEX `program_id` (`program_id`),
		INDEX `alert_id` (`alert_id`),
		INDEX `host` (`host`),
		INDEX `seq` (`seq`),
		INDEX `logtime` (`logtime`),
		INDEX `priority_id` (`priority_id`),
		INDEX `facility_id` (`facility_id`)) 
		ENGINE=$engine;");

	syslog_db_execute("CREATE TABLE IF NOT EXISTS `" . $syslogdb_default . "`.`syslog_statistics` (
		`id` bigint UNSIGNED auto_increment,
		`host_id` int(10) UNSIGNED NOT NULL,
		`facility_id` int(10) UNSIGNED NOT NULL,
		`priority_id` int(10) UNSIGNED NOT NULL,
		`program_id` int(10) unsigned default NULL,
		`insert_time` TIMESTAMP NOT NULL,
		`records` int(10) UNSIGNED NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_pk` (`host_id`, `facility_id`, `priority_id`, `program_id`, `insert_time`),
		INDEX `host_id`(`host_id`),
		INDEX `facility_id`(`facility_id`),
		INDEX `priority_id`(`priority_id`),
		INDEX `program_id` (`program_id`),
		INDEX `insert_time`(`insert_time`))
		ENGINE = $engine
		COMMENT = 'Maintains High Level Statistics';");

	if (!isset($settings['syslog'])) {
		syslog_config_settings();
	}

	foreach($settings['syslog'] AS $name => $values) {
		if (isset($values['default'])) {
			db_execute('REPLACE INTO `' . $database_default . "`.`settings` (name, value) VALUES ('$name', '" . $values['default'] . "')");
		}
	}
}

function plugin_syslog_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/syslog/INFO', true);
	return $info['info'];
}

function syslog_check_dependencies() {
	return true;
}

function syslog_poller_bottom() {
	global $config;

	$command_string = read_config_option('path_php_binary');
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/syslog/syslog_process.php';
	exec_background($command_string, $extra_args);
}

function syslog_install_advisor($syslog_exists, $db_version) {
	global $config, $colors, $syslog_retentions;

	top_header();

	syslog_config_arrays();

	$fields_syslog_update = array(
		'upgrade_type' => array(
			'method' => 'drop_array',
			'friendly_name' => __('What upgrade/install type do you wish to use'),
			'description' => __('When you have very large tables, performing a Truncate will be much quicker.  If you are
			concerned about archive data, you can choose either Inline, which will freeze your browser for the period
			of this upgrade, or background, which will create a background process to bring your old syslog data
			from a backup table to the new syslog format.  Again this process can take several hours.'),
			'value' => 'truncate',
			'array' => array('truncate' => __('Truncate Syslog Table'), 'inline' => __('Inline Upgrade'), 'background' => __('Background Upgrade')),
		),
		'engine' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Database Storage Engine'),
			'description' => __('In MySQL 5.1.6 and above, you have the option to make this a partitioned table by days.  Prior to this
			release, you only have the traditional table structure available.'),
			'value' => 'myisam',
			'array' => array('myisam' => __('MyISAM Storage'), 'innodb' => __('InnoDB Storage')),
		),
		'db_type' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Database Architecture'),
			'description' => __('In MySQL 5.1.6 and above, you have the option to make this a partitioned table by days.
				In MySQL 5.5 and above, you can create multiple partitions per day.
				Prior to MySQL 5.1.6, you only have the traditional table structure available.'),
			'value' => 'trad',
			'array' => ($db_version >= '5.1' ? array('trad' => __('Traditional Table'), 'part' => __('Partitioned Table')): array('trad' => __('Traditional Table'))),
		),
		'days' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Retention Policy'),
			'description' => __('Choose how many days of Syslog values you wish to maintain in the database.'),
			'value' => '30',
			'array' => $syslog_retentions
		),
		'mode' => array(
			'method' => 'hidden',
			'value' => 'install'
		),
		'install' => array(
			'method' => 'hidden',
			'value' => 'true'
		),
		'id' => array(
			'method' => 'hidden',
			'value' => 'syslog'
		)
	);

	if ($db_version >= 5.5) {
		$fields_syslog_update['dayparts'] = array(
			'method' => 'drop_array',
			'friendly_name' => __('Partitions per Day'),
			'description' => __('Select the number of partitions per day that you wish to create.'),
			'value' => '1',
			'array' => array(
				'1'  => __('%d Per Day', 1), 
				'2'  => __('%d Per Day', 2), 
				'4'  => __('%d Per Day', 4),
				'6'  => __('%d Per Day', 6), 
				'12' => __('%d Per Day', 12)
			)
		);
	}

	if ($syslog_exists) {
		$type = __('Upgrade');
	}else{
		$type = __('Install');
	}

	print "<table align='center' width='80%'><tr><td>\n";
	html_start_box(__('Syslog %s Advisor', $type) . '<', '100%', $colors['header'], '3', 'center', '');
	print "<tr><td>\n";
	if ($syslog_exists) {
		print "<h2 style='color:red;'>" . __('WARNING: Syslog Upgrade is Time Consuming!!!') . "</h2>\n";
		print "<p>" . __('The upgrade of the \'main\' syslog table can be a very time consuming process.  As such, it is recommended
			that you either reduce the size of your syslog table prior to upgrading, or choose the background option</p>
			<p>If you choose the background option, your legacy syslog table will be renamed, and a new syslog table will
			be created.  Then, an upgrade process will be launched in the background.  Again, this background process can
			quite a bit of time to complete.  However, your data will be preserved</p>
			<p>Regardless of your choice, all existing removal and alert rules will be maintained during the upgrade process.</p>
			<p>Press <b>\'Upgrade\'</b> to proceed with the upgrade, or <b>\'Cancel\'</b> to return to the Plugins menu.') . "</p>
			</td></tr>";
	}else{
		unset($fields_syslog_update['upgrade_type']);
		print "<p>" . __('You have several options to choose from when installing Syslog.  The first is the Database Architecture.
			Starting with MySQL 5.1.6, you can elect to utilize Table Partitioning to prevent the size of the tables
			from becoming excessive thus slowing queries.') ."</p><p>" . __('You can also set the MySQL storage engine.  If you have not tuned you system for InnoDB storage properties,
			it is strongly recommended that you utilize the MyISAM storage engine.') . "</p>
			<p>" . __('You can also select the retention duration.  Please keep in mind that if you have several hosts logging
			to syslog, this table can become quite large.  So, if not using partitioning, you might want to keep the size
			smaller.') . "</p>
			</td></tr>";
	}
	html_end_box();
	print "<form action='plugins.php' method='get'>\n";
	html_start_box(__('Syslog %s Settings', $type), '100%', $colors['header'], '3', 'center', '');
	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_syslog_update, array()))
		);
	html_end_box();
	syslog_confirm_button('install', 'plugins.php', $syslog_exists);
	print "</td></tr></table>\n";

	bottom_footer();
	exit;
}

function syslog_uninstall_advisor() {
	global $config, $colors;

	include(dirname(__FILE__) . '/config.php');

	syslog_connect();

	$syslog_exists = sizeof(syslog_db_fetch_row('SHOW TABLES FROM `' . $syslogdb_default . "` LIKE 'syslog'"));

	top_header();

	$fields_syslog_update = array(
		'uninstall_method' => array(
			'method' => 'drop_array',
			'friendly_name' => __('What uninstall method do you want to use?'),
			'description' => __('When uninstalling syslog, you can remove everything, or only components, just in case you plan on re-installing in the future.'),
			'value' => 'all',
			'array' => array('all' => __('Remove Everything (Logs, Tables, Settings)'), 'syslog' => __('Syslog Data Only')),
		),
		'mode' => array(
			'method' => 'hidden',
			'value' => 'uninstall'
		),
		'uninstall' => array(
			'method' => 'hidden',
			'value' => 'true'
		),
		'id' => array(
			'method' => 'hidden',
			'value' => 'syslog'
		)
	);

	form_start('plugins.php');

	print "<table align='center' width='80%'><tr><td>\n";

	html_start_box(__('Syslog Uninstall Preferences'), '100%', $colors['header'], '3', 'center', '');
	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_syslog_update, array()))
		);
	html_end_box();

	syslog_confirm_button('uninstall', 'plugins.php', $syslog_exists);
	print "</td></tr></table>\n";

	bottom_footer();
	exit;
}

function syslog_confirm_button($action, $cancel_url, $syslog_exists) {
	if ($action == 'install' ) {
		if ($syslog_exists) {
			$value = __('Upgrade');
		}else{
			$value = __('Install');
		}
	}else{
		$value = __('Uninstall');
	}

	?>
	<table align='center' width='100%'>
		<tr>
			<td class='saveRow' align='right'>
				<input id='<?php print ($syslog_exists ? 'return':'cancel')?>' type='button' value='<?php print __('Cancel');?>'>
				<input id='<?php print $action;?>' type='submit' value='<?php print $value;?>'>
				<script type='text/javascript'>
				$(function() {
					$('form').submit(function(event) {
						event.preventDefault();
						strURL = $(this).attr('action');
						strURL += (strURL.indexOf('?') >= 0 ? '&':'?') + 'header=false';
						json = $(this).serializeObject();
						$.post(strURL, json).done(function(data) {
							$('#main').html(data);
							applySkin();
							window.scrollTo(0, 0);
						});
					});

					$('#cancel').click(function() {
						loadPageNoHeader('plugins.php?header=false');
					});
				});
				</script>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

function syslog_config_settings() {
	global $tabs, $settings, $syslog_retentions, $syslog_alert_retentions, $syslog_refresh;

	$tabs['syslog'] = __('Syslog');

	$temp = array(
		'syslog_header' => array(
			'friendly_name' => __('General Settings'),
			'method' => 'spacer',
		),
		'syslog_enabled' => array(
			'friendly_name' => __('Syslog Enabled'),
			'description' => __('If this checkbox is set, records will be transferred from the Syslog Incoming table to the
				main syslog table and Alerts and Reports will be enabled.  Please keep in mind that if the system is disabled
				log entries will still accumulate into the Syslog Incoming table as this is defined by the rsyslog or syslog-ng process.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'syslog_html' => array(
			'friendly_name' => __('HTML Based Email'),
			'description' => __('If this checkbox is set, all Emails will be sent in HTML format.  Otherwise, Emails will be sent in plain text.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'syslog_statistics' => array(
			'friendly_name' => __('Enable Statistics Gathering'),
			'description' => __('If this checkbox is set, statistics on where syslog messages are arriving from will be maintained.
			This statistical information can be used to render things such as heat maps.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'syslog_domains' => array(
			'friendly_name' => __('Strip Domains'),
			'description' => __('A comma delimited list of domains that you wish to remove from the syslog hostname, Examples would be \'mydomain.com, otherdomain.com\''),
			'method' => 'textbox',
			'default' => '',
			'size' => 80,
			'max_length' => 255,
		),
		'syslog_validate_hostname' => array(
			'friendly_name' => __('Validate Hostnames'),
			'description' => __('If this checkbox is set, all hostnames are validated.  If the hostname is not valid. All records are assigned
			to a special host called \'invalidhost\'.  This setting can impact syslog processing time on large systems.  Therefore, use of this
			setting should only be used when other means are not in place to prevent this from happening.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'syslog_refresh' => array(
			'friendly_name' => __('Refresh Interval'),
			'description' => __('This is the time in seconds before the page refreshes.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => $syslog_refresh
		),
		'syslog_maxrecords' => array(
			'friendly_name' => __('Max Report Records'),
			'description' => __('For Threshold based Alerts, what is the maximum number that you wish to
			show in the report.  This is used to limit the size of the html log and Email.'),
			'method' => 'drop_array',
			'default' => '100',
			'array' => array(
				20  => __('%d Records', 20), 
				40  => __('%d Records', 40), 
				60  => __('%d Records', 60), 
				100 => __('%d Records', 100), 
				200 => __('%d Records', 200), 
				400 => __('%d Records', 400)
			)
		),
		'syslog_retention' => array(
			'friendly_name' => __('Syslog Retention'),
			'description' => __('This is the number of days to keep events.'),
			'method' => 'drop_array',
			'default' => '30',
			'array' => $syslog_retentions
		),
		'syslog_alert_retention' => array(
			'friendly_name' => __('Syslog Alert Retention'),
			'description' => __('This is the number of days to keep alert logs.'),
			'method' => 'drop_array',
			'default' => '30',
			'array' => $syslog_alert_retentions
		),
		'syslog_ticket_command' => array(
			'friendly_name' => __('Command for Opening Tickets'),
			'description' => __('This command will be executed for opening Help Desk Tickets.  The command will be required to
			parse multiple input parameters as follows: <b>--alert-name</b>, <b>--severity</b>, <b>--hostlist</b>, <b>--message</b>.
			The hostlist will be a comma delimited list of hosts impacted by the alert.'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => 80
		)
	);

	if (isset($settings['syslog'])) {
		$settings['syslog'] = array_merge($settings['syslog'], $temp);
	}else{
		$settings['syslog'] = $temp;
	}
}

function syslog_top_graph_refresh($refresh) {
	return $refresh;
}

function syslog_show_tab() {
	global $config;

	if (api_user_realm_auth('syslog.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'syslog.php')) {
			print '<a href="' . $config['url_path'] . 'plugins/syslog/syslog.php"><img src="' . $config['url_path'] . 'plugins/syslog/images/tab_syslog_down.gif" alt="syslog" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/syslog/syslog.php"><img src="' . $config['url_path'] . 'plugins/syslog/images/tab_syslog.gif" alt="syslog" align="absmiddle" border="0"></a>';
		}
	}
}

function syslog_config_arrays () {
	global $syslog_actions, $menu, $message_types, $severities, $messages;
	global $syslog_levels, $syslog_facilities, $syslog_freqs, $syslog_times, $syslog_refresh;
	global $syslog_retentions, $syslog_alert_retentions, $menu_glyphs;

	$syslog_actions = array(
		1 => __('Delete'),
		2 => __('Disable'),
		3 => __('Enable')
	);

	$syslog_levels = array(
		0 => 'emerg',
		1 => 'crit',
		2 => 'alert',
		3 => 'err',
		4 => 'warn',
		5 => 'notice',
		6 => 'info',
		7 => 'debug',
		8 => 'other'
	);

	$syslog_facilities = array(
		0 => 'kernel',
		1 => 'user',
		2 => 'mail',
		3 => 'daemon',
		4 => 'auth',
		5 => 'syslog',
		6 => 'lpr',
		7 => 'news',
		8 => 'uucp',
		9 => 'cron',
		10 => 'authpriv',
		11 => 'ftp',
		12 => 'ntp',
		13 => 'log audit',
		14 => 'log alert',
		15 => 'cron',
		16 => 'local0',
		17 => 'local1',
		18 => 'local2',
		19 => 'local3',
		20 => 'local4',
		21 => 'local5',
		22 => 'local6',
		23 => 'local7'
	);

	$syslog_retentions = array(
		'0'   => __('Indefinite'),
		'1'   => __('%d Day', 1),
		'2'   => __('%d Days', 2),
		'3'   => __('%d Days', 3),
		'4'   => __('%d Days', 4),
		'5'   => __('%d Days', 5),
		'6'   => __('%d Days', 6),
		'7'   => __('%d Week', 1),
		'14'  => __('%d Weeks', 2),
		'30'  => __('%d Month', 1),
		'60'  => __('%d Months', 2),
		'90'  => __('%d Months', 3),
		'120' => __('%d Months', 4),
		'160' => __('%d Months', 5),
		'183' => __('%d Months', 6),
		'365' => __('%d Year', 1)
	);

	$syslog_alert_retentions = array(
		'0'   => __('Indefinite'),
		'1'   => __('%d Day', 1),
		'2'   => __('%d Days', 2),
		'3'   => __('%d Days', 3),
		'4'   => __('%d Days', 4),
		'5'   => __('%d Days', 5),
		'6'   => __('%d Days', 6),
		'7'   => __('%d Week', 1),
		'14'  => __('%d Weeks', 2),
		'30'  => __('%d Month', 1),
		'60'  => __('%d Months', 2),
		'90'  => __('%d Months', 3),
		'120' => __('%d Months', 4),
		'160' => __('%d Months', 5),
		'183' => __('%d Months', 6),
		'365' => __('%d Year', 1)
	);

	$syslog_refresh = array(
		9999999 => __('Never'),
		'60'    => __('%d Minute', 1),
		'120'   => __('%d Minutes', 2),
		'300'   => __('%d Minutes', 5),
		'600'   => __('%d Minutes', 10)
	);

	$severities = array(
		'0' => __('Notice'),
		'1' => __('Warning'),
		'2' => __('Critical')
	);

	$message_types = array(
		'messageb' => __('Begins with'),
		'messagec' => __('Contains'),
		'messagee' => __('Ends with'),
		'host'     => __('Hostname is'),
		'facility' => __('Facility is'),
		'sql'      => __('SQL Expression'));

	$syslog_freqs = array(
		'86400'  => __('Daily'), 
		'604800' => __('Weekly')
	);

	for ($i = 0; $i <= 86400; $i+=1800) {
		$minute = $i % 3600;
		if ($minute > 0) {
			$minute = '30';
		}else{
			$minute = '00';
		}

		if ($i > 0) {
			$hour = strrev(substr(strrev('00' . intval($i/3600)),0,2));
		}else{
			$hour = '00';
		}

		$syslog_times[$i] = $hour . ':' . $minute;
	}

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Import/Export')) {
			$menu2[__('Syslog Settings')]['plugins/syslog/syslog_alerts.php'] = __('Alert Rules');
			$menu2[__('Syslog Settings')]['plugins/syslog/syslog_removal.php'] = __('Removal Rules');
			$menu2[__('Syslog Settings')]['plugins/syslog/syslog_reports.php'] = __('Report Rules');
		}
	}
	$menu = $menu2;

	$menu_glyphs[__('Syslog Settings')] = 'fa fa-life-ring';

	if (isset($_SESSION['syslog_info']) && $_SESSION['syslog_info'] != '') {
		$messages['syslog_info'] = array('message' => $_SESSION['syslog_info'], 'type' => 'info');
	}

	if (isset($_SESSION['syslog_error']) && $_SESSION['syslog_error'] != '') {
		$messages['syslog_error'] = array('message' => $_SESSION['syslog_error'], 'type' => 'error');
	}
}

function syslog_draw_navigation_text ($nav) {
	global $config;

	$nav['syslog.php:']                = array('title' => __('Syslog'), 'mapping' => '', 'url' => $config['url_path'] . 'plugins/syslog/syslog.php', 'level' => '1');
	$nav['syslog_removal.php:']        = array('title' => __('Syslog Removals'), 'mapping' => 'index.php:', 'url' => $config['url_path'] . 'plugins/syslog/syslog_removal.php', 'level' => '1');
	$nav['syslog_removal.php:edit']    = array('title' => __('(Edit)'), 'mapping' => 'index.php:,syslog_removal.php:', 'url' => 'syslog_removal.php', 'level' => '2');
	$nav['syslog_removal.php:newedit'] = array('title' => __('(Edit)'), 'mapping' => 'index.php:,syslog_removal.php:', 'url' => 'syslog_removal.php', 'level' => '2');
	$nav['syslog_removal.php:actions'] = array('title' => __('(Actions)'), 'mapping' => 'index.php:,syslog_removal.php:', 'url' => 'syslog_removal.php', 'level' => '2');

	$nav['syslog_alerts.php:']         = array('title' => __('Syslog Alerts'), 'mapping' => 'index.php:', 'url' => $config['url_path'] . 'plugins/syslog/syslog_alerts.php', 'level' => '1');
	$nav['syslog_alerts.php:edit']     = array('title' => __('(Edit)'), 'mapping' => 'index.php:,syslog_alerts.php:', 'url' => 'syslog_alerts.php', 'level' => '2');
	$nav['syslog_alerts.php:newedit']  = array('title' => __('(Edit)'), 'mapping' => 'index.php:,syslog_alerts.php:', 'url' => 'syslog_alerts.php', 'level' => '2');
	$nav['syslog_alerts.php:actions']  = array('title' => __('(Actions)'), 'mapping' => 'index.php:,syslog_alerts.php:', 'url' => 'syslog_alerts.php', 'level' => '2');

	$nav['syslog_reports.php:']        = array('title' => __('Syslog Reports'), 'mapping' => 'index.php:', 'url' => $config['url_path'] . 'plugins/syslog/syslog_reports.php', 'level' => '1');
	$nav['syslog_reports.php:edit']    = array('title' => __('(Edit)'), 'mapping' => 'index.php:,syslog_reports.php:', 'url' => 'syslog_reports.php', 'level' => '2');
	$nav['syslog_reports.php:actions'] = array('title' => __('(Actions)'), 'mapping' => 'index.php:,syslog_reports.php:', 'url' => 'syslog_reports.php', 'level' => '2');
	$nav['syslog.php:actions']         = array('title' => __('Syslog'), 'mapping' => '', 'url' => $config['url_path'] . 'plugins/syslog/syslog.php', 'level' => '1');

	return $nav;
}

function syslog_config_insert() {
	syslog_connect();

	syslog_check_upgrade();
}

function syslog_graph_buttons($graph_elements = array()) {
	global $config, $timespan, $graph_timeshifts;

	include(dirname(__FILE__) . '/config.php');

	if (get_nfilter_request_var('action') == 'view') return;

	if (isset_request_var('graph_end') && strlen(get_filter_request_var('graph_end'))) {
		$date1 = date('Y-m-d H:i:s', get_filter_request_var('graph_start'));
		$date2 = date('Y-m-d H:i:s', get_filter_request_var('graph_end'));
	}else{
		$date1 = $timespan['current_value_date1'];
		$date2 = $timespan['current_value_date2'];
	}

	if (isset($graph_elements[1]['local_graph_id'])) {
		$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', array($graph_elements[1]['local_graph_id']));
		$sql_where   = '';

		if (!empty($host_id)) {
			$host  = db_fetch_row_prepared('SELECT id, description, hostname FROM host WHERE id = ?', array($host_id));

			if (sizeof($host)) {
				if (!is_ipaddress($host['description'])) {
					$parts = explode('.', $host['description']);
					$sql_where = 'WHERE host LIKE ' . db_qstr($parts[0] . '.%') . ' OR host = ' . db_qstr($host['description']);
				}else{
					$sql_where = 'WHERE host = ' . db_qstr($host['description']);
				}

				if (!is_ipaddress($host['hostname'])) {
					$parts = explode('.', $host['hostname']);
					$sql_where .= ($sql_where != '' ? ' OR ':'WHERE ') . 'host LIKE ' . db_qstr($parts[0] . '.%') . ' OR host = ' . db_qstr($host['hostname']);
				}else{
					$sql_where .= ($sql_where != '' ? ' OR ':'WHERE ') . 'host = ' . db_qstr($host['hostname']);
				}

				if ($sql_where != '') {
					$host_id = syslog_db_fetch_cell('SELECT host_id FROM syslog_hosts ' . $sql_where);

					if ($host_id) {
						print "<a class='iconLink' href='" . htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog.php?tab=syslog&reset=1&host=' . $host_id . '&date1=' . $date1 . '&date2=' . $date2) . "'><img src='" . $config['url_path'] . "plugins/syslog/images/view_syslog.png' border='0' alt='' title='" . __('Display Syslog in Range') . "'></a><br>";
					}
				}
			}
		}
	}
}

function syslog_utilities_action($action) {
	global $config, $colors, $refresh, $syslog_cnn;

	if ($action == 'purge_syslog_hosts') {
		$records = 0;

		syslog_db_execute('DELETE FROM syslog_hosts WHERE host_id NOT IN (SELECT DISTINCT host_id FROM syslog UNION SELECT DISTINCT host_id FROM syslog_removed)');
		$records += db_affected_rows($syslog_cnn);

		syslog_db_execute('DELETE FROM syslog_host_facilities WHERE host_id NOT IN (SELECT DISTINCT host_id FROM syslog UNION SELECT DISTINCT host_id FROM syslog_removed)');
		$records += db_affected_rows($syslog_cnn);

		syslog_db_execute('DELETE FROM syslog_statistics WHERE host_id NOT IN (SELECT DISTINCT host_id FROM syslog UNION SELECT DISTINCT host_id FROM syslog_removed)');
		$records += db_affected_rows($syslog_cnn);

		$_SESSION['syslog_info'] = "<b>There were $records host records removed from the Syslog database";

		raise_message('syslog_info');

		header('Location: utilities.php');
		exit;
	}

	return $action;
}

function syslog_utilities_list() {
	global $config, $colors;

	html_header(array(__('Syslog Utilities')), 2); ?>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='utilities.php?action=purge_syslog_hosts'><?php print __('Purge Syslog Devices');?></a>
		</td>
		<td class='textArea'>
			<?php print __('This menu pick provides a means to remove Devices that are no longer reporting into Cacti\'s syslog server.');?>
		</td>
	</tr>
	<?php
}

