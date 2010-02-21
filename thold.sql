DROP TABLE IF EXISTS `thold_data`;
CREATE TABLE `thold_data` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL default '',
  `rra_id` int(11) NOT NULL default '0',
  `data_id` int(11) NOT NULL default '0',
  `graph_id` int(11) NOT NULL default '0',
  `graph_template` int(11) NOT NULL default '0',
  `data_template` int(11) NOT NULL default '0',
  `rrd_step` mediumint(6) NOT NULL default '0',
  `data_source_name` varchar(19) NOT NULL default '',
  `data_source_type_id` smallint(5) NOT NULL default '0',
  `thold_hi` varchar(100) default NULL,
  `thold_low` varchar(100) default NULL,
  `thold_fail_count` int(11) NOT NULL default '0',
  `thold_alert` int(1) NOT NULL default '0',
  `thold_enabled` enum('on','off') NOT NULL default 'on',
  `bl_enabled` enum('on','off') NOT NULL default 'off',
  `bl_ref_time` int(50) unsigned default NULL,
  `bl_ref_time_range` int(10) unsigned default NULL,
  `bl_pct_down` int(10) unsigned default NULL,
  `bl_pct_up` int(10) unsigned default NULL,
  `bl_fail_trigger` int(10) unsigned default NULL,
  `bl_fail_count` int(11) unsigned default NULL,
  `bl_alert` int(2) NOT NULL default '0',
  `lastread` varchar(100) default NULL,
  `oldvalue` varchar(100) default NULL,
  `host_id` int(10) default NULL,
  `syslog_priority` int(2) default '3',
  `data_type` int(3) NOT NULL default '0',
  `cdef` int(11) NOT NULL default '0',
  `percent_ds` varchar(64) NOT NULL,
  `expression` varchar(70) NOT NULL default '',
  `template` int(11) NOT NULL default '0',
  `template_enabled` char(3) NOT NULL default '',
  `tcheck` int(1) NOT NULL default '0',
  `exempt` char(3) NOT NULL default 'off',
  PRIMARY KEY  (`id`),
  KEY `host_id` (`host_id`),
  KEY `rra_id` (`rra_id`),
  KEY `data_id` (`data_id`),
  KEY `graph_id` (`graph_id`),
  KEY `graph_template` (`graph_template`),
  KEY `data_template` (`data_template`),
  KEY `template` (`template`),
  KEY `template_enabled` (`template_enabled`),
  KEY `thold_enabled` (`thold_enabled`)
) TYPE=MyISAM;

DROP TABLE IF EXISTS `thold_template`;
CREATE TABLE thold_template (
  id int(11) NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  data_template_id int(32) NOT NULL default '0',
  data_template_name varchar(100) NOT NULL default '',
  data_source_id int(10) NOT NULL default '0',
  data_source_name varchar(100) NOT NULL default '',
  data_source_friendly varchar(100) NOT NULL default '',
  thold_hi varchar(100) default NULL,
  thold_low varchar(100) default NULL,
  thold_enabled enum('on','off') NOT NULL default 'on',
  bl_enabled enum('on','off') NOT NULL default 'off',
  bl_ref_time int(50) default NULL,
  bl_ref_time_range int(10) default NULL,
  bl_pct_down int(10) default NULL,
  bl_pct_up int(10) default NULL,
  bl_fail_trigger int(10) default NULL,
  bl_alert int(2) default NULL,
  data_type int(3) NOT NULL default '0',
  cdef int(11) NOT NULL default '0',
  percent_ds varchar(64) NOT NULL,
  expression varchar(70) NOT NULL default '',
  exempt char(3) NOT NULL default 'off',
  PRIMARY KEY  (id),
  KEY `data_template_id` (`data_template_id`),
  KEY `data_source_id` (`data_source_id`)
) TYPE=MyISAM COMMENT='Table of thresholds defaults for graphs';

DROP TABLE IF EXISTS `plugin_thold_alerts`;
CREATE TABLE IF NOT EXISTS `plugin_thold_alerts` (
  `id` int(12) NOT NULL auto_increment,
  `threshold_id` int(12) NOT NULL,
  `repeat_fail` int(12) NOT NULL,
  `repeat_alert` int(12) NOT NULL,
  `restored_alert` char(3) NOT NULL default 'off',
  `type` varchar(64) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `threshold_id` (`threshold_id`),
  KEY `repeat_fail` (`repeat_fail`),
  KEY `repeat_alert` (`repeat_alert`)
) TYPE=MyISAM COMMENT='Table of Tholds Alerts';

DROP TABLE IF EXISTS `plugin_thold_template_alerts`;
CREATE TABLE IF NOT EXISTS `plugin_thold_template_alerts` (
  `id` int(12) NOT NULL auto_increment,
  `template_id` int(12) NOT NULL,
  `repeat_fail` int(12) NOT NULL,
  `repeat_alert` int(12) NOT NULL,
  `restored_alert` char(3) NOT NULL default 'off',
  `type` varchar(64) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `template_id` (`template_id`),
  KEY `repeat_fail` (`repeat_fail`),
  KEY `repeat_alert` (`repeat_alert`)
) TYPE=MyISAM COMMENT='Table of Tholds Template Alerts';

DROP TABLE IF EXISTS `plugin_thold_contacts`;
CREATE TABLE plugin_thold_contacts (
  `id` int(12) NOT NULL auto_increment,
  `user_id` int(12) NOT NULL,
  `type` varchar(32) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `type` (`type`),
  KEY `user_id` (`user_id`)
) TYPE=MyISAM;

CREATE TABLE `plugin_thold_log` (
  `id` int(12) NOT NULL auto_increment,
  `time` int(24) NOT NULL,
  `host_id` int(10) NOT NULL,
  `graph_id` int(10) NOT NULL,
  `threshold_id` int(10) NOT NULL,
  `threshold_value` varchar(64) NOT NULL,
  `current` varchar(64) NOT NULL,
  `status` int(5) NOT NULL,
  `type` int(5) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `time` (`time`),
  KEY `host_id` (`host_id`),
  KEY `graph_id` (`graph_id`),
  KEY `threshold_id` (`threshold_id`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=MyISAM COMMENT='Table of All Threshold Breaches';

REPLACE INTO settings VALUES ('alert_bl_past_default',86400);
REPLACE INTO settings VALUES ('alert_bl_timerange_def',10800);
REPLACE INTO settings VALUES ('alert_bl_percent_def',20);
REPLACE INTO settings VALUES ('alert_bl_trigger',3);
