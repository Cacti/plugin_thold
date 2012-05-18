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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/includes/database.php');

define("MAX_DISPLAY_PAGES", 21);

thold_initialize_rusage();

plugin_thold_upgrade ();

include_once('./plugins/thold/general_header.php');

if (!thold_check_dependencies()) {
	cacti_log("THOLD: You are missing a required dependency, please install and enable the '<a href='http://cactiusers.org/'>Settings'</a> plugin.", true, 'POLLER');
	print "<br><br><center><font color=red>You are missing a dependency for thold, please install the '<a href='http://cactiusers.org'>Settings</a>' plugin.</font></color>";
	exit;
}

if (!plugin_thold_check_strict()) {
	cacti_log("THOLD: You are running MySQL in Strict Mode, which is not supported by Thold.", true, 'POLLER');
	print "<br><br><center><font color=red>You are running MySQL in Strict Mode, which is not supported by Thold.</font></color>";
	exit;
}

/* global colors */
$thold_bgcolors = array(
	'red'    => 'F21924',
	'yellow' => 'FAFD9E',
	'orange' => 'FB4A14',
	'warning' => 'FF7A30',
	'green'  => 'CCFFCC',
	'grey'   => 'CDCFC4');

$host_colors = array(
	HOST_DOWN => "FF6666",
	HOST_ERROR => "ff6044",
	HOST_RECOVERING => "ff7d00",
	HOST_UP => "7EE600",
	HOST_UNKNOWN => "7CB3F1"
);

$disabled_color = "CDCFC4";
$notmon_color  = "FAFD9E";

delete_old_thresholds();

/* present a tabbed interface */
$tabs_thold = array(
	"thold" => "Thresholds",
	"log" => "Log",
	"hoststat" => "Host Status");

/* set the default tab */
if (isset($_REQUEST["tab"])) {
	$_SESSION['sess_thold_tab'] = $_REQUEST["tab"];
}elseif (isset($_SESSION['sess_thold_tab'])) {
	$_REQUEST['tab'] = $_SESSION['sess_thold_tab'];
}
$current_tab = (isset($_REQUEST["tab"]) ? $_REQUEST["tab"]:"thold");

/* draw the tabs */
print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

if (sizeof($tabs_thold)) {
foreach (array_keys($tabs_thold) as $tab_short_name) {
	print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
		"white-space:nowrap;'" .
		" width='1%' " .
		" align='center' class='tab'>
		<span class='textHeader'><a href='" . htmlspecialchars($config['url_path'] .
		"plugins/thold/thold_graph.php?" .
		"tab=" . $tab_short_name) .
		"'>$tabs_thold[$tab_short_name]</a></span>
	</td>\n
	<td width='1'></td>\n";
}
}
print "<td></td>\n</tr></table>\n";

if ($current_tab == 'thold') {
	tholds();
}elseif ($current_tab == 'hoststat') {
	hosts();
}else{
	thold_show_log();
}

include_once("./include/bottom_footer.php");

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION["sess_nav_level_cache"] = '';

function form_thold_filter() {
	global $item_rows, $config, $colors;

	?>
	<tr bgcolor='#<?php print $colors["panel"];?>'>
		<td>
		<form name='form_thold' action='thold_graph.php'>
			<table cellpadding='0' cellspacing='0'>
				<tr>
					<td width='50'>
						&nbsp;Template:&nbsp;
					</td>
					<td width='1'>
						<select name='data_template_id' onChange='applyTHoldFilterChange(document.form_thold)'>
							<option value='-1'<?php if ($_REQUEST["data_template_id"] == "-1") {?> selected<?php }?>>All</option>
							<option value='0'<?php if ($_REQUEST["data_template_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$data_templates = db_fetch_assoc("SELECT DISTINCT data_template.id, data_template.name " .
								"FROM thold_data " .
								"LEFT JOIN data_template ON thold_data.data_template=data_template.id " .
								($_REQUEST["host_id"] > 0 ? "WHERE thold_data.host_id=" . $_REQUEST["host_id"]:"") .
								" ORDER by data_template.name");

							if (sizeof($data_templates)) {
								foreach ($data_templates as $data_template) {
									print "<option value='" . $data_template["id"] . "'"; if ($_REQUEST["data_template_id"] == $data_template["id"]) { print " selected"; } print ">" . $data_template["name"] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='50'>
						&nbsp;Host:&nbsp;
					</td>
					<td width='1'>
						<select name='host_id' onChange='applyTHoldFilterChange(document.form_thold)'>
							<option value='-1'<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>All</option>
							<option value='0'<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$ids = db_fetch_assoc("SELECT DISTINCT host.id, host.description " .
								"FROM host " .
								"INNER JOIN thold_data ON host.id=thold_data.host_id " .
								"LEFT JOIN data_template ON thold_data.data_template=data_template.id " .
								($_REQUEST["data_template_id"] >= 0 ? "WHERE thold_data.data_template=" . $_REQUEST["data_template_id"]:"") .
								" ORDER by data_template.name");

							if (sizeof($ids)) {
								foreach ($ids as $id) {
									print "<option value='" . $id["id"] . "'"; if ($_REQUEST["host_id"] == $id["id"]) { print " selected"; } print ">" . $id["description"] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='50'>
						&nbsp;Status:&nbsp;
					</td>
					<td width='1'>
						<select name='triggered' onChange='applyTHoldFilterChange(document.form_thold)'>
							<option value='-1'<?php if ($_REQUEST["triggered"] == "-1") {?> selected<?php }?>>All</option>
							<option value='1'<?php if ($_REQUEST["triggered"] == "1") {?> selected<?php }?>>Breached</option>
							<option value='3'<?php if ($_REQUEST["triggered"] == "3") {?> selected<?php }?>>Triggered</option>
							<option value='2'<?php if ($_REQUEST["triggered"] == "2") {?> selected<?php }?>>Enabled</option>
							<option value='0'<?php if ($_REQUEST["triggered"] == "0") {?> selected<?php }?>>Disabled</option>
						</select>
					</td>
					<td width='1'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='applyTHoldFilterChange(document.form_thold)'>
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
					<td width='20'>
						&nbsp;Search:&nbsp;
					</td>
					<td width='144'>
						<input type='text' name='filter' size='20' value='<?php print $_REQUEST["filter"];?>'>
					</td>
					<td width='1'>
						<input type="submit" value="Go">
					</td>
					<td width='1'>
						<input id="clear" name="clear" type="submit" value="Clear">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='tab' value='thold'>
		</form>
		</td>
	</tr>
	<?php
}

function tholds() {
	global $config, $colors, $thold_bgcolors, $device_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("data_template_id"));
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("triggered"));
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
		kill_session_var("sess_thold_current_page");
		kill_session_var("sess_thold_filter");
		kill_session_var("sess_thold_data_template_id");
		kill_session_var("sess_thold_host_id");
		kill_session_var("sess_thold_rows");
		kill_session_var("sess_thold_triggered");
		kill_session_var("sess_thold_sort_column");
		kill_session_var("sess_thold_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["data_template_id"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["triggered"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	} else {
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('filter', 'sess_thold_filter');
		$changed += thold_request_check_changed('data_template_id', 'sess_thold_data_template_id');
		$changed += thold_request_check_changed('host_id', 'sess_thold_host_id');
		$changed += thold_request_check_changed('rows', 'sess_thold_rows');
		$changed += thold_request_check_changed('triggered', 'sess_thold_triggered');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_thold_current_page", "1");
	load_current_session_value("filter", "sess_thold_filter", "");
	load_current_session_value("triggered", "sess_thold_triggered", read_config_option("thold_filter_default"));
	load_current_session_value("data_template_id", "sess_thold_data_template_id", "-1");
	load_current_session_value("host_id", "sess_thold_host_id", "-1");
	load_current_session_value("rows", "sess_thold_rows", read_config_option("alert_num_rows"));
	load_current_session_value("sort_column", "sess_thold_sort_column", "thold_alert");
	load_current_session_value("sort_direction", "sess_thold_sort_direction", "DESC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1 || !isset($_REQUEST["rows"])) {
		$_REQUEST["rows"] = read_config_option("alert_num_rows");
		if ($_REQUEST["rows"] < 2) $_REQUEST["rows"] = 30;
	}

	?>
	<script type="text/javascript">
	<!--

	function applyTHoldFilterChange(objForm) {
		strURL = '?tab=thold&triggered=' + objForm.triggered.value;
		strURL = strURL + '&data_template_id=' + objForm.data_template_id.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Threshold Status</strong>", "100%", $colors["header"], "3", "center", "");
	form_thold_filter();
	html_end_box();

	/* build the SQL query and WHERE clause */
	if ($_REQUEST['sort_column'] == 'lastread') {
		$sort = $_REQUEST['sort_column'] . "/1";
	}else{
		$sort = $_REQUEST['sort_column'];
	}
	$limit = ' LIMIT ' . ($_REQUEST["rows"]*($_REQUEST['page']-1)) . "," . $_REQUEST["rows"];
	$sql_where = '';

	/* triggered filter */
	if ($_REQUEST['triggered'] == '-1') {
		/* return all rows */
	} else {
		if($_REQUEST['triggered'] == '0') { $sql_where = "WHERE thold_data.thold_enabled='off'"; } /*disabled*/
		if($_REQUEST['triggered'] == '2') { $sql_where = "WHERE thold_data.thold_enabled='on'"; } /* enabled */
		if($_REQUEST['triggered'] == '1') { $sql_where = "WHERE (thold_data.thold_alert!=0 OR thold_data.bl_alert>0)"; } /* breached */
		if($_REQUEST['triggered'] == '3') { $sql_where = "WHERE ((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger))"; } /* triggered */
	}

	if (strlen($_REQUEST["filter"])) {
		$sql_where .= (strlen($sql_where) ? " AND": "WHERE") . " thold_data.name LIKE '%%" . $_REQUEST["filter"] . "%%'";
	}

	/* data template id filter */
	if ($_REQUEST['data_template_id'] != '-1') {
		$sql_where .= (strlen($sql_where) ? " AND": "WHERE") . " thold_data.data_template=" . $_REQUEST['data_template_id'];
	}

	/* host id filter */
	if ($_REQUEST['host_id'] != '-1') {
		$sql_where .= (strlen($sql_where) ? " AND": "WHERE") . " thold_data.host_id=" . $_REQUEST['host_id'];
	}

	/* thold permissions */
	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$total_rows_sql = "SELECT * FROM thold_data
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
		$sql_where";

	$total_rows = sizeof(db_fetch_assoc($total_rows_sql));

	/* get the thold records */
	$sql = "SELECT * FROM thold_data
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
		$sql_where
		ORDER BY $sort " . $_REQUEST['sort_direction'] .
		$limit;

	$result = db_fetch_assoc($sql);

	html_start_box('', '100%', $colors['header'], '4', 'center', '');

	if ($total_rows) {
		/* generate page list */
		$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "thold_graph.php?tab=thold");

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='13'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?filter=" . $_REQUEST["filter"] . "&page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?filter=" . $_REQUEST["filter"] . "&page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='13'>
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
		'nosort' => array('Actions', ''),
		'name' => array('Name', 'ASC'),
		'id' => array('ID', 'ASC'),
		'thold_type' => array('Type', 'ASC'),
		'nosort2' => array('Trigger', 'ASC'),
		'nosort3' => array('Duration', 'ASC'),
		'repeat_alert' => array('Repeat', 'ASC'),
		'nosort4' => array('Warn Hi/Lo', 'ASC'),
		'nosort5' => array('Alert Hi/Lo', 'ASC'),
		'nosort6' => array('BL Hi/Lo', 'ASC'),
		'lastread' => array('Current', 'ASC'),
		'thold_alert' => array('Triggered', 'ASC'),
		'thold_enabled' => array('Enabled', 'ASC'));

	html_header_sort($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction']);

	$timearray   = array(1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => 'Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');

	$c=0;
	$i=0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (count($result)) {
		foreach ($result as $row) {
			$c++;
			$alertstat = 'no';
			$bgcolor   = 'green';
			if ($row['thold_type'] == 0) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ( $row['thold_fail_count'] >= $row['thold_fail_trigger'] ) {
						$bgcolor = 'red';
					} elseif ( $row['thold_warning_fail_count'] >= $row['thold_warning_fail_trigger'] ) {
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

			print "<td width='1%' style='white-space:nowrap;' nowrap>";
			if (api_user_realm_auth('thold_add.php')) {
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?rra=' . $row["rra_id"] . '&view_rrd=' . $row["data_id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Threshold"></a>';
			}
			if ($row["thold_enabled"] == 'on') {
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?id=' . $row["id"] .'&action=disable') . '"><img src="' . $config['url_path'] . 'plugins/thold/images/disable_thold.png" border="0" alt="" title="Disable Threshold"></a>';
			}else{
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?id=' . $row["id"] . '&action=enable') . '"><img src="' . $config['url_path'] . 'plugins/thold/images/enable_thold.png" border="0" alt="" title="Enable Threshold"></a>';
			}
			print "<a href='". htmlspecialchars($config['url_path'] . "graph.php?local_graph_id=" . $row['graph_id'] . "&rra_id=all") . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graph'></a>";
			print "<a href='". htmlspecialchars($config['url_path'] . "plugins/thold/thold_graph.php?tab=log&threshold_id=" . $row["id"] . "&status=-1") . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_log.gif' border='0' alt='' title='View Threshold History'></a>";

			print "</td>";
			print "<td width='28%'>" . ($row['name'] != '' ? $row['name'] : 'No name set') . "</td>";
			print "<td width='10'>" . $row["id"] . "</td>";
			print "<td nowrap style='white-space:nowrap;' width='120'>" . $types[$row['thold_type']] . "</td>";
			switch($row['thold_type']) {
				case 0:
					print "<td nowrap style='white-space:nowrap;' width='80'><i>" . plugin_thold_duration_convert($row['rra_id'], $row['thold_fail_trigger'], 'alert') . "</i></td>";
					print "<td width='80'>N/A</td>";
					break;
				case 1:
					print "<td nowrap style='white-space:nowrap;' width='80'><i>" . plugin_thold_duration_convert($row['rra_id'], $row['bl_fail_trigger'], 'alert') . "</i></td>";
					print "<td nowrap style='white-space:nowrap;' width='80'>" . $timearray[$row['bl_ref_time_range']/300]. "</td>";;
					break;
				case 2:
					print "<td nowrap style='white-space:nowrap;' width='80'><i>" . $row['time_fail_trigger'] . " Triggers</i></td>";
					print "<td nowrap style='white-space:nowrap;' width='80'>" . plugin_thold_duration_convert($row['rra_id'], $row['time_fail_length'], 'time') . "</td>";;
					break;
				default:
					print "<td width='80'>N/A</td>";
					print "<td width='80'>N/A</td>";
			}
			print "<td nowrap style='white-space:nowrap;' width='80'>" . ($row['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($row['rra_id'], $row['repeat_alert'], 'repeat')) . "</td>";
			print "<td nowrap style='white-space:nowrap;' width='80'>" . ($row['thold_type'] == 1 ? "N/A":($row['thold_type'] == 2 ? thold_format_number($row['time_warning_hi']) . '/' . thold_format_number($row['time_warning_low']) : thold_format_number($row['thold_warning_hi']) . '/' . thold_format_number($row['thold_warning_low']))) . "</td>";
			print "<td width='80'>" . ($row['thold_type'] == 1 ? "N/A":($row['thold_type'] == 2 ? thold_format_number($row['time_hi']) . '/' . thold_format_number($row['time_low']) : thold_format_number($row['thold_hi']) . '/' . thold_format_number($row['thold_low']))) . "</td>";
			print "<td width='80'>" . ($row['thold_type'] == 1 ? $row['bl_pct_up'] . (strlen($row['bl_pct_up']) ? '%':'-') . '/' . $row['bl_pct_down'] . (strlen($row['bl_pct_down']) ? '%':'-'): 'N/A') . "</td>";
			print "<td width='80'>" . thold_format_number($row['lastread']) . "</td>";
			print "<td width='80'>" . ($row['thold_alert'] ? "yes":"no") . "</td>";
			if ($row['thold_enabled'] == 'off') {
				print "<td width='40'><b>Disabled</b></td>";
			}else{
				print "<td width='40'>Enabled</td>";
			}
			form_end_row();
		}
	} else {
		form_alternate_row_color($colors['alternate'],$colors['light'],0);
		print '<td colspan=12><center>No Thresholds</center></td></tr>';
	}
	print $nav;
	html_end_box(false);

	thold_legend();

	//thold_display_rusage();
}


/* form_host_status_row_color - returns a color to use based upon the host's current status*/
function form_host_status_row_color ($status, $disabled) {
	global $host_colors, $disabled_color;

	// Determine the color to use
	if ($disabled) {
		$color=$disabled_color;
	} else {
		$color=$host_colors["$status"];
	}

	print "<tr bgcolor='#$color'>\n";

	return $color;
}

function get_uncolored_device_status($disabled, $status) {
	if ($disabled) {
		return "Disabled";
	}else{
		switch ($status) {
			case HOST_DOWN:
				return "Down";
				break;
			case HOST_RECOVERING:
				return "Recovering";
				break;
			case HOST_UP:
				return "Up";
				break;
			case HOST_ERROR:
				return "Error";
				break;
			default:
				return "Unknown";
				break;
		}
	}
}

function hosts() {
	global $config, $colors, $device_actions, $item_rows, $host_colors, $notmon_color;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("host_status"));
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
		kill_session_var("sess_thold_host_current_page");
		kill_session_var("sess_thold_host_filter");
		kill_session_var("sess_thold_host_host_template_id");
		kill_session_var("sess_thold_host_status");
		kill_session_var("sess_thold_host_rows");
		kill_session_var("sess_thold_host_sort_column");
		kill_session_var("sess_thold_host_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["host_status"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	} else {
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('filter', 'sess_thold_host_filter');
		$changed += thold_request_check_changed('host_template_id', 'sess_thold_host_template_id');
		$changed += thold_request_check_changed('host_status', 'sess_thold_host_status');
		$changed += thold_request_check_changed('rows', 'sess_thold_host_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_log_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_log_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	if ((!empty($_SESSION["sess_thold_host_status"])) && (!empty($_REQUEST["host_status"]))) {
		if ($_SESSION["sess_thold_host_status"] != $_REQUEST["host_status"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_thold_host_current_page", "1");
	load_current_session_value("filter", "sess_thold_host_filter", "");
	load_current_session_value("host_template_id", "sess_thold_host_host_template_id", "-1");
	load_current_session_value("host_status", "sess_thold_host_status", "-4");
	load_current_session_value("rows", "sess_thold_host_rows", read_config_option("num_rows_device"));
	load_current_session_value("sort_column", "sess_thold_host_sort_column", "description");
	load_current_session_value("sort_direction", "sess_thold_host_sort_direction", "ASC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '?tab=hoststat&host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Device Status</strong>", "100%", $colors["header"], "3", "center", "");
	form_host_filter();
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = "where (host.hostname LIKE '%%" . $_REQUEST["filter"] . "%%' OR host.description LIKE '%%" . $_REQUEST["filter"] . "%%')";

	if ($_REQUEST["host_status"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["host_status"] == "-2") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled='on'" : "where host.disabled='on'");
	}elseif ($_REQUEST["host_status"] == "-3") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled=''" : "where host.disabled=''");
	}elseif ($_REQUEST["host_status"] == "-4") {
		$sql_where .= (strlen($sql_where) ? " and (host.status!='3' or host.disabled='on')" : "where (host.status!='3' or host.disabled='on')");
	}elseif ($_REQUEST["host_status"] == "-5") {
		$sql_where .= (strlen($sql_where) ? " and (host.availability_method=0)" : "where (host.availability_method=0)");
	}elseif ($_REQUEST["host_status"] == "3") {
		$sql_where .= (strlen($sql_where) ? " and (host.availability_method!=0 and host.status=3 and host.disabled='')" : "where (host.availability_method=0 and host.status=3 and host.disabled='')");
	}else {
		$sql_where .= (strlen($sql_where) ? " and (host.status=" . $_REQUEST["host_status"] . " AND host.disabled = '')" : "where (host.status=" . $_REQUEST["host_status"] . " AND host.disabled = '')");
	}

	if ($_REQUEST["host_template_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["host_template_id"] == "0") {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=0" : "where host.host_template_id=0");
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=" . $_REQUEST["host_template_id"] : "where host.host_template_id=" . $_REQUEST["host_template_id"]);
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");


	$sortby = $_REQUEST["sort_column"];
	if ($sortby=="hostname") {
		$sortby = "INET_ATON(hostname)";
	}

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where    .= ' AND ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$total_rows = db_fetch_cell("SELECT
		COUNT(host.id)
		FROM host
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		$sql_where");

	$sql_query = "SELECT *
		FROM host
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		$sql_where
		ORDER BY " . $sortby . " " . $_REQUEST["sort_direction"] . "
		LIMIT " . ($_REQUEST["rows"]*($_REQUEST["page"]-1)) . "," . $_REQUEST["rows"];

	//print $sql_query;

	$hosts = db_fetch_assoc($sql_query);

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "thold_graph.php?tab=hoststat");

	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?filter=" . $_REQUEST["filter"] . "&host_template_id=" . $_REQUEST["host_template_id"] . "&host_status=" . $_REQUEST["host_status"] . "&page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?filter=" . $_REQUEST["filter"] . "&host_template_id=" . $_REQUEST["host_template_id"] . "&host_status=" . $_REQUEST["host_status"] . "&page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
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
		"nosort" => array("<br>Actions", ""),
		"description" => array("<br>Description", "ASC"),
		"id" => array("<br>ID", "ASC"),
		"nosort1" => array("<br>Graphs", "ASC"),
		"nosort2" => array("Data<br>Sources", "ASC"),
		"status" => array("<br>Status", "ASC"),
		"status_event_count" => array("Event<br>Count", "ASC"),
		"hostname" => array("<br>Hostname", "ASC"),
		"cur_time" => array("<br>Current (ms)", "DESC"),
		"avg_time" => array("<br>Average (ms)", "DESC"),
		"availability" => array("<br>Availability", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if (isset($host_graphs[$host["id"]])) {
				$graphs = $host_graphs[$host["id"]];
			}else{
				$graphs = 0;
			}

			if (isset($host_data_sources[$host["id"]])) {
				$ds = $host_data_sources[$host["id"]];
			}else{
				$ds = 0;
			}

			if ($host["availability_method"] != 0) {
				form_host_status_row_color($host["status"], $host["disabled"]); $i++;
				print "<td width='1%' style='white-space:nowrap'>";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host["id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Host"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . "graph_view.php?action=preview&graph_template_id=0&filter=&host_id=" . $host["id"]) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graphs'></a>";
				print "</td>";
				?>
				<td>
					<?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $host["description"]) : $host["description"]);?>
				</td>
				<td width='80'><?php print round(($host["id"]), 2);?></td>
				<td width='80'><i><?php print $graphs;?></i></td>
				<td width='80'><i><?php print $ds;?></i></td>
				<td width='140'><?php print get_uncolored_device_status(($host["disabled"] == "on" ? true : false), $host["status"]);?></td>
				<td width='100'><?php print round(($host["status_event_count"]), 2);?></td>
				<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $host["hostname"]) : $host["hostname"]);?></td>
				<td width='100'><?php print round(($host["cur_time"]), 2);?></td>
				<td width='100'><?php print round(($host["avg_time"]), 2);?></td>
				<td width='100'><?php print round($host["availability"], 2);?></td>
				<?php
			}else{
				form_alternate_row_color($notmon_color,$notmon_color,$i); $i++;
				print "<td width='1%' style='white-space:nowrap'>";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host["id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Host"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . "graph_view.php?action=preview&graph_template_id=0&filter=&host_id=" . $host["id"]) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graphs'></a>";
				print "</td>";
				?>
				<td>
					<?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $host["description"]) : $host["description"]);?>
				</td>
				<td width='80'><?php print $host["id"];?></td>
				<td width='80'><i><?php print $graphs;?></i></td>
				<td width='80'><i><?php print $ds;?></i></td>
				<td width='140'><?php print "Not Monitored";?></td>
				<td width='100'><?php print "N/A";?></td>
				<td><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $host["hostname"]) : $host["hostname"]);?></td>
				<td width='100'><?php print "N/A";?></td>
				<td width='100'><?php print "N/A";?></td>
				<td width='100'><?php print "N/A";?></td>
				<?php
			}

			form_end_row();
		}
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}

	/* put the nav bar on the bottom as well */
	print $nav;

	html_end_box(false);

	host_legend();

	//thold_display_rusage();
}

function form_host_filter() {
	global $item_rows, $config, $colors;

	?>
	<tr bgcolor='#<?php print $colors["panel"];?>'>
		<td>
		<form name='form_devices' action='thold_graph.php?tab=hoststat'>
			<table cellpadding='0' cellspacing='0'>
				<tr>
					<td width='1'>
						&nbsp;Type:&nbsp;
					</td>
					<td width='1'>
						<select name='host_template_id' onChange='applyViewDeviceFilterChange(document.form_devices)'>
							<option value='-1'<?php if ($_REQUEST["host_template_id"] == "-1") {?> selected<?php }?>>All</option>
							<option value='0'<?php if ($_REQUEST["host_template_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates)) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template["id"] . "'"; if ($_REQUEST["host_template_id"] == $host_template["id"]) { print " selected"; } print ">" . $host_template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width='1'>
						&nbsp;Status:&nbsp;
					</td>
					<td width='1'>
						<select name='host_status' onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value='-1'<?php if ($_REQUEST["host_status"] == "-1") {?> selected<?php }?>>All</option>
							<option value='-3'<?php if ($_REQUEST["host_status"] == "-3") {?> selected<?php }?>>Enabled</option>
							<option value='-2'<?php if ($_REQUEST["host_status"] == "-2") {?> selected<?php }?>>Disabled</option>
							<option value='-4'<?php if ($_REQUEST["host_status"] == "-4") {?> selected<?php }?>>Not Up</option>
							<option value='-5'<?php if ($_REQUEST["host_status"] == "-5") {?> selected<?php }?>>Not Monitored</option>
							<option value='3'<?php if ($_REQUEST["host_status"] == "3") {?> selected<?php }?>>Up</option>
							<option value='1'<?php if ($_REQUEST["host_status"] == "1") {?> selected<?php }?>>Down</option>
							<option value='2'<?php if ($_REQUEST["host_status"] == "2") {?> selected<?php }?>>Recovering</option>
							<option value='0'<?php if ($_REQUEST["host_status"] == "0") {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td width='1'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='applyViewDeviceFilterChange(document.form_devices)'>
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
						&nbsp;Search:&nbsp;
					</td>
					<td width='144'>
						<input type='text' name='filter' size='20' value='<?php print $_REQUEST["filter"];?>'>
					</td>
					<td>
						<input type="submit" value="Go">
					</td>
					<td>
						<input id="clear" name="clear" type="submit" value="Clear">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='tab' value='hoststat'>
		</form>
		</td>
	</tr>
	<?php
}

function thold_show_log() {
	global $config, $colors, $item_rows;

	$thold_log = array(
		'Alarm'     => 'F21924',
		'Warning'   => 'FB4A14',
		'Re-Trigger' => 'FF7A30',
		'Alert Trigger'   => 'FAFD9E',
		'Warn Trigger'  => 'FAFD9E',
		'Restoral'  => 'CCFFCC',
		'Restore'   => 'CDCFC4',
		'Alarm2Warn' => 'FACD6E');

	$thold_status = array(
		'0' => 'Restore',
		'1' => 'Alert Trigger',
		'2' => 'Re-Trigger',
		'3' => 'Warning',
		'4' => 'Alarm',
		'5' => 'Restoral',
		'6' => 'Warn Trigger',
		'7' => 'Alarm-Warn');

	$types = array('High/Low', 'Baseline Deviation', 'Time Based');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("threshold_id"));
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("status"));
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
		kill_session_var("sess_thold_log_current_page");
		kill_session_var("sess_thold_log_filter");
		kill_session_var("sess_thold_log_threshold_id");
		kill_session_var("sess_thold_log_host_id");
		kill_session_var("sess_thold_log_status");
		kill_session_var("sess_thold_log_rows");
		kill_session_var("sess_thold_log_sort_column");
		kill_session_var("sess_thold_log_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["threshold_id"]);
		unset($_REQUEST["status"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	} else {
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('filter', 'sess_thold_log_filter');
		$changed += thold_request_check_changed('threshold_id', 'sess_thold_log_threshold_id');
		$changed += thold_request_check_changed('host_id', 'sess_thold_log_host_id');
		$changed += thold_request_check_changed('status', 'sess_thold_log_status');
		$changed += thold_request_check_changed('rows', 'sess_thold_log_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_log_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_log_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_thold_log_current_page", "1");
	load_current_session_value("filter", "sess_thold_log_filter", "");
	load_current_session_value("threshold_id", "sess_thold_log_threshold_id", "-1");
	load_current_session_value("host_id", "sess_thold_log_host_id", "-1");
	load_current_session_value("status", "sess_thold_log_status", "-1");
	load_current_session_value("rows", "sess_thold_log_rows", read_config_option("num_rows_device"));
	load_current_session_value("sort_column", "sess_thold_log_sort_column", "time");
	load_current_session_value("sort_direction", "sess_thold_log_sort_direction", "DESC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--
	function filterChange(objForm) {
		strURL = '?tab=log&status=' + objForm.status.value;
		strURL = strURL + '&threshold_id=' + objForm.threshold_id.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box("<strong>Threshold Log</strong> [last 30 days]", "100%", $colors["header"], "3", "center", "");
	form_thold_log_filter();
	html_end_box();

	$sql_where = '';

	if ($_REQUEST["host_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["host_id"] == "0") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " host.id IS NULL";
	}elseif (!empty($_REQUEST["host_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " plugin_thold_log.host_id=" . $_REQUEST["host_id"];
	}

	if ($_REQUEST["threshold_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["threshold_id"] == "0") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " thold_data.id IS NULL";
	}elseif (!empty($_REQUEST["threshold_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " plugin_thold_log.threshold_id=" . $_REQUEST["threshold_id"];
	}

	if ($_REQUEST["status"] == "-1") {
		/* Show all items */
	}else{
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " plugin_thold_log.status=" . $_REQUEST["status"];
	}

	if (strlen($_REQUEST["filter"])) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " plugin_thold_log.description LIKE '%" . $_REQUEST["filter"] . "%'";
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sortby = $_REQUEST["sort_column"];

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	$sql_where .= ' AND ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM plugin_thold_log
		LEFT JOIN host ON plugin_thold_log.host_id=host.id
		LEFT JOIN thold_data ON plugin_thold_log.threshold_id=thold_data.id
		LEFT JOIN graph_templates_graph AS gtg ON plugin_thold_log.graph_id=gtg.local_graph_id
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		$sql_where");

	$sql_query = "SELECT plugin_thold_log.*, host.description AS hdescription, thold_data.name AS name, gtg.title_cache
		FROM plugin_thold_log
		LEFT JOIN host ON plugin_thold_log.host_id=host.id
		LEFT JOIN thold_data ON plugin_thold_log.threshold_id=thold_data.id
		LEFT JOIN graph_templates_graph AS gtg ON plugin_thold_log.graph_id=gtg.local_graph_id
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		$sql_where
		ORDER BY " . $sortby . " " . $_REQUEST["sort_direction"] . "
		LIMIT " . ($_REQUEST["rows"]*($_REQUEST["page"]-1)) . "," . $_REQUEST["rows"];

	//print $sql_query;

	$logs = db_fetch_assoc($sql_query);

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "thold_graph.php?tab=log");

	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?tab=log&page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("thold_graph.php?tab=log&page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
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
		"hdescription" => array("<br>Host", "ASC"),
		"name" => array("<br>Threshold", "ASC"),
		"time" => array("<br>Time", "ASC"),
		"threshold_value" => array("Alarm<br>Value", "ASC"),
		"current" => array("Current<br>Value", "ASC"),
		"status" => array("<br>Status", "DESC"),
		"type" => array("<br>Type", "DESC"),
		"description" => array("<br>Event Description", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($logs)) {
		foreach ($logs as $l) {
			?>
			<tr style='background-color:#<?php print $thold_log[$thold_status[$l['status']]];?>'>
			<td style='white-space:nowrap;'><?php print $l["hdescription"];?></td>
			<td style='white-space:nowrap;'><?php print $l["name"];?></td>
			<td style='white-space:nowrap;'><?php print date("Y-m-d H:i:s", $l["time"]);?></td>
			<td><?php print ($l["threshold_value"] != '' ? thold_format_number($l["threshold_value"]):'N/A');?></td>
			<td><?php print ($l["current"] != '' ? thold_format_number($l["current"]):'N/A');?></td>
			<td nowrap style='white-space:nowrap;'><?php print $thold_status[$l["status"]];?></td>
			<td nowrap style='white-space:nowrap;'><?php print $types[$l["type"]];?></td>
			<td style='white-space:nowrap;'><?php print (strlen($l["description"]) ? $l["description"]:"Restoral Event");?></td>
			<?php

			form_end_row();
		}
	}else{
		print "<tr><td><em>No Threshold Logs Found</em></td></tr>";
	}

	/* put the nav bar on the bottom as well */
	print $nav;

	html_end_box(false);

	log_legend();

	//thold_display_rusage();
}

function form_thold_log_filter() {
	global $item_rows, $config, $colors;

	?>
	<tr bgcolor='#<?php print $colors["panel"];?>'>
		<td>
		<form name='form_thold_log' action='thold_graph.php?tab=log'>
			<table cellpadding='0' cellspacing='0'>
				<tr>
					<td width='50'>
						&nbsp;Threshold:&nbsp;
					</td>
					<td width='1'>
						<select name='threshold_id' onChange='filterChange(document.form_thold_log)'>
							<option value='-1'<?php if ($_REQUEST["threshold_id"] == "-1") {?> selected<?php }?>>All</option>
							<option value='0'<?php if ($_REQUEST["threshold_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$tholds = db_fetch_assoc("SELECT DISTINCT thold_data.id, thold_data.name " .
								"FROM thold_data " .
								"INNER JOIN plugin_thold_log ON thold_data.id=plugin_thold_log.threshold_id " .
								($_REQUEST["host_id"] > 0 ? "WHERE thold_data.host_id=" . $_REQUEST["host_id"]:"") .
								" ORDER by thold_data.name");

							if (sizeof($tholds)) {
								foreach ($tholds as $thold) {
									print "<option value='" . $thold["id"] . "'"; if ($_REQUEST["threshold_id"] == $thold["id"]) { print " selected"; } print ">" . $thold["name"] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='50'>
						&nbsp;Host:&nbsp;
					</td>
					<td width='1'>
						<select name='host_id' onChange='filterChange(document.form_thold_log)'>
							<option value='-1'<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>All</option>
							<option value='0'<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$ids = db_fetch_assoc("SELECT DISTINCT host.id, host.description " .
								"FROM host " .
								"INNER JOIN plugin_thold_log ON host.id=plugin_thold_log.host_id " .
								($_REQUEST["threshold_id"] >= 0 ? "WHERE plugin_thold_log.threshold_id=" . $_REQUEST["threshold_id"]:"") .
								" ORDER by host.description");

							if (sizeof($ids)) {
								foreach ($ids as $id) {
									print "<option value='" . $id["id"] . "'"; if ($_REQUEST["host_id"] == $id["id"]) { print " selected"; } print ">" . $id["description"] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='50'>
						&nbsp;Status:&nbsp;
					</td>
					<td width='1'>
						<select name='status' onChange='filterChange(document.form_thold_log)'>
							<option value='-1'<?php if ($_REQUEST["status"] == "-1") {?> selected<?php }?>>All</option>
							<option value='4'<?php if ($_REQUEST["status"] == "4") {?> selected<?php }?>>Notify - Alarm</option>
							<option value='7'<?php if ($_REQUEST["status"] == "7") {?> selected<?php }?>>Notify - Alarm2Warning</option>
							<option value='3'<?php if ($_REQUEST["status"] == "3") {?> selected<?php }?>>Notify - Warning</option>
							<option value='2'<?php if ($_REQUEST["status"] == "2") {?> selected<?php }?>>Notify - ReTriggers</option>
							<option value='5'<?php if ($_REQUEST["status"] == "5") {?> selected<?php }?>>Notify - Restoral</option>
							<option value='1'<?php if ($_REQUEST["status"] == "1") {?> selected<?php }?>>Triggers - Alert</option>
							<option value='6'<?php if ($_REQUEST["status"] == "1") {?> selected<?php }?>>Triggers - Warning</option>
							<option value='0'<?php if ($_REQUEST["status"] == "0") {?> selected<?php }?>>Restorals</option>
						</select>
					</td>
					<td width='1'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='filterChange(document.form_thold_log)'>
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
					<td width='20'>
						&nbsp;Search:&nbsp;
					</td>
					<td width='144'>
						<input type='text' name='filter' size='20' value='<?php print $_REQUEST["filter"];?>'>
					</td>
					<td width='1'>
						<input type="submit" value="Go">
					</td>
					<td width='1'>
						<input id="clear" name="clear" type="submit" value="Clear">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='tab' value='log'>
		</form>
		</td>
	</tr>
	<?php
}
