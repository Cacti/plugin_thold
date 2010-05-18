<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2009 The Cacti Group                                      |
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

$ds_actions = array(
	1 => 'Delete'
	);

$new_alerts = do_hook_function('thold_alert_array', array('email' => 'Email', 'snmp-write' => 'Snmp Write', 'script' => 'Script'));

$action = '';
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'delete';
}

if (isset($_REQUEST['delete_alert']) && intval($_REQUEST['delete_alert']) > 0 && isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0) {
	thold_template_delete_alert($_REQUEST['delete_alert']);
	header("Location: thold_templates.php?action=edit&id=" . $_REQUEST['id'] . "#alerts\n\n");
	exit;
}

switch ($action) {
	case 'add':
		template_add();
		break;
	case 'edit':
		include_once('./include/top_header.php');
		template_edit();
		include_once('./include/bottom_footer.php');
		break;
	case 'save':
		if (isset($_POST['save']) && $_POST['save'] == 'edit') {
			if (isset($_SESSION["graph_return"])) {
				$return_to = $_SESSION["graph_return"];
				unset($_SESSION["graph_return"]);
				kill_session_var("graph_return");
				header('Location: ' . $return_to);
			} else if (isset($_REQUEST['new_alert']) && isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0) {
				if (isset($new_alerts[$_REQUEST['new_alert']])) {
					thold_template_add_alert($_REQUEST['new_alert'], $_REQUEST['id']);
					header("Location: thold_templates.php?action=edit&id=" . $_REQUEST['id'] . "#alerts\n\n");
					exit;
				}
			} else {
				template_save_edit();
			}
		} else if (isset($_POST['save']) && $_POST['save'] == 'add') {

		}
		break;
	case 'delete':
		template_delete();
		break;
	default:
		include_once('./include/top_header.php');
		templates();
		include_once('./include/bottom_footer.php');
		break;
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);
			input_validate_input_number($id);
			plugin_thold_log_changes($id, 'deleted_template', array('id' => $id));
			db_fetch_assoc("delete from thold_template where id = $id LIMIT 1");
			db_execute('DELETE FROM plugin_thold_template_alerts WHERE template_id=' . $id);
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
		<center><table>
			<tr>
				<td width='70' style='white-space:nowrap;'>
					&nbsp;<b>Data Template:</b>
				</td>
				<td style='width:1;'>
					<select name=data_template_id onChange="applyTholdFilterChange(document.tholdform, 'dt')">
						<option value=""></option><?php
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
						<option value=""></option><?php
						foreach ($data_fields as $id => $name) {
							echo "<option value='" . $id . "'" . ($id == $_REQUEST['data_source_id'] ? ' selected' : '') . '>' . $name . '</option>';
						}?>
					</select>
				</td>
			</tr>
			<?php
		}

		if ($_REQUEST["data_source_id"] != '') {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><input id="save" type=hidden name="save" value="save"><br><center><input type=image src="../../images/button_create.gif" alt="Create"></center></td></tr>';
		} else {
			echo '<tr><td colspan=2><input type=hidden name=action value="add"><br><br><br></td></tr>';
		}
		echo '</table></form></td></tr>';
		html_end_box();
	}else{
		$data_template_id = $_REQUEST['data_template_id'];
		$data_source_id = $_REQUEST['data_source_id'];

		$save['id'] = '';
		$save['data_template_id'] = $data_template_id;

		$temp = db_fetch_assoc('select id, name from data_template where id=' . $data_template_id);
		$save['name'] = $temp[0]['name'];
		$save['data_template_name'] = $temp[0]['name'];
		$save['data_source_id'] = $data_source_id;

		$temp = db_fetch_assoc('select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where id = ' . $data_source_id);

		$save['data_source_name'] = $temp[0]['data_source_name'];
		$save['name'] .= ' [' . $temp[0]['data_source_name'] . ']';

		if ($temp[0]['data_input_field_id'] != 0)
			$temp2 = db_fetch_assoc('select name from data_input_fields where id = ' . $temp[0]['data_input_field_id']);
		else
			$temp2[0]['name'] = $temp[0]['data_source_name'];

		$save['data_source_friendly'] = $temp2[0]['name'];
		$save['thold_enabled'] = 'on';
		$save['thold_type'] = 0;
		$save['bl_enabled'] = 'off';
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
	input_validate_input_number(get_request_var_post('bl_ref_time'));
	input_validate_input_number(get_request_var_post('bl_ref_time_range'));
	input_validate_input_number(get_request_var_post('bl_pct_down'));
	input_validate_input_number(get_request_var_post('bl_pct_up'));
	input_validate_input_number(get_request_var_post('bl_fail_trigger'));
	input_validate_input_number(get_request_var_post('repeat_alert'));
	input_validate_input_number(get_request_var_post('data_type'));
	input_validate_input_number(get_request_var_post('cdef'));
	/* ==================================================== */

	/* clean up date1 string */
	if (isset($_POST['name'])) {
		$_POST['name'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('name')));
	}

	/* save: data_template */
	$save['id'] = $_POST['id'];
	$save['name'] = $_POST['name'];
	$save['thold_type'] = $_POST['thold_type'];

	// High / Low
	$save['thold_hi'] = $_POST['thold_hi'];
	$save['thold_low'] = $_POST['thold_low'];

	// Time Based
	$save['time_hi'] = $_POST['time_hi'];
	$save['time_low'] = $_POST['time_low'];

	$save['time_fail_trigger'] = $_POST['time_fail_trigger'];
	$save['time_fail_length'] = $_POST['time_fail_length'];

	if (isset($_POST['thold_enabled']))
		$save['thold_enabled'] = 'on';
	else
		$save['thold_enabled'] = 'off';
	if (isset($_POST['exempt']))
		$save['exempt'] = 'on';
	else
		$save['exempt'] = 'off';
	if (isset($_POST['restored_alert']))
		$save['restored_alert'] = 'on';
	else
		$save['restored_alert'] = 'off';
	if (isset($_POST['bl_enabled']))
		$save['bl_enabled'] = 'on';
	else
		$save['bl_enabled'] = 'off';
	if (isset($_POST['bl_ref_time'])  && $_POST['bl_ref_time'] != '')
		$save['bl_ref_time'] = $_POST['bl_ref_time'];
	else {
		$alert_bl_past_default = read_config_option('alert_bl_past_default');
		if ($alert_bl_past_default != '' && is_numeric($alert_bl_past_default))
			$save['bl_ref_time'] = $alert_bl_past_default;
		else
			$save['bl_ref_time'] = 86400;
	}
	if (isset($_POST['bl_ref_time_range']) && $_POST['bl_ref_time_range'] != '')
		$save['bl_ref_time_range'] = $_POST['bl_ref_time_range'];
	else {
		$alert_bl_timerange_def = read_config_option('alert_bl_timerange_def');
		if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def))
			$save['bl_ref_time_range'] = $alert_bl_timerange_def;
		else
			$save['bl_ref_time_range'] = 10800;
	}
	if (isset($_POST['bl_pct_down']) && $_POST['bl_pct_down'] != '')
		$save['bl_pct_down'] = $_POST['bl_pct_down'];
	if (isset($_POST['bl_pct_up']) && $_POST['bl_pct_up'] != '')
		$save['bl_pct_up'] = $_POST['bl_pct_up'];
	if (isset($_POST['bl_fail_trigger']) && $_POST['bl_fail_trigger'] != '')
		$save['bl_fail_trigger'] = $_POST['bl_fail_trigger'];
	else {
		$alert_bl_trigger = read_config_option('alert_bl_trigger');
		if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger))
			$save['bl_fail_trigger'] = $alert_bl_trigger;
		else
			$save['bl_fail_trigger'] = 3;
	}

	$save['cdef'] = $_POST['cdef'];
	$save['data_type'] = $_POST['data_type'];
	$save['percent_ds'] = $_POST['percent_ds'];
	$save['expression'] = $_POST['expression'];

	if (!is_error_message()) {
		$id = sql_save($save, 'thold_template');
		if ($id) {
			raise_message(1);
			plugin_thold_log_changes($id, 'modified_template', $save);
		} else {
			raise_message(2);
		}
	}

	$alerts = array();
	foreach ($_POST as $p => $v) {
		if (substr($p, 0, 13) == 'repeat_alert_') {
			$alerts[substr($p, 13)]['repeat_alert'] = $v;
		}
		if (substr($p, 0, 13) == 'notify_extra_') {
			$alerts[substr($p, 13)]['notify_extra'] = $v;
		}
		if (substr($p, 0, 16) == 'notify_accounts_') {
			$v = implode($v, ',');
			$alerts[substr($p, 16)]['notify_accounts'] = $v;
		}
		if (substr($p, 0, 12) == 'repeat_fail_') {
			$alerts[substr($p, 12)]['repeat_fail'] = $v;
		}
		if (substr($p, 0, 5) == 'type_') {
			$alerts[substr($p, 5)]['type'] = $v;
		}
		if (substr($p, 0, 8) == 'oid_num_') {
			$alerts[substr($p, 8)]['oid_num'] = $v;
		}
		if (substr($p, 0, 10) == 'community_') {
			$alerts[substr($p, 10)]['community'] = $v;
		}
		if (substr($p, 0, 9) == 'oid_type_') {
			$alerts[substr($p, 9)]['oid_type'] = $v;
		}
		if (substr($p, 0, 10) == 'oid_value_') {
			$alerts[substr($p, 10)]['oid_value'] = $v;
		}
		if (substr($p, 0, 5) == 'path_') {
			$alerts[substr($p, 5)]['path'] = $v;
		}
		if (substr($p, 0, 5) == 'args_') {
			$alerts[substr($p, 5)]['args'] = $v;
		}
	}

	$p = $config['base_path'] . '/plugins/thold/scripts/';
	if ($handle = opendir($p)) {
	    while (false !== ($file = readdir($handle))) {
	        if ($file != "." && $file != ".." && $file != strtolower('index.php') && $file != strtolower('.htaccess') && !is_dir("$p$file")) {
	            $scripts[] = $file;
	        }
	    }
	    closedir($handle);
	}

	if (count($alerts)) {
		foreach ($alerts as $p => $v) {
			switch ($v['type']) {
				case 'email':
					$save = array();
					$save['id'] = $p;
					$save['template_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					$save['data'] = base64_encode(serialize(array('notify_accounts' => $v['notify_accounts'], 'notify_extra' => $v['notify_extra'])));
					$aid = sql_save($save , 'plugin_thold_template_alerts');
					break;
				case 'snmp-write':
					$save = array();
					$save['id'] = $p;
					$save['template_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					if (!isset($v['oid_host'])) $v['oid_host'] = '';
					$save['data'] = base64_encode(serialize(array('oid_host' => $v['oid_host'], 'oid_num' => $v['oid_num'], 'community' => $v['community'], 'oid_type' => $v['oid_type'], 'oid_value' => $v['oid_value'])));
					$aid = sql_save($save , 'plugin_thold_template_alerts');
					break;
				case 'script':
					$save = array();
					$save['id'] = $p;
					$save['template_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					if (in_array($v['path'], $scripts)) {
						$v['args'] = str_replace(array('|'), '', $v['args']);
						$save['data'] = base64_encode(serialize(array('args' => $v['args'], 'path' => basename($v['path']))));
					}
					$aid = sql_save($save , 'plugin_thold_template_alerts');
					break;
			}
		}
	}

	do_hook('thold_template_alert_save');

	if ((is_error_message()) || (empty($_POST['id']))) {
		header('Location: thold_templates.php?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
	}else{
		thold_template_update_thresholds ($id);
		header('Location: thold_templates.php');
	}
}

function template_edit() {
	global $colors, $config, $new_alerts;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */
//	if (isset($_GET['id']))
		$id = $_GET['id'];
	$thold_item_data = db_fetch_assoc('select * from thold_template where id = ' . $id);

	$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;


	$temp = db_fetch_assoc('select id, name from data_template where id = ' . $thold_item_data['data_template_id']);

	foreach ($temp as $d) {
		$data_templates[$d['id']] = $d['name'];
	}

	$temp = db_fetch_assoc('select id, data_source_name, data_input_field_id from data_template_rrd where id = ' . $thold_item_data['data_source_id']);
	$source_id = $temp[0]['data_input_field_id'];

	if ($source_id != 0) {
		$temp2 = db_fetch_assoc('select id, name from data_input_fields where id = ' . $source_id);
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
		$alertarray  = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
		$timearray   = array(1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
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
		1 => 'Baseline',
		2 => 'Time Based',
	);

	$data_types = array (
		0 => 'Exact Value',
		1 => 'CDEF',
		2 => 'Percentage',
		3 => 'RPN Expression'
	);

	$data_fields2 = array();
	$temp = db_fetch_assoc('select id, local_data_template_rrd_id, data_source_name, data_input_field_id from data_template_rrd where local_data_template_rrd_id = 0 and data_template_id = ' . $thold_item_data['data_template_id']);
	foreach ($temp as $d) {
		if ($d['data_input_field_id'] != 0) {
			$temp2 = db_fetch_assoc('select id, name, data_name from data_input_fields where id = ' . $d['data_input_field_id'] . ' order by data_name');
			$data_fields2[$d['data_source_name']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
		} else {
			$temp2[0]['name'] = $d['data_source_name'];
			$data_fields2[$d['data_source_name']] = $temp2[0]['name'];
		}
	}

	html_start_box('', '100%', $colors['header'], '3', 'center', '');
	print "<form name='THold' action=thold_templates.php method=post><input type='hidden' name='save' value='edit'><input type='hidden' name='id' value='$id'>";
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
			'description' => 'Provide the THold Template a meaningful name',
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
		'thold_header' => array(
			'friendly_name' => 'High / Low Settings',
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_hi']) ? $thold_item_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_low']) ? $thold_item_data['thold_low'] : ''
		),
		'time_header' => array(
			'friendly_name' => 'Time Based Settings',
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['time_hi']) ? $thold_item_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['time_low']) ? $thold_item_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => 'Trigger Count',
			'method' => 'textbox',
			'max_length' => 5,
			'default' => read_config_option('thold_time_fail_trigger'),
			'description' => 'The number of times the data source must be in breach condition prior to issuing an alert.',
			'value' => isset($thold_item_data['time_fail_trigger']) ? $thold_item_data['time_fail_trigger'] : ''
		),
		'time_fail_length' => array(
			'friendly_name' => 'Time Period Length',
			'method' => 'drop_array',
			'array' => $timearray,
			'default' => (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 1),
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_fail_length']) ? $thold_item_data['time_fail_length'] : ''
		),
		'baseline_header' => array(
			'friendly_name' => 'Baseline monitoring',
			'method' => 'spacer',
		),
		'bl_enabled' => array(
			'friendly_name' => 'Baseline monitoring',
			'method' => 'checkbox',
			'default' => 'off',
			'description' => 'When enabled, baseline monitoring checks the current data source value against a value in the past. The available range of values is retrieved and a minimum and maximum values are taken as a respective baseline reference. The precedence however is on the &quot;hard&quot; thresholds above.',
			'value' => isset($thold_item_data['bl_enabled']) ? $thold_item_data['bl_enabled'] : ''
		),
		'bl_ref_time' => array(
			'friendly_name' => 'Reference in the past',
			'method' => 'textbox',
			'max_length' => 20,
			'default' => read_config_option('alert_bl_past_default'),
			'description' => 'Specifies the relative point in the past that will be used as a reference. The value represents seconds, so for a day you would specify 86400, for a week 604800, etc.',
			'value' => isset($thold_item_data['bl_ref_time']) ? $thold_item_data['bl_ref_time'] : ''
		),
		'bl_ref_time_range' => array(
			'friendly_name' => 'Time range',
			'method' => 'textbox',
			'max_length' => 20,
			'default' => read_config_option('alert_bl_timerange_def'),
			'description' => 'Specifies the time range of values in seconds to be taken from the reference in the past',
			'value' => isset($thold_item_data['bl_ref_time_range']) ? $thold_item_data['bl_ref_time_range'] : ''
		),
		'bl_pct_up' => array(
			'friendly_name' => 'Baseline deviation UP',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 3,
			'description' => 'Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_up']) ? $thold_item_data['bl_pct_up'] : ''
		),
		'bl_pct_down' => array(
			'friendly_name' => 'Baseline deviation DOWN',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 3,
			'description' => 'Specifies allowed deviation in percentage for the lower bound threshold. If not set, lower bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_down']) ? $thold_item_data['bl_pct_down'] : ''
		),
		'bl_fail_trigger' => array(
			'friendly_name' => 'Baseline Trigger Count',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 3,
			'default' => read_config_option('alert_bl_trigger'),
			'description' => 'Number of consecutive times the data source must be in a breached condition for an alert to be raised.<br>Leave empty to use default value (<b>Default: ' . read_config_option('alert_bl_trigger') . ' cycles</b>)',
			'value' => isset($thold_item_data['bl_fail_trigger']) ? $thold_item_data['bl_fail_trigger'] : ''
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
			'default' => read_config_option('data_type'),
			'description' => 'Special formatting for the given data.',
			'value' => isset($thold_item_data['data_type']) ? $thold_item_data['data_type'] : ''
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
			'description' => 'An RPM Expression that assumes that the selected Data Source is already in the 
			RPN stack.  This RPN expression can include any additional Data Sources names in the current 
			RRDfile.  However, in all cases the selected Data Source is loaded on the stack first.',
			'value' => isset($thold_item_data['expression']) ? $thold_item_data['expression'] : '',
			'width' => '255',
			'max_length' => 70,
			'size' => '80'
		),
	);

draw_edit_form(
	array(
		'config' => array(
			'no_form_tag' => true
			),
		'fields' => $form_array + array(
			'data_template_rrd_id' => array(
				'method' => 'hidden',
				'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
			),
		)
	)
);

html_end_box();

if (isset($thold_item_data['id'])) {
	$template_id = $thold_item_data['id'];

	print "<br><center><h1>Alerts</h1></center><a name='alerts'></a>";
	$alerts = db_fetch_assoc('SELECT * FROM plugin_thold_template_alerts WHERE template_id = ' . $thold_item_data['id'] . ' ORDER BY repeat_fail, repeat_alert ASC');
/*
	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $thold_item_data['rra_id']);
	$step = intval($step/60);
	if ($step == 1) {
		$repeatarray = array(0 => 'Never', 1 => 'Every Minute', 2 => 'Every 2 Minutes', 3 => 'Every 3 Minutes', 4 => 'Every 4 Minutes', 5 => 'Every 5 Minutes', 10 => 'Every 10 Minutes', 15 => 'Every 15 Minutes', 20 => 'Every 20 Minutes', 30 => 'Every 30 Minutes', 45 => 'Every 45 Minutes', 60 => 'Every Hour', 120 => 'Every 2 Hours', 180 => 'Every 3 Hours', 240 => 'Every 4 Hours', 360 => 'Every 6 Hours', 480 => 'Every 8 Hours', 720 => 'Every 12 Hours', 1440 => 'Every Day', 2880 => 'Every 2 Days', 10080 => 'Every Week', 20160 => 'Every 2 Weeks', 43200 => 'Every Month');
	} else if ($step == 5) {
		$repeatarray = array(0 => 'Never', 1 => 'Every 5 Minutes', 2 => 'Every 10 Minutes', 3 => 'Every 15 Minutes', 4 => 'Every 20 Minutes', 6 => 'Every 30 Minutes', 8 => 'Every 45 Minutes', 12 => 'Every Hour', 24 => 'Every 2 Hours', 36 => 'Every 3 Hours', 48 => 'Every 4 Hours', 72 => 'Every 6 Hours', 96 => 'Every 8 Hours', 144 => 'Every 12 Hours', 288 => 'Every Day', 576 => 'Every 2 Days', 2016 => 'Every Week', 4032 => 'Every 2 Weeks', 8640 => 'Every Month');
	} else {
		$repeatarray = array(0 => 'Never', 1 => 'Every Polling', 2 => 'Every 2 Pollings', 3 => 'Every 3 Pollings', 4 => 'Every 4 Pollings', 6 => 'Every 6 Pollings', 8 => 'Every 8 Pollings', 12 => 'Every 12 Pollings', 24 => 'Every 24 Pollings', 36 => 'Every 36 Pollings', 48 => 'Every 48 Pollings', 72 => 'Every 72 Pollings', 96 => 'Every 96 Pollings', 144 => 'Every 144 Pollings', 288 => 'Every 288 Pollings', 576 => 'Every 576 Pollings', 2016 => 'Every 2016 Pollings');
	}
	if ($step == 1) {
		$alertarray = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
	} else if ($step == 5) {
		$alertarray = array(0 => 'Never', 1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => 'Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
	} else {
		$alertarray = array(0 => 'Never', 1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
	}
*/
	if (count($alerts)) {
		$p = $config['base_path'] . '/plugins/thold/scripts/';
		if ($handle = opendir($p)) {
		    while (false !== ($file = readdir($handle))) {
		        if ($file != "." && $file != ".." && $file != strtolower('index.php') && $file != strtolower('.htaccess') && !is_dir("$p$file") && is_executable("$p$file")) {
		            $scripts[$file] = $file;
		        }
		    }
		    closedir($handle);
		}

		foreach ($alerts as $alert) {
			switch ($alert['type']) {
				case 'email':
					html_start_box("", "100%", $colors["header"], "3", "center", "");
					$id = $alert['id'];
					$alert['data'] = unserialize(base64_decode($alert['data']));
					$alert['notify_extra'] = $alert['data']['notify_extra'];
					if ($alert['data']['notify_accounts'] != '') {
						$sql = 'SELECT id FROM plugin_thold_contacts WHERE id IN (' . $alert['data']['notify_accounts'] .')';
					} else {
						$sql = 'SELECT id FROM plugin_thold_contacts WHERE id = 0';
					}
					$form_array = array(
						"alert_header_$id" => array(
							"friendly_name" => "<table width='100%' cellpadding=0 cellspacing=0><tr><td><font color=white size=2><b>Email</b></font></td><td align=right><a href='thold_templates.php?delete_alert=$id&id=$template_id'><font color=white size=2><b>X</b></font></a></td></tr></table>",
							"method" => "spacer",
						),
						"type_$id" => array(
							"method" => "hidden",
							"value" => 'email',
						),
						"repeat_fail_$id" => array(
							"friendly_name" => "Alert After",
							"method" => "drop_array",
							"default" => '1',
							"description" => "Alert after this number of failed polling intervals.",
							"value" => isset($alert["repeat_fail"]) ? $alert["repeat_fail"] : "",
							'array' => $alertarray,
						),
						"repeat_alert_$id" => array(
							"friendly_name" => "Re-Alert Cycle",
							"method" => "drop_array",
							"default" => read_config_option("alert_repeat"),
							"description" => "Repeat alert after specified number of cycles.",
							"value" => isset($alert["repeat_alert"]) ? $alert["repeat_alert"] : "",
							'array' => $repeatarray,
						),
						"notify_accounts_$id" => array(
							"friendly_name" => "Notify accounts",
							"method" => "drop_multi",
							"description" => "This is a listing of accounts that will be notified when this threshold is breached.<br><br><br><br>",
							"array" => $send_notification_array,
							"sql" => $sql,
						),
						"notify_extra_$id" => array(
							"friendly_name" => "Extra Alert Emails",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "You may specify here extra e-mails to receive alerts for this data source (comma separated)",
							"value" => isset($alert["notify_extra"]) ? $alert["notify_extra"] : ""
						),
					);
					draw_edit_form(
						array(
							"config" => array(
							"no_form_tag" => true
							),
						"fields" => $form_array,
						)
					);
					html_end_box();
					break;
				case 'snmp-write':
					html_start_box("", "100%", $colors["header"], "3", "center", "");
					$id = $alert['id'];
					$data = $alert['data'];
					$data = unserialize(base64_decode($data));
					$form_array = array(
						"alert_header_$id" => array(
							"friendly_name" => "<table width='100%' cellpadding=0 cellspacing=0><tr><td><font color=white size=2><b>SNMP Write</b></font></td><td align=right><a href='thold_templates.php?delete_alert=$id&id=$template_id'><font color=white size=2><b>X</b></font></a></td></tr></table>",
							"method" => "spacer",
						),
						"type_$id" => array(
							"method" => "hidden",
							"value" => 'snmp-write',
						),
						"repeat_fail_$id" => array(
							"friendly_name" => "Alert After",
							"method" => "drop_array",
							"default" => '1',
							"description" => "Alert after this number of failed polling intervals.",
							"value" => isset($alert["repeat_fail"]) ? $alert["repeat_fail"] : "",
							'array' => $alertarray,
						),
						"repeat_alert_$id" => array(
							"friendly_name" => "Re-Alert Cycle",
							"method" => "drop_array",
							"default" => read_config_option("alert_repeat"),
							"description" => "Repeat alert after specified number of cycles.",
							"value" => isset($alert["repeat_alert"]) ? $alert["repeat_alert"] : "",
							'array' => $repeatarray,
						),
/*
						"oid_host_$id" => array(
							"friendly_name" => "Host",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "The host to send the SNMP Write to (leave blank for current).",
							"value" => isset($data["oid_host"]) ? stripslashes($data["oid_host"]) : ""
						),
*/
						"community_$id" => array(
							"friendly_name" => "Community",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "Specify the community name to use.",
							"value" => isset($data["community"]) ? stripslashes($data["community"]) : ""
						),
						"oid_num_$id" => array(
							"friendly_name" => "OID",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "Specify the OID to write to.",
							"value" => isset($data["oid_num"]) ? stripslashes($data["oid_num"]) : ""
						),
						"oid_type_$id" => array(
							"friendly_name" => "OID Type",
							"method" => "drop_array",
							"default" => '',
							"description" => "Type of value to write to the OID.",
							"value" => isset($data["oid_type"]) ? $data["oid_type"] : "",
							'array' => array('i' => 'Integer', 'u' => 'Unsigned Integer', 't' => 'Timeticks', 'a' => 'IP Address', 'o' => 'OID', 's' => 'String', 'x' => 'Hex String', 'd' => 'Decimal String'),
						),
						"oid_value_$id" => array(
							"friendly_name" => "Value",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "This is the value to send (Leave blank to send the current threshold value).",
							"value" => isset($data["oid_value"]) ? stripslashes($data["oid_value"]) : ""
						),

					);
					draw_edit_form(
						array(
							"config" => array(
							"no_form_tag" => true
							),
						"fields" => $form_array,
						)
					);
					html_end_box();

					break;
				case 'script':
					html_start_box("", "100%", $colors["header"], "3", "center", "");
					$id = $alert['id'];
					$data = $alert['data'];
					$data = unserialize(base64_decode($data));
					$form_array = array(
						"alert_header_$id" => array(
							"friendly_name" => "<table width='100%' cellpadding=0 cellspacing=0><tr><td><font color=white size=2><b>Script</b></font></td><td align=right><a href='thold_templates.php?delete_alert=$id&id=$template_id'><font color=white size=2><b>X</b></font></a></td></tr></table>",
							"method" => "spacer",
						),
						"type_$id" => array(
							"method" => "hidden",
							"value" => 'script',
						),
						"repeat_fail_$id" => array(
							"friendly_name" => "Alert After",
							"method" => "drop_array",
							"default" => '1',
							"description" => "Alert after this number of failed polling intervals.",
							"value" => isset($alert["repeat_fail"]) ? $alert["repeat_fail"] : "",
							'array' => $alertarray,
						),
						"repeat_alert_$id" => array(
							"friendly_name" => "Re-Alert Cycle",
							"method" => "drop_array",
							"default" => read_config_option("alert_repeat"),
							"description" => "Repeat alert after specified number of cycles.",
							"value" => isset($alert["repeat_alert"]) ? $alert["repeat_alert"] : "",
							'array' => $repeatarray,
						),
						"path_$id" => array(
							"friendly_name" => "Script Name",
							"method" => "drop_array",
							"description" => "Specify the name of the script.  It must reside in the thold/scripts/ directory.",
							"value" => isset($data["path"]) ? stripslashes($data["path"]) : "",
							'array' => $scripts,
						),
						"args_$id" => array(
							"friendly_name" => "Script Arguments",
							"method" => "textbox",
							"max_length" => 255,
							"description" => "Specify the extra arguments to pass to the script.",
							"value" => isset($data["args"]) ? stripslashes($data["args"]) : ""
						),
					);
					draw_edit_form(
						array(
							"config" => array(
							"no_form_tag" => true
							),
						"fields" => $form_array,
						)
					);
					html_end_box();

					break;
				default:
					do_hook_function('thold_alert_show', array($item));
					break;
			}
		}
	}

	form_save_button("thold_templates.php?id=" . $id, "save");
	print "<br><center><h1>Add New Alerts</h1></center><a name='newalerts'></a>";

	print '<form action=thold_templates.php method=post>';
	print "<input type='hidden' name='id' value='" . $thold_item_data['id'] . "'>";
	print "<input type='hidden' name='save' value='edit'>";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$form_array = array(
		"alert_header" => array(
			"friendly_name" => "Email Alert",
			"method" => "spacer",
			),
			"new_alert" => array(
				"friendly_name" => "Add new alert",
				"method" => "drop_array",
				"default" => "NULL",
				"description" => "",
				"value" => 0,
				"array" => $new_alerts
			),
	);

	draw_edit_form(
		array(
			"config" => array(
			"no_form_tag" => true
			),
			"fields" => $form_array,
		)
	);

	html_end_box();
	form_save_button("thold_templates.php?id=" . $thold_item_data['id'], "create");
} else {

	form_save_button("thold_templates.php?id=" . $thold_item_data['id'], "save");
}


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
	}

	BL_EnableDisable();
	document.THold.bl_enabled.onclick = BL_EnableDisable;

	function changeTholdType () {
		type = document.getElementById('thold_type').value;
		switch(type) {
		case '0':
			thold_toggle_hilow ('');
			thold_toggle_baseline ('none');
			thold_toggle_time ('none');
			break;
		case '1':
			thold_toggle_hilow ('none');
			thold_toggle_baseline ('');
			thold_toggle_time ('none');
			break;
		case '2':
			thold_toggle_hilow ('none');
			thold_toggle_baseline ('none');
			thold_toggle_time ('');
			break;
		}
	}

	function changeDataType () {
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

	function thold_toggle_hilow (status) {
		document.getElementById('row_thold_header').style.display  = status;
		document.getElementById('row_thold_hi').style.display  = status;
		document.getElementById('row_thold_low').style.display  = status;
	}

	function thold_toggle_baseline (status) {
		document.getElementById('row_baseline_header').style.display  = status;
		document.getElementById('row_bl_enabled').style.display  = status;
		document.getElementById('row_bl_ref_time').style.display  = status;
		document.getElementById('row_bl_ref_time_range').style.display  = status;
		document.getElementById('row_bl_pct_up').style.display  = status;
		document.getElementById('row_bl_pct_down').style.display  = status;
		document.getElementById('row_bl_fail_trigger').style.display  = status;
	}

	function thold_toggle_time (status) {
		document.getElementById('row_time_header').style.display  = status;
		document.getElementById('row_time_hi').style.display  = status;
		document.getElementById('row_time_low').style.display  = status;
		document.getElementById('row_time_fail_trigger').style.display  = status;
		document.getElementById('row_time_fail_length').style.display  = status;
	}

	changeTholdType ();
	changeDataType ();

	</script>
	<?php

}

function templates() {
	global $colors, $ds_actions;

	html_start_box('<strong>Threshold Templates</strong>', '100%', $colors['header'], '3', 'center', 'thold_templates.php?action=add');

	html_header_checkbox(array('Name', 'Data Template', 'DS Name', 'Type', 'High', 'Low', 'Duration'));

	$template_list = db_fetch_assoc('SELECT *
		FROM thold_template
		ORDER BY data_template_name');

	$i = 0;
	$types = array('High/Low', 'Baseline', 'Time Based');
	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $template["id"]); $i++;
			form_selectable_cell('<a class="linkEditMain" href="thold_templates.php?action=edit&id=' . $template['id'] . '">' . ($template['name'] == '' ? $template['data_template_name'] . ' [' . $template['data_source_name'] . ']' : $template['name']) . '</a>', $template["id"]);
			form_selectable_cell($template['data_template_name'], $template["id"]);
			form_selectable_cell($template['data_source_name'], $template["id"]);
			form_selectable_cell($types[$template['thold_type']], $template["id"]);
			form_selectable_cell(($template['thold_type'] == 0 ? $template['thold_hi'] : $template['time_hi']), $template["id"]);
			form_selectable_cell(($template['thold_type'] == 0 ? $template['thold_low'] : $template['time_low']), $template["id"]);
			form_selectable_cell(($template['thold_type'] == 2 ? plugin_thold_duration_convert($template['data_template_id'], $template['time_fail_length'], 'time', 'data_template_id') : ''), $template["id"]);
			form_checkbox_cell($template['data_template_name'], $template["id"]);
			form_end_row();
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
