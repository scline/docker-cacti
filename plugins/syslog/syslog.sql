--
-- Table structure for table `syslog`
--

DROP TABLE IF EXISTS `syslog`;
CREATE TABLE `syslog` (
  `facility_id` int(10) unsigned DEFAULT NULL,
  `priority_id` int(10) unsigned DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `host_id` int(10) unsigned DEFAULT NULL,
  `logtime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `message` text NOT NULL,
  `seq` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  KEY `seq` (`seq`),
  KEY `logtime` (`logtime`),
  KEY `program_id` (`program_id`),
  KEY `host_id` (`host_id`),
  KEY `priority_id` (`priority_id`),
  KEY `facility_id` (`facility_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_alert`
--

DROP TABLE IF EXISTS `syslog_alert`;
CREATE TABLE `syslog_alert` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `severity` int(10) unsigned NOT NULL DEFAULT '0',
  `method` int(10) unsigned NOT NULL DEFAULT '0',
  `num` int(10) unsigned NOT NULL DEFAULT '1',
  `type` varchar(16) NOT NULL DEFAULT '',
  `enabled` char(2) DEFAULT 'on',
  `repeat_alert` int(10) unsigned NOT NULL DEFAULT '0',
  `open_ticket` char(2) DEFAULT '',
  `message` varchar(128) NOT NULL DEFAULT '',
  `user` varchar(32) NOT NULL DEFAULT '',
  `date` int(16) NOT NULL DEFAULT '0',
  `email` varchar(255) DEFAULT NULL,
  `command` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_facilities`
--

DROP TABLE IF EXISTS `syslog_facilities`;
CREATE TABLE `syslog_facilities` (
  `facility_id` int(10) unsigned NOT NULL,
  `facility` varchar(10) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`facility_id`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB;

--
-- Dumping data for table `syslog_facilities`
--

INSERT INTO `syslog_facilities` VALUES (0,'kern','2016-05-20 21:46:10'),(1,'user','2016-05-20 21:46:10'),(2,'mail','2016-05-20 21:46:10'),(3,'daemon','2016-05-20 21:46:10'),(4,'auth','2016-05-20 21:46:10'),(5,'syslog','2016-05-20 21:46:10'),(6,'lpd','2016-05-20 21:46:10'),(7,'news','2016-05-20 21:46:10'),(8,'uucp','2016-05-20 21:46:10'),(9,'crond','2016-05-20 21:46:10'),(10,'authpriv','2016-05-20 21:46:10'),(11,'ftpd','2016-05-20 21:46:10'),(12,'ntpd','2016-05-20 21:46:10'),(13,'logaudit','2016-05-20 21:46:10'),(14,'logalert','2016-05-20 21:46:10'),(15,'crond','2016-05-20 21:46:10'),(16,'local0','2016-05-20 21:46:10'),(17,'local1','2016-05-20 21:46:10'),(18,'local2','2016-05-20 21:46:10'),(19,'local3','2016-05-20 21:46:10'),(20,'local4','2016-05-20 21:46:10'),(21,'local5','2016-05-20 21:46:10'),(22,'local6','2016-05-20 21:46:10'),(23,'local7','2016-05-20 21:46:10');

--
-- Table structure for table `syslog_host_facilities`
--

DROP TABLE IF EXISTS `syslog_host_facilities`;
CREATE TABLE `syslog_host_facilities` (
  `host_id` int(10) unsigned NOT NULL,
  `facility_id` int(10) unsigned NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host_id`,`facility_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_hosts`
--

DROP TABLE IF EXISTS `syslog_hosts`;
CREATE TABLE `syslog_hosts` (
  `host_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(64) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host`),
  KEY `host_id` (`host_id`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB COMMENT='Contains all hosts currently in the syslog table';

--
-- Table structure for table `syslog_incoming`
--

DROP TABLE IF EXISTS `syslog_incoming`;
CREATE TABLE `syslog_incoming` (
  `facility_id` int(10) unsigned DEFAULT NULL,
  `priority_id` int(10) unsigned DEFAULT NULL,
  `program` varchar(40) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `host` varchar(64) DEFAULT NULL,
  `message` varchar(1024) NOT NULL DEFAULT '',
  `seq` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`seq`),
  KEY `program` (`program`),
  KEY `status` (`status`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_logs`
--

DROP TABLE IF EXISTS `syslog_logs`;
CREATE TABLE `syslog_logs` (
  `alert_id` int(10) unsigned NOT NULL DEFAULT '0',
  `logseq` bigint(20) unsigned NOT NULL,
  `logtime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logmsg` varchar(1024) DEFAULT NULL,
  `host` varchar(64) DEFAULT NULL,
  `facility_id` int(10) unsigned DEFAULT NULL,
  `priority_id` int(10) unsigned DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  `html` blob,
  `seq` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`seq`),
  KEY `logseq` (`logseq`),
  KEY `program_id` (`program_id`),
  KEY `alert_id` (`alert_id`),
  KEY `host` (`host`),
  KEY `seq` (`seq`),
  KEY `logtime` (`logtime`),
  KEY `priority_id` (`priority_id`),
  KEY `facility_id` (`facility_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_priorities`
--

DROP TABLE IF EXISTS `syslog_priorities`;
CREATE TABLE `syslog_priorities` (
  `priority_id` int(10) unsigned NOT NULL,
  `priority` varchar(10) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`priority_id`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB;

--
-- Dumping data for table `syslog_priorities`
--

INSERT INTO `syslog_priorities` VALUES (0,'emerg','2016-05-20 21:46:10'),(1,'alert','2016-05-20 21:46:10'),(2,'crit','2016-05-20 21:46:10'),(3,'err','2016-05-20 21:46:10'),(4,'warning','2016-05-20 21:46:10'),(5,'notice','2016-05-20 21:46:10'),(6,'info','2016-05-20 21:46:10'),(7,'debug','2016-05-20 21:46:10'),(8,'other','2016-05-20 21:46:10');

--
-- Table structure for table `syslog_programs`
--

DROP TABLE IF EXISTS `syslog_programs`;
CREATE TABLE `syslog_programs` (
  `program_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program` varchar(40) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`program`),
  KEY `host_id` (`program_id`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB COMMENT='Contains all programs currently in the syslog table';

--
-- Table structure for table `syslog_remove`
--

DROP TABLE IF EXISTS `syslog_remove`;
CREATE TABLE `syslog_remove` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(16) NOT NULL DEFAULT '',
  `enabled` char(2) DEFAULT 'on',
  `method` char(5) DEFAULT 'del',
  `message` varchar(128) NOT NULL DEFAULT '',
  `user` varchar(32) NOT NULL DEFAULT '',
  `date` int(16) NOT NULL DEFAULT '0',
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_removed`
--

DROP TABLE IF EXISTS `syslog_removed`;
CREATE TABLE `syslog_removed` (
  `facility_id` int(10) unsigned DEFAULT NULL,
  `priority_id` int(10) unsigned DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `host_id` int(10) unsigned DEFAULT NULL,
  `logtime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `message` text NOT NULL,
  `seq` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  KEY `seq` (`seq`),
  KEY `logtime` (`logtime`),
  KEY `program_id` (`program_id`),
  KEY `host_id` (`host_id`),
  KEY `priority_id` (`priority_id`),
  KEY `facility_id` (`facility_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_reports`
--

DROP TABLE IF EXISTS `syslog_reports`;
CREATE TABLE `syslog_reports` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(16) NOT NULL DEFAULT '',
  `enabled` char(2) DEFAULT 'on',
  `timespan` int(16) NOT NULL DEFAULT '0',
  `timepart` int(5) NOT NULL DEFAULT '0',
  `lastsent` int(16) NOT NULL DEFAULT '0',
  `body` varchar(1024) DEFAULT NULL,
  `message` varchar(128) DEFAULT NULL,
  `user` varchar(32) NOT NULL DEFAULT '',
  `date` int(16) NOT NULL DEFAULT '0',
  `email` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `syslog_statistics`
--

DROP TABLE IF EXISTS `syslog_statistics`;
CREATE TABLE `syslog_statistics` (
  `id` bigint unsigned auto_increment,
  `host_id` int(10) unsigned NOT NULL,
  `facility_id` int(10) unsigned NOT NULL,
  `priority_id` int(10) unsigned NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `insert_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `records` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pk` (`host_id`,`facility_id`,`priority_id`,`program_id`,`insert_time`),
  KEY `host_id` (`host_id`),
  KEY `facility_id` (`facility_id`),
  KEY `priority_id` (`priority_id`),
  KEY `program_id` (`program_id`),
  KEY `insert_time` (`insert_time`)
) ENGINE=InnoDB COMMENT='Maintains High Level Statistics';

