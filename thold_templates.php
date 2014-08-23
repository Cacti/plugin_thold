<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2014 The Cacti Group                                      |
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

include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

$thold_actions = array(
	1 => 'Export',
	2 => 'Delete'
);

$action = '';
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} elseif (isset($_GET['action'])) {
	$action = $_GET['action'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 2) {
	$action = 'delete';
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'export';
}

if (isset($_REQUEST['import'])) {
	$action = 'import';
}

switch ($action) {
	case 'add':
		template_add();
		break;
	case 'save':
		if (isset($_POST["save_component_import"])) {
			template_import();
		}elseif (isset($_POST['save']) && $_POST['save'] == 'edit') {
			template_save_edit();

			if (isset($_SESSION["graph_return"])) {
				$return_to = $_SESSION["graph_return"];
				unset($_SESSION["graph_return"]);
				kill_session_var("graph_return");
				header('Location: ' . $return_to);
			}
		} elseif (isset($_POST['save']) && $_POST['save'] == 'add') {

		}

		break;
	case 'delete':
		template_delete();

		break;
	case 'export':
		template_export();

		break;
	case 'import':
		include_once('./include/top_header.php');
		import();
		include_once('./include/bottom_footer.php');

		break;
	case 'edit':
		include_once('./include/top_header.php');
		template_edit();
		include_once('./include/bottom_footer.php');

		break;
	default:
		include_once('./include/top_header.php');
		templates();
		include_once('./include/bottom_footer.php');

		break;
}

function template_export() {
	$output = "<templates>\n";
	if (sizeof($_POST)) {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);

			if (is_numeric($id)) {
				$data = db_fetch_row("SELECT * FROM thold_template WHERE id=$id");
				if (sizeof($data)) {
					$data_template_hash = db_fetch_cell("SELECT hash
						FROM data_template
						WHERE id=" . $data["data_template_id"]);

					$data_source_hash   = db_fetch_cell("SELECT hash
						FROM data_template_rrd
						WHERE id=" . $data["data_source_id"]);

					unset($data['id']);
					$data['data_template_id'] = $data_template_hash;
					$data['data_source_id']   = $data_source_hash;
					$output .= array2xml($data);
				}
			}
		}
	}
	}

	$output .= "</templates>";

	header("Content-type: application/xml");
	header("Content-Disposition: attachment; filename=thold_template_export.xml");

	print $output;

	exit;
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);
			input_validate_input_number($id);
			plugin_thold_log_changes($id, 'deleted_template', array('id' => $id));
			db_fetch_assoc("delete from thold_template where id = $id LIMIT 1");
			db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id=' . $id);
			db_execute("UPDATE thold_data SET template = '', template_enabled = 'off' WHERE template = $id");
		}
	}

	Header('Location: thold_templates.php');
	exit;
}

function template_add() {
	global $colors;

	if ((!isset($_REQUEST['save'])) || ($_REQUEST['save'] == '')) {
		$data_templates = array_rekey(db_fetch_assoc('select id, name from data_template order by name'), "id", "name");

		include_once('./include/top_header.php');

		?>
		<script type="text/javascript">
		<!--
		function applyTholdFilterChange(objForm, type) {
			if ((type == 'dt') && (document.getElementById("data_source_id"))) {
				document.getElementById("data_source_id").value = "";
			}

			if (document.getElementById("save")) {
				document.getElementById("save").value = "";
			}

			document.tholdform.submit();
		}
		-->
		</script>
		<?php

		html_start_box('<strong>Threshold Template Creation Wizard</strong>', '50%', $colors['header'], '3', 'center', '');

		print "<tr><td><form action=thold_templates.php method='post' name='tholdform'>";

		if (!isset($_REQUEST["data_template_id"])) $_REQUEST["data_template_id"] = '';
		if (!isset($_REQUEST["data_source_id"])) $_REQUEST["data_source_id"] = '';

		if ($_REQUEST["data_template_id"] == '') {
			print '<center><h3>Please select a Data Template</h3></center>';
		} else if ($_REQUEST["data_source_id"] == '') {
			print '<center><h3>Please select a Data Source</h3></center>';
		} else {
			print '<center><h3>Please press "Create" to create your Threshold Template</h3></center>';
		}

		/* display the data template dropdown */
		?>
		<table align='center'>
			<tr>
				<td width='70' style='white-space:nowrap;'>
					&nbsp;<b>Data Template:</b>
				</td>
				<td style='width:1;'>
					<select name=data_template_id onChange="applyTholdFilterChange(document.tholdform, 'dt')">
						<option value="">None</option><?php
						foreach ($data_templates as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == $_REQUEST['data_template_id'] ? ' selected' : '') . '>' . $name . '</option>';
						}?>
					</select>
				</td>
			</tr><?php

		if ($_REQUEST['data_template_id'] != '') {
			$data_template_id = $_REQUEST['data_template_id'];
			$data_fields      = array();
			$temp             = db_fetch_assoc('select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where local_data_template_rrd_id = 0 and data_template_id = ' . $data_template_id);

			foreach ($temp as $d) {
				if ($d['data_input_field_id'] != 0) {
					$temp2 = db_fetch_assoc('select name, data_name from data_input_fields where id = ' . $d['data_input_field_id']);
					$data_fields[$d['id']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
				} else {
					$temp2[0]['name'] = $d['data_source_name'];
					$data_fields[$d['id']] = $temp2[0]['name'];
				}
			}

			/* display the data source dropdown */
			?>
			<tr>
				<td width='70' style='white-space:nowrap;'>
					&nbsp;<b>Data Source:</b>
				</td>
				<td>
					<select id='data_source_id' name='data_source_id' onChange="applyTholdFilterChange(document.tholdform, 'ds')">
						<option value="">None</option><?php
						foreach ($data_fields as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == $_REQUEST['data_source_id'] ? ' selected' : '') . '>' . $name . '</option>';
						}?>
					</select>
				</td>
			</tr>
			<?php
		}

		if ($_REQUEST["data_source_id"] != '') {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><input id="save" type=hidden name="save" value="save"><br><center><input type="submit" value="Create"></center></td></tr>';
		} else {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><br><br><br></td></tr>';
		}
		echo '</table></form></td></tr>';
		html_end_box();
		include_once('./include/bottom_footer.php');
	} else {
		$data_template_id = $_REQUEST['data_template_id'];
		$data_source_id = $_REQUEST['data_source_id'];

		$save['id'] = '';
		$save['hash'] = get_hash_thold_template(0);
		$save['data_template_id'] = $data_template_id;

		$temp = db_fetch_assoc('select id, name from data_template where id=' . $data_template_id);
		$save['name'] = $temp[0]['name'];
		$save['data_template_name'] = $temp[0]['name'];
		$save['data_source_id'] = $data_source_id;

		$temp = db_fetch_assoc('select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where id = ' . $data_source_id);

		$save['data_source_name'] = $temp[0]['data_source_name'];
		$save['name'] .= ' [' . $temp[0]['data_source_name'] . ']';

		if ($temp[0]['data_input_field_id'] != 0) {
			$temp2 = db_fetch_assoc('select name from data_input_fields where id = ' . $temp[0]['data_input_field_id']);
		} else {
			$temp2[0]['name'] = $temp[0]['data_source_name'];
		}

		$save['data_source_friendly'] = $temp2[0]['name'];
		$save['thold_enabled'] = 'on';
		$save['thold_type'] = 0;
		$save['repeat_alert'] = read_config_option('alert_repeat');
		$id = sql_save($save, 'thold_template');

		if ($id) {
			plugin_thold_log_changes($id, 'modified_template', $save);
			Header("Location: thold_templates.php?action=edit&id=$id");
			exit;
		} else {
			raise_message('thold_save');
			Header('Location: thold_templates.php?action=add');
			exit;
		}
	}
}

function template_save_edit() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('thold_type'));
	input_validate_input_number(get_request_var_post('thold_hi'));
	input_validate_input_number(get_request_var_post('thold_low'));
	input_validate_input_number(get_request_var_post('thold_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_hi'));
	input_validate_input_number(get_request_var_post('time_low'));
	input_validate_input_number(get_request_var_post('time_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_fail_length'));
	input_validate_input_number(get_request_var_post('thold_warning_type'));
	input_validate_input_number(get_request_var_post('thold_warning_hi'));
	input_validate_input_number(get_request_var_post('thold_warning_low'));
	input_validate_input_number(get_request_var_post('thold_warning_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_warning_hi'));
	input_validate_input_number(get_request_var_post('time_warning_low'));
	input_validate_input_number(get_request_var_post('time_warning_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_warning_fail_length'));
	input_validate_input_number(get_request_var_post('bl_ref_time_range'));
	input_validate_input_number(get_request_var_post('bl_pct_down'));
	input_validate_input_number(get_request_var_post('bl_pct_up'));
	input_validate_input_number(get_request_var_post('bl_fail_trigger'));
	input_validate_input_number(get_request_var_post('repeat_alert'));
	input_validate_input_number(get_request_var_post('data_type'));
	input_validate_input_number(get_request_var_post('cdef'));
	input_validate_input_number(get_request_var_post('notify_warning'));
	input_validate_input_number(get_request_var_post('notify_alert'));
	input_validate_input_number(get_request_var_post('snmp_event_severity'));
	input_validate_input_number(get_request_var_post('snmp_event_warning_severity'));
	/* ==================================================== */

	/* clean up date1 string */
	if (isset($_POST['name'])) {
		$_POST['name'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('name')));
	}

	if (isset($_POST['snmp_trap_category'])) {
		$_POST['snmp_event_category'] = mysql_real_escape_string( trim ( str_replace(array("\\", "'", '"'), '', get_request_var_post('snmp_event_category')) ) );
	}

	/* save: data_template */
	$save['id'] = $_POST['id'];
	$save['hash'] = get_hash_thold_template($save['id']);
	$save['name'] = $_POST['name'];
	$save['thold_type'] = $_POST['thold_type'];

	// High / Low
	$save['thold_hi'] = $_POST['thold_hi'];
	$save['thold_low'] = $_POST['thold_low'];
	$save['thold_fail_trigger'] = $_POST['thold_fail_trigger'];
	// Time Based
	$save['time_hi'] = $_POST['time_hi'];
	$save['time_low'] = $_POST['time_low'];

	$save['time_fail_trigger'] = $_POST['time_fail_trigger'];
	$save['time_fail_length'] = $_POST['time_fail_length'];

	if (isset($_POST['thold_fail_trigger']) && $_POST['thold_fail_trigger'] != '') {
		$save['thold_fail_trigger'] = $_POST['thold_fail_trigger'];
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_fail_trigger'] = 5;
		}
	}

	/***  Warnings  ***/
	// High / Low Warnings
	$save['thold_warning_hi'] = $_POST['thold_warning_hi'];
	$save['thold_warning_low'] = $_POST['thold_warning_low'];
	$save['thold_warning_fail_trigger'] = $_POST['thold_warning_fail_trigger'];
	// Time Based Warnings
	$save['time_warning_hi'] = $_POST['time_warning_hi'];
	$save['time_warning_low'] = $_POST['time_warning_low'];

	$save['time_warning_fail_trigger'] = $_POST['time_warning_fail_trigger'];
	$save['time_warning_fail_length'] = $_POST['time_warning_fail_length'];

	if (isset($_POST['thold_warning_fail_trigger']) && $_POST['thold_warning_fail_trigger'] != '') {
		$save['thold_warning_fail_trigger'] = $_POST['thold_warning_fail_trigger'];
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_warning_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_warning_fail_trigger'] = 5;
		}
	}

	if (isset($_POST['thold_enabled'])) {
		$save['thold_enabled'] = 'on';
	} else {
		$save['thold_enabled'] = 'off';
	}

	if (isset($_POST['exempt'])) {
		$save['exempt'] = 'on';
	} else {
		$save['exempt'] = 'off';
	}

	if (isset($_POST['restored_alert'])) {
		$save['restored_alert'] = 'on';
	} else {
		$save['restored_alert'] = 'off';
	}

	if (isset($_POST['bl_ref_time_range']) && $_POST['bl_ref_time_range'] != '') {
		$save['bl_ref_time_range'] = $_POST['bl_ref_time_range'];
	} else {
		$alert_bl_timerange_def = read_config_option('alert_bl_timerange_def');
		if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def)) {
			$save['bl_ref_time_range'] = $alert_bl_timerange_def;
		} else {
			$save['bl_ref_time_range'] = 10800;
		}
	}

	$save['bl_pct_down'] = $_POST['bl_pct_down'];
	$save['bl_pct_up'] = $_POST['bl_pct_up'];

	if (isset($_POST['bl_fail_trigger']) && $_POST['bl_fail_trigger'] != '') {
		$save['bl_fail_trigger'] = $_POST['bl_fail_trigger'];
	} else {
		$alert_bl_trigger = read_config_option('alert_bl_trigger');
		if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger)) {
			$save['bl_fail_trigger'] = $alert_bl_trigger;
		} else {
			$save['bl_fail_trigger'] = 3;
		}
	}

	if (isset($_POST['repeat_alert']) && $_POST['repeat_alert'] != '') {
		$save['repeat_alert'] = $_POST['repeat_alert'];
	} else {
		$alert_repeat = read_config_option('alert_repeat');
		if ($alert_repeat != '' && is_numeric($alert_repeat)) {
			$save['repeat_alert'] = $alert_repeat;
		} else {
			$save['repeat_alert'] = 12;
		}
	}

	if (isset($_POST['snmp_event_category'])) {
		$save['snmp_event_category'] = $_POST['snmp_event_category'];
		$save['snmp_event_severity'] = $_POST['snmp_event_severity'];
	}
	if (isset($_POST["snmp_event_warning_severity"])) {
		if($_POST['snmp_event_warning_severity'] > $_POST['snmp_event_severity']) {
			$save['snmp_event_warning_severity'] = $_POST['snmp_event_severity'];
		}else {
			$save['snmp_event_warning_severity'] = $_POST['snmp_event_warning_severity'];
		}
	}

	$save['notify_extra'] = $_POST['notify_extra'];
	$save['notify_warning_extra'] = $_POST['notify_warning_extra'];
	$save['notify_warning'] = $_POST['notify_warning'];
	$save['notify_alert'] = $_POST['notify_alert'];
	$save['cdef'] = $_POST['cdef'];

	$save['data_type']  = $_POST['data_type'];
	$save['percent_ds'] = $_POST['percent_ds'];
	$save['expression'] = $_POST['expression'];



	if (!is_error_message()) {
		$id = sql_save($save, 'thold_template');
		if ($id) {
			raise_message(1);
			if (isset($_POST['notify_accounts']) && is_array($_POST['notify_accounts'])) {
				thold_save_template_contacts ($id, $_POST['notify_accounts']);
			} elseif (!isset($_POST['notify_accounts'])) {
				thold_save_template_contacts ($id, array());
			}
			thold_template_update_thresholds ($id);

			plugin_thold_log_changes($id, 'modified_template', $save);
		} else {
			raise_message(2);
		}
	}

	if ((is_error_message()) || (empty($_POST['id']))) {
		header('Location: thold_templates.php?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
	} else {
		header('Location: thold_templates.php');
	}
}

function template_edit() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */
	$id = $_GET['id'];
	$thold_item_data = db_fetch_assoc('SELECT * FROM thold_template WHERE id=' . $id);

	$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;


	$temp = db_fetch_assoc('SELECT id, name FROM data_template WHERE id=' . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		$data_templates[$d['id']] = $d['name'];
	}

	$temp = db_fetch_assoc('SELECT id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE id=' . $thold_item_data['data_source_id']);

	$source_id = $temp[0]['data_input_field_id'];

	if ($source_id != 0) {
		$temp2 = db_fetch_assoc('SELECT id, name FROM data_input_fields WHERE id=' . $source_id);
		foreach ($temp2 as $d) {
			$data_fields[$d['id']] = $d['name'];
		}
	} else {
		$data_fields[$temp[0]['id']]= $temp[0]['data_source_name'];
	}

	$send_notification_array = array();

	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data,
		plugin_thold_contacts.type, user_auth.full_name
		FROM plugin_thold_contacts, user_auth
		WHERE user_auth.id=plugin_thold_contacts.user_id
		AND plugin_thold_contacts.data!=''
		ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

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
		$alertarray  = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
		$timearray   = array(1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 6 => '6 Minutes', 7 => '7 Minutes', 8 => '8 Minutes', 9 => '9 Minutes', 10 => '10 Minutes', 12 => '12 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 24 => '24 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 288 => '4.8 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
	} else if ($step == 300) {
		$repeatarray = array(0 => 'Never', 1 => 'Every 5 Minutes', 2 => 'Every 10 Minutes', 3 => 'Every 15 Minutes', 4 => 'Every 20 Minutes', 6 => 'Every 30 Minutes', 8 => 'Every 45 Minutes', 12 => 'Every Hour', 24 => 'Every 2 Hours', 36 => 'Every 3 Hours', 48 => 'Every 4 Hours', 72 => 'Every 6 Hours', 96 => 'Every 8 Hours', 144 => 'Every 12 Hours', 288 => 'Every Day', 576 => 'Every 2 Days', 2016 => 'Every Week', 4032 => 'Every 2 Weeks', 8640 => 'Every Month');
		$alertarray  = array(0 => 'Never', 1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => '1 Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
		$timearray   = array(1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => '1 Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
	} else {
		$repeatarray = array(0 => 'Never', 1 => 'Every Polling', 2 => 'Every 2 Pollings', 3 => 'Every 3 Pollings', 4 => 'Every 4 Pollings', 6 => 'Every 6 Pollings', 8 => 'Every 8 Pollings', 12 => 'Every 12 Pollings', 24 => 'Every 24 Pollings', 36 => 'Every 36 Pollings', 48 => 'Every 48 Pollings', 72 => 'Every 72 Pollings', 96 => 'Every 96 Pollings', 144 => 'Every 144 Pollings', 288 => 'Every 288 Pollings', 576 => 'Every 576 Pollings', 2016 => 'Every 2016 Pollings');
		$alertarray  = array(0 => 'Never', 1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
		$timearray   = array(1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
	}

	$thold_types = array (
		0 => 'High / Low Values',
		1 => 'Baseline Deviation',
		2 => 'Time Based',
	);

	$data_types = array (
		0 => 'Exact Value',
		1 => 'CDEF',
		2 => 'Percentage',
		3 => 'RPN Expression'
	);

	$rra_steps = db_fetch_assoc("SELECT rra.steps
		FROM data_template_data d
		JOIN data_template_data_rra a
	    ON d.id=a.data_template_data_id
		JOIN rra
		ON a.rra_id=rra.id
	    WHERE rra.steps>1
		AND d.data_template_id=" . $thold_item_data['data_template_id'] . "
	    AND d.local_data_template_data_id=0
		ORDER BY steps");

	$reference_types = array();
	foreach($rra_steps as $rra_step) {
	    $seconds = $step * $rra_step['steps'];
	    $reference_types[$seconds] = $timearray[$rra_step['steps']] . " Average" ;
	}

	$data_fields2 = array();
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name,
		data_input_field_id
		FROM data_template_rrd
		WHERE local_data_template_rrd_id=0
		AND data_template_id=' . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		if ($d['data_input_field_id'] != 0) {
			$temp2 = db_fetch_assoc('SELECT id, name, data_name
				FROM data_input_fields
				WHERE id=' . $d['data_input_field_id'] . '
				ORDER BY data_name');

			$data_fields2[$d['data_source_name']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
		} else {
			$temp2[0]['name'] = $d['data_source_name'];
			$data_fields2[$d['data_source_name']] = $temp2[0]['name'];
		}
	}

	$replacements = db_fetch_assoc("SELECT DISTINCT field_name
		FROM data_local AS dl
		INNER JOIN (SELECT DISTINCT field_name, snmp_query_id FROM host_snmp_cache) AS hsc
		ON dl.snmp_query_id=hsc.snmp_query_id
		WHERE dl.data_template_id=" . $thold_item_data['data_template_id']);

	$nr = array();
	if (sizeof($replacements)) {
	foreach($replacements as $r) {
		$nr[] = "<span style='color:blue;'>|query_" . $r['field_name'] . "|</span>";
	}
	}

	$vhf = explode("|", trim(VALID_HOST_FIELDS, "()"));
	if (sizeof($vhf)) {
	foreach($vhf as $r) {
		$nr[] = "<span style='color:blue;'>|" . $r . "|</span>";
	}
	}

	$replacements = "<br><b>Replacement Fields:</b> " . implode(", ", $nr);

	$dss = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE data_template_id=" . $thold_item_data['data_template_id'] . " AND local_data_id=0");

	if (sizeof($dss)) {
	foreach($dss as $ds) {
		$dsname[] = "<span style='color:blue;'>|ds:" . $ds["data_source_name"] . "|</span>";
	}
	}

	$datasources = "<br><b>Data Sources:</b> " . implode(", ", $dsname);

	print "<form name='THold' action='thold_templates.php' method='post'>\n";

	html_start_box('', '100%', $colors['header'], '3', 'center', '');
	$form_array = array(
		'general_header' => array(
			'friendly_name' => 'Mandatory settings',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => 'Template Name',
			'method' => 'textbox',
			'max_length' => 100,
			'default' => $thold_item_data['data_template_name'] . ' [' . $thold_item_data['data_source_name'] . ']',
			'description' => 'Provide the THold Template a meaningful name.  Host Substritution and Data Query Substitution variables can be used as well as |graph_title| for the Graph Title',
			'value' => isset($thold_item_data['name']) ? $thold_item_data['name'] : ''
		),
		'data_template_name' => array(
			'friendly_name' => 'Data Template',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Template that you are using. (This can not be changed)',
			'value' => $thold_item_data['data_template_id'],
			'array' => $data_templates,
		),
		'data_field_name' => array(
			'friendly_name' => 'Data Field',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Field that you are using. (This can not be changed)',
			'value' => $thold_item_data['id'],
			'array' => $data_fields,
		),
		'thold_enabled' => array(
			'friendly_name' => 'Enabled',
			'method' => 'checkbox',
			'default' => 'on',
			'description' => 'Whether or not this threshold will be checked and alerted upon.',
			'value' => isset($thold_item_data['thold_enabled']) ? $thold_item_data['thold_enabled'] : ''
		),
		'exempt' => array(
			'friendly_name' => 'Weekend Exemption',
			'description' => 'If this is checked, this Threshold will not alert on weekends.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['exempt']) ? $thold_item_data['exempt'] : ''
			),
		'restored_alert' => array(
			'friendly_name' => 'Disable Restoration Email',
			'description' => 'If this is checked, Thold will not send an alert when the threshold has returned to normal status.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['restored_alert']) ? $thold_item_data['restored_alert'] : ''
			),
		'thold_type' => array(
			'friendly_name' => 'Threshold Type',
			'method' => 'drop_array',
			'on_change' => 'changeTholdType()',
			'array' => $thold_types,
			'default' => read_config_option('thold_type'),
			'description' => 'The type of Threshold that will be monitored.',
			'value' => isset($thold_item_data['thold_type']) ? $thold_item_data['thold_type'] : ''
		),
		'repeat_alert' => array(
			'friendly_name' => 'Re-Alert Cycle',
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => read_config_option('alert_repeat'),
			'description' => 'Repeat alert after this amount of time has pasted since the last alert.',
			'value' => isset($thold_item_data['repeat_alert']) ? $thold_item_data['repeat_alert'] : ''
		),
		'thold_warning_header' => array(
			'friendly_name' => 'High / Low Warning Settings',
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => 'High Warning Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_warning_hi']) ? $thold_item_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => 'Low Warning Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_warning_low']) ? $thold_item_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => 'Min Warning Trigger Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in a breach condition for an alert to be raised.',
			'value' => isset($thold_item_data['thold_warning_fail_trigger']) ? $thold_item_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => 'High / Low Settings',
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_hi']) ? $thold_item_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_low']) ? $thold_item_data['thold_low'] : ''
		),
		'thold_fail_trigger' => array(
			'friendly_name' => 'Min Trigger Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in a breach condition for an alert to be raised.',
			'value' => isset($thold_item_data['thold_fail_trigger']) ? $thold_item_data['thold_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_header' => array(
			'friendly_name' => 'Time Based Warning Settings',
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => 'High Warning Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_hi']) ? $thold_item_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => 'Low Warning Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_low']) ? $thold_item_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => 'Warning Trigger Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'default' => read_config_option('thold_warning_time_fail_trigger'),
			'description' => 'The number of times the data source must be in breach condition prior to issuing a warning.',
			'value' => isset($thold_item_data['time_warning_fail_trigger']) ? $thold_item_data['time_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => 'Warning Time Period Length',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_warning_fail_length']) ? $thold_item_data['time_warning_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
		),
		'time_header' => array(
			'friendly_name' => 'Time Based Settings',
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['time_hi']) ? $thold_item_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['time_low']) ? $thold_item_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => 'Trigger Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'description' => 'The number of times the data source must be in breach condition prior to issuing an alert.',
			'value' => isset($thold_item_data['time_fail_trigger']) ? $thold_item_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => 'Time Period Length',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_fail_length']) ? $thold_item_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 2)
		),
		'baseline_header' => array(
			'friendly_name' => 'Baseline Monitoring',
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => 'Time reference in the past',
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => 'Specifies the point in the past (based on rrd resolution) that will be used as a reference',
			'value' => isset($thold_item_data['bl_ref_time_range']) ? $thold_item_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => 'Baseline Deviation UP',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_up']) ? $thold_item_data['bl_pct_up'] : read_config_option("alert_bl_percent_def")
		),
		'bl_pct_down' => array(
			'friendly_name' => 'Baseline Deviation DOWN',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the lower bound threshold. If not set, lower bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_down']) ? $thold_item_data['bl_pct_down'] : read_config_option("alert_bl_percent_def")
		),
		'bl_fail_trigger' => array(
			'friendly_name' => 'Baseline Trigger Count',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Number of consecutive times the data source must be in a breached condition for an alert to be raised.<br>Leave empty to use default value (<b>Default: ' . read_config_option('alert_bl_trigger') . ' cycles</b>)',
			'value' => isset($thold_item_data['bl_fail_trigger']) ? $thold_item_data['bl_fail_trigger'] : read_config_option("alert_bl_trigger")
		),
		'data_manipulation' => array(
			'friendly_name' => 'Data Manipulation',
			'method' => 'spacer',
		),
		'data_type' => array(
			'friendly_name' => 'Data Type',
			'method' => 'drop_array',
			'on_change' => 'changeDataType()',
			'array' => $data_types,
			'description' => 'Special formatting for the given data.',
			'value' => isset($thold_item_data['data_type']) ? $thold_item_data['data_type'] : read_config_option('data_type')
		),
		'cdef' => array(
			'friendly_name' => 'Threshold CDEF',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Apply this CDEF before returning the data.',
			'value' => isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => 'Percent Datasource',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Second Datasource Item to use as total value to calculate percentage from.',
			'value' => isset($thold_item_data['percent_ds']) ? $thold_item_data['percent_ds'] : 0,
			'array' => $data_fields2,
		),
		'expression' => array(
			'friendly_name' => 'RPN Expression',
			'method' => 'textbox',
			'default' => '',
			'description' => 'An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes
			all functions below in addition to both Host and Data Query replacement expressions such as
			<span style="color:blue;">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span style="color:blue;">|ds:dsname|</span>.  For example, <span style="color:blue;">|ds:traffic_in|</span> will get the current value
			of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br>Math Operators: <span style="color:blue;">+, -, /, *, %, ^</span><br>Functions: <span style="color:blue;">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br>Flow Operators: <span style="color:blue;">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br>Comparison Functions: <span style="color:blue;">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>'.$replacements.$datasources,
			'value' => isset($thold_item_data['expression']) ? $thold_item_data['expression'] : '',
			'max_length' => '255',
			'size' => '80'
		),
		'other_header' => array(
			'friendly_name' => 'Other Settings',
			'method' => 'spacer',
		),
		'notify_warning' => array(
			'friendly_name' => 'Warning Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Warnings for this Data Source',
			'value' => isset($thold_item_data['notify_warning']) ? $thold_item_data['notify_warning'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		),
		'notify_alert' => array(
			'friendly_name' => 'Alert Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Alerts for this Data Source',
			'value' => isset($thold_item_data['notify_alert']) ? $thold_item_data['notify_alert'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		)
	);

	if (read_config_option("thold_alert_snmp") == 'on') {
		$extra = array(
			'snmp_event_category' => array(
				'friendly_name' => 'SNMP Notification - Event Category',
				'method' => 'textbox',
				'description' => 'To allow a NMS to categorize different SNMP notifications more easily please fill in the category SNMP notifications for this template should make use of. E.g.: "disk_usage", "link_utilization", "ping_test", "nokia_firewall_cpu_utilization" ...',
				'value' => isset($thold_item_data['snmp_event_category']) ? $thold_item_data['snmp_event_category'] : '',
				'default' => '',
				'max_length' => '255',
			),
			'snmp_event_severity' => array(
				'friendly_name' => 'SNMP Notification - Alert Event Severity',
				'method' => 'drop_array',
				'default' => '3',
				'description' => 'Severity to be used for alerts. (low impact -> critical impact)',
				'value' => isset($thold_item_data['snmp_event_severity']) ? $thold_item_data['snmp_event_severity'] : 3,
				'array' => array( 1=>"low", 2=> "medium", 3=> "high", 4=> "critical"),
			),
		);
		$form_array += $extra;

		if(read_config_option("thold_alert_snmp_warning") != "on") {
			$extra = array(
				'snmp_event_warning_severity' => array(
					'friendly_name' => 'SNMP Notification - Warning Event Severity',
					'method' => 'drop_array',
					'default' => '2',
					'description' => 'Severity to be used for warnings. (low impact -> critical impact).<br>Note: The severity of warnings has to be equal or lower than the severity being defined for alerts.',
					'value' => isset($thold_item_data['snmp_event_warning_severity']) ? $thold_item_data['snmp_event_warning_severity'] : 2,
					'array' => array( 1=>"low", 2=> "medium", 3=> "high", 4=> "critical"),
				),
			);
		}
		$form_array += $extra;
	}

	if (read_config_option("thold_disable_legacy") != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => 'Notify accounts',
				'method' => 'drop_multi',
				'description' => 'This is a listing of accounts that will be notified when this threshold is breached.<br><br><br><br>',
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => 'Alert Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive alerts for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => 'Warning Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive warnings for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	} else {
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore',
			),
			'notify_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => $form_array
			)
	);

	form_hidden_box("save", "edit", "");
	form_hidden_box("id", $id, "");

	html_end_box();

	form_save_button('thold_templates.php?id=' . $id, 'save');

	?>
	<!-- Make it look intelligent :) -->
	<script language="JavaScript">
	function changeTholdType() {
		type = document.getElementById('thold_type').value;
		switch(type) {
		case '0': // Hi/Low
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');
			break;
		case '1': // Baseline
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');
			break;
		case '2': // Time Based
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');
			break;
		}
	}

	function changeDataType() {
		type = document.getElementById('data_type').value;
		switch(type) {
		case '0':
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = 'none';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '1':
			document.getElementById('row_cdef').style.display  = '';
			document.getElementById('row_percent_ds').style.display  = 'none';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '2':
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = '';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '3':
			document.getElementById('row_expression').style.display  = '';
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = 'none';
			break;
		}
	}

	function thold_toggle_hilow(status) {
		document.getElementById('row_thold_header').style.display  = status;
		document.getElementById('row_thold_hi').style.display  = status;
		document.getElementById('row_thold_low').style.display  = status;
		document.getElementById('row_thold_fail_trigger').style.display  = status;
		document.getElementById('row_thold_warning_header').style.display  = status;
		document.getElementById('row_thold_warning_hi').style.display  = status;
		document.getElementById('row_thold_warning_low').style.display  = status;
		document.getElementById('row_thold_warning_fail_trigger').style.display  = status;
	}

	function thold_toggle_baseline(status) {
		document.getElementById('row_baseline_header').style.display  = status;
		document.getElementById('row_bl_ref_time_range').style.display  = status;
		document.getElementById('row_bl_pct_up').style.display  = status;
		document.getElementById('row_bl_pct_down').style.display  = status;
		document.getElementById('row_bl_fail_trigger').style.display  = status;
	}

	function thold_toggle_time(status) {
		document.getElementById('row_time_header').style.display  = status;
		document.getElementById('row_time_hi').style.display  = status;
		document.getElementById('row_time_low').style.display  = status;
		document.getElementById('row_time_fail_trigger').style.display  = status;
		document.getElementById('row_time_fail_length').style.display  = status;
		document.getElementById('row_time_warning_header').style.display  = status;
		document.getElementById('row_time_warning_hi').style.display  = status;
		document.getElementById('row_time_warning_low').style.display  = status;
		document.getElementById('row_time_warning_fail_trigger').style.display  = status;
		document.getElementById('row_time_warning_fail_length').style.display  = status;
	}

	changeTholdType ();
	changeDataType ();

	if (document.THold["notify_accounts[]"] && document.THold["notify_accounts[]"].length == 0) {
		document.getElementById('row_notify_accounts').style.display='none';
	}

	if (document.THold.notify_warning.length == 1) {
		document.getElementById('row_notify_warning').style.display='none';
	}

	if (document.THold.notify_alert.length == 1) {
		document.getElementById('row_notify_alert').style.display='none';
	}

	</script>
	<?php

}

function template_request_validation() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_tt_current_page");
		kill_session_var("sess_tt_filter");
		kill_session_var("sess_tt_rows");
		kill_session_var("sess_tt_sort_column");
		kill_session_var("sess_tt_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	} else {
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('filter', 'sess_tt_filter');
		$changed += thold_request_check_changed('rows', 'sess_tt_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_tt_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_tt_sort_direction');
		if ($changed) {
		$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_tt_current_page", "1");
	load_current_session_value("filter", "sess_tt_filter", "");
	load_current_session_value("rows", "sess_tt_rows", read_config_option("alert_num_rows"));
	load_current_session_value("sort_column", "sess_tt_sort_column", "name");
	load_current_session_value("sort_direction", "sess_tt_sort_direction", "ASC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("alert_num_rows");
		if ($_REQUEST["rows"] < 2) $_REQUEST["rows"] = 30;
	}
}

function templates() {
	global $colors, $thold_actions, $item_rows;

	template_request_validation();

	?>
	<script type="text/javascript">
	<!--
	function applyTHoldFilterChange(objForm) {
		strURL = '?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function importTemplate() {
		strURL = '?action=import';
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box('<strong>Threshold Templates</strong>', '100%', $colors['header'], '3', 'center', 'thold_templates.php?action=add');

	?>
	<tr bgcolor='#<?php print $colors["panel"];?>' class='noprint'>
		<td class='noprint'>
			<form name='listthold' action='thold_templates.php'>
			<table cellpadding='0' cellspacing='0'>
				<tr class='noprint'>
					<td width='20'>
						&nbsp;Search:&nbsp;
					</td>
					<td width='144'>
						<input type='text' name='filter' size='20' value='<?php print $_REQUEST["filter"];?>'>
					</td>
					<td width='1'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='-1'<?php if ($_REQUEST["rows"] == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if ($_REQUEST["rows"] == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width='1'>
						<input type="submit" value="Go">
					</td>
					<td width='1'>
						<input id="clear" name="clear" type="submit" value="Clear">
					</td>
					<td width='1'>
						<input id="import" name="import" type="button" value="Import" onClick="importTemplate()">
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';
	$limit = ' LIMIT ' . ($_REQUEST["rows"]*($_REQUEST['page']-1)) . "," . $_REQUEST["rows"];
	$order = "ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction'];
	if (strlen($_REQUEST["filter"])) {
		$sql_where .= (strlen($sql_where) ? " AND": "WHERE") . " thold_template.name LIKE '%%" . $_REQUEST["filter"] . "%%'";
	}

	define('MAX_DISPLAY_PAGES', 21);

	$total_rows    = db_fetch_cell("SELECT count(*) FROM thold_template");
	$template_list = db_fetch_assoc("SELECT * FROM thold_template $sql_where $order $limit");

	if ($total_rows) {
		/* generate page list */
		$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "thold_templates.php?tab=thold");

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_templates.php?filter=" . $_REQUEST["filter"] . "&page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_templates.php?filter=" . $_REQUEST["filter"] . "&page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

    /* print checkbox form for validation */
    print "<form name='chk' method='post' action='thold_templates.php'>\n";

	html_start_box('' , '100%', $colors['header'], '3', 'center', '');

	print $nav;

	html_header_sort_checkbox(array(
		'name' => array('Name', 'ASC'),
		'data_template_name' => array('Data Template', 'ASC'),
		'data_source_name' => array('DS Name', 'ASC'),
		'thold_type' => array('Type', 'ASC'),
		'nosort1' => array('High/Up', ''),
		'nosort2' => array('Low/Down', ''),
		'nosort3' => array('Trigger', ''),
		'nosort4' => array('Duration', ''),
		'nosort5' => array('Repeat', '')), $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$i = 0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (sizeof($template_list)) {
		foreach ($template_list as $template) {
			switch ($template['thold_type']) {
				case 0:					# hi/lo
					$value_hi = thold_format_number($template['thold_hi']);
					$value_lo = thold_format_number($template['thold_low']);
					$value_trig = $template['thold_fail_trigger'];
					$value_duration = '';
					$value_warning_hi = thold_format_number($template['thold_warning_hi']);
					$value_warning_lo = thold_format_number($template['thold_warning_low']);
					$value_warning_trig = $template['thold_warning_fail_trigger'];
					$value_warning_duration = '';
					break;
				case 1:					# baseline
					$value_hi = $template['bl_pct_up'] . (strlen($template['bl_pct_up']) ? '%':'-');
					$value_lo = $template['bl_pct_down'] . (strlen($template['bl_pct_down']) ? '%':'-');
					$value_trig = $template['bl_fail_trigger'];
					$step = db_fetch_cell("SELECT rrd_step
						FROM data_template_data
						WHERE data_template_id=" . $template['data_template_id'] . "
						LIMIT 1");
					$value_duration = $template['bl_ref_time_range'] / $step;;
					break;
				case 2:					#time
					$value_hi = thold_format_number($template['time_hi']);
					$value_lo = thold_format_number($template['time_low']);
					$value_trig = $template['time_fail_trigger'];
					$value_duration = $template['time_fail_length'];
					break;
			}
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $template["id"]); $i++;
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('thold_templates.php?action=edit&id=' . $template['id']) . '">' . ($template['name'] == '' ? $template['data_template_name'] . ' [' . $template['data_source_name'] . ']' : $template['name']) . '</a>', $template["id"]);
			form_selectable_cell($template['data_template_name'], $template["id"]);
			form_selectable_cell($template['data_source_name'], $template["id"]);
			form_selectable_cell($types[$template['thold_type']], $template["id"]);
			form_selectable_cell($value_hi, $template["id"]);
			form_selectable_cell($value_lo, $template["id"]);

			$trigger =  plugin_thold_duration_convert($template['data_template_id'], $value_trig, 'alert', 'data_template_id');
			form_selectable_cell((strlen($trigger) ? "<i>" . $trigger . "</i>":"-"), $template["id"]);

			$duration = plugin_thold_duration_convert($template['data_template_id'], $value_duration, 'time', 'data_template_id');
			form_selectable_cell((strlen($duration) ? $duration:"-"), $template["id"]);
			form_selectable_cell(plugin_thold_duration_convert($template['data_template_id'], $template['repeat_alert'], 'repeat', 'data_template_id'), $template['id']);
			form_checkbox_cell($template['data_template_name'], $template["id"]);
			form_end_row();
		}

		print $nav;
	} else {
		print "<tr><td><em>No Threshold Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($thold_actions);

	print "</form>\n";
}

function import() {
	global $colors;

	$form_data = array(
		"import_file" => array(
			"friendly_name" => "Import Template from Local File",
			"description" => "If the XML file containing Threshold Template data is located on your local
			machine, select it here.",
			"method" => "file"
		),
		"import_text" => array(
			"method" => "textarea",
			"friendly_name" => "Import Template from Text",
			"description" => "If you have the XML file containing Threshold Template data as text, you can paste
			it into this box to import it.",
			"value" => "",
			"default" => "",
			"textarea_rows" => "10",
			"textarea_cols" => "80",
			"class" => "textAreaNotes"
		)
	);

	?>
	<form method="post" action="thold_templates.php" enctype="multipart/form-data">
	<?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", $colors["header"], "3", "center", "");

		print "<tr><td>Cacti has imported the following items:</td></tr>";
		foreach($_SESSION["import_debug_info"] as $line) {
			print "<tr><td>" . $line . "</td></tr>";
		}

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import Threshold Templates</strong>", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => $form_data
		));

	html_end_box();
	form_hidden_box("save_component_import","1","");
	form_save_button("", "import");
}

function template_import() {
	include_once("./lib/xml.php");

	if (trim($_POST["import_text"] != "")) {
		/* textbox input */
		$xml_data = $_POST["import_text"];
	}elseif (($_FILES["import_file"]["tmp_name"] != "none") && ($_FILES["import_file"]["tmp_name"] != "")) {
		/* file upload */
		$fp = fopen($_FILES["import_file"]["tmp_name"],"r");
		$xml_data = fread($fp,filesize($_FILES["import_file"]["tmp_name"]));
		fclose($fp);
	}else{
		header("Location: thold_templates.php"); exit;
	}

	/* obtain debug information if it's set */
	$xml_array = xml2array($xml_data);

	$debug_data = array();

	if (sizeof($xml_array)) {
	foreach($xml_array as $template => $contents) {
		$error = false;
		$save  = array();
		if (sizeof($contents)) {
		foreach($contents as $name => $value) {
			$value = htmlentities($value);
			switch($name) {
			case 'data_template_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell("SELECT id FROM data_template WHERE hash='$value'");

				if (!empty($found)) {
					$save['data_template_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template Subordinate Data Template Not Found!";
				}

				break;
			case 'data_source_id':
				// See if the hash exists, if it doesn't, Error Out
				$found = db_fetch_cell("SELECT id FROM data_template_rrd WHERE hash='$value'");

				if (!empty($found)) {
					$save['data_source_id'] = $found;
				}else{
					$error = true;
					$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template Subordinate Data Source Not Found!";
				}

				break;
			case 'hash':
				// See if the hash exists, if it does, update the thold
				$found = db_fetch_cell("SELECT id FROM thold_template WHERE hash='$value'");

				if (!empty($found)) {
					$save['hash'] = $value;
					$save['id']   = $found;
				}else{
					$save['hash'] = $value;
					$save['id']   = 0;
				}

				break;
			case 'name':
				$tname = $value;
				$save['name'] = $value;

				break;
			default:
				$save[$name] = $value;

				break;
			}
		}
		}

		if (!$error) {
			$id = sql_save($save, 'thold_template');

			if ($id) {
				$debug_data[] = "<span style='font-weight:bold;color:green;'>NOTE:</span> Threshold Template '<b>$tname</b>' " . ($save['id'] > 0 ? "Updated":"Imported") . "!";
			}else{
				$debug_data[] = "<span style='font-weight:bold;color:red;'>ERROR:</span> Threshold Template '<b>$tname</b>' " . ($save['id'] > 0 ? "Update":"Import") . " Failed!";
			}
		}
	}
	}

	if(sizeof($debug_data) > 0) {
		$_SESSION["import_debug_info"] = $debug_data;
	}

	header("Location: thold_templates.php?action=import");
}

