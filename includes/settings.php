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

function thold_draw_navigation_text ($nav) {
	global $config;

	$nav['thold.php:'] = array('title' => __('Thresholds'), 'mapping' => 'index.php:', 'url' => 'thold.php', 'level' => '1');
	$nav['thold.php:actions'] = array('title' => __('(actions)'), 'mapping' => 'index.php:,thold.php:', 'url' => 'thold.php', 'level' => '2');
	$nav['thold.php:edit'] = array('title' => __('(edit)'), 'mapping' => 'index.php:,thold.php:', 'url' => 'thold.php', 'level' => '2');
	$nav['thold.php:save'] = array('title' => __('(save)'), 'mapping' => 'index.php:,thold.php:', 'url' => 'thold.php', 'level' => '2');
	$nav['thold.php:add'] = array('title' => __('(add)'), 'mapping' => 'index.php:,thold.php:', 'url' => 'thold.php', 'level' => '2');
	$nav['thold.php:autocreate'] = array('title' => __('Thresholds'), 'mapping' => 'index.php:', 'url' => 'thold.php', 'level' => '2');
	$nav['thold_graph.php:'] = array('title' => __('Thresholds'), 'mapping' => 'index.php:', 'url' => 'thold_graph.php', 'level' => '1');
	$nav['thold_graph.php:thold'] = array('title' => __('Thresholds'), 'mapping' => $config['url_path'] . 'graph_view.php:', 'url' => 'thold_graph.php', 'level' => '1');
	$nav['thold_view_failures.php:'] = array('title' => __('Thresholds - Failures'), 'mapping' => 'index.php:', 'url' => 'thold_view_failures.php', 'level' => '1');
	$nav['thold_view_normal.php:'] = array('title' => __('Thresholds - Normal'), 'mapping' => 'index.php:', 'url' => 'thold_view_normal.php', 'level' => '1');
	$nav['thold_view_recover.php:'] = array('title' => __('Thresholds - Recovering'), 'mapping' => 'index.php:', 'url' => 'thold_view_recover.php', 'level' => '1');
	$nav['thold_view_recent.php:'] = array('title' => __('Recent Thresholds'), 'mapping' => 'index.php:', 'url' => 'thold_view_recent.php', 'level' => '1');
	$nav['thold_view_host.php:'] = array('title' => __('Recent Device Failures'), 'mapping' => 'index.php:', 'url' => 'thold_view_host.php', 'level' => '1');

	$nav['thold_templates.php:'] = array('title' => __('Threshold Templates'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:edit'] = array('title' => __('Threshold Templates'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:save'] = array('title' => __('Threshold Templates'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:add'] = array('title' => __('Threshold Templates'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:actions'] = array('title' => __('Threshold Templates'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '1');
	$nav['thold_templates.php:import'] = array('title' => __('Threshold Template Import'), 'mapping' => 'index.php:', 'url' => 'thold_templates.php', 'level' => '2');

	$nav['notify_lists.php:'] = array('title' => __('Notification Lists'), 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:edit'] = array('title' => __('Notification Lists (edit)'), 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:save'] = array('title' => __('Notification Lists'), 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');
	$nav['notify_lists.php:actions'] = array('title' => __('Notification Lists'), 'mapping' => 'index.php:', 'url' => 'notify_lists.php', 'level' => '1');

	return $nav;
}

function thold_config_insert () {
	global $menu;

	$menu[__('Management')]['plugins/thold/notify_lists.php'] = __('Notification Lists');
	$menu[__('Management')]['plugins/thold/thold.php'] = __('Thresholds');
	$menu[__('Templates')]['plugins/thold/thold_templates.php'] = __('Threshold');
	if (isset_request_var('thold_vrule')) {
		if (get_nfilter_request_var('thold_vrule') == 'on') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = 'on';
			$_SESSION['sess_config_array']['boost_png_cache_enable'] = false;
		} elseif (get_nfilter_request_var('thold_vrule') == '') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = '';
		}
	}

	define('ST_RESTORAL', 0); // Restoral
	define('ST_TRIGGERA', 1); // Trigger Alert
	define('ST_NOTIFYRA', 2); // Notify Alert Retrigger
	define('ST_NOTIFYWA', 3); // Notify Warning
	define('ST_NOTIFYAL', 4); // Notify Alert
	define('ST_NOTIFYRS', 5); // Notify Restoral
	define('ST_TRIGGERW', 6); // Trigger Warning
	define('ST_NOTIFYAW', 7); // Notify Restoral to Warning

	define('STAT_HI',     2);
	define('STAT_LO',     1);
	define('STAT_NORMAL', 0);
}

function thold_config_arrays () {
	global $messages;

	$messages['thold_save'] = array(
		'message' => __('A template with that Data Source already exists!'),
		'type' => 'error'
	);

	if (isset($_SESSION['thold_message']) && $_SESSION['thold_message'] != '') {
		$messages['thold_message'] = array(
			'message' => $_SESSION['thold_message'], 
			'type' => 'info'
		);
	}
}

function thold_config_form () {
	global $fields_host_edit;
	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['thold_mail_spacer'] = array(
				'friendly_name' => __('Device Up/Down Notification Settings'),
				'method' => 'spacer',
				'collapsible' => true
			);
			$fields_host_edit3['thold_send_email'] = array(
				'friendly_name' => __('Threshold Up/Down Email Notification'),
				'method' => 'drop_array',
				'array' =>  array(
					'0' => __('Disabled'), 
					'1' => __('Global List'), 
					'2' => __('List Below'), 
					'3' => __('Global and List Below')
				),
				'description' => __('Which Notification List(s) of should be notified about Device Up/Down events?'),
				'value' => '|arg1:thold_send_email|',
				'on_change' => 'changeNotify()',
				'default' => '0',
				'form_id' => false
			);
			$fields_host_edit3['thold_host_email'] = array(
				'friendly_name' => __('Notification List'),
				'description' => __('Additional Email address, separated by commas for multiple Emails.'),
				'method' => 'drop_sql',
				'sql' => 'SELECT id,name FROM plugin_notification_lists ORDER BY name',
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

	if (get_current_page() != 'settings.php') return;

	include('./plugins/thold/includes/arrays.php');
	include_once('./plugins/thold/thold_functions.php');

	if ($config['cacti_server_os'] == 'unix') {
		$syslog_facil_array = array(
			LOG_AUTH     => 'Auth', 
			LOG_AUTHPRIV => 'Auth Private', 
			LOG_CRON     => 'Cron', 
			LOG_DAEMON   => 'Daemon', 
			LOG_KERN     => 'Kernel', 
			LOG_LOCAL0   => 'Local 0', 
			LOG_LOCAL1   => 'Local 1', 
			LOG_LOCAL2   => 'Local 2', 
			LOG_LOCAL3   => 'Local 3', 
			LOG_LOCAL4   => 'Local 4', 
			LOG_LOCAL5   => 'Local 5', 
			LOG_LOCAL6   => 'Local 6', 
			LOG_LOCAL7   => 'Local 7', 
			LOG_LPR      => 'LPR', 
			LOG_MAIL     => 'Mail', 
			LOG_NEWS     => 'News', 
			LOG_SYSLOG   => 'Syslog', 
			LOG_USER     => 'User', 
			LOG_UUCP     => 'UUCP'
		);

		$default_facility = LOG_DAEMON;
	} else {
		$syslog_facil_array = array(LOG_USER => 'User');

		$default_facility = LOG_USER;
	}

	$tabs['alerts'] = __('Thresholds');
	$settings['alerts'] = array(
		'general_header' => array(
			'friendly_name' => __('General'),
			'method' => 'spacer',
		),
		'thold_disable_all' => array(
			'friendly_name' => __('Disable All Thresholds'),
			'description' => __('Checking this box will disable Alerting on all Thresholds.  This can be used when it is necessary to perform maintenance on your network.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_autocreate' => array(
			'friendly_name' => __('Auto Create Thresholds'),
			'description' => __('If selected, when running either automation, or when creating/saving a Device, all Thresholds associated with the Device Template will be created.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_disable_legacy' => array(
			'friendly_name' => __('Disable Legacy Notifications'),
			'description' => __('Checking this box will disable Legacy Alerting on all Thresholds.  Legacy Alerting is defined as any Specific Email Alerts not associated with a Notification List.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_filter_default' => array(
			'friendly_name' => __('Default Status'),
			'description' => __('Default Threshold Tab filter status.'),
			'method' => 'drop_array',
			'array' => array(
				'-1' => __('Any'), 
				'0'  => __('Disabled'), 
				'2'  => __('Enabled'), 
				'1'  => __('Breached'), 
				'3'  => __('Triggered')
			),
			'default' => 20
		),
		'logging_header' => array(
			'friendly_name' => __('Logging'),
			'method' => 'spacer',
		),
		'thold_log_cacti' => array(
			'friendly_name' => __('Log Threshold Breaches'),
			'description' => __('Enable logging of all Threshold failures to the Cacti Log.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_show_datasource' => array(
			'friendly_name' => __('Show Data Source in Log'),
			'description' => __('Show the Data Source name in the Log if not present.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_log_changes' => array(
			'friendly_name' => __('Log Threshold Changes'),
			'description' => __('Enable logging of all Threshold changes to the Cacti Log.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_log_debug' => array(
			'friendly_name' => __('Debug Log'),
			'description' => __('Enable logging of debug messages with Threshold'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_log_storage' => array(
			'friendly_name' => __('Alert Log Retention'),
			'description' => __('Keep Threshold Logs for this number of days.'),
			'method' => 'drop_array',
			'default' => '31',
			'array' => $thold_log_retention
		),
		'daemon_header' => array(
			'friendly_name' => __('Threshold Daemon'),
			'method' => 'spacer',
		),
		'thold_daemon_enable' => array(
			'friendly_name' => __('Enable Threshold Daemon'),
			'description' => __('Checking this box will enable the use of a dedicated Threshold daemon. This can be used to increase system performance and/or to distribute Threshold monitoring to a separate server.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'thold_max_concurrent_processes' => array(
			'friendly_name' => __('Maximum Concurrent Threshold Processes'),
			'description' => __('The maximum number of concurrent processes to be handled by the Threshold Daemon.'),
			'method' => 'textbox',
			'size' => 2,
			'max_length' => 2,
			'default' => read_config_option('concurrent_processes')
		),
		'thold_alerting_header' => array(
			'friendly_name' => __('Alert Presets'),
			'method' => 'spacer',
		),
		'alert_exempt' => array(
			'friendly_name' => __('Weekend exemptions'),
			'description' => __('If this is checked, Thold will not run on weekends.'),
			'method' => 'checkbox',
		),
		'alert_trigger' => array(
			'friendly_name' => __('Default Trigger Count'),
			'description' => __('Default number of consecutive times the Data Source must be in breach of the Threshold for an Alert to be raised.'),
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 1
		),
		'alert_repeat' => array(
			'friendly_name' => __('Re-Alerting'),
			'description' => __('Repeat Alert after specified number of poller cycles.'),
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 12
		),
		'thold_baseline_header' => array(
			'friendly_name' => __('Baseline Presets'),
			'method' => 'spacer',
		),
		'alert_bl_timerange_def' => array(
			'friendly_name' => __('Baseline Time Range Default'),
			'description' => __('This is the default value used in creating Thresholds or templates.'),
			'method' => 'drop_array',
			'array' => get_reference_types(),
			'size' => 12,
			'max_length' => 12,
			'default' => 86400
		),
		'alert_bl_trigger' => array(
			'friendly_name' => __('Baseline Trigger Count'),
			'description' => __('Default number of consecutive times the Data Source must be in breach of the calculated Baseline Threshold for an Alert to be raised.'),
			'method' => 'textbox',
			'size' => 4,
			'max_length' => 4,
			'default' => 2
		),
		'alert_bl_percent_def' => array(
			'friendly_name' => __('Baseline Deviation Percentage'),
			'description' => __('This is the default value used in creating Thresholds or templates.'),
			'method' => 'textbox',
			'size' => 3,
			'max_length' => 3,
			'default' => 20
		),
		'syslog_header' => array(
			'friendly_name' => __('Syslog Settings'),
			'method' => 'spacer',
		),
		'alert_syslog' => array(
			'friendly_name' => __('Syslog Support'),
			'description' => __('These messages will be sent to your local syslog. If you would like these sent to a remote box, you must setup your local syslog to do so.'),
			'method' => 'checkbox'
		),
		'thold_syslog_level' => array(
			'friendly_name' => __('Syslog Level'),
			'description' => __('This is the priority level that your syslog messages will be sent as.'),
			'method' => 'drop_array',
			'default' => LOG_WARNING,
			'array' => array(
				LOG_EMERG   => __('Emergency'), 
				LOG_ALERT   => __('Alert'), 
				LOG_CRIT    => __('Critical'), 
				LOG_ERR     => __('Error'), 
				LOG_WARNING => __('Warning'), 
				LOG_NOTICE  => __('Notice'), 
				LOG_INFO    => __('Info'), 
				LOG_DEBUG   => __('Debug')
			),
		),
		'thold_syslog_facility' => array(
			'friendly_name' => __('Syslog Facility'),
			'description' => __('This is the facility level that your syslog messages will be sent as.'),
			'method' => 'drop_array',
			'default' => $default_facility,
			'array' => $syslog_facil_array,
		),
		'thold_alerting_header3' => array(
			'friendly_name' => __('SNMP Notification Presets'),
			'method' => 'spacer',
		),
		'thold_alert_snmp' => array(
			'friendly_name' => __('SNMP Notifications'),
			'description' => __('Threshold status messages (informs/traps) will be sent to SNMP notification receivers. This includes Alerts, Warnings and Restoration traps per default. Note: This feature requires the Cacti SNMPAgent plugin.'),
			'method' => 'checkbox',
			'default' => '0',
		),
		'thold_alert_snmp_warning' => array(
			'friendly_name' => __('Disable Warning Notifications'),
			'description' => __('If this is checked, Threshold will not send a notification when a warning Threshold has been breached.'),
			'method' => 'checkbox'
		),
		'thold_alert_snmp_normal' => array(
			'friendly_name' => __('Disable Restoration Notifications'),
			'description' => __('If this is checked, Threshold will not send a notification when the Threshold has returned to normal status.'),
			'method' => 'checkbox'
		),
		'thold_snmp_event_description' => array(
			'friendly_name' => __('SNMP Event Description'),
			'description' => __('You can customize the event description being sent out to the SNMP notification receivers by using additional varbinds. Following variable bindings will be supported:<br>&#060;THRESHOLDNAME&#062; &#060;HOSTNAME&#062; &#060;HOSTIP&#062; &#060;TEMPLATE_ID&#062; &#060;TEMPLATE_NAME&#062; &#060;THR_TYPE&#062; &#060;DS_NAME&#062; &#060;HI&#062; &#060;LOW&#062; &#060;EVENT_CATEGORY&#062; &#060;FAIL_COUNT&#062; &#060;FAIL_DURATION&#062;'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => '',
		),
		'thold_email_header' => array(
			'friendly_name' => __('Emailing Options'),
			'method' => 'spacer',
		),
		'thold_email_prio' => array(
			'friendly_name' => __('Send Emails with Urgent Priority'),
			'description' => __('Allows you to set Emails with urgent priority'),
			'method' => 'checkbox',
			'default' => ''
		),
		'alert_deadnotify' => array(
			'friendly_name' => __('Dead Device Notifications'),
			'description' => __('Enable Dead/Recovering host notification'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'alert_email' => array(
			'friendly_name' => __('Dead Device Notifications Email'),
			'description' => __('This is the Email Address that the Dead Device Notifications will be sent to if the Global Notification List is selected.'),
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
		),
		'thold_down_subject' => array(
			'friendly_name' => __('Down Device Subject'),
			'description' => __('This is the Email subject that will be used for Down Device Messages.'),
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
			'default' => __('Device Error: <DESCRIPTION> (<HOSTNAME>) is DOWN'),
		),
		'thold_down_text' => array(
			'friendly_name' => __('Down Device Message'),
			'description' => __('This is the message that will be displayed as the message body of all UP / Down Device Messages (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060HOSTNAME&#062  &#060DESCRIPTION&#062 &#060UPTIME&#062  &#060UPTIMETEXT&#062  &#060DOWNTIME&#062 &#060MESSAGE&#062 &#060SUBJECT&#062 &#060DOWN/UP&#062 &#060SNMP_HOSTNAME&#062 &#060SNMP_LOCATION&#062 &#060SNMP_CONTACT&#062 &#060SNMP_SYSTEM&#062 &#060LAST_FAIL&#062 &#060AVAILABILITY&#062 &#060TOT_POLL&#062 &#060FAIL_POLL&#062 &#060CUR_TIME&#062 &#060AVG_TIME&#062 &#060NOTES&#062'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '7',
			'textarea_cols' => '80',
			'default' => __('System Error : <DESCRIPTION> (<HOSTNAME>) is <DOWN/UP><br>Reason: <MESSAGE><br><br>Average system response: <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked DOWN : <LAST_FAIL><br>Device Previously UP for: <DOWNTIME><br>NOTE: <NOTES>'),
		),
		'thold_up_subject' => array(
			'friendly_name' => __('Recovering Device Subject'),
			'description' => __('This is the Email subject that will be used for Recovering Device Messages.'),
			'method' => 'textbox',
			'size' => 80,
			'max_length' => 255,
			'default' => __('Device Notice: <DESCRIPTION> (<HOSTNAME>) returned from DOWN state'),
		),
		'thold_up_text' => array(
			'friendly_name' => __('Recovering Device Message'),
			'description' => __('This is the message that will be displayed as the message body of all UP / Down Device Messages (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060HOSTNAME&#062  &#060DESCRIPTION&#062 &#060UPTIME&#062  &#060UPTIMETEXT&#062  &#060DOWNTIME&#062 &#060MESSAGE&#062 &#060SUBJECT&#062 &#060DOWN/UP&#062 &#060SNMP_HOSTNAME&#062 &#060SNMP_LOCATION&#062 &#060SNMP_CONTACT&#062 &#060SNMP_SYSTEM&#062 &#060LAST_FAIL&#062 &#060AVAILABILITY&#062 &#060TOT_POLL&#062 &#060FAIL_POLL&#062 &#060CUR_TIME&#062 &#060AVG_TIME&#062 &#060NOTES&#062'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '7',
			'textarea_cols' => '80',
			'default' => __('<br>System <DESCRIPTION> (<HOSTNAME>) status: <DOWN/UP><br><br>Current ping response: <CUR_TIME> ms<br>Average system response: <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked UP: <LAST_FAIL><br>Device Previously DOWN for: <DOWNTIME><br><br>Snmp Info:<br>Name - <SNMP_HOSTNAME><br>Location - <SNMP_LOCATION><br>Uptime - <UPTIMETEXT> (<UPTIME> ms)<br>System - <SNMP_SYSTEM><br><br>NOTE: <NOTES>'),
		),
		'thold_from_email' => array(
			'friendly_name' => __('From Email Address'),
			'description' => __('This is the Email address that the Threshold will appear from.'),
			'method' => 'textbox',
			'default' => read_config_option('settings_from_email'),
			'max_length' => 255,
		),
		'thold_from_name' => array(
			'friendly_name' => __('From Name'),
			'description' => __('This is the actual name that the Threshold will appear from.'),
			'method' => 'textbox',
			'default' => read_config_option('settings_from_name'),
			'max_length' => 255,
		),
		'thold_alert_text' => array(
			'friendly_name' => __('Threshold Alert Message'),
			'description' => __('This is the message that will be displayed at the top of all Threshold Alerts (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#60NOTES&#62'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => __('An Alert has been issued that requires your attention. <br><br><strong>Device</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH>'),
		),
		'thold_warning_text' => array(
			'friendly_name' => __('Threshold Warning Message'),
			'description' => __('This is the message that will be displayed at the top of all Threshold warnings (255 Char MAX).  HTML is allowed, but will be removed for text only Emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#60NOTES&#62'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			'default' => __('A warning has been issued that requires your attention. <br><br><strong>Device</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH>'),
		),
		'thold_send_text_only' => array(
			'friendly_name' => __('Send Alerts as Text'),
			'description' => __('If checked, this will cause all Alerts to be sent as plain text Emails with no graph.  The default is HTML Emails with the graph embedded in the Email.'),
			'method' => 'checkbox',
			'default' => ''
		)
	);
}

