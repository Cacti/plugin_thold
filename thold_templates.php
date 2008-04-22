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

chdir('../../');

include_once("./include/auth.php");
include_once($config["base_path"] . "/plugins/thold/thold_functions.php");

$ds_actions = array(
	1 => "Delete"
	);

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'delete';
}

switch ($action) {
	case 'add': 
		template_add();
		break;
	case 'edit': 
		include_once("./include/top_header.php");
		template_edit();
		include_once("./include/bottom_footer.php");
		break;
	case 'save': 
		if (isset($_POST['save']) && $_POST['save'] == 'edit') {
			template_save_edit();
		} else if (isset($_POST['save']) && $_POST['save'] == 'add') {

		}
		break;
	case 'delete':
		template_delete();
		break;
	default:
		include_once("./include/top_header.php");
		templates();
		include_once("./include/bottom_footer.php");
		break;
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == "chk_") {
			$id = substr($t, 4);
			db_fetch_assoc("delete from thold_template where id = $id LIMIT 1");
			db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id=' . $id);
			db_execute("UPDATE thold_data SET template = '', template_enabled = 'off' WHERE template = $id");

		}
	}

	Header("Location: thold_templates.php");
	exit;
}



function template_add() {
	global $colors;
	if (!isset($_POST['data_template_id'])) {

		$temp = db_fetch_assoc("select id, name from data_template order by name");
		$data_templates = array();
		foreach ($temp as $d) {
			$data_templates[$d['id']] = $d['name'];
		}

		$fields = array("friendly_name" => "Data Template",
				"method" => "drop_array",
				"default" => "NULL",
				"description" => "Data Template that you are using. (This can not be changed)",
				"value" => "",
				"array" => $data_templates);


		include_once("./include/top_header.php");
		html_start_box("", "50%", $colors["header"], "1", "center", "");
		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=thold_templates.php method='post'>";
		print "<input type=hidden name='action' value='add'>";
		print "<center><h2>Threshold Template Wizard</h2><br><br>";
		print "Please select a Data Template : ";
		draw_edit_control("data_template_id", $fields);

		print "<br><br><input type='image' src='../../images/button_go.gif' alt='Go'>";

		print '</form></center><br></td></tr></table>';
		html_end_box();
		include_once("./include/bottom_footer.php");
		return;
	} else if (isset($_POST['data_template_id']) && !isset($_POST['data_source_id'])){
		$data_template_id = $_POST['data_template_id'];

		$temp = db_fetch_assoc("select id, name from data_template where id=" . $data_template_id . " order by name");
		$data_templates = array();
		foreach ($temp as $d) {
			$data_templates[$d['id']] = $d['name'];
		}
		$data_fields = array();
		$temp = db_fetch_assoc("select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where local_data_template_rrd_id = 0 and data_template_id = " . $data_template_id);

		foreach ($temp as $d) {
			if ($d['data_input_field_id'] != 0)
				$temp2 = db_fetch_assoc("select name from data_input_fields where id = " . $d['data_input_field_id']);
			else
				$temp2[0]['name'] = $d['data_source_name'];
			$data_fields[$d['id']] = $temp2[0]['name'];
		}

		$fields2 = array("friendly_name" => "Data Template",
				"method" => "drop_array",
				"default" => "NULL",
				"description" => "",
				"value" => "",
				"array" => $data_templates);

		$fields = array("friendly_name" => "Data Template",
				"method" => "drop_array",
				"default" => "NULL",
				"description" => "",
				"value" => "",
				"array" => $data_fields);


		include_once("./include/top_header.php");
		html_start_box("", "50%", $colors["header"], "1", "center", "");
		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=thold_templates.php method='post'>";
		print "<input type=hidden name='action' value='add'>";
		print "<center><h2>Threshold Template Wizard</h2><br><br>";
		print "Date Template Name : ";
		draw_edit_control("data_template_id", $fields2);
		print "<br>";
		print "Please select a Data Field : ";
		draw_edit_control("data_source_id", $fields);

		print "<br><br><input type='image' src='../../images/button_go.gif' alt='Go'>";

		print '</form></center><br></td></tr></table>';
		html_end_box();
		include_once("./include/bottom_footer.php");
		return;
	} else if (isset($_POST['data_template_id']) && isset($_POST['data_source_id'])){
		$data_template_id = $_POST['data_template_id'];
		$data_source_id = $_POST['data_source_id'];
		
		$save["id"] = '';
		$save["data_template_id"] = $data_template_id;

		$temp = db_fetch_assoc("select id, name from data_template where id=" . $data_template_id);

		$save["data_template_name"] = $temp[0]["name"];
		$save["data_source_id"] = $data_source_id;

		$temp = db_fetch_assoc("select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where id = " . $data_source_id);

		$save["data_source_name"] = $temp[0]['data_source_name'];

		if ($temp[0]['data_input_field_id'] != 0)
			$temp2 = db_fetch_assoc("select name from data_input_fields where id = " . $temp[0]['data_input_field_id']);
		else
			$temp2[0]['name'] = $temp[0]['data_source_name'];

		$save["data_source_friendly"] = $temp2[0]['name'];
		$save["thold_enabled"] = 'on';
		$save["bl_enabled"] = 'off';
		$save["repeat_alert"] = read_config_option("alert_repeat");
		$id = sql_save($save, "thold_template");

		if ($id) {
			Header("Location: thold_templates.php?action=edit&id=$id");
			exit;
		} else {
			raise_message("thold_save");
			Header("Location: thold_templates.php?action=add");
			exit;
		}
	}
}

function template_save_edit() {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("thold_hi"));
		input_validate_input_number(get_request_var_post("thold_low"));
		input_validate_input_number(get_request_var_post("thold_fail_trigger"));
		input_validate_input_number(get_request_var_post("bl_ref_time"));
		input_validate_input_number(get_request_var_post("bl_ref_time_range"));
		input_validate_input_number(get_request_var_post("bl_pct_down"));
		input_validate_input_number(get_request_var_post("bl_pct_up"));
		input_validate_input_number(get_request_var_post("bl_fail_trigger"));
		input_validate_input_number(get_request_var_post("repeat_alert"));
		input_validate_input_number(get_request_var_post("cdef"));
		/* ==================================================== */

		/* save: data_template */
		$save["id"] = $_POST["id"];
		$save["thold_hi"] = $_POST["thold_hi"];
		$save["thold_low"] = $_POST["thold_low"];
		$save["thold_fail_trigger"] = $_POST["thold_fail_trigger"];

		if (isset($_POST["thold_fail_trigger"]) && $_POST["thold_fail_trigger"] != '')
			$save["thold_fail_trigger"] = $_POST["thold_fail_trigger"];
		else {
			$alert_trigger = read_config_option("alert_trigger");
			if ($alert_trigger != '' && is_numeric($alert_trigger))
				$save["thold_fail_trigger"] = $alert_trigger;
			else
				$save["thold_fail_trigger"] = 5;
		}

		if (isset($_POST["thold_enabled"]))
			$save["thold_enabled"] = 'on';
		else
			$save["thold_enabled"] = 'off';
		if (isset($_POST["bl_enabled"]))
			$save["bl_enabled"] = 'on';
		else
			$save["bl_enabled"] = 'off';
		if (isset($_POST["bl_ref_time"])  && $_POST['bl_ref_time'] != '')
			$save["bl_ref_time"] = $_POST["bl_ref_time"];
		else {
			$alert_bl_past_default = read_config_option("alert_bl_past_default");
			if ($alert_bl_past_default != '' && is_numeric($alert_bl_past_default))
				$save["bl_ref_time"] = $alert_bl_past_default;
			else
				$save["bl_ref_time"] = 86400;
		}
		if (isset($_POST["bl_ref_time_range"]) && $_POST['bl_ref_time_range'] != '')
			$save["bl_ref_time_range"] = $_POST["bl_ref_time_range"];
		else {
			$alert_bl_timerange_def = read_config_option("alert_bl_timerange_def");
			if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def))
				$save["bl_ref_time_range"] = $alert_bl_timerange_def;
			else
				$save["bl_ref_time_range"] = 10800;
		}
		if (isset($_POST["bl_pct_down"]) && $_POST["bl_pct_down"] != '')
			$save["bl_pct_down"] = $_POST["bl_pct_down"];
		if (isset($_POST["bl_pct_up"]) && $_POST["bl_pct_up"] != '')
			$save["bl_pct_up"] = $_POST["bl_pct_up"];
		if (isset($_POST["bl_fail_trigger"]) && $_POST["bl_fail_trigger"] != '')
			$save["bl_fail_trigger"] = $_POST["bl_fail_trigger"];
		else {
			$alert_bl_trigger = read_config_option("alert_bl_trigger");
			if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger))
				$save["bl_fail_trigger"] = $alert_bl_trigger;
			else
				$save["bl_fail_trigger"] = 3;
		}

		if (isset($_POST["repeat_alert"]) && $_POST["repeat_alert"] != '')
			$save["repeat_alert"] = $_POST["repeat_alert"];
		else {
			$alert_repeat = read_config_option("alert_repeat");
			if ($alert_repeat != '' && is_numeric($alert_repeat))
				$save["repeat_alert"] = $alert_repeat;
			else
				$save["repeat_alert"] = 12;
		}

		$save["notify_extra"] = $_POST["notify_extra"];
		$save["cdef"] = $_POST["cdef"];

		if (!is_error_message()) {
			$id = sql_save($save, "thold_template");
			if ($id) {
				raise_message(1);
				if (isset($_POST['notify_accounts'])) {
					thold_save_template_contacts ($id, $_POST['notify_accounts']);
				} else {
					thold_save_template_contacts ($id, array());
				}
				thold_template_update_thresholds ($id);
			}else{
				raise_message(2);
			}
		}

		if ((is_error_message()) || (empty($_POST["id"]))) {
			header("Location: thold_templates.php?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));
		}else{
			header("Location: thold_templates.php");
		}
}

function template_edit() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */
//	if (isset($_GET['id']))
		$id = $_GET['id'];
	$thold_item_data = db_fetch_assoc("select * from thold_template where id = " . $id);

	$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;


	$temp = db_fetch_assoc("select id, name from data_template where id = " . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		$data_templates[$d['id']] = $d['name'];
	}


	$temp = db_fetch_assoc("select id, data_source_name, data_input_field_id from data_template_rrd where id = " . $thold_item_data['data_source_id']);
	$source_id = $temp[0]['data_input_field_id'];

	if ($source_id != 0) {
		$temp2 = db_fetch_assoc("select id, name from data_input_fields where id = " . $source_id);
		foreach ($temp2 as $d) {
			$data_fields[$d['id']] = $d['name'];
		}
	} else {
		$data_fields[$temp[0]['id']]= $temp[0]['data_source_name'];
	}

	$send_notification_array = array();

	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data, plugin_thold_contacts.type, user_auth.full_name FROM plugin_thold_contacts, user_auth WHERE user_auth.id = plugin_thold_contacts.user_id AND plugin_thold_contacts.data != '' ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");
	if (!empty($users)) {
		foreach ($users as $user) {
			$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
		}
	}
	if (isset($thold_item_data['id'])) {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=' . $thold_item_data['id'];
	} else {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=0';
	}

	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE data_template_id = ' . $thold_item_data['data_template_id'], FALSE);
	if ($step == 60) {
		$repeatarray = array(0 => 'Never', 1 => 'Every Minute', 2 => 'Every 2 Minutes', 3 => 'Every 3 Minutes', 4 => 'Every 4 Minutes', 5 => 'Every 5 Minutes', 10 => 'Every 10 Minutes', 15 => 'Every 15 Minutes', 20 => 'Every 20 Minutes', 30 => 'Every 30 Minutes', 45 => 'Every 45 Minutes', 60 => 'Every Hour', 120 => 'Every 2 Hours', 180 => 'Every 3 Hours', 240 => 'Every 4 Hours', 360 => 'Every 6 Hours', 480 => 'Every 8 Hours', 720 => 'Every 12 Hours', 1440 => 'Every Day', 2880 => 'Every 2 Days', 10080 => 'Every Week', 20160 => 'Every 2 Weeks', 43200 => 'Every Month');
		$alertarray = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
	} else if ($step == 300) {
		$repeatarray = array(0 => 'Never', 1 => 'Every 5 Minutes', 2 => 'Every 10 Minutes', 3 => 'Every 15 Minutes', 4 => 'Every 20 Minutes', 6 => 'Every 30 Minutes', 8 => 'Every 45 Minutes', 12 => 'Every Hour', 24 => 'Every 2 Hours', 36 => 'Every 3 Hours', 48 => 'Every 4 Hours', 72 => 'Every 6 Hours', 96 => 'Every 8 Hours', 144 => 'Every 12 Hours', 288 => 'Every Day', 576 => 'Every 2 Days', 2016 => 'Every Week', 4032 => 'Every 2 Weeks', 8640 => 'Every Month');
		$alertarray = array(0 => 'Never', 1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => 'Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
	} else {
		$repeatarray = array(0 => 'Never', 1 => 'Every Polling', 2 => 'Every 2 Pollings', 3 => 'Every 3 Pollings', 4 => 'Every 4 Pollings', 6 => 'Every 6 Pollings', 8 => 'Every 8 Pollings', 12 => 'Every 12 Pollings', 24 => 'Every 24 Pollings', 36 => 'Every 36 Pollings', 48 => 'Every 48 Pollings', 72 => 'Every 72 Pollings', 96 => 'Every 96 Pollings', 144 => 'Every 144 Pollings', 288 => 'Every 288 Pollings', 576 => 'Every 576 Pollings', 2016 => 'Every 2016 Pollings');
		$alertarray = array(0 => 'Never', 1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
	}

	html_start_box("", "98%", $colors["header"], "3", "center", "");
	print "<form name='THold' action=thold_templates.php method=post><input type='hidden' name='save' value='edit'><input type='hidden' name='id' value='$id'>";
	$form_array = array(
		"general_header" => array(
			"friendly_name" => "Mandatory settings",
			"method" => "spacer",
		),
		"data_template_name" => array(
			"friendly_name" => "Data Template",
			"method" => "drop_array",
			"default" => "NULL",
			"description" => "Data Template that you are using. (This can not be changed)",
			"value" => $thold_item_data['data_template_id'],
			"array" => $data_templates,
		),
		"data_field_name" => array(
			"friendly_name" => "Data Field",
			"method" => "drop_array",
			"default" => "NULL",
			"description" => "Data Field that you are using.",
			"value" => $thold_item_data['data_source_id'],
			"array" => $data_fields,
		),
		"thold_enabled" => array(
			"friendly_name" => "Enabled",
			"method" => "checkbox",
			"default" => "on",
			"description" => "Whether or not this threshold will be checked and alerted upon.",
			"value" => isset($thold_item_data["thold_enabled"]) ? $thold_item_data["thold_enabled"] : ""
		),
		"thold_hi" => array(
			"friendly_name" => "High Threshold",
			"method" => "textbox",
			"max_length" => 100,
			"description" => "If set and data source value goes above this number, alert will be triggered",
			"value" => isset($thold_item_data["thold_hi"]) ? $thold_item_data["thold_hi"] : ""
		),
		"thold_low" => array(
			"friendly_name" => "Low Threshold",
			"method" => "textbox",
			"max_length" => 100,
			"description" => "If set and data source value goes below this number, alert will be triggered",
			"value" => isset($thold_item_data["thold_low"]) ? $thold_item_data["thold_low"] : ""
		),
		"thold_fail_trigger" => array(
			"friendly_name" => "Trigger Count",
			'method' => 'drop_array',
			'array' => $alertarray,
			"default" => read_config_option("alert_trigger"),
			"description" => "The amount of time the data source must be in breach of the threshold for an alert to be raised.",
			"value" => isset($thold_item_data["thold_fail_trigger"]) ? $thold_item_data["thold_fail_trigger"] : ""
		),
		"baseline_header" => array(
			"friendly_name" => "Baseline monitoring",
			"method" => "spacer",
		),
		"bl_enabled" => array(
			"friendly_name" => "Baseline monitoring",
			"method" => "checkbox",
			"default" => "off",
			"description" => "When enabled, baseline monitoring checks the current data source value against a value in the past. The available range of values is retrieved and a minimum and maximum values are taken as a respective baseline reference. The precedence however is on the &quot;hard&quot; thresholds above.",
			"value" => isset($thold_item_data["bl_enabled"]) ? $thold_item_data["bl_enabled"] : ""
		),
		"bl_ref_time" => array(
			"friendly_name" => "Reference in the past",
			"method" => "textbox",
			"max_length" => 20,
			"default" => read_config_option("alert_bl_past_default"),
			"description" => "Specifies the relative point in the past that will be used as a reference. The value represents seconds, so for a day you would specify 86400, for a week 604800, etc.",
			"value" => isset($thold_item_data["bl_ref_time"]) ? $thold_item_data["bl_ref_time"] : ""
		),
		"bl_ref_time_range" => array(
			"friendly_name" => "Time range",
			"method" => "textbox",
			"max_length" => 20,
			"default" => read_config_option("alert_bl_timerange_def"),
			"description" => "Specifies the time range of values in seconds to be taken from the reference in the past",
			"value" => isset($thold_item_data["bl_ref_time_range"]) ? $thold_item_data["bl_ref_time_range"] : ""
		),
		"bl_pct_up" => array(
			"friendly_name" => "Baseline deviation UP",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"description" => "Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.",
			"value" => isset($thold_item_data["bl_pct_up"]) ? $thold_item_data["bl_pct_up"] : ""
		),
		"bl_pct_down" => array(
			"friendly_name" => "Baseline deviation DOWN",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"description" => "Specifies allowed deviation in percentage for the lower bound threshold. If not set, lower bound threshold will not be checked at all.",
			"value" => isset($thold_item_data["bl_pct_down"]) ? $thold_item_data["bl_pct_down"] : ""
		),
		"bl_fail_trigger" => array(
			"friendly_name" => "Baseline Trigger Count",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_bl_trigger"),
			"description" => "Number of consecutive times the data source must be in breach of the baseline threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_bl_trigger") . " cycles</b>)",
			"value" => isset($thold_item_data["bl_fail_trigger"]) ? $thold_item_data["bl_fail_trigger"] : ""
		),
		"other_header" => array(
			"friendly_name" => "Other setting",
			"method" => "spacer",
		),
		"cdef" => array(
			"friendly_name" => "Threshold CDEF",
			"method" => "drop_array",
			"default" => "NULL",
			"description" => "Apply this CDEF before returning the data.",
			"value" => isset($thold_item_data["cdef"]) ? $thold_item_data["cdef"] : 0,
			"array" => thold_cdef_select_usable_names()
		),
		"repeat_alert" => array(
			"friendly_name" => "Re-Alert Cycle",
			"method" => "drop_array",
			'array' => $repeatarray,
			"default" => read_config_option("alert_repeat"),
			"description" => "Repeat alert after this amount of time has pasted since the last alert.",
			"value" => isset($thold_item_data["repeat_alert"]) ? $thold_item_data["repeat_alert"] : ""
		),
		"notify_accounts" => array(
			"friendly_name" => "Notify accounts",
			"method" => "drop_multi",
			"description" => "This is a listing of accounts that will be notified when this threshold is breached.<br><br><br><br>",
			"array" => $send_notification_array,
			"sql" => $sql,
		),
		"notify_extra" => array(
			"friendly_name" => "Alert E-Mail",
			"method" => "textbox",
			"max_length" => 255,
			"description" => "You may specify here extra e-mails to receive alerts for this data source (comma separated)",
			"value" => isset($thold_item_data["notify_extra"]) ? $thold_item_data["notify_extra"] : ""
		),
	);

	draw_edit_form(
		array(
			"config" => array(
				"no_form_tag" => true
				),
			"fields" => $form_array
			)
	);

	html_end_box();
	form_save_button("thold_templates.php?id=" . $id, "save");

	?>
	<!-- Make it look intelligent :) -->
	<script language="JavaScript">
	function BL_EnableDisable()
	{
		var _f = document.THold;
		var status = !_f.bl_enabled.checked;
	
		_f.bl_ref_time.disabled = status;
		_f.bl_ref_time_range.disabled = status;
		_f.bl_pct_down.disabled = status;
		_f.bl_pct_up.disabled = status;
		_f.bl_fail_trigger.disabled = status;
	}

	BL_EnableDisable();
	document.THold.bl_enabled.onclick = BL_EnableDisable;

	</script>
	<?php

}


function templates() {
	global $colors, $ds_actions;

	html_start_box("<strong>Threshold Templates</strong>", "98%", $colors["header"], "3", "center", "thold_templates.php?action=add");

	html_header_checkbox(array("Data Template", "Data Source Name", "High", "Low", "Trigger", "Repeat", "Email"));

	$template_list = db_fetch_assoc("select *
		from thold_template
		order by data_template_name");

	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="thold_templates.php?action=edit&id=<?php print $template["id"];?>"><?php print $template["data_template_name"];?></a>
			</td>
			<td>
				<?php print $template['data_source_friendly'];?>
			</td>
			<td>
				<?php print $template['thold_hi'];?>
			</td>
			<td>
				<?php print $template['thold_low'];?>
			</td>
			<td>
				<?php print $template['thold_fail_trigger'];?>
			</td>
			<td>
				<?php print $template['repeat_alert'];?>
			</td>
			<td>
				<?php print $template['notify_extra'];?>
			</td>

			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $template["id"];?>' title="<?php print $template["data_template_name"];?>">
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		print "<tr><td><em>No Data Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	print "</form>\n";
}

?>
