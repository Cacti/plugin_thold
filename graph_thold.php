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

$guest_account = true;

chdir('../../');
include_once("./include/auth.php");
include_once($config["base_path"] . "/plugins/thold/include/top_thold_header.php");
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

if (!thold_check_dependencies()) {
	cacti_log("THOLD: You are missing a required dependency, please install the '<a href='http://cactiusers.org/'>Settings'</a> plugin.", true, "POLLER");
	print "<br><br><center><font color=red>You are missing a dependency for thold, please install the '<a href='http://cactiusers.org'>Settings</a>' plugin.</font></color>";
	exit;
}

delete_old_thresholds();

if (isset($_REQUEST['show'])) {
	$_REQUEST['page'] = 1;
}

if (basename($_SERVER['PHP_SELF']) == 'graph_thold.php') {
	$_REQUEST['show'] = '';
}

print '<center> Last Poll: ';

$thold_last_poll = read_config_option("thold_last_poll", true);

if ($thold_last_poll > 0 && $thold_last_poll != '') {
	echo $thold_last_poll;
} else {
	echo "Poller has not yet ran!";
}

print '</center><br>';
thold_view_thresholds();

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION["sess_nav_level_cache"] = '';

function thold_view_thresholds() {
	global $title, $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["page"])) {
		$_REQUEST["i_page"] = $_REQUEST["page"];
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("i_page", "sess_thold_view_thresholds_current_page", "1");
	load_current_session_value("page", "sess_thold_view_thresholds_current_page", "1");
	load_current_session_value("sort_column", "sess_thold_view_thresholds_sort_column", "thold_alert");
	load_current_session_value("sort_direction", "sess_thold_view_thresholds_sort_direction", "DESC");

	if (isset($_REQUEST['show'])) {
		$show = $_REQUEST['show'];
	} else {
		$show = '';
	}

	$_REQUEST["i_page"] = $_REQUEST["page"];

	html_start_box("", "98%", $colors["header"], "3", "center", "");

	$sql_where = '';
	switch ($show) {
		case 'thold-failures':
			$sql_where = 'AND thold_alert > 0 ';
			break;
		case 'thold-normal':
			$sql_where = 'AND thold_alert = 0 ';
			break;
		case 'thold-recover':
			$sql_where = 'AND thold_alert > 0 AND thold_fail_trigger > thold_fail_count ';
			break;
	}
	$sort = 'ORDER BY ' . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];
	$perpage = read_config_option("alert_num_rows");
	$limit = " LIMIT " . ($perpage*($_REQUEST["i_page"]-1)) . "," . $perpage;

	$result = db_fetch_assoc("SELECT DISTINCT graph_templates_item.local_graph_id, thold_data.*, host.description, host.status, data_template_data.name_cache, data_template_rrd.data_source_name
					 FROM thold_data, host, data_template_data, data_template_rrd, graph_templates_item
					 WHERE thold_data.host_id=host.id AND data_template_data.local_data_id=thold_data.rra_id AND data_template_rrd.id=thold_data.data_id AND data_template_rrd.id=graph_templates_item.task_item_id
					 $sql_where $sort $limit");
	$total_rows = db_fetch_cell("SELECT count(*)
					 FROM thold_data
					 WHERE 1=1
					 $sql_where");

	/* generate page list */

	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["i_page"], 10, $perpage, $total_rows, "graph_thold.php"));
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='8'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["i_page"] > 1) { $nav .= "<a class='linkOverDark' href='graph_thold.php?page=" . ($_REQUEST["i_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["i_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($perpage*($_REQUEST["i_page"]-1))+1) . " to " . ((($total_rows < $perpage) || ($total_rows < ($perpage*$_REQUEST["i_page"]))) ? $total_rows : ($perpage*$_REQUEST["i_page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["i_page"] * $perpage) < $total_rows) { $nav .= "<a class='linkOverDark' href='graph_thold.php?page=" . ($_REQUEST["i_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["i_page"] * $perpage) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"id" => array("<br>ID", "ASC"),
		"description" => array("Hostname", "ASC"),
		"name_cache" => array("Description", "ASC"),
		"thold_hi" => array("High Threshold", "DESC"),
		"thold_low" => array("Low Threshold", "DESC"),
		"bl_enabled" => array("Baselining", "DESC"),
		"lastread" => array("Current", "DESC"),
		"thold_alert" => array("Currently Triggered", "DESC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
	$i = 0;
	if (sizeof($result) > 0) {
		foreach ($result as $row) {
			if ($row["thold_alert"] != 0) {
				$alertstat="Yes";
				$bgcolor=($row["thold_fail_count"] >= $row["thold_fail_trigger"] ? "red" : "yellow");
			} else {
				$alertstat="No";
				$bgcolor="";
		
				if ($row["bl_enabled"] == "on") {
					if ($row["bl_alert"] == 1) {
						$alertstat = "baseline-LOW";
						$bgcolor = ($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
					} else if ($row["bl_alert"] == 2) {
						$alertstat = "baseline-HIGH";
						$bgcolor = ($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
					}
				}
			}
			if ($row['status'] == 3) {
				$hostcolor = '';
			} elseif ($row['status'] == 2) {
				$hostcolor = 'yellow';
			} else {
				$hostcolor = 'red';
			}
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=200>
					<?php print "<p class='linkEditMain'><a href='thold.php?rra=" . $row['rra_id'] . '&view_rrd=' . $row['data_id'] . "'>" . $row["id"] . "</a></p>";?>
				</td>
				<td<?php echo ($hostcolor ? (" bgcolor='" . $hostcolor . "'") : ''); ?>><?php print "<a href='" . $config['url_path'] . "host.php?action=edit&id=" . $row['host_id'] . "'>" . $row['description']; ?></a></td>
				<td><a href="<?php echo $config['url_path']; ?>graph.php?local_graph_id=<?php echo $row['local_graph_id']; ?>&rra_id=all"><?php print $row['name_cache'] . ' [' . $row['data_source_name']; ?>]</a></td>
				<td<?php echo ($row['thold_alert'] == 2 ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php print $row["thold_hi"];?></td>
				<td<?php echo ($row['thold_alert'] == 1 ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php print $row["thold_low"];?></td>
				<td<?php echo (($row['bl_enabled'] == 'on' && $row['bl_alert'] > 0) ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php print $row["bl_enabled"];?></td>
				<td<?php echo ($bgcolor ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php print $row["lastread"];?></td>
				<td<?php echo ($bgcolor ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php print $alertstat;?></td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Thresholds Found</em></td></tr>";
	}
	html_end_box(false);
}
