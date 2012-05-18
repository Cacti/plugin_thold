<?php
/*
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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* global colors */
$thold_bgcolors = array(
	'red'     => 'F21924',
	'orange'  => 'FB4A14',
	'warning' => 'FF7A30',
	'yellow'  => 'FAFD9E',
	'green'   => 'CCFFCC',
	'grey'    => 'CDCFC4');

if (isset($_POST['drp_action'])) {
	do_thold();
} else {
	delete_old_thresholds();
	list_tholds();
}

function do_thold() {
	global $hostid;

	$tholds = array();
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_(.*)$", $var, $matches)) {
			$del = $matches[1];
			$rra = db_fetch_cell("SELECT rra_id FROM thold_data WHERE id=$del");

			input_validate_input_number($del);
			$tholds[$del] = $rra;
		}
	}

	switch ($_POST['drp_action']) {
		case 1:	// Delete
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'deleted', array('id' => $del));
					db_execute("DELETE FROM thold_data WHERE id=$del");
					db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $del);
					db_execute('DELETE FROM plugin_thold_log WHERE threshold_id=' . $del);
				}
			}
			break;
		case 2:	// Disabled
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'disabled_threshold', array('id' => $del));
					db_execute("UPDATE thold_data SET thold_enabled='off' WHERE id=$del");
				}
			}
			break;
		case 3:	// Enabled
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'enabled_threshold', array('id' => $del));
					db_execute("UPDATE thold_data SET thold_enabled='on' WHERE id=$del");
				}
			}
			break;
		case 4:	// Reapply Suggested Name
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					$thold = db_fetch_row("SELECT * FROM thold_data WHERE id=$del");
					/* check if thold templated */
					if ($thold['template_enabled'] == "on") {
						$template = db_fetch_row("SELECT * FROM thold_template WHERE id=" . $thold["template"]);
						$name = thold_format_name($template, $thold["graph_id"], $thold["data_id"], $template['data_source_name']);
						plugin_thold_log_changes($del, 'reapply_name', array('id' => $del));
						db_execute("UPDATE thold_data SET name='$name' WHERE id=$del");
					}
				}
			}
			break;
		case 5:	// Propagate Template
			foreach ($tholds as $thold_id => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					$template = db_fetch_row("SELECT td.template id, td.template_enabled enabled
						FROM thold_data td
						INNER JOIN thold_template tt ON tt.id = td.template
						WHERE td.id = $thold_id"); 
					if (isset($template['id']) && $template['id'] != 0 && $template['enabled'] != 'on') {
						thold_template_update_threshold($thold_id, $template['id']);
						plugin_thold_log_changes($thold_id, 'modified', array('id' => $thold_id, 'template_enabled' => 'on'));
					}
				}
			}
			break;
	}

	if (isset($hostid) && $hostid != '')
		Header('Location:listthold.php?hostid=$hostid');
	else
		Header('Location:listthold.php');

	exit;
}

/**
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function thold_request_validation() {
	global $title, $colors, $rows_selector, $config, $reset_multi;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_thold_list_rows');
		kill_session_var('sess_thold_list_page');
		kill_session_var('sess_thold_list_sort_column');
		kill_session_var('sess_thold_list_sort_direction');
		kill_session_var('sess_thold_list_hostid');
		kill_session_var('sess_thold_list_state');
		kill_session_var('sess_thold_list_template');

		$_REQUEST['page'] = 1;
		unset($_REQUEST['rows']);
		unset($_REQUEST['page']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['hostid']);
		unset($_REQUEST['template']);
		unset($_REQUEST['state']);
		$reset_multi = true;
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('rows', 'sess_thold_list_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_list_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_list_sort_direction');
		$changed += thold_request_check_changed('hostid', 'sess_thold_list_hostid');
		$changed += thold_request_check_changed('state', 'sess_thold_list_state');
		$changed += thold_request_check_changed('template', 'sess_thold_list_template');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember search fields in session vars */
	load_current_session_value('rows', 'sess_thold_list_rows', read_config_option('num_rows_thold'));
	load_current_session_value('page', 'sess_thold_list_current_page', '1');
	load_current_session_value('sort_column', 'sess_thold_list_sort_column', 'thold_alert');
	load_current_session_value('sort_direction', 'sess_thold_list_sort_direction', 'DESC');
	load_current_session_value('state', 'sess_thold_list_state', read_config_option('thold_filter_default'));
	load_current_session_value('hostid', 'sess_thold_list_hostid', '');
	load_current_session_value('template', 'sess_thold_list_template', '');
}

function list_tholds() {
	global $colors, $thold_bgcolors, $config, $hostid;

	$thold_actions = array(1 => 'Delete', 2 => 'Disable', 3 => 'Enable', 4 => 'Reapply Suggested Names', 5 => 'Propagate Template');

	thold_request_validation();

	$statefilter='';
	if (isset($_REQUEST['state'])) {
		if ($_REQUEST['state'] == '-1') {
			$statefilter = '';
		} else {
			if($_REQUEST['state'] == '0') { $statefilter = "thold_data.thold_enabled='off'"; }
			if($_REQUEST['state'] == '2') { $statefilter = "thold_data.thold_enabled='on'"; }
			if($_REQUEST['state'] == '1') { $statefilter = '(thold_data.thold_alert!=0 OR thold_data.bl_alert>0)'; }
			if($_REQUEST['state'] == '3') { $statefilter = '((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger))'; }
		}
	}

	$alert_num_rows = read_config_option('alert_num_rows');
	if ($alert_num_rows < 1 || $alert_num_rows > 999) {
		db_execute("REPLACE INTO settings VALUES ('alert_num_rows', 30)");
		/* pull it again so it updates the cache */
		$alert_num_rows = read_config_option('alert_num_rows', true);
	}

	include($config['include_path'] . '/top_header.php');

	$sql_where = '';

	$sort = $_REQUEST['sort_column'];
	$limit = ' LIMIT ' . ($alert_num_rows*($_REQUEST['page']-1)) . ",$alert_num_rows";

	if (!empty($_REQUEST['hostid']) && $_REQUEST['hostid'] != 'ALL') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_data.host_id = " . $_REQUEST['hostid'];
	}
	if (!empty($_REQUEST['template']) && $_REQUEST['template'] != 'ALL') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_data.data_template = " . $_REQUEST['template'];
	}
	if ($statefilter != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "$statefilter";
	}

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$sql = "SELECT thold_data.*, thold_template.name template_name FROM thold_data
		LEFT JOIN user_auth_perms on ((thold_data.graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ") OR (thold_data.host_id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ") OR (thold_data.graph_template=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . "))
		LEFT JOIN thold_template ON thold_template.id = thold_data.template
		$sql_where
		ORDER BY $sort " . $_REQUEST['sort_direction'] . ", template asc" .
		$limit;
	$result = db_fetch_assoc($sql);

	$sql_where_hid    = 'WHERE ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
	$hostresult = db_fetch_assoc("SELECT DISTINCT host.id, host.description, host.hostname
		FROM host
		INNER JOIN thold_data ON (host.id = thold_data.host_id)
		LEFT JOIN user_auth_perms on (thold_data.host_id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		$sql_where_hid
		ORDER BY description");

	$data_templates = db_fetch_assoc("SELECT DISTINCT data_template.id, data_template.name
		FROM data_template
		INNER JOIN thold_data ON (thold_data.data_template = data_template.id)
		ORDER BY data_template.name");

	?>
	<script type="text/javascript">
	<!--
	function applyTHoldFilterChange(objForm) {
		strURL = '?hostid=' + objForm.hostid.value;
		strURL = strURL + '&state=' + objForm.state.value;
		strURL = strURL + '&template=' + objForm.template.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box('<strong>Threshold Management</strong>' , '100%', $colors['header'], '3', 'center', 'thold_add.php');
	?>
	<tr bgcolor='#<?php print $colors["panel"];?>' class='noprint'>
		<td class='noprint'>
			<form name='listthold' action='listthold.php' method='post'>
			<table cellpadding='0' cellspacing='0'>
				<tr class='noprint'>
					<td width='1'>
						&nbsp;Host:&nbsp;
					</td>
					<td width='1'>
						<select name='hostid' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='ALL'>Any</option>
							<?php
							foreach ($hostresult as $row) {
								echo "<option value='" . $row['id'] . "'" . (isset($_REQUEST['hostid']) && $row['id'] == $_REQUEST['hostid'] ? ' selected' : '') . '>' . $row['description'] . ' - (' . $row['hostname'] . ')' . '</option>';
							}
							?>
						</select>
					</td>
					<td width='1'>
						&nbsp;Template:&nbsp;
					</td>
					<td width='1'>
						<select name='template' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='ALL'>Any</option>
							<?php
							foreach ($data_templates as $row) {
								echo "<option value='" . $row['id'] . "'" . (isset($_REQUEST['template']) && $row['id'] == $_REQUEST['template'] ? ' selected' : '') . '>' . $row['name'] . '</option>';
							}
							?>
						</select>
					</td>
					<td width='1'>
						&nbsp;State:&nbsp;
					</td>
					<td width='1'>
						<select name='state' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='-1'<?php if ($_REQUEST["state"] == "-1") {?> selected<?php }?>>All</option>
							<option value='1'<?php if ($_REQUEST["state"] == "1") {?> selected<?php }?>>Breached</option>
							<option value='3'<?php if ($_REQUEST["state"] == "3") {?> selected<?php }?>>Triggered</option>
							<option value='2'<?php if ($_REQUEST["state"] == "2") {?> selected<?php }?>>Enabled</option>
							<option value='0'<?php if ($_REQUEST["state"] == "0") {?> selected<?php }?>>Disabled</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type='submit' name='clear' value='Clear' title='Return to Defaults'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='search' value='search'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	define('MAX_DISPLAY_PAGES', 21);

	$total_rows = count(db_fetch_assoc("SELECT thold_data.id
		FROM thold_data
		LEFT JOIN user_auth_perms
		ON ((thold_data.graph_id=user_auth_perms.item_id
		AND user_auth_perms.type=1
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.host_id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.graph_template=user_auth_perms.item_id
		AND user_auth_perms.type=4
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . "))
		$sql_where"));

	$url_page_select = get_page_list($_REQUEST['page'], MAX_DISPLAY_PAGES, $alert_num_rows, $total_rows, 'listthold.php?');

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='listthold.php'>\n";

	html_start_box('', '100%', $colors['header'], '4', 'center', '');

	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='12'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("listthold.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($alert_num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $alert_num_rows) || ($total_rows < ($alert_num_rows*$_REQUEST["page"]))) ? $total_rows : ($alert_num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("listthold.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='12'>
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

	print $nav;

	$display_text = array(
		'name' => array('Name', 'ASC'),
		'thold_type' => array('Type', 'ASC'),
		'thold_hi' => array('High', 'ASC'),
		'thold_low' => array('Low', 'ASC'),
		'nosort3' => array('Trigger', ''),
		'nosort4' => array('Duration', ''),
		'repeat_alert' => array('Repeat', 'ASC'),
		'lastread' => array('Current', 'ASC'),
		'thold_alert' => array('Triggered', 'ASC'),
		'thold_enabled' => array('Enabled', 'ASC'),
		'template_enabled' => array('Templated', 'ASC'));

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$timearray = array(
		1 => '5 Minutes',
		2 => '10 Minutes',
		3 => '15 Minutes',
		4 => '20 Minutes',
		6 => '30 Minutes',
		8 => '45 Minutes',
		12 => 'Hour',
		24 => '2 Hours',
		36 => '3 Hours',
		48 => '4 Hours',
		72 => '6 Hours',
		96 => '8 Hours',
		144 => '12 Hours',
		288 => '1 Day',
		576 => '2 Days',
		2016 => '1 Week',
		4032 => '2 Weeks',
		8640 => '1 Month');

	$c=0;
	$i=0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (count($result)) {
		foreach ($result as $row) {
			$c++;

			$grapharr = db_fetch_row('SELECT DISTINCT graph_templates_item.local_graph_id
						FROM graph_templates_item, data_template_rrd
						WHERE (data_template_rrd.local_data_id=' . $row['rra_id'] . ' AND data_template_rrd.id=graph_templates_item.task_item_id)');
			$graph_id = $grapharr['local_graph_id'];

			$alertstat='no';
			$bgcolor='green';
			if ($row['thold_type'] == 0) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ($row['thold_fail_count'] >= $row['thold_fail_trigger']) {
						$bgcolor = 'red';
					} elseif ($row['thold_warning_fail_count'] >= $row['thold_warning_fail_trigger']) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} elseif ($row['thold_type'] == 2) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ($row['thold_fail_count'] >= $row['time_fail_trigger']) {
						$bgcolor = 'red';
					} elseif ($row['thold_warning_fail_count'] >= $row['time_warning_fail_trigger']) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} else {
				if($row['bl_alert'] == 1) {
					$alertstat='baseline-LOW';
					$bgcolor=($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat='baseline-HIGH';
					$bgcolor=($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				}
			};

			if ($row['thold_enabled'] == 'off') {
				form_alternate_row_color($thold_bgcolors['grey'], $thold_bgcolors['grey'], $i, 'line' . $row["id"]); $i++;
			}else{
				form_alternate_row_color($thold_bgcolors[$bgcolor], $thold_bgcolors[$bgcolor], $i, 'line' . $row["id"]); $i++;
			}
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("thold.php?rra=" . $row['rra_id'] . "&view_rrd=" . $row['data_id']) . "'>" . ($row['name'] != '' ? $row['name'] : $row['name_cache'] . " [" . $row['data_source_name'] . ']') . '</a>', $row['id']);
			form_selectable_cell($types[$row['thold_type']], $row["id"]);
			switch($row['thold_type']) {
				case 0:
					form_selectable_cell(thold_format_number($row['thold_hi']), $row["id"]);
					form_selectable_cell(thold_format_number($row['thold_low']), $row["id"]);
					form_selectable_cell("<i>" . plugin_thold_duration_convert($row['rra_id'], $row['thold_fail_trigger'], 'alert') . "</i>", $row["id"]);
					form_selectable_cell("",  $row["id"]);
					break;
				case 1:
					form_selectable_cell(thold_format_number($row['thold_hi']), $row["id"]);
					form_selectable_cell(thold_format_number($row['thold_low']), $row["id"]);
					form_selectable_cell("<i>" . plugin_thold_duration_convert($row['rra_id'], $row['bl_fail_trigger'], 'alert') . "</i>", $row["id"]);
					form_selectable_cell($timearray[$row['bl_ref_time_range']/300], $row["id"]);
					break;
				case 2:
					form_selectable_cell(thold_format_number($row['time_hi']), $row["id"]);
					form_selectable_cell(thold_format_number($row['time_low']), $row["id"]);
					form_selectable_cell("<i>" . $row['time_fail_trigger'] . " Triggers</i>",  $row["id"]);
					form_selectable_cell(plugin_thold_duration_convert($row['rra_id'], $row['time_fail_length'], 'time'), $row["id"]);
					break;
				default:
					form_selectable_cell("",  $row["id"]);
					form_selectable_cell("",  $row["id"]);
					form_selectable_cell("",  $row["id"]);
					form_selectable_cell("",  $row["id"]);
			}
			form_selectable_cell(($row['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($row['rra_id'], $row['repeat_alert'], 'repeat')), $row["id"]);
			form_selectable_cell(thold_format_number($row['lastread']), $row["id"]);
			form_selectable_cell($alertstat, $row["id"]);
			form_selectable_cell((($row['thold_enabled'] == 'off') ? "Disabled": "Enabled"), $row["id"]);
			if ($row['template'] != 0)
				form_selectable_cell((($row['template_enabled'] == 'off' ) ? "No": "<span title='{$row['template_name']}'>Yes</span>"), $row["id"]);
			else
				form_selectable_cell('', $row["id"]);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row_color($colors['alternate'],$colors['light'],0);
		print '<td colspan=12><center>No Thresholds</center></td></tr>';
	}
	print $nav;

	html_end_box(false);

	thold_legend();

	draw_actions_dropdown($thold_actions);

	if (isset($hostid) && $hostid != '')
		print "<input type=hidden name=hostid value=$hostid>";
	print "</form>\n";

	include_once($config['include_path'] . '/bottom_footer.php');
}
