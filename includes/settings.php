<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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

function thold_draw_navigation_text ($nav) {
	$nav['thold.php:'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'thold.php', 'level' => '1');
	$nav['thold.php:save'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'thold.php', 'level' => '1');
	$nav['thold.php:autocreate'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'thold.php', 'level' => '1');
	$nav['listthold.php:'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'listthold.php', 'level' => '1');
	$nav['listthold.php:actions'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'listthold.php', 'level' => '1');
	$nav['thold_graph.php:'] = array('title' => 'Thresholds', 'mapping' => 'index.php:', 'url' => 'thold_graph.php', 'level' => '1');
	$nav['thold_view_failures.php:'] = array('title' => 'Thresholds - Failures', 'mapping' => 'index.php:', 'url' => 'thold_view_failures.php', 'level' => '1');
	$nav['thold_view_normal.php:'] = array('title' => 'Thresholds - Normal', 'mapping' => 'index.php:', 'url' => 'thold_view_normal.php', 'level' => '1');
	$nav['thold_view_recover.php:'] = array('title' => 'Thresholds - Recovering', 'mapping' => 'index.php:', 'url' => 'thold_view_recover.php', 'level' => '1');
	$nav['thold_view_recent.php:'] = array('title' => 'Recent Thresholds', 'mapping' => 'index.php:', 'url' => 'thold_view_recent.php', 'level' => '1');
	$nav['thold_view_host.php:'] = array('title' => 'Recent Host Failures', 'mapping' => 'index.php:', 'url' => 'thold_view_host.php', 'level' => '1');

	$nav['thold_templates.php:'] = array('title' => 'Threshold Templates', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:edit'] = array('title' => 'Threshold Templates', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:save'] = array('title' => 'Threshold Templates', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:add'] = array('title' => 'Threshold Templates', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:actions'] = array('title' => 'Threshold Templates', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:import'] = array('title' => 'Threshold Template Import', 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '2');

	$nav['thold_add.php:'] = array('title' => 'Create Threshold', 'mapping' => 'index.php:', 'url' => 'thold_add.php', 'level' => '1');
	$nav['notify_lists.php:'] = array('title' => 'Notification Lists', 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:edit'] = array('title' => 'Notification Lists (edit)', 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:save'] = array('title' => 'Notification Lists', 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:actions'] = array('title' => 'Notification Lists', 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');

	return $nav;
}

function thold_config_insert () {
	global $menu;

	$menu['Management']['plugins/thold/notify_lists.php'] = 'Notification Lists';
	$menu['Management']['plugins/thold/listthold.php'] = 'Thresholds';
	$menu['Templates']['plugins/thold/thold_templates.php'] = 'Threshold Templates';
	if (isset($_GET['thold_vrule'])) {
		if ($_GET['thold_vrule'] == 'on') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = 'on';
			$_SESSION['sess_config_array']['boost_png_cache_enable'] = false;
		} elseif ($_GET['thold_vrule'] == 'off') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = 'off';
		}
	}

	define("ST_RESTORAL", 0); // Restoral
	define("ST_TRIGGERA", 1); // Trigger Alarm
	define("ST_NOTIFYRA", 2); // Notify Alarm Retrigger
	define("ST_NOTIFYWA", 3); // Notify Warning
	define("ST_NOTIFYAL", 4); // Notify Alarm
	define("ST_NOTIFYRS", 5); // Notify Restoral
	define("ST_TRIGGERW", 6); // Trigger Warning
	define("ST_NOTIFYAW", 7); // Notify Restoral to Warning

	define("STAT_HI",     2);
	define("STAT_LO",     1);
	define("STAT_NORMAL", 0);
}

function thold_config_arrays () {
	global $messages;
	$messages['thold_save'] = array(
		'message' => 'A template with that Data Source already exists!',
		'type' => 'error');
	if (isset($_SESSION['thold_message']) && $_SESSION['thold_message'] != '') {
		$messages['thold_created'] = array('message' => $_SESSION['thold_message'], 'type' => 'info');
	}
}

function thold_config_form () {
	global $fields_host_edit;
	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['thold_send_email'] = array(
				'method' => 'drop_array',
				'array' =>  array('0' => 'Disabled', '1' => 'Global List', '2' => 'List Below', '3' => 'Global and List Below'),
				'friendly_name' => 'Thold Up/Down Email Notification',
				'description' => 'Which Notification List(s) of should be notified about Host Up/Down events?',
				'value' => '|arg1:thold_send_email|',
				'on_change' => 'changeNotify()',
				'default' => '0',
				'form_id' => false
			);
			$fields_host_edit3['thold_host_email'] = array(
				'friendly_name' => 'Notification List',
				'description' => 'Additional Email address, separated by commas for multi Emails.',
				'method' => 'drop_sql',
				'sql' => "SELECT id,name FROM plugin_notification_lists ORDER BY name",
				'value' => '|arg1:thold_host_email|',
				'default' => '',
				'none_value' => 'None'
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function thold_config_settings () {
	global $tabs, $settings, $item_rows, $config;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php') return;

	include_once("./plugins/thold/thold_functions.php");

	if ($config["cacti_server_os"] == "unix") {
		$syslog_facil_array = array(LOG_AUTH => 'Auth', LOG_AUTHPRIV => 'Auth Private', LOG_CRON => 'Cron', LOG_DAEMON => 'Daemon', LOG_KERN => 'Kernel', LOG_LOCAL0 => 'Local 0', LOG_LOCAL1 => 'Local 1', LOG_LOCAL2 => 'Local 2', LOG_LOCAL3 => 'Local 3', LOG_LOCAL4 => 'Local 4', LOG_LOCAL5 => 'Local 5', LOG_LOCAL6 => 'Local 6', LOG_LOCAL7 => 'Local 7', LOG_LPR => 'LPR', LOG_MAIL => 'Mail', LOG_NEWS => 'News', LOG_SYSLOG => 'Syslog', LOG_USER => 'User', LOG_UUCP => 'UUCP');
		$default_facility = LOG_DAEMON;
	} else {
		$syslog_facil_array = array(LOG_USER => 'User');
		$default_facility = LOG_USER;
	}

	$tabs['alerts'] = 'Thresholds';
	$settings['alerts'] = array(
		'general_header' => array(
			'friendly_name' => 'General',
			'method' => 'spacer',
			),
		'thold_disable_all' => array(
			'friendly_name' => 'Disable All Thresholds',
			'description' => 'Checking this box will disable Alerting on all Thresholds.  This can be used when it is necessary to perform maintenance on your network.',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_disable_legacy' => array(
			'friendly_name' => 'Disable Legacy Notifications',
			'description' => 'Checking this box will disable Legacy Alerting on all Thresholds.  Legacy Alerting is defined as any Specific Email Alerts not associated with a Notification List.',
			'method' => 'checkbox',
			'default' => ''
			),
		'thold_filter_default' => array(
			'friendly_name' => 'Default Status',
			'description' => 'Default Threshold Filter Status',
			'method' => 'drop_array',
			'array' => array("-1" => "Any", "0" => "Disabled", "2" => "Enabled", "1" => "Breached", "3" => "Triggered"),
			'default' => 20
			),
		'alert_base_url' => array(
			'friendly_name' => 'Base URL',
			'description' => 'Cacti base URL',
			'method' => 'textbox',
			// Set the default only if called from 'settings.php'
			'default' => ((isset($_SERVER['HTTP_HOST']) && isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) == 'settings.php') ? ('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/') : ''),
			'max_length' => 255,
			),
		'alert_num_rows' => array(
			'friendly_name' => 'Thresholds Per Page',
			'description' => 'Number of thresholds to display per page',
			'method' => 'drop_array',
			'array' => $item_rows,
			'default' => 20
			),
		'thold_log_cacti' => array(
			'friendly_name' => 'Log Threshold Breaches',
			'description' => 'Enable logging of all Threshold failures to the Cacti Log',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_log_changes' => array(
			'friendly_name' => 'Log Threshold Changes',
			'description' => 'Enable logging of all Threshold changes to the Cacti Log',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages with Thold',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_log_storage' => array(
			'friendly_name' => 'Store Log for x days',
			'description' => 'Keep the database threshold logs for this number of days.',
			'method' => 'textbox',
			'default' => '31',
			'size' => '8',
			'max_length' => 255,
			),
		'thold_alerting_header' => array(
			'friendly_name' => 'Default Alerting Options',
			'method' => 'spacer',
			),
		'alert_exempt' => array(
			'friendly_name' => 'Weekend exemptions',
			'description' => 'If this is checked, thold will not run on weekends.',
			'method' => 'checkbox',
			),
		'alert_trigger' => array(
			'friendly_name' => 'Default Trigger Count',
			'description' => 'Default number of consecutive times the Data Source must be in breach of the Threshold for an Alert to be raised',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 1
			),
		'alert_repeat' => array(
			'friendly_name' => 'Re-Alerting',
			'description' => 'Repeat Alert after specified number of poller cycles.',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 12
			),
		'alert_syslog' => array(
			'friendly_name' => 'Syslog Support',
			'description' => 'These messages will be sent to your local syslog. If you would like these sent to a remote box, you must setup your local syslog to do so',
			'method' => 'checkbox'
			),
		'thold_syslog_level' => array(
			'friendly_name' => 'Syslog Level',
			'description' => 'This is the priority level that your syslog messages will be sent as.',
			'method' => 'drop_array',
			'default' => LOG_WARNING,
			'array' => array(LOG_EMERG => 'Emergency', LOG_ALERT => 'Alert', LOG_CRIT => 'Critical', LOG_ERR => 'Error', LOG_WARNING => 'Warning', LOG_NOTICE => 'Notice', LOG_INFO => 'Info', LOG_DEBUG => 'Debug'),
			),
		'thold_syslog_facility' => array(
			'friendly_name' => 'Syslog Facility',
			'description' => 'This is the facility level that your syslog messages will be sent as.',
			'method' => 'drop_array',
			'default' => $default_facility,
			'array' => $syslog_facil_array,
			),
		'thold_email_header' => array(
			'friendly_name' => 'Emailing Options',
			'method' => 'spacer',
			),
		'thold_email_prio' => array(
			'friendly_name' => 'Send Emails with Urgent Priority',
			'description' => 'Allows you to set e-mails with urgent priority',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'alert_deadnotify' => array(
			'friendly_name' => 'Dead Hosts Notifications',
			'description' => 'Enable Dead/Recovering host notification',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'alert_email' => array(
			'friendly_name' => 'Dead Host Notifications Email',
			'description' => 'This is the Email Address that the Dead Host Notifications will be sent to if the Global Notification List is selected.',
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
			),
		'thold_down_subject' => array(
			'friendly_name' => 'Down Host Subject',
			'description' => 'This is the Email subject that will be used for Down Host Messages.',
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
			'default' => 'Host Error: <DESCRIPTION> (<HOSTNAME>) is DOWN',
			),
		'thold_down_text' => array(
			'friendly_name' => 'Down Host Message',
			'description' => 'This is the message that will be displayed as the message body of all UP / Down Host Messages (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060HOSTNAME&#062  &#060DESCRIPTION&#062 &#060UPTIME&#062  &#060UPTIMETEXT&#062  &#060DOWNTIME&#062 &#060MESSAGE&#062 &#060SUBJECT&#062 &#060DOWN/UP&#062 &#060SNMP_HOSTNAME&#062 &#060SNMP_LOCATION&#062 &#060SNMP_CONTACT&#062 &#060SNMP_SYSTEM&#062 &#060LAST_FAIL&#062 &#060AVAILABILITY&#062 &#060TOT_POLL&#062 &#060FAIL_POLL&#062 &#060CUR_TIME&#062 &#060AVG_TIME&#062 &#060NOTES&#062',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => 'System Error : <DESCRIPTION> (<HOSTNAME>) is <DOWN/UP><br>Reason: <MESSAGE><br><br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked DOWN : <LAST_FAIL><br>Host Previously UP for: <DOWNTIME><br>NOTE: <NOTES>',
			),
		'thold_up_subject' => array(
			'friendly_name' => 'Recovering Host Subject',
			'description' => 'This is the Email subject that will be used for Recovering Host Messages.',
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
			'default' => 'Host Notice: <DESCRIPTION> (<HOSTNAME>) returned from DOWN state',
			),
		'thold_up_text' => array(
			'friendly_name' => 'Recovering Host Message',
			'description' => 'This is the message that will be displayed as the message body of all UP / Down Host Messages (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060HOSTNAME&#062  &#060DESCRIPTION&#062 &#060UPTIME&#062  &#060UPTIMETEXT&#062  &#060DOWNTIME&#062 &#060MESSAGE&#062 &#060SUBJECT&#062 &#060DOWN/UP&#062 &#060SNMP_HOSTNAME&#062 &#060SNMP_LOCATION&#062 &#060SNMP_CONTACT&#062 &#060SNMP_SYSTEM&#062 &#060LAST_FAIL&#062 &#060AVAILABILITY&#062 &#060TOT_POLL&#062 &#060FAIL_POLL&#062 &#060CUR_TIME&#062 &#060AVG_TIME&#062 &#060NOTES&#062',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => '<br>System <DESCRIPTION> (<HOSTNAME>) status: <DOWN/UP><br><br>Current ping response: <CUR_TIME> ms<br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked UP: <LAST_FAIL><br>Host Previously DOWN for: <DOWNTIME><br><br>Snmp Info:<br>Name - <SNMP_HOSTNAME><br>Location - <SNMP_LOCATION><br>Uptime - <UPTIMETEXT> (<UPTIME> ms)<br>System - <SNMP_SYSTEM><br><br>NOTE: <NOTES>',
		),
		'thold_from_email' => array(
			'friendly_name' => 'From Email Address',
			'description' => 'This is the Email address that the threshold will appear from.',
			'method' => 'textbox',
			'default' => read_config_option("settings_from_email"),
			'max_length' => 255,
			),
		'thold_from_name' => array(
			'friendly_name' => 'From Name',
			'description' => 'This is the actual name that the threshold will appear from.',
			'method' => 'textbox',
			'default' => read_config_option("settings_from_name"),
			'max_length' => 255,
			),
		'thold_alert_text' => array(
			'friendly_name' => 'Threshold Alert Message',
			'description' => 'This is the message that will be displayed at the top of all Threshold Alerts (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => 'An Alert has been issued that requires your attention. <br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH>',
			),
		'thold_warning_text' => array(
			'friendly_name' => 'Threshold Warning Message',
			'description' => 'This is the message that will be displayed at the top of all threshold warnings (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => 'A warning has been issued that requires your attention. <br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH>',
			),
		'thold_send_text_only' => array(
			'friendly_name' => 'Send Alerts as Text',
			'description' => 'If checked, this will cause all Alerts to be sent as plain text Emails with no graph.  The default is HTML Emails with the graph embedded in the Email.',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_baseline_header' => array(
			'friendly_name' => 'Default Baseline Settings',
			'method' => 'spacer',
			),
		'alert_bl_timerange_def' => array(
			'friendly_name' => 'Baseline Time Range Default',
			'description' => 'This is the default value used in creating thresholds or templates.',
			'method' => 'drop_array',
			'array' => get_reference_types(),
			'size' => 12,
			'max_length' => 12,
			'default' => 86400
			),
		'alert_bl_trigger' => array(
			'friendly_name' => 'Baseline Trigger Count',
			'description' => 'Default number of consecutive times the Data Source must be in breach of the calculated Baseline Threshold for an Alert to be raised',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 2
			),
		'alert_bl_percent_def' => array(
			'friendly_name' => 'Baseline Deviation Percentage',
			'description' => 'This is the default value used in creating thresholds or templates.',
			'method' => 'textbox',
			'size' => 3,
			'max_length' => 3,
			'default' => 20
			)
		);
}

