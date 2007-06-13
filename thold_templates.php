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
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

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
//		$save["thold_hi"] = $_POST["thold_hi"];
//		$save["thold_low"] = $_POST["thold_low"];
//		$save["thold_fail_trigger"] = $_POST["thold_fail_trigger"];

		$save["thold_enabled"] = 'on';
		$save["bl_enabled"] = 'off';
		$save["repeat_alert"] = read_config_option("alert_repeat");
//		$save["notify_extra"] = "";
		$save["notify_default"] = "NULL";
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

		$save["notify_default"] = $_POST["notify_default"];
		$save["notify_extra"] = $_POST["notify_extra"];
		$save["cdef"] = $_POST["cdef"];

		if (!is_error_message()) {
			$id = sql_save($save, "thold_template");
			if ($id) {
				raise_message(1);
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
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_trigger"),
			"description" => "Number of consecutive times the data source must be in breach of the threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_trigger") . " cycles</b>)",
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
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_repeat"),
			"description" => "Repeat alert after specified number of cycles.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_repeat") . " cycles</b>)",
			"value" => isset($thold_item_data["repeat_alert"]) ? $thold_item_data["repeat_alert"] : ""
		),
		
		"notify_default" => array(
			"friendly_name" => "Send notifications to default alert address",
			"method" => "drop_array",
			"default" => "NULL",
			"description" => "Determines if the notifications will be sent to e-mail address specified in global settings.",
			"value" => isset($thold_item_data["notify_default"]) ? $thold_item_data["notify_default"] : "",
			"array" => array("NULL" => "Use global control: " . (read_config_option("alert_notify_default") == "on" ? "On" : "Off"),
					"on" => "Force: On",
					"off" => "Force: Off")
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