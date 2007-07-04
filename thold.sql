DROP TABLE IF EXISTS `thold_data`;
CREATE TABLE `thold_data` (
  `id` int(11) NOT NULL auto_increment,
  `rra_id` int(11) NOT NULL default '0',
  `data_id` int(11) NOT NULL default '0',
  `thold_hi` varchar(100) default NULL,
  `thold_low` varchar(100) default NULL,
  `thold_fail_trigger` int(10) unsigned default NULL,
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
  `oldvalue` varchar(100) NOT NULL default '',
  `repeat_alert` int(10) unsigned default NULL,
  `notify_extra` varchar(255) default NULL,
  `host_id` int(10) default NULL,
  `syslog_priority` int(2) default '3',
  `cdef` int(11) NOT NULL default '0',
  `template` int(11) NOT NULL default '0',
  `template_enabled` char(3) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `rra_id` (`rra_id`),
  KEY `template` (`template`),
  KEY `template_enabled` (`template_enabled`),
  KEY `data_id` (`data_id`),
  KEY `thold_enabled` (`thold_enabled`)
) TYPE=MyISAM;

DROP TABLE IF EXISTS `thold_template`;
CREATE TABLE thold_template (
  id int(11) NOT NULL auto_increment,
  data_template_id int(32) NOT NULL default '0',
  data_template_name varchar(100) NOT NULL default '',
  data_source_id int(10) NOT NULL default '0',
  data_source_name varchar(100) NOT NULL default '',
  data_source_friendly varchar(100) NOT NULL default '',
  thold_hi varchar(100) default NULL,
  thold_low varchar(100) default NULL,
  thold_fail_trigger int(10) default '1',
  thold_enabled enum('on','off') NOT NULL default 'on',
  bl_enabled enum('on','off') NOT NULL default 'off',
  bl_ref_time int(50) default NULL,
  bl_ref_time_range int(10) default NULL,
  bl_pct_down int(10) default NULL,
  bl_pct_up int(10) default NULL,
  bl_fail_trigger int(10) default NULL,
  bl_alert int(2) default NULL,
  repeat_alert int(10) NOT NULL default '12',
  notify_extra varchar(255) NOT NULL default '',
  cdef int(11) NOT NULL default '0',
  UNIQUE KEY data_source_id (data_source_id),
  KEY id (id)
) TYPE=MyISAM COMMENT='Table of thresholds defaults for graphs';

DROP TABLE IF EXISTS `plugin_thold_template_contact`;
CREATE TABLE plugin_thold_template_contact (
  template_id int(12) NOT NULL,
  contact_id int(12) NOT NULL,
  KEY template_id (template_id),
  KEY contact_id (contact_id)
) TYPE=MyISAM COMMENT='Table of Tholds Template Contacts';

DROP TABLE IF EXISTS `plugin_thold_threshold_contact`;
CREATE TABLE plugin_thold_threshold_contact (
  thold_id int(12) NOT NULL,
  contact_id int(12) NOT NULL,
  KEY thold_id (thold_id),
  KEY contact_id (contact_id)
) TYPE=MyISAM COMMENT='Table of Tholds Threshold Contacts';

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

REPLACE INTO user_auth_realm VALUES (18, 1);
REPLACE INTO user_auth_realm VALUES (19, 1);

REPLACE INTO settings VALUES ('alert_bl_past_default',86400);
REPLACE INTO settings VALUES ('alert_bl_timerange_def',10800);
REPLACE INTO settings VALUES ('alert_bl_percent_def',20);
REPLACE INTO settings VALUES ('alert_bl_trigger',3);
