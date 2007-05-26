<?php
/*******************************************************************************

    Author ......... Jimmy Conner
    Contact ........ jimmy@sqmail.org
    Home Site ...... http://cactiusers.org
    Program ........ Thresholds for Cacti

*******************************************************************************/

function plugin_init_thold() {
	global $plugin_hooks;
	$plugin_hooks['top_header_tabs']['thold'] = 'thold_show_tab';
	$plugin_hooks['top_graph_header_tabs']['thold'] = 'thold_show_tab';
	$plugin_hooks['config_arrays']['thold'] = 'thold_config_arrays';
	$plugin_hooks['config_settings']['thold'] = 'thold_config_settings';
	$plugin_hooks['draw_navigation_text']['thold'] = 'thold_draw_navigation_text';
	$plugin_hooks['data_sources_table']['thold'] = 'thold_data_sources_table';
	$plugin_hooks['graphs_new_top_links']['thold'] = 'thold_graphs_new';
	$plugin_hooks['api_device_save']['thold'] = 'thold_api_device_save';
	$plugin_hooks['poller_bottom']['thold'] = 'thold_update_host_status';
	$plugin_hooks['poller_output']['thold'] = 'thold_poller_output';
	$plugin_hooks['device_action_array']['thold'] = 'thold_device_action_array';
	$plugin_hooks['device_action_execute']['thold'] = 'thold_device_action_execute';
	$plugin_hooks['device_action_prepare']['thold'] = 'thold_device_action_prepare';
}

function thold_version () {
	return array(	'name'		=> 'thold',
			'version' 	=> '0.3.3',
			'longname'	=> 'Thresholds',
			'author'	=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'	=> 'jimmy@sqmail.org',
			'url'		=> 'http://cactiusers.org/cacti/versions.php'
			);
}

function thold_device_action_execute ($action) {
	global $config;
	if ($action != 'thold')
		return $action;

	include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

	$selected_items = unserialize(stripslashes($_POST["selected_items"]));

	for ($i=0; ($i < count($selected_items)); $i++) {
		input_validate_input_number($selected_items[$i]);

		autocreate($selected_items[$i]);
	}
	return $action;
}

function thold_device_action_prepare ($save) {
	global $colors, $host_list;
	if ($save['drp_action'] != 'thold')
		return $save;

	print "	<tr>
			<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>To apply all appropriate thresholds to these hosts, press the \"yes\" button below.</p>
				<p>" . $save['host_list'] . "</p>
			</td>
			</tr>";
}

function thold_device_action_array($device_action_array) {
	$device_action_array['thold'] = 'Apply Thresholds';
	return $device_action_array;
}

function thold_poller_output ($rrd_update_array) {
	global $config;
	include_once($config["base_path"] . "/plugins/thold/thold-functions.php");
	$thold_items = db_fetch_assoc("select thold_data.cdef, thold_data.rra_id, thold_data.data_id, thold_data.lastread, thold_data.oldvalue,
	data_template_rrd.data_source_name as name, data_template_rrd.data_source_type_id from thold_data LEFT JOIN data_template_rrd on (data_template_rrd.id = thold_data.data_id)");
	$rrd_update_array_reindexed = array();
	foreach($rrd_update_array as $item) {
		if (isset($item['times'][key($item['times'])])) {
			$rrd_update_array_reindexed[$item['local_data_id']] = $item['times'][key($item['times'])];
		} 
	}

	foreach ($thold_items as $t_item) {
		if (isset($rrd_update_array_reindexed[$t_item['rra_id']])) {
			$item = $rrd_update_array_reindexed[$t_item['rra_id']];
			if (isset($item[$t_item['name']])) {
				switch ($t_item['data_source_type_id']) {
					case 2:	// COUNTER
						if ($item[$t_item['name']] >= $t_item['oldvalue']) {
							// Everything is normal
							$currentval = $item[$t_item['name']] - $t_item['oldvalue'];
						} else {
							// Possible overflow, see if its 32bit or 64bit
							if ($t_item['oldvalue'] > 4294967295) {
								$currentval = (18446744073709551615 - $t_item['oldvalue']) + $item[$t_item['name']];
							} else {
								$currentval = (4294967295 - $t_item['oldvalue']) + $item[$t_item['name']];
							}
						}
						$currentval = $currentval / 300;
						db_execute("UPDATE thold_data SET oldvalue = '" . $item[$t_item['name']] . "' where data_id = " . $t_item['data_id']);
						break;
					case 3:	// DERIVE
						$currentval = ($item[$t_item['name']] - $t_item['oldvalue']) / 300;
						db_execute("UPDATE thold_data SET oldvalue = '" . $item[$t_item['name']] . "' where data_id = " . $t_item['data_id']);
						break;
					case 4:	// ABSOLUTE
						$currentval = $item[$t_item['name']] / 300;
						break;
					case 1:	// GAUGE
					default:
						$currentval = $item[$t_item['name']];
						break;
				}
				thold_check_treshold ($t_item['rra_id'], $t_item['data_id'], $t_item['name'], $currentval, $t_item['cdef']);
			} 
		}
	}
	return $rrd_update_array;
}

function thold_update_host_status () {
	global $config;

	// Return if we aren't set to notify
	$deadnotify = (read_config_option("alert_deadnotify") == "on");
	if (!$deadnotify) return;

	include_once($config["base_path"] . '/plugins/thold/thold-functions.php');

	$alert_email = read_config_option("alert_email");
	$ping_failure_count = read_config_option('ping_failure_count');
		
	// Lets find hosts that were down, but are now back up
	$failed = read_config_option('thold_failed_hosts', true);
	$failed = explode(',', $failed);
	if (!empty($failed)) {
		foreach($failed as $id) {
			if ($id != '') {
				$host = db_fetch_row('SELECT id, status, description, hostname FROM host WHERE id = ' . $id);
				if ($host['status'] == HOST_UP) {
					$subject = 'Host Notice : ' . $host['description'] . ' (' . $host['hostname'] . ') returned from DOWN state';
					$msg = $subject;
					if ($alert_email == '') {
						cacti_log("THOLD: Can not send Host Recovering email since the 'Alert e-mail' setting is not set!", true, "POLLER");
					} else {
						thold_mail($alert_email, '', $subject, $msg, '');
					}
				}
			}
		}
	}
	
	// Lets find hosts that are down
	$hosts = db_fetch_assoc('SELECT id, description, hostname, status_last_error FROM host WHERE status = ' . HOST_DOWN . ' AND status_event_count = ' . $ping_failure_count);
	if (count($hosts)) {
		foreach($hosts as $host) {
			$subject = 'Host Error : ' . $host['description'] . ' (' . $host['hostname'] . ') is DOWN';
			$msg = 'Host Error : ' . $host['description'] . ' (' . $host['hostname'] . ') is DOWN<br>Message : ' . $host['status_last_error'];
			if ($alert_email == '') {
				cacti_log("THOLD: Can not send Host Down email since the 'Alert e-mail' setting is not set!", true, "POLLER");
			} else {
				thold_mail($alert_email, '', $subject, $msg, '');
			}
		}
	}

	// Now lets record all failed hosts
	$hosts = db_fetch_assoc('SELECT id FROM host WHERE status != ' . HOST_UP);
	$failed = array();
	if (!empty($hosts)) {
		foreach ($hosts as $host) {
			$failed[] = $host['id'];
		}
	}
	$failed = implode(',', $failed);
	db_execute("REPLACE INTO settings (name, value) VALUES ('thold_failed_hosts', '$failed')");
	return;
}

function thold_api_device_save ($save) {

	$sql = "select disabled from host where id = " . $save['id'];
	$result = db_fetch_assoc($sql);
	if (!isset($result[0]['disabled']))
		return $save;

	if ($save['disabled'] != $result[0]['disabled']) {
		if ($save['disabled'] == '')
			$sql = "update thold_data set thold_enabled = 'on' where host_id=" . $save['id'];
		else
			$sql = "update thold_data set thold_enabled = 'off' where host_id=" . $save['id'];
		$result = mysql_query($sql) or die (mysql_error());
	}

	return $save;
}

function thold_show_tab () {
	global $config, $user_auth_realms, $user_auth_realm_filenames;
	$realm_id2 = 0;

	if (isset($user_auth_realm_filenames{basename('graph_thold.php')})) {
		$realm_id2 = $user_auth_realm_filenames{basename('graph_thold.php')};
	}
	if ((db_fetch_assoc("select user_auth_realm.realm_id
		from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
		and user_auth_realm.realm_id='$realm_id2'")) || (empty($realm_id2))) {

		print '<a href="' . $config['url_path'] . 'plugins/thold/graph_thold.php"><img src="' . $config['url_path'] . 'plugins/thold/images/tab_thold' . ((substr(basename($_SERVER["PHP_SELF"]),0,11) == "graph_thold") ? "_down": "") . '.gif" alt="thold" align="absmiddle" border="0"></a>';
	}
	thold_setup_table();
}

function thold_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames, $menu, $messages;
	$user_auth_realms[18]='Configure Thresholds';
	$user_auth_realm_filenames['thold.php'] = 18;
	$user_auth_realm_filenames['listthold.php'] = 18;
	$user_auth_realm_filenames['thold_templates.php'] = 18;
	$user_auth_realm_filenames['email-test.php'] = 18;
	$user_auth_realms[19]='View Thresholds';
	$user_auth_realm_filenames['graph_thold.php'] = 19;
	$menu["Management"]['plugins/thold/listthold.php'] = "Thresholds";
	$menu["Templates"]['plugins/thold/thold_templates.php'] = "Threshold Templates";
	$messages['thold_save'] = array(
		"message" => 'A template with that Data Source already exists!',
		"type" => "error");
	if (isset($_SESSION['thold_message']) && $_SESSION['thold_message'] != '') {
		$messages['thold_created'] = array("message" => $_SESSION['thold_message'], "type" => "info");
//		$_SESSION['thold_message'] = '';
	}

}

function thold_draw_navigation_text ($nav) {
	$nav["thold.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["thold.php:save"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["thold.php:autocreate"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["listthold.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "listthold.php", "level" => "1");
	$nav["listthold.php:actions"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "listthold.php", "level" => "1");
	$nav["graph_thold.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "graph_thold.php", "level" => "1");
	$nav["thold_templates.php:"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:edit"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:save"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:add"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:actions"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	return $nav;
}

function thold_data_sources_table ($ds) {
	global $config;
	$ds['template_name'] = "<a href='plugins/thold/thold.php?rra=" . $ds['data_source']['local_data_id'] . '&hostid=' . $ds['data_source']['host_id'] . "'>" . ((empty($ds['data_source']["data_template_name"])) ? "<em>None</em>" : $ds['data_source']['data_template_name']) . '</a>';
	return $ds;
}

function thold_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	$sql = "show tables from `" . $database_default . "`";

	$result = db_fetch_assoc($sql) or die (mysql_error());

	$tables = array();
	$sql = array();

	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}

	if (!in_array('thold_data', $tables)) {
		$sql[] = "CREATE TABLE `thold_data` (
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
 			  `notify_default` enum('on','off') default NULL,
 			  `notify_extra` varchar(255) default NULL,
 			  `host_id` int(10) default NULL,
 			  `syslog_priority` int(2) default '3',
 			  `cdef` int(11) NOT NULL default '0',
 			  PRIMARY KEY  (`id`),
  			KEY `rra_id` (`rra_id`)
			) TYPE=MyISAM;";
		$sql[] = "INSERT INTO `user_auth_realm` VALUES (18, 1);";
		$sql[] = "INSERT INTO `user_auth_realm` VALUES (19, 1);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_past_default',86400);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_timerange_def',10800);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_percent_def',20);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_trigger',3);";
	}

	if (!in_array('thold_templates', $tables)) {
		$sql[] = "CREATE TABLE thold_template (
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
		  notify_default enum('on','off') default NULL,
		  notify_extra varchar(255) NOT NULL default '',
		  cdef int(11) NOT NULL default '0',
		  UNIQUE KEY data_source_id (data_source_id),
		  KEY id (id)
		) TYPE=MyISAM COMMENT='Table of thresholds defaults for graphs';";
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = mysql_query($sql[$a]);
		}
	}

	$sql = "show columns from thold_data from `" . $database_default . "`";
	$result = mysql_query($sql) or die (mysql_error());
	$found = false;
	$found2 = false;
	$found3 = false;

	while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if ($row['Field'] == 'thold_enabled')
			$found = true;
		if ($row['Field'] == 'cdef')
			$found2 = true;
		if ($row['Field'] == 'oldvalue')
			$found3 = true;
	}

	if (!$found) {
		$sql = "alter table thold_data add thold_enabled enum('on','off') NOT NULL default 'on' after thold_alert";
		$result = mysql_query($sql) or die (mysql_error());
	}

	if (!$found2) {
		$sql = "alter table thold_data ADD cdef INT(11) NOT NULL";
		$result = mysql_query($sql) or die (mysql_error());
	}

	if (!$found3) {
		$sql = "alter table thold_data ADD oldvalue VARCHAR( 100 ) NOT NULL AFTER lastread";
		$result = mysql_query($sql) or die (mysql_error());
	}

	$sql = "show columns from thold_template from `" . $database_default . "`";
	$result = mysql_query($sql) or die (mysql_error());
	$found = false;
	while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if ($row['Field'] == 'cdef')
			$found = true;
	}
	if (!$found) {
		$sql = "alter table thold_template ADD cdef INT(11) NOT NULL";
		$result = mysql_query($sql) or die (mysql_error());
	}
}

function thold_graphs_new () {
	global $_REQUEST, $config;
	print '<span style="color: #c16921;">*</span><a href="' . $config['url_path'] . 'plugins/thold/thold.php?action=autocreate&hostid=' . $_REQUEST["host_id"] . '">Auto-create thresholds</a><br>';
}

function thold_config_settings () {
	global $tabs, $settings;

	define_syslog_variables();

	$tabs["alerts"] = "Alerting/Thold";
      $javascript = '<script type="text/javascript">
<!--
   function emailtest() {
      w = 420;
      h = 350;
      email = window.open("plugins/thold/email-test.php", "EmailTest", "width=" + w + ",height=" + h + ",resizable=0,status=0");
      email.moveTo((screen.width - w) /2 , (screen.height - h) /2 );
   }
//-->
</script>';
	$settings["alerts"] = array(
		"general_header" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"alert_email" => array(
			"friendly_name" => "Alert e-mail",
			"description" => "Email address(es) to send alerts to: (use commas to for multiple addresses)",
			"method" => "textbox",
			"max_length" => 255,
			),
		"alert_base_url" => array(
			"friendly_name" => "Base URL",
			"description" => "Cacti base URL",
			"method" => "textbox",
			// Set the default only if called from "settings.php"
			"default" => ((isset($_SERVER["HTTP_HOST"]) && isset($_SERVER["PHP_SELF"]) && basename($_SERVER["PHP_SELF"]) == "settings.php") ? ("http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/") : ""),
			"max_length" => 255,
			),
		"alert_notify_default" => array(
			"friendly_name" => "Send notifications",
			"description" => "Enable sending alert notification",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_notify_bl" => array(
			"friendly_name" => "Baseline notifications",
			"description" => "Enable sending alert for baseline notifications",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_deadnotify" => array(
			"friendly_name" => "Dead Hosts notifications",
			"description" => "Enable Dead/Recovering host notification",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_show_alerts_only" => array(
			"friendly_name" => "Display Alerts Only",
			"description" => "If checked, only hosts and data sources that have an alert active will be displayed",
			"method" => "checkbox",
			"default" => "off"
			),
		"alert_show_host_status" => array(
			"friendly_name" => "Display Host Status",
			"description" => "If checked, host status will be displayed together with the thresholds",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_exempt" => array(
			"friendly_name" => "Weekend exemptions",
			"description" => "If this is checked, thold will not run on weekends.",
			"method" => "checkbox",
			),
		"alert_syslog" => array(
			"friendly_name" => "Syslogging",
			"description" => "These messages will be sent to your local syslog. If you would like these sent to a remote box, you must setup your local syslog to do so",
			"method" => "checkbox"
			),
		"thold_syslog_level" => array(
			"friendly_name" => "Syslog Level",
			"description" => "This is the priority level that your syslog messages will be sent as.",
			"method" => "drop_array",
			"default" => LOG_WARNING,
			"array" => array(LOG_EMERG => 'Emergency', LOG_ALERT => 'Alert', LOG_CRIT => 'Critical', LOG_ERR => 'Error', LOG_WARNING => 'Warning', LOG_NOTICE => 'Notice', LOG_INFO => 'Info', LOG_DEBUG => 'Debug'),
			),
		"alert_num_rows" => array(
			"friendly_name" => "Thresholds per page",
			"description" => "Number of thresholds to display per page",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 30
			),
		"alert_trigger" => array(
			"friendly_name" => "Default Trigger Count",
			"description" => "Number of consecutive times the data source must be in breach of the threshold for an alert to be raised",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 1
			),
		"alert_bl_trigger" => array(
			"friendly_name" => "Default Baseline Trigger Count",
			"description" => "Number of consecutive times the data source must be in breach of the calculated baseline threshold for an alert to be raised",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 2
			),
		"alert_repeat" => array(
			"friendly_name" => "Re-Alerting",
			"description" => "Repeat alert after specified number of cycles.",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 12
			),
		"alert_bl_past_default" => array(
			"friendly_name" => "Baseline reference in the past default",
			"description" => "This is the default value used in creating thresholds or templates.",
			"method" => "textbox",
			"size" => 12,
			"max_length" => 12,
			"default" => 86400
			),
		"alert_bl_timerange_def" => array(
				"friendly_name" => "Baseline time range default",
			"description" => "This is the default value used in creating thresholds or templates.",
			"method" => "textbox",
			"size" => 12,
			"max_length" => 12,
			"default" => 10800
			),
		"alert_bl_percent_def" => array(
			"friendly_name" => "Baseline deviation percentage",
			"description" => "This is the default value used in creating thresholds or templates.",
			"method" => "textbox",
			"size" => 3,
			"max_length" => 3,
			"default" => 15
			),
		"thold_email_header" => array(
			"friendly_name" => "\n$javascript\n<table width='99%' cellspacing=0 cellpadding=0 align=left><tr><td class='textSubHeaderDark'>Emailing Options</td><td align=right class='textSubHeaderDark'><a href='javascript:emailtest();' class='textSubHeaderDark'><font color=white>Send a Test Email</font></a></td></tr></table>",
			"method" => "spacer",
			),
		"thold_how" => array(
			"friendly_name" => "Mail Services",
			"description" => "Which mail service to use in order to send mail",
			"method" => "drop_array",
			"default" => "PHP Mail() Function",
			"array" => array("PHP Mail() Function", "Sendmail", "SMTP"),
			),
		"thold_from_email" => array(
			"friendly_name" => "From Email Address",
			"description" => "This is the email address that the threshold will appear from.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"thold_from_name" => array(
			"friendly_name" => "From Name",
			"description" => "This is the actual name that the threshold will appear from.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"thold_sendmail_header" => array(
			"friendly_name" => "Sendmail Options",
			"method" => "spacer",
			),
		"thold_sendmail_path" => array(
			"friendly_name" => "Sendmail Path",
			"description" => "This is the path to sendmail on your server. (Only used if Sendmail is selected as the Mail Service)",
			"method" => "textbox",
			"max_length" => 255,
			"default" => "/usr/sbin/sendmail",
			),
		"thold_smtp_header" => array(
			"friendly_name" => "SMTP Options",
			"method" => "spacer",
			),
		"thold_smtp_host" => array(
			"friendly_name" => "SMTP Hostname",
			"description" => "This is the hostname/IP of the SMTP Server you will send the email to.",
			"method" => "textbox",
			"default" => "localhost",
			"max_length" => 255,
			),
		"thold_smtp_port" => array(
			"friendly_name" => "SMTP Port",
			"description" => "This is the port on the SMTP Server that SMTP uses.",
			"method" => "textbox",
			"max_length" => 255,
			"default" => 25,
			),
		"thold_smtp_username" => array(
			"friendly_name" => "SMTP Username",
			"description" => "This is the username to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)",
			"method" => "textbox",
			"max_length" => 255,
			),
		"thold_smtp_password" => array(
			"friendly_name" => "SMTP Password",
			"description" => "This is the password to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)",
			"method" => "textbox_password",
			"max_length" => 255,
			),
		);
}

?>