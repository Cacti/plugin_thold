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

	$nav['thold_add.php:'] = array('title' => 'Create Threshold', 'mapping' => 'index.php:', 'url' => 'thold_add.php', 'level' => '1');

	return $nav;
}

function thold_config_insert () {
	global $menu;

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

function thold_config_settings () {
	global $tabs, $settings, $config;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	define_syslog_variables();

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
			'friendly_name' => 'Disable all thresholds',
			'description' => 'Checking this box will disable alerting on all thresholds.  This can be used when it is necessary to perform maintenance on your network.',
			'method' => 'checkbox',
			'default' => 'off'
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
			'friendly_name' => 'Thresholds per page',
			'description' => 'Number of thresholds to display per page',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 30
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
			'description' => 'Number of consecutive times the data source must be in breach of the threshold for an alert to be raised',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 1
			),
		'alert_repeat' => array(
			'friendly_name' => 'Re-Alerting',
			'description' => 'Repeat alert after specified number of cycles.',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 12
			),
		'alert_syslog' => array(
			'friendly_name' => 'Syslogging',
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
		'alert_deadnotify' => array(
			'friendly_name' => 'Dead Hosts Notifications',
			'description' => 'Enable Dead/Recovering host notification',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'alert_email' => array(
			'friendly_name' => 'Dead Host Notifications Email',
			'description' => 'This is the email address that the dead host notifications will be sent to.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'thold_down_subject' => array(
			'friendly_name' => 'Down Host Subject',
			'description' => 'This is the email subject that will be used for Down Host Messages.',
			'method' => 'textbox',
			'max_length' => 255,
			'default' => 'Host Error: <DESCRIPTION> (<HOSTNAME>) is DOWN',
			),
		'thold_up_subject' => array(
			'friendly_name' => 'Recovering Host Subject',
			'description' => 'This is the email subject that will be used for Recovering Host Messages.',
			'method' => 'textbox',
			'max_length' => 255,
			'default' => 'Host Notice: <DESCRIPTION> (<HOSTNAME>) returned from DOWN state',
			),
		'thold_down_text' => array(
			'friendly_name' => 'Down Host Message',
			'description' => 'This is the message that will be displayed as the message body of all UP / Down Host Messages (255 Char MAX).  HTML is allowed, but will be removed for text only emails.  There are several descriptors that may be used.<br>&#060HOSTNAME&#062  &#060DESCRIPTION&#062 &#060UPTIME&#062  &#060UPTIMETEXT&#062  &#060DOWNTIME&#062 &#060MESSAGE&#062 &#060SUBJECT&#062 &#060DOWN/UP&#062 &#060SNMP_HOSTNAME&#062 &#060SNMP_LOCATION&#062 &#060SNMP_CONTACT&#062 &#060SNMP_SYSTEM&#062 &#060LAST_FAIL&#062 &#060AVAILABILITY&#062 &#060CUR_TIME&#062 &#060AVG_TIME&#062 &#060NOTES&#062',
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => 'Host: <DESCRIPTION> (<HOSTNAME>)<br>Status: <DOWN/UP><br>Message: <MESSAGE><br><br>Uptime: <UPTIME> (<UPTIMETEXT>)<br>Availiability: <AVAILABILITY><br>Response: <CUR_TIME> ms<br>Down Since: <LAST_FAIL><br>NOTE: <NOTES>',
			),
		'thold_from_email' => array(
			'friendly_name' => 'From Email Address',
			'description' => 'This is the email address that the threshold will appear from.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'thold_from_name' => array(
			'friendly_name' => 'From Name',
			'description' => 'This is the actual name that the threshold will appear from.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'thold_alert_text' => array(
			'friendly_name' => 'Threshold Alert Message',
			'description' => 'This is the message that will be displayed at the top of all threshold alerts (255 Char MAX).  HTML is allowed, but will be removed for text only emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062',
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => 'An alert has been issued that requires your attention. <br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH>',
			),
		'thold_send_text_only' => array(
			'friendly_name' => 'Send alerts as text',
			'description' => 'If checked, this will cause all alerts to be sent as plain text emails with no graph.  The default is HTML emails with the graph embedded in the email.',
			'method' => 'checkbox',
			'default' => 'off'
			),
		'thold_baseline_header' => array(
			'friendly_name' => 'Default Baseline Options',
			'method' => 'spacer',
			),
		'alert_notify_bl' => array(
			'friendly_name' => 'Baseline notifications',
			'description' => 'Enable sending alert for baseline notifications',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'alert_bl_trigger' => array(
			'friendly_name' => 'Default Baseline Trigger Count',
			'description' => 'Number of consecutive times the data source must be in breach of the calculated baseline threshold for an alert to be raised',
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 2
			),
		'alert_bl_past_default' => array(
			'friendly_name' => 'Baseline reference in the past default',
			'description' => 'This is the default value used in creating thresholds or templates.',
			'method' => 'textbox',
			'size' => 12,
			'max_length' => 12,
			'default' => 86400
			),
		'alert_bl_timerange_def' => array(
				'friendly_name' => 'Baseline time range default',
			'description' => 'This is the default value used in creating thresholds or templates.',
			'method' => 'textbox',
			'size' => 12,
			'max_length' => 12,
			'default' => 10800
			),
		'alert_bl_percent_def' => array(
			'friendly_name' => 'Baseline deviation percentage',
			'description' => 'This is the default value used in creating thresholds or templates.',
			'method' => 'textbox',
			'size' => 3,
			'max_length' => 3,
			'default' => 15
			)
		);
}
