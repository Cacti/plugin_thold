<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

function plugin_init_thold() {
	global $plugin_hooks;

	$plugin_hooks['config_arrays']['thold'] = 'thold_config_arrays';
	$plugin_hooks['config_settings']['thold'] = 'thold_config_settings';
	$plugin_hooks['top_header_tabs']['thold'] = 'thold_show_tab';
	$plugin_hooks['top_graph_header_tabs']['thold'] = 'thold_show_tab';
	$plugin_hooks['draw_navigation_text']['thold'] = 'thold_draw_navigation_text';
	if (!thold_check_dependencies())
		return;
	$plugin_hooks['data_sources_table']['thold'] = 'thold_data_sources_table';
	$plugin_hooks['user_admin_setup_sql_save']['thold'] = 'thold_user_admin_setup_sql_save';
	$plugin_hooks['graphs_new_top_links']['thold'] = 'thold_graphs_new';
	$plugin_hooks['api_device_save']['thold'] = 'thold_api_device_save';
	$plugin_hooks['poller_bottom']['thold'] = 'thold_update_host_status';
	$plugin_hooks['poller_output']['thold'] = 'thold_poller_output';
	$plugin_hooks['device_action_array']['thold'] = 'thold_device_action_array';
	$plugin_hooks['device_action_execute']['thold'] = 'thold_device_action_execute';
	$plugin_hooks['device_action_prepare']['thold'] = 'thold_device_action_prepare';
	$plugin_hooks['rrd_graph_graph_options']['thold'] = 'thold_rrd_graph_graph_options';
//	$plugin_hooks['graph_buttons']['thold'] = 'thold_graph_button';
	$plugin_hooks['user_admin_edit']['thold'] = 'thold_user_admin_edit';
}

function thold_check_upgrade () {
	// Let's only run this check if we are on a page that actually needs the data
	$files = array('thold.php', 'thold_graph.php', 'thold_templates.php', 'listthold.php', 'poller.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files))
		return;

	$current = thold_version ();
	$current = $current['version'];
	$old = read_config_option('plugin_thold_version');
	if ($current != $old)
		thold_setup_table ();
}

function thold_check_dependencies() {
	global $plugins, $config;
	if (!in_array('settings', $plugins))
		return false;
	$v = settings_version();
	if ($v['version'] < 0.2)
		return false;
	return true;
}

function thold_version () {
	return array(	'name'		=> 'thold',
			'version' 	=> '0.3.6',
			'longname'	=> 'Thresholds',
			'author'	=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'	=> 'jimmy@sqmail.org',
			'url'		=> 'http://cactiusers.org/cacti/versions.php'
			);
}

function thold_graph_button ($data) {
	global $config;

	$local_graph_id = $data[1]['local_graph_id'];
	$rra_id = $data[1]['rra'];
	if ((isset($_REQUEST["view_type"])) && (!empty($_REQUEST["view_type"])))
	{
		$view_type = $_REQUEST["view_type"];
	} else {
		$_REQUEST["view_type"] = "";
		$view_type = read_config_option('dataquery_type');
	}
	if ((isset($_REQUEST["graph_start"])) && (!empty($_REQUEST["graph_start"]))) 
	{
		$start=$_REQUEST["graph_start"];
	} else {
		$_REQUEST["graph_start"] = "";
		$start = time() - 3600;
	}

	if ((isset($_REQUEST["graph_end"])) && (!empty($_REQUEST["graph_end"])))
	{
		$end=$_REQUEST["graph_end"];
	} else {
		$_REQUEST["graph_end"] = "";
		$end = time();
	}
	$url = $_SERVER['REQUEST_URI'];
	$url = str_replace('&thold_vrule=on', '', $url);
	$url = str_replace('&thold_vrule=off', '', $url);
	print '<a href="' .  $url . '&thold_vrule=' . (isset($_SESSION['sess_config_array']['thold_draw_vrules']) && $_SESSION['sess_config_array']['thold_draw_vrules'] == 'on' ? 'off' : 'on') . '"><img src="' . $config['url_path'] . 'plugins/thold/images/reddot.png" border="0" alt="Thresholds" title="Thresholds" style="padding: 3px;"></a><br>';
}

function thold_rrd_graph_graph_options ($g) {
	if (read_config_option('thold_draw_vrules') != 'on') {
		return $g;
	}
	$id = $g['graph_id'];

	$end = $g['end'];
	if ($end < 0)
		$end = time() + $end;
	$end++;

	$start = $g['start'];
	if ($start < 0)
		$start = $end + $start;
	$start--;

	$rows = db_fetch_assoc("SELECT time, status FROM plugin_thold_log WHERE graph_id = $id AND type = 0 and time > $start and time < $end");
	if (!empty($rows)) {
		foreach ($rows as $row) {
			$g['graph_defs'] .= 'VRULE:' . $row['time'] . ($row['status'] == 0 ? '#00FF21' : '#FF0000') . ' \\' . "\n";
		}
	}
	return $g;
}

function thold_device_action_execute ($action) {
	global $config;
	if ($action != 'thold')
		return $action;

	include_once($config["base_path"] . "/plugins/thold/thold_functions.php");

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
	include_once($config["base_path"] . "/plugins/thold/thold_functions.php");
	$thold_items = db_fetch_assoc("select thold_data.cdef, thold_data.rra_id, thold_data.data_id, thold_data.lastread, thold_data.oldvalue,
	data_template_rrd.data_source_name as name, data_template_rrd.data_source_type_id from thold_data LEFT JOIN data_template_rrd on (data_template_rrd.id = thold_data.data_id)");
	$rrd_update_array_reindexed = array();
	foreach($rrd_update_array as $item) {
		if (isset($item['times'][key($item['times'])])) {
			$rrd_update_array_reindexed[$item['local_data_id']] = $item['times'][key($item['times'])];
		} 
	}
	$polling_interval = read_config_option("poller_interval");
	if (!isset($polling_interval) || $polling_interval < 1) {
		$polling_interval = 300;
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
						$currentval = $currentval / $polling_interval;
						db_execute("UPDATE thold_data SET oldvalue = '" . $item[$t_item['name']] . "' where data_id = " . $t_item['data_id']);
						break;
					case 3:	// DERIVE
						$currentval = ($item[$t_item['name']] - $t_item['oldvalue']) / $polling_interval;
						db_execute("UPDATE thold_data SET oldvalue = '" . $item[$t_item['name']] . "' where data_id = " . $t_item['data_id']);
						break;
					case 4:	// ABSOLUTE
						$currentval = $item[$t_item['name']] / $polling_interval;
						break;
					case 1:	// GAUGE
					default:
						$currentval = $item[$t_item['name']];
						break;
				}
				thold_check_threshold ($t_item['rra_id'], $t_item['data_id'], $t_item['name'], $currentval, $t_item['cdef']);
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
	include_once($config["base_path"] . '/plugins/thold/thold_functions.php');

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
	$sql = 'select disabled from host where id = ' . $save['id'];
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
	global $config;
	if (api_user_realm_auth('thold_graph.php')) {
		$cp = false;
		if (basename($_SERVER["PHP_SELF"]) == "thold_graph.php" || basename($_SERVER["PHP_SELF"]) == "thold_view_failures.php" || basename($_SERVER["PHP_SELF"]) == "thold_view_normal.php")
			$cp = true;

		print '<a href="' . $config['url_path'] . 'plugins/thold/thold_graph.php"><img src="' . $config['url_path'] . 'plugins/thold/images/tab_thold' . ($cp ? "_down": "") . '.gif" alt="thold" align="absmiddle" border="0"></a>';
	}
	thold_check_upgrade ();
}

function thold_user_admin_edit ($user) {
	global $fields_user_user_edit_host;

	$value = '';
	if ($user != 0) {
		$value = db_fetch_cell("SELECT data FROM plugin_thold_contacts WHERE user_id = $user AND type = 'email'");
	}


	$fields_user_user_edit_host['email'] = array(
				"method" => "textbox",
				"value" => $value,
				"friendly_name" => "Email Address",
				"form_id" => "|arg1:id|",
				"default" => "",
				"max_length" => 255
				);


}

function thold_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames, $menu, $messages, $thold_menu;
	$user_auth_realms[18]='Configure Thresholds';
	$user_auth_realm_filenames['thold.php'] = 18;
	$user_auth_realm_filenames['listthold.php'] = 18;
	$user_auth_realm_filenames['thold_templates.php'] = 18;
	$user_auth_realm_filenames['email-test.php'] = 18;
	$user_auth_realms[19]='View Thresholds';
	$user_auth_realm_filenames['thold_graph.php'] = 19;
	$user_auth_realm_filenames['thold_view_failures.php'] = 19;
	$user_auth_realm_filenames['thold_view_normal.php'] = 19;
	$user_auth_realm_filenames['thold_view_recover.php'] = 19;
	$user_auth_realm_filenames['thold_view_recent.php'] = 19;
	$user_auth_realm_filenames['thold_view_host.php'] = 19;

	$menu["Management"]['plugins/thold/listthold.php'] = "Thresholds";
	$menu["Templates"]['plugins/thold/thold_templates.php'] = "Threshold Templates";
	$messages['thold_save'] = array(
		"message" => 'A template with that Data Source already exists!',
		"type" => "error");
	if (isset($_SESSION['thold_message']) && $_SESSION['thold_message'] != '') {
		$messages['thold_created'] = array("message" => $_SESSION['thold_message'], "type" => "info");
//		$_SESSION['thold_message'] = '';
	}
	if (isset($_GET['thold_vrule'])) {
		if ($_GET['thold_vrule'] == 'on') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = 'on';
			$_SESSION['sess_config_array']['boost_png_cache_enable'] = false;
		} elseif ($_GET['thold_vrule'] == 'off') {
			$_SESSION['sess_config_array']['thold_draw_vrules'] = 'off';
		}
	}
	$thold_menu = array(
		"Thresholds" => array(
			"plugins/thold/thold_graph.php" => "All",
			"plugins/thold/thold_view_failures.php" => "Current Failures",
			"plugins/thold/thold_view_recover.php" => "Current Recovering",
			"plugins/thold/thold_view_normal.php" => "Current Normal",
			"" => "",

			),
		"Reports" => array(
			"plugins/thold/thold_view_recent.php" => "All Threshold Alerts",
			"plugins/thold/thold_view_host.php" => "All Host Down Alerts",
			),
		);
}

function thold_draw_navigation_text ($nav) {
	$nav["thold.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["thold.php:save"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["thold.php:autocreate"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold.php", "level" => "1");
	$nav["listthold.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "listthold.php", "level" => "1");
	$nav["listthold.php:actions"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "listthold.php", "level" => "1");
	$nav["thold_graph.php:"] = array("title" => "Thresholds", "mapping" => "index.php:", "url" => "thold_graph.php", "level" => "1");
	$nav["thold_view_failures.php:"] = array("title" => "Thresholds - Failures", "mapping" => "index.php:", "url" => "thold_view_failures.php", "level" => "1");
	$nav["thold_view_normal.php:"] = array("title" => "Thresholds - Normal", "mapping" => "index.php:", "url" => "thold_view_normal.php", "level" => "1");
	$nav["thold_view_recover.php:"] = array("title" => "Thresholds - Recovering", "mapping" => "index.php:", "url" => "thold_view_recover.php", "level" => "1");
	$nav["thold_view_recent.php:"] = array("title" => "Recent Thresholds", "mapping" => "index.php:", "url" => "thold_view_recent.php", "level" => "1");
	$nav["thold_view_host.php:"] = array("title" => "Recent Host Failures", "mapping" => "index.php:", "url" => "thold_view_host.php", "level" => "1");

	$nav["thold_templates.php:"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:edit"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:save"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:add"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	$nav["thold_templates.php:actions"] = array("title" => "Threshold Templates", "mapping" => "index.php:", "url" => "thold_templates.php", "level" => "1");
	return $nav;
}

function thold_data_sources_table ($ds) {
	global $config;
	if (!isset($ds['data_source'])) {
		$ds['data_template_name'] = "<a href='plugins/thold/thold.php?rra=" . $ds['local_data_id'] . '&hostid=' . $ds['host_id'] . "'>" . ((empty($ds["data_template_name"])) ? "<em>None</em>" : $ds['data_template_name']) . '</a>';
	} else {
		$ds['template_name'] = "<a href='plugins/thold/thold.php?rra=" . $ds['data_source']['local_data_id'] . '&hostid=' . $ds['data_source']['host_id'] . "'>" . ((empty($ds['data_source']["data_template_name"])) ? "<em>None</em>" : $ds['data_source']['data_template_name']) . '</a>';
	}
	return $ds;
}

function thold_setup_table () {
	global $config;

	include_once($config["library_path"] . "/database.php");

	// Set the new version
	$new = thold_version();
	$new = $new['version'];
	db_execute("REPLACE INTO settings (name, value) VALUES ('plugin_thold_version', '$new')");

	$result = db_fetch_assoc('show tables');

	$tables = array();
	$sql = array();

	if (count($result) > 1) {
		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$tables[] = $t;
			}
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
			) TYPE=MyISAM;";
		$sql[] = "INSERT INTO `user_auth_realm` VALUES (18, 1);";
		$sql[] = "INSERT INTO `user_auth_realm` VALUES (19, 1);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_past_default',86400);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_timerange_def',10800);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_percent_def',20);";
		$sql[] = "INSERT INTO settings VALUES ('alert_bl_trigger',3);";
	}

	if (!in_array('thold_template', $tables)) {
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
				  notify_extra varchar(255) NOT NULL default '',
				  cdef int(11) NOT NULL default '0',
				  UNIQUE KEY data_source_id (data_source_id),
				  KEY id (id)
				) TYPE=MyISAM COMMENT='Table of thresholds defaults for graphs';";
	}

	if (!in_array('plugin_thold_log', $tables)) {
		$sql[] = "CREATE TABLE plugin_thold_log (
				  id int(10) NOT NULL auto_increment,
				  `time` int(32) NOT NULL,
				  host_id int(10) NOT NULL,
				  graph_id int(10) NOT NULL,
				  threshold_id int(10) NOT NULL,
				  threshold_value varchar(64) NOT NULL,
				  current varchar(64) NOT NULL,
				  `status` int(5) NOT NULL,
				  `type` int(5) NOT NULL,
				  description varchar(255) NOT NULL,
				  PRIMARY KEY  (id),
				  KEY `time` (`time`),
				  KEY host_id (host_id),
				  KEY graph_id (graph_id),
				  KEY `status` (`status`),
				  KEY `type` (`type`),
				  KEY threshold_id (threshold_id)
			) TYPE=MyISAM;";
	}

	if (!in_array('plugin_thold_template_contact', $tables)) {
		$sql[] = "CREATE TABLE plugin_thold_template_contact (
				  template_id int(12) NOT NULL,
				  contact_id int(12) NOT NULL,
				  KEY template_id (template_id),
				  KEY contact_id (contact_id)
				) TYPE=MyISAM COMMENT='Table of Tholds Template Contacts';";
	}

	if (!in_array('plugin_thold_threshold_contact', $tables)) {
		$sql[] = "CREATE TABLE plugin_thold_threshold_contact (
				  thold_id int(12) NOT NULL,
				  contact_id int(12) NOT NULL,
				  KEY thold_id (thold_id),
				  KEY contact_id (contact_id)
				) TYPE=MyISAM COMMENT='Table of Tholds Threshold Contacts';";
	}

	if (!in_array('plugin_thold_contacts', $tables)) {
		$sql[] = "CREATE TABLE plugin_thold_contacts (
				  `id` int(12) NOT NULL auto_increment,
				  `user_id` int(12) NOT NULL,
				  `type` varchar(32) NOT NULL,
				  `data` text NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `type` (`type`),
				  KEY `user_id` (`user_id`)
				) TYPE=MyISAM;";
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}

	$found = false;
	$found2 = false;
	$found3 = false;
	$found4 = false;

	$result = db_fetch_assoc('show columns from thold_data');

	foreach($result as $row) {
		if ($row['Field'] == 'thold_enabled')
			$found = true;
		if ($row['Field'] == 'cdef')
			$found2 = true;
		if ($row['Field'] == 'oldvalue')
			$found3 = true;
		if ($row['Field'] == 'template')
			$found4 = true;
	}

	if (!$found) {
		db_execute("alter table thold_data add thold_enabled enum('on','off') NOT NULL default 'on' after thold_alert");
	}

	if (!$found2) {
		db_execute("alter table thold_data ADD cdef INT(11) NOT NULL");
	}

	if (!$found3) {
		db_execute("alter table thold_data ADD oldvalue VARCHAR( 100 ) NOT NULL AFTER lastread");
	}

	if (!$found4) {
		db_execute("alter table thold_data ADD template INT( 11 ) NOT NULL");
		db_execute("alter table thold_data ADD template_enabled varchar(3) NOT NULL default ''");
		db_execute("ALTER TABLE `thold_data` ADD INDEX `template`(`template`)");
		db_execute("ALTER TABLE `thold_data` ADD INDEX `template_enabled`(`template_enabled`)");
		db_execute("ALTER TABLE `thold_data` ADD INDEX `data_id`(`data_id`)");
		db_execute("ALTER TABLE `thold_data` ADD INDEX `thold_enabled`(`thold_enabled`)");
	}

	$result = db_fetch_assoc('show columns from thold_template');
	$found = false;
	foreach($result as $row) {
		if ($row['Field'] == 'cdef')
			$found = true;
	}
	if (!$found) {
		db_execute("alter table thold_template ADD cdef INT(11) NOT NULL");
	}

	$result = db_fetch_assoc('show columns from thold_template');
	$found = false;
	foreach($result as $row) {
		if ($row['Field'] == 'cdef')
			$found = true;
	}
	if (!$found) {
		db_execute("alter table thold_template ADD cdef INT(11) NOT NULL");
	}
}

function thold_graphs_new () {
	global $_REQUEST, $config;
	print '<span style="color: #c16921;">*</span><a href="' . $config['url_path'] . 'plugins/thold/thold.php?action=autocreate&hostid=' . $_REQUEST["host_id"] . '">Auto-create thresholds</a><br>';
}

function thold_user_admin_setup_sql_save ($save) {
	global $database_default, $database_type, $database_port, $database_password, $database_username, $database_hostname, $config;
	if (is_error_message()) {
		return $save;
	}

	if (isset($_POST['email'])) {
		$email = form_input_validate($_POST["email"], "email", "", true, 3);
		if ($save['id'] == 0) {
			$save['id'] = sql_save($save, "user_auth");
		}

		$cid = db_fetch_cell("SELECT id FROM plugin_thold_contacts WHERE type = 'email' AND user_id = " . $save['id'], false);

		if ($cid) {
			db_execute("REPLACE INTO plugin_thold_contacts (id, user_id, type, data) VALUES ($cid, " . $save['id'] . ", 'email', '$email')");
		}else{
			db_execute("REPLACE INTO plugin_thold_contacts (user_id, type, data) VALUES (" . $save['id'] . ", 'email', '$email')");
		}
	}
	return $save;
}

function thold_config_settings () {
	global $tabs, $settings;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	define_syslog_variables();

	$tabs["alerts"] = "Alerting/Thold";
	$settings["alerts"] = array(
		"general_header" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"alert_base_url" => array(
			"friendly_name" => "Base URL",
			"description" => "Cacti base URL",
			"method" => "textbox",
			// Set the default only if called from "settings.php"
			"default" => ((isset($_SERVER["HTTP_HOST"]) && isset($_SERVER["PHP_SELF"]) && basename($_SERVER["PHP_SELF"]) == "settings.php") ? ("http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/") : ""),
			"max_length" => 255,
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
		"thold_alerting_header" => array(
			"friendly_name" => "Default Alerting Options",
			"method" => "spacer",
			),
		"alert_deadnotify" => array(
			"friendly_name" => "Dead Hosts Notifications",
			"description" => "Enable Dead/Recovering host notification",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_email" => array(
			"friendly_name" => "Dead Host Notifications Email",
			"description" => "This is the email address that the dead host notifications will be sent to.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"thold_send_text_only" => array(
			"friendly_name" => "Send alerts as text",
			"description" => "If checked, this will cause all alerts to be sent as plain text emails with no graph.  The default is HTML emails with the graph embedded in the email.",
			"method" => "checkbox",
			"default" => "off"
			),
		"alert_exempt" => array(
			"friendly_name" => "Weekend exemptions",
			"description" => "If this is checked, thold will not run on weekends.",
			"method" => "checkbox",
			),
		"alert_trigger" => array(
			"friendly_name" => "Default Trigger Count",
			"description" => "Number of consecutive times the data source must be in breach of the threshold for an alert to be raised",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 1
			),
		"alert_repeat" => array(
			"friendly_name" => "Re-Alerting",
			"description" => "Repeat alert after specified number of cycles.",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 12
			),
		"thold_alert_text" => array(
			"friendly_name" => "Alert Text Message",
			"description" => "This is the message that will be displayed at the top of all threshold alerts (255 Char MAX).  HTML is allowed, but will be removed for text only emails.  There are several descriptors that may be used.<br>&#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062  &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062",
			"method" => "textarea",
			"textarea_rows" => "3",
			"textarea_cols" => "60",
			"default" => '<html><body>An alert has been issued that requires your attention. <br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>',
			),
		"thold_baseline_header" => array(
			"friendly_name" => "Default Baseline Options",
			"method" => "spacer",
			),
		"alert_notify_bl" => array(
			"friendly_name" => "Baseline notifications",
			"description" => "Enable sending alert for baseline notifications",
			"method" => "checkbox",
			"default" => "on"
			),
		"alert_bl_trigger" => array(
			"friendly_name" => "Default Baseline Trigger Count",
			"description" => "Number of consecutive times the data source must be in breach of the calculated baseline threshold for an alert to be raised",
			"method" => "textbox",
			"size" => 4,
			"max_length" => 4,
			"default" => 2
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
			"friendly_name" => "Emailing Options",
			"method" => "spacer",
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
		);
}

