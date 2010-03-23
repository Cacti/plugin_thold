<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010 The Cacti Group                                      |
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


function thold_delete_alert($id) {
	db_execute("DELETE FROM plugin_thold_alerts WHERE id = $id");
}

function thold_add_alert($type, $id) {
	$save = array();
	$save['id'] = 0;
	$save['threshold_id'] = $id;
	$save['type'] = $type;
	$sid = sql_save($save, 'plugin_thold_alerts');
	return $sid;
}

function thold_template_delete_alert($id, $template) {
	db_execute("DELETE FROM plugin_thold_template_alerts WHERE id = $id");
	thold_template_update_thresholds ($template);
}

function thold_template_add_alert($type, $id) {
	$save = array();
	$save['id'] = 0;
	$save['template_id'] = $id;
	$save['type'] = $type;
	$sid = sql_save($save, 'plugin_thold_template_alerts');
	thold_template_update_thresholds ($id);
	return $sid;
}

function thold_template_save_alert () {
	global $config;

	if (isset($_REQUEST['id'])) {
		input_validate_input_number(get_request_var('id'));
		$id = $_REQUEST['id'];
	} else {
		return;
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
		if (substr($p, 0, 15) == 'restored_alert_') {
			$alerts[substr($p, 15)]['restored_alert'] = $v;
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
					$save['restored_alert'] = $v['restored_alert'];
					$save['data'] = base64_encode(serialize(array('notify_accounts' => $v['notify_accounts'], 'notify_extra' => $v['notify_extra'])));
					$aid = sql_save($save , 'plugin_thold_template_alerts');
					break;
				case 'snmp-write':
					$save = array();
					$save['id'] = $p;
					$save['template_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					$save['restored_alert'] = $v['restored_alert'];
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
					$save['restored_alert'] = $v['restored_alert'];
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
	thold_template_update_thresholds ($id);
}

function thold_save_alert () {
	global $config;

	if (isset($_REQUEST['thold_id'])) {
		input_validate_input_number(get_request_var('thold_id'));
		$id = $_REQUEST['thold_id'];
	} else {
		return;
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
		if (substr($p, 0, 15) == 'restored_alert_') {
			$alerts[substr($p, 15)]['restored_alert'] = $v;
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
					$save['threshold_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					$save['restored_alert'] = $v['restored_alert'];
					$save['data'] = base64_encode(serialize(array('notify_accounts' => $v['notify_accounts'], 'notify_extra' => $v['notify_extra'])));
					$aid = sql_save($save , 'plugin_thold_alerts');
					break;
				case 'snmp-write':
					$save = array();
					$save['id'] = $p;
					$save['threshold_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					$save['restored_alert'] = $v['restored_alert'];
					if (!isset($v['oid_host'])) $v['oid_host'] = '';
					$save['data'] = base64_encode(serialize(array('oid_host' => $v['oid_host'], 'oid_num' => $v['oid_num'], 'community' => $v['community'], 'oid_type' => $v['oid_type'], 'oid_value' => $v['oid_value'])));
					$aid = sql_save($save , 'plugin_thold_alerts');
					break;
				case 'script':
					$save = array();
					$save['id'] = $p;
					$save['threshold_id'] = $id;
					$save['repeat_alert'] = $v['repeat_alert'];
					$save['repeat_fail'] = $v['repeat_fail'];
					$save['restored_alert'] = $v['restored_alert'];
					if (in_array($v['path'], $scripts)) {
						$v['args'] = str_replace(array('|'), '', $v['args']);
						$save['data'] = base64_encode(serialize(array('args' => $v['args'], 'path' => basename($v['path']))));
					}
					$aid = sql_save($save , 'plugin_thold_alerts');
					break;
			}
		}
	}

	do_hook('thold_alert_save');
}

function thold_send_alert($item, $status = true) {
	global $config;
	if ($status) {
		$rows = db_fetch_assoc('SELECT * FROM plugin_thold_alerts WHERE threshold_id = ' . $item['id'] . ' AND (repeat_fail = ' . $item['thold_fail_count'] . ' OR MOD(' . $item['thold_fail_count'] . ', repeat_alert) = 0)');
	} else {
		$rows = db_fetch_assoc('SELECT * FROM plugin_thold_alerts WHERE threshold_id = ' . $item['id'] . ' AND repeat_fail < ' . ($item['thold_fail_count'] + 1));
	}

	if (count($rows)) {
		foreach($rows as $row) {
			switch ($row['type']) {
				case 'email':
					if ($status || $row['restored_alert'] == 'off') {
						$row['data'] = unserialize(base64_decode($row['data']));
						$emailsarr = db_fetch_assoc('SELECT data from plugin_thold_contacts WHERE id IN (' . $row['data']['notify_accounts'] . ')');
						$emails = array();
						foreach ($emailsarr as $e) {
							$emails[] = $e['data'];
						}
						$emails = implode(',', $emails);
						if (trim($row['data']['notify_extra']) != '') {
							$emails .= ($email == '' ? '' : ',') . $row['data']['notify_extra'];
						}
						cacti_log("Sending email (" . $item['thold_fail_count'] . ") to " . $emails);
						thold_mail($emails, '', $item['subject'], $item['msg'], $item['file_array']);
					}
					break;
				case 'snmp-write':
					if ($status || $row['restored_alert'] == 'off') {
						$row['data'] = unserialize(base64_decode($row['data']));
						print "     Sending SNMP Write\n";;
						thold_snmp_write ($item);


						if (isset($row['data']['oid_host']) && trim($row['data']['oid_host']) != '') {

						} else {
							$row['data']['oid_host'] = db_fetch_cell("SELECT hostname FROM host WHERE id = " . $item['host_id']);
						}

						if (trim($row['data']['oid_value']) == '') {
							$row['data']['oid_value'] = $item['lastread'];
						}
						snmpset($row['data']['oid_host'], $row['data']['community'], $row['data']['oid_num'], $row['data']['oid_type'], $row['data']['oid_value']);
					}
					break;
				case 'script':
					if ($status || $row['restored_alert'] == 'off') {
						$row['data'] = unserialize(base64_decode($row['data']));
						$args = $row['data']['args'];

						$args = do_hook_function('plugin_thold_script_args', $args);

						$args = str_replace('<DESCRIPTION>', $item['fields']['description'], $args);
						$args = str_replace('<HOSTNAME>', $item['fields']['hostname'], $args);
						$args = str_replace('<TIME>', $item['fields']['time'], $args);
						$args = str_replace('<GRAPH_ID>', $item['graph_id'], $args);
						$args = str_replace('<RRA_ID>', $item['rra_id'], $args);
						$args = str_replace('<DATA_ID>', $item['data_id'], $args);
						$args = str_replace('<DATA_TEMPLATE>', $item['data_template'], $args);
						$args = str_replace('<GRAPH_TEMPLATE>', $item['graph_template'], $args);
						$args = str_replace('<CURRENTVALUE>', $item['lastread'], $args);
						$args = str_replace('<NAME>', $item['name'], $args);
						$args = str_replace('<THOLD_ID>', $item['id'], $args);
						$args = str_replace('<DSNAME>', $item['fields']['dsname'], $args);
						$args = str_replace('<THOLDTYPE>', $types[$item['thold_type']], $args);
						$args = str_replace('<HI>', ($item['thold_type'] == 0 ? $item['thold_hi'] : ($item['thold_type'] == 2 ? $item['time_hi'] : '')), $args);
						$args = str_replace('<LOW>', ($item['thold_type'] == 0 ? $item['thold_low'] : ($item['thold_type'] == 2 ? $item['time_low'] : '')), $args);
						$args = str_replace('<TRIGGER>', ($item['thold_type'] == 0 ? $item['thold_fail_trigger'] : ($item['thold_type'] == 2 ? $item['time_fail_trigger'] : '')), $args);
						$args = str_replace('<DURATION>', ($item['thold_type'] == 2 ? plugin_thold_duration_convert($item['rra_id'], $item['time_fail_length'], 'time') : ''), $args);
						$args = str_replace('<DATE_RFC822>', date(DATE_RFC822), $args);
						$args = str_replace('<DEVICENOTE>', $item['fields']['notes'], $args);
						$args = str_replace(array('|', '<', '>'), '', $args);

						$command_string = $config['base_path'] . '/plugins/thold/scripts/' . $row['data']['path'];
						if (substr($row['data']['path'], -4) == '.php') {
								$command_string = trim(read_config_option("path_php_binary"));
								$args = ' -q ' . $config['base_path'] . '/plugins/thold/scripts/' . $row['data']['path'] . " $args";
						}

						exec_background($command_string, $args);

						cacti_log("Running Script : $command_string $args");
					}

					break;
				default:
					// Method is not yet supported
				break;
			}
		}
	}
}

function thold_snmp_write ($item) {


}

function thold_initialize_rusage() {
	global $thold_start_rusage;
	if (function_exists("getrusage")) {
		$thold_start_rusage = getrusage();
	}
	$thold_start_rusage["microtime"] = microtime();
}

function thold_display_rusage() {
	global $colors, $thold_start_rusage;

	if (function_exists("getrusage")) {
		$dat = getrusage();

		html_start_box("", "100%", $colors["header"], "3", "left", "");
		print "<tr>";

		if (!isset($thold_start_rusage["ru_nswap"])) {
			//print "<td colspan='10'>ERROR: Can not display RUSAGE please call thold_initialize_rusage first</td>";
		}else{
			$i_u_time = $thold_start_rusage["ru_utime.tv_sec"] + ($thold_start_rusage["ru_utime.tv_usec"] * 1E-6);
			$i_s_time = $thold_start_rusage["ru_stime.tv_sec"] + ($thold_start_rusage["ru_stime.tv_usec"] * 1E-6);
			$s_s      = $thold_start_rusage["ru_nswap"];
			$s_pf     = $thold_start_rusage["ru_majflt"];

			list($micro,$seconds) = split(" ", $thold_start_rusage["microtime"]);
			$start_time = $seconds + $micro;
			list($micro,$seconds) = split(" ", microtime());
			$end_time   = $seconds + $micro;

			$utime    = ($dat["ru_utime.tv_sec"] + ($dat["ru_utime.tv_usec"] * 1E-6)) - $i_u_time;
			$stime    = ($dat["ru_stime.tv_sec"] + ($dat["ru_stime.tv_usec"] * 1E-6)) - $i_s_time;
			$swaps    = $dat["ru_nswap"] - $s_s;
			$pages    = $dat["ru_majflt"] - $s_pf;

			print "<td colspan='10' width='1%' style='text-align:left;'>";
			print "<b>Time:</b>&nbsp;" . round($end_time - $start_time,2) . " seconds, ";
			print "<b>User:</b>&nbsp;" . round($utime,2) . " seconds, ";
			print "<b>System:</b>&nbsp;" . round($stime,2) . " seconds, ";
			print "<b>Swaps:</b>&nbsp;" . ($swaps) . " swaps,";
			print "<b>Pages:</b>&nbsp;" . ($pages) . " pages";
			print "</td>";
		}

		print "</tr>";
		html_end_box(false);
	}
}

function thold_legend() {
	global $colors, $thold_bgcolors;

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['red'] . ";'><b>Alarm</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['orange'] . ";'><b>Warning</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['yellow'] . ";'><b>Notice</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['green'] . ";'><b>Ok</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['grey'] . ";'><b>Disabled</b></td>";
	print "</tr>";
	html_end_box(false);
}

function host_legend() {
	global $colors, $host_colors, $disabled_color, $notmon_color;

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_DOWN] . ";'><b>Down</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_UP] . ";'><b>Up</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_RECOVERING] . ";'><b>Recovering</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_UNKNOWN] . ";'><b>Unknown</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $notmon_color . ";'><b>Not Monitored</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $disabled_color . ";'><b>Disabled</b></td>";
	print "</tr>";
	html_end_box(false);
}

// Update automatically 'alert_base_url' if not set and if we are called from the browser
// so that check-thold can pick it up
if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['PHP_SELF']) && read_config_option('alert_base_url') == '') {
	$dir = dirname($_SERVER['PHP_SELF']);
	if (strpos($dir, '/plugins/') !== false)
		$dir = substr($dir, 0, strpos($dir, '/plugins/'));
	db_execute("replace into settings (name,value) values ('alert_base_url', '" . ("http://" . $_SERVER['HTTP_HOST'] . $dir . "/") . "')");

	/* reset local settings cache so the user sees the new settings */
	kill_session_var('sess_config_array');
}

function thold_expression_rpn_pop(&$stack) {
	global $rpn_error;

	if (sizeof($stack)) {
		return array_pop($stack);
	}else{
		$rpn_error = true;
		return false;
	}
}

function thold_expression_math_rpn($operator, &$stack) {
	global $rpn_error;

	switch($operator) {
	case '+':
	case '-':
	case '/':
	case '*':
	case '%':
	case '^':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			eval("\$v3 = " . $v2 . ' ' . $operator . ' ' . $v1 . ';');
			array_push($stack, $v3);
		}
		break;
	case 'SIN':
	case 'COS':
	case 'TAN':
	case 'ATAN':
	case 'SQRT':
	case 'FLOOR':
	case 'CEIL':
	case 'DEG2RAD':
	case 'RAD2DEG':
	case 'ABS':
	case 'EXP':
	case 'LOG':
		$v1 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			eval("\$v2 = " . $operator . "(" . $v1 . ");");
			array_push($stack, $v2);
		}
		break;
	case 'ATAN2':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			$v3 = atan2($v1, $v2);
			array_push($stack, $v3);
		}
		break;
	case 'ADDNAN':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			if ($v1 == 'NAN' || $v1 == 'U') $v1 = 0;
			if ($v2 == 'NAN' || $v2 == 'U') $v2 = 0;
			array_push($stack, $v1 + $v2);
		}
		break;
	}
}

function thold_expression_boolean_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'UN') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'U' || $v1 == 'NAN') {
			array_push($stack, '1');
		}else{
			array_push($stack, '0');
		}
	}elseif ($operator == 'ISINF') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'INF' || $v1 == 'NEGINF') {
			array_push($stack, '1');
		}else{
			array_push($stack, '0');
		}
	}elseif ($operator == 'IF') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if ($v3 == 0) {
			array_push($stack, $v1);
		}else{
			array_push($stack, $v2);
		}
	}else{
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		/* deal with unknown or infinite data */
		if (($v1 == 'INF' || $v2 == 'INF') ||
			($v1 == 'NAN' || $v2 == 'NAN') ||
			($v1 == 'U' || $v2 == 'U') ||
			($v1 == 'NEGINF' || $v2 == 'NEGINF')) {
			array_push($stack, '0');
		}

		switch($operator) {
		case 'LT':
			if ($v1 < $v2) {
				array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		case 'GT':
			if ($v1 > $v2) {
				array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		case 'LE':
			if ($v1 <= $v2) {
			array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		case 'GE':
			if ($v1 >= $v2) {
				array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		case 'EQ':
			if ($v1 == $v2) {
				array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		case 'NE':
			if ($v1 != $v2) {
				array_push($stack, '1');
			}else{
				array_push($stack, '0');
			}
			break;
		}
	}
}

function thold_expression_compare_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'MAX' || $operator == 'MIN') {
		$v[0] = thold_expression_rpn_pop($stack);
		$v[1] = thold_expression_rpn_pop($stack);

		if (in_array('INF', $v)) {
			array_push($stack, 'INF');
		}elseif (in_array('NEGINF', $v)) {
			array_push($stack, 'NEGINF');
		}elseif (in_array('U', $v)) {
			array_push($stack, 'U');
		}elseif (in_array('NAN', $v)) {
			array_push($stack, 'NAN');
		}elseif ($operator == 'MAX') {
			array_push($stack, max($v));
		}else{
			array_push($stack, min($v));
		}
	}else{
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (($v1 == 'U' || $v1 == 'NAN') ||
			($v2 == 'U' || $v2 == 'NAN') ||
			($v3 == 'U' || $v3 == 'NAN')) {
			array_push($stack, 'U');
		}elseif (($v1 == 'INF' || $v1 == 'NEGINF') ||
			($v2 == 'INF' || $v2 == 'NEGINF') ||
			($v3 == 'INF' || $v3 == 'NEGINF')) {
			array_push($stack, 'U');
		}elseif ($v1 < $v2) {
			if ($v3 >= $v1 && $v3 <= $v2) {
				array_push($stack, $v3);
			}else{
				array_push($stack, 'U');
			}
		}else{
			if ($v3 >= $v2 && $v3 <= $v1) {
				array_push($stack, $v3);
			}else{
				array_push($stack, 'U');
			}
		}
	}
}

function thold_expression_specvals_rpn($operator, &$stack, $count) {
	global $rpn_error;

	if ($operator == 'UNKN') {
		array_push($stack, 'U');
	}elseif ($operator == 'INF') {
		array_push($stack, 'INF');
	}elseif ($operator == 'NEGINF') {
		array_push($stack, 'NEGINF');
	}elseif ($operator == 'COUNT') {
		array_push($stack, $count);
	}elseif ($operator == 'PREV') {
		/* still have to figure this out */
	}
}

function thold_expression_stackops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'DUP') {
		$v1 = thold_expression_rpn_pop($stack);
		array_push($stack, $v1);
		array_push($stack, $v1);
	}elseif ($operator == 'POP') {
		thold_expression_rpn_pop($stack);
	}else{
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		array_push($stack, $v2);
		array_push($stack, $v1);
	}
}

function thold_expression_time_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'NOW') {
		array_push($stack, time());
	}elseif ($operator == 'TIME') {
		/* still need to figure this one out */
	}elseif ($operator == 'LTIME') {
		/* still need to figure this one out */
	}
}

function thold_expression_setops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'SORT') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			sort($v, SORT_NUMERIC);

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'REV') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			$v = array_reverse($v);

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'AVG') {
		$count = thold_expression_rpn_pop($stack);
		if ($count > 0) {
			$total  = 0;
			$inf    = false;
			$neginf = false;
			for($i = 0; $i < $count; $i++) {
				$v = thold_expression_rpn_pop($stack);
				if ($v == 'INF') {
					$inf = true;
				}elseif ($v == 'NEGINF') {
					$neginf = true;
				}else{
					$total += $v;
				}
			}

			if ($inf) {
				array_push($stack, 'INF');
			}elseif ($neginf) {
				array_push($stack, 'NEGINF');
			}else{
				array_push($stack, $total/$count);
			}
		}
	}
}

function thold_expression_ds_value($operator, &$stack, $data_sources) {
	global $rpn_error;

	if (sizeof($data_sources)) {
	foreach($data_sources as $rrd_name => $value) {
		if (strtoupper($rrd_name) == $operator) {
			array_push($stack, $value);
			return;
		}
	}
	}

	array_push($stack, 0);
}

function thold_expression_specialtype_rpn($operator, &$stack, $rra_id, $currentval) {
	switch ($operator) {
	case 'CURRENT_DATA_SOURCE':
		array_push($stack, $currentval);
		break;
	case 'CURRENT_GRAPH_MAXIMUM_VALUE':
		array_push(get_current_value($rra_id, 'upper_limit', 0));
		break;
	case 'CURRENT_GRAPH_MINIMUM_VALUE':
		array_push(get_current_value($rra_id, 'lower_limit', 0));
		break;
	case 'CURRENT_DS_MINIMUM_VALUE':
		array_push(get_current_value($rra_id, 'rrd_minimum', 0));
		break;
	case 'CURRENT_DS_MAXIMUM_VALUE':
		array_push($stack, get_current_value($rra_id, 'rrd_maximum', 0));
		break;
	case 'VALUE_OF_HDD_TOTAL':
		array_push($stack, get_current_value($rra_id, 'hdd_total', 0));
		break;
	case 'ALL_DATA_SOURCES_NODUPS':
	case 'ALL_DATA_SOURCES_DUPS':
		$v1 = 0;
		$all_dsns = array();
		$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = " . $rra_id);
		if (is_array($all_dsns)) {
			foreach ($all_dsns as $dsn) {
				$v1 += get_current_value($rra_id, $dsn['data_source_name'], 0);
			}
		}

		array_push($stack, $v1);
		break;
	default:
		cacti_log('WARNING: CDEF property not implemented yet: ' . $operator, false, 'THOLD');
		array_push($stack, $oldvalue);
		break;
	}
}

function thold_calculate_expression($thold, $currentval, $rrd_update_array_reindexed) {
	global $rpn_error;

	/* set an rpn error flag */
	$rpn_error = false;

	/* operators to support */
	$math       = array('+', '-', '*', '/', '%', '^', 'ADDNAN', 'SIN', 'COS', 'LOG', 'EXP',
		'SQRT', 'ATAN', 'ATAN2', 'FLOOR', 'CEIL', 'DEG2RAD', 'RAD2DEG', 'ABS');
	$boolean    = array('LT', 'LE', 'GT', 'GE', 'EQ', 'NE', 'UN', 'ISNF', 'IF');
	$comparison = array('MIN', 'MAX', 'LIMIT');
	$setops     = array('SORT', 'REV', 'AVG');
	$specvals   = array('UNKN', 'INF', 'NEGINF', 'PREV', 'COUNT');
	$stackops   = array('DUP', 'POP', 'EXC');
	$time       = array('NOW', 'TIME', 'LTIME');
	$spectypes  = array('CURRENT_DATA_SOURCE','CURRENT_GRAPH_MINIMUM_VALUE',
		'CURRENT_GRAPH_MINIMUM_VALUE','CURRENT_DS_MINIMUM_VALUE',
		'CURRENT_DS_MAXIMUM_VALUE','VALUE_OF_HDD_TOTAL',
		'ALL_DATA_SOURCES_NODUPS','ALL_DATA_SOURCES_DUPS');

	/* our expression array */
	$expression = explode(',', $thold['expression']);

	/* out current data sources */
	$data_sources = $rrd_update_array_reindexed[$thold['rra_id']];
	if (sizeof($data_sources)) {
		foreach($data_sources as $key => $value) {
			$key = strtoupper($key);
			$nds[$key] = $value;
		}
		$data_sources = $nds;
	}

	/* now let's process the RPN stack */
	$x = count($expression);

	if ($x == 0) return $currentval;

	/* operation stack for RPN */
	$stack = array();

	/* the current DS values goes on first */
	array_push($stack, $currentval);

	/* current pointer in the RPN operations list */
	$cursor = 0;

	while($cursor < $x) {
		$operator = strtoupper(trim($expression[$cursor]));

		/* is the operator a data source */
		if (is_numeric($operator)) {
			//cacti_log("NOTE: Numeric '$operator'", false, "THOLD");
			array_push($stack, $operator);
		}elseif (array_key_exists($operator, $data_sources)) {
			//cacti_log("NOTE: DS Value '$operator'", false, "THOLD");
			thold_expression_ds_value($operator, $stack, $data_sources);
		}elseif (in_array($operator, $comparison)) {
			//cacti_log("NOTE: Compare '$operator'", false, "THOLD");
			thold_expression_compare_rpn($operator, $stack);
		}elseif (in_array($operator, $boolean)) {
			//cacti_log("NOTE: Boolean '$operator'", false, "THOLD");
			thold_expression_boolean_rpn($operator, $stack);
		}elseif (in_array($operator, $math)) {
			//cacti_log("NOTE: Math '$operator'", false, "THOLD");
			thold_expression_math_rpn($operator, $stack);
		}elseif (in_array($operator, $setops)) {
			//cacti_log("NOTE: SetOps '$operator'", false, "THOLD");
			thold_expression_setops_rpn($operator, $stack);
		}elseif (in_array($operator, $specvals)) {
			//cacti_log("NOTE: SpecVals '$operator'", false, "THOLD");
			thold_expression_specvals_rpn($operator, $stack, $cursor + 2);
		}elseif (in_array($operator, $stackops)) {
			//cacti_log("NOTE: StackOps '$operator'", false, "THOLD");
			thold_expression_stackops_rpn($operator, $stack);
		}elseif (in_array($operator, $time)) {
			//cacti_log("NOTE: Time '$operator'", false, "THOLD");
			thold_expression_time_rpn($operator, $stack);
		}elseif (in_array($operator, $spectypes)) {
			//cacti_log("NOTE: SpecialTypes '$operator'", false, "THOLD");
			thold_expression_specialtype_rpn($operator, $stack, $thold['rra_id'], $currentval);
		}else{
			cacti_log("WARNING: Unsupported Field '$operator'", false, "THOLD");
			$rpn_error = true;
		}

		$cursor++;

		if ($rpn_error) {
			cacti_log("ERROR: RPN Expression is invalid '" . $currentval . "," . $thold['expression'] . "'", false, 'THOLD');
			return 0;
		}
	}

	return $stack[0];
}

function thold_calculate_percent($thold, $currentval, $rrd_update_array_reindexed) {
	$ds = $thold['percent_ds'];
	if (isset($rrd_update_array_reindexed[$thold['rra_id']][$ds])) {
		$t = $rrd_update_array_reindexed[$thold['rra_id']][$thold['percent_ds']];
		if ($t != 0) {
			$currentval = ($currentval / $t) * 100;
		} else {
			$currentval = 0;
		}
	} else {
		$currentval = '';
	}
	return $currentval;
}

function thold_user_auth_threshold ($rra) {
	$current_user = db_fetch_row("select policy_graphs,policy_hosts,policy_graph_templates from user_auth where id=" . $_SESSION["sess_user_id"]);
	$sql_where = 'WHERE ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
	$graphs = db_fetch_assoc('SELECT DISTINCT graph_templates_graph.local_graph_id
		FROM data_template_rrd
		LEFT JOIN graph_templates_item ON graph_templates_item.task_item_id = data_template_rrd.id
		LEFT JOIN graph_local ON (graph_local.id=graph_templates_item.local_graph_id)
		LEFT JOIN host ON graph_local.host_id = host.id
		LEFT JOIN graph_templates_graph ON graph_templates_graph.local_graph_id = graph_local.id
		LEFT JOIN graph_templates ON (graph_templates.id=graph_templates_graph.graph_template_id)
		LEFT JOIN user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . "))
		$sql_where
		AND data_template_rrd.local_data_id = $rra");
	if (!empty($graphs)) {
		return true;
	}
	return false;
}

function thold_log($save){
	$save['id'] = 0;
	if (read_config_option('thold_log_cacti') == 'on') {
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $save['threshold_id'], FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template'], FALSE);

		if ($save['status'] == 0) {
			$desc = "Threshold Restored  ID: " . $save['threshold_id'];
		} else {
			$desc = "Threshold Breached  ID: " . $save['threshold_id'];
		}
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $thold['data_source_name'];

		$types = array('High/Low', 'Baseline', 'Time Based');
		$desc .= '  Type: ' . $types[$thold['thold_type']];
		$desc .= '  Enabled: ' . $thold['thold_enabled'];
		switch ($thold['thold_type']) {
			case 0:
				$desc .= '  Current: ' . $save['current'];
				$desc .= '  High: ' . $thold['thold_hi'];
				$desc .= '  Low: ' . $thold['thold_low'];
				$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['thold_fail_trigger'], 'alert');
				break;
			case 1:
				$desc .= '  Current: ' . $save['current'];
				break;
			case 2:
				$desc .= '  Current: ' . $save['current'];
				$desc .= '  High: ' . $thold['time_hi'];
				$desc .= '  Low: ' . $thold['time_low'];
				$desc .= '  Trigger: ' . $thold['time_fail_trigger'];
				$desc .= '  Time: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['time_fail_length'], 'time');
				break;
		}
		if ($save['status'] != 1) {
			thold_cacti_log($desc);
		}
	}
	unset($save['emails']);
	$id = sql_save($save, 'plugin_thold_log');
}

function plugin_thold_duration_convert($rra, $data, $type, $field = 'local_data_id') {
	/* handle a null data value */
	if ($data == '') {
		return '';
	}

	$step = db_fetch_cell("SELECT rrd_step FROM data_template_data WHERE $field = $rra");
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

	switch ($type) {
		case 'repeat':
			return (isset($repeatarray[$data]) ? $repeatarray[$data] : $data);
			break;
		case 'alert':
			return (isset($alertarray[$data]) ? $alertarray[$data] : $data);
			break;
		case 'time':
			return (isset($timearray[$data]) ? $timearray[$data] : $data);
			break;
	}
	return $data;
}

function plugin_thold_log_changes($id, $changed, $message = array()) {
	global $config;
	$desc = '';

	if (read_config_option('thold_log_changes') != 'on') {
		return;
	}

	if (isset($_SESSION['sess_user_id'])) {
		$user = db_fetch_row('SELECT username FROM user_auth WHERE id = ' . $_SESSION['sess_user_id']);
		$user = $user['username'];
	} else {
		$user = 'Unknown';
	}

	switch ($changed) {
		case 'enabled_threshold':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
			$desc = "Enabled Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];
			break;
		case 'disabled_threshold':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
			$desc = "Disabled Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];
			break;
		case 'enabled_host':
			$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
			$desc = "User: $user  Enabled Host[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
			break;
		case 'disabled_host':
			$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
			$desc = "User: $user  Disabled Host[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
			break;
		case 'auto_created':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
			$desc = "Auto-created Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];
			break;
		case 'created':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
			$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
			$desc = "Created Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $ds;
			break;
		case 'deleted':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
			$desc = "Deleted Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];
			break;
		case 'deleted_template':
			$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);
			$desc = "Deleted Template  User: $user  ID: $id";
			$desc .= '  DataTemplate: ' . $thold['data_template_name'];
			$desc .= '  DataSource: ' . $thold['data_source_name'];
			break;
		case 'modified':
			$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
/*
			$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data FROM plugin_thold_contacts, plugin_thold_threshold_contact WHERE plugin_thold_contacts.id = plugin_thold_threshold_contact.contact_id AND plugin_thold_threshold_contact.thold_id = ' . $id);
			$alert_emails = array();
			if (count($rows)) {
				foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
				}
			}
			$alert_emails = implode(',', $alert_emails);
			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
*/
			if ($message['id'] > 0) {
				$desc = "Modified Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			} else {
				$desc = "Created Threshold  User: $user  ID:  <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
			}

			$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);

			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];

			if ($message['template_enabled'] == 'on') {
				$desc .= '  Use Template: On';
			} else {
				$types = array('High/Low', 'Baseline', 'Time Based');
				$desc .= '  Type: ' . $types[$message['thold_type']];
				$desc .= '  Enabled: ' . $message['thold_enabled'];
				switch ($message['thold_type']) {
					case 0:
						$desc .= '  High: ' . $message['thold_hi'];
						$desc .= '  Low: ' . $message['thold_low'];
//						$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $message['thold_fail_trigger'], 'alert');
						break;
					case 1:
						$desc .= '  Enabled: ' . $message['bl_enabled'];
						$desc .= '  Reference: ' . $message['bl_ref_time'];
						$desc .= '  Range: ' . $message['bl_ref_time_range'];
						$desc .= '  Dev Up: ' . (isset($message['bl_pct_up'])? $message['bl_pct_up'] : "" );
						$desc .= '  Dev Down: ' . (isset($message['bl_pct_down'])? $message['bl_pct_down'] : "" );
						$desc .= '  Trigger: ' . $message['bl_fail_trigger'];
						break;
					case 2:
						$desc .= '  High: ' . $message['time_hi'];
						$desc .= '  Low: ' . $message['time_low'];
						$desc .= '  Trigger: ' . $message['time_fail_trigger'];
						$desc .= '  Time: ' . plugin_thold_duration_convert($thold['rra_id'], $message['time_fail_length'], 'time');
						break;
				}
				$desc .= '  CDEF: ' . $message['cdef'];
//				$desc .= '  Emails: ' . $alert_emails;
			}
			break;
		case 'modified_template':
			$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);
/*
			$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data FROM plugin_thold_contacts, plugin_thold_template_contact WHERE plugin_thold_contacts.id = plugin_thold_template_contact.contact_id AND plugin_thold_template_contact.template_id = ' . $id);
			$alert_emails = array();
			if (count($rows)) {
				foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
				}
			}
			$alert_emails = implode(',', $alert_emails);
			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
*/
			if ($message['id'] > 0) {
				$desc = "Modified Template  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id'>$id</a>";
			} else {
				$desc = "Created Template  User: $user  ID:  <a href='" . $config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id'>$id</a>";
			}

			$desc .= '  DataTemplate: ' . $thold['data_template_name'];
			$desc .= '  DataSource: ' . $thold['data_source_name'];

			$types = array('High/Low', 'Baseline', 'Time Based');
			$desc .= '  Type: ' . $types[$message['thold_type']];
			$desc .= '  Enabled: ' . $message['thold_enabled'];
			switch ($message['thold_type']) {
				case 0:
					$desc .= '  High: ' . (isset($message['thold_hi']) ? $message['thold_hi'] : '');
					$desc .= '  Low: ' . (isset($message['thold_low']) ? $message['thold_low'] : '');
					$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id');
					break;
				case 1:
					$desc .= '  Enabled: ' . $message['bl_enabled'];
					$desc .= '  Reference: ' . $message['bl_ref_time'];
					$desc .= '  Range: ' . $message['bl_ref_time_range'];
					$desc .= '  Dev Up: ' . (isset($message['bl_pct_up'])? $message['bl_pct_up'] : "" );
					$desc .= '  Dev Down: ' . (isset($message['bl_pct_down'])? $message['bl_pct_down'] : "" );
					$desc .= '  Trigger: ' . $message['bl_fail_trigger'];
					break;
				case 2:
					$desc .= '  High: ' . $message['time_hi'];
					$desc .= '  Low: ' . $message['time_low'];
					$desc .= '  Trigger: ' . $message['time_fail_trigger'];
					$desc .= '  Time: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_fail_length'], 'alert', 'data_template_id');
					break;
			}
			$desc .= '  CDEF: ' . (isset($message['cdef']) ? $message['cdef']: '');
//			$desc .= '  ReAlert: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['repeat_alert'], 'alert', 'data_template_id');
//			$desc .= '  Emails: ' . $alert_emails;
			break;
	}

	if ($desc != '') {
		thold_cacti_log($desc);
	}
}

function thold_check_threshold ($rra_id, $data_id, $name, $currentval, $cdef) {
	global $config;

	// Maybe set an option for these?
	$debug = false;

	// Do not proceed if we have chosen to globally disable all alerts
	if (read_config_option('thold_disable_all') == 'on') {
		return;
	}

	$alert_exempt = read_config_option('alert_exempt');
	/* check for exemptions */
	$weekday = date('l');
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $alert_exempt == 'on') {
		return;
	}

	/* Get all the info about the item from the database */
	$item = db_fetch_row("SELECT * FROM thold_data WHERE thold_enabled = 'on' AND data_id = " . $data_id);

	/* Return if the item doesn't exist, which means its disabled */
	if (!isset($item['id']))
		return;

	$graph_id = $item['graph_id'];

	// Only alert if Host is in UP mode (not down, unknown, or recovering)
	$hostname = db_fetch_row('SELECT * FROM host WHERE id = ' . $item['host_id']);
	if ($hostname['status'] != 3) {
		return;
	}

	/* Pull the cached name, if not present, it means that the graph hasn't polled yet */
	$t = db_fetch_assoc('SELECT id, name, name_cache FROM data_template_data WHERE local_data_id = ' . $rra_id . ' ORDER BY id LIMIT 1');
	if (isset($t[0]['name_cache']))
		$desc = $t[0]['name_cache'];
	else
		return;
	/* Pull a few default settings */
	$global_alert_address = read_config_option('alert_email');
	$global_notify_enabled = (read_config_option('alert_notify_default') == 'on');
	$global_bl_notify_enabled = (read_config_option('alert_notify_bl') == 'on');
	$logset = (read_config_option('alert_syslog') == 'on');
	$deadnotify = (read_config_option('alert_deadnotify') == 'on');
	$realert = read_config_option('alert_repeat');
	$alert_trigger = read_config_option('alert_trigger');
	$alert_bl_trigger = read_config_option('alert_bl_trigger');
	$httpurl = read_config_option('alert_base_url');
	$thold_show_datasource = read_config_option('thold_show_datasource');
	$thold_send_text_only = read_config_option('thold_send_text_only');
	$thold_alert_text = read_config_option('thold_alert_text');

	// Remove this after adding an option for it
	$thold_show_datasource = true;

	$trigger = ($item['thold_fail_trigger'] == '' ? $alert_trigger : $item['thold_fail_trigger']);
	$alertstat = $item['thold_alert'];

	// Make sure the alert text has been set
	if (!isset($thold_alert_text) || $thold_alert_text == '') {
		$thold_alert_text = "<html><body>An alert has been issued that requires your attention.<br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>";
	}

	$types = array('High/Low', 'Baseline', 'Time Based');

	// Do some replacement of variables
	$thold_alert_text = do_hook_function('plugin_thold_email_text', $thold_alert_text);

	$thold_alert_text = str_replace('<DESCRIPTION>', $hostname['description'], $thold_alert_text);
	$thold_alert_text = str_replace('<HOSTNAME>', $hostname['hostname'], $thold_alert_text);
	$thold_alert_text = str_replace('<TIME>', time(), $thold_alert_text);
	$thold_alert_text = str_replace('<GRAPHID>', $graph_id, $thold_alert_text);
	$thold_alert_text = str_replace('<URL>', "<a href='$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1'>$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1</a>", $thold_alert_text);
	$thold_alert_text = str_replace('<CURRENTVALUE>', $currentval, $thold_alert_text);
	$thold_alert_text = str_replace('<THRESHOLDNAME>', $desc, $thold_alert_text);
	$thold_alert_text = str_replace('<DSNAME>', $name, $thold_alert_text);
	$thold_alert_text = str_replace('<THOLDTYPE>', $types[$item['thold_type']], $thold_alert_text);
	$thold_alert_text = str_replace('<HI>', ($item['thold_type'] == 0 ? $item['thold_hi'] : ($item['thold_type'] == 2 ? $item['time_hi'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<LOW>', ($item['thold_type'] == 0 ? $item['thold_low'] : ($item['thold_type'] == 2 ? $item['time_low'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<TRIGGER>', ($item['thold_type'] == 0 ? $item['thold_fail_trigger'] : ($item['thold_type'] == 2 ? $item['time_fail_trigger'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<DURATION>', ($item['thold_type'] == 2 ? plugin_thold_duration_convert($item['rra_id'], $item['time_fail_length'], 'time') : ''), $thold_alert_text);
	$thold_alert_text = str_replace('<DATE_RFC822>', date(DATE_RFC822), $thold_alert_text);
	$thold_alert_text = str_replace('<DEVICENOTE>', $hostname['notes'], $thold_alert_text);

	$item['fields']['description'] = $hostname['description'];
	$item['fields']['hostname'] = $hostname['hostname'];
	$item['fields']['time'] = time();
	$item['fields']['dsname'] = $name;
	$item['fields']['DATE_RFC822'] = date(DATE_RFC822);
	$item['fields']['DEVICENOTE'] = $hostname['notes'];

	$msg = $thold_alert_text;

	if ($thold_send_text_only == 'on') {
		$file_array = '';
	} else {
		$file_array = array(0 => array('local_graph_id' => $graph_id, 'rra_id' => 0, 'file' => "$httpurl/graph_image.php?local_graph_id=$graph_id&rra_id=0&view_type=tree",'mimetype'=>'image/png','filename'=>$graph_id));
	}

	switch ($item['thold_type']) {
		case 0:	//  HI/Low
			$breach_up = ($item['thold_hi'] != '' && $currentval > $item['thold_hi']);
			$breach_down = ($item['thold_low'] != '' && $currentval < $item['thold_low']);
			if ( $breach_up || $breach_down) {
				$item['thold_fail_count']++;
				$item['thold_alert'] = ($breach_up ? 2 : 1);

				// Re-Alert?

// FIXME - Need to fix re-alert message for individual emails!!!
				$ra = false;
				$status = 1;
				$rows = db_fetch_assoc('SELECT * FROM plugin_thold_alerts WHERE threshold_id = ' . $item['id'] . ' AND (repeat_fail = ' . $item['thold_fail_count'] . ' OR MOD(' . $item['thold_fail_count'] . ', repeat_alert) = 0)');

				$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $item['thold_hi'] : $item['thold_low']) . " with $currentval";

				if (!empty($rows)) {
					$status = 2;
					$item['subject'] = $subject;
					$item['msg'] = $msg;
					$item['file_array'] = $file_array;
					thold_send_alert($item);

					if ($logset == 1) {
						logger($desc, $breach_up, ($breach_up ? $item['thold_hi'] : $item['thold_low']), $currentval, $trigger, $item['thold_fail_count']);
					}
				}

				thold_log(array(
					'type' => 0,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['thold_hi'] : $item['thold_low']),
					'current' => $currentval,
					'status' => $status,
					'description' => $subject
					));

				db_execute('UPDATE thold_data SET thold_alert=' . $item['thold_alert'] . ', thold_fail_count=' . $item['thold_fail_count'] . ' WHERE id = ' . $item['id']);
			} else {
				if ($alertstat != 0) {
					if ($logset == 1) {
						logger($desc, 'ok', 0, $currentval, $trigger, $item['thold_fail_count']);
					}

					if ($item['thold_fail_count'] >= $trigger) {
						$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";
						$item['subject'] = $subject;
						$item['msg'] = $msg;
						$item['file_array'] = $file_array;
						thold_send_alert($item, false);

						thold_log(array(
							'type' => 0,
							'time' => time(),
							'host_id' => $item['host_id'],
							'graph_id' => $graph_id,
							'threshold_id' => $item['id'],
							'threshold_value' => '',
							'current' => $currentval,
							'status' => 0,
							'description' => $subject
							));
					}
				}
				db_execute("UPDATE thold_data SET thold_alert=0, thold_fail_count=0 WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
			}

			break;

		case 1:	//  Baseline
			$bl_alert_prev = $item['bl_alert'];
			$bl_count_prev = $item['bl_fail_count'];
			$bl_fail_trigger = ($item['bl_fail_trigger'] == '' ? $alert_bl_trigger : $item['bl_fail_trigger']);

			$item['bl_alert'] = thold_check_baseline($rra_id, $name, $item['bl_ref_time'], $item['bl_ref_time_range'], $currentval, $item['bl_pct_down'], $item['bl_pct_up']);
			switch($item['bl_alert']) {
				case -2:	// Exception is active
					// Future
					break;
				case -1:	// Reference value not available
					break;

				case 0:		// All clear
					if ($global_bl_notify_enabled && $item['bl_fail_count'] >= $bl_fail_trigger) {
						$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";

						$item['subject'] = $subject;
						$item['msg'] = $msg;
						$item['file_array'] = $file_array;
						thold_send_alert($item);
					}
					$item['bl_fail_count'] = 0;
					break;

				case 1:		// Value is below calculated threshold
				case 2:		// Value is above calculated threshold
					$item['bl_fail_count']++;

					// Re-Alert?
					$ra = ($item['bl_fail_count'] > $bl_fail_trigger && ($item['bl_fail_count'] % ($item['repeat_alert'] == '' ? $realert : $item['repeat_alert'])) == 0);
					if($global_bl_notify_enabled && ($item['bl_fail_count'] ==  $bl_fail_trigger || $ra)) {
						if ($logset == 1) {
							logger($desc, $breach_up, ($breach_up ? $item['thold_hi'] : $item['thold_low']), $currentval, $item['thold_fail_trigger'], $item['thold_fail_count']);
						}
						$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($item['bl_alert'] == 2 ? 'above' : 'below') . " calculated baseline threshold with $currentval";

						$item['subject'] = $subject;
						$item['msg'] = $msg;
						$item['file_array'] = $file_array;
						thold_send_alert($item);
					}
					break;
			}

			$sql  = "UPDATE thold_data SET thold_alert=0, thold_fail_count=0";
			$sql .= ", bl_alert='" . $item['bl_alert'] . "'";
			$sql .= ", bl_fail_count='" . $item['bl_fail_count'] . "'";
			$sql .= " WHERE rra_id='$rra_id' AND data_id=" . $item['data_id'];
			db_execute($sql);
			break;

		case 2:	//  Time Based

			$breach_up = ($item['time_hi'] != '' && $currentval > $item['time_hi']);
			$breach_down = ($item['time_low'] != '' && $currentval < $item['time_low']);

			$item['thold_alert'] = ($breach_up ? 2 : ($breach_down ? 1 : 0));
			$trigger = $item['time_fail_trigger'];
			$step = $item['rrd_step'];
			$time = time() - ($item['time_fail_length'] * $step);
			$failures = db_fetch_cell('SELECT count(id) FROM plugin_thold_log WHERE threshold_id = ' . $item['id'] . ' AND status > 0 AND time > ' . $time);
			if ( $breach_up || $breach_down) {
				$item['thold_fail_count'] = $failures;
				// We should only re-alert X minutes after last email, not every 5 pollings, etc...
				// Re-Alert?
				$realerttime = time() - (($item['repeat_alert'] - 1) * $step);
				$lastemailtime = db_fetch_cell('SELECT time FROM plugin_thold_log WHERE threshold_id = ' . $item['id'] . ' AND status = 2 ORDER BY time DESC LIMIT 1', FALSE);
				$ra = ($failures > $trigger && $item['repeat_alert'] != 0 && $lastemailtime > 1 && ($lastemailtime < $realerttime));
				$status = 1;
				$failures++;
				if ($failures == $trigger || $ra) {
					$status = 2;
				}
				if ($item['repeat_alert'] == 0 && $failures == $trigger) {
					$lastalert = db_fetch_cell('SELECT * FROM plugin_thold_log WHERE threshold_id = ' . $item['id'] . ' ORDER BY time DESC LIMIT 1');
					if ($lastalert['status'] > 1 && $time> $lastalert['time']) {
						$status = 1;
					}
				}
				$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($failures > $trigger ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $item['time_hi'] : $item['time_low']) . " with $currentval";
				if ($status == 2) {
					if ($logset == 1) {
						logger($desc, $breach_up, ($breach_up ? $item['time_hi'] : $item['time_low']), $currentval, $trigger, $failures);
					}

					$item['subject'] = $subject;
					$item['msg'] = $msg;
					$item['file_array'] = $file_array;
					thold_send_alert($item);
				}
				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => $status,
					'description' => $subject
					));

				$sql  = "UPDATE thold_data SET thold_alert=" . $item['thold_alert'] . ", thold_fail_count=" . $failures;
				$sql .= " WHERE rra_id=$rra_id AND data_id=" . $item['data_id'];
				db_execute($sql);
			} else {
				if ($alertstat != 0 && $failures < $trigger) {
					if ($logset == 1)
						logger($desc, 'ok', 0, $currentval, $trigger, $item['thold_fail_count']);
					$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";
					thold_log(array(
						'type' => 2,
						'time' => time(),
						'host_id' => $item['host_id'],
						'graph_id' => $graph_id,
						'threshold_id' => $item['id'],
						'threshold_value' => '',
						'current' => $currentval,
						'status' => 0,
						'description' => $subject
						));

					$sql  = "UPDATE thold_data SET thold_alert=0, thold_fail_count=" . $failures;
					$sql .= " WHERE rra_id=$rra_id AND data_id=" . $item['data_id'];
					db_execute($sql);
				} else {
					$sql  = "UPDATE thold_data SET thold_fail_count=" . $failures;
					$sql .= " WHERE rra_id=$rra_id AND data_id=" . $item['data_id'];
					db_execute($sql);
				}
			}
			break;
	}

	// debugging output
	if ($debug == 1) {
		$filename = $config['base_path'] . '/log/thold.log';
		if (is_writable($filename)) {
			if (!$handle = fopen($filename, 'a')) {
				echo "Cannot open file ($filename)";
				continue;
			}
		} else {
			echo "The file $filename is not writable";
			continue;
		}
		$logdate = date('m-d-y.H:i:s');
		$logout = "$logdate element: $desc alertstat: $alertstat graph_id: $graph_id thold_low: " . $item['thold_low'] . ' thold_hi: ' . $item['thold_hi'] . " rra: $rra trigger: " . $trigger . ' triggerct: ' . $item['thold_fail_count'] . " current: $currentval logset: $logset";
		fwrite($handle, $logout);
		fclose($handle);
	}
}

function logger($desc, $breach_up, $threshld, $currentval, $trigger, $triggerct) {
	define_syslog_variables();

	$desc = do_hook_function('plugin_thold_syslog_message', $desc);

	$syslog_level = read_config_option('thold_syslog_level');
	$syslog_facility = read_config_option('thold_syslog_facility');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}
	if (!isset($syslog_facility)) {
		$syslog_facility = LOG_DAEMON;
	}

	openlog('CactiTholdLog', LOG_PID | LOG_PERROR, $syslog_facility);

	if(strval($breach_up) == 'ok') {
		syslog($syslog_level, $desc . ' restored to normal with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct);
	} else {
		syslog($syslog_level, $desc . ' went ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . $threshld . ' with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct);
	}
}

function thold_cdef_get_usable () {
	$cdef_items = db_fetch_assoc("select * from cdef_items where value = 'CURRENT_DATA_SOURCE' order by cdef_id");
	$cdef_usable = array();
	if (sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			  	$cdef_usable[] =  $cdef_item['cdef_id'];
		}
	}

	return $cdef_usable;
}

function thold_cdef_select_usable_names () {
	$ids = thold_cdef_get_usable();
	$cdefs = db_fetch_assoc('select id, name from cdef');
	$cdef_names[0] = '';
	if (sizeof($cdefs) > 0) {
		foreach ($cdefs as $cdef) {
			if (in_array($cdef['id'], $ids)) {

			  	$cdef_names[$cdef['id']] =  $cdef['name'];
			}
		}
	}
	return $cdef_names;
}

function thold_build_cdef ($id, $value, $rra, $ds) {
	$oldvalue = $value;

	$cdefs = db_fetch_assoc("select * from cdef_items where cdef_id = $id order by sequence");
	if (sizeof($cdefs) > 0) {
		foreach ($cdefs as $cdef) {
		     	if ($cdef['type'] == 4) {
				$cdef['type'] = 6;
				switch ($cdef['value']) {
				case 'CURRENT_DATA_SOURCE':
					$cdef['value'] = $oldvalue; // get_current_value($rra, $ds, 0);
					break;
				case 'CURRENT_GRAPH_MAXIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'upper_limit', 0);
					break;
				case 'CURRENT_GRAPH_MINIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'lower_limit', 0);
					break;
				case 'CURRENT_DS_MINIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'rrd_minimum', 0);
					break;
				case 'CURRENT_DS_MAXIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'rrd_maximum', 0);
					break;
				case 'VALUE_OF_HDD_TOTAL':
					$cdef['value'] = get_current_value($rra, 'hdd_total', 0);
					break;
				case 'ALL_DATA_SOURCES_NODUPS': // you can't have DUPs in a single data source, really...
				case 'ALL_DATA_SOURCES_DUPS':
					$cdef['value'] = 0;
					$all_dsns = array();
					$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = $rra");
					if(is_array($all_dsns)) {
						foreach ($all_dsns as $dsn) {
							$cdef['value'] += get_current_value($rra, $dsn['data_source_name'], 0);
						}
					}
					break;
				default:
					print 'CDEF property not implemented yet: ' . $cdef['value'];
					return $oldvalue;
					break;
				}
			} else if ($cdef['type'] == 6) {
				$regresult = preg_match('/^\|query_(.*)\|$/', $cdef['value'], $matches);
				if($regresult > 0) {
					// Grab result for query
					$cdef['value'] = db_fetch_cell("SELECT `h`.`field_value`
						FROM `poller_item` p, `host_snmp_cache` h
						WHERE `p`.`local_data_id` = '" . $rra . "'
						AND `p`.`host_id` = `h`.`host_id`
						AND `h`.`field_name` = '" . $matches[1] . "'
						AND `p`.`rrd_name` = 'traffic_in'
						AND SUBSTRING_INDEX(`p`.`arg1`, '.', -1 ) = `h`.`snmp_index`", FALSE);
				}
			}
			$cdef_array[] = $cdef;
		}
	}
	$x = count($cdef_array);

	if ($x == 0) return $oldvalue;

	$stack = array(); // operation stack for RPN
	array_push($stack, $cdef_array[0]); // first one always goes on
	$cursor = 1; // current pointer through RPN operations list

	while($cursor < $x) {
		$type = $cdef_array[$cursor]['type'];
		switch($type) {
			case 6:
				array_push($stack, $cdef_array[$cursor]);
				break;
			case 2:
				// this is a binary operation. pop two values, and then use them.
				$v1 = thold_expression_rpn_pop($stack);
				$v2 = thold_expression_rpn_pop($stack);
				$result = thold_rpn($v2['value'], $v1['value'], $cdef_array[$cursor]['value']);
				// put the result back on the stack.
				array_push($stack, array('type'=>6,'value'=>$result));
				break;
			default:
				print 'Unknown RPN type: ';
				print $cdef_array[$cursor]['type'];
				return($oldvalue);
				break;
		}
		$cursor++;
	}

	return $stack[0]['value'];
}

function thold_rpn ($x, $y, $z) {
	switch ($z) {
		case 1:
			return $x + $y;
			break;
		case 2:
			return $x - $y;
			break;
		case 3:
			return $x * $y;
			break;
		case 4:
			if ($y == 0) return (-1);
			return $x / $y;
			break;
		case 5:
			return $x % $y;
			break;
	}
	return '';
}

function delete_old_thresholds () {
	$result = db_fetch_assoc('SELECT id, data_id, rra_id FROM thold_data');
	foreach ($result as $row) {
		$ds_item_desc = db_fetch_assoc('select id, data_source_name from data_template_rrd where id = ' . $row['data_id']);
		if (!isset($ds_item_desc[0]['data_source_name'])) {
			db_execute('DELETE FROM thold_data WHERE id=' . $row['id']);
			db_execute('DELETE FROM plugin_thold_threshold_alerts WHERE thold_id=' . $row['id']);
		}
	}
}

function thold_rrd_last($rra, $cf) {
	global $config;
	$last_time_entry = rrdtool_execute('last ' . trim(get_data_source_path($rra, true)) . ' ' . trim($cf), false, RRDTOOL_OUTPUT_STDOUT);
	return trim($last_time_entry);
}

function get_current_value($rra, $ds, $cdef = 0) {
	global $config;
	$last_time_entry = thold_rrd_last($rra, 'AVERAGE');

	// This should fix and 'did you really mean month 899 errors', this is because your RRD has not polled yet
	if ($last_time_entry == -1)
		$last_time_entry = time();

	$data_template_data = db_fetch_row("SELECT * FROM data_template_data WHERE local_data_id = $rra");

	$step = $data_template_data['rrd_step'];

	// Round down to the nearest 100
	$last_time_entry = (intval($last_time_entry /100) * 100) - $step;
	$last_needed = $last_time_entry + $step;

	$result = rrdtool_function_fetch($rra, trim($last_time_entry), trim($last_needed));

	// Return Blank if the data source is not found (Newly created?)
	if (!isset( $result['data_source_names'])) return '';

	$idx = array_search($ds, $result['data_source_names']);

	// Return Blank if the value was not found (Cache Cleared?)
	if (!isset($result['values'][$idx][0]))
			return '';

	$value = $result['values'][$idx][0];
	if ($cdef != 0)
		$value = thold_build_cdef($cdef, $value, $rra, $ds);
	return round($value, 4);
}

function thold_get_ref_value($rra_id, $ds, $ref_time, $time_range) {
	global $config;

	$real_ref_time = time() - $ref_time;

	$result = rrdtool_function_fetch($rra_id, $real_ref_time - ($time_range / 2), $real_ref_time + ($time_range / 2));

	$idx = array_search($ds, $result['data_source_names']);
	if(count($result['values'][$idx]) == 0) {
		return false;
	}

	return $result['values'][$idx];
}

/* thold_check_exception_periods
 @to-do: This function should check 'globally' declared exceptions, like
 holidays etc., as well as exceptions bound to the speciffic $rra_id. $rra_id
 should inherit exceptions that are assigned on the higher level (i.e. device).

*/
function thold_check_exception_periods($rra_id, $ref_time, $ref_range) {
	// TO-DO
	// Check if the reference time falls into global exceptions
	// Check if the current time falls into global exceptions
	// Check if $rra_id + $ds have an exception (again both reference time and current time)
	// Check if there are inheritances

	// More on the exception concept:
	// -Exceptions can be one time and recurring
	// -Exceptions can be global and assigned to:
	// 	-templates
	//	-devices
	//	-data sources
	//

	return false;
}

/* thold_check_baseline -
 Should be called after hard limits have been checked and only when they are OK

 The function "goes back in time" $ref_time seconds and retrieves the data
 for $ref_range seconds. Then it finds minimum and maximum values and calculates
 allowed deviations from those values.

 @arg $rra_id - the data source to check the data
 @arg $ds - Index of the data_source in the RRD
 @arg $ref_time - Integer value representing reference offset in seconds
 @arg $ref_range - Integer value indicating reference time range in seconds
 @arg $current_value - Current "value" of the data source
 @arg $pct_down - Allowed baseline deviation in % - if set to false will not be considered
 @arg $pct_up - Allowed baseline deviation in % - if set to false will not be considered

 @returns (integer) - integer value that indicates status
   -2 if the exception is active
   -1 if the reference value is not available
   0 if the current value is within the boundaries
   1 if the current value is below the calculated threshold
   2 if the current value is above the calculated threshold
 */
function &thold_check_baseline($rra_id, $ds, $ref_time, $ref_range, $current_value, $pct_down, $pct_up) {
	global $debug;

	// First let's check if either current time or reference time falls within either
	// globally set exceptions or rra itself has some exceptios

	if(thold_check_exception_periods($rra_id, $ref_time, $ref_range)) {
		return -2;	// An exception period is blocking us out...
	}
	$ref_values = thold_get_ref_value($rra_id, $ds, $ref_time, $ref_range);

	if(!$ref_values) {
		// if($debug) echo "Baseline reference value not yet established!\n";
		return -1; // Baseline reference value not yet established
	}
	$current_value = get_current_value($rra_id,$ds);
	$ref_value_max = round(max($ref_values));
	$ref_value_min = round(min($ref_values));

	$blt_low = false;
	$blt_high = false;

	if($pct_down != '') {
		$blt_low = round($ref_value_min - ($ref_value_min * $pct_down / 100));
	}

	if($pct_up != '') {
		$blt_high = round($ref_value_max + ($ref_value_max * $pct_up / 100));
	}

	$failed = 0;

	// Check low boundary
	if($blt_low && $current_value < $blt_low) {
		$failed = 1;
	}

	// Check up boundary
	if($failed == 0 && $blt_high && $current_value > $blt_high) {
		$failed = 2;
	}

	if($debug) {
		echo "RRA: $rra_id : $ds\n";
		echo 'Ref. values count: '. count($ref_values) . "\n";
		echo "Ref. value (min): $ref_value_min\n";
		echo "Ref. value (max): $ref_value_max\n";
		echo "Cur. value: $current_value\n";
		echo "Low bl thresh: $blt_low\n";
		echo "High bl thresh: $blt_high\n";
		echo 'Check against baseline: ';
		switch($failed) {
			case 0:
			echo 'OK';
			break;

			case 1:
			echo 'FAIL: Below baseline threshold!';
			break;

			case 2:
			echo 'FAIL: Above baseline threshold!';
			break;
		}
		echo "\n";
		echo "------------------\n";
	}

	return $failed;
}

function save_thold() {
	global $rra, $banner, $hostid, $config;

	$template_enabled = isset($_POST['template_enabled']) && $_POST['template_enabled'] == 'on' ? $_POST['template_enabled'] : 'off';
	if ($template_enabled == 'on') {
		input_validate_input_number($_POST['rra']);
		input_validate_input_number($_POST['data_template_rrd_id']);

		$rra_id = $_POST['rra'];
		if (!thold_user_auth_threshold ($rra_id)) {
			$banner = '<font color=red><strong>Permission Denied</strong></font>';
			return;
		}
		$data_id = $_POST['data_template_rrd_id'];
		$data = db_fetch_row("SELECT id, template FROM thold_data WHERE rra_id = $rra_id AND data_id = $data_id");
		thold_template_update_threshold ($data['id'], $data['template']);
		$banner = '<font color=green><strong>Record Updated</strong></font>';
		plugin_thold_log_changes($data['id'], 'modified', array('id' => $data['id'], 'template_enabled' => 'on'));
		return true;
	}

	// Make sure this is defined
	$_POST['bl_enabled'] = isset($_POST['bl_enabled']) ? 'on' : 'off';
	$_POST['thold_enabled'] = isset($_POST['thold_enabled']) ? 'on' : 'off';
	$_POST['template_enabled'] = isset($_POST['template_enabled']) ? 'on' : 'off';


	$banner = '<font color=red><strong>';
//	if (($_POST['thold_type'] == 0 && !isset($_POST['thold_hi']) || trim($_POST['thold_hi']) == '') && ($_POST['thold_type'] == 0 && !isset($_POST['thold_low']) || trim($_POST['thold_low']) == '') && (!isset($_POST['bl_ref_time']) || trim($_POST['bl_ref_time'])  == '')) {
//		$banner .= 'You must specify either &quot;High Threshold&quot; or &quot;Low Threshold&quot; or both!<br>RECORD NOT UPDATED!</strong></font>';
//		return;
//	}

	if ($_POST['thold_type'] == 0 && isset($_POST['thold_hi']) && isset($_POST['thold_low']) && trim($_POST['thold_hi']) != '' && trim($_POST['thold_low']) != '' && round($_POST['thold_low'],4) >= round($_POST['thold_hi'],4)) {
		$banner .= 'Impossible thresholds: &quot;High Threshold&quot; smaller than or equal to &quot;Low Threshold&quot;<br>RECORD NOT UPDATED!</strong></font>';
		return;
	}

	if($_POST['thold_type'] == 1 && $_POST['bl_enabled'] == 'on') {
		$banner .= 'With baseline thresholds enabled ';
		if(!thold_mandatory_field_ok('bl_ref_time', 'Reference in the past')) {
			return;
		}
		if((!isset($_POST['bl_pct_down']) || trim($_POST['bl_pct_down']) == '') && (!isset($_POST['bl_pct_up']) || trim($_POST['bl_pct_up']) == '')) {
			$banner .= 'You must specify either &quot;Baseline deviation UP&quot; or &quot;Baseline deviation DWON&quot; or both!<br>RECORD NOT UPDATED!</strong></font>';
			return;
		}
	}

	$existing = db_fetch_assoc('SELECT id FROM thold_data WHERE rra_id = ' . $rra . ' AND data_id = ' . $_POST['data_template_rrd_id']);
	$save = array();
	if (count($existing)) {
		$save['id'] = $existing[0]['id'];
	} else {
		$save['id'] = 0;
		$save['template'] = '';
	}

	input_validate_input_number(get_request_var('thold_hi'));
	input_validate_input_number(get_request_var('thold_low'));
	input_validate_input_number(get_request_var('cdef'));
	input_validate_input_number($_POST['rra']);
	input_validate_input_number($_POST['data_template_rrd_id']);
	input_validate_input_number(get_request_var('thold_type'));
	input_validate_input_number(get_request_var('time_hi'));
	input_validate_input_number(get_request_var('time_low'));
	input_validate_input_number(get_request_var('time_fail_trigger'));
	input_validate_input_number(get_request_var('time_fail_length'));
	input_validate_input_number(get_request_var('data_type'));

	$_POST['name'] = str_replace(array("\\", '"', "'"), '', $_POST['name']);
	$save['name'] = (trim($_POST['name'])) == '' ? '' : $_POST['name'];
	$save['host_id'] = $hostid;
	$save['data_id'] = $_POST['data_template_rrd_id'];
	$save['rra_id'] = $_POST['rra'];
	$save['thold_enabled'] = isset($_POST['thold_enabled']) ? $_POST['thold_enabled'] : '';
	$save['exempt'] = isset($_POST['exempt']) ? $_POST['exempt'] : 'off';
	$save['thold_type'] = $_POST['thold_type'];
	// High / Low
	$save['thold_hi'] = (trim($_POST['thold_hi'])) == '' ? '' : round($_POST['thold_hi'],4);
	$save['thold_low'] = (trim($_POST['thold_low'])) == '' ? '' : round($_POST['thold_low'],4);
	// Time Based
	$save['time_hi'] = (trim($_POST['time_hi'])) == '' ? '' : round($_POST['time_hi'],4);
	$save['time_low'] = (trim($_POST['time_low'])) == '' ? '' : round($_POST['time_low'],4);
	$save['time_fail_trigger'] = (trim($_POST['time_fail_trigger'])) == '' ? '' : $_POST['time_fail_trigger'];
	$save['time_fail_length'] = (trim($_POST['time_fail_length'])) == '' ? '' : $_POST['time_fail_length'];
	// Baseline
	$save['bl_enabled'] = isset($_POST['bl_enabled']) ? $_POST['bl_enabled'] : '';
	$save['cdef'] = (trim($_POST['cdef'])) == '' ? '' : $_POST['cdef'];
	$save['template_enabled'] = $_POST['template_enabled'];

	$save['data_type'] = $_POST['data_type'];
	if (isset($_POST['percent_ds'])) {
		$save['percent_ds'] = $_POST['percent_ds'];
	} else {
		$save['percent_ds'] = '';
	}

	/* Get the Data Template, Graph Template, and Graph */
	$rrdsql = db_fetch_row('SELECT id, data_template_id FROM data_template_rrd WHERE local_data_id=' . $save['rra_id'] . ' ORDER BY id');
	$rrdlookup = $rrdsql['id'];
	$grapharr = db_fetch_row("SELECT local_graph_id, graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id <> '' LIMIT 1");

	$save['graph_id'] = $grapharr['local_graph_id'];
	$save['graph_template'] = $grapharr['graph_template_id'];
	$save['data_template'] = $rrdsql['data_template_id'];

	if (!thold_user_auth_threshold ($save['rra_id'])) {
		$banner = '<font color=red><strong>Permission Denied</strong></font>';
		return;
	}

	if($_POST['bl_enabled'] == 'on') {
		input_validate_input_number(get_request_var('bl_ref_time'));
		input_validate_input_number(get_request_var('bl_ref_time_range'));
		input_validate_input_number(get_request_var('bl_pct_down'));
		input_validate_input_number(get_request_var('bl_pct_up'));
		input_validate_input_number(get_request_var('bl_fail_trigger'));
		$save['bl_ref_time'] = (trim($_POST['bl_ref_time'])) == '' ? '' : $_POST['bl_ref_time'];
		$save['bl_ref_time_range'] = (trim($_POST['bl_ref_time_range'])) == '' ? '' : $_POST['bl_ref_time_range'];
		$save['bl_pct_down'] = (trim($_POST['bl_pct_down'])) == '' ? '' : $_POST['bl_pct_down'];
		$save['bl_pct_up'] = (trim($_POST['bl_pct_up'])) == '' ? '' : $_POST['bl_pct_up'];
		$save['bl_fail_trigger'] = (trim($_POST['bl_fail_trigger'])) == '' ? '' : $_POST['bl_fail_trigger'];
	}

	$id = sql_save($save , 'thold_data');

	if ($id) {
		plugin_thold_log_changes($id, 'modified', $save);
	}

	$banner = '<font color=green><strong>Record Updated</strong></font>';
}

function thold_save_template_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id = ' . $id);
	// ADD SOME SECURITY!!
	if (!empty($contacts)) {
		foreach ($contacts as $contact) {
			db_execute("INSERT INTO plugin_thold_template_contact (template_id, contact_id) VALUES ($id, $contact)");
		}
	}
}

function thold_save_threshold_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ' . $id);
	// ADD SOME SECURITY!!
	foreach ($contacts as $contact) {
		db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) VALUES ($id, $contact)");
	}
}

function thold_mandatory_field_ok($name, $friendly_name) {
	global $banner;
	if(!isset($_POST[$name]) || (isset($_POST[$name]) && (trim($_POST[$name]) == '' || $_POST[$name] <= 0))) {
		$banner .= '&quot;' . $friendly_name . '&quot; must be set to positive integer value!<br>RECORD NOT UPDATED!</strong></font>';
		return false;
	}
	return true;
}

// Create tholds for all possible data elements for a host
function autocreate($hostid) {
	$c = 0;
	$message = '';

	$rralist = db_fetch_assoc("SELECT id, data_template_id FROM data_local where host_id='$hostid'");

	if (!count($rralist)) {
		$_SESSION['thold_message'] = '<font size=-2>No thresholds were created.</font>';
		return 0;
	}

	foreach ($rralist as $row) {
		$local_data_id = $row['id'];
		$data_template_id = $row['data_template_id'];
		$existing = db_fetch_assoc('SELECT id FROM thold_data WHERE rra_id = ' . $local_data_id . ' AND data_id = ' . $data_template_id);
		$template = db_fetch_assoc('SELECT * FROM thold_template WHERE data_template_id = ' . $data_template_id);
		if (count($existing) == 0 && count($template)) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id order by id LIMIT 1");

			$grapharr = db_fetch_row("SELECT local_graph_id, graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id <> '' LIMIT 1");
			$graph = (isset($grapharr['local_graph_id']) ? $grapharr['local_graph_id'] : '');

			if ($graph) {
				for ($y = 0; $y < count($template); $y++) {
					$data_source_name = $template[$y]['data_source_name'];
					$insert = array();

					$desc = db_fetch_cell('SELECT name_cache FROM data_template_data WHERE local_data_id=' . $local_data_id . ' LIMIT 1');
					$ds = db_fetch_row('SELECT data_source_type_id, rrd_heartbeat FROM data_template_rrd WHERE local_data_id=' . $local_data_id . ' AND local_data_template_rrd_id = ' . $template[$y]['data_source_id'] );

					$insert['name'] = $desc . ' [' . $data_source_name . ']';
					$insert['host_id'] = $hostid;
					$insert['rra_id'] = $local_data_id;
					$insert['graph_id'] = $graph;
					$insert['data_template'] = $data_template_id;
					$insert['graph_template'] = $grapharr['graph_template_id'];

					$insert['thold_hi'] = $template[$y]['thold_hi'];
					$insert['thold_low'] = $template[$y]['thold_low'];
					$insert['thold_enabled'] = $template[$y]['thold_enabled'];
					$insert['bl_enabled'] = $template[$y]['bl_enabled'];
					$insert['bl_ref_time'] = $template[$y]['bl_ref_time'];
					$insert['bl_ref_time_range'] = $template[$y]['bl_ref_time_range'];
					$insert['bl_pct_down'] = $template[$y]['bl_pct_down'];
					$insert['bl_pct_up'] = $template[$y]['bl_pct_up'];
					$insert['bl_fail_trigger'] = $template[$y]['bl_fail_trigger'];
					$insert['bl_alert'] = $template[$y]['bl_alert'];
					$insert['cdef'] = $template[$y]['cdef'];
					$insert['template'] = $template[$y]['id'];
					$insert['template_enabled'] = 'on';
					$insert['data_source_name'] = $data_source_name;
					$insert['rrd_step'] = $ds['rrd_heartbeat'];
					$insert['data_source_type_id'] = $ds['data_source_type_id'];



					$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id FROM data_template_rrd where local_data_id='$local_data_id' and data_source_name = '$data_source_name'");

					$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');
					foreach ($rrdlist as $rrdrow) {
						$data_rrd_id=$rrdrow['id'];
						$insert['data_id'] = $data_rrd_id;
						$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE rra_id='$local_data_id' AND data_id='$data_rrd_id'");
						if (count($existing) == 0) {
							$insert['id'] = 0;
							$id = sql_save($insert, 'thold_data');
							if ($id) {
								thold_template_update_threshold ($id, $insert['template']);

								$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id");
								$tname = $l[0]['name'];

								$name = $data_source_name;
								if ($rrdrow['data_input_field_id'] != 0) {
									$l = db_fetch_assoc('SELECT name FROM data_input_fields where id=' . $rrdrow['data_input_field_id']);
									$name = $l[0]['name'];
								}
								plugin_thold_log_changes($id, 'auto_created', " $tname [$name]");
								$message .= "Created threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<br>";
								$c++;
							}
						}
					}
				}
			}
		}
	}
	$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	return $c;
}

/* Sends a group of graphs to a user */
function thold_mail($to, $from, $subject, $message, $filename, $headers = '') {
	global $config;
	include_once($config['base_path'] . '/plugins/settings/include/mailer.php');
	include_once($config['base_path'] . '/plugins/thold/setup.php');

	$subject = trim($subject);

	$message = str_replace('<SUBJECT>', $subject, $message);

	$how = read_config_option('settings_how');
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host = read_config_option('settings_smtp_host');
		$smtp_port = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from = read_config_option('thold_from_email');
		$fromname = read_config_option('thold_from_name');
		if ($from == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cactiusers.org';
			}
		}
		if ($fromname == '')
			$fromname = 'Cacti';

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format('Cacti', $from);
		if ($Mailer->header_set('From', $from) === false) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	if ($to == '')
		return 'Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.';
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set('To', $t)) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '')
		$wordwrap = 76;
	if ($wordwrap > 9999)
		$wordwrap = 9999;
	if ($wordwrap < 0)
		$wordwrap = 76;

	$Mailer->Config['Mail']['WordWrap'] = $wordwrap;

	if (! $Mailer->header_set('Subject', $subject)) {
		print 'ERROR: ' . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	if (is_array($filename) && !empty($filename) && strstr($message, '<GRAPH>') !==0) {
		foreach($filename as $val) {
			$graph_data_array = array('output_flag'=> RRDTOOL_OUTPUT_STDOUT);
  			$data = rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
			if ($data != '') {
				$cid = $Mailer->content_id();
				if ($Mailer->attach($data, $val['filename'].'.png', 'image/png', 'inline', $cid) == false) {
					print 'ERROR: ' . $Mailer->error() . "\n";
					return $Mailer->error();
				}
				$message = str_replace('<GRAPH>', "<br><img src='cid:$cid'>", $message);
			} else {
				$message = str_replace('<GRAPH>', "<br><img src='" . $val['file'] . "'><br>Could not open!<br>" . $val['file'], $message);
			}
		}
	}
	$text = array('text' => '', 'html' => '');
	if ($filename == '') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	$v = thold_version();
	$Mailer->header_set('X-Mailer', 'Cacti-Thold-v' . $v['version']);
	$Mailer->header_set('User-Agent', 'Cacti-Thold-v' . $v['version']);

	if ($Mailer->send($text) == false) {
		print 'ERROR: ' . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	return '';
}

function thold_template_update_threshold ($id, $template) {
	db_execute("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.template_enabled = 'on',
		thold_data.thold_low = thold_template.thold_low,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_enabled = thold_template.bl_enabled,
		thold_data.bl_ref_time = thold_template.bl_ref_time,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.exempt = thold_template.exempt,
		thold_data.data_template = thold_template.data_template_id,
		thold_data.expression = thold_template.expression
		WHERE thold_data.id=$id AND thold_template.id=$template");
	db_execute('DELETE FROM plugin_thold_alerts where threshold_id = ' . $id);
	db_execute("INSERT INTO plugin_thold_alerts (threshold_id, repeat_fail, repeat_alert, restored_alert, type, data) SELECT $id, repeat_fail, repeat_alert, restored_alert, type, data FROM plugin_thold_template_alerts WHERE template_id = $template");

}

function thold_template_update_thresholds ($id) {

	db_execute("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_enabled = thold_template.bl_enabled,
		thold_data.bl_ref_time = thold_template.bl_ref_time,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.exempt = thold_template.exempt,
		thold_data.data_template = thold_template.data_template_id,
		thold_data.data_source_name = thold_template.data_source_name,
		thold_data.expression = thold_template.expression
		WHERE thold_data.template=$id AND thold_data.template_enabled='on' AND thold_template.id=$id");

	$rows = db_fetch_assoc("SELECT id, template FROM thold_data WHERE thold_data.template=$id AND thold_data.template_enabled='on'");

	foreach ($rows as $row) {
		db_execute('DELETE FROM plugin_thold_alerts where threshold_id = ' . $row['id']);
		db_execute('INSERT INTO plugin_thold_alerts (threshold_id, repeat_fail, repeat_alert, restored_alert, type, data) SELECT ' . $row['id'] . ', repeat_fail, repeat_alert, restored_alert, type, data FROM plugin_thold_template_alerts WHERE template_id = ' . $row['template']);
	}
	return;
}

function thold_cacti_log($string) {
	global $config;
	$environ = 'THOLD';
	/* fill in the current date for printing in the log */
	$date = date("m/d/Y h:i:s A");

	/* determine how to log data */
	$logdestination = read_config_option("log_destination");
	$logfile        = read_config_option("path_cactilog");

	/* format the message */
	$message = "$date - " . $environ . ": " . $string . "\n";

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option("log_verbosity") != POLLER_VERBOSITY_NONE)) {
		if ($logfile == "") {
			$logfile = $config["base_path"] . "/log/cacti.log";
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, "a");

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$string = strip_tags($string);
		$log_type = "";
		if (substr_count($string,"ERROR:"))
			$log_type = "err";
		else if (substr_count($string,"WARNING:"))
			$log_type = "warn";
		else if (substr_count($string,"STATS:"))
			$log_type = "stat";
		else if (substr_count($string,"NOTICE:"))
			$log_type = "note";

		if (strlen($log_type)) {
			define_syslog_variables();

			if ($config["cacti_server_os"] == "win32")
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_USER);
			else
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_SYSLOG);

			if (($log_type == "err") && (read_config_option("log_perror"))) {
				syslog(LOG_CRIT, $environ . ": " . $string);
			}

			if (($log_type == "warn") && (read_config_option("log_pwarn"))) {
				syslog(LOG_WARNING, $environ . ": " . $string);
			}

			if ((($log_type == "stat") || ($log_type == "note")) && (read_config_option("log_pstats"))) {
				syslog(LOG_INFO, $environ . ": " . $string);
			}

			closelog();
		}
	}
}

function thold_threshold_enable($id) {
	db_execute("UPDATE thold_data SET thold_enabled='on' WHERE id=$id");
}

function thold_threshold_disable($id) {
	db_execute("UPDATE thold_data SET thold_enabled='off' WHERE id=$id");
}

/* thold_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function thold_save_button($cancel_url, $force_type = "", $key_field = "id") {
	global $config;

	if (empty($force_type)) {
		if (empty($_GET[$key_field])) {
			$value = "Create";
		}else{
			$value = "Save";
		}
	}elseif ($force_type == "save") {
		$value = "Save";
	}elseif ($force_type == "create") {
		$value = "Create";
	}
	?>
	<script type="text/javascript">
	<!--
	function th_returnTo(location) {
		document.location = location;
	}
	-->
	</script>
	<table align='center' width='100%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			<td bgcolor="#f5f5f5" align="right">
				<input type='hidden' name='action' value='save'>
				<input type='button' onClick='th_returnTo("<?php print $cancel_url;?>")' value='Cancel'>
				<input type='submit' value='<?php print $value;?>'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

function thold_actions_dropdown($actions_array) {
	global $config;

	?>
	<table align='center' width='100%'>
		<tr>
			<td width='1' valign='top'>
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='submit' name='go' value='Go'>
			</td>
		</tr>
	</table>

	<input type='hidden' name='action' value='actions'>
	<?php
}
