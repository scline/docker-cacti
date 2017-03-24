
--
-- Alter statements for the `host` table
--

ALTER TABLE `host` ADD COLUMN `thold_send_email` int(10) NOT NULL DEFAULT '1' AFTER `disabled`;
ALTER TABLE `host` ADD COLUMN `thold_host_email` int(10) unsigned DEFAULT NULL AFTER `thold_send_email`;

--
-- Table structure for table `plugin_notification_lists`
--

CREATE TABLE `plugin_notification_lists` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `description` varchar(512) NOT NULL,
  `emails` varchar(512) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COMMENT='Table of Notification Lists';

--
-- Table structure for table `plugin_thold_contacts`
--

CREATE TABLE `plugin_thold_contacts` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `user_id` int(12) NOT NULL,
  `type` varchar(32) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_type` (`user_id`,`type`),
  KEY `type` (`type`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COMMENT='Table of threshold contacts';

--
-- Table structure for table `plugin_thold_daemon_data`
--

CREATE TABLE `plugin_thold_daemon_data` (
  `id` int(11) NOT NULL,
  `pid` varchar(25) NOT NULL,
  `rrd_reindexed` varchar(600) NOT NULL,
  `rrd_time_reindexed` int(10) unsigned NOT NULL,
  KEY `id` (`id`,`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table of Poller Outdata needed for queued daemon processes';

--
-- Table structure for table `plugin_thold_daemon_processes`
--

CREATE TABLE `plugin_thold_daemon_processes` (
  `pid` varchar(25) NOT NULL,
  `start` int(10) unsigned NOT NULL DEFAULT '0',
  `end` int(10) unsigned NOT NULL DEFAULT '0',
  `processed_items` mediumint(8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table of Thold Daemon Processes being queued';

--
-- Table structure for table `plugin_thold_host_failed`
--

CREATE TABLE `plugin_thold_host_failed` (
  `id` int(12) unsigned NOT NULL AUTO_INCREMENT,
  `host_id` int(12) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COMMENT='Table of Hosts in a Down State';

--
-- Table structure for table `plugin_thold_host_template`
--

CREATE TABLE `plugin_thold_host_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `host_template_id` int(11) unsigned NOT NULL DEFAULT '0',
  `thold_template_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Table of Device Template Threshold Templates';

--
-- Table structure for table `plugin_thold_log`
--

CREATE TABLE `plugin_thold_log` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `time` int(24) NOT NULL,
  `host_id` int(10) NOT NULL,
  `local_graph_id` int(11) unsigned NOT NULL DEFAULT '0',
  `threshold_id` int(10) NOT NULL,
  `threshold_value` varchar(64) NOT NULL,
  `current` varchar(64) NOT NULL,
  `status` int(5) NOT NULL,
  `type` int(5) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `host_id` (`host_id`),
  KEY `graph_id` (`local_graph_id`),
  KEY `threshold_id` (`threshold_id`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=6723 DEFAULT CHARSET=latin1 COMMENT='Table of All Threshold Breaches';

--
-- Table structure for table `plugin_thold_template_contact`
--

CREATE TABLE `plugin_thold_template_contact` (
  `template_id` int(12) NOT NULL,
  `contact_id` int(12) NOT NULL,
  KEY `template_id` (`template_id`),
  KEY `contact_id` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table of Tholds Template Contacts';

--
-- Table structure for table `plugin_thold_threshold_contact`
--

CREATE TABLE `plugin_thold_threshold_contact` (
  `thold_id` int(12) NOT NULL,
  `contact_id` int(12) NOT NULL,
  KEY `thold_id` (`thold_id`),
  KEY `contact_id` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Table of Tholds Threshold Contacts';

--
-- Table structure for table `thold_data`
--

CREATE TABLE `thold_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `local_data_id` int(11) unsigned NOT NULL DEFAULT '0',
  `data_template_rrd_id` int(11) unsigned NOT NULL DEFAULT '0',
  `local_graph_id` int(11) unsigned NOT NULL DEFAULT '0',
  `graph_template_id` int(11) unsigned NOT NULL DEFAULT '0',
  `data_template_id` int(11) unsigned NOT NULL DEFAULT '0',
  `thold_hi` varchar(100) DEFAULT NULL,
  `thold_low` varchar(100) DEFAULT NULL,
  `thold_fail_trigger` int(10) unsigned DEFAULT NULL,
  `thold_fail_count` int(11) NOT NULL DEFAULT '0',
  `time_hi` varchar(100) DEFAULT NULL,
  `time_low` varchar(100) DEFAULT NULL,
  `time_fail_trigger` int(12) NOT NULL DEFAULT '1',
  `time_fail_length` int(12) NOT NULL DEFAULT '1',
  `thold_warning_hi` varchar(100) DEFAULT NULL,
  `thold_warning_low` varchar(100) DEFAULT NULL,
  `thold_warning_fail_trigger` int(10) unsigned DEFAULT NULL,
  `thold_warning_fail_count` int(11) NOT NULL DEFAULT '0',
  `time_warning_hi` varchar(100) DEFAULT NULL,
  `time_warning_low` varchar(100) DEFAULT NULL,
  `time_warning_fail_trigger` int(12) NOT NULL DEFAULT '1',
  `time_warning_fail_length` int(12) NOT NULL DEFAULT '1',
  `thold_alert` int(1) NOT NULL DEFAULT '0',
  `thold_enabled` enum('on','off') NOT NULL DEFAULT 'on',
  `thold_type` int(3) NOT NULL DEFAULT '0',
  `bl_ref_time_range` int(10) unsigned DEFAULT NULL,
  `bl_pct_down` varchar(100) DEFAULT NULL,
  `bl_pct_up` varchar(100) DEFAULT NULL,
  `bl_fail_trigger` int(10) unsigned DEFAULT NULL,
  `bl_fail_count` int(11) unsigned DEFAULT NULL,
  `bl_alert` int(2) NOT NULL DEFAULT '0',
  `lastread` varchar(100) DEFAULT NULL,
  `lasttime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `oldvalue` varchar(100) DEFAULT NULL,
  `repeat_alert` int(10) unsigned DEFAULT NULL,
  `notify_default` enum('on','off') DEFAULT NULL,
  `notify_extra` varchar(512) DEFAULT NULL,
  `notify_warning_extra` varchar(512) DEFAULT NULL,
  `notify_warning` int(10) unsigned DEFAULT NULL,
  `notify_alert` int(10) unsigned DEFAULT NULL,
  `host_id` int(10) DEFAULT NULL,
  `syslog_priority` int(2) NOT NULL DEFAULT '3',
  `data_type` int(12) NOT NULL DEFAULT '0',
  `cdef` int(11) NOT NULL DEFAULT '0',
  `percent_ds` varchar(64) NOT NULL DEFAULT '',
  `expression` varchar(70) NOT NULL DEFAULT '',
  `thold_template_id` int(11) unsigned NOT NULL DEFAULT '0',
  `template_enabled` char(3) NOT NULL DEFAULT '',
  `tcheck` int(1) NOT NULL DEFAULT '0',
  `exempt` char(3) NOT NULL DEFAULT 'off',
  `restored_alert` char(3) NOT NULL DEFAULT 'off',
  `bl_thold_valid` int(10) unsigned NOT NULL DEFAULT '0',
  `snmp_event_category` varchar(255) DEFAULT NULL,
  `snmp_event_severity` tinyint(1) NOT NULL DEFAULT '3',
  `snmp_event_warning_severity` tinyint(1) NOT NULL DEFAULT '2',
  `thold_daemon_pid` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `host_id` (`host_id`),
  KEY `rra_id` (`local_data_id`),
  KEY `data_id` (`data_template_rrd_id`),
  KEY `graph_id` (`local_graph_id`),
  KEY `template` (`thold_template_id`),
  KEY `thold_enabled` (`thold_enabled`),
  KEY `template_enabled` (`template_enabled`),
  KEY `tcheck` (`tcheck`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=latin1 COMMENT='Threshold data';

--
-- Table structure for table `thold_template`
--

CREATE TABLE `thold_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `data_template_id` int(10) NOT NULL DEFAULT '0',
  `data_template_name` varchar(100) NOT NULL DEFAULT '',
  `data_source_id` int(10) NOT NULL DEFAULT '0',
  `data_source_name` varchar(100) NOT NULL DEFAULT '',
  `data_source_friendly` varchar(100) NOT NULL DEFAULT '',
  `thold_hi` varchar(100) DEFAULT NULL,
  `thold_low` varchar(100) DEFAULT NULL,
  `thold_fail_trigger` int(10) unsigned DEFAULT NULL,
  `time_hi` varchar(100) DEFAULT NULL,
  `time_low` varchar(100) DEFAULT NULL,
  `time_fail_trigger` int(12) NOT NULL DEFAULT '1',
  `time_fail_length` int(12) NOT NULL DEFAULT '1',
  `thold_warning_hi` varchar(100) DEFAULT NULL,
  `thold_warning_low` varchar(100) DEFAULT NULL,
  `thold_warning_fail_trigger` int(10) unsigned DEFAULT NULL,
  `thold_warning_fail_count` int(11) NOT NULL DEFAULT '0',
  `time_warning_hi` varchar(100) DEFAULT NULL,
  `time_warning_low` varchar(100) DEFAULT NULL,
  `time_warning_fail_trigger` int(12) NOT NULL DEFAULT '1',
  `time_warning_fail_length` int(12) NOT NULL DEFAULT '1',
  `thold_enabled` enum('on','off') NOT NULL DEFAULT 'on',
  `thold_type` int(3) NOT NULL DEFAULT '0',
  `bl_ref_time_range` int(10) unsigned DEFAULT NULL,
  `bl_pct_down` varchar(100) DEFAULT NULL,
  `bl_pct_up` varchar(100) DEFAULT NULL,
  `bl_fail_trigger` int(10) unsigned DEFAULT NULL,
  `bl_fail_count` int(11) unsigned DEFAULT NULL,
  `bl_alert` int(2) NOT NULL DEFAULT '0',
  `repeat_alert` int(10) unsigned DEFAULT NULL,
  `notify_default` enum('on','off') DEFAULT NULL,
  `notify_extra` varchar(512) DEFAULT NULL,
  `notify_warning_extra` varchar(512) DEFAULT NULL,
  `notify_warning` int(10) unsigned DEFAULT NULL,
  `notify_alert` int(10) unsigned DEFAULT NULL,
  `data_type` int(12) NOT NULL DEFAULT '0',
  `cdef` int(11) NOT NULL DEFAULT '0',
  `percent_ds` varchar(64) NOT NULL DEFAULT '',
  `expression` varchar(70) NOT NULL DEFAULT '',
  `exempt` char(3) NOT NULL DEFAULT 'off',
  `restored_alert` char(3) NOT NULL DEFAULT 'off',
  `snmp_event_category` varchar(255) DEFAULT NULL,
  `snmp_event_severity` tinyint(1) NOT NULL DEFAULT '3',
  `snmp_event_warning_severity` tinyint(1) NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `data_source_id` (`data_source_id`),
  KEY `data_template_id` (`data_template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1 COMMENT='Table of thresholds defaults for graphs';

